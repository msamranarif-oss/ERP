<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\GoodsReceivedNote;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\PayrollPeriod;
use App\Models\PurchaseBill;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Services\Accounting\AccountBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalAutoPostService
{
    protected JournalEntryService $jeService;

    protected AccountBalanceService $balanceService;

    public function __construct(JournalEntryService $jeService, AccountBalanceService $balanceService)
    {
        $this->jeService = $jeService;
        $this->balanceService = $balanceService;
    }

    public function postPayroll(PayrollPeriod $period): ?JournalEntry
    {
        // Idempotency guard
        $existing = $this->isAlreadyPosted(PayrollPeriod::class, $period->id);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($period) {
                $tenantId = $period->tenant_id;

                $salaryExpense = $this->findAccount($tenantId, 'salary_expense');
                $salaryPayable = $this->findAccount($tenantId, 'salary_payable');
                $taxPayable = $this->findAccount($tenantId, 'withholding_tax')
                    ?? $this->findAccount($tenantId, 'tax_payable');

                $runs = $period->runs()->where('status', 'approved')->get();

                $grossTotal = $runs->sum('gross_earnings');
                $taxTotal = $runs->sum('tax_amount');
                $netTotal = $runs->sum('net_pay');

                if ($grossTotal <= 0) {
                    Log::warning('JournalAutoPost::postPayroll: gross total is zero, skipping.', ['period_id' => $period->id]);

                    return null;
                }

                $entryDate = $period->end_date instanceof \DateTimeInterface
                    ? $period->end_date->format('Y-m-d')
                    : (string) $period->end_date;

                $lines = [
                    [
                        'account_id' => $salaryExpense->id,
                        'debit' => round($grossTotal, 2),
                        'credit' => 0,
                        'tenant_id' => $tenantId,
                        'description' => 'Gross payroll expense',
                    ],
                    [
                        'account_id' => $salaryPayable->id,
                        'debit' => 0,
                        'credit' => round($netTotal, 2),
                        'tenant_id' => $tenantId,
                        'description' => 'Net salary payable',
                    ],
                ];

                if ($taxTotal > 0) {
                    $lines[] = [
                        'account_id' => $taxPayable->id,
                        'debit' => 0,
                        'credit' => round($taxTotal, 2),
                        'tenant_id' => $tenantId,
                        'description' => 'PAYE/WHT withheld',
                    ];
                }

                return $this->jeService->createJournalEntry([
                    'entry_date' => $entryDate,
                    'reference_type' => PayrollPeriod::class,
                    'reference_id' => $period->id,
                    'reference' => 'PAY-'.$period->id,
                    'description' => 'Auto-posted: Payroll — '.$period->name,
                    'status' => 'posted',
                    'is_auto_generated' => true,
                    'sequence' => 'payroll',
                    'lines' => $lines,
                ], $tenantId, $period->approved_by ?? 1);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postPayroll failed', [
                'period_id' => $period->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function postSale(Sale $sale): ?JournalEntry
    {
        // Idempotency guard
        $existing = $this->isAlreadyPosted(Sale::class, $sale->id);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($sale) {
                $tenantId = $sale->tenant_id;

                $arAccount = $this->findAccount($tenantId, 'accounts_receivable');
                $revenueAccount = $this->findAccount($tenantId, 'sales_revenue');
                $outputVatAccount = $this->findAccount($tenantId, 'output_vat');
                $salesDiscountsAccount = $this->findAccount($tenantId, 'sales_discounts');
                $cogsAccount = $this->findAccount($tenantId, 'cost_of_goods_sold');
                $inventoryAccount = $this->findAccount($tenantId, 'inventory_finished_goods')
                    ?? $this->findAccount($tenantId, 'inventory');

                $entryDate = $sale->sale_date instanceof \DateTimeInterface
                    ? $sale->sale_date->format('Y-m-d')
                    : ($sale->created_at?->toDateString() ?? now()->toDateString());

                $subtotal = (float) $sale->subtotal;
                $discountAmount = (float) ($sale->discount_amount ?? 0);
                $couponDiscount = (float) ($sale->coupon_discount_amount ?? 0);
                $loyaltyDiscount = (float) ($sale->loyalty_discount_amount ?? 0);
                $totalDiscount = $discountAmount + $couponDiscount + $loyaltyDiscount;
                $taxAmount = (float) ($sale->tax_amount ?? 0);
                $shippingAmount = (float) ($sale->shipping_amount ?? 0);
                $netRevenue = $subtotal - $totalDiscount;
                $invoiceTotal = $netRevenue + $taxAmount + $shippingAmount;
                $taxableAmount = $netRevenue;

                $lines = [];

                $lines[] = [
                    'account_id' => $arAccount->id,
                    'debit' => round($invoiceTotal, 2),
                    'credit' => 0,
                    'tenant_id' => $tenantId,
                    'description' => 'AR — Sale #'.($sale->sale_number ?? $sale->id),
                ];

                if ($totalDiscount > 0) {
                    $lines[] = [
                        'account_id' => $salesDiscountsAccount->id,
                        'debit' => round($totalDiscount, 2),
                        'credit' => 0,
                        'tenant_id' => $tenantId,
                        'description' => 'Sales discounts given',
                    ];
                }

                $lines[] = [
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => round($subtotal, 2),
                    'tenant_id' => $tenantId,
                    'description' => 'Sales revenue recognition',
                    'tax_rate_id' => $sale->tax_rate_id ?? null,
                    'taxable_amount' => round($taxableAmount, 2),
                    'tax_amount' => round($taxAmount, 2),
                ];

                if ($taxAmount > 0) {
                    $lines[] = [
                        'account_id' => $outputVatAccount->id,
                        'debit' => 0,
                        'credit' => round($taxAmount, 2),
                        'tenant_id' => $tenantId,
                        'description' => 'Output VAT charged on sale',
                    ];
                }

                if ($shippingAmount > 0) {
                    $shippingRevenue = $this->findAccount($tenantId, 'other_operating_income');
                    $lines[] = [
                        'account_id' => $shippingRevenue->id,
                        'debit' => 0,
                        'credit' => round($shippingAmount, 2),
                        'tenant_id' => $tenantId,
                        'description' => 'Shipping/freight charged',
                    ];
                }

                $cogsAmount = (float) ($sale->cogs_amount ?? 0);
                if ($cogsAmount <= 0 && $sale->relationLoaded('items')) {
                    $cogsAmount = (float) $sale->items->sum(fn ($item) => $item->quantity * ($item->unit_cost ?? 0));
                }
                if ($cogsAmount > 0) {
                    $lines[] = [
                        'account_id' => $cogsAccount->id,
                        'debit' => round($cogsAmount, 2),
                        'credit' => 0,
                        'tenant_id' => $tenantId,
                        'description' => 'COGS — perpetual inventory relief',
                    ];
                    $lines[] = [
                        'account_id' => $inventoryAccount->id,
                        'debit' => 0,
                        'credit' => round($cogsAmount, 2),
                        'tenant_id' => $tenantId,
                        'description' => 'Reduce inventory asset',
                    ];
                }

                $revenueJE = $this->jeService->createJournalEntry([
                    'entry_date' => $entryDate,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'reference' => 'SALE-'.($sale->sale_number ?? $sale->id),
                    'description' => 'Auto-posted: Sale revenue/COGS — #'.($sale->sale_number ?? $sale->id),
                    'status' => 'posted',
                    'is_auto_generated' => true,
                    'sequence' => 'journal_entry',
                    'lines' => $lines,
                    'branch_id' => $sale->branch_id,
                ], $tenantId, $sale->sold_by ?? $sale->created_by ?? 1);

                $paidAmount = (float) ($sale->paid_amount ?? 0);
                if ($paidAmount > 0) {
                    $this->postSalePaymentSettlement($sale, $paidAmount, $entryDate, $tenantId, $arAccount);
                }

                return $revenueJE;
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postSale failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    protected function postSalePaymentSettlement(
        Sale $sale,
        float $paidAmount,
        string $entryDate,
        int $tenantId,
        ChartOfAccount $arAccount,
    ): ?JournalEntry {
        $payments = $sale->payments;
        if ($payments->isEmpty()) {
            $payments = collect([(object) [
                'amount' => $paidAmount,
                'payment_method_id' => null,
                'paymentMethod' => null,
            ]]);
        }

        $lines = [];
        $totalSettled = 0;
        foreach ($payments as $payment) {
            $amount = (float) ($payment->amount ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $totalSettled += $amount;

            $settlementAccount = $this->resolveSettlementAccount(
                $tenantId,
                $payment->payment_method_id ?? null,
                $payment->paymentMethod?->slug ?? null,
            );

            $lines[] = [
                'account_id' => $settlementAccount->id,
                'debit' => round($amount, 2),
                'credit' => 0,
                'tenant_id' => $tenantId,
                'description' => 'Payment received — '.($payment->paymentMethod?->name ?? 'Cash'),
            ];
        }

        if (bccomp($totalSettled, 0, 2) <= 0) {
            return null;
        }

        $lines[] = [
            'account_id' => $arAccount->id,
            'debit' => 0,
            'credit' => round($totalSettled, 2),
            'tenant_id' => $tenantId,
            'description' => 'AR settlement — Sale #'.($sale->sale_number ?? $sale->id),
        ];

        return $this->jeService->createJournalEntry([
            'entry_date' => $entryDate,
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'reference' => 'RCT-'.($sale->sale_number ?? $sale->id),
            'description' => 'Auto-posted: Cash settlement — Sale #'.($sale->sale_number ?? $sale->id),
            'status' => 'posted',
            'is_auto_generated' => true,
            'sequence' => 'journal_entry',
            'lines' => $lines,
            'branch_id' => $sale->branch_id,
        ], $tenantId, $sale->sold_by ?? $sale->created_by ?? 1);
    }

    protected function resolveSettlementAccount(
        int $tenantId,
        ?int $paymentMethodId = null,
        ?string $methodSlug = null,
    ): ChartOfAccount {
        $slug = strtolower((string) $methodSlug);

        if (in_array($slug, ['bank-transfer', 'bank', 'transfer', 'cheque', 'check'])) {
            return $this->findAccount($tenantId, 'bank_operating');
        }
        if (in_array($slug, ['card', 'credit-card', 'debit-card', 'mobile', 'mpesa', 'mobile-money'])) {
            return $this->findAccount($tenantId, 'cash_in_transit')
                ?? $this->findAccount($tenantId, 'bank_operating');
        }

        return $this->findAccount($tenantId, 'petty_cash');
    }

    public function postGRN(GoodsReceivedNote $grn): ?JournalEntry
    {
        // Idempotency guard
        $existing = $this->isAlreadyPosted(GoodsReceivedNote::class, $grn->id);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($grn) {
                $tenantId = $grn->tenant_id;

                $goodsInTransit = $this->findAccount($tenantId, 'goods_in_transit');
                $grniClearing = $this->findAccount($tenantId, 'grni_clearing');

                $total = (float) ($grn->total_amount
                    ?? $grn->items->sum(fn ($i) => $i->received_quantity * ($i->unit_cost ?? 0)));

                if ($total <= 0) {
                    Log::warning('JournalAutoPost::postGRN zero amount, skipping.', ['grn_id' => $grn->id]);

                    return null;
                }

                $entryDate = $grn->received_date instanceof \DateTimeInterface
                    ? $grn->received_date->format('Y-m-d')
                    : now()->toDateString();

                return $this->jeService->createJournalEntry([
                    'entry_date' => $entryDate,
                    'reference_type' => GoodsReceivedNote::class,
                    'reference_id' => $grn->id,
                    'reference' => 'GRN-'.($grn->grn_number ?? $grn->id),
                    'description' => 'Auto-posted: GRN goods received — #'.($grn->grn_number ?? $grn->id),
                    'status' => 'posted',
                    'is_auto_generated' => true,
                    'sequence' => 'grn',
                    'lines' => [
                        [
                            'account_id' => $goodsInTransit->id,
                            'debit' => round($total, 2),
                            'credit' => 0,
                            'tenant_id' => $tenantId,
                            'description' => 'Goods received awaiting invoice',
                            'branch_id' => $grn->warehouse?->branch_id ?? null,
                        ],
                        [
                            'account_id' => $grniClearing->id,
                            'debit' => 0,
                            'credit' => round($total, 2),
                            'tenant_id' => $tenantId,
                            'description' => 'GRNI clearing liability',
                        ],
                    ],
                ], $tenantId, $grn->created_by ?? 1);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postGRN failed', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function postPurchaseBill(PurchaseBill $bill): ?JournalEntry
    {
        // Idempotency guard
        $existing = $this->isAlreadyPosted(PurchaseBill::class, $bill->id);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($bill) {
                $tenantId = $bill->tenant_id;

                $grniClearing = $this->findAccount($tenantId, 'grni_clearing');
                $apControl = $this->findAccount($tenantId, 'accounts_payable');
                $inputVat = $this->findAccount($tenantId, 'input_vat');
                $freightIn = $this->findAccount($tenantId, 'freight_in');
                $ppvAccount = $this->findAccount($tenantId, 'purchase_price_variance')
                    ?? $this->findAccount($tenantId, 'cost_of_goods_sold');

                $entryDate = $bill->bill_date instanceof \DateTimeInterface
                    ? $bill->bill_date->format('Y-m-d')
                    : now()->toDateString();

                $subtotal = (float) $bill->subtotal;
                $taxAmount = (float) ($bill->tax_amount ?? 0);
                $discountAmount = (float) ($bill->discount_amount ?? 0);
                $shippingCost = (float) ($bill->shipping_cost ?? 0);
                $billTotal = (float) $bill->total;

                $matchedGrnTotal = 0;
                if ($bill->purchase_order_id) {
                    $grns = GoodsReceivedNote::where('purchase_order_id', $bill->purchase_order_id)
                        ->where('status', 'completed')
                        ->with('items')
                        ->get();
                    $matchedGrnTotal = (float) $grns->sum(function ($grn) {
                        return $grn->items->sum(fn ($i) => $i->received_quantity * ($i->unit_cost ?? 0));
                    });
                }
                if ($matchedGrnTotal <= 0) {
                    $matchedGrnTotal = $subtotal;
                }

                $ppv = $subtotal - $matchedGrnTotal - $discountAmount;

                $lines = [];

                if ($matchedGrnTotal > 0) {
                    $lines[] = [
                        'account_id' => $grniClearing->id,
                        'debit' => round($matchedGrnTotal, 2),
                        'credit' => 0,
                        'tenant_id' => $tenantId,
                        'description' => 'Clear GRNI for matched PO receipts',
                    ];
                }

                if ($shippingCost > 0) {
                    $lines[] = [
                        'account_id' => $freightIn->id,
                        'debit' => round($shippingCost, 2),
                        'credit' => 0,
                        'tenant_id' => $tenantId,
                        'description' => 'Allocated freight/capitalized',
                    ];
                }

                if ($taxAmount > 0) {
                    $lines[] = [
                        'account_id' => $inputVat->id,
                        'debit' => round($taxAmount, 2),
                        'credit' => 0,
                        'tenant_id' => $tenantId,
                        'description' => 'Input VAT on supplier invoice',
                    ];
                }

                if (bccomp($ppv, 0, 2) !== 0) {
                    if ($ppv > 0) {
                        $lines[] = [
                            'account_id' => $ppvAccount->id,
                            'debit' => round(abs($ppv), 2),
                            'credit' => 0,
                            'tenant_id' => $tenantId,
                            'description' => 'PPV unfavorable (bill > PO cost)',
                        ];
                    } else {
                        $lines[] = [
                            'account_id' => $ppvAccount->id,
                            'debit' => 0,
                            'credit' => round(abs($ppv), 2),
                            'tenant_id' => $tenantId,
                            'description' => 'PPV favorable (bill < PO cost)',
                        ];
                    }
                }

                $lines[] = [
                    'account_id' => $apControl->id,
                    'debit' => 0,
                    'credit' => round($billTotal, 2),
                    'tenant_id' => $tenantId,
                    'description' => 'Supplier invoice payable — '.$bill->bill_number,
                ];

                return $this->jeService->createJournalEntry([
                    'entry_date' => $entryDate,
                    'reference_type' => PurchaseBill::class,
                    'reference_id' => $bill->id,
                    'reference' => 'BILL-'.($bill->bill_number ?? $bill->id),
                    'description' => 'Auto-posted: Purchase Bill — '.$bill->bill_number,
                    'status' => 'posted',
                    'is_auto_generated' => true,
                    'sequence' => 'journal_entry',
                    'lines' => $lines,
                ], $tenantId, $bill->created_by ?? 1);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postPurchaseBill failed', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function postPurchaseBillPayment(
        PurchaseBill $bill,
        float $amountPaid,
        ?int $bankAccountId = null,
        ?int $tenantId = null,
        ?int $userId = null,
    ): ?JournalEntry {
        $tenantId ??= $bill->tenant_id;
        $userId ??= 1;

        try {
            return DB::transaction(function () use ($bill, $amountPaid, $bankAccountId, $tenantId, $userId) {
                $apControl = $this->findAccount($tenantId, 'accounts_payable');
                $bankAccount = $this->resolvePaymentBankAccount($tenantId, $bankAccountId);

                return $this->jeService->createJournalEntry([
                    'entry_date' => now()->toDateString(),
                    'reference_type' => PurchaseBill::class,
                    'reference_id' => $bill->id,
                    'reference' => 'PAY-BILL-'.($bill->bill_number ?? $bill->id),
                    'description' => 'Bill payment — '.$bill->bill_number,
                    'status' => 'posted',
                    'is_auto_generated' => true,
                    'sequence' => 'journal_entry',
                    'lines' => [
                        [
                            'account_id' => $apControl->id,
                            'debit' => round($amountPaid, 2),
                            'credit' => 0,
                            'tenant_id' => $tenantId,
                            'description' => 'Reduce AP',
                        ],
                        [
                            'account_id' => $bankAccount->id,
                            'debit' => 0,
                            'credit' => round($amountPaid, 2),
                            'tenant_id' => $tenantId,
                            'description' => 'Cash disbursement',
                        ],
                    ],
                ], $tenantId, $userId);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postPurchaseBillPayment failed', [
                'bill_id' => $bill->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function postExpenseApproval(Expense $expense): ?JournalEntry
    {
        // Idempotency guard
        $existing = $this->isAlreadyPosted(Expense::class, $expense->id);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($expense) {
                $tenantId = $expense->tenant_id;

                $debitAccountId = $expense->account_id
                    ?? $expense->category?->account_id;
                if (! $debitAccountId) {
                    throw new \RuntimeException('No expense ledger account defined for this expense.');
                }

                $hasSupplier = ! empty($expense->supplier_id);

                if ($hasSupplier) {
                    $creditAccount = $this->findAccount($tenantId, 'accounts_payable');
                } elseif ($expense->payment_method === 'bank' && $expense->bankAccount) {
                    $creditAccount = $expense->bankAccount->account
                        ?? $this->findAccount($tenantId, 'bank_operating');
                } elseif ($expense->payment_method === 'cash') {
                    $creditAccount = $this->findAccount($tenantId, 'petty_cash');
                } else {
                    $creditAccount = $this->findAccount($tenantId, 'bank_operating');
                }

                $entryDate = $expense->expense_date instanceof \DateTimeInterface
                    ? $expense->expense_date->format('Y-m-d')
                    : (string) $expense->expense_date;

                $je = $this->jeService->createJournalEntry([
                    'entry_date' => $entryDate,
                    'reference_type' => Expense::class,
                    'reference_id' => $expense->id,
                    'reference' => $expense->reference ?? ('EXP-'.$expense->id),
                    'description' => 'Expense: '.($expense->description ?? $expense->payee ?? 'Expense'),
                    'status' => 'posted',
                    'is_auto_generated' => true,
                    'sequence' => 'journal_entry',
                    'lines' => [
                        [
                            'account_id' => $debitAccountId,
                            'debit' => round((float) $expense->amount, 2),
                            'credit' => 0,
                            'tenant_id' => $tenantId,
                            'description' => $expense->category?->name ?? 'Expense',
                            'branch_id' => $expense->branch_id ?? null,
                        ],
                        [
                            'account_id' => $creditAccount->id,
                            'debit' => 0,
                            'credit' => round((float) $expense->amount, 2),
                            'tenant_id' => $tenantId,
                            'description' => $hasSupplier ? 'On account (AP)' : 'Paid via '.($expense->payment_method ?? 'cash'),
                            'branch_id' => $expense->branch_id ?? null,
                        ],
                    ],
                ], $tenantId, $expense->created_by ?? 1);

                $expense->update(['journal_entry_id' => $je->id]);

                if ($expense->payment_method === 'bank' && $expense->bank_account_id) {
                    try {
                        BankTransaction::create([
                            'tenant_id' => $tenantId,
                            'bank_account_id' => $expense->bank_account_id,
                            'journal_entry_id' => $je->id,
                            'transaction_date' => $entryDate,
                            'type' => 'withdrawal',
                            'amount' => round((float) $expense->amount, 2),
                            'reference' => $expense->reference ?? ('EXP-'.$expense->id),
                            'description' => 'Expense: '.($expense->description ?? $expense->payee ?? ''),
                            'reference_type' => Expense::class,
                            'reference_id' => $expense->id,
                            'created_by' => $expense->created_by ?? 1,
                        ]);
                    } catch (\Throwable $txnErr) {
                        Log::warning('Could not create BankTransaction for expense', [
                            'expense_id' => $expense->id,
                            'error' => $txnErr->getMessage(),
                        ]);
                    }
                }

                return $je;
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postExpenseApproval failed', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function resolvePaymentBankAccount(int $tenantId, ?int $bankAccountId): ChartOfAccount
    {
        if ($bankAccountId) {
            $ba = \App\Models\BankAccount::find($bankAccountId);
            if ($ba && $ba->account_id) {
                $coa = ChartOfAccount::find($ba->account_id);
                if ($coa) {
                    return $coa;
                }
            }
        }

        return $this->findAccount($tenantId, 'bank_operating');
    }

    public function findAccount(int $tenantId, string $systemSlug): ChartOfAccount
    {
        $account = ChartOfAccount::where('tenant_id', $tenantId)
            ->where('system_slug', $systemSlug)
            ->first();

        if (! $account) {
            Log::error('Missing system GL account for slug: '.$systemSlug.'. Routing to Suspense.', [
                'tenant_id' => $tenantId,
            ]);

            return ChartOfAccount::where('tenant_id', $tenantId)
                ->where('system_slug', 'suspense')
                ->firstOrFail();
        }

        return $account;
    }

    /**
     * Idempotency guard: returns an existing posted/reversed journal entry for the
     * given source document, preventing duplicate entries on retries.
     */
    private function isAlreadyPosted(string $referenceType, int $referenceId): ?JournalEntry
    {
        return JournalEntry::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->whereIn('status', ['posted', 'reversed'])
            ->first();
    }

    // ─────────────────────────────────────────────────────────────────
    // Task 6: Sale Return auto-posting
    // ─────────────────────────────────────────────────────────────────

    public function postSaleReturn(\App\Models\SaleReturn $return): ?JournalEntry
    {
        // Idempotency guard
        $existing = $this->isAlreadyPosted(\App\Models\SaleReturn::class, $return->id);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($return) {
                $tenantId = $return->tenant_id;

                $revenueAccount = $this->findAccount($tenantId, 'sales_revenue');
                $outputVat      = $this->findAccount($tenantId, 'output_vat');

                // Credit account depends on refund_method
                $creditAccount = match ($return->refund_method) {
                    'bank_transfer'  => $this->findAccount($tenantId, 'bank_operating'),
                    'credit_account' => $this->findAccount($tenantId, 'accounts_receivable'),
                    default          => $this->findAccount($tenantId, 'petty_cash'), // cash
                };

                $lines = [];

                // Reverse the revenue
                $lines[] = [
                    'account_id'  => $revenueAccount->id,
                    'debit'       => round((float) $return->subtotal, 2),
                    'credit'      => 0,
                    'tenant_id'   => $tenantId,
                    'description' => 'Sales return — revenue reversal',
                ];

                // Reverse the VAT if any
                if ((float) $return->tax_amount > 0) {
                    $lines[] = [
                        'account_id'  => $outputVat->id,
                        'debit'       => round((float) $return->tax_amount, 2),
                        'credit'      => 0,
                        'tenant_id'   => $tenantId,
                        'description' => 'Output VAT reversed on return',
                    ];
                }

                // Credit the refund account
                $lines[] = [
                    'account_id'  => $creditAccount->id,
                    'debit'       => 0,
                    'credit'      => round((float) $return->total, 2),
                    'tenant_id'   => $tenantId,
                    'description' => 'Refund via '.($return->refund_method ?? 'cash'),
                ];

                $entryDate = $return->return_date
                    ? (is_string($return->return_date) ? $return->return_date : $return->return_date->format('Y-m-d'))
                    : now()->toDateString();

                return $this->jeService->createJournalEntry([
                    'entry_date'     => $entryDate,
                    'reference_type' => \App\Models\SaleReturn::class,
                    'reference_id'   => $return->id,
                    'reference'      => 'RET-'.($return->return_number ?? $return->id),
                    'description'    => 'Auto-posted: Sale Return — '.($return->return_number ?? $return->id),
                    'status'         => 'posted',
                    'is_auto_generated' => true,
                    'sequence'       => 'journal_entry',
                    'lines'          => $lines,
                ], $tenantId, $return->processed_by ?? 1);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postSaleReturn failed', [
                'return_id' => $return->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Task 7: Stock Adjustment auto-posting
    // ─────────────────────────────────────────────────────────────────

    public function postStockAdjustment(\App\Models\StockAdjustment $adj): ?JournalEntry
    {
        // Idempotency guard
        $existing = $this->isAlreadyPosted(\App\Models\StockAdjustment::class, $adj->id);
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($adj) {
                $tenantId = $adj->tenant_id;

                // Load items if not already loaded
                $adj->loadMissing('items');

                $totalValue = (float) $adj->items->sum(fn ($i) => $i->quantity * ($i->unit_cost ?? 0));

                if ($totalValue <= 0) {
                    Log::warning('JournalAutoPost::postStockAdjustment: total value is zero, skipping.', [
                        'adjustment_id' => $adj->id,
                    ]);

                    return null;
                }

                $inventoryAccount = $this->findAccount($tenantId, 'inventory');

                if ($adj->adjustment_type === 'addition') {
                    // Gain: Debit inventory asset, Credit gain account
                    $gainAccount = $this->findAccount($tenantId, 'inventory_adjustment_gain')
                        ?? $this->findAccount($tenantId, 'other_operating_income');

                    $lines = [
                        [
                            'account_id'  => $inventoryAccount->id,
                            'debit'       => round($totalValue, 2),
                            'credit'      => 0,
                            'tenant_id'   => $tenantId,
                            'description' => 'Inventory addition — stock adjustment #'.($adj->adjustment_number ?? $adj->id),
                        ],
                        [
                            'account_id'  => $gainAccount->id,
                            'debit'       => 0,
                            'credit'      => round($totalValue, 2),
                            'tenant_id'   => $tenantId,
                            'description' => 'Inventory adjustment gain',
                        ],
                    ];
                } else {
                    // Shrinkage: Debit shrinkage/COGS, Credit inventory asset
                    $shrinkageAccount = $this->findAccount($tenantId, 'inventory_shrinkage')
                        ?? $this->findAccount($tenantId, 'cost_of_goods_sold');

                    $lines = [
                        [
                            'account_id'  => $shrinkageAccount->id,
                            'debit'       => round($totalValue, 2),
                            'credit'      => 0,
                            'tenant_id'   => $tenantId,
                            'description' => 'Inventory shrinkage — stock adjustment #'.($adj->adjustment_number ?? $adj->id),
                        ],
                        [
                            'account_id'  => $inventoryAccount->id,
                            'debit'       => 0,
                            'credit'      => round($totalValue, 2),
                            'tenant_id'   => $tenantId,
                            'description' => 'Reduce inventory asset',
                        ],
                    ];
                }

                $entryDate = $adj->date
                    ? (is_string($adj->date) ? $adj->date : $adj->date->format('Y-m-d'))
                    : now()->toDateString();

                return $this->jeService->createJournalEntry([
                    'entry_date'        => $entryDate,
                    'reference_type'    => \App\Models\StockAdjustment::class,
                    'reference_id'      => $adj->id,
                    'reference'         => 'ADJ-'.($adj->adjustment_number ?? $adj->id),
                    'description'       => 'Auto-posted: Stock Adjustment ('.ucfirst($adj->adjustment_type).') — '.($adj->adjustment_number ?? $adj->id),
                    'status'            => 'posted',
                    'is_auto_generated' => true,
                    'sequence'          => 'journal_entry',
                    'lines'             => $lines,
                ], $tenantId, $adj->approved_by ?? $adj->created_by ?? 1);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postStockAdjustment failed', [
                'adjustment_id' => $adj->id,
                'error'         => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // Task 8: Manufacturing auto-posting
    // ─────────────────────────────────────────────────────────────────

    public function postManufacturingStart(\App\Models\ManufacturingOrder $mo): ?JournalEntry
    {
        // Use a compound reference key to differentiate start from complete
        $existing = JournalEntry::where('reference_type', \App\Models\ManufacturingOrder::class)
            ->where('reference_id', $mo->id)
            ->where('reference', 'like', 'WIP-START-%')
            ->whereIn('status', ['posted', 'reversed'])
            ->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($mo) {
                $tenantId = $mo->tenant_id;

                $mo->loadMissing('items');

                $rawMaterialCost = (float) $mo->items->sum(
                    fn ($i) => $i->quantity_planned * ($i->unit_cost ?? 0)
                );

                if ($rawMaterialCost <= 0) {
                    Log::warning('JournalAutoPost::postManufacturingStart: zero raw material cost, skipping.', [
                        'mo_id' => $mo->id,
                    ]);

                    return null;
                }

                $wipAccount       = $this->findAccount($tenantId, 'work_in_progress');
                $inventoryAccount = $this->findAccount($tenantId, 'inventory');

                return $this->jeService->createJournalEntry([
                    'entry_date'        => $mo->start_date
                        ? (is_string($mo->start_date) ? $mo->start_date : $mo->start_date->format('Y-m-d'))
                        : now()->toDateString(),
                    'reference_type'    => \App\Models\ManufacturingOrder::class,
                    'reference_id'      => $mo->id,
                    'reference'         => 'WIP-START-'.($mo->order_number ?? $mo->id),
                    'description'       => 'Auto-posted: Manufacturing Start — '.($mo->order_number ?? $mo->id),
                    'status'            => 'posted',
                    'is_auto_generated' => true,
                    'sequence'          => 'journal_entry',
                    'lines'             => [
                        [
                            'account_id'  => $wipAccount->id,
                            'debit'       => round($rawMaterialCost, 2),
                            'credit'      => 0,
                            'tenant_id'   => $tenantId,
                            'description' => 'Raw materials transferred to WIP',
                        ],
                        [
                            'account_id'  => $inventoryAccount->id,
                            'debit'       => 0,
                            'credit'      => round($rawMaterialCost, 2),
                            'tenant_id'   => $tenantId,
                            'description' => 'Raw material inventory consumed',
                        ],
                    ],
                ], $tenantId, $mo->created_by ?? 1);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postManufacturingStart failed', [
                'mo_id' => $mo->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function postManufacturingComplete(\App\Models\ManufacturingOrder $mo, float $produced): ?JournalEntry
    {
        $existing = JournalEntry::where('reference_type', \App\Models\ManufacturingOrder::class)
            ->where('reference_id', $mo->id)
            ->where('reference', 'like', 'WIP-COMPLETE-%')
            ->whereIn('status', ['posted', 'reversed'])
            ->first();
        if ($existing) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($mo, $produced) {
                $tenantId = $mo->tenant_id;

                $mo->loadMissing('items');

                $wipCost = (float) $mo->items->sum(
                    fn ($i) => ($i->quantity_consumed ?? $i->quantity_planned) * ($i->unit_cost ?? 0)
                );

                if ($wipCost <= 0) {
                    Log::warning('JournalAutoPost::postManufacturingComplete: zero WIP cost, skipping.', [
                        'mo_id' => $mo->id,
                    ]);

                    return null;
                }

                $wipAccount            = $this->findAccount($tenantId, 'work_in_progress');
                $finishedGoodsAccount  = $this->findAccount($tenantId, 'inventory_finished_goods')
                    ?? $this->findAccount($tenantId, 'inventory');

                return $this->jeService->createJournalEntry([
                    'entry_date'        => $mo->end_date
                        ? (is_string($mo->end_date) ? $mo->end_date : $mo->end_date->format('Y-m-d'))
                        : now()->toDateString(),
                    'reference_type'    => \App\Models\ManufacturingOrder::class,
                    'reference_id'      => $mo->id,
                    'reference'         => 'WIP-COMPLETE-'.($mo->order_number ?? $mo->id),
                    'description'       => 'Auto-posted: Manufacturing Complete — '.($mo->order_number ?? $mo->id),
                    'status'            => 'posted',
                    'is_auto_generated' => true,
                    'sequence'          => 'journal_entry',
                    'lines'             => [
                        [
                            'account_id'  => $finishedGoodsAccount->id,
                            'debit'       => round($wipCost, 2),
                            'credit'      => 0,
                            'tenant_id'   => $tenantId,
                            'description' => 'Finished goods produced: '.number_format($produced, 4).' units',
                        ],
                        [
                            'account_id'  => $wipAccount->id,
                            'debit'       => 0,
                            'credit'      => round($wipCost, 2),
                            'tenant_id'   => $tenantId,
                            'description' => 'WIP absorbed into finished goods',
                        ],
                    ],
                ], $tenantId, $mo->created_by ?? 1);
            });
        } catch (\Throwable $e) {
            Log::error('JournalAutoPost::postManufacturingComplete failed', [
                'mo_id' => $mo->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
