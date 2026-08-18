<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockAdjustmentService extends BaseService
{
    protected SequenceService $sequenceService;

    public function __construct(SequenceService $sequenceService)
    {
        parent::__construct(new StockAdjustment());
        $this->sequenceService = $sequenceService;
    }

    public function createAdjustment(array $data)
    {
        DB::beginTransaction();
        try {
            $adjustment = StockAdjustment::create([
                'tenant_id' => auth()->user()->tenant_id,
                'warehouse_id' => $data['warehouse_id'],
                'adjustment_number' => $this->sequenceService->generateReference('adjustment', 'ADJ'),
                'adjustment_date' => $data['adjustment_date'] ?? now(),
                'type' => $data['type'],
                'reason' => $data['reason'] ?? null,
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $item) {
                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'unit_id' => $item['unit_id'],
                    'quantity_before' => $item['quantity_before'],
                    'quantity_after' => $item['quantity_after'],
                    'difference' => $item['quantity_after'] - $item['quantity_before'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            DB::commit();
            return $adjustment->load('items');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Adjustment failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function approveAdjustment(int $id)
    {
        $adjustment = StockAdjustment::findOrFail($id);
        if ($adjustment->status !== 'pending') {
            throw new \Exception('Adjustment is not in pending status');
        }

        DB::beginTransaction();
        try {
            foreach ($adjustment->items as $item) {
                $stock = StockLevel::where('warehouse_id', $adjustment->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->increment('quantity', $item->difference);
                } else {
                    $stock = StockLevel::create([
                        'tenant_id' => $adjustment->tenant_id,
                        'warehouse_id' => $adjustment->warehouse_id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->difference,
                    ]);
                }

                // Record stock movement
                \App\Models\StockMovement::create([
                    'tenant_id' => $adjustment->tenant_id,
                    'warehouse_id' => $adjustment->warehouse_id,
                    'product_id' => $item->product_id,
                    'reference_type' => 'StockAdjustment',
                    'reference_id' => $adjustment->id,
                    'type' => $item->difference > 0 ? 'in' : 'out',
                    'quantity' => abs($item->difference),
                    'balance_after' => $stock->quantity,
                    'unit_cost' => $item->unit_cost,
                    'created_by' => auth()->id(),
                ]);
            }

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            DB::commit();
            return $adjustment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Stock Adjustment Approval failed', ['error' => $e->getMessage(), 'id' => $id]);
            throw $e;
        }
    }
}
