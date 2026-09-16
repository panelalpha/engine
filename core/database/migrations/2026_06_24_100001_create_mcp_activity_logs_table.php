<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMcpActivityLogsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('token_id')->nullable()->index();
            $table->string('token_name');
            $table->string('tool_name');
            $table->json('input')->nullable();
            $table->enum('status', ['success', 'error']);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('token_id')->references('id')->on('personal_access_tokens')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_activity_logs');
    }
}
