<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::with(['category', 'baseUnit', 'stockLevels'])
            ->where('tenant_id', $request->user()->tenant_id)
            ->orderBy('name')
            ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\ProductResource::collection($products);
    }

    public function store(Request $request): \App\Http\Resources\ProductResource
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:products,sku,NULL,id,tenant_id,' . $request->user()->tenant_id,
            'barcode' => 'nullable|string|max:255|unique:products,barcode,NULL,id,tenant_id,' . $request->user()->tenant_id,
            'category_id' => 'nullable|exists:categories,id',
            'base_unit_id' => 'nullable|exists:units,id',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:2048',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'min_price' => 'nullable|numeric|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'reorder_quantity' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'track_inventory' => 'boolean',
            'has_variants' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'tax_type' => 'nullable|in:inclusive,exclusive,exempt',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'attributes' => 'nullable|array',
        ]);

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        }

        $product = Product::create($data);

        return new \App\Http\Resources\ProductResource($product->load('category', 'baseUnit', 'stockLevels'));
    }

    public function show(Product $product): \App\Http\Resources\ProductResource
    {
        return new \App\Http\Resources\ProductResource($product->load('category', 'baseUnit', 'productUnits.unit', 'variants', 'stockLevels.warehouse'));
    }

    public function update(Request $request, Product $product): \App\Http\Resources\ProductResource
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|nullable|string|max:255|unique:products,sku,' . $product->id . ',id,tenant_id,' . $request->user()->tenant_id,
            'barcode' => 'sometimes|nullable|string|max:255|unique:products,barcode,' . $product->id . ',id,tenant_id,' . $request->user()->tenant_id,
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'base_unit_id' => 'sometimes|nullable|exists:units,id',
            'description' => 'sometimes|nullable|string',
            'image' => 'sometimes|nullable|image|max:2048',
            'cost_price' => 'sometimes|nullable|numeric|min:0',
            'selling_price' => 'sometimes|nullable|numeric|min:0',
            'min_price' => 'sometimes|nullable|numeric|min:0',
            'reorder_level' => 'sometimes|nullable|integer|min:0',
            'reorder_quantity' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_sellable' => 'sometimes|boolean',
            'is_purchasable' => 'sometimes|boolean',
            'track_inventory' => 'sometimes|boolean',
            'has_variants' => 'sometimes|boolean',
            'allow_negative_stock' => 'sometimes|boolean',
            'tax_type' => 'sometimes|nullable|in:inclusive,exclusive,exempt',
            'tax_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'attributes' => 'sometimes|nullable|array',
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        }

        $product->update($data);

        return new \App\Http\Resources\ProductResource($product->load('category', 'baseUnit', 'productUnits.unit', 'variants', 'stockLevels.warehouse'));
    }

    public function destroy(Product $product): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Delete stock levels
            $product->stockLevels()->delete();
            
            // Delete variants
            $product->variants()->delete();
            
            // Delete product units
            $product->productUnits()->delete();
            
            // Delete the product
            $product->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stock(Product $product): JsonResponse
    {
        $stockLevels = StockLevel::where('product_id', $product->id)
            ->with('warehouse')
            ->get();

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\StockLevelResource::collection($stockLevels),
        ]);
    }

    public function storeVariant(Request $request, Product $product): \App\Http\Resources\ProductVariantResource
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:product_variants,sku,NULL,id,product_id,' . $product->id,
            'barcode' => 'nullable|string|max:255|unique:product_variants,barcode,NULL,id,product_id,' . $product->id,
            'attributes' => 'required|array',
            'cost_price' => 'nullable|numeric|min:0',
            'selling_price' => 'nullable|numeric|min:0',
            'image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        $data = $request->all();
        $data['product_id'] = $product->id;

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('product_variants', 'public');
            $data['image'] = $path;
        }

        $variant = ProductVariant::create($data);

        return new \App\Http\Resources\ProductVariantResource($variant);
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): \App\Http\Resources\ProductVariantResource
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|nullable|string|max:255|unique:product_variants,sku,' . $variant->id . ',id,product_id,' . $product->id,
            'barcode' => 'sometimes|nullable|string|max:255|unique:product_variants,barcode,' . $variant->id . ',id,product_id,' . $product->id,
            'attributes' => 'sometimes|required|array',
            'cost_price' => 'sometimes|nullable|numeric|min:0',
            'selling_price' => 'sometimes|nullable|numeric|min:0',
            'image' => 'sometimes|nullable|image|max:2048',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('product_variants', 'public');
            $data['image'] = $path;
        }

        $variant->update($data);

        return new \App\Http\Resources\ProductVariantResource($variant);
    }

    public function destroyVariant(Product $product, ProductVariant $variant): JsonResponse
    {
        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product variant deleted successfully',
        ]);
    }
}
