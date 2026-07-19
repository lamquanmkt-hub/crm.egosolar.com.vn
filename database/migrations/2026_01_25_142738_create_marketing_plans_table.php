<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_plans', function (Blueprint $table) {
            $table->id();

            // Tên kế hoạch: VD "Kế hoạch Marketing - T01/2026"
            $table->string('name');

            // Thời gian kế hoạch (toàn kỳ)
            $table->date('start_date');
            $table->date('end_date');

            // draft | active | done
            $table->string('status', 20)->default('draft');

            // Tuỳ chọn: mục tiêu chung + note
            $table->text('objective')->nullable();
            $table->text('note')->nullable();

            // Tuỳ chọn: lưu tổng ngân sách plan (có thể tính từ items cũng được)
            $table->decimal('budget_plan_total', 14, 2)->nullable();

            $table->timestamps();

            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_plans');
    }
};
