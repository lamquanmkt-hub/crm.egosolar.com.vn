<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Kế hoạch tuần của MỘT nhân viên kỹ thuật (thứ Hai → Chủ nhật).
 *
 * Trạng thái tuần theo nghiệp vụ:
 *   - "Chưa lập"  = KHÔNG có bản ghi nào (hoặc có nhưng chưa có dòng kế hoạch).
 *   - draft       = Đang lập.
 *   - finalized   = Đã hoàn tất (nhân viên tự chốt, KHÔNG cần ai duyệt).
 *   - adjusted    = Đã được Trưởng phòng điều chỉnh.
 */
class TechnicalWeekPlan extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    public const STATUS_ADJUSTED = 'adjusted';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Đang lập',
        self::STATUS_FINALIZED => 'Đã hoàn tất',
        self::STATUS_ADJUSTED => 'Đã được Trưởng phòng điều chỉnh',
    ];

    public const STATUS_TONES = [
        self::STATUS_DRAFT => 'secondary',
        self::STATUS_FINALIZED => 'success',
        self::STATUS_ADJUSTED => 'warning',
    ];

    /** Nhãn khi nhân viên chưa có kế hoạch nào cho tuần. */
    public const LABEL_NOT_STARTED = 'Chưa lập';

    protected $table = 'technical_week_plans';

    protected $fillable = [
        'company_id',
        'user_id',
        'user_name',
        'week_start',
        'week_end',
        'status',
        'finalized_at',
        'finalized_by',
        'adjusted_at',
        'adjusted_by',
        'adjust_note',
        'update_requested',
        'update_request_note',
        'update_requested_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'finalized_at' => 'datetime',
        'adjusted_at' => 'datetime',
        'update_requested' => 'boolean',
        'update_requested_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TechnicalPlanItem::class, 'week_plan_id');
    }

    public function dayMarks(): HasMany
    {
        return $this->hasMany(TechnicalPlanDayMark::class, 'week_plan_id');
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? (string) $this->status;
    }

    public function statusTone(): string
    {
        return self::STATUS_TONES[$this->status] ?? 'secondary';
    }

    public function isFinalized(): bool
    {
        return in_array($this->status, [self::STATUS_FINALIZED, self::STATUS_ADJUSTED], true);
    }

    /** Thứ Hai của tuần chứa `$date` — chuẩn hoá dùng chung toàn module. */
    public static function weekStartFor(Carbon $date): Carbon
    {
        return $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** 7 ngày thứ Hai → Chủ nhật của tuần chứa `$date`. */
    public static function weekDays(Carbon $date): array
    {
        $start = self::weekStartFor($date);
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $days[] = $start->copy()->addDays($i);
        }

        return $days;
    }
}
