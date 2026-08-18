<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Payroll period (e.g. "January 2026")
        if (! Schema::hasTable('payroll_periods')) {
            Schema::create('payroll_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->enum('period_type', ['monthly', 'biweekly', 'weekly'])->default('monthly');
                $table->date('start_date');
                $table->date('end_date');
                $table->enum('status', ['draft', 'processing', 'approved', 'paid'])->default('draft');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('journal_entry_id')->nullable(); // accounting ref
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status'], 'pp_tenant_status_idx');
                $table->index(['tenant_id', 'start_date'], 'pp_tenant_date_idx');
            });
        }

        // Per-employee payroll calculation result (one row per employee per period)
        if (! Schema::hasTable('payroll_runs')) {
            Schema::create('payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('payroll_period_id');
                $table->unsignedBigInteger('employee_id');

                // Attendance for this period
                $table->integer('working_days')->default(0);
                $table->integer('days_worked')->default(0);
                $table->integer('days_absent')->default(0);
                $table->decimal('leave_days_paid', 5, 2)->default(0);
                $table->decimal('leave_days_unpaid', 5, 2)->default(0);
                $table->decimal('overtime_hours', 5, 2)->default(0);

                // Pay summary
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->decimal('gross_earnings', 15, 2)->default(0);
                $table->decimal('total_deductions', 15, 2)->default(0);
                $table->decimal('loan_deductions', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('net_pay', 15, 2)->default(0);

                $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['payroll_period_id', 'employee_id'], 'pr_period_emp_unique');
                $table->index(['tenant_id', 'payroll_period_id'], 'pr_tenant_period_idx');
            });
        }

        // Line-by-line component breakdown for each payroll run
        if (! Schema::hasTable('payroll_run_lines')) {
            Schema::create('payroll_run_lines', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payroll_run_id');
                $table->unsignedBigInteger('salary_component_id')->nullable();
                $table->string('component_name');
                $table->enum('type', ['earning', 'deduction', 'tax'])->default('earning');
                $table->decimal('amount', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['payroll_run_id'], 'prl_run_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_lines');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('payroll_periods');
    }
};
