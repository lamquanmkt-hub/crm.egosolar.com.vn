<?php

declare(strict_types=1);

namespace App\Models\Technical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Đánh dấu một NGÀY không có kế hoạch một cách hợp lệ.
 *
 * Nhờ bản ghi này mà bước "Hoàn tất kế hoạch tuần" phân biệt được ngày nghỉ có
 * chủ đích với ngày bị bỏ quên.
 */
class TechnicalPlanDayMark extends Model
{
    public const MARK_DAY_OFF = 'day_off';

    public const MARK_LEAVE = 'leave';

    public const MARK_AWAITING = 'awaiting_assignment';

    public const MARK_NO_PLAN = 'no_plan';

    public const MARK_LABELS = [
        self::MARK_DAY_OFF => 'Nghỉ theo lịch',
        self::MARK_LEAVE => 'Nghỉ phép',
        self::MARK_AWAITING => 'Chờ phân công',
        self::MARK_NO_PLAN => 'Không có kế hoạch',
    ];

    protected $table = 'technical_plan_day_marks';

    protected $fillable = [
        'company_id',
        'week_plan_id',
        'user_id',
        'plan_date',
        'mark',
        'reason',
    ];

    protected $casts = [
        'plan_date' => 'date',
    ];

    public function weekPlan(): BelongsTo
    {
        return $this->belongsTo(TechnicalWeekPlan::class, 'week_plan_id');
    }

    public function markLabel(): string
    {
        return self::MARK_LABELS[$this->mark] ?? (string) $this->mark;
    }
}
