<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kế hoạch tuần của nhân viên kỹ thuật (giai đoạn 2).
 *
 * Nguyên tắc:
 * - KHÔNG sao chép dữ liệu nguồn. Một dòng kế hoạch chỉ LIÊN KẾT tới công việc
 *   nguồn bằng bộ ba (source_type, source_id, site_id) + vài cột điều phối
 *   (buổi, thời lượng dự kiến, ưu tiên, mục tiêu). Nội dung thật của công việc
 *   vẫn nằm ở module Công trình / Task / Bảo trì.
 * - KHÔNG đặt khoá ngoại cho source_id / site_id vì nguồn nằm ở nhiều bảng
 *   khác nhau; bù lại có index và cột snapshot tên công trình.
 * - Chống trùng "cùng người + cùng ngày + cùng việc nguồn" bằng UNIQUE
 *   (user_id, plan_date, source_type, source_id, active_flag):
 *     * `active_flag` = 1 khi dòng còn sống, = NULL khi đã soft-delete, nên
 *       dòng đã xoá không chặn việc lập lại kế hoạch.
 *     * `source_id` NULL (việc nội bộ cá nhân) => MySQL coi mỗi NULL là khác
 *       nhau => việc cá nhân KHÔNG bị ràng buộc, đúng nghiệp vụ.
 * - Lịch sử điều chỉnh KHÔNG có khoá ngoại tới dòng kế hoạch: nhật ký phải
 *   sống lâu hơn bản ghi (cùng triết lý payment_request_edit_logs đợt P0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('technical_week_plans')) {
            Schema::create('technical_week_plans', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();

                // Luôn là thứ Hai; week_end luôn là Chủ nhật cùng tuần.
                $table->date('week_start');
                $table->date('week_end');

                // draft | finalized | adjusted  (chưa lập = chưa có dòng nào)
                $table->string('status', 30)->default('draft')->index();

                $table->dateTime('finalized_at')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();

                $table->dateTime('adjusted_at')->nullable();
                $table->foreignId('adjusted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('adjust_note')->nullable();

                // Trưởng phòng yêu cầu nhân viên cập nhật lại kế hoạch.
                $table->boolean('update_requested')->default(false);
                $table->text('update_request_note')->nullable();
                $table->dateTime('update_requested_at')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(['user_id', 'week_start'], 'twp_user_week_uniq');
                $table->index(['company_id', 'week_start'], 'twp_company_week_idx');
                $table->index(['week_start', 'status'], 'twp_week_status_idx');
            });
        }

        if (! Schema::hasTable('technical_plan_items')) {
            Schema::create('technical_plan_items', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->foreignId('week_plan_id')
                    ->constrained('technical_week_plans')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();

                $table->date('plan_date');

                // morning | afternoon | full_day | custom  (config/technical.php)
                $table->string('day_part', 20)->default('full_day');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();

                $table->string('title');
                $table->text('objective')->nullable();
                $table->text('note')->nullable();

                // project_workflow | task | maintenance | personal | manager_assigned
                $table->string('source_type', 40)->index();
                $table->unsignedBigInteger('source_id')->nullable();

                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->string('site_name')->nullable();

                $table->unsignedInteger('estimated_minutes')->nullable();

                // low | normal | high | urgent
                $table->string('priority', 20)->default('normal');

                // planned | in_progress | done | not_done | moved | cancelled
                $table->string('status', 30)->default('planned')->index();
                $table->unsignedTinyInteger('progress_percent')->default(0);

                // Hạn của công việc nguồn tại thời điểm lập kế hoạch (để cảnh báo trễ).
                $table->dateTime('source_due_at')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('created_by_name')->nullable();

                // Trưởng phòng giao thêm / điều chỉnh.
                $table->boolean('is_manager_assigned')->default(false);
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('assigned_at')->nullable();

                $table->date('moved_from_date')->nullable();
                $table->date('moved_to_date')->nullable();

                $table->timestamps();
                $table->softDeletes();

                // 1 khi còn sống, NULL khi đã soft-delete — xem ghi chú đầu file.
                $table->unsignedTinyInteger('active_flag')->nullable()->default(1);

                $table->index(['user_id', 'plan_date'], 'tpi_user_date_idx');
                $table->index(['week_plan_id', 'plan_date'], 'tpi_plan_date_idx');
                $table->index(['company_id', 'plan_date'], 'tpi_company_date_idx');
                $table->index(['plan_date', 'status'], 'tpi_date_status_idx');
                $table->index(['source_type', 'source_id'], 'tpi_source_idx');

                $table->unique(
                    ['user_id', 'plan_date', 'source_type', 'source_id', 'active_flag'],
                    'tpi_user_date_source_uniq',
                );
            });
        }

        if (! Schema::hasTable('technical_plan_day_marks')) {
            Schema::create('technical_plan_day_marks', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();

                $table->foreignId('week_plan_id')
                    ->constrained('technical_week_plans')
                    ->cascadeOnDelete();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

                $table->date('plan_date');

                // day_off | leave | awaiting_assignment | no_plan
                $table->string('mark', 40);
                $table->string('reason', 500)->nullable();

                $table->timestamps();

                $table->unique(['user_id', 'plan_date'], 'tpdm_user_date_uniq');
                $table->index(['week_plan_id', 'plan_date'], 'tpdm_plan_date_idx');
            });
        }

        if (! Schema::hasTable('technical_plan_histories')) {
            Schema::create('technical_plan_histories', function (Blueprint $table): void {
                $table->id();

                $table->unsignedBigInteger('company_id')->nullable()->index();

                // Cố tình KHÔNG đặt khoá ngoại: nhật ký phải sống lâu hơn bản ghi.
                $table->unsignedBigInteger('week_plan_id')->nullable()->index();
                $table->unsignedBigInteger('plan_item_id')->nullable()->index();

                // Chủ sở hữu kế hoạch bị tác động (để lọc nhật ký theo nhân sự).
                $table->unsignedBigInteger('target_user_id')->nullable()->index();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('user_name')->nullable();

                // create | update | move | assign | delete | status | finalize
                // | adjust | request_update | copy_week
                $table->string('action', 40)->index();

                $table->text('changes_before')->nullable();
                $table->text('changes_after')->nullable();
                $table->text('reason')->nullable();

                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();

                $table->timestamps();

                $table->index(['plan_item_id', 'created_at'], 'tph_item_created_idx');
                $table->index(['week_plan_id', 'created_at'], 'tph_plan_created_idx');
            });
        }
    }

    public function down(): void
    {
        // Thứ tự ngược để không vướng khoá ngoại. Chỉ xoá đúng các bảng do
        // migration này tạo ra.
        Schema::dropIfExists('technical_plan_histories');
        Schema::dropIfExists('technical_plan_day_marks');
        Schema::dropIfExists('technical_plan_items');
        Schema::dropIfExists('technical_week_plans');
    }
};
