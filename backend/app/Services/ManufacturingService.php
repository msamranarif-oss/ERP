<?php

namespace App\Services;

use App\Models\ManufacturingOrder;
use App\Models\ManufacturingOrderItem;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManufacturingService
{
    protected JournalAutoPostService $journalService;

    public function __construct(JournalAutoPostService $journalService)
    {
        $this->journalService = $journalService;
    }

    /**
     * Create a Manufacturing Order from a BOM (Bundle).
     */
    public function createFromBOM(int $productId, float $quantity, int $warehouseId, int $branchId): ManufacturingOrder
    {
        $product = Product::findOrFail($productId);
        $bundle  = ProductBundle::with('items')->where('product_id', $productId)->first();

        if (! $bundle) {
            throw new \Exception('No BOM (Bundle) found for this product.');
        }

        return DB::transaction(function () use ($product, $quantity, $warehouseId, $branchId, $bundle) {
            $mo = ManufacturingOrder::create([
                'tenant_id'        => $product->tenant_id,
                'branch_id'        => $branchId,
                'warehouse_id'     => $warehouseId,
                'product_id'       => $product->id,
                'order_number'     => 'MO-'.strtoupper(uniqid()),
                'quantity_planned' => $quantity,
                'status'           => 'planned',
                'created_by'       => auth()->id(),
            ]);

            foreach ($bundle->items as $item) {
                ManufacturingOrderItem::create([
                    'manufacturing_order_id' => $mo->id,
                    'product_id'             => $item->product_id,
                    'variant_id'             => $item->variant_id,
                    'quantity_planned'       => $item->quantity * $quantity,
                    'unit_cost'              => $item->product->cost_price ?? 0,
                ]);
            }

            return $mo->load('items');
        });
    }

    /**
     * Start production: Deduct raw materials (Work-in-Progress).
     */
    public function startProduction(ManufacturingOrder $mo): void
    {
        if ($mo->status !== 'planned') {
            throw new \Exception("Order must be in 'planned' status to start.");
        }

        DB::transaction(function () use ($mo) {
            foreach ($mo->items as $item) {
                $this->deductStock(
                    $item->product_id,
                    $item->variant_id,
                    $mo->warehouse_id,
                    $item->quantity_planned,
                    "Manufacturing Consumption: {$mo->order_number}"
                );
                $item->update(['quantity_consumed' => $item->quantity_planned]);
            }

            $mo->update(['status' => 'in_progress', 'start_date' => now()]);
        });

        // Task 8: Post WIP journal entry (non-fatal — production must not be blocked)
        try {
            // Reload so start_date is fresh
            $mo->refresh();
            $this->journalService->postManufacturingStart($mo);
        } catch (\Exception $e) {
            Log::warning('postManufacturingStart failed', [
                'mo_id' => $mo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Complete production: Add finished goods to stock.
     */
    public function completeProduction(ManufacturingOrder $mo, float $actualProduced = null): void
    {
        if ($mo->status !== 'in_progress') {
            throw new \Exception("Order must be 'in_progress' to complete.");
        }

        $produced = $actualProduced ?? $mo->quantity_planned;

        DB::transaction(function () use ($mo, $produced) {
            $this->addStock(
                $mo->product_id,
                null,
                $mo->warehouse_id,
                $mo->tenant_id,
                $produced,
                "Manufacturing Production: {$mo->order_number}"
            );

            $mo->update([
                'status'            => 'completed',
                'quantity_produced' => $produced,
                'end_date'          => now(),
            ]);
        });

        // Task 8: Post finished-goods journal entry (non-fatal)
        try {
            $mo->refresh();
            $this->journalService->postManufacturingComplete($mo, $produced);
        } catch (\Exception $e) {
            Log::warning('postManufacturingComplete failed', [
                'mo_id' => $mo->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deductStock($productId, $variantId, $warehouseId, $qty, $reason): void
    {
        $stock = StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('variant_id', $variantId)
            ->first();

        if (! $stock || $stock->quantity < $qty) {
            $product = Product::find($productId);
            throw new \Exception('Insufficient stock for raw material: '.($product->name ?? $productId));
        }

        $stock->decrement('quantity', $qty);

        StockMovement::create([
            'tenant_id'    => $stock->tenant_id,
            'warehouse_id' => $warehouseId,
            'product_id'   => $productId,
            'variant_id'   => $variantId,
            'type'         => 'out',
            'quantity'     => $qty,
            'reference'    => $reason,
        ]);
    }

    private function addStock($productId, $variantId, $warehouseId, $tenantId, $qty, $reason): void
    {
        $stock = StockLevel::firstOrCreate([
            'tenant_id'    => $tenantId,
            'warehouse_id' => $warehouseId,
            'product_id'   => $productId,
            'variant_id'   => $variantId,
        ]);

        $stock->increment('quantity', $qty);

        StockMovement::create([
            'tenant_id'    => $tenantId,
            'warehouse_id' => $warehouseId,
            'product_id'   => $productId,
            'variant_id'   => $variantId,
            'type'         => 'in',
            'quantity'     => $qty,
            'reference'    => $reason,
        ]);
    }
}
