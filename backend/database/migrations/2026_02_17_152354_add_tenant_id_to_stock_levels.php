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
        if (!Schema::hasColumn('stock_levels', 'tenant_id')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->foreignId('tenant_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
            });

            // Set default tenant_id for existing records if any
            $firstTenant = DB::table('tenants')->first();
            if ($firstTenant) {
                DB::table('stock_levels')->whereNull('tenant_id')->update(['tenant_id' => $firstTenant->id]);
            }

            Schema::table('stock_levels', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable(false)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stock_levels', 'tenant_id')) {
            Schema::table('stock_levels', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
