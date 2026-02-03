# Multi-Tenant ERP System Implementation Plan

## Overview
Build a comprehensive multi-tenant ERP system with Inventory, POS, Installments, and Accounting modules using Laravel 12.x API backend and React.js with Tailwind CSS frontend.

## Architecture Decisions
- **Multi-tenancy**: Shared tables with `tenant_id` column
- **Authentication**: Laravel Sanctum (SPA token-based)
- **Primary Keys**: Auto-increment integers with `tenant_id` composite indexes
- **Development**: Full-stack parallel (backend + frontend simultaneously)
- **UI Framework**: shadcn/ui + Tailwind CSS (accessible, customizable components)

---

## Project Structure

```
ERP/
├── backend/                    # Laravel API
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Models/
│   │   ├── Services/
│   │   ├── Traits/
│   │   └── Scopes/
│   ├── database/migrations/
│   ├── routes/api.php
│   └── ...
│
└── frontend/                   # React + Tailwind
    ├── src/
    │   ├── components/
    │   ├── pages/
    │   ├── hooks/
    │   ├── services/
    │   ├── store/
    │   └── utils/
    └── ...
```

---

## Phase 1: Foundation Setup

### 1.1 Laravel Backend Setup
**Files to create:**
- `backend/` - Laravel project via `composer create-project`
- `backend/app/Traits/BelongsToTenant.php` - Multi-tenant trait
- `backend/app/Scopes/TenantScope.php` - Global tenant scope
- `backend/app/Http/Middleware/SetTenantMiddleware.php`
- `backend/app/Models/Tenant.php`
- `backend/app/Models/User.php` (modify)

**Database migrations:**
```
tenants (id, name, slug, domain, settings, status, created_at, updated_at)
users (id, tenant_id, name, email, password, role, branch_id, is_active, ...)
branches (id, tenant_id, name, address, phone, is_main, ...)
roles (via Spatie Permission)
permissions (via Spatie Permission)
activity_log (via Spatie Activity Log)
```

**Packages to install:**
- spatie/laravel-permission
- spatie/laravel-activitylog
- spatie/laravel-query-builder
- laravel/sanctum
- barryvdh/laravel-dompdf
- maatwebsite/excel
- intervention/image

### 1.2 React Frontend Setup with shadcn/ui
**Files to create:**
- `frontend/` - Vite React project with TypeScript
- `frontend/src/contexts/AuthContext.tsx`
- `frontend/src/contexts/TenantContext.tsx`
- `frontend/src/lib/api.ts` - Axios instance
- `frontend/src/components/Layout/` - Main layout components
- `frontend/src/pages/Auth/Login.tsx`
- `frontend/src/pages/Dashboard/`

**Setup steps:**
1. Create Vite React TypeScript project
2. Initialize shadcn/ui with `npx shadcn@latest init`
3. Add required shadcn/ui components

**shadcn/ui components to install:**
- button, input, label, textarea (forms)
- select, checkbox, radio-group, switch (form controls)
- dialog, sheet, drawer (modals/overlays)
- dropdown-menu, context-menu, menubar (navigation)
- table, data-table (data display - uses @tanstack/react-table)
- card, badge, avatar (display)
- tabs, accordion, collapsible (organization)
- command, popover, combobox (search/select)
- calendar, date-picker (dates)
- toast, sonner (notifications)
- form (react-hook-form + zod integration)
- sidebar (navigation layout)
- chart (recharts wrapper)
- skeleton, spinner (loading states)

**Additional packages:**
- axios
- react-router-dom
- @tanstack/react-query
- @tanstack/react-table (for data-table)
- zustand (state management)
- react-hook-form + @hookform/resolvers
- zod (schema validation)
- date-fns (date utilities)
- lucide-react (icons - shadcn default)
- recharts (charts)
- react-to-print (receipt printing)

**Frontend folder structure:**
```
frontend/src/
├── components/
│   ├── ui/              # shadcn/ui components (auto-generated)
│   ├── layout/          # App layout (Sidebar, Header, etc.)
│   ├── shared/          # Reusable composed components
│   ├── inventory/       # Inventory-specific components
│   ├── pos/             # POS-specific components
│   ├── installments/    # Installment-specific components
│   └── accounting/      # Accounting-specific components
├── pages/               # Route pages
├── hooks/               # Custom React hooks
├── lib/                 # Utilities (api, utils, etc.)
├── store/               # Zustand stores
├── types/               # TypeScript types/interfaces
└── schemas/             # Zod validation schemas
```

---

## Phase 2: Inventory Module

