<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Account Types
        if (! Schema::hasTable('account_types')) {
            Schema::create('account_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('category'); // asset, liability, equity, revenue, expense
                $table->string('normal_balance'); // debit, credit
                $table->timestamps();
            });
        }

        // Fiscal Years
        if (! Schema::hasTable('fiscal_years')) {
            Schema::create('fiscal_years', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_closed')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tenant_id', 'name']);
                $table->index(['tenant_id', 'is_closed']);
            });
        }

        // Accounting Periods
        if (! Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_closed')->default(false);
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['fiscal_year_id', 'is_closed']);
            });
        }

        // Chart of Accounts
        if (! Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('account_type_id')->constrained()->cascadeOnDelete();
                $table->string('code');
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->boolean('allow_direct_posting')->default(true);
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('current_balance', 15, 2)->default(0);
                $table->integer('level')->default(1);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'account_type_id']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        // Tax Rates
        if (! Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->decimal('rate', 5, 2);
                $table->string('type')->default('percentage'); // percentage, fixed
                $table->foreignId('sales_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('purchase_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }

        // Journal Entries
        if (! Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('fiscal_year_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('accounting_period_id')->nullable()->constrained()->nullOnDelete();
                $table->string('entry_number');
                $table->date('entry_date');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('type')->default('general'); // general, sales, purchase, payment, receipt, adjustment
                $table->text('description')->nullable();
                $table->decimal('total_debit', 15, 2)->default(0);
                $table->decimal('total_credit', 15, 2)->default(0);
                $table->string('status')->default('draft'); // draft, posted, voided
                $table->boolean('is_auto_generated')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'entry_number']);
                $table->index(['tenant_id', 'entry_date']);
                $table->index(['tenant_id', 'status']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        // Journal Entry Lines
        if (! Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['journal_entry_id']);
                $table->index(['account_id']);
            });
        }

        // Bank Accounts
        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->string('bank_name');
                $table->string('account_number');
                $table->string('account_name');
                $table->string('branch')->nullable();
                $table->string('swift_code')->nullable();
                $table->string('currency')->default('USD');
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('current_balance', 15, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // Bank Transactions
        if (! Schema::hasTable('bank_transactions')) {
            Schema::create('bank_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
                $table->string('type'); // deposit, withdrawal, transfer, fee, interest
                $table->decimal('amount', 15, 2);
                $table->decimal('balance_after', 15, 2);
                $table->string('reference')->nullable();
                $table->text('description')->nullable();
                $table->date('transaction_date');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->boolean('is_reconciled')->default(false);
                $table->timestamp('reconciled_at')->nullable();
                $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['bank_account_id', 'transaction_date']);
                $table->index(['bank_account_id', 'is_reconciled']);
            });
        }

        // Bank Reconciliations
        if (! Schema::hasTable('bank_reconciliations')) {
            Schema::create('bank_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
                $table->date('statement_date');
                $table->decimal('statement_opening_balance', 15, 2);
                $table->decimal('statement_closing_balance', 15, 2);
                $table->decimal('system_balance', 15, 2);
                $table->decimal('difference', 15, 2)->default(0);
                $table->string('status')->default('draft'); // draft, completed
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['bank_account_id', 'statement_date']);
            });
        }

        // Budgets
        if (! Schema::hasTable('budgets')) {
            Schema::create('budgets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('fiscal_year_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('period'); // monthly, quarterly, yearly
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('budgeted_amount', 15, 2)->default(0);
                $table->decimal('actual_amount', 15, 2)->default(0);
                $table->decimal('variance', 15, 2)->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'fiscal_year_id', 'account_id', 'period_start'], 'budgets_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
        Schema::dropIfExists('account_types');
    }
};
