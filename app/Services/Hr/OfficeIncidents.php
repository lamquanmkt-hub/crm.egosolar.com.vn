<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Services\Hr\Payroll\PayrollAccess;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sự cố văn phòng — cài đặt, phân quyền, thống kê đúng hạn (SLA) cho KPI HCNS.
 *
 * Quy trình theo tài liệu KPI HCNS:
 *   1. Nhân viên báo sự cố (vị trí, mô tả, ảnh/video).
 *   2. HCNS phân loại mức độ (trong 30 phút) => hạn xử lý: Mức 1 2 giờ, Mức 2 8 giờ, Mức 3 48 giờ
 *      (tính từ lúc báo). Chi phí trên 500.000đ phải Giám đốc duyệt trước khi sửa.
 *   3. HCNS tự sửa hoặc gọi thợ, giám sát.
 *   4. Người báo kiểm tra, chấm sao => đóng; chưa đạt => quay lại xử lý.
 * Cài đặt lưu trong bảng hr_payroll_settings với khoá tiền tố "incident_".
 */
final class OfficeIncidents
{
    public const STATUSES = [
        'new' => 'Chờ phân loại',
        'waiting_approval' => 'Chờ Giám đốc duyệt chi',
        'in_progress' => 'Đang xử lý',
        'waiting_acceptance' => 'Chờ nghiệm thu',
        'closed' => 'Đã đóng',
        'cancelled' => 'Đã huỷ',
    ];

    public const LEVELS = [
        1 => 'Mức 1 — Khẩn cấp',
        2 => 'Mức 2 — Bình thường',
        3 => 'Mức 3 — Thấp',
    ];

    public const LEVEL_EXAMPLES = [
        1 => 'Mất điện toàn công ty, mất mạng Internet, vỡ ống nước ngập sàn, hỏng cửa cuốn không ra vào được',
        2 => 'Hỏng điều hoà phòng họp, cháy bóng đèn, tắc nhà vệ sinh, hỏng máy in / máy photo',
        3 => 'Ghế kẹt bánh xe, cửa kính lỏng bản lề, rèm kẹt, cây xanh héo',
    ];

    public const DEFAULTS = [
        'incident_cost_approval_threshold' => 500000,
        'incident_classify_minutes' => 30,
        'incident_sla_hours_1' => 2,
        'incident_sla_hours_2' => 8,
        'incident_sla_hours_3' => 48,
    ];

    public const LABELS = [
        'incident_cost_approval_threshold' => 'Chi phí trên mức này phải Giám đốc duyệt (đồng)',
        'incident_classify_minutes' => 'HCNS phân loại trong vòng (phút)',
        'incident_sla_hours_1' => 'Hạn xử lý Mức 1 (giờ)',
        'incident_sla_hours_2' => 'Hạn xử lý Mức 2 (giờ)',
        'incident_sla_hours_3' => 'Hạn xử lý Mức 3 (giờ)',
    ];

    /** Xử lý sự cố: HCNS, Admin. */
    public const HANDLE_ROLES = ['admin', 'super_admin', 'hr'];

    /** @return array<string, float> */
    public static function settings(): array
    {
        $values = self::DEFAULTS;
        if (SchemaCache::hasTable('hr_payroll_settings')) {
            $stored = DB::table('hr_payroll_settings')->whereIn('setting_key', array_keys(self::DEFAULTS))->pluck('setting_value', 'setting_key');
            foreach ($stored as $key => $value) {
                if ($value !== null && $value !== '') {
                    $values[$key] = (float) $value;
                }
            }
        }

        return $values;
    }

    public static function saveSettings(array $input, ?int $userId): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            if (array_key_exists($key, $input)) {
                DB::table('hr_payroll_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => (string) $input[$key], 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public static function dueAt(Carbon $reportedAt, int $level, ?array $settings = null): Carbon
    {
        $settings ??= self::settings();

        return $reportedAt->copy()->addMinutes((int) round((float) ($settings['incident_sla_hours_'.$level] ?? 24) * 60));
    }

    public static function needsApproval(float $cost, ?array $settings = null): bool
    {
        $settings ??= self::settings();

        return $cost > (float) $settings['incident_cost_approval_threshold'];
    }

    public static function canHandle($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::HANDLE_ROLES);
    }

    public static function canApprove($user): bool
    {
        return PayrollAccess::canApprove($user);
    }

    public static function canView($user, object $incident): bool
    {
        return (int) $incident->reporter_id === (int) $user->id || self::canHandle($user) || self::canApprove($user);
    }

    /**
     * Số vụ xử lý xong trong tháng (theo lần báo xong gần nhất) và số vụ đúng hạn — số liệu cho KPI HCNS.
     *
     * @return array{total: int, on_time: int, percent: ?float, late_open: int}
     */
    public static function slaStats(string $month): array
    {
        if (! SchemaCache::hasTable('hr_office_incidents')) {
            return ['total' => 0, 'on_time' => 0, 'percent' => null, 'late_open' => 0];
        }

        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();

        $done = DB::table('hr_office_incidents')
            ->whereIn('status', ['waiting_acceptance', 'closed'])
            ->whereNotNull('due_at')
            ->whereBetween('resolved_at', [$start, $end])
            ->get(['resolved_at', 'due_at']);
        $onTime = $done->filter(fn ($i) => Carbon::parse($i->resolved_at)->lte(Carbon::parse($i->due_at)))->count();

        // Vụ quá hạn mà vẫn chưa xử lý xong (hạn rơi trong tháng) cũng tính là trễ.
        $lateOpen = DB::table('hr_office_incidents')
            ->whereIn('status', ['new', 'waiting_approval', 'in_progress'])
            ->whereBetween('due_at', [$start, $end->lt(now()) ? $end : now()])
            ->count();

        $total = $done->count() + $lateOpen;

        return [
            'total' => $total,
            'on_time' => $onTime,
            'percent' => $total > 0 ? round($onTime / $total * 100, 2) : null,
            'late_open' => $lateOpen,
        ];
    }

    /** Tài khoản có vai trò xử lý sự cố (để chọn người xử lý chịu KPI HC06). */
    public static function handlerOptions(): \Illuminate\Support\Collection
    {
        if (! SchemaCache::hasTable('model_has_roles') || ! SchemaCache::hasTable('roles')) {
            return collect();
        }

        return DB::table('users as u')
            ->join('model_has_roles as mr', fn ($j) => $j->on('mr.model_id', '=', 'u.id')->where('mr.model_type', \App\Models\User::class))
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->whereIn('r.name', self::HANDLE_ROLES)
            ->select('u.id', 'u.name')->distinct()->orderBy('u.name')->get();
    }

    /** Cột theo dõi KPI HC06 đã có (migration HC06). */
    public static function kpiColumnsReady(): bool
    {
        return \App\Services\Hr\Kpi\Sources\AssetsServiceSource::incidentsReady()
            && SchemaCache::hasColumn('hr_office_incidents', 'company_id')
            && SchemaCache::hasColumn('hr_office_incidents', 'cancelled_at');
    }

    public static function log(int $incidentId, ?int $userId, string $action, ?string $note = null): void
    {
        DB::table('hr_office_incident_logs')->insert([
            'incident_id' => $incidentId, 'user_id' => $userId, 'action' => $action,
            'note' => $note !== null ? mb_substr($note, 0, 1000) : null, 'created_at' => now(),
        ]);
    }

    public static function nextCode(): string
    {
        $prefix = 'SC-'.now()->format('ym').'-';
        $last = DB::table('hr_office_incidents')->where('code', 'like', $prefix.'%')->orderByDesc('code')->value('code');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }
}