### 2.1 Backend - Database Migrations
```
categories (id, tenant_id, parent_id, name, slug, description, image, is_active)
units (id, tenant_id, name, abbreviation, is_base)
unit_conversions (id, tenant_id, from_unit_id, to_unit_id, conversion_factor)
products (id, tenant_id, sku, barcode, name, category_id, base_unit_id, cost_price, selling_price, reorder_level, is_active, has_variants, ...)
product_units (id, product_id, unit_id, conversion_factor, selling_price, barcode)
product_variants (id, product_id, sku, barcode, attributes, cost_price, selling_price)
warehouses (id, tenant_id, branch_id, name, address, is_default)
stock_levels (id, product_id, variant_id, warehouse_id, quantity, reserved_quantity)
suppliers (id, tenant_id, name, email, phone, address, contact_person, balance)
purchase_orders (id, tenant_id, supplier_id, warehouse_id, po_number, status, total, notes, ...)
purchase_order_items (id, purchase_order_id, product_id, variant_id, unit_id, quantity, unit_price, total)
stock_movements (id, tenant_id, type, reference_type, reference_id, product_id, variant_id, warehouse_id, quantity, notes, created_by)
stock_transfers (id, tenant_id, from_warehouse_id, to_warehouse_id, status, notes, ...)
stock_transfer_items (id, transfer_id, product_id, variant_id, quantity)
stock_adjustments (id, tenant_id, warehouse_id, type, reason, status, approved_by, ...)
stock_adjustment_items (id, adjustment_id, product_id, variant_id, quantity_before, quantity_after, difference)
```

### 2.2 Backend - Controllers & Services
- `ProductController` - CRUD, variants, units, stock info
- `CategoryController` - Hierarchical categories
- `WarehouseController` - Warehouse management
- `SupplierController` - Supplier management
- `PurchaseOrderController` - PO lifecycle (draft, submitted, received, cancelled)
- `StockMovementController` - Movement history
- `StockTransferController` - Inter-warehouse transfers
- `StockAdjustmentController` - Adjustments with approval
- `InventoryReportService` - Stock reports, valuation, movement reports

### 2.3 Frontend - Inventory Pages (shadcn/ui)
- `pages/Inventory/Products/` - DataTable with filtering, Create/Edit Dialog
- `pages/Inventory/Categories/` - Tree view with Command palette search
- `pages/Inventory/Warehouses/` - Card-based layout
- `pages/Inventory/Suppliers/` - DataTable with Sheet for details
- `pages/Inventory/PurchaseOrders/` - DataTable with status badges, detail Sheet
- `pages/Inventory/StockTransfers/` - Form with Combobox for warehouse selection
- `pages/Inventory/StockAdjustments/` - Form with approval Dialog
- `pages/Inventory/Reports/` - Charts with date-picker filters

---

## Phase 3: POS Module

### 3.1 Backend - Database Migrations
```
customers (id, tenant_id, code, name, email, phone, address, credit_limit, balance, points, ...)
payment_methods (id, tenant_id, name, code, is_active, settings)
cash_registers (id, tenant_id, branch_id, name, is_active)
register_sessions (id, register_id, user_id, opening_balance, closing_balance, expected_balance, difference, status, opened_at, closed_at)
sales (id, tenant_id, branch_id, customer_id, register_session_id, sale_number, type, subtotal, discount_amount, tax_amount, total, paid_amount, change_amount, payment_status, status, notes, sold_by, ...)
sale_items (id, sale_id, product_id, variant_id, unit_id, quantity, unit_price, discount, tax, total, cost_price)
sale_payments (id, sale_id, payment_method_id, amount, reference, ...)
sale_returns (id, tenant_id, sale_id, return_number, total, reason, status, processed_by, ...)
sale_return_items (id, return_id, sale_item_id, quantity, amount)
held_sales (id, tenant_id, register_session_id, customer_id, items_json, notes, held_by, ...)
discounts (id, tenant_id, name, type, value, min_purchase, start_date, end_date, is_active)
```

### 3.2 Backend - Controllers & Services
- `POSController` - Main POS operations (create sale, apply discount, process payment)
- `CustomerController` - Customer management, history, balance
- `CashRegisterController` - Register and session management
- `SaleController` - Sale history, void, receipt
- `SaleReturnController` - Returns processing
- `HeldSaleController` - Park/retrieve sales
- `POSReportService` - Daily sales, cashier reports, payment summaries

### 3.3 Frontend - POS Pages (shadcn/ui)
- `pages/POS/Terminal/` - Full-screen POS with product grid, cart sidebar
- `pages/POS/Customers/` - DataTable with customer detail Sheet
- `pages/POS/CashRegisters/` - Card layout with session management
- `pages/POS/Sales/` - DataTable with filters, receipt Dialog
- `pages/POS/Returns/` - Form with item selection
- `pages/POS/Reports/` - Charts and DataTables
- `components/pos/ProductGrid.tsx` - Grid of product Cards with search Command
- `components/pos/Cart.tsx` - Sheet sidebar with item list
- `components/pos/PaymentDialog.tsx` - Dialog with payment method Tabs
- `components/pos/ReceiptDialog.tsx` - Print-ready receipt view

