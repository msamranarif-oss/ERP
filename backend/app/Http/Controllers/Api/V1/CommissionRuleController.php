<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\CommissionRule;
use App\Http\Requests\CommissionRuleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommissionRuleController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(CommissionRule::class, 'commission_rule');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CommissionRule::with(['user'])->where('tenant_id', Auth::user()->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('rate_percent', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $commissionRules = $query->orderBy('created_at', 'desc')
                                ->paginate($request->per_page ?? 15);

        return $this->successResponse($commissionRules, 'Commission rules retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CommissionRuleRequest $request): JsonResponse
    {
        $commissionRule = CommissionRule::create($request->validated());

        return $this->successResponse($commissionRule, 'Commission rule created successfully.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CommissionRule $commission_rule): JsonResponse
    {
        $this->authorize('view', $commission_rule);

        $commissionRule = $commission_rule->load(['user']);

        return $this->successResponse($commissionRule, 'Commission rule retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CommissionRuleRequest $request, CommissionRule $commission_rule): JsonResponse
    {
        $this->authorize('update', $commission_rule);

        $commission_rule->update($request->validated());

        return $this->successResponse($commission_rule, 'Commission rule updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CommissionRule $commission_rule): JsonResponse
    {
        $this->authorize('delete', $commission_rule);

        $commission_rule->delete();

        return $this->successResponse(null, 'Commission rule deleted successfully.');
    }

    /**
     * Get active commission rules
     */
    public function active(): JsonResponse
    {
        $commissionRules = CommissionRule::where('tenant_id', Auth::user()->tenant_id)
                                        ->where('is_active', true)
                                        ->with(['user'])
                                        ->get();

        return $this->successResponse($commissionRules, 'Active commission rules retrieved successfully');
    }
}