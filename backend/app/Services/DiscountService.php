<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class DiscountService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new Coupon());
    }

    /**
     * Validate a coupon code and return the coupon if valid.
     *
     * @param string $code
     * @param float $totalAmount
     * @param array $items List of items in cart, each must have 'product_id'
     * @param int|null $tenantId
     * @return Coupon
     * @throws \Exception
     */
    public function validateCoupon(string $code, float $totalAmount, array $items = [], int $tenantId = null): Coupon
    {
        $tenantId = $tenantId ?? auth()->user()->tenant_id;

        $coupon = Coupon::with('categories')->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if (!$coupon) {
            throw new \Exception("Invalid coupon code.");
        }

        if (!$coupon->isActive()) {
            throw new \Exception("This coupon is expired or inactive.");
        }

        if ($totalAmount < $coupon->min_purchase_amount) {
            throw new \Exception("Minimum purchase amount of " . number_format($coupon->min_purchase_amount, 2) . " required for this coupon.");
        }

        // If coupon is restricted to categories, check if any item belongs to those categories
        if ($coupon->categories->isNotEmpty()) {
            $categoryIds = $coupon->categories->pluck('id')->toArray();
            $eligibleItemsFound = false;

            foreach ($items as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                if ($product && in_array($product->category_id, $categoryIds)) {
                    $eligibleItemsFound = true;
                    break;
                }
            }

            if (!$eligibleItemsFound) {
                throw new \Exception("This coupon is not applicable to any items in your cart.");
            }
        }

        return $coupon;
    }

    /**
     * Calculate discount amount for a coupon.
     *
     * @param Coupon $coupon
     * @param float $totalAmount Total order amount (used for fixed or if no categories)
     * @param array $items List of items in cart (used for category-wise calculation)
     * @return float
     */
    public function calculateDiscount(Coupon $coupon, float $totalAmount, array $items = []): float
    {
        $discountAmount = 0;
        $eligibleSubtotal = 0;
        $isRestricted = $coupon->categories->isNotEmpty();

        if ($isRestricted) {
            $categoryIds = $coupon->categories->pluck('id')->toArray();
            foreach ($items as $item) {
                $product = \App\Models\Product::find($item['product_id']);
                if ($product && in_array($product->category_id, $categoryIds)) {
                    // Item price * quantity
                    $eligibleSubtotal += ($item['unit_price'] * $item['quantity']);
                }
            }
        } else {
            $eligibleSubtotal = $totalAmount;
        }

        if ($coupon->type === 'percentage') {
            $discountAmount = $eligibleSubtotal * ($coupon->value / 100);
            if ($coupon->max_discount_amount > 0 && $discountAmount > $coupon->max_discount_amount) {
                $discountAmount = $coupon->max_discount_amount;
            }
        } elseif ($coupon->type === 'fixed') {
            // For fixed coupons, we just apply the value, but capped at the eligible subtotal if restricted
            $discountAmount = min($coupon->value, $eligibleSubtotal);
        }

        // Ensure discount doesn't exceed total
        return min($discountAmount, $totalAmount);
    }

    /**
     * Record coupon usage.
     *
     * @param Coupon $coupon
     */
    public function incrementUsage(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }
}
