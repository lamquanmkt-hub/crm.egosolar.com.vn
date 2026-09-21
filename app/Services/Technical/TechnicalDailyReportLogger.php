<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalDailyReportHistory;
use Illuminate\Http\Request;

/**
 * Ghi nhật ký cho báo cáo ngày kỹ thuật.
 *
 * Nguyên tắc giống `PaymentRequestAuditLogger` của đợt P0:
 * - Chỉ THÊM dòng, không sửa, không xoá.
 * - Được gọi TRONG cùng `DB::transaction()` với thay đổi dữ liệu và KHÔNG
 *   nuốt lỗi: nếu ghi nhật ký hỏng thì thay đổi dữ liệu cũng phải rollback.
 *   Không bao giờ được phép có thay đổi trạng thái báo cáo mà thiếu dòng log.
 */
class TechnicalDailyReportLogger
{
    /** Độ dài tối thiểu của lý do khi yêu cầu sửa / mở lại báo cáo. */
    public const REASON_MIN_LENGTH = 5;

    /** @return array<int, string> */
    public static function reasonRules(): array
    {
        return ['required', 'string', 'min:'.self::REASON_MIN_LENGTH, 'max:2000'];
    }

    /** @return array<string, string> */
    public static function reasonMessages(): array
    {
        return [
            'note.required' => 'Bắt buộc nhập lý do / ý kiến.',
            'note.min' => 'Lý do phải có ít nhất '.self::REASON_MIN_LENGTH.' ký tự.',
        ];
    }

    public static function log(
        TechnicalDailyReport $report,
        string $action,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $note = null,
    ): void {
        $user = auth()->user();

        TechnicalDailyReportHistory::create([
            'report_id' => $report->id,
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'action' => $action,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'note' => $note,
            'ip_address' => self::ip(),
            'user_agent' => self::userAgent(),
        ]);
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
