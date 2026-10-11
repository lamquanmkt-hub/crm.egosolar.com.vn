<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Kpi\HcnsSourceReviews;
use App\Services\Hr\Kpi\Sources\DocumentAccuracySource;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Controller quản lý bàn giao hồ sơ tài liệu: vòng đời trạng thái, file đính kèm và lịch sử xử lý.
 */
class DocumentHandoverController extends Controller
{
    protected array $statuses = [
        'created' => 'Tạo hồ sơ',
        'assigned' => 'Giao HS',
        'received' => 'Nhận HS',
        'sent' => 'Gửi HS',
        'returned' => 'Trả HS',
        'completed' => 'Hoàn tất',
        'archived' => 'Lưu trữ HS',
    ];

    protected array $priorities = [
        'low' => 'Thấp',
        'normal' => 'Bình thường',
        'high' => 'Cao',
        'urgent' => 'Gấp',
    ];

    protected array $nextStatuses = [
        'created' => 'assigned',
        'assigned' => 'received',
        'received' => 'sent',
        'sent' => 'returned',
        'returned' => 'completed',
        'completed' => 'archived',
    ];

    /**
     * Hiển thị danh sách hồ sơ bàn giao với tìm kiếm, lọc trạng thái / độ ưu tiên và thống kê.
     */
    public function index(Request $request)
    {
        $this->ensureTables();

        $query = DB::table('hr_document_handovers as h')
            ->leftJoin('users as creator', 'creator.id', '=', 'h.created_by')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'h.assigned_to')
            ->select('h.*', 'creator.name as creator_name', 'assignee.name as assignee_name');

        if ($request->filled('q')) {
            $q = trim((string) $request->q);

            $query->where(function ($x) use ($q) {
                $x->where('h.code', 'like', '%'.$q.'%')
                    ->orWhere('h.title', 'like', '%'.$q.'%')
                    ->orWhere('h.customer_name', 'like', '%'.$q.'%')
                    ->orWhere('h.department_name', 'like', '%'.$q.'%')
                    ->orWhere('h.document_type', 'like', '%'.$q.'%');
            });
        }

        if ($request->filled('status') && array_key_exists($request->status, $this->statuses)) {
            $query->where('h.status', $request->status);
        }

        if ($request->filled('priority') && array_key_exists($request->priority, $this->priorities)) {
            $query->where('h.priority', $request->priority);
        }

