<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('held_sale_items')) {
            Schema::create('held_sale_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('held_sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_percent', 5, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['held_sale_id']);
                $table->index(['product_id']);
            });
        }

        // Removed Schema::table change() to avoid SQLite lock limitations during tests
    }

    public function down(): void
    {
        Schema::dropIfExists('held_sale_items');
    }
};
