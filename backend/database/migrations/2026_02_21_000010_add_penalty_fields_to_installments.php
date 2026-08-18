<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The actual table for installment line items is `installment_schedules`.
     * `penalty_amount` and `paid_at` already exist from the original migration.
     * We only add what is genuinely missing: `penalty_rate` (per-schedule override)
     * and `penalty_applied_at` (to prevent double-application).
     */
    public function up(): void
    {
        Schema::table('installment_schedules', function (Blueprint $table) {
            if (!Schema::hasColumn('installment_schedules', 'penalty_rate')) {
                $table->decimal('penalty_rate', 5, 2)->default(0)
                    ->after('penalty_amount')
                    ->comment('% per day — per-schedule override of credit_settings value');
            }
            if (!Schema::hasColumn('installment_schedules', 'penalty_applied_at')) {
                $table->date('penalty_applied_at')->nullable()->after('penalty_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('installment_schedules', function (Blueprint $table) {
            $table->dropColumn(['penalty_rate', 'penalty_applied_at']);
        });
    }
};
