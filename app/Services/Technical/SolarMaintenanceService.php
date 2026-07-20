<?php

namespace App\Services\Technical;

use App\Models\Site;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service nghiệp vụ lịch bảo trì điện mặt trời: tạo chuỗi lịch, cập nhật, đổi trạng thái, phân công.
 */
class SolarMaintenanceService
{
    /**
     * Tạo chuỗi lịch bảo trì định kỳ theo số đợt và chu kỳ tháng, gán người phụ trách từng đợt.
     *
     * @return Collection Danh sách các lịch vừa tạo
     */
    public function createSeries(array $data, User $actor): Collection
    {
        return DB::transaction(function () use ($data, $actor) {
            $site = ! empty($data['site_id'])
                ? Site::withoutGlobalScopes()->find((int) $data['site_id'])
                : null;

            $currentCompanyId = EgoCompanyScope::currentId();
            $companyId = (int) ($site?->company_id ?: $currentCompanyId ?: 0);

            if ($site && $currentCompanyId > 0 && (int) $site->company_id !== $currentCompanyId) {
                throw ValidationException::withMessages([
                    'site_id' => 'Công trình không thuộc công ty đang làm việc.',
                ]);
            }

            $roundsCount = max(1, min(24, (int) ($data['rounds_count'] ?? 1)));
            $intervalMonths = max(1, min(24, (int) ($data['round_interval_months'] ?? 3)));
            $baseDate = Carbon::parse($data['scheduled_date']);
            $roundGroup = 'SMG-'.now()->format('YmdHis').'-'.$actor->id.'-'.random_int(1000, 9999);
            $created = collect();

            for ($round = 1; $round <= $roundsCount; $round++) {
                $roundDate = data_get($data, "round_dates.{$round}");
                $scheduledDate = $roundDate
                    ? Carbon::parse($roundDate)->toDateString()
                    : $baseDate->copy()->addMonths(($round - 1) * $intervalMonths)->toDateString();

                $assigneeIds = $this->cleanIds(
                    data_get($data, "round_assignees.{$round}", $data['assigned_user_ids'] ?? [])
                );

                $schedule = SolarMaintenanceSchedule::create([
                    'company_id' => $companyId > 0 ? $companyId : null,
                    'site_id' => $site?->id,
                    'customer_name' => $this->filled($data['customer_name'] ?? null)
                        ?: $this->filled($site?->contact_name),
                    'site_name' => $this->filled($data['site_name'] ?? null)
                        ?: $this->filled($site?->name)
                        ?: 'Công trình chưa đặt tên',
                    'address' => $this->filled($data['address'] ?? null)
                        ?: $this->filled($site?->address),
                    'type' => $data['type'],
                    'status' => count($assigneeIds) ? 'assigned' : 'unassigned',
                    'approval_status' => 'not_submitted',
                    'priority' => $data['priority'],
                    'round_no' => $round,
                    'total_rounds' => $roundsCount,
                    'round_group' => $roundGroup,
                    'scheduled_date' => $scheduledDate,
                    'system_kwp' => $data['system_kwp'] ?? $site?->system_kwp,
                    'inverter_info' => $data['inverter_info'] ?? null,
                    'issue_note' => $data['issue_note'] ?? null,
                    'technical_note' => $data['technical_note'] ?? null,
                    'created_by' => $actor->id,
                ]);

                $schedule->update([
                    'schedule_code' => sprintf('SM-%s-%06d', now()->format('Y'), $schedule->id),
                ]);

                $this->syncAssignees($schedule, $assigneeIds, $actor);
                $this->recordStatus($schedule, null, $schedule->status, 'Khởi tạo lịch', $actor);
                $this->recordAudit($schedule, 'created', [], $schedule->fresh()->toArray(), $actor);

                $created->push($schedule->fresh(['assignees.user']));
            }

            return $created;
        });
    }

