<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
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

        return \App\Http\Resources\CustomerResource::collection($customers);
    }

    public function store(Request $request): \App\Http\Resources\CustomerResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50|unique:customers,code,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'email' => 'nullable|email|max:255|unique:customers,email,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $customer = Customer::create([
            ...$validated,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\CustomerResource($customer);
    }

    public function show(Customer $customer): \App\Http\Resources\CustomerResource
    {
        return new \App\Http\Resources\CustomerResource($customer);
    }

    public function update(Request $request, Customer $customer): \App\Http\Resources\CustomerResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('customers')->ignore($customer->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'code' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers')->ignore($customer->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('customers')->ignore($customer->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'phone' => 'sometimes|nullable|string|max:20',
            'address' => 'sometimes|nullable|string|max:500',
            'city' => 'sometimes|nullable|string|max:100',
            'country' => 'sometimes|nullable|string|max:100',
            'tax_number' => 'sometimes|nullable|string|max:50',
            'credit_limit' => 'sometimes|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $customer->update($validated);

        return new \App\Http\Resources\CustomerResource($customer);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully',
        ]);
    }

    public function transactions(Customer $customer)
    {
        $transactions = $customer->sales()->with(['items.product', 'payments'])->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    public function creditHistory(Customer $customer)
    {
        $creditSales = $customer->creditSales()->with(['items.product', 'payments'])->get();

        return response()->json([
            'success' => true,
            'data' => $creditSales
        ]);
    }
}