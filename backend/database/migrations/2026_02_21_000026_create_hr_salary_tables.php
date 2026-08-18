<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reusable salary component definitions (earning / deduction / tax)
        if (! Schema::hasTable('salary_components')) {
            Schema::create('salary_components', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 30)->nullable();
                $table->enum('type', ['earning', 'deduction', 'tax'])->default('earning');
                $table->enum('calculation_type', ['fixed', 'percentage_of_basic', 'percentage_of_gross'])->default('fixed');
                $table->decimal('default_value', 15, 2)->default(0);
                $table->boolean('taxable')->default(true);
                $table->boolean('is_statutory')->default(false); // e.g. social security
                $table->boolean('is_active')->default(true);
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'type'], 'sc_tenant_type_idx');
            });
        }

        // Employee salary record (effective-dated to allow history)
        if (! Schema::hasTable('employee_salaries')) {
            Schema::create('employee_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->decimal('basic_salary', 15, 2)->default(0);
                $table->date('effective_date');
                $table->string('currency', 10)->default('USD');
                $table->enum('pay_frequency', ['monthly', 'biweekly', 'weekly'])->default('monthly');
                $table->text('notes')->nullable();
                $table->boolean('is_current')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id'], 'es_tenant_emp_idx');
            });
        }

        // Per-employee salary component overrides
        if (! Schema::hasTable('employee_salary_components')) {
            Schema::create('employee_salary_components', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('employee_salary_id');
                $table->unsignedBigInteger('salary_component_id');
                $table->decimal('value', 15, 2)->default(0);  // override amount or %
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['employee_salary_id']);
            });
        }

        // Employee loans / advances
        if (! Schema::hasTable('employee_loans')) {
            Schema::create('employee_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->decimal('amount', 15, 2);
                $table->decimal('balance', 15, 2);
                $table->decimal('monthly_deduction', 15, 2)->default(0);
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id', 'status'], 'loan_tenant_emp_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('salary_components');
        Schema::dropIfExists('employee_loans');
    }
};
