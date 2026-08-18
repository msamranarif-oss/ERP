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
        if (! Schema::hasTable('recipe_ingredients')) {
            Schema::create('recipe_ingredients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
                $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 10, 4);
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
                $table->boolean('optional')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['recipe_id']);
                $table->index(['ingredient_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
