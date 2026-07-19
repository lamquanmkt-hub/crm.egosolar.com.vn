<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_calendar_weekly_metrics', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('content_calendar_id');
            $table->date('week_start'); // Thứ 2 của tuần
            $table->date('week_end');   // Chủ nhật của tuần

            // Số liệu cơ bản (bạn có thể mở rộng thêm sau)
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);

            // tuỳ bạn: nếu muốn tracking leads theo post/video
            $table->unsignedInteger('leads')->default(0);

            $table->text('note')->nullable();
            $table->unsignedBigInteger('entered_by')->nullable();

            $table->timestamps();

            $table->unique(['content_calendar_id', 'week_start'], 'ccwm_ccid_week_uq');
            $table->index(['week_start', 'week_end']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_calendar_weekly_metrics');
    }
};