<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ChartOfAccountsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Account::with(['parent', 'children']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $accounts = $query->orderBy('code')
                           ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\AccountResource::collection($accounts);
    }

    public function tree()
    {
        $accounts = Account::with(['children'])->whereNull('parent_id')->orderBy('code')->get();
        
        return response()->json([
            'success' => true,
            'data' => $accounts
        ]);
    }

    public function store(Request $request): \App\Http\Resources\AccountResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:accounts,code,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'category' => 'required|in:current_asset,non_current_asset,current_liability,non_current_liability,capital,reserves,revenue,expenses',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:accounts,id',
            'opening_balance' => 'nullable|numeric',
            'is_active' => 'boolean',
        ]);

        $account = Account::create([
            ...$validated,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\AccountResource($account->load(['parent', 'children']));
    }

    public function show(Account $account): \App\Http\Resources\AccountResource
    {
        return new \App\Http\Resources\AccountResource($account->load(['parent', 'children']));
    }

    public function update(Request $request, Account $account): \App\Http\Resources\AccountResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('accounts')->ignore($account->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('accounts')->ignore($account->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'type' => 'sometimes|required|in:asset,liability,equity,revenue,expense',
            'category' => 'sometimes|required|in:current_asset,non_current_asset,current_liability,non_current_liability,capital,reserves,revenue,expenses',
            'description' => 'sometimes|nullable|string|max:500',
            'parent_id' => 'sometimes|nullable|exists:accounts,id',
            'opening_balance' => 'sometimes|nullable|numeric',
            'is_active' => 'sometimes|boolean',
        ]);

        $account->update($validated);

        return new \App\Http\Resources\AccountResource($account->load(['parent', 'children']));
    }

    public function destroy(Account $account): JsonResponse
    {
        // Prevent deletion if account has transactions
        if ($account->journalEntries()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account that has journal entries.',
            ], 422);
        }

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}