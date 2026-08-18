<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        
        $dashboardData = Cache::remember("dashboard_stats_{$tenantId}", now()->addMinutes(10), function() use ($tenantId) {
            return $this->dashboardService->getDashboardStats($tenantId);
        });

        return response()->json([
            'success' => true,
            'data' => $dashboardData
        ]);
    }

    public function salesChart(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $period = $request->get('period', 'monthly'); // monthly, weekly, daily

        $chartData = $this->dashboardService->getSalesChartData($tenantId, $period);

        return response()->json([
            'success' => true,
            'data' => $chartData
        ]);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $limit = $request->get('limit', 5);

        $topProducts = $this->dashboardService->getTopProducts($tenantId, $limit);

        return response()->json([
            'success' => true,
            'data' => $topProducts,
        ]);
    }
}
