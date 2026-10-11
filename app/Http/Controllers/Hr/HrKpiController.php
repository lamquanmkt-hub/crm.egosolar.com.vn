<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Kpi\HcnsKpiChecklist;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Services\Hr\OfficeIncidents;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Services\Hr\Payroll\PayrollGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * KPI văn phòng theo vị trí (HCNS, Kế toán kho):
 * - Chấm KPI tháng: Ban Giám đốc / Admin nhập Thực tế hoặc % đạt từng tiêu chí (không tự chấm cho mình).
 * - Cài đặt KPI: tiêu chí, trọng số, chỉ tiêu, bậc quy đổi lương KPI.
 * Nhân viên được chấm theo bộ KPI gán trong Hồ sơ lương. KPI Kỹ sư dùng trang Nhập số liệu KPI kỹ thuật.
 */
final class HrKpiController extends Controller
{
    public function index(Request $request, HrKpiEvaluator $evaluator): View
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403, 'Bạn không có quyền xem KPI nhân sự.');

        if ($request->query('tab') === 'settings') {
            return view('hr.kpi.index', $this->settingsData($request, $evaluator) + [
                'tab' => 'settings',
                'month' => $this->month($request->query('month')),
            ]);
        }

        $month = $this->month($request->query('month'));
        $templates = DB::table('hr_kpi_templates')->where('is_active', true)->orderBy('id')->get();
        $code = (string) $request->query('template', (string) optional($templates->first())->code);
        // Bộ KPI hiệu lực của kỳ: HCNS có phiên bản đã duyệt thì dùng trọng số/chỉ tiêu/bậc của phiên bản.
        $effective = $evaluator->effective($code, $month);
        $template = $effective['template'];
        abort_unless($template, 404, 'Không tìm thấy bộ KPI.');

        $criteria = $effective['criteria'];
        $profiles = PayrollGenerator::profilesFor($month)->filter(fn ($p) => $p->kpi_template === $code);
        $employeeOptions = DB::table('users')->whereIn('id', $profiles->keys()->all() ?: [0])->orderBy('name')->get(['id', 'name']);
        // Lọc một nhân viên (mở thẳng KPI + checklist của người đó); không có trong bộ KPI kỳ này thì hiện tất cả.
        $employeeId = (int) $request->query('employee', 0);
        $employees = $employeeId && $employeeOptions->contains('id', $employeeId) ? $employeeOptions->where('id', $employeeId)->values() : $employeeOptions;
        $scores = $evaluator->scores($employees->pluck('id')->all(), $month);

        // Checklist nguồn HCNS (HC09, HC07, HC06…): chỉ đọc, không đổi điểm chính thức khi quy tắc còn là bản nháp.
        $withChecklist = $template->code === \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE && HcnsKpiChecklist::available();
        $rows = $employees->map(function ($e) use ($evaluator, $template, $criteria, $scores, $withChecklist, $month, $request) {
            // Kỳ đã chốt lương: hiển thị đúng ảnh chụp lúc duyệt (không tính lại từ nguồn hiện tại).
            if ($snapshot = \App\Services\Hr\Kpi\HcnsKpiSnapshots::current((int) $e->id, $month, (string) $template->code)) {
                $restored = \App\Services\Hr\Kpi\HcnsKpiSnapshots::restore($snapshot);

                return ['employee' => $e, 'result' => $restored['result'], 'summary' => $restored['summary'],
                    'checklist' => $restored['checklist'] ? app(HcnsKpiChecklist::class)->forViewer($restored['checklist'], $request->user()) : null,
                    'snapshot' => $restored];
            }
            $userScores = $scores->get($e->id, collect());
            $result = $evaluator->score($template, $criteria, $userScores, $evaluator->sourceOverrides($template, $criteria, (int) $e->id, $month, $userScores));

            return ['employee' => $e, 'result' => $result, 'summary' => $evaluator->fromResult($template, $result),
                'checklist' => $withChecklist ? app(HcnsKpiChecklist::class)->forUser((int) $e->id, $month, $template, $criteria, $userScores, $request->user()) : null,
                'snapshot' => null];
        });

        return view('hr.kpi.index', [
            'tab' => 'scores',
            'month' => $month,
            'templates' => $templates,
            'template' => $template,
            'criteria' => $criteria,
            'kpiVersion' => $effective['version'],
            'rows' => $rows,
            'employeeOptions' => $employeeOptions,
            'employeeId' => $employees->count() === 1 && $employeeId ? $employeeId : 0,
            'calcTypes' => HrKpiEvaluator::CALC_TYPES,
            'canEvaluate' => PayrollAccess::canEvaluateKpi($request->user()) && ! self::monthLocked($month),
            'monthLocked' => self::monthLocked($month),
            'autoData' => $this->autoData($template->code, $month),
            'technicalCount' => PayrollGenerator::profilesFor($month)->filter(fn ($p) => $p->kpi_template === HrKpiEvaluator::TECHNICAL)->count(),
        ]);
    }

    public function store(Request $request, HrKpiEvaluator $evaluator): RedirectResponse
    {
        abort_unless(PayrollAccess::canEvaluateKpi($request->user()), 403, 'Chỉ Ban Giám đốc hoặc Admin được chấm KPI.');

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'template' => ['required', 'string'],
            'scores' => ['nullable', 'array'],
            'scores.*' => ['array'],
            'scores.*.*.actual' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'scores.*.*.achievement' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'scores.*.*.na' => ['nullable', 'boolean'],
            'scores.*.*.note' => ['nullable', 'string', 'max:1000'],
        ], [
            'scores.*.*.actual.numeric' => 'Thực tế phải là số.',
            'scores.*.*.achievement.numeric' => '% đạt phải là số.',
            'scores.*.*.achievement.max' => '% đạt tối đa 200.',
        ]);

        $month = $validated['month'];
        $effective = $evaluator->effective($validated['template'], $month);
        $template = $effective['template'];
        abort_unless($template, 404);
        if (self::monthLocked($month)) {
            return back()->with('error', 'Bảng lương tháng '.$month.' đã duyệt nên điểm KPI tháng này đã khoá. Giám đốc mở khoá bảng lương (ghi lý do) nếu cần sửa.');
        }
        $criterionIds = $effective['criteria']->pluck('id')->map(fn ($id) => (int) $id)->all();
        // Tiêu chí lấy từ nguồn theo quy tắc đã duyệt: không nhận Thực tế/% đạt nhập tay (chỉ giữ “Không phát sinh” và ghi chú).
        $sourceCriterionIds = $template->code === \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE && $effective['version'] !== null
            ? $effective['criteria']->filter(fn ($c) => isset(HcnsKpiChecklist::SOURCES[$c->code]) && HcnsKpiChecklist::sourceOfficial((string) $c->code, (array) $effective['version']['rules']))->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];
        // HCNS: “Không phát sinh” phải có lý do (người/giờ ghi nhận lấy từ phiên đăng nhập) — không đánh N/A trống để loại tiêu chí.
        if ($template->code === \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE) {
            foreach ((array) ($validated['scores'] ?? []) as $userId => $byCriterion) {
                foreach ((array) $byCriterion as $criterionId => $v) {
                    if (! empty($v['na']) && trim((string) ($v['note'] ?? '')) === '') {
                        throw ValidationException::withMessages(["scores.$userId.$criterionId.note" => 'Ghi lý do khi đánh dấu “Không phát sinh”.']);
                    }
                }
            }
        }
        $allowedUsers = PayrollGenerator::profilesFor($month)->filter(fn ($p) => $p->kpi_template === $template->code)->keys()->map(fn ($id) => (int) $id)->all();

        foreach (array_keys((array) ($validated['scores'] ?? [])) as $userId) {
            if ((int) $userId === (int) $request->user()->id) {
                throw ValidationException::withMessages(['scores' => 'Không được tự chấm KPI cho chính mình.']);
            }
        }

        DB::transaction(function () use ($validated, $month, $criterionIds, $allowedUsers, $request, $sourceCriterionIds): void {
            foreach ((array) ($validated['scores'] ?? []) as $userId => $byCriterion) {
                if (! in_array((int) $userId, $allowedUsers, true)) {
                    continue;
                }
                foreach ((array) $byCriterion as $criterionId => $v) {
                    if (! in_array((int) $criterionId, $criterionIds, true)) {
                        continue;
                    }
                    $key = ['user_id' => (int) $userId, 'payroll_month' => $month, 'criterion_id' => (int) $criterionId];
                    $fromSource = in_array((int) $criterionId, $sourceCriterionIds, true);
                    $values = [
                        'actual_value' => ! $fromSource && ($v['actual'] ?? '') !== '' ? $v['actual'] : null,
                        'achievement' => ! $fromSource && ($v['achievement'] ?? '') !== '' ? $v['achievement'] : null,
                        'not_applicable' => ! empty($v['na']),
                        'note' => trim((string) ($v['note'] ?? '')) ?: null,
                    ];

                    if ($values['actual_value'] === null && $values['achievement'] === null && ! $values['not_applicable'] && $values['note'] === null) {
                        DB::table('hr_kpi_scores')->where($key)->delete();

                        continue;
                    }

                    $values += ['updated_by' => $request->user()->id, 'updated_at' => now()];
                    if (DB::table('hr_kpi_scores')->where($key)->exists()) {
                        DB::table('hr_kpi_scores')->where($key)->update($values);
                    } else {
                        DB::table('hr_kpi_scores')->insert($key + $values + ['created_at' => now()]);
                    }
                }
            }
        });

        return redirect()->route('hr.kpi.index', ['template' => $template->code, 'month' => $month])
            ->with('success', 'Đã lưu KPI tháng '.$month.'. Bấm "Tính lại" ở Bảng lương nếu bảng lương tháng này đã được tạo.');
    }

    public function settings(Request $request): RedirectResponse
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);

        return redirect()->route('hr.kpi.index', ['tab' => 'settings'] + $request->only(['template', 'month']));
    }

    private function settingsData(Request $request, HrKpiEvaluator $evaluator): array
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);

        $templates = DB::table('hr_kpi_templates')->orderBy('id')->get();
        $template = $evaluator->template((string) $request->query('template', (string) optional($templates->first())->code));
        abort_unless($template, 404);
        $criteria = DB::table('hr_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->orderBy('id')->get();
        $settingsSource = null;

        // HCNS: form hiển thị và lưu vào BẢN NHÁP phiên bản (trọng số/chỉ tiêu/bậc chưa chốt chỉ là nháp).
        if ($template->code === \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE && \App\Services\Hr\Kpi\HcnsKpiRules::configReady()) {
            $rules = app(\App\Services\Hr\Kpi\HcnsKpiRules::class);
            $month = $this->month($request->query('month'));
            $draft = $rules->draft();
            $applied = $rules->forMonth($month);
            $config = $draft['config'] ?? ($applied['status'] === 'applied' ? $applied['config'] : null);
            if ($config) {
                [$template, $criteria] = $this->settingsOverlay($template, $criteria, $config);
            }
            $settingsSource = [
                'draft' => $draft,
                'label' => $draft && $draft['config'] ? 'Đang sửa bản nháp v'.$draft['version'].' — chưa áp dụng'
                    : ($applied['status'] === 'applied' && $applied['config'] ? 'Đang xem '.$applied['label'].'; lưu sẽ tạo bản nháp mới'
                    : 'Đang xem cài đặt gốc; lưu sẽ tạo bản nháp (chưa áp dụng cho kỳ nào)'),
            ];
        }

        return [
            'templates' => $templates,
            'template' => $template,
            'criteria' => $criteria,
            'calcTypes' => HrKpiEvaluator::CALC_TYPES,
            'canEdit' => PayrollAccess::canEvaluateKpi($request->user()),
            'settingsSource' => $settingsSource,
        ] + $this->ruleSettingsData($request, $evaluator, $template);
    }

    /** Áp cấu hình phiên bản lên tất cả tiêu chí (kể cả đang tắt) để hiển thị trong form cài đặt. */
    private function settingsOverlay(object $template, \Illuminate\Support\Collection $criteria, array $config): array
    {
        $tpl = clone $template;
        $tpl->payout_tiers = array_values((array) ($config['tiers'] ?? []));
        $tpl->max_achievement = $config['max_achievement'] ?? $template->max_achievement;
        $tpl->max_payout_rate = array_key_exists('max_payout_rate', $config) ? $config['max_payout_rate'] : $template->max_payout_rate;
        $rows = $criteria->map(function ($c) use ($config) {
            $c = clone $c;
            if ($v = $config['criteria'][(string) $c->id] ?? null) {
                foreach (['name', 'group_name', 'target_text', 'weight', 'calc_type', 'target_value', 'unit', 'is_active'] as $key) {
                    $c->{$key} = $v[$key] ?? null;
                }
            } else {
                $c->is_active = false;
            }

            return $c;
        });

        return [$tpl, $rows];
    }

    /**
     * Khối "Quy tắc nguồn KPI HCNS": phiên bản đã duyệt/bản nháp + xem trước tác động từng người với bản nháp.
     */
    private function ruleSettingsData(Request $request, HrKpiEvaluator $evaluator, object $template): array
    {
        $rulesService = app(\App\Services\Hr\Kpi\HcnsKpiRules::class);
        if ($template->code !== \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE || ! \App\Services\Hr\Kpi\HcnsKpiRules::ready() || ! HcnsKpiChecklist::available()) {
            return ['ruleVersions' => null];
        }

        $month = $this->month($request->query('month'));
        $draft = $rulesService->draft();
        $previewRule = $draft ?? ['status' => 'draft', 'version' => null, 'id' => null, 'label' => 'Mặc định nháp (chưa lưu)', 'effective_month' => null, 'rules' => $rulesService->defaults(), 'config' => null, 'created_by' => null];
        // Checklist xem trước dùng trọng số/chỉ tiêu của chính bản nháp (nếu có), không dùng cài đặt đang áp dụng.
        ['template' => $template, 'criteria' => $criteria] = $evaluator->withVersion($template->code, $previewRule);
        $profiles = PayrollGenerator::profilesFor($month)->filter(fn ($p) => $p->kpi_template === $template->code);
        $people = DB::table('users')->whereIn('id', $profiles->keys()->all() ?: [0])->orderBy('name')->get(['id', 'name']);
        $scores = $evaluator->scores($people->pluck('id')->all(), $month);

        return [
            'ruleVersions' => $rulesService->versions(),
            'ruleCurrent' => $rulesService->forMonth($month),
            'ruleDraft' => $draft,
            'ruleOptions' => (array) config('hcns_kpi.rules', []),
            'ruleValues' => $previewRule['rules'],
            'rulePreview' => $people->map(fn ($p) => [
                'name' => $p->name,
                // Tổng chính thức hiện tại (cùng đường với bảng lương) và tổng nếu bản nháp được duyệt cho kỳ này.
                'official' => $evaluator->forEmployee((int) $p->id, $month, $template->code),
                'preview' => $evaluator->previewForVersion((int) $p->id, $month, $previewRule),
                'checklist' => app(HcnsKpiChecklist::class)->forUser((int) $p->id, $month, $template, $criteria, $scores->get($p->id, collect()), $request->user(), null, $previewRule),
            ]),
            'canEditRules' => PayrollAccess::canEvaluateKpi($request->user()),
            // Người lập bản nháp không tự duyệt: ẩn nút với chính họ (server vẫn chặn).
            'canApproveRules' => PayrollAccess::canApprove($request->user())
                && ! ($draft && (int) ($draft['created_by'] ?? 0) === (int) $request->user()->id),
            'ruleDraftOwn' => $draft && (int) ($draft['created_by'] ?? 0) === (int) $request->user()->id,
        ];
    }

    /** Lưu bản nháp quy tắc đo nguồn HCNS (chỉ xem trước, không đổi điểm chính thức). */
    public function saveRuleDraft(Request $request): RedirectResponse
    {
        abort_unless(PayrollAccess::canEvaluateKpi($request->user()), 403, 'Chỉ Ban Giám đốc hoặc Admin được sửa quy tắc KPI.');
        abort_unless(\App\Services\Hr\Kpi\HcnsKpiRules::ready(), 404);
        $data = $request->validate(['rules' => ['required', 'array'], 'note' => ['nullable', 'string', 'max:2000'], 'month' => ['nullable', 'date_format:Y-m']]);

        $id = app(\App\Services\Hr\Kpi\HcnsKpiRules::class)->saveDraft($data['rules'], (int) $request->user()->id, $data['note'] ?? null);

        return redirect()->route('hr.kpi.index', ['template' => 'hcns', 'tab' => 'settings', 'month' => $data['month'] ?? $this->month(null)])
            ->with('success', 'Đã lưu bản nháp quy tắc nguồn (#'.$id.'). Chỉ dùng để xem trước cho tới khi được duyệt.');
    }

    /**
     * Duyệt áp dụng bản nháp từ một kỳ. Người duyệt khác người lập nháp; không áp vào kỳ đã chốt lương
     * hoặc trước kỳ của phiên bản đang áp dụng (không viết lại lịch sử).
     */
    public function approveRuleVersion(Request $request, int $version): RedirectResponse
    {
        abort_unless(PayrollAccess::canApprove($request->user()), 403, 'Chỉ Ban Giám đốc hoặc Admin được duyệt quy tắc KPI.');
        abort_unless(\App\Services\Hr\Kpi\HcnsKpiRules::ready(), 404);
        $data = $request->validate(['effective_month' => ['required', 'date_format:Y-m']], ['effective_month.required' => 'Chọn kỳ bắt đầu áp dụng.']);
        $row = DB::table('hr_kpi_rule_versions')->where('id', $version)->where('template_code', \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE)->first();
        abort_unless($row, 404);

        $error = match (true) {
            $row->status !== 'draft' => 'Chỉ duyệt được bản nháp.',
            (int) $row->created_by === (int) $request->user()->id => 'Người lập bản nháp không tự duyệt — cần người duyệt khác.',
            DB::table('hr_payroll_periods')->where('status', 'approved')->where('payroll_month', '>=', $data['effective_month'])->exists()
                => 'Từ kỳ '.$data['effective_month'].' trở đi đã có kỳ chốt lương — không áp quy tắc mới vào kỳ đã chốt.',
            DB::table('hr_kpi_rule_versions')->where('template_code', $row->template_code)->where('status', 'applied')
                ->where('effective_month', '>=', $data['effective_month'])->exists() => 'Đã có phiên bản áp dụng từ kỳ này hoặc sau — chọn kỳ muộn hơn.',
            default => null,
        };
        if ($error) {
            return back()->with('error', $error);
        }

        // Phiên bản phải tự đủ: bản nháp chưa có trọng số/chỉ tiêu/bậc riêng thì chụp cài đặt gốc lúc duyệt.
        $config = $row->config ?? null;
        if (! $config && \App\Services\Hr\Kpi\HcnsKpiRules::configReady()) {
            $base = app(HrKpiEvaluator::class)->template(\App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE);
            $config = json_encode(app(\App\Services\Hr\Kpi\HcnsKpiRules::class)->configFromTemplate($base, app(HrKpiEvaluator::class)->allCriteria((int) $base->id)), JSON_UNESCAPED_UNICODE);
        }
        $missingWeight = collect(json_decode((string) $config, true)['criteria'] ?? [])->filter(fn ($c) => ($c['is_active'] ?? true) && $c['weight'] === null);
        if ($missingWeight->isNotEmpty()) {
            return back()->with('error', 'Bản nháp còn tiêu chí chưa có trọng số: '.$missingWeight->pluck('name')->implode(', ').'.');
        }

        DB::table('hr_kpi_rule_versions')->where('id', $row->id)->update([
            'status' => 'applied', 'effective_month' => $data['effective_month'],
            'approved_by' => $request->user()->id, 'approved_at' => now(), 'updated_at' => now(),
        ] + (\App\Services\Hr\Kpi\HcnsKpiRules::configReady() ? ['config' => $config] : []));

        return redirect()->route('hr.kpi.index', ['template' => 'hcns', 'tab' => 'settings', 'month' => $data['effective_month']])
            ->with('success', 'Đã duyệt quy tắc nguồn v'.$row->version.' áp dụng từ kỳ '.$data['effective_month'].'.');
    }

    public function saveSettings(Request $request, HrKpiEvaluator $evaluator): RedirectResponse
    {
        abort_unless(PayrollAccess::canEvaluateKpi($request->user()), 403, 'Chỉ Ban Giám đốc hoặc Admin được sửa cài đặt KPI.');

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'template' => ['required', 'string'],
            'max_payout_rate' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'max_achievement' => ['required', 'numeric', 'min:100', 'max:200'],
            'criteria' => ['required', 'array'],
            'criteria.*.name' => ['required', 'string', 'max:255'],
            'criteria.*.group_name' => ['nullable', 'string', 'max:190'],
            'criteria.*.target_text' => ['nullable', 'string', 'max:255'],
            'criteria.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'criteria.*.calc_type' => ['required', Rule::in(array_keys(HrKpiEvaluator::CALC_TYPES))],
            'criteria.*.target_value' => ['nullable', 'numeric', 'min:0'],
            'criteria.*.unit' => ['nullable', 'string', 'max:40'],
            'criteria.*.is_active' => ['nullable', 'boolean'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.min' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'tiers.*.mode' => ['required', Rule::in(['fixed', 'proportional'])],
            'tiers.*.rate' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'tiers.*.label' => ['nullable', 'string', 'max:190'],
        ], ['criteria.*.name.required' => 'Tên tiêu chí không được trống.']);

        $template = $evaluator->template($validated['template']);
        abort_unless($template, 404);

        foreach ($validated['criteria'] as $id => $c) {
            if (in_array($c['calc_type'], ['higher_better', 'lower_better'], true) && ($c['target_value'] ?? '') === '') {
                throw ValidationException::withMessages(["criteria.$id.target_value" => 'Tiêu chí "'.$c['name'].'" tự tính cần có Chỉ tiêu (số).']);
            }
        }

        $tiers = collect($validated['tiers'])
            ->filter(fn ($t) => ($t['min'] ?? '') !== '')
            ->map(fn ($t) => [
                'min' => (float) $t['min'],
                'mode' => $t['mode'],
                'rate' => (float) (($t['rate'] ?? '') !== '' ? $t['rate'] : ($t['mode'] === 'proportional' ? 100 : 0)) / 100,
                'label' => trim((string) ($t['label'] ?? '')),
            ])
            ->sortByDesc('min')
            ->values()
            ->all();

        // HCNS: trọng số/chỉ tiêu/bậc chưa chốt → lưu vào bản nháp phiên bản, KHÔNG sửa cài đặt đang dùng cho các kỳ.
        if ($template->code === \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE && \App\Services\Hr\Kpi\HcnsKpiRules::configReady()) {
            $live = DB::table('hr_kpi_criteria')->where('template_id', $template->id)->get()->keyBy(fn ($c) => (string) $c->id);
            $config = ['criteria' => [], 'tiers' => $tiers, 'max_achievement' => (float) $validated['max_achievement'],
                'max_payout_rate' => ($validated['max_payout_rate'] ?? '') !== '' ? (float) $validated['max_payout_rate'] / 100 : null];
            foreach ($validated['criteria'] as $id => $c) {
                if (! $live->has((string) $id)) {
                    continue;
                }
                $config['criteria'][(string) $id] = [
                    'code' => (string) $live->get((string) $id)->code, 'name' => $c['name'], 'group_name' => $c['group_name'] ?? null,
                    'target_text' => $c['target_text'] ?? null, 'weight' => ($c['weight'] ?? '') !== '' ? (float) $c['weight'] : null,
                    'calc_type' => $c['calc_type'], 'target_value' => ($c['target_value'] ?? '') !== '' ? (float) $c['target_value'] : null,
                    'unit' => $c['unit'] ?? null, 'is_active' => ! empty($c['is_active']),
                ];
            }
            $id = app(\App\Services\Hr\Kpi\HcnsKpiRules::class)->saveDraftConfig($config, (int) $request->user()->id, $validated['month'] ?? $this->month(null));

            return redirect()->route('hr.kpi.index', ['tab' => 'settings', 'template' => $template->code, 'month' => $validated['month'] ?? $this->month(null)])
                ->with('success', 'Đã lưu trọng số/chỉ tiêu/bậc vào bản nháp (#'.$id.'). Chưa áp dụng cho kỳ nào — cần người khác duyệt.');
        }

        DB::transaction(function () use ($validated, $template, $tiers, $request): void {
            DB::table('hr_kpi_templates')->where('id', $template->id)->update([
                'payout_tiers' => json_encode($tiers, JSON_UNESCAPED_UNICODE),
                'max_payout_rate' => ($validated['max_payout_rate'] ?? '') !== '' ? (float) $validated['max_payout_rate'] / 100 : null,
                'max_achievement' => $validated['max_achievement'],
                'updated_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

            foreach ($validated['criteria'] as $id => $c) {
                DB::table('hr_kpi_criteria')->where('id', (int) $id)->where('template_id', $template->id)->update([
                    'name' => $c['name'],
                    'group_name' => $c['group_name'] ?? null,
                    'target_text' => $c['target_text'] ?? null,
                    'weight' => ($c['weight'] ?? '') !== '' ? $c['weight'] : null,
                    'calc_type' => $c['calc_type'],
                    'target_value' => ($c['target_value'] ?? '') !== '' ? $c['target_value'] : null,
                    'unit' => $c['unit'] ?? null,
                    'is_active' => ! empty($c['is_active']),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('hr.kpi.index', ['tab' => 'settings', 'template' => $template->code, 'month' => $validated['month'] ?? $this->month(null)])->with('success', 'Đã lưu cài đặt '.$template->name.'.');
    }

    /**
     * KPI của tôi: nhân viên xem điểm KPI của chính mình theo tháng (chỉ đọc).
     * Bộ KPI lấy theo Hồ sơ lương của tháng; KPI kỹ sư lấy từ module KPI kỹ thuật.
     */
    public function my(Request $request, HrKpiEvaluator $evaluator): View
    {
        $userId = (int) $request->user()->id;
        $month = $this->month($request->query('month'));
        $code = PayrollGenerator::profilesFor($month)->get($userId)?->kpi_template;

        $template = null;
        $result = null;
        $checklist = null;
        $snapshot = null;
        if ($code !== null && $code !== '' && $code !== HrKpiEvaluator::TECHNICAL) {
            $effective = $evaluator->effective($code, $month);
            $template = $effective['template'];
            if ($template && ($snap = \App\Services\Hr\Kpi\HcnsKpiSnapshots::current($userId, $month, $code))) {
                // Kỳ đã chốt lương: xem đúng ảnh chụp lúc duyệt.
                $snapshot = \App\Services\Hr\Kpi\HcnsKpiSnapshots::restore($snap);
                $result = $snapshot['result'];
                $checklist = $snapshot['checklist'] ? app(HcnsKpiChecklist::class)->forViewer($snapshot['checklist'], $request->user()) : null;
            } elseif ($template) {
                $myCriteria = $effective['criteria'];
                $myScores = $evaluator->scores([$userId], $month)->get($userId, collect());
                $result = $evaluator->score($template, $myCriteria, $myScores, $evaluator->sourceOverrides($template, $myCriteria, $userId, $month, $myScores));
                $checklist = $template->code === \App\Services\Hr\Kpi\HcnsKpiRules::TEMPLATE && HcnsKpiChecklist::available()
                    ? app(HcnsKpiChecklist::class)->forUser($userId, $month, $template, $myCriteria, $myScores, $request->user())
                    : null;
            }
        }

        // 6 tháng gần nhất (bộ KPI văn phòng; KPI kỹ sư xem ở trang Kỹ thuật vì tính từ dữ liệu dự án).
        $history = collect(range(0, 5))
            ->map(fn (int $i) => now()->startOfMonth()->subMonths($i)->format('Y-m'))
            ->map(function (string $m) use ($evaluator, $userId) {
                $tpl = PayrollGenerator::profilesFor($m)->get($userId)?->kpi_template;

                return [
                    'month' => $m,
                    'code' => $tpl,
                    'summary' => $tpl && $tpl !== HrKpiEvaluator::TECHNICAL ? $evaluator->forEmployee($userId, $m, $tpl) : null,
                    'locked' => self::monthLocked($m),
                ];
            });

        return view('hr.kpi.my', [
            'month' => $month,
            'code' => $code,
            'template' => $template,
            'result' => $result,
            'summary' => $evaluator->forEmployee($userId, $month, $code),
            'history' => $history,
            'locked' => self::monthLocked($month),
            'checklist' => $checklist,
            'snapshot' => $snapshot,
        ]);
    }

    /**
     * Số liệu phần mềm đã có để người chấm tham khảo, theo mã tiêu chí: [code => mô tả].
     * Sự cố văn phòng đúng hạn => tiêu chí "Quản lý tài sản & văn phòng phẩm" của HCNS.
     */
    private function autoData(string $templateCode, string $month): array
    {
        if ($templateCode !== 'hcns' || ! class_exists(OfficeIncidents::class)) {
            return [];
        }

        $sla = OfficeIncidents::slaStats($month);
        if ($sla['total'] === 0) {
            return ['assets' => 'tháng này chưa có sự cố văn phòng nào cần xử lý.'];
        }

        return ['assets' => sprintf('sự cố văn phòng xử lý đúng hạn %d / %d vụ (%s%%)%s.', $sla['on_time'], $sla['total'],
            rtrim(rtrim(number_format((float) $sla['percent'], 2, ',', '.'), '0'), ','),
            $sla['late_open'] ? ', trong đó '.$sla['late_open'].' vụ quá hạn chưa xong' : '')];
    }

    /** KPI của tháng đã có bảng lương duyệt thì khoá, tránh lệch với số đã trả. */
    public static function monthLocked(string $month): bool
    {
        return DB::table('hr_payroll_periods')->where('payroll_month', $month)->where('status', 'approved')->exists();
    }

    private function month(?string $value): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value) ? (string) $value : now()->format('Y-m');
    }
}
