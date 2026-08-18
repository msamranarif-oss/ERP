<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    protected PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
     * Calculate pricing for cart items
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:1',
            'items.*.discount_type' => 'nullable|string|in:percentage,fixed',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discounts' => 'nullable|array',
            'discounts.global_discount_type' => 'nullable|string|in:percentage,fixed',
            'discounts.global_discount_amount' => 'nullable|numeric|min:0',
            'discounts.coupon_discount' => 'nullable|numeric|min:0',
            'discounts.loyalty_discount' => 'nullable|numeric|min:0',
            'discounts.shipping_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $cartItems = $request->input('items');
            $discounts = $request->input('discounts', []);

            // Calculate pricing
            $pricingResult = $this->pricingService->calculateCartPricing($cartItems, $discounts);

            // Validate pricing
            $this->pricingService->validatePricing($pricingResult);

            // Format for receipt (optional)
            $receiptLines = $this->pricingService->formatForReceipt($pricingResult);

            return response()->json([
                'success' => true,
                'data' => [
                    'pricing' => $pricingResult,
                    'receipt_lines' => $receiptLines,
                ],
                'message' => 'Pricing calculated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pricing calculation failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Calculate single line item pricing
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateLineItem(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_price' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'discount_type' => 'nullable|string|in:percentage,fixed',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $item = $request->only([
                'product_id',
                'quantity',
                'unit_price',
                'tax_rate',
                'discount_type',
                'discount_amount',
            ]);

            $linePricing = $this->pricingService->calculateLineItemPricing($item);

            return response()->json([
                'success' => true,
                'data' => $linePricing,
                'message' => 'Line item pricing calculated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Line item pricing calculation failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Calculate discount amount
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateDiscount(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
        ]);

        try {
            $discount = $this->pricingService->calculateDiscount(
                (float) $request->input('amount'),
                $request->input('discount_type'),
                (float) $request->input('discount_value')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'original_amount' => $request->input('amount'),
                    'discount_type' => $request->input('discount_type'),
                    'discount_value' => $request->input('discount_value'),
                    'discount_amount' => $discount,
                    'final_amount' => $request->input('amount') - $discount,
                ],
                'message' => 'Discount calculated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Discount calculation failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Calculate tax amount
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateTax(Request $request): JsonResponse
    {
        $request->validate([
            'taxable_amount' => 'required|numeric|min:0',
            'tax_rate' => 'required|numeric|min:0|max:1',
        ]);

        try {
            $tax = $this->pricingService->calculateTax(
                (float) $request->input('taxable_amount'),
                (float) $request->input('tax_rate')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'taxable_amount' => $request->input('taxable_amount'),
                    'tax_rate' => $request->input('tax_rate'),
                    'tax_amount' => $tax,
                    'total_with_tax' => $request->input('taxable_amount') + $tax,
                ],
                'message' => 'Tax calculated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax calculation failed: ' . $e->getMessage(),
            ], 422);
        }
    }
}
