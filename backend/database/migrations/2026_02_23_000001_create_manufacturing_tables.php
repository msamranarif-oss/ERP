<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('manufacturing_orders')) {
            Schema::create('manufacturing_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // Finished Good
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->string('order_number')->unique();
                $table->decimal('quantity_planned', 15, 4);
                $table->decimal('quantity_produced', 15, 4)->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->enum('status', ['draft', 'planned', 'in_progress', 'completed', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('manufacturing_order_items')) {
            Schema::create('manufacturing_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('manufacturing_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // Raw Material
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->decimal('quantity_planned', 15, 4);
                $table->decimal('quantity_consumed', 15, 4)->default(0);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('manufacturing_order_items');
        Schema::dropIfExists('manufacturing_orders');
    }
};