    /**
     * Cập nhật lịch bảo trì: thông tin, trạng thái (có kiểm tra chuyển đổi) và phân công.
     */
    public function update(SolarMaintenanceSchedule $schedule, array $data, User $actor): SolarMaintenanceSchedule
    {
        return DB::transaction(function () use ($schedule, $data, $actor) {
            $schedule->loadMissing('assignees.user');
            $old = $schedule->toArray();
            $oldStatus = (string) $schedule->status;
            $newStatus = (string) ($data['status'] ?? $oldStatus);

            if ($newStatus !== $oldStatus) {
                $this->assertTransition($schedule, $newStatus, $data['reason'] ?? null, $actor);
            }

            $fillable = Arr::only($data, [
                'type',
                'priority',
                'scheduled_date',
                'system_kwp',
                'inverter_info',
                'issue_note',
                'technical_note',
                'result_note',
            ]);

            foreach ($fillable as $key => $value) {
                $schedule->{$key} = $value;
            }

            $schedule->status = $newStatus;
            $this->applyStatusTimestamps($schedule, $oldStatus, $newStatus, $data['reason'] ?? null, $actor);
            $schedule->save();

            $hasStructuredAssignment = array_key_exists('leader_user_id', $data)
                || array_key_exists('member_user_ids', $data);

            if ($hasStructuredAssignment) {
                $leaderId = (int) ($data['leader_user_id'] ?? 0);
                $memberIds = $this->cleanIds($data['member_user_ids'] ?? []);
                $ids = array_values(array_unique(array_filter(array_merge([$leaderId], $memberIds))));
                $this->syncAssignees($schedule, $ids, $actor);
            } elseif (array_key_exists('assigned_user_ids', $data)) {
                $this->syncAssignees($schedule, $this->cleanIds($data['assigned_user_ids'] ?? []), $actor);
            }

            if ($newStatus !== $oldStatus) {
                $this->recordStatus(
                    $schedule,
                    $oldStatus,
                    $newStatus,
                    $data['reason'] ?? null,
                    $actor,
                    $data['result_note'] ?? null
                );
            }

            $fresh = $schedule->fresh(['assignees.user']);
            $this->recordAudit($fresh, 'updated', $old, $fresh->toArray(), $actor);

            return $fresh;
        });
    }

    /**
     * Đổi trạng thái lịch bảo trì (chặn các trạng thái thuộc luồng phê duyệt), ghi lịch sử và audit.
     */
    public function changeStatus(SolarMaintenanceSchedule $schedule, array $data, User $actor): SolarMaintenanceSchedule
    {
        return DB::transaction(function () use ($schedule, $data, $actor) {
            $old = $schedule->toArray();
            $oldStatus = (string) $schedule->status;
            $newStatus = (string) $data['status'];

            if (in_array($newStatus, ['pending_approval', 'approved', 'revision_requested'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Trạng thái phê duyệt phải được thao tác bằng nút Gửi duyệt / Phê duyệt / Yêu cầu chỉnh sửa.',
                ]);
            }

            $this->assertTransition($schedule, $newStatus, $data['reason'] ?? null, $actor);

            $schedule->status = $newStatus;
            $schedule->result_note = $data['result_note'] ?? $schedule->result_note;
            $this->applyStatusTimestamps($schedule, $oldStatus, $newStatus, $data['reason'] ?? null, $actor);
            $schedule->save();

            $this->recordStatus(
                $schedule,
                $oldStatus,
                $newStatus,
                $data['reason'] ?? null,
                $actor,
                $data['result_note'] ?? null
            );

            $fresh = $schedule->fresh(['assignees.user']);
            $this->recordAudit($fresh, 'status_changed', $old, $fresh->toArray(), $actor);

            return $fresh;
        });
    }

    /**
     * Xoá mềm lịch bảo trì kèm ghi audit log.
     */
    public function softDelete(SolarMaintenanceSchedule $schedule, User $actor): void
    {
        DB::transaction(function () use ($schedule, $actor) {
            $this->recordAudit($schedule, 'soft_deleted', $schedule->toArray(), [], $actor);
            $schedule->delete();
        });
    }

