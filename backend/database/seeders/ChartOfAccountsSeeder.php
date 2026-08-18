<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping ChartOfAccountsSeeder.');

            return;
        }

        $accountTypeMap = $this->seedAccountTypes();

        foreach ($tenants as $tenant) {
            $this->seedStandardCOA($tenant->id, $accountTypeMap);
        }

        $this->command->info('Standard Chart of Accounts seeded for all tenants.');
    }

    public function seedForTenant(int $tenantId): void
    {
        $accountTypeMap = $this->seedAccountTypes();
        $this->seedStandardCOA($tenantId, $accountTypeMap);
    }

    private function seedAccountTypes(): array
    {
        $types = [
            ['name' => 'Current Asset',           'category' => 'asset',      'normal_balance' => 'debit'],
            ['name' => 'Fixed Asset',             'category' => 'asset',      'normal_balance' => 'debit'],
            ['name' => 'Contra Asset',            'category' => 'asset',      'normal_balance' => 'credit'],
            ['name' => 'Current Liability',       'category' => 'liability',  'normal_balance' => 'credit'],
            ['name' => 'Long-term Liability',     'category' => 'liability',  'normal_balance' => 'credit'],
            ['name' => 'Equity',                  'category' => 'equity',     'normal_balance' => 'credit'],
            ['name' => 'Contra Equity',           'category' => 'equity',     'normal_balance' => 'debit'],
            ['name' => 'Revenue',                 'category' => 'revenue',    'normal_balance' => 'credit'],
            ['name' => 'Contra Revenue',          'category' => 'revenue',    'normal_balance' => 'debit'],
            ['name' => 'Cost of Goods Sold',      'category' => 'expense',    'normal_balance' => 'debit'],
            ['name' => 'Operating Expense',       'category' => 'expense',    'normal_balance' => 'debit'],
            ['name' => 'Non-operating Expense',   'category' => 'expense',    'normal_balance' => 'debit'],
            ['name' => 'Income Tax',              'category' => 'expense',    'normal_balance' => 'debit'],
            ['name' => 'Suspense',                'category' => 'asset',      'normal_balance' => 'debit'],
        ];

        $map = [];
        foreach ($types as $typeData) {
            $type = AccountType::updateOrCreate(
                ['name' => $typeData['name']],
                [
                    'category' => $typeData['category'],
                    'normal_balance' => $typeData['normal_balance'],
                ]
            );
            $map[$typeData['name']] = $type->id;
        }

        return $map;
    }

    private function seedStandardCOA(int $tenantId, array $accountTypeMap): void
    {
        $accounts = [
            // ── 1xxx ASSETS ──────────────────────────────────────────────
            ['code' => '1001', 'system_slug' => 'petty_cash',                    'name' => 'Petty Cash Fund',                     'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1002', 'system_slug' => 'bank_operating',                'name' => 'Main Bank Operating Account',          'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1003', 'system_slug' => 'bank_savings',                  'name' => 'Savings Account',                      'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1004', 'system_slug' => 'cash_in_transit',               'name' => 'Cash in Transit',                      'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],

            ['code' => '1101', 'system_slug' => 'accounts_receivable',           'name' => 'Accounts Receivable — Control',        'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => false],
            ['code' => '1102', 'system_slug' => 'allowance_doubtful',            'name' => 'Allowance for Doubtful Accounts',      'typeName' => 'Contra Asset',  'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1103', 'system_slug' => 'employee_loans',                'name' => 'Employee Advances & Loans',            'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1104', 'system_slug' => 'advances_to_suppliers',         'name' => 'Advances to Suppliers',                'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],

            ['code' => '1201', 'system_slug' => 'inventory_finished_goods',      'name' => 'Inventory — Finished Goods',           'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => false],
            ['code' => '1202', 'system_slug' => 'inventory_raw_materials',       'name' => 'Inventory — Raw Materials',            'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => false],
            ['code' => '1203', 'system_slug' => 'inventory_wip',                 'name' => 'Inventory — Work in Progress',         'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => false],
            ['code' => '1204', 'system_slug' => 'goods_in_transit',              'name' => 'Goods in Transit / GRNI Clearing',     'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1200', 'system_slug' => 'inventory',                     'name' => 'Inventory Control',                    'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => false],

            ['code' => '1301', 'system_slug' => 'prepaid_rent',                  'name' => 'Prepaid Rent',                         'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1302', 'system_slug' => 'prepaid_insurance',             'name' => 'Prepaid Insurance',                    'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1303', 'system_slug' => 'deposits',                      'name' => 'Utility & Security Deposits',          'typeName' => 'Current Asset', 'level' => 1, 'allow_direct_posting' => true],

            ['code' => '1501', 'system_slug' => 'ppe_cost',                      'name' => 'Property, Plant & Equipment at Cost',  'typeName' => 'Fixed Asset',   'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1502', 'system_slug' => 'accumulated_depreciation',      'name' => 'Accumulated Depreciation',             'typeName' => 'Contra Asset',  'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1601', 'system_slug' => 'intangibles',                   'name' => 'Intangible Assets',                    'typeName' => 'Fixed Asset',   'level' => 1, 'allow_direct_posting' => true],
            ['code' => '1602', 'system_slug' => 'accumulated_amortization',      'name' => 'Accumulated Amortization',             'typeName' => 'Contra Asset',  'level' => 1, 'allow_direct_posting' => true],

            // ── 2xxx LIABILITIES ─────────────────────────────────────────
            ['code' => '2001', 'system_slug' => 'accounts_payable',              'name' => 'Accounts Payable — Control',           'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => false],
            ['code' => '2002', 'system_slug' => 'grni_clearing',                 'name' => 'GRNI Clearing Account',                'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2003', 'system_slug' => 'accrued_expenses',              'name' => 'Accrued Expenses',                     'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],

            ['code' => '2101', 'system_slug' => 'output_vat',                    'name' => 'Output VAT / Tax Payable',             'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2102', 'system_slug' => 'input_vat',                     'name' => 'Input VAT Receivable',                 'typeName' => 'Current Asset',     'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2103', 'system_slug' => 'withholding_tax',               'name' => 'PAYE / WHT Payable',                   'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2104', 'system_slug' => 'payroll_statutory_deductions',  'name' => 'Payroll Statutory Deductions Payable', 'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],

            ['code' => '2201', 'system_slug' => 'salary_payable',                'name' => 'Salary & Wages Payable',               'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2202', 'system_slug' => 'bonus_payable',                 'name' => 'Bonus Payable',                        'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2203', 'system_slug' => 'leave_encashment_payable',      'name' => 'Leave Encashment Payable',             'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2000', 'system_slug' => 'tax_payable',                   'name' => 'Tax Payable (generic)',                'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],

            ['code' => '2301', 'system_slug' => 'deferred_revenue',              'name' => 'Unearned Revenue / Gift Cards',        'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2302', 'system_slug' => 'customer_deposits',             'name' => 'Customer Deposits',                    'typeName' => 'Current Liability', 'level' => 1, 'allow_direct_posting' => true],

            ['code' => '2501', 'system_slug' => 'long_term_loan',                'name' => 'Long-term Bank Loan',                  'typeName' => 'Long-term Liability', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '2502', 'system_slug' => 'lease_liability',               'name' => 'Lease Liability',                      'typeName' => 'Long-term Liability', 'level' => 1, 'allow_direct_posting' => true],

            // ── 3xxx EQUITY ──────────────────────────────────────────────
            ['code' => '3001', 'system_slug' => 'owner_capital',                 'name' => "Owner's Capital",                      'typeName' => 'Equity',              'level' => 1, 'allow_direct_posting' => true],
            ['code' => '3101', 'system_slug' => 'retained_earnings',             'name' => 'Retained Earnings',                    'typeName' => 'Equity',              'level' => 1, 'allow_direct_posting' => true],
            ['code' => '3201', 'system_slug' => 'owner_drawings',                'name' => 'Owner Drawings',                       'typeName' => 'Contra Equity',       'level' => 1, 'allow_direct_posting' => true],
            ['code' => '3301', 'system_slug' => 'current_year_pl',               'name' => 'Current Year P&L',                     'typeName' => 'Equity',              'level' => 1, 'allow_direct_posting' => false],

            // ── 4xxx REVENUE ─────────────────────────────────────────────
            ['code' => '4001', 'system_slug' => 'sales_revenue',                 'name' => 'Product Sales Revenue',                'typeName' => 'Revenue',             'level' => 1, 'allow_direct_posting' => false],
            ['code' => '4002', 'system_slug' => 'service_revenue',               'name' => 'Service Revenue',                      'typeName' => 'Revenue',             'level' => 1, 'allow_direct_posting' => true],
            ['code' => '4101', 'system_slug' => 'sales_returns',                 'name' => 'Sales Returns & Allowances',           'typeName' => 'Contra Revenue',      'level' => 1, 'allow_direct_posting' => true],
            ['code' => '4102', 'system_slug' => 'sales_discounts',               'name' => 'Sales Discounts',                      'typeName' => 'Contra Revenue',      'level' => 1, 'allow_direct_posting' => true],
            ['code' => '4201', 'system_slug' => 'interest_income',               'name' => 'Interest Income',                      'typeName' => 'Revenue',             'level' => 1, 'allow_direct_posting' => true],
            ['code' => '4301', 'system_slug' => 'other_operating_income',        'name' => 'Other Operating Income',               'typeName' => 'Revenue',             'level' => 1, 'allow_direct_posting' => true],

            // ── 5xxx COGS ────────────────────────────────────────────────
            ['code' => '5001', 'system_slug' => 'cost_of_goods_sold',            'name' => 'Cost of Goods Sold',                   'typeName' => 'Cost of Goods Sold',  'level' => 1, 'allow_direct_posting' => false],
            ['code' => '5002', 'system_slug' => 'freight_in',                    'name' => 'Freight-In',                           'typeName' => 'Cost of Goods Sold',  'level' => 1, 'allow_direct_posting' => true],
            ['code' => '5003', 'system_slug' => 'purchase_price_variance',       'name' => 'Purchase Price Variance',              'typeName' => 'Cost of Goods Sold',  'level' => 1, 'allow_direct_posting' => true],

            // ── 6xxx OpEx ────────────────────────────────────────────────
            ['code' => '6001', 'system_slug' => 'salary_expense',                'name' => 'Salaries & Benefits Expense',          'typeName' => 'Operating Expense',   'level' => 1, 'allow_direct_posting' => true],
            ['code' => '6401', 'system_slug' => 'rent_expense',                  'name' => 'Rent Expense',                         'typeName' => 'Operating Expense',   'level' => 1, 'allow_direct_posting' => true],
            ['code' => '6501', 'system_slug' => 'utilities_expense',             'name' => 'Utilities Expense',                    'typeName' => 'Operating Expense',   'level' => 1, 'allow_direct_posting' => true],
            ['code' => '6601', 'system_slug' => 'depreciation_expense',          'name' => 'Depreciation & Amortization Expense',  'typeName' => 'Operating Expense',   'level' => 1, 'allow_direct_posting' => true],
            ['code' => '6801', 'system_slug' => 'professional_fees',             'name' => 'Professional Fees Expense',            'typeName' => 'Operating Expense',   'level' => 1, 'allow_direct_posting' => true],
            ['code' => '6901', 'system_slug' => 'other_operating_expense',       'name' => 'Other Operating Expense',              'typeName' => 'Operating Expense',   'level' => 1, 'allow_direct_posting' => true],

            // ── 7xxx Non-Operating ───────────────────────────────────────
            ['code' => '7001', 'system_slug' => 'interest_expense',              'name' => 'Interest Expense',                     'typeName' => 'Non-operating Expense', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '7101', 'system_slug' => 'fx_gain_loss',                  'name' => 'Foreign Exchange Gain/Loss',           'typeName' => 'Non-operating Expense', 'level' => 1, 'allow_direct_posting' => true],
            ['code' => '7201', 'system_slug' => 'asset_disposal_gain_loss',      'name' => 'Gain/Loss on Asset Disposal',          'typeName' => 'Non-operating Expense', 'level' => 1, 'allow_direct_posting' => true],

            // ── 8xxx Income Tax ──────────────────────────────────────────
            ['code' => '8001', 'system_slug' => 'income_tax_expense',            'name' => 'Income Tax Expense',                   'typeName' => 'Income Tax',            'level' => 1, 'allow_direct_posting' => true],
            ['code' => '8101', 'system_slug' => 'deferred_tax',                  'name' => 'Deferred Tax',                         'typeName' => 'Income Tax',            'level' => 1, 'allow_direct_posting' => true],

            // ── 9999 SUSPENSE ────────────────────────────────────────────
            ['code' => '9999', 'system_slug' => 'suspense',                      'name' => 'Uncategorized / Suspense',             'typeName' => 'Suspense',              'level' => 1, 'allow_direct_posting' => true],
        ];

        foreach ($accounts as $acc) {
            $typeId = $accountTypeMap[$acc['typeName']] ?? $accountTypeMap['Current Asset'] ?? null;

            if (! $typeId) {
                continue;
            }

            ChartOfAccount::updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $acc['code']],
                [
                    'account_type_id' => $typeId,
                    'system_slug' => $acc['system_slug'],
                    'name' => $acc['name'],
                    'description' => 'Standard COA — '.$acc['name'],
                    'is_active' => true,
                    'is_system' => true,
                    'allow_direct_posting' => $acc['allow_direct_posting'],
                    'opening_balance' => 0,
                    'current_balance' => 0,
                    'level' => $acc['level'],
                ]
            );
        }
    }
}
