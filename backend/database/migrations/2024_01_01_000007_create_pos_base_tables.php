<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customers
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('code')->nullable();
                $table->string('name');
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('tax_number')->nullable();
                $table->enum('customer_type', ['retail', 'wholesale', 'corporate'])->default('retail');
                $table->decimal('credit_limit', 15, 2)->default(0);
                $table->decimal('balance', 15, 2)->default(0);
                $table->integer('points')->default(0);
                $table->date('date_of_birth')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'phone']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        // Payment Methods
        if (! Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code');
                $table->string('type')->default('cash'); // cash, card, bank_transfer, mobile_money, credit
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->json('settings')->nullable();
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }

        // Cash Registers
        if (! Schema::hasTable('cash_registers')) {
            Schema::create('cash_registers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code']);
            });
        }

        // Register Sessions
        if (! Schema::hasTable('register_sessions')) {
            Schema::create('register_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('opening_balance', 15, 2)->default(0);
                $table->decimal('cash_sales', 15, 2)->default(0);
                $table->decimal('card_sales', 15, 2)->default(0);
                $table->decimal('other_sales', 15, 2)->default(0);
                $table->decimal('refunds', 15, 2)->default(0);
                $table->decimal('cash_in', 15, 2)->default(0);
                $table->decimal('cash_out', 15, 2)->default(0);
                $table->decimal('expected_balance', 15, 2)->default(0);
                $table->decimal('closing_balance', 15, 2)->nullable();
                $table->decimal('difference', 15, 2)->nullable();
                $table->string('status')->default('open'); // open, closed
                $table->text('opening_notes')->nullable();
                $table->text('closing_notes')->nullable();
                $table->timestamp('opened_at');
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['cash_register_id', 'status']);
                $table->index(['user_id', 'status']);
            });
        }

        // Discounts
        if (! Schema::hasTable('discounts')) {
            Schema::create('discounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('type'); // percentage, fixed
                $table->decimal('value', 15, 2);
                $table->decimal('min_purchase', 15, 2)->nullable();
                $table->decimal('max_discount', 15, 2)->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('register_sessions');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customers');
    }
};
