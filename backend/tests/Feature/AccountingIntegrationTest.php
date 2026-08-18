<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\AccountType;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ManufacturingOrder;
use App\Models\ManufacturingOrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductBundle;
use App\Models\BundleItem;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\JournalAutoPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * AccountingIntegrationTest
 *
 * Covers all 9 fix/integration areas:
 *   1. Idempotency guard on auto-posting
 *   2. Void-sale JE reference lookup fix
 *   3. Multi-unit stock restoration on void
 *   4. Manual JE entry_date field fix
 *   5. accounting_status tracking on Sale
 *   6. Sale return → journal entry
 *   7. Stock adjustment → journal entry
 *   8. Manufacturing start/complete → journal entries
 *   9. Fiscal period enforcement on POS sale
 */
class AccountingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────
    // Shared fixtures
    // ─────────────────────────────────────────────────────────────

    protected Tenant $tenant;
    protected User $user;
    protected Branch $branch;
    protected Warehouse $warehouse;
    protected CashRegister $register;
    protected RegisterSession $session;
    protected PaymentMethod $paymentMethod;
    protected Product $product;

    /** Account type IDs keyed by normal_balance */
    protected AccountType $debitType;
    protected AccountType $creditType;

    /** All GL accounts keyed by system_slug */
    protected array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        // ── Tenant & infrastructure ───────────────────────────────
        $this->tenant  = Tenant::factory()->create(['status' => true]);
        $this->branch  = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user    = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('admin');

        $this->warehouse = Warehouse::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->register = CashRegister::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->session = RegisterSession::factory()->create([
            'tenant_id'        => $this->tenant->id,
            'cash_register_id' => $this->register->id,
            'user_id'          => $this->user->id,
            'status'           => 'open',
            'opened_at'        => now(),
        ]);

        $this->paymentMethod = PaymentMethod::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'type'      => 'cash',
            'slug'      => 'cash',
        ]);

        // ── Product ───────────────────────────────────────────────
        $category      = Category::factory()->create(['tenant_id' => $this->tenant->id]);
        $unit          = Unit::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->product = Product::factory()->create([
            'tenant_id'       => $this->tenant->id,
            'category_id'     => $category->id,
            'base_unit_id'    => $unit->id,
            'is_active'       => true,
            'is_sellable'     => true,
            'track_inventory' => true,
            'selling_price'   => 100.00,
            'cost_price'      => 60.00,
        ]);

        StockLevel::factory()->create([
            'tenant_id'    => $this->tenant->id,
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity'     => 100,
        ]);

        // ── GL accounts ──────────────────────────────────────────
        $this->seedGlAccounts();
    }

    // ─────────────────────────────────────────────────────────────
    // GL account seeder
    // ─────────────────────────────────────────────────────────────

    /**
     * Creates the minimum set of GL accounts required for all auto-posting flows.
     * Each account gets a unique system_slug that JournalAutoPostService uses to resolve it.
     */
    protected function seedGlAccounts(): void
    {
        $this->debitType  = AccountType::create(['name' => 'Asset',   'category' => 'asset',   'normal_balance' => 'debit']);
        $this->creditType = AccountType::create(['name' => 'Revenue', 'category' => 'revenue', 'normal_balance' => 'credit']);
        $liabilityType    = AccountType::create(['name' => 'Liability', 'category' => 'liability', 'normal_balance' => 'credit']);
        $expenseType      = AccountType::create(['name' => 'Expense',  'category' => 'expense',  'normal_balance' => 'debit']);

        $slugs = [
            // POS sale
            'accounts_receivable'       => [$this->debitType->id,  '1100'],
            'sales_revenue'             => [$this->creditType->id, '4000'],
            'output_vat'                => [$liabilityType->id,    '2200'],
            'sales_discounts'           => [$expenseType->id,      '5100'],
            'cost_of_goods_sold'        => [$expenseType->id,      '5000'],
            'inventory'                 => [$this->debitType->id,  '1300'],
            'inventory_finished_goods'  => [$this->debitType->id,  '1310'],
            'petty_cash'                => [$this->debitType->id,  '1010'],
            'bank_operating'            => [$this->debitType->id,  '1020'],
            'cash_in_transit'           => [$this->debitType->id,  '1030'],
            // Purchase / AP
            'accounts_payable'          => [$liabilityType->id,    '2100'],
            'input_vat'                 => [$this->debitType->id,  '1200'],
            'grni_clearing'             => [$liabilityType->id,    '2300'],
            'goods_in_transit'          => [$this->debitType->id,  '1320'],
            'freight_in'                => [$expenseType->id,      '5200'],
            'purchase_price_variance'   => [$expenseType->id,      '5300'],
            // Payroll
            'salary_expense'            => [$expenseType->id,      '6000'],
            'salary_payable'            => [$liabilityType->id,    '2400'],
            'withholding_tax'           => [$liabilityType->id,    '2500'],
            // Manufacturing
            'work_in_progress'          => [$this->debitType->id,  '1330'],
            // Stock adjustments
            'inventory_shrinkage'       => [$expenseType->id,      '5400'],
            'inventory_adjustment_gain' => [$this->creditType->id, '4100'],
            'other_operating_income'    => [$this->creditType->id, '4200'],
            // Fallback
            'suspense'                  => [$this->debitType->id,  '9999'],
        ];

        foreach ($slugs as $slug => [$typeId, $code]) {
            $this->accounts[$slug] = ChartOfAccount::create([
                'tenant_id'           => $this->tenant->id,
                'account_type_id'     => $typeId,
                'system_slug'         => $slug,
                'code'                => $code,
                'name'                => ucwords(str_replace('_', ' ', $slug)),
                'is_active'           => true,
                'is_system'           => true,
                'allow_direct_posting' => true,
                'opening_balance'     => 0,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /** Returns a valid POS sale payload for one unit of $this->product at $100. */
    protected function basicSalePayload(array $overrides = []): array
    {
        return array_merge([
            'register_session_id' => $this->session->id,
            'customer_id'         => null,
            'items'               => [[
                'product_id' => $this->product->id,
                'quantity'   => 1,
                'unit_price' => 100.00,
                'tax_amount' => 0,
                'tax_percent' => 0,
            ]],
            'total_amount'        => 100.00,
            'paid_amount'         => 100.00,
            'payment_method_id'   => $this->paymentMethod->id,
            'payment_amount'      => 100.00,
            'tax_amount'          => 0,
        ], $overrides);
    }

    /** Creates a Sale + SaleItem directly in the DB (bypasses POSService) for isolated tests. */
    protected function createSaleDirectly(array $itemOverrides = []): Sale
    {
        $sale = Sale::create([
            'tenant_id'          => $this->tenant->id,
            'branch_id'          => $this->branch->id,
            'warehouse_id'       => $this->warehouse->id,
            'register_session_id' => $this->session->id,
            'sale_number'        => 'SL-TEST-'.uniqid(),
            'sale_date'          => now(),
            'subtotal'           => 100.00,
            'discount_amount'    => 0,
            'tax_amount'         => 0,
            'shipping_amount'    => 0,
            'total'              => 100.00,
            'paid_amount'        => 100.00,
            'change_amount'      => 0,
            'balance_due'        => 0,
            'payment_status'     => 'paid',
            'status'             => 'completed',
            'accounting_status'  => 'pending',
            'sold_by'            => $this->user->id,
        ]);

        SaleItem::create(array_merge([
            'tenant_id'         => $this->tenant->id,
            'sale_id'           => $sale->id,
            'product_id'        => $this->product->id,
            'unit_id'           => $this->product->base_unit_id,
            'product_name'      => $this->product->name,
            'quantity'          => 1,
            'base_quantity'     => 1,
            'conversion_factor' => 1,
            'unit_price'        => 100.00,
            'discount'          => 0,
            'tax'               => 0,
            'tax_rate'          => 0,
            'total'             => 100.00,
            'cost_price'        => 60.00,
        ], $itemOverrides));

        return $sale;
    }

    // ─────────────────────────────────────────────────────────────
    // Test 1: Happy-path POS sale posts journal entry
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_pos_sale_posts_journal_entry_on_success(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/pos/sale', $this->basicSalePayload());

        $response->assertStatus(201);

        $saleId = $response->json('data.id');
        $this->assertNotNull($saleId);

        // accounting_status should be 'posted' (or 'failed' only if suspense fallback fired)
        $sale = Sale::find($saleId);
        $this->assertContains($sale->accounting_status, ['posted', 'failed'],
            'accounting_status must be explicitly set after sale creation');

        // At least one journal entry must exist referencing this sale
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => Sale::class,
            'reference_id'   => $saleId,
            'status'         => 'posted',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 2: accounting_status = failed when GL not configured
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_pos_sale_marks_accounting_failed_when_gl_not_configured(): void
    {
        Sanctum::actingAs($this->user);

        // Remove ALL GL accounts so auto-posting must fail (findAccount throws on missing suspense)
        ChartOfAccount::where('tenant_id', $this->tenant->id)->delete();

        $response = $this->postJson('/api/v1/pos/sale', $this->basicSalePayload());

        // Sale must still succeed — cashier flow must not be blocked
        $response->assertStatus(201);

        $saleId = $response->json('data.id');
        $sale   = Sale::find($saleId);

        $this->assertEquals('failed', $sale->accounting_status,
            'accounting_status must be "failed" when GL accounts are missing');

        $this->assertNotNull($sale->accounting_failure_reason,
            'accounting_failure_reason must be populated on failure');
    }

    // ─────────────────────────────────────────────────────────────
    // Test 3: Void sale reverses journal entry (Task 2 fix)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_void_sale_reverses_journal_entry(): void
    {
        Sanctum::actingAs($this->user);

        // Create sale and get its JE via the service directly to guarantee one exists
        $sale = $this->createSaleDirectly();

        /** @var JournalAutoPostService $autoPost */
        $autoPost = app(JournalAutoPostService::class);
        $autoPost->postSale($sale->load('items'));

        // Confirm the posted JE exists with correct reference
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => Sale::class,
            'reference_id'   => $sale->id,
            'status'         => 'posted',
        ]);

        // Now void the sale via the API
        $response = $this->postJson("/api/v1/sales/{$sale->id}/void", [
            'reason' => 'Test void — accounting reversal check',
        ]);

        $response->assertStatus(200);

        // Original JE must now be 'reversed'
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => Sale::class,
            'reference_id'   => $sale->id,
            'status'         => 'reversed',
        ]);

        // A reversal JE must exist
        $originalJe = JournalEntry::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('status', 'reversed')
            ->first();

        $this->assertNotNull($originalJe);
        $this->assertDatabaseHas('journal_entries', [
            'reversal_of_id' => $originalJe->id,
            'type'           => 'reversal',
            'status'         => 'posted',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 4: Void restores base_quantity, not selling-unit quantity (Task 3 fix)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_void_sale_restores_base_quantity_not_unit_quantity(): void
    {
        Sanctum::actingAs($this->user);

        // Create sale with multi-unit item: 2 "dozen" (conversion_factor=12 → base_qty=24)
        $sale = $this->createSaleDirectly([
            'quantity'          => 2,
            'base_quantity'     => 24,   // 2 × 12
            'conversion_factor' => 12,
            'unit_price'        => 100.00,
            'total'             => 200.00,
        ]);

        // Set up stock level with known quantity
        $stockLevel = StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $stockLevel->update(['quantity' => 0]); // depleted after the original sale

        $response = $this->postJson("/api/v1/sales/{$sale->id}/void", [
            'reason' => 'Multi-unit void test',
        ]);

        $response->assertStatus(200);

        // Stock must have been restored by base_quantity=24, not selling-qty=2
        $stockLevel->refresh();
        $this->assertEquals(24, (float) $stockLevel->quantity,
            'voidSale must restore base_quantity (24), not selling-unit quantity (2)');
    }

    // ─────────────────────────────────────────────────────────────
    // Test 5: Manual journal entry respects submitted entry_date (Task 4 fix)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_manual_journal_entry_respects_submitted_entry_date(): void
    {
        Sanctum::actingAs($this->user);

        $targetDate = '2024-03-15';

        $arAccount  = $this->accounts['accounts_receivable'];
        $revAccount = $this->accounts['sales_revenue'];

        $response = $this->postJson('/api/v1/journal-entries', [
            'entry_date'  => $targetDate,
            'description' => 'Back-dated test entry',
            'lines'       => [
                ['account_id' => $arAccount->id,  'debit' => 100, 'credit' => 0],
                ['account_id' => $revAccount->id, 'debit' => 0,   'credit' => 100],
            ],
        ]);

        $response->assertStatus(201);

        $jeId = $response->json('data.id');
        $je   = JournalEntry::find($jeId);

        $this->assertEquals($targetDate, $je->entry_date->format('Y-m-d'),
            'entry_date in DB must match the submitted date, not today');
    }

    // ─────────────────────────────────────────────────────────────
    // Test 6: Idempotency — calling postSale twice produces one JE (Task 1 guard)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_duplicate_post_sale_is_idempotent(): void
    {
        $sale = $this->createSaleDirectly();

        /** @var JournalAutoPostService $autoPost */
        $autoPost = app(JournalAutoPostService::class);

        $firstResult  = $autoPost->postSale($sale->load('items'));
        $secondResult = $autoPost->postSale($sale->load('items'));

        // Both calls must return the same JE
        $this->assertEquals($firstResult->id, $secondResult->id,
            'Second postSale call must return the existing JE, not create a new one');

        // Exactly one JE in DB for this sale
        $count = JournalEntry::where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->count();

        $this->assertEquals(1, $count,
            'Only one journal entry should exist after two postSale calls');
    }

    // ─────────────────────────────────────────────────────────────
    // Test 7a: Sale return with cash refund creates reversing JE (Task 6)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_sale_return_with_cash_refund_creates_reversing_journal_entry(): void
    {
        Sanctum::actingAs($this->user);

        // Create a completed sale with a real sale item
        $sale = $this->createSaleDirectly();
        $saleItem = $sale->items->first();

        $response = $this->postJson('/api/v1/sale-returns', [
            'sale_id'       => $sale->id,
            'reason'        => 'Defective item',
            'refund_method' => 'cash',
            'items'         => [[
                'sale_item_id'  => $saleItem->id,
                'product_id'    => $this->product->id,
                'quantity'      => 1,
                'unit_price'    => 100.00,
                'return_to_stock' => true,
            ]],
        ]);

        $response->assertStatus(201);

        $returnId = $response->json('data.id');

        // A journal entry must be posted for this return
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => \App\Models\SaleReturn::class,
            'reference_id'   => $returnId,
            'status'         => 'posted',
        ]);

        // Revenue account should be debited (reversed)
        $je = JournalEntry::where('reference_type', \App\Models\SaleReturn::class)
            ->where('reference_id', $returnId)
            ->first();

        $revenueLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['sales_revenue']->id)
            ->first();

        $this->assertNotNull($revenueLine, 'Revenue account must be debited on a sale return');
        $this->assertGreaterThan(0, (float) $revenueLine->debit);

        // Cash / petty_cash should be credited (money going out)
        $cashLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['petty_cash']->id)
            ->first();

        $this->assertNotNull($cashLine, 'Petty cash account must be credited for cash refund');
        $this->assertGreaterThan(0, (float) $cashLine->credit);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 7b: Sale return with bank_transfer credits bank_operating
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_sale_return_with_bank_transfer_credits_bank_account(): void
    {
        Sanctum::actingAs($this->user);

        $sale     = $this->createSaleDirectly();
        $saleItem = $sale->items->first();

        $response = $this->postJson('/api/v1/sale-returns', [
            'sale_id'       => $sale->id,
            'reason'        => 'Wrong item sent',
            'refund_method' => 'bank_transfer',
            'items'         => [[
                'sale_item_id'    => $saleItem->id,
                'product_id'      => $this->product->id,
                'quantity'        => 1,
                'unit_price'      => 100.00,
                'return_to_stock' => false,
            ]],
        ]);

        $response->assertStatus(201);
        $returnId = $response->json('data.id');

        $je = JournalEntry::where('reference_type', \App\Models\SaleReturn::class)
            ->where('reference_id', $returnId)
            ->first();

        $this->assertNotNull($je, 'Journal entry must exist for bank_transfer return');

        $bankLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['bank_operating']->id)
            ->first();

        $this->assertNotNull($bankLine, 'bank_operating must be credited for bank_transfer refund');
        $this->assertGreaterThan(0, (float) $bankLine->credit);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 7c: Sale return with credit_account credits AR
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_sale_return_with_credit_account_credits_accounts_receivable(): void
    {
        Sanctum::actingAs($this->user);

        $sale     = $this->createSaleDirectly();
        $saleItem = $sale->items->first();

        $response = $this->postJson('/api/v1/sale-returns', [
            'sale_id'       => $sale->id,
            'reason'        => 'Customer credit',
            'refund_method' => 'credit_account',
            'items'         => [[
                'sale_item_id'    => $saleItem->id,
                'product_id'      => $this->product->id,
                'quantity'        => 1,
                'unit_price'      => 100.00,
                'return_to_stock' => false,
            ]],
        ]);

        $response->assertStatus(201);
        $returnId = $response->json('data.id');

        $je = JournalEntry::where('reference_type', \App\Models\SaleReturn::class)
            ->where('reference_id', $returnId)
            ->first();

        $this->assertNotNull($je);

        $arLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['accounts_receivable']->id)
            ->first();

        $this->assertNotNull($arLine, 'AR must be credited for credit_account return');
        $this->assertGreaterThan(0, (float) $arLine->credit);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 8a: Stock adjustment addition creates inventory gain entry (Task 7)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_stock_adjustment_addition_creates_inventory_gain_entry(): void
    {
        Sanctum::actingAs($this->user);

        // Create a pending adjustment
        $adj = StockAdjustment::create([
            'tenant_id'         => $this->tenant->id,
            'adjustment_number' => 'SA-TEST-001',
            'warehouse_id'      => $this->warehouse->id,
            'adjustment_type'   => 'addition',
            'date'              => now()->toDateString(),
            'reason'            => 'Found extra stock',
            'status'            => 'pending',
            'created_by'        => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'tenant_id'           => $this->tenant->id,
            'stock_adjustment_id' => $adj->id,
            'product_id'          => $this->product->id,
            'quantity'            => 10,
            'unit_cost'           => 5.00,   // total value = 50.00
            'reason'              => 'Surplus',
        ]);

        $response = $this->postJson("/api/v1/stock-adjustments/{$adj->id}/approve");
        $response->assertStatus(200);

        // Journal entry must exist
        $this->assertDatabaseHas('journal_entries', [
            'reference_type' => StockAdjustment::class,
            'reference_id'   => $adj->id,
            'status'         => 'posted',
        ]);

        $je = JournalEntry::where('reference_type', StockAdjustment::class)
            ->where('reference_id', $adj->id)
            ->first();

        // Inventory debited by 50.00
        $invLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['inventory']->id)
            ->first();

        $this->assertNotNull($invLine, 'Inventory account must be debited for stock addition');
        $this->assertEquals(50.00, (float) $invLine->debit);

        // Gain credited by 50.00
        $gainLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['inventory_adjustment_gain']->id)
            ->first();

        $this->assertNotNull($gainLine, 'Gain account must be credited for stock addition');
        $this->assertEquals(50.00, (float) $gainLine->credit);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 8b: Stock adjustment subtraction creates shrinkage entry
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_stock_adjustment_subtraction_creates_shrinkage_entry(): void
    {
        Sanctum::actingAs($this->user);

        $adj = StockAdjustment::create([
            'tenant_id'         => $this->tenant->id,
            'adjustment_number' => 'SA-TEST-002',
            'warehouse_id'      => $this->warehouse->id,
            'adjustment_type'   => 'subtraction',
            'date'              => now()->toDateString(),
            'reason'            => 'Damaged goods',
            'status'            => 'pending',
            'created_by'        => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'tenant_id'           => $this->tenant->id,
            'stock_adjustment_id' => $adj->id,
            'product_id'          => $this->product->id,
            'quantity'            => 5,
            'unit_cost'           => 10.00,   // total value = 50.00
            'reason'              => 'Damaged',
        ]);

        $response = $this->postJson("/api/v1/stock-adjustments/{$adj->id}/approve");
        $response->assertStatus(200);

        $je = JournalEntry::where('reference_type', StockAdjustment::class)
            ->where('reference_id', $adj->id)
            ->first();

        $this->assertNotNull($je, 'Journal entry must exist for stock subtraction');

        // Shrinkage debited
        $shrinkLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['inventory_shrinkage']->id)
            ->first();

        $this->assertNotNull($shrinkLine, 'Shrinkage account must be debited');
        $this->assertEquals(50.00, (float) $shrinkLine->debit);

        // Inventory credited
        $invLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['inventory']->id)
            ->first();

        $this->assertNotNull($invLine, 'Inventory account must be credited');
        $this->assertEquals(50.00, (float) $invLine->credit);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 9a: Manufacturing start creates WIP journal entry (Task 8)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_manufacturing_start_creates_wip_journal_entry(): void
    {
        // Create a manufacturing order directly (planned state)
        $mo = ManufacturingOrder::create([
            'tenant_id'        => $this->tenant->id,
            'branch_id'        => $this->branch->id,
            'warehouse_id'     => $this->warehouse->id,
            'product_id'       => $this->product->id,
            'order_number'     => 'MO-TEST-001',
            'quantity_planned' => 10,
            'status'           => 'planned',
            'created_by'       => $this->user->id,
        ]);

        ManufacturingOrderItem::create([
            'manufacturing_order_id' => $mo->id,
            'product_id'             => $this->product->id,
            'quantity_planned'       => 5,
            'unit_cost'              => 20.00,   // total raw cost = 100.00
        ]);

        // Ensure enough raw-material stock exists
        StockLevel::updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id],
            ['quantity' => 50]
        );

        /** @var \App\Services\ManufacturingService $mfgService */
        $mfgService = app(\App\Services\ManufacturingService::class);
        $mfgService->startProduction($mo);

        // WIP journal entry must have been posted
        $je = JournalEntry::where('reference_type', ManufacturingOrder::class)
            ->where('reference_id', $mo->id)
            ->where('reference', 'like', 'WIP-START-%')
            ->first();

        $this->assertNotNull($je, 'WIP-START journal entry must be created on startProduction()');
        $this->assertEquals('posted', $je->status);

        // WIP debited by raw material cost
        $wipLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['work_in_progress']->id)
            ->first();

        $this->assertNotNull($wipLine, 'WIP account must be debited');
        $this->assertEquals(100.00, (float) $wipLine->debit);

        // Inventory credited by same amount
        $invLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['inventory']->id)
            ->first();

        $this->assertNotNull($invLine, 'Inventory must be credited for raw materials consumed');
        $this->assertEquals(100.00, (float) $invLine->credit);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 9b: Manufacturing complete creates finished-goods journal entry
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_manufacturing_complete_creates_finished_goods_journal_entry(): void
    {
        // Set up an in_progress order
        $mo = ManufacturingOrder::create([
            'tenant_id'        => $this->tenant->id,
            'branch_id'        => $this->branch->id,
            'warehouse_id'     => $this->warehouse->id,
            'product_id'       => $this->product->id,
            'order_number'     => 'MO-TEST-002',
            'quantity_planned' => 10,
            'status'           => 'in_progress',
            'start_date'       => now(),
            'created_by'       => $this->user->id,
        ]);

        ManufacturingOrderItem::create([
            'manufacturing_order_id' => $mo->id,
            'product_id'             => $this->product->id,
            'quantity_planned'       => 5,
            'quantity_consumed'      => 5,
            'unit_cost'              => 20.00,   // WIP cost = 100.00
        ]);

        /** @var \App\Services\ManufacturingService $mfgService */
        $mfgService = app(\App\Services\ManufacturingService::class);
        $mfgService->completeProduction($mo, 10);

        // Finished-goods journal entry must exist
        $je = JournalEntry::where('reference_type', ManufacturingOrder::class)
            ->where('reference_id', $mo->id)
            ->where('reference', 'like', 'WIP-COMPLETE-%')
            ->first();

        $this->assertNotNull($je, 'WIP-COMPLETE journal entry must be created on completeProduction()');
        $this->assertEquals('posted', $je->status);

        // Finished goods debited
        $fgLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['inventory_finished_goods']->id)
            ->first();

        $this->assertNotNull($fgLine, 'Finished goods account must be debited');
        $this->assertEquals(100.00, (float) $fgLine->debit);

        // WIP credited
        $wipLine = JournalEntryLine::where('journal_entry_id', $je->id)
            ->where('account_id', $this->accounts['work_in_progress']->id)
            ->first();

        $this->assertNotNull($wipLine, 'WIP account must be credited on production completion');
        $this->assertEquals(100.00, (float) $wipLine->credit);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 10: POS sale blocked when accounting period is closed (Task 9)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_pos_sale_blocked_when_accounting_period_is_closed(): void
    {
        Sanctum::actingAs($this->user);

        // Create a fiscal year + closed period covering today
        $fy = FiscalYear::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => date('Y').' FY',
            'start_date' => now()->startOfYear(),
            'end_date'   => now()->endOfYear(),
            'status'     => 'active',
            'is_active'  => true,
        ]);

        AccountingPeriod::create([
            'fiscal_year_id' => $fy->id,
            'name'           => 'Closed Period',
            'start_date'     => now()->startOfMonth(),
            'end_date'       => now()->endOfMonth(),
            'is_closed'      => true,
            'closed_at'      => now()->subDay(),
            'closed_by'      => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/pos/sale', $this->basicSalePayload());

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('closed', strtolower($response->json('message')));
    }

    // ─────────────────────────────────────────────────────────────
    // Test 11: POS sale succeeds when accounting period is open
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_pos_sale_succeeds_when_accounting_period_is_open(): void
    {
        Sanctum::actingAs($this->user);

        // Create a fiscal year + OPEN period covering today
        $fy = FiscalYear::create([
            'tenant_id'  => $this->tenant->id,
            'name'       => date('Y').' FY Open',
            'start_date' => now()->startOfYear(),
            'end_date'   => now()->endOfYear(),
            'status'     => 'active',
            'is_active'  => true,
        ]);

        AccountingPeriod::create([
            'fiscal_year_id' => $fy->id,
            'name'           => 'Open Period',
            'start_date'     => now()->startOfMonth(),
            'end_date'       => now()->endOfMonth(),
            'is_closed'      => false,
        ]);

        $response = $this->postJson('/api/v1/pos/sale', $this->basicSalePayload());

        $response->assertStatus(201);
    }

    // ─────────────────────────────────────────────────────────────
    // Test 12: JE balance is always equal (debit == credit)
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_all_auto_posted_journal_entries_are_balanced(): void
    {
        $sale = $this->createSaleDirectly();

        /** @var JournalAutoPostService $autoPost */
        $autoPost = app(JournalAutoPostService::class);
        $autoPost->postSale($sale->load('items'));

        JournalEntry::where('tenant_id', $this->tenant->id)->each(function (JournalEntry $je) {
            $totalDebit  = JournalEntryLine::where('journal_entry_id', $je->id)->sum('debit');
            $totalCredit = JournalEntryLine::where('journal_entry_id', $je->id)->sum('credit');

            $this->assertEquals(
                round((float) $totalDebit, 4),
                round((float) $totalCredit, 4),
                "Journal entry #{$je->id} ({$je->reference}) is not balanced: debit={$totalDebit}, credit={$totalCredit}"
            );
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Test 13: accounting_status field is present in sale API response
    // ─────────────────────────────────────────────────────────────

    /** @test */
    public function test_sale_api_response_includes_accounting_status(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/pos/sale', $this->basicSalePayload());
        $response->assertStatus(201);

        $saleId = $response->json('data.id');

        $showResponse = $this->getJson("/api/v1/sales/{$saleId}");
        $showResponse->assertStatus(200);
        $showResponse->assertJsonStructure(['data' => ['accounting_status']]);
    }
}
