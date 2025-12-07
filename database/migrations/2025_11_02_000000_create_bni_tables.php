<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bni_payment_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16)->index();

            $table->string('client_id', 100);
            $table->string('tenant_id', 100)->nullable()->comment('Identifier for tenant, used in multi-tenant architecture');
            $table->string('reff_id', 100)->nullable()->comment('Reference ID from related transaction, e.g. sales or order');

            $table->string('customer_no', 100)->nullable();
            $table->string('customer_name', 100)->nullable();
            $table->string('invoice_no', 100)->nullable();
            $table->text('qris_content')->nullable()->comment('QRIS string payload provided by BNI');
            $table->string('va_number', 50)->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status', 8)->nullable()->index();

            $table->string('external_id', 100)->nullable()->comment('Unique internal request ID');

            $table->dateTime('expired_at')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->string('ip', 64)->nullable();

            $table->timestamps();

            $table->index(['tenant_id']);
            $table->index(['reff_id']);
            $table->index(['invoice_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bni_api_calls');
    }
};
