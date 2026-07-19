<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mkt_seo_timeline_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();

            $table->string('phase', 120)->nullable();       // Giai đoạn 1/2/...
            $table->string('stt', 50)->nullable();          // 1.1, 1.2, ...
            $table->string('objective', 255)->nullable();   // Mục tiêu
            $table->text('detail')->nullable();             // Chi tiết
            $table->string('duration', 120)->nullable();    // Thời gian dự kiến
            $table->string('note', 255)->nullable();        // Ghi chú
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('plan_id')
                ->references('id')
                ->on('mkt_plans')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mkt_seo_timeline_tasks');
    }
};