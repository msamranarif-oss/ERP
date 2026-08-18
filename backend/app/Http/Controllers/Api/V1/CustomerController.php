<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Customer;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Http\Requests\Sales\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Customer::class, 'customer');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Customer::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $customers = $query->orderBy('name')
                           ->paginate($request->per_page ?? 15);

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $customer = Customer::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

        return new CustomerResource($customer);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return $this->successResponse(null, 'Customer deleted successfully');
    }

    public function transactions(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $transactions = $customer->sales()->with(['items.product', 'payments'])->get();

        return $this->successResponse($transactions);
    }

    public function creditHistory(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $creditSales = $customer->creditSales()->with(['items.product', 'payments'])->get();

        return $this->successResponse($creditSales);
    }

    public function statement(\Illuminate\Http\Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
        $start = $request->get('start_date', now()->startOfMonth()->toDateString());
        $end   = $request->get('end_date', now()->toDateString());

        $sales = $customer->sales()
            ->whereBetween('created_at', ["{$start} 00:00:00", "{$end} 23:59:59"])
            ->get(['id','sale_number','total_amount','created_at'])
            ->map(fn($s) => ['date' => $s->created_at->toDateString(),'reference' => $s->sale_number,'type' => 'sale','debit' => (float)$s->total_amount,'credit' => 0.0]);

        $payments = \App\Models\SalePayment::whereHas('sale', fn($q) => $q->where('customer_id', $customer->id))
            ->whereBetween('created_at', ["{$start} 00:00:00", "{$end} 23:59:59"])
            ->get(['id','amount','created_at'])
            ->map(fn($p) => ['date' => $p->created_at->toDateString(),'reference' => 'PMT-'.$p->id,'type' => 'payment','debit' => 0.0,'credit' => (float)$p->amount]);

        $balance = 0.0;
        $ledger  = $sales->merge($payments)->sortBy('date')->values()
            ->map(function ($row) use (&$balance) {
                $balance += $row['debit'] - $row['credit'];
                return array_merge($row, ['balance' => $balance]);
            });

        return $this->successResponse([
            'customer'        => $customer->only('id','name','email','phone'),
            'period'          => ['start' => $start, 'end' => $end],
            'transactions'    => $ledger,
            'closing_balance' => $balance,
        ]);
    }
}