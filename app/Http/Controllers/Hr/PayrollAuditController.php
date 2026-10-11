<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Kpi\HcnsKpiRules;
use App\Services\Hr\Kpi\HcnsSourceFiles;
use App\Services\Hr\Kpi\HcnsSourceReviews;
use App\Services\Hr\Kpi\PayrollAudits;
use App\Services\Hr\Kpi\Sources\PayrollAccuracySource;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Support\EgoCompanyLock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Đối soát độ chính xác bảng lương HR — KPI HC04, ngay trong trang Bảng lương (không thêm menu).
 *
 * Người nhập (mặc định người lập đợt) ghi từng dòng đã kiểm: khoản đã kiểm, kết quả đúng / có lỗi / chưa xác định;
 * lỗi có khoản, loại, nội dung, nguồn, chứng cứ, cách xử lý. Lưu nháp → gửi kiểm → người kiểm độc lập (khác HCNS
 * chịu KPI, khác người nhập/người gửi) xác nhận hoặc trả bổ sung. Không đụng số tiền của bảng lương.
 */
class PayrollAuditController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(PayrollAudits::ready(), 404);
        abort_unless(PayrollAccess::canManage($request->user()), 403, 'Chỉ HR, Kế toán, Ban Giám đốc hoặc Admin được lập đợt đối soát lương.');
        $data = $request->validate([
            'period_id' => ['required', 'integer', 'exists:hr_payroll_periods,id'],
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['required', Rule::in(array_keys(PayrollAudits::SCOPES))],
            'scope_department' => ['nullable', 'required_if:scope,department', 'string', 'max:190'],
            'line_ids' => ['nullable', 'required_if:scope,lines', 'array'],
            'line_ids.*' => ['integer'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'checker_id' => ['nullable', 'integer', 'exists:users,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', 'different:owner_id'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'reviewer_id.different' => 'Người kiểm phải khác HCNS chịu trách nhiệm.',
            'scope_department.required_if' => 'Chọn phòng ban cần đối soát.',
            'line_ids.required_if' => 'Chọn ít nhất một dòng lương.',
        ]);
        $data['checker_id'] = (int) ($data['checker_id'] ?? $request->user()->id);
        if ((int) $data['reviewer_id'] === $data['checker_id']) {
            throw ValidationException::withMessages(['reviewer_id' => 'Người kiểm phải khác người nhập kết quả đối soát.']);
        }
        $period = DB::table('hr_payroll_periods')->where('id', (int) $data['period_id'])->first();
        $draft = (object) ['payroll_month' => $period->payroll_month];
        if ($locked = PayrollAudits::lockedMonth($draft)) {
            return back()->withInput()->with('error', PayrollAudits::lockMessage($locked));
        }
        $lineIds = $data['scope'] === 'lines'
            ? DB::table('hr_payroll_lines')->where('period_id', $period->id)->whereIn('id', array_map('intval', (array) $data['line_ids']))->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()
            : null;
        if ($data['scope'] === 'lines' && $lineIds === []) {
            throw ValidationException::withMessages(['line_ids' => 'Các dòng đã chọn không thuộc kỳ lương này.']);
        }
        $audit = (object) ['period_id' => $period->id, 'scope' => $data['scope'], 'scope_department' => $data['scope'] === 'department' ? trim((string) $data['scope_department']) : null,
            'scope_line_ids' => $lineIds ? json_encode($lineIds) : null];
        $count = app(PayrollAudits::class)->scopeLines($audit)->count();
        if ($count === 0) {
            return back()->withInput()->with('error', 'Phạm vi đã chọn không có dòng lương nào — không lập được đợt đối soát.');
        }

        $id = DB::transaction(function () use ($data, $period, $audit, $count) {
            $id = DB::table('hr_payroll_audits')->insertGetId([
                'company_id' => EgoCompanyLock::id(), 'code' => PayrollAudits::nextCode(), 'period_id' => $period->id, 'payroll_month' => $period->payroll_month,
                'title' => trim($data['title']), 'scope' => $audit->scope, 'scope_department' => $audit->scope_department, 'scope_line_ids' => $audit->scope_line_ids,
                'owner_id' => (int) $data['owner_id'], 'checker_id' => $data['checker_id'], 'reviewer_id' => (int) $data['reviewer_id'],
                'status' => 'open', 'note' => $data['note'] ?? null, 'created_by' => Auth::id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            app(PayrollAudits::class)->log($id, 'created', 'Lập đợt đối soát kỳ lương '.$period->payroll_month.' · '.PayrollAudits::SCOPES[$audit->scope]
                .($audit->scope_department ? ' “'.$audit->scope_department.'”' : '').' · '.$count.' dòng', (int) Auth::id());

            return $id;
        });

        return redirect()->route('hr.payroll.audits.show', $id)->with('success', 'Đã lập đợt đối soát. Người nhập ghi kết quả từng dòng, thêm lỗi (nếu có) rồi gửi kiểm.');
    }

    public function show(Request $request, $id)
    {
        abort_unless(PayrollAudits::ready(), 404);
        abort_unless(PayrollAccess::canManage($request->user()), 403, 'Chỉ HR, Kế toán, Ban Giám đốc hoặc Admin được xem đối soát lương.');
        $audit = $this->find((int) $id);
        $svc = app(PayrollAudits::class);
        $user = $request->user();
        $period = DB::table('hr_payroll_periods')->where('id', $audit->period_id)->first();
        $kpiMonth = PayrollAudits::kpiMonth((string) $audit->payroll_month, app(HcnsKpiRules::class)->forMonth(
            Carbon::createFromFormat('Y-m-d', $audit->payroll_month.'-01')->addMonthNoOverflow()->format('Y-m'))['rules']);
        $rule = app(HcnsKpiRules::class)->forMonth($kpiMonth);
        $rows = app(PayrollAccuracySource::class)->rowsForAudit((int) $audit->id, $rule['rules']);
        $summary = app(PayrollAccuracySource::class)->summarize($rows, $kpiMonth, now(), $rule['rules']);
        $review = $this->review((int) $audit->id);
        $errors = $svc->errors((int) $audit->id);
        $files = app(HcnsSourceFiles::class);
        $names = DB::table('users')->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $items = $svc->items((int) $audit->id)->keyBy('line_id');
        $scopeLines = $svc->scopeLines($audit);
        // Dòng đã kiểm nhưng không còn trong phạm vi (bảng lương tính lại xóa dòng) vẫn hiện để giữ lịch sử.
        $goneItems = $items->filter(fn ($i, $lineId) => ! $scopeLines->contains('id', (int) $lineId));

        return view('hr.payroll.audit-show', [
            'audit' => $audit,
            'period' => $period,
            'kpiMonth' => $kpiMonth,
            'rule' => $rule,
            'rowsByItem' => collect($rows)->filter(fn ($r) => ($r['source_id'] ?? 0) > 0)->keyBy('source_id'),
            'summary' => $summary,
            'panelRow' => $this->panelRow($audit, $review, $summary, $rows),
            'scopeLines' => $scopeLines,
            'goneItems' => $goneItems,
            'items' => $items,
            'lineNow' => DB::table('hr_payroll_lines')->whereIn('id', $items->keys()->filter()->all() ?: [0])->get()->keyBy('id'),
            'auditErrors' => $errors->groupBy('item_id'),
            'errorFiles' => $errors->mapWithKeys(fn ($e) => [$e->id => $files->list(PayrollAudits::ERROR_FILE_TYPE, (int) $e->id)]),
            'files' => $files->list(PayrollAudits::SOURCE_TYPE, (int) $audit->id),
            'review' => $review,
            'events' => $review ? app(HcnsSourceReviews::class)->events((int) $review->id) : collect(),
            'logs' => $svc->logs((int) $audit->id),
            'missing' => $audit->status === 'cancelled' ? [] : $svc->missing($audit),
            'names' => $names,
            'locked' => PayrollAudits::lockedMonth($audit),
            'canEdit' => $this->canEdit($audit, $user),
            'canReview' => $this->canReview($audit, $user),
            'users' => DB::table('users')->where(fn ($q) => $q->where('is_active', 1)->orWhereNull('is_active'))->orderBy('name')->get(['id', 'name']),
            'departments' => DB::table('hr_payroll_lines')->where('period_id', $audit->period_id)->get(['department_name'])
                ->map(fn ($l) => PayrollController::departmentOf($l))->unique()->sort()->values(),
            'components' => PayrollAudits::components(),
            'results' => (array) config('hcns_kpi.payroll_item_results', []),
            'errorTypes' => (array) config('hcns_kpi.payroll_error_types', []),
            'resolutions' => (array) config('hcns_kpi.payroll_error_resolutions', []),
            'checks' => (array) config('hcns_kpi.payroll_audit_checks', []),
        ]);
    }

    /** Sửa đợt: tên, người, phạm vi. Kỳ lương cố định sau khi lập. */
    public function update(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'checker_id' => ['required', 'integer', 'exists:users,id'],
            'reviewer_id' => ['required', 'integer', 'exists:users,id', 'different:owner_id', 'different:checker_id'],
            'scope' => ['required', Rule::in(array_keys(PayrollAudits::SCOPES))],
            'scope_department' => ['nullable', 'required_if:scope,department', 'string', 'max:190'],
            'line_ids' => ['nullable', 'required_if:scope,lines', 'array'],
            'line_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['reviewer_id.different' => 'Người kiểm phải khác HCNS chịu trách nhiệm và người nhập.']);
        $lineIds = $data['scope'] === 'lines'
            ? DB::table('hr_payroll_lines')->where('period_id', $audit->period_id)->whereIn('id', array_map('intval', (array) $data['line_ids']))->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all()
            : null;
        $values = ['title' => trim($data['title']), 'owner_id' => (int) $data['owner_id'], 'checker_id' => (int) $data['checker_id'], 'reviewer_id' => (int) $data['reviewer_id'],
            'scope' => $data['scope'], 'scope_department' => $data['scope'] === 'department' ? trim((string) $data['scope_department']) : null,
            'scope_line_ids' => $lineIds ? json_encode($lineIds) : null, 'note' => $data['note'] ?? null];
        if ($data['scope'] === 'lines' && ! $lineIds) {
            throw ValidationException::withMessages(['line_ids' => 'Các dòng đã chọn không thuộc kỳ lương này.']);
        }
        $names = DB::table('users')->pluck('name', 'id')->all();
        $labels = ['title' => 'tên', 'owner_id' => 'HCNS chịu trách nhiệm', 'checker_id' => 'người nhập', 'reviewer_id' => 'người kiểm', 'scope' => 'phạm vi',
            'scope_department' => 'phòng ban', 'scope_line_ids' => 'các dòng chọn', 'note' => 'ghi chú'];
        $show = fn ($field, $v) => str_ends_with($field, '_id') && $v ? ($names[(int) $v] ?? '#'.$v) : ((string) $v ?: '—');
        $changes = [];
        foreach ($values as $field => $value) {
            if ((string) ($audit->{$field} ?? '') !== (string) ($value ?? '')) {
                $changes[$field] = $labels[$field].': '.$show($field, $audit->{$field}).' → '.$show($field, $value);
            }
        }
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }
        DB::table('hr_payroll_audits')->where('id', $audit->id)->update($values + ['updated_at' => now()]);
        app(PayrollAudits::class)->log((int) $audit->id, 'plan_updated', implode('; ', $changes), (int) $user->id);
        $this->invalidate((int) $audit->id, 'Sửa '.implode(', ', array_map(fn ($k) => $labels[$k], array_keys($changes))));

        return back()->with('success', 'Đã lưu đợt đối soát. Nếu đã gửi/kiểm tra mà đổi người/phạm vi, cần gửi lại.');
    }

    /** Ghi kết quả nhiều dòng: kết quả, khoản đã kiểm, ghi chú; “kiểm lại” chụp lại số tiền hiện tại của dòng. */
    public function saveItems(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $componentKeys = array_keys(PayrollAudits::components());
        $data = $request->validate([
            'result' => ['array'],
            'result.*' => ['nullable', Rule::in(array_keys((array) config('hcns_kpi.payroll_item_results', [])))],
            'components' => ['array'],
            'components.*' => ['array'],
            'components.*.*' => [Rule::in($componentKeys)],
            'item_note' => ['array'],
            'item_note.*' => ['nullable', 'string', 'max:1000'],
            'recheck' => ['array'],
        ]);
        $svc = app(PayrollAudits::class);
        $lines = $svc->scopeLines($audit)->keyBy('id');
        $items = $svc->items((int) $audit->id)->keyBy('line_id');
        $errors = DB::table('hr_payroll_audit_errors')->where('audit_id', $audit->id)->get()->groupBy('item_id');
        $changed = [];
        DB::transaction(function () use ($data, $lines, $items, $errors, $audit, $user, &$changed, $componentKeys) {
            foreach ((array) ($data['result'] ?? []) as $lineId => $result) {
                $lineId = (int) $lineId;
                $line = $lines->get($lineId);
                $item = $items->get($lineId);
                if (! $line) {
                    throw ValidationException::withMessages(['result' => 'Dòng #'.$lineId.' không thuộc phạm vi đợt đối soát.']);
                }
                $result = $result ?: null;
                $components = array_values(array_intersect($componentKeys, (array) ($data['components'][$lineId] ?? ($item ? (json_decode((string) $item->components, true) ?: []) : []))));
                $note = array_key_exists($lineId, (array) ($data['item_note'] ?? [])) ? (trim((string) $data['item_note'][$lineId]) ?: null) : ($item->note ?? null);
                $itemErrors = $item ? $errors->get($item->id, collect()) : collect();
                $active = $itemErrors->whereNull('removed_at');
                if ($result === null) {
                    if ($item && $itemErrors->isNotEmpty()) {
                        throw ValidationException::withMessages(['result' => 'Dòng “'.$line->employee_name.'” đã có lỗi ghi nhận (kể cả đã gỡ) — không bỏ kết quả được; chọn “Chưa xác định” nếu chưa kết luận.']);
                    }
                    if ($item) {
                        DB::table('hr_payroll_audit_items')->where('id', $item->id)->delete();
                        $changed[] = $line->employee_name.': bỏ kết quả';
                    }

                    continue;
                }
                if (in_array($result, ['ok', 'error'], true) && $components === []) {
                    throw ValidationException::withMessages(['components' => 'Chọn khoản đã kiểm cho dòng “'.$line->employee_name.'”.']);
                }
                if ($result === 'ok' && $active->isNotEmpty()) {
                    throw ValidationException::withMessages(['result' => 'Dòng “'.$line->employee_name.'” còn '.$active->count().' lỗi — gỡ lỗi (có lý do) hoặc chọn “Có lỗi”.']);
                }
                $outside = $active->pluck('component')->diff($components);
                if ($outside->isNotEmpty() && $result !== 'undetermined') {
                    throw ValidationException::withMessages(['components' => 'Dòng “'.$line->employee_name.'” có lỗi ở khoản chưa đánh dấu đã kiểm.']);
                }
                $recheck = ! empty($data['recheck'][$lineId]);
                $same = $item && $item->result === $result && (json_decode((string) $item->components, true) ?: []) === $components && $item->note === $note;
                if ($same && ! $recheck) {
                    continue;
                }
                $values = ['result' => $result, 'components' => json_encode($components), 'note' => $note, 'user_id' => $line->user_id,
                    'employee_name' => $line->employee_name, 'department_name' => $line->department_name,
                    'line_fingerprint' => PayrollAudits::lineFingerprint($line), 'line_snapshot' => json_encode(PayrollAudits::lineSnapshot($line)),
                    'checked_by' => $user->id, 'checked_at' => now(), 'updated_at' => now()];
                if ($item) {
                    DB::table('hr_payroll_audit_items')->where('id', $item->id)->update($values);
                } else {
                    DB::table('hr_payroll_audit_items')->insert($values + ['audit_id' => $audit->id, 'line_id' => $lineId, 'created_at' => now()]);
                }
                $changed[] = $line->employee_name.': '.((array) config('hcns_kpi.payroll_item_results'))[$result].' · '.count($components).' khoản'.($recheck ? ' (kiểm lại số mới)' : '');
            }
        });
        if ($changed === []) {
            return back()->with('success', 'Không có thay đổi kết quả.');
        }
        $svc->log((int) $audit->id, 'items_saved', implode('; ', $changed), (int) $user->id);
        $this->invalidate((int) $audit->id, 'Sửa kết quả đối soát');

        return back()->with('success', 'Đã lưu kết quả '.count($changed).' dòng.');
    }

    public function errorStore(Request $request, $id, $itemId)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $item = DB::table('hr_payroll_audit_items')->where('id', (int) $itemId)->where('audit_id', $audit->id)->first();
        abort_unless($item, 404);
        $data = $this->validateError($request, $item);
        $request->validate(['files' => ['nullable', 'array', 'max:10'], 'files.*' => ['file', 'mimes:'.HcnsSourceFiles::MIMES, 'max:'.HcnsSourceFiles::MAX_KB]]);
        DB::transaction(function () use ($data, $item, $audit, $user, $request) {
            $errorId = DB::table('hr_payroll_audit_errors')->insertGetId($data + [
                'audit_id' => $audit->id, 'item_id' => $item->id, 'created_by' => $user->id,
                'fixed_at' => $data['resolution_status'] === 'fixed' ? now() : null, 'fixed_by' => $data['resolution_status'] === 'fixed' ? $user->id : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $count = app(HcnsSourceFiles::class)->store(PayrollAudits::ERROR_FILE_TYPE, $errorId, (array) $request->file('files', []), (int) $user->id);
            if ($item->result !== 'error') {
                DB::table('hr_payroll_audit_items')->where('id', $item->id)->update(['result' => 'error', 'updated_at' => now()]);
            }
            app(PayrollAudits::class)->log((int) $audit->id, 'error_added', $item->employee_name.' · '.$this->errorText($data).($count ? ' · '.$count.' tệp chứng cứ' : ''), (int) $user->id);
        });
        $this->invalidate((int) $audit->id, 'Thêm lỗi');

        return redirect()->to(route('hr.payroll.audits.show', $audit->id).'#pa-line-'.$item->line_id)->with('success', 'Đã ghi lỗi cho '.$item->employee_name.'.');
    }

    public function errorUpdate(Request $request, $id, $errorId)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $error = $this->error($audit, (int) $errorId);
        abort_if($error->removed_at, 422, 'Lỗi đã gỡ — chỉ xem lịch sử.');
        $item = DB::table('hr_payroll_audit_items')->where('id', $error->item_id)->first();
        $data = $this->validateError($request, $item);
        $changes = [];
        foreach ($data as $field => $value) {
            if ((string) ($error->{$field} ?? '') !== (string) ($value ?? '') && ! (is_numeric($error->{$field}) && is_numeric($value) && (float) $error->{$field} === (float) $value)) {
                $changes[] = $field.': '.((string) $error->{$field} ?: '—').' → '.((string) $value ?: '—');
            }
        }
        if ($changes === []) {
            return back()->with('success', 'Không có thay đổi.');
        }
        $fixed = $data['resolution_status'] === 'fixed';
        DB::table('hr_payroll_audit_errors')->where('id', $error->id)->update($data + [
            'fixed_at' => $fixed ? ($error->resolution_status === 'fixed' ? $error->fixed_at : now()) : null,
            'fixed_by' => $fixed ? ($error->resolution_status === 'fixed' ? $error->fixed_by : $user->id) : null,
            'updated_at' => now(),
        ]);
        app(PayrollAudits::class)->log((int) $audit->id, 'error_updated', $item->employee_name.' · lỗi #'.$error->id.' · '.implode('; ', $changes), (int) $user->id);
        $this->invalidate((int) $audit->id, 'Sửa lỗi');

        return redirect()->to(route('hr.payroll.audits.show', $audit->id).'#pa-line-'.$item->line_id)->with('success', 'Đã cập nhật lỗi.');
    }

    /** Gỡ lỗi (ghi nhầm): chỉ đánh dấu kèm lý do, lịch sử giữ nguyên. */
    public function errorRemove(Request $request, $id, $errorId)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $error = $this->error($audit, (int) $errorId);
        abort_if($error->removed_at, 422, 'Lỗi đã gỡ.');
        $data = $request->validate(['remove_reason' => ['required', 'string', 'min:5', 'max:2000']], ['remove_reason.required' => 'Ghi lý do gỡ lỗi.']);
        DB::table('hr_payroll_audit_errors')->where('id', $error->id)->update([
            'removed_at' => now(), 'removed_by' => $user->id, 'remove_reason' => trim($data['remove_reason']), 'updated_at' => now(),
        ]);
        $item = DB::table('hr_payroll_audit_items')->where('id', $error->item_id)->first();
        app(PayrollAudits::class)->log((int) $audit->id, 'error_removed', $item->employee_name.' · lỗi #'.$error->id.' — '.trim($data['remove_reason']), (int) $user->id);
        $this->invalidate((int) $audit->id, 'Gỡ lỗi');

        return redirect()->to(route('hr.payroll.audits.show', $audit->id).'#pa-line-'.$item->line_id)->with('success', 'Đã gỡ lỗi (giữ trong lịch sử). Kiểm tra lại kết quả của dòng.');
    }

    public function errorFileUpload(Request $request, $id, $errorId)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $error = $this->error($audit, (int) $errorId);
        abort_if($error->removed_at, 422, 'Lỗi đã gỡ — không thêm tệp.');
        $request->validate(['files' => ['required', 'array', 'max:10'], 'files.*' => ['file', 'mimes:'.HcnsSourceFiles::MIMES, 'max:'.HcnsSourceFiles::MAX_KB]],
            ['files.required' => 'Chọn ít nhất một tệp.']);
        $count = app(HcnsSourceFiles::class)->store(PayrollAudits::ERROR_FILE_TYPE, (int) $error->id, (array) $request->file('files', []), (int) $user->id);
        app(PayrollAudits::class)->log((int) $audit->id, 'file_added', 'Thêm '.$count.' tệp chứng cứ cho lỗi #'.$error->id, (int) $user->id);
        $this->invalidate((int) $audit->id, 'Thêm chứng cứ lỗi');

        return back()->with('success', 'Đã tải '.$count.' tệp chứng cứ.');
    }

    public function fileUpload(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $request->validate(['files' => ['required', 'array', 'max:10'], 'files.*' => ['file', 'mimes:'.HcnsSourceFiles::MIMES, 'max:'.HcnsSourceFiles::MAX_KB]],
            ['files.required' => 'Chọn ít nhất một tệp.']);
        $count = app(HcnsSourceFiles::class)->store(PayrollAudits::SOURCE_TYPE, (int) $audit->id, (array) $request->file('files', []), (int) $user->id);
        app(PayrollAudits::class)->log((int) $audit->id, 'file_added', 'Thêm '.$count.' tệp bảng đối chiếu/biên bản', (int) $user->id);
        $this->invalidate((int) $audit->id, 'Thêm tệp đối soát');

        return back()->with('success', 'Đã tải '.$count.' tệp.');
    }

    /** Xem/tải tệp của đợt hoặc của một lỗi thuộc đợt — chỉ người có quyền bảng lương. */
    public function file(Request $request, $id, $fileId)
    {
        abort_unless(PayrollAudits::ready(), 404);
        abort_unless(PayrollAccess::canManage($request->user()), 403, 'Chỉ HR, Kế toán, Ban Giám đốc hoặc Admin được xem tệp đối soát lương.');
        $audit = $this->find((int) $id);
        $file = $this->auditFile($audit, (int) $fileId);

        return app(HcnsSourceFiles::class)->respond($file, $request->boolean('download'));
    }

    public function fileDelete($id, $fileId)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $file = $this->auditFile($audit, (int) $fileId);
        app(HcnsSourceFiles::class)->delete($file);
        app(PayrollAudits::class)->log((int) $audit->id, 'file_deleted', 'Xóa tệp '.$file->original_name.($file->source_type === PayrollAudits::ERROR_FILE_TYPE ? ' (lỗi #'.$file->source_id.')' : ''), (int) $user->id);
        $this->invalidate((int) $audit->id, 'Xóa tệp');

        return back()->with('success', 'Đã xóa tệp.');
    }

    public function kpiDraft(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $svc = app(PayrollAudits::class);
        app(HcnsSourceReviews::class)->saveDraft(PayrollAudits::SOURCE_TYPE, (int) $audit->id, PayrollAccuracySource::CODE,
            (int) $audit->owner_id, (int) $audit->reviewer_id, $svc->declared($audit), $svc->fingerprint($audit), (int) $user->id, $this->note($request));

        return back()->with('success', 'Đã lưu nháp. Chưa gửi người kiểm, chưa tính KPI.');
    }

    public function kpiSubmit(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $svc = app(PayrollAudits::class);
        if (($missing = $svc->missing($audit)) !== []) {
            return back()->with('error', 'Chưa gửi kiểm được — thiếu: '.implode('; ', $missing).'.');
        }
        DB::transaction(function () use ($audit, $user, $svc, $request) {
            app(HcnsSourceReviews::class)->submit(PayrollAudits::SOURCE_TYPE, (int) $audit->id, PayrollAccuracySource::CODE,
                (int) $audit->owner_id, (int) $audit->reviewer_id, $svc->declared($audit), $svc->fingerprint($audit), (int) $user->id, $this->note($request));
            $svc->log((int) $audit->id, 'submitted', 'Gửi kiểm đợt đối soát', (int) $user->id);
        });

        return back()->with('success', 'Đã gửi kiểm. Chờ người kiểm độc lập xác nhận.');
    }

    public function kpiConfirm(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'reviewer');
        $checkKeys = array_keys((array) config('hcns_kpi.payroll_audit_checks', []));
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
            throw ValidationException::withMessages(['note' => 'Ghi rõ nội dung chưa đúng khi có mục không đạt.']);
        }
        $review = $this->review((int) $audit->id);
        abort_unless($review, 404);
        // Độc lập: không phải người nhập, không phải người đã gửi (HcnsSourceReviews chặn thêm HCNS chịu KPI).
        if ((int) $review->submitted_by === (int) $user->id) {
            abort(403, 'Người gửi kiểm không tự xác nhận đợt đối soát.');
        }
        app(HcnsSourceReviews::class)->confirm($review, $checks, app(PayrollAudits::class)->fingerprint($audit), (int) $user->id, $data['note'] ?? null);

        return back()->with('success', 'Đã xác nhận kết quả đối soát.');
    }

    public function kpiReturn(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'reviewer');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']], ['reason.required' => 'Ghi lý do trả bổ sung.']);
        $review = $this->review((int) $audit->id);
        abort_unless($review, 404);
        app(HcnsSourceReviews::class)->returnForFix($review, trim($data['reason']), (int) $user->id);

        return back()->with('success', 'Đã trả bổ sung cho người nhập.');
    }

    /** Hủy đợt khi chưa gửi kiểm (có lý do). Đợt đã hủy không vào KPI, lịch sử giữ nguyên. */
    public function cancel(Request $request, $id)
    {
        [$audit, $user] = $this->actor((int) $id, 'editor');
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:5', 'max:2000']], ['cancel_reason.required' => 'Ghi lý do hủy.']);
        $review = $this->review((int) $audit->id);
        if ($review && in_array($review->status, ['submitted', 'confirmed'], true)) {
            return back()->with('error', 'Đợt đã gửi kiểm/đã kiểm tra — không hủy được. Người kiểm trả bổ sung nếu cần sửa.');
        }
        DB::table('hr_payroll_audits')->where('id', $audit->id)->update([
            'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancel_reason' => trim($data['cancel_reason']), 'updated_at' => now(),
        ]);
        app(PayrollAudits::class)->log((int) $audit->id, 'cancelled', trim($data['cancel_reason']), (int) $user->id);

        return back()->with('success', 'Đã hủy đợt đối soát.');
    }

    private function validateError(Request $request, object $item): array
    {
        $components = json_decode((string) $item->components, true) ?: [];
        $data = $request->validate([
            'component' => ['required', Rule::in($components)],
            'error_type' => ['required', Rule::in(array_keys((array) config('hcns_kpi.payroll_error_types', [])))],
            'description' => ['required', 'string', 'min:5', 'max:4000'],
            'expected_amount' => ['nullable', 'numeric', 'min:-10000000000', 'max:10000000000'],
            'recorded_amount' => ['nullable', 'numeric', 'min:-10000000000', 'max:10000000000'],
            'source_url' => ['nullable', 'string', 'max:500', 'regex:/^(\/[^\s]*|https?:\/\/[^\s]+)$/'],
            'resolution' => ['nullable', 'string', 'max:4000'],
            'resolution_status' => ['required', Rule::in(array_keys((array) config('hcns_kpi.payroll_error_resolutions', [])))],
        ], [
            'component.in' => 'Chọn khoản có lỗi trong các khoản đã đánh dấu kiểm của dòng này.',
            'description.required' => 'Ghi nội dung lỗi.',
            'source_url.regex' => 'Liên kết nguồn phải là đường dẫn trong hệ thống (bắt đầu bằng /) hoặc http(s)://.',
        ]);
        if (in_array($data['resolution_status'], ['fixed', 'next_period', 'no_change'], true) && trim((string) ($data['resolution'] ?? '')) === '') {
            throw ValidationException::withMessages(['resolution' => 'Ghi cách xử lý khi đã sửa / điều chỉnh kỳ sau / không sửa.']);
        }

        return [
            'component' => $data['component'], 'error_type' => $data['error_type'], 'description' => trim($data['description']),
            'expected_amount' => $data['expected_amount'] ?? null, 'recorded_amount' => $data['recorded_amount'] ?? null,
            'source_url' => isset($data['source_url']) ? trim($data['source_url']) : null, 'resolution' => isset($data['resolution']) ? (trim($data['resolution']) ?: null) : null,
            'resolution_status' => $data['resolution_status'],
        ];
    }

    private function errorText(array $data): string
    {
        return (PayrollAudits::components()[$data['component']]['label'] ?? $data['component']).' · '
            .(((array) config('hcns_kpi.payroll_error_types'))[$data['error_type']] ?? $data['error_type']).' · '.mb_strimwidth($data['description'], 0, 120, '…');
    }

    private function panelRow(object $audit, ?object $review, array $summary, array $rows): array
    {
        $names = DB::table('users')->whereIn('id', array_filter([$review->confirmed_by ?? null]))->pluck('name', 'id');
        $status = $review->status ?? 'none';
        [$state, $reason] = match (true) {
            $audit->status === 'cancelled' => ['excluded', 'Đợt đã hủy — không vào KPI.'],
            $status === 'confirmed' && ($review->result ?? null) === 'pass' => ['counted', 'Đã xác nhận. '.$summary['measure_text'].'.'],
            $status === 'confirmed' => ['pending', 'Người kiểm ghi không đạt — sửa và gửi lại.'],
            $status === 'submitted' => ['pending', 'Đã gửi kiểm — chờ người kiểm độc lập xác nhận.'],
            $status === 'returned' => ['pending', 'Trả bổ sung — sửa rồi gửi lại.'],
            $status === 'changed' => ['pending', 'Đã sửa sau khi gửi — cần gửi lại.'],
            default => ['missing', 'Chưa gửi kiểm — chưa tính.'],
        };
        $missingRows = collect($rows)->where('kpi_state', 'missing')->count();
        if ($state === 'counted' && $missingRows > 0) {
            $reason .= ' Còn '.$missingRows.' phần thiếu (chưa kiểm/chưa xác định/dòng đổi sau kiểm) — số đo chưa đủ.';
        }

        return ['kpi_state' => $state, 'reason' => $reason, 'state_label' => $state === 'counted' ? 'Đã xác nhận — '.$summary['numerator'].'/'.$summary['denominator'].' phần đúng' : null,
            'confirmed_by_name' => ! empty($review->confirmed_by) ? ($names[(int) $review->confirmed_by] ?? '—') : null];
    }

    private function find(int $id): object
    {
        $audit = DB::table('hr_payroll_audits')->where('id', $id)->first();
        abort_unless($audit && PayrollAudits::inCompany($audit), 404);

        return $audit;
    }

    private function error(object $audit, int $errorId): object
    {
        $error = DB::table('hr_payroll_audit_errors')->where('id', $errorId)->where('audit_id', $audit->id)->first();
        abort_unless($error, 404);

        return $error;
    }

    private function auditFile(object $audit, int $fileId): object
    {
        $file = DB::table('hr_kpi_source_files')->where('id', $fileId)->first();
        $ok = $file && (
            ($file->source_type === PayrollAudits::SOURCE_TYPE && (int) $file->source_id === (int) $audit->id)
            || ($file->source_type === PayrollAudits::ERROR_FILE_TYPE && DB::table('hr_payroll_audit_errors')->where('id', $file->source_id)->where('audit_id', $audit->id)->exists())
        );
        abort_unless($ok, 404);

        return $file;
    }

    /** @return array{0: object, 1: \App\Models\User} */
    private function actor(int $id, string $role): array
    {
        abort_unless(PayrollAudits::ready(), 404);
        $user = Auth::user();
        abort_unless(PayrollAccess::canManage($user), 403, 'Chỉ HR, Kế toán, Ban Giám đốc hoặc Admin được thao tác đối soát lương.');
        $audit = $this->find($id);
        if ($locked = PayrollAudits::lockedMonth($audit)) {
            throw ValidationException::withMessages(['kpi' => PayrollAudits::lockMessage($locked)]);
        }
        if ($audit->status === 'cancelled') {
            throw ValidationException::withMessages(['kpi' => 'Đợt đối soát đã hủy — chỉ xem.']);
        }
        match ($role) {
            'editor' => abort_unless($this->canEdit($audit, $user), 403, 'Chỉ người nhập của đợt hoặc Ban Giám đốc/Admin được ghi/sửa đối soát.'),
            'reviewer' => abort_unless($this->canReview($audit, $user), 403, 'Bạn không phải người kiểm độc lập của đợt này.'),
        };

        return [$audit, $user];
    }

    private function canEdit(object $audit, $user): bool
    {
        return PayrollAccess::canManage($user) && ((int) $audit->checker_id === (int) $user->id || PayrollAccess::canApprove($user));
    }

    /** Người kiểm độc lập: khác HCNS chịu KPI và khác người nhập; là người kiểm được chỉ định hoặc Ban Giám đốc/Admin. */
    private function canReview(object $audit, $user): bool
    {
        if (! PayrollAccess::canManage($user) || in_array((int) $user->id, [(int) $audit->owner_id, (int) $audit->checker_id], true)) {
            return false;
        }

        return (int) ($audit->reviewer_id ?? 0) === (int) $user->id || PayrollAccess::canApprove($user);
    }

    private function review(int $id): ?object
    {
        return app(HcnsSourceReviews::class)->find(PayrollAudits::SOURCE_TYPE, $id, PayrollAccuracySource::CODE);
    }

    private function note(Request $request): ?string
    {
        $note = trim((string) $request->input('note', ''));

        return $note === '' ? null : mb_substr($note, 0, 2000);
    }

    private function invalidate(int $id, string $what): void
    {
        $audit = DB::table('hr_payroll_audits')->where('id', $id)->first();
        $before = $this->review($id);
        $after = app(HcnsSourceReviews::class)->invalidateIfChanged(PayrollAudits::SOURCE_TYPE, $id, PayrollAccuracySource::CODE,
            app(PayrollAudits::class)->fingerprint($audit), (int) Auth::id(), $what);
        if ($before && $after && $before->status !== $after->status) {
            app(PayrollAudits::class)->log($id, 'review_invalidated', $what.' sau khi đã gửi/kiểm tra — cần gửi lại.', (int) Auth::id());
        }
    }
}
