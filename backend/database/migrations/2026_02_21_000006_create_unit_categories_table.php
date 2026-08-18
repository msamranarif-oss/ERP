<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unit_categories')) {
            Schema::create('unit_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index(['tenant_id', 'is_active']);
            });
        }

        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'unit_category_id')) {
                $table->unsignedBigInteger('unit_category_id')->nullable()->after('tenant_id');
                $table->foreign('unit_category_id')->references('id')->on('unit_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('units', 'symbol')) {
                $table->string('symbol', 20)->nullable()->after('abbreviation');
            }
            if (! Schema::hasColumn('units', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['unit_category_id']);
            $table->dropColumn(['unit_category_id', 'symbol', 'is_system']);
        });
        Schema::dropIfExists('unit_categories');
    }
};
