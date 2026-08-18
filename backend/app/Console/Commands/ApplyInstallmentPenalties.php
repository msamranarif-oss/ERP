<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ApplyInstallmentPenalties extends Command
{
    protected $signature   = 'installments:apply-penalties';
    protected $description = 'Apply daily penalty charges to overdue installment_schedules';

    public function handle(): int
    {
        $today = now()->toDateString();
        $applied = 0;

        // installment_schedules is the real table; penalty_amount already exists
        DB::table('installment_schedules')
            ->where('due_date', '<', $today)
            ->whereNotIn('status', ['paid'])
            ->where('penalty_rate', '>', 0)
            ->whereNull('penalty_applied_at')
            ->orderBy('id')
            ->chunk(200, function ($rows) use (&$applied, $today) {
                foreach ($rows as $row) {
                    DB::transaction(function () use ($row, $today, &$applied) {
                        $daysOverdue = now()->diffInDays($row->due_date);
                        $remaining   = $row->total_amount - $row->paid_amount;

                        if ($remaining <= 0) return;

                        $penalty = round(
                            $remaining * ($row->penalty_rate / 100) * $daysOverdue,
                            2
                        );

                        DB::table('installment_schedules')
                            ->where('id', $row->id)
                            ->update([
                                'penalty_amount'      => $row->penalty_amount + $penalty,
                                'penalty_applied_at'  => $today,
                            ]);

                        $applied++;
                    });
                }
            });

        $this->info("Penalties applied to {$applied} installment schedule rows.");
        return 0;
    }
}
