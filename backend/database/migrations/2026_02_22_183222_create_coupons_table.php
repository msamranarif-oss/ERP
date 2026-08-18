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
        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('code')->index();
                $table->string('description')->nullable();
                $table->enum('type', ['percentage', 'fixed'])->default('fixed');
                $table->decimal('value', 15, 2);
                $table->decimal('min_purchase_amount', 15, 2)->default(0);
                $table->decimal('max_discount_amount', 15, 2)->nullable(); // For percentage types
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->integer('usage_limit')->nullable();
                $table->integer('used_count')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
