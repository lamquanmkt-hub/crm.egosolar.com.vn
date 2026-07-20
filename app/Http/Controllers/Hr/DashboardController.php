<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;

/**
 * Controller trang tổng quan (dashboard) của module HR.
 */
class DashboardController extends Controller
{
    /**
     * Hiển thị dashboard HR: tổng nhân sự, nghỉ phép chờ duyệt và thống kê chấm công theo khoảng thời gian.
     */
    public function index(Request $request)
    {
        $fromDate = $request->from_date
            ? Carbon::parse($request->from_date)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $toDate = $request->to_date
            ? Carbon::parse($request->to_date)->endOfDay()
            : now()->endOfDay();

        if ($request->period === 'this_month') {
            $fromDate = now()->startOfMonth()->startOfDay();
            $toDate = now()->endOfMonth()->endOfDay();
        }

        if ($request->period === 'last_month') {
            $fromDate = now()->subMonth()->startOfMonth()->startOfDay();
            $toDate = now()->subMonth()->endOfMonth()->endOfDay();
        }

        if ($request->period === 'this_year') {
            $fromDate = now()->startOfYear()->startOfDay();
            $toDate = now()->endOfYear()->endOfDay();
        }

        $today = now()->toDateString();

        $totalEmployees = Schema::hasColumn('users', 'is_active')
            ? User::where('is_active', 1)->count()
            : User::count();

        $pendingLeaves = 0;
        if (class_exists(LeaveRequest::class) && Schema::hasTable('leave_requests')) {
            $pendingLeaves = LeaveRequest::where('status', 'pending')
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count();
        }

        $todayAttendance = 0;
        $lateCount = 0;
        $checkInRate = 0;
        $employeeAttendanceStats = collect();

        if (class_exists(AttendanceRecord::class) && Schema::hasTable('attendance_records')) {
            $todayAttendance = AttendanceRecord::whereDate('work_date', $today)
                ->whereNotNull('check_in_at')
                ->count();

            $lateCount = AttendanceRecord::whereBetween('work_date', [
                    $fromDate->toDateString(),
                    $toDate->toDateString()
                ])
                ->where('late_minutes', '>', 0)
                ->count();

            $checkInRate = $totalEmployees > 0
                ? round(($todayAttendance / $totalEmployees) * 100)
                : 0;

            $employeeAttendanceStats = AttendanceRecord::query()
                ->select([
                    'attendance_records.user_id',
                    'users.name as employee_name',
                    DB::raw("COALESCE(departments.name, '-') as department_name"),
                    DB::raw('COUNT(attendance_records.id) as total_records'),
                    DB::raw('SUM(CASE WHEN attendance_records.check_in_at IS NOT NULL THEN 1 ELSE 0 END) as total_checkin_days'),
                    DB::raw("SUM(CASE WHEN attendance_records.status = 'completed' THEN 1 ELSE 0 END) as completed_days"),
                    DB::raw("SUM(CASE WHEN attendance_records.late_minutes > 0 THEN 1 ELSE 0 END) as late_days"),
                    DB::raw("SUM(CASE WHEN attendance_records.status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_days"),
                    DB::raw("SUM(CASE WHEN attendance_records.status IN ('checked_in', 'incomplete') THEN 1 ELSE 0 END) as incomplete_days"),
                    DB::raw('SUM(attendance_records.work_minutes) as total_work_minutes'),
                    DB::raw("SUM(CASE WHEN attendance_records.late_minutes = 0 AND attendance_records.check_in_at IS NOT NULL THEN 1 ELSE 0 END) as ontime_days"),
                ])
                ->join('users', 'users.id', '=', 'attendance_records.user_id')
                ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
                ->whereBetween('attendance_records.work_date', [
                    $fromDate->toDateString(),
                    $toDate->toDateString()
                ])
                ->groupBy('attendance_records.user_id', 'users.name', 'departments.name')
                ->orderByDesc('total_checkin_days')
                ->orderBy('users.name')
                ->get()
                ->map(function ($item) {
                    $item->total_hours = round(((int) $item->total_work_minutes) / 60, 2);
                    $item->ontime_rate = (int) $item->total_checkin_days > 0
                        ? round(((int) $item->ontime_days / (int) $item->total_checkin_days) * 100)
                        : 0;

                    $item->performance_label = match (true) {
                        $item->ontime_rate >= 95 && (int) $item->late_days === 0 && (int) $item->incomplete_days === 0 => 'Xuất sắc',
                        $item->ontime_rate >= 80 && (int) $item->incomplete_days <= 1 => 'Tốt',
                        $item->ontime_rate >= 60 => 'Cần chú ý',
                        default => 'Kém',
                    };

                    $item->performance_badge = match ($item->performance_label) {
                        'Xuất sắc' => 'success',
                        'Tốt' => 'primary',
                        'Cần chú ý' => 'warning',
                        default => 'danger',
                    };

                    return $item;
                });
        }

        return view('hr.dashboard', compact(
            'totalEmployees',
            'pendingLeaves',
            'todayAttendance',
            'lateCount',
            'checkInRate',
            'employeeAttendanceStats',
            'fromDate',
            'toDate'
        ));
    }
}