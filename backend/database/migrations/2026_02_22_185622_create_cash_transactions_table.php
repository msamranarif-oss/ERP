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
        if (! Schema::hasTable('cash_transactions')) {
            Schema::create('cash_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('register_session_id')->constrained()->cascadeOnDelete();
                $table->enum('type', ['in', 'out']);
                $table->decimal('amount', 15, 2);
                $table->string('reason')->nullable();
                $table->foreignId('transacted_by')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'register_session_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
