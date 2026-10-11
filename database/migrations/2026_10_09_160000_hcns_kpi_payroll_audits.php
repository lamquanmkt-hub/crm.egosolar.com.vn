<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI HCNS HC04 — đối soát độ chính xác bảng lương HR (hr_payroll_periods/lines).
 *
 *  - hr_payroll_audits: một đợt đối soát cho một kỳ lương: phạm vi, HCNS chịu KPI, người nhập, người kiểm.
 *  - hr_payroll_audit_items: từng dòng lương đã kiểm (khoản đã kiểm, kết quả đúng/có lỗi/chưa xác định,
 *    ảnh chụp số tiền lúc kiểm để biết dòng đổi sau đó).
 *  - hr_payroll_audit_errors: lỗi của một dòng (khoản, loại, nội dung, số đúng/số đã ghi, nguồn, cách xử lý);
 *    gỡ lỗi chỉ đánh dấu (giữ lịch sử).
 *
 * Chỉ tạo bảng mới — không sửa bảng lương, không đụng số tiền hay kỳ Excel 08/2026. Xác nhận độc lập dùng
 * hr_kpi_source_reviews, tệp chứng cứ dùng hr_kpi_source_files, lịch sử dùng hr_kpi_source_logs (đã có).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_payroll_audits')) {
            Schema::create('hr_payroll_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('code', 40)->nullable();
                $table->unsignedBigInteger('period_id')->index();
                $table->char('payroll_month', 7)->index();
                $table->string('title', 255);
                $table->string('scope', 20)->default('all');
                $table->string('scope_department', 190)->nullable();
                $table->json('scope_line_ids')->nullable();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->unsignedBigInteger('checker_id')->nullable()->index();
                $table->unsignedBigInteger('reviewer_id')->nullable()->index();
                $table->string('status', 20)->default('open');
                $table->text('note')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_payroll_audit_items')) {
            Schema::create('hr_payroll_audit_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('audit_id')->index();
                $table->unsignedBigInteger('line_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('employee_name', 190)->nullable();
                $table->string('department_name', 190)->nullable();
                $table->json('components')->nullable();
                $table->string('result', 20)->nullable();
                $table->string('line_fingerprint', 64)->nullable();
                $table->json('line_snapshot')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('checked_by')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();
                $table->unique(['audit_id', 'line_id'], 'hr_payroll_audit_items_audit_line_uq');
            });
        }

        if (! Schema::hasTable('hr_payroll_audit_errors')) {
            Schema::create('hr_payroll_audit_errors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('audit_id')->index();
                $table->unsignedBigInteger('item_id')->index();
                $table->string('component', 40);
                $table->string('error_type', 40);
                $table->text('description');
                $table->decimal('expected_amount', 15, 2)->nullable();
                $table->decimal('recorded_amount', 15, 2)->nullable();
                $table->string('source_url', 500)->nullable();
                $table->text('resolution')->nullable();
                $table->string('resolution_status', 20)->default('open');
                $table->timestamp('fixed_at')->nullable();
                $table->unsignedBigInteger('fixed_by')->nullable();
                $table->timestamp('removed_at')->nullable();
                $table->unsignedBigInteger('removed_by')->nullable();
                $table->text('remove_reason')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_payroll_audit_errors');
        Schema::dropIfExists('hr_payroll_audit_items');
        Schema::dropIfExists('hr_payroll_audits');
    }
};
