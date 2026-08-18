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
        Schema::table('held_sales', function (Blueprint $table) {
            $table->foreignId('restaurant_table_id')->nullable()->constrained('restaurant_tables')->nullOnDelete();
            $table->enum('order_type', ['dine-in', 'takeaway', 'delivery'])->default('takeaway');
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            
            $table->index(['restaurant_table_id']);
            $table->index(['order_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('held_sales', function (Blueprint $table) {
            $table->dropForeign(['restaurant_table_id']);
            $table->dropForeign(['sale_id']);
            $table->dropColumn(['restaurant_table_id', 'order_type', 'sale_id']);
        });
    }
};
