<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductService;
use App\Http\Requests\Inventory\StoreProductRequest;
use App\Http\Requests\Inventory\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ProductVariantResource;
use App\Http\Resources\StockLevelResource;
use App\Exceptions\InventoryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProductController extends ApiController
{
    protected ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $products = Product::with(['category', 'baseUnit', 'stockLevels'])
                ->when($request->search, fn ($q, $s) =>
                    $q->where(fn ($inner) =>
                        $inner->where('name', 'like', "%$s%")
                              ->orWhere('sku', 'like', "%$s%")
                              ->orWhere('barcode', 'like', "%$s%")
                    ))
                ->when($request->category_id,  fn ($q, $id)   => $q->where('category_id', $id))
                ->when($request->brand_id,  fn ($q, $id)   => $q->where('brand_id', $id))
                ->when($request->has('is_active'), fn ($q)     => $q->where('is_active', $request->boolean('is_active')))
                ->when($request->product_type, fn ($q, $type) => $q->where('product_type', $type))
                ->when($request->has('low_stock'), function ($q) {
                    $q->whereColumn('reorder_level', '>', DB::raw('(SELECT COALESCE(SUM(quantity), 0) FROM stock_levels WHERE stock_levels.product_id = products.id)'));
                })
                ->orderBy($request->get('sort_by', 'name'), $request->get('sort_dir', 'asc'))
                ->paginate($request->get('per_page', 15));

            return $this->successResponse(ProductResource::collection($products)->response()->getData(true));
        } catch (\Exception $e) {
            return $this->errorResponse('An error occurred while fetching products.', 500);
        }
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->createProduct($request->validated());

            return $this->successResponse(new ProductResource($product), 'Product created successfully', 201);
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 422, [], $e->getCode());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, ['errors' => $e->errors()], 'VALIDATION_ERROR');
        } catch (\Exception $e) {
            return $this->errorResponse('An internal error occurred while creating the product.', 500);
        }
    }

    public function show(Product $product): JsonResponse
    {
        return $this->successResponse(new ProductResource($product->load(['category', 'baseUnit', 'brand', 'productUnits.unit', 'variants', 'stockLevels.warehouse'])));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $data = $request->validated();

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image');
            }

            $updatedProduct = $this->productService->updateProduct($product->id, $data);

            return $this->successResponse(new ProductResource($updatedProduct), 'Product updated successfully');
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 422, [], $e->getCode());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, ['errors' => $e->errors()], 'VALIDATION_ERROR');
        } catch (\Exception $e) {
            return $this->errorResponse('An internal error occurred while updating the product.', 500);
        }
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            $this->productService->deleteProduct($product->id);

            return $this->successResponse(null, 'Product deleted successfully');
        } catch (InventoryException $e) {
            return $this->errorResponse($e->getMessage(), 422, [], $e->getCode());
        } catch (\Exception $e) {
            return $this->errorResponse('An internal error occurred. Please try again later.', 500);
        }
    }

    public function stock(Product $product): JsonResponse
    {
        $this->authorize('view', $product);
        $stockLevels = $this->productService->getProductStock($product->id);

        return $this->successResponse(StockLevelResource::collection($stockLevels));
    }

    public function storeVariant(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
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

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image');
        }

        $variant = $this->productService->createVariant($product->id, $data);

        return $this->successResponse(new ProductVariantResource($variant), 'Product variant created successfully', 201);
    }

    public function updateVariant(Request $request, Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);
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
            $data['image'] = $request->file('image');
        }

        $updatedVariant = $this->productService->updateVariant($variant->id, $data);

        return $this->successResponse(new ProductVariantResource($updatedVariant), 'Product variant updated successfully');
    }

    public function destroyVariant(Product $product, ProductVariant $variant): JsonResponse
    {
        $this->authorize('update', $product);
        $this->productService->deleteVariant($variant->id);

        return $this->successResponse(null, 'Product variant deleted successfully');
    }
}
