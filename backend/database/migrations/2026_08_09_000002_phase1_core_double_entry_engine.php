<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('journal_entries', 'is_adjusting')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->boolean('is_adjusting')->default(false)->after('is_auto_generated');
                $table->foreignId('reviewed_by')->nullable()->after('created_by')
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                $table->foreignId('approved_by')->nullable()->after('reviewed_at')
                    ->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            });
        }

        if (! Schema::hasColumn('journal_entry_lines', 'branch_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('tenant_id')
                    ->constrained()->nullOnDelete();
                $table->foreignId('department_id')->nullable()->after('branch_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('expenses', 'supplier_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('supplier_id')->nullable()->after('branch_id')
                    ->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('expenses', 'journal_entry_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('journal_entry_id')->nullable()->after('bank_account_id')
                    ->constrained('journal_entries')->nullOnDelete();
            });
        }

        $existingJelIndexes = Schema::getIndexes('journal_entry_lines');
        $hasJelComposite = false;
        foreach ($existingJelIndexes as $idx) {
            $cols = $idx['columns'] ?? [];
            if (is_array($cols)
                && count($cols) >= 4
                && $cols[0] === 'account_id'
                && in_array('debit', $cols)
                && in_array('credit', $cols)
            ) {
                $hasJelComposite = true;
                break;
            }
        }
        if (! $hasJelComposite) {
            try {
                Schema::table('journal_entry_lines', function (Blueprint $table) {
                    $table->index(['account_id', 'debit', 'credit']);
                });
            } catch (\Throwable $e) {
            }
        }

        $existingJeDateIdx = false;
        foreach (Schema::getIndexes('journal_entries') as $idx) {
            $cols = $idx['columns'] ?? [];
            if (is_array($cols) && count($cols) >= 1 && $cols[0] === 'entry_date') {
                $existingJeDateIdx = true;
                break;
            }
        }
        if (! $existingJeDateIdx) {
            try {
                Schema::table('journal_entries', function (Blueprint $table) {
                    $table->index(['entry_date', 'status']);
                });
            } catch (\Throwable $e) {
            }
        }
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            try {
                $table->dropIndex(['entry_date', 'status']);
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('journal_entries', 'approved_by')) {
                $table->dropForeign(['approved_by']);
                $table->dropForeign(['reviewed_by']);
                $table->dropColumn([
                    'is_adjusting',
                    'reviewed_by',
                    'reviewed_at',
                    'approved_by',
                    'approved_at',
                ]);
            }
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            try {
                $table->dropIndex(['account_id', 'debit', 'credit']);
            } catch (\Throwable $e) {
            }
            if (Schema::hasColumn('journal_entry_lines', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropForeign(['department_id']);
                $table->dropColumn(['branch_id', 'department_id']);
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'journal_entry_id')) {
                $table->dropForeign(['journal_entry_id']);
                $table->dropForeign(['supplier_id']);
                $table->dropColumn(['journal_entry_id', 'supplier_id']);
            }
        });
    }
};
