<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApprovalLog;
use App\Models\LeaveRequestAttachment;
use App\Models\User;
use App\Services\Hr\AttendanceLeaveNoteService;
use App\Services\Hr\LeaveApprovalAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveApprovalAccessService $approvalAccess
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $canManageAll = $this->approvalAccess->canManageAll($user);
        $canReview = $this->approvalAccess->canReview($user);

        $tab = (string) $request->input('tab', 'mine');

        if (! in_array($tab, ['mine', 'approval', 'all'], true)) {
            $tab = 'mine';
        }

        if ($tab === 'approval' && ! $canReview) {
            $tab = 'mine';
        }

        if ($tab === 'all' && ! $canManageAll) {
            $tab = $canReview ? 'approval' : 'mine';
        }

        $status = trim((string) $request->input('status', ''));
        $requestType = trim((string) $request->input('request_type', ''));
        $userId = $request->integer('user_id') ?: null;

        $query = LeaveRequest::query()
            ->with([
                'user.department',
                'approver',
                'attachments',
                'approvalLogs.fromApprover',
                'approvalLogs.toApprover',
                'approvalLogs.actor',
            ])
            ->orderByDesc('created_at');

        if ($tab === 'mine') {
            $query->where('user_id', $user->id);
        } elseif ($tab === 'approval') {
            $this->approvalAccess->scopeReviewable($query, $user);
        } elseif ($userId) {
            $query->where('user_id', $userId);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($requestType !== '') {
            $query->where('request_type', $requestType);
        }

        $leaveRequests = $query
            ->paginate(24)
            ->withQueryString();

        $leaveRequests->getCollection()->transform(function (LeaveRequest $leave) use ($user): LeaveRequest {
            $leave->setAttribute('_can_approve', $leave->status === 'pending' && $this->approvalAccess->canApprove($user, $leave));
            $leave->setAttribute('_can_transfer', $this->approvalAccess->canTransfer($user, $leave));
            $leave->setAttribute('_can_cancel', $leave->status === 'pending' && (int) $leave->user_id === (int) $user->id);
            $leave->setAttribute('_can_delete_attachment', $leave->status === 'pending' && ((int) $leave->user_id === (int) $user->id || $this->approvalAccess->canManageAll($user)));

            return $leave;
        });

        $mineCount = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->count();

        $minePendingCount = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $pendingApprovalCount = 0;

        if ($canReview) {
            $pendingQuery = LeaveRequest::query()->where('status', 'pending');
            $pendingApprovalCount = $this->approvalAccess
                ->scopeReviewable($pendingQuery, $user)
                ->count();
        }

        $approvedThisMonth = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('start_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('days');

        $employees = collect();

        if ($canManageAll) {
            $employees = User::query()
                ->when(
                    Schema::hasColumn('users', 'is_active'),
                    fn (Builder $builder) => $builder->where('is_active', true)
                )
                ->orderBy('name')
                ->get(['id', 'name', 'department_id']);
        }

        $approvers = $this->approvalAccess->eligibleApprovers($user);

        return view('hr.leave.index', [
            'leaveRequests' => $leaveRequests,
            'requests' => $leaveRequests,
            'employees' => $employees,
            'approvers' => $approvers,
            'tab' => $tab,
            'status' => $status,
            'requestType' => $requestType,
            'userId' => $userId,
            'canManageAll' => $canManageAll,
            'canReview' => $canReview,
            'mineCount' => $mineCount,
            'minePendingCount' => $minePendingCount,
            'pendingApprovalCount' => $pendingApprovalCount,
            'approvedThisMonth' => (float) $approvedThisMonth,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        $approvers = $this->approvalAccess->eligibleApprovers($user);
        $defaultApprover = $this->approvalAccess->defaultApprover($user, $approvers);
        $presetType = (string) $request->query('request_type', 'leave');

        if (! in_array($presetType, ['leave', 'wfh', 'business_trip', 'late', 'early_leave'], true)) {
            $presetType = 'leave';
        }

        return view('hr.leave.create', [
            'approvers' => $approvers,
            'defaultApproverId' => $defaultApprover?->id,
            'presetType' => $presetType,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'request_type' => ['required', 'in:leave,wfh,business_trip,late,early_leave'],
            'leave_type' => ['nullable', 'in:annual,unpaid,sick,personal,wfh,business_trip,late,early_leave'],
            'approver_id' => ['required', 'integer', 'exists:users,id'],
            'start_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:3000'],
            'attachments' => ['nullable', 'array', 'max:8'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx', 'max:10240'],
        ], [
            'approver_id.required' => 'Vui lòng chọn người duyệt.',
            'approver_id.exists' => 'Người duyệt không hợp lệ.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu.',
            'reason.required' => 'Vui lòng nhập lý do đăng ký.',
            'attachments.max' => 'Mỗi đơn được đính kèm tối đa 8 file.',
            'attachments.*.mimes' => 'File chứng minh chỉ hỗ trợ ảnh, PDF, Word hoặc Excel.',
            'attachments.*.max' => 'Mỗi file chứng minh tối đa 10 MB.',
        ]);

        $user = $request->user();
        $approvers = $this->approvalAccess->eligibleApprovers($user);

        abort_unless(
            $approvers->contains(fn (User $approver): bool => (int) $approver->id === (int) $validated['approver_id']),
            422,
            'Người duyệt được chọn không thuộc danh sách được phép.'
        );

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->startOfDay();
        $requestType = $validated['request_type'];
        $leaveType = $requestType === 'leave'
            ? ($validated['leave_type'] ?? 'annual')
            : $requestType;

        $hasConflict = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString())
            ->exists();

        if ($hasConflict) {
            return back()
                ->withInput()
                ->with('error', 'Bạn đã có một đơn đang chờ hoặc đã duyệt bị trùng thời gian này.');
        }

        $days = $startDate->diffInDays($endDate) + 1;

        $leave = DB::transaction(function () use (
            $request,
            $validated,
            $user,
            $startDate,
            $endDate,
            $requestType,
            $leaveType,
            $days
        ): LeaveRequest {
            $leave = LeaveRequest::create([
                'user_id' => $user->id,
                'approver_id' => (int) $validated['approver_id'],
                'request_type' => $requestType,
                'leave_type' => $leaveType,
                'start_date' => $startDate->toDateString(),
                'start_time' => $validated['start_time'] ?? null,
                'end_date' => $endDate->toDateString(),
                'end_time' => $validated['end_time'] ?? null,
                'days' => $days,
                'reason' => trim($validated['reason']),
                'status' => 'pending',
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $safeBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                $safeBase = $safeBase !== '' ? $safeBase : 'chung-minh';
                $filename = now()->format('YmdHis').'-'.Str::random(8).'-'.$safeBase.'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs('private/leave-requests/'.$leave->id, $filename, 'local');

                LeaveRequestAttachment::create([
                    'leave_request_id' => $leave->id,
                    'uploaded_by' => $user->id,
                    'disk' => 'local',
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize() ?: 0,
                ]);
            }

            LeaveRequestApprovalLog::create([
                'leave_request_id' => $leave->id,
                'action' => 'created',
                'from_approver_id' => null,
                'to_approver_id' => $leave->approver_id,
                'action_by' => $user->id,
                'note' => 'Tạo đơn và gửi duyệt.',
            ]);

            return $leave;
        });

        return redirect()
            ->route('hr.leave.index', ['tab' => 'mine'])
            ->with('success', 'Đã gửi đơn #'.$leave->id.' và chuyển đến người duyệt.');
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        $user = $request->user();

        abort_unless($this->approvalAccess->canApprove($user, $leave), 403, 'Bạn không có quyền duyệt đơn này.');

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Đơn này đã được xử lý trước đó.');
        }

        $validated = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1500'],
        ]);

        DB::transaction(function () use ($leave, $user, $validated): void {
            $leave->update([
                'status' => 'approved',
                'approved_at' => now(),
                'rejected_at' => null,
                'approval_note' => $validated['approval_note'] ?? null,
            ]);

            LeaveRequestApprovalLog::create([
                'leave_request_id' => $leave->id,
                'action' => 'approved',
                'from_approver_id' => $leave->approver_id,
                'to_approver_id' => $leave->approver_id,
                'action_by' => $user->id,
                'note' => $validated['approval_note'] ?? null,
            ]);
        });

        app(AttendanceLeaveNoteService::class)->sync($leave->fresh(['user', 'approver']), $user);

        return back()->with('success', 'Đã duyệt đơn và đồng bộ ghi chú vào bảng công.');
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $user = $request->user();

        abort_unless($this->approvalAccess->canApprove($user, $leave), 403, 'Bạn không có quyền từ chối đơn này.');

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Đơn này đã được xử lý trước đó.');
        }

        $validated = $request->validate([
            'approval_note' => ['required', 'string', 'max:1500'],
        ], [
            'approval_note.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        DB::transaction(function () use ($leave, $user, $validated): void {
            $leave->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'approved_at' => null,
                'approval_note' => $validated['approval_note'],
            ]);

            LeaveRequestApprovalLog::create([
                'leave_request_id' => $leave->id,
                'action' => 'rejected',
                'from_approver_id' => $leave->approver_id,
                'to_approver_id' => $leave->approver_id,
                'action_by' => $user->id,
                'note' => $validated['approval_note'],
            ]);
        });

        return back()->with('success', 'Đã từ chối đơn.');
    }

    public function transferApprover(Request $request, LeaveRequest $leave)
    {
        $user = $request->user();

        abort_unless($this->approvalAccess->canTransfer($user, $leave), 403, 'Bạn không có quyền chuyển người duyệt đơn này.');

        $validated = $request->validate([
            'approver_id' => ['required', 'integer', 'exists:users,id'],
            'transfer_note' => ['required', 'string', 'max:1500'],
        ], [
            'approver_id.required' => 'Vui lòng chọn người duyệt mới.',
            'transfer_note.required' => 'Vui lòng nhập lý do chuyển người duyệt.',
        ]);

        $leave->loadMissing('user');
        $eligible = $this->approvalAccess->eligibleApprovers($leave->user);
        $newApproverId = (int) $validated['approver_id'];

        abort_unless(
            $eligible->contains(fn (User $candidate): bool => (int) $candidate->id === $newApproverId),
            422,
            'Người duyệt mới không thuộc danh sách được phép.'
        );

        if ((int) $leave->approver_id === $newApproverId) {
            return back()->with('error', 'Đây đã là người duyệt hiện tại.');
        }

        DB::transaction(function () use ($leave, $user, $newApproverId, $validated): void {
            $oldApproverId = $leave->approver_id;

            $leave->update([
                'approver_id' => $newApproverId,
            ]);

            LeaveRequestApprovalLog::create([
                'leave_request_id' => $leave->id,
                'action' => 'transferred',
                'from_approver_id' => $oldApproverId,
                'to_approver_id' => $newApproverId,
                'action_by' => $user->id,
                'note' => $validated['transfer_note'],
            ]);
        });

        return back()->with('success', 'Đã chuyển đơn sang người duyệt mới.');
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        abort_unless((int) $leave->user_id === (int) $request->user()->id, 403, 'Bạn không thể hủy đơn của người khác.');

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Chỉ đơn đang chờ duyệt mới được hủy.');
        }

        DB::transaction(function () use ($leave, $request): void {
            $leave->update(['status' => 'cancelled']);

            LeaveRequestApprovalLog::create([
                'leave_request_id' => $leave->id,
                'action' => 'cancelled',
                'from_approver_id' => $leave->approver_id,
                'to_approver_id' => $leave->approver_id,
                'action_by' => $request->user()->id,
                'note' => 'Nhân viên hủy đơn.',
            ]);
        });

        return back()->with('success', 'Đã hủy đơn.');
    }

    public function downloadAttachment(
        Request $request,
        LeaveRequest $leave,
        LeaveRequestAttachment $attachment
    ): StreamedResponse {
        abort_unless((int) $attachment->leave_request_id === (int) $leave->id, 404);
        abort_unless($this->approvalAccess->canView($request->user(), $leave), 403, 'Bạn không có quyền xem file này.');
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->file_path), 404, 'File không còn tồn tại.');

        return Storage::disk($attachment->disk)->download(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }

    public function deleteAttachment(
        Request $request,
        LeaveRequest $leave,
        LeaveRequestAttachment $attachment
    ) {
        abort_unless((int) $attachment->leave_request_id === (int) $leave->id, 404);

        $canDelete = $leave->status === 'pending'
            && ((int) $leave->user_id === (int) $request->user()->id
                || $this->approvalAccess->canManageAll($request->user()));

        abort_unless($canDelete, 403, 'Bạn không có quyền xóa file này.');

        Storage::disk($attachment->disk)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Đã xóa file chứng minh.');
    }
}