---

## Phase 4: Installment/Credit Module

### 4.1 Backend - Database Migrations
```
credit_settings (id, tenant_id, max_credit_period_days, default_interest_rate, interest_type, penalty_rate, min_down_payment_percent, ...)
credit_customers (id, customer_id, credit_score, credit_limit, used_credit, status, verified_by, ...)
credit_sales (id, tenant_id, sale_id, customer_id, total_amount, down_payment, financed_amount, interest_rate, interest_amount, total_payable, installment_count, installment_frequency, start_date, end_date, status, ...)
installment_schedules (id, credit_sale_id, installment_number, due_date, principal_amount, interest_amount, total_amount, paid_amount, balance, status, paid_at)
installment_payments (id, installment_schedule_id, payment_method_id, amount, penalty_amount, reference, notes, received_by, ...)
credit_applications (id, tenant_id, customer_id, requested_amount, purpose, status, reviewed_by, ...)
payment_reminders (id, credit_sale_id, type, sent_at, response, ...)
```

### 4.2 Backend - Controllers & Services
- `CreditSaleController` - Create credit sale, view details, close
- `InstallmentController` - Payment schedule, receive payment
- `CreditCustomerController` - Customer credit profile, history
- `CreditApplicationController` - Credit applications workflow
- `CreditReportService` - Outstanding balances, overdue accounts, collection reports
- `PaymentReminderService` - Automated reminders (email/SMS integration point)

### 4.3 Frontend - Installment Pages (shadcn/ui)
- `pages/Installments/CreditSales/` - DataTable with status badges, detail Sheet
- `pages/Installments/Payments/` - Payment form with schedule Table
- `pages/Installments/Overdue/` - DataTable with overdue alerts (Badge)
- `pages/Installments/Customers/` - Credit profile Cards
- `pages/Installments/Reports/` - Collection charts, aging DataTable
- `components/installments/PaymentSchedule.tsx` - Table with status indicators
- `components/installments/PaymentDialog.tsx` - Form Dialog for recording payment

---

## Phase 5: Accounting Module

### 5.1 Backend - Database Migrations
```
fiscal_years (id, tenant_id, name, start_date, end_date, is_closed, closed_at)
accounting_periods (id, fiscal_year_id, name, start_date, end_date, is_closed)
account_types (id, name, category, normal_balance) -- Asset, Liability, Equity, Revenue, Expense
chart_of_accounts (id, tenant_id, parent_id, account_type_id, code, name, description, is_active, is_system, opening_balance)
journal_entries (id, tenant_id, entry_number, entry_date, reference_type, reference_id, description, status, posted_by, ...)
journal_entry_lines (id, journal_entry_id, account_id, debit, credit, description)
bank_accounts (id, tenant_id, account_id, bank_name, account_number, account_name, balance, is_active)
bank_transactions (id, bank_account_id, type, amount, reference, description, transaction_date, is_reconciled, reconciled_at)
bank_reconciliations (id, bank_account_id, statement_date, statement_balance, system_balance, difference, status, reconciled_by, ...)
tax_rates (id, tenant_id, name, rate, type, is_active)
budgets (id, tenant_id, fiscal_year_id, account_id, period, amount)
```

### 5.2 Backend - Controllers & Services
- `ChartOfAccountsController` - Account management
- `JournalEntryController` - Manual entries, view, post, reverse
- `BankAccountController` - Bank account management
- `BankReconciliationController` - Reconciliation process
- `FiscalYearController` - Year/period management
- `AccountingIntegrationService` - Auto journal entries from sales/purchases
- `FinancialReportService` - Trial balance, P&L, Balance sheet, Cash flow

### 5.3 Frontend - Accounting Pages (shadcn/ui)
- `pages/Accounting/ChartOfAccounts/` - Tree view with Collapsible nodes
- `pages/Accounting/JournalEntries/` - DataTable, entry form with dynamic lines
- `pages/Accounting/BankAccounts/` - Card layout with transaction history
- `pages/Accounting/Reconciliation/` - Split view with checkbox reconciliation
- `pages/Accounting/Reports/` - Financial statement Tables with export
- `pages/Accounting/FiscalYears/` - Period management with Calendar
- `components/accounting/AccountTree.tsx` - Hierarchical Collapsible tree
- `components/accounting/JournalEntryForm.tsx` - Dynamic debit/credit lines

---

## Phase 6: Reports & Dashboard

### 6.1 Backend - Report Services
- `DashboardService` - KPIs, charts data
- `SalesReportService` - Sales by period, product, customer, branch
- `InventoryReportService` - Stock valuation, movement, aging
- `FinancialReportService` - All financial statements
- `InstallmentReportService` - Collections, aging, projections

