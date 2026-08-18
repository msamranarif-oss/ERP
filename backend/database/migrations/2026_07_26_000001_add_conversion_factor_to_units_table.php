<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'conversion_factor')) {
                $table->decimal('conversion_factor', 15, 6)
                    ->default(1)
                    ->after('symbol');
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (Schema::hasColumn('units', 'conversion_factor')) {
                $table->dropColumn('conversion_factor');
            }
        });
    }
};
