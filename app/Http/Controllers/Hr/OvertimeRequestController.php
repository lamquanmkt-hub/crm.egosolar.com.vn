<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Hr\OvertimeAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quản lý đơn đăng ký tăng ca của nhân viên.
 * Phân quyền nằm ở OvertimeAccessService (không ai tự duyệt đơn của chính mình).
 */
class OvertimeRequestController extends Controller
{
    public function __construct(private readonly OvertimeAccessService $access) {}

    /**
     * Danh sách đơn tăng ca theo tháng, 2 tab: "Của tôi" và "Cần duyệt" (người duyệt / HR).
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));
        $status = $request->input('status');
        $userId = $request->input('user_id');
        $canReview = $this->access->canReview($user);
        $canManage = $this->access->canManageAll($user);
        $tab = $request->input('tab') === 'approval' && $canReview ? 'approval' : 'mine';

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();

        $base = OvertimeRequest::query()
            ->whereBetween('overtime_date', [$start->toDateString(), $end->toDateString()]);

        if ($tab === 'mine') {
            $base->where('user_id', $user->id);
        } else {
            // Cần duyệt: đơn của người khác giao cho mình; HR / Admin / Kế toán thấy mọi đơn của người khác.
            $base->where('user_id', '!=', $user->id);
            if (! $canManage) {
                $base->where('approver_id', $user->id);
            }
            if ($userId && $canManage) {
                $base->where('user_id', $userId);
            }
        }

        $summary = [
            'total' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
            'hours' => (float) (clone $base)->where('status', 'approved')->sum('hours'),
        ];

        $requests = (clone $base)
            ->with(['user.department', 'approver', 'approvedBy'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('overtime_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $pendingReviewCount = $canReview
            ? $this->access->scopePendingForReviewer(OvertimeRequest::query(), $user)->count()
            : 0;
        $myPendingCount = OvertimeRequest::query()->where('user_id', $user->id)->where('status', 'pending')->count();

        $employees = $canManage && $tab === 'approval' ? $this->employeeOptions() : collect();
        $access = $this->access;

        return view('hr.overtime.index', compact(
            'requests',
            'summary',
            'employees',
            'month',
            'status',
            'userId',
            'canManage',
            'canReview',
            'tab',
            'pendingReviewCount',
            'myPendingCount',
            'access'
        ));
    }

    /**
     * Form đăng ký tăng ca kèm danh sách người duyệt (nhóm quản lý, không gồm chính mình).
     */
    public function create()
    {
        $approvers = $this->access->approverOptions(auth()->id());

        return view('hr.overtime.create', compact('approvers'));
    }

    /**
     * Tạo đơn tăng ca: bắt buộc người duyệt hợp lệ, qua đêm tự cộng 1 ngày, tối đa 16 giờ/lần,
     * không trùng khung giờ với đơn đang chờ / đã duyệt của chính mình.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'overtime_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'approver_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ], [
            'approver_id.required' => 'Vui lòng chọn người duyệt.',
            'reason.required' => 'Vui lòng nhập lý do tăng ca.',
            'reason.min' => 'Lý do tăng ca cần ít nhất 5 ký tự.',
        ]);

        $approverId = (int) $data['approver_id'];
        if (! $this->access->approverOptions((int) $user->id)->contains('id', $approverId)) {
            return back()
                ->withInput()
                ->withErrors(['approver_id' => 'Người duyệt không hợp lệ: chọn quản lý / trưởng phòng / HR / admin, không chọn chính mình.']);
        }

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

        $overlap = OvertimeRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt)
            ->first();

        if ($overlap) {
            return back()
                ->withInput()
                ->with('error', 'Khung giờ này trùng với đơn tăng ca ngày '.$overlap->overtime_date->format('d/m/Y')
                    .' ('.$overlap->start_at->format('H:i').' - '.$overlap->end_at->format('H:i').', '.$overlap->status_label.').');
        }

        OvertimeRequest::create([
            'user_id' => $user->id,
            'approver_id' => $approverId,
            'overtime_date' => $date,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'hours' => round($minutes / 60, 2),
            'reason' => trim($data['reason']),
            'status' => 'pending',
        ]);

        return redirect()
            ->route('hr.overtime.index', ['month' => Carbon::parse($date)->format('Y-m')])
            ->with('success', 'Đã gửi đơn đăng ký tăng ca, chờ người duyệt xử lý.');
    }

    /**
     * Duyệt đơn tăng ca (chỉ đơn đang chờ) và đồng bộ ghi chú vào bản ghi chấm công.
     */
    public function approve(Request $request, OvertimeRequest $overtime)
    {
        $request->validate([
            'approval_note' => ['nullable', 'string', 'max:3000'],
        ]);

        return $this->decide($request, $overtime, 'approved');
    }

    /**
     * Từ chối đơn tăng ca (chỉ đơn đang chờ).
     */
    public function reject(Request $request, OvertimeRequest $overtime)
    {
        $request->validate([
            'approval_note' => ['nullable', 'string', 'max:3000'],
        ]);

        return $this->decide($request, $overtime, 'rejected');
    }

    private function decide(Request $request, OvertimeRequest $overtime, string $decision)
    {
        $user = auth()->user();

        abort_unless($this->access->canApprove($user, $overtime), 403, 'Bạn không có quyền duyệt đơn tăng ca này.');

        $done = DB::transaction(function () use ($request, $overtime, $decision, $user) {
            $locked = OvertimeRequest::query()->lockForUpdate()->find($overtime->id);

            if (! $locked || $locked->status !== 'pending') {
                return false;
            }

            $locked->update([
                'status' => $decision,
                'approved_by' => $user->id,
                'approved_at' => $decision === 'approved' ? now() : null,
                'rejected_at' => $decision === 'rejected' ? now() : null,
                'approval_note' => $request->approval_note,
            ]);

            if ($decision === 'approved') {
                $this->syncAttendanceNote($locked->fresh(['user', 'approver', 'approvedBy']));
            }

            return true;
        });

        if (! $done) {
            return back()->with('error', 'Đơn tăng ca này đã được xử lý trước đó.');
        }

        return back()->with('success', $decision === 'approved'
            ? 'Đã duyệt đơn tăng ca và ghi vào chấm công.'
            : 'Đã từ chối đơn tăng ca.');
    }

    /**
     * Ghi / thay thế ghi chú tăng ca (theo tag) vào bản ghi chấm công của ngày tương ứng.
     */
    private function syncAttendanceNote(OvertimeRequest $overtime): void
    {
        $date = Carbon::parse($overtime->overtime_date)->toDateString();

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
