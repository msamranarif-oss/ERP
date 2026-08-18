<?php

namespace App\Providers;

use App\Models\AccountType;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\CreditSale;
use App\Models\CreditSaleItem;
use App\Models\Customer;
use App\Models\FiscalYear;
use App\Models\Installment;
use App\Models\InstallmentPayment;
use App\Models\InstallmentSchedule;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentReminder;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\RegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitConversion;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\AccountTypePolicy;
use App\Policies\CashRegisterPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ChartOfAccountPolicy;
use App\Policies\CreditSaleItemPolicy;
use App\Policies\CreditSalePolicy;
use App\Policies\CustomerPolicy;
use App\Policies\FiscalYearPolicy;
use App\Policies\InstallmentPaymentPolicy;
use App\Policies\InstallmentPolicy;
use App\Policies\InstallmentSchedulePolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\PaymentMethodPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentReminderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RegisterSessionPolicy;
use App\Policies\SaleItemPolicy;
use App\Policies\SalePaymentPolicy;
use App\Policies\SalePolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Policies\StockLevelPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UnitConversionPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserPolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Payment::class => \App\Policies\PaymentPolicy::class,
        \App\Models\Installment::class => \App\Policies\InstallmentPolicy::class,
        \App\Models\CreditSaleItem::class => \App\Policies\CreditSaleItemPolicy::class,
        \App\Models\InstallmentSchedule::class => \App\Policies\InstallmentSchedulePolicy::class,
        \App\Models\PaymentReminder::class => \App\Policies\PaymentReminderPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Branch::class => \App\Policies\BranchPolicy::class,
        \App\Models\Product::class => \App\Policies\ProductPolicy::class,
        \App\Models\Category::class => \App\Policies\CategoryPolicy::class,
        \App\Models\Unit::class => \App\Policies\UnitPolicy::class,
        \App\Models\Warehouse::class => \App\Policies\WarehousePolicy::class,
        \App\Models\Supplier::class => \App\Policies\SupplierPolicy::class,
        \App\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Models\Sale::class => \App\Policies\SalePolicy::class,
        \App\Models\RegisterSession::class => \App\Policies\RegisterSessionPolicy::class,
        \App\Models\CashRegister::class => \App\Policies\CashRegisterPolicy::class,
        \App\Models\ChartOfAccount::class => \App\Policies\ChartOfAccountPolicy::class,
        \App\Models\JournalEntry::class => \App\Policies\JournalEntryPolicy::class,
        \App\Models\Tenant::class => \App\Policies\TenantPolicy::class,
        \App\Models\StockLevel::class => \App\Policies\StockLevelPolicy::class,
        \App\Models\CreditSale::class => \App\Policies\CreditSalePolicy::class,
        \App\Models\AccountType::class => \App\Policies\AccountTypePolicy::class,
        \App\Models\InstallmentPayment::class => \App\Policies\InstallmentPaymentPolicy::class,
        \App\Models\SaleItem::class => \App\Policies\SaleItemPolicy::class,
        \App\Models\SalePayment::class => \App\Policies\SalePaymentPolicy::class,
        \App\Models\UnitConversion::class => \App\Policies\UnitConversionPolicy::class,
        \App\Models\PurchaseOrder::class => \App\Policies\PurchaseOrderPolicy::class,
        \App\Models\StockTransfer::class => \App\Policies\StockTransferPolicy::class,
        \App\Models\StockAdjustment::class => \App\Policies\StockAdjustmentPolicy::class,
        \App\Models\FiscalYear::class => \App\Policies\FiscalYearPolicy::class,
        \App\Models\JournalEntryLine::class => \App\Policies\BasePolicy::class,
        \App\Models\AccountingPeriod::class => \App\Policies\BasePolicy::class,
        \App\Models\BankTransaction::class => \App\Policies\BasePolicy::class,
        \App\Models\BankReconciliation::class => \App\Policies\BasePolicy::class,
        \App\Models\StockMovement::class => \App\Policies\BasePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Super-admin & admin bypass: grant all abilities before any policy check.
        // This is the standard Spatie Permission pattern for super-admin users.
        Gate::before(function ($user, $ability) {
            if ($user->hasRole(['super-admin', 'admin'])) {
                return true;
            }
        });
    }
}
