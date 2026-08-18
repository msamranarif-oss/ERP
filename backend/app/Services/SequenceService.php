<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SequenceService
{
    /**
     * Generate a human-friendly reference string using the atomic sequence counter.
     *
     * @param  string  $type  Sequence type key (e.g. 'sale', 'adjustment', 'journal_entry')
     * @param  string  $prefix  Prefix override for the generated reference
     */
    public function generateReference(string $type, string $prefix): string
    {
        $year = (int) date('Y');

        $counter = DB::transaction(function () use ($type, $year) {
            $tenantId = auth()->check() ? auth()->user()->tenant_id : null;

            if ($tenantId === null) {
                throw new \RuntimeException('Cannot generate reference: no authenticated tenant.');
            }

            $counter = DB::table('sequence_counters')
                ->lockForUpdate()
                ->where('tenant_id', $tenantId)
                ->where('type', $type)
                ->where('year', $year)
                ->first();

            if (! $counter) {
                DB::table('sequence_counters')->insert([
                    'tenant_id' => $tenantId,
                    'type' => $type,
                    'year' => $year,
                    'current_value' => 1,
                    'updated_at' => now(),
                ]);

                return 1;
            }

            DB::table('sequence_counters')
                ->where('id', $counter->id)
                ->increment('current_value', 1, ['updated_at' => now()]);

            return $counter->current_value + 1;
        });

        return sprintf('%s-%d-%05d', strtoupper($prefix), $year, $counter);
    }

    /**
     * Static variant used by async/CLI services that have tenant context.
     * Uses type-based prefix mapping (no external prefix argument).
     */
    public static function nextValue(string $type, int $tenantId, ?int $year = null): string
    {
        $year = $year ?? (int) date('Y');

        $counter = DB::transaction(function () use ($type, $tenantId, $year) {
            $row = DB::table('sequence_counters')
                ->lockForUpdate()
                ->where('tenant_id', $tenantId)
                ->where('type', $type)
                ->where('year', $year)
                ->first();

            if (! $row) {
                DB::table('sequence_counters')->insert([
                    'tenant_id' => $tenantId,
                    'type' => $type,
                    'year' => $year,
                    'current_value' => 1,
                    'updated_at' => now(),
                ]);

                return 1;
            }

            DB::table('sequence_counters')
                ->where('id', $row->id)
                ->increment('current_value', 1, ['updated_at' => now()]);

            return $row->current_value + 1;
        });

        $typePrefixes = [
            'journal_entry' => 'JE',
            'sale' => 'INV',
            'payment' => 'RCT',
            'grn' => 'GRN',
            'po' => 'PO',
            'payroll' => 'PAY',
            'expense' => 'EXP',
            'adjustment' => 'ADJ',
            'sale_return' => 'SR',
            'purchase_return' => 'PR',
            'held_sale' => 'HD',
        ];

        $prefix = $typePrefixes[$type] ?? strtoupper($type);

        return sprintf('%s-%d-%05d', $prefix, $year, $counter);
    }
}
