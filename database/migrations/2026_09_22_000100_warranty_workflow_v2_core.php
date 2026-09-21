<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nâng cấp module Bảo hành / Sửa chữa (v2) — CHỈ THÊM (additive):
 *  - cột mới trên crm_serial_warranty_claims (ngoại lệ, quyết định, nhận hàng, thu hồi, hoàn tất...)
 *  - open_serial_key UNIQUE: chống 2 phiếu mở trên cùng 1 serial (race condition)
 *  - warranty_claim_events: lịch sử/audit bất biến
 *  - warranty_serial_reservations: giữ hàng thật, active_key UNIQUE chống giữ 1 serial cho 2 phiếu
 *  - warranty_serial_replacements: quan hệ serial cũ <-> serial mới
 *  - warranty_claim_notifications: việc/thông báo cho Kho, người duyệt...
 *  - cột disk/deleted_* cho file minh chứng (private disk)
 * Không xóa/đổi kiểu dữ liệu hiện có.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_serial_warranty_claims')) {
            Schema::table('crm_serial_warranty_claims', function (Blueprint $table): void {
                $add = function (string $col, callable $def) use ($table): void {
                    if (! Schema::hasColumn('crm_serial_warranty_claims', $col)) {
                        $def($table);
                    }
                };

                $add('open_serial_key', fn ($t) => $t->unsignedBigInteger('open_serial_key')->nullable()->unique('uq_wclaim_open_serial'));
                $add('status_changed_at', fn ($t) => $t->timestamp('status_changed_at')->nullable());

                // Ngoại lệ bảo hành
                $add('warranty_exception', fn ($t) => $t->boolean('warranty_exception')->default(false));
                $add('exception_reason', fn ($t) => $t->text('exception_reason')->nullable());
                $add('exception_requested_by', fn ($t) => $t->unsignedBigInteger('exception_requested_by')->nullable());
                $add('exception_requested_at', fn ($t) => $t->timestamp('exception_requested_at')->nullable());
                $add('exception_approved_by', fn ($t) => $t->unsignedBigInteger('exception_approved_by')->nullable());
                $add('exception_approved_at', fn ($t) => $t->timestamp('exception_approved_at')->nullable());
                $add('exception_override', fn ($t) => $t->boolean('exception_override')->default(false));
                $add('override_reason', fn ($t) => $t->text('override_reason')->nullable());

                // Quyết định duyệt (yêu cầu bổ sung / từ chối) — không ghi đè approved_*
                $add('decision_reason', fn ($t) => $t->text('decision_reason')->nullable());
                $add('decision_by', fn ($t) => $t->unsignedBigInteger('decision_by')->nullable());
                $add('decision_at', fn ($t) => $t->timestamp('decision_at')->nullable());

                // Kho
                $add('reserved_serial_unit_id', fn ($t) => $t->unsignedBigInteger('reserved_serial_unit_id')->nullable()->index());
                $add('reserved_serial_code', fn ($t) => $t->string('reserved_serial_code', 190)->nullable());
                $add('issued_at', fn ($t) => $t->timestamp('issued_at')->nullable());
                $add('issued_by', fn ($t) => $t->unsignedBigInteger('issued_by')->nullable());

                // Kỹ thuật nhận & thay
                $add('tech_received_at', fn ($t) => $t->timestamp('tech_received_at')->nullable());
                $add('tech_received_by', fn ($t) => $t->unsignedBigInteger('tech_received_by')->nullable());
                $add('tech_delivered_by', fn ($t) => $t->string('tech_delivered_by', 190)->nullable());
                $add('tech_receive_note', fn ($t) => $t->text('tech_receive_note')->nullable());
                $add('replaced_at', fn ($t) => $t->timestamp('replaced_at')->nullable());
                $add('replaced_by', fn ($t) => $t->unsignedBigInteger('replaced_by')->nullable());
                $add('replace_result', fn ($t) => $t->string('replace_result', 40)->nullable());
                $add('replace_note', fn ($t) => $t->text('replace_note')->nullable());

                // Thu hồi thiết bị lỗi
                $add('faulty_return_status', fn ($t) => $t->string('faulty_return_status', 20)->nullable()->index());
                $add('faulty_returned_by', fn ($t) => $t->string('faulty_returned_by', 190)->nullable());
                $add('faulty_received_by', fn ($t) => $t->unsignedBigInteger('faulty_received_by')->nullable());
                $add('faulty_return_warehouse_id', fn ($t) => $t->unsignedBigInteger('faulty_return_warehouse_id')->nullable());
                $add('faulty_condition', fn ($t) => $t->string('faulty_condition', 60)->nullable());
                $add('faulty_return_note', fn ($t) => $t->text('faulty_return_note')->nullable());
                $add('faulty_deferred_reason', fn ($t) => $t->text('faulty_deferred_reason')->nullable());
                $add('faulty_deferred_by', fn ($t) => $t->unsignedBigInteger('faulty_deferred_by')->nullable());

                // Hoàn tất
                $add('completion_snapshot', fn ($t) => $t->longText('completion_snapshot')->nullable());

                // Sửa chữa tính phí
                $add('warranty_eligibility', fn ($t) => $t->string('warranty_eligibility', 30)->nullable());
                $add('out_of_scope_reason', fn ($t) => $t->text('out_of_scope_reason')->nullable());
                $add('diagnosis_cause', fn ($t) => $t->text('diagnosis_cause')->nullable());
                $add('parts_needed', fn ($t) => $t->text('parts_needed')->nullable());
                $add('est_repair_hours', fn ($t) => $t->decimal('est_repair_hours', 8, 2)->nullable());
                $add('tech_note', fn ($t) => $t->text('tech_note')->nullable());
                $add('current_quotation_id', fn ($t) => $t->unsignedBigInteger('current_quotation_id')->nullable());
                $add('repair_started_at', fn ($t) => $t->timestamp('repair_started_at')->nullable());
                $add('repair_started_by', fn ($t) => $t->unsignedBigInteger('repair_started_by')->nullable());
                $add('repair_work_done', fn ($t) => $t->text('repair_work_done')->nullable());
                $add('repair_hours_actual', fn ($t) => $t->decimal('repair_hours_actual', 8, 2)->nullable());
                $add('repair_issues', fn ($t) => $t->text('repair_issues')->nullable());
                $add('handed_over_at', fn ($t) => $t->timestamp('handed_over_at')->nullable());
                $add('handed_over_by', fn ($t) => $t->unsignedBigInteger('handed_over_by')->nullable());
                $add('handover_receiver_name', fn ($t) => $t->string('handover_receiver_name', 190)->nullable());
                $add('handover_condition', fn ($t) => $t->string('handover_condition', 120)->nullable());
                $add('handover_result', fn ($t) => $t->text('handover_result')->nullable());
                $add('handover_guidance', fn ($t) => $t->text('handover_guidance')->nullable());
                $add('handover_note', fn ($t) => $t->text('handover_note')->nullable());
                $add('final_cost', fn ($t) => $t->decimal('final_cost', 15, 2)->nullable());
            });

            // Backfill an toàn: chỉ gán khóa cho serial có ĐÚNG 1 phiếu mở (tránh vi phạm unique nếu dữ liệu cũ đã trùng).
            DB::statement("
                UPDATE crm_serial_warranty_claims c
                JOIN (
                    SELECT serial_unit_id, MIN(id) AS id
                    FROM crm_serial_warranty_claims
                    WHERE serial_unit_id IS NOT NULL
                      AND deleted_at IS NULL
                      AND claim_type IN ('replacement','paid_repair')
                      AND status NOT IN ('completed','cancelled','rejected')
                    GROUP BY serial_unit_id
                    HAVING COUNT(*) = 1
                ) one ON one.id = c.id
                SET c.open_serial_key = c.serial_unit_id
                WHERE c.open_serial_key IS NULL
            ");
        }

        if (! Schema::hasTable('warranty_claim_events')) {
            Schema::create('warranty_claim_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->string('action', 60)->index();
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->longText('before_data')->nullable();
                $table->longText('after_data')->nullable();
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('ip_address', 64)->nullable();
                $table->string('related_type', 60)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['claim_id', 'id'], 'idx_wce_claim_id');
            });
        }

        if (! Schema::hasTable('warranty_serial_reservations')) {
            Schema::create('warranty_serial_reservations', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('serial_unit_id')->index();
                $table->string('serial_code', 190)->nullable();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('movement_id')->nullable()->index();
                $table->unsignedBigInteger('warehouse_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('status', 20)->default('active')->index(); // active|consumed|released
                // = serial_unit_id khi status=active, NULL khi đã kết thúc → UNIQUE chống giữ 1 serial cho 2 phiếu
                $table->unsignedBigInteger('active_key')->nullable()->unique('uq_wsr_active_serial');
                $table->unsignedBigInteger('reserved_by')->nullable();
                $table->timestamp('reserved_at')->nullable();
                $table->unsignedBigInteger('released_by')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->text('release_reason')->nullable();
                $table->string('previous_state', 30)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warranty_serial_replacements')) {
            Schema::create('warranty_serial_replacements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('claim_id')->unique('uq_wsrep_claim');
                $table->unsignedBigInteger('old_serial_unit_id')->index();
                $table->string('old_serial_code', 190);
                $table->unsignedBigInteger('new_serial_unit_id')->index();
                $table->string('new_serial_code', 190);
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('site_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->timestamp('replaced_at')->nullable();
                $table->unsignedBigInteger('technician_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('warranty_claim_notifications')) {
            Schema::create('warranty_claim_notifications', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('claim_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();   // null = theo nhóm quyền
                $table->string('audience', 30)->nullable()->index();          // warehouse|approver|technician
                $table->string('type', 60);
                $table->string('title', 190);
                $table->text('message')->nullable();
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('solar_warranty_claim_attachments')) {
            Schema::table('solar_warranty_claim_attachments', function (Blueprint $table): void {
                if (! Schema::hasColumn('solar_warranty_claim_attachments', 'disk')) {
                    $table->string('disk', 20)->default('public'); // bản ghi cũ = public, bản mới = local (private)
                }
                if (! Schema::hasColumn('solar_warranty_claim_attachments', 'deleted_by')) {
                    $table->unsignedBigInteger('deleted_by')->nullable();
                }
                if (! Schema::hasColumn('solar_warranty_claim_attachments', 'deleted_reason')) {
                    $table->string('deleted_reason', 255)->nullable();
                }
                if (! Schema::hasColumn('solar_warranty_claim_attachments', 'step')) {
                    $table->string('step', 40)->nullable();
                }
            });
        }

        if (Schema::hasTable('crm_serial_warranties')) {
            Schema::table('crm_serial_warranties', function (Blueprint $table): void {
                if (! Schema::hasColumn('crm_serial_warranties', 'replaced_serial_unit_id')) {
                    $table->unsignedBigInteger('replaced_serial_unit_id')->nullable()->index();
                }
                if (! Schema::hasColumn('crm_serial_warranties', 'replacement_claim_id')) {
                    $table->unsignedBigInteger('replacement_claim_id')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('solar_warranty_stock_movements')
            && ! Schema::hasColumn('solar_warranty_stock_movements', 'reservation_id')) {
            Schema::table('solar_warranty_stock_movements', function (Blueprint $table): void {
                $table->unsignedBigInteger('reservation_id')->nullable()->index();
                $table->unsignedBigInteger('part_line_id')->nullable()->index();
                $table->decimal('qty_before', 15, 3)->nullable();
                $table->decimal('qty_after', 15, 3)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Migration additive: không tự động xóa dữ liệu nghiệp vụ.
    }
};
