<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('base_quantity', 15, 4)->nullable()->after('quantity')
                  ->comment('Quantity converted to base unit for inventory tracking');
            $table->decimal('conversion_factor', 15, 6)->default(1)
                  ->after('base_quantity')
                  ->comment('Snapshot of unit conversion factor at time of sale');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['base_quantity', 'conversion_factor']);
        });
    }
};
