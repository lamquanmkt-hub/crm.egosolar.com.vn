<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Kpi\HcnsKpiRules;
use App\Services\Hr\Kpi\HcnsSourceReviews;
use App\Services\Hr\Kpi\RecruitmentKpi;
use App\Services\Hr\Kpi\Sources\ProbationQuitSource;
use App\Services\Hr\Kpi\Sources\RecruitmentCostSource;
use App\Services\Hr\Kpi\Sources\RecruitmentTimeSource;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Services\Hr\RecruitmentAttachmentService;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * KPI tuyển dụng HC01–HC03 ngay trong module Tuyển dụng (không thêm menu):
 *   Yêu cầu tuyển → trang “KPI tuyển dụng” (người phụ trách, mốc tiếp nhận, lượt tuyển, chi phí phân bổ)
 *   → Lượt tuyển (nhận việc thực tế HC01, kết quả thử việc HC03) → Khoản chi (HC02).
 * Người phụ trách lưu nháp/gửi kiểm; người kiểm (khác người phụ trách, khác chính nhân viên) xác nhận hoặc trả bổ sung.
 */
class RecruitmentKpiController extends Controller
{
    private const CRITERIA = [RecruitmentTimeSource::CODE => 'HC01', ProbationQuitSource::CODE => 'HC03'];

    // ================================================================ Yêu cầu tuyển

