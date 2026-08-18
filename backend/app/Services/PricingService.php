<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Centralized Pricing Service
 * 
 * Single source of truth for all pricing calculations in the POS system.
 * Eliminates calculation logic duplication between frontend and backend.
 * 
 * Features:
 * - Line item pricing with discounts
 * - Cart-level (global) discounts
 * - Coupon discount calculation
 * - Loyalty points redemption
 * - Tax calculation with precision
 * - Detailed pricing breakdown for receipts
 * 
 * Precision:
 * - Quantities: 4 decimal places
 * - Currency: 2 decimal places
 * - Tax rates: 4 decimal places
 */
class PricingService extends BaseService
{
    /**
     * Calculate pricing for a single line item
     *
     * @param array $item Cart item data
     * @param float $globalDiscountValue Global discount amount (already calculated)
     * @param float $subtotalAfterLineDiscounts Subtotal after line discounts
     * @return array Detailed line item pricing breakdown
     */
    public function calculateLineItemPricing(array $item, float $globalDiscountValue = 0, float $subtotalAfterLineDiscounts = 0): array
    {
        $quantity = (float) ($item['quantity'] ?? 1);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $taxRate = (float) ($item['tax_rate'] ?? 0);
        
        // Calculate gross amount (before discounts)
        $grossAmount = $quantity * $unitPrice;
        
        // Calculate line-level discount
        $lineDiscount = 0;
        if (!empty($item['discount_type']) && !empty($item['discount_amount'])) {
            if ($item['discount_type'] === 'percentage') {
                $lineDiscount = $grossAmount * ((float) $item['discount_amount'] / 100);
            } else {
                $lineDiscount = (float) $item['discount_amount'];
            }
        }
        
        // Ensure discount doesn't exceed gross amount
        $lineDiscount = min($lineDiscount, $grossAmount);
        
        // Amount after line discount
        $amountAfterLineDiscount = $grossAmount - $lineDiscount;
        
        // Calculate proportional global discount
        $proportionalGlobalDiscount = 0;
        if ($globalDiscountValue > 0 && $subtotalAfterLineDiscounts > 0) {
            $proportionalGlobalDiscount = ($amountAfterLineDiscount / $subtotalAfterLineDiscounts) * $globalDiscountValue;
        }
        
        // Final taxable amount
        $taxableAmount = $amountAfterLineDiscount - $proportionalGlobalDiscount;
        
        // Calculate tax
        $taxAmount = $taxableAmount * $taxRate;
        
        // Final total for this line item
        $totalAmount = $taxableAmount + $taxAmount;
        
        return [
            'product_id' => $item['product_id'] ?? null,
            'variant_id' => $item['variant_id'] ?? null,
            'batch_id' => $item['batch_id'] ?? null,
            'quantity' => $quantity,
            'unit_price' => round($unitPrice, 2),
            'gross_amount' => round($grossAmount, 2),
            'discount_type' => $item['discount_type'] ?? null,
            'discount_amount' => round($lineDiscount, 2),
            'amount_after_line_discount' => round($amountAfterLineDiscount, 2),
            'proportional_global_discount' => round($proportionalGlobalDiscount, 2),
            'taxable_amount' => round($taxableAmount, 2),
            'tax_rate' => $taxRate,
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * Calculate pricing for entire cart
     *
     * @param array $cartItems Array of cart items
     * @param array $discounts Discount configuration
     * @return array Complete pricing breakdown
     */
    public function calculateCartPricing(array $cartItems, array $discounts = []): array
    {
        // Step 1: Calculate gross subtotal (before any discounts)
        $subtotalBeforeDiscounts = 0;
        foreach ($cartItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $subtotalBeforeDiscounts += $quantity * $unitPrice;
        }
        $subtotalBeforeDiscounts = round($subtotalBeforeDiscounts, 2);
        
        // Step 2: Calculate line-level discounts
        $lineDiscountsTotal = 0;
        $lineBreakdowns = [];
        
        foreach ($cartItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $grossAmount = $quantity * $unitPrice;
            
            $lineDiscount = 0;
            if (!empty($item['discount_type']) && !empty($item['discount_amount'])) {
                if ($item['discount_type'] === 'percentage') {
                    $lineDiscount = $grossAmount * ((float) $item['discount_amount'] / 100);
                } else {
                    $lineDiscount = (float) $item['discount_amount'];
                }
            }
            
            $lineDiscount = min($lineDiscount, $grossAmount);
            $lineDiscountsTotal += $lineDiscount;
            
            $lineBreakdowns[] = [
                'product_id' => $item['product_id'] ?? null,
                'variant_id' => $item['variant_id'] ?? null,
                'gross_amount' => round($grossAmount, 2),
                'discount_amount' => round($lineDiscount, 2),
                'net_amount' => round($grossAmount - $lineDiscount, 2),
            ];
        }
        $lineDiscountsTotal = round($lineDiscountsTotal, 2);
        
        // Step 3: Calculate subtotal after line discounts
        $subtotalAfterLineDiscounts = $subtotalBeforeDiscounts - $lineDiscountsTotal;
        $subtotalAfterLineDiscounts = round($subtotalAfterLineDiscounts, 2);
        
        // Step 4: Calculate global/cart-level discount
        $globalDiscountValue = 0;
        $globalDiscountType = $discounts['global_discount_type'] ?? null;
        $globalDiscountAmount = (float) ($discounts['global_discount_amount'] ?? 0);
        
        if ($globalDiscountAmount > 0) {
            if ($globalDiscountType === 'percentage') {
                $globalDiscountValue = $subtotalAfterLineDiscounts * ($globalDiscountAmount / 100);
            } else {
                $globalDiscountValue = $globalDiscountAmount;
            }
            
            // Ensure global discount doesn't exceed subtotal
            $globalDiscountValue = min($globalDiscountValue, $subtotalAfterLineDiscounts);
        }
        $globalDiscountValue = round($globalDiscountValue, 2);
        
        // Step 5: Calculate final subtotal after all discounts
        $finalSubtotal = $subtotalAfterLineDiscounts - $globalDiscountValue;
        $finalSubtotal = round($finalSubtotal, 2);
        
        // Step 6: Calculate coupon discount
        $couponDiscount = (float) ($discounts['coupon_discount'] ?? 0);
        $couponDiscount = min($couponDiscount, $finalSubtotal);
        $couponDiscount = round($couponDiscount, 2);
        
        $subtotalAfterCoupon = $finalSubtotal - $couponDiscount;
        $subtotalAfterCoupon = round($subtotalAfterCoupon, 2);
        
        // Step 7: Calculate loyalty discount
        $loyaltyDiscount = (float) ($discounts['loyalty_discount'] ?? 0);
        $loyaltyDiscount = min($loyaltyDiscount, $subtotalAfterCoupon);
        $loyaltyDiscount = round($loyaltyDiscount, 2);
        
        $finalTaxableAmount = $subtotalAfterCoupon - $loyaltyDiscount;
        $finalTaxableAmount = round($finalTaxableAmount, 2);
        
        // Step 8: Calculate tax for each item with proportional discount allocation
        $totalTax = 0;
        $detailedLineItems = [];
        
        foreach ($cartItems as $item) {
            $linePricing = $this->calculateLineItemPricing(
                $item,
                $globalDiscountValue,
                $subtotalAfterLineDiscounts
            );
            
            // Apply coupon and loyalty discounts proportionally
            $lineTotalAfterGlobalDiscount = $linePricing['total_amount'];
            
            if ($couponDiscount > 0 && $finalSubtotal > 0) {
                $proportionalCouponDiscount = ($lineTotalAfterGlobalDiscount / $finalSubtotal) * $couponDiscount;
                $lineTotalAfterGlobalDiscount -= $proportionalCouponDiscount;
            }
            
            if ($loyaltyDiscount > 0 && $subtotalAfterCoupon > 0) {
                $proportionalLoyaltyDiscount = ($lineTotalAfterGlobalDiscount / $subtotalAfterCoupon) * $loyaltyDiscount;
                $lineTotalAfterGlobalDiscount -= $proportionalLoyaltyDiscount;
            }
            
            // Recalculate tax after all discounts
            $taxableAmount = $lineTotalAfterGlobalDiscount / (1 + $linePricing['tax_rate']);
            $taxAmount = $lineTotalAfterGlobalDiscount - $taxableAmount;
            
            $detailedLineItems[] = array_merge($linePricing, [
                'coupon_discount_proportional' => round($couponDiscount > 0 && $finalSubtotal > 0 
                    ? ($linePricing['total_amount'] / $finalSubtotal) * $couponDiscount 
                    : 0, 2),
                'loyalty_discount_proportional' => round($loyaltyDiscount > 0 && $subtotalAfterCoupon > 0
                    ? ($lineTotalAfterGlobalDiscount / $subtotalAfterCoupon) * $loyaltyDiscount
                    : 0, 2),
                'final_taxable_amount' => round($taxableAmount, 2),
                'final_tax_amount' => round($taxAmount, 2),
                'final_total' => round($taxableAmount + $taxAmount, 2),
            ]);
            
            $totalTax += $taxAmount;
        }
        $totalTax = round($totalTax, 2);
        
        // Step 9: Calculate final total
        $finalTotal = $finalTaxableAmount + $totalTax;
        $finalTotal = round($finalTotal, 2);
        
        // Step 10: Calculate shipping (if applicable)
        $shippingAmount = (float) ($discounts['shipping_amount'] ?? 0);
        $finalTotalWithShipping = $finalTotal + $shippingAmount;
        $finalTotalWithShipping = round($finalTotalWithShipping, 2);
        
        return [
            'summary' => [
                'subtotal_before_discounts' => $subtotalBeforeDiscounts,
                'line_discounts_total' => $lineDiscountsTotal,
                'subtotal_after_line_discounts' => $subtotalAfterLineDiscounts,
                'global_discount_type' => $globalDiscountType,
                'global_discount_amount' => $globalDiscountAmount,
                'global_discount_value' => $globalDiscountValue,
                'final_subtotal' => $finalSubtotal,
                'coupon_discount' => $couponDiscount,
                'loyalty_discount' => $loyaltyDiscount,
                'final_taxable_amount' => $finalTaxableAmount,
                'total_tax' => $totalTax,
                'subtotal_total' => $finalTotal,
                'shipping_amount' => $shippingAmount,
                'grand_total' => $finalTotalWithShipping,
            ],
            'line_items' => $detailedLineItems,
            'line_breakdowns' => $lineBreakdowns,
        ];
    }

    /**
     * Calculate tax amount for a given taxable amount and tax rate
     *
     * @param float $taxableAmount
     * @param float $taxRate
     * @return float Tax amount
     */
    public function calculateTax(float $taxableAmount, float $taxRate): float
    {
        $tax = $taxableAmount * $taxRate;
        return round($tax, 2);
    }

    /**
     * Apply discount to amount
     *
     * @param float $amount Original amount
     * @param string $discountType 'percentage' or 'fixed'
     * @param float $discountValue Discount value
     * @return float Discount amount
     */
    public function calculateDiscount(float $amount, string $discountType, float $discountValue): float
    {
        if ($discountValue <= 0) {
            return 0;
        }
        
        $discount = 0;
        if ($discountType === 'percentage') {
            $discount = $amount * ($discountValue / 100);
        } else {
            $discount = $discountValue;
        }
        
        // Ensure discount doesn't exceed amount
        return min(round($discount, 2), $amount);
    }

    /**
     * Validate pricing data
     *
     * @param array $pricingResult Pricing calculation result
     * @return bool True if valid
     * @throws \Exception If invalid
     */
    public function validatePricing(array $pricingResult): bool
    {
        $summary = $pricingResult['summary'];
        
        // Validate all amounts are non-negative
        $fields = [
            'subtotal_before_discounts',
            'line_discounts_total',
            'subtotal_after_line_discounts',
            'global_discount_value',
            'final_subtotal',
            'coupon_discount',
            'loyalty_discount',
            'final_taxable_amount',
            'total_tax',
            'subtotal_total',
            'shipping_amount',
            'grand_total',
        ];
        
        foreach ($fields as $field) {
            if ($summary[$field] < 0) {
                throw new \Exception("Invalid pricing: {$field} cannot be negative");
            }
        }
        
        // Validate mathematical relationships
        if (abs($summary['subtotal_after_line_discounts'] - ($summary['subtotal_before_discounts'] - $summary['line_discounts_total'])) > 0.01) {
            throw new \Exception("Invalid pricing: subtotal_after_line_discounts calculation error");
        }
        
        if (abs($summary['final_subtotal'] - ($summary['subtotal_after_line_discounts'] - $summary['global_discount_value'])) > 0.01) {
            throw new \Exception("Invalid pricing: final_subtotal calculation error");
        }
        
        if (abs($summary['final_taxable_amount'] - ($summary['final_subtotal'] - $summary['coupon_discount'] - $summary['loyalty_discount'])) > 0.01) {
            throw new \Exception("Invalid pricing: final_taxable_amount calculation error");
        }
        
        // Verify grand total
        $expectedGrandTotal = $summary['final_taxable_amount'] + $summary['total_tax'] + $summary['shipping_amount'];
        if (abs($summary['grand_total'] - $expectedGrandTotal) > 0.01) {
            throw new \Exception("Invalid pricing: grand_total calculation error");
        }
        
        return true;
    }

    /**
     * Format pricing breakdown for receipt printing
     *
     * @param array $pricingResult
     * @return array Formatted receipt lines
     */
    public function formatForReceipt(array $pricingResult): array
    {
        $summary = $pricingResult['summary'];
        $lines = [];
        
        // Subtotal
        $lines[] = [
            'label' => 'Subtotal',
            'amount' => $summary['subtotal_before_discounts'],
        ];
        
        // Line discounts
        if ($summary['line_discounts_total'] > 0) {
            $lines[] = [
                'label' => 'Item Discounts',
                'amount' => -$summary['line_discounts_total'],
            ];
        }
        
        // Global discount
        if ($summary['global_discount_value'] > 0) {
            $label = $summary['global_discount_type'] === 'percentage'
                ? "Cart Discount ({$summary['global_discount_amount']}%)"
                : "Cart Discount";
            $lines[] = [
                'label' => $label,
                'amount' => -$summary['global_discount_value'],
            ];
        }
        
        // Coupon discount
        if ($summary['coupon_discount'] > 0) {
            $lines[] = [
                'label' => 'Coupon Discount',
                'amount' => -$summary['coupon_discount'],
            ];
        }
        
        // Loyalty discount
        if ($summary['loyalty_discount'] > 0) {
            $lines[] = [
                'label' => 'Loyalty Discount',
                'amount' => -$summary['loyalty_discount'],
            ];
        }
        
        // Tax
        $lines[] = [
            'label' => 'Tax',
            'amount' => $summary['total_tax'],
        ];
        
        // Shipping
        if ($summary['shipping_amount'] > 0) {
            $lines[] = [
                'label' => 'Shipping',
                'amount' => $summary['shipping_amount'],
            ];
        }
        
        // Grand Total
        $lines[] = [
            'label' => 'TOTAL',
            'amount' => $summary['grand_total'],
            'is_total' => true,
        ];
        
        return $lines;
    }
}
