<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0.1.5: Add reference column to journal_entries
        if (! Schema::hasColumn('journal_entries', 'reference')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->string('reference')->nullable()->after('entry_date');
            });
        }

        // Phase 1 prep: Add reversal_of_id FK to journal_entries
        if (! Schema::hasColumn('journal_entries', 'reversal_of_id')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->foreignId('reversal_of_id')
                    ->nullable()
                    ->after('void_reason')
                    ->constrained('journal_entries')
                    ->nullOnDelete();
            });
        }

        // 0.2: Add calculation_mode column to tax_rates
        if (! Schema::hasColumn('tax_rates', 'calculation_mode')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->string('calculation_mode')
                    ->default('exclusive')
                    ->after('type');
            });
        }

        // 0.5 + 0.6: Add system_slug to chart_of_accounts for system account lookup
        if (! Schema::hasColumn('chart_of_accounts', 'system_slug')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->string('system_slug')
                    ->nullable()
                    ->after('code');
                $table->unique(['tenant_id', 'system_slug']);
            });
        }

        // 0.3.1 + 0.3.2: Add tenant_id, softDeletes, tax columns to journal_entry_lines
        if (! Schema::hasColumn('journal_entry_lines', 'tenant_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('account_id')
                    ->constrained()
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('journal_entry_lines', 'tax_rate_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->foreignId('tax_rate_id')
                    ->nullable()
                    ->after('account_id')
                    ->constrained('tax_rates')
                    ->nullOnDelete();
                $table->decimal('taxable_amount', 15, 2)
                    ->default(0)
                    ->after('credit');
                $table->decimal('tax_amount', 15, 2)
                    ->default(0)
                    ->after('taxable_amount');
            });
        }

        // Note: changing FK from cascadeOnDelete to restrictOnDelete
        // for account_id on journal_entry_lines to prevent accidental history loss.
        // We do this in 2 steps: drop the existing FK, then re-add with restrict.
        try {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $existingFKs = Schema::getForeignKeys('journal_entry_lines');
                $hasAccountFK = false;
                foreach ($existingFKs as $fk) {
                    $cols = $fk['columns'] ?? [];
                    if (is_array($cols) && in_array('account_id', $cols)) {
                        $hasAccountFK = true;
                        $table->dropForeign($fk['name']);
                        break;
                    }
                }
                if (! $hasAccountFK) {
                    try {
                        $table->dropForeign(['account_id']);
                    } catch (\Exception $e) {
                        // FK may not exist, proceed
                    }
                }
                $table->foreign('account_id')
                    ->references('id')
                    ->on('chart_of_accounts')
                    ->restrictOnDelete();
            });
        } catch (\Exception $e) {
            // If FK manipulation fails, continue — it's a safety net, not blocker
        }

        if (! Schema::hasColumn('journal_entry_lines', 'deleted_at')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // 0.3.3: Add tenant_id to bank_transactions
        if (! Schema::hasColumn('bank_transactions', 'tenant_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->foreignId('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained()
                    ->cascadeOnDelete();
                $table->index(['tenant_id', 'transaction_date']);
            });
        }

        // 0.3.5: Add 4 JSON columns to bank_reconciliations
        if (! Schema::hasColumn('bank_reconciliations', 'outstanding_checks')) {
            Schema::table('bank_reconciliations', function (Blueprint $table) {
                $table->json('outstanding_checks')->nullable()->after('difference');
                $table->json('deposits_in_transit')->nullable()->after('outstanding_checks');
                $table->json('bank_charges')->nullable()->after('deposits_in_transit');
                $table->json('interest_earned')->nullable()->after('bank_charges');
            });
        }

        // Add composite index for account balance aggregation (Phase 1 prep)
        $existingIndexes = Schema::getIndexes('journal_entry_lines');
        $hasAccountDateIdx = false;
        foreach ($existingIndexes as $idx) {
            $cols = $idx['columns'] ?? [];
            if (is_array($cols) && count($cols) >= 2 && $cols[0] === 'account_id') {
                $hasAccountDateIdx = true;
                break;
            }
        }
        if (! $hasAccountDateIdx) {
            try {
                Schema::table('journal_entry_lines', function (Blueprint $table) {
                    $table->index(['account_id', 'debit', 'credit']);
                });
            } catch (\Exception $e) {
                // Index may already exist
            }
        }
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            try {
                $table->dropIndex(['account_id', 'debit', 'credit']);
            } catch (\Exception $e) {
            }
        });

        if (Schema::hasColumn('bank_reconciliations', 'interest_earned')) {
            Schema::table('bank_reconciliations', function (Blueprint $table) {
                $table->dropColumn([
                    'outstanding_checks',
                    'deposits_in_transit',
                    'bank_charges',
                    'interest_earned',
                ]);
            });
        }

        if (Schema::hasColumn('bank_transactions', 'tenant_id')) {
            Schema::table('bank_transactions', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropIndex(['tenant_id', 'transaction_date']);
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasColumn('journal_entry_lines', 'deleted_at')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }

        if (Schema::hasColumn('journal_entry_lines', 'tax_rate_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->dropForeign(['tax_rate_id']);
                $table->dropColumn([
                    'tax_rate_id',
                    'taxable_amount',
                    'tax_amount',
                ]);
            });
        }

        if (Schema::hasColumn('journal_entry_lines', 'tenant_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasColumn('chart_of_accounts', 'system_slug')) {
            Schema::table('chart_of_accounts', function (Blueprint $table) {
                $table->dropUnique(['tenant_id', 'system_slug']);
                $table->dropColumn('system_slug');
            });
        }

        if (Schema::hasColumn('tax_rates', 'calculation_mode')) {
            Schema::table('tax_rates', function (Blueprint $table) {
                $table->dropColumn('calculation_mode');
            });
        }

        if (Schema::hasColumn('journal_entries', 'reversal_of_id')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropForeign(['reversal_of_id']);
                $table->dropColumn('reversal_of_id');
            });
        }

        if (Schema::hasColumn('journal_entries', 'reference')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropColumn('reference');
            });
        }
    }
};
