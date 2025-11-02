<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bni_billings', function (Blueprint $table) {
            $table->id();
            $table->string('trx_id')->unique();
            $table->string('virtual_account')->nullable();
            $table->string('trx_amount')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('billing_type')->nullable();
            $table->string('description')->nullable();
            $table->string('va_status')->nullable();
            $table->string('payment_amount')->nullable();
            $table->string('payment_ntb')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('last_inquiry_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bni_billings');
    }
};
