<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('login_audit_logs')) {
            Schema::create('login_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('event'); // login, logout, failed_login, pin_login
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->boolean('success')->default(true);
                $table->string('failure_reason')->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(['tenant_id', 'user_id', 'occurred_at']);
                $table->index(['tenant_id', 'event']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_audit_logs');
    }
};
