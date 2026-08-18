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
        Schema::table('held_sales', function (Blueprint $table) {
            $table->string('status')->default('held')->after('notes');
            $table->decimal('shipping_cost', 15, 2)->default(0)->after('tax_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('held_sales', function (Blueprint $table) {
            $table->dropColumn(['status', 'shipping_cost']);
        });
    }
};
