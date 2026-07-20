<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\OvertimeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quản lý đơn đăng ký tăng ca của nhân viên.
 */
class OvertimeRequestController extends Controller
{
    /**
     * Hiển thị danh sách đơn tăng ca theo tháng / trạng thái / nhân viên kèm số liệu tổng hợp.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));
        $status = $request->input('status');
        $userId = $request->input('user_id');

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();
        $canManage = $this->canManageHr($user);

        $query = OvertimeRequest::query()
            ->with(['user.department', 'approver', 'approvedBy'])
            ->whereBetween('overtime_date', [$start->toDateString(), $end->toDateString()]);

        if (! $canManage) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('approver_id', $user->id);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($userId && $canManage) {
            $query->where('user_id', $userId);
        }

        $requests = $query
            ->orderByDesc('overtime_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $summaryBase = OvertimeRequest::query()
            ->whereBetween('overtime_date', [$start->toDateString(), $end->toDateString()]);

        if (! $canManage) {
            $summaryBase->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('approver_id', $user->id);
            });
        }

        $summary = [
            'total' => (clone $summaryBase)->count(),
            'pending' => (clone $summaryBase)->where('status', 'pending')->count(),
            'approved' => (clone $summaryBase)->where('status', 'approved')->count(),
            'rejected' => (clone $summaryBase)->where('status', 'rejected')->count(),
            'hours' => (float) (clone $summaryBase)->where('status', 'approved')->sum('hours'),
        ];

        $employees = $canManage ? $this->employeeOptions() : collect();

        return view('hr.overtime.index', compact(
            'requests',
            'summary',
            'employees',
            'month',
            'status',
            'userId',
            'canManage'
        ));
    }

    /**
     * Hiển thị form đăng ký tăng ca kèm danh sách người duyệt.
     */
    public function create()
    {
        $approvers = $this->approverOptions();

        return view('hr.overtime.create', compact('approvers'));
    }

    /**
     * Tạo đơn tăng ca mới; tự cộng thêm 1 ngày nếu giờ kết thúc qua đêm, giới hạn tối đa 16 giờ/lần.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'overtime_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'approver_id' => ['nullable', 'integer'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);

        $date = Carbon::parse($data['overtime_date'])->toDateString();
        $startAt = Carbon::parse($date.' '.$data['start_time']);
        $endAt = Carbon::parse($date.' '.$data['end_time']);

        if ($endAt->lte($startAt)) {
            $endAt->addDay();
        }

        $minutes = $startAt->diffInMinutes($endAt);

        if ($minutes <= 0 || $minutes > 16 * 60) {
            return back()
                ->withInput()
                ->with('error', 'Thời gian tăng ca không hợp lệ, tối đa 16 giờ/lần.');
        }

        OvertimeRequest::create([
            'user_id' => auth()->id(),
            'approver_id' => $data['approver_id'] ?: null,
            'overtime_date' => $date,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'hours' => round($minutes / 60, 2),
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('hr.overtime.index')
            ->with('success', 'Đã gửi đơn đăng ký tăng ca.');
    }

    /**
     * Duyệt đơn tăng ca và đồng bộ ghi chú vào bản ghi chấm công.
     */
    public function approve(Request $request, OvertimeRequest $overtime)
    {
        $user = auth()->user();

        abort_unless($this->canApprove($user, $overtime), 403);

        $request->validate([
            'approval_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $overtime->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'rejected_at' => null,
            'approval_note' => $request->approval_note,
        ]);

        $this->syncAttendanceNote($overtime->fresh(['user', 'approver', 'approvedBy']));

        return back()->with('success', 'Đã duyệt đơn tăng ca và note vào chấm công.');
    }

    /**
     * Từ chối đơn tăng ca.
     */
    public function reject(Request $request, OvertimeRequest $overtime)
    {
        $user = auth()->user();

        abort_unless($this->canApprove($user, $overtime), 403);

        $request->validate([
            'approval_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $overtime->update([
            'status' => 'rejected',
            'approved_by' => $user->id,
            'approved_at' => null,
            'rejected_at' => now(),
            'approval_note' => $request->approval_note,
        ]);

        return back()->with('success', 'Đã từ chối đơn tăng ca.');
    }

    /**
     * Ghi / thay thế ghi chú tăng ca (theo tag) vào bản ghi chấm công của ngày tương ứng.
     */
    private function syncAttendanceNote(OvertimeRequest $overtime): void
    {
        $date = Carbon::parse($overtime->overtime_date)->toDateString();

        DB::transaction(function () use ($overtime, $date) {
            $record = AttendanceRecord::firstOrNew([
                'user_id' => $overtime->user_id,
                'work_date' => $date,
            ]);

            if (! $record->exists) {
                $record->status = 'absent';
                $record->late_minutes = 0;
                $record->early_leave_minutes = 0;
                $record->work_minutes = 0;
            }

            $tagStart = '[Tăng ca #'.$overtime->id.']';
            $tagEnd = '[/Tăng ca #'.$overtime->id.']';

            $current = (string) ($record->note ?? '');
            $pattern = '/\s*'.preg_quote($tagStart, '/').'.*?'.preg_quote($tagEnd, '/').'\s*/su';
            $clean = trim((string) preg_replace($pattern, "\n", $current));

            $noteText = implode(' | ', array_filter([
                'Tăng ca đã duyệt',
                'Ngày: '.Carbon::parse($overtime->overtime_date)->format('d/m/Y'),
                'Giờ: '.Carbon::parse($overtime->start_at)->format('H:i').' - '.Carbon::parse($overtime->end_at)->format('H:i'),
                'Số giờ: '.rtrim(rtrim(number_format((float) $overtime->hours, 2, '.', ''), '0'), '.'),
                $overtime->reason ? 'Lý do: '.trim($overtime->reason) : null,
                $overtime->approvedBy?->name ? 'Người duyệt: '.$overtime->approvedBy->name : null,
                $overtime->approval_note ? 'Ghi chú duyệt: '.trim($overtime->approval_note) : null,
            ]));

            $newTaggedNote = $tagStart.' '.$noteText.' '.$tagEnd;
            $record->note = trim($clean === '' ? $newTaggedNote : ($clean."\n".$newTaggedNote));
            $record->save();
        });
    }

    /**
     * Kiểm tra user có quyền duyệt đơn tăng ca (quản lý HR hoặc đúng người duyệt).
     */
    private function canApprove($user, OvertimeRequest $overtime): bool
    {
        if (! $user) {
            return false;
        }

        return $this->canManageHr($user) || (int) $overtime->approver_id === (int) $user->id;
    }

    /**
     * Kiểm tra user thuộc nhóm quản lý HR (admin / accounting / hr).
     */
    private function canManageHr($user): bool
    {
        if (! $user) {
            return false;
        }

        $roles = ['admin', 'accounting', 'hr'];

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles)) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        $rawRole = strtolower((string) ($user->role ?? ''));

        return in_array($rawRole, $roles, true);
    }

    /**
     * Lấy danh sách người duyệt khả dụng (tối đa 100 user đang hoạt động).
     */
    private function approverOptions()
    {
        $query = User::query()->orderBy('name');

        if (Schema::hasColumn('users', 'is_active')) {
            $query->where('is_active', 1);
        }

        return $query->limit(100)->get(['id', 'name', 'email']);
    }

    /**
     * Lấy danh sách nhân viên đang hoạt động kèm phòng ban để lọc.
     */
    private function employeeOptions()
    {
        return User::query()
            ->with('department')
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'department_id']);
    }
}
