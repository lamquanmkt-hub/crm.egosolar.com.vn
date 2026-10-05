<?php

declare(strict_types=1);

namespace App\Services\Hr\Payroll;

use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tạo / tính lại bảng lương tháng (kỳ ở trạng thái nháp). Kỳ đã duyệt thì khoá, không tính lại.
 * Nhân viên có trong bảng lương = có Hồ sơ lương hiệu lực tại tháng đó.
 */
final class PayrollGenerator
{
    /** Các ô HR được sửa tay trên dòng lương (giữ nguyên khi Tính lại). */
    public const MANUAL_FIELDS = [
        'paid_days' => 'Ngày tính lương',
        'kpi_rate' => 'Hệ số KPI (%)',
        'other_income' => 'Khác (chuyên cần, sinh nhật)',
        'trip_allowance' => 'Công tác tỉnh',
        'ot_amount' => 'Tăng ca',
        'advance' => 'Tạm ứng',
        'late_penalty' => 'Phạt đi trễ',
        'other_deduction' => 'Trừ khác',
    ];

    public function __construct(
        private readonly PayrollAttendanceSummary $attendance,
        private readonly PayrollCalculator $calculator,
        private readonly HrKpiEvaluator $kpi,
    ) {}

    /** Hồ sơ lương hiệu lực tại tháng (bản ghi có tháng hiệu lực gần nhất, không sau tháng đang xét). */
    public static function profilesFor(string $month): Collection
    {
        $latest = DB::table('hr_salary_profiles')
            ->where('effective_month', '<=', $month)
            ->groupBy('user_id')
            ->select('user_id', DB::raw('MAX(effective_month) as effective_month'));

        return DB::table('hr_salary_profiles as p')
            ->joinSub($latest, 'l', fn ($j) => $j->on('l.user_id', '=', 'p.user_id')->on('l.effective_month', '=', 'p.effective_month'))
            ->select('p.*')
            ->get()
            ->keyBy('user_id');
    }

