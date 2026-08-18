<?php

namespace App\Services;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockTransferService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new StockTransfer());
    }

    /**
     * Get all stock transfers with filters
     */
    public function getAll(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->with(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('transfer_number', 'like', '%' . $filters['search'] . '%')
                  ->orWhereHas('fromWarehouse', function ($sub) use ($filters) {
                      $sub->where('name', 'like', '%' . $filters['search'] . '%');
                  })
                  ->orWhereHas('toWarehouse', function ($sub) use ($filters) {
                      $sub->where('name', 'like', '%' . $filters['search'] . '%');
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_warehouse_id'])) {
            $query->where('from_warehouse_id', $filters['from_warehouse_id']);
        }

        if (!empty($filters['to_warehouse_id'])) {
            $query->where('to_warehouse_id', $filters['to_warehouse_id']);
        }

        return $query->orderBy('created_at', 'desc')
                     ->paginate($perPage);
    }

    /**
     * Create a new stock transfer
     */
    public function createStockTransfer(array $data)
    {
        // Validate that from and to warehouses belong to the same tenant
        $fromWarehouse = Warehouse::findOrFail($data['from_warehouse_id']);
        $toWarehouse = Warehouse::findOrFail($data['to_warehouse_id']);
        
        if ($fromWarehouse->tenant_id !== auth()->user()->tenant_id || 
            $toWarehouse->tenant_id !== auth()->user()->tenant_id) {
            throw new \Exception('Invalid warehouse selection.');
        }

        DB::beginTransaction();
        try {
            $stockTransfer = StockTransfer::create([
                'transfer_number' => 'ST-' . date('Y') . '-' . str_pad(StockTransfer::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id' => $data['to_warehouse_id'],
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                $stockTransfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            DB::commit();

            return $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating stock transfer', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Update a stock transfer
     */
    public function updateStockTransfer(int $stockTransferId, array $data)
    {
        $stockTransfer = StockTransfer::findOrFail($stockTransferId);

        if ($stockTransfer->status !== 'pending') {
            throw new \Exception('Cannot update stock transfer that is not in pending status.');
        }

        DB::beginTransaction();
        try {
            if (isset($data['from_warehouse_id']) || isset($data['to_warehouse_id'])) {
                $stockTransfer->update([
                    'from_warehouse_id' => $data['from_warehouse_id'] ?? $stockTransfer->from_warehouse_id,
                    'to_warehouse_id' => $data['to_warehouse_id'] ?? $stockTransfer->to_warehouse_id,
                ]);
            }

            if (isset($data['date']) || isset($data['notes'])) {
                $stockTransfer->update([
                    'date' => $data['date'] ?? $stockTransfer->date,
                    'notes' => $data['notes'] ?? $stockTransfer->notes,
                ]);
            }

            if (isset($data['items'])) {
                // Delete existing items
                $stockTransfer->items()->delete();

                // Add new items
                foreach ($data['items'] as $item) {
                    $stockTransfer->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'] ?? 0,
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            DB::commit();

            return $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'items.product', 'createdBy']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating stock transfer', [
                'error' => $e->getMessage(),
                'stock_transfer_id' => $stockTransferId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a stock transfer
     */
    public function deleteStockTransfer(int $stockTransferId)
    {
        $stockTransfer = StockTransfer::findOrFail($stockTransferId);

        if ($stockTransfer->status !== 'pending') {
            throw new \Exception('Cannot delete stock transfer that is not in pending status.');
        }

        $stockTransfer->items()->delete();
        $stockTransfer->delete();

        return true;
    }

    /**
     * Approve a stock transfer
     */
    public function approveStockTransfer(int $stockTransferId)
    {
        $stockTransfer = StockTransfer::findOrFail($stockTransferId);

        if ($stockTransfer->status !== 'pending') {
            throw new \Exception('Cannot approve stock transfer that is not in pending status.');
        }

        $stockTransfer->update(['status' => 'approved']);

        return $stockTransfer;
    }

    /**
     * Complete a stock transfer
     */
    public function completeStockTransfer(int $stockTransferId)
    {
        $stockTransfer = StockTransfer::findOrFail($stockTransferId);

        if ($stockTransfer->status !== 'approved') {
            throw new \Exception('Stock transfer must be approved before completing.');
        }

        DB::beginTransaction();
        try {
            // Process the stock transfer by updating stock levels
            foreach ($stockTransfer->items as $item) {
                // 1. Reduce stock from from_warehouse
                $fromStock = \App\Models\StockLevel::where('warehouse_id', $stockTransfer->from_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$fromStock || $fromStock->quantity < $item->quantity) {
                    throw new \Exception("Insufficient stock for product ID {$item->product_id} in source warehouse.");
                }

                $fromStock->decrement('quantity', $item->quantity);

                // Record 'out' movement
                \App\Models\StockMovement::create([
                    'tenant_id' => $stockTransfer->tenant_id,
                    'warehouse_id' => $stockTransfer->from_warehouse_id,
                    'product_id' => $item->product_id,
                    'reference_type' => 'StockTransfer',
                    'reference_id' => $stockTransfer->id,
                    'type' => 'out',
                    'quantity' => $item['quantity'],
                    'balance_after' => $fromStock->quantity,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'created_by' => auth()->id(),
                ]);

                // 2. Add stock to to_warehouse
                $toStock = \App\Models\StockLevel::firstOrCreate(
                    [
                        'warehouse_id' => $stockTransfer->to_warehouse_id,
                        'product_id' => $item->product_id,
                        'tenant_id' => $stockTransfer->tenant_id
                    ],
                    ['quantity' => 0]
                );
                
                $toStock->increment('quantity', $item->quantity);

                // Record 'in' movement
                 \App\Models\StockMovement::create([
                    'tenant_id' => $stockTransfer->tenant_id,
                    'warehouse_id' => $stockTransfer->to_warehouse_id,
                    'product_id' => $item->product_id,
                    'reference_type' => 'StockTransfer',
                    'reference_id' => $stockTransfer->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'balance_after' => $toStock->quantity,
                    'unit_cost' => $item->unit_cost ?? 0,
                    'created_by' => auth()->id(),
                ]);
            }

            $stockTransfer->update(['status' => 'completed', 'completed_at' => now()]);

            DB::commit();

            return $stockTransfer;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error completing stock transfer', [
                'error' => $e->getMessage(),
                'stock_transfer_id' => $stockTransferId
            ]);
            
            throw $e;
        }
    }
}