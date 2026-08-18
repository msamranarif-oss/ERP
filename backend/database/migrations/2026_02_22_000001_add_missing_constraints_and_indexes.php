<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function hasFk($table, $key)
    {
        return count(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?", 
            [$table, $key]
        )) > 0;
    }

    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'batch_id') && !$this->hasFk('sale_items', 'sale_items_batch_id_foreign')) {
                $table->foreign('batch_id')->references('id')->on('batches')->nullOnDelete();
            }
            if (Schema::hasColumn('sale_items', 'serial_number_id') && !$this->hasFk('sale_items', 'sale_items_serial_number_id_foreign')) {
                $table->foreign('serial_number_id')->references('id')->on('serial_numbers')->nullOnDelete();
            }
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_payments', 'tenant_id')) {
                $table->foreignId('tenant_id')->after('sale_id')->constrained()->cascadeOnDelete();
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->change();
            $table->decimal('balance_due', 15, 2)->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 4)->change();
            $table->decimal('unit_price', 15, 2)->change();
            $table->decimal('total', 15, 2)->change();
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'cost_price')) $table->decimal('cost_price', 15, 2)->change();
            if (Schema::hasColumn('products', 'selling_price')) $table->decimal('selling_price', 15, 2)->change();
            if (Schema::hasColumn('products', 'reorder_level')) $table->integer('reorder_level')->change();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            // we catch exceptions if index exists
        });

        try { Schema::table('sale_items', function (Blueprint $table) { $table->index(['sale_id', 'product_id']); }); } catch (\Exception $e) {}
        try { Schema::table('sale_items', function (Blueprint $table) { $table->index(['product_id', 'created_at']); }); } catch (\Exception $e) {}

        try { Schema::table('sale_payments', function (Blueprint $table) { $table->index(['sale_id', 'payment_method_id']); }); } catch (\Exception $e) {}
        try { Schema::table('sale_payments', function (Blueprint $table) { $table->index(['payment_method_id', 'created_at']); }); } catch (\Exception $e) {}
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            try { $table->dropIndex(['sale_id', 'product_id']); } catch (\Exception $e) {}
            try { $table->dropIndex(['product_id', 'created_at']); } catch (\Exception $e) {}
            if ($this->hasFk('sale_items', 'sale_items_batch_id_foreign')) $table->dropForeign(['batch_id']);
            if ($this->hasFk('sale_items', 'sale_items_serial_number_id_foreign')) $table->dropForeign(['serial_number_id']);
        });

        Schema::table('sale_payments', function (Blueprint $table) {
            try { $table->dropIndex(['sale_id', 'payment_method_id']); } catch (\Exception $e) {}
            try { $table->dropIndex(['payment_method_id', 'created_at']); } catch (\Exception $e) {}
            if (Schema::hasColumn('sale_payments', 'tenant_id')) {
                try { $table->dropForeign(['tenant_id']); } catch (\Exception $e) {}
                $table->dropColumn('tenant_id');
            }
        });
    }
};