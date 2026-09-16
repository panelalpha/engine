<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipv4_nat_maps', function (Blueprint $table) {
            $table->id();
            $table->string('local_ip')->unique();
            $table->string('public_ip')->unique();
            $table->timestamps();

            $table->index('local_ip');
            $table->index('public_ip');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipv4_nat_maps');
    }
};
