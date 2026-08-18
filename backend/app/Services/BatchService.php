<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BatchService
{
    public function createBatch(array $data): Batch
    {
        return DB::transaction(function () use ($data) {
            $batch = Batch::create($data);

            // Create/update stock level for this batch
            StockLevel::updateOrCreate(
                [
                    'tenant_id'    => $data['tenant_id'],
                    'product_id'   => $data['product_id'],
                    'warehouse_id' => $data['warehouse_id'] ?? null,
                    'batch_id'     => $batch->id,
                ],
                ['quantity' => $data['quantity_received']]
            );

            return $batch;
        });
    }

    /**
     * Auto-select the best batch for a sale using FEFO, FIFO, LIFO, or manual.
     */
    public function selectBatchForSale(int $productId, float $qty, string $method = 'fefo'): ?Batch
    {
        $query = Batch::where('product_id', $productId)
                      ->where('status', 'active')
                      ->where('quantity_remaining', '>=', $qty);

        return match ($method) {
            'fefo'  => $query->whereNotNull('expiry_date')->orderBy('expiry_date')->first()
                       ?? $query->orderBy('created_at')->first(),
            'fifo'  => $query->orderBy('created_at')->first(),
            'lifo'  => $query->orderByDesc('created_at')->first(),
            default => $query->orderBy('created_at')->first(),
        };
    }

    public function deductFromBatch(int $batchId, float $quantity): void
    {
        DB::transaction(function () use ($batchId, $quantity) {
            $batch = Batch::lockForUpdate()->findOrFail($batchId);

            if ($batch->quantity_remaining < $quantity) {
                throw new \Exception("Insufficient batch stock. Available: {$batch->quantity_remaining}, Requested: {$quantity}");
            }

            $remaining = $batch->quantity_remaining - $quantity;
            $batch->update([
                'quantity_remaining' => $remaining,
                'status'             => $remaining <= 0 ? 'depleted' : $batch->status,
            ]);
        });
    }

    public function transferBatch(int $batchId, int $toWarehouseId, float $qty): Batch
    {
        return DB::transaction(function () use ($batchId, $toWarehouseId, $qty) {
            $source = Batch::lockForUpdate()->findOrFail($batchId);

            if ($source->quantity_remaining < $qty) {
                throw new \Exception("Insufficient stock to transfer.");
            }

            $source->decrement('quantity_remaining', $qty);

            // Create a new batch record in the destination warehouse
            $newBatch = $source->replicate();
            $newBatch->warehouse_id        = $toWarehouseId;
            $newBatch->quantity_received   = $qty;
            $newBatch->quantity_remaining  = $qty;
            $newBatch->batch_number        = $source->batch_number . '-T' . now()->format('YmdHis');
            $newBatch->save();

            StockMovement::create([
                'tenant_id'    => $source->tenant_id,
                'product_id'   => $source->product_id,
                'warehouse_id' => $toWarehouseId,
                'batch_id'     => $newBatch->id,
                'type'         => 'transfer_in',
                'quantity'     => $qty,
                'unit_cost'    => $source->cost_price,
                'reference'    => 'BATCH-TRANSFER',
                'created_by'   => auth()->id(),
            ]);

            return $newBatch;
        });
    }

    public function recallBatch(int $batchId, string $reason): Batch
    {
        $batch = Batch::findOrFail($batchId);
        $batch->update(['status' => 'recalled', 'is_recalled' => true, 'notes' => $reason]);
        return $batch;
    }

    public function splitBatch(int $batchId, float $splitQty): Batch
    {
        return DB::transaction(function () use ($batchId, $splitQty) {
            $original = Batch::lockForUpdate()->findOrFail($batchId);

            if ($original->quantity_remaining < $splitQty) {
                throw new \Exception("Cannot split: split quantity exceeds remaining.");
            }

            $original->decrement('quantity_remaining', $splitQty);

            $split = $original->replicate();
            $split->batch_number       = $original->batch_number . '-S' . now()->format('YmdHis');
            $split->quantity_received  = $splitQty;
            $split->quantity_remaining = $splitQty;
            $split->save();

            return $split;
        });
    }

    public function mergeBatches(array $batchIds): Batch
    {
        return DB::transaction(function () use ($batchIds) {
            $batches = Batch::lockForUpdate()->whereIn('id', $batchIds)->get();

            if ($batches->count() < 2) {
                throw new \Exception("At least 2 batches are required to merge.");
            }

            $first = $batches->first();
            $totalQty  = $batches->sum('quantity_remaining');
            $avgCost   = $batches->avg('cost_price');

            // Update first batch to hold merged totals
            $first->update([
                'quantity_received'  => $first->quantity_received + $batches->skip(1)->sum('quantity_received'),
                'quantity_remaining' => $totalQty,
                'cost_price'         => $avgCost,
                'batch_number'       => $first->batch_number . '-M' . now()->format('YmdHis'),
            ]);

            // Mark others as depleted
            Batch::whereIn('id', $batches->skip(1)->pluck('id'))
                 ->update(['quantity_remaining' => 0, 'status' => 'depleted']);

            return $first->fresh();
        });
    }

    public function getExpiryAlerts(int $days = 30): Collection
    {
        return Batch::active()
                    ->expiringSoon($days)
                    ->with(['product', 'warehouse'])
                    ->get();
    }
}
