<?php

namespace App\Services;

use App\Services\Interfaces\ServiceInterface;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Services\CategoryService;
use App\Services\BrandService;
use App\Services\StockLevelService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductServiceRefactored extends BaseService implements ServiceInterface
{
    protected CategoryService $categoryService;
    protected BrandService $brandService;
    protected StockLevelService $stockLevelService;

    public function __construct(
        CategoryService $categoryService,
        BrandService $brandService,
        StockLevelService $stockLevelService
    ) {
        parent::__construct(new Product());
        $this->categoryService = $categoryService;
        $this->brandService = $brandService;
        $this->stockLevelService = $stockLevelService;
    }

    /**
     * Get products with advanced filtering and search
     */
    public function getProducts(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->with([
            'category',
            'brand',
            'baseUnit',
            'productUnits.unit',
            'variants',
            'stockLevels.warehouse'
        ])->where('is_active', true);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('sku', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('barcode', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (!empty($filters['is_sellable'])) {
            $query->where('is_sellable', $filters['is_sellable']);
        }

        if (!empty($filters['min_price']) || !empty($filters['max_price'])) {
            $query->whereBetween('selling_price', [
                $filters['min_price'] ?? 0,
                $filters['max_price'] ?? 999999999
            ]);
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($perPage);
    }

    /**
     * Create a product with related data
     */
    public function createProduct(array $data)
    {
        try {
            DB::beginTransaction();

            // Validate related entities
            if (!empty($data['category_id'])) {
                $this->categoryService->findById($data['category_id']);
            }

            if (!empty($data['brand_id'])) {
                $this->brandService->findById($data['brand_id']);
            }

            $product = $this->create($data);

            // Initialize stock levels if track_inventory is enabled
            if ($product->track_inventory && !empty($data['initial_stock'])) {
                $warehouseId = $data['warehouse_id'] ?? null;
                $tenantId = request()->user()->tenant_id ?? 1; // Default fallback
                $this->stockLevelService->initializeStock($product->id, $warehouseId, $tenantId, $data['initial_stock']);
            }

            DB::commit();

            return $product->load(['category', 'brand', 'baseUnit']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating product', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Update product with stock level management
     */
    public function updateProduct(int $id, array $data)
    {
        try {
            DB::beginTransaction();

            $product = $this->findById($id);

            // Handle stock adjustments
            if (isset($data['stock_adjustment']) && $product->track_inventory) {
                $warehouseId = $product->warehouse_id ?? null;
                $tenantId = request()->user()->tenant_id ?? 1; // Default fallback
                $this->stockLevelService->adjustStock($product->id, $warehouseId, $tenantId, $data['stock_adjustment']);
                unset($data['stock_adjustment']);
            }

            $updatedProduct = $this->update($id, $data);

            DB::commit();

            return $updatedProduct->load(['category', 'brand', 'baseUnit', 'stockLevels']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating product', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(int $threshold = 10)
    {
        return $this->model->with(['stockLevels.warehouse'])
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->whereHas('stockLevels', function ($query) use ($threshold) {
                $query->where('quantity', '<=', $threshold);
            })
            ->get();
    }

    /**
     * Get product sales statistics
     */
    public function getProductSalesStats(int $productId, array $dateRange = [])
    {
        $product = $this->findById($productId);
        
        $query = $product->saleItems();
        
        if (!empty($dateRange['start'])) {
            $query->whereHas('sale', function ($q) use ($dateRange) {
                $q->where('sale_date', '>=', $dateRange['start']);
            });
        }
        
        if (!empty($dateRange['end'])) {
            $query->whereHas('sale', function ($q) use ($dateRange) {
                $q->where('sale_date', '<=', $dateRange['end']);
            });
        }

        $stats = $query->selectRaw('
            SUM(quantity) as total_sold,
            SUM(total) as total_revenue,
            AVG(unit_price) as avg_price,
            COUNT(DISTINCT sale_id) as transaction_count
        ')->first();

        return [
            'product' => $product,
            'stats' => $stats,
            'date_range' => $dateRange
        ];
    }
}