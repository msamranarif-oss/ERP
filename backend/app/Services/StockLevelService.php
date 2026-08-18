<?php

namespace App\Services;

use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\Interfaces\ServiceInterface;
use Illuminate\Support\Facades\DB;

class StockLevelService extends BaseService implements ServiceInterface
{
    public function __construct()
    {
        parent::__construct(new StockLevel());
    }

    /**
     * Get stock level for a product in a warehouse
     */
    public function getStockLevel($productId, $warehouseId, $tenantId)
    {
        return StockLevel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Initialize stock level for a product
     */
    public function initializeStock($productId, $warehouseId, $tenantId, $initialQuantity, $unitCost = 0)
    {
        $stockLevel = $this->getStockLevel($productId, $warehouseId, $tenantId);

        if ($stockLevel) {
            // Update existing stock level
            $stockLevel->update([
                'quantity' => $initialQuantity,
                'unit_cost' => $unitCost
            ]);
        } else {
            // Create new stock level
            $stockLevel = StockLevel::create([
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => $initialQuantity,
                'unit_cost' => $unitCost
            ]);
        }

        // Record stock movement
        $this->recordMovement($productId, $warehouseId, $tenantId, 'in', $initialQuantity, 0, $initialQuantity, 'Initial stock');

        return $stockLevel;
    }

    /**
     * Adjust stock level (increase or decrease)
     */
    public function adjustStock($productId, $warehouseId, $tenantId, $quantity, $type = 'adjustment', $notes = null)
    {
        $stockLevel = $this->getStockLevel($productId, $warehouseId, $tenantId);

        if (!$stockLevel) {
            throw new \InvalidArgumentException('Stock level not found for this product and warehouse');
        }

        $oldQuantity = $stockLevel->quantity;
        $newQuantity = $oldQuantity + $quantity;

        if ($newQuantity < 0) {
            throw new \InvalidArgumentException('Cannot reduce stock below zero');
        }

        $stockLevel->update(['quantity' => $newQuantity]);

        // Record stock movement
        $movementType = $quantity > 0 ? 'in' : 'out';
        $this->recordMovement($productId, $warehouseId, $tenantId, $movementType, abs($quantity), $oldQuantity, $newQuantity, $type, $notes);

        return $stockLevel;
    }

    /**
     * Record stock movement
     */
    public function recordMovement($productId, $warehouseId, $tenantId, $type, $quantity, $quantityBefore, $quantityAfter, $referenceType = null, $notes = null, $referenceId = null)
    {
        return StockMovement::create([
            'tenant_id' => $tenantId,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes
        ]);
    }

    /**
     * Get stock levels with product and warehouse information
     */
    public function getStockLevelsWithDetails($warehouseId = null, $tenantId = null)
    {
        $query = StockLevel::with(['product', 'warehouse']);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->orderBy('product_id')->get();
    }

    /**
     * Get low stock products
     */
    public function getLowStock($warehouseId = null, $tenantId = null, $threshold = 10)
    {
        $query = StockLevel::with(['product', 'warehouse'])
            ->where('quantity', '<=', $threshold);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->orderBy('quantity')->get();
    }

    /**
     * Get out of stock products
     */
    public function getOutOfStock($warehouseId = null, $tenantId = null)
    {
        $query = StockLevel::with(['product', 'warehouse'])
            ->where('quantity', 0);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get();
    }

    /**
     * Transfer stock between warehouses
     */
    public function transferStock($productId, $fromWarehouseId, $toWarehouseId, $tenantId, $quantity, $notes = null)
    {
        if ($fromWarehouseId === $toWarehouseId) {
            throw new \InvalidArgumentException('Cannot transfer stock to the same warehouse');
        }

        // Reduce stock from source warehouse
        $this->adjustStock($productId, $fromWarehouseId, $tenantId, -$quantity, 'transfer_out', $notes);

        // Add stock to destination warehouse
        $this->adjustStock($productId, $toWarehouseId, $tenantId, $quantity, 'transfer_in', $notes);

        return true;
    }

    /**
     * Get stock movement history
     */
    public function getMovementHistory($productId = null, $warehouseId = null, $tenantId = null, $limit = 50)
    {
        $query = StockMovement::with(['product', 'warehouse']);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Get stock valuation
     */
    public function getStockValuation($warehouseId = null, $tenantId = null)
    {
        $query = StockLevel::join('products', 'stock_levels.product_id', '=', 'products.id')
            ->select(
                'stock_levels.*',
                'products.name as product_name',
                'products.sku',
                DB::raw('stock_levels.quantity * stock_levels.unit_cost as total_value')
            );

        if ($warehouseId) {
            $query->where('stock_levels.warehouse_id', $warehouseId);
        }

        if ($tenantId) {
            $query->where('stock_levels.tenant_id', $tenantId);
        }

        $stockLevels = $query->get();

        $totalValuation = $stockLevels->sum('total_value');

        return [
            'stock_levels' => $stockLevels,
            'total_valuation' => $totalValuation
        ];
    }
}