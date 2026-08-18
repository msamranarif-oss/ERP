<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Quotation::where('tenant_id', auth()->user()->tenant_id)
                          ->with(['customer', 'creator']);

        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->filled('customer_id')) $query->where('customer_id', $request->customer_id);

        return response()->json(['success' => true, 'data' => $query->orderByDesc('quotation_date')->paginate($request->per_page ?? 15)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'   => 'nullable|exists:customers,id',
            'quotation_date'=> 'required|date',
            'valid_until'   => 'nullable|date|after_or_equal:quotation_date',
            'notes'         => 'nullable|string',
            'items'         => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.unit_id'         => 'required|exists:units,id',
            'items.*.quantity'        => 'required|numeric|min:0.0001',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.discount_percent'=> 'nullable|numeric|min:0|max:100',
            'items.*.variant_id'      => 'nullable|exists:product_variants,id',
        ]);

        $quotation = DB::transaction(function () use ($data) {
            $subtotal = 0;
            foreach ($data['items'] as &$item) {
                $item['discount_percent'] = $item['discount_percent'] ?? 0;
                $item['total'] = round(
                    $item['quantity'] * $item['unit_price'] * (1 - $item['discount_percent'] / 100),
                    2
                );
                $subtotal += $item['total'];
            }

            $q = Quotation::create([
                'tenant_id'       => auth()->user()->tenant_id,
                'customer_id'     => $data['customer_id'] ?? null,
                'quotation_number'=> 'QUO-' . strtoupper(uniqid()),
                'quotation_date'  => $data['quotation_date'],
                'valid_until'     => $data['valid_until'] ?? null,
                'subtotal'        => $subtotal,
                'total_amount'    => $subtotal,
                'notes'           => $data['notes'] ?? null,
                'status'          => 'draft',
                'created_by'      => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                QuotationItem::create(array_merge($item, ['quotation_id' => $q->id]));
            }

            return $q->load('items.product', 'customer');
        });

        return response()->json(['success' => true, 'data' => $quotation, 'message' => 'Quotation created.'], 201);
    }

    public function show(Quotation $quotation): JsonResponse
    {
        $this->authorizeTenant($quotation);
        return response()->json(['success' => true, 'data' => $quotation->load(['items.product', 'items.unit', 'customer', 'creator', 'sale'])]);
    }

    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        $this->authorizeTenant($quotation);
        if (!in_array($quotation->status, ['draft', 'sent'])) {
            return response()->json(['success' => false, 'message' => 'Cannot edit an accepted/rejected quotation.'], 422);
        }
        $quotation->update($request->validate([
            'valid_until' => 'nullable|date',
            'notes'       => 'nullable|string',
            'status'      => 'sometimes|in:draft,sent,rejected',
        ]));
        return response()->json(['success' => true, 'data' => $quotation->fresh()]);
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $this->authorizeTenant($quotation);
        if ($quotation->status === 'accepted') {
            return response()->json(['success' => false, 'message' => 'Cannot delete an accepted quotation.'], 422);
        }
        $quotation->delete();
        return response()->json(['success' => true, 'message' => 'Quotation deleted.']);
    }

    /**
     * Convert quotation to a sale draft. Creates Sale + SaleItems from quotation items.
     */
    public function convertToSale(Quotation $quotation): JsonResponse
    {
        $this->authorizeTenant($quotation);

        if ($quotation->status === 'accepted') {
            return response()->json(['success' => false, 'message' => 'Already converted.'], 422);
        }

        $sale = DB::transaction(function () use ($quotation) {
            $sale = Sale::create([
                'tenant_id'   => $quotation->tenant_id,
                'customer_id' => $quotation->customer_id,
                'sale_date'   => now()->toDateString(),
                'subtotal'    => $quotation->subtotal,
                'total'       => $quotation->total_amount,
                'status'      => 'draft',
                'created_by'  => auth()->id(),
                'notes'       => 'Converted from quotation #' . $quotation->quotation_number,
            ]);

            foreach ($quotation->items as $item) {
                SaleItem::create([
                    'sale_id'          => $sale->id,
                    'product_id'       => $item->product_id,
                    'variant_id'       => $item->variant_id,
                    'unit_id'          => $item->unit_id,
                    'quantity'         => $item->quantity,
                    'unit_price'       => $item->unit_price,
                    'discount_percent' => $item->discount_percent,
                    'total'            => $item->total,
                ]);
            }

            $quotation->update(['status' => 'accepted', 'sale_id' => $sale->id]);
            return $sale;
        });

        return response()->json(['success' => true, 'data' => ['sale' => $sale, 'quotation' => $quotation->fresh()], 'message' => 'Quotation converted to sale draft.']);
    }

    /**
     * Mark quotation as sent to customer.
     */
    public function send(Quotation $quotation): JsonResponse
    {
        $this->authorizeTenant($quotation);

        if ($quotation->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft quotations can be sent.'], 422);
        }

        $quotation->update(['status' => 'sent']);

        return response()->json([
            'success' => true,
            'data'    => $quotation->fresh()->load(['items.product', 'customer']),
            'message' => 'Quotation marked as sent.',
        ]);
    }

    private function authorizeTenant(Quotation $quotation): void
    {
        abort_if($quotation->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
