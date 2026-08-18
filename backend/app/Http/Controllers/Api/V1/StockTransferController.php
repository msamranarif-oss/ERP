<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\Product;
use App\Services\StockTransferService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    protected StockTransferService $stockTransferService;

    public function __construct(StockTransferService $stockTransferService)
    {
        $this->stockTransferService = $stockTransferService;
        $this->authorizeResource(StockTransfer::class, 'stock_transfer');
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
        if ($request->filled('from_warehouse_id')) {
            $filters['from_warehouse_id'] = $request->from_warehouse_id;
        }
        if ($request->filled('to_warehouse_id')) {
            $filters['to_warehouse_id'] = $request->to_warehouse_id;
        }
        
        $transfers = $this->stockTransferService->getAll($filters, $request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        try {
            $stockTransfer = $this->stockTransferService->createStockTransfer($validated);

            return response()->json([
                'success' => true,
                'data' => $stockTransfer,
                'message' => 'Stock transfer created successfully.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function show(StockTransfer $stock_transfer)
    {
        return response()->json([
            'success' => true,
            'data' => $stock_transfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy'])
        ]);
    }

    public function update(Request $request, StockTransfer $stock_transfer)
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'sometimes|required|exists:warehouses,id|different:to_warehouse_id',
            'to_warehouse_id' => 'sometimes|required|exists:warehouses,id|different:from_warehouse_id',
            'date' => 'sometimes|required|date',
            'notes' => 'sometimes|nullable|string|max:1000',
            'items' => 'sometimes|required|array|min:1',
            'items.*.product_id' => 'sometimes|required|exists:products,id',
            'items.*.quantity' => 'sometimes|required|integer|min:1',
            'items.*.unit_cost' => 'sometimes|nullable|numeric|min:0',
        ]);

        try {
            $updatedStockTransfer = $this->stockTransferService->updateStockTransfer($stock_transfer->id, $validated);

            return response()->json([
                'success' => true,
                'data' => $updatedStockTransfer,
                'message' => 'Stock transfer updated successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function destroy(StockTransfer $stock_transfer)
    {
        try {
            $this->stockTransferService->deleteStockTransfer($stock_transfer->id);

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function approve(StockTransfer $stock_transfer)
    {
        try {
            $approvedStockTransfer = $this->stockTransferService->approveStockTransfer($stock_transfer->id);

            return response()->json([
                'success' => true,
                'data' => $approvedStockTransfer,
                'message' => 'Stock transfer approved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    public function complete(StockTransfer $stock_transfer)
    {
        try {
            $completedStockTransfer = $this->stockTransferService->completeStockTransfer($stock_transfer->id);

            return response()->json([
                'success' => true,
                'data' => $completedStockTransfer,
                'message' => 'Stock transfer completed successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}