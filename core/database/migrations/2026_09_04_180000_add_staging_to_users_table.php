<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('staging')->nullable()->after('status');
            $table->foreign('staging')->references('id')->on('users')->restrictOnDelete();
            $table->unique('staging');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['staging']);
            $table->dropForeign(['staging']);
            $table->dropColumn('staging');
        });
    }
};
