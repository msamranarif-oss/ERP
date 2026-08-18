<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Sale;
use App\Services\SaleService;
use App\Http\Requests\Sales\VoidSaleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends ApiController
{
    protected SaleService $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
        $this->authorizeResource(Sale::class, 'sale');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'customer_id', 'date_from', 'date_to']);
        $sales = $this->saleService->getSalesWithFilters($filters, $request->per_page ?? 15);

        return $this->successResponse(\App\Http\Resources\SaleResource::collection($sales)->response()->getData(true));
    }

    public function show(Sale $sale): JsonResponse
    {
        return $this->successResponse(new \App\Http\Resources\SaleResource($sale->load(['customer', 'items.product', 'payments.paymentMethod', 'registerSession'])));
    }

    public function void(VoidSaleRequest $request, Sale $sale): JsonResponse
    {
        $this->authorize('update', $sale);

        try {
            $voidedSale = $this->saleService->voidSale($sale->id, $request->validated());

            return $this->successResponse($voidedSale, 'Sale voided successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function receipt(Sale $sale): JsonResponse
    {
        $this->authorize('view', $sale);
        $receiptData = $this->saleService->generateReceipt($sale->id);

        return $this->successResponse($receiptData);
    }
}