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
        // Add indexes for credit_sales table
        if (Schema::hasTable('credit_sales')) {
            Schema::table('credit_sales', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at']);
                $table->index(['customer_id', 'status']);
                $table->index(['created_at']);
            });
        }

        // Add indexes for installments table
        if (Schema::hasTable('installments')) {
            Schema::table('installments', function (Blueprint $table) {
                $table->index(['credit_sale_id', 'status']);
                $table->index(['due_date', 'status']);
                $table->index(['status']);
            });
        }

        // Add indexes for payments table
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->index(['installment_id']);
                $table->index(['credit_sale_id', 'created_at']);
                $table->index(['payment_method_id']);
            });
        }

        // Composite indexes for common query patterns in transactions are already useful
        // but simple tenant_id + status/is_active are usually in base migrations

        // Add composite indexes for common query patterns
        if (Schema::hasTable('credit_sale_items')) {
            Schema::table('credit_sale_items', function (Blueprint $table) {
                $table->index(['credit_sale_id', 'product_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes for credit_sales table
        if (Schema::hasTable('credit_sales')) {
            Schema::table('credit_sales', function (Blueprint $table) {
                $table->dropIndex(['tenant_id', 'status', 'created_at']);
                $table->dropIndex(['customer_id', 'status']);
                $table->dropIndex(['created_at']);
            });
        }

        // Drop indexes for installments table
        if (Schema::hasTable('installments')) {
            Schema::table('installments', function (Blueprint $table) {
                $table->dropIndex(['credit_sale_id', 'status']);
                $table->dropIndex(['due_date', 'status']);
                $table->dropIndex(['status']);
            });
        }

        // Drop indexes for payments table
        if (Schema::hasTable('payments')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropIndex(['installment_id']);
                $table->dropIndex(['credit_sale_id', 'created_at']);
                $table->dropIndex(['payment_method_id']);
            });
        }

        // Drop composite indexes

        // Drop indexes for credit_sale_items table
        if (Schema::hasTable('credit_sale_items')) {
            Schema::table('credit_sale_items', function (Blueprint $table) {
                $table->dropIndex(['credit_sale_id', 'product_id']);
            });
        }
    }
};
