<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add proper indexes to stock_movements table for performance
        Schema::table('stock_movements', function (Blueprint $table) {
            // Add proper indexes for performance
            $table->index(['product_id', 'warehouse_id']);
            $table->index(['created_at']);
            $table->index(['type']);
        });

        // Add missing indexes to improve query performance
        Schema::table('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'sku']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->index(['tenant_id', 'name']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->index(['tenant_id', 'name']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->index(['tenant_id', 'name']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'name']);
            $table->index(['tenant_id', 'email']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->index(['tenant_id', 'name']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['product_id', 'sale_id']);
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->index(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['product_id_warehouse_id_index']);
            $table->dropIndex(['created_at_index']);
            $table->dropIndex(['type_index']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id_name_index']);
            $table->dropIndex(['tenant_id_sku_index']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['tenant_id_name_index']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropIndex(['tenant_id_name_index']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id_name_index']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id_name_index']);
            $table->dropIndex(['tenant_id_email_index']);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['tenant_id_name_index']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex(['product_id_sale_id_index']);
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropIndex(['product_id_warehouse_id_index']);
        });
    }
};