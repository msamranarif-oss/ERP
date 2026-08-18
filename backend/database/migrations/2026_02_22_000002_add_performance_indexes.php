<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations to add performance indexes
     */
    public function up(): void
    {
        // Add composite indexes for common query patterns
        Schema::table('sales', function (Blueprint $table) {
            // Index for sales reports by date and branch
            $table->index(['branch_id', 'sale_date', 'status'], 'sales_branch_date_status_index');
            
            // Index for customer sales history
            $table->index(['customer_id', 'sale_date'], 'sales_customer_date_index');
            
            // Index for sales by cashier
            $table->index(['sold_by', 'sale_date'], 'sales_sold_by_date_index');
            
            // Index for payment status queries
            $table->index(['payment_status', 'sale_date'], 'sales_payment_status_date_index');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // Index for product sales analysis
            $table->index(['product_id', 'sale_id'], 'sale_items_product_sale_index');
            
            // Index for variant sales
            $table->index(['variant_id', 'sale_id'], 'sale_items_variant_sale_index');
            
            // Index for batch tracking queries
            $table->index(['batch_id', 'sale_id'], 'sale_items_batch_sale_index');
        });

        Schema::table('products', function (Blueprint $table) {
            // Index for product search by name
            $table->index(['name', 'is_active'], 'products_name_active_index');
            
            // Index for product search by SKU
            $table->index(['sku', 'is_active'], 'products_sku_active_index');
            
            // Index for product search by barcode
            $table->index(['barcode', 'is_active'], 'products_barcode_active_index');
            
            // Index for category-based product queries
            $table->index(['category_id', 'is_active', 'is_sellable'], 'products_category_active_sellable_index');
            
            // Index for brand-based queries
            $table->index(['brand_id', 'is_active'], 'products_brand_active_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            // Index for customer search by name
            $table->index(['name', 'is_active'], 'customers_name_active_index');
            
            // Index for customer search by phone
            $table->index(['phone', 'is_active'], 'customers_phone_active_index');
            
            // Index for customer search by email
            $table->index(['email', 'is_active'], 'customers_email_active_index');
            
            // Index for customer type queries
            $table->index(['customer_type', 'is_active'], 'customers_type_active_index');
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            // Index for inventory queries by warehouse and product
            $table->index(['warehouse_id', 'product_id'], 'stock_levels_warehouse_product_index');
            
            // Index for low stock alerts
            $table->index(['product_id', 'quantity'], 'stock_levels_product_quantity_index');
            
            // Index for batch-specific inventory
            $table->index(['batch_id', 'product_id'], 'stock_levels_batch_product_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            // Index for stock movement history
            $table->index(['product_id', 'created_at'], 'stock_movements_product_date_index');
            
            // Index for warehouse-specific movements
            $table->index(['warehouse_id', 'created_at'], 'stock_movements_warehouse_date_index');
            
            // Index for movement type queries
            $table->index(['type', 'created_at'], 'stock_movements_type_date_index');
        });

        Schema::table('users', function (Blueprint $table) {
            // Index for user search by name
            $table->index(['name', 'is_active'], 'users_name_active_index');
            
            // Index for user search by email
            $table->index(['email', 'is_active'], 'users_email_active_index');
            
            // Index for branch-based user queries
            $table->index(['branch_id', 'is_active'], 'users_branch_active_index');
        });

        Schema::table('register_sessions', function (Blueprint $table) {
            // Index for session status queries
            $table->index(['status', 'opened_at'], 'register_sessions_status_opened_index');
            
            // Index for user-specific sessions
            $table->index(['user_id', 'opened_at'], 'register_sessions_user_date_index');
            
            // Index for cash register sessions
            $table->index(['cash_register_id', 'opened_at'], 'register_sessions_register_date_index');
        });


    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_branch_date_status_index');
            $table->dropIndex('sales_customer_date_index');
            $table->dropIndex('sales_sold_by_date_index');
            $table->dropIndex('sales_payment_status_date_index');
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('sale_items_product_sale_index');
            $table->dropIndex('sale_items_variant_sale_index');
            $table->dropIndex('sale_items_batch_sale_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_name_active_index');
            $table->dropIndex('products_sku_active_index');
            $table->dropIndex('products_barcode_active_index');
            $table->dropIndex('products_category_active_sellable_index');
            $table->dropIndex('products_brand_active_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_name_active_index');
            $table->dropIndex('customers_phone_active_index');
            $table->dropIndex('customers_email_active_index');
            $table->dropIndex('customers_type_active_index');
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropIndex('stock_levels_warehouse_product_index');
            $table->dropIndex('stock_levels_product_quantity_index');
            $table->dropIndex('stock_levels_batch_product_index');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_product_date_index');
            $table->dropIndex('stock_movements_warehouse_date_index');
            $table->dropIndex('stock_movements_type_date_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_name_active_index');
            $table->dropIndex('users_email_active_index');
            $table->dropIndex('users_branch_active_index');
        });

        Schema::table('register_sessions', function (Blueprint $table) {
            $table->dropIndex('register_sessions_status_opened_index');
            $table->dropIndex('register_sessions_user_date_index');
            $table->dropIndex('register_sessions_register_date_index');
        });


    }
};