<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseBill;
use App\Models\GoodsReceivedNote;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PurchaseReportController extends Controller
{
    private function tenantId(): int
    {
        return auth()->user()->tenant_id;
    }

    private function dateRange(Request $request): array
    {
        return [
            $request->get('start_date', Carbon::now()->startOfMonth()->toDateString()),
            $request->get('end_date',   Carbon::now()->endOfMonth()->toDateString()),
        ];
    }

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $orders = PurchaseOrder::where('tenant_id', $tid)->whereBetween('order_date', [$from, $to]);
        $bills  = PurchaseBill::where('tenant_id', $tid)->whereBetween('bill_date', [$from, $to]);
        $grns   = GoodsReceivedNote::where('tenant_id', $tid)->whereBetween('received_date', [$from, $to]);

        return response()->json(['success' => true, 'data' => [
            'period'         => ['from' => $from, 'to' => $to],
            'total_orders'   => $orders->count(),
            'orders_value'   => (float) $orders->sum('total_amount'),
            'total_bills'    => $bills->count(),
            'bills_value'    => (float) $bills->sum('total_amount'),
            'bills_paid'     => (float) $bills->sum('paid_amount'),
            'bills_due'      => (float) $bills->sum(DB::raw('total_amount - paid_amount')),
            'total_grns'     => $grns->count(),
        ]]);
    }

    public function bySupplier(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $data = PurchaseBill::where('purchase_bills.tenant_id', $tid)
            ->whereBetween('bill_date', [$from, $to])
            ->join('suppliers', 'suppliers.id', '=', 'purchase_bills.supplier_id')
            ->select(
                'suppliers.id',
                'suppliers.name',
                DB::raw('COUNT(*) as bill_count'),
                DB::raw('SUM(purchase_bills.total_amount) as total_value'),
                DB::raw('SUM(purchase_bills.paid_amount)  as total_paid'),
                DB::raw('SUM(purchase_bills.total_amount - purchase_bills.paid_amount) as outstanding')
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_value')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function byProduct(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $data = DB::table('grn_items')
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'grn_items.grn_id')
            ->join('products', 'products.id', '=', 'grn_items.product_id')
            ->where('goods_received_notes.tenant_id', $tid)
            ->whereBetween('goods_received_notes.received_date', [$from, $to])
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(grn_items.quantity_received) as total_received'),
                DB::raw('COUNT(DISTINCT goods_received_notes.id) as grn_count')
            )
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_received')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function byCategory(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $data = DB::table('grn_items')
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'grn_items.grn_id')
            ->join('products', 'products.id', '=', 'grn_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('goods_received_notes.tenant_id', $tid)
            ->whereBetween('goods_received_notes.received_date', [$from, $to])
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('SUM(grn_items.quantity_received) as total_received'),
                DB::raw('COUNT(DISTINCT grn_items.product_id) as product_count')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_received')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function apAging(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $tid   = $this->tenantId();
        $buckets = ['current' => [0,30], '31_60' => [31,60], '61_90' => [61,90], '90_plus' => [91,null]];
        $result = [];

        foreach ($buckets as $label => [$min, $max]) {
            $q = PurchaseBill::where('tenant_id', $tid)
                ->where('payment_status', '!=', 'paid')
                ->whereNotNull('due_date')
                ->whereRaw('DATEDIFF(?, due_date) >= ?', [$today, $min]);
            if ($max) $q->whereRaw('DATEDIFF(?, due_date) <= ?', [$today, $max]);
            $result[$label] = [
                'count'       => $q->count(),
                'balance_due' => (float) $q->sum(DB::raw('total_amount - paid_amount')),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function supplierLedger(Request $request): JsonResponse
    {
        $request->validate(['supplier_id' => 'required|exists:suppliers,id']);
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();
        $supplierId = $request->supplier_id;

        $bills = PurchaseBill::where('tenant_id', $tid)->where('supplier_id', $supplierId)
            ->whereBetween('bill_date', [$from, $to])->get()
            ->map(fn($b) => ['date'=>$b->bill_date,'type'=>'bill','ref'=>$b->bill_number,'debit'=>0,'credit'=>(float)$b->total_amount]);

        $payments = Payment::where('tenant_id', $tid)->where('payee_type', 'supplier')->where('payee_id', $supplierId)
            ->whereBetween('payment_date', [$from, $to])->get()
            ->map(fn($p) => ['date'=>$p->payment_date,'type'=>'payment','ref'=>$p->reference??'PAY-'.$p->id,'debit'=>(float)$p->amount,'credit'=>0]);

        $balance = 0;
        $ledger  = $bills->merge($payments)->sortBy('date')->values()->map(function ($row) use (&$balance) {
            $balance += $row['credit'] - $row['debit'];
            return array_merge($row, ['balance' => $balance]);
        });

        return response()->json(['success' => true, 'data' => ['entries' => $ledger, 'closing_balance' => $balance]]);
    }

    public function purchasePriceHistory(Request $request): JsonResponse
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $tid = $this->tenantId();

        $history = DB::table('grn_items')
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'grn_items.grn_id')
            ->join('purchase_order_items', 'purchase_order_items.id', '=', 'grn_items.purchase_order_item_id')
            ->where('goods_received_notes.tenant_id', $tid)
            ->where('grn_items.product_id', $request->product_id)
            ->select(
                'goods_received_notes.received_date',
                'grn_items.quantity_received',
                'purchase_order_items.unit_cost'
            )
            ->orderBy('goods_received_notes.received_date')
            ->get();

        return response()->json(['success' => true, 'data' => $history]);
    }
}
