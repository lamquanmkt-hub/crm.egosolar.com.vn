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
        'project_id',
        'maintenance_profile_id',
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
        'scheduled_start_at',
        'scheduled_end_at',
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

        // Duyệt phân công / Assignment approval
        'assignment_approval_status',
        'assignment_submitted_at',
        'assignment_submitted_by',
        'assignment_approved_at',
        'assignment_approved_by',
        'assignment_revision_requested_at',
        'assignment_revision_requested_by',
        'assignment_approval_note',

        // Nhân công ngoài / External labor
        'external_labor_enabled',
        'external_labor_name',
        'external_labor_phone',
        'external_labor_headcount',
        'external_labor_total_cost',
        'external_labor_advance_amount',
        'external_labor_bank_info',
        'external_labor_note',
        'external_labor_payment_request_id',

        // Kỹ thuật / Technical
        'system_kwp',
        'inverter_info',
        'issue_note',
        'technical_note',
        'result_note',
        'plan_checklist',
        'external_labor_contact',
        'external_labor_estimated_cost',
        'execution_fault_note',
        'incident_kind',
        'incident_material_note',
        'incident_replacement_reason',
        'incident_estimated_cost',
        'completion_actual_cost',
        'completion_state',
        'report_conclusion',
        'execution_finished_at',

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
        'scheduled_start_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'completed_date' => 'date',
        'started_at' => 'datetime',
        'execution_finished_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reopened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'assigned_user_ids' => 'array',
        'assignment_submitted_at' => 'datetime',
        'assignment_approved_at' => 'datetime',
        'assignment_revision_requested_at' => 'datetime',
        'external_labor_enabled' => 'boolean',
        'external_labor_headcount' => 'integer',
        'external_labor_total_cost' => 'decimal:2',
        'external_labor_advance_amount' => 'decimal:2',
        'system_kwp' => 'decimal:2',
        'plan_checklist' => 'array',
        'external_labor_estimated_cost' => 'decimal:2',
        'incident_estimated_cost' => 'decimal:2',
        'completion_actual_cost' => 'decimal:2',
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

    public const ASSIGNMENT_APPROVAL_STATUSES = [
        'not_required' => 'Hồ sơ cũ - không yêu cầu',
        'draft' => 'Chưa gửi duyệt phân công',
        'pending' => 'Chờ sếp duyệt phân công',
        'approved' => 'Đã duyệt phân công',
        'revision_requested' => 'Yêu cầu sửa phân công',
        'rejected' => 'Từ chối phân công',
    ];

    public const APPROVAL_STATUSES = [
        'not_required' => 'Không yêu cầu duyệt',
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


    protected static function booted(): void
    {
        // EGO_MAINTENANCE_TECHNICAL_VISIBILITY_V2
        //
        // Kỹ thuật xem toàn bộ lịch O&M của các company.
        // Role khác vẫn chỉ thấy company đang làm việc.
        static::addGlobalScope(
            'maintenance_company_visibility',
            function (Builder $builder): void {

                $user = auth()->user();

                if (
                    $user
                    && \App\Support\SolarMaintenanceAccess::isTechnician($user)
                ) {
                    return;
                }

                $companyId = \App\Support\EgoCompanyScope::currentId();

                if ($companyId > 0) {
                    $builder->where(
                        $builder->getModel()->qualifyColumn('company_id'),
                        $companyId
                    );
                }
            }
        );

        static::saved(function (self $schedule): void {
            app(\App\Services\Technical\TechnicalScheduleSyncService::class)->syncMaintenance($schedule);
        });

        static::deleted(function (self $schedule): void {
            app(\App\Services\Technical\TechnicalScheduleSyncService::class)->removeMaintenance($schedule);
        });
    }


    public function maintenanceProfile(): BelongsTo
    {
        return $this->belongsTo(SolarMaintenanceProfile::class, 'maintenance_profile_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(SolarMaintenanceChecklistItem::class, 'maintenance_schedule_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Models\ProjectTest\Project::class, 'project_id');
    }

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

    public function assignmentSubmitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignment_submitted_by');
    }

    public function assignmentApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignment_approved_by');
    }

    public function assignmentRevisionRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignment_revision_requested_by');
    }

    public function externalLaborPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Payments\PaymentRequest::class, 'external_labor_payment_request_id');
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

    public function workItems(): HasMany
    {
        return $this->hasMany(SolarMaintenanceWorkItem::class, 'maintenance_schedule_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SolarMaintenanceComment::class, 'maintenance_schedule_id')
            ->latest('id');
    }

    public function warrantyClaims(): HasMany
    {
        return $this->hasMany(SolarWarrantyClaim::class, 'maintenance_schedule_id')
            ->latest('id');
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
