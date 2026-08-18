<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Payment Processing Service
 * 
 * Handles split payments, tip calculation, change calculation,
 * and payment validation WITHOUT external payment gateways.
 * 
 * Supports: Cash, Credit, Card (manual entry), Gift Cards, Store Credit
 */
class PaymentProcessingService
{
    /**
     * Validate split payment allocation
     * 
     * Ensures that:
     * - Total payments equal or exceed the sale total
     * - No negative payment amounts
     * - Payment methods are valid
     * - Credit limits are respected
     * 
     * @param array $payments Array of payment allocations
     * @param float $totalAmount Total sale amount
     * @param array $context Additional context (customer, credit limits, etc.)
     * @return array Validation result
     */
    public function validateSplitPayments(array $payments, float $totalAmount, array $context = []): array
    {
        $errors = [];
        $totalPaid = 0;
        $validatedPayments = [];

        // Validate each payment
        foreach ($payments as $index => $payment) {
            $validator = Validator::make($payment, [
                'method_id' => 'required|integer|min:1',
                'method_name' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'type' => 'nullable|string|in:cash,credit,card,gift_card,store_credit',
            ]);

            if ($validator->fails()) {
                $errors[] = "Payment #" . ($index + 1) . ": " . $validator->errors()->first();
                continue;
            }

            $amount = (float) $payment['amount'];
            $type = $payment['type'] ?? 'cash';

            // Check for duplicate payment methods (except cash which can be split)
            if ($type !== 'cash') {
                $duplicate = collect($validatedPayments)->first(function ($p) use ($payment, $type) {
                    return $p['method_id'] === $payment['method_id'];
                });

                if ($duplicate) {
                    $errors[] = "Payment method '{$payment['method_name']}' can only be used once";
                    continue;
                }
            }

            // Credit limit check
            if ($type === 'credit' && isset($context['customer_credit_limit'])) {
                $availableCredit = $context['customer_credit_limit'] - ($context['customer_balance'] ?? 0);
                if ($amount > $availableCredit) {
                    $errors[] = "Credit payment exceeds available credit limit (Available: {$availableCredit})";
                    continue;
                }
            }

            $totalPaid += $amount;
            $validatedPayments[] = [
                'method_id' => $payment['method_id'],
                'method_name' => $payment['method_name'],
                'amount' => round($amount, 2),
                'type' => $type,
            ];
        }

        // Check if total covers the sale amount
        $totalPaid = round($totalPaid, 2);
        $totalAmount = round($totalAmount, 2);

        if ($totalPaid < $totalAmount) {
            $shortfall = $totalAmount - $totalPaid;
            $errors[] = "Insufficient payment. Short by: {$shortfall}";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_paid' => $totalPaid,
            'total_amount' => $totalAmount,
            'overpayment' => max(0, $totalPaid - $totalAmount),
            'validated_payments' => $validatedPayments,
        ];
    }

    /**
     * Calculate change due
     * 
     * @param float $amountPaid Total amount paid
     * @param float $totalAmount Total sale amount including tip
     * @return array Change calculation breakdown
     */
    public function calculateChange(float $amountPaid, float $totalAmount): array
    {
        $amountPaid = round($amountPaid, 2);
        $totalAmount = round($totalAmount, 2);
        $changeDue = round(max(0, $amountPaid - $totalAmount), 2);

        // Calculate bill breakdown for cash payments
        $billBreakdown = $this->calculateBillBreakdown($changeDue);

        return [
            'amount_paid' => $amountPaid,
            'total_amount' => $totalAmount,
            'change_due' => $changeDue,
            'requires_change' => $changeDue > 0,
            'bill_breakdown' => $billBreakdown,
        ];
    }

    /**
     * Calculate optimal bill/coin breakdown for change
     * 
     * @param float $changeDue Amount of change due
     * @return array Bill and coin breakdown
     */
    private function calculateBillBreakdown(float $changeDue): array
    {
        $remaining = (int) round($changeDue * 100); // Convert to cents
        
        $denominations = [
            'hundred_dollar' => 10000,
            'fifty_dollar' => 5000,
            'twenty_dollar' => 2000,
            'ten_dollar' => 1000,
            'five_dollar' => 500,
            'one_dollar' => 100,
            'quarter' => 25,
            'dime' => 10,
            'nickel' => 5,
            'penny' => 1,
        ];

        $breakdown = [];
        
        foreach ($denominations as $name => $value) {
            $count = intdiv($remaining, $value);
            if ($count > 0) {
                $breakdown[$name] = [
                    'count' => $count,
                    'value' => $value / 100,
                    'total' => round(($count * $value) / 100, 2),
                ];
                $remaining %= $value;
            }
        }

        return $breakdown;
    }

    /**
     * Calculate tip amount
     * 
     * @param float $subtotal Sale subtotal (before tax)
     * @param float $tipPercentage Tip percentage (0-100)
     * @param float $customTip Custom tip amount (overrides percentage)
     * @return array Tip calculation result
     */
    public function calculateTip(
        float $subtotal,
        float $tipPercentage = 0,
        float $customTip = 0
    ): array {
        $subtotal = round($subtotal, 2);
        
        // Custom tip overrides percentage
        if ($customTip > 0) {
            $tipAmount = round($customTip, 2);
            $effectivePercentage = round(($tipAmount / $subtotal) * 100, 2);
        } else {
            $tipPercentage = min(max($tipPercentage, 0), 100); // Clamp 0-100
            $tipAmount = round($subtotal * ($tipPercentage / 100), 2);
            $effectivePercentage = $tipPercentage;
        }

        $totalWithTip = round($subtotal + $tipAmount, 2);

        return [
            'subtotal' => $subtotal,
            'tip_percentage' => $effectivePercentage,
            'tip_amount' => $tipAmount,
            'total_with_tip' => $totalWithTip,
            'has_tip' => $tipAmount > 0,
        ];
    }

