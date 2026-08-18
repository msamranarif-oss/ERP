<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Departments (supports hierarchy via parent_id)
        if (! Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('manager_id')->nullable(); // employee_id
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'name'], 'dept_tenant_name_idx');
            });
        }

        // Positions / Job Titles
        if (! Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->decimal('min_salary', 15, 2)->nullable();
                $table->decimal('max_salary', 15, 2)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'department_id'], 'pos_tenant_dept_idx');
            });
        }

        // Employees
        if (! Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();       // links to system user
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedBigInteger('position_id')->nullable();
                $table->unsignedBigInteger('reports_to')->nullable();     // manager employee_id

                // Identification
                $table->string('employee_number')->nullable();
                $table->string('full_name');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->enum('gender', ['male', 'female', 'other'])->nullable();
                $table->date('date_of_birth')->nullable();
                $table->string('national_id')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->string('photo')->nullable();

                // Employment
                $table->date('hire_date');
                $table->date('termination_date')->nullable();
                $table->enum('contract_type', ['full_time', 'part_time', 'contract', 'intern'])->default('full_time');
                $table->enum('status', ['active', 'suspended', 'terminated', 'on_leave'])->default('active');
                $table->string('work_location')->nullable();

                // Banking
                $table->string('bank_name')->nullable();
                $table->string('bank_account')->nullable();
                $table->string('bank_branch')->nullable();

                // Tax
                $table->string('tax_id')->nullable();
                $table->decimal('tax_exemption', 15, 2)->default(0);

                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'employee_number']);
                $table->index(['tenant_id', 'status'], 'emp_tenant_status_idx');
                $table->index(['tenant_id', 'department_id'], 'emp_tenant_dept_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
