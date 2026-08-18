<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tax_rates');
        if (! Schema::hasTable('tax_rates')) {
            Schema::create('tax_rates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->string('description')->nullable();
                $table->string('code')->nullable();
                $table->decimal('rate', 8, 4);
                $table->string('type')->default('percentage'); // percentage, fixed
                $table->foreignId('sales_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->foreignId('purchase_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index(['tenant_id', 'is_active']);

                $table->unique(['tenant_id', 'code']);
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
