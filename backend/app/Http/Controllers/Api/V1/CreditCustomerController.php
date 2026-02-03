<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditCustomer;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CreditCustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CreditCustomer::with(['customer']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('customer', function ($sub) use ($request) {
                    $sub->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%')
                        ->orWhere('phone', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $creditCustomers = $query->orderBy('created_at', 'desc')
                                   ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\CreditCustomerResource::collection($creditCustomers);
    }

    public function store(Request $request): \App\Http\Resources\CreditCustomerResource
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'credit_limit' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'grace_period_days' => 'required|integer|min:0|max:365',
            'late_fee_percent' => 'required|numeric|min:0|max:100',
            'max_installments' => 'required|integer|min:1|max:120',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if customer already has credit account
        $existingCreditCustomer = CreditCustomer::where('customer_id', $validated['customer_id'])
                                                 ->where('tenant_id', auth()->user()->tenant_id)
                                                 ->first();

        if ($existingCreditCustomer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer already has a credit account.',
            ], 422);
        }

        $creditCustomer = CreditCustomer::create([
            ...$validated,
            'status' => 'active',
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\CreditCustomerResource($creditCustomer->load(['customer']));
    }

    public function show(CreditCustomer $credit_customer): \App\Http\Resources\CreditCustomerResource
    {
        return new \App\Http\Resources\CreditCustomerResource($credit_customer->load(['customer']));
    }

    public function update(Request $request, CreditCustomer $credit_customer): \App\Http\Resources\CreditCustomerResource
    {
        $validated = $request->validate([
            'credit_limit' => 'sometimes|required|numeric|min:0',
            'interest_rate' => 'sometimes|required|numeric|min:0|max:100',
            'grace_period_days' => 'sometimes|required|integer|min:0|max:365',
            'late_fee_percent' => 'sometimes|required|numeric|min:0|max:100',
            'max_installments' => 'sometimes|required|integer|min:1|max:120',
            'notes' => 'sometimes|nullable|string|max:1000',
            'status' => 'sometimes|required|in:active,inactive,suspended',
        ]);

        $credit_customer->update($validated);

        return new \App\Http\Resources\CreditCustomerResource($credit_customer->load(['customer']));
    }

    public function destroy(CreditCustomer $credit_customer): JsonResponse
    {
        // Prevent deletion if customer has active credit sales
        if ($credit_customer->creditSales()->where('status', '!=', 'completed')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete credit customer with active credit sales.',
            ], 422);
        }

        $credit_customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Credit customer deleted successfully.',
        ]);
    }

    public function verify(CreditCustomer $credit_customer, Request $request)
    {
        $validated = $request->validate([
            'verification_data' => 'required|array',
            'verification_data.identity_document' => 'required|string|max:255',
            'verification_data.address_proof' => 'required|string|max:255',
            'verification_data.income_proof' => 'required|string|max:255',
        ]);

        $credit_customer->update([
            'is_verified' => true,
            'verification_data' => $validated['verification_data'],
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $credit_customer->refresh()->load(['customer']),
            'message' => 'Credit customer verified successfully.',
        ]);
    }
}