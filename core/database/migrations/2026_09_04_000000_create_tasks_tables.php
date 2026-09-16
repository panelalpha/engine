<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTables extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', static function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable()->index();
            $table->string('job_id')->nullable()->unique();
            $table->string('queue');
            $table->string('job_type');
            $table->string('status', 32);
            $table->json('details')->nullable();
            $table->unsignedInteger('pid')->nullable();
            $table->string('pid_start_time', 32)->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('task_logs', static function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->text('log');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->index(['task_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_logs');
        Schema::dropIfExists('tasks');
    }
}
