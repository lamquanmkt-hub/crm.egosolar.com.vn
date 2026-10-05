<?php

declare(strict_types=1);

namespace App\Services\Hr\Payroll;

use App\Models\AttendanceSetting;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tổng hợp dữ liệu đầu vào bảng lương của một tháng từ các phân hệ đã có (CHỈ ĐỌC):
 *
 *   Ngày công chuẩn = ngày làm việc theo Cài đặt chấm công + ngày lễ có hưởng lương rơi vào ngày làm việc
 *                     (giống ô "Ngày công chuẩn" trong file Excel: lễ được đánh NL và vẫn tính công).
 *   Mỗi ngày làm việc của nhân viên (tổng = 1):
 *     - Nghỉ lễ hưởng lương                            => holiday (NL)
 *     - Đơn nghỉ đã duyệt, loại hưởng lương            => paid_leave (P)
 *     - Đơn nghỉ đã duyệt, loại không lương / ốm       => unpaid (Ro)
 *     - Phần còn lại: có check-in, đơn làm online/công tác, chuyến công tác đã duyệt => worked (x / CT)
 *                      ngược lại => unpaid (vắng)
 *   Ngày tính lương = worked + paid_leave + holiday.
 *
 * Kèm theo: số lần đi trễ (trừ ngày có đơn xin đi trễ đã duyệt), giờ tăng ca đã duyệt,
 * phụ cấp công tác (chuyến công tác kết thúc trong tháng) và tạm ứng lương đã chi.
 */
final class PayrollAttendanceSummary
{
    private ?AttendanceSetting $setting = null;

    private array $saturdayCustomDates = [];

    /** @var array<string, object> ngày lễ trong khoảng đang xét, key Y-m-d */
    private array $holidays = [];

    /**
     * @param  array<int, int>  $userIds
     * @return array{standard_days: float, calendar: array, users: array<int, array<string, float|int>>}
     */
    public function forMonth(string $month, array $userIds, array $settings): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $this->boot($start->copy()->subMonth(), $end->copy()->addMonth());

