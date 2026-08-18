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
        if (! Schema::hasTable('recipe_toppings')) {
            Schema::create('recipe_toppings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
                $table->foreignId('topping_id')->constrained()->cascadeOnDelete();
                $table->boolean('is_required')->default(false);
                $table->boolean('default_selected')->default(false);
                $table->timestamps();

                $table->index(['recipe_id']);
                $table->index(['topping_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_toppings');
    }
};
