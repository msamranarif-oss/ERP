<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Services\BundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBundleController extends Controller
{
    public function __construct(private BundleService $service) {}

    public function show(Product $product): JsonResponse
    {
        $this->authorizeTenant($product);
        $bundle = ProductBundle::where('product_id', $product->id)->with('items.product', 'items.unit')->firstOrFail();
        return response()->json(['success' => true, 'data' => $bundle]);
    }

    public function store(Request $request, Product $product): JsonResponse
    {
        $this->authorizeTenant($product);
        $data = $request->validate([
            'pricing_type'      => 'required|in:fixed,dynamic',
            'discount_amount'   => 'nullable|numeric|min:0',
            'discount_percent'  => 'nullable|numeric|min:0|max:100',
            'promo_valid_from'  => 'nullable|date',
            'promo_valid_to'    => 'nullable|date|after_or_equal:promo_valid_from',
            'is_active'         => 'boolean',
            'items'             => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_id'   => 'required|exists:units,id',
            'items.*.quantity'  => 'required|numeric|min:0.0001',
            'items.*.variant_id' => 'nullable|exists:product_variants,id',
            'items.*.is_optional' => 'boolean',
        ]);

        $bundle = $this->service->createBundle($product->id, $data['items'], collect($data)->except('items')->toArray());
        return response()->json(['success' => true, 'data' => $bundle, 'message' => 'Bundle created.'], 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorizeTenant($product);
        $data = $request->validate([
            'pricing_type'    => 'sometimes|in:fixed,dynamic',
            'discount_amount' => 'nullable|numeric|min:0',
            'discount_percent'=> 'nullable|numeric|min:0|max:100',
            'is_active'       => 'boolean',
            'items'           => 'sometimes|array|min:1',
        ]);

        $bundle = $this->service->createBundle($product->id, $data['items'] ?? [], collect($data)->except('items')->toArray());
        return response()->json(['success' => true, 'data' => $bundle]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorizeTenant($product);
        ProductBundle::where('product_id', $product->id)->delete();
        $product->update(['product_type' => 'simple']);
        return response()->json(['success' => true, 'message' => 'Bundle removed.']);
    }

    public function preview(Request $request, Product $product): JsonResponse
    {
        $this->authorizeTenant($product);
        $bundle = ProductBundle::where('product_id', $product->id)->firstOrFail();
        $warehouseId = $request->validate(['warehouse_id' => 'required|exists:warehouses,id'])['warehouse_id'];
        $qty         = (float) $request->get('quantity', 1);

        $preview = $this->service->previewBundle($bundle->id, $warehouseId, $qty);
        return response()->json(['success' => true, 'data' => $preview]);
    }

    private function authorizeTenant(Product $product): void
    {
        abort_if($product->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
