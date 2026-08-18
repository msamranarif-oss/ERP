<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('opening_stock_entries')) {
            Schema::create('opening_stock_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->date('entry_date');
                $table->string('reference')->nullable();
                $table->string('status')->default('draft'); // draft, approved
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'entry_date']);
            });
        }

        if (! Schema::hasTable('opening_stock_items')) {
            Schema::create('opening_stock_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('opening_stock_entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();
                $table->foreignId('unit_id')->constrained();
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['opening_stock_entry_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_stock_items');
        Schema::dropIfExists('opening_stock_entries');
    }
};
