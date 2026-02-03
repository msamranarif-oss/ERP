<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HeldSale;
use App\Models\HeldSaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HeldSaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = HeldSale::with(['customer', 'items.product']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('hold_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $holds = $query->orderBy('created_at', 'desc')
                       ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $holds
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $heldSale = HeldSale::create([
                'hold_number' => 'HD-' . date('Y') . '-' . str_pad(HeldSale::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'customer_id' => $validated['customer_id'] ?? null,
                'sub_total' => $validated['total_amount'] - ($validated['tax_amount'] ?? 0) - ($validated['shipping_cost'] ?? 0),
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'held',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                HeldSaleItem::create([
                    'held_sale_id' => $heldSale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_percent' => $item['tax_percent'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $heldSale->load(['customer', 'items.product']),
                'message' => 'Held sale created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create held sale: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(HeldSale $held_sale)
    {
        return response()->json([
            'success' => true,
            'data' => $held_sale->load(['customer', 'items.product'])
        ]);
    }

    public function update(Request $request, HeldSale $held_sale)
    {
        $validated = $request->validate([
            'customer_id' => 'sometimes|nullable|exists:customers,id',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'sometimes|required|exists:products,id',
            'items.*.quantity' => 'sometimes|required|integer|min:1',
            'items.*.unit_price' => 'sometimes|required|numeric|min:0',
            'items.*.discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'sometimes|nullable|numeric|min:0',
            'discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'tax_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'tax_amount' => 'sometimes|nullable|numeric|min:0',
            'shipping_cost' => 'sometimes|nullable|numeric|min:0',
            'total_amount' => 'sometimes|required|numeric|min:0',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $heldSale = $held_sale;
            
            $heldSale->update([
                'customer_id' => $validated['customer_id'] ?? $heldSale->customer_id,
                'sub_total' => $validated['total_amount'] - ($validated['tax_amount'] ?? 0) - ($validated['shipping_cost'] ?? 0),
                'discount_amount' => $validated['discount_amount'] ?? $heldSale->discount_amount,
                'tax_amount' => $validated['tax_amount'] ?? $heldSale->tax_amount,
                'shipping_cost' => $validated['shipping_cost'] ?? $heldSale->shipping_cost,
                'total_amount' => $validated['total_amount'] ?? $heldSale->total_amount,
                'notes' => $validated['notes'] ?? $heldSale->notes,
            ]);

            if (isset($validated['items'])) {
                // Delete existing items
                $heldSale->items()->delete();

                // Add new items
                foreach ($validated['items'] as $item) {
                    HeldSaleItem::create([
                        'held_sale_id' => $heldSale->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount_percent' => $item['discount_percent'] ?? 0,
                        'discount_amount' => $item['discount_amount'] ?? 0,
                        'tax_percent' => $item['tax_percent'] ?? 0,
                        'tax_amount' => $item['tax_amount'] ?? 0,
                        'subtotal' => $item['quantity'] * $item['unit_price'],
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $heldSale->load(['customer', 'items.product']),
                'message' => 'Held sale updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update held sale: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(HeldSale $held_sale)
    {
        $held_sale->items()->delete();
        $held_sale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Held sale deleted successfully.'
        ]);
    }

    public function retrieve(HeldSale $held_sale)
    {
        // This would convert the held sale to a regular sale
        // For now, we'll just return the held sale data
        // The actual conversion would happen in the POS terminal
        
        return response()->json([
            'success' => true,
            'data' => $held_sale->load(['customer', 'items.product']),
            'message' => 'Held sale retrieved successfully.'
        ]);
    }
}