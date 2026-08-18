<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BankReconciliationController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BranchSettingController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\CashTransactionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ChartOfAccountsController;
use App\Http\Controllers\Api\V1\CommissionRuleController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\CreditApplicationController;
use App\Http\Controllers\Api\V1\CreditCustomerController;
use App\Http\Controllers\Api\V1\CreditSaleController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FiscalYearController;
use App\Http\Controllers\Api\V1\GRNController;
use App\Http\Controllers\Api\V1\HeldSaleController;
use App\Http\Controllers\Api\V1\InstallmentController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\LoyaltyTransactionController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PaymentReminderController;
use App\Http\Controllers\Api\V1\POSController;
use App\Http\Controllers\Api\V1\PricingController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseBillController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\RecipeController;
use App\Http\Controllers\Api\V1\RegisterSessionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TaxRateController;
use App\Http\Controllers\Api\V1\TenantSettingController;
use App\Http\Controllers\Api\V1\ToppingController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('auth/refresh', [AuthController::class, 'refresh']); // Allow refresh with expiring/expired tokens

    // Protected routes
    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/user', [AuthController::class, 'user']);
        Route::put('auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('auth/password', [AuthController::class, 'changePassword']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('dashboard/sales-chart', [DashboardController::class, 'salesChart']);
        Route::get('dashboard/top-products', [DashboardController::class, 'topProducts']);

        // Users & Roles
        Route::apiResource('users', UserController::class);
        Route::get('permissions', [RoleController::class, 'listPermissions']);
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('branches', BranchController::class);
        Route::get('branches/{branch}/settings', [BranchSettingController::class, 'show']);
        Route::put('branches/{branch}/settings', [BranchSettingController::class, 'update']);

        // Inventory
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('units', UnitController::class);
        Route::apiResource('warehouses', WarehouseController::class);
        Route::apiResource('suppliers', SupplierController::class);

        Route::apiResource('products', ProductController::class);
        Route::get('products/{product}/stock', [ProductController::class, 'stock']);
        Route::post('products/{product}/variants', [ProductController::class, 'storeVariant']);
        Route::put('products/{product}/variants/{variant}', [ProductController::class, 'updateVariant']);
        Route::delete('products/{product}/variants/{variant}', [ProductController::class, 'destroyVariant']);

        Route::apiResource('purchase-orders', PurchaseOrderController::class);
        Route::post('purchase-orders/{purchase_order}/submit', [PurchaseOrderController::class, 'submit']);
        Route::post('purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive']);
        Route::post('purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel']);

        Route::apiResource('goods-received-notes', GRNController::class)->only(['index', 'store', 'show']);
        Route::apiResource('purchase-bills', PurchaseBillController::class)->only(['index', 'store', 'show']);
        Route::post('purchase-bills/{purchase_bill}/pay', [PurchaseBillController::class, 'pay']);

        Route::apiResource('stock-transfers', StockTransferController::class);
        Route::post('stock-transfers/{stock_transfer}/approve', [StockTransferController::class, 'approve']);
        Route::post('stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete']);

        Route::apiResource('stock-adjustments', StockAdjustmentController::class);
        Route::post('stock-adjustments/{stock_adjustment}/approve', [StockAdjustmentController::class, 'approve']);
        Route::post('stock-adjustments/{stock_adjustment}/reject', [StockAdjustmentController::class, 'reject']);

        // POS
        Route::apiResource('customers', CustomerController::class);
        Route::get('customers/{customer}/transactions', [CustomerController::class, 'transactions']);
        Route::get('customers/{customer}/credit-history', [CustomerController::class, 'creditHistory']);

        // Pricing Service
        Route::post('pricing/calculate', [PricingController::class, 'calculate']);
        Route::post('pricing/calculate-line-item', [PricingController::class, 'calculateLineItem']);
        Route::post('pricing/calculate-discount', [PricingController::class, 'calculateDiscount']);
        Route::post('pricing/calculate-tax', [PricingController::class, 'calculateTax']);

        // Payment Processing Service (No Gateway)
        Route::post('payments/validate', [PaymentController::class, 'validatePayments']);
        Route::post('payments/calculate-change', [PaymentController::class, 'calculateChange']);
        Route::post('payments/calculate-tip', [PaymentController::class, 'calculateTip']);
        Route::post('payments/calculate-total', [PaymentController::class, 'calculateTotal']);
        Route::post('payments/allocate-tip', [PaymentController::class, 'allocateTip']);
        Route::post('payments/summary', [PaymentController::class, 'paymentSummary']);
        Route::post('payments/status', [PaymentController::class, 'paymentStatus']);

        Route::apiResource('payment-methods', PaymentMethodController::class);
        Route::apiResource('cash-registers', CashRegisterController::class);

        Route::get('register-sessions/current', [RegisterSessionController::class, 'current']);
        Route::apiResource('register-sessions', RegisterSessionController::class)->only(['index', 'show', 'store']);
        Route::post('register-sessions/{register_session}/close', [RegisterSessionController::class, 'close']);
        Route::post('register-sessions/{register_session}/cash-in', [RegisterSessionController::class, 'cashIn']);
        Route::post('register-sessions/{register_session}/cash-out', [RegisterSessionController::class, 'cashOut']);

        // Recipe Management
        Route::apiResource('recipes', RecipeController::class);
        Route::get('recipes/{recipe}/nutrition-info', [RecipeController::class, 'getNutritionInfo']);
        Route::apiResource('toppings', ToppingController::class);

        // POS Operations
        Route::prefix('pos')->group(function () {
            Route::post('sale', [POSController::class, 'createSale']);
            Route::get('products', [POSController::class, 'products']);
            Route::get('product/{barcode}', [POSController::class, 'findByBarcode']);
            Route::get('customers/{customer}/loyalty-points', [POSController::class, 'calculateLoyaltyPoints']);
        });

        Route::apiResource('sales', SaleController::class)->only(['index', 'show']);
        Route::post('sales/{sale}/void', [SaleController::class, 'void']);
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt']);

        Route::apiResource('sale-returns', SaleReturnController::class);

        Route::apiResource('held-sales', HeldSaleController::class);
        Route::post('held-sales/{held_sale}/retrieve', [HeldSaleController::class, 'retrieve']);

        Route::apiResource('coupons', CouponController::class);
        Route::post('coupons/validate', [CouponController::class, 'validateCode']);
        // Installments / Credit
        Route::apiResource('credit-sales', CreditSaleController::class);
        Route::post('credit-sales/{credit_sale}/payment', [CreditSaleController::class, 'recordPayment']);
        Route::get('credit-sales/{credit_sale}/schedule', [CreditSaleController::class, 'schedule']);

        Route::apiResource('credit-customers', CreditCustomerController::class);
        Route::post('credit-customers/{credit_customer}/verify', [CreditCustomerController::class, 'verify']);

        Route::get('installments/overdue', [InstallmentController::class, 'overdue']);
        Route::get('installments/due-today', [InstallmentController::class, 'dueToday']);
        Route::get('installments/upcoming', [InstallmentController::class, 'upcoming']);
        Route::apiResource('installments', InstallmentController::class);
        Route::post('installments/{installment}/pay', [InstallmentController::class, 'pay']);

        // Accounting
        Route::apiResource('accounts', ChartOfAccountsController::class);
        Route::get('accounts-tree', [ChartOfAccountsController::class, 'tree']);

        Route::apiResource('journal-entries', JournalEntryController::class);
        Route::post('journal-entries/{journal_entry}/post', [JournalEntryController::class, 'post']);
        Route::post('journal-entries/{journal_entry}/void', [JournalEntryController::class, 'void']);

        Route::apiResource('bank-accounts', BankAccountController::class);
        Route::get('bank-accounts/{bank_account}/transactions', [BankAccountController::class, 'transactions']);

        Route::apiResource('bank-reconciliations', BankReconciliationController::class);
        Route::post('bank-reconciliations/{bank_reconciliation}/complete', [BankReconciliationController::class, 'complete']);

        Route::apiResource('fiscal-years', FiscalYearController::class);
        Route::post('fiscal-years/{fiscal_year}/close', [FiscalYearController::class, 'close']);

        // Tax Rates (Fix 12)
        Route::apiResource('tax-rates', TaxRateController::class);

        // Commission Rules
        Route::apiResource('commission-rules', CommissionRuleController::class);
        Route::get('commission-rules/active', [CommissionRuleController::class, 'active']);

        // Budgets
        Route::apiResource('budgets', BudgetController::class);
        Route::get('budgets/fiscal-year/{fiscalYearId}', [BudgetController::class, 'byFiscalYear']);
        Route::get('budgets/{budget}/vs-actual', [BudgetController::class, 'vsActual']);

        // Credit Applications
        Route::apiResource('credit-applications', CreditApplicationController::class);
        Route::post('credit-applications/{credit_application}/approve', [CreditApplicationController::class, 'approve']);
        Route::post('credit-applications/{credit_application}/reject', [CreditApplicationController::class, 'reject']);

        // Payment Reminders
        Route::apiResource('payment-reminders', PaymentReminderController::class);
        Route::post('payment-reminders/{payment_reminder}/send', [PaymentReminderController::class, 'send']);
        Route::get('payment-reminders/pending', [PaymentReminderController::class, 'pending']);
        Route::get('payment-reminders/overdue', [PaymentReminderController::class, 'overdue']);

        // Cash Transactions
        Route::apiResource('cash-transactions', CashTransactionController::class);
        Route::get('cash-transactions/type/{type}', [CashTransactionController::class, 'getByType']);
        Route::get('cash-transactions/daily-totals', [CashTransactionController::class, 'dailyTotals']);

        // Loyalty Transactions
        Route::apiResource('loyalty-transactions', LoyaltyTransactionController::class);
        Route::get('loyalty-transactions/customer/{customer}/balance', [LoyaltyTransactionController::class, 'getBalance']);
        Route::get('loyalty-transactions/customer/{customer}/history', [LoyaltyTransactionController::class, 'getHistory']);

        // Tenant Settings
        Route::apiResource('tenant-settings', TenantSettingController::class);
        Route::get('tenant-settings/group/{group}', [TenantSettingController::class, 'getByGroup']);
        Route::get('tenant-settings/key/{key}', [TenantSettingController::class, 'getByKey']);
        Route::put('tenant-settings/batch-update', [TenantSettingController::class, 'batchUpdate']);

        // Reports
        Route::prefix('reports')->group(function () {
            // Sales Reports
            Route::get('sales/summary', [ReportController::class, 'salesSummary']);
            Route::get('sales/by-product', [ReportController::class, 'salesByProduct']);
            Route::get('sales/by-customer', [ReportController::class, 'salesByCustomer']);
            Route::get('sales/by-branch', [ReportController::class, 'salesByBranch']);
            Route::get('sales/by-cashier', [ReportController::class, 'salesByCashier']);
            Route::get('sales/by-payment-method', [ReportController::class, 'salesByPaymentMethod']);

            // Inventory Reports
            Route::get('inventory/stock-levels', [ReportController::class, 'stockLevels']);
            Route::get('inventory/stock-movements', [ReportController::class, 'stockMovements']);
            Route::get('inventory/low-stock', [ReportController::class, 'lowStock']);
            Route::get('inventory/valuation', [ReportController::class, 'stockValuation']);

            // Financial Reports
            Route::get('accounting/trial-balance', [ReportController::class, 'trialBalance']);
            Route::get('accounting/profit-loss', [ReportController::class, 'profitLoss']);
            Route::get('accounting/balance-sheet', [ReportController::class, 'balanceSheet']);
            Route::get('accounting/cash-flow', [ReportController::class, 'cashFlow']);
            Route::get('accounting/ledger', [ReportController::class, 'generalLedger']);

            // Installment Reports
            Route::get('installments/summary', [ReportController::class, 'installmentSummary']);
            Route::get('installments/overdue', [ReportController::class, 'overdueInstallments']);
            Route::get('installments/collections', [ReportController::class, 'collections']);
            Route::get('installments/aging', [ReportController::class, 'installmentAging']);
            Route::get('export', [ReportController::class, 'export']);
        });

        // ---- Phase 2 Routes ----

        // Brands (Section A)
        Route::apiResource('brands', \App\Http\Controllers\Api\V1\BrandController::class);

        // Products: clone + variant generation (Section A)
        Route::post('products/{product}/clone', [\App\Http\Controllers\Api\V1\ProductController::class, 'clone']);
        Route::post('products/{product}/generate-variants', [\App\Http\Controllers\Api\V1\ProductController::class, 'generateVariants']);

        // Product Images (Section A)
        Route::post('products/{product}/images', [\App\Http\Controllers\Api\V1\ProductController::class, 'uploadImage']);
        Route::delete('products/{product}/images/{image}', [\App\Http\Controllers\Api\V1\ProductController::class, 'deleteImage']);
        Route::patch('products/{product}/images/{image}/primary', [\App\Http\Controllers\Api\V1\ProductController::class, 'setPrimaryImage']);

        // Unit Categories (Section B)
        Route::apiResource('unit-categories', \App\Http\Controllers\Api\V1\UnitCategoryController::class);

        // Attribute Groups + Values (Section C)
        Route::apiResource('attribute-groups', \App\Http\Controllers\Api\V1\AttributeController::class);
        Route::get('attribute-groups/{attributeGroup}/values', [\App\Http\Controllers\Api\V1\AttributeController::class, 'values']);
        Route::post('attribute-groups/{attributeGroup}/values', [\App\Http\Controllers\Api\V1\AttributeController::class, 'storeValue']);
        Route::delete('attribute-values/{value}', [\App\Http\Controllers\Api\V1\AttributeController::class, 'destroyValue']);

        // Customer Statement (Section E)
        Route::get('customers/{customer}/statement', [\App\Http\Controllers\Api\V1\CustomerController::class, 'statement']);

        // Purchase Returns (Section G)
        Route::apiResource('purchase-returns', \App\Http\Controllers\Api\V1\PurchaseReturnController::class);
        Route::post('purchase-returns/{purchase_return}/approve', [\App\Http\Controllers\Api\V1\PurchaseReturnController::class, 'approve']);

        // Register Session Z-Report (Section H)
        Route::get('register-sessions/{register_session}/z-report', [\App\Http\Controllers\Api\V1\RegisterSessionController::class, 'zReport']);

        // ---- Phase 3 Routes ----

        // Batches (Section A)
        Route::prefix('batches')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\BatchController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\BatchController::class, 'store']);
            Route::get('/alerts/expiring', [\App\Http\Controllers\Api\V1\BatchController::class, 'expiryAlerts']);
            Route::post('/merge', [\App\Http\Controllers\Api\V1\BatchController::class, 'merge']);
            Route::get('/product/{productId}', [\App\Http\Controllers\Api\V1\BatchController::class, 'byProduct']);
            Route::get('/{batch}', [\App\Http\Controllers\Api\V1\BatchController::class, 'show']);
            Route::put('/{batch}', [\App\Http\Controllers\Api\V1\BatchController::class, 'update']);
            Route::post('/{batch}/transfer', [\App\Http\Controllers\Api\V1\BatchController::class, 'transfer']);
            Route::post('/{batch}/recall', [\App\Http\Controllers\Api\V1\BatchController::class, 'recall']);
            Route::post('/{batch}/split', [\App\Http\Controllers\Api\V1\BatchController::class, 'split']);
        });

        // Serial Numbers (Section B)
        Route::prefix('serial-numbers')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'index']);
            Route::post('/', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'store']);
            Route::post('/bulk', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'bulkStore']);
            Route::get('/search', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'search']);
            Route::get('/product/{productId}', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'byProduct']);
            Route::get('/{serial}', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'show']);
            Route::put('/{serial}', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'update']);
            Route::post('/{serial}/mark-defective', [\App\Http\Controllers\Api\V1\SerialNumberController::class, 'markDefective']);
        });

        // Lots (Section C)
        Route::apiResource('lots', \App\Http\Controllers\Api\V1\LotController::class);
        Route::post('lots/{lot}/approve-qc', [\App\Http\Controllers\Api\V1\LotController::class, 'approveQC']);
        Route::post('lots/{lot}/reject-qc', [\App\Http\Controllers\Api\V1\LotController::class, 'rejectQC']);
        Route::get('lots/{lot}/trace', [\App\Http\Controllers\Api\V1\LotController::class, 'trace']);

        // Product Bundles (Section D)
        Route::get('products/{product}/bundle', [\App\Http\Controllers\Api\V1\ProductBundleController::class, 'show']);
        Route::post('products/{product}/bundle', [\App\Http\Controllers\Api\V1\ProductBundleController::class, 'store']);
        Route::put('products/{product}/bundle', [\App\Http\Controllers\Api\V1\ProductBundleController::class, 'update']);
        Route::delete('products/{product}/bundle', [\App\Http\Controllers\Api\V1\ProductBundleController::class, 'destroy']);
        Route::post('products/{product}/bundle/preview', [\App\Http\Controllers\Api\V1\ProductBundleController::class, 'preview']);

        // Opening Stock (Section F)
        Route::get('opening-stock', [\App\Http\Controllers\Api\V1\OpeningStockController::class, 'index']);
        Route::post('opening-stock', [\App\Http\Controllers\Api\V1\OpeningStockController::class, 'store']);
        Route::get('opening-stock/{openingStock}', [\App\Http\Controllers\Api\V1\OpeningStockController::class, 'show']);
        Route::delete('opening-stock/{openingStock}', [\App\Http\Controllers\Api\V1\OpeningStockController::class, 'destroy']);
        Route::post('opening-stock/{openingStock}/approve', [\App\Http\Controllers\Api\V1\OpeningStockController::class, 'approve']);

        // ---- Phase 4 Routes ----

        // Auth extras (PIN + audit)
        Route::post('auth/pin-login', [\App\Http\Controllers\Api\V1\AuthController::class, 'pinLogin'])->withoutMiddleware(['auth:sanctum']);
        Route::post('auth/set-pin', [\App\Http\Controllers\Api\V1\AuthController::class, 'setPin']);
        Route::post('auth/change-pin', [\App\Http\Controllers\Api\V1\AuthController::class, 'changePin']);
        Route::get('audit-logs/logins', [\App\Http\Controllers\Api\V1\AuthController::class, 'auditLogs']);

        // Expense Management
        Route::apiResource('expense-categories', \App\Http\Controllers\Api\V1\ExpenseCategoryController::class);
        Route::apiResource('expenses', \App\Http\Controllers\Api\V1\ExpenseController::class);
        Route::post('expenses/{expense}/approve', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'approve']);

        // Finance Reports — AR / AP Aging + Supplier Ledger
        Route::get('reports/finance/ar-aging', [\App\Http\Controllers\Api\V1\ReportController::class, 'arAging']);
        // @deprecated – use reports/purchases/ap-aging (PurchaseReportController) instead
        Route::get('reports/finance/ap-aging', [\App\Http\Controllers\Api\V1\ReportController::class, 'apAging']);
        // @deprecated – use reports/purchases/supplier-ledger (PurchaseReportController) instead
        Route::get('suppliers/{supplier}/ledger', [\App\Http\Controllers\Api\V1\ReportController::class, 'supplierLedger']);

        // Sales Commissions (rules use CommissionRuleController routes above)
        Route::get('sale-commissions', [\App\Http\Controllers\Api\V1\CommissionController::class, 'index']);
        Route::post('sale-commissions/mark-paid', [\App\Http\Controllers\Api\V1\CommissionController::class, 'markPaid']);

        // Delivery Orders
        Route::apiResource('delivery-orders', \App\Http\Controllers\Api\V1\DeliveryOrderController::class);
        Route::post('delivery-orders/{deliveryOrder}/dispatch', [\App\Http\Controllers\Api\V1\DeliveryOrderController::class, 'dispatch']);
        Route::post('delivery-orders/{deliveryOrder}/deliver', [\App\Http\Controllers\Api\V1\DeliveryOrderController::class, 'deliver']);

        // Quotations
        Route::apiResource('quotations', \App\Http\Controllers\Api\V1\QuotationController::class);
        Route::post('quotations/{quotation}/convert-to-sale', [\App\Http\Controllers\Api\V1\QuotationController::class, 'convertToSale']);
        Route::post('quotations/{quotation}/send', [\App\Http\Controllers\Api\V1\QuotationController::class, 'send']);

        // ---- Phase 5 Routes ----

        // Purchase Reports
        Route::prefix('reports/purchases')->group(function () {
            Route::get('summary', [\App\Http\Controllers\Api\V1\PurchaseReportController::class, 'summary']);
            Route::get('by-supplier', [\App\Http\Controllers\Api\V1\PurchaseReportController::class, 'bySupplier']);
            Route::get('by-product', [\App\Http\Controllers\Api\V1\PurchaseReportController::class, 'byProduct']);
            Route::get('by-category', [\App\Http\Controllers\Api\V1\PurchaseReportController::class, 'byCategory']);
            Route::get('ap-aging', [\App\Http\Controllers\Api\V1\PurchaseReportController::class, 'apAging']);
            Route::get('supplier-ledger', [\App\Http\Controllers\Api\V1\PurchaseReportController::class, 'supplierLedger']);
            Route::get('price-history', [\App\Http\Controllers\Api\V1\PurchaseReportController::class, 'purchasePriceHistory']);
        });

        // Product Reports
        Route::prefix('reports/products')->group(function () {
            Route::get('{productId}/ledger', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'stockLedger']);
            Route::get('margin', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'profitMargin']);
            Route::get('slow-moving', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'slowMoving']);
            Route::get('dead-stock', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'deadStock']);
            Route::get('stock-aging', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'stockAging']);
            Route::get('expiring-soon', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'expiringSoon']);
            Route::get('expired', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'expired']);
            Route::get('variant-sales', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'variantSales']);
            Route::get('purchase-price-history', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'purchasePriceHistory']);
            Route::get('selling-price-history', [\App\Http\Controllers\Api\V1\ProductReportController::class, 'sellingPriceHistory']);
        });

        // Customer Reports
        Route::prefix('reports/customers')->group(function () {
            Route::get('balances', [\App\Http\Controllers\Api\V1\CustomerReportController::class, 'balances']);
            Route::get('ar-aging', [\App\Http\Controllers\Api\V1\CustomerReportController::class, 'arAging']);
            Route::get('top-customers', [\App\Http\Controllers\Api\V1\CustomerReportController::class, 'topCustomers']);
            Route::get('loyalty', [\App\Http\Controllers\Api\V1\CustomerReportController::class, 'loyaltyPoints']);
            Route::get('{id}/statement', [\App\Http\Controllers\Api\V1\CustomerReportController::class, 'statement']);
        });

        // Tax Reports
        Route::prefix('reports/tax')->group(function () {
            Route::get('collected', [\App\Http\Controllers\Api\V1\TaxReportController::class, 'taxCollected']);
            Route::get('paid', [\App\Http\Controllers\Api\V1\TaxReportController::class, 'taxPaid']);
            Route::get('summary', [\App\Http\Controllers\Api\V1\TaxReportController::class, 'summary']);
        });
        Route::get('reports/finance/expenses-summary', [\App\Http\Controllers\Api\V1\TaxReportController::class, 'expensesSummary']);
        Route::get('reports/sales/by-salesperson', [\App\Http\Controllers\Api\V1\ReportController::class, 'bySalesperson']);

        // Settings
        Route::get('settings/sms', [\App\Http\Controllers\Api\V1\SettingsController::class, 'smsSettings']);
        Route::put('settings/sms', [\App\Http\Controllers\Api\V1\SettingsController::class, 'updateSmsSettings']);
        Route::post('settings/sms/test', [\App\Http\Controllers\Api\V1\SettingsController::class, 'testSms']);
        Route::get('settings/email', [\App\Http\Controllers\Api\V1\SettingsController::class, 'emailSettings']);
        Route::put('settings/email', [\App\Http\Controllers\Api\V1\SettingsController::class, 'updateEmailSettings']);
        Route::post('settings/email/test', [\App\Http\Controllers\Api\V1\SettingsController::class, 'testEmail']);
        Route::get('settings/label-templates', [\App\Http\Controllers\Api\V1\SettingsController::class, 'labelTemplates']);
        Route::post('settings/label-templates', [\App\Http\Controllers\Api\V1\SettingsController::class, 'storeLabelTemplate']);
        Route::put('settings/label-templates/{labelTemplate}', [\App\Http\Controllers\Api\V1\SettingsController::class, 'updateLabelTemplate']);
        Route::delete('settings/label-templates/{labelTemplate}', [\App\Http\Controllers\Api\V1\SettingsController::class, 'destroyLabelTemplate']);
        Route::get('settings/installment-plan-templates', [\App\Http\Controllers\Api\V1\SettingsController::class, 'installmentPlanTemplates']);
        Route::post('settings/installment-plan-templates', [\App\Http\Controllers\Api\V1\SettingsController::class, 'storeInstallmentPlanTemplate']);
        Route::put('settings/installment-plan-templates/{installmentPlanTemplate}', [\App\Http\Controllers\Api\V1\SettingsController::class, 'updateInstallmentPlanTemplate']);
        Route::delete('settings/installment-plan-templates/{installmentPlanTemplate}', [\App\Http\Controllers\Api\V1\SettingsController::class, 'destroyInstallmentPlanTemplate']);

        // ---- Document PDF Downloads + Partial Payments ----

        // Sale Invoice
        Route::get('sales/{id}/invoice/download', [\App\Http\Controllers\Api\V1\DocumentController::class, 'downloadInvoice']);
        Route::get('sales/{id}/invoice/preview', [\App\Http\Controllers\Api\V1\DocumentController::class, 'streamInvoice']);

        // Sale Partial Payments
        Route::get('sales/{saleId}/payments', [\App\Http\Controllers\Api\V1\DocumentController::class, 'salePayments']);
        Route::post('sales/{saleId}/payments', [\App\Http\Controllers\Api\V1\DocumentController::class, 'recordSalePayment']);

        // Payment Receipt
        Route::get('sale-payments/{paymentId}/receipt', [\App\Http\Controllers\Api\V1\DocumentController::class, 'downloadPaymentReceipt']);

        // Purchase Order
        Route::get('purchase-orders/{id}/download', [\App\Http\Controllers\Api\V1\DocumentController::class, 'downloadPurchaseOrder']);

        // Purchase Bill
        Route::get('purchase-bills/{id}/download', [\App\Http\Controllers\Api\V1\DocumentController::class, 'downloadPurchaseBill']);
        Route::get('purchase-bills/{billId}/payments', [\App\Http\Controllers\Api\V1\DocumentController::class, 'billPayments']);
        Route::post('purchase-bills/{billId}/payments', [\App\Http\Controllers\Api\V1\DocumentController::class, 'recordBillPayment']);

        // Installment Receipt
        Route::get('installments/{installmentId}/receipt', [\App\Http\Controllers\Api\V1\DocumentController::class, 'downloadInstallmentReceipt']);

        // =============================================
        //  HR / PAYROLL MODULE
        // =============================================

        // Departments
        Route::apiResource('departments', \App\Http\Controllers\Api\V1\DepartmentController::class);

        // Positions
        Route::apiResource('positions', \App\Http\Controllers\Api\V1\PositionController::class);

        // Employees
        Route::get('employees', \App\Http\Controllers\Api\V1\EmployeeController::class.'@index');
        Route::post('employees', \App\Http\Controllers\Api\V1\EmployeeController::class.'@store');
        Route::get('employees/{id}', \App\Http\Controllers\Api\V1\EmployeeController::class.'@show');
        Route::put('employees/{id}', \App\Http\Controllers\Api\V1\EmployeeController::class.'@update');
        Route::delete('employees/{id}', \App\Http\Controllers\Api\V1\EmployeeController::class.'@destroy');
        Route::post('employees/{id}/photo', \App\Http\Controllers\Api\V1\EmployeeController::class.'@uploadPhoto');
        Route::get('employees/{id}/payslips', \App\Http\Controllers\Api\V1\EmployeeController::class.'@payslips');

        // Salary Components & Employee Salary Structures
        Route::get('salary-components', \App\Http\Controllers\Api\V1\SalaryStructureController::class.'@indexComponents');
        Route::post('salary-components', \App\Http\Controllers\Api\V1\SalaryStructureController::class.'@storeComponent');
        Route::put('salary-components/{id}', \App\Http\Controllers\Api\V1\SalaryStructureController::class.'@updateComponent');
        Route::delete('salary-components/{id}', \App\Http\Controllers\Api\V1\SalaryStructureController::class.'@destroyComponent');
        Route::get('employees/{employeeId}/salary', \App\Http\Controllers\Api\V1\SalaryStructureController::class.'@getEmployeeSalary');
        Route::put('employees/{employeeId}/salary', \App\Http\Controllers\Api\V1\SalaryStructureController::class.'@setEmployeeSalary');

        // Attendance
        Route::get('attendance', \App\Http\Controllers\Api\V1\AttendanceController::class.'@index');
        Route::post('attendance', \App\Http\Controllers\Api\V1\AttendanceController::class.'@store');
        Route::post('attendance/bulk', \App\Http\Controllers\Api\V1\AttendanceController::class.'@bulkStore');
        Route::get('attendance/summary', \App\Http\Controllers\Api\V1\AttendanceController::class.'@summary');
        Route::put('attendance/{id}', \App\Http\Controllers\Api\V1\AttendanceController::class.'@update');
        Route::delete('attendance/{id}', \App\Http\Controllers\Api\V1\AttendanceController::class.'@destroy');

        // Leave Types
        Route::get('leave-types', \App\Http\Controllers\Api\V1\LeaveController::class.'@indexTypes');
        Route::post('leave-types', \App\Http\Controllers\Api\V1\LeaveController::class.'@storeType');
        Route::put('leave-types/{id}', \App\Http\Controllers\Api\V1\LeaveController::class.'@updateType');
        Route::delete('leave-types/{id}', \App\Http\Controllers\Api\V1\LeaveController::class.'@destroyType');

        // Leave Requests
        Route::get('leave-requests', \App\Http\Controllers\Api\V1\LeaveController::class.'@indexRequests');
        Route::post('leave-requests', \App\Http\Controllers\Api\V1\LeaveController::class.'@storeRequest');
        Route::put('leave-requests/{id}/approve', \App\Http\Controllers\Api\V1\LeaveController::class.'@approve');
        Route::put('leave-requests/{id}/reject', \App\Http\Controllers\Api\V1\LeaveController::class.'@reject');
        Route::get('employees/{employeeId}/leave-balance', \App\Http\Controllers\Api\V1\LeaveController::class.'@employeeBalance');

        // Employee Loans
        Route::get('employee-loans', \App\Http\Controllers\Api\V1\EmployeeLoanController::class.'@index');
        Route::post('employee-loans', \App\Http\Controllers\Api\V1\EmployeeLoanController::class.'@store');
        Route::get('employee-loans/{id}', \App\Http\Controllers\Api\V1\EmployeeLoanController::class.'@show');
        Route::put('employee-loans/{id}', \App\Http\Controllers\Api\V1\EmployeeLoanController::class.'@update');

        // Payroll
        Route::get('payroll-periods', \App\Http\Controllers\Api\V1\PayrollController::class.'@index');
        Route::post('payroll-periods', \App\Http\Controllers\Api\V1\PayrollController::class.'@store');
        Route::get('payroll-periods/{id}', \App\Http\Controllers\Api\V1\PayrollController::class.'@show');
        Route::get('payroll-periods/{id}/summary', \App\Http\Controllers\Api\V1\PayrollController::class.'@summary');
        Route::post('payroll-periods/{id}/run', \App\Http\Controllers\Api\V1\PayrollController::class.'@runPayroll');
        Route::post('payroll-periods/{id}/approve', \App\Http\Controllers\Api\V1\PayrollController::class.'@approve');
        Route::post('payroll-periods/{id}/post-accounting', \App\Http\Controllers\Api\V1\PayrollController::class.'@postAccounting');
        Route::get('payroll-runs/{id}/payslip', \App\Http\Controllers\Api\V1\PayrollController::class.'@payslip');

        // ── Restaurant Module ────────────────────────────────────────────

        // Tables CRUD
        Route::get('restaurant/tables', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'tables']);
        Route::post('restaurant/tables', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'storeTable']);
        Route::put('restaurant/tables/{table}', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'updateTable']);
        Route::delete('restaurant/tables/{table}', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'destroyTable']);

        // Table queries
        Route::get('restaurant/tables/available', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'availableTables']);
        Route::get('restaurant/tables/area', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'tablesByArea']);

        // Table actions
        Route::put('restaurant/tables/{table}/status', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'updateTableStatus']);
        Route::post('restaurant/tables/{table}/open', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'openTable']);
        Route::post('restaurant/tables/{table}/close', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'closeTable']);
        Route::post('restaurant/tables/{table}/transfer', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'transferTable']);

        // Kitchen operations
        Route::post('restaurant/held-sales/{held_sale}/kot', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'generateKOT']);
        Route::get('restaurant/orders/pending', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'pendingOrders']);
        Route::get('restaurant/stats/turnover', [\App\Http\Controllers\Api\V1\RestaurantController::class, 'tableStats']);

        // ── Manufacturing / BOM Module ───────────────────────────────────

        Route::get('manufacturing/orders', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'index']);
        Route::post('manufacturing/orders', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'store']);
        Route::get('manufacturing/orders/{order}', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'show']);
        Route::put('manufacturing/orders/{order}', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'update']);
        Route::delete('manufacturing/orders/{order}', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'destroy']);
        Route::post('manufacturing/orders/{order}/start', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'start']);
        Route::post('manufacturing/orders/{order}/complete', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'complete']);
        Route::post('manufacturing/orders/{order}/cancel', [\App\Http\Controllers\Api\V1\ManufacturingController::class, 'cancel']);
    });
});
