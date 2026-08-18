<?php

namespace Tests\Unit;

use App\Services\PaymentProcessingService;
use Tests\TestCase;

/**
 * Payment Processing Service Tests
 * 
 * Comprehensive tests for split payments, tips, change calculation,
 * and payment validation.
 */
class PaymentProcessingServiceTest extends TestCase
{
    protected PaymentProcessingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentProcessingService();
    }

    /** @test */
    public function it_validates_single_cash_payment()
    {
        $payments = [
            [
                'method_id' => 1,
                'method_name' => 'Cash',
                'amount' => 100.00,
                'type' => 'cash',
            ]
        ];

        $result = $this->service->validateSplitPayments($payments, 100.00);

        $this->assertTrue($result['valid']);
        $this->assertEquals(100.00, $result['total_paid']);
        $this->assertEquals(0, $result['overpayment']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_validates_split_payments()
    {
        $payments = [
            [
                'method_id' => 1,
                'method_name' => 'Cash',
                'amount' => 50.00,
                'type' => 'cash',
            ],
            [
                'method_id' => 2,
                'method_name' => 'Credit Card',
                'amount' => 50.00,
                'type' => 'card',
            ],
        ];

        $result = $this->service->validateSplitPayments($payments, 100.00);

        $this->assertTrue($result['valid']);
        $this->assertEquals(100.00, $result['total_paid']);
    }

    /** @test */
    public function it_rejects_insufficient_payment()
    {
        $payments = [
            [
                'method_id' => 1,
                'method_name' => 'Cash',
                'amount' => 80.00,
                'type' => 'cash',
            ],
        ];

        $result = $this->service->validateSplitPayments($payments, 100.00);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('Insufficient payment', $result['errors'][0]);
    }

    /** @test */
    public function it_calculates_overpayment()
    {
        $payments = [
            [
                'method_id' => 1,
                'method_name' => 'Cash',
                'amount' => 120.00,
                'type' => 'cash',
            ],
        ];

        $result = $this->service->validateSplitPayments($payments, 100.00);

        $this->assertTrue($result['valid']);
        $this->assertEquals(20.00, $result['overpayment']);
    }

    /** @test */
    public function it_validates_credit_limit()
    {
        $payments = [
            [
                'method_id' => 3,
                'method_name' => 'Store Credit',
                'amount' => 200.00,
                'type' => 'credit',
            ],
        ];

        $context = [
            'customer_credit_limit' => 500.00,
            'customer_balance' => 350.00, // Only 150 available
        ];

        $result = $this->service->validateSplitPayments($payments, 200.00, $context);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('exceeds available credit', $result['errors'][0]);
    }

    /** @test */
    public function it_calculates_change_with_bill_breakdown()
    {
        $result = $this->service->calculateChange(150.00, 123.45);

        $this->assertEquals(150.00, $result['amount_paid']);
        $this->assertEquals(123.45, $result['total_amount']);
        $this->assertEquals(26.55, $result['change_due']);
        $this->assertTrue($result['requires_change']);

        // Verify bill breakdown
        $this->assertArrayHasKey('twenty_dollar', $result['bill_breakdown']);
        $this->assertArrayHasKey('five_dollar', $result['bill_breakdown']);
        $this->assertArrayHasKey('one_dollar', $result['bill_breakdown']);
        $this->assertArrayHasKey('quarter', $result['bill_breakdown']);
        $this->assertArrayHasKey('dime', $result['bill_breakdown']);
        $this->assertArrayHasKey('nickel', $result['bill_breakdown']);
    }

    /** @test */
    public function it_calculates_no_change_when_exact_payment()
    {
        $result = $this->service->calculateChange(100.00, 100.00);

        $this->assertEquals(0, $result['change_due']);
        $this->assertFalse($result['requires_change']);
        $this->assertEmpty($result['bill_breakdown']);
    }

    /** @test */
    public function it_calculates_tip_by_percentage()
    {
        $result = $this->service->calculateTip(100.00, 15);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(15.00, $result['tip_percentage']);
        $this->assertEquals(15.00, $result['tip_amount']);
        $this->assertEquals(115.00, $result['total_with_tip']);
        $this->assertTrue($result['has_tip']);
    }

    /** @test */
    public function it_calculates_custom_tip_amount()
    {
        $result = $this->service->calculateTip(100.00, 0, 25.00);

        $this->assertEquals(25.00, $result['tip_amount']);
        $this->assertEquals(25.00, $result['tip_percentage']); // Effective percentage
        $this->assertEquals(125.00, $result['total_with_tip']);
    }

    /** @test */
    public function it_handles_zero_tip()
    {
        $result = $this->service->calculateTip(100.00, 0);

        $this->assertEquals(0, $result['tip_amount']);
        $this->assertFalse($result['has_tip']);
    }

    /** @test */
    public function it_calculates_total_with_tip_and_tax()
    {
        $result = $this->service->calculateTotalWithTip(100.00, 8.00, 15.00);

        $this->assertEquals(100.00, $result['subtotal']);
        $this->assertEquals(8.00, $result['tax']);
        $this->assertEquals(15.00, $result['tip']);
        $this->assertEquals(123.00, $result['total']);
    }

    /** @test */
    public function it_allocates_tip_proportionally_across_payments()
    {
        $payments = [
            ['method_id' => 1, 'method_name' => 'Cash', 'amount' => 50.00],
            ['method_id' => 2, 'method_name' => 'Card', 'amount' => 50.00],
        ];

        $result = $this->service->allocateTipAcrossPayments($payments, 20.00);

        $this->assertCount(2, $result);
        $this->assertEquals(10.00, $result[0]['tip_amount']);
        $this->assertEquals(10.00, $result[1]['tip_amount']);
        $this->assertEquals(60.00, $result[0]['total_with_tip']);
        $this->assertEquals(60.00, $result[1]['total_with_tip']);
    }

    /** @test */
    public function it_allocates_tip_with_rounding_adjustment()
    {
        $payments = [
            ['method_id' => 1, 'method_name' => 'Cash', 'amount' => 33.33],
            ['method_id' => 2, 'method_name' => 'Card', 'amount' => 33.33],
            ['method_id' => 3, 'method_name' => 'Credit', 'amount' => 33.34],
        ];

        $result = $this->service->allocateTipAcrossPayments($payments, 10.00);

        // Last payment should have rounding adjustment
        $totalTip = array_sum(array_column($result, 'tip_amount'));
        $this->assertEquals(10.00, round($totalTip, 2));
    }

    /** @test */
    public function it_validates_cash_payment_sufficient()
    {
        $result = $this->service->validateCashPayment(100.00, 80.00);

        $this->assertTrue($result['valid']);
        $this->assertEquals(20.00, $result['change_due']);
    }

    /** @test */
    public function it_rejects_insufficient_cash_payment()
    {
        $result = $this->service->validateCashPayment(50.00, 80.00);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    /** @test */
    public function it_formats_payment_summary_for_receipt()
    {
        $payments = [
            ['method_name' => 'Cash', 'amount' => 60.00, 'type' => 'cash'],
            ['method_name' => 'Card', 'amount' => 40.00, 'type' => 'card'],
        ];

        $result = $this->service->formatPaymentSummary($payments, 95.00, 5.00);

        $this->assertEquals(100.00, $result['subtotal_payments']);
        $this->assertEquals(95.00, $result['sale_total']);
        $this->assertEquals(5.00, $result['tip']);
        $this->assertEquals(0, $result['change_due']); // 100 - 95 - 5 = 0
        $this->assertEquals(2, $result['payment_count']);
    }

    /** @test */
    public function it_determines_fully_paid_status()
    {
        $this->assertTrue($this->service->isFullyPaid(100.00, 100.00));
        $this->assertTrue($this->service->isFullyPaid(100.00, 100.01));
        $this->assertFalse($this->service->isFullyPaid(100.00, 99.99));
    }

    /** @test */
    public function it_gets_payment_status()
    {
        $this->assertEquals('paid', $this->service->getPaymentStatus(100.00, 100.00));
        $this->assertEquals('partial', $this->service->getPaymentStatus(100.00, 50.00));
        $this->assertEquals('unpaid', $this->service->getPaymentStatus(100.00, 0));
    }

    /** @test */
    public function it_calculates_remaining_balance()
    {
        $this->assertEquals(50.00, $this->service->getRemainingBalance(100.00, 50.00));
        $this->assertEquals(0, $this->service->getRemainingBalance(100.00, 100.00));
        $this->assertEquals(0, $this->service->getRemainingBalance(100.00, 120.00)); // No negative
    }

    /** @test */
    public function it_handles_complex_split_payment_scenario()
    {
        // Real-world scenario: $150 sale with tip, split across 3 payment methods
        $subtotal = 130.00;
        $tax = 10.40;
        $tip = 19.50; // 15%
        $total = $subtotal + $tax + $tip; // 159.90

        $payments = [
            ['method_id' => 1, 'method_name' => 'Cash', 'amount' => 60.00, 'type' => 'cash'],
            ['method_id' => 2, 'method_name' => 'Gift Card', 'amount' => 50.00, 'type' => 'gift_card'],
            ['method_id' => 3, 'method_name' => 'Credit Card', 'amount' => 49.90, 'type' => 'card'],
        ];

        $validation = $this->service->validateSplitPayments($payments, $total);
        $this->assertTrue($validation['valid']);

        $change = $this->service->calculateChange(159.90, $total);
        $this->assertEquals(0, $change['change_due']);

        $summary = $this->service->formatPaymentSummary($payments, $total, $tip);
        $this->assertEquals(159.90, $summary['subtotal_payments']);
    }

    /** @test */
    public function it_prevents_negative_payment_amounts()
    {
        $payments = [
            ['method_id' => 1, 'method_name' => 'Cash', 'amount' => -10.00, 'type' => 'cash'],
        ];

        $result = $this->service->validateSplitPayments($payments, 100.00);
        $this->assertFalse($result['valid']);
    }

    /** @test */
    public function it_handles_very_small_amounts()
    {
        $result = $this->service->calculateChange(0.05, 0.03);
        $this->assertEquals(0.02, $result['change_due']);
        $this->assertArrayHasKey('penny', $result['bill_breakdown']);
        $this->assertEquals(2, $result['bill_breakdown']['penny']['count']);
    }

    /** @test */
    public function it_handles_large_cash_payments()
    {
        $result = $this->service->calculateChange(500.00, 123.45);
        $this->assertEquals(376.55, $result['change_due']);
        
        $this->assertArrayHasKey('hundred_dollar', $result['bill_breakdown']);
        $this->assertEquals(3, $result['bill_breakdown']['hundred_dollar']['count']);
    }
}
