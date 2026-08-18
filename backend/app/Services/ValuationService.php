<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class ValuationService
{
    /**
     * Return the weighted average cost for selling `$quantity` units
     * of a product from a warehouse using the given method.
     */
    public function getCostForSale(
        int    $productId,
        int    $warehouseId,
        float  $quantity,
        string $method = 'avg_cost'
    ): float {
        return match ($method) {
            'fifo'     => $this->fifo($productId, $warehouseId, $quantity),
            'lifo'     => $this->lifo($productId, $warehouseId, $quantity),
            'fefo'     => $this->fefo($productId, $warehouseId, $quantity),
            'avg_cost' => $this->avgCost($productId, $warehouseId),
            default    => $this->avgCost($productId, $warehouseId),
        };
    }

    // ──────────────────────────────────────────────────────────────────

    private function fifo(int $productId, int $warehouseId, float $qty): float
    {
        return $this->weightedCostFromBatches($productId, $warehouseId, $qty, 'created_at', 'asc');
    }

    private function lifo(int $productId, int $warehouseId, float $qty): float
    {
        return $this->weightedCostFromBatches($productId, $warehouseId, $qty, 'created_at', 'desc');
    }

    private function fefo(int $productId, int $warehouseId, float $qty): float
    {
        return $this->weightedCostFromBatches($productId, $warehouseId, $qty, 'expiry_date', 'asc');
    }

    private function avgCost(int $productId, int $warehouseId): float
    {
        $level = StockLevel::where('product_id', $productId)
                           ->where('warehouse_id', $warehouseId)
                           ->first();

        return $level ? (float) ($level->avg_cost ?? $level->unit_cost ?? 0) : 0.0;
    }

    /**
     * Walk through batches in given order and compute a weighted average cost
     * for the requested quantity (like FIFO/LIFO ledger depletion).
     */
    private function weightedCostFromBatches(
        int    $productId,
        int    $warehouseId,
        float  $qty,
        string $orderColumn,
        string $direction
    ): float {
        $batches = Batch::where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('status', 'active')
                        ->where('quantity_remaining', '>', 0)
                        ->orderBy($orderColumn, $direction)
                        ->get(['quantity_remaining', 'cost_price']);

        $remaining    = $qty;
        $totalCost    = 0.0;
        $totalConsumed = 0.0;

        foreach ($batches as $batch) {
            if ($remaining <= 0) break;

            $take      = min((float) $batch->quantity_remaining, $remaining);
            $totalCost    += $take * (float) $batch->cost_price;
            $totalConsumed += $take;
            $remaining    -= $take;
        }

        return $totalConsumed > 0 ? round($totalCost / $totalConsumed, 4) : 0.0;
    }
}
