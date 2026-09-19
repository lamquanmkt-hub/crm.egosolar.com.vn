<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrectionAttachment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\Hr\AttendanceCorrectionAccessService;
use App\Services\Hr\AttendanceCorrectionService;
use App\Support\SchemaCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceCorrectionController extends Controller
{
    public function __construct(
        private readonly AttendanceCorrectionAccessService $access,
        private readonly AttendanceCorrectionService $correctionService
    ) {}

    /**
     * Mọi tài khoản đăng nhập theo dõi đơn của mình; chỉ HR xem hàng chờ và lịch sử xử lý.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $canReview = $this->access->canReview($user);
        $tab = (string) $request->input('tab', $canReview ? 'approval' : 'mine');

        if (! in_array($tab, ['mine', 'approval'], true) || ($tab === 'approval' && ! $canReview)) {
            $tab = 'mine';
        }

        $status = trim((string) $request->input('status', ''));
        $userId = $request->integer('user_id') ?: null;

        $query = AttendanceCorrectionRequest::query()
            ->with(['user.department', 'attendanceRecord', 'reviewer', 'attachments'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        if ($tab === 'mine') {
            $query->where('user_id', $user->id);
        } else {
            if ($status === '') {
                $status = 'pending';
            }

            if ($userId) {
                $query->where('user_id', $userId);
            }
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $corrections = $query->paginate(20)->withQueryString();
        $myPendingCount = AttendanceCorrectionRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();
        $pendingApprovalCount = $canReview
            ? AttendanceCorrectionRequest::query()->where('status', 'pending')->count()
            : 0;

        $employees = collect();
        if ($canReview) {
            $employees = User::query()
                ->when(
                    SchemaCache::hasColumn('users', 'is_active'),
                    fn (Builder $builder) => $builder->where('is_active', true)
                )
                ->orderBy('name')
                ->get(['id', 'name', 'department_id']);
        }

        return view('hr.attendance.corrections.index', compact(
            'corrections',
            'employees',
            'tab',
            'status',
            'userId',
            'canReview',
            'myPendingCount',
            'pendingApprovalCount'
        ));
    }

    /**
     * Nhân viên gửi giờ đề nghị từ đúng bản ghi chấm công của mình.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'attendance_record_id' => ['required', 'integer', 'exists:attendance_records,id'],
            'requested_check_in_time' => ['required', 'date_format:H:i'],
            'requested_check_out_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,heic,heif,pdf,doc,docx,xls,xlsx,txt', 'max:10240'],
        ], [
            'requested_check_in_time.required' => 'Vui lòng nhập giờ check-in đề nghị.',
            'reason.required' => 'Vui lòng nhập lý do cần sửa chấm công.',
            'attachments.max' => 'Mỗi yêu cầu được gửi tối đa 5 ảnh/file giải trình.',
            'attachments.*.mimes' => 'File giải trình chỉ hỗ trợ ảnh, PDF, Word, Excel hoặc TXT.',
            'attachments.*.max' => 'Mỗi ảnh/file giải trình tối đa 10 MB.',
        ]);

        $user = $request->user();
        $record = AttendanceRecord::query()->findOrFail($validated['attendance_record_id']);
        abort_unless((int) $record->user_id === (int) $user->id, 403, 'Bạn không thể sửa chấm công của người khác.');

        if (Carbon::parse($record->work_date)->isFuture()) {
            return back()->withInput()->with('error', 'Không thể tạo yêu cầu cho ngày trong tương lai.');
        }

        [$requestedCheckIn, $requestedCheckOut] = $this->validatedDateTimes(
            $record,
            $validated['requested_check_in_time'],
            $validated['requested_check_out_time'] ?? null
        );

        $sameCheckIn = $record->check_in_at?->equalTo($requestedCheckIn) ?? false;
        $sameCheckOut = $record->check_out_at
            ? ($requestedCheckOut !== null && $record->check_out_at->equalTo($requestedCheckOut))
            : $requestedCheckOut === null;

        if ($sameCheckIn && $sameCheckOut) {
            throw ValidationException::withMessages([
                'requested_check_in_time' => 'Giờ đề nghị đang giống dữ liệu chấm công hiện tại.',
            ]);
        }

        $storedAttachmentPaths = [];

        try {
            $correction = DB::transaction(function () use (
                $record,
                $request,
                $user,
                $requestedCheckIn,
                $requestedCheckOut,
                $validated,
                &$storedAttachmentPaths
            ): AttendanceCorrectionRequest {
                $lockedRecord = AttendanceRecord::query()->lockForUpdate()->findOrFail($record->id);

                $alreadyPending = AttendanceCorrectionRequest::query()
                    ->where('attendance_record_id', $lockedRecord->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($alreadyPending) {
                    throw ValidationException::withMessages([
                        'attendance_record_id' => 'Ngày này đã có yêu cầu đang chờ HR duyệt.',
                    ]);
                }

                $correction = AttendanceCorrectionRequest::create([
                    'attendance_record_id' => $lockedRecord->id,
                    'user_id' => $user->id,
                    'work_date' => Carbon::parse($lockedRecord->work_date)->toDateString(),
                    'original_check_in_at' => $lockedRecord->check_in_at,
                    'original_check_out_at' => $lockedRecord->check_out_at,
                    'requested_check_in_at' => $requestedCheckIn,
                    'requested_check_out_at' => $requestedCheckOut,
                    'reason' => trim($validated['reason']),
                    'status' => 'pending',
                    'pending_guard' => 1,
                ]);

                foreach ($request->file('attachments', []) as $file) {
                    $safeBase = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
                    $safeBase = $safeBase !== '' ? $safeBase : 'giai-trinh';
                    $extension = strtolower($file->getClientOriginalExtension());
                    $filename = now()->format('YmdHis').'-'.Str::random(10).'-'.$safeBase.'.'.$extension;
                    $path = $file->storeAs(
                        'private/attendance-corrections/'.$correction->id,
                        $filename,
                        'local'
                    );

                    if (! $path) {
                        throw new \RuntimeException('Không thể lưu file giải trình.');
                    }

                    $storedAttachmentPaths[] = $path;

                    AttendanceCorrectionAttachment::create([
                        'attendance_correction_request_id' => $correction->id,
                        'uploaded_by' => $user->id,
                        'disk' => 'local',
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getClientMimeType(),
                        'file_size' => $file->getSize() ?: 0,
                    ]);
                }

                return $correction;
            }, 3);
        } catch (QueryException $e) {
            foreach ($storedAttachmentPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            if (str_contains(strtolower($e->getMessage()), 'attendance_correction_one_pending_unique')) {
                throw ValidationException::withMessages([
                    'attendance_record_id' => 'Ngày này đã có yêu cầu đang chờ HR duyệt.',
                ]);
            }

            throw $e;
        } catch (\Throwable $e) {
            foreach ($storedAttachmentPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }

        return redirect()
            ->route('hr.attendance-corrections.index', ['tab' => 'mine'])
            ->with('success', 'Đã gửi yêu cầu sửa chấm công #'.$correction->id.' đến HR.');
    }

    /**
     * HR có thể giữ giờ nhân viên đề nghị hoặc nhập giờ chốt trước khi duyệt.
     */
    public function approve(Request $request, AttendanceCorrectionRequest $correction)
    {
        abort_unless($this->access->canReview($request->user()), 403, 'Chỉ HR được duyệt sửa chấm công.');
        abort_if((int) $correction->user_id === (int) $request->user()->id, 403, 'HR không được tự duyệt yêu cầu sửa công của chính mình.');

        $validated = $request->validate([
            'approved_check_in_time' => ['required', 'date_format:H:i'],
            'approved_check_out_time' => ['nullable', 'date_format:H:i'],
            'review_note' => ['nullable', 'string', 'max:1500'],
        ], [
            'approved_check_in_time.required' => 'Vui lòng nhập giờ check-in được duyệt.',
        ]);

        DB::transaction(function () use ($correction, $request, $validated): void {
            $lockedCorrection = AttendanceCorrectionRequest::query()
                ->lockForUpdate()
                ->findOrFail($correction->id);

            if ($lockedCorrection->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Yêu cầu này đã được xử lý trước đó.',
                ]);
            }

            $record = AttendanceRecord::query()
                ->lockForUpdate()
                ->findOrFail($lockedCorrection->attendance_record_id);
            [$approvedCheckIn, $approvedCheckOut] = $this->validatedDateTimes(
                $record,
                $validated['approved_check_in_time'],
                $validated['approved_check_out_time'] ?? null
            );

            $beforeSnapshot = $this->correctionService->snapshot($record);
            $this->correctionService->apply(
                $lockedCorrection,
                $record,
                $approvedCheckIn,
                $approvedCheckOut,
                (string) $request->user()->name
            );
            $record->refresh();

            $lockedCorrection->update([
                'approved_check_in_at' => $approvedCheckIn,
                'approved_check_out_at' => $approvedCheckOut,
                'status' => 'approved',
                'pending_guard' => null,
                'reviewed_by' => $request->user()->id,
                'review_note' => $validated['review_note'] ?? null,
                'reviewed_at' => now(),
                'cancelled_at' => null,
                'before_apply_snapshot' => $beforeSnapshot,
                'applied_snapshot' => $this->correctionService->snapshot($record),
            ]);
        }, 3);

        return back()->with('success', 'Đã duyệt và tính lại bảng công của nhân viên.');
    }

    public function reject(Request $request, AttendanceCorrectionRequest $correction)
    {
        abort_unless($this->access->canReview($request->user()), 403, 'Chỉ HR được từ chối yêu cầu.');
        abort_if((int) $correction->user_id === (int) $request->user()->id, 403, 'HR không được tự xử lý yêu cầu sửa công của chính mình.');

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1500'],
        ], [
            'review_note.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        DB::transaction(function () use ($correction, $request, $validated): void {
            $lockedCorrection = AttendanceCorrectionRequest::query()
                ->lockForUpdate()
                ->findOrFail($correction->id);

            if ($lockedCorrection->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Yêu cầu này đã được xử lý trước đó.']);
            }

            $lockedCorrection->update([
                'status' => 'rejected',
                'pending_guard' => null,
                'reviewed_by' => $request->user()->id,
                'review_note' => $validated['review_note'],
                'reviewed_at' => now(),
                'cancelled_at' => null,
            ]);
        }, 3);

        return back()->with('success', 'Đã từ chối yêu cầu sửa chấm công.');
    }

    public function cancel(Request $request, AttendanceCorrectionRequest $correction)
    {
        abort_unless((int) $correction->user_id === (int) $request->user()->id, 403, 'Bạn không thể hủy đơn của người khác.');

        DB::transaction(function () use ($correction): void {
            $lockedCorrection = AttendanceCorrectionRequest::query()
                ->lockForUpdate()
                ->findOrFail($correction->id);

            if ($lockedCorrection->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Chỉ yêu cầu đang chờ duyệt mới được hủy.']);
            }

            $lockedCorrection->update([
                'status' => 'cancelled',
                'pending_guard' => null,
                'cancelled_at' => now(),
            ]);
        }, 3);

        return back()->with('success', 'Đã hủy yêu cầu sửa chấm công.');
    }

    public function downloadAttachment(
        Request $request,
        AttendanceCorrectionRequest $correction,
        AttendanceCorrectionAttachment $attachment
    ): StreamedResponse {
        abort_unless(
            (int) $attachment->attendance_correction_request_id === (int) $correction->id,
            404
        );

        $canView = (int) $correction->user_id === (int) $request->user()->id
            || $this->access->canReview($request->user());

        abort_unless($canView, 403, 'Bạn không có quyền xem file giải trình này.');
        abort_unless(
            Storage::disk($attachment->disk)->exists($attachment->file_path),
            404,
            'File giải trình không còn tồn tại.'
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream']
        );
    }

    /** @return array{0: Carbon, 1: Carbon|null} */
    private function validatedDateTimes(
        AttendanceRecord $record,
        string $checkInTime,
        ?string $checkOutTime
    ): array {
        $date = Carbon::parse($record->work_date)->toDateString();
        $checkInAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$checkInTime);
        $checkOutAt = filled($checkOutTime)
            ? Carbon::createFromFormat('Y-m-d H:i', $date.' '.$checkOutTime)
            : null;

        if ($checkOutAt && $checkOutAt->lte($checkInAt)) {
            throw ValidationException::withMessages([
                'requested_check_out_time' => 'Giờ check-out phải lớn hơn giờ check-in.',
                'approved_check_out_time' => 'Giờ check-out phải lớn hơn giờ check-in.',
            ]);
        }

        if ($checkOutAt && $checkInAt->diffInHours($checkOutAt) > 20) {
            throw ValidationException::withMessages([
                'requested_check_out_time' => 'Khoảng thời gian làm việc không được vượt quá 20 giờ.',
                'approved_check_out_time' => 'Khoảng thời gian làm việc không được vượt quá 20 giờ.',
            ]);
        }

        return [$checkInAt, $checkOutAt];
    }
}
