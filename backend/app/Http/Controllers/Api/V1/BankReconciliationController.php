<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller
{
    public function index(Request $request)
    {
        $query = BankReconciliation::with(['bankAccount.account', 'createdBy']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('bankAccount.account', function ($sub) use ($request) {
                    $sub->where('name', 'like', '%'.$request->search.'%');
                });
            });
        }

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->bank_account_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reconciliations = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $reconciliations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'statement_date' => 'required|date',
            'statement_opening_balance' => 'required|numeric',
            'statement_closing_balance' => 'required|numeric',
            'system_balance' => 'required|numeric',
            'difference' => 'nullable|numeric',
            'outstanding_checks' => 'nullable|array',
            'outstanding_checks.*.id' => 'required|exists:journal_entries,id',
            'deposits_in_transit' => 'nullable|array',
            'deposits_in_transit.*.id' => 'required|exists:journal_entries,id',
            'bank_charges' => 'nullable|array',
            'bank_charges.*.amount' => 'required|numeric',
            'bank_charges.*.description' => 'required|string|max:255',
            'interest_earned' => 'nullable|array',
            'interest_earned.*.amount' => 'required|numeric',
            'interest_earned.*.description' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $bankAccount = BankAccount::find($validated['bank_account_id']);
        if ($bankAccount->tenant_id !== Auth::user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bank account.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $reconciliation = BankReconciliation::create([
                'bank_account_id' => $validated['bank_account_id'],
                'statement_date' => $validated['statement_date'],
                'statement_opening_balance' => $validated['statement_opening_balance'],
                'statement_closing_balance' => $validated['statement_closing_balance'],
                'system_balance' => $validated['system_balance'],
                'difference' => $validated['difference'] ?? ($validated['statement_closing_balance'] - $validated['system_balance']),
                'outstanding_checks' => $validated['outstanding_checks'] ?? [],
                'deposits_in_transit' => $validated['deposits_in_transit'] ?? [],
                'bank_charges' => $validated['bank_charges'] ?? [],
                'interest_earned' => $validated['interest_earned'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'status' => 'pending',
                'tenant_id' => Auth::user()->tenant_id,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $reconciliation->load(['bankAccount.account', 'createdBy']),
                'message' => 'Bank reconciliation created successfully.',
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'An internal error occurred. Please try again later.',
            ], 500);
        }
    }

    public function show(BankReconciliation $bank_reconciliation)
    {
        return response()->json([
            'success' => true,
            'data' => $bank_reconciliation->load(['bankAccount.account', 'createdBy', 'completedBy']),
        ]);
    }

    public function update(BankReconciliation $bank_reconciliation, Request $request)
    {
        if ($bank_reconciliation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update bank reconciliation that is not in pending status.',
            ], 422);
        }

        $validated = $request->validate([
            'statement_date' => 'sometimes|required|date',
            'statement_opening_balance' => 'sometimes|required|numeric',
            'statement_closing_balance' => 'sometimes|required|numeric',
            'system_balance' => 'sometimes|required|numeric',
            'difference' => 'sometimes|nullable|numeric',
            'outstanding_checks' => 'sometimes|nullable|array',
            'outstanding_checks.*.id' => 'sometimes|required|exists:journal_entries,id',
            'deposits_in_transit' => 'sometimes|nullable|array',
            'deposits_in_transit.*.id' => 'sometimes|required|exists:journal_entries,id',
            'bank_charges' => 'sometimes|nullable|array',
            'bank_charges.*.amount' => 'sometimes|required|numeric',
            'bank_charges.*.description' => 'sometimes|required|string|max:255',
            'interest_earned' => 'sometimes|nullable|array',
            'interest_earned.*.amount' => 'sometimes|required|numeric',
            'interest_earned.*.description' => 'sometimes|required|string|max:255',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        if (isset($validated['difference'])) {
            $validated['difference'] = $validated['difference'];
        } elseif (isset($validated['statement_closing_balance'], $validated['system_balance'])) {
            $validated['difference'] = $validated['statement_closing_balance'] - $validated['system_balance'];
        }

        $bank_reconciliation->update($validated);

        return response()->json([
            'success' => true,
            'data' => $bank_reconciliation->load(['bankAccount.account', 'createdBy', 'completedBy']),
            'message' => 'Bank reconciliation updated successfully.',
        ]);
    }

    public function destroy(BankReconciliation $bank_reconciliation)
    {
        if ($bank_reconciliation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete bank reconciliation that is not in pending status.',
            ], 422);
        }

        $bank_reconciliation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bank reconciliation deleted successfully.',
        ]);
    }

    public function complete(BankReconciliation $bank_reconciliation)
    {
        if ($bank_reconciliation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot complete bank reconciliation that is not in pending status.',
            ], 422);
        }

        $bank_reconciliation->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $bank_reconciliation->load(['bankAccount.account', 'createdBy', 'completedBy']),
            'message' => 'Bank reconciliation completed successfully.',
        ]);
    }
}
