<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mysql_sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_id')->index();
            $table->string('token');
            $table->json('details')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mysql_sso_tokens');
    }
};
