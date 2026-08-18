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
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'idx_sales_tenant_date');
            $table->index(['tenant_id', 'customer_id'], 'idx_sales_tenant_customer');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'product_id'], 'idx_sale_items_tenant_product');
            $table->index(['tenant_id', 'created_at'], 'idx_sale_items_tenant_date');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_tenant_date');
            $table->dropIndex('idx_sales_tenant_customer');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('idx_sale_items_tenant_product');
            $table->dropIndex('idx_sale_items_tenant_date');
        });
    }
};
