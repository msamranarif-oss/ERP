<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration adds composite indexes to improve dashboard query performance
     */
    public function up(): void
    {
        $addIndex = function (string $tableName, $columns, ?string $name = null) {
            try {
                Schema::table($tableName, function (Blueprint $table) use ($columns, $name) {
                    if ($name !== null) {
                        $table->index($columns, $name);
                    } else {
                        $table->index($columns);
                    }
                });
            } catch (\Throwable $e) {
            }
        };

        $addIndex('sales', ['sale_date', 'tenant_id', 'total'], 'sales_date_range');
        $addIndex('sales', 'sold_by', 'sales_sold_by_index');
        $addIndex('sales', ['customer_id', 'sale_date'], 'sales_customer_lookup');
        $addIndex('sale_items', ['product_id', 'quantity', 'total'], 'sale_items_product_revenue');
        $addIndex('users', ['tenant_id', 'is_active'], 'users_tenant_active');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_date_range');
            $table->dropIndex('sales_sold_by_index');
            $table->dropIndex('sales_customer_lookup');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('sale_items_product_revenue');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tenant_active');
        });
    }
};
