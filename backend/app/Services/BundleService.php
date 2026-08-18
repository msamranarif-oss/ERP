<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\BundleItem;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class BundleService
{
    public function createBundle(int $productId, array $items, array $options): ProductBundle
    {
        return DB::transaction(function () use ($productId, $items, $options) {
            $product = Product::findOrFail($productId);

            $bundle = ProductBundle::updateOrCreate(
                ['product_id' => $productId],
                array_merge(['tenant_id' => $product->tenant_id], $options)
            );

            // Replace items
            BundleItem::where('product_bundle_id', $bundle->id)->delete();
            foreach ($items as $item) {
                BundleItem::create(array_merge($item, ['product_bundle_id' => $bundle->id]));
            }

            $product->update(['product_type' => 'bundle']);

            return $bundle->load('items.product', 'items.unit');
        });
    }

    /**
     * Fixed pricing: return bundle product's selling_price.
     * Dynamic pricing: sum of (component selling_price × quantity).
     */
    public function calculateBundlePrice(int $bundleId): float
    {
        $bundle = ProductBundle::with(['product', 'items.product'])->findOrFail($bundleId);

        if ($bundle->pricing_type === 'fixed') {
            $base = (float) $bundle->product->selling_price;

            // Apply discount
            if ($bundle->discount_percent) {
                $base -= $base * ($bundle->discount_percent / 100);
            } elseif ($bundle->discount_amount) {
                $base -= (float) $bundle->discount_amount;
            }

            return max(0, $base);
        }

        // Dynamic: sum components
        $total = $bundle->items->sum(fn($item) =>
            (float) $item->product->selling_price * (float) $item->quantity
        );

        if ($bundle->discount_percent) {
            $total -= $total * ($bundle->discount_percent / 100);
        } elseif ($bundle->discount_amount) {
            $total -= (float) $bundle->discount_amount;
        }

        return max(0, $total);
    }

    /**
     * Preview: price + per-component stock availability check.
     */
    public function previewBundle(int $bundleId, int $warehouseId, float $requestedQty = 1): array
    {
        $bundle = ProductBundle::with(['items.product', 'items.unit'])->findOrFail($bundleId);
        $price  = $this->calculateBundlePrice($bundleId);

        $components = $bundle->items->map(function ($item) use ($warehouseId, $requestedQty) {
            $needed = $item->quantity * $requestedQty;
            $stock  = StockLevel::where('product_id', $item->product_id)
                                ->where('warehouse_id', $warehouseId)
                                ->sum('quantity');

            return [
                'product'     => $item->product->name,
                'quantity'    => $needed,
                'stock'       => $stock,
                'is_available' => $stock >= $needed,
            ];
        });

        return [
            'bundle_price' => $price,
            'components'   => $components,
            'can_fulfill'  => $components->every(fn($c) => $c['is_available']),
        ];
    }

    /**
     * Deduct each bundle component's stock levels by qty × requestedQty.
     */
    public function deductBundleStock(int $bundleId, float $requestedQty, int $warehouseId): void
    {
        $bundle = ProductBundle::with('items')->findOrFail($bundleId);

        DB::transaction(function () use ($bundle, $requestedQty, $warehouseId) {
            foreach ($bundle->items as $item) {
                $toDeduct = $item->quantity * $requestedQty;

                $stock = StockLevel::where('product_id', $item->product_id)
                                   ->where('warehouse_id', $warehouseId)
                                   ->lockForUpdate()
                                   ->first();

                if (!$stock || $stock->quantity < $toDeduct) {
                    throw new \Exception("Insufficient stock for bundle component: {$item->product->name}");
                }

                $stock->decrement('quantity', $toDeduct);
            }
        });
    }
}
