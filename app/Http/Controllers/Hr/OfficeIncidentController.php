<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\OfficeIncidents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sự cố văn phòng: mọi nhân viên báo; HCNS phân loại, xử lý; Giám đốc duyệt chi trên ngưỡng;
 * người báo nghiệm thu, chấm sao. Xem quy trình ở App\Services\Hr\OfficeIncidents.
 */
final class OfficeIncidentController extends Controller
{
    private const FILE_RULES = ['nullable', 'array', 'max:6'];

    private const FILE_ITEM_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,webp,heic,mp4,mov,pdf'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $seeAll = OfficeIncidents::canHandle($user) || OfficeIncidents::canApprove($user);
        $status = (string) $request->query('status', '');
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) ? (string) $request->query('month') : now()->format('Y-m');

        $incidents = DB::table('hr_office_incidents as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.reporter_id')
            ->when(! $seeAll, fn ($q) => $q->where('i.reporter_id', $user->id))
            ->when($status !== '' && array_key_exists($status, OfficeIncidents::STATUSES), fn ($q) => $q->where('i.status', $status))
            ->when($status === '', fn ($q) => $q->where(fn ($w) => $w->whereNotIn('i.status', ['closed', 'cancelled'])
                ->orWhere('i.created_at', '>=', Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfDay())))
            ->orderByRaw("FIELD(i.status, 'waiting_approval', 'new', 'in_progress', 'waiting_acceptance', 'closed', 'cancelled')")
            ->orderBy('i.due_at')
            ->orderByDesc('i.id')
            ->limit(300)
            ->get(['i.*', 'u.name as reporter_name']);

        return view('hr.incidents.index', [
            'incidents' => $incidents,
            'status' => $status,
            'month' => $month,
            'seeAll' => $seeAll,
            'canHandle' => OfficeIncidents::canHandle($user),
            'canApprove' => OfficeIncidents::canApprove($user),
            'stats' => $seeAll ? OfficeIncidents::slaStats($month) : null,
            'settings' => OfficeIncidents::settings(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'location' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:3000'],
            'files' => self::FILE_RULES,
            'files.*' => self::FILE_ITEM_RULES,
        ], [
            'location.required' => 'Nhập vị trí sự cố (phòng nào, góc nào).',
            'description.required' => 'Mô tả hiện tượng sự cố.',
            'files.*.max' => 'Mỗi file tối đa 20MB.',
            'files.*.mimes' => 'Chỉ nhận ảnh (jpg, png, webp, heic), video (mp4, mov) hoặc pdf.',
        ]);

        $id = DB::transaction(function () use ($validated, $request) {
            $id = DB::table('hr_office_incidents')->insertGetId((OfficeIncidents::kpiColumnsReady() ? ['company_id' => \App\Support\EgoCompanyLock::id()] : []) + [
                'code' => OfficeIncidents::nextCode(),
                'reporter_id' => $request->user()->id,
                'location' => $validated['location'],
                'description' => $validated['description'],
                'status' => 'new',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            OfficeIncidents::log($id, (int) $request->user()->id, 'reported');

            return $id;
        });
        $this->storeFiles($request, $id, 'report');

        return redirect()->route('hr.incidents.show', $id)->with('success', 'Đã gửi báo sự cố. Hành chính Nhân sự sẽ phân loại và xử lý.');
    }

    public function show(Request $request, int $incident): View
    {
        $row = $this->find($incident);
        abort_unless(OfficeIncidents::canView($request->user(), $row), 403, 'Bạn không có quyền xem sự cố này.');

        $names = DB::table('users')->whereIn('id', array_filter([$row->reporter_id, $row->classified_by, $row->cost_approved_by, $row->accepted_by,
            $row->handler_id ?? null, $row->resolved_by ?? null]))->pluck('name', 'id');

        // KPI HC06 nhóm sự cố: số đo theo quy tắc của kỳ hạn xử lý (chỉ khi đã phân loại có hạn).
        $kpiRow = null;
        $kpiRules = null;
        if (OfficeIncidents::kpiColumnsReady() && $row->due_at && ! empty($row->handler_id)) {
            $kpiRules = app(\App\Services\Hr\Kpi\HcnsKpiRules::class)->forMonth(Carbon::parse($row->due_at)->format('Y-m'));
            $kpiRow = app(\App\Services\Hr\Kpi\Sources\AssetsServiceSource::class)->incidentRow($row, (array) ($kpiRules['rules']['assets'] ?? []),
                DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all(), now());
        }

        return view('hr.incidents.show', [
            'incident' => $row,
            'names' => $names,
            'kpiReady' => OfficeIncidents::kpiColumnsReady(),
            'kpiRow' => $kpiRow,
            'kpiRules' => $kpiRules,
            'kpiLocked' => $this->kpiLockedMonth($row),
            'handlerOptions' => OfficeIncidents::kpiColumnsReady() && OfficeIncidents::canHandle($request->user()) ? OfficeIncidents::handlerOptions() : collect(),
            'files' => DB::table('hr_office_incident_files')->where('incident_id', $row->id)->orderBy('id')->get(),
            'logs' => DB::table('hr_office_incident_logs as l')->leftJoin('users as u', 'u.id', '=', 'l.user_id')
                ->where('l.incident_id', $row->id)->orderBy('l.id')->get(['l.*', 'u.name as user_name']),
            'canHandle' => OfficeIncidents::canHandle($request->user()),
            'canApprove' => OfficeIncidents::canApprove($request->user()),
            'isReporter' => (int) $row->reporter_id === (int) $request->user()->id,
            'settings' => OfficeIncidents::settings(),
        ]);
    }

    /** Bước 2: HCNS phân loại mức độ, cách xử lý, chi phí dự kiến. Chi trên ngưỡng => chờ Giám đốc duyệt. */
    public function classify(Request $request, int $incident): RedirectResponse
    {
        abort_unless(OfficeIncidents::canHandle($request->user()), 403, 'Chỉ Hành chính Nhân sự phân loại sự cố.');
        $row = $this->find($incident);
        abort_unless(in_array($row->status, ['new', 'in_progress'], true), 422, 'Sự cố ở trạng thái này không phân loại lại được.');

        $validated = $request->validate([
            'level' => ['required', Rule::in(array_keys(OfficeIncidents::LEVELS))],
            'handler_type' => ['required', Rule::in(['internal', 'vendor'])],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'handler_id' => ['nullable', 'integer', Rule::in(OfficeIncidents::kpiColumnsReady() ? OfficeIncidents::handlerOptions()->pluck('id')->all() : [])],
        ], ['handler_id.in' => 'Người xử lý phải là tài khoản Hành chính Nhân sự/Admin.']);

        $settings = OfficeIncidents::settings();
        $cost = (float) ($validated['estimated_cost'] ?? 0);
        $needsApproval = OfficeIncidents::needsApproval($cost, $settings) && $row->cost_approval !== 'approved';
        $newDue = OfficeIncidents::dueAt(Carbon::parse($row->created_at), (int) $validated['level'], $settings);

        // KPI HC06: phân loại quyết định hạn và người chịu KPI → khóa khi kỳ của hạn (cũ hoặc mới) đã chốt lương.
        $kpi = [];
        if (OfficeIncidents::kpiColumnsReady()) {
            $months = array_filter([$row->due_at ? Carbon::parse($row->due_at)->format('Y-m') : null, $newDue->format('Y-m')]);
            if ($locked = \App\Services\Hr\Kpi\HcnsKpiRules::lockedMonth(array_values($months))) {
                return back()->with('error', 'Kỳ KPI tháng '.substr($locked, 5, 2).'/'.substr($locked, 0, 4).' đã chốt lương — không phân loại lại (đổi hạn/người xử lý) được. Vẫn báo xử lý xong và nghiệm thu bình thường.');
            }
            $kpi = [
                'handler_id' => (int) ($validated['handler_id'] ?? 0) ?: ($row->handler_id ?: $request->user()->id),
                'waiting_since' => $needsApproval ? ($row->waiting_since ?? now()) : null,
            ];
        }

        DB::table('hr_office_incidents')->where('id', $row->id)->update($kpi + [
            'level' => (int) $validated['level'],
            'classified_by' => $row->classified_by ?? $request->user()->id,
            'classified_at' => $row->classified_at ?? now(),
            'due_at' => $newDue,
            'handler_type' => $validated['handler_type'],
            'vendor_name' => $validated['vendor_name'] ?? null,
            'estimated_cost' => $cost,
            'cost_approval' => $needsApproval ? 'pending' : ($row->cost_approval === 'approved' ? 'approved' : null),
            'status' => $needsApproval ? 'waiting_approval' : 'in_progress',
            'updated_at' => now(),
        ]);
        OfficeIncidents::log($row->id, (int) $request->user()->id, 'classified',
            OfficeIncidents::LEVELS[(int) $validated['level']].' · '.($validated['handler_type'] === 'vendor' ? 'Thuê ngoài' : 'Tự xử lý')
            .($cost > 0 ? ' · Dự kiến '.number_format($cost, 0, ',', '.').'đ' : '').(($validated['note'] ?? null) ? ' · '.$validated['note'] : '')
            .(isset($kpi['handler_id']) ? ' · Người xử lý: '.(DB::table('users')->where('id', $kpi['handler_id'])->value('name') ?? '#'.$kpi['handler_id']) : ''));

        return back()->with('success', $needsApproval
            ? 'Đã phân loại. Chi phí trên '.number_format((float) $settings['incident_cost_approval_threshold'], 0, ',', '.').'đ — đã gửi Giám đốc duyệt chi.'
            : 'Đã phân loại, bắt đầu xử lý.');
    }

    /** Giám đốc duyệt / từ chối chi phí. Từ chối => HCNS phân loại lại (phương án rẻ hơn). */
    public function approveCost(Request $request, int $incident): RedirectResponse
    {
        abort_unless(OfficeIncidents::canApprove($request->user()), 403, 'Chỉ Ban Giám đốc duyệt chi.');
        $row = $this->find($incident);
        abort_unless($row->status === 'waiting_approval', 422, 'Sự cố không ở trạng thái chờ duyệt chi.');

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
        ], ['note.required_if' => 'Từ chối thì ghi lý do.']);

        $approved = $validated['decision'] === 'approve';
        // KPI HC06: cộng dồn thời gian chờ Giám đốc duyệt chi (dùng khi quy tắc chọn trừ thời gian chờ).
        $wait = OfficeIncidents::kpiColumnsReady() && $row->waiting_since ? [
            'approval_wait_minutes' => (int) $row->approval_wait_minutes + (int) Carbon::parse($row->waiting_since)->diffInMinutes(now()),
            'waiting_since' => null,
        ] : [];
        DB::table('hr_office_incidents')->where('id', $row->id)->update($wait + [
            'cost_approval' => $approved ? 'approved' : 'rejected',
            'cost_approved_by' => $request->user()->id,
            'cost_approved_at' => now(),
            'cost_approval_note' => $validated['note'] ?? null,
            'status' => $approved ? 'in_progress' : 'new',
            'updated_at' => now(),
        ]);
        OfficeIncidents::log($row->id, (int) $request->user()->id, $approved ? 'cost_approved' : 'cost_rejected', $validated['note'] ?? null);

        return back()->with('success', $approved ? 'Đã duyệt chi.' : 'Đã từ chối chi phí — HCNS sẽ đề xuất lại.');
    }

    /** Bước 3 xong: HCNS báo đã xử lý kèm ảnh kết quả => chờ người báo nghiệm thu. */
    public function resolve(Request $request, int $incident): RedirectResponse
    {
        abort_unless(OfficeIncidents::canHandle($request->user()), 403, 'Chỉ Hành chính Nhân sự cập nhật xử lý.');
        $row = $this->find($incident);
        abort_unless($row->status === 'in_progress', 422, 'Sự cố chưa ở bước xử lý.');

        $validated = $request->validate([
            'actual_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'result_note' => ['required', 'string', 'max:3000'],
            'files' => self::FILE_RULES,
            'files.*' => self::FILE_ITEM_RULES,
        ], ['result_note.required' => 'Ghi kết quả xử lý.']);

        $cost = (float) ($validated['actual_cost'] ?? 0);
        if (OfficeIncidents::needsApproval($cost) && $row->cost_approval !== 'approved') {
            return back()->withInput()->with('error', 'Chi phí thực tế vượt ngưỡng nhưng chưa được Giám đốc duyệt. Phân loại lại với chi phí dự kiến để gửi duyệt.');
        }

        DB::table('hr_office_incidents')->where('id', $row->id)->update((OfficeIncidents::kpiColumnsReady() ? [
            'resolved_by' => $request->user()->id,
            'first_resolved_at' => $row->first_resolved_at ?? now(),
        ] : []) + [
            'actual_cost' => $cost,
            'result_note' => $validated['result_note'],
            'resolved_at' => now(),
            'status' => 'waiting_acceptance',
            'updated_at' => now(),
        ]);
        $this->storeFiles($request, $row->id, 'result');
        OfficeIncidents::log($row->id, (int) $request->user()->id, 'resolved', $validated['result_note']);

        return back()->with('success', 'Đã báo xử lý xong. Chờ người báo sự cố nghiệm thu.');
    }

    /** Bước 4: người báo (hoặc Ban Giám đốc) nghiệm thu, chấm sao => đóng; chưa đạt => quay lại xử lý. */
    public function accept(Request $request, int $incident): RedirectResponse
    {
        $row = $this->find($incident);
        $isReporter = (int) $row->reporter_id === (int) $request->user()->id;
        abort_unless($isReporter || OfficeIncidents::canApprove($request->user()), 403, 'Chỉ người báo sự cố nghiệm thu.');
        abort_unless($row->status === 'waiting_acceptance', 422, 'Sự cố chưa ở bước nghiệm thu.');

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accept', 'reject'])],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5', 'required_if:decision,accept'],
            'feedback' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject'],
        ], [
            'rating.required_if' => 'Chọn số sao đánh giá.',
            'feedback.required_if' => 'Ghi rõ chỗ chưa đạt để HCNS xử lý lại.',
        ]);

        if ($validated['decision'] === 'accept') {
            DB::table('hr_office_incidents')->where('id', $row->id)->update([
                'status' => 'closed',
                'accepted_at' => now(),
                'accepted_by' => $request->user()->id,
                'rating' => (int) $validated['rating'],
                'feedback' => $validated['feedback'] ?? null,
                'closed_at' => now(),
                'on_time' => $row->due_at && $row->resolved_at ? Carbon::parse($row->resolved_at)->lte(Carbon::parse($row->due_at)) : null,
                'updated_at' => now(),
            ]);
            OfficeIncidents::log($row->id, (int) $request->user()->id, 'accepted', str_repeat('★', (int) $validated['rating']).(($validated['feedback'] ?? null) ? ' · '.$validated['feedback'] : ''));

            return back()->with('success', 'Đã nghiệm thu và đóng sự cố. Cảm ơn bạn đã đánh giá.');
        }

        DB::table('hr_office_incidents')->where('id', $row->id)->update([
            'status' => 'in_progress',
            'reopen_count' => (int) $row->reopen_count + 1,
            'updated_at' => now(),
        ]);
        OfficeIncidents::log($row->id, (int) $request->user()->id, 'reopened', $validated['feedback']);

        return back()->with('success', 'Đã gửi lại cho HCNS xử lý tiếp.');
    }

    public function cancel(Request $request, int $incident): RedirectResponse
    {
        $row = $this->find($incident);
        $isReporter = (int) $row->reporter_id === (int) $request->user()->id;
        abort_unless(($isReporter && $row->status === 'new') || OfficeIncidents::canHandle($request->user()), 403);
        abort_if(in_array($row->status, ['closed', 'cancelled'], true), 422, 'Sự cố đã đóng.');

        $note = (string) ($request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? '');
        if ($locked = $this->kpiLockedMonth($row)) {
            return back()->with('error', 'Kỳ KPI tháng '.substr($locked, 5, 2).'/'.substr($locked, 0, 4).' đã chốt lương — không hủy sự cố của kỳ này.');
        }
        DB::table('hr_office_incidents')->where('id', $row->id)->update((OfficeIncidents::kpiColumnsReady() ? ['cancelled_at' => now()] : [])
            + ['status' => 'cancelled', 'updated_at' => now()]);
        OfficeIncidents::log($row->id, (int) $request->user()->id, 'cancelled', $note ?: null);

        return redirect()->route('hr.incidents.index')->with('success', 'Đã huỷ sự cố '.$row->code.'.');
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        abort_unless(OfficeIncidents::canApprove($request->user()), 403, 'Chỉ Ban Giám đốc sửa cài đặt sự cố.');
        $rules = [];
        foreach (array_keys(OfficeIncidents::DEFAULTS) as $key) {
            $rules[$key] = ['required', 'numeric', 'min:0', 'max:10000000000'];
        }
        OfficeIncidents::saveSettings($request->validate($rules), (int) $request->user()->id);

        return back()->with('success', 'Đã lưu cài đặt sự cố văn phòng (áp dụng cho sự cố phân loại từ bây giờ).');
    }

    public function download(Request $request, int $incident, int $file): StreamedResponse
    {
        $row = $this->find($incident);
        abort_unless(OfficeIncidents::canView($request->user(), $row), 403);
        $f = DB::table('hr_office_incident_files')->where('id', $file)->where('incident_id', $row->id)->first();
        abort_unless($f && Storage::disk($f->disk)->exists($f->file_path), 404, 'File không còn tồn tại.');

        return Storage::disk($f->disk)->response($f->file_path, $f->original_name);
    }

    /** Tháng đã chốt lương theo hạn xử lý của sự cố (null = chưa khóa / chưa có hạn). */
    private function kpiLockedMonth(object $row): ?string
    {
        return OfficeIncidents::kpiColumnsReady() && $row->due_at
            ? \App\Services\Hr\Kpi\HcnsKpiRules::lockedMonth([Carbon::parse($row->due_at)->format('Y-m')])
            : null;
    }

    private function find(int $id): object
    {
        $row = DB::table('hr_office_incidents')->where('id', $id)->first();
        abort_unless($row, 404, 'Không tìm thấy sự cố.');

        return $row;
    }

    private function storeFiles(Request $request, int $incidentId, string $stage): void
    {
        foreach ((array) $request->file('files', []) as $file) {
            $name = Str::random(12).'.'.strtolower($file->getClientOriginalExtension() ?: 'bin');
            $path = $file->storeAs('private/office-incidents/'.$incidentId, $name, 'local');
            DB::table('hr_office_incident_files')->insert([
                'incident_id' => $incidentId, 'stage' => $stage, 'disk' => 'local', 'file_path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'mime_type' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(), 'uploaded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
