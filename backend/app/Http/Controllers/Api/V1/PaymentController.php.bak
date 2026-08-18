<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Payment Processing Controller
 * 
 * Handles payment calculations, validation, and processing
 * WITHOUT external payment gateways.
 */
class PaymentController extends Controller
{
    protected PaymentProcessingService $paymentService;

    public function __construct(PaymentProcessingService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Validate split payment allocation
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function validatePayments(Request $request): JsonResponse
    {
        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.method_id' => 'required|integer',
            'payments.*.method_name' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.type' => 'nullable|string|in:cash,credit,card,gift_card,store_credit',
            'total_amount' => 'required|numeric|min:0.01',
            'customer_credit_limit' => 'nullable|numeric|min:0',
            'customer_balance' => 'nullable|numeric|min:0',
        ]);

        $context = [];
        if ($request->filled('customer_credit_limit')) {
            $context['customer_credit_limit'] = (float) $request->customer_credit_limit;
            $context['customer_balance'] = (float) ($request->customer_balance ?? 0);
        }

        $result = $this->paymentService->validateSplitPayments(
            $request->payments,
            (float) $request->total_amount,
            $context
        );

        return response()->json([
            'success' => $result['valid'],
            'data' => $result,
        ], $result['valid'] ? 200 : 422);
    }

    /**
     * Calculate change due
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateChange(Request $request): JsonResponse
    {
        $request->validate([
            'amount_paid' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0.01',
        ]);

        $result = $this->paymentService->calculateChange(
            (float) $request->amount_paid,
            (float) $request->total_amount
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Calculate tip amount
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateTip(Request $request): JsonResponse
    {
        $request->validate([
            'subtotal' => 'required|numeric|min:0.01',
            'tip_percentage' => 'nullable|numeric|min:0|max:100',
            'custom_tip' => 'nullable|numeric|min:0',
        ]);

        $result = $this->paymentService->calculateTip(
            (float) $request->subtotal,
            (float) ($request->tip_percentage ?? 0),
            (float) ($request->custom_tip ?? 0)
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Calculate total with tip and tax
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateTotal(Request $request): JsonResponse
    {
        $request->validate([
            'subtotal' => 'required|numeric|min:0',
            'tax' => 'required|numeric|min:0',
            'tip' => 'nullable|numeric|min:0',
        ]);

        $result = $this->paymentService->calculateTotalWithTip(
            (float) $request->subtotal,
            (float) $request->tax,
            (float) ($request->tip ?? 0)
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Allocate tip across payment methods
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function allocateTip(Request $request): JsonResponse
    {
        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.method_id' => 'required|integer',
            'payments.*.method_name' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.type' => 'nullable|string',
            'total_tip' => 'required|numeric|min:0',
        ]);

        $result = $this->paymentService->allocateTipAcrossPayments(
            $request->payments,
            (float) $request->total_tip
        );

        return response()->json([
            'success' => true,
            'data' => [
                'payments_with_tip' => $result,
                'total_tip' => round(array_sum(array_column($result, 'tip_amount')), 2),
            ],
        ]);
    }

    /**
     * Get payment summary for receipt
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentSummary(Request $request): JsonResponse
    {
        $request->validate([
            'payments' => 'required|array|min:1',
            'payments.*.method_name' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.type' => 'nullable|string',
            'total_amount' => 'required|numeric|min:0.01',
            'tip' => 'nullable|numeric|min:0',
        ]);

        $result = $this->paymentService->formatPaymentSummary(
            $request->payments,
            (float) $request->total_amount,
            (float) ($request->tip ?? 0)
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Check if sale is fully paid
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'total_amount' => 'required|numeric|min:0.01',
            'total_paid' => 'required|numeric|min:0',
        ]);

        $isFullyPaid = $this->paymentService->isFullyPaid(
            (float) $request->total_amount,
            (float) $request->total_paid
        );

        $status = $this->paymentService->getPaymentStatus(
            (float) $request->total_amount,
            (float) $request->total_paid
        );

        $remaining = $this->paymentService->getRemainingBalance(
            (float) $request->total_amount,
            (float) $request->total_paid
        );

        return response()->json([
            'success' => true,
            'data' => [
                'is_fully_paid' => $isFullyPaid,
                'status' => $status,
                'remaining_balance' => $remaining,
                'total_amount' => round((float) $request->total_amount, 2),
                'total_paid' => round((float) $request->total_paid, 2),
            ],
        ]);
    }
}
