<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Báo cáo ngày của module Kỹ thuật (giai đoạn 1).
 *
 * Vì sao KHÔNG dùng lại `technical_work_records`:
 * - Bảng đó chỉ chứa ĐÚNG MỘT báo cáo cho mỗi kế hoạch và mỗi lần lưu là GHI
 *   ĐÈ (`update` trong TechnicalWorkController::saveReport) — không thể báo cáo
 *   nhiều đầu việc trong một ngày, cũng không giữ được lịch sử.
 * - Bảng đó không có người duyệt / trạng thái duyệt / lịch sử chỉnh sửa.
 * - Nguồn công việc của nó là chính nó, trong khi báo cáo ngày mới phải gắn
 *   được với 3 nguồn công việc thật (quy trình Công trình, task, bảo trì).
 *
 * Bảng cũ được giữ nguyên, không sửa, không xoá dữ liệu.
 *
 * Nguyên tắc ràng buộc:
 * - `user_id` / `approved_by` / `uploaded_by` → FK tới `users` với nullOnDelete,
 *   kèm cột snapshot `user_name` để báo cáo không mất danh tính người viết.
 * - `site_id` và `source_id` KHÔNG đặt FK (nguồn nằm ở nhiều bảng khác nhau,
 *   và công trình có thể bị xoá) — bù lại có index và snapshot tên.
 * - `technical_daily_report_histories.report_id` KHÔNG có FK: lịch sử phải
 *   sống sót kể cả khi báo cáo bị xoá cứng (cùng triết lý với
 *   `payment_request_edit_logs` của đợt P0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technical_daily_reports')) {
            Schema::create('technical_daily_reports', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();

                $table->date('report_date')->index();

                // project_workflow | task | maintenance
                $table->string('source_type', 40)->index();
                $table->unsignedBigInteger('source_id')->nullable()->index();

                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->string('site_name')->nullable();
                $table->string('work_title')->nullable();

                $table->text('content');
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->decimal('work_hours', 5, 2)->nullable();
                $table->text('materials_note')->nullable();
                $table->text('issues_note')->nullable();
                $table->text('next_plan')->nullable();

                // draft | submitted | approved | revision_requested
                $table->string('status', 30)->default('draft')->index();
                $table->dateTime('submitted_at')->nullable();

                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->text('review_note')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'report_date'], 'tdr_user_date_idx');
                $table->index(['source_type', 'source_id', 'report_date'], 'tdr_source_date_idx');
                $table->index(['status', 'report_date'], 'tdr_status_date_idx');
                $table->index(['company_id', 'report_date'], 'tdr_company_date_idx');
            });
        }

        if (! Schema::hasTable('technical_daily_report_files')) {
            Schema::create('technical_daily_report_files', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('report_id')
                    ->constrained('technical_daily_reports')
                    ->cascadeOnDelete();

                $table->string('disk', 40)->default('local');
                $table->string('path', 500);
                $table->string('original_name');
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('size')->default(0);

                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('technical_daily_report_histories')) {
            Schema::create('technical_daily_report_histories', function (Blueprint $table): void {
                $table->id();

                // Cố tình KHÔNG đặt khoá ngoại: lịch sử phải sống lâu hơn báo cáo.
                $table->unsignedBigInteger('report_id')->index();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();

                $table->string('action', 40)->index();
                $table->string('status_before', 30)->nullable();
                $table->string('status_after', 30)->nullable();
                $table->text('note')->nullable();

                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();

                $table->timestamps();

                $table->index(['report_id', 'created_at'], 'tdrh_report_created_idx');
            });
        }
    }

    public function down(): void
    {
        // Thứ tự ngược để không vướng khoá ngoại. Chỉ xoá đúng 3 bảng do
        // migration này tạo ra; không đụng tới technical_work_records.
        Schema::dropIfExists('technical_daily_report_files');
        Schema::dropIfExists('technical_daily_report_histories');
        Schema::dropIfExists('technical_daily_reports');
    }
};