        $items = $query
            ->orderByRaw("FIELD(h.status, 'created','assigned','received','sent','returned','completed','archived')")
            ->orderByDesc('h.updated_at')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'total' => DB::table('hr_document_handovers')->count(),
            'processing' => DB::table('hr_document_handovers')->whereNotIn('status', ['completed', 'archived'])->count(),
            'completed' => DB::table('hr_document_handovers')->where('status', 'completed')->count(),
            'archived' => DB::table('hr_document_handovers')->where('status', 'archived')->count(),
        ];

        return view('hr.document-handovers.index', [
            'items' => $items,
            'stats' => $stats,
            'users' => $this->users(),
            'statuses' => $this->statuses,
            'priorities' => $this->priorities,
        ]);
    }

    /**
     * Hiển thị chi tiết hồ sơ bàn giao kèm lịch sử xử lý và danh sách file.
     *
     * @param  int|string  $id  ID hồ sơ
     */
    public function show($id)
    {
        $this->ensureTables();

        $item = DB::table('hr_document_handovers as h')
            ->leftJoin('users as creator', 'creator.id', '=', 'h.created_by')
            ->leftJoin('users as assignee', 'assignee.id', '=', 'h.assigned_to')
            ->select('h.*', 'creator.name as creator_name', 'assignee.name as assignee_name')
            ->where('h.id', (int) $id)
            ->first();

        abort_unless($item, 404);

        $histories = DB::table('hr_document_handover_histories as his')
            ->leftJoin('users as u', 'u.id', '=', 'his.user_id')
            ->select('his.*', 'u.name as user_name')
            ->where('his.handover_id', (int) $id)
            ->orderByDesc('his.created_at')
            ->get();

        $files = DB::table('hr_document_handover_files as f')
            ->leftJoin('users as u', 'u.id', '=', 'f.uploaded_by')
            ->select('f.*', 'u.name as uploader_name')
            ->where('f.handover_id', (int) $id)
            ->orderByDesc('f.created_at')
            ->get();

        $kpiReady = DocumentAccuracySource::ready();
        $review = $kpiReady ? $this->kpiReview((int) $id) : null;
        $user = Auth::user();

        return view('hr.document-handovers.show', [
            'item' => $item,
            'histories' => $histories,
            'files' => $files,
            'users' => $this->users(),
            'statuses' => $this->statuses,
            'priorities' => $this->priorities,
            'nextStatuses' => $this->nextStatuses,
            'kpiReady' => $kpiReady,
            'kpiReview' => $review,
            'kpiEvents' => $review ? app(HcnsSourceReviews::class)->events((int) $review->id) : collect(),
            'kpiRow' => $kpiReady && ! empty($item->kpi_tracked) ? app(DocumentAccuracySource::class)->evaluate((object) ((array) $item + [
                'review_status' => $review->status ?? null, 'result' => $review->result ?? null, 'checks' => $review->checks ?? null,
                'first_submitted_at' => $review->first_submitted_at ?? null, 'submitted_at' => $review->submitted_at ?? null,
                'submission_count' => $review->submission_count ?? 0, 'confirmed_at' => $review->confirmed_at ?? null,
                'confirmed_by' => $review->confirmed_by ?? null, 'returned_at' => $review->returned_at ?? null, 'return_reason' => $review->return_reason ?? null,
            ]), DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all()) : null,
            'kpiMissing' => $kpiReady && ! empty($item->kpi_tracked) ? app(DocumentAccuracySource::class)->missingFields($item) : [],
            'kpiIsProcessor' => $user && (int) ($item->processor_id ?? 0) === (int) $user->id,
            'kpiCanReview' => $user && $this->canReviewKpi($item, $user),
            'documentClasses' => (array) config('hcns_kpi.document_classes', []),
            'documentChecks' => (array) config('hcns_kpi.document_checks', []),
        ]);
    }

    /** Người xử lý lưu nháp phần phân loại/luân chuyển (chưa gửi, chưa tính KPI). */
    public function kpiDraft(Request $request, $id)
    {
        [$item, $user] = $this->kpiActor((int) $id, 'processor');
        app(HcnsSourceReviews::class)->saveDraft(DocumentAccuracySource::SOURCE_TYPE, (int) $id, DocumentAccuracySource::CODE,
            (int) $item->processor_id, $item->reviewer_id ? (int) $item->reviewer_id : null,
            app(DocumentAccuracySource::class)->declared($item), app(DocumentAccuracySource::class)->fingerprint($item),
            (int) $user->id, $this->kpiNote($request));
        $this->history((int) $id, $item->status, 'KPI HC07: lưu nháp', $this->kpiNote($request));

        return back()->with('success', 'Đã lưu nháp. Chưa gửi người kiểm, chưa tính KPI.');
    }

    /** Người xử lý gửi kiểm tra phân loại/luân chuyển. */
    public function kpiSubmit(Request $request, $id)
    {
        [$item, $user] = $this->kpiActor((int) $id, 'processor');
        $missing = app(DocumentAccuracySource::class)->missingFields($item);
        if ($missing !== []) {
            return back()->with('error', 'Chưa gửi kiểm được — thiếu: '.implode(', ', $missing).'. Bấm "Sửa hồ sơ" để bổ sung.');
        }
        app(HcnsSourceReviews::class)->submit(DocumentAccuracySource::SOURCE_TYPE, (int) $id, DocumentAccuracySource::CODE,
            (int) $item->processor_id, (int) $item->reviewer_id,
            app(DocumentAccuracySource::class)->declared($item), app(DocumentAccuracySource::class)->fingerprint($item),
            (int) $user->id, $this->kpiNote($request));
        $this->history((int) $id, $item->status, 'KPI HC07: gửi kiểm tra', $this->kpiNote($request));

        return back()->with('success', 'Đã gửi kiểm tra phân loại/luân chuyển. Chờ người kiểm xác nhận.');
    }

    /** Người kiểm (khác người xử lý) xác nhận từng mục đạt/không đạt. */
    public function kpiConfirm(Request $request, $id)
    {
        [$item, $user] = $this->kpiActor((int) $id, 'reviewer');
        $checkKeys = array_keys((array) config('hcns_kpi.document_checks', []));
        $data = $request->validate([
            'checks' => ['required', 'array'],
            'checks.*' => ['required', Rule::in(['pass', 'fail'])],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['checks.required' => 'Chọn Đạt/Không đạt cho từng mục kiểm tra.']);
        $checks = array_intersect_key((array) $data['checks'], array_flip($checkKeys));
        if (count($checks) !== count($checkKeys)) {
            throw ValidationException::withMessages(['checks' => 'Chọn Đạt/Không đạt cho đủ '.count($checkKeys).' mục kiểm tra.']);
        }
        if (collect($checks)->contains('fail') && trim((string) ($data['note'] ?? '')) === '') {
            throw ValidationException::withMessages(['note' => 'Ghi rõ lỗi khi có mục không đạt.']);
        }

        $review = $this->kpiReview((int) $id);
        abort_unless($review, 404);
        app(HcnsSourceReviews::class)->confirm($review, $checks, app(DocumentAccuracySource::class)->fingerprint($item), (int) $user->id, $data['note'] ?? null);
        $this->history((int) $id, $item->status, 'KPI HC07: xác nhận kiểm tra', $data['note'] ?? null);

        return back()->with('success', 'Đã xác nhận kết quả kiểm tra.');
    }

    /** Người kiểm trả bổ sung có lý do. */
    public function kpiReturn(Request $request, $id)
    {
        [$item, $user] = $this->kpiActor((int) $id, 'reviewer');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']], [
            'reason.required' => 'Ghi lý do trả bổ sung.',
        ]);
        $review = $this->kpiReview((int) $id);
        abort_unless($review, 404);
        app(HcnsSourceReviews::class)->returnForFix($review, trim($data['reason']), (int) $user->id);
        $this->history((int) $id, $item->status, 'KPI HC07: trả bổ sung', $data['reason']);

        return back()->with('success', 'Đã trả bổ sung cho người xử lý.');
    }

    /** @return array{0: object, 1: \App\Models\User} */
    private function kpiActor(int $id, string $role): array
    {
        $this->ensureTables();
        abort_unless(DocumentAccuracySource::ready(), 404);
        $item = DB::table('hr_document_handovers')->where('id', $id)->first();
        abort_unless($item && ! empty($item->kpi_tracked), 404);
        if ($locked = $this->kpiLockError($id)) {
            throw ValidationException::withMessages(['kpi' => $locked]);
        }
        $user = Auth::user();
        if ($role === 'processor') {
            abort_unless((int) ($item->processor_id ?? 0) === (int) $user->id, 403, 'Chỉ người xử lý HCNS của hồ sơ được lưu nháp/gửi kiểm.');
        } else {
            abort_unless($this->canReviewKpi($item, $user), 403, 'Bạn không phải người kiểm của hồ sơ này.');
        }

        return [$item, $user];
    }

    /** Người kiểm được chỉ định, hoặc Ban Giám đốc/Admin; không bao giờ là người xử lý. */
    private function canReviewKpi(object $item, $user): bool
    {
        if ((int) ($item->processor_id ?? 0) === (int) $user->id) {
            return false;
        }

        return (int) ($item->reviewer_id ?? 0) === (int) $user->id || PayrollAccess::canApprove($user);
    }

    private function kpiNote(Request $request): ?string
    {
        $note = trim((string) $request->input('note', ''));

        return $note === '' ? null : mb_substr($note, 0, 2000);
    }

    private function kpiReview(int $id): ?object
    {
        return HcnsSourceReviews::ready()
            ? app(HcnsSourceReviews::class)->find(DocumentAccuracySource::SOURCE_TYPE, $id, DocumentAccuracySource::CODE)
            : null;
    }

    /**
     * Trường theo dõi KPI HC07 từ form tạo/sửa. Người kiểm khác người xử lý; đã gửi kiểm thì không bỏ theo dõi.
     */
    private function kpiFields(Request $request, ?object $row): array
    {
        if (! DocumentAccuracySource::ready()) {
            return [];
        }
        $tracked = $request->boolean('kpi_tracked');
        $data = $request->validate([
            'kpi_tracked' => ['nullable', 'boolean'],
            'processor_id' => [$tracked ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'reviewer_id' => [$tracked ? 'required' : 'nullable', 'integer', 'exists:users,id', 'different:processor_id'],
            'classification' => ['nullable', Rule::in(array_keys((array) config('hcns_kpi.document_classes', [])))],
            'routed_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'routed_to_note' => ['nullable', 'string', 'max:255'],
        ], [
            'processor_id.required' => 'Chọn người xử lý HCNS chịu trách nhiệm.',
            'reviewer_id.required' => 'Chọn người kiểm tra độc lập.',
            'reviewer_id.different' => 'Người kiểm phải khác người xử lý.',
        ]);

        if ($row && ! empty($row->kpi_tracked) && ! $tracked) {
            $review = $this->kpiReview((int) $row->id);
            if ($review && $review->first_submitted_at) {
                throw ValidationException::withMessages(['kpi_tracked' => 'Hồ sơ đã gửi kiểm KPI HC07 — không bỏ theo dõi được.']);
            }
        }

        return [
            // Hồ sơ đã có giữ nguyên company_id (không đổi công ty của bản ghi cũ); hồ sơ mới gắn công ty khóa.
            'company_id' => $row ? $row->company_id : EgoCompanyLock::id(),
            'kpi_tracked' => $tracked,
            'processor_id' => $data['processor_id'] ?? null,
            'reviewer_id' => $data['reviewer_id'] ?? null,
            'classification' => $data['classification'] ?? null,
            'routed_to_user_id' => $data['routed_to_user_id'] ?? null,
            'routed_to_note' => trim((string) ($data['routed_to_note'] ?? '')) ?: null,
        ];
    }

    /** Sau khi hồ sơ đổi: nội dung khác bản đã gửi/xác nhận → phải gửi và kiểm lại. */
    private function invalidateKpiReview(int $id, string $what): void
    {
        if (! DocumentAccuracySource::ready()) {
            return;
        }
        $item = DB::table('hr_document_handovers')->where('id', $id)->first();
        if (! $item) {
            return;
        }
        $before = $this->kpiReview($id);
        $after = app(HcnsSourceReviews::class)->invalidateIfChanged(DocumentAccuracySource::SOURCE_TYPE, $id, DocumentAccuracySource::CODE,
            app(DocumentAccuracySource::class)->fingerprint($item), (int) Auth::id(), $what);
        if ($before && $after && $before->status !== $after->status) {
            $this->history($id, $item->status, 'KPI HC07: xác nhận cũ hết hiệu lực', $what.' sau khi đã gửi/kiểm tra — cần gửi lại.');
        }
    }

    /** Hồ sơ đã gửi kiểm KPI HC07 ở kỳ đã chốt lương → khóa mọi thay đổi ảnh hưởng kết quả (null = được sửa). */
    private function kpiLockError(int $id): ?string
    {
        $review = $this->kpiReview($id);
        if (! $review || ! $review->first_submitted_at) {
            return null;
        }
        $month = \App\Services\Hr\Kpi\HcnsKpiRules::lockedMonth([\Illuminate\Support\Carbon::parse($review->first_submitted_at)->format('Y-m')]);

        return $month ? 'Kỳ KPI tháng '.substr($month, 5, 2).'/'.substr($month, 0, 4).' đã chốt lương — nguồn HC07 của hồ sơ này bị khóa. Mở lại kỳ lương theo quy trình nếu cần sửa.' : null;
    }

    private function fileDisk(object $file): string
    {
        return (string) ($file->disk ?? '') !== '' ? (string) $file->disk : 'public';
    }

    /**
     * Tạo hồ sơ bàn giao mới với mã tự sinh, lưu file đính kèm và ghi lịch sử.
     */
    public function store(Request $request)
    {
        $this->ensureTables();

        $data = $this->validateMain($request);

        $priority = $data['priority'] ?? 'normal';
        if (! array_key_exists($priority, $this->priorities)) {
            $priority = 'normal';
        }

        $assignedTo = $data['assigned_to'] ?? null;
        $assigneeName = $assignedTo ? $this->userName((int) $assignedTo) : null;

        $id = DB::table('hr_document_handovers')->insertGetId($this->kpiFields($request, null) + [
            'code' => $this->makeCode(),
            'title' => $data['title'],
            'document_type' => $data['document_type'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'department_name' => $data['department_name'] ?? null,
            'priority' => $priority,
            'status' => 'created',
            'created_by' => Auth::id(),
            'assigned_to' => $assignedTo,
            'current_holder' => $data['current_holder'] ?? $assigneeName,
            'due_date' => $data['due_date'] ?? null,
            'description' => $data['description'] ?? null,
            'note' => $data['note'] ?? null,
            'storage_location' => $data['storage_location'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->storeFiles($request, $id);
        $this->history($id, 'created', 'Tạo hồ sơ', $data['note'] ?? null);

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã tạo hồ sơ mới.');
    }

    /**
     * Cập nhật thông tin hồ sơ bàn giao và ghi lịch sử thay đổi.
     *
     * @param  int|string  $id  ID hồ sơ
     */
    public function update(Request $request, $id)
    {
        $this->ensureTables();

        $row = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        abort_unless($row, 404);
        if ($locked = $this->kpiLockError((int) $id)) {
            return back()->with('error', $locked);
        }

        $data = $this->validateMain($request);

        $priority = $data['priority'] ?? 'normal';
        if (! array_key_exists($priority, $this->priorities)) {
            $priority = 'normal';
        }

        $kpi = $this->kpiFields($request, $row);

        DB::table('hr_document_handovers')
            ->where('id', (int) $id)
            ->update($kpi + [
                'title' => $data['title'],
                'document_type' => $data['document_type'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'department_name' => $data['department_name'] ?? null,
                'priority' => $priority,
                'assigned_to' => $data['assigned_to'] ?? null,
                'current_holder' => $data['current_holder'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'description' => $data['description'] ?? null,
                'note' => $data['note'] ?? null,
                'storage_location' => $data['storage_location'] ?? null,
                'updated_at' => now(),
            ]);

        $this->storeFiles($request, (int) $id);
        $this->history((int) $id, $row->status, 'Cập nhật thông tin hồ sơ', $data['note'] ?? null);
        $this->invalidateKpiReview((int) $id, 'Sửa thông tin hồ sơ');

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã cập nhật hồ sơ.');
    }

    /**
     * Upload thêm file đính kèm cho hồ sơ và ghi lịch sử.
     *
     * @param  int|string  $id  ID hồ sơ
     */
    public function uploadFiles(Request $request, $id)
    {
        $this->ensureTables();

        $item = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        abort_unless($item, 404);
        if ($locked = $this->kpiLockError((int) $id)) {
            return back()->with('error', $locked);
        }

        $request->validate([
            'attachments' => ['required', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ]);

        $this->storeFiles($request, (int) $id);
        $this->history((int) $id, $item->status, 'Thêm file hồ sơ', 'Upload file đính kèm.');
        $this->invalidateKpiReview((int) $id, 'Thêm file hồ sơ');

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã thêm file hồ sơ.');
    }

    /**
     * Chuyển trạng thái hồ sơ, cập nhật mốc thời gian / người giữ hồ sơ tương ứng và ghi lịch sử.
     *
     * @param  int|string  $id  ID hồ sơ
     */
    public function changeStatus(Request $request, $id)
    {
        $this->ensureTables();

        $row = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        abort_unless($row, 404);

        $data = $request->validate([
            'status' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer'],
            'current_holder' => ['nullable', 'string', 'max:190'],
            'storage_location' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $data['status'];
        abort_unless(array_key_exists($status, $this->statuses), 422);

        $update = [
            'status' => $status,
            'updated_at' => now(),
        ];

        $timeFields = [
            'assigned' => 'assigned_at',
            'received' => 'received_at',
            'sent' => 'sent_at',
            'returned' => 'returned_at',
            'completed' => 'completed_at',
            'archived' => 'archived_at',
        ];

        if (isset($timeFields[$status])) {
            $update[$timeFields[$status]] = now();
        }

        if ($request->filled('assigned_to')) {
            $update['assigned_to'] = (int) $data['assigned_to'];
            $update['current_holder'] = $this->userName((int) $data['assigned_to']);
        }

        if ($request->filled('current_holder')) {
            $update['current_holder'] = $data['current_holder'];
        }

        if ($request->filled('storage_location')) {
            $update['storage_location'] = $data['storage_location'];
        }

        if ($request->filled('note')) {
            $update['note'] = $data['note'];
        }

        DB::table('hr_document_handovers')
            ->where('id', (int) $id)
            ->update($update);

        $this->history((int) $id, $status, $this->statuses[$status] ?? 'Cập nhật trạng thái', $data['note'] ?? null);

        return redirect()
            ->route('hr.document-handovers.show', $id)
            ->with('success', 'Đã chuyển trạng thái: '.($this->statuses[$status] ?? $status));
    }

    /**
     * Xoá hồ sơ bàn giao cùng file vật lý, bản ghi file và lịch sử (trong transaction).
     *
     * @param  int|string  $id  ID hồ sơ
     */
    public function destroy($id)
    {
        $this->ensureTables();

        $review = $this->kpiReview((int) $id);
        if ($review && $review->first_submitted_at) {
            // Hồ sơ đã gửi kiểm KPI HC07: xóa sẽ mất nguồn và lịch sử xác nhận.
            return back()->with('error', 'Hồ sơ đã gửi kiểm tra KPI HC07 nên không xóa được.');
        }

        $files = DB::table('hr_document_handover_files')->where('handover_id', (int) $id)->get();

        DB::transaction(function () use ($id, $files) {
            foreach ($files as $file) {
                if (! empty($file->path)) {
                    Storage::disk($this->fileDisk($file))->delete($file->path);
                }
            }
            if (HcnsSourceReviews::ready()) {
                $reviewIds = DB::table('hr_kpi_source_reviews')->where('source_type', DocumentAccuracySource::SOURCE_TYPE)->where('source_id', (int) $id)->pluck('id');
                DB::table('hr_kpi_source_review_events')->whereIn('review_id', $reviewIds)->delete();
                DB::table('hr_kpi_source_reviews')->whereIn('id', $reviewIds)->delete();
            }

            DB::table('hr_document_handover_files')->where('handover_id', (int) $id)->delete();
            DB::table('hr_document_handover_histories')->where('handover_id', (int) $id)->delete();
            DB::table('hr_document_handovers')->where('id', (int) $id)->delete();
        });

        return redirect()
            ->route('hr.document-handovers.index')
            ->with('success', 'Đã xóa hồ sơ.');
    }

    /**
     * Xoá một file đính kèm của hồ sơ và ghi lịch sử.
     *
     * @param  int|string  $id  ID hồ sơ
     * @param  int|string  $fileId  ID file
     */
    public function deleteFile($id, $fileId)
    {
        $this->ensureTables();

        $file = DB::table('hr_document_handover_files')
            ->where('id', (int) $fileId)
            ->where('handover_id', (int) $id)
            ->first();

        abort_unless($file, 404);
        if ($locked = $this->kpiLockError((int) $id)) {
            return back()->with('error', $locked);
        }

        if (! empty($file->path)) {
            Storage::disk($this->fileDisk($file))->delete($file->path);
        }

        DB::table('hr_document_handover_files')->where('id', (int) $fileId)->delete();

        $item = DB::table('hr_document_handovers')->where('id', (int) $id)->first();
        if ($item) {
            $this->history((int) $id, $item->status, 'Xóa file hồ sơ', $file->original_name ?? null);
            $this->invalidateKpiReview((int) $id, 'Xóa file hồ sơ: '.($file->original_name ?? ''));
        }

        return back()->with('success', 'Đã xóa file hồ sơ.');
    }

    /**
     * Tải xuống file đính kèm của hồ sơ.
     *
     * @param  int|string  $id  ID hồ sơ
     * @param  int|string  $fileId  ID file
     */
    public function downloadFile($id, $fileId)
    {
        $this->ensureTables();

        $file = DB::table('hr_document_handover_files')
            ->where('id', (int) $fileId)
            ->where('handover_id', (int) $id)
            ->first();

        abort_unless($file, 404);
        $disk = Storage::disk($this->fileDisk($file));
        abort_unless($disk->exists($file->path), 404);

        return $disk->download($file->path, $file->original_name ?: basename($file->path));
    }

    /**
     * Xem trước file đính kèm (ảnh, PDF, text) dưới dạng trang HTML nhúng.
     *
     * @param  int|string  $id  ID hồ sơ
     * @param  int|string  $fileId  ID file
     */
    public function previewFile($id, $fileId)
    {
        $this->ensureTables();

        $file = DB::table('hr_document_handover_files')
            ->where('id', (int) $fileId)
            ->where('handover_id', (int) $id)
            ->first();

        abort_unless($file, 404);
        $disk = Storage::disk($this->fileDisk($file));
        abort_unless($disk->exists($file->path), 404);

        $real = $disk->path($file->path);
        $name = $file->original_name ?: basename($file->path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = $file->mime_type ?: (@mime_content_type($real) ?: 'application/octet-stream');

        $downloadUrl = route('hr.document-handovers.files.download', [$id, $fileId]);
        $header = '<div class="pv-top"><strong>'.e($name).'</strong><a href="'.e($downloadUrl).'">Tải xuống</a></div>';

        if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
            $data = base64_encode(file_get_contents($real));

            return response($this->previewPage($name, $header.'<div class="pv-body pv-img-wrap"><img src="data:'.e($mime).';base64,'.$data.'"></div>'));
        }

        if ($ext === 'pdf' || $mime === 'application/pdf') {
            $data = base64_encode(file_get_contents($real));

            return response($this->previewPage($name, $header.'<iframe src="data:application/pdf;base64,'.$data.'#toolbar=1"></iframe>'));
        }

        if (in_array($ext, ['txt', 'log', 'csv'], true)) {
            $text = htmlspecialchars((string) file_get_contents($real), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return response($this->previewPage($name, $header.'<div class="pv-body"><pre>'.$text.'</pre></div>'));
        }

        return response($this->previewPage($name, $header.'<div class="pv-body"><div class="pv-notice">Định dạng này chưa hỗ trợ xem trước. Anh bấm Tải xuống để mở file.</div></div>'));
    }

    /**
     * Validate dữ liệu form hồ sơ bàn giao và trả về mảng dữ liệu hợp lệ.
     */
    protected function validateMain(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', 'string', 'max:120'],
            'customer_name' => ['nullable', 'string', 'max:190'],
            'department_name' => ['nullable', 'string', 'max:190'],
            'priority' => ['nullable', 'string', 'max:50'],
            'assigned_to' => ['nullable', 'integer'],
            'current_holder' => ['nullable', 'string', 'max:190'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'note' => ['nullable', 'string'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:51200'],
        ]);
    }

    /**
     * Lưu các file đính kèm hợp lệ của hồ sơ vào storage và ghi bản ghi DB.
     */
    protected function storeFiles(Request $request, int $handoverId): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ((array) $request->file('attachments') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            // Tệp mới lưu riêng tư (chỉ mở qua route xem/tải có kiểm quyền); DB chưa có cột disk thì giữ cách cũ.
            $private = Schema::hasColumn('hr_document_handover_files', 'disk');
            $path = $file->store('hr-document-handovers/'.$handoverId, $private ? 'local' : 'public');

            DB::table('hr_document_handover_files')->insert([
                'handover_id' => $handoverId,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ] + ($private ? ['disk' => 'local'] : []));
        }
    }

    /**
     * Tạo các bảng hồ sơ bàn giao (handovers, histories, files) nếu chưa tồn tại.
     */
    protected function ensureTables(): void
    {
        if (! Schema::hasTable('hr_document_handovers')) {
            Schema::create('hr_document_handovers', function ($table) {
                $table->id();
                $table->string('code', 80)->nullable()->index();
                $table->string('title', 255);
                $table->string('document_type', 120)->nullable();
                $table->string('customer_name', 190)->nullable();
                $table->string('department_name', 190)->nullable();
                $table->string('priority', 50)->default('normal')->index();
                $table->string('status', 50)->default('created')->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('assigned_to')->nullable()->index();
                $table->string('current_holder', 190)->nullable();
                $table->date('due_date')->nullable();
                $table->text('description')->nullable();
                $table->text('note')->nullable();
                $table->string('storage_location', 255)->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('returned_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_document_handover_histories')) {
            Schema::create('hr_document_handover_histories', function ($table) {
                $table->id();
                $table->unsignedBigInteger('handover_id')->index();
                $table->string('status', 50)->nullable()->index();
                $table->string('action', 120)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hr_document_handover_files')) {
            Schema::create('hr_document_handover_files', function ($table) {
                $table->id();
                $table->unsignedBigInteger('handover_id')->index();
                $table->string('path', 500);
                $table->string('original_name', 255)->nullable();
                $table->string('mime_type', 190)->nullable();
                $table->unsignedBigInteger('size')->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    /**
     * Sinh mã hồ sơ dạng HS-{năm}-{số thứ tự 5 chữ số}.
     */
    protected function makeCode(): string
    {
        $prefix = 'HS-'.now()->format('Y').'-';
        $count = DB::table('hr_document_handovers')->where('code', 'like', $prefix.'%')->count() + 1;

        return $prefix.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Ghi một dòng lịch sử xử lý cho hồ sơ bàn giao.
     */
    protected function history(int $id, string $status, string $action, ?string $note = null): void
    {
        DB::table('hr_document_handover_histories')->insert([
            'handover_id' => $id,
            'status' => $status,
            'action' => $action,
            'note' => $note,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Lấy danh sách user (id, name, email) để chọn người nhận hồ sơ.
     */
    protected function users()
    {
        return Schema::hasTable('users')
            ? DB::table('users')->select('id', 'name', 'email')->orderBy('name')->get()
            : collect();
    }

    /**
     * Lấy tên user theo ID, trả về null nếu không có bảng users.
     */
    protected function userName(int $id): ?string
    {
        return Schema::hasTable('users')
            ? DB::table('users')->where('id', $id)->value('name')
            : null;
    }

    /**
     * Dựng trang HTML hoàn chỉnh (kèm CSS) cho chức năng xem trước file.
     */
    protected function previewPage(string $title, string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><title>'.e($title).'</title>
        <style>
            *{box-sizing:border-box}
            html,body{margin:0;min-height:100%;font-family:Arial,"DejaVu Sans",sans-serif;background:#f8fafc;color:#0f172a}
            iframe{width:100%;height:calc(100vh - 54px);border:0;background:#fff}
            .pv-top{height:54px;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 14px;background:linear-gradient(90deg,#0f3b78,#0891b2);color:#fff}
            .pv-top strong{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
            .pv-top a{border:1px solid rgba(255,255,255,.35);border-radius:10px;padding:8px 12px;color:#fff;text-decoration:none;font-weight:900;font-size:13px;background:rgba(255,255,255,.13)}
            .pv-body{padding:16px;overflow:auto}
            .pv-img-wrap{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 54px)}
            .pv-img-wrap img{max-width:min(100%,920px);max-height:calc(100vh - 90px);object-fit:contain;border-radius:14px;box-shadow:0 18px 45px rgba(15,23,42,.16);background:#fff}
            pre{white-space:pre-wrap;background:#fff;border:1px solid #dbe3ef;border-radius:14px;padding:16px;line-height:1.6}
            .pv-notice{max-width:680px;margin:70px auto;padding:24px;border-radius:18px;border:1px solid #dbe3ef;background:#fff;text-align:center;color:#475569;font-weight:800}
        </style></head><body>'.$body.'</body></html>';
    }
}