        $calendar = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->toDateString();
            $holiday = $this->holidays[$key] ?? null;
            $calendar[$key] = match (true) {
                $holiday && $this->isRegularWorkday($day) && (bool) ($holiday->is_paid ?? true) => 'holiday',
                $holiday !== null => 'off',
                $this->isRegularWorkday($day) => 'work',
                default => 'off',
            };
        }

        $standardDays = (float) count(array_filter($calendar, fn ($type) => $type !== 'off'));
        $holidayDays = (float) count(array_filter($calendar, fn ($type) => $type === 'holiday'));
        $workdays = array_keys(array_filter($calendar, fn ($type) => $type === 'work'));

        $attended = $this->attendedDays($userIds, $start, $end);
        $leaves = $this->leaveAllocations($userIds, $start, $end, PayrollSettings::paidLeaveTypes($settings));
        $trips = $this->tripDays($userIds, $start, $end);
        $lateCounts = $this->lateCounts($userIds, $start, $end, $workdays);
        $overtime = $this->overtime($userIds, $start, $end, $calendar, $settings);
        $tripAllowances = $this->tripAllowances($userIds, $start, $end);
        $advances = $this->salaryAdvances($userIds, $month);

        $users = [];
        foreach ($userIds as $userId) {
            $worked = $paidLeave = $unpaid = 0.0;

            foreach ($workdays as $date) {
                $leave = $leaves[$userId][$date] ?? ['paid' => 0.0, 'unpaid' => 0.0, 'worked' => 0.0];
                $p = min(1.0, $leave['paid']);
                $u = min(1.0 - $p, $leave['unpaid']);
                $rest = 1.0 - $p - $u;
                $present = isset($attended[$userId][$date]) || isset($trips[$userId][$date]) || $leave['worked'] > 0;

                $paidLeave += $p;
                $unpaid += $u;
                if ($present) {
                    $worked += $rest;
                } else {
                    $unpaid += $rest;
                }
            }

            $users[$userId] = [
                'standard_days' => $standardDays,
                'worked_days' => $worked,
                'paid_leave_days' => $paidLeave,
                'holiday_days' => $holidayDays,
                'unpaid_days' => $unpaid,
                'paid_days' => $worked + $paidLeave + $holidayDays,
                'late_count' => (int) ($lateCounts[$userId] ?? 0),
                'ot_hours' => (float) ($overtime[$userId]['hours'] ?? 0),
                'ot_weighted_hours' => (float) ($overtime[$userId]['weighted'] ?? 0),
                'trip_allowance' => (float) ($tripAllowances[$userId] ?? 0),
                'advance' => (float) ($advances[$userId] ?? 0),
            ];
        }

        return ['standard_days' => $standardDays, 'calendar' => $calendar, 'users' => $users];
    }

    public function latePenaltyPerTime(): float
    {
        return SchemaCache::hasColumn('attendance_settings', 'late_penalty_per_time')
            ? (float) ($this->setting?->late_penalty_per_time ?? AttendanceSetting::query()->value('late_penalty_per_time') ?? 0)
            : 0.0;
    }

    private function boot(Carbon $from, Carbon $to): void
    {
        $this->setting = SchemaCache::hasTable('attendance_settings') ? AttendanceSetting::query()->first() : null;

        $raw = $this->setting->saturday_custom_dates ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : preg_split('/[\s,;]+/', $raw);
        }
        $this->saturdayCustomDates = [];
        foreach ((array) $raw as $date) {
            try {
                $this->saturdayCustomDates[] = Carbon::parse((string) $date)->toDateString();
            } catch (\Throwable) {
            }
        }

        $this->holidays = [];
        if (SchemaCache::hasTable('attendance_holidays')) {
            foreach (DB::table('attendance_holidays')->whereBetween('holiday_date', [$from->toDateString(), $to->toDateString()])->get() as $h) {
                $this->holidays[Carbon::parse($h->holiday_date)->toDateString()] = $h;
            }
        }
    }

    /** Ngày làm việc theo Cài đặt chấm công (chưa xét ngày lễ) — cùng quy tắc với Bảng chấm công. */
    private function isRegularWorkday(Carbon $day): bool
    {
        $s = $this->setting;

        return match ($day->dayOfWeekIso) {
            1 => (bool) ($s->workday_monday ?? true),
            2 => (bool) ($s->workday_tuesday ?? true),
            3 => (bool) ($s->workday_wednesday ?? true),
            4 => (bool) ($s->workday_thursday ?? true),
            5 => (bool) ($s->workday_friday ?? true),
            6 => match ($s->saturday_mode ?? 'off') {
                'all' => true,
                'odd' => (int) ceil($day->day / 7) % 2 === 1,
                'even' => (int) ceil($day->day / 7) % 2 === 0,
                'custom' => in_array($day->toDateString(), $this->saturdayCustomDates, true),
                default => false,
            },
            default => (bool) ($s->workday_sunday ?? false),
        };
    }

    private function isWorkday(Carbon $day): bool
    {
        return $this->isRegularWorkday($day) && ! isset($this->holidays[$day->toDateString()]);
    }

    /** @return array<int, array<string, true>> */
    private function attendedDays(array $userIds, Carbon $start, Carbon $end): array
    {
        $out = [];
        $rows = DB::table('attendance_records')
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in_at')
            ->get(['user_id', 'work_date']);
        foreach ($rows as $r) {
            $out[(int) $r->user_id][Carbon::parse($r->work_date)->toDateString()] = true;
        }

        return $out;
    }

    /**
     * Phân bổ số ngày của từng đơn đã duyệt vào các ngày làm việc trong khoảng đơn (đơn nửa ngày => 0,5).
     *
     * @return array<int, array<string, array{paid: float, unpaid: float, worked: float}>>
     */
    private function leaveAllocations(array $userIds, Carbon $start, Carbon $end, array $paidTypes): array
    {
        if (! SchemaCache::hasTable('leave_requests')) {
            return [];
        }

        $out = [];
        $rows = DB::table('leave_requests')
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereIn('request_type', ['leave', 'wfh', 'business_trip'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();

        foreach ($rows as $r) {
            $category = match (true) {
                in_array($r->request_type, ['wfh', 'business_trip'], true) => 'worked',
                in_array((string) $r->leave_type, $paidTypes, true) => 'paid',
                default => 'unpaid',
            };

            $days = [];
            $from = Carbon::parse($r->start_date)->startOfDay();
            $to = Carbon::parse($r->end_date ?? $r->start_date)->startOfDay();
            for ($d = $from->copy(); $d->lte($to) && count($days) < 400; $d->addDay()) {
                if ($this->isWorkday($d)) {
                    $days[] = $d->toDateString();
                }
            }

            $remaining = ($r->days !== null && (float) $r->days > 0) ? (float) $r->days : (float) count($days);
            foreach ($days as $date) {
                if ($remaining <= 0) {
                    break;
                }
                $alloc = min(1.0, $remaining);
                $remaining -= $alloc;
                if ($date < $start->toDateString() || $date > $end->toDateString()) {
                    continue;
                }
                $out[(int) $r->user_id][$date] ??= ['paid' => 0.0, 'unpaid' => 0.0, 'worked' => 0.0];
                $out[(int) $r->user_id][$date][$category] += $alloc;
            }
        }

        return $out;
    }

    /** @return array<int, array<string, true>> */
    private function tripDays(array $userIds, Carbon $start, Carbon $end): array
    {
        if (! SchemaCache::hasTable('hr_business_trips')) {
            return [];
        }

        $out = [];
        $rows = DB::table('hr_business_trips')
            ->whereIn('user_id', $userIds)
            ->whereIn('status', ['approved', 'completed'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['user_id', 'start_date', 'end_date']);
        foreach ($rows as $r) {
            $from = Carbon::parse($r->start_date)->max($start);
            $to = Carbon::parse($r->end_date)->min($end);
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $out[(int) $r->user_id][$d->toDateString()] = true;
            }
        }

        return $out;
    }

    /** @return array<int, int> */
    private function lateCounts(array $userIds, Carbon $start, Carbon $end, array $workdays): array
    {
        $excused = [];
        if (SchemaCache::hasTable('leave_requests')) {
            $rows = DB::table('leave_requests')
                ->whereIn('user_id', $userIds)
                ->where('status', 'approved')
                ->where('request_type', 'late')
                ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->get(['user_id', 'start_date']);
            foreach ($rows as $r) {
                $excused[(int) $r->user_id][Carbon::parse($r->start_date)->toDateString()] = true;
            }
        }

        $counts = [];
        $rows = DB::table('attendance_records')
            ->whereIn('user_id', $userIds)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('check_in_at')
            ->where('late_minutes', '>', 0)
            ->get(['user_id', 'work_date']);
        foreach ($rows as $r) {
            $date = Carbon::parse($r->work_date)->toDateString();
            if (! in_array($date, $workdays, true) || isset($excused[(int) $r->user_id][$date])) {
                continue;
            }
            $counts[(int) $r->user_id] = ($counts[(int) $r->user_id] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Giờ tăng ca đã duyệt và giờ quy đổi theo hệ số: ngày lễ / ngày nghỉ hằng tuần / ngày làm việc.
     *
     * @param  array<string, string>  $calendar  Y-m-d => work|holiday|off
     * @return array<int, array{hours: float, weighted: float}>
     */
    private function overtime(array $userIds, Carbon $start, Carbon $end, array $calendar, array $settings): array
    {
        if (! SchemaCache::hasTable('hr_overtime_requests')) {
            return [];
        }

        $out = [];
        $rows = DB::table('hr_overtime_requests')
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereBetween('overtime_date', [$start->toDateString(), $end->toDateString()])
            ->get(['user_id', 'overtime_date', 'hours']);
        foreach ($rows as $r) {
            $date = Carbon::parse($r->overtime_date)->toDateString();
            $rate = match (true) {
                isset($this->holidays[$date]) => (float) $settings['ot_rate_holiday'],
                ($calendar[$date] ?? 'off') === 'work' => (float) $settings['ot_rate_weekday'],
                default => (float) $settings['ot_rate_restday'],
            };
            $hours = (float) $r->hours;
            $out[(int) $r->user_id]['hours'] = ($out[(int) $r->user_id]['hours'] ?? 0) + $hours;
            $out[(int) $r->user_id]['weighted'] = ($out[(int) $r->user_id]['weighted'] ?? 0) + $hours * $rate / 100;
        }

        return $out;
    }

    private function tripAllowances(array $userIds, Carbon $start, Carbon $end): Collection
    {
        if (! SchemaCache::hasTable('hr_business_trips')) {
            return collect();
        }

        return DB::table('hr_business_trips')
            ->whereIn('user_id', $userIds)
            ->whereIn('status', ['approved', 'completed'])
            ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('user_id')
            ->pluck(DB::raw('SUM(COALESCE(daily_allowance,0) + COALESCE(meal_allowance,0) + COALESCE(hotel_allowance,0) + COALESCE(transport_allowance,0) + COALESCE(other_allowance,0))'), 'user_id');
    }

    /** Tạm ứng lương đã chi (phiếu DNTUL đã thanh toán và đã ghi vào kỳ lương). */
    private function salaryAdvances(array $userIds, string $month): Collection
    {
        if (! SchemaCache::hasTable('salary_advance_requests')) {
            return collect();
        }

        return DB::table('salary_advance_requests')
            ->whereIn('user_id', $userIds)
            ->where('payroll_month', $month)
            ->whereNotNull('applied_to_payroll_at')
            ->groupBy('user_id')
            ->pluck(DB::raw('SUM(requested_amount)'), 'user_id');
    }
}
