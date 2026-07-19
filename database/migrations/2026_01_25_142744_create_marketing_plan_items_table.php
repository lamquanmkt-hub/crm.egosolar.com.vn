<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_plan_items', function (Blueprint $table) {
            $table->id();

            // FK -> marketing_plans.id
            $table->foreignId('marketing_plan_id')
                ->constrained('marketing_plans')
                ->cascadeOnDelete();

            // ads | seo | seeding
            $table->string('type', 20);

            // Tên hạng mục
            $table->string('title');

            // Nền tảng: Facebook/Google/SEO/Group...
            $table->string('platform')->nullable();

            // Thời gian chạy riêng của hạng mục (có thể null)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Phụ trách (tạm để string cho nhanh, sau này muốn chuẩn thì đổi sang user_id)
            $table->string('owner')->nullable();

            // Ngân sách kế hoạch của hạng mục
            $table->decimal('budget_plan', 14, 2)->default(0);

            // KPI kế hoạch (json): vd {"lead_target":120,"cpl_target":125000}
            $table->json('kpi_plan')->nullable();

            // draft | doing | done
            $table->string('status', 20)->default('draft');

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['marketing_plan_id', 'type']);
            $table->index(['type', 'platform']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_plan_items');
    }
};
