<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_activity_logs')) {
            return;
        }

        Schema::create('task_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('task_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('action', 100);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->text('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['task_id', 'created_at'], 'task_activity_task_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_activity_logs');
    }
};
