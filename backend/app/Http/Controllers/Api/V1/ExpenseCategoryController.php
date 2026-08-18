<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = ExpenseCategory::where('tenant_id', auth()->user()->tenant_id)
                                     ->when($request->active, fn($q) => $q->where('is_active', true))
                                     ->with('account')
                                     ->get();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active'  => 'boolean',
        ]);
        $data['tenant_id'] = auth()->user()->tenant_id;
        $cat = ExpenseCategory::create($data);
        return response()->json(['success' => true, 'data' => $cat], 201);
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        $this->authorizeTenant($expenseCategory);
        return response()->json(['success' => true, 'data' => $expenseCategory->load('account')]);
    }

    public function update(Request $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $this->authorizeTenant($expenseCategory);
        $expenseCategory->update($request->validate([
            'name'       => 'sometimes|string|max:100',
            'account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_active'  => 'boolean',
        ]));
        return response()->json(['success' => true, 'data' => $expenseCategory->fresh()]);
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        $this->authorizeTenant($expenseCategory);
        $expenseCategory->delete();
        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    private function authorizeTenant(ExpenseCategory $cat): void
    {
        abort_if($cat->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
