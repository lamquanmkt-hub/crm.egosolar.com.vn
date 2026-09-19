<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('technical_work_records')) {
            return;
        }

        Schema::create('technical_work_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->date('work_date');
            $table->string('work_type', 100);
            $table->string('priority', 30)->default('normal');
            $table->text('device_info')->nullable();
            $table->text('requirement')->nullable();
            $table->text('technical_note')->nullable();
            $table->longText('assignee_ids')->nullable();
            $table->string('status', 30)->default('planned')->index();
            $table->string('report_title')->nullable();
            $table->date('report_date')->nullable();
            $table->longText('result_text')->nullable();
            $table->text('report_note')->nullable();
            $table->longText('remaining_work')->nullable();
            $table->date('due_date')->nullable();
            $table->longText('completion_report')->nullable();
            $table->longText('plan_files')->nullable();
            $table->longText('report_files')->nullable();
            $table->longText('completion_files')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Không tự xóa để tránh mất hồ sơ Kỹ thuật nếu rollback nhầm trên production.
    }
};
