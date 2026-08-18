<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CreditSaleException;
use App\Exceptions\PaymentException;
use App\Http\Controllers\ApiController;
use App\Models\CreditSale;
use App\Services\CreditSaleService;
use App\Http\Requests\Credit\StoreCreditSaleRequest;
use App\Http\Requests\Credit\UpdateCreditSaleRequest;
use App\Http\Requests\Credit\RecordPaymentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditSaleController extends ApiController
{
    protected CreditSaleService $creditSaleService;

    public function __construct(CreditSaleService $creditSaleService)
    {
        $this->creditSaleService = $creditSaleService;
        $this->authorizeResource(CreditSale::class, 'credit_sale');
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'customer_id']);
        $creditSales = $this->creditSaleService->getAll($filters, $request->per_page ?? 15);

        return $this->successResponse($creditSales, 'Credit sales retrieved successfully');
    }

    public function store(StoreCreditSaleRequest $request): JsonResponse
    {
        try {
            $creditSale = $this->creditSaleService->createCreditSale($request->validated());

            return $this->successResponse($creditSale, 'Credit sale created successfully.', 201);
        } catch (CreditSaleException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create credit sale: ' . $e->getMessage(), 500);
        }
    }

    public function show(CreditSale $credit_sale): JsonResponse
    {
        $creditSale = $this->creditSaleService->findById($credit_sale->id);
        return $this->successResponse($creditSale->load(['customer.customer', 'items.product', 'installments']), 'Credit sale retrieved successfully');
    }

    public function update(UpdateCreditSaleRequest $request, CreditSale $credit_sale): JsonResponse
    {
        try {
            $updatedCreditSale = $this->creditSaleService->update($credit_sale->id, $request->validated());

            return $this->successResponse($updatedCreditSale->load(['customer.customer', 'items.product', 'installments']), 'Credit sale updated successfully.');
        } catch (CreditSaleException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update credit sale: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(CreditSale $credit_sale): JsonResponse
    {
        try {
            $this->creditSaleService->deleteCreditSale($credit_sale->id);

            return $this->successResponse(null, 'Credit sale deleted successfully.');
        } catch (CreditSaleException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete credit sale: ' . $e->getMessage(), 500);
        }
    }

    public function recordPayment(CreditSale $credit_sale, RecordPaymentRequest $request): JsonResponse
    {
        $this->authorize('update', $credit_sale);

        try {
            $payment = $this->creditSaleService->recordPayment($credit_sale->id, $request->validated());

            return $this->successResponse($payment, 'Payment recorded successfully.', 201);
        } catch (PaymentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record payment: ' . $e->getMessage(), 500);
        }
    }

    public function schedule(CreditSale $credit_sale): JsonResponse
    {
        $this->authorize('view', $credit_sale);
        $installments = $this->creditSaleService->getSchedule($credit_sale->id);

        return $this->successResponse($installments, 'Installment schedule retrieved successfully');
    }
}