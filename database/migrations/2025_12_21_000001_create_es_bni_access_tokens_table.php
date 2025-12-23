<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bni_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('client_id', 255)->nullable()->index();
            $table->string('token', 255)->nullable()->unique();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bni_access_tokens');
    }
};
