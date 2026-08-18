<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sales
        if (! Schema::hasTable('sales')) {
            Schema::create('sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('register_session_id')->nullable()->constrained()->nullOnDelete();
                $table->string('sale_number');
                $table->string('type')->default('sale'); // sale, return, exchange
                $table->date('sale_date');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->string('discount_type')->nullable(); // percentage, fixed
                $table->decimal('discount_value', 15, 2)->nullable();
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('shipping_amount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->decimal('change_amount', 15, 2)->default(0);
                $table->decimal('balance_due', 15, 2)->default(0);
                $table->string('payment_status')->default('paid'); // unpaid, partial, paid
                $table->string('status')->default('completed'); // draft, completed, voided
                $table->text('notes')->nullable();
                $table->text('internal_notes')->nullable();
                $table->foreignId('sold_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('voided_at')->nullable();
                $table->string('void_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'sale_number']);
                $table->index(['tenant_id', 'sale_date']);
                $table->index(['tenant_id', 'customer_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['branch_id', 'sale_date']);
            });
        }

        // Sale Items
        if (! Schema::hasTable('sale_items')) {
            Schema::create('sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
                $table->string('product_name');
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount', 15, 2)->default(0);
                $table->string('discount_type')->nullable();
                $table->decimal('tax', 15, 2)->default(0);
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->decimal('total', 15, 2);
                $table->decimal('cost_price', 15, 2)->default(0);
                $table->decimal('profit', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['sale_id']);
                $table->index(['product_id']);
            });
        }

        // Sale Payments
        if (! Schema::hasTable('sale_payments')) {
            Schema::create('sale_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // Sale Returns
        if (! Schema::hasTable('sale_returns')) {
            Schema::create('sale_returns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->string('return_number');
                $table->date('return_date');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->string('reason')->nullable();
                $table->string('status')->default('completed'); // pending, completed, rejected
                $table->string('refund_method')->nullable(); // cash, credit, store_credit
                $table->text('notes')->nullable();
                $table->foreignId('processed_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'return_number']);
                $table->index(['tenant_id', 'return_date']);
            });
        }

        // Sale Return Items
        if (! Schema::hasTable('sale_return_items')) {
            Schema::create('sale_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('amount', 15, 2);
                $table->string('condition')->nullable(); // good, damaged, defective
                $table->boolean('return_to_stock')->default(true);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // Held Sales (parked transactions)
        if (! Schema::hasTable('held_sales')) {
            Schema::create('held_sales', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('register_session_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('reference')->nullable();
                $table->json('items');
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('held_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'branch_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('held_sales');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
