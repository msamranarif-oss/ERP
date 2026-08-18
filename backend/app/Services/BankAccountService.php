<?php

namespace App\Services;

use App\Models\AccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BankAccountService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new BankAccount);
    }

    /**
     * Get all bank accounts with filters
     */
    public function getAll(array $filters = [], int $perPage = 15)
    {
        $query = $this->model->with(['account']);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('account_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('account_number', 'like', '%'.$filters['search'].'%')
                    ->orWhere('bank_name', 'like', '%'.$filters['search'].'%');
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('account_name')
            ->paginate($perPage);
    }

    /**
     * Create a new bank account
     */
    public function createBankAccount(array $data)
    {
        try {
            $accountType = AccountType::where('name', 'Current Asset')->first();
            if (! $accountType) {
                throw new \Exception('Missing Current Asset account type.');
            }

            $accountName = $data['account_name'] ?? $data['name'] ?? 'Bank Account';
            $user = Auth::user();
            $tenantId = $user ? $user->tenant_id : null;
            if (! $tenantId) {
                throw new \Exception('Unable to determine tenant context.');
            }

            $account = ChartOfAccount::create([
                'tenant_id' => $tenantId,
                'account_type_id' => $accountType->id,
                'code' => 'BANK-'.strtoupper(substr(md5(uniqid()), 0, 6)),
                'name' => $accountName,
                'description' => 'Bank account for '.$data['bank_name'],
                'is_active' => $data['is_active'] ?? true,
                'allow_direct_posting' => true,
                'opening_balance' => $data['opening_balance'] ?? 0,
                'current_balance' => $data['opening_balance'] ?? 0,
            ]);

            $bankAccount = BankAccount::create([
                'tenant_id' => $tenantId,
                'account_id' => $account->id,
                'bank_name' => $data['bank_name'],
                'account_number' => $data['account_number'],
                'account_name' => $accountName,
                'branch' => $data['branch_name'] ?? null,
                'routing_number' => $data['routing_number'] ?? null,
                'swift_code' => $data['swift_code'] ?? null,
                'iban' => $data['iban'] ?? null,
                'currency' => $data['currency'],
                'opening_balance' => $data['opening_balance'] ?? 0,
                'current_balance' => $data['opening_balance'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return $bankAccount->load(['account']);
        } catch (\Exception $e) {
            Log::error('Error creating bank account', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Update a bank account
     */
    public function updateBankAccount(int $bankAccountId, array $data)
    {
        try {
            $bankAccount = BankAccount::findOrFail($bankAccountId);

            $accountData = [];
            if (isset($data['account_name'])) {
                $accountData['name'] = $data['account_name'];
            }
            if (isset($data['opening_balance'])) {
                $accountData['opening_balance'] = $data['opening_balance'];
                $accountData['current_balance'] = $data['opening_balance'];
            }
            if (isset($data['is_active'])) {
                $accountData['is_active'] = $data['is_active'];
            }

            if (! empty($accountData)) {
                $bankAccount->account->update($accountData);
            }

            $updateData = $data;
            unset($updateData['name'], $updateData['account_name'], $updateData['opening_balance'], $updateData['is_active']);
            $bankAccount->update($updateData);

            return $bankAccount->load(['account']);
        } catch (\Exception $e) {
            Log::error('Error updating bank account', [
                'error' => $e->getMessage(),
                'bank_account_id' => $bankAccountId,
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Delete a bank account
     */
    public function deleteBankAccount(int $bankAccountId)
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);

        // Prevent deletion if bank account has transactions
        if ($bankAccount->account->journalEntries()->exists()) {
            throw new \Exception('Cannot delete bank account that has transactions.');
        }

        $bankAccount->account->delete();
        $bankAccount->delete();

        return true;
    }

    /**
     * Get bank account transactions
     */
    public function getTransactions(int $bankAccountId)
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);

        return $bankAccount->account->journalEntries()
            ->with(['lines.account', 'createdBy'])
            ->orderBy('entry_date', 'desc')
            ->get();
    }
}
