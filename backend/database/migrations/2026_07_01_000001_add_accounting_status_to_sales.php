<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('accounting_status', ['pending', 'posted', 'failed'])
                ->default('pending')
                ->after('status');

            $table->string('accounting_failure_reason', 500)
                ->nullable()
                ->after('accounting_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['accounting_status', 'accounting_failure_reason']);
        });
    }
};
