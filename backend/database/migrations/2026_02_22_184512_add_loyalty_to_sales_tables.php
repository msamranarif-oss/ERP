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
            $table->decimal('points_redeemed', 15, 2)->default(0)->after('coupon_discount_amount');
            $table->decimal('loyalty_discount_amount', 15, 2)->default(0)->after('points_redeemed');
        });

        Schema::table('held_sales', function (Blueprint $table) {
            $table->decimal('points_redeemed', 15, 2)->default(0)->after('coupon_discount_amount');
            $table->decimal('loyalty_discount_amount', 15, 2)->default(0)->after('points_redeemed');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['points_redeemed', 'loyalty_discount_amount']);
        });

        Schema::table('held_sales', function (Blueprint $table) {
            $table->dropColumn(['points_redeemed', 'loyalty_discount_amount']);
        });
    }
};
