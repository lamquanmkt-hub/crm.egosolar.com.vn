<?php

namespace App\Services;

use App\Models\SolarMaintenanceApproval;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service xử lý quy trình phê duyệt lịch bảo trì điện mặt trời (gửi duyệt, duyệt, yêu cầu sửa, từ chối, mở lại).
 */
class SolarMaintenanceApprovalService
{
    /**
     * Gửi lịch bảo trì cho Trưởng phòng kỹ thuật phê duyệt (yêu cầu đã có kết quả xử lý).
     */
    public function submit(SolarMaintenanceSchedule $schedule, User $actor, ?string $comment = null): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment) {
            if (! in_array($schedule->status, ['in_progress', 'waiting_material', 'waiting_submission', 'revision_requested'], true)) {
                throw ValidationException::withMessages([
                    'approval' => 'Chỉ gửi duyệt khi công việc đang thực hiện, chờ gửi duyệt hoặc đang được yêu cầu chỉnh sửa.',
                ]);
            }

            if (! trim((string) $schedule->result_note)) {
                throw ValidationException::withMessages([
                    'result_note' => 'Phải nhập kết quả xử lý trước khi gửi duyệt.',
                ]);
            }

            $oldStatus = $schedule->status;
            $schedule->forceFill([
                'status' => 'pending_approval',
                'approval_status' => 'pending',
                'submitted_at' => now(),
                'submitted_by' => $actor->id,
                'approved_at' => null,
                'approved_by' => null,
                'revision_requested_at' => null,
                'revision_requested_by' => null,
                'approval_note' => $comment,
            ])->save();

            $this->approval($schedule, $actor, 'submit', 'pending', $comment, null);
            $this->history($schedule, $actor, $oldStatus, 'pending_approval', $comment ?: 'Gửi Trưởng phòng kỹ thuật phê duyệt');
            $this->audit($schedule, $actor, 'approval_submitted');
        });
    }

    /**
     * Phê duyệt lịch bảo trì đang chờ duyệt (người thực hiện không được tự duyệt).
     */
    public function approve(SolarMaintenanceSchedule $schedule, User $actor, ?string $comment = null): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment) {
            $this->assertPending($schedule);
            $this->assertNotExecutor($schedule, $actor);

            $oldStatus = $schedule->status;
            $schedule->forceFill([
                'status' => 'approved',
                'approval_status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $actor->id,
                'approval_note' => $comment,
            ])->save();

            $this->approval($schedule, $actor, 'approve', 'approved', $comment, $actor->id);
            $this->history($schedule, $actor, $oldStatus, 'approved', $comment ?: 'Trưởng phòng kỹ thuật đã phê duyệt');
            $this->audit($schedule, $actor, 'approval_approved');
        });
    }

    /**
     * Yêu cầu chỉnh sửa lịch bảo trì đang chờ duyệt (bắt buộc có nội dung cần sửa).
     */
    public function requestRevision(SolarMaintenanceSchedule $schedule, User $actor, string $comment): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment) {
            $this->assertPending($schedule);
            $this->assertNotExecutor($schedule, $actor);

            if (! trim($comment)) {
                throw ValidationException::withMessages(['comment' => 'Phải nhập nội dung cần chỉnh sửa.']);
            }

            $oldStatus = $schedule->status;
            $schedule->forceFill([
                'status' => 'revision_requested',
                'approval_status' => 'revision_requested',
                'revision_requested_at' => now(),
                'revision_requested_by' => $actor->id,
                'approval_note' => $comment,
            ])->save();

            $this->approval($schedule, $actor, 'request_revision', 'revision_requested', $comment, $actor->id);
            $this->history($schedule, $actor, $oldStatus, 'revision_requested', $comment);
            $this->audit($schedule, $actor, 'approval_revision_requested');
        });
    }

    /**
     * Từ chối lịch bảo trì đang chờ duyệt và chuyển về trạng thái yêu cầu chỉnh sửa.
     */
    public function reject(SolarMaintenanceSchedule $schedule, User $actor, string $comment): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment) {
            $this->assertPending($schedule);
            $this->assertNotExecutor($schedule, $actor);

            if (! trim($comment)) {
                throw ValidationException::withMessages(['comment' => 'Phải nhập lý do từ chối.']);
            }

            $oldStatus = $schedule->status;
            $schedule->forceFill([
                'status' => 'revision_requested',
                'approval_status' => 'rejected',
                'revision_requested_at' => now(),
                'revision_requested_by' => $actor->id,
                'approval_note' => $comment,
            ])->save();

            $this->approval($schedule, $actor, 'reject', 'rejected', $comment, $actor->id);
            $this->history($schedule, $actor, $oldStatus, 'revision_requested', 'Từ chối: '.$comment);
            $this->audit($schedule, $actor, 'approval_rejected');
        });
    }

    /**
     * Mở lại lịch đã duyệt/hoàn thành về trạng thái đang thực hiện, reset thông tin phê duyệt.
     */
    public function reopen(SolarMaintenanceSchedule $schedule, User $actor, string $comment): void
    {
        DB::transaction(function () use ($schedule, $actor, $comment) {
            if (! in_array($schedule->status, ['approved', 'completed', 'pending_approval', 'revision_requested'], true)) {
                throw ValidationException::withMessages(['comment' => 'Trạng thái hiện tại không cần mở lại.']);
            }

            if (! trim($comment)) {
                throw ValidationException::withMessages(['comment' => 'Phải nhập lý do mở lại công việc.']);
            }

            $oldStatus = $schedule->status;
            $schedule->forceFill([
                'status' => 'in_progress',
                'approval_status' => 'not_submitted',
                'submitted_at' => null,
                'submitted_by' => null,
                'approved_at' => null,
                'approved_by' => null,
                'revision_requested_at' => null,
                'revision_requested_by' => null,
                'approval_note' => $comment,
                'completed_at' => null,
                'completed_date' => null,
                'reopened_at' => now(),
                'reopened_by' => $actor->id,
            ])->save();

            $this->approval($schedule, $actor, 'reopen', 'not_submitted', $comment, $actor->id);
            $this->history($schedule, $actor, $oldStatus, 'in_progress', 'Mở lại: '.$comment);
            $this->audit($schedule, $actor, 'approval_reopened');
        });
    }

    /**
     * Kiểm tra lịch phải đang ở trạng thái chờ phê duyệt.
     */
    private function assertPending(SolarMaintenanceSchedule $schedule): void
    {
        if ($schedule->status !== 'pending_approval' || $schedule->approval_status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'Lịch này không ở trạng thái chờ phê duyệt.']);
        }
    }

    /**
     * Chặn người trực tiếp thực hiện tự phê duyệt công việc của mình.
     */
    private function assertNotExecutor(SolarMaintenanceSchedule $schedule, User $actor): void
    {
        if ((int) $schedule->assigned_to === (int) $actor->id
            || $schedule->assignees()->where('user_id', $actor->id)->exists()) {
            throw ValidationException::withMessages([
                'approval' => 'Người trực tiếp thực hiện không được tự phê duyệt công việc của mình.',
            ]);
        }
    }

    /**
     * Ghi bản ghi phê duyệt (SolarMaintenanceApproval) cho hành động vừa thực hiện.
     */
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
            'approval_level' => 'technical_manager',
            'approver_id' => $approverId,
            'submitted_by' => $schedule->submitted_by ?: $actor->id,
            'action' => $action,
            'status' => $status,
            'comment' => $comment,
            'submitted_at' => $schedule->submitted_at ?: now(),
            'reviewed_at' => $action === 'submit' ? null : now(),
            'metadata' => [
                'schedule_code' => $schedule->schedule_code,
                'company_id' => $schedule->company_id,
            ],
        ]);
    }

    /**
     * Ghi lịch sử chuyển trạng thái của lịch bảo trì.
     */
    private function history(
        SolarMaintenanceSchedule $schedule,
        User $actor,
        string $from,
        string $to,
        ?string $reason
    ): void {
        $schedule->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'metadata' => ['source' => 'approval_workflow'],
        ]);
    }

    /**
     * Ghi audit log (kèm IP, user agent) cho hành động phê duyệt.
     */
    private function audit(SolarMaintenanceSchedule $schedule, User $actor, string $action): void
    {
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
