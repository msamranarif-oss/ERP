<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Credit Settings
        Schema::create('credit_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->integer('max_credit_period_days')->default(365);
            $table->decimal('default_interest_rate', 5, 2)->default(0);
            $table->string('interest_type')->default('simple'); // simple, compound
            $table->string('interest_period')->default('monthly'); // daily, weekly, monthly, yearly
            $table->decimal('penalty_rate', 5, 2)->default(0);
            $table->integer('grace_period_days')->default(0);
            $table->decimal('min_down_payment_percent', 5, 2)->default(0);
            $table->integer('max_installments')->default(12);
            $table->boolean('require_approval')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id']);
        });

        // Credit Customers (extended customer credit info)
        Schema::create('credit_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->integer('credit_score')->default(0);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('used_credit', 15, 2)->default(0);
            $table->decimal('available_credit', 15, 2)->default(0);
            $table->string('status')->default('pending'); // pending, approved, suspended, blocked
            $table->string('id_type')->nullable();
            $table->string('id_number')->nullable();
            $table->string('id_document')->nullable();
            $table->string('employer')->nullable();
            $table->string('employer_phone')->nullable();
            $table->decimal('monthly_income', 15, 2)->nullable();
            $table->text('guarantor_name')->nullable();
            $table->string('guarantor_phone')->nullable();
            $table->text('guarantor_address')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['customer_id']);
        });

        // Credit Applications
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->string('purpose')->nullable();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['customer_id']);
        });

        // Credit Sales
        Schema::create('credit_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_amount', 15, 2);
            $table->decimal('down_payment', 15, 2)->default(0);
            $table->decimal('financed_amount', 15, 2);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->string('interest_type')->default('simple');
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_payable', 15, 2);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('total_balance', 15, 2);
            $table->integer('installment_count');
            $table->string('installment_frequency')->default('monthly'); // weekly, bi_weekly, monthly
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('active'); // active, completed, defaulted, cancelled
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['customer_id']);
            $table->index(['tenant_id', 'end_date']);
        });

        // Installment Schedules
        Schema::create('installment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_sale_id')->constrained()->cascadeOnDelete();
            $table->integer('installment_number');
            $table->date('due_date');
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('interest_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2);
            $table->string('status')->default('pending'); // pending, partial, paid, overdue
            $table->date('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['credit_sale_id', 'installment_number']);
            $table->index(['due_date', 'status']);
        });

        // Installment Payments
        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['installment_schedule_id']);
        });

        // Payment Reminders
        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('installment_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // sms, email, call
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('message')->nullable();
            $table->text('response')->nullable();
            $table->timestamps();

            $table->index(['credit_sale_id']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
        Schema::dropIfExists('installment_payments');
        Schema::dropIfExists('installment_schedules');
        Schema::dropIfExists('credit_sales');
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('credit_customers');
        Schema::dropIfExists('credit_settings');
    }
};
