<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\CreditSale;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\PurchaseBill;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\ReportService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    protected ReportService $reportService;
    protected ExportService $exportService;

    public function __construct(ReportService $reportService, ExportService $exportService)
    {
        $this->reportService = $reportService;
        $this->exportService = $exportService;
    }
    private function getDateRange(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->toDateString());
        return [$startDate, $endDate];
    }

    // ==========================================
    // Sales Reports
    // ==========================================

    public function salesSummary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        $sales = $this->reportService->getSalesSummary($tenantId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    public function salesByProduct(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        $data = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $tenantId)
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->select(
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as quantity_sold'),
                DB::raw('SUM(sale_items.total) as total_revenue'),
                DB::raw('AVG(sale_items.unit_price) as avg_price')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_revenue')
            ->limit($request->get('limit', 50))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function salesByCustomer(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        $data = Sale::where('sales.tenant_id', $tenantId)
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->select(
                'customers.name as customer_name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.total) as total_spent'),
                DB::raw('SUM(sales.balance_due) as outstanding_balance')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit($request->get('limit', 50))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function salesByBranch(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        $data = Sale::where('sales.tenant_id', $tenantId)
            ->join('branches', 'sales.branch_id', '=', 'branches.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->select(
                'branches.name as branch_name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.total) as total_revenue')
            )
            ->groupBy('branches.id', 'branches.name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function salesByCashier(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        $data = Sale::where('sales.tenant_id', $tenantId)
            ->join('users', 'sales.sold_by', '=', 'users.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->select(
                'users.name as cashier_name',
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('SUM(sales.total) as total_revenue')
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function salesByPaymentMethod(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);
        $registerSessionId = $request->get('register_session_id');

        $data = $this->reportService->getSalesByPaymentMethod($tenantId, $startDate, $endDate, $registerSessionId);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // ==========================================
    // Inventory Reports
    // ==========================================

    public function stockLevels(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $query = Product::where('products.tenant_id', $tenantId)
            ->with(['category']);
            
        if ($request->has('low_stock')) {
            $query->leftJoin('stock_levels', 'products.id', '=', 'stock_levels.product_id')
                ->select('products.*')
                ->groupBy('products.id')
                ->havingRaw('SUM(stock_levels.quantity - stock_levels.reserved_quantity) <= products.reorder_level');
        }

        $products = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function stockMovements(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $filters = $request->only(['start_date', 'end_date', 'product_id', 'warehouse_id', 'per_page']);

        $movements = $this->reportService->getStockMovements($tenantId, $filters);

        return response()->json([
            'success' => true,
            'data' => $movements
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $products = Product::where('products.tenant_id', $tenantId)
            ->leftJoin('stock_levels', 'products.id', '=', 'stock_levels.product_id')
            ->select('products.*')
            ->selectRaw('SUM(stock_levels.quantity - stock_levels.reserved_quantity) as available_stock')
            ->groupBy('products.id')
            ->havingRaw('SUM(stock_levels.quantity - stock_levels.reserved_quantity) <= products.reorder_level')
            ->with('category')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function stockValuation(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $valuation = DB::table('products')
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

        return response()->json([
            'success' => true,
            'data' => $valuation
        ]);
    }

    // ==========================================
    // Financial Reports (Stubs for now)
    // ==========================================

    public function trialBalance(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        $data = $this->reportService->getTrialBalance($tenantId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        $data = $this->reportService->getProfitLoss($tenantId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $date = $request->get('date', now()->toDateString());

        $data = $this->reportService->getBalanceSheet($tenantId, $date);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function generalLedger(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $accountId = $request->get('account_id');
        $dateFrom  = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $dateTo    = $request->get('end_date',   Carbon::now()->toDateString());

        $query = JournalEntryLine::with(['account', 'journalEntry'])
            ->whereHas('journalEntry', fn ($q) => $q
                ->where('tenant_id', $tenantId)
                ->where('status', 'posted')
                ->whereBetween('entry_date', [$dateFrom, $dateTo]))
            ->when($accountId, fn ($q, $id) => $q->where('account_id', $id))
            ->orderBy('created_at');

        $lines = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data'    => $lines,
        ]);
    }

    public function cashFlow(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $dateFrom = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $dateTo   = $request->get('end_date',   Carbon::now()->toDateString());

        // Operating inflows — payments received on sales
        $inflows = SalePayment::whereHas('sale', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereBetween('created_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->sum('amount');

        // Operating outflows — amounts paid on purchase bills
        $outflows = PurchaseBill::where('tenant_id', $tenantId)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->sum('amount_paid');

        return response()->json([
            'success' => true,
            'data'    => [
                'period'         => ['from' => $dateFrom, 'to' => $dateTo],
                'total_inflows'  => (float) $inflows,
                'total_outflows' => (float) $outflows,
                'net_cash_flow'  => (float) ($inflows - $outflows),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $type = $request->get('report_type');
        $tenantId = $request->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        if ($type === 'sales_summary') {
            $data = $this->reportService->getSalesSummary($tenantId, $startDate, $endDate);
            return $this->exportService->exportToCsv(collect([$data]), [
                'Total Count', 'Total Revenue', 'Discount', 'Tax', 'Shipping', 'Paid', 'Due'
            ], 'sales_summary.csv');
        }

        return response()->json(['success' => false, 'message' => 'Invalid report type'], 400);
    }

    // ==========================================
    // Installment Reports
    // ==========================================

    public function installmentSummary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // Use actual column names: loan_amount, down_payment
        $summary = CreditSale::where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*)                    AS total_agreements,
                SUM(loan_amount)            AS total_loan_amount,
                SUM(down_payment)           AS total_down_payment,
                SUM(loan_amount + down_payment) AS total_financed
            ')
            ->withSum('installments as total_collected', 'paid_amount')
            ->withCount(['installments as overdue_count' =>
                fn ($q) => $q->where('status', 'overdue')
            ])
            ->first();

        // Compute outstanding from installments table to avoid virtual-column issues
        $outstanding = Installment::whereHas('creditSale', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', '!=', 'paid')
            ->selectRaw('SUM(due_amount - paid_amount) as balance')
            ->value('balance') ?? 0;

        return response()->json([
            'success' => true,
            'data'    => array_merge($summary->toArray(), [
                'total_outstanding' => (float) $outstanding,
            ]),
        ]);
    }

    public function overdueInstallments(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $overdue = $this->reportService->getOverdueInstallments($tenantId);

        return response()->json([
            'success' => true,
            'data' => $overdue
        ]);
    }

    public function collections(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        $result = $this->reportService->getCollections($tenantId, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $result['collections'],
            'summary' => $result['summary']
        ]);
    }

    public function installmentAging(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $today    = Carbon::today()->toDateString();

        $buckets = [
            'current'   => [0,  30],
            'days_31_60' => [31, 60],
            'days_61_90' => [61, 90],
            'days_90_plus' => [91, null],
        ];

        $result = [];
        foreach ($buckets as $label => [$min, $max]) {
            $query = Installment::whereHas('creditSale', fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('status', '!=', 'paid')
                ->where('due_date', '<', $today)
                ->whereRaw('DATEDIFF(?, due_date) >= ?', [$today, $min]);

            if ($max !== null) {
                $query->whereRaw('DATEDIFF(?, due_date) <= ?', [$today, $max]);
            }

            $result[$label] = [
                'count'       => $query->count(),
                'balance_due' => (float) $query->sum(DB::raw('due_amount - paid_amount')),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── AR Aging ────────────────────────────────────────────────────────

    public function arAging(Request $request): JsonResponse
    {
        $today    = now()->toDateString();
        $tenantId = auth()->user()->tenant_id;

        $buckets = [
            'current' => [0, 30],
            '31_60'   => [31, 60],
            '61_90'   => [61, 90],
            '90_plus' => [91, null],
        ];

        $result = [];
        foreach ($buckets as $label => [$min, $max]) {
            $query = \App\Models\Installment::where('tenant_id', $tenantId)
                ->where('status', '!=', 'paid')
                ->whereRaw('DATEDIFF(?, due_date) >= ?', [$today, $min]);
            if ($max) {
                $query->whereRaw('DATEDIFF(?, due_date) <= ?', [$today, $max]);
            }
            $result[$label] = [
                'count'       => $query->count(),
                'balance_due' => (float) $query->sum(DB::raw('due_amount - paid_amount')),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── AP Aging ────────────────────────────────────────────────────────

    public function apAging(Request $request): JsonResponse
    {
        $today    = now()->toDateString();
        $tenantId = auth()->user()->tenant_id;

        $buckets = [
            'current' => [0, 30],
            '31_60'   => [31, 60],
            '61_90'   => [61, 90],
            '90_plus' => [91, null],
        ];

        $result = [];
        foreach ($buckets as $label => [$min, $max]) {
            $query = \App\Models\PurchaseBill::where('tenant_id', $tenantId)
                ->where('payment_status', '!=', 'paid')
                ->whereNotNull('due_date')
                ->whereRaw('DATEDIFF(?, due_date) >= ?', [$today, $min]);
            if ($max) {
                $query->whereRaw('DATEDIFF(?, due_date) <= ?', [$today, $max]);
            }
            $result[$label] = [
                'count'       => $query->count(),
                'balance_due' => (float) $query->sum(DB::raw('total_amount - paid_amount')),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    // ── Supplier Ledger ─────────────────────────────────────────────────

    public function supplierLedger(Request $request, int $supplierId): JsonResponse
    {
        $tenantId = auth()->user()->tenant_id;
        [$startDate, $endDate] = $this->getDateRange($request);

        // Purchase bills (liability increases)
        $bills = \App\Models\PurchaseBill::where('tenant_id', $tenantId)
            ->where('supplier_id', $supplierId)
            ->whereBetween('bill_date', [$startDate, $endDate])
            ->get()
            ->map(fn($b) => [
                'date'        => $b->bill_date,
                'type'        => 'purchase_bill',
                'reference'   => $b->bill_number,
                'debit'       => 0,
                'credit'      => (float) $b->total_amount,
                'description' => 'Purchase Bill',
            ]);

        // Payments (liability decreases)
        $payments = \App\Models\Payment::where('tenant_id', $tenantId)
            ->where('payee_type', 'supplier')
            ->where('payee_id', $supplierId)
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->get()
            ->map(fn($p) => [
                'date'        => $p->payment_date,
                'type'        => 'payment',
                'reference'   => $p->reference ?? ('PAY-' . $p->id),
                'debit'       => (float) $p->amount,
                'credit'      => 0,
                'description' => 'Payment',
            ]);

        $transactions = $bills->merge($payments)->sortBy('date')->values();

        $runningBalance = 0;
        $ledger = $transactions->map(function ($row) use (&$runningBalance) {
            $runningBalance += $row['credit'] - $row['debit'];
            return array_merge($row, ['balance' => $runningBalance]);
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'supplier_id' => $supplierId,
                'period'      => ['from' => $startDate, 'to' => $endDate],
                'entries'     => $ledger,
                'closing_balance' => $runningBalance,
            ],
        ]);
    }
}