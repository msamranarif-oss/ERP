<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use App\Models\CreditSale;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ChartOfAccount;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportService
{
    /**
     * Get date range from request
     */
    private function getDateRange($startDate = null, $endDate = null)
    {
        $start = $startDate ?? Carbon::now()->startOfMonth()->toDateString();
        $end = $endDate ?? Carbon::now()->endOfMonth()->toDateString();
        return [$start, $end];
    }

    // ==========================================
    // Sales Reports
    // ==========================================

    public function getSalesSummary($tenantId, $startDate = null, $endDate = null)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        return Sale::where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$start, $end])
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(total) as total_revenue,
                SUM(discount_amount) as total_discount,
                SUM(tax_amount) as total_tax,
                SUM(shipping_amount) as total_shipping,
                SUM(paid_amount) as total_paid,
                SUM(balance_due) as total_due
            ')
            ->first();
    }

    public function getSalesByProduct($tenantId, $startDate = null, $endDate = null, $limit = 50)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$start, $end])
            ->select(
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as quantity_sold'),
                DB::raw('SUM(sale_items.total) as total_revenue'),
                DB::raw('AVG(sale_items.unit_price) as avg_price')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }

    public function getSalesByCustomer($tenantId, $startDate = null, $endDate = null, $limit = 50)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        return Sale::where('sales.tenant_id', $tenantId)
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->select(
                'customers.name as customer_name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.total) as total_spent'),
                DB::raw('SUM(sales.balance_due) as outstanding_balance')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();
    }

    public function getSalesByBranch($tenantId, $startDate = null, $endDate = null)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        return Sale::where('sales.tenant_id', $tenantId)
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->select(
                'branches.name as branch_name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.total) as total_revenue')
            )
            ->groupBy('branches.id', 'branches.name')
            ->get();
    }

    public function getSalesByCashier($tenantId, $startDate = null, $endDate = null)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        return Sale::where('sales.tenant_id', $tenantId)
            ->join('users', 'sales.sold_by', '=', 'users.id')
            ->whereBetween('sales.sale_date', [$start, $end])
            ->select(
                'users.name as cashier_name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.total) as total_revenue')
            )
            ->groupBy('users.id', 'users.name')
            ->get();
    }

    public function getSalesByPaymentMethod($tenantId, $startDate = null, $endDate = null, $registerSessionId = null)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        $query = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$start, $end]);

        // Filter by register session if provided (for per-terminal POS summary)
        if ($registerSessionId) {
            $query->where('sales.register_session_id', $registerSessionId);
        }

        return $query->select(
                'payment_methods.name as method_name',
                'payment_methods.type as method_type',
                DB::raw('COUNT(sale_payments.id) as transaction_count'),
                DB::raw('SUM(sale_payments.amount) as total_amount')
            )
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type')
            ->get();
    }

    // ==========================================
    // Inventory Reports
    // ==========================================

    public function getStockLevels($tenantId, $lowStockOnly = false)
    {
        $query = Product::where('products.tenant_id', $tenantId)
            ->with(['category']);
            
        if ($lowStockOnly) {
            $query->leftJoin('stock_levels', 'products.id', '=', 'stock_levels.product_id')
                ->select('products.*')
                ->groupBy('products.id')
                ->havingRaw('SUM(stock_levels.quantity - stock_levels.reserved_quantity) <= products.reorder_level');
        }

        return $query;
    }

    public function getLowStock($tenantId)
    {
        return Product::where('products.tenant_id', $tenantId)
            ->leftJoin('stock_levels', 'products.id', '=', 'stock_levels.product_id')
            ->select('products.*')
            ->selectRaw('SUM(stock_levels.quantity - stock_levels.reserved_quantity) as available_stock')
            ->groupBy('products.id')
            ->havingRaw('SUM(stock_levels.quantity - stock_levels.reserved_quantity) <= products.reorder_level')
            ->with('category')
            ->get();
    }

    public function getStockValuation($tenantId)
    {
        return DB::table('products')
            ->leftJoin('stock_levels', 'products.id', '=', 'stock_levels.product_id')
            ->where('products.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->select(
                DB::raw('COUNT(DISTINCT products.id) as total_products'),
                DB::raw('SUM(stock_levels.quantity) as total_items'),
                DB::raw('SUM(stock_levels.quantity * products.cost_price) as total_cost_value'),
                DB::raw('SUM(stock_levels.quantity * products.selling_price) as total_sales_value'),
                DB::raw('SUM(stock_levels.quantity * (products.selling_price - products.cost_price)) as potential_profit')
            )
            ->first();
    }

    // ==========================================
    // Installment Reports
    // ==========================================

    public function getInstallmentSummary($tenantId)
    {
        return CreditSale::where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_agreements,
                SUM(financed_amount) as total_financed,
                SUM(total_paid) as total_collected,
                SUM(total_balance) as total_outstanding
            ')
            ->first();
    }

    public function getOverdueInstallments($tenantId)
    {
        return Installment::where('tenant_id', $tenantId)
            ->where('due_date', '<', now())
            ->where('status', 'pending')
            ->with(['creditSale.customer.customer'])
            ->limit(100)
            ->get();
    }

    public function getCollections($tenantId, $startDate = null, $endDate = null)
    {
        $start = $startDate ?? now()->subDays(30)->toDateString();
        $end = $endDate ?? now()->toDateString();
        
        $collections = Payment::where('tenant_id', $tenantId)
            ->whereBetween('payment_date', [$start, $end])
            ->with(['creditSale.customer.customer', 'paymentMethod'])
            ->orderBy('payment_date', 'desc')
            ->get();
        
        $summary = [
            'total_collections' => $collections->count(),
            'total_amount' => $collections->sum('amount'),
            'start_date' => $start,
            'end_date' => $end,
        ];

        return [
            'collections' => $collections,
            'summary' => $summary
        ];
    }

    // ==========================================
    // Financial Reports
    // ==========================================

    public function getTrialBalance($tenantId, $startDate = null, $endDate = null)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        return ChartOfAccount::where('tenant_id', $tenantId)
            ->whereHas('journalEntryLines.journalEntry', function($q) use ($start, $end) {
                $q->whereBetween('entry_date', [$start, $end])
                  ->where('status', 'posted');
            })
            ->withSum(['journalEntryLines as total_debit' => function($q) use ($start, $end) {
                $q->whereHas('journalEntry', function($sq) use ($start, $end) {
                    $sq->whereBetween('entry_date', [$start, $end])->where('status', 'posted');
                });
            }], 'debit')
            ->withSum(['journalEntryLines as total_credit' => function($q) use ($start, $end) {
                $q->whereHas('journalEntry', function($sq) use ($start, $end) {
                    $sq->whereBetween('entry_date', [$start, $end])->where('status', 'posted');
                });
            }], 'credit')
            ->get()
            ->map(function($account) {
                $balance = $account->total_debit - $account->total_credit;
                return [
                    'account_code' => $account->code,
                    'account_name' => $account->name,
                    'debit' => (float)$account->total_debit,
                    'credit' => (float)$account->total_credit,
                    'balance' => (float)$balance
                ];
            });
    }

    public function getProfitLoss($tenantId, $startDate = null, $endDate = null)
    {
        [$start, $end] = $this->getDateRange($startDate, $endDate);

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereHas('accountType', function($q) {
                $q->whereIn('category', ['revenue', 'expense']);
            })
            ->withSum(['journalEntryLines as total_amount' => function($q) use ($start, $end) {
                $q->whereHas('journalEntry', function($sq) use ($start, $end) {
                    $sq->whereBetween('entry_date', [$start, $end])->where('status', 'posted');
                });
            }], DB::raw('debit - credit'))
            ->get();

        $revenue = $accounts->filter(fn($a) => $a->accountType->category === 'revenue');
        $expense = $accounts->filter(fn($a) => $a->accountType->category === 'expense');

        $totalRevenue = abs((float)$revenue->sum('total_amount'));
        $totalExpense = (float)$expense->sum('total_amount');

        return [
            'revenue_accounts' => $revenue->values(),
            'expense_accounts' => $expense->values(),
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_profit' => $totalRevenue - $totalExpense
        ];
    }

    public function getBalanceSheet($tenantId, $date = null)
    {
        $endDate = $date ?? now()->toDateString();

        $accounts = ChartOfAccount::where('tenant_id', $tenantId)
            ->whereHas('accountType', function($q) {
                $q->whereIn('category', ['asset', 'liability', 'equity']);
            })
            ->withSum(['journalEntryLines as total_amount' => function($q) use ($endDate) {
                $q->whereHas('journalEntry', function($sq) use ($endDate) {
                    $sq->where('entry_date', '<=', $endDate)->where('status', 'posted');
                });
            }], DB::raw('debit - credit'))
            ->get();

        $assets = $accounts->filter(fn($a) => $a->accountType->category === 'asset');
        $liabilities = $accounts->filter(fn($a) => $a->accountType->category === 'liability');
        $equity = $accounts->filter(fn($a) => $a->accountType->category === 'equity');

        return [
            'assets' => $assets->values(),
            'liabilities' => $liabilities->values(),
            'equity' => $equity->values(),
            'total_assets' => (float)$assets->sum('total_amount'),
            'total_liabilities' => abs((float)$liabilities->sum('total_amount')),
            'total_equity' => abs((float)$equity->sum('total_amount')),
            'date' => $endDate
        ];
    }

    // ==========================================
    // Inventory Analytics
    // ==========================================

    public function getStockMovements($tenantId, $filters = [])
    {
        $query = StockMovement::where('tenant_id', $tenantId)
            ->with(['product', 'warehouse', 'createdBy']);

        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('created_at', [$filters['start_date'], $filters['end_date']]);
        }

        if (!empty($filters['product_id'])) {
            $query->where('product_id', $filters['product_id']);
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($filters['per_page'] ?? 50);
    }
}