    public function request($id)
    {
        abort_unless(RecruitmentKpi::ready(), 404);
        $req = $this->findRequest((int) $id);
        $user = Auth::user();
        $kpi = app(RecruitmentKpi::class);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $hires = app(RecruitmentTimeSource::class)->query()->where('h.request_id', $req->id)->orderBy('h.id')->get();
        $hireRows = $hires->map(fn ($h) => app(RecruitmentTimeSource::class)->row($h, $names, now()));
        $costs = DB::table('hr_recruitment_costs as k')
            ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'k.id')->where('v.source_type', RecruitmentKpi::COST_TYPE)->where('v.criterion_code', RecruitmentCostSource::CODE))
            ->where('k.request_id', $req->id)->orderBy('k.id')->get(['k.*', 'v.status as review_status', 'v.result', 'v.confirmed_by', 'v.confirmed_at', 'v.return_reason']);
        $costRows = $costs->map(fn ($k) => app(RecruitmentCostSource::class)->costRow((object) ((array) $k + ['position' => $req->position]), $kpi->document((int) $k->payment_request_id), $names,
            Carbon::parse($kpi->document((int) $k->payment_request_id)['valid_at'] ?? $k->created_at)));
        $activeHires = $hires->whereIn('status', ['pending', 'started'])->count();
        $counted = $hireRows->where('kpi_state', 'counted');
        $costTotal = $costRows->where('kpi_state', 'counted')->sum('amount');

        return view('hr.recruitment.kpi.request', [
            'req' => $req,
            'names' => $names,
            'hireRows' => $hireRows,
            'costRows' => $costRows,
            'activeHires' => $activeHires,
            'summary' => [
                'avg_days' => $counted->isNotEmpty() ? round($counted->avg('days'), 2) : null,
                'counted' => $counted->count(),
                'cost_total' => $costTotal,
                'cost_per_hire' => $counted->count() > 0 ? round($costTotal / $counted->count(), 2) : null,
            ],
            'candidates' => DB::table('hr_recruitment_candidates')->where('recruitment_request_id', $req->id)->orderBy('full_name')->get(['id', 'full_name', 'status']),
            'users' => DB::table('users')->select('id', 'name')->orderBy('name')->get(),
            'documents' => RecruitmentKpi::costsReady() ? DB::table('payment_requests')
                ->where(fn ($q) => $q->where('company_id', EgoCompanyLock::id())->orWhereNull('company_id'))
                ->whereIn('doc_type', array_keys(RecruitmentKpi::DOC_TYPES))->whereNotIn('status', ['draft'])
                ->orderByDesc('id')->limit(300)->get(['id', 'code', 'doc_type', 'amount', 'status', 'payment_content', 'reason']) : collect(),
            'logs' => $kpi->logs(RecruitmentKpi::REQUEST_TYPE, (int) $req->id),
            'canEdit' => $user && $this->canEditRequest($req, $user),
            'approvalStatuses' => ['pending' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Từ chối duyệt', 'closed' => 'Hoàn tất'],
        ]);
    }

    /** Bật theo dõi KPI, chọn HCNS phụ trách/người kiểm, ghi mốc tiếp nhận yêu cầu thật. */
    public function requestUpdate(Request $request, $id)
    {
        $req = $this->findRequest((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRequest($req, $user), 403, 'Chỉ HCNS phụ trách hoặc Ban Giám đốc/Admin được sửa phần KPI của yêu cầu.');
        $data = $request->validate([
            'kpi_tracked' => ['nullable', 'boolean'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', 'different:owner_id'],
            'request_received_at' => ['required', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'owner_id.required' => 'Chọn HCNS phụ trách (chịu KPI).',
            'reviewer_id.required' => 'Chọn người kiểm độc lập.',
            'reviewer_id.different' => 'Người kiểm phải khác HCNS phụ trách.',
            'request_received_at.required' => 'Nhập mốc HCNS tiếp nhận yêu cầu (giờ thật).',
            'request_received_at.before_or_equal' => 'Mốc tiếp nhận không được sau hiện tại.',
        ]);
        $received = Carbon::parse($data['request_received_at'])->format('Y-m-d H:i:s');
        if (! empty($req->kpi_tracked) && ! $request->boolean('kpi_tracked') && DB::table('hr_recruitment_hires')->where('request_id', $req->id)->exists()) {
            throw ValidationException::withMessages(['kpi_tracked' => 'Yêu cầu đã có lượt tuyển theo dõi KPI — không bỏ theo dõi được.']);
        }
        if ((string) $req->request_received_at !== $received) {
            $this->assertRequestHiresOpen($req, 'mốc tiếp nhận yêu cầu');
            if ($req->request_received_at === null) {
                $earliest = DB::table('hr_recruitment_hires')->where('request_id', $req->id)->whereNotNull('actual_start_date')->min('actual_start_date');
                if ($earliest && Carbon::parse($earliest)->lt(Carbon::parse($received)->startOfDay())) {
                    throw ValidationException::withMessages(['request_received_at' => 'Mốc tiếp nhận sau ngày nhận việc của lượt tuyển đã có.']);
                }
            }
        }

        $values = ['company_id' => $req->company_id ?? EgoCompanyLock::id(), 'kpi_tracked' => $request->boolean('kpi_tracked'),
            'owner_id' => (int) $data['owner_id'], 'reviewer_id' => (int) $data['reviewer_id'], 'request_received_at' => $received];
        $changes = $this->diff($req, $values, ['kpi_tracked' => 'theo dõi KPI', 'owner_id' => 'HCNS phụ trách', 'reviewer_id' => 'người kiểm', 'request_received_at' => 'mốc tiếp nhận']);
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }
        DB::table('hr_recruitment_requests')->where('id', $req->id)->update($values + ['updated_at' => now()]);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::REQUEST_TYPE, (int) $req->id, 'kpi_updated', implode('; ', $changes).(trim((string) ($data['note'] ?? '')) ? ' · '.$data['note'] : ''), (int) $user->id);
        // Mốc tiếp nhận nằm trong nội dung kiểm HC01 của từng lượt tuyển → bản đã gửi/xác nhận hết hiệu lực.
        foreach (DB::table('hr_recruitment_hires')->where('request_id', $req->id)->pluck('id') as $hireId) {
            $this->invalidateHire((int) $hireId, 'Sửa phần KPI của yêu cầu tuyển');
        }

        return back()->with('success', 'Đã lưu phần KPI của yêu cầu tuyển.');
    }

    /** Hủy đợt tuyển có lý do: lượt chưa nhận việc chuyển “đã hủy”; lượt đã nhận việc giữ nguyên. */
    public function requestCancel(Request $request, $id)
    {
        $req = $this->findRequest((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRequest($req, $user), 403);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:2000']], ['cancel_reason.required' => 'Ghi lý do hủy đợt tuyển.']);
        abort_if(! empty($req->cancelled_at), 422, 'Đợt tuyển đã hủy.');
        $pending = DB::table('hr_recruitment_hires')->where('request_id', $req->id)->where('status', 'pending')->get();
        foreach ($pending as $h) {
            if ($locked = RecruitmentKpi::hireLockedMonth($h, [$h->expected_start_date])) {
                throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'lượt tuyển')]);
            }
        }
        DB::transaction(function () use ($req, $user, $data, $pending) {
            DB::table('hr_recruitment_requests')->where('id', $req->id)->update(['cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancel_reason' => trim($data['cancel_reason']), 'updated_at' => now()]);
            foreach ($pending as $h) {
                DB::table('hr_recruitment_hires')->where('id', $h->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancel_reason' => 'Hủy đợt tuyển: '.trim($data['cancel_reason']), 'updated_at' => now()]);
                app(RecruitmentKpi::class)->log(RecruitmentKpi::HIRE_TYPE, (int) $h->id, 'cancelled', 'Hủy theo đợt tuyển: '.trim($data['cancel_reason']), (int) $user->id);
            }
            app(RecruitmentKpi::class)->log(RecruitmentKpi::REQUEST_TYPE, (int) $req->id, 'cancelled', trim($data['cancel_reason']).' · hủy '.$pending->count().' lượt chưa nhận việc', (int) $user->id);
        });

        return back()->with('success', 'Đã hủy đợt tuyển. Lượt đã nhận việc giữ nguyên.');
    }

    // ================================================================ Lượt tuyển

    /** Thêm lượt tuyển: một suất của yêu cầu đã duyệt → ứng viên thuộc đúng yêu cầu. */
    public function hireStore(Request $request, $id)
    {
        $req = $this->findRequest((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRequest($req, $user), 403, 'Chỉ HCNS phụ trách hoặc Ban Giám đốc/Admin được thêm lượt tuyển.');
        $data = $request->validate(['candidate_id' => ['required', 'integer']], ['candidate_id.required' => 'Chọn ứng viên.']);
        $problems = array_filter([
            empty($req->kpi_tracked) ? 'yêu cầu chưa bật theo dõi KPI' : null,
            ! in_array((string) $req->approval_status, ['approved', 'closed'], true) ? 'yêu cầu chưa được duyệt' : null,
            empty($req->request_received_at) ? 'chưa có mốc tiếp nhận yêu cầu' : null,
            empty($req->owner_id) || empty($req->reviewer_id) ? 'chưa có HCNS phụ trách/người kiểm' : null,
            ! empty($req->cancelled_at) ? 'đợt tuyển đã hủy' : null,
        ]);
        if ($problems !== []) {
            throw ValidationException::withMessages(['candidate_id' => 'Chưa thêm được lượt tuyển: '.implode(', ', $problems).'.']);
        }
        $candidate = DB::table('hr_recruitment_candidates')->where('id', $data['candidate_id'])->first();
        if (! $candidate || (int) $candidate->recruitment_request_id !== (int) $req->id) {
            throw ValidationException::withMessages(['candidate_id' => 'Ứng viên phải được gắn với đúng yêu cầu tuyển này (bằng ID) ở bước Sàng lọc.']);
        }
        if (DB::table('hr_recruitment_hires')->where('candidate_id', $candidate->id)->whereIn('status', ['pending', 'started'])->exists()) {
            throw ValidationException::withMessages(['candidate_id' => 'Ứng viên đã có lượt tuyển đang mở — không thêm trùng.']);
        }
        $active = DB::table('hr_recruitment_hires')->where('request_id', $req->id)->whereIn('status', ['pending', 'started'])->count();
        if ($active >= (int) $req->quantity) {
            throw ValidationException::withMessages(['candidate_id' => 'Đã đủ '.$req->quantity.' suất của yêu cầu. Hủy/ghi “không nhận việc” lượt cũ hoặc tăng số lượng (có duyệt) trước.']);
        }
        $offer = DB::table('hr_recruitment_offers')->where('candidate_id', $candidate->id)->orderByRaw("FIELD(status, 'accepted', 'onboarded') DESC")->orderByDesc('id')->first();
        $hireId = DB::table('hr_recruitment_hires')->insertGetId([
            'company_id' => EgoCompanyLock::id(), 'request_id' => $req->id, 'candidate_id' => $candidate->id, 'offer_id' => $offer?->id,
            'owner_id' => $req->owner_id, 'reviewer_id' => $req->reviewer_id, 'status' => 'pending',
            'expected_start_date' => $offer?->start_date, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::HIRE_TYPE, $hireId, 'created', 'Lượt tuyển cho '.$candidate->full_name.($offer ? ' · thư mời #'.$offer->id.' (dự kiến '.($offer->start_date ?: '—').')' : ''), (int) $user->id);

        return redirect()->route('hr.recruitment.hires.show', $hireId)->with('success', 'Đã thêm lượt tuyển. Khi ứng viên đi làm, ghi ngày nhận việc thực tế, nối tài khoản nhân viên và tải chứng cứ rồi gửi kiểm.');
    }

    public function hire($id)
    {
        abort_unless(RecruitmentKpi::ready(), 404);
        $hire = $this->findHire((int) $id);
        $req = $this->findRequest((int) $hire->request_id);
        $user = Auth::user();
        $kpi = app(RecruitmentKpi::class);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $rules = app(HcnsKpiRules::class)->forMonth(($hire->actual_start_date ? Carbon::parse($hire->actual_start_date) : now())->format('Y-m'))['rules'];
        $timeSource = app(RecruitmentTimeSource::class)->query()->where('h.id', $hire->id)->first();
        $timeRow = $timeSource ? app(RecruitmentTimeSource::class)->row($timeSource, $names, now()) : null;
        $probRow = app(ProbationQuitSource::class)->row((object) ((array) $hire + ['position' => $req->position, 'candidate_name' => null] + $this->reviewFields($hire->id, ProbationQuitSource::CODE)),
            (array) ($rules[ProbationQuitSource::CODE] ?? []), $names, now());
        $profile = \App\Support\SchemaCache::hasTable('hr_employee_profiles') && $hire->employee_user_id
            ? DB::table('hr_employee_profiles')->where('employee_id', $hire->employee_user_id)->first() : null;

        return view('hr.recruitment.kpi.hire', [
            'hire' => $hire, 'req' => $req, 'names' => $names,
            'candidate' => $hire->candidate_id ? DB::table('hr_recruitment_candidates')->where('id', $hire->candidate_id)->first() : null,
            'offer' => $hire->offer_id ? DB::table('hr_recruitment_offers')->where('id', $hire->offer_id)->first() : null,
            'profile' => $profile,
            'timeRow' => $timeRow, 'probRow' => $probRow,
            'timeReview' => $this->review((int) $hire->id, RecruitmentTimeSource::CODE),
            'probReview' => $this->review((int) $hire->id, ProbationQuitSource::CODE),
            'files' => $kpi->files((int) $hire->id),
            'timeMissing' => $kpi->hireMissing($hire), 'probMissing' => $kpi->probationMissing($hire),
            'timeLocked' => RecruitmentKpi::hireLockedMonth($hire), 'probLocked' => RecruitmentKpi::probationLockedMonth($hire),
            'logs' => $kpi->logs(RecruitmentKpi::HIRE_TYPE, (int) $hire->id),
            'isOwner' => $user && (int) $hire->owner_id === (int) $user->id,
            'canEdit' => $user && $this->canEditRecord($hire, $user),
            'canReviewTime' => $user && $this->canReview($hire, $user, RecruitmentTimeSource::CODE),
            'canReviewProb' => $user && $this->canReview($hire, $user, ProbationQuitSource::CODE),
            'candidates' => DB::table('hr_recruitment_candidates')->where('recruitment_request_id', $req->id)->orderBy('full_name')->get(['id', 'full_name']),
            'users' => DB::table('users')->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /** HC01: ứng viên, tài khoản nhân viên, trạng thái, ngày nhận việc thực tế, người phụ trách/kiểm. */
    public function hireUpdate(Request $request, $id)
    {
        $hire = $this->findHire((int) $id);
        $req = $this->findRequest((int) $hire->request_id);
        $user = Auth::user();
        abort_unless($this->canEditRecord($hire, $user), 403, 'Chỉ HCNS phụ trách hoặc Ban Giám đốc/Admin được sửa lượt tuyển.');
        abort_if($hire->status === 'cancelled', 422, 'Lượt tuyển đã hủy — chỉ xem.');
        $data = $request->validate([
            'candidate_id' => ['required', 'integer'],
            'employee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', 'different:owner_id'],
            'status' => ['required', Rule::in(['pending', 'started', 'no_show'])],
            'actual_start_date' => ['nullable', 'date', 'before_or_equal:today', 'required_if:status,started'],
            'cancel_reason' => ['nullable', 'string', 'max:2000', 'required_if:status,no_show'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'reviewer_id.different' => 'Người kiểm phải khác HCNS phụ trách.',
            'actual_start_date.required_if' => 'Nhập ngày nhận việc THỰC TẾ (không dùng ngày dự kiến).',
            'actual_start_date.before_or_equal' => 'Ngày nhận việc thực tế không được sau hôm nay.',
            'cancel_reason.required_if' => 'Ghi lý do ứng viên không nhận việc.',
        ]);
        $candidate = DB::table('hr_recruitment_candidates')->where('id', $data['candidate_id'])->first();
        if (! $candidate || (int) $candidate->recruitment_request_id !== (int) $req->id) {
            throw ValidationException::withMessages(['candidate_id' => 'Ứng viên phải thuộc đúng yêu cầu tuyển này.']);
        }
        if ($data['status'] === 'started') {
            if (empty($data['employee_user_id'])) {
                throw ValidationException::withMessages(['employee_user_id' => 'Nối tài khoản nhân viên (bằng ID) khi ghi đã nhận việc.']);
            }
            if ((int) $data['employee_user_id'] === (int) $data['reviewer_id']) {
                throw ValidationException::withMessages(['reviewer_id' => 'Người kiểm không được là chính nhân viên mới.']);
            }
            if (RecruitmentKpi::recruitDays($req->request_received_at, $data['actual_start_date']) === null) {
                throw ValidationException::withMessages(['actual_start_date' => 'Ngày nhận việc thực tế phải từ ngày HCNS tiếp nhận yêu cầu trở đi.']);
            }
            if (DB::table('hr_recruitment_hires')->where('employee_user_id', $data['employee_user_id'])->where('request_id', $req->id)->where('id', '!=', $hire->id)->whereIn('status', ['pending', 'started'])->exists()) {
                throw ValidationException::withMessages(['employee_user_id' => 'Tài khoản này đã gắn với lượt tuyển khác của cùng yêu cầu.']);
            }
        }
        $values = [
            'candidate_id' => (int) $data['candidate_id'],
            'employee_user_id' => ! empty($data['employee_user_id']) ? (int) $data['employee_user_id'] : ($data['status'] === 'started' ? null : $hire->employee_user_id),
            'owner_id' => (int) $data['owner_id'], 'reviewer_id' => (int) $data['reviewer_id'], 'status' => $data['status'],
            'actual_start_date' => $data['status'] === 'started' ? Carbon::parse($data['actual_start_date'])->toDateString() : null,
            'cancel_reason' => $data['status'] === 'no_show' ? trim((string) $data['cancel_reason']) : $hire->cancel_reason,
        ];
        if ($locked = RecruitmentKpi::hireLockedMonth($hire, [$values['actual_start_date']])) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'lượt tuyển')]);
        }
        if (in_array($hire->status, ['started'], true) && $data['status'] !== 'started' && $this->reviewSubmitted((int) $hire->id, RecruitmentTimeSource::CODE)) {
            throw ValidationException::withMessages(['status' => 'Lượt đã gửi kiểm/đã xác nhận nhận việc — người kiểm trả bổ sung trước khi đổi trạng thái.']);
        }
        $changes = $this->diff($hire, $values, ['candidate_id' => 'ứng viên', 'employee_user_id' => 'tài khoản nhân viên', 'owner_id' => 'HCNS phụ trách',
            'reviewer_id' => 'người kiểm', 'status' => 'trạng thái', 'actual_start_date' => 'ngày nhận việc thực tế', 'cancel_reason' => 'lý do']);
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }
        DB::table('hr_recruitment_hires')->where('id', $hire->id)->update($values + ['updated_at' => now()]);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::HIRE_TYPE, (int) $hire->id, 'updated', implode('; ', $changes).(trim((string) ($data['note'] ?? '')) ? ' · '.$data['note'] : ''), (int) $user->id);
        $this->invalidateHire((int) $hire->id, 'Sửa '.implode(', ', array_map(fn ($c) => strtok($c, ':'), $changes)));

        return back()->with('success', 'Đã lưu lượt tuyển. Nếu đã gửi/kiểm tra, cần gửi lại.');
    }

    /** HC03: ngày thử việc, kết quả, ngày nghỉ thực, hình thức và lý do. */
    public function probationUpdate(Request $request, $id)
    {
        $hire = $this->findHire((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRecord($hire, $user), 403);
        abort_if($hire->status !== 'started', 422, 'Chỉ ghi thử việc cho lượt đã nhận việc thực tế.');
        $data = $request->validate([
            'probation_start_date' => ['required', 'date'],
            'probation_end_date' => ['required', 'date', 'after_or_equal:probation_start_date'],
            'probation_outcome' => ['nullable', Rule::in(array_keys((array) config('hcns_kpi.probation_outcomes')))],
            'probation_left_date' => ['nullable', 'date', 'required_if:probation_outcome,left', 'after_or_equal:probation_start_date', 'before_or_equal:probation_end_date', 'before_or_equal:today'],
            'probation_left_type' => ['nullable', Rule::in(array_keys((array) config('hcns_kpi.probation_left_types'))), 'required_if:probation_outcome,left'],
            'probation_reason' => ['nullable', 'string', 'max:2000', 'required_if:probation_outcome,left'],
        ], [
            'probation_end_date.after_or_equal' => 'Ngày kết thúc thử việc phải sau ngày bắt đầu.',
            'probation_left_date.required_if' => 'Nhập ngày nghỉ thực khi ghi “Nghỉ trong thử việc”.',
            'probation_left_date.before_or_equal' => 'Ngày nghỉ phải nằm trong thời gian thử việc và không sau hôm nay.',
            'probation_left_type.required_if' => 'Chọn hình thức nghỉ.',
            'probation_reason.required_if' => 'Ghi lý do nghỉ.',
        ]);
        if (Carbon::parse($data['probation_start_date'])->lt(Carbon::parse($hire->actual_start_date))) {
            throw ValidationException::withMessages(['probation_start_date' => 'Thử việc không bắt đầu trước ngày nhận việc thực tế.']);
        }
        if (($data['probation_outcome'] ?? null) === 'passed' && Carbon::parse($data['probation_end_date'])->isFuture()) {
            throw ValidationException::withMessages(['probation_outcome' => 'Chưa hết thời gian thử việc — chưa ghi “Đạt thử việc”.']);
        }
        $left = ($data['probation_outcome'] ?? null) === 'left';
        $values = [
            'probation_start_date' => Carbon::parse($data['probation_start_date'])->toDateString(),
            'probation_end_date' => Carbon::parse($data['probation_end_date'])->toDateString(),
            'probation_outcome' => $data['probation_outcome'] ?? null,
            'probation_left_date' => $left ? Carbon::parse($data['probation_left_date'])->toDateString() : null,
            'probation_left_type' => $left ? $data['probation_left_type'] : null,
            'probation_reason' => $left ? trim((string) $data['probation_reason']) : (trim((string) ($data['probation_reason'] ?? '')) ?: null),
        ];
        $after = (object) array_merge((array) $hire, $values);
        if ($locked = RecruitmentKpi::probationLockedMonth($hire, [RecruitmentKpi::probationMonth($after) ? RecruitmentKpi::probationMonth($after).'-01' : null])) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'kết quả thử việc')]);
        }
        $changes = $this->diff($hire, $values, ['probation_start_date' => 'bắt đầu thử việc', 'probation_end_date' => 'kết thúc thử việc', 'probation_outcome' => 'kết quả',
            'probation_left_date' => 'ngày nghỉ', 'probation_left_type' => 'hình thức nghỉ', 'probation_reason' => 'lý do']);
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }
        DB::table('hr_recruitment_hires')->where('id', $hire->id)->update($values + ['updated_at' => now()]);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::HIRE_TYPE, (int) $hire->id, 'probation_updated', implode('; ', $changes), (int) $user->id);
        $this->invalidateHire((int) $hire->id, 'Sửa kết quả thử việc', [ProbationQuitSource::CODE]);

        return back()->with('success', 'Đã lưu kết quả thử việc. Nếu đã gửi/kiểm tra, cần gửi lại.');
    }

    public function hireCancel(Request $request, $id)
    {
        $hire = $this->findHire((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRecord($hire, $user), 403);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:2000']], ['cancel_reason.required' => 'Ghi lý do hủy lượt tuyển.']);
        abort_if($hire->status === 'cancelled', 422, 'Lượt tuyển đã hủy.');
        if ($this->reviewSubmitted((int) $hire->id, RecruitmentTimeSource::CODE) || $this->reviewSubmitted((int) $hire->id, ProbationQuitSource::CODE)) {
            throw ValidationException::withMessages(['kpi' => 'Lượt đã gửi kiểm/đã xác nhận — không hủy được. Người kiểm trả bổ sung nếu cần sửa.']);
        }
        if ($locked = RecruitmentKpi::hireLockedMonth($hire, [$hire->expected_start_date])) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'lượt tuyển')]);
        }
        DB::table('hr_recruitment_hires')->where('id', $hire->id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id,
            'cancel_reason' => trim($data['cancel_reason']), 'updated_at' => now()]);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::HIRE_TYPE, (int) $hire->id, 'cancelled', trim($data['cancel_reason']), (int) $user->id);

        return back()->with('success', 'Đã hủy lượt tuyển (giữ lịch sử, suất được mở lại).');
    }

    public function hireFileUpload(Request $request, $id)
    {
        $hire = $this->findHire((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRecord($hire, $user), 403);
        $this->assertHireUnlocked($hire);
        $service = app(RecruitmentAttachmentService::class);
        $service->validate($request);
        if ($request->file('attachments', []) === []) {
            throw ValidationException::withMessages(['attachments' => 'Chọn ít nhất một tệp.']);
        }
        $count = $service->attach($request, RecruitmentKpi::ATTACHMENT_ENTITY, (int) $hire->id);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::HIRE_TYPE, (int) $hire->id, 'file_added', 'Thêm '.$count.' tệp chứng cứ', (int) $user->id);
        $this->invalidateHire((int) $hire->id, 'Thêm tệp chứng cứ');

        return back()->with('success', 'Đã tải '.$count.' tệp chứng cứ.');
    }

    public function hireFileDelete($id, $fileId)
    {
        $hire = $this->findHire((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRecord($hire, $user), 403);
        $this->assertHireUnlocked($hire);
        $file = app(RecruitmentAttachmentService::class)->deleteOne(RecruitmentKpi::ATTACHMENT_ENTITY, (int) $hire->id, (int) $fileId);
        abort_unless($file, 404);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::HIRE_TYPE, (int) $hire->id, 'file_deleted', 'Xóa tệp '.$file->original_name, (int) $user->id);
        $this->invalidateHire((int) $hire->id, 'Xóa tệp chứng cứ');

        return back()->with('success', 'Đã xóa tệp chứng cứ.');
    }

    /** Nháp/gửi/xác nhận/trả cho HC01 (recruit_time) hoặc HC03 (probation_quit) của một lượt tuyển. */
    public function hireKpi(Request $request, $id, string $criterion, string $action)
    {
        abort_unless(isset(self::CRITERIA[$criterion]), 404);
        $hire = $this->findHire((int) $id);
        $user = Auth::user();
        $kpi = app(RecruitmentKpi::class);
        $isTime = $criterion === RecruitmentTimeSource::CODE;
        $locked = $isTime ? RecruitmentKpi::hireLockedMonth($hire) : RecruitmentKpi::probationLockedMonth($hire);
        if ($locked) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, $isTime ? 'lượt tuyển' : 'kết quả thử việc')]);
        }
        abort_if($hire->status === 'cancelled', 422, 'Lượt tuyển đã hủy.');
        $declared = $isTime ? $kpi->hireDeclared($hire) : $kpi->probationDeclared($hire);
        $fingerprint = $isTime ? $kpi->hireFingerprint($hire) : $kpi->probationFingerprint($hire);
        $label = self::CRITERIA[$criterion];

        return $this->reviewAction($request, $action, RecruitmentKpi::HIRE_TYPE, (int) $hire->id, $criterion, $hire, $user, $declared, $fingerprint,
            fn () => $isTime ? $kpi->hireMissing($hire) : $kpi->probationMissing($hire),
            (array) config($isTime ? 'hcns_kpi.hire_checks' : 'hcns_kpi.probation_checks'),
            fn ($note) => $kpi->log(RecruitmentKpi::HIRE_TYPE, (int) $hire->id, 'kpi_'.$action, $label.($note ? ': '.$note : ''), (int) $user->id));
    }

    // ================================================================ Chi phí

    public function costStore(Request $request, $id)
    {
        $req = $this->findRequest((int) $id);
        $user = Auth::user();
        abort_unless(RecruitmentKpi::costsReady(), 404);
        abort_unless($this->canEditRequest($req, $user), 403, 'Chỉ HCNS phụ trách hoặc Ban Giám đốc/Admin được gắn chi phí.');
        if (empty($req->kpi_tracked) || empty($req->owner_id) || empty($req->reviewer_id)) {
            throw ValidationException::withMessages(['payment_request_id' => 'Bật theo dõi KPI và chọn HCNS phụ trách/người kiểm cho yêu cầu trước.']);
        }
        $data = $this->validateCost($request);
        if (DB::table('hr_recruitment_costs')->where('request_id', $req->id)->where('payment_request_id', $data['payment_request_id'])->whereNull('removed_at')->exists()) {
            throw ValidationException::withMessages(['payment_request_id' => 'Chứng từ này đã gắn với đợt tuyển — sửa số phân bổ thay vì gắn lần nữa.']);
        }
        $this->assertAllocation((int) $data['payment_request_id'], (float) $data['allocated_amount'], null);
        $draft = (object) ['payment_request_id' => (int) $data['payment_request_id']];
        if ($locked = app(RecruitmentKpi::class)->costLockedMonth($draft)) {
            throw ValidationException::withMessages(['payment_request_id' => RecruitmentKpi::lockMessage($locked, 'chi phí tuyển dụng')]);
        }
        $costId = DB::table('hr_recruitment_costs')->insertGetId([
            'company_id' => EgoCompanyLock::id(), 'request_id' => $req->id, 'payment_request_id' => (int) $data['payment_request_id'],
            'allocated_amount' => $data['allocated_amount'], 'owner_id' => $req->owner_id, 'reviewer_id' => $req->reviewer_id,
            'note' => trim((string) ($data['note'] ?? '')) ?: null, 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $doc = app(RecruitmentKpi::class)->document((int) $data['payment_request_id']);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::COST_TYPE, $costId, 'created', 'Gắn '.($doc['code'] ?? '#'.$data['payment_request_id']).' · phân bổ '.number_format((float) $data['allocated_amount'], 0, ',', '.').' đ', (int) $user->id);

        return redirect()->route('hr.recruitment.costs.show', $costId)->with('success', 'Đã gắn chứng từ cho đợt tuyển. Kiểm tra số phân bổ rồi gửi kiểm.');
    }

    public function cost($id)
    {
        abort_unless(RecruitmentKpi::costsReady(), 404);
        $cost = $this->findCost((int) $id);
        $req = $this->findRequest((int) $cost->request_id);
        $user = Auth::user();
        $kpi = app(RecruitmentKpi::class);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $doc = $kpi->document((int) $cost->payment_request_id);
        $row = app(RecruitmentCostSource::class)->costRow((object) ((array) $cost + ['position' => $req->position] + $this->reviewFields($cost->id, RecruitmentCostSource::CODE, RecruitmentKpi::COST_TYPE)),
            $doc, $names, Carbon::parse($doc['valid_at'] ?? $cost->created_at));
        $other = DB::table('hr_recruitment_costs as k')->join('hr_recruitment_requests as r', 'r.id', '=', 'k.request_id')
            ->where('k.payment_request_id', $cost->payment_request_id)->where('k.id', '!=', $cost->id)->whereNull('k.removed_at')
            ->get(['k.id', 'k.allocated_amount', 'r.position', 'r.id as request_id']);

        return view('hr.recruitment.kpi.cost', [
            'cost' => $cost, 'req' => $req, 'doc' => $doc, 'row' => $row, 'names' => $names, 'other' => $other,
            'review' => $this->review((int) $cost->id, RecruitmentCostSource::CODE, RecruitmentKpi::COST_TYPE),
            'events' => ($r = $this->review((int) $cost->id, RecruitmentCostSource::CODE, RecruitmentKpi::COST_TYPE)) ? app(HcnsSourceReviews::class)->events((int) $r->id) : collect(),
            'missing' => $kpi->costMissing($cost),
            'locked' => $kpi->costLockedMonth($cost),
            'logs' => $kpi->logs(RecruitmentKpi::COST_TYPE, (int) $cost->id),
            'isOwner' => $user && (int) $cost->owner_id === (int) $user->id,
            'canEdit' => $user && $this->canEditRecord($cost, $user),
            'canReview' => $user && $this->canReview($cost, $user, RecruitmentCostSource::CODE),
            'canOpenDoc' => $user && ((method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'accounting', 'ketoan', 'ke_toan', 'hr'])) || (int) ($doc['created_by'] ?? 0) === (int) $user->id),
            'users' => DB::table('users')->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function costUpdate(Request $request, $id)
    {
        $cost = $this->findCost((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRecord($cost, $user), 403);
        abort_if(! empty($cost->removed_at), 422, 'Khoản chi đã gỡ — chỉ xem.');
        $data = $request->validate([
            'allocated_amount' => ['required', 'numeric', 'min:1', 'max:10000000000'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', Rule::notIn([(int) $cost->owner_id])],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['reviewer_id.not_in' => 'Người kiểm phải khác HCNS phụ trách.']);
        if ($locked = app(RecruitmentKpi::class)->costLockedMonth($cost)) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'chi phí tuyển dụng')]);
        }
        $this->assertAllocation((int) $cost->payment_request_id, (float) $data['allocated_amount'], (int) $cost->id);
        $values = ['allocated_amount' => number_format((float) $data['allocated_amount'], 2, '.', ''), 'reviewer_id' => (int) $data['reviewer_id'], 'note' => trim((string) ($data['note'] ?? '')) ?: null];
        $changes = $this->diff($cost, $values, ['allocated_amount' => 'số phân bổ', 'reviewer_id' => 'người kiểm', 'note' => 'ghi chú']);
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }
        DB::table('hr_recruitment_costs')->where('id', $cost->id)->update($values + ['updated_at' => now()]);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::COST_TYPE, (int) $cost->id, 'adjusted', implode('; ', $changes), (int) $user->id);
        $this->invalidateCost((int) $cost->id, 'Điều chỉnh '.implode(', ', array_map(fn ($c) => strtok($c, ':'), $changes)));

        return back()->with('success', 'Đã điều chỉnh khoản chi. Nếu đã gửi/kiểm tra, cần gửi lại.');
    }

    public function costRemove(Request $request, $id)
    {
        $cost = $this->findCost((int) $id);
        $user = Auth::user();
        abort_unless($this->canEditRecord($cost, $user), 403);
        $data = $request->validate(['remove_reason' => ['required', 'string', 'min:5', 'max:2000']], ['remove_reason.required' => 'Ghi lý do gỡ khoản chi.']);
        abort_if(! empty($cost->removed_at), 422, 'Khoản chi đã gỡ.');
        if ($locked = app(RecruitmentKpi::class)->costLockedMonth($cost)) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'chi phí tuyển dụng')]);
        }
        DB::table('hr_recruitment_costs')->where('id', $cost->id)->update(['removed_at' => now(), 'removed_by' => $user->id, 'remove_reason' => trim($data['remove_reason']), 'updated_at' => now()]);
        app(RecruitmentKpi::class)->log(RecruitmentKpi::COST_TYPE, (int) $cost->id, 'removed', trim($data['remove_reason']), (int) $user->id);
        $this->invalidateCost((int) $cost->id, 'Gỡ khoản chi');

        return back()->with('success', 'Đã gỡ khoản chi khỏi đợt tuyển (giữ lịch sử).');
    }

    public function costKpi(Request $request, $id, string $action)
    {
        $cost = $this->findCost((int) $id);
        $user = Auth::user();
        $kpi = app(RecruitmentKpi::class);
        abort_if(! empty($cost->removed_at), 422, 'Khoản chi đã gỡ.');
        if ($locked = $kpi->costLockedMonth($cost)) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'chi phí tuyển dụng')]);
        }

        return $this->reviewAction($request, $action, RecruitmentKpi::COST_TYPE, (int) $cost->id, RecruitmentCostSource::CODE, $cost, $user,
            $kpi->costDeclared($cost), $kpi->costFingerprint($cost), fn () => $kpi->costMissing($cost), (array) config('hcns_kpi.cost_checks'),
            fn ($note) => $kpi->log(RecruitmentKpi::COST_TYPE, (int) $cost->id, 'kpi_'.$action, 'HC02'.($note ? ': '.$note : ''), (int) $user->id));
    }

    // ================================================================ dùng chung

    private function reviewAction(Request $request, string $action, string $type, int $id, string $criterion, object $record, $user,
        array $declared, string $fingerprint, \Closure $missing, array $checkLabels, \Closure $log)
    {
        $reviews = app(HcnsSourceReviews::class);
        $note = trim((string) $request->input('note', '')) ?: null;
        switch ($action) {
            case 'draft':
            case 'submit':
                abort_unless((int) $record->owner_id === (int) $user->id, 403, 'Chỉ HCNS phụ trách được lưu nháp/gửi kiểm.');
                if ($action === 'submit' && ($m = $missing()) !== []) {
                    return back()->with('error', 'Chưa gửi kiểm được — thiếu: '.implode(', ', $m).'.');
                }
                $reviews->{$action === 'draft' ? 'saveDraft' : 'submit'}($type, $id, $criterion, (int) $record->owner_id, (int) $record->reviewer_id ?: null, $declared, $fingerprint, (int) $user->id, $note);
                $log($note);

                return back()->with('success', $action === 'draft' ? 'Đã lưu nháp. Chưa gửi người kiểm, chưa tính KPI.' : 'Đã gửi kiểm tra. Chờ người kiểm xác nhận.');
            case 'confirm':
            case 'return':
                abort_unless($this->canReview($record, $user, $criterion), 403, 'Bạn không phải người kiểm của hồ sơ này.');
                $review = $reviews->find($type, $id, $criterion);
                abort_unless($review, 404);
                if ($action === 'return') {
                    $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']], ['reason.required' => 'Ghi lý do trả bổ sung.']);
                    $reviews->returnForFix($review, trim($data['reason']), (int) $user->id);
                    $log(trim($data['reason']));

                    return back()->with('success', 'Đã trả bổ sung cho HCNS phụ trách.');
                }
                $data = $request->validate([
                    'checks' => ['required', 'array'], 'checks.*' => ['required', Rule::in(['pass', 'fail'])], 'note' => ['nullable', 'string', 'max:2000'],
                ], ['checks.required' => 'Chọn Đạt/Không đạt cho từng mục kiểm tra.']);
                $checks = array_intersect_key((array) $data['checks'], $checkLabels);
                if (count($checks) !== count($checkLabels)) {
                    throw ValidationException::withMessages(['checks' => 'Chọn Đạt/Không đạt cho đủ '.count($checkLabels).' mục kiểm tra.']);
                }
                if (collect($checks)->contains('fail') && $note === null) {
                    throw ValidationException::withMessages(['note' => 'Ghi rõ lỗi khi có mục không đạt.']);
                }
                $reviews->confirm($review, $checks, $fingerprint, (int) $user->id, $note);
                $log($note);

                return back()->with('success', 'Đã xác nhận kết quả kiểm tra.');
        }
        abort(404);
    }

    private function validateCost(Request $request): array
    {
        $data = $request->validate([
            'payment_request_id' => ['required', 'integer', 'exists:payment_requests,id'],
            'allocated_amount' => ['required', 'numeric', 'min:1', 'max:10000000000'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['payment_request_id.required' => 'Chọn chứng từ (ĐNTT/phiếu chi/tạm ứng/hoàn tiền).', 'allocated_amount.min' => 'Số phân bổ phải lớn hơn 0.']);
        $doc = DB::table('payment_requests')->where('id', $data['payment_request_id'])->first();
        if (! isset(RecruitmentKpi::DOC_TYPES[(string) ($doc->doc_type ?: 'payment_request')]) || (! empty($doc->company_id) && (int) $doc->company_id !== EgoCompanyLock::id())) {
            throw ValidationException::withMessages(['payment_request_id' => 'Chứng từ không thuộc loại/công ty dùng cho chi phí tuyển dụng.']);
        }

        return $data;
    }

    /** Một chứng từ không phân bổ vượt số thực chi (hoặc số đề nghị khi chưa thực chi) — chống cộng trùng. */
    private function assertAllocation(int $paymentRequestId, float $amount, ?int $exceptCostId): void
    {
        $kpi = app(RecruitmentKpi::class);
        $doc = $kpi->document($paymentRequestId);
        $cap = $doc['valid'] ? (float) $doc['valid_amount'] : (float) $doc['amount'];
        $used = $kpi->allocatedElsewhere($paymentRequestId, $exceptCostId);
        if ($amount + $used > $cap + 0.004) {
            throw ValidationException::withMessages(['allocated_amount' => 'Vượt số '.($doc['valid'] ? 'thực chi' : 'đề nghị').' của chứng từ: còn phân bổ được '
                .number_format(max($cap - $used, 0), 0, ',', '.').' đ (đã phân bổ nơi khác '.number_format($used, 0, ',', '.').' đ).']);
        }
    }

    private function assertRequestHiresOpen(object $req, string $what): void
    {
        foreach (DB::table('hr_recruitment_hires')->where('request_id', $req->id)->get() as $h) {
            if ($locked = RecruitmentKpi::hireLockedMonth($h)) {
                throw ValidationException::withMessages(['request_received_at' => RecruitmentKpi::lockMessage($locked, $what.' (ảnh hưởng lượt tuyển đã chốt)')]);
            }
        }
    }

    private function assertHireUnlocked(object $hire): void
    {
        if ($locked = RecruitmentKpi::hireLockedMonth($hire) ?: RecruitmentKpi::probationLockedMonth($hire)) {
            throw ValidationException::withMessages(['kpi' => RecruitmentKpi::lockMessage($locked, 'chứng cứ lượt tuyển')]);
        }
    }

    private function invalidateHire(int $hireId, string $what, array $criteria = [RecruitmentTimeSource::CODE, ProbationQuitSource::CODE]): void
    {
        $hire = DB::table('hr_recruitment_hires')->where('id', $hireId)->first();
        $kpi = app(RecruitmentKpi::class);
        foreach ($criteria as $criterion) {
            $before = $this->review($hireId, $criterion);
            $after = app(HcnsSourceReviews::class)->invalidateIfChanged(RecruitmentKpi::HIRE_TYPE, $hireId, $criterion,
                $criterion === RecruitmentTimeSource::CODE ? $kpi->hireFingerprint($hire) : $kpi->probationFingerprint($hire), (int) Auth::id(), $what);
            if ($before && $after && $before->status !== $after->status) {
                $kpi->log(RecruitmentKpi::HIRE_TYPE, $hireId, 'review_invalidated', self::CRITERIA[$criterion].': '.$what.' sau khi đã gửi/kiểm tra — cần gửi lại.', (int) Auth::id());
            }
        }
    }

    private function invalidateCost(int $costId, string $what): void
    {
        $cost = DB::table('hr_recruitment_costs')->where('id', $costId)->first();
        $kpi = app(RecruitmentKpi::class);
        $before = $this->review($costId, RecruitmentCostSource::CODE, RecruitmentKpi::COST_TYPE);
        $after = app(HcnsSourceReviews::class)->invalidateIfChanged(RecruitmentKpi::COST_TYPE, $costId, RecruitmentCostSource::CODE, $kpi->costFingerprint($cost), (int) Auth::id(), $what);
        if ($before && $after && $before->status !== $after->status) {
            $kpi->log(RecruitmentKpi::COST_TYPE, $costId, 'review_invalidated', $what.' sau khi đã gửi/kiểm tra — cần gửi lại.', (int) Auth::id());
        }
    }

    private function review(int $id, string $criterion, string $type = RecruitmentKpi::HIRE_TYPE): ?object
    {
        return app(HcnsSourceReviews::class)->find($type, $id, $criterion);
    }

    private function reviewSubmitted(int $hireId, string $criterion): bool
    {
        return in_array($this->review($hireId, $criterion)->status ?? null, ['submitted', 'confirmed'], true);
    }

    private function reviewFields(int $id, string $criterion, string $type = RecruitmentKpi::HIRE_TYPE): array
    {
        $r = $this->review($id, $criterion, $type);

        return ['review_status' => $r->status ?? null, 'result' => $r->result ?? null, 'checks' => $r->checks ?? null, 'confirmed_by' => $r->confirmed_by ?? null,
            'confirmed_at' => $r->confirmed_at ?? null, 'return_reason' => $r->return_reason ?? null];
    }

    /** Sửa phần KPI của yêu cầu: HCNS phụ trách (hoặc khi chưa có người phụ trách), Ban Giám đốc/Admin. */
    private function canEditRequest(object $req, $user): bool
    {
        return empty($req->owner_id) || (int) $req->owner_id === (int) $user->id || PayrollAccess::canApprove($user);
    }

    private function canEditRecord(object $record, $user): bool
    {
        return (int) $record->owner_id === (int) $user->id || PayrollAccess::canApprove($user);
    }

    /** Người kiểm chỉ định hoặc Ban Giám đốc/Admin; không phải người phụ trách; HC01/HC03 không phải chính nhân viên mới. */
    private function canReview(object $record, $user, string $criterion): bool
    {
        if ((int) $record->owner_id === (int) $user->id) {
            return false;
        }
        if ($criterion !== RecruitmentCostSource::CODE && (int) ($record->employee_user_id ?? 0) === (int) $user->id) {
            return false;
        }

        return (int) ($record->reviewer_id ?? 0) === (int) $user->id || PayrollAccess::canApprove($user);
    }

    private function diff(object $before, array $values, array $labels): array
    {
        $changes = [];
        foreach ($values as $field => $value) {
            if (! isset($labels[$field])) {
                continue;
            }
            $old = (string) ($before->{$field} ?? '');
            $new = is_bool($value) ? (string) (int) $value : (string) ($value ?? '');
            if ($field === 'allocated_amount') {
                [$old, $new] = [number_format((float) $old, 2, '.', ''), number_format((float) $new, 2, '.', '')];
            }
            if ($old !== $new) {
                $changes[] = $labels[$field].': '.($old === '' ? '—' : $old).' → '.($new === '' ? '—' : $new);
            }
        }

        return $changes;
    }

    private function findRequest(int $id): object
    {
        $req = DB::table('hr_recruitment_requests')->where('id', $id)->first();
        abort_unless($req, 404);
        abort_if(! empty($req->company_id) && (int) $req->company_id !== EgoCompanyLock::id(), 404);

        return $req;
    }

    private function findHire(int $id): object
    {
        abort_unless(RecruitmentKpi::ready(), 404);
        $hire = DB::table('hr_recruitment_hires')->where('id', $id)->first();
        abort_unless($hire && (empty($hire->company_id) || (int) $hire->company_id === EgoCompanyLock::id()), 404);

        return $hire;
    }

    private function findCost(int $id): object
    {
        $cost = DB::table('hr_recruitment_costs')->where('id', $id)->first();
        abort_unless($cost && (empty($cost->company_id) || (int) $cost->company_id === EgoCompanyLock::id()), 404);

        return $cost;
    }
}