    public function generate(string $month, ?int $userId): object
    {
        return DB::transaction(function () use ($month, $userId) {
            $period = DB::table('hr_payroll_periods')->where('payroll_month', $month)->lockForUpdate()->first();
            if ($period && $period->status === 'approved') {
                throw new RuntimeException('Bảng lương tháng '.$month.' đã duyệt và khoá, không thể tính lại.');
            }

            $settings = PayrollSettings::all();
            $profiles = self::profilesFor($month);
            $userIds = $profiles->keys()->map(fn ($id) => (int) $id)->all();
            $summary = $this->attendance->forMonth($month, $userIds, $settings);
            $latePenaltyPerTime = $this->attendance->latePenaltyPerTime();
            $employees = $this->employees($userIds);

            if (! $period) {
                $periodId = DB::table('hr_payroll_periods')->insertGetId([
                    'payroll_month' => $month,
                    'status' => 'draft',
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $periodId = (int) $period->id;
            }

            $existing = DB::table('hr_payroll_lines')->where('period_id', $periodId)->get()->keyBy('user_id');
            $kept = [];

            foreach ($profiles as $uid => $profile) {
                $uid = (int) $uid;
                $employee = $employees->get($uid);
                $days = $summary['users'][$uid];
                // Tài khoản đã khoá và không có công trong tháng => không đưa vào bảng lương.
                if (! $employee || (! $employee->is_active && $days['worked_days'] + $days['paid_leave_days'] <= 0)) {
                    continue;
                }

                $manual = $existing->has($uid) ? (json_decode((string) $existing->get($uid)->manual, true) ?: []) : [];
                $line = $this->buildLine($periodId, $profile, $employee, $days, $manual, $settings, $latePenaltyPerTime, $month);
                $this->upsertLine($periodId, $uid, $line, $existing->has($uid));
                $kept[] = $uid;
            }

            DB::table('hr_payroll_lines')->where('period_id', $periodId)->whereNotIn('user_id', $kept ?: [0])->delete();

            DB::table('hr_payroll_periods')->where('id', $periodId)->update([
                'standard_days' => $summary['standard_days'],
                'settings_snapshot' => json_encode($settings + ['late_penalty_per_time' => $latePenaltyPerTime], JSON_UNESCAPED_UNICODE),
                'calculated_at' => now(),
                'updated_at' => now(),
            ]);

            return DB::table('hr_payroll_periods')->where('id', $periodId)->first();
        });
    }

    /** Lưu giá trị sửa tay của một dòng rồi tính lại đúng dòng đó. */
    public function updateManual(object $line, array $manual, ?string $note, ?int $editorId = null): void
    {
        $period = DB::table('hr_payroll_periods')->where('id', $line->period_id)->first();
        if (! $period || $period->status === 'approved') {
            throw new RuntimeException('Bảng lương đã duyệt và khoá, không thể sửa.');
        }

        $settings = (json_decode((string) $period->settings_snapshot, true) ?: []) + PayrollSettings::all();
        $profile = self::profilesFor($period->payroll_month)->get($line->user_id);
        $employee = $this->employees([(int) $line->user_id])->get($line->user_id);
        if (! $profile || ! $employee) {
            throw new RuntimeException('Nhân viên không còn hồ sơ lương hiệu lực trong tháng này.');
        }

        $days = $this->attendance->forMonth($period->payroll_month, [(int) $line->user_id], $settings)['users'][(int) $line->user_id];
        $built = $this->buildLine((int) $period->id, $profile, $employee, $days, $manual, $settings, (float) ($settings['late_penalty_per_time'] ?? 0), $period->payroll_month);
        $built['note'] = $note;
        $built['manual_updated_by'] = $editorId;
        $built['manual_updated_at'] = now();
        $this->upsertLine((int) $period->id, (int) $line->user_id, $built, true);
    }

    /** Dấu vân tay số tiền từng dòng của kỳ — dùng để phát hiện số liệu đổi khi tính lại trước lúc duyệt. */
    public static function fingerprint(int $periodId): string
    {
        return md5(DB::table('hr_payroll_lines')->where('period_id', $periodId)->orderBy('user_id')
            ->get(['user_id', 'gross_income', 'insurance_employee', 'pit', 'net_salary', 'kpi_rate'])
            ->map(fn ($l) => implode(':', [$l->user_id, round((float) $l->gross_income), round((float) $l->insurance_employee),
                round((float) $l->pit), round((float) $l->net_salary), $l->kpi_rate === null ? '-' : round((float) $l->kpi_rate, 4)]))
            ->implode('|'));
    }

    private function buildLine(int $periodId, object $profile, object $employee, array $days, array $manual, array $settings, float $latePenaltyPerTime, string $month): array
    {
        $pick = fn (string $key, float $auto) => array_key_exists($key, $manual) && $manual[$key] !== null && $manual[$key] !== '' ? (float) $manual[$key] : $auto;

        $kpi = $this->kpi->forEmployee((int) $profile->user_id, $month, $profile->kpi_template);
        $kpiRate = array_key_exists('kpi_rate', $manual) && $manual['kpi_rate'] !== null && $manual['kpi_rate'] !== ''
            ? (float) $manual['kpi_rate'] / 100
            : $kpi['rate'];

        $baseSalary = (float) $profile->base_salary;
        $standard = (float) $days['standard_days'];
        // Lương 1 giờ = lương chính ÷ công chuẩn ÷ 8; giờ tăng ca đã nhân hệ số theo loại ngày.
        $otAuto = (int) ($settings['ot_auto'] ?? 0) === 1 && $standard > 0
            ? round((float) ($days['ot_weighted_hours'] ?? 0) * $baseSalary / $standard / 8)
            : 0.0;

        $paidDays = $pick('paid_days', (float) $days['paid_days']);
        $input = [
            'standard_days' => $standard,
            'paid_days' => $paidDays,
            // Sửa tay ngày tính lương thì ngày không lương đổi theo (để xét mốc đóng BH).
            'unpaid_days' => max($standard - $paidDays, 0),
            'base_salary' => $baseSalary,
            'kpi_salary' => (float) $profile->kpi_salary,
            'decision_bonus' => (float) $profile->decision_bonus,
            'travel_allowance' => (float) $profile->travel_allowance,
            'phone_allowance' => (float) $profile->phone_allowance,
            'meal_allowance' => (float) $profile->meal_allowance,
            'insurance_salary' => (float) ($profile->insurance_salary ?? $profile->base_salary),
            'insurance_enabled' => (bool) $profile->insurance_enabled,
            'dependents' => (int) $profile->dependents,
            'pit_mode' => (string) $profile->pit_mode,
            'kpi_rate' => $kpiRate,
            'other_income' => $pick('other_income', 0.0),
            'trip_allowance' => $pick('trip_allowance', (float) $days['trip_allowance']),
            'ot_amount' => $pick('ot_amount', $otAuto),
            'advance' => $pick('advance', (float) $days['advance']),
            'late_penalty' => $pick('late_penalty', $days['late_count'] * $latePenaltyPerTime),
            'other_deduction' => $pick('other_deduction', 0.0),
        ];

        $warnings = [];
        if ($kpiRate === null && (float) $profile->kpi_salary > 0) {
            $warnings[] = 'Chưa có KPI: '.$kpi['label'];
        }
        if ($input['paid_days'] <= 0) {
            $warnings[] = 'Không có ngày công trong tháng';
        }

        $result = $this->calculator->calculate($input, $settings);

        return [
            'employee_name' => (string) $employee->name,
            'employee_code' => $employee->employee_code,
            'department_name' => $employee->department_name,
            'position_name' => $employee->position_name,
            'standard_days' => $standard,
            'worked_days' => $days['worked_days'],
            'paid_leave_days' => $days['paid_leave_days'],
            'holiday_days' => $days['holiday_days'],
            'unpaid_days' => $days['unpaid_days'],
            'paid_days' => $input['paid_days'],
            'late_count' => $days['late_count'],
            'ot_hours' => $days['ot_hours'],
            'base_salary' => $baseSalary,
            'kpi_salary' => $input['kpi_salary'],
            'decision_bonus' => $input['decision_bonus'],
            'travel_allowance' => $input['travel_allowance'],
            'phone_allowance' => $input['phone_allowance'],
            'meal_allowance' => $input['meal_allowance'],
            'insurance_salary' => $input['insurance_salary'],
            'dependents' => $input['dependents'],
            'pit_mode' => $input['pit_mode'],
            'kpi_template' => $profile->kpi_template,
            'kpi_percent' => $kpi['percent'],
            'kpi_rate' => $kpiRate,
            'kpi_label' => array_key_exists('kpi_rate', $manual) && $manual['kpi_rate'] !== null && $manual['kpi_rate'] !== ''
                ? 'Hệ số KPI nhập tay'
                : mb_substr($kpi['label'], 0, 190),
            'manual' => $manual === [] ? null : json_encode($manual, JSON_UNESCAPED_UNICODE),
            'warnings' => $warnings === [] ? null : json_encode($warnings, JSON_UNESCAPED_UNICODE),
        ] + $result;
    }

    private function upsertLine(int $periodId, int $userId, array $values, bool $exists): void
    {
        $key = ['period_id' => $periodId, 'user_id' => $userId];
        if ($exists) {
            DB::table('hr_payroll_lines')->where($key)->update($values + ['updated_at' => now()]);

            return;
        }

        DB::table('hr_payroll_lines')->insert($key + $values + ['created_at' => now(), 'updated_at' => now()]);
    }

    /** Thông tin nhân viên để chụp vào dòng lương: tên, mã NV, phòng ban, chức vụ, còn hoạt động. */
    private function employees(array $userIds): Collection
    {
        $query = DB::table('users as u')
            ->whereIn('u.id', $userIds ?: [0])
            ->select('u.id', 'u.name');

        $query->addSelect(DB::raw(SchemaCache::hasColumn('users', 'is_active') ? 'COALESCE(u.is_active, 1) as is_active' : '1 as is_active'));

        if (SchemaCache::hasTable('departments') && SchemaCache::hasColumn('users', 'department_id')) {
            $query->leftJoin('departments as d', 'd.id', '=', 'u.department_id')->addSelect('d.name as department_name');
        } else {
            $query->addSelect(DB::raw('NULL as department_name'));
        }
        if (SchemaCache::hasTable('positions') && SchemaCache::hasColumn('users', 'position_id')) {
            $query->leftJoin('positions as p', 'p.id', '=', 'u.position_id')->addSelect('p.name as position_name');
        } else {
            $query->addSelect(DB::raw('NULL as position_name'));
        }
        if (SchemaCache::hasTable('hr_employee_profiles')) {
            $query->leftJoin('hr_employee_profiles as ep', 'ep.employee_id', '=', 'u.id')->addSelect('ep.employee_code');
        } else {
            $query->addSelect(DB::raw('NULL as employee_code'));
        }

        return $query->get()->keyBy('id');
    }
}
