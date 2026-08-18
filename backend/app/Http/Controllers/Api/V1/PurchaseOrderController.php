<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
        $this->authorizeResource(PurchaseOrder::class, 'purchase_order');
    }
   

    public function index(Request $request)
    {
        $filters = [];
        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }
        if ($request->filled('status')) {
            $filters['status'] = $request->status;
        }
        if ($request->filled('supplier_id')) {
            $filters['supplier_id'] = $request->supplier_id;
        }
        
        $orders = $this->purchaseOrderService->getPurchaseOrdersWithFilters($filters, $request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
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

        try {
            $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder($validated);

            return response()->json([
                'success' => true,
                'data' => $purchaseOrder,
                'message' => 'Purchase order created successfully.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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
        $validated = $request->validate([
            'supplier_id' => 'sometimes|required|exists:suppliers,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
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

        try {
            $updatedPurchaseOrder = $this->purchaseOrderService->updatePurchaseOrder($purchase_order->id, $validated);

            return response()->json([
                'success' => true,
                'data' => $updatedPurchaseOrder,
                'message' => 'Purchase order updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(PurchaseOrder $purchase_order)
    {
        try {
            $this->purchaseOrderService->deletePurchaseOrder($purchase_order->id);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function submit(PurchaseOrder $purchase_order)
    {
        try {
            $submittedPurchaseOrder = $this->purchaseOrderService->submitPurchaseOrder($purchase_order->id);

            return response()->json([
                'success' => true,
                'data' => $submittedPurchaseOrder,
                'message' => 'Purchase order submitted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function receive(PurchaseOrder $purchase_order, Request $request)
    {
        $validated = $request->validate([
            'received_items' => 'required|array|min:1',
            'received_items.*.product_id' => 'required|exists:products,id',
            'received_items.*.received_quantity' => 'required|integer|min:1',
        ]);

        try {
            $receivedPurchaseOrder = $this->purchaseOrderService->receivePurchaseOrder($purchase_order->id, $validated);

            return response()->json([
                'success' => true,
                'data' => $receivedPurchaseOrder,
                'message' => 'Purchase order received successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(PurchaseOrder $purchase_order)
    {
        try {
            $cancelledPurchaseOrder = $this->purchaseOrderService->cancelPurchaseOrder($purchase_order->id);

            return response()->json([
                'success' => true,
                'data' => $cancelledPurchaseOrder,
                'message' => 'Purchase order cancelled successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
}