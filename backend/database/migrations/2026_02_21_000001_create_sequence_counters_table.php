<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sequence_counters')) {
            Schema::create('sequence_counters', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('type', 30);   // sale, payment, grn, po, etc.
                $table->smallInteger('year');
                $table->unsignedBigInteger('current_value')->default(0);
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->unique(['tenant_id', 'type', 'year']);
                $table->index(['tenant_id', 'type', 'year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sequence_counters');
    }
};
