<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Sales\CreateSaleRequest;
use App\Services\POSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class POSController extends ApiController
{
    protected POSService $posService;
    protected \App\Services\LoyaltyService $loyaltyService;

    public function __construct(POSService $posService, \App\Services\LoyaltyService $loyaltyService)
    {
        $this->posService = $posService;
        $this->loyaltyService = $loyaltyService;
    }

    public function products(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id', 'ids']);
        $products = $this->posService->getProducts($filters, $request->per_page ?? 15);

        return $this->successResponse(\App\Http\Resources\ProductResource::collection($products)->response()->getData(true));
    }

    public function findByBarcode($barcode): JsonResponse
    {
        $product = $this->posService->findByBarcode($barcode);

        if (!$product) {
            return $this->notFoundResponse('Product not found.');
        }

        return $this->successResponse(new \App\Http\Resources\ProductResource($product));
    }

    public function createSale(CreateSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->posService->createSale($request->validated());

            return $this->successResponse(new \App\Http\Resources\SaleResource($sale), 'Sale created successfully.', 201);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('POS Sale Creation Error: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function calculateLoyaltyPoints(Request $request, \App\Models\Customer $customer): JsonResponse
    {
        $points = (float)$customer->loyalty_points;
        $value = $this->loyaltyService->calculateRedemptionValue($points, auth()->user()->tenant_id);

        return $this->successResponse([
            'points' => $points,
            'monetary_value' => $value,
            'tenant_id' => auth()->user()->tenant_id
        ]);
    }
}