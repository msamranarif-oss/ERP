<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Core batches table
        if (! Schema::hasTable('batches')) {
            Schema::create('batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
                $table->string('batch_number');
                $table->date('manufacturing_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->decimal('quantity_received', 15, 4)->default(0);
                $table->decimal('quantity_remaining', 15, 4)->default(0);
                $table->decimal('cost_price', 15, 2)->default(0);
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('grn_id')->nullable()->constrained('goods_received_notes')->nullOnDelete();
                $table->foreignId('lot_id')->nullable(); // FK added after lots table exists
                $table->string('status')->default('active'); // active, expired, quarantine, recalled, depleted
                $table->boolean('is_recalled')->default(false);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'batch_number']);
                $table->index(['tenant_id', 'product_id', 'expiry_date']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // Extend stock_levels with batch + bin location
        Schema::table('stock_levels', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_levels', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('warehouse_id')
                    ->constrained('batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_levels', 'bin_location')) {
                $table->string('bin_location')->nullable()->after('batch_id');
            }
            if (! Schema::hasColumn('stock_levels', 'rack')) {
                $table->string('rack')->nullable()->after('bin_location');
            }
            if (! Schema::hasColumn('stock_levels', 'shelf')) {
                $table->string('shelf')->nullable()->after('rack');
            }
        });

        // Extend stock_movements with batch
        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('unit_id')
                    ->constrained('batches')->nullOnDelete();
            }
        });

        // Extend sale_items with batch + serial_number (serial_numbers table created next migration)
        Schema::table('sale_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sale_items', 'batch_id')) {
                $table->foreignId('batch_id')->nullable()->after('variant_id')
                    ->constrained('batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('sale_items', 'serial_number_id')) {
                // Deferred FK — serial_numbers table doesn't exist yet
                $table->unsignedBigInteger('serial_number_id')->nullable()->after('batch_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'serial_number_id']);
        });
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn('batch_id');
        });
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropColumn(['batch_id', 'bin_location', 'rack', 'shelf']);
        });
        Schema::dropIfExists('batches');
    }
};
