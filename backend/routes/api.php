<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\StockAdjustmentController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\RegisterSessionController;
use App\Http\Controllers\Api\V1\POSController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\HeldSaleController;
use App\Http\Controllers\Api\V1\CreditSaleController;
use App\Http\Controllers\Api\V1\CreditCustomerController;
use App\Http\Controllers\Api\V1\InstallmentController;
use App\Http\Controllers\Api\V1\ChartOfAccountsController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BankReconciliationController;
use App\Http\Controllers\Api\V1\FiscalYearController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Public auth routes
    Route::post('auth/login', [AuthController::class, 'login']);

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
        Route::apiResource('roles', RoleController::class);
        Route::apiResource('branches', BranchController::class);

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

        Route::apiResource('payment-methods', PaymentMethodController::class);
        Route::apiResource('cash-registers', CashRegisterController::class);
        
        Route::apiResource('register-sessions', RegisterSessionController::class)->only(['index', 'show', 'store']);
        Route::post('register-sessions/{register_session}/close', [RegisterSessionController::class, 'close']);
        Route::get('register-sessions/current', [RegisterSessionController::class, 'current']);

        // POS Operations
        Route::post('pos/sale', [POSController::class, 'createSale']);
        Route::get('pos/products', [POSController::class, 'products']);
        Route::get('pos/product/{barcode}', [POSController::class, 'findByBarcode']);

        Route::apiResource('sales', SaleController::class)->only(['index', 'show']);
        Route::post('sales/{sale}/void', [SaleController::class, 'void']);
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt']);

        Route::apiResource('sale-returns', SaleReturnController::class);
        
        Route::apiResource('held-sales', HeldSaleController::class);
        Route::post('held-sales/{held_sale}/retrieve', [HeldSaleController::class, 'retrieve']);

        // Installments / Credit
        Route::apiResource('credit-sales', CreditSaleController::class);
        Route::post('credit-sales/{credit_sale}/payment', [CreditSaleController::class, 'recordPayment']);
        Route::get('credit-sales/{credit_sale}/schedule', [CreditSaleController::class, 'schedule']);

        Route::apiResource('credit-customers', CreditCustomerController::class);
        Route::post('credit-customers/{credit_customer}/verify', [CreditCustomerController::class, 'verify']);

        Route::get('installments/overdue', [InstallmentController::class, 'overdue']);
        Route::get('installments/due-today', [InstallmentController::class, 'dueToday']);
        Route::get('installments/upcoming', [InstallmentController::class, 'upcoming']);

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

        // Reports
        Route::prefix('reports')->group(function () {
            // Sales Reports
            Route::get('sales/summary', [ReportController::class, 'salesSummary']);
            Route::get('sales/by-product', [ReportController::class, 'salesByProduct']);
            Route::get('sales/by-customer', [ReportController::class, 'salesByCustomer']);
            Route::get('sales/by-branch', [ReportController::class, 'salesByBranch']);
            Route::get('sales/by-cashier', [ReportController::class, 'salesByCashier']);

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
        });
    });
});
