<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'items.product', 'createdBy']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('po_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('supplier', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $orders = $query->orderBy('created_at', 'desc')
                       ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'date' => 'required|date',
            'expected_delivery_date' => 'required|date|after_or_equal:date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'po_number' => 'PO-' . date('Y') . '-' . str_pad(PurchaseOrder::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'date' => $validated['date'],
                'expected_delivery_date' => $validated['expected_delivery_date'],
                'notes' => $validated['notes'] ?? null,
                'discount_percent' => $validated['discount_percent'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'tax_rate' => $validated['tax_rate'] ?? 0,
                'status' => 'pending',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder->load(['supplier', 'items.product', 'createdBy']),
                'message' => 'Purchase order created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(PurchaseOrder $purchase_order)
    {
        return response()->json([
            'success' => true,
            'data' => $purchase_order->load(['supplier', 'items.product', 'createdBy'])
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchase_order)
    {
        if ($purchase_order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update purchase order that is not in pending status.'
            ], 422);
        }

        $validated = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'date' => 'sometimes|required|date',
            'expected_delivery_date' => 'sometimes|required|date|after_or_equal:date',
            'notes' => 'sometimes|nullable|string|max:1000',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'sometimes|required|exists:products,id',
            'items.*.quantity' => 'sometimes|required|integer|min:1',
            'items.*.unit_cost' => 'sometimes|required|numeric|min:0',
            'items.*.tax_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount_amount' => 'sometimes|nullable|numeric|min:0',
            'shipping_cost' => 'sometimes|nullable|numeric|min:0',
            'tax_rate' => 'sometimes|nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $purchase_order->update([
                'supplier_id' => $validated['supplier_id'] ?? $purchase_order->supplier_id,
                'date' => $validated['date'] ?? $purchase_order->date,
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? $purchase_order->expected_delivery_date,
                'notes' => $validated['notes'] ?? $purchase_order->notes,
                'discount_percent' => $validated['discount_percent'] ?? $purchase_order->discount_percent,
                'discount_amount' => $validated['discount_amount'] ?? $purchase_order->discount_amount,
                'shipping_cost' => $validated['shipping_cost'] ?? $purchase_order->shipping_cost,
                'tax_rate' => $validated['tax_rate'] ?? $purchase_order->tax_rate,
            ]);

            if (isset($validated['items'])) {
                // Delete existing items
                $purchase_order->items()->delete();

                // Add new items
                foreach ($validated['items'] as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchase_order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'tax_rate' => $item['tax_rate'] ?? 0,
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $purchase_order->load(['supplier', 'items.product', 'createdBy']),
                'message' => 'Purchase order updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        if ($purchase_order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete purchase order that is not in pending status.'
            ], 422);
        }

        $purchase_order->items()->delete();
        $purchase_order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Purchase order deleted successfully.'
        ]);
    }

    public function submit(PurchaseOrder $purchase_order)
    {
        if ($purchase_order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot submit purchase order that is not in pending status.'
            ], 422);
        }

        $purchase_order->update(['status' => 'submitted']);

        return response()->json([
            'success' => true,
            'data' => $purchase_order,
            'message' => 'Purchase order submitted successfully.'
        ]);
    }

    public function receive(PurchaseOrder $purchase_order, Request $request)
    {
        if ($purchase_order->status !== 'submitted') {
            return response()->json([
                'success' => false,
                'message' => 'Purchase order must be submitted before receiving.'
            ], 422);
        }

        $validated = $request->validate([
            'received_items' => 'required|array|min:1',
            'received_items.*.product_id' => 'required|exists:products,id',
            'received_items.*.received_quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Update received quantities
            foreach ($validated['received_items'] as $receivedItem) {
                $orderItem = $purchase_order->items()->where('product_id', $receivedItem['product_id'])->first();
                if (!$orderItem) {
                    throw new \Exception('Order item not found.');
                }

                if ($receivedItem['received_quantity'] > $orderItem->quantity) {
                    throw new \Exception('Received quantity cannot exceed ordered quantity.');
                }

                // Update stock levels
                $product = $orderItem->product;
                $product->increment('available_stock', $receivedItem['received_quantity']);

                // TODO: Create stock movement record when StockMovement model is available
                // StockMovement::create([ ... ]);
            }

            $purchase_order->update(['status' => 'received']);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $purchase_order,
                'message' => 'Purchase order received successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive purchase order: ' . $e->getMessage()
            ], 500);
        }
    }

    public function cancel(PurchaseOrder $purchase_order)
    {
        if ($purchase_order->status === 'received') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel purchase order that has already been received.'
            ], 422);
        }

        $purchase_order->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'data' => $purchase_order,
            'message' => 'Purchase order cancelled successfully.'
        ]);
    }
}