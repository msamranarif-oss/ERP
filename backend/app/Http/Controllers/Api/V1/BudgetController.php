<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\Budget;
use App\Http\Requests\BudgetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BudgetController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(Budget::class, 'budget');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Budget::with(['account', 'fiscalYear'])->where('tenant_id', Auth::user()->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('period_type', 'like', '%' . $request->search . '%')
                  ->orWhere('period_value', 'like', '%' . $request->search . '%')
                  ->orWhere('amount', 'like', '%' . $request->search . '%')
                  ->orWhereHas('account', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('fiscal_year_id')) {
            $query->where('fiscal_year_id', $request->fiscal_year_id);
        }

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->filled('period_type')) {
            $query->where('period_type', $request->period_type);
        }

        $budgets = $query->orderBy('fiscal_year_id', 'desc')
                        ->orderBy('period_type')
                        ->orderBy('period_value')
                        ->paginate($request->per_page ?? 15);

        return $this->successResponse($budgets, 'Budgets retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BudgetRequest $request): JsonResponse
    {
        $budget = Budget::create($request->validated());

        return $this->successResponse($budget, 'Budget created successfully.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        $budget = $budget->load(['account', 'fiscalYear']);

        return $this->successResponse($budget, 'Budget retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BudgetRequest $request, Budget $budget): JsonResponse
    {
        $this->authorize('update', $budget);

        $budget->update($request->validated());

        return $this->successResponse($budget, 'Budget updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Budget $budget): JsonResponse
    {
        $this->authorize('delete', $budget);

        $budget->delete();

        return $this->successResponse(null, 'Budget deleted successfully.');
    }

    /**
     * Get budgets for a specific fiscal year
     */
    public function byFiscalYear(int $fiscalYearId): JsonResponse
    {
        $budgets = Budget::where('tenant_id', Auth::user()->tenant_id)
                        ->where('fiscal_year_id', $fiscalYearId)
                        ->with(['account', 'fiscalYear'])
                        ->get();

        return $this->successResponse($budgets, 'Budgets for fiscal year retrieved successfully');
    }

    /**
     * Get budget vs actual comparison
     */
    public function vsActual(Budget $budget): JsonResponse
    {
        $this->authorize('view', $budget);

        // Placeholder for budget vs actual comparison
        // This would typically involve comparing budgeted amounts to actual transactions
        
        $result = [
            'budget' => $budget,
            'actual' => 0, // This would be calculated from actual transactions
            'variance' => 0, // This would be calculated as actual - budgeted
            'variance_percentage' => 0 // This would be variance / budgeted * 100
        ];

        return $this->successResponse($result, 'Budget vs actual comparison retrieved successfully');
    }
}