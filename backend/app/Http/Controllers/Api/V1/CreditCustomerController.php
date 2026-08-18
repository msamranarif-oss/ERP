<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\CreditCustomer;
use App\Http\Requests\Credit\StoreCreditCustomerRequest;
use App\Http\Requests\Credit\UpdateCreditCustomerRequest;
use App\Http\Requests\Credit\VerifyCreditCustomerRequest;
use App\Http\Resources\CreditCustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreditCustomerController extends ApiController
{
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

        return CreditCustomerResource::collection($creditCustomers);
    }

    public function store(StoreCreditCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $request->user()->tenant_id;

        // Check if customer already has credit account
        $existingCreditCustomer = CreditCustomer::where('customer_id', $validated['customer_id'])
                                                 ->where('tenant_id', $tenantId)
                                                 ->first();

        if ($existingCreditCustomer) {
            return $this->errorResponse('Customer already has a credit account.', 422);
        }

        $creditCustomer = CreditCustomer::create([
            ...$validated,
            'status' => 'active',
            'tenant_id' => $tenantId,
        ]);

        return $this->successResponse(new CreditCustomerResource($creditCustomer->load(['customer'])), 'Credit account created successfully.', 201);
    }

    public function show(CreditCustomer $credit_customer): CreditCustomerResource
    {
        return new CreditCustomerResource($credit_customer->load(['customer']));
    }

    public function update(UpdateCreditCustomerRequest $request, CreditCustomer $credit_customer): CreditCustomerResource
    {
        $credit_customer->update($request->validated());

        return new CreditCustomerResource($credit_customer->load(['customer']));
    }

    public function destroy(CreditCustomer $credit_customer): JsonResponse
    {
        // Prevent deletion if customer has active credit sales
        if ($credit_customer->creditSales()->where('status', '!=', 'completed')->exists()) {
            return $this->errorResponse('Cannot delete credit customer with active credit sales.', 422);
        }

        $credit_customer->delete();

        return $this->successResponse(null, 'Credit customer deleted successfully.');
    }

    public function verify(VerifyCreditCustomerRequest $request, CreditCustomer $credit_customer): JsonResponse
    {
        $credit_customer->update([
            'is_verified' => true,
            'verification_data' => $request->validated()['verification_data'],
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        return $this->successResponse(new CreditCustomerResource($credit_customer->refresh()->load(['customer'])), 'Credit customer verified successfully.');
    }
}