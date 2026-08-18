<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SMS gateway per tenant
        if (! Schema::hasTable('sms_settings')) {
            Schema::create('sms_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('gateway')->default('twilio'); // twilio, africas_talking, vonage
                $table->string('api_key')->nullable();
                $table->string('api_secret')->nullable();
                $table->string('sender_id')->nullable();
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        // SMTP email settings per tenant
        if (! Schema::hasTable('email_settings')) {
            Schema::create('email_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('driver')->default('smtp');
                $table->string('host')->nullable();
                $table->integer('port')->default(587);
                $table->string('username')->nullable();
                $table->string('password')->nullable();
                $table->string('encryption')->nullable(); // tls, ssl
                $table->string('from_address')->nullable();
                $table->string('from_name')->nullable();
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        // Barcode/label templates
        if (! Schema::hasTable('label_templates')) {
            Schema::create('label_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('size')->default('58mm'); // 58mm, 80mm, a4
                $table->json('fields'); // ["product_name","barcode","price","expiry","company_logo"]
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
        }

        // Installment plan templates
        if (! Schema::hasTable('installment_plan_templates')) {
            Schema::create('installment_plan_templates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->string('name'); // "12-Month Monthly Plan"
                $table->string('frequency'); // weekly, biweekly, monthly, quarterly
                $table->integer('term');    // number of installments
                $table->decimal('interest_rate', 5, 2)->default(0);
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plan_templates');
        Schema::dropIfExists('label_templates');
        Schema::dropIfExists('email_settings');
        Schema::dropIfExists('sms_settings');
    }
};
