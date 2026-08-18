<?php

namespace App\Services;

use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class LoyaltyService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new LoyaltyTransaction());
    }

    /**
     * Calculate points that can be earned from a sale.
     */
    public function calculateEarnedPoints(Sale $sale): float
    {
        $rule = LoyaltyRule::where('tenant_id', $sale->tenant_id)
            ->where('is_active', true)
            ->first();

        if (!$rule || (float)$rule->spend_amount <= 0) {
            return 0;
        }

        // Use total_amount from sale (after discounts)
        return floor((float)$sale->total / (float)$rule->spend_amount) * (float)$rule->points_earned;
    }

    /**
     * Calculate the monetary value of loyalty points.
     */
    public function calculateRedemptionValue(float $points, int $tenantId): float
    {
        $rule = LoyaltyRule::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();

        if (!$rule) {
            return 0;
        }

        return $points * (float)$rule->point_value;
    }

    /**
     * Earn points for a customer from a sale.
     */
    public function earnPoints(Customer $customer, float $points, Sale $sale): void
    {
        if ($points <= 0) return;

        DB::transaction(function () use ($customer, $points, $sale) {
            $customer->increment('loyalty_points', $points);

            LoyaltyTransaction::create([
                'tenant_id' => $sale->tenant_id,
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'points' => $points,
                'type' => 'earned',
                'description' => "Earned from Sale #{$sale->sale_number}",
                'created_by' => auth()->id() ?? $sale->sold_by,
            ]);
        });
    }

    /**
     * Redeem points for a customer discount.
     */
    public function redeemPoints(Customer $customer, float $points, Sale $sale): float
    {
        if ($points <= 0) return 0;

        $rule = LoyaltyRule::where('tenant_id', $sale->tenant_id)
            ->where('is_active', true)
            ->first();

        if (!$rule) {
            throw new \Exception("Loyalty program is not active.");
        }

        if ($customer->loyalty_points < $points) {
            throw new \Exception("Insufficient loyalty points.");
        }

        if ($points < $rule->min_redeem_points) {
            throw new \Exception("Minimum of {$rule->min_redeem_points} points required for redemption.");
        }

        $redemptionValue = $this->calculateRedemptionValue($points, $sale->tenant_id);

        DB::transaction(function () use ($customer, $points, $sale, $redemptionValue) {
            $customer->decrement('loyalty_points', $points);

            LoyaltyTransaction::create([
                'tenant_id' => $sale->tenant_id,
                'customer_id' => $customer->id,
                'sale_id' => $sale->id,
                'points' => -$points,
                'type' => 'redeemed',
                'description' => "Redeemed for Sale #{$sale->sale_number}",
                'created_by' => auth()->id() ?? $sale->sold_by,
            ]);
        });

        return $redemptionValue;
    }
}
