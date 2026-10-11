<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Services\Hr\Payroll\PayrollExcelExporter;
use App\Services\Hr\Payroll\PayrollGenerator;
use App\Services\Hr\Payroll\PayrollSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bảng lương tự động theo tháng (thay file Excel lương):
 * Tạo bảng lương → HR rà soát, sửa tay ô cần thiết → Ban Giám đốc duyệt & khoá → phiếu lương / xuất Excel.
 */
final class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403, 'Chỉ HR, Kế toán, Ban Giám đốc hoặc Admin được xem bảng lương.');

        $month = $this->month($request->query('month'));
        if (! $request->filled('month') && ! DB::table('hr_payroll_periods')->where('payroll_month', $month)->exists()) {
            $month = DB::table('hr_payroll_periods')->where('payroll_month', '<=', $month)->max('payroll_month') ?: $month;
        }
        $period = DB::table('hr_payroll_periods')->where('payroll_month', $month)->first();
        $allLines = $period
            ? DB::table('hr_payroll_lines')->where('period_id', $period->id)->orderBy('department_name')->orderBy('employee_name')->get()
            : collect();
        $departments = $allLines->map(fn ($l) => self::departmentOf($l))->unique()->sort()->values();
        $department = (string) $request->query('department', '');
        $lines = $department === '' ? $allLines : $allLines->filter(fn ($l) => self::departmentOf($l) === $department)->values();

        return view('hr.payroll.index', [
            'month' => $month,
            'period' => $period,
            'lines' => $lines,
            'departments' => $departments,
            'department' => $department,
            'profileCount' => PayrollGenerator::profilesFor($month)->count(),
            'manualFields' => PayrollGenerator::MANUAL_FIELDS,
            'canApprove' => PayrollAccess::canApprove($request->user()),
            // KPI HC04: các đợt đối soát độ chính xác của kỳ lương này (không đổi số tiền bảng lương).
            'audits' => $period && \App\Services\Hr\Kpi\PayrollAudits::ready() ? $this->audits((int) $period->id) : collect(),
            'auditReady' => $period && \App\Services\Hr\Kpi\PayrollAudits::ready(),
            'auditUsers' => $period && \App\Services\Hr\Kpi\PayrollAudits::ready()
                ? DB::table('users')->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))->orderBy('name')->get(['id', 'name'])
                : collect(),
            'allLines' => $allLines,
            'totals' => [
                'gross' => $lines->sum('gross_income'),
                'insurance_employee' => $lines->sum('insurance_employee'),
                'insurance_employer' => $lines->sum('insurance_employer'),
                'pit' => $lines->sum('pit'),
                'net' => $lines->sum('net_salary'),
                'warnings' => $lines->filter(fn ($l) => $l->warnings)->count(),
            ],
        ]);
    }

    public function generate(Request $request, PayrollGenerator $generator): RedirectResponse
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);
        $month = $request->validate(['month' => ['required', 'date_format:Y-m']])['month'];

        $period = DB::table('hr_payroll_periods')->where('payroll_month', $month)->first();
        if ($period && PayrollGenerator::isExcelSnapshot($period)) {
            return redirect()->route('hr.payroll.index', ['month' => $month])->with('error', 'Bảng lương nhập từ Excel đang giữ số liệu gốc để đối chiếu. Cần liên kết nhân viên và hoàn thiện dữ liệu ERP trước khi chuyển sang tự tính.');
        }

        if (PayrollGenerator::profilesFor($month)->isEmpty()) {
            return redirect()->route('hr.payroll.settings', ['tab' => 'profiles', 'month' => $month])->with('error', 'Chưa có hồ sơ lương nào hiệu lực cho tháng '.$month.'. Nhập hồ sơ lương trước khi tạo bảng lương.');
        }

        try {
            $generator->generate($month, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.payroll.index', ['month' => $month])->with('success', 'Đã tính bảng lương tháng '.$month.' từ chấm công, nghỉ phép, công tác, tạm ứng và KPI.');
    }

    public function updateLine(Request $request, int $line, PayrollGenerator $generator): RedirectResponse
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);
        $row = DB::table('hr_payroll_lines')->where('id', $line)->first();
        abort_unless($row, 404);
        abort_unless(PayrollAccess::canEditEmployee($request->user(), (int) $row->user_id), 403, 'Không được tự sửa dòng lương của chính mình.');

        $rules = ['note' => ['nullable', 'string', 'max:1000']];
        foreach (array_keys(PayrollGenerator::MANUAL_FIELDS) as $field) {
            $rules["manual.$field"] = ['nullable', 'numeric', 'min:0', $field === 'paid_days' ? 'max:31' : ($field === 'kpi_rate' ? 'max:200' : 'max:10000000000')];
        }
        $validated = $request->validate($rules, [
            'manual.*.numeric' => 'Giá trị phải là số.',
            'manual.*.min' => 'Giá trị không được âm.',
        ]);

        $manual = array_filter((array) ($validated['manual'] ?? []), fn ($v) => $v !== null && $v !== '');

        // Hệ số KPI nhập tay thay cho kết quả chấm KPI => chỉ Ban Giám đốc; người khác giữ nguyên giá trị đang có.
        if (! PayrollAccess::canApprove($request->user())) {
            $current = json_decode((string) $row->manual, true) ?: [];
            unset($manual['kpi_rate']);
            if (array_key_exists('kpi_rate', $current)) {
                $manual['kpi_rate'] = $current['kpi_rate'];
            }
        }

        try {
            $generator->updateManual($row, $manual, $validated['note'] ?? null, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $month = DB::table('hr_payroll_periods')->where('id', $row->period_id)->value('payroll_month');

        return redirect()->route('hr.payroll.index', ['month' => $month])->with('success', 'Đã cập nhật lương của '.$row->employee_name.'.');
    }

    public function approve(Request $request, int $period, PayrollGenerator $generator): RedirectResponse
    {
        abort_unless(PayrollAccess::canApprove($request->user()), 403, 'Chỉ Ban Giám đốc hoặc Admin được duyệt bảng lương.');
        $row = DB::table('hr_payroll_periods')->where('id', $period)->first();
        abort_unless($row, 404);
        if ($row->status === 'approved') {
            return back()->with('error', 'Bảng lương tháng '.$row->payroll_month.' đã được duyệt.');
        }

        // Tính lại ngay trước khi duyệt: nếu KPI, chấm công, hồ sơ lương... đã đổi sau lần tính trước thì dừng để xem lại.
        $before = PayrollGenerator::fingerprint($period);
        try {
            $generator->generate($row->payroll_month, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (PayrollGenerator::fingerprint($period) !== $before) {
            return redirect()->route('hr.payroll.index', ['month' => $row->payroll_month])
                ->with('error', 'Số liệu đã thay đổi kể từ lần tính trước (KPI, chấm công hoặc hồ sơ lương). Phần mềm đã tính lại — vui lòng xem lại rồi bấm Duyệt lần nữa.');
        }

        $missingKpi = DB::table('hr_payroll_lines')->where('period_id', $period)->where('warnings', 'like', '%Chưa có KPI%')->pluck('employee_name');
        if ($missingKpi->isNotEmpty()) {
            return back()->with('error', 'Chưa duyệt được: còn nhân viên chưa có KPI ('.$missingKpi->implode(', ').'). Chấm KPI rồi Tính lại, hoặc nhập tay hệ số KPI.');
        }

        DB::table('hr_payroll_periods')->where('id', $period)->where('status', 'draft')->update([
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'updated_at' => now(),
        ]);
        // Chụp KPI HCNS (cấu hình, kết quả, nguồn) của kỳ vừa chốt để kỳ cũ không đổi theo nguồn hiện tại.
        app(\App\Services\Hr\Kpi\HcnsKpiSnapshots::class)->capture((int) $period, (int) $request->user()->id);

        return redirect()->route('hr.payroll.index', ['month' => $row->payroll_month])->with('success', 'Đã duyệt và khoá bảng lương tháng '.$row->payroll_month.'. Nhân viên xem được phiếu lương của mình.');
    }

    public function reopen(Request $request, int $period): RedirectResponse
    {
        abort_unless(PayrollAccess::canApprove($request->user()), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Bắt buộc nhập lý do mở khoá.']);
        $row = DB::table('hr_payroll_periods')->where('id', $period)->first();
        abort_unless($row, 404);

        DB::table('hr_payroll_periods')->where('id', $period)->update([
            'status' => 'draft',
            'approved_by' => null,
            'approved_at' => null,
            'note' => trim(($row->note ? $row->note."\n" : '').now()->format('d/m/Y H:i').' – '.$request->user()->name.' mở khoá: '.$validated['reason']),
            'updated_at' => now(),
        ]);
        // Mở khóa: ảnh chụp KPI cũ không còn hiện hành (giữ làm lịch sử); duyệt lại sẽ chụp bản mới.
        app(\App\Services\Hr\Kpi\HcnsKpiSnapshots::class)->supersede((int) $period, (int) $request->user()->id);

        return redirect()->route('hr.payroll.index', ['month' => $row->payroll_month])->with('success', 'Đã mở khoá bảng lương tháng '.$row->payroll_month.'.');
    }

    public function export(Request $request, PayrollExcelExporter $exporter): StreamedResponse
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);
        $month = $this->month($request->query('month'));
        $period = DB::table('hr_payroll_periods')->where('payroll_month', $month)->first();
        abort_unless($period, 404, 'Chưa có bảng lương tháng '.$month.'.');

        return $exporter->download($period);
    }

    public function payslip(Request $request, int $line): View
    {
        $row = DB::table('hr_payroll_lines')->where('id', $line)->first();
        abort_unless($row, 404);
        $period = DB::table('hr_payroll_periods')->where('id', $row->period_id)->first();

        $isOwnApproved = (int) $row->user_id === (int) $request->user()->id && $period->status === 'approved';
        abort_unless(PayrollAccess::canManage($request->user()) || $isOwnApproved, 403, 'Bạn chỉ xem được phiếu lương của chính mình sau khi bảng lương được duyệt.');

        return view('hr.payroll.payslip', ['line' => $row, 'period' => $period]);
    }

    public function my(Request $request): View
    {
        $lines = DB::table('hr_payroll_lines as l')
            ->join('hr_payroll_periods as p', 'p.id', '=', 'l.period_id')
            ->where('l.user_id', $request->user()->id)
            ->where('p.status', 'approved')
            ->orderByDesc('p.payroll_month')
            ->get(['l.*', 'p.payroll_month']);

        return view('hr.payroll.my', ['lines' => $lines]);
    }

    public function settings(Request $request): View
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);

        $tab = $request->query('tab') === 'rules' ? 'rules' : 'profiles';
        $profileData = $tab === 'profiles'
            ? app(SalaryProfileController::class)->viewData($request, app(\App\Services\Hr\Kpi\HrKpiEvaluator::class))
            : [];

        return view('hr.payroll.settings', [
            'tab' => $tab,
            'profileData' => $profileData,
            'settings' => PayrollSettings::all(),
            'labels' => PayrollSettings::LABELS,
            'leaveTypes' => PayrollSettings::LEAVE_TYPES,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);

        $rules = [];
        foreach (PayrollSettings::DEFAULTS as $key => $default) {
            $rules[$key] = match ($key) {
                'paid_leave_types' => ['nullable', 'array'],
                'ot_auto' => ['required', Rule::in(['0', '1', 0, 1])],
                default => ['required', 'numeric', 'min:0', 'max:1000000000'],
            };
        }
        $rules['paid_leave_types.*'] = [Rule::in(array_keys(PayrollSettings::LEAVE_TYPES))];
        $validated = $request->validate($rules);
        $validated['paid_leave_types'] ??= [];

        PayrollSettings::save($validated, (int) $request->user()->id);

        return redirect()->route('hr.payroll.settings', ['tab' => 'rules'])->with('success', 'Đã lưu cài đặt lương. Áp dụng cho các bảng lương tính lại từ bây giờ (bảng đã duyệt giữ nguyên).');
    }

    /** Đợt đối soát HC04 của kỳ (công ty khóa hoặc chưa gán) kèm trạng thái kiểm tra, số dòng đã kiểm và số lỗi. */
    private function audits(int $periodId): \Illuminate\Support\Collection
    {
        return DB::table('hr_payroll_audits as a')
            ->leftJoin('hr_kpi_source_reviews as v', fn ($j) => $j->on('v.source_id', '=', 'a.id')
                ->where('v.source_type', \App\Services\Hr\Kpi\PayrollAudits::SOURCE_TYPE)->where('v.criterion_code', 'payroll_accuracy'))
            ->leftJoin('users as o', 'o.id', '=', 'a.owner_id')
            ->leftJoin('users as c', 'c.id', '=', 'a.checker_id')
            ->leftJoin('users as r', 'r.id', '=', 'a.reviewer_id')
            ->where('a.period_id', $periodId)
            ->where(fn ($q) => $q->where('a.company_id', \App\Support\EgoCompanyLock::id())->orWhereNull('a.company_id'))
            ->orderByDesc('a.id')
            ->get(['a.*', 'v.status as review_status', 'v.result as review_result', 'o.name as owner_name', 'c.name as checker_name', 'r.name as reviewer_name'])
            ->map(function ($a) {
                $items = DB::table('hr_payroll_audit_items')->where('audit_id', $a->id)->get(['result']);
                $a->checked = $items->whereIn('result', ['ok', 'error'])->count();
                $a->with_errors = $items->where('result', 'error')->count();
                $a->undetermined = $items->where('result', 'undetermined')->count();
                $a->scope_count = app(\App\Services\Hr\Kpi\PayrollAudits::class)->scopeLines($a)->count();

                return $a;
            });
    }

    /** Tên phòng ban chụp trên dòng lương; trống => nhóm "Chưa gán phòng ban". */
    public static function departmentOf(object $line): string
    {
        return trim((string) $line->department_name) ?: 'Chưa gán phòng ban';
    }

    private function month(?string $value): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value) ? (string) $value : now()->format('Y-m');
    }
}
