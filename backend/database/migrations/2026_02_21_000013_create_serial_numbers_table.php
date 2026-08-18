<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('serial_numbers')) {
            Schema::create('serial_numbers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
                $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
                $table->string('serial_number')->index();
                $table->string('imei')->nullable()->index();
                $table->string('status')->default('in_stock'); // in_stock, sold, returned, defective, in_repair, scrapped
                $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->nullOnDelete();
                $table->foreignId('sold_to')->nullable()->constrained('customers')->nullOnDelete();
                $table->timestamp('sold_at')->nullable();
                $table->date('warranty_expiry')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'serial_number']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // Now add the FK constraint for sale_items.serial_number_id
        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreign('serial_number_id')
                ->references('id')->on('serial_numbers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropForeign(['serial_number_id']);
        });
        Schema::dropIfExists('serial_numbers');
    }
};
