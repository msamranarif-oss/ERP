<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardService
{

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(int $tenantId)
    {
        // Get sales statistics for the current month
        $currentMonthSales = Sale::where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->sum('total');

        $previousMonthSales = Sale::where('tenant_id', $tenantId)
            ->whereBetween('sale_date', [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()])
            ->sum('total');

        $salesGrowth = $previousMonthSales > 0 
            ? round((($currentMonthSales - $previousMonthSales) / $previousMonthSales) * 100, 2)
            : ($currentMonthSales > 0 ? 100 : 0);

        // Get counts
        $totalProducts = Product::where('tenant_id', $tenantId)->count();
        $totalCustomers = Customer::where('tenant_id', $tenantId)->count();
        $totalSuppliers = Supplier::where('tenant_id', $tenantId)->count();

        // Recent sales
        $recentSales = Sale::where('tenant_id', $tenantId)
            ->with(['customer', 'soldBy'])
            ->latest()
            ->take(5)
            ->get();

        return [
            'stats' => [
                'total_sales_current_month' => $currentMonthSales,
                'sales_growth_percentage' => $salesGrowth,
                'total_products' => $totalProducts,
                'total_customers' => $totalCustomers,
                'total_suppliers' => $totalSuppliers,
            ],
            'recent_sales' => $recentSales->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'invoice_number' => $sale->sale_number,
                    'customer_name' => $sale->customer?->name,
                    'amount' => $sale->total,
                    'date' => $sale->created_at->format('Y-m-d H:i'),
                    'cashier' => $sale->soldBy?->name,
                ];
            }),
        ];
    }

    /**
     * Get sales chart data
     */
    public function getSalesChartData(int $tenantId, string $period = 'monthly')
    {
        $startDate = match($period) {
            'daily' => Carbon::now()->subDays(7),
            'weekly' => Carbon::now()->subWeeks(4),
            'yearly' => Carbon::now()->subYears(1),
            default => Carbon::now()->subMonths(6),
        };

        $cacheKey = "sales_chart_{$tenantId}_{$period}_" . $startDate->format('Y-m-d');
        
        return Cache::remember($cacheKey, now()->addHour(), function() use ($tenantId, $period, $startDate) {
            $salesData = [];

            if ($period === 'daily') {
                $salesData = Sale::where('tenant_id', $tenantId)
                    ->where('sale_date', '>=', $startDate)
                    ->selectRaw('DATE(sale_date) as date, SUM(total) as total')
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get()
                    ->pluck('total', 'date')
                    ->toArray();
            } elseif ($period === 'weekly') {
                $salesData = Sale::where('tenant_id', $tenantId)
                    ->where('sale_date', '>=', $startDate)
                    ->selectRaw('WEEK(sale_date) as week, YEAR(sale_date) as year, SUM(total) as total')
                    ->groupBy('week', 'year')
                    ->orderBy('year', 'asc')
                    ->orderBy('week', 'asc')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [Carbon::now()->year($item->year)->week($item->week)->format('Y-W') => $item->total];
                    })
                    ->toArray();
            } else {
                $salesData = Sale::where('tenant_id', $tenantId)
                    ->where('sale_date', '>=', $startDate)
                    ->selectRaw('MONTH(sale_date) as month, YEAR(sale_date) as year, SUM(total) as total')
                    ->groupBy('month', 'year')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get()
                    ->mapWithKeys(function ($item) {
                        return [Carbon::create($item->year, $item->month)->format('Y-m') => $item->total];
                    })
                    ->toArray();
            }

            return [
                'period' => $period,
                'sales_data' => $salesData,
            ];
        });
    }

    /**
     * Get top products
     */
    public function getTopProducts(int $tenantId, int $limit = 5)
    {
        return DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.tenant_id', $tenantId)
            ->select(
                'products.name as product_name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity_sold'),
                DB::raw('SUM(sale_items.total) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('total_quantity_sold')
            ->limit($limit)
            ->get();
    }
}