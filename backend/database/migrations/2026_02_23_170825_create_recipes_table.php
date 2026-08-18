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
        if (! Schema::hasTable('recipes')) {
            Schema::create('recipes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('prep_time_minutes')->nullable();
                $table->integer('cook_time_minutes')->nullable();
                $table->integer('total_time_minutes')->nullable();
                $table->integer('servings');
                $table->json('instructions')->nullable(); // Array of steps
                $table->decimal('nutritional_calories', 8, 2)->nullable();
                $table->decimal('nutritional_protein', 8, 2)->nullable();
                $table->decimal('nutritional_carbs', 8, 2)->nullable();
                $table->decimal('nutritional_fat', 8, 2)->nullable();
                $table->string('image_url')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('category')->nullable(); // e.g., appetizer, main course, dessert
                $table->enum('difficulty_level', ['easy', 'medium', 'hard'])->default('medium');
                $table->string('seasonal_availability')->nullable(); // e.g., summer, winter, all year
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'is_active']);
                $table->index(['product_id']);
                $table->index(['category']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
