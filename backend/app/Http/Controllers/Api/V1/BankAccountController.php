<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Services\BankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BankAccountController extends Controller
{
    protected BankAccountService $bankAccountService;

    public function __construct(BankAccountService $bankAccountService)
    {
        $this->bankAccountService = $bankAccountService;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = [];
        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }
        if ($request->filled('is_active')) {
            $filters['is_active'] = $request->is_active;
        }

        $bankAccounts = $this->bankAccountService->getAll($filters, $request->per_page ?? 15);

        return BankAccountResource::collection($bankAccounts);
    }

    public function store(Request $request): BankAccountResource
    {
        $validated = $request->validate([
            'name' => 'sometimes|required_without:account_name|string|max:255',
            'account_name' => 'sometimes|required_without:name|string|max:255',
            'account_number' => 'required|string|max:255|unique:bank_accounts,account_number,NULL,id,tenant_id,'.Auth::user()->tenant_id,
            'bank_name' => 'required|string|max:255',
            'branch_name' => 'nullable|string|max:255',
            'routing_number' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'currency' => 'required|string|size:3',
            'opening_balance' => 'required|numeric',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['name']) && ! isset($validated['account_name'])) {
            $validated['account_name'] = $validated['name'];
            unset($validated['name']);
        }

        $bankAccount = $this->bankAccountService->createBankAccount($validated);

        return new \App\Http\Resources\BankAccountResource($bankAccount);
    }

    public function show(BankAccount $bank_account): BankAccountResource
    {
        return new BankAccountResource($bank_account->load(['account']));
    }

    public function update(Request $request, BankAccount $bank_account): BankAccountResource
    {
        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required_without:account_name',
                'string',
                'max:255',
            ],
            'account_name' => [
                'sometimes',
                'required_without:name',
                'string',
                'max:255',
            ],
            'account_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('bank_accounts')->ignore($bank_account->id)->where(function ($query) {
                    return $query->where('tenant_id', Auth::user()->tenant_id);
                }),
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

        if (isset($validated['name']) && ! isset($validated['account_name'])) {
            $validated['account_name'] = $validated['name'];
            unset($validated['name']);
        }

        $bankAccount = $this->bankAccountService->updateBankAccount($bank_account->id, $validated);

        return new \App\Http\Resources\BankAccountResource($bankAccount);
    }

    public function destroy(BankAccount $bank_account): JsonResponse
    {
        try {
            $this->bankAccountService->deleteBankAccount($bank_account->id);

            return response()->json([
                'success' => true,
                'message' => 'Bank account deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function transactions(BankAccount $bank_account)
    {
        $transactions = $this->bankAccountService->getTransactions($bank_account->id);

        return response()->json([
            'success' => true,
            'data' => $transactions,
        ]);
    }
}
