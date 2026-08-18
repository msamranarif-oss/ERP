<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\AccountBalanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class JournalEntryService extends BaseService
{
    protected AccountBalanceService $balanceService;

    public function __construct(AccountBalanceService $balanceService)
    {
        parent::__construct(new JournalEntry);
        $this->balanceService = $balanceService;
    }

    public function createJournalEntry(array $data, ?int $tenantId = null, ?int $createdBy = null)
    {
        $tenantId ??= auth()->user()?->tenant_id;
        $createdBy ??= auth()->id();

        $totalDebits = collect($data['lines'])->sum('debit');
        $totalCredits = collect($data['lines'])->sum('credit');

        if (bccomp($totalDebits, $totalCredits, 4) !== 0) {
            throw new \Exception('Debits and credits must balance.');
        }

        $entryDate = $data['entry_date'] ?? $data['date'] ?? now()->toDateString();

        $periodInfo = $this->balanceService->resolvePeriodForDate($tenantId, $entryDate);

        return DB::transaction(function () use (
            $data,
            $totalDebits,
            $totalCredits,
            $tenantId,
            $createdBy,
            $entryDate,
            $periodInfo,
        ) {
            $journalEntry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'fiscal_year_id' => $periodInfo['fiscal_year_id'],
                'accounting_period_id' => $periodInfo['accounting_period_id'],
                'entry_number' => $data['entry_number'] ?? SequenceService::nextValue(
                    $data['sequence'] ?? 'journal_entry',
                    $tenantId,
                    (int) date('Y', strtotime($entryDate)),
                ),
                'entry_date' => $entryDate,
                'reference' => $data['reference'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'type' => $data['type'] ?? 'manual',
                'description' => $data['description'] ?? '',
                'total_debit' => $totalDebits,
                'total_credit' => $totalCredits,
                'status' => $data['status'] ?? 'draft',
                'is_auto_generated' => $data['is_auto_generated'] ?? false,
                'is_adjusting' => $data['is_adjusting'] ?? false,
                'created_by' => $createdBy,
                'reviewed_by' => $data['reviewed_by'] ?? null,
                'reviewed_at' => $data['reviewed_at'] ?? null,
                'approved_by' => $data['approved_by'] ?? null,
                'approved_at' => $data['approved_at'] ?? null,
                'posted_by' => $data['status'] === 'posted' ? ($data['posted_by'] ?? $createdBy) : null,
                'posted_at' => $data['status'] === 'posted' ? ($data['posted_at'] ?? now()) : null,
                'reversal_of_id' => $data['reversal_of_id'] ?? null,
            ]);

            foreach ($data['lines'] as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $line['account_id'],
                    'tenant_id' => $tenantId,
                    'tax_rate_id' => $line['tax_rate_id'] ?? null,
                    'taxable_amount' => $line['taxable_amount'] ?? 0,
                    'tax_amount' => $line['tax_amount'] ?? 0,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'description' => $line['description'] ?? null,
                    'branch_id' => $line['branch_id'] ?? null,
                    'department_id' => $line['department_id'] ?? null,
                ]);
            }

            return $journalEntry->load(['lines.account', 'createdBy']);
        });
    }

    public function updateJournalEntry(int $journalEntryId, array $data, ?int $tenantId = null)
    {
        $journalEntry = JournalEntry::findOrFail($journalEntryId);

        if ($journalEntry->status !== 'draft') {
            throw new \Exception('Cannot update journal entry that is not in draft status.');
        }

        $tenantId ??= $journalEntry->tenant_id;

        $lines = $data['lines'] ?? $journalEntry->lines->toArray();
        $totalDebits = collect($lines)->sum('debit');
        $totalCredits = collect($lines)->sum('credit');

        if (bccomp($totalDebits, $totalCredits, 4) !== 0) {
            throw new \Exception('Debits and credits must balance.');
        }

        $entryDate = $data['entry_date'] ?? $data['date'] ?? $journalEntry->entry_date;
        if (! is_string($entryDate)) {
            $entryDate = $entryDate->format('Y-m-d');
        }

        $periodInfo = $this->balanceService->resolvePeriodForDate($tenantId, $entryDate);

        return DB::transaction(function () use (
            $journalEntry,
            $data,
            $totalDebits,
            $totalCredits,
            $entryDate,
            $periodInfo,
            $tenantId,
        ) {
            $journalEntry->update([
                'fiscal_year_id' => $periodInfo['fiscal_year_id'] ?? $journalEntry->fiscal_year_id,
                'accounting_period_id' => $periodInfo['accounting_period_id'] ?? $journalEntry->accounting_period_id,
                'entry_date' => $entryDate,
                'reference' => $data['reference'] ?? $journalEntry->reference,
                'description' => $data['description'] ?? $journalEntry->description,
                'total_debit' => $totalDebits,
                'total_credit' => $totalCredits,
            ]);

            if (isset($data['lines'])) {
                $journalEntry->lines()->delete();

                foreach ($data['lines'] as $line) {
                    JournalEntryLine::create([
                        'journal_entry_id' => $journalEntry->id,
                        'account_id' => $line['account_id'],
                        'tenant_id' => $tenantId,
                        'tax_rate_id' => $line['tax_rate_id'] ?? null,
                        'taxable_amount' => $line['taxable_amount'] ?? 0,
                        'tax_amount' => $line['tax_amount'] ?? 0,
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'description' => $line['description'] ?? null,
                        'branch_id' => $line['branch_id'] ?? null,
                        'department_id' => $line['department_id'] ?? null,
                    ]);
                }
            }

            return $journalEntry->load(['lines.account', 'createdBy', 'postedBy']);
        });
    }

    public function deleteJournalEntry(int $journalEntryId)
    {
        $journalEntry = JournalEntry::findOrFail($journalEntryId);

        if ($journalEntry->status !== 'draft') {
            throw new \Exception('Cannot delete journal entry that is not in draft status.');
        }

        return DB::transaction(function () use ($journalEntry) {
            $journalEntry->lines()->delete();
            $journalEntry->delete();

            return true;
        });
    }

    public function postJournalEntry(int $journalEntryId, ?int $postedBy = null, float $approvalThreshold = 10000)
    {
        $postedBy ??= auth()->id();

        return DB::transaction(function () use ($journalEntryId, $postedBy, $approvalThreshold) {
            $journalEntry = JournalEntry::lockForUpdate()->findOrFail($journalEntryId);

            if ($journalEntry->status !== 'draft') {
                throw new \Exception('Cannot post journal entry that is not in draft status.');
            }

            if (! $journalEntry->isBalanced()) {
                throw new \Exception('Cannot post unbalanced journal entry.');
            }

            $entryDate = $journalEntry->entry_date instanceof \DateTimeInterface
                ? $journalEntry->entry_date->format('Y-m-d')
                : $journalEntry->entry_date;

            $periodInfo = $this->balanceService->resolvePeriodForDate($journalEntry->tenant_id, $entryDate);

            if ($periodInfo['is_closed']) {
                $periodName = $periodInfo['accounting_period']?->name ?? date('M Y', strtotime($entryDate));
                throw new \Exception("Period {$periodName} is closed. Cannot post journal entry.");
            }

            if ($journalEntry->total_debit > $approvalThreshold) {
                if (! $journalEntry->approved_by || ! $journalEntry->approved_at) {
                    throw new \Exception(
                        'Journal entry exceeds approval threshold of '.number_format($approvalThreshold, 2).' and requires approval before posting.',
                    );
                }
            }

            $journalEntry->update([
                'fiscal_year_id' => $periodInfo['fiscal_year_id'] ?? $journalEntry->fiscal_year_id,
                'accounting_period_id' => $periodInfo['accounting_period_id'] ?? $journalEntry->accounting_period_id,
                'status' => 'posted',
                'posted_at' => now(),
                'posted_by' => $postedBy,
            ]);

            return $journalEntry->load(['lines.account', 'createdBy', 'postedBy']);
        });
    }

    public function voidJournalEntry(int $journalEntryId, array $data, ?int $voidedBy = null)
    {
        $voidedBy ??= auth()->id();
        $reason = $data['reason'] ?? 'Voided';

        return DB::transaction(function () use ($journalEntryId, $voidedBy, $reason) {
            $original = JournalEntry::lockForUpdate()->findOrFail($journalEntryId);

            if (! $original->isPosted() && ! $original->isReversed()) {
                throw new \Exception('Cannot void journal entry that is not posted or reversed.');
            }

            if ($original->reversal_of_id !== null) {
                throw new \Exception('Cannot void a reversal journal entry.');
            }

            if ($original->isReversed()) {
                Log::warning('Attempted to void an already reversed journal entry.', ['je_id' => $original->id]);

                return $original;
            }

            $entryDate = now()->toDateString();
            $periodInfo = $this->balanceService->resolvePeriodForDate($original->tenant_id, $entryDate);

            $reversalLines = $original->lines->map(fn ($line) => [
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => 'Reversal: '.($line->description ?? $original->entry_number),
                'tax_rate_id' => $line->tax_rate_id,
                'taxable_amount' => $line->taxable_amount,
                'tax_amount' => $line->tax_amount,
                'branch_id' => $line->branch_id,
                'department_id' => $line->department_id,
            ])->toArray();

            $reversalEntry = $this->createJournalEntry([
                'entry_date' => $entryDate,
                'entry_number' => SequenceService::nextValue('journal_entry', $original->tenant_id, (int) date('Y')),
                'reference' => 'REV-'.$original->entry_number,
                'reference_type' => $original::class,
                'reference_id' => $original->id,
                'type' => 'reversal',
                'description' => "Reversing entry for {$original->entry_number}. Reason: {$reason}",
                'status' => 'posted',
                'is_auto_generated' => true,
                'sequence' => 'journal_entry',
                'lines' => $reversalLines,
                'reversal_of_id' => $original->id,
                'fiscal_year_id' => $periodInfo['fiscal_year_id'],
                'accounting_period_id' => $periodInfo['accounting_period_id'],
                'posted_by' => $voidedBy,
                'posted_at' => now(),
            ], $original->tenant_id, $voidedBy);

            $original->update([
                'status' => 'reversed',
                'voided_by' => $voidedBy,
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            return $original->load(['lines.account', 'reversal', 'createdBy', 'postedBy']);
        });
    }

    public function getJournalEntriesWithFilters(array $filters = [], int $perPage = 15)
    {
        $query = JournalEntry::with(['lines.account', 'createdBy', 'fiscalYear', 'accountingPeriod']);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('reference', 'like', '%'.$filters['search'].'%')
                    ->orWhere('entry_number', 'like', '%'.$filters['search'].'%')
                    ->orWhere('description', 'like', '%'.$filters['search'].'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['fiscal_year_id'])) {
            $query->where('fiscal_year_id', $filters['fiscal_year_id']);
        }

        if (! empty($filters['accounting_period_id'])) {
            $query->where('accounting_period_id', $filters['accounting_period_id']);
        }

        return $query->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }
}