    /**
     * Calculate total with tip and tax
     * 
     * @param float $subtotal Sale subtotal
     * @param float $taxAmount Tax amount
     * @param float $tipAmount Tip amount
     * @return array Total calculation
     */
    public function calculateTotalWithTip(float $subtotal, float $taxAmount, float $tipAmount = 0): array
    {
        $subtotal = round($subtotal, 2);
        $taxAmount = round($taxAmount, 2);
        $tipAmount = round($tipAmount, 2);

        $total = round($subtotal + $taxAmount + $tipAmount, 2);

        return [
            'subtotal' => $subtotal,
            'tax' => $taxAmount,
            'tip' => $tipAmount,
            'total' => $total,
            'has_tip' => $tipAmount > 0,
        ];
    }

    /**
     * Allocate tip across payment methods proportionally
     * 
     * @param array $payments Array of payments
     * @param float $totalTip Total tip amount
     * @return array Payments with allocated tips
     */
    public function allocateTipAcrossPayments(array $payments, float $totalTip): array
    {
        $totalPaid = array_sum(array_column($payments, 'amount'));
        
        if ($totalPaid == 0 || $totalTip == 0) {
            return array_map(function ($payment) {
                return array_merge($payment, ['tip_amount' => 0]);
            }, $payments);
        }

        $allocatedPayments = [];
        $allocatedTotal = 0;

        foreach ($payments as $index => $payment) {
            $proportion = $payment['amount'] / $totalPaid;
            $tipAllocation = round($totalTip * $proportion, 2);

            // Adjust last payment to ensure exact total
            if ($index === count($payments) - 1) {
                $tipAllocation = round($totalTip - $allocatedTotal, 2);
            }

            $allocatedPayments[] = array_merge($payment, [
                'tip_amount' => $tipAllocation,
                'total_with_tip' => round($payment['amount'] + $tipAllocation, 2),
            ]);

            $allocatedTotal += $tipAllocation;
        }

        return $allocatedPayments;
    }

    /**
     * Validate cash payment amount
     * 
     * @param float $amountPaid Amount paid in cash
     * @param float $totalAmount Total amount due
     * @return array Validation result
     */
    public function validateCashPayment(float $amountPaid, float $totalAmount): array
    {
        $amountPaid = round($amountPaid, 2);
        $totalAmount = round($totalAmount, 2);

        $errors = [];

        if ($amountPaid <= 0) {
            $errors[] = "Payment amount must be greater than zero";
        }

        if ($amountPaid < $totalAmount) {
            $shortfall = round($totalAmount - $amountPaid, 2);
            $errors[] = "Insufficient cash. Need {$shortfall} more";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'amount_paid' => $amountPaid,
            'total_amount' => $totalAmount,
            'change_due' => round(max(0, $amountPaid - $totalAmount), 2),
        ];
    }

    /**
     * Format payment summary for receipt
     * 
     * @param array $payments Array of payments
     * @param float $totalAmount Total sale amount
     * @param float $tipAmount Tip amount
     * @return array Formatted payment summary
     */
    public function formatPaymentSummary(array $payments, float $totalAmount, float $tipAmount = 0): array
    {
        $totalPaid = array_sum(array_column($payments, 'amount'));
        $changeDue = round(max(0, $totalPaid - $totalAmount - $tipAmount), 2);

        $summary = [
            'payments' => array_map(function ($payment) {
                return [
                    'method' => $payment['method_name'],
                    'amount' => round($payment['amount'], 2),
                    'type' => $payment['type'] ?? 'cash',
                ];
            }, $payments),
            'subtotal_payments' => round($totalPaid, 2),
            'sale_total' => round($totalAmount, 2),
            'tip' => round($tipAmount, 2),
            'change_due' => $changeDue,
            'payment_count' => count($payments),
        ];

        return $summary;
    }

    /**
     * Determine if sale is fully paid
     * 
     * @param float $totalAmount Total amount due (including tip)
     * @param float $totalPaid Total amount paid
     * @return bool
     */
    public function isFullyPaid(float $totalAmount, float $totalPaid): bool
    {
        return round($totalPaid, 2) >= round($totalAmount, 2);
    }

    /**
     * Get payment status
     * 
     * @param float $totalAmount Total amount due
     * @param float $totalPaid Total amount paid
     * @return string Payment status
     */
    public function getPaymentStatus(float $totalAmount, float $totalPaid): string
    {
        $totalAmount = round($totalAmount, 2);
        $totalPaid = round($totalPaid, 2);

        if ($totalPaid >= $totalAmount) {
            return 'paid';
        } elseif ($totalPaid > 0) {
            return 'partial';
        } else {
            return 'unpaid';
        }
    }

    /**
     * Calculate remaining balance
     * 
     * @param float $totalAmount Total amount due
     * @param float $totalPaid Total amount paid
     * @return float Remaining balance
     */
    public function getRemainingBalance(float $totalAmount, float $totalPaid): float
    {
        return round(max(0, $totalAmount - $totalPaid), 2);
    }
}
