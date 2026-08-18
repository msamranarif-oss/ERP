<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\ChartOfAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ChartOfAccountsController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ChartOfAccount::class, 'chart_of_account');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChartOfAccount::with(['parent', 'children', 'accountType']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('code', 'like', '%'.$request->search.'%')
                    ->orWhere('description', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('type')) {
            $query->whereHas('accountType', function ($q) use ($request) {
                $q->where('category', $request->type);
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('accountType', function ($q) use ($request) {
                $q->where('name', $request->category);
            });
        }

        if ($request->filled('account_type_id')) {
            $query->where('account_type_id', $request->account_type_id);
        }

        $accounts = $query->orderBy('code')
            ->paginate($request->per_page ?? 15);

        return ChartOfAccountResource::collection($accounts);
    }

    public function tree()
    {
        $accounts = ChartOfAccount::with(['children'])->whereNull('parent_id')->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function store(Request $request): ChartOfAccountResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:chart_of_accounts,code,NULL,id,tenant_id,'.auth()->user()->tenant_id,
            'account_type_id' => 'required|exists:account_types,id',
            'description' => 'nullable|string|max:500',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'opening_balance' => 'nullable|numeric',
            'is_active' => 'boolean',
            'allow_direct_posting' => 'boolean',
        ]);

        $account = ChartOfAccount::create([
            ...$validated,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new ChartOfAccountResource($account->load(['parent', 'children', 'accountType']));
    }

    public function show(ChartOfAccount $chart_of_account): ChartOfAccountResource
    {
        return new ChartOfAccountResource($chart_of_account->load(['parent', 'children', 'accountType']));
    }

    public function update(Request $request, ChartOfAccount $chart_of_account): ChartOfAccountResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('chart_of_accounts')->ignore($chart_of_account->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                }),
            ],
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts')->ignore($chart_of_account->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                }),
            ],
            'account_type_id' => 'sometimes|required|exists:account_types,id',
            'description' => 'sometimes|nullable|string|max:500',
            'parent_id' => 'sometimes|nullable|exists:chart_of_accounts,id',
            'opening_balance' => 'sometimes|nullable|numeric',
            'is_active' => 'sometimes|boolean',
            'allow_direct_posting' => 'sometimes|boolean',
        ]);

        $chart_of_account->update($validated);

        return new ChartOfAccountResource($chart_of_account->load(['parent', 'children', 'accountType']));
    }

    public function destroy(ChartOfAccount $chart_of_account): JsonResponse
    {
        if ($chart_of_account->journalEntries()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete account that has journal entries.',
            ], 422);
        }

        $chart_of_account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully.',
        ]);
    }
}
