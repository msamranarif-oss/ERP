<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PIN login support for POS cashiers
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'pin')) {
                $table->string('pin', 64)->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'has_pos_access')) {
                $table->boolean('has_pos_access')->default(false)->after('pin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pin', 'has_pos_access']);
        });
    }
};
