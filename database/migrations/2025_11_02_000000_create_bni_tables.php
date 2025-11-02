<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bni_api_calls', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16)->index();
            $table->string('endpoint', 191);
            $table->string('method', 10);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->string('bni_status', 8)->nullable()->index();
            $table->string('bni_code', 8)->nullable();
            $table->string('ip', 64)->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->uuid('correlation_id')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bni_api_calls');
    }
};
