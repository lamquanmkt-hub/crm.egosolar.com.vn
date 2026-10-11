<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nền tính lương KPI HCNS (đợt hcns-kpi bước 2) — chỉ THÊM cột nullable/bảng mới.
 *
 * - hr_kpi_rule_versions.config: trọng số, chỉ tiêu, bậc trả tiền theo phiên bản (nháp/đã duyệt theo kỳ).
 *   Bản không có config dùng cài đặt gốc như trước.
 * - hr_kpi_period_snapshots: ảnh chụp kết quả + nguồn + cấu hình KPI của từng người khi duyệt bảng lương;
 *   mở khóa không xóa, chỉ đánh dấu bản cũ không còn hiện hành.
 * - task_attachments.disk: tệp công việc lưu riêng tư (null = disk public như cũ).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_kpi_rule_versions') && ! Schema::hasColumn('hr_kpi_rule_versions', 'config')) {
            Schema::table('hr_kpi_rule_versions', fn (Blueprint $table) => $table->json('config')->nullable());
        }

        if (! Schema::hasTable('hr_kpi_period_snapshots')) {
            Schema::create('hr_kpi_period_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('period_id')->index();
                $table->char('payroll_month', 7)->index();
                $table->unsignedBigInteger('user_id');
                $table->string('template_code', 40);
                $table->unsignedBigInteger('version_id')->nullable();
                $table->string('version_label', 190)->nullable();
                $table->decimal('total', 8, 2)->nullable();
                $table->decimal('rate', 8, 4)->nullable();
                $table->string('status', 30)->nullable();
                $table->string('label', 190)->nullable();
                $table->json('payload');
                $table->boolean('is_current')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('superseded_at')->nullable();
                $table->unsignedBigInteger('superseded_by')->nullable();
                $table->index(['user_id', 'payroll_month', 'is_current'], 'hr_kpi_snapshots_lookup');
            });
        }

        if (Schema::hasTable('task_attachments') && ! Schema::hasColumn('task_attachments', 'disk')) {
            Schema::table('task_attachments', fn (Blueprint $table) => $table->string('disk', 20)->nullable());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_period_snapshots');
        // Cột config/disk được giữ lại khi rollback để không mất cấu hình nháp và đường dẫn tệp riêng tư.
    }
};
