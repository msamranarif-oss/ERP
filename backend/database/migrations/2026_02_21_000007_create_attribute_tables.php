<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attribute_groups')) {
            Schema::create('attribute_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->string('name'); // Color, Size, Material
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->index(['tenant_id']);
            });
        }

        if (! Schema::hasTable('attribute_values')) {
            Schema::create('attribute_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('attribute_group_id');
                $table->string('value'); // Red, Blue | S, M, L, XL
                $table->string('color_code', 10)->nullable(); // For color swatches
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->foreign('attribute_group_id')->references('id')->on('attribute_groups')->cascadeOnDelete();
                $table->index(['attribute_group_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attribute_groups');
    }
};
