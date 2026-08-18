<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JournalEntry;
use App\Services\JournalEntryService;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    protected JournalEntryService $journalEntryService;

    public function __construct(JournalEntryService $journalEntryService)
    {
        $this->journalEntryService = $journalEntryService;
        $this->authorizeResource(JournalEntry::class, 'journal_entry');
    }

    public function index(Request $request)
    {
        $filters = [];
        if ($request->filled('search')) {
            $filters['search'] = $request->search;
        }
        if ($request->filled('status')) {
            $filters['status'] = $request->status;
        }
        if ($request->filled('type')) {
            $filters['type'] = $request->type;
        }
        if ($request->filled('date_from')) {
            $filters['date_from'] = $request->date_from;
        }
        if ($request->filled('date_to')) {
            $filters['date_to'] = $request->date_to;
        }
        if ($request->filled('fiscal_year_id')) {
            $filters['fiscal_year_id'] = $request->fiscal_year_id;
        }
        if ($request->filled('accounting_period_id')) {
            $filters['accounting_period_id'] = $request->accounting_period_id;
        }

        $entries = $this->journalEntryService->getJournalEntriesWithFilters($filters, $request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $entries,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'entry_date'             => 'required|date',
            'reference'              => 'nullable|string|max:255',
            'description'            => 'required|string|max:500',
            'lines'                  => 'required|array|min:2',
            'lines.*.account_id'     => 'required|exists:chart_of_accounts,id',
            'lines.*.debit'          => 'required|numeric|min:0',
            'lines.*.credit'         => 'required|numeric|min:0',
            'lines.*.description'    => 'nullable|string|max:255',
            'lines.*.branch_id'      => 'nullable|exists:branches,id',
            'lines.*.department_id'  => 'nullable|exists:departments,id',
        ]);

        try {
            $journalEntry = $this->journalEntryService->createJournalEntry($validated);

            return response()->json([
                'success' => true,
                'data'    => $journalEntry,
                'message' => 'Journal entry created successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(JournalEntry $journal_entry)
    {
        return response()->json([
            'success' => true,
            'data' => $journal_entry->load(['lines.account', 'createdBy', 'postedBy']),
        ]);
    }

    public function update(Request $request, JournalEntry $journal_entry)
    {
        $validated = $request->validate([
            'entry_date'             => 'sometimes|required|date',
            'reference'              => 'sometimes|nullable|string|max:255',
            'description'            => 'sometimes|required|string|max:500',
            'lines'                  => 'sometimes|required|array|min:2',
            'lines.*.account_id'     => 'sometimes|required|exists:chart_of_accounts,id',
            'lines.*.debit'          => 'sometimes|required|numeric|min:0',
            'lines.*.credit'         => 'sometimes|required|numeric|min:0',
            'lines.*.description'    => 'sometimes|nullable|string|max:255',
            'lines.*.branch_id'      => 'sometimes|nullable|exists:branches,id',
            'lines.*.department_id'  => 'sometimes|nullable|exists:departments,id',
        ]);

        try {
            $updatedJournalEntry = $this->journalEntryService->updateJournalEntry($journal_entry->id, $validated);

            return response()->json([
                'success' => true,
                'data' => $updatedJournalEntry,
                'message' => 'Journal entry updated successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(JournalEntry $journal_entry)
    {
        try {
            $this->journalEntryService->deleteJournalEntry($journal_entry->id);

            return response()->json([
                'success' => true,
                'message' => 'Journal entry deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function post(JournalEntry $journal_entry)
    {
        try {
            $postedJournalEntry = $this->journalEntryService->postJournalEntry($journal_entry->id);

            return response()->json([
                'success' => true,
                'data' => $postedJournalEntry,
                'message' => 'Journal entry posted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function void(JournalEntry $journal_entry, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $voidedJournalEntry = $this->journalEntryService->voidJournalEntry($journal_entry->id, $validated);

            return response()->json([
                'success' => true,
                'data' => $voidedJournalEntry,
                'message' => 'Journal entry voided successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
