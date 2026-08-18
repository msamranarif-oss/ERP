<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quotations')) {
            Schema::create('quotations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
                $table->string('quotation_number');
                $table->date('quotation_date');
                $table->date('valid_until')->nullable();
                $table->string('status')->default('draft'); // draft, sent, accepted, rejected, expired
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('discount_amount', 15, 2)->default(0);
                $table->decimal('tax_amount', 15, 2)->default(0);
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->constrained('users');
                $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete(); // populated on conversion
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'quotation_number']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('quotation_items')) {
            Schema::create('quotation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
                $table->foreignId('unit_id')->constrained();
                $table->decimal('quantity', 15, 4);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->decimal('total', 15, 2);
                $table->timestamps();

                $table->index(['quotation_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
