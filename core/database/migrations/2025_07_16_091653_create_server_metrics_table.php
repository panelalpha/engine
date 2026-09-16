<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table) {
            $table->timestamp('timestamp');
            $table->float('cpu_percent');
            $table->float('cpu_load_avg_1');
            $table->float('cpu_load_avg_5');
            $table->float('cpu_load_avg_15');
            $table->float('ram_percent');
            $table->float('swap_percent');
            $table->unsignedBigInteger('disk_read_bps');
            $table->unsignedBigInteger('disk_write_bps');
            $table->unsignedBigInteger('disk_read_iops');
            $table->unsignedBigInteger('disk_write_iops');
            $table->unsignedBigInteger('net_in_bps');
            $table->unsignedBigInteger('net_out_bps');
            $table->unsignedBigInteger('net_in_pps');
            $table->unsignedBigInteger('net_out_pps');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
