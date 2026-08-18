<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_bundles')) {
            Schema::create('product_bundles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->string('pricing_type')->default('fixed'); // fixed, dynamic
                $table->decimal('discount_amount', 15, 2)->nullable();
                $table->decimal('discount_percent', 5, 2)->nullable();
                $table->date('promo_valid_from')->nullable();
                $table->date('promo_valid_to')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['product_id']);
                $table->index(['tenant_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('bundle_items')) {
            Schema::create('bundle_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_bundle_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('unit_id')->constrained();
                $table->decimal('quantity', 15, 4)->default(1);
                $table->boolean('is_optional')->default(false);
                $table->timestamps();

                $table->index(['product_bundle_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('product_bundles');
    }
};
