<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Exceptions\InventoryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new Product());
    }

    /**
     * Create a new product with validation
     */
    public function createProduct(array $data)
    {
        try {
            DB::beginTransaction();

            // Validate data before processing
            $this->validateProductData($data);

            // Check for duplicate SKU
            if (!empty($data['sku'])) {
                $exists = Product::where('tenant_id', auth()->user()->tenant_id)
                    ->where('sku', $data['sku'])
                    ->exists();
                
                if ($exists) {
                    throw InventoryException::duplicateSKU($data['sku']);
                }
            }

            // Check for duplicate barcode
            if (!empty($data['barcode'])) {
                $exists = Product::where('tenant_id', auth()->user()->tenant_id)
                    ->where('barcode', $data['barcode'])
                    ->exists();
                
                if ($exists) {
                    throw InventoryException::duplicateBarcode($data['barcode']);
                }
            }

            // Generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = $this->generateSKU($data);
            }

            $productData = $data;
            $productData['tenant_id'] = auth()->user()->tenant_id;

            if (isset($data['image']) && $data['image']) {
                $productData['image'] = $data['image']->store('products', 'public');
            }

            $product = Product::create($productData);

            DB::commit();

            return $product->load('category', 'baseUnit', 'stockLevels');
        } catch (InventoryException $e) {
            DB::rollback();
            throw $e;
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
     * Update a product
     */
    public function updateProduct(int $productId, array $data)
    {
        try {
            DB::beginTransaction();
            $product = Product::findOrFail($productId);

            // Validate data before processing
            $this->validateProductData($data);

            // Check for duplicate SKU (excluding current product)
            if (!empty($data['sku']) && $data['sku'] !== $product->sku) {
                $exists = Product::where('tenant_id', auth()->user()->tenant_id)
                    ->where('sku', $data['sku'])
                    ->where('id', '!=', $productId)
                    ->exists();
                
                if ($exists) {
                    throw InventoryException::duplicateSKU($data['sku']);
                }
            }

            // Check for duplicate barcode (excluding current product)
            if (!empty($data['barcode']) && $data['barcode'] !== $product->barcode) {
                $exists = Product::where('tenant_id', auth()->user()->tenant_id)
                    ->where('barcode', $data['barcode'])
                    ->where('id', '!=', $productId)
                    ->exists();
                
                if ($exists) {
                    throw InventoryException::duplicateBarcode($data['barcode']);
                }
            }

            if (isset($data['image']) && $data['image']) {
                $data['image'] = $data['image']->store('products', 'public');
            }

            $product->update($data);

            DB::commit();
            return $product->load('category', 'baseUnit', 'productUnits.unit', 'variants', 'stockLevels.warehouse');
        } catch (InventoryException $e) {
            DB::rollback();
            throw $e;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error updating product', [
                'error' => $e->getMessage(),
                'product_id' => $productId,
                'data' => $data
            ]);
            
            throw $e;
        }
    }

    /**
     * Delete a product with related records
     */
    public function deleteProduct(int $productId)
    {
        try {
            DB::beginTransaction();

            $product = Product::findOrFail($productId);

            // Delete stock levels
            $product->stockLevels()->delete();
            
            // Delete variants
            $product->variants()->delete();
            
            // Delete product units
            $product->productUnits()->delete();
            
            // Delete the product
            $product->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error deleting product', [
                'error' => $e->getMessage(),
                'product_id' => $productId
            ]);
            
            throw $e;
        }
    }

    /**
     * Get product stock levels
     */
    public function getProductStock(int $productId)
    {
        return StockLevel::where('product_id', $productId)
            ->with('warehouse')
            ->get();
    }

    /**
     * Create product variant
     */
    public function createVariant(int $productId, array $data)
    {
        try {
            DB::beginTransaction();
            $data['product_id'] = $productId;

            if (isset($data['image']) && $data['image']) {
                $data['image'] = $data['image']->store('product_variants', 'public');
            }

            $variant = ProductVariant::create($data);
            DB::commit();
            return $variant;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Update product variant
     */
    public function updateVariant(int $variantId, array $data)
    {
        try {
            DB::beginTransaction();
            $variant = ProductVariant::findOrFail($variantId);

            if (isset($data['image']) && $data['image']) {
                $data['image'] = $data['image']->store('product_variants', 'public');
            }

            $variant->update($data);
            DB::commit();
            return $variant;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Delete product variant
     */
    public function deleteVariant(int $variantId)
    {
        $variant = ProductVariant::findOrFail($variantId);
        return $variant->delete();
    }

    // ---------------------------------------------------------------
    // Phase 2 additions
    // ---------------------------------------------------------------

    private function generateSKU(array $data): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['name'] ?? 'PRD'), 0, 3));
        return $prefix . '-' . strtoupper(substr(uniqid(), -5));
    }

    public function cloneProduct(int $productId): Product
    {
        $original = Product::with(['productUnits'])->findOrFail($productId);

        return DB::transaction(function () use ($original) {
            $data = collect($original->toArray())
                ->except(['id','created_at','updated_at','deleted_at'])
                ->merge([
                    'name'      => $original->name . ' (Copy)',
                    'sku'       => $this->generateSKU(['name' => $original->name]),
                    'barcode'   => null,
                    'is_active' => false,
                ])->toArray();

            $clone = Product::create($data);

            foreach ($original->productUnits as $unit) {
                $unitData = collect($unit->toArray())
                    ->except(['id','created_at','updated_at','deleted_at'])
                    ->merge(['product_id' => $clone->id])
                    ->toArray();
                \App\Models\ProductUnit::create($unitData);
            }

            return $clone->load('category', 'baseUnit', 'productUnits.unit');
        });
    }

    public function generateVariantMatrix(int $productId, array $attributeGroupIds): array
    {
        $product = Product::findOrFail($productId);
        $groups  = \App\Models\AttributeGroup::whereIn('id', $attributeGroupIds)
            ->where('tenant_id', $product->tenant_id)
            ->with('values')
            ->get();

        if ($groups->isEmpty()) {
            throw new \Exception('No attribute groups found.');
        }

        $valueSets = $groups->map(fn($g) => $g->values->map(fn($v) => [
            'group' => $g->name, 'value' => $v->value,
        ])->toArray())->toArray();

        $combinations = [[]];
        foreach ($valueSets as $set) {
            $temp = [];
            foreach ($combinations as $existing) {
                foreach ($set as $item) {
                    $temp[] = array_merge($existing, [$item]);
                }
            }
            $combinations = $temp;
        }

        $created  = [];
        $tenantId = $product->tenant_id;

        foreach ($combinations as $combo) {
            $name       = implode(' / ', array_column($combo, 'value'));
            $attributes = array_combine(array_column($combo, 'group'), array_column($combo, 'value'));
            $created[]  = \App\Models\ProductVariant::firstOrCreate(
                ['tenant_id' => $tenantId, 'product_id' => $productId, 'name' => $name],
                ['attributes' => $attributes, 'selling_price' => $product->selling_price, 'cost_price' => $product->cost_price, 'is_active' => true]
            );
        }

        $product->update(['has_variants' => true]);
        return $created;
    }

    public function getPriceForCustomer(Product $product, ?string $customerType): float
    {
        if ($customerType) {
            $today = now()->toDateString();
            $rule  = \App\Models\ProductPriceRule::where('product_id', $product->id)
                ->where('customer_type', $customerType)
                ->where('is_active', true)
                ->where(fn($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $today))
                ->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today))
                ->orderBy('price')->first();

            if ($rule) return (float) $rule->price;
            if ($customerType === 'wholesale' && $product->wholesale_price) return (float) $product->wholesale_price;
        }
        return (float) $product->selling_price;
    }

    /**
     * Validate product data before creation/update
     */
    private function validateProductData(array $data): void
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0|max:999999999',
            'selling_price' => 'nullable|numeric|min:0|max:999999999',
            'min_price' => 'nullable|numeric|min:0|max:999999999',
            'wholesale_price' => 'nullable|numeric|min:0|max:999999999',
            'max_price' => 'nullable|numeric|min:0|max:999999999',
            'reorder_level' => 'nullable|integer|min:0|max:999999',
            'reorder_quantity' => 'nullable|integer|min:0|max:999999',
            'min_order_qty' => 'nullable|integer|min:1|max:999999',
            'max_order_qty' => 'nullable|integer|min:0|max:999999',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'weight' => 'nullable|numeric|min:0|max:999999',
            'length' => 'nullable|numeric|min:0|max:999999',
            'width' => 'nullable|numeric|min:0|max:999999',
            'height' => 'nullable|numeric|min:0|max:999999',
            'product_type' => 'nullable|in:simple,variant,bundle,service',
            'status' => 'nullable|in:active,draft,archived',
            'valuation_method' => 'nullable|in:avg_cost,fifo,lifo',
            'tax_type' => 'nullable|in:inclusive,exclusive,exempt',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }
    }
}