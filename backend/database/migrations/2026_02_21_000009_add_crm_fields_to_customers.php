<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'cnic')) {
                $table->string('cnic', 20)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('customers', 'photo')) {
                $table->string('photo')->nullable()->after('cnic');
            }
            if (! Schema::hasColumn('customers', 'customer_group')) {
                $table->string('customer_group', 30)->nullable(); // retail, wholesale, vip, dealer, staff
            }
            if (! Schema::hasColumn('customers', 'is_blacklisted')) {
                $table->boolean('is_blacklisted')->default(false);
            }
            if (! Schema::hasColumn('customers', 'blacklist_reason')) {
                $table->string('blacklist_reason')->nullable();
            }
            if (! Schema::hasColumn('customers', 'blacklisted_at')) {
                $table->date('blacklisted_at')->nullable();
            }
            if (! Schema::hasColumn('customers', 'loyalty_points')) {
                $table->decimal('loyalty_points', 15, 2)->default(0);
            }
        });

        if (! Schema::hasTable('loyalty_rules')) {
            Schema::create('loyalty_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->decimal('spend_amount', 10, 2)->default(100);      // Rs.100 spent
                $table->decimal('points_earned', 10, 2)->default(1);       // = 1 point
                $table->decimal('point_value', 10, 4)->default(1);         // 1 point = Rs.1
                $table->decimal('min_redeem_points', 10, 2)->default(100);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index(['tenant_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['cnic', 'photo', 'customer_group', 'is_blacklisted', 'blacklist_reason', 'blacklisted_at', 'loyalty_points']);
        });
    }
};
