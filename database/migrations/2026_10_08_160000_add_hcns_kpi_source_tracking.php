<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nguồn KPI HCNS (đợt hcns-kpi P1–P3) — chỉ THÊM cột nullable/bảng mới, không sửa dữ liệu đang có.
 *
 * - tasks: thủ tục hành chính HCNS (HC09): loại thủ tục, hạn gốc, lần gửi đầu/gửi gần nhất/số lần gửi,
 *   người/giờ xác nhận, hủy có lý do. Các mốc này được ghi từ lúc triển khai, không suy ngược từ updated_at.
 * - hr_document_handovers: theo dõi KPI phân loại/luân chuyển (HC07) bằng ID người xử lý/người kiểm/nơi nhận.
 * - hr_document_handover_files.disk: tệp mới lưu riêng tư (null = tệp cũ trên disk public).
 * - hr_kpi_source_reviews (+ events): kết quả kiểm tra độc lập gắn phiên bản nội dung của hồ sơ nguồn.
 * - hr_kpi_rule_versions: phiên bản quy tắc đo nguồn HCNS (nháp/áp dụng theo kỳ). Không tạo sẵn bản nào.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table): void {
                $add = fn (string $column) => ! Schema::hasColumn('tasks', $column);
                if ($add('hcns_procedure_type')) {
                    $table->string('hcns_procedure_type', 60)->nullable();
                }
                if ($add('original_due_at')) {
                    $table->dateTime('original_due_at')->nullable();
                }
                if ($add('draft_saved_at')) {
                    $table->timestamp('draft_saved_at')->nullable();
                }
                if ($add('first_submitted_at')) {
                    $table->timestamp('first_submitted_at')->nullable();
                }
                if ($add('last_submitted_at')) {
                    $table->timestamp('last_submitted_at')->nullable();
                }
                if ($add('submission_count')) {
                    $table->unsignedInteger('submission_count')->default(0);
                }
                if ($add('approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable();
                }
                if ($add('approved_at')) {
                    $table->timestamp('approved_at')->nullable();
                }
                if ($add('cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }
                if ($add('cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                }
                if ($add('cancel_reason')) {
                    $table->text('cancel_reason')->nullable();
                }
            });
            if (! $this->hasIndex('tasks', 'tasks_hcns_kpi_idx')) {
                Schema::table('tasks', fn (Blueprint $table) => $table->index(['task_type', 'assignee_id', 'due_at'], 'tasks_hcns_kpi_idx'));
            }
        }

        if (Schema::hasTable('hr_document_handovers')) {
            Schema::table('hr_document_handovers', function (Blueprint $table): void {
                $add = fn (string $column) => ! Schema::hasColumn('hr_document_handovers', $column);
                if ($add('company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->index();
                }
                if ($add('kpi_tracked')) {
                    $table->boolean('kpi_tracked')->default(false)->index();
                }
                if ($add('processor_id')) {
                    $table->unsignedBigInteger('processor_id')->nullable()->index();
                }
                if ($add('reviewer_id')) {
                    $table->unsignedBigInteger('reviewer_id')->nullable();
                }
                if ($add('classification')) {
                    $table->string('classification', 60)->nullable();
                }
                if ($add('routed_to_user_id')) {
                    $table->unsignedBigInteger('routed_to_user_id')->nullable();
                }
                if ($add('routed_to_note')) {
                    $table->string('routed_to_note', 255)->nullable();
                }
            });
        }

        if (Schema::hasTable('hr_document_handover_files') && ! Schema::hasColumn('hr_document_handover_files', 'disk')) {
            Schema::table('hr_document_handover_files', fn (Blueprint $table) => $table->string('disk', 20)->nullable());
        }

        if (! Schema::hasTable('hr_kpi_source_reviews')) {
            Schema::create('hr_kpi_source_reviews', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('criterion_code', 60);
                $table->string('source_type', 60);
                $table->unsignedBigInteger('source_id');
                $table->unsignedBigInteger('owner_id')->nullable()->index();
                $table->unsignedBigInteger('reviewer_id')->nullable();
                $table->string('status', 20)->default('draft')->index();
                $table->unsignedInteger('revision')->default(0);
                $table->string('fingerprint', 64)->nullable();
                $table->json('declared')->nullable();
                $table->json('checks')->nullable();
                $table->string('result', 10)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('draft_saved_at')->nullable();
                $table->timestamp('first_submitted_at')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->unsignedInteger('submission_count')->default(0);
                $table->unsignedBigInteger('submitted_by')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->unsignedBigInteger('returned_by')->nullable();
                $table->text('return_reason')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamps();
                $table->unique(['source_type', 'source_id', 'criterion_code'], 'hr_kpi_source_reviews_source_unique');
            });
        }

        if (! Schema::hasTable('hr_kpi_source_review_events')) {
            Schema::create('hr_kpi_source_review_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('review_id')->index();
                $table->string('action', 40);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedInteger('revision')->default(0);
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20)->nullable();
                $table->text('note')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('hr_kpi_rule_versions')) {
            Schema::create('hr_kpi_rule_versions', function (Blueprint $table): void {
                $table->id();
                $table->string('template_code', 40);
                $table->unsignedInteger('version');
                $table->string('status', 20)->default('draft')->index();
                $table->char('effective_month', 7)->nullable();
                $table->json('rules');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['template_code', 'version']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_kpi_rule_versions');
        Schema::dropIfExists('hr_kpi_source_review_events');
        Schema::dropIfExists('hr_kpi_source_reviews');
        // Cột thêm vào bảng nghiệp vụ được giữ lại khi rollback để không mất mốc đã ghi.
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($index) => ($index['name'] ?? '') === $name);
    }
};
