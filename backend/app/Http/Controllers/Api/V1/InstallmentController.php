<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Installment;
use App\Services\InstallmentService;
use App\Http\Requests\Credit\UpdateInstallmentRequest;
use App\Http\Requests\Credit\PayInstallmentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstallmentController extends ApiController
{
    protected InstallmentService $installmentService;

    public function __construct(InstallmentService $installmentService)
    {
        $this->installmentService = $installmentService;
        $this->authorizeResource(Installment::class, 'installment');
    }

    public function overdue(): JsonResponse
    {
        $installments = $this->installmentService->getOverdueInstallments();
        return $this->successResponse($installments);
    }

    public function dueToday(): JsonResponse
    {
        $installments = $this->installmentService->getDueTodayInstallments();
        return $this->successResponse($installments);
    }

    public function upcoming(): JsonResponse
    {
        $installments = $this->installmentService->getUpcomingInstallments(7);
        return $this->successResponse($installments);
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'customer_id']);
        $installments = $this->installmentService->getInstallmentsWithFilters($filters, $request->per_page ?? 15);

        return $this->successResponse($installments);
    }

    public function show(Installment $installment): JsonResponse
    {
        return $this->successResponse($installment->load(['creditSale.customer.customer']));
    }

    public function update(UpdateInstallmentRequest $request, Installment $installment): JsonResponse
    {
        $this->authorize('update', $installment);
        
        $installment->update($request->validated());

        return $this->successResponse($installment->load(['creditSale.customer.customer']), 'Installment updated successfully.');
    }

    public function destroy(Installment $installment): JsonResponse
    {
        $this->authorize('delete', $installment);
        
        $installment->delete();

        return $this->successResponse(null, 'Installment deleted successfully.');
    }

    public function pay(PayInstallmentRequest $request, Installment $installment): JsonResponse
    {
        try {
            $payment = $this->installmentService->processPayment($installment->id, $request->validated());

            return $this->successResponse($payment, 'Payment recorded successfully.');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}