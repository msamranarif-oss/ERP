<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TaxReportController extends Controller
{
    private function tenantId(): int { return auth()->user()->tenant_id; }

    private function dateRange(Request $request): array
    {
        return [
            $request->get('start_date', Carbon::now()->startOfMonth()->toDateString()),
            $request->get('end_date',   Carbon::now()->endOfMonth()->toDateString()),
        ];
    }

    /**
     * Tax collected on sales, grouped by tax rate.
     */
    public function taxCollected(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $data = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('tax_rates as tr', 'tr.id', '=', 'si.tax_rate_id')
            ->where('s.tenant_id', $tid)
            ->where('s.status', 'finalized')
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('tr.id', 'tr.name', 'tr.rate')
            ->select(
                'tr.id',
                'tr.name as tax_name',
                'tr.rate',
                DB::raw('SUM(si.quantity * si.unit_price * tr.rate / 100) as tax_amount'),
                DB::raw('SUM(si.total) as taxable_amount')
            )
            ->get();

        return response()->json(['success' => true, 'data' => [
            'period'        => ['from' => $from, 'to' => $to],
            'by_rate'       => $data,
            'total_tax'     => (float) $data->sum('tax_amount'),
        ]]);
    }

    /**
     * Tax paid on purchases (from purchase bills).
     */
    public function taxPaid(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $data = DB::table('purchase_bill_items as pbi')
            ->join('purchase_bills as pb', 'pb.id', '=', 'pbi.purchase_bill_id')
            ->join('tax_rates as tr', 'tr.id', '=', 'pbi.tax_rate_id')
            ->where('pb.tenant_id', $tid)
            ->whereBetween('pb.bill_date', [$from, $to])
            ->groupBy('tr.id', 'tr.name', 'tr.rate')
            ->select(
                'tr.id',
                'tr.name as tax_name',
                'tr.rate',
                DB::raw('SUM(pbi.quantity * pbi.unit_cost * tr.rate / 100) as tax_amount'),
                DB::raw('SUM(pbi.total) as taxable_amount')
            )
            ->get();

        return response()->json(['success' => true, 'data' => [
            'period'    => ['from' => $from, 'to' => $to],
            'by_rate'   => $data,
            'total_tax' => (float) $data->sum('tax_amount'),
        ]]);
    }

    /**
     * Net tax summary = tax collected - tax paid.
     */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $collected = (float) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('tax_rates as tr', 'tr.id', '=', 'si.tax_rate_id')
            ->where('s.tenant_id', $tid)
            ->where('s.status', 'finalized')
            ->whereBetween('s.sale_date', [$from, $to])
            ->sum(DB::raw('si.quantity * si.unit_price * tr.rate / 100'));

        $paid = (float) DB::table('purchase_bill_items as pbi')
            ->join('purchase_bills as pb', 'pb.id', '=', 'pbi.purchase_bill_id')
            ->join('tax_rates as tr', 'tr.id', '=', 'pbi.tax_rate_id')
            ->where('pb.tenant_id', $tid)
            ->whereBetween('pb.bill_date', [$from, $to])
            ->sum(DB::raw('pbi.quantity * pbi.unit_cost * tr.rate / 100'));

        return response()->json(['success' => true, 'data' => [
            'period'          => ['from' => $from, 'to' => $to],
            'tax_collected'   => $collected,
            'input_tax_paid'  => $paid,
            'net_tax_payable' => $collected - $paid,
        ]]);
    }

    /**
     * Expenses summary by category.
     */
    public function expensesSummary(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $tid = $this->tenantId();

        $byCategory = Expense::where('tenant_id', $tid)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from, $to])
            ->with('category:id,name')
            ->get()
            ->groupBy(fn($e) => $e->category?->name ?? 'Uncategorized')
            ->map(fn($group) => round($group->sum('amount'), 2));

        return response()->json(['success' => true, 'data' => [
            'period'      => ['from' => $from, 'to' => $to],
            'by_category' => $byCategory,
            'total'       => round($byCategory->sum(), 2),
        ]]);
    }
}
