<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Services\JournalAutoPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    protected JournalAutoPostService $autoPostService;

    public function __construct(JournalAutoPostService $autoPostService)
    {
        $this->autoPostService = $autoPostService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Expense::where('tenant_id', auth()->user()->tenant_id)
            ->with(['category', 'creator', 'branch', 'supplier', 'journalEntry']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->expense_category_id);
        }
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->to);
        }

        return response()->json(['success' => true, 'data' => $query->orderByDesc('expense_date')->paginate($request->per_page ?? 15)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'account_id' => 'nullable|exists:chart_of_accounts,id',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,bank,card',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'branch_id' => 'nullable|exists:branches,id',
            'payee' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'reference' => 'nullable|string|max:100',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('expenses/attachments', 'public');
        }

        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';

        $expense = Expense::create($data);

        return response()->json(['success' => true, 'data' => $expense->load(['category', 'supplier']), 'message' => 'Expense created.'], 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $this->authorizeTenant($expense);

        return response()->json(['success' => true, 'data' => $expense->load(['category', 'branch', 'bankAccount', 'creator', 'supplier', 'journalEntry.lines.account'])]);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeTenant($expense);
        if (! $expense->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Only draft expenses can be updated.'], 422);
        }
        $data = $request->validate([
            'expense_category_id' => 'sometimes|exists:expense_categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'account_id' => 'nullable|exists:chart_of_accounts,id',
            'expense_date' => 'sometimes|date',
            'amount' => 'sometimes|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,bank,card',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'payee' => 'nullable|string',
            'description' => 'nullable|string',
            'reference' => 'nullable|string|max:100',
        ]);
        $expense->update($data);

        return response()->json(['success' => true, 'data' => $expense->fresh()->load(['category', 'supplier'])]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->authorizeTenant($expense);
        if (! $expense->isDraft()) {
            return response()->json(['success' => false, 'message' => 'Only draft expenses can be deleted.'], 422);
        }
        $expense->delete();

        return response()->json(['success' => true, 'message' => 'Expense deleted.']);
    }

    public function approve(Expense $expense): JsonResponse
    {
        $this->authorizeTenant($expense);
        if ($expense->isApproved()) {
            return response()->json(['success' => false, 'message' => 'Expense is already approved.'], 422);
        }

        try {
            $this->autoPostService->postExpenseApproval($expense);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $expense->fresh()->load(['category', 'supplier', 'journalEntry.lines.account', 'journalEntry.createdBy']),
            'message' => 'Expense approved and posted to accounting.',
        ]);
    }

    private function authorizeTenant(Expense $expense): void
    {
        abort_if($expense->tenant_id !== auth()->user()->tenant_id, 403);
    }
}
