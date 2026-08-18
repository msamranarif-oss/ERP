<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Leave type definitions
        if (! Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code', 20)->nullable();
                $table->integer('days_allowed_per_year')->default(0);
                $table->boolean('carry_forward')->default(false);
                $table->integer('max_carry_forward_days')->default(0);
                $table->boolean('is_paid')->default(true);
                $table->boolean('requires_approval')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id'], 'lt_tenant_idx');
            });
        }

        // Per-employee leave balance per year
        if (! Schema::hasTable('leave_balances')) {
            Schema::create('leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('leave_type_id');
                $table->year('year');
                $table->decimal('allocated', 8, 2)->default(0);
                $table->decimal('used', 8, 2)->default(0);
                $table->decimal('pending', 8, 2)->default(0);   // in-flight requests
                $table->decimal('carried_forward', 8, 2)->default(0);
                $table->timestamps();

                $table->unique(['employee_id', 'leave_type_id', 'year'], 'lb_emp_type_year_idx');
                $table->index(['tenant_id', 'employee_id'], 'lb_tenant_emp_idx');
            });
        }

        // Leave requests
        if (! Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('leave_type_id');
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('days', 5, 1);
                $table->text('reason')->nullable();
                $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->text('notes')->nullable();        // reviewer notes
                $table->timestamps();

                $table->index(['tenant_id', 'employee_id', 'status'], 'lr_tenant_emp_status_idx');
            });
        }

        // Daily attendance
        if (! Schema::hasTable('attendance')) {
            Schema::create('attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('employee_id');
                $table->date('date');
                $table->time('check_in')->nullable();
                $table->time('check_out')->nullable();
                $table->decimal('hours_worked', 5, 2)->default(0);
                $table->decimal('overtime_hours', 5, 2)->default(0);
                $table->enum('status', ['present', 'absent', 'late', 'half_day', 'leave', 'holiday', 'weekend'])->default('present');
                $table->unsignedBigInteger('leave_request_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'employee_id', 'date'], 'att_tenant_emp_date_idx');
                $table->index(['tenant_id', 'date'], 'att_tenant_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
