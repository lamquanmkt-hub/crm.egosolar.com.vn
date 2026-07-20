<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lịch / đợt bảo trì hệ thống điện mặt trời — trạng thái, phân công, phê duyệt và kết quả.
 */
class SolarMaintenanceSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'solar_maintenance_schedules';

    protected $fillable = [
        // Thông tin chung / Core
        'schedule_code',
        'site_id',
        'company_id',
        'customer_name',
        'site_name',
        'address',
        'type',
        'status',
        'priority',
        'round_no',
        'total_rounds',
        'round_group',

        // Lịch & tiến độ / Schedule & progress
        'scheduled_date',
        'completed_date',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancellation_reason',
        'reopened_at',
        'reopened_by',

        // Phân công / Assignment
        'assigned_to',
        'assigned_name',
        'assigned_user_ids',

        // Kỹ thuật / Technical
        'system_kwp',
        'inverter_info',
        'issue_note',
        'technical_note',
        'result_note',

        // Phê duyệt / Approval
        'approval_status',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'revision_requested_at',
        'revision_requested_by',
        'approval_note',

        'created_by',
        'deleted_at',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'completed_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reopened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'assigned_user_ids' => 'array',
        'system_kwp' => 'decimal:2',
    ];

    public const TYPES = [
        'periodic' => 'Bảo trì định kỳ',
        'warranty_inverter' => 'Bảo hành inverter',
        'panel_check' => 'Kiểm tra tấm pin',
        'cleaning' => 'Vệ sinh hệ thống',
        'incident' => 'Xử lý sự cố',
        'monitoring' => 'Kiểm tra monitoring',
        'handover' => 'Nghiệm thu / bàn giao',
    ];

    public const STATUSES = [
        'draft' => 'Nháp',
        'scheduled' => 'Đã lên lịch',
        'unassigned' => 'Chờ phân công',
        'assigned' => 'Đã phân công',
        'customer_confirmed' => 'Khách đã xác nhận',
        'travelling' => 'Đang di chuyển',
        'in_progress' => 'Đang thực hiện',
        'waiting_material' => 'Chờ vật tư',
        'waiting_submission' => 'Chờ gửi duyệt',
        'pending_approval' => 'Chờ phê duyệt',
        'revision_requested' => 'Yêu cầu chỉnh sửa',
        'approved' => 'Đã phê duyệt',
        'waiting_customer' => 'Chờ khách xác nhận',
        'completed' => 'Hoàn thành',
        'postponed' => 'Hoãn',
        'cancelled' => 'Đã hủy',
    ];

    public const APPROVAL_STATUSES = [
        'not_submitted' => 'Chưa gửi duyệt',
        'pending' => 'Đang chờ duyệt',
        'approved' => 'Đã phê duyệt',
        'revision_requested' => 'Yêu cầu chỉnh sửa',
        'rejected' => 'Từ chối',
    ];

    public const PRIORITIES = [
        'low' => 'Thấp',
        'normal' => 'Bình thường',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ];

    public const TRANSITIONS = [
        'draft' => ['scheduled', 'unassigned', 'assigned', 'cancelled'],
        'scheduled' => ['unassigned', 'assigned', 'customer_confirmed', 'travelling', 'in_progress', 'postponed', 'cancelled'],
        'unassigned' => ['scheduled', 'assigned', 'postponed', 'cancelled'],
        'assigned' => ['scheduled', 'customer_confirmed', 'travelling', 'in_progress', 'postponed', 'cancelled'],
        'customer_confirmed' => ['travelling', 'in_progress', 'postponed', 'cancelled'],
        'travelling' => ['in_progress', 'postponed', 'cancelled'],
        'in_progress' => ['waiting_material', 'waiting_submission', 'pending_approval', 'postponed', 'cancelled'],
        'waiting_material' => ['in_progress', 'waiting_submission', 'pending_approval', 'postponed', 'cancelled'],
        'waiting_submission' => ['in_progress', 'pending_approval', 'postponed', 'cancelled'],
        'pending_approval' => ['revision_requested', 'approved', 'cancelled'],
        'revision_requested' => ['in_progress', 'waiting_submission', 'pending_approval', 'cancelled'],
        'approved' => ['waiting_customer', 'completed', 'in_progress'],
        'waiting_customer' => ['completed', 'in_progress', 'postponed', 'cancelled'],
        'postponed' => ['scheduled', 'unassigned', 'assigned', 'cancelled'],
        'completed' => ['in_progress'],
        'cancelled' => ['scheduled', 'unassigned'],
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id')->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignees(): HasMany
    {
        return $this->hasMany(SolarMaintenanceAssignee::class, 'maintenance_schedule_id')
            ->orderByDesc('is_leader')
            ->orderByRaw("CASE WHEN role = 'leader' THEN 0 ELSE 1 END")
            ->orderBy('id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(SolarMaintenanceApproval::class, 'maintenance_schedule_id')
            ->latest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SolarMaintenanceAttachment::class, 'maintenance_schedule_id')
            ->latest('id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SolarMaintenanceStatusHistory::class, 'maintenance_schedule_id')
            ->latest('changed_at');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(SolarMaintenanceAuditLog::class, 'maintenance_schedule_id')
            ->latest('created_at');
    }

    public function scopeForCompany(Builder $query, int $companyId, bool $includeUnknown = false): Builder
    {
        if ($companyId <= 0) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($companyId, $includeUnknown) {
            $q->where($this->qualifyColumn('company_id'), $companyId);
            if ($includeUnknown) {
                $q->orWhereNull($this->qualifyColumn('company_id'));
            }
        });
    }

    public function getAssigneeNamesAttribute(): string
    {
        $names = $this->relationLoaded('assignees')
            ? $this->assignees->pluck('user.name')->filter()->values()->all()
            : [];

        return $names ? implode(', ', $names) : (trim((string) $this->assigned_name) ?: 'Chưa phân công');
    }

    public function getLeaderAttribute(): ?SolarMaintenanceAssignee
    {
        if (! $this->relationLoaded('assignees')) {
            return null;
        }

        return $this->assignees->first(fn ($item) => (bool) ($item->is_leader ?? false) || $item->role === 'leader');
    }

    public function isOverdue(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled'], true)
            && $this->scheduled_date
            && $this->scheduled_date->isBefore(today());
    }
}
