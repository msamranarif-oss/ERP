<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('purchase_returns')) {
            Schema::create('purchase_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('supplier_id');
                $table->unsignedBigInteger('grn_id')->nullable();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->string('return_number')->unique();
                $table->date('return_date');
                $table->string('status', 20)->default('pending'); // pending, approved, completed
                $table->text('reason')->nullable();
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('created_by');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
                $table->foreign('grn_id')->references('id')->on('goods_received_notes')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users');
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('purchase_return_items')) {
            Schema::create('purchase_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_return_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->unsignedBigInteger('unit_id');
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_cost', 15, 2);
                $table->decimal('total', 15, 2);
                $table->string('reason')->nullable();
                $table->timestamps();

                $table->foreign('purchase_return_id')->references('id')->on('purchase_returns')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products');
                $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
                $table->foreign('unit_id')->references('id')->on('units');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
