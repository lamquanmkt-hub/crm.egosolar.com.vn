<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sự cố văn phòng (quy trình 4 bước trong tài liệu KPI HCNS) — chỉ THÊM bảng mới:
 *   Tiếp nhận → Phân loại mức độ & duyệt chi (> ngưỡng cần Giám đốc) → Xử lý → Nghiệm thu, chấm sao & đóng.
 * Số vụ xử lý đúng hạn (SLA) là số liệu cho KPI HCNS "Quản lý tài sản & văn phòng phẩm".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_office_incidents')) {
            Schema::create('hr_office_incidents', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 30)->unique();
                $table->unsignedBigInteger('reporter_id')->index();
                $table->string('location', 255)->comment('Phòng / vị trí');
                $table->text('description');
                $table->string('status', 30)->default('new')->index()
                    ->comment('new|waiting_approval|in_progress|waiting_acceptance|closed|cancelled');

                // Bước 2: phân loại & duyệt chi
                $table->unsignedTinyInteger('level')->nullable()->comment('1 khẩn cấp | 2 bình thường | 3 thấp');
                $table->unsignedBigInteger('classified_by')->nullable();
                $table->timestamp('classified_at')->nullable();
                $table->timestamp('due_at')->nullable()->comment('Hạn xử lý theo SLA của mức độ');
                $table->string('handler_type', 20)->nullable()->comment('internal|vendor');
                $table->string('vendor_name', 255)->nullable();
                $table->decimal('estimated_cost', 15, 2)->default(0);
                $table->string('cost_approval', 20)->nullable()->comment('null = không cần | pending | approved | rejected');
                $table->unsignedBigInteger('cost_approved_by')->nullable();
                $table->timestamp('cost_approved_at')->nullable();
                $table->string('cost_approval_note', 1000)->nullable();

                // Bước 3: xử lý
                $table->decimal('actual_cost', 15, 2)->nullable();
                $table->timestamp('resolved_at')->nullable()->comment('Lần HCNS báo đã xử lý xong gần nhất');
                $table->text('result_note')->nullable();
                $table->unsignedInteger('reopen_count')->default(0);

                // Bước 4: nghiệm thu & đóng
                $table->timestamp('accepted_at')->nullable();
                $table->unsignedBigInteger('accepted_by')->nullable();
                $table->unsignedTinyInteger('rating')->nullable()->comment('1 – 5 sao');
                $table->string('feedback', 1000)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->boolean('on_time')->nullable()->comment('resolved_at <= due_at, chốt khi đóng');

                $table->timestamps();
                $table->index(['status', 'due_at'], 'hr_office_incidents_status_due_idx');
            });
        }

        if (! Schema::hasTable('hr_office_incident_files')) {
            Schema::create('hr_office_incident_files', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('incident_id')->index();
                $table->string('stage', 20)->comment('report = ảnh lúc báo | result = ảnh sau khi sửa');
                $table->string('disk', 30)->default('local');
                $table->string('file_path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_office_incident_logs')) {
            Schema::create('hr_office_incident_logs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('incident_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 40);
                $table->string('note', 1000)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_office_incident_logs');
        Schema::dropIfExists('hr_office_incident_files');
        Schema::dropIfExists('hr_office_incidents');
    }
};
