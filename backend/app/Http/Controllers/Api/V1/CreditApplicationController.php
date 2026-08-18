<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\CreditApplication;
use App\Http\Requests\Credit\CreditApplicationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreditApplicationController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(CreditApplication::class, 'credit_application');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CreditApplication::with(['customer', 'createdBy', 'reviewedBy'])
                                ->where('tenant_id', Auth::user()->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('requested_amount', 'like', '%' . $request->search . '%')
                  ->orWhere('purpose', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%')
                          ->orWhere('email', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $creditApplications = $query->orderBy('created_at', 'desc')
                                   ->paginate($request->per_page ?? 15);

        return $this->successResponse($creditApplications, 'Credit applications retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreditApplicationRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = Auth::user()->tenant_id;
        $data['created_by'] = Auth::user()->id;

        $creditApplication = CreditApplication::create($data);

        return $this->successResponse($creditApplication, 'Credit application created successfully.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CreditApplication $credit_application): JsonResponse
    {
        $this->authorize('view', $credit_application);

        $creditApplication = $credit_application->load(['customer', 'createdBy', 'reviewedBy']);

        return $this->successResponse($creditApplication, 'Credit application retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CreditApplicationRequest $request, CreditApplication $credit_application): JsonResponse
    {
        $this->authorize('update', $credit_application);

        $credit_application->update($request->validated());

        return $this->successResponse($credit_application, 'Credit application updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CreditApplication $credit_application): JsonResponse
    {
        $this->authorize('delete', $credit_application);

        $credit_application->delete();

        return $this->successResponse(null, 'Credit application deleted successfully.');
    }

    /**
     * Approve a credit application
     */
    public function approve(CreditApplication $credit_application): JsonResponse
    {
        $this->authorize('update', $credit_application);

        if ($credit_application->status !== 'pending') {
            return $this->errorResponse('Credit application is not in pending status.', 422);
        }

        $credit_application->update([
            'status' => 'approved',
            'reviewed_by' => Auth::user()->id,
            'reviewed_at' => now()
        ]);

        return $this->successResponse($credit_application, 'Credit application approved successfully.');
    }

    /**
     * Reject a credit application
     */
    public function reject(CreditApplication $credit_application, Request $request): JsonResponse
    {
        $this->authorize('update', $credit_application);

        if ($credit_application->status !== 'pending') {
            return $this->errorResponse('Credit application is not in pending status.', 422);
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        $credit_application->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => Auth::user()->id,
            'reviewed_at' => now()
        ]);

        return $this->successResponse($credit_application, 'Credit application rejected successfully.');
    }
}