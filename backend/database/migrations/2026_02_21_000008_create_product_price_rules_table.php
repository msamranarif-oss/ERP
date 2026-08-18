<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_price_rules')) {
            Schema::create('product_price_rules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->string('customer_type', 30)->nullable(); // retail, wholesale, vip, dealer
                $table->decimal('price', 15, 2);
                $table->date('valid_from')->nullable();
                $table->date('valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
                $table->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
                $table->index(['tenant_id', 'product_id', 'customer_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_rules');
    }
};
