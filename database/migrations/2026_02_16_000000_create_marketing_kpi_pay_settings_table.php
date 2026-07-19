<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_kpi_pay_settings', function (Blueprint $table) {
            $table->id();

            // Kỳ áp dụng: YYYY-MM (vd: 2026-02)
            $table->string('period', 7)->index();

            // Nhân viên
            $table->unsignedBigInteger('user_id')->index();

            // Lương do marketing_manager nhập (đơn vị VND)
            $table->unsignedBigInteger('base_salary')->default(0);      // lương cơ bản
            $table->unsignedBigInteger('kpi_salary_pool')->default(0);  // quỹ lương KPI

            // Target KPI theo từng người
            $table->unsignedInteger('target_post')->default(0);
            $table->unsignedInteger('target_video_ai')->default(0);
            $table->unsignedInteger('target_video_review')->default(0);

            $table->timestamps();

            // 1 người chỉ có 1 cấu hình / 1 tháng
            $table->unique(['period', 'user_id'], 'uniq_period_user');

            // Nếu bạn muốn ràng buộc khoá ngoại (có thể bật sau)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_kpi_pay_settings');
    }
};
