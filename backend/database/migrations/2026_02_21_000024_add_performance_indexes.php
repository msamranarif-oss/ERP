<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Safe index creation — wraps each block individually to avoid aborting on duplicates
        $this->safeIndex('products', function (Blueprint $table) {
            $table->index(['tenant_id', 'name'],     'products_tenant_name_idx');
            $table->index(['tenant_id', 'tax_type'],  'products_tenant_taxtype_idx');
        });

        $this->safeIndex('sale_items', function (Blueprint $table) {
            $table->index(['sale_id', 'product_id'], 'sale_items_sale_product_idx');
            $table->index(['product_id', 'created_at'], 'sale_items_product_date_idx');
        });

        $this->safeIndex('journal_entry_lines', function (Blueprint $table) {
            $table->index(['account_id', 'created_at'], 'jel_account_date_idx');
        });

        $this->safeIndex('installments', function (Blueprint $table) {
            $table->index(['tenant_id', 'due_date', 'status'], 'installments_aging_idx');
            $table->index(['credit_sale_id', 'status'],         'installments_credit_status_idx');
        });

        $this->safeIndex('stock_movements', function (Blueprint $table) {
            $table->index(['tenant_id', 'product_id', 'created_at'], 'sm_tenant_product_date_idx');
        });

        $this->safeIndex('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'sales_tenant_date_idx');
            $table->index(['tenant_id', 'status'],     'sales_tenant_status_idx');
        });

        $this->safeIndex('purchase_orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at'], 'po_tenant_date_idx');
            $table->index(['tenant_id', 'supplier_id'], 'po_tenant_supplier_idx');
        });
    }

    public function down(): void
    {
        $drops = [
            'purchase_orders'     => ['po_tenant_date_idx', 'po_tenant_supplier_idx'],
            'sales'               => ['sales_tenant_date_idx', 'sales_tenant_status_idx'],
            'stock_movements'     => ['sm_tenant_product_date_idx'],
            'installments'        => ['installments_aging_idx', 'installments_credit_status_idx'],
            'journal_entry_lines' => ['jel_account_date_idx'],
            'sale_items'          => ['sale_items_sale_product_idx', 'sale_items_product_date_idx'],
            'products'            => ['products_tenant_name_idx', 'products_tenant_taxtype_idx'],
        ];

        foreach ($drops as $tbl => $idxNames) {
            foreach ($idxNames as $idx) {
                try {
                    Schema::table($tbl, fn($t) => $t->dropIndex($idx));
                } catch (\Exception $e) {
                    // Ignore if index doesn't exist
                }
            }
        }
    }

    private function safeIndex(string $table, callable $callback): void
    {
        try {
            Schema::table($table, $callback);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Index migration skipped for {$table}: " . $e->getMessage());
        }
    }
};
