<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\CreditSale;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
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

        $sales = Sale::where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [$startDate, $endDate])
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

    // ==========================================
    // Inventory Reports
    // ==========================================

    public function stockLevels(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $query = Product::where('tenant_id', $tenantId)
            ->with(['category']);
            
        if ($request->has('low_stock')) {
            $query->whereRaw('available_stock <= alert_quantity');
        }

        $products = $query->paginate($request->get('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function stockMovements(Request $request): JsonResponse
    {
        // Placeholder until StockMovement model is created
        return response()->json([
            'success' => true,
            'data' => []
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $products = Product::where('tenant_id', $tenantId)
            ->whereRaw('available_stock <= alert_quantity')
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
        
        $valuation = Product::where('tenant_id', $tenantId)
            ->select(
                DB::raw('COUNT(*) as total_products'),
                DB::raw('SUM(available_stock) as total_items'),
                DB::raw('SUM(available_stock * purchase_price) as total_cost_value'),
                DB::raw('SUM(available_stock * selling_price) as total_sales_value'),
                DB::raw('SUM(available_stock * (selling_price - purchase_price)) as potential_profit')
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
        return response()->json(['success' => true, 'data' => [], 'message' => 'Pending JournalEntryLine model implementation']);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [], 'message' => 'Pending JournalEntryLine model implementation']);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [], 'message' => 'Pending JournalEntryLine model implementation']);
    }

    public function cashFlow(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [], 'message' => 'Pending implementation']);
    }

    public function generalLedger(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [], 'message' => 'Pending JournalEntryLine model implementation']);
    }

    // ==========================================
    // Installment Reports
    // ==========================================

    public function installmentSummary(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $summary = CreditSale::where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_agreements,
                SUM(financed_amount) as total_financed,
                SUM(total_paid) as total_collected,
                SUM(total_balance) as total_outstanding
            ')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    public function overdueInstallments(Request $request): JsonResponse
    {
        // Fallback to CreditSale level check since Schedule model is missing
        $tenantId = $request->user()->tenant_id;
        
        $overdue = CreditSale::where('tenant_id', $tenantId)
            ->where('status', 'active') // Assuming active means potentially overdue
            // Real logic needs InstallmentSchedule table
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $overdue,
            'message' => 'Pending InstallmentSchedule model for accurate overdue checking'
        ]);
    }

    public function collections(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [], 'message' => 'Pending Payment model implementation']);
    }

    public function installmentAging(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => [], 'message' => 'Pending InstallmentSchedule model implementation']);
    }
}