    /**
     * Đồng bộ danh sách người phụ trách (người đầu là leader), chỉ chấp nhận nhân sự kỹ thuật hợp lệ.
     */
    private function syncAssignees(SolarMaintenanceSchedule $schedule, array $ids, User $actor): void
    {
        $ids = $this->cleanIds($ids);
        $users = User::query()
            ->with(['roles', 'department', 'position'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $invalid = collect($ids)->filter(function ($id) use ($users) {
            $user = $users->get($id);

            return ! $user || ! SolarMaintenanceAccess::isSelectableTechnician($user);
        })->values()->all();

        if ($invalid) {
            throw ValidationException::withMessages([
                'assigned_user_ids' => 'Người phụ trách chỉ được chọn từ nhân sự thuộc bộ phận kỹ thuật đang hoạt động.',
            ]);
        }

        $schedule->assignees()->delete();

        foreach ($ids as $index => $id) {
            $role = $index === 0 ? 'leader' : 'member';
            $schedule->assignees()->create([
                'user_id' => $id,
                'role' => $role,
                'assignment_role' => $role,
                'is_leader' => $index === 0,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);
        }

        $names = collect($ids)
            ->map(fn ($id) => $users->get($id)?->name)
            ->filter()
            ->values()
            ->all();

        $schedule->forceFill([
            'assigned_to' => $ids[0] ?? null,
            'assigned_user_ids' => $ids,
            'assigned_name' => implode(', ', $names),
        ])->saveQuietly();
    }

    /**
     * Kiểm tra việc chuyển trạng thái có hợp lệ theo bảng TRANSITIONS; mở lại lịch kết thúc cần quyền quản lý và lý do.
     */
    private function assertTransition(
        SolarMaintenanceSchedule $schedule,
        string $newStatus,
        ?string $reason,
        User $actor
    ): void {
        $oldStatus = (string) $schedule->status;

        if ($oldStatus === $newStatus) {
            return;
        }

        $allowed = SolarMaintenanceSchedule::TRANSITIONS[$oldStatus] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Không thể chuyển từ “'
                    .(SolarMaintenanceSchedule::STATUSES[$oldStatus] ?? $oldStatus)
                    .'” sang “'
                    .(SolarMaintenanceSchedule::STATUSES[$newStatus] ?? $newStatus)
                    .'”.',
            ]);
        }

        if (in_array($oldStatus, ['completed', 'cancelled', 'approved'], true)) {
            if (! SolarMaintenanceAccess::isManager($actor)) {
                throw ValidationException::withMessages([
                    'status' => 'Chỉ Admin hoặc Trưởng phòng kỹ thuật được mở lại lịch đã kết thúc hoặc đã phê duyệt.',
                ]);
            }

            if (! $this->filled($reason)) {
                throw ValidationException::withMessages([
                    'reason' => 'Phải nhập lý do khi mở lại lịch đã kết thúc hoặc đã phê duyệt.',
                ]);
            }
        }
    }

    /**
     * Cập nhật các mốc thời gian (bắt đầu, hoàn thành, huỷ, mở lại) theo trạng thái mới.
     */
    private function applyStatusTimestamps(
        SolarMaintenanceSchedule $schedule,
        string $oldStatus,
        string $newStatus,
        ?string $reason,
        User $actor
    ): void {
        if ($newStatus === 'in_progress' && ! $schedule->started_at) {
            $schedule->started_at = now();
        }

        if ($newStatus === 'completed') {
            if ($schedule->approval_status !== 'approved' && ! SolarMaintenanceAccess::isAdmin($actor)) {
                throw ValidationException::withMessages([
                    'status' => 'Lịch phải được Trưởng phòng kỹ thuật phê duyệt trước khi hoàn thành.',
                ]);
            }

            $schedule->completed_at = now();
            $schedule->completed_date = now()->toDateString();
            $schedule->cancelled_at = null;
            $schedule->cancellation_reason = null;
        }

        if ($newStatus === 'cancelled') {
            $schedule->cancelled_at = now();
            $schedule->cancellation_reason = $reason;
        }

        if ($oldStatus === 'completed' && $newStatus !== 'completed') {
            $schedule->completed_at = null;
            $schedule->completed_date = null;
            $schedule->reopened_at = now();
            $schedule->reopened_by = $actor->id;
        }

        if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
            $schedule->cancelled_at = null;
            $schedule->cancellation_reason = null;
            $schedule->reopened_at = now();
            $schedule->reopened_by = $actor->id;
        }
    }

    /**
     * Ghi lịch sử chuyển trạng thái của lịch bảo trì.
     */
    private function recordStatus(
        SolarMaintenanceSchedule $schedule,
        ?string $from,
        string $to,
        ?string $reason,
        User $actor,
        ?string $note = null
    ): void {
        $schedule->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'note' => $note,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'metadata' => [
                'schedule_code' => $schedule->schedule_code,
                'company_id' => $schedule->company_id,
            ],
        ]);
    }

    /**
     * Ghi audit log (giá trị cũ/mới, IP, user agent) cho lịch bảo trì.
     */
    private function recordAudit(
        SolarMaintenanceSchedule $schedule,
        string $action,
        array $old,
        array $new,
        User $actor
    ): void {
        $schedule->auditLogs()->create([
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'user_id' => $actor->id,
            'ip_address' => request()?->ip(),
            'user_agent' => mb_substr((string) request()?->userAgent(), 0, 1000),
            'created_at' => now(),
        ]);
    }

    /**
     * Chuẩn hoá danh sách ID: ép int, loại giá trị rỗng/không dương, bỏ trùng.
     */
    private function cleanIds($ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Trả về chuỗi đã trim nếu có nội dung, ngược lại trả về null.
     */
    private function filled($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
