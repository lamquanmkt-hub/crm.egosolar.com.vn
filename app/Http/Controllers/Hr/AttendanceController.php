<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSetting;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Controller chấm công: check-in/out GPS, bảng công cá nhân, tổng hợp toàn công ty và xuất Excel / PDF.
 */
class AttendanceController extends Controller
{
    /**
     * Hiển thị bảng chấm công cá nhân theo tháng kèm bản ghi hôm nay.
     */
    public function myAttendance(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();
        $today = now()->toDateString();
        $setting = $this->getAttendanceSetting();

        $records = AttendanceRecord::with('user')
            ->where('user_id', $user->id)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('work_date')
            ->get();

        $todayRecord = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('work_date', $today)
            ->first();

        return view('hr.attendance.my', compact(
            'records',
            'todayRecord',
            'month',
            'start',
            'end',
            'setting'
        ));
    }

    /**
     * Hiển thị bảng chấm công toàn công ty theo tháng với bộ lọc, thống kê từng nhân viên và xếp hạng.
     */
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');
        $status = $request->input('status');
        $departmentId = $request->input('department_id');

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();
        $today = now()->toDateString();

        $employeesQuery = User::query()
            ->with(['department', 'position'])
            ->orderBy('name');

        if (Schema::hasColumn('users', 'is_active')) {
            $employeesQuery->where('is_active', 1);
        }

        if ($userId) {
            $employeesQuery->where('id', $userId);
        }

        if ($departmentId) {
            $employeesQuery->where('department_id', $departmentId);
        }

        $employees = $employeesQuery->get();
        $employeeIds = $employees->pluck('id');

