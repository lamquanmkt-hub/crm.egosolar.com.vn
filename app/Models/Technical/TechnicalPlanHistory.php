<?php

declare(strict_types=1);

namespace App\Models\Technical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nhật ký điều chỉnh kế hoạch tuần: ai / trước / sau / thời gian / lý do.
 *
 * Chỉ THÊM dòng, không sửa, không xoá. Không có khoá ngoại tới dòng kế hoạch
 * để nhật ký sống lâu hơn bản ghi bị xoá.
 */
class TechnicalPlanHistory extends Model
{
    private const FIELD_LABELS = [
        'plan_date' => 'Ngày thực hiện',
        'day_part' => 'Buổi',
        'start_time' => 'Giờ bắt đầu',
        'end_time' => 'Giờ kết thúc',
        'title' => 'Nội dung',
        'objective' => 'Mục tiêu',
        'note' => 'Ghi chú',
        'source_type' => 'Nguồn',
        'site_name' => 'Công trình',
        'estimated_minutes' => 'Thời lượng',
        'priority' => 'Ưu tiên',
        'status' => 'Trạng thái',
        'progress_percent' => 'Tiến độ',
        'update_requested' => 'Yêu cầu cập nhật',
    ];

    public const ACTION_CREATE = 'create';

    public const ACTION_UPDATE = 'update';

    public const ACTION_MOVE = 'move';

    public const ACTION_COPY = 'copy';

    public const ACTION_COPY_WEEK = 'copy_week';

    public const ACTION_DELETE = 'delete';

    public const ACTION_STATUS = 'status';

    public const ACTION_ASSIGN = 'assign';

    public const ACTION_FINALIZE = 'finalize';

    public const ACTION_ADJUST = 'adjust';

    public const ACTION_REQUEST_UPDATE = 'request_update';

    public const ACTION_DAY_MARK = 'day_mark';

    public const ACTION_LABELS = [
        self::ACTION_CREATE => 'Thêm việc',
        self::ACTION_UPDATE => 'Sửa việc',
        self::ACTION_MOVE => 'Chuyển ngày',
        self::ACTION_COPY => 'Sao chép việc',
        self::ACTION_COPY_WEEK => 'Sao chép tuần trước',
        self::ACTION_DELETE => 'Xoá việc',
        self::ACTION_STATUS => 'Đổi trạng thái',
        self::ACTION_ASSIGN => 'Trưởng phòng giao việc',
        self::ACTION_FINALIZE => 'Hoàn tất kế hoạch tuần',
        self::ACTION_ADJUST => 'Trưởng phòng điều chỉnh',
        self::ACTION_REQUEST_UPDATE => 'Yêu cầu cập nhật kế hoạch',
        self::ACTION_DAY_MARK => 'Đánh dấu ngày',
    ];

    protected $table = 'technical_plan_histories';

    protected $fillable = [
        'company_id',
        'week_plan_id',
        'plan_item_id',
        'target_user_id',
        'user_id',
        'user_name',
        'action',
        'changes_before',
        'changes_after',
        'reason',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? (string) $this->action;
    }

    /** @return array<string, mixed> */
    public function beforeArray(): array
    {
        return $this->decode($this->changes_before);
    }

    /** @return array<string, mixed> */
    public function afterArray(): array
    {
        return $this->decode($this->changes_after);
    }

    /** @return array<string, string> */
    public function formattedBefore(): array
    {
        return $this->formatChanges($this->beforeArray());
    }

    /** @return array<string, string> */
    public function formattedAfter(): array
    {
        return $this->formatChanges($this->afterArray());
    }

    /** @param array<string, mixed> $changes
     *  @return array<string, string>
     */
    private function formatChanges(array $changes): array
    {
        $formatted = [];

        foreach ($changes as $field => $value) {
            if (! array_key_exists($field, self::FIELD_LABELS)) {
                continue;
            }

            $label = self::FIELD_LABELS[$field];
            $display = match ($field) {
                'plan_date' => $this->formatDate($value),
                'day_part' => (string) (config('technical.day_parts.'.(string) $value) ?? $value),
                'priority' => (string) (config('technical.priorities.'.(string) $value) ?? $value),
                'status' => (string) (TechnicalPlanItem::STATUS_LABELS[(string) $value] ?? $value),
                'estimated_minutes' => is_numeric($value) ? $this->minutesLabel((int) $value) : '—',
                'progress_percent' => is_numeric($value) ? ((int) $value).'%' : '—',
                'update_requested' => (bool) $value ? 'Có' : 'Không',
                default => $this->clean((string) ($value ?? '')),
            };

            $formatted[$label] = $display !== '' ? $display : '—';
        }

        return $formatted;
    }

    private function formatDate(mixed $value): string
    {
        try {
            return \Illuminate\Support\Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return $this->clean((string) ($value ?? '')) ?: '—';
        }
    }

    private function minutesLabel(int $minutes): string
    {
        $hours = intdiv(max(0, $minutes), 60);
        $rest = max(0, $minutes) % 60;

        return $rest === 0 ? $hours.' giờ' : ($hours > 0 ? $hours.' giờ '.$rest.' phút' : $rest.' phút');
    }

    private function clean(string $value): string
    {
        return trim((string) (preg_replace('/^\[LOCAL TEST\]\s*/iu', '', trim($value)) ?? $value));
    }

    /** @return array<string, mixed> */
    private function decode(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
