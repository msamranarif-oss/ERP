<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockMovement;
use App\Models\StockLevel;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductReportController extends Controller
{
    private function tenantId(): int { return auth()->user()->tenant_id; }

    /**
     * Stock ledger (running balance) for one product.
     */
    public function stockLedger(Request $request, int $productId): JsonResponse
    {
        $tid = $this->tenantId();
        $movements = StockMovement::where('tenant_id', $tid)
            ->where('product_id', $productId)
            ->with(['warehouse', 'createdBy:id,name'])
            ->orderBy('created_at')
            ->paginate($request->per_page ?? 50);

        return response()->json(['success' => true, 'data' => $movements]);
    }

    /**
     * Gross profit margin per product.
     */
    public function profitMargin(Request $request): JsonResponse
    {
        $tid  = $this->tenantId();
        $from = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->get('end_date',   Carbon::now()->endOfMonth()->toDateString());

        $data = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('stock_levels as sl', fn($j) =>
                $j->on('sl.product_id', '=', 'si.product_id')
                  ->on('sl.tenant_id', '=', 's.tenant_id')
            )
            ->where('s.tenant_id', $tid)
            ->where('s.status', 'finalized')
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('p.id', 'p.name')
            ->select(
                'p.id',
                'p.name',
                DB::raw('SUM(si.quantity * si.unit_price)  as revenue'),
                DB::raw('SUM(si.quantity * COALESCE(sl.avg_cost, 0)) as cost_of_goods'),
                DB::raw('SUM(si.quantity * si.unit_price) - SUM(si.quantity * COALESCE(sl.avg_cost, 0)) as gross_profit')
            )
            ->orderByDesc('gross_profit')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Products with < threshold units sold in last N days.
     */
    public function slowMoving(Request $request): JsonResponse
    {
        $tid       = $this->tenantId();
        $days      = (int) $request->get('days', 30);
        $threshold = (int) $request->get('threshold', 5);
        $since     = now()->subDays($days)->toDateString();

        $data = DB::table('products as p')
            ->leftJoin('sale_items as si', fn($j) =>
                $j->on('si.product_id', '=', 'p.id')
                  ->join('sales as s', fn($j2) =>
                      $j2->on('s.id', '=', 'si.sale_id')
                         ->where('s.status', 'finalized')
                         ->where('s.sale_date', '>=', $since)
                  )
            )
            ->where('p.tenant_id', $tid)
            ->groupBy('p.id', 'p.name', 'p.sku')
            ->havingRaw('COALESCE(SUM(si.quantity), 0) < ?', [$threshold])
            ->select('p.id', 'p.name', 'p.sku', DB::raw('COALESCE(SUM(si.quantity),0) as units_sold'))
            ->orderBy('units_sold')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Products with zero sales in past 90 days and positive stock.
     */
    public function deadStock(Request $request): JsonResponse
    {
        $tid   = $this->tenantId();
        $since = now()->subDays(90)->toDateString();

        $soldIds = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.tenant_id', $tid)
            ->where('s.status', 'finalized')
            ->where('s.sale_date', '>=', $since)
            ->distinct()->pluck('si.product_id');

        $data = StockLevel::where('tenant_id', $tid)
            ->where('quantity', '>', 0)
            ->whereNotIn('product_id', $soldIds)
            ->with('product:id,name,sku')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * How many days each product's current stock has been sitting.
     */
    public function stockAging(Request $request): JsonResponse
    {
        $tid = $this->tenantId();
        $today = now()->toDateString();

        $data = DB::table('stock_levels as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->leftJoin(DB::raw('(SELECT product_id, MAX(created_at) as last_in FROM stock_movements WHERE type="in" GROUP BY product_id) as lm'),
                'lm.product_id', '=', 'sl.product_id')
            ->where('sl.tenant_id', $tid)
            ->where('sl.quantity', '>', 0)
            ->select(
                'p.id', 'p.name', 'sl.quantity',
                DB::raw("DATEDIFF('$today', DATE(lm.last_in)) as days_in_stock"),
                'lm.last_in'
            )
            ->orderByDesc('days_in_stock')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Batches expiring within N days with remaining quantity > 0.
     */
    public function expiringSoon(Request $request): JsonResponse
    {
        $tid  = $this->tenantId();
        $days = (int) $request->get('days', 30);

        $data = Batch::where('tenant_id', $tid)
            ->where('quantity_remaining', '>', 0)
            ->whereNotIn('status', ['expired'])
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->with('product:id,name,sku')
            ->orderBy('expiry_date')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Batches past expiry date with remaining stock > 0.
     */
    public function expired(Request $request): JsonResponse
    {
        $tid = $this->tenantId();

        $data = Batch::where('tenant_id', $tid)
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '<', now()->toDateString())
            ->with('product:id,name,sku')
            ->orderBy('expiry_date')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Sales breakdown per product variant.
     */
    public function variantSales(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $tid  = $this->tenantId();
        $from = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->get('end_date',   Carbon::now()->endOfMonth()->toDateString());

        $data = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->leftJoin('product_variants as pv', 'pv.id', '=', 'si.variant_id')
            ->where('s.tenant_id', $tid)
            ->where('si.product_id', $request->product_id)
            ->where('s.status', 'finalized')
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('si.variant_id', 'pv.name')
            ->select('si.variant_id', 'pv.name as variant_name',
                DB::raw('SUM(si.quantity) as qty_sold'),
                DB::raw('SUM(si.total)    as revenue'))
            ->orderByDesc('qty_sold')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Historical selling prices for a product from sale items.
     */
    public function sellingPriceHistory(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $tid = $this->tenantId();

        $history = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.tenant_id', $tid)
            ->where('si.product_id', $request->product_id)
            ->where('s.status', 'finalized')
            ->select('s.sale_date', 'si.unit_price', 'si.quantity')
            ->orderBy('s.sale_date')
            ->limit(200)
            ->get();

        return response()->json(['success' => true, 'data' => $history]);
    }

    /**
     * Historical purchase prices from GRN items.
     */
    public function purchasePriceHistory(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $tid = $this->tenantId();

        $history = DB::table('grn_items as gi')
            ->join('goods_received_notes as g', 'g.id', '=', 'gi.grn_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'gi.purchase_order_item_id')
            ->where('g.tenant_id', $tid)
            ->where('gi.product_id', $request->product_id)
            ->select('g.received_date', 'gi.quantity_received', 'poi.unit_cost')
            ->orderBy('g.received_date')
            ->get();

        return response()->json(['success' => true, 'data' => $history]);
    }
}
