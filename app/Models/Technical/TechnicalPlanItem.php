<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use App\Support\Technical\TechnicalWorkItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Một DÒNG kế hoạch trong ngày.
 *
 * Dòng kế hoạch KHÔNG sao chép công việc nguồn: nó chỉ giữ liên kết
 * (source_type, source_id, site_id) + thông tin điều phối do nhân viên/trưởng
 * phòng nhập (buổi, mục tiêu, thời lượng dự kiến, ưu tiên, ghi chú).
 */
class TechnicalPlanItem extends Model
{
    use SoftDeletes;

    /* Nguồn công việc — 3 nguồn thật tái dùng từ giai đoạn 1, + 2 nguồn nội bộ. */
    public const SOURCE_PERSONAL = 'personal';

    public const SOURCE_MANAGER_ASSIGNED = 'manager_assigned';

    /** Nguồn phải được xác thực lại với feed (việc ĐÃ ĐƯỢC GIAO cho chính người đó). */
    public const FEED_SOURCES = [
        TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW,
        TechnicalWorkItem::SOURCE_TASK,
        TechnicalWorkItem::SOURCE_MAINTENANCE,
    ];

    public const SOURCE_LABELS = [
        TechnicalWorkItem::SOURCE_PROJECT_WORKFLOW => 'Công trình',
        TechnicalWorkItem::SOURCE_TASK => 'Task nội bộ',
        TechnicalWorkItem::SOURCE_MAINTENANCE => 'Bảo trì / Bảo hành',
        self::SOURCE_PERSONAL => 'Việc nội bộ cá nhân',
        self::SOURCE_MANAGER_ASSIGNED => 'Trưởng phòng giao thêm',
    ];

    public const STATUS_PLANNED = 'planned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    public const STATUS_NOT_DONE = 'not_done';

    public const STATUS_MOVED = 'moved';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PLANNED => 'Dự kiến',
        self::STATUS_IN_PROGRESS => 'Đang thực hiện',
        self::STATUS_DONE => 'Hoàn thành',
        self::STATUS_NOT_DONE => 'Chưa hoàn thành',
        self::STATUS_MOVED => 'Chuyển sang ngày sau',
        self::STATUS_CANCELLED => 'Đã huỷ',
    ];

    public const STATUS_TONES = [
        self::STATUS_PLANNED => 'secondary',
        self::STATUS_IN_PROGRESS => 'info',
        self::STATUS_DONE => 'success',
        self::STATUS_NOT_DONE => 'danger',
        self::STATUS_MOVED => 'warning',
        self::STATUS_CANCELLED => 'secondary',
    ];

    /** Trạng thái còn phải làm — dùng cho cảnh báo và thống kê. */
    public const OPEN_STATUSES = [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS];

    /** Trạng thái được tính vào mẫu số "công việc theo kế hoạch". */
    public const COUNTED_STATUSES = [
        self::STATUS_PLANNED, self::STATUS_IN_PROGRESS,
        self::STATUS_DONE, self::STATUS_NOT_DONE, self::STATUS_MOVED,
    ];

    protected $table = 'technical_plan_items';

    protected $fillable = [
        'company_id',
        'week_plan_id',
        'user_id',
        'user_name',
        'plan_date',
        'day_part',
        'start_time',
        'end_time',
        'title',
        'objective',
        'note',
        'source_type',
        'source_id',
        'site_id',
        'site_name',
        'estimated_minutes',
        'priority',
        'status',
        'progress_percent',
        'source_due_at',
        'created_by',
        'created_by_name',
        'is_manager_assigned',
        'assigned_by',
        'assigned_at',
        'moved_from_date',
        'moved_to_date',
        'active_flag',
    ];

    protected $casts = [
        'plan_date' => 'date',
        'moved_from_date' => 'date',
        'moved_to_date' => 'date',
        'source_due_at' => 'datetime',
        'assigned_at' => 'datetime',
        'estimated_minutes' => 'integer',
        'progress_percent' => 'integer',
        'is_manager_assigned' => 'boolean',
    ];

    /**
     * `active_flag` là một nửa của UNIQUE chống trùng
     * (user_id, plan_date, source_type, source_id, active_flag).
     *
     * Dòng còn sống mang 1 nên ràng buộc có hiệu lực; dòng đã soft-delete
     * mang NULL nên KHÔNG chặn nhân viên lập lại đúng việc đó về sau.
     */
    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->active_flag = 1;
        });

        static::deleted(function (self $item): void {
            if ($item->isForceDeleting()) {
                return;
            }

            static::withTrashed()->whereKey($item->getKey())->update(['active_flag' => null]);
        });

        static::restored(function (self $item): void {
            static::withTrashed()->whereKey($item->getKey())->update(['active_flag' => 1]);
        });
    }

    public function weekPlan(): BelongsTo
    {
        return $this->belongsTo(TechnicalWeekPlan::class, 'week_plan_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUS_TONES[$this->status] ?? 'secondary';
    }

    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->source_type] ?? (string) $this->source_type;
    }

    public function dayPartLabel(): string
    {
        if ($this->day_part === 'custom' && $this->start_time) {
            return substr((string) $this->start_time, 0, 5)
                .($this->end_time ? ' – '.substr((string) $this->end_time, 0, 5) : '');
        }

        return (string) (config('technical.day_parts')[$this->day_part] ?? $this->day_part);
    }

    public function priorityLabel(): string
    {
        return (string) (config('technical.priorities')[$this->priority] ?? $this->priority);
    }

    public function estimatedHours(): ?float
    {
        return $this->estimated_minutes !== null
            ? round($this->estimated_minutes / 60, 2)
            : null;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    /** Có gắn với một công việc nguồn thật hay không. */
    public function hasFeedSource(): bool
    {
        return in_array($this->source_type, self::FEED_SOURCES, true) && $this->source_id !== null;
    }
}
