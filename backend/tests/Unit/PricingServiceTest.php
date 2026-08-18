<?php

namespace Tests\Unit;

use App\Services\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    protected PricingService $pricingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricingService = new PricingService(null);
    }

    /** @test */
    public function it_calculates_line_item_pricing_without_discounts()
    {
        $item = [
            'product_id' => 1,
            'quantity' => 2,
            'unit_price' => 100.00,
            'tax_rate' => 0.10,
        ];

        $result = $this->pricingService->calculateLineItemPricing($item);

        $this->assertEquals(200.00, $result['gross_amount']);
        $this->assertEquals(0, $result['discount_amount']);
        $this->assertEquals(200.00, $result['taxable_amount']);
        $this->assertEquals(20.00, $result['tax_amount']);
        $this->assertEquals(220.00, $result['total_amount']);
    }

    /** @test */
    public function it_calculates_line_item_pricing_with_percentage_discount()
    {
        $item = [
            'product_id' => 1,
            'quantity' => 3,
            'unit_price' => 50.00,
            'tax_rate' => 0.08,
            'discount_type' => 'percentage',
            'discount_amount' => 20,
        ];

        $result = $this->pricingService->calculateLineItemPricing($item);

        $this->assertEquals(150.00, $result['gross_amount']);
        $this->assertEquals(30.00, $result['discount_amount']); // 20% of 150
        $this->assertEquals(120.00, $result['amount_after_line_discount']);
        $this->assertEquals(9.60, $result['tax_amount']); // 8% of 120
        $this->assertEquals(129.60, $result['total_amount']);
    }

    /** @test */
    public function it_calculates_line_item_pricing_with_fixed_discount()
    {
        $item = [
            'product_id' => 1,
            'quantity' => 1,
            'unit_price' => 100.00,
            'tax_rate' => 0.10,
            'discount_type' => 'fixed',
            'discount_amount' => 25.00,
        ];

        $result = $this->pricingService->calculateLineItemPricing($item);

        $this->assertEquals(100.00, $result['gross_amount']);
        $this->assertEquals(25.00, $result['discount_amount']);
        $this->assertEquals(75.00, $result['amount_after_line_discount']);
        $this->assertEquals(7.50, $result['tax_amount']);
        $this->assertEquals(82.50, $result['total_amount']);
    }

    /** @test */
    public function it_prevents_discount_exceeding_gross_amount()
    {
        $item = [
            'product_id' => 1,
            'quantity' => 1,
            'unit_price' => 50.00,
            'tax_rate' => 0.10,
            'discount_type' => 'fixed',
            'discount_amount' => 100.00, // More than gross amount
        ];

        $result = $this->pricingService->calculateLineItemPricing($item);

        $this->assertEquals(50.00, $result['gross_amount']);
        $this->assertEquals(50.00, $result['discount_amount']); // Capped at gross amount
        $this->assertEquals(0, $result['taxable_amount']);
        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals(0, $result['total_amount']);
    }

    /** @test */
    public function it_calculates_cart_pricing_with_multiple_items()
    {
        $cartItems = [
            [
                'product_id' => 1,
                'quantity' => 2,
                'unit_price' => 100.00,
                'tax_rate' => 0.10,
            ],
            [
                'product_id' => 2,
                'quantity' => 1,
                'unit_price' => 50.00,
                'tax_rate' => 0.10,
            ],
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems);

        $this->assertEquals(250.00, $result['summary']['subtotal_before_discounts']);
        $this->assertEquals(0, $result['summary']['line_discounts_total']);
        $this->assertEquals(250.00, $result['summary']['subtotal_after_line_discounts']);
        $this->assertEquals(25.00, $result['summary']['total_tax']);
        $this->assertEquals(275.00, $result['summary']['grand_total']);
    }

    /** @test */
    public function it_applies_global_percentage_discount()
    {
        $cartItems = [
            [
                'product_id' => 1,
                'quantity' => 2,
                'unit_price' => 100.00,
                'tax_rate' => 0.10,
            ],
        ];

        $discounts = [
            'global_discount_type' => 'percentage',
            'global_discount_amount' => 10,
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems, $discounts);

        $this->assertEquals(200.00, $result['summary']['subtotal_before_discounts']);
        $this->assertEquals(20.00, $result['summary']['global_discount_value']); // 10% of 200
        $this->assertEquals(180.00, $result['summary']['final_subtotal']);
        $this->assertEquals(18.00, $result['summary']['total_tax']);
        $this->assertEquals(198.00, $result['summary']['grand_total']);
    }

    /** @test */
    public function it_applies_global_fixed_discount()
    {
        $cartItems = [
            [
                'product_id' => 1,
                'quantity' => 1,
                'unit_price' => 100.00,
                'tax_rate' => 0.10,
            ],
        ];

        $discounts = [
            'global_discount_type' => 'fixed',
            'global_discount_amount' => 25.00,
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems, $discounts);

        $this->assertEquals(100.00, $result['summary']['subtotal_before_discounts']);
        $this->assertEquals(25.00, $result['summary']['global_discount_value']);
        $this->assertEquals(75.00, $result['summary']['final_subtotal']);
        $this->assertEquals(7.50, $result['summary']['total_tax']);
        $this->assertEquals(82.50, $result['summary']['grand_total']);
    }

    /** @test */
    public function it_applies_coupon_and_loyalty_discounts()
    {
        $cartItems = [
            [
                'product_id' => 1,
                'quantity' => 1,
                'unit_price' => 200.00,
                'tax_rate' => 0.10,
            ],
        ];

        $discounts = [
            'coupon_discount' => 20.00,
            'loyalty_discount' => 10.00,
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems, $discounts);

        $this->assertEquals(200.00, $result['summary']['subtotal_before_discounts']);
        $this->assertEquals(20.00, $result['summary']['coupon_discount']);
        $this->assertEquals(10.00, $result['summary']['loyalty_discount']);
        $this->assertEquals(170.00, $result['summary']['final_taxable_amount']);
        $this->assertEquals(17.00, $result['summary']['total_tax']);
        $this->assertEquals(187.00, $result['summary']['grand_total']);
    }

    /** @test */
    public function it_calculates_shipping()
    {
        $cartItems = [
            [
                'product_id' => 1,
                'quantity' => 1,
                'unit_price' => 100.00,
                'tax_rate' => 0.10,
            ],
        ];

        $discounts = [
            'shipping_amount' => 15.00,
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems, $discounts);

        $this->assertEquals(110.00, $result['summary']['subtotal_total']);
        $this->assertEquals(15.00, $result['summary']['shipping_amount']);
        $this->assertEquals(125.00, $result['summary']['grand_total']);
    }

    /** @test */
    public function it_validates_pricing_successfully()
    {
        $cartItems = [
            [
                'product_id' => 1,
                'quantity' => 2,
                'unit_price' => 50.00,
                'tax_rate' => 0.10,
            ],
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems);
        $isValid = $this->pricingService->validatePricing($result);

        $this->assertTrue($isValid);
    }

    /** @test */
    public function it_formats_pricing_for_receipt()
    {
        $cartItems = [
            [
                'product_id' => 1,
                'quantity' => 1,
                'unit_price' => 100.00,
                'tax_rate' => 0.10,
            ],
        ];

        $discounts = [
            'global_discount_type' => 'percentage',
            'global_discount_amount' => 10,
            'coupon_discount' => 5.00,
            'shipping_amount' => 10.00,
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems, $discounts);
        $receiptLines = $this->pricingService->formatForReceipt($result);

        $this->assertIsArray($receiptLines);
        $this->assertGreaterThan(0, count($receiptLines));
        
        // Check total line exists
        $totalLine = collect($receiptLines)->firstWhere('is_total', true);
        $this->assertNotNull($totalLine);
        $this->assertEquals('TOTAL', $totalLine['label']);
    }

    /** @test */
    public function it_calculates_simple_discount()
    {
        $discount = $this->pricingService->calculateDiscount(100.00, 'percentage', 15);
        $this->assertEquals(15.00, $discount);

        $discount = $this->pricingService->calculateDiscount(100.00, 'fixed', 25.00);
        $this->assertEquals(25.00, $discount);
    }

    /** @test */
    public function it_calculates_simple_tax()
    {
        $tax = $this->pricingService->calculateTax(200.00, 0.08);
        $this->assertEquals(16.00, $tax);
    }

    /** @test */
    public function it_handles_zero_quantity()
    {
        $item = [
            'product_id' => 1,
            'quantity' => 0,
            'unit_price' => 100.00,
            'tax_rate' => 0.10,
        ];

        $result = $this->pricingService->calculateLineItemPricing($item);

        $this->assertEquals(0, $result['gross_amount']);
        $this->assertEquals(0, $result['total_amount']);
    }

    /** @test */
    public function it_handles_decimal_quantities()
    {
        $item = [
            'product_id' => 1,
            'quantity' => 1.5,
            'unit_price' => 100.00,
            'tax_rate' => 0.10,
        ];

        $result = $this->pricingService->calculateLineItemPricing($item);

        $this->assertEquals(150.00, $result['gross_amount']);
        $this->assertEquals(15.00, $result['tax_amount']);
        $this->assertEquals(165.00, $result['total_amount']);
    }

    /** @test */
    public function it_maintains_precision_with_multiple_items()
    {
        $cartItems = [
            ['product_id' => 1, 'quantity' => 3, 'unit_price' => 33.33, 'tax_rate' => 0.10],
            ['product_id' => 2, 'quantity' => 2, 'unit_price' => 16.67, 'tax_rate' => 0.10],
            ['product_id' => 3, 'quantity' => 1, 'unit_price' => 99.99, 'tax_rate' => 0.10],
        ];

        $result = $this->pricingService->calculateCartPricing($cartItems);

        // Verify no negative values
        foreach ($result['summary'] as $key => $value) {
            $this->assertGreaterThanOrEqual(0, $value, "Field {$key} should not be negative");
        }

        // Verify mathematical relationships
        $this->assertEquals(
            round($result['summary']['subtotal_before_discounts'] - $result['summary']['line_discounts_total'], 2),
            $result['summary']['subtotal_after_line_discounts']
        );
    }
}
