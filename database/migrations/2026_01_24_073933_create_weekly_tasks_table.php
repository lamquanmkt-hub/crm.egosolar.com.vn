<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::create('weekly_tasks', function (Blueprint $table) {
        $table->id();
        $table->string('title');                 // Tên công việc
        $table->enum('priority', ['high','medium','low'])->default('medium');
        $table->string('category')->nullable();  // Hạng mục: Content/Digital/Event...
        $table->string('assignee')->nullable();  // Người phụ trách (đơn giản: text)
        $table->date('start_date')->nullable();
        $table->date('due_date')->nullable();
        $table->unsignedTinyInteger('progress')->default(0); // 0-100
        $table->enum('status', ['pending','doing','done','overdue'])->default('pending');
        $table->text('note')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_tasks');
    }
};
