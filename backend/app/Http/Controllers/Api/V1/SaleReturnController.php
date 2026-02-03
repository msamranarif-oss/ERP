<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Sale;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SaleReturnController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = SaleReturn::with(['sale', 'items.product', 'returnedBy']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('return_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('sale', function ($sub) use ($request) {
                      $sub->where('sale_number', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('sale_id')) {
            $query->where('sale_id', $request->sale_id);
        }

        $returns = $query->orderBy('created_at', 'desc')
                         ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $returns
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'reason' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.return_reason' => 'required|string|max:255',
            'refund_amount' => 'required|numeric|min:0',
            'refund_method' => 'required|in:cash,bank_transfer,credit_account',
            'notes' => 'nullable|string|max:1000',
        ]);

        $sale = Sale::find($validated['sale_id']);
        if (!$sale || $sale->tenant_id !== auth()->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found or does not belong to your tenant.'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $saleReturn = SaleReturn::create([
                'return_number' => 'SR-' . date('Y') . '-' . str_pad(SaleReturn::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'sale_id' => $validated['sale_id'],
                'reason' => $validated['reason'],
                'refund_amount' => $validated['refund_amount'],
                'refund_method' => $validated['refund_method'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'completed',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                SaleReturnItem::create([
                    'sale_return_id' => $saleReturn->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'return_reason' => $item['return_reason'],
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $saleReturn->load(['sale', 'items.product', 'returnedBy']),
                'message' => 'Sale return created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sale return: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(SaleReturn $sale_return)
    {
        return response()->json([
            'success' => true,
            'data' => $sale_return->load(['sale', 'items.product', 'returnedBy'])
        ]);
    }

    public function update(Request $request, SaleReturn $sale_return)
    {
        $validated = $request->validate([
            'reason' => 'sometimes|required|string|max:255',
            'refund_amount' => 'sometimes|required|numeric|min:0',
            'refund_method' => 'sometimes|required|in:cash,bank_transfer,credit_account',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $sale_return->update($validated);

        return response()->json([
            'success' => true,
            'data' => $sale_return->load(['sale', 'items.product', 'returnedBy']),
            'message' => 'Sale return updated successfully.'
        ]);
    }

    public function destroy(SaleReturn $sale_return)
    {
        if ($sale_return->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete completed or processed sale return.'
            ], 422);
        }

        $sale_return->items()->delete();
        $sale_return->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sale return deleted successfully.'
        ]);
    }
}