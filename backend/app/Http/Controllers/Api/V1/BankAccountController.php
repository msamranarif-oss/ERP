<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = BankAccount::with(['account']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('account_number', 'like', '%' . $request->search . '%')
                  ->orWhere('bank_name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $bankAccounts = $query->orderBy('name')
                               ->paginate($request->per_page ?? 15);

        return \App\Http\Resources\BankAccountResource::collection($bankAccounts);
    }

    public function store(Request $request): \App\Http\Resources\BankAccountResource
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts,account_number,NULL,id,tenant_id,' . auth()->user()->tenant_id,
            'bank_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'routing_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'currency' => 'required|string|size:3',
            'opening_balance' => 'required|numeric',
            'is_active' => 'boolean',
        ]);

        $account = Account::create([
            'name' => $validated['name'],
            'code' => 'BANK-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'type' => 'asset',
            'category' => 'current_asset',
            'description' => 'Bank account for ' . $validated['bank_name'],
            'opening_balance' => $validated['opening_balance'],
            'is_active' => $validated['is_active'] ?? true,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        $bankAccount = BankAccount::create([
            ...$validated,
            'account_id' => $account->id,
            'tenant_id' => auth()->user()->tenant_id,
        ]);

        return new \App\Http\Resources\BankAccountResource($bankAccount->load(['account']));
    }

    public function show(BankAccount $bank_account): \App\Http\Resources\BankAccountResource
    {
        return new \App\Http\Resources\BankAccountResource($bank_account->load(['account']));
    }

    public function update(Request $request, BankAccount $bank_account): \App\Http\Resources\BankAccountResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('bank_accounts')->ignore($bank_account->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'account_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('bank_accounts')->ignore($bank_account->id)->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'bank_name' => 'sometimes|required|string|max:255',
            'branch_name' => 'sometimes|nullable|string|max:255',
            'routing_number' => 'sometimes|nullable|string|max:255',
            'swift_code' => 'sometimes|nullable|string|max:255',
            'iban' => 'sometimes|nullable|string|max:255',
            'currency' => 'sometimes|required|string|size:3',
            'opening_balance' => 'sometimes|required|numeric',
            'is_active' => 'sometimes|boolean',
        ]);

        $bankAccount = $bank_account;
        
        if (isset($validated['name']) || isset($validated['opening_balance']) || isset($validated['is_active'])) {
            $bankAccount->account->update([
                'name' => $validated['name'] ?? $bankAccount->account->name,
                'opening_balance' => $validated['opening_balance'] ?? $bankAccount->account->opening_balance,
                'is_active' => $validated['is_active'] ?? $bankAccount->account->is_active,
            ]);
        }

        $bankAccount->update(array_diff_key($validated, ['name', 'opening_balance', 'is_active']));

        return new \App\Http\Resources\BankAccountResource($bankAccount->load(['account']));
    }

    public function destroy(BankAccount $bank_account): JsonResponse
    {
        // Prevent deletion if bank account has transactions
        if ($bank_account->account->journalEntries()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bank account that has transactions.',
            ], 422);
        }

        $bank_account->account->delete();
        $bank_account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bank account deleted successfully.',
        ]);
    }

    public function transactions(BankAccount $bank_account)
    {
        $transactions = $bank_account->account->journalEntries()
                                           ->with(['lines.account', 'createdBy'])
                                           ->orderBy('date', 'desc')
                                           ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }
}