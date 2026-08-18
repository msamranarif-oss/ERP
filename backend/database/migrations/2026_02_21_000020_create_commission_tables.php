<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('commission_rules')) {
            Schema::create('commission_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                // null user_id = applies globally to all salespersons
                $table->decimal('rate_percent', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('sale_commissions')) {
            Schema::create('sale_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('sale_amount', 15, 2);
                $table->decimal('commission_rate', 5, 2);
                $table->decimal('commission_amount', 15, 2);
                $table->string('status')->default('pending'); // pending, paid
                $table->timestamps();

                $table->index(['tenant_id', 'user_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_commissions');
        Schema::dropIfExists('commission_rules');
    }
};
