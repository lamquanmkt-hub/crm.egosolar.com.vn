<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Hr\AttendanceLeaveNoteService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quản lý đơn nghỉ phép / làm online (WFH) của nhân viên.
 */
class LeaveRequestController extends Controller
{
    /**
     * Hiển thị danh sách đơn nghỉ phép theo bộ lọc; nhân viên thường chỉ thấy đơn của mình hoặc đơn mình duyệt.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $status = $request->input('status');
        $requestType = $request->input('request_type');
        $userId = $request->input('user_id');

        $canManageHr = $this->egoCanManageHr($user);

        $query = LeaveRequest::query()
            ->with(['user.department', 'approver'])
            ->orderByDesc('created_at');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        if ($requestType !== null && $requestType !== '') {
            $query->where('request_type', $requestType);
        }

        if ($canManageHr && $userId) {
            $query->where('user_id', $userId);
        }

        // Nhân viên thường chỉ thấy đơn của mình hoặc đơn mình được chọn duyệt.
        // Role admin/accounting/hr xem full toàn bộ đơn.
        if (!$canManageHr) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);

                if (Schema::hasColumn('hr_leave_requests', 'approver_id')) {
                    $q->orWhere('approver_id', $user->id);
                }
            });
        }

        $leaveRequests = $query->paginate(50)->withQueryString();

        // Tạo nhiều alias để không vỡ view cũ đang dùng tên biến khác.
        $requests = $leaveRequests;
        $leaves = $leaveRequests;
        $items = $leaveRequests;

        $employees = collect();

        if ($canManageHr) {
            $employees = User::query()
                ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
                ->orderBy('name')
                ->get();
        }

        return view('hr.leave.index', compact(
            'leaveRequests',
            'requests',
            'leaves',
            'items',
            'employees',
            'status',
            'requestType',
            'userId',
            'canManageHr'
        ));
    }

    /**
     * Hiển thị form tạo đơn nghỉ phép kèm danh sách người duyệt.
     */
    public function create()
    {
        $approvers = User::query()
            ->where('id', '<>', auth()->id())
            ->orderBy('name')
            ->get();

        return view('hr.leave.create', compact('approvers'));
    }

    /**
     * Tạo đơn nghỉ phép / WFH mới sau khi kiểm tra trùng thời gian.
     */
    public function store(Request $request)
    {
        $request->validate([
            'request_type' => ['required', 'in:leave,wfh'],
            'leave_type' => ['nullable', 'in:annual,unpaid,sick,personal,wfh'],
            'approver_id' => ['required', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'approver_id.required' => 'Bạn cần chọn người duyệt.',
            'approver_id.exists' => 'Người duyệt không hợp lệ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
        ]);

        $user = auth()->user();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $days = $startDate->diffInDays($endDate) + 1;

        $requestType = $request->request_type;
        $leaveType = $requestType === 'wfh' ? 'wfh' : $request->leave_type;

        $hasConflict = LeaveRequest::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate->toDateString())
                            ->where('end_date', '>=', $endDate->toDateString());
                    });
            })
            ->exists();

        if ($hasConflict) {
            return back()
                ->withInput()
                ->with('error', 'Bạn đã có đơn trùng thời gian trong khoảng này.');
        }

        LeaveRequest::create([
            'user_id' => $user->id,
            'approver_id' => $request->approver_id,
            'request_type' => $requestType,
            'leave_type' => $leaveType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return redirect()
            ->route('hr.leave.index')
            ->with('success', $requestType === 'wfh'
                ? 'Đăng ký làm online thành công, đang chờ duyệt.'
                : 'Tạo đơn nghỉ phép thành công, đang chờ duyệt.');
    }

    /**
     * Duyệt đơn nghỉ phép và đồng bộ ghi chú vào bảng chấm công.
     */
    public function approve(Request $request, LeaveRequest $leave)
    {
        $user = auth()->user();

        if (!$this->canApprove($user, $leave)) {
            abort(403, 'Bạn không có quyền duyệt đơn này.');
        }

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Đơn này đã được xử lý trước đó.');
        }

        $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave->update([
            'status' => 'approved',
            'approved_at' => now(),
            'rejected_at' => null,
            'approval_note' => $request->approval_note,
        ]);

        app(AttendanceLeaveNoteService::class)->sync($leave->fresh(['user', 'approver']), $user);

        return back()->with('success', 'Duyệt đơn thành công. Đã note đơn vào bảng chấm công.');
    }

    /**
     * Từ chối đơn nghỉ phép đang chờ duyệt.
     */
    public function reject(Request $request, LeaveRequest $leave)
    {
        $user = auth()->user();

        if (!$this->canApprove($user, $leave)) {
            abort(403, 'Bạn không có quyền từ chối đơn này.');
        }

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Đơn này đã được xử lý trước đó.');
        }

        $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'approved_at' => null,
            'approval_note' => $request->approval_note,
        ]);

        return back()->with('success', 'Đã từ chối đơn.');
    }

    /**
     * Kiểm tra user có quyền quản lý đơn (admin / sales_manager / marketing_manager) hay không.
     */
    private function canManageRequests($user): bool
    {
        return $user->hasRole('admin')
            || $user->hasRole('sales_manager')
            || $user->hasRole('marketing_manager');
    }

    /**
     * Kiểm tra user có quyền duyệt đơn này (quản lý hoặc đúng người được chọn duyệt).
     */
    private function canApprove($user, LeaveRequest $leave): bool
    {
        if ($user->hasRole('admin') || $user->hasRole('sales_manager') || $user->hasRole('marketing_manager')) {
            return true;
        }

        return (int) $leave->approver_id === (int) $user->id;
    }
    /**
     * Kiểm tra user thuộc nhóm quản lý HR (admin / accounting / hr) qua nhiều cơ chế role khác nhau.
     */
    private function egoCanManageHr($user): bool
    {
        if (!$user) {
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


}