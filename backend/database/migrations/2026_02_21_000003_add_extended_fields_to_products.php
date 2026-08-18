<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----- products -----
        Schema::table('products', function (Blueprint $table) {
            // Only add columns that don't already exist
            if (!Schema::hasColumn('products', 'brand_id')) {
                $table->unsignedBigInteger('brand_id')->nullable()->after('category_id');
                $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'product_type')) {
                $table->string('product_type', 30)->default('simple')->after('has_variants');
            }
            if (!Schema::hasColumn('products', 'short_description')) {
                $table->string('short_description')->nullable()->after('description');
            }
            if (!Schema::hasColumn('products', 'tags')) {
                $table->json('tags')->nullable()->after('attributes');
            }
            if (!Schema::hasColumn('products', 'status')) {
                $table->string('status', 20)->default('active')->after('is_active');
            }
            if (!Schema::hasColumn('products', 'min_order_qty')) {
                $table->unsignedInteger('min_order_qty')->default(1)->after('reorder_quantity');
            }
            if (!Schema::hasColumn('products', 'max_order_qty')) {
                $table->unsignedInteger('max_order_qty')->nullable()->after('min_order_qty');
            }
            if (!Schema::hasColumn('products', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('products', 'is_pos_visible')) {
                $table->boolean('is_pos_visible')->default(true)->after('is_featured');
            }
            if (!Schema::hasColumn('products', 'is_online_visible')) {
                $table->boolean('is_online_visible')->default(false)->after('is_pos_visible');
            }
            if (!Schema::hasColumn('products', 'batch_tracking')) {
                $table->boolean('batch_tracking')->default(false)->after('track_inventory');
            }
            if (!Schema::hasColumn('products', 'serial_tracking')) {
                $table->boolean('serial_tracking')->default(false)->after('batch_tracking');
            }
            if (!Schema::hasColumn('products', 'lot_tracking')) {
                $table->boolean('lot_tracking')->default(false)->after('serial_tracking');
            }
            if (!Schema::hasColumn('products', 'valuation_method')) {
                $table->string('valuation_method', 20)->default('avg_cost')->after('allow_negative_stock');
            }
            if (!Schema::hasColumn('products', 'warranty_period')) {
                $table->string('warranty_period')->nullable();
            }
            if (!Schema::hasColumn('products', 'warranty_terms')) {
                $table->text('warranty_terms')->nullable();
            }
            if (!Schema::hasColumn('products', 'weight')) {
                $table->decimal('weight', 10, 3)->nullable();
            }
            if (!Schema::hasColumn('products', 'length')) {
                $table->decimal('length', 10, 3)->nullable();
            }
            if (!Schema::hasColumn('products', 'width')) {
                $table->decimal('width', 10, 3)->nullable();
            }
            if (!Schema::hasColumn('products', 'height')) {
                $table->decimal('height', 10, 3)->nullable();
            }
            if (!Schema::hasColumn('products', 'internal_notes')) {
                $table->text('internal_notes')->nullable();
            }
            if (!Schema::hasColumn('products', 'wholesale_price')) {
                $table->decimal('wholesale_price', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('products', 'max_price')) {
                $table->decimal('max_price', 15, 2)->nullable();
            }

            $table->index(['tenant_id', 'product_type'], 'products_tenant_type_idx');
            $table->index(['tenant_id', 'status'], 'products_tenant_status_idx');
        });

        // ----- product_units -----
        Schema::table('product_units', function (Blueprint $table) {
            if (!Schema::hasColumn('product_units', 'min_price')) {
                $table->decimal('min_price', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('product_units', 'wholesale_price')) {
                $table->decimal('wholesale_price', 15, 2)->nullable();
            }
        });

        // ----- product_variants -----
        Schema::table('product_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('product_variants', 'min_price')) {
                $table->decimal('min_price', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('product_variants', 'wholesale_price')) {
                $table->decimal('wholesale_price', 15, 2)->nullable();
            }
            if (!Schema::hasColumn('product_variants', 'weight')) {
                $table->decimal('weight', 10, 3)->nullable();
            }
            if (!Schema::hasColumn('product_variants', 'length')) {
                $table->decimal('length', 10, 3)->nullable();
            }
            if (!Schema::hasColumn('product_variants', 'width')) {
                $table->decimal('width', 10, 3)->nullable();
            }
            if (!Schema::hasColumn('product_variants', 'height')) {
                $table->decimal('height', 10, 3)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_type_idx');
            $table->dropIndex('products_tenant_status_idx');
            $table->dropForeign(['brand_id']);
            $table->dropColumn([
                'brand_id','product_type','short_description','tags','status',
                'min_order_qty','max_order_qty','is_featured','is_pos_visible',
                'is_online_visible','batch_tracking','serial_tracking','lot_tracking',
                'valuation_method','warranty_period','warranty_terms',
                'weight','length','width','height','internal_notes',
                'wholesale_price','max_price',
            ]);
        });
    }
};
