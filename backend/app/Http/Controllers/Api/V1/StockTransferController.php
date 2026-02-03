<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('transfer_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('fromWarehouse', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  })
                  ->orWhereHas('toWarehouse', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->from_warehouse_id);
        }

        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->to_warehouse_id);
        }

        $transfers = $query->orderBy('created_at', 'desc')
                          ->paginate($request->per_page ?? 15);

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

        // Validate that from and to warehouses belong to the same tenant
        $fromWarehouse = Warehouse::findOrFail($validated['from_warehouse_id']);
        $toWarehouse = Warehouse::findOrFail($validated['to_warehouse_id']);
        
        if ($fromWarehouse->tenant_id !== auth()->user()->tenant_id || 
            $toWarehouse->tenant_id !== auth()->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid warehouse selection.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $stockTransfer = StockTransfer::create([
                'transfer_number' => 'ST-' . date('Y') . '-' . str_pad(StockTransfer::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id' => $validated['to_warehouse_id'],
                'date' => $validated['date'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $stockTransfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy']),
                'message' => 'Stock transfer created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock transfer: ' . $e->getMessage()
            ], 500);
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
        if ($stock_transfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update stock transfer that is not in pending status.'
            ], 422);
        }

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

        DB::beginTransaction();
        try {
            $stockTransfer = $stock_transfer;
            
            if (isset($validated['from_warehouse_id']) || isset($validated['to_warehouse_id'])) {
                $stockTransfer->update([
                    'from_warehouse_id' => $validated['from_warehouse_id'] ?? $stockTransfer->from_warehouse_id,
                    'to_warehouse_id' => $validated['to_warehouse_id'] ?? $stockTransfer->to_warehouse_id,
                ]);
            }

            if (isset($validated['date']) || isset($validated['notes'])) {
                $stockTransfer->update([
                    'date' => $validated['date'] ?? $stockTransfer->date,
                    'notes' => $validated['notes'] ?? $stockTransfer->notes,
                ]);
            }

            if (isset($validated['items'])) {
                // Delete existing items
                $stockTransfer->items()->delete();

                // Add new items
                foreach ($validated['items'] as $item) {
                    $stockTransfer->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'] ?? 0,
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy']),
                'message' => 'Stock transfer updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stock transfer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(StockTransfer $stock_transfer)
    {
        if ($stock_transfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete stock transfer that is not in pending status.'
            ], 422);
        }

        $stock_transfer->items()->delete();
        $stock_transfer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Stock transfer deleted successfully.'
        ]);
    }

    public function approve(StockTransfer $stock_transfer)
    {
        if ($stock_transfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot approve stock transfer that is not in pending status.'
            ], 422);
        }

        $stock_transfer->update(['status' => 'approved']);

        return response()->json([
            'success' => true,
            'data' => $stock_transfer,
            'message' => 'Stock transfer approved successfully.'
        ]);
    }

    public function complete(StockTransfer $stock_transfer)
    {
        if ($stock_transfer->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Stock transfer must be approved before completing.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Process the stock transfer by updating stock levels
            foreach ($stock_transfer->items as $item) {
                // Reduce stock from from_warehouse
                // Add stock to to_warehouse
                // Create stock movement records
            }

            $stock_transfer->update(['status' => 'completed', 'completed_at' => now()]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $stock_transfer,
                'message' => 'Stock transfer completed successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete stock transfer: ' . $e->getMessage()
            ], 500);
        }
    }
}