<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CustomerReportController extends Controller
{
    private function tenantId(): int { return auth()->user()->tenant_id; }

    /**
     * Customers with outstanding credit balance.
     */
    public function balances(Request $request): JsonResponse
    {
        $data = Customer::where('tenant_id', $this->tenantId())
            ->where('balance', '>', 0)
            ->orderByDesc('balance')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Overdue installments bucketed by customer.
     */
    public function arAging(Request $request): JsonResponse
    {
        $today   = now()->toDateString();
        $tid     = $this->tenantId();
        $buckets = ['current' => [0,30], '31_60' => [31,60], '61_90' => [61,90], '90_plus' => [91, null]];
        $result  = [];

        foreach ($buckets as $label => [$min, $max]) {
            $q = Installment::where('tenant_id', $tid)
                ->where('status', '!=', 'paid')
                ->whereRaw('DATEDIFF(?, due_date) >= ?', [$today, $min]);
            if ($max) $q->whereRaw('DATEDIFF(?, due_date) <= ?', [$today, $max]);
            $result[$label] = [
                'count'       => $q->count(),
                'balance_due' => (float) $q->sum(DB::raw('due_amount - paid_amount')),
            ];
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Top customers by total spend in period.
     */
    public function topCustomers(Request $request): JsonResponse
    {
        $tid  = $this->tenantId();
        $from = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->get('end_date',   Carbon::now()->endOfMonth()->toDateString());

        $data = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.tenant_id', $tid)
            ->where('s.status', 'finalized')
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('c.id', 'c.name', 'c.phone')
            ->select('c.id', 'c.name', 'c.phone',
                DB::raw('COUNT(s.id) as order_count'),
                DB::raw('SUM(s.total) as total_spend'))
            ->orderByDesc('total_spend')
            ->limit($request->get('limit', 10))
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Customers sorted by loyalty point balance.
     */
    public function loyaltyPoints(Request $request): JsonResponse
    {
        $data = Customer::where('tenant_id', $this->tenantId())
            ->where('loyalty_points', '>', 0)
            ->orderByDesc('loyalty_points')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Full transaction statement for one customer in a date range.
     */
    public function statement(Request $request, int $customerId): JsonResponse
    {
        $tid  = $this->tenantId();
        $from = $request->get('start_date', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->get('end_date',   Carbon::now()->endOfMonth()->toDateString());

        $customer = Customer::where('tenant_id', $tid)->findOrFail($customerId);

        // Sales (charges)
        $sales = Sale::where('tenant_id', $tid)
            ->where('customer_id', $customerId)
            ->where('status', 'finalized')
            ->whereBetween('sale_date', [$from, $to])
            ->get()
            ->map(fn($s) => [
                'date'        => $s->sale_date,
                'type'        => 'sale',
                'reference'   => $s->invoice_number ?? 'INV-'.$s->id,
                'debit'       => (float) $s->total,
                'credit'      => 0,
                'description' => 'Sale Invoice',
            ]);

        // Payments received from customer
        $payments = SalePayment::whereHas('sale', fn($q) =>
                $q->where('tenant_id', $tid)
                  ->where('customer_id', $customerId)
                  ->whereBetween('sale_date', [$from, $to])
            )
            ->get()
            ->map(fn($p) => [
                'date'        => $p->payment_date ?? $p->created_at->toDateString(),
                'type'        => 'payment',
                'reference'   => 'PAY-'.$p->id,
                'debit'       => 0,
                'credit'      => (float) $p->amount,
                'description' => 'Payment received',
            ]);

        $balance     = 0;
        $transactions = $sales->merge($payments)->sortBy('date')->values()->map(function ($row) use (&$balance) {
            $balance += $row['debit'] - $row['credit'];
            return array_merge($row, ['balance' => $balance]);
        });

        return response()->json(['success' => true, 'data' => [
            'customer'        => $customer->only('id', 'name', 'phone', 'email'),
            'period'          => ['from' => $from, 'to' => $to],
            'entries'         => $transactions,
            'closing_balance' => $balance,
        ]]);
    }
}
