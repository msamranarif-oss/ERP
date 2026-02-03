<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = JournalEntry::with(['lines.account', 'createdBy']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('reference', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $entries = $query->orderBy('date', 'desc')
                          ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $entries
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'description' => 'required|string|max:500',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        // Validate that debits equal credits
        $totalDebits = collect($validated['lines'])->sum('debit');
        $totalCredits = collect($validated['lines'])->sum('credit');

        if (abs($totalDebits - $totalCredits) > 0.01) { // Allow small rounding differences
            return response()->json([
                'success' => false,
                'message' => 'Debits and credits must balance.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $journalEntry = JournalEntry::create([
                'entry_number' => 'JE-' . date('Y') . '-' . str_pad(JournalEntry::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'date' => $validated['date'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'],
                'total_debit' => $totalDebits,
                'total_credit' => $totalCredits,
                'status' => 'draft',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['lines'] as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'] ?? null,
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $journalEntry->load(['lines.account', 'createdBy']),
                'message' => 'Journal entry created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal entry: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(JournalEntry $journal_entry)
    {
        return response()->json([
            'success' => true,
            'data' => $journal_entry->load(['lines.account', 'createdBy', 'postedBy'])
        ]);
    }

    public function update(Request $request, JournalEntry $journal_entry)
    {
        if ($journal_entry->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update journal entry that is not in draft status.'
            ], 422);
        }

        $validated = $request->validate([
            'date' => 'sometimes|required|date',
            'reference' => 'sometimes|nullable|string|max:255',
            'description' => 'sometimes|required|string|max:500',
            'lines' => 'sometimes|required|array|min:2',
            'lines.*.account_id' => 'sometimes|required|exists:accounts,id',
            'lines.*.debit' => 'sometimes|required|numeric|min:0',
            'lines.*.credit' => 'sometimes|required|numeric|min:0',
            'lines.*.description' => 'sometimes|nullable|string|max:255',
        ]);

        // Validate that debits equal credits
        $lines = $validated['lines'] ?? $journal_entry->lines->toArray();
        $totalDebits = collect($lines)->sum('debit');
        $totalCredits = collect($lines)->sum('credit');

        if (abs($totalDebits - $totalCredits) > 0.01) { // Allow small rounding differences
            return response()->json([
                'success' => false,
                'message' => 'Debits and credits must balance.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $journalEntry = $journal_entry;
            $journalEntry->update([
                'date' => $validated['date'] ?? $journalEntry->date,
                'reference' => $validated['reference'] ?? $journalEntry->reference,
                'description' => $validated['description'] ?? $journalEntry->description,
                'total_debit' => $totalDebits,
                'total_credit' => $totalCredits,
            ]);

            if (isset($validated['lines'])) {
                // Delete existing lines
                $journalEntry->lines()->delete();

                // Add new lines
                foreach ($validated['lines'] as $line) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $line['account_id'],
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'description' => $line['description'] ?? null,
                        'tenant_id' => auth()->user()->tenant_id,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $journalEntry->load(['lines.account', 'createdBy', 'postedBy']),
                'message' => 'Journal entry updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update journal entry: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(JournalEntry $journal_entry)
    {
        if ($journal_entry->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete journal entry that is not in draft status.'
            ], 422);
        }

        $journal_entry->lines()->delete();
        $journal_entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Journal entry deleted successfully.'
        ]);
    }

    public function post(JournalEntry $journal_entry)
    {
        if ($journal_entry->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot post journal entry that is not in draft status.'
            ], 422);
        }

        $journal_entry->update([
            'status' => 'posted',
            'posted_at' => now(),
            'posted_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $journal_entry->load(['lines.account', 'createdBy', 'postedBy']),
            'message' => 'Journal entry posted successfully.'
        ]);
    }

    public function void(JournalEntry $journal_entry, Request $request)
    {
        if ($journal_entry->status !== 'posted') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot void journal entry that is not posted.'
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        $journal_entry->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by' => auth()->id(),
            'void_reason' => $validated['reason'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $journal_entry->load(['lines.account', 'createdBy', 'postedBy']),
            'message' => 'Journal entry voided successfully.'
        ]);
    }
}