<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nguồn KPI HC01–HC03 (tuyển dụng, chi phí tuyển, nghỉ trong thử việc) — chỉ THÊM cột nullable/bảng mới.
 *
 * - hr_recruitment_requests: theo dõi KPI, HCNS phụ trách/người kiểm bằng ID, mốc HCNS tiếp nhận yêu cầu thật,
 *   người/giờ duyệt yêu cầu (ghi từ lúc có bản này), hủy đợt tuyển có lý do. Yêu cầu cũ giữ nguyên, không tự vào KPI.
 * - hr_recruitment_hires: mỗi lượt tuyển (một suất trong số lượng) nối yêu cầu → ứng viên → thư mời → tài khoản
 *   nhân viên, ngày nhận việc thực tế, kết quả thử việc (ngày, kết quả, ngày nghỉ thực, lý do).
 * - hr_recruitment_costs: phân bổ chứng từ đề nghị thanh toán/tạm ứng/hoàn tiền đang có cho đợt tuyển.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_recruitment_requests')) {
            Schema::table('hr_recruitment_requests', function (Blueprint $table): void {
                $add = fn (string $c) => ! Schema::hasColumn('hr_recruitment_requests', $c);
                if ($add('company_id')) { $table->unsignedBigInteger('company_id')->nullable()->index(); }
                if ($add('kpi_tracked')) { $table->boolean('kpi_tracked')->default(false)->index(); }
                if ($add('owner_id')) { $table->unsignedBigInteger('owner_id')->nullable()->index(); }
                if ($add('reviewer_id')) { $table->unsignedBigInteger('reviewer_id')->nullable(); }
                if ($add('request_received_at')) { $table->timestamp('request_received_at')->nullable(); }
                if ($add('approved_by')) { $table->unsignedBigInteger('approved_by')->nullable(); }
                if ($add('approved_at')) { $table->timestamp('approved_at')->nullable(); }
                if ($add('cancelled_at')) { $table->timestamp('cancelled_at')->nullable(); }
                if ($add('cancelled_by')) { $table->unsignedBigInteger('cancelled_by')->nullable(); }
                if ($add('cancel_reason')) { $table->text('cancel_reason')->nullable(); }
            });
        }

        if (! Schema::hasTable('hr_recruitment_hires')) {
            Schema::create('hr_recruitment_hires', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('candidate_id')->nullable()->index();
                $table->unsignedBigInteger('offer_id')->nullable();
                $table->unsignedBigInteger('employee_user_id')->nullable()->index();
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->unsignedBigInteger('reviewer_id')->nullable();
                // pending = chờ nhận việc; started = đã nhận việc thực tế; no_show = không nhận việc; cancelled = hủy lượt.
                $table->string('status', 20)->default('pending')->index();
                $table->date('expected_start_date')->nullable();
                $table->date('actual_start_date')->nullable()->index();
                $table->timestamp('cancelled_at')->nullable();
                $table->unsignedBigInteger('cancelled_by')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->date('probation_start_date')->nullable();
                $table->date('probation_end_date')->nullable()->index();
                // null = chưa xác định; in_probation | passed | left
                $table->string('probation_outcome', 20)->nullable();
                $table->date('probation_left_date')->nullable();
                $table->string('probation_left_type', 30)->nullable();
                $table->text('probation_reason')->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_recruitment_costs')) {
            Schema::create('hr_recruitment_costs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('request_id')->index();
                $table->unsignedBigInteger('payment_request_id')->index();
                $table->decimal('allocated_amount', 15, 2);
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->text('note')->nullable();
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
        Schema::dropIfExists('hr_recruitment_costs');
        Schema::dropIfExists('hr_recruitment_hires');
        // Cột thêm vào hr_recruitment_requests được giữ lại khi rollback để không mất mốc đã ghi.
    }
};