        $attendanceQuery = AttendanceRecord::query()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);

        if ($employeeIds->isNotEmpty()) {
            $attendanceQuery->whereIn('user_id', $employeeIds);
        } else {
            $attendanceQuery->whereRaw('1 = 0');
        }

        if ($status) {
            $attendanceQuery->where('status', $status);
        }

        $detailQuery = AttendanceRecord::query()
            ->with(['user.department', 'user.position'])
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);

        if ($employeeIds->isNotEmpty()) {
            $detailQuery->whereIn('user_id', $employeeIds);
        } else {
            $detailQuery->whereRaw('1 = 0');
        }

        if ($status) {
            $detailQuery->where('status', $status);
        }

        $records = $detailQuery
            ->orderByDesc('work_date')
            ->orderBy('user_id')
            ->paginate(20)
            ->withQueryString();

        $departments = collect();
        if (class_exists(\App\Models\Department::class) && Schema::hasTable('departments')) {
            $departments = \App\Models\Department::orderBy('name')->get();
        }

        $statsRaw = (clone $attendanceQuery)
            ->select([
                'user_id',
                DB::raw('COUNT(id) as total_records'),
                DB::raw('SUM(CASE WHEN check_in_at IS NOT NULL THEN 1 ELSE 0 END) as valid_days'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_days"),
                DB::raw('SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) as late_days'),
                DB::raw("SUM(CASE WHEN status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_days"),
                DB::raw('SUM(CASE WHEN check_in_at IS NOT NULL AND check_out_at IS NULL THEN 1 ELSE 0 END) as incomplete_days'),
                DB::raw('SUM(work_minutes) as total_work_minutes'),
                DB::raw('SUM(CASE WHEN check_in_at IS NOT NULL AND late_minutes = 0 THEN 1 ELSE 0 END) as ontime_days'),
            ])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $employeeStats = $employees->map(function ($employee) use ($statsRaw) {
            $row = $statsRaw->get($employee->id);

            $validDays = (int) ($row->valid_days ?? 0);
            $completedDays = (int) ($row->completed_days ?? 0);
            $lateDays = (int) ($row->late_days ?? 0);
            $earlyLeaveDays = (int) ($row->early_leave_days ?? 0);
            $incompleteDays = (int) ($row->incomplete_days ?? 0);
            $totalWorkMinutes = (int) ($row->total_work_minutes ?? 0);
            $ontimeDays = (int) ($row->ontime_days ?? 0);

            $ontimeRate = $validDays > 0 ? round(($ontimeDays / $validDays) * 100) : 0;

            if ($validDays === 0) {
                $rankLabel = 'Chưa chấm công';
                $rankBadge = 'secondary';
            } elseif ($ontimeRate >= 95 && $incompleteDays === 0) {
                $rankLabel = 'Xuất sắc';
                $rankBadge = 'success';
            } elseif ($ontimeRate >= 80) {
                $rankLabel = 'Tốt';
                $rankBadge = 'primary';
            } elseif ($ontimeRate >= 60) {
                $rankLabel = 'Cần chú ý';
                $rankBadge = 'warning';
            } else {
                $rankLabel = 'Kém';
                $rankBadge = 'danger';
            }

            return (object) [
                'user_id' => $employee->id,
                'employee_name' => $employee->name,
                'department_name' => optional($employee->department)->name ?? '-',
                'position_name' => optional($employee->position)->name ?? '-',
                'valid_days' => $validDays,
                'completed_days' => $completedDays,
                'late_days' => $lateDays,
                'early_leave_days' => $earlyLeaveDays,
                'incomplete_days' => $incompleteDays,
                'total_hours' => round($totalWorkMinutes / 60, 2),
                'ontime_rate' => $ontimeRate,
                'rank_label' => $rankLabel,
                'rank_badge' => $rankBadge,
            ];
        });

        $validAttendanceDays = $employeeStats->sum('valid_days');
        $completedDays = $employeeStats->sum('completed_days');
        $lateCount = $employeeStats->sum('late_days');
        $earlyLeaveCount = $employeeStats->sum('early_leave_days');
        $incompleteCount = $employeeStats->sum('incomplete_days');
        $totalWorkHours = round($employeeStats->sum('total_hours'), 2);

        $checkedInToday = AttendanceRecord::query()
            ->whereDate('work_date', $today)
            ->whereNotNull('check_in_at')
            ->when(
                $employeeIds->isNotEmpty(),
                fn ($q) => $q->whereIn('user_id', $employeeIds),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->count();

        $completionRate = $validAttendanceDays > 0
            ? round(($completedDays / $validAttendanceDays) * 100)
            : 0;

        $summary = [
            'employees' => $employees->count(),
            'valid_days' => $validAttendanceDays,
            'completed' => $completedDays,
            'late' => $lateCount,
            'early_leave' => $earlyLeaveCount,
            'incomplete' => $incompleteCount,
            'checked_in_today' => $checkedInToday,
            'total_hours' => $totalWorkHours,
            'completion_rate' => $completionRate,
        ];

        return view('hr.attendance.index', compact(
            'records',
            'employees',
            'departments',
            'employeeStats',
            'month',
            'start',
            'end',
            'summary'
        ));
    }

    /**
     * Check-in hôm nay: tính số phút đi muộn theo cài đặt và lưu toạ độ / địa chỉ GPS.
     */
    public function checkIn(Request $request)
    {
        $setting = $this->getAttendanceSetting();

        $request->validate($this->locationValidationRules($setting), [
            'lat.required' => 'Hệ thống đang bắt buộc lấy GPS. Vui lòng cho phép quyền vị trí.',
            'lng.required' => 'Hệ thống đang bắt buộc lấy GPS. Vui lòng cho phép quyền vị trí.',
            'lat.numeric' => 'Tọa độ GPS không hợp lệ.',
            'lng.numeric' => 'Tọa độ GPS không hợp lệ.',
        ]);

        $user = auth()->user();
        $now = now();
        $workDate = $now->toDateString();

        $record = AttendanceRecord::firstOrNew([
            'user_id' => $user->id,
            'work_date' => $workDate,
        ]);

        if ($record->check_in_at) {
            return back()->with('error', 'Bạn đã check-in hôm nay rồi.');
        }

        $workStartTime = $this->normalizeTime($setting->work_start_time ?? null, '08:30:00');
        $graceMinutes = max((int) ($setting->late_grace_minutes ?? 0), 0);

        $standardCheckIn = Carbon::parse($workDate.' '.$workStartTime);
        $allowedCheckIn = (clone $standardCheckIn)->addMinutes($graceMinutes);

        $lateMinutes = $now->greaterThan($allowedCheckIn)
            ? $allowedCheckIn->diffInMinutes($now)
            : 0;

        $record->check_in_at = $now;
        $record->late_minutes = $lateMinutes;
        $record->status = $lateMinutes > 0 ? 'late' : 'checked_in';
        $record->note = $request->note;

        if ($request->filled('lat') && $request->filled('lng')) {
            $record->check_in_lat = $request->lat;
            $record->check_in_lng = $request->lng;
            $record->check_in_address = $this->resolveAddress($request->lat, $request->lng);
        }

        $record->save();

        return back()->with('success', 'Check-in thành công.');
    }

    /**
     * Check-out hôm nay: tính giờ công, số phút về sớm và cập nhật trạng thái completed / early_leave.
     */
    public function checkOut(Request $request)
    {
        $setting = $this->getAttendanceSetting();

        $request->validate($this->locationValidationRules($setting), [
            'lat.required' => 'Hệ thống đang bắt buộc lấy GPS. Vui lòng cho phép quyền vị trí.',
            'lng.required' => 'Hệ thống đang bắt buộc lấy GPS. Vui lòng cho phép quyền vị trí.',
            'lat.numeric' => 'Tọa độ GPS không hợp lệ.',
            'lng.numeric' => 'Tọa độ GPS không hợp lệ.',
        ]);

        $user = auth()->user();
        $now = now();
        $workDate = $now->toDateString();

        $record = AttendanceRecord::where('user_id', $user->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if (! $record || ! $record->check_in_at) {
            return back()->with('error', 'Bạn chưa check-in hôm nay.');
        }

        if ($record->check_out_at) {
            return back()->with('error', 'Bạn đã check-out hôm nay rồi.');
        }

        $checkInAt = Carbon::parse($record->check_in_at);

        $workEndTime = $this->normalizeTime($setting->work_end_time ?? null, '18:00:00');
        $standardCheckOut = Carbon::parse($workDate.' '.$workEndTime);

        $workMinutes = $checkInAt->diffInMinutes($now);

        $earlyLeaveMinutes = $now->lt($standardCheckOut)
            ? $now->diffInMinutes($standardCheckOut)
            : 0;

        $minWorkMinutes = max((int) ($setting->min_work_minutes ?? 480), 0);

        $record->check_out_at = $now;
        $record->work_minutes = $workMinutes;
        $record->early_leave_minutes = $earlyLeaveMinutes;

        if ($request->filled('lat') && $request->filled('lng')) {
            $record->check_out_lat = $request->lat;
            $record->check_out_lng = $request->lng;
            $record->check_out_address = $this->resolveAddress($request->lat, $request->lng);
        }

        if ($earlyLeaveMinutes > 0 || ($minWorkMinutes > 0 && $workMinutes < $minWorkMinutes)) {
            $record->status = 'early_leave';
        } else {
            $record->status = 'completed';
        }

        if ($request->filled('note')) {
            $record->note = trim(($record->note ? $record->note."\n" : '').$request->note);
        }

        $record->save();

        return back()->with('success', 'Check-out thành công.');
    }

    /**
     * Lấy bản ghi cài đặt chấm công, tự tạo với giá trị mặc định nếu chưa có.
     */
    private function getAttendanceSetting(): AttendanceSetting
    {
        $setting = AttendanceSetting::first();

        if ($setting) {
            return $setting;
        }

        $setting = new AttendanceSetting;
        $setting->work_start_time = '08:30:00';
        $setting->work_end_time = '18:00:00';
        $setting->late_grace_minutes = 5;
        $setting->late_penalty_per_time = 0;
        $setting->min_work_minutes = 480;
        $setting->require_gps = true;
        $setting->save();

        return $setting;
    }

    /**
     * Trả về rule validate toạ độ GPS và ghi chú; GPS bắt buộc hay không tuỳ cài đặt.
     */
    private function locationValidationRules(AttendanceSetting $setting): array
    {
        $gpsRequired = (bool) ($setting->require_gps ?? true);

        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'lat' => [$gpsRequired ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'lng' => [$gpsRequired ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Chuẩn hoá chuỗi giờ về định dạng H:i:s, trả về giá trị fallback nếu không hợp lệ.
     */
    private function normalizeTime(?string $time, string $fallback): string
    {
        if (blank($time)) {
            return $fallback;
        }

        $time = trim((string) $time);

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            return $time.':00';
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        return $fallback;
    }

    /**
     * Chuyển toạ độ GPS thành địa chỉ qua Nominatim; nếu lỗi thì trả về chuỗi toạ độ thô.
     *
     * @param  mixed  $lat  Vĩ độ
     * @param  mixed  $lng  Kinh độ
     */
    private function resolveAddress($lat, $lng): ?string
    {
        if (blank($lat) || blank($lng)) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'Laravel').'/1.0 attendance-system',
                    'Accept-Language' => 'vi',
                ])
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $lat,
                    'lon' => $lng,
                ]);

            if ($response->successful()) {
                $displayName = $response->json('display_name');

                if (! empty($displayName)) {
                    return $displayName;
                }
            }
        } catch (\Throwable $e) {
        }

        return 'GPS: '.number_format((float) $lat, 7, '.', '').', '.number_format((float) $lng, 7, '.', '');
    }

    /**
     * Xuất báo cáo chấm công tháng ra Excel: sheet tổng hợp, lịch công chuẩn và sheet chi tiết từng nhân viên.
     */
    public function exportExcel(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');
        $status = $request->input('status');
        $departmentId = $request->input('department_id');

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();
        $setting = AttendanceSetting::first();

        $employeesQuery = User::query()
            ->with(['department', 'position'])
            ->orderBy('name');

        if (Schema::hasColumn('users', 'is_active')) {
            $employeesQuery->where('is_active', 1);
        }

        if ($userId) {
            $employeesQuery->where('id', $userId);
        }

        if ($departmentId) {
            $employeesQuery->where('department_id', $departmentId);
        }

        $employees = $employeesQuery->get();
        $employeeIds = $employees->pluck('id');

        $holidays = collect();

        if (Schema::hasTable('attendance_holidays')) {
            $holidays = DB::table('attendance_holidays')
                ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
                ->orderBy('holiday_date')
                ->get()
                ->keyBy(function ($holiday) {
                    return Carbon::parse($holiday->holiday_date)->toDateString();
                });
        }

        $rawSaturdayCustomDates = $setting->saturday_custom_dates ?? [];

        if (is_string($rawSaturdayCustomDates)) {
            $decoded = json_decode($rawSaturdayCustomDates, true);
            $rawSaturdayCustomDates = is_array($decoded)
                ? $decoded
                : preg_split('/[\s,;]+/', $rawSaturdayCustomDates);
        }

        $saturdayCustomDates = [];

        if (is_array($rawSaturdayCustomDates)) {
            foreach ($rawSaturdayCustomDates as $customDate) {
                try {
                    $saturdayCustomDates[] = Carbon::parse($customDate)->toDateString();
                } catch (\Throwable $e) {
                }
            }
        }

        $monthCalendar = collect();

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $dateKey = $day->toDateString();
            $holiday = $holidays->get($dateKey);

            $isWorkday = false;
            $dayType = 'Nghỉ theo lịch';

            if ($day->dayOfWeekIso === 1) {
                $isWorkday = $setting ? (bool) ($setting->workday_monday ?? true) : true;
                $dayType = $isWorkday ? 'Đi làm' : 'Nghỉ theo lịch';
            } elseif ($day->dayOfWeekIso === 2) {
                $isWorkday = $setting ? (bool) ($setting->workday_tuesday ?? true) : true;
                $dayType = $isWorkday ? 'Đi làm' : 'Nghỉ theo lịch';
            } elseif ($day->dayOfWeekIso === 3) {
                $isWorkday = $setting ? (bool) ($setting->workday_wednesday ?? true) : true;
                $dayType = $isWorkday ? 'Đi làm' : 'Nghỉ theo lịch';
            } elseif ($day->dayOfWeekIso === 4) {
                $isWorkday = $setting ? (bool) ($setting->workday_thursday ?? true) : true;
                $dayType = $isWorkday ? 'Đi làm' : 'Nghỉ theo lịch';
            } elseif ($day->dayOfWeekIso === 5) {
                $isWorkday = $setting ? (bool) ($setting->workday_friday ?? true) : true;
                $dayType = $isWorkday ? 'Đi làm' : 'Nghỉ theo lịch';
            } elseif ($day->dayOfWeekIso === 6) {
                $saturdayMode = $setting->saturday_mode ?? 'off';
                $weekOfMonth = (int) ceil($day->day / 7);

                $isWorkday = match ($saturdayMode) {
                    'all' => true,
                    'odd' => $weekOfMonth % 2 === 1,
                    'even' => $weekOfMonth % 2 === 0,
                    'custom' => in_array($dateKey, $saturdayCustomDates, true),
                    default => false,
                };

                $dayType = $isWorkday ? 'Đi làm thứ 7' : 'Nghỉ thứ 7';
            } elseif ($day->dayOfWeekIso === 7) {
                $isWorkday = $setting ? (bool) ($setting->workday_sunday ?? false) : false;
                $dayType = $isWorkday ? 'Đi làm chủ nhật' : 'Nghỉ chủ nhật';
            }

            if ($holiday) {
                $isWorkday = false;
                $dayType = 'Nghỉ lễ / nghỉ công ty';
            }

            $monthCalendar->push((object) [
                'date' => $dateKey,
                'date_display' => $day->format('d/m/Y'),
                'weekday' => match ($day->dayOfWeekIso) {
                    1 => 'Thứ 2',
                    2 => 'Thứ 3',
                    3 => 'Thứ 4',
                    4 => 'Thứ 5',
                    5 => 'Thứ 6',
                    6 => 'Thứ 7',
                    default => 'Chủ nhật',
                },
                'is_workday' => $isWorkday,
                'day_type' => $dayType,
                'holiday_name' => $holiday->name ?? null,
            ]);
        }

        $standardDays = $monthCalendar->where('is_workday', true)->count();
        $holidayDeductedDays = $holidays->count();
        $saturdayWorkdays = $monthCalendar
            ->filter(fn ($item) => $item->weekday === 'Thứ 7' && $item->is_workday)
            ->count();

        $recordsQuery = AttendanceRecord::query()
            ->with(['user.department', 'user.position'])
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);

        if ($employeeIds->isNotEmpty()) {
            $recordsQuery->whereIn('user_id', $employeeIds);
        } else {
            $recordsQuery->whereRaw('1 = 0');
        }

        if ($status) {
            $recordsQuery->where('status', $status);
        }

        $records = $recordsQuery
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();

        $recordsByUser = $records->groupBy('user_id');

        $statsRaw = AttendanceRecord::query()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->when(
                $employeeIds->isNotEmpty(),
                fn ($q) => $q->whereIn('user_id', $employeeIds),
                fn ($q) => $q->whereRaw('1 = 0')
            )
            ->when($status, fn ($q) => $q->where('status', $status))
            ->select([
                'user_id',
                DB::raw('SUM(CASE WHEN check_in_at IS NOT NULL THEN 1 ELSE 0 END) as valid_days'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_days"),
                DB::raw('SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) as late_days'),
                DB::raw("SUM(CASE WHEN status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_days"),
                DB::raw('SUM(CASE WHEN check_in_at IS NOT NULL AND check_out_at IS NULL THEN 1 ELSE 0 END) as incomplete_days'),
                DB::raw('SUM(work_minutes) as total_work_minutes'),
                DB::raw('SUM(CASE WHEN check_in_at IS NOT NULL AND late_minutes = 0 THEN 1 ELSE 0 END) as ontime_days'),
            ])
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $employeeStats = $employees->map(function ($employee) use ($statsRaw, $standardDays) {
            $row = $statsRaw->get($employee->id);

            $validDays = (int) ($row->valid_days ?? 0);
            $completedDays = (int) ($row->completed_days ?? 0);
            $lateDays = (int) ($row->late_days ?? 0);
            $earlyLeaveDays = (int) ($row->early_leave_days ?? 0);
            $incompleteDays = (int) ($row->incomplete_days ?? 0);
            $totalWorkMinutes = (int) ($row->total_work_minutes ?? 0);
            $ontimeDays = (int) ($row->ontime_days ?? 0);

            $missingDays = max($standardDays - $validDays, 0);
            $ontimeRate = $validDays > 0 ? round(($ontimeDays / $validDays) * 100) : 0;

            if ($validDays === 0) {
                $rankLabel = 'Chưa chấm công';
            } elseif ($missingDays === 0 && $ontimeRate >= 95 && $incompleteDays === 0) {
                $rankLabel = 'Xuất sắc';
            } elseif ($missingDays <= 1 && $ontimeRate >= 80) {
                $rankLabel = 'Tốt';
            } elseif ($missingDays <= 3) {
                $rankLabel = 'Cần chú ý';
            } else {
                $rankLabel = 'Kém';
            }

            return (object) [
                'user_id' => $employee->id,
                'employee_name' => $employee->name,
                'department_name' => optional($employee->department)->name ?? '-',
                'position_name' => optional($employee->position)->name ?? '-',
                'standard_days' => $standardDays,
                'valid_days' => $validDays,
                'missing_days' => $missingDays,
                'completed_days' => $completedDays,
                'late_days' => $lateDays,
                'early_leave_days' => $earlyLeaveDays,
                'incomplete_days' => $incompleteDays,
                'total_hours' => round($totalWorkMinutes / 60, 2),
                'ontime_rate' => $ontimeRate,
                'rank_label' => $rankLabel,
            ];
        });

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator(config('app.name', 'CRM'))
            ->setTitle('Báo cáo chấm công '.$month);

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'EAF6FF'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'CBD5E1'],
                ],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $cellStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E2E8F0'],
                ],
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];

        $autoSize = function ($sheet, $lastColumn) {
            foreach (range('A', $lastColumn) as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
        };

        $statusLabel = function ($status) {
            return match ($status) {
                'checked_in' => 'Đã check-in',
                'late' => 'Đi muộn',
                'completed' => 'Hoàn tất',
                'early_leave' => 'Về sớm',
                'incomplete' => 'Thiếu check-out',
                'absent' => 'Vắng mặt',
                default => $status ?: '-',
            };
        };

        $formatTime = function ($value) {
            return $value ? Carbon::parse($value)->format('H:i:s') : '-';
        };

        $cleanSheetTitle = function ($name, $usedTitles) {
            $name = preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', (string) $name);
            $name = trim(preg_replace('/\s+/', ' ', $name));

            if ($name === '') {
                $name = 'Nhan vien';
            }

            $base = function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
            $title = $base;
            $i = 2;

            while (in_array($title, $usedTitles, true)) {
                $suffix = ' '.$i;
                $limit = 31 - strlen($suffix);
                $shortBase = function_exists('mb_substr') ? mb_substr($base, 0, $limit) : substr($base, 0, $limit);
                $title = $shortBase.$suffix;
                $i++;
            }

            return $title;
        };

        /*
        |--------------------------------------------------------------------------
        | Sheet 1: Tổng hợp
        |--------------------------------------------------------------------------
        */
        $summarySheet = $spreadsheet->getActiveSheet();
        $summarySheet->setTitle('Tong hop');

        $summarySheet->mergeCells('A1:O1');
        $summarySheet->setCellValue('A1', 'BÁO CÁO TỔNG HỢP CHẤM CÔNG');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $summarySheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $summarySheet->mergeCells('A2:O2');
        $summarySheet->setCellValue('A2', 'Tháng '.$start->format('m/Y').' | Công chuẩn: '.$standardDays.' | Thứ 7 đi làm: '.$saturdayWorkdays.' | Ngày nghỉ/lễ đã cấu hình: '.$holidayDeductedDays);
        $summarySheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $summaryHeaders = [
            'STT',
            'Nhân viên',
            'Phòng ban',
            'Chức vụ',
            'Công chuẩn',
            'Công thực tế',
            'Thiếu công',
            'Hoàn tất',
            'Đi muộn',
            'Về sớm',
            'Thiếu check-out',
            'Tổng giờ công',
            'Tỷ lệ đúng giờ',
            'Xếp hạng',
            'Ghi chú',
        ];

        $summarySheet->fromArray($summaryHeaders, null, 'A4');
        $summarySheet->getStyle('A4:O4')->applyFromArray($headerStyle);

        $rowNumber = 5;

        foreach ($employeeStats as $index => $item) {
            $summarySheet->fromArray([
                $index + 1,
                $item->employee_name,
                $item->department_name,
                $item->position_name,
                $item->standard_days,
                $item->valid_days,
                $item->missing_days,
                $item->completed_days,
                $item->late_days,
                $item->early_leave_days,
                $item->incomplete_days,
                $item->total_hours,
                $item->ontime_rate.'%',
                $item->rank_label,
                $item->missing_days > 0 ? 'Thiếu '.$item->missing_days.' công' : '',
            ], null, 'A'.$rowNumber);

            $rowNumber++;
        }

        if ($rowNumber > 5) {
            $summarySheet->getStyle('A4:O'.($rowNumber - 1))->applyFromArray($cellStyle);
        }

        $summarySheet->freezePane('A5');
        $autoSize($summarySheet, 'O');

        /*
        |--------------------------------------------------------------------------
        | Sheet 2: Lịch công chuẩn
        |--------------------------------------------------------------------------
        */
        $calendarSheet = $spreadsheet->createSheet();
        $calendarSheet->setTitle('Lich cong chuan');

        $calendarSheet->mergeCells('A1:F1');
        $calendarSheet->setCellValue('A1', 'LỊCH CÔNG CHUẨN THÁNG '.$start->format('m/Y'));
        $calendarSheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $calendarSheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $calendarHeaders = [
            'Ngày',
            'Thứ',
            'Loại ngày',
            'Có tính công chuẩn',
            'Tên ngày nghỉ/lễ',
            'Ghi chú',
        ];

        $calendarSheet->fromArray($calendarHeaders, null, 'A3');
        $calendarSheet->getStyle('A3:F3')->applyFromArray($headerStyle);

        $calendarRow = 4;

        foreach ($monthCalendar as $calendarDay) {
            $calendarSheet->fromArray([
                $calendarDay->date_display,
                $calendarDay->weekday,
                $calendarDay->day_type,
                $calendarDay->is_workday ? 'Có' : 'Không',
                $calendarDay->holiday_name ?: '',
                $calendarDay->is_workday ? 'Ngày phải chấm công' : 'Không tính công chuẩn',
            ], null, 'A'.$calendarRow);

            $calendarRow++;
        }

        $calendarSheet->getStyle('A3:F'.($calendarRow - 1))->applyFromArray($cellStyle);
        $calendarSheet->freezePane('A4');
        $autoSize($calendarSheet, 'F');

        /*
        |--------------------------------------------------------------------------
        | Sheet từng nhân viên
        |--------------------------------------------------------------------------
        */
        $usedSheetTitles = ['Tong hop', 'Lich cong chuan'];

        foreach ($employees as $employee) {
            $sheetTitle = $cleanSheetTitle($employee->name, $usedSheetTitles);
            $usedSheetTitles[] = $sheetTitle;

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetTitle);

            $sheet->mergeCells('A1:O1');
            $sheet->setCellValue('A1', 'CHI TIẾT CHẤM CÔNG - '.$employee->name);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells('A2:O2');
            $sheet->setCellValue('A2', 'Tháng '.$start->format('m/Y').' | Phòng ban: '.(optional($employee->department)->name ?? '-').' | Chức vụ: '.(optional($employee->position)->name ?? '-').' | Công chuẩn: '.$standardDays);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            $detailHeaders = [
                'STT',
                'Ngày',
                'Thứ',
                'Loại ngày',
                'Cần chấm công',
                'Check-in',
                'Địa chỉ vào',
                'Check-out',
                'Địa chỉ ra',
                'Đi muộn',
                'Về sớm',
                'Giờ công',
                'Trạng thái',
                'Ngày nghỉ/lễ',
                'Ghi chú',
            ];

            $sheet->fromArray($detailHeaders, null, 'A4');
            $sheet->getStyle('A4:O4')->applyFromArray($headerStyle);

            $employeeRecords = $recordsByUser->get($employee->id, collect())->keyBy(function ($record) {
                return Carbon::parse($record->work_date)->toDateString();
            });

            $detailRow = 5;

            foreach ($monthCalendar as $index => $calendarDay) {
                $record = $employeeRecords->get($calendarDay->date);

                if ($record) {
                    $rowStatus = $statusLabel($record->status);
                } elseif ($calendarDay->is_workday) {
                    $rowStatus = 'Thiếu công';
                } else {
                    $rowStatus = 'Nghỉ';
                }

                $sheet->fromArray([
                    $index + 1,
                    $calendarDay->date_display,
                    $calendarDay->weekday,
                    $calendarDay->day_type,
                    $calendarDay->is_workday ? 'Có' : 'Không',
                    $record ? $formatTime($record->check_in_at) : '-',
                    $record ? ($record->check_in_address ?: '-') : '-',
                    $record ? $formatTime($record->check_out_at) : '-',
                    $record ? ($record->check_out_address ?: '-') : '-',
                    $record ? ((int) $record->late_minutes.' phút') : '-',
                    $record ? ((int) $record->early_leave_minutes.' phút') : '-',
                    $record ? (round(((int) $record->work_minutes) / 60, 2).' giờ') : '-',
                    $rowStatus,
                    $calendarDay->holiday_name ?: '',
                    $record ? ($record->note ?: '') : '',
                ], null, 'A'.$detailRow);

                $detailRow++;
            }

            $sheet->getStyle('A4:O'.($detailRow - 1))->applyFromArray($cellStyle);
            $sheet->getStyle('G5:G'.($detailRow - 1))->getAlignment()->setWrapText(true);
            $sheet->getStyle('I5:I'.($detailRow - 1))->getAlignment()->setWrapText(true);
            $sheet->freezePane('A5');
            $autoSize($sheet, 'O');
        }

        $spreadsheet->setActiveSheetIndex(0);

        $fileName = 'bao-cao-cham-cong-'.$month.'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Xuất bảng công tháng ra PDF khổ A4 ngang theo bộ lọc.
     */
    public function exportPdf(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $userId = $request->input('user_id');
        $status = $request->input('status');
        $departmentId = $request->input('department_id');

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();

        $employeesQuery = User::query()
            ->with(['department', 'position'])
            ->orderBy('name');

        if (Schema::hasColumn('users', 'is_active')) {
            $employeesQuery->where('is_active', 1);
        }

        if ($userId) {
            $employeesQuery->where('id', $userId);
        }

        if ($departmentId) {
            $employeesQuery->where('department_id', $departmentId);
        }

        $employees = $employeesQuery->get();
        $employeeIds = $employees->pluck('id');

        $recordsQuery = AttendanceRecord::query()
            ->with(['user.department', 'user.position'])
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);

        if ($employeeIds->isNotEmpty()) {
            $recordsQuery->whereIn('user_id', $employeeIds);
        } else {
            $recordsQuery->whereRaw('1 = 0');
        }

        if ($status) {
            $recordsQuery->where('status', $status);
        }

        $records = $recordsQuery
            ->orderBy('work_date')
            ->orderBy('user_id')
            ->get();

        $pdf = Pdf::loadView('hr.attendance.export_pdf', compact(
            'records',
            'employees',
            'month',
            'start',
            'end'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('bang-cong-'.$month.'.pdf');
    }
}
