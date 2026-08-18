<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Services\Accounting\AccountBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class POSService extends BaseService
{
    protected JournalAutoPostService $journalService;

    protected SequenceService $sequenceService;

    protected CreditSaleService $creditSaleService;

    protected DiscountService $discountService;

    protected LoyaltyService $loyaltyService;

    protected AccountBalanceService $balanceService;

    public function __construct(
        JournalAutoPostService $journalService,
        SequenceService $sequenceService,
        CreditSaleService $creditSaleService,
        DiscountService $discountService,
        LoyaltyService $loyaltyService,
        AccountBalanceService $balanceService
    ) {
        parent::__construct(null);
        $this->journalService = $journalService;
        $this->sequenceService = $sequenceService;
        $this->creditSaleService = $creditSaleService;
        $this->discountService = $discountService;
        $this->loyaltyService = $loyaltyService;
        $this->balanceService = $balanceService;
    }

    /**
     * Get products for POS
     */
    public function getProducts(array $filters = [], int $perPage = 15)
    {
        $query = Product::with([
            'category',
            'baseUnit',
            'productUnits.unit',
            'stockLevels',
            'batches' => function ($q) {
                $q->active()->withStock();
            },
            'serialNumbers' => function ($q) {
                $q->inStock();
            },
        ])
            ->where('is_active', true)
            ->where('is_sellable', true);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('sku', 'like', '%'.$filters['search'].'%')
                    ->orWhere('barcode', 'like', '%'.$filters['search'].'%');
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['ids'])) {
            $ids = is_array($filters['ids'])
                ? $filters['ids']
                : array_filter(array_map('intval', explode(',', (string) $filters['ids'])));
            if (! empty($ids)) {
                $query->whereIn('id', $ids);
            }
        }

        return $query->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Find product by barcode
     */
    public function findByBarcode(string $barcode)
    {
        // 1. Check for standard scale/dynamic barcodes
        // Typical format: Prefix(2) + ItemCode(5) + Weight/Price(5) + CheckDigit(1)
        // e.g., 20XXXXXWWWWWC where 20 is prefix, X is item code, W is weight/price, C is check digit
        // Common prefixes: 20-29 for in-store items.
        if (strlen($barcode) === 13 && preg_match('/^(20|21|22|23|24|25|26|27|28|29)/', $barcode)) {
            $itemCode = substr($barcode, 2, 5);
            $dynamicValueString = substr($barcode, 7, 5);

            // Decimal format is usually WW.WWW (kg) or PPP.PP (price)
            $dynamicValue = (float) $dynamicValueString / 1000;

            // Find product by item code (assuming SKU or special standard field holds the 5-digit item code)
            // We'll search SKU first, then generic barcode check.
            $product = Product::where('sku', 'like', "%{$itemCode}%")
                ->orWhere('barcode', 'like', "%{$itemCode}%")
                ->where('is_active', true)
                ->where('is_sellable', true)
                ->with([
                    'category',
                    'baseUnit',
                    'productUnits.unit',
                    'batches' => function ($q) {
                        $q->active()->withStock();
                    },
                    'serialNumbers' => function ($q) {
                        $q->inStock();
                    },
                ])
                ->first();

            if ($product) {
                // Determine if it's quantity-based (weight/volume/length) or price-based.
                // We'll treat prefixes 20, 22, 24 as quantity (e.g., kg, L, m) with 3 decimal places (WW.WWW)
                // We'll treat prefixes 21, 23, 25-29 as total price with 2 decimal places (PPP.PP)
                $prefix = substr($barcode, 0, 2);
                if (in_array($prefix, ['20', '22', '24'])) {
                    // e.g. 00200 = 0.200 (kg/L/m), 01000 = 1.000, etc.
                    $product->dynamic_quantity = $dynamicValueString / 1000;
                } else {
                    // Barcode encodes total price (e.g. 01250 = 12.50)
                    // We calculate quantity = total_price / unit_price
                    $totalPriceEncoded = (float) $dynamicValueString / 100;
                    if ($product->selling_price > 0) {
                        $product->dynamic_quantity = round($totalPriceEncoded / $product->selling_price, 6);
                    } else {
                        $product->dynamic_quantity = 1;
                    }
                }

                // Original barcode kept for reference
                $product->scanned_barcode = $barcode;

                return $product;
            }
        }

        // 2. First check regular product-level barcode
        $product = Product::where('barcode', $barcode)
            ->where('is_active', true)
            ->where('is_sellable', true)
            ->with([
                'category',
                'baseUnit',
                'productUnits.unit',
                'batches' => function ($q) {
                    $q->active()->withStock();
                },
                'serialNumbers' => function ($q) {
                    $q->inStock();
                },
            ])
            ->first();

        if ($product) {
            return $product;
        }

        // Fallback: check ProductUnit barcodes
        $productUnit = ProductUnit::where('barcode', $barcode)
            ->whereHas('product', function ($q) {
                $q->where('is_active', true)->where('is_sellable', true);
            })
            ->with('unit')
            ->first();

        if ($productUnit) {
            $product = $productUnit->product()->with([
                'category',
                'baseUnit',
                'productUnits.unit',
                'batches' => function ($q) {
                    $q->active()->withStock();
                },
                'serialNumbers' => function ($q) {
                    $q->inStock();
                },
            ])->first();

            if ($product) {
                // Flag the matched unit so frontend can auto-select it
                $product->matched_unit_id = $productUnit->unit_id;
            }

            return $product;
        }

        return null;
    }

    /**
     * Create a POS sale
     */
    public function createSale(array $data)
    {
        // Verify register session is open
        $registerSession = RegisterSession::with('cashRegister.branch.warehouses')->find($data['register_session_id']);
        if (! $registerSession || $registerSession->status !== 'open') {
            throw new \Exception('Register session is not open.');
        }

        $branch = $registerSession->cashRegister->branch;
        $warehouse = $branch->warehouses->first();

        if (! $warehouse) {
            // Fallback: If no warehouse linked to branch, try to find a default one for the tenant
            $warehouse = \App\Models\Warehouse::where('tenant_id', auth()->user()->tenant_id)
                ->where('is_active', true)
                ->first();
        }

        if (! $warehouse) {
            throw new \Exception('No warehouse assigned to this branch or found in system.');
        }

        DB::beginTransaction();
        try {
            // Task 9: Fiscal period open-check — only enforced when a fiscal year is configured
            $tenantId = auth()->user()->tenant_id;
            $saleDate = now()->toDateString();
            $periodInfo = $this->balanceService->resolvePeriodForDate($tenantId, $saleDate);
            if ($periodInfo['fiscal_year_id'] !== null && ($periodInfo['is_closed'] ?? false)) {
                $periodName = $periodInfo['accounting_period']?->name ?? $saleDate;
                throw new \Exception("Accounting period '{$periodName}' is closed. Sales cannot be posted to a closed period.");
            }

            $coupon = null;
            $couponDiscount = 0;
            if (! empty($data['coupon_code'])) {
                $coupon = $this->discountService->validateCoupon($data['coupon_code'], (float) $data['total_amount'], $data['items']);
                $couponDiscount = $this->discountService->calculateDiscount($coupon, (float) $data['total_amount'], $data['items']);
            }

            $pointsRedeemed = 0;
            $loyaltyDiscount = 0;
            if (! empty($data['points_to_redeem']) && ! empty($data['customer_id'])) {
                $customer = \App\Models\Customer::find($data['customer_id']);
                if ($customer) {
                    $pointsToRedeem = (float) $data['points_to_redeem'];
                    // We don't have the sale yet, so we'll pass a dummy sale or handle validation first
                    // Actually, let's just pre-calculate the value.
                    $loyaltyDiscount = $this->loyaltyService->calculateRedemptionValue($pointsToRedeem, auth()->user()->tenant_id);
                    $pointsRedeemed = $pointsToRedeem;
                }
            }

            $totalAfterDiscounts = (float) $data['total_amount'] - $couponDiscount - $loyaltyDiscount;

            $saleNumber = $this->sequenceService->generateReference('sale', 'SL');

            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'customer_id' => $data['customer_id'] ?? null,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'sale_date' => now(),
                'subtotal' => $data['total_amount'] - ($data['tax_amount'] ?? 0) - ($data['shipping_cost'] ?? 0),
                'discount_amount' => $data['discount_amount'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'shipping_amount' => $data['shipping_cost'] ?? 0,
                'coupon_id' => $coupon ? $coupon->id : null,
                'coupon_discount_amount' => $couponDiscount,
                'points_redeemed' => $pointsRedeemed,
                'loyalty_discount_amount' => $loyaltyDiscount,
                'total' => $totalAfterDiscounts,
                'paid_amount' => min($totalAfterDiscounts, $data['paid_amount']), // Ensure paid_amount <= total
                'change_amount' => max(0, $data['paid_amount'] - $totalAfterDiscounts),
                'balance_due' => max(0, $totalAfterDiscounts - $data['paid_amount']),
                'payment_status' => $this->determinePaymentStatus((float) $totalAfterDiscounts, (float) $data['paid_amount']),
                'status' => 'completed',
                'type' => $data['type'] ?? 'walk-in',
                'tenant_id' => auth()->user()->tenant_id,
                'sold_by' => auth()->id(),
                'register_session_id' => $data['register_session_id'],
                'restaurant_table_id' => $data['restaurant_table_id'] ?? null,
                'order_type' => $data['order_type'] ?? 'takeaway',
            ]);

            // Server-side total validation: recalculate from items to prevent price manipulation
            $serverCalculatedTotal = 0;
            foreach ($data['items'] as $validationItem) {
                $lineSubtotal = (float) $validationItem['quantity'] * (float) $validationItem['unit_price'];
                $lineDiscountAmt = (float) ($validationItem['discount_amount'] ?? 0);
                if (! empty($validationItem['discount_percent'])) {
                    $lineDiscountAmt += $lineSubtotal * ((float) $validationItem['discount_percent'] / 100);
                }
                $lineDiscountAmt = min($lineDiscountAmt, $lineSubtotal);
                $lineAfterDiscount = $lineSubtotal - $lineDiscountAmt;
                $lineTaxAmt = (float) ($validationItem['tax_amount'] ?? 0);
                if ($lineTaxAmt === 0.0 && ! empty($validationItem['tax_percent'])) {
                    $lineTaxAmt = $lineAfterDiscount * ((float) $validationItem['tax_percent'] / 100);
                }
                $serverCalculatedTotal += ($lineAfterDiscount + $lineTaxAmt);
            }

            // Allow 1% tolerance for JS/PHP floating-point rounding differences
            $submittedTotal = (float) $data['total_amount'];
            $tolerance = max(1.00, $serverCalculatedTotal * 0.01);
            if (abs($serverCalculatedTotal - $submittedTotal) > $tolerance) {
                throw new \Exception(
                    'Total mismatch: submitted '.number_format($submittedTotal, 2).
                    ' but server calculated '.number_format($serverCalculatedTotal, 2).
                    '. Please refresh and try again.'
                );
            }

            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                if (! $product) {
                    continue;
                }

                // Resolve Batch if selected
                $batchId = $item['batch_id'] ?? null;
                $batch = null;
                if ($batchId) {
                    $batch = \App\Models\Batch::find($batchId);
                }

                // Base pricing (overridden by batch if available)
                $baseSellingPrice = (float) ($product->selling_price ?? 0);
                $baseCostPrice = (float) ($product->cost_price ?? 0);

                if ($batch && $batch->selling_price > 0) {
                    $baseSellingPrice = (float) $batch->selling_price;
                }
                if ($batch && $batch->cost_price > 0) {
                    $baseCostPrice = (float) $batch->cost_price;
                }

                // Multi-unit: resolve the correct selling price and conversion factor
                $conversionFactor = 1;
                $productUnit = null;
                $productSellingPrice = $baseSellingPrice;
                $productCostPrice = $baseCostPrice;

                if (! empty($item['unit_id']) && $item['unit_id'] != $product->base_unit_id) {
                    $productUnit = ProductUnit::where('product_id', $product->id)
                        ->where('unit_id', $item['unit_id'])
                        ->first();
                    if ($productUnit) {
                        $conversionFactor = (float) ($productUnit->conversion_factor ?: 1);

                        // Use explicit unit-specific price if set, OTHERWISE multiply the base price by conversion factor
                        if ($productUnit->selling_price > 0) {
                            $productSellingPrice = (float) $productUnit->selling_price;
                        } else {
                            $productSellingPrice = $baseSellingPrice * $conversionFactor;
                        }

                        if ($productUnit->cost_price > 0) {
                            $productCostPrice = (float) $productUnit->cost_price;
                        } else {
                            $productCostPrice = $baseCostPrice * $conversionFactor;
                        }
                    }
                }

                $quantityInBaseUnit = (float) $item['quantity'] * $conversionFactor;

                // SERVER-SIDE PRICE VALIDATION: prevent price manipulation.
                // The submitted unit_price must be at least 80% of the resolved selling price.
                $submittedPrice = (float) $item['unit_price'];
                if ($productSellingPrice > 0 && $submittedPrice < ($productSellingPrice * 0.80)) {
                    // Check if manager override is provided
                    if (! empty($data['manager_override_by'])) {
                        $overrideUser = \App\Models\User::find($data['manager_override_by']);
                        if ($overrideUser && $overrideUser->hasAnyRole(['admin', 'manager', 'super-admin'])) {
                            \Log::info("Manager override by {$overrideUser->name} (ID: {$overrideUser->id}) for price on '{$product->name}': ".
                                number_format($submittedPrice, 2).' (floor: '.number_format($productSellingPrice * 0.80, 2).')');
                        } else {
                            throw new \Exception('Invalid manager override credentials.');
                        }
                    } else {
                        throw new \Exception(
                            "Unit price for '{$product->name}' (".number_format($submittedPrice, 2).') '.
                            'is more than 20% below the valid selling price ('.number_format($productSellingPrice, 2).'). '.
                            'A manager override is required for larger discounts.'
                        );
                    }
                }

                // Handle Batch tracking
                $batchId = $item['batch_id'] ?? null;
                if ($batchId) {
                    $batch = \App\Models\Batch::find($batchId);
                    if ($batch) {
                        $batch->decrement('quantity_remaining', $quantityInBaseUnit);
                    }
                }

                // Find or create stock level for this warehouse (optionally by batch)
                $stockLevel = \App\Models\StockLevel::firstOrCreate([
                    'tenant_id' => auth()->user()->tenant_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'variant_id' => $item['variant_id'] ?? null,
                    'batch_id' => $batchId,
                ]);

                $quantityBefore = $stockLevel->quantity;

                // Stop negative stock if tracked and negative not allowed
                if ($product->track_inventory && ! $product->allow_negative_stock) {
                    $available = (float) $stockLevel->available_quantity;
                    if ($available < $quantityInBaseUnit) {
                        throw new \Exception("Insufficient stock for '{$product->name}'. Available: {$available}, Required: {$quantityInBaseUnit}");
                    }
                }

                $stockLevel->decrement('quantity', $quantityInBaseUnit);
                $quantityAfter = $stockLevel->fresh()->quantity;

                // Create stock movement record (always in base unit)
                \App\Models\StockMovement::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'variant_id' => $item['variant_id'] ?? null,
                    'batch_id' => $batchId,
                    'unit_id' => $product->base_unit_id,
                    'reference_type' => 'Sale',
                    'reference_id' => $sale->id,
                    'type' => 'out',
                    'quantity' => $quantityInBaseUnit,
                    'quantity_before' => $quantityBefore,
                    'quantity_after' => $quantityAfter,
                    'unit_cost' => $product->cost_price ?? 0,
                    'created_by' => auth()->id(),
                ]);

                // Fix 4: compute SaleItem total correctly (subtotal âˆ’ discount + tax)
                $lineSubtotal = (float) $item['quantity'] * (float) $item['unit_price'];
                $lineDiscountAmt = (float) ($item['discount_amount'] ?? 0);
                if ($lineDiscountAmt === 0.0 && ! empty($item['discount_percent'])) {
                    $lineDiscountAmt = $lineSubtotal * ((float) $item['discount_percent'] / 100);
                }
                $lineAfterDiscount = $lineSubtotal - $lineDiscountAmt;
                $lineTaxAmt = (float) ($item['tax_amount'] ?? 0);
                if ($lineTaxAmt === 0.0 && ! empty($item['tax_percent'])) {
                    $lineTaxAmt = $lineAfterDiscount * ((float) $item['tax_percent'] / 100);
                }
                $lineTotal = $lineAfterDiscount + $lineTaxAmt;

                $saleItem = SaleItem::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'batch_id' => $batchId,
                    'serial_number_id' => $item['serial_number_id'] ?? null,
                    'unit_id' => $item['unit_id'] ?? $product->base_unit_id ?? 1,
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'base_quantity' => $quantityInBaseUnit,
                    'conversion_factor' => $conversionFactor,
                    'unit_price' => $item['unit_price'],
                    'discount' => $lineDiscountAmt,
                    'tax' => $lineTaxAmt,
                    'tax_rate' => $item['tax_percent'] ?? 0,
                    'total' => $lineTotal,
                    'cost_price' => $productCostPrice,
                ]);

                // Handle Serial Number tracking
                if (! empty($item['serial_number_id'])) {
                    $serial = \App\Models\SerialNumber::find($item['serial_number_id']);
                    if ($serial) {
                        $serial->update([
                            'status' => 'sold',
                            'sale_item_id' => $saleItem->id,
                            'sold_to' => $sale->customer_id,
                            'sold_at' => now(),
                        ]);
                    }
                }
            }

            // Create payment records
            $payments = [];
            if (! empty($data['payments'])) {
                $payments = $data['payments'];
            } elseif (isset($data['payment_method_id'])) {
                $payments[] = [
                    'payment_method_id' => $data['payment_method_id'],
                    'amount' => $data['payment_amount'],
                    'reference' => null,
                    'notes' => $data['notes'] ?? null,
                ];
            }

            foreach ($payments as $paymentData) {
                $paymentMethod = \App\Models\PaymentMethod::find($paymentData['payment_method_id']);

                SalePayment::create([
                    'tenant_id' => auth()->user()->tenant_id,
                    'sale_id' => $sale->id,
                    'payment_method_id' => $paymentData['payment_method_id'],
                    'amount' => $paymentData['amount'],
                    'reference' => $paymentData['reference'] ?? null,
                    'notes' => $paymentData['notes'] ?? $data['notes'] ?? null,
                ]);

                // Handle customer credit
                if ($paymentMethod && $paymentMethod->type === 'credit' && $sale->customer_id) {
                    $customer = Customer::find($sale->customer_id);
                    if ($customer) {
                        $customer->increment('balance', $paymentData['amount']);

                        // Log credit transaction if needed
                        Log::info('Customer credit used', [
                            'customer_id' => $customer->id,
                            'amount' => $paymentData['amount'],
                            'sale_id' => $sale->id,
                        ]);
                    }
                }
            }

            // Handle Credit Sale record creation
            if (($data['type'] ?? 'walk-in') === 'credit') {
                $this->createCreditSaleRecord($sale, $data);
            }

            if ($coupon) {
                $this->discountService->incrementUsage($coupon);
            }

            if ($pointsRedeemed > 0) {
                $customer = \App\Models\Customer::find($data['customer_id']);
                $this->loyaltyService->redeemPoints($customer, $pointsRedeemed, $sale);
            }

            DB::commit();

            // Award loyalty points
            try {
                $this->awardLoyaltyPoints($sale);
            } catch (\Exception $e) {
                Log::warning('Loyalty points award failed', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            }

            // ── Post-commit side-effects (non-fatal) ────────────────────────
            // Task 5: Update accounting_status so failed postings are visible and retryable.
            try {
                $this->journalService->postSale($sale);
                $sale->update(['accounting_status' => 'posted']);
            } catch (\Exception $e) {
                Log::warning('JournalAutoPost failed', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
                $sale->update([
                    'accounting_status'        => 'failed',
                    'accounting_failure_reason' => substr($e->getMessage(), 0, 500),
                ]);
            }

            try {
                if (! empty($data['sold_by'])) {
                    $rule = CommissionRule::where('tenant_id', $sale->tenant_id)
                        ->where('is_active', true)
                        ->where(function ($q) use ($data) {
                            $q->where('user_id', $data['sold_by'])->orWhereNull('user_id');
                        })
                        ->orderByRaw('user_id IS NULL ASC')
                        ->first();
                    if ($rule) {
                        $amt = round((float) $sale->total * $rule->rate_percent / 100, 2);
                        SaleCommission::create([
                            'tenant_id' => $sale->tenant_id,
                            'sale_id' => $sale->id,
                            'user_id' => $data['sold_by'],
                            'sale_amount' => $sale->total,
                            'commission_rate' => $rule->rate_percent,
                            'commission_amount' => $amt,
                            'status' => 'pending',
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Commission auto-create failed', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            }

            return $sale->load(['customer', 'items.product', 'payments.paymentMethod', 'registerSession']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating POS sale', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    private function awardLoyaltyPoints(\App\Models\Sale $sale): void
    {
        if (! $sale->customer_id) {
            return;
        }

        $customer = \App\Models\Customer::find($sale->customer_id);
        if (! $customer) {
            return;
        }

        $earned = $this->loyaltyService->calculateEarnedPoints($sale);
        if ($earned > 0) {
            $this->loyaltyService->earnPoints($customer, $earned, $sale);
        }
    }

    private function determinePaymentStatus(float $total, float $paid): string
    {
        if ($paid >= $total) {
            return 'paid';
        }
        if ($paid > 0) {
            return 'partial';
        }

        return 'pending';
    }

    private function createCreditSaleRecord(Sale $sale, array $data): void
    {
        $financedAmount = (float) $sale->total - (float) $sale->paid_amount;

        $this->creditSaleService->createCreditSale([
            'sale_id' => $sale->id, // Add required sale_id
            'customer_id' => $sale->customer_id,
            'total_amount' => $sale->total,
            'down_payment' => $sale->paid_amount,
            'financed_amount' => $financedAmount, // Use financed_amount as per model
            'interest_rate' => $data['interest_rate'] ?? 0,
            'interest_amount' => 0,
            'total_payable' => $financedAmount,
            'installment_count' => $data['installment_count'] ?? 1,
            'installment_frequency' => $data['installment_frequency'] ?? 'monthly',
            'start_date' => now(),
            'notes' => $data['notes'] ?? 'POS Credit Sale',
            'items' => array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'tax_percent' => $item['tax_percent'] ?? 0,
                ];
            }, $data['items']),
        ]);
    }
}
