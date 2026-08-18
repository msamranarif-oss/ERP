<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lots')) {
            Schema::create('lots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
                $table->string('lot_number');
                $table->date('received_date')->nullable();
                $table->decimal('quantity', 15, 4)->default(0);
                $table->string('qc_status')->default('pending'); // pending, passed, rejected
                $table->text('qc_notes')->nullable();
                $table->timestamp('qc_reviewed_at')->nullable();
                $table->foreignId('qc_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('pending'); // pending, available, quarantine, returned
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'lot_number']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // Now we can add the deferred lot_id FK to batches
        Schema::table('batches', function (Blueprint $table) {
            $table->foreign('lot_id')->references('id')->on('lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropForeign(['lot_id']);
        });
        Schema::dropIfExists('lots');
    }
};
