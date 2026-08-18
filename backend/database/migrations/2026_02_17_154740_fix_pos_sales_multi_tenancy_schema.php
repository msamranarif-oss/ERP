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
        $tables = [
            'register_sessions',
            'sale_items',
            'sale_payments',
            'sale_return_items',
            'installment_schedules',
            'installment_payments',
            'payment_reminders',
            'credit_customers',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'tenant_id')) {
                    $table->foreignId('tenant_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
                }
                
                if (!Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->softDeletes();
                }
            });

            // Set default tenant_id for existing records
            $firstTenant = Illuminate\Support\Facades\DB::table('tenants')->first();
            if ($firstTenant) {
                Illuminate\Support\Facades\DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $firstTenant->id]);
            }

            // Make it non-nullable after populating data
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'register_sessions',
            'sale_items',
            'sale_payments',
            'sale_return_items',
            'installment_schedules',
            'installment_payments',
            'payment_reminders',
            'credit_customers',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'tenant_id')) {
                    // Drop foreign key first. Laravel usually names it table_column_foreign
                    try {
                        $table->dropForeign($tableName . '_tenant_id_foreign');
                    } catch (\Exception $e) {
                        // Ignore if foreign key doesn't exist or is named differently
                    }
                    $table->dropColumn('tenant_id');
                }
                
                if (Schema::hasColumn($tableName, 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