### 6.2 Frontend - Dashboard & Reports (shadcn/ui)
- `pages/Dashboard/` - KPI Cards, Charts (recharts via shadcn chart)
- `pages/Reports/Sales/` - DataTable with date-picker range filters
- `pages/Reports/Inventory/` - Stock DataTable with export Buttons
- `pages/Reports/Financial/` - Statement Tables with print view
- `pages/Reports/Installments/` - Aging DataTable, collection Charts
- `components/dashboard/KPICard.tsx` - Card with trend indicator
- `components/dashboard/SalesChart.tsx` - Area/Bar chart component
- `components/shared/ReportFilters.tsx` - Date range, branch Select
- `components/shared/DataTableToolbar.tsx` - Search, filters, export
- `components/shared/ExportDropdown.tsx` - PDF/Excel export menu

---

## API Routes Structure

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    // Auth
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/user', [AuthController::class, 'user']);
    
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        // Users & Roles
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('branches', BranchController::class);
        
        // Inventory
        Route::apiResource('products', ProductController::class);
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::apiResource('suppliers', SupplierController::class);
        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive']);
        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        
        // POS
        Route::post('pos/sale', [POSController::class, 'createSale']);
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('cash-registers', CashRegisterController::class);
        Route::apiResource('register-sessions', RegisterSessionController::class);
        Route::apiResource('sales', SaleController::class);
        Route::apiResource('sale-returns', SaleReturnController::class);
        Route::apiResource('held-sales', HeldSaleController::class);
        
        // Installments
        Route::apiResource('credit-sales', CreditSaleController::class);
        Route::post('credit-sales/{id}/payment', [CreditSaleController::class, 'recordPayment']);
        Route::apiResource('credit-customers', CreditCustomerController::class);
        Route::get('installments/overdue', [InstallmentController::class, 'overdue']);
        
        // Accounting
        Route::apiResource('accounts', ChartOfAccountsController::class);
        Route::apiResource('journal-entries', JournalEntryController::class);
        Route::post('journal-entries/{id}/post', [JournalEntryController::class, 'post']);
        Route::apiResource('bank-accounts', BankAccountController::class);
        Route::apiResource('bank-reconciliations', BankReconciliationController::class);
        Route::apiResource('fiscal-years', FiscalYearController::class);
        
        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('dashboard', [ReportController::class, 'dashboard']);
            Route::get('sales', [ReportController::class, 'sales']);
            Route::get('inventory', [ReportController::class, 'inventory']);
            Route::get('trial-balance', [ReportController::class, 'trialBalance']);
            Route::get('profit-loss', [ReportController::class, 'profitLoss']);
            Route::get('balance-sheet', [ReportController::class, 'balanceSheet']);
            Route::get('installments', [ReportController::class, 'installments']);
        });
    });
});
```

---

## Key Implementation Details

### Multi-Tenancy Implementation
```php
// app/Traits/BelongsToTenant.php
trait BelongsToTenant {
    protected static function bootBelongsToTenant() {
        static::addGlobalScope(new TenantScope);
        static::creating(function ($model) {
            $model->tenant_id = auth()->user()->tenant_id;
        });
    }
}
```

### Accounting Integration Points
- **Sales**: Auto-create journal entry (Debit: Cash/AR, Credit: Sales Revenue)
- **Purchases**: Auto-create journal entry (Debit: Inventory, Credit: AP/Cash)
- **Payments Received**: Update AR, record bank transaction
- **Payments Made**: Update AP, record bank transaction

### Stock Movement Tracking
- Every stock change creates a `stock_movement` record
- Types: purchase, sale, transfer_in, transfer_out, adjustment, return

---

## Verification Plan

### Backend Testing
1. Run `php artisan test` for all feature and unit tests
2. Test API endpoints with Postman/Insomnia
3. Verify tenant isolation by creating multiple tenants
4. Test accounting journal entry automation

### Frontend Testing
1. Run `npm run build` to verify no build errors
2. Test authentication flow (login, logout, token refresh)
3. Test each module CRUD operations
4. Test POS checkout flow end-to-end
5. Test installment payment recording
6. Verify reports generate correctly

### Integration Testing
1. Complete sale flow: POS sale -> Stock reduction -> Journal entry
2. Complete purchase flow: PO -> Receive -> Stock increase -> Journal entry
3. Credit sale flow: Sale -> Installment schedule -> Payments -> AR updates
4. Report accuracy verification against manual calculations

---

## Implementation Order

1. **Foundation** (Backend + Frontend setup, Auth, Multi-tenancy)
2. **Inventory Module** (Products, Categories, Stock management)
3. **POS Module** (Sales, Customers, Registers)
4. **Installment Module** (Credit sales, Payments)
5. **Accounting Module** (COA, Journal entries, Reports)
6. **Dashboard & Reports** (KPIs, All reports)
7. **Testing & Refinement**
