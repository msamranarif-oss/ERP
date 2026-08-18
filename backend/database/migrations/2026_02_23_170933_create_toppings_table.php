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
        if (! Schema::hasTable('toppings')) {
            Schema::create('toppings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('cost_per_unit', 10, 2);
                $table->decimal('selling_price', 10, 2);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_available')->default(true);
                $table->string('image_url')->nullable();
                $table->integer('max_allowed')->default(1); // Maximum number allowed per order
                $table->integer('min_required')->default(0); // Minimum required per order
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'is_active']);
                $table->index(['category_id']);
                $table->index(['is_available']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('toppings');
    }
};
