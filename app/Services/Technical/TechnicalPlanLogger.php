<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\Technical\TechnicalPlanHistory;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use Illuminate\Http\Request;

/**
 * Nhật ký kế hoạch tuần.
 *
 * Cùng nguyên tắc với `TechnicalDailyReportLogger` / `PaymentRequestAuditLogger`
 * của đợt P0:
 *   - Chỉ THÊM dòng.
 *   - Gọi TRONG cùng `DB::transaction()` với thay đổi dữ liệu và KHÔNG nuốt
 *     lỗi: nếu ghi nhật ký hỏng thì thay đổi dữ liệu cũng rollback.
 *
 * MỌI điều chỉnh của trưởng phòng BẮT BUỘC có lý do (xem `reasonRules()`), và
 * lý do đó được lưu vào chính dòng nhật ký này.
 */
class TechnicalPlanLogger
{
    public const REASON_MIN_LENGTH = 5;

    /** Các trường được chụp ảnh trước/sau khi một dòng kế hoạch thay đổi. */
    public const TRACKED_FIELDS = [
        'plan_date', 'day_part', 'start_time', 'end_time', 'title', 'objective',
        'note', 'source_type', 'source_id', 'site_id', 'site_name',
        'estimated_minutes', 'priority', 'status', 'progress_percent',
    ];

    /** @return array<int, string> */
    public static function reasonRules(bool $required = true): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            'string',
            'min:'.self::REASON_MIN_LENGTH,
            'max:2000',
        ]));
    }

    /** @return array<string, string> */
    public static function reasonMessages(string $field = 'reason'): array
    {
        return [
            $field.'.required' => 'Bắt buộc nhập lý do điều chỉnh.',
            $field.'.min' => 'Lý do phải có ít nhất '.self::REASON_MIN_LENGTH.' ký tự.',
        ];
    }

    /**
     * Ghi một dòng nhật ký.
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function log(
        string $action,
        ?TechnicalWeekPlan $plan = null,
        ?TechnicalPlanItem $item = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?int $targetUserId = null,
    ): void {
        $actor = auth()->user();

        TechnicalPlanHistory::create([
            'company_id' => $plan?->company_id ?? $item?->company_id,
            'week_plan_id' => $plan?->id ?? $item?->week_plan_id,
            'plan_item_id' => $item?->id,
            'target_user_id' => $targetUserId ?? $plan?->user_id ?? $item?->user_id,
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'action' => $action,
            'changes_before' => self::encode($before),
            'changes_after' => self::encode($after),
            'reason' => $reason,
            'ip_address' => self::ip(),
            'user_agent' => self::userAgent(),
        ]);
    }

    /**
     * Ảnh chụp các trường theo dõi của một dòng kế hoạch.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(TechnicalPlanItem $item): array
    {
        $snapshot = [];

        foreach (self::TRACKED_FIELDS as $field) {
            $value = $item->getAttribute($field);

            $snapshot[$field] = $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $value;
        }

        return $snapshot;
    }

    /**
     * Chỉ giữ những trường THỰC SỰ khác nhau giữa hai ảnh chụp — nhật ký đọc
     * được ngay, không phải dò thủ công.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $before, array $after): array
    {
        $changedBefore = [];
        $changedAfter = [];

        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;

            if ((string) $old !== (string) $value) {
                $changedBefore[$field] = $old;
                $changedAfter[$field] = $value;
            }
        }

        return [$changedBefore, $changedAfter];
    }

    /** @param array<string, mixed>|null $data */
    private static function encode(?array $data): ?string
    {
        if ($data === null || $data === []) {
            return null;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private static function ip(): ?string
    {
        try {
            return app()->bound('request') ? app(Request::class)->ip() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function userAgent(): ?string
    {
        try {
            $agent = app()->bound('request') ? app(Request::class)->userAgent() : null;

            return $agent !== null ? mb_substr((string) $agent, 0, 1000) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
