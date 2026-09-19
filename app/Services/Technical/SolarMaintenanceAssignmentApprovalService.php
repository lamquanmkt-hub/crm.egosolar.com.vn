<?php

namespace App\Services\Technical;

use App\Models\SolarMaintenanceApproval;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cổng duyệt phân công O&M.
 *
 * Không thay đổi thứ tự 6 bước hiện hữu. Hồ sơ vẫn ở bước "Phân công"
 * cho tới khi sếp duyệt. Với hồ sơ cũ, trạng thái not_required cho phép
 * tiếp tục theo luồng cũ để không làm gián đoạn công việc đang chạy.
 */
class SolarMaintenanceAssignmentApprovalService
{
    public const LEGACY_BYPASS = 'not_required';
    public const DRAFT = 'draft';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REVISION = 'revision_requested';
    public const REJECTED = 'rejected';

    public function submit(SolarMaintenanceSchedule $schedule, User $actor, ?string $comment = null): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment): void {
            $this->assertAssignmentStage($schedule);

            if (! $schedule->assignees()->exists()) {
                throw ValidationException::withMessages([
                    'assignment' => 'Phải phân công ít nhất một nhân sự nội bộ trước khi gửi sếp duyệt.',
                ]);
            }

            if (! in_array((string) $schedule->assignment_approval_status, [
                self::DRAFT,
                self::REVISION,
                self::REJECTED,
                self::LEGACY_BYPASS,
                '',
            ], true)) {
                throw ValidationException::withMessages([
                    'assignment' => 'Phân công hiện tại không ở trạng thái có thể gửi duyệt.',
                ]);
            }

            $this->assertExternalLaborData($schedule);

            $schedule->forceFill([
                'assignment_approval_status' => self::PENDING,
                'assignment_submitted_at' => now(),
                'assignment_submitted_by' => $actor->id,
                'assignment_approved_at' => null,
                'assignment_approved_by' => null,
                'assignment_revision_requested_at' => null,
                'assignment_revision_requested_by' => null,
                'assignment_approval_note' => trim((string) $comment) ?: null,
            ])->save();

            $this->approval($schedule, $actor, 'submit_assignment', self::PENDING, $comment, null);
            $this->history($schedule, $actor, 'Đã gửi phân công cho sếp duyệt.');
            $this->audit($schedule, $actor, 'assignment_approval_submitted');
        });
    }

    /**
     * @return int|null ID ĐNTT được tạo tự động nếu có nhân công ngoài.
     */
    public function approve(SolarMaintenanceSchedule $schedule, User $actor, ?string $comment = null): ?int
    {
        return DB::transaction(function () use ($schedule, $actor, $comment): ?int {
            $this->assertAssignmentPending($schedule);
            $this->assertNotExecutor($schedule, $actor);
            $this->assertExternalLaborData($schedule);

            $schedule->forceFill([
                'assignment_approval_status' => self::APPROVED,
                'assignment_approved_at' => now(),
                'assignment_approved_by' => $actor->id,
                'assignment_revision_requested_at' => null,
                'assignment_revision_requested_by' => null,
                'assignment_approval_note' => trim((string) $comment) ?: $schedule->assignment_approval_note,
            ])->save();

            $this->approval($schedule, $actor, 'approve_assignment', self::APPROVED, $comment, $actor->id);
            $this->history($schedule, $actor, 'Sếp đã duyệt phân công. Bước Thực hiện đã được mở.');
            $this->audit($schedule, $actor, 'assignment_approval_approved');

            return $this->createExternalLaborPaymentRequest($schedule, $actor);
        });
    }

    /**
     * V13.3 - Khi Admin chỉnh lại Bước 2 đã qua và bổ sung nhân công ngoài,
     * tự tạo ĐNTT nháp nếu chưa có để dữ liệu O&M và Tài chính không lệch nhau.
     */
    public function ensureExternalLaborPaymentRequestForAdmin(
        SolarMaintenanceSchedule $schedule,
        User $actor
    ): ?int {
        $this->assertExternalLaborData($schedule);

        return $this->createExternalLaborPaymentRequest(
            $schedule,
            $actor
        );
    }

    public function requestRevision(SolarMaintenanceSchedule $schedule, User $actor, string $comment): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment): void {
            $this->assertAssignmentPending($schedule);
            $this->assertNotExecutor($schedule, $actor);

            $comment = trim($comment);
            if ($comment === '') {
                throw ValidationException::withMessages(['comment' => 'Phải nhập nội dung cần chỉnh sửa.']);
            }

            $schedule->forceFill([
                'assignment_approval_status' => self::REVISION,
                'assignment_revision_requested_at' => now(),
                'assignment_revision_requested_by' => $actor->id,
                'assignment_approved_at' => null,
                'assignment_approved_by' => null,
                'assignment_approval_note' => $comment,
            ])->save();

            $this->approval($schedule, $actor, 'revise_assignment', self::REVISION, $comment, $actor->id);
            $this->history($schedule, $actor, 'Sếp yêu cầu chỉnh sửa phân công: '.$comment);
            $this->audit($schedule, $actor, 'assignment_approval_revision_requested');
        });
    }

    public function reject(SolarMaintenanceSchedule $schedule, User $actor, string $comment): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment): void {
            $this->assertAssignmentPending($schedule);
            $this->assertNotExecutor($schedule, $actor);

            $comment = trim($comment);
            if ($comment === '') {
                throw ValidationException::withMessages(['comment' => 'Phải nhập lý do từ chối.']);
            }

            $schedule->forceFill([
                'assignment_approval_status' => self::REJECTED,
                'assignment_revision_requested_at' => now(),
                'assignment_revision_requested_by' => $actor->id,
                'assignment_approved_at' => null,
                'assignment_approved_by' => null,
                'assignment_approval_note' => $comment,
            ])->save();

            $this->approval($schedule, $actor, 'reject_assignment', self::REJECTED, $comment, $actor->id);
            $this->history($schedule, $actor, 'Sếp từ chối phân công: '.$comment);
            $this->audit($schedule, $actor, 'assignment_approval_rejected');
        });
    }

    private function assertAssignmentStage(SolarMaintenanceSchedule $schedule): void
    {
        if (! in_array((string) $schedule->status, ['assigned', 'customer_confirmed', 'travelling'], true)) {
            throw ValidationException::withMessages([
                'assignment' => 'Chỉ gửi duyệt phân công trước khi bắt đầu thực hiện.',
            ]);
        }
    }

    private function assertAssignmentPending(SolarMaintenanceSchedule $schedule): void
    {
        $this->assertAssignmentStage($schedule);

        if ((string) $schedule->assignment_approval_status !== self::PENDING) {
            throw ValidationException::withMessages([
                'assignment' => 'Phân công này không ở trạng thái chờ sếp duyệt.',
            ]);
        }
    }

    private function assertNotExecutor(SolarMaintenanceSchedule $schedule, User $actor): void
    {
        if ((int) $schedule->assigned_to === (int) $actor->id
            || $schedule->assignees()->where('user_id', $actor->id)->exists()) {
            throw ValidationException::withMessages([
                'assignment' => 'Người nằm trong nhóm thực hiện không được tự duyệt phân công của mình.',
            ]);
        }
    }

    private function assertExternalLaborData(SolarMaintenanceSchedule $schedule): void
    {
        if (! (bool) $schedule->external_labor_enabled) {
            return;
        }

        $headcount = (int) ($schedule->external_labor_headcount ?? 0);
        $total = (float) ($schedule->external_labor_total_cost ?? 0);
        $requestAmount = (float) ($schedule->external_labor_advance_amount ?? 0);

        $errors = [];
        if ($headcount < 1) {
            $errors[] = 'Số lượng nhân công ngoài phải từ 1 người.';
        }
        if ($total <= 0) {
            $errors[] = 'Phải nhập tổng tiền công nhân công ngoài.';
        }
        if ($requestAmount < 0) {
            $errors[] = 'Số tiền đề nghị/tạm ứng không hợp lệ.';
        }
        if ($requestAmount > $total) {
            $errors[] = 'Số tiền đề nghị/tạm ứng không được lớn hơn tổng tiền công.';
        }

        if ($errors) {
            throw ValidationException::withMessages(['external_labor' => $errors]);
        }
    }

    private function createExternalLaborPaymentRequest(SolarMaintenanceSchedule $schedule, User $actor): ?int
    {
        if (! (bool) $schedule->external_labor_enabled) {
            return null;
        }

        if (! Schema::hasTable('payment_requests')) {
            return null;
        }

        if ((int) ($schedule->external_labor_payment_request_id ?? 0) > 0) {
            return (int) $schedule->external_labor_payment_request_id;
        }

        $total = (int) round((float) ($schedule->external_labor_total_cost ?? 0));
        $requested = (int) round((float) ($schedule->external_labor_advance_amount ?? 0));
        $amount = $requested > 0 ? $requested : $total;

        if ($amount <= 0) {
            return null;
        }

        $columns = Schema::getColumnListing('payment_requests');
        $now = now();
        $receiver = trim((string) ($schedule->external_labor_name ?? ''));
        if ($receiver === '') {
            $receiver = 'Nhân công ngoài - '.($schedule->schedule_code ?: '#'.$schedule->id);
        }

        $isAdvance = $amount < $total;
        $content = ($isAdvance ? 'Tạm ứng' : 'Thanh toán')
            .' nhân công ngoài O&M '.($schedule->schedule_code ?: '#'.$schedule->id)
            .' - '.($schedule->site_name ?: optional($schedule->site)->name ?: 'Công trình');

        $data = [
            'code' => 'TMP-'.(string) Str::uuid(),
            'created_by' => $actor->id,
            'company' => (string) session('active_company_name', 'Công ty TNHH TMKT Quốc Tế EGO'),
            'receiver_name' => $receiver,
            'department' => 'Kỹ thuật',
            'payment_content' => $content,
            'reason' => trim((string) ($schedule->external_labor_note ?? '')) ?: $content,
            'amount' => $amount,
            'bank_info' => trim((string) ($schedule->external_labor_bank_info ?? '')) ?: null,
            'status' => 'draft',
            'doc_type' => $isAdvance ? 'advance' : 'payment_request',
            'site_id' => $schedule->site_id,
            'cost_type' => 'external_labor',
            'company_id' => $schedule->company_id,
            'maintenance_schedule_id' => $schedule->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $insert = array_intersect_key($data, array_flip($columns));
        $id = (int) DB::table('payment_requests')->insertGetId($insert);

        $update = ['code' => 'PR-'.now()->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT)];
        if (in_array('updated_at', $columns, true)) {
            $update['updated_at'] = $now;
        }
        DB::table('payment_requests')->where('id', $id)->update($update);

        $schedule->forceFill(['external_labor_payment_request_id' => $id])->save();

        $this->history(
            $schedule,
            $actor,
            'Tự tạo ĐNTT nhân công ngoài '.$update['code'].' số tiền '.number_format($amount, 0, ',', '.').'đ.'
        );

        return $id;
    }

    private function approval(
        SolarMaintenanceSchedule $schedule,
        User $actor,
        string $action,
        string $status,
        ?string $comment,
        ?int $approverId
    ): void {
        SolarMaintenanceApproval::create([
            'maintenance_schedule_id' => $schedule->id,
            'approval_level' => 'assignment_manager',
            'approver_id' => $approverId,
            'submitted_by' => $schedule->assignment_submitted_by ?: $actor->id,
            'action' => $action,
            'status' => $status,
            'comment' => $comment,
            'submitted_at' => $schedule->assignment_submitted_at ?: now(),
            'reviewed_at' => $action === 'submit_assignment' ? null : now(),
            'metadata' => [
                'schedule_code' => $schedule->schedule_code,
                'company_id' => $schedule->company_id,
                'site_id' => $schedule->site_id,
                'project_id' => $schedule->project_id,
                'round_no' => $schedule->round_no,
                'total_rounds' => $schedule->total_rounds,
                'external_labor_enabled' => (bool) $schedule->external_labor_enabled,
                'external_labor_headcount' => (int) ($schedule->external_labor_headcount ?? 0),
                'external_labor_total_cost' => (float) ($schedule->external_labor_total_cost ?? 0),
                'external_labor_advance_amount' => (float) ($schedule->external_labor_advance_amount ?? 0),
            ],
        ]);
    }

    private function history(SolarMaintenanceSchedule $schedule, User $actor, string $reason): void
    {
        $schedule->statusHistories()->create([
            'from_status' => $schedule->status,
            'to_status' => $schedule->status,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'metadata' => ['source' => 'assignment_approval_v13'],
        ]);
    }

    private function audit(SolarMaintenanceSchedule $schedule, User $actor, string $action): void
    {
        if (! Schema::hasTable('solar_maintenance_audit_logs')) {
            return;
        }

        $schedule->auditLogs()->create([
            'action' => $action,
            'old_values' => null,
            'new_values' => $schedule->fresh()->toArray(),
            'user_id' => $actor->id,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }
}
