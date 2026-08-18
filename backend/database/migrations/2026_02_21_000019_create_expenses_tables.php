<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            Schema::create('expense_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->string('reference')->nullable();
                $table->date('expense_date');
                $table->decimal('amount', 15, 2);
                $table->string('payment_method')->nullable(); // cash, bank, card
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->string('payee')->nullable();
                $table->text('description')->nullable();
                $table->string('attachment')->nullable();
                $table->string('status')->default('draft'); // draft, approved, rejected
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'expense_date']);
                $table->index(['tenant_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
