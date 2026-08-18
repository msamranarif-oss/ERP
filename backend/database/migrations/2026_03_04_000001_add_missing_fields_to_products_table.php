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
        //  Schema::table('products', function (Blueprint $table) {
        //     // Add brand relationship
        //     $table->foreignId('brand_id')->nullable()->after('category_id')->constrained()->nullOnDelete();

        //     // Add additional description fields
        //     $table->text('short_description')->nullable()->after('description');
        //     $table->text('internal_notes')->nullable()->after('short_description');

        //     // Add additional pricing fields
        //     $table->decimal('wholesale_price', 15, 2)->nullable()->after('min_price');
        //     $table->decimal('max_price', 15, 2)->nullable()->after('wholesale_price');

        //     // Add order quantity constraints
        //     $table->integer('min_order_qty')->default(1)->after('reorder_quantity');
        //     $table->integer('max_order_qty')->nullable()->after('min_order_qty');

        //     // Add visibility and feature flags
        //     $table->boolean('is_featured')->default(false)->after('is_active');
        //     $table->boolean('is_pos_visible')->default(true)->after('is_featured');
        //     $table->boolean('is_online_visible')->default(false)->after('is_pos_visible');

        //     // Add tracking features
        //     $table->boolean('batch_tracking')->default(false)->after('allow_negative_stock');
        //     $table->boolean('serial_tracking')->default(false)->after('batch_tracking');
        //     $table->boolean('lot_tracking')->default(false)->after('serial_tracking');

        //     // Add product classification
        //     $table->string('product_type')->default('simple')->after('status');
        //     $table->string('status')->default('active')->after('product_type');
        //     $table->string('valuation_method')->default('avg_cost')->after('status');

        //     // Add physical dimensions
        //     $table->decimal('weight', 15, 3)->nullable()->after('tags');
        //     $table->decimal('length', 15, 3)->nullable()->after('weight');
        //     $table->decimal('width', 15, 3)->nullable()->after('length');
        //     $table->decimal('height', 15, 3)->nullable()->after('width');

        //     // Add warranty information
        //     $table->string('warranty_period')->nullable()->after('height');
        //     $table->text('warranty_terms')->nullable()->after('warranty_period');

        //     // Add indexes for better query performance
        //     $table->index(['tenant_id', 'brand_id']);
        //     $table->index(['tenant_id', 'product_type']);
        //     $table->index(['tenant_id', 'status']);
        //     $table->index(['tenant_id', 'is_featured']);
        //     $table->index(['tenant_id', 'is_pos_visible']);
        //     $table->index(['tenant_id', 'is_online_visible']);
        // });

        $addColumn = function (string $column, callable $definer) {
            if (Schema::hasColumn('products', $column)) {
                return;
            }
            try {
                Schema::table('products', function (Blueprint $table) use ($definer) {
                    $definer($table);
                });
            } catch (\Throwable $e) {
            }
        };

        $addIndex = function ($columns, ?string $name = null) {
            try {
                Schema::table('products', function (Blueprint $table) use ($columns, $name) {
                    if ($name !== null) {
                        $table->index($columns, $name);
                    } else {
                        $table->index($columns);
                    }
                });
            } catch (\Throwable $e) {
            }
        };

        $hasFk = false;
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['brand_id']);
            });
        } catch (\Throwable $e) {
            $hasFk = Schema::hasColumn('products', 'brand_id');
        }
        if (! Schema::hasColumn('products', 'brand_id')) {
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->foreignId('brand_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
                });
            } catch (\Throwable $e) {
                try {
                    Schema::table('products', function (Blueprint $table) {
                        $table->foreignId('brand_id')->nullable()->after('category_id');
                    });
                } catch (\Throwable $e2) {
                }
            }
        }

        $addColumn('short_description', fn (Blueprint $t) => $t->text('short_description')->nullable()->after('description'));
        $addColumn('internal_notes', fn (Blueprint $t) => $t->text('internal_notes')->nullable()->after('short_description'));
        $addColumn('wholesale_price', fn (Blueprint $t) => $t->decimal('wholesale_price', 15, 2)->nullable()->after('min_price'));
        $addColumn('max_price', fn (Blueprint $t) => $t->decimal('max_price', 15, 2)->nullable()->after('wholesale_price'));
        $addColumn('min_order_qty', fn (Blueprint $t) => $t->integer('min_order_qty')->default(1)->after('reorder_quantity'));
        $addColumn('max_order_qty', fn (Blueprint $t) => $t->integer('max_order_qty')->nullable()->after('min_order_qty'));
        $addColumn('is_featured', fn (Blueprint $t) => $t->boolean('is_featured')->default(false)->after('is_active'));
        $addColumn('is_pos_visible', fn (Blueprint $t) => $t->boolean('is_pos_visible')->default(true)->after('is_featured'));
        $addColumn('is_online_visible', fn (Blueprint $t) => $t->boolean('is_online_visible')->default(false)->after('is_pos_visible'));
        $addColumn('batch_tracking', fn (Blueprint $t) => $t->boolean('batch_tracking')->default(false)->after('allow_negative_stock'));
        $addColumn('serial_tracking', fn (Blueprint $t) => $t->boolean('serial_tracking')->default(false)->after('batch_tracking'));
        $addColumn('lot_tracking', fn (Blueprint $t) => $t->boolean('lot_tracking')->default(false)->after('serial_tracking'));
        $addColumn('product_type', fn (Blueprint $t) => $t->string('product_type')->default('simple')->after('status'));
        $addColumn('status', fn (Blueprint $t) => $t->string('status')->default('active')->after('product_type'));
        $addColumn('valuation_method', fn (Blueprint $t) => $t->string('valuation_method')->default('avg_cost')->after('status'));
        $addColumn('weight', fn (Blueprint $t) => $t->decimal('weight', 15, 3)->nullable()->after('tags'));
        $addColumn('length', fn (Blueprint $t) => $t->decimal('length', 15, 3)->nullable()->after('weight'));
        $addColumn('width', fn (Blueprint $t) => $t->decimal('width', 15, 3)->nullable()->after('length'));
        $addColumn('height', fn (Blueprint $t) => $t->decimal('height', 15, 3)->nullable()->after('width'));
        $addColumn('warranty_period', fn (Blueprint $t) => $t->string('warranty_period')->nullable()->after('height'));
        $addColumn('warranty_terms', fn (Blueprint $t) => $t->text('warranty_terms')->nullable()->after('warranty_period'));

        $addIndex(['tenant_id', 'brand_id']);
        $addIndex(['tenant_id', 'product_type']);
        $addIndex(['tenant_id', 'status']);
        $addIndex(['tenant_id', 'is_featured']);
        $addIndex(['tenant_id', 'is_pos_visible']);
        $addIndex(['tenant_id', 'is_online_visible']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop all added columns
            $table->dropForeign(['brand_id']);
            $table->dropColumn([
                'brand_id',
                'short_description',
                'internal_notes',
                'wholesale_price',
                'max_price',
                'min_order_qty',
                'max_order_qty',
                'is_featured',
                'is_pos_visible',
                'is_online_visible',
                'batch_tracking',
                'serial_tracking',
                'lot_tracking',
                'product_type',
                'status',
                'valuation_method',
                'weight',
                'length',
                'width',
                'height',
                'warranty_period',
                'warranty_terms',
            ]);
        });
    }
};
