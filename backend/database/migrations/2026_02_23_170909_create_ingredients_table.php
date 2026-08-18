<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ingredients')) {
            Schema::create('ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('category')->nullable(); // e.g., vegetable, meat, spice
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('cost_per_unit', 10, 2);
                $table->decimal('minimum_stock_level', 10, 4)->default(0);
                $table->decimal('maximum_stock_level', 10, 4)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
                $table->string('sku')->nullable();
                $table->string('barcode')->nullable();
                $table->string('image_url')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'is_active']);
                $table->index(['supplier_id']);
                $table->index(['sku']);
                $table->index(['barcode']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredients');
    }
};
