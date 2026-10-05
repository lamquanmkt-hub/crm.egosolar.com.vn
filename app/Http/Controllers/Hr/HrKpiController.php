<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
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

        $month = $this->month($request->query('month'));
        $templates = DB::table('hr_kpi_templates')->where('is_active', true)->orderBy('id')->get();
        $code = (string) $request->query('template', (string) optional($templates->first())->code);
        $template = $evaluator->template($code);
        abort_unless($template, 404, 'Không tìm thấy bộ KPI.');

        $criteria = $evaluator->criteria((int) $template->id);
        $profiles = PayrollGenerator::profilesFor($month)->filter(fn ($p) => $p->kpi_template === $code);
        $employees = DB::table('users')->whereIn('id', $profiles->keys()->all() ?: [0])->orderBy('name')->get(['id', 'name']);
        $scores = $evaluator->scores($employees->pluck('id')->all(), $month);

        $rows = $employees->map(function ($e) use ($evaluator, $template, $criteria, $scores) {
            $result = $evaluator->score($template, $criteria, $scores->get($e->id, collect()));

            return ['employee' => $e, 'result' => $result, 'summary' => $evaluator->fromResult($template, $result)];
        });

        return view('hr.kpi.index', [
            'month' => $month,
            'templates' => $templates,
            'template' => $template,
            'criteria' => $criteria,
            'rows' => $rows,
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
        $template = $evaluator->template($validated['template']);
        abort_unless($template, 404);
        if (self::monthLocked($month)) {
            return back()->with('error', 'Bảng lương tháng '.$month.' đã duyệt nên điểm KPI tháng này đã khoá. Giám đốc mở khoá bảng lương (ghi lý do) nếu cần sửa.');
        }
        $criterionIds = $evaluator->criteria((int) $template->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $allowedUsers = PayrollGenerator::profilesFor($month)->filter(fn ($p) => $p->kpi_template === $template->code)->keys()->map(fn ($id) => (int) $id)->all();

        foreach (array_keys((array) ($validated['scores'] ?? [])) as $userId) {
            if ((int) $userId === (int) $request->user()->id) {
                throw ValidationException::withMessages(['scores' => 'Không được tự chấm KPI cho chính mình.']);
            }
        }

        DB::transaction(function () use ($validated, $month, $criterionIds, $allowedUsers, $request): void {
            foreach ((array) ($validated['scores'] ?? []) as $userId => $byCriterion) {
                if (! in_array((int) $userId, $allowedUsers, true)) {
                    continue;
                }
                foreach ((array) $byCriterion as $criterionId => $v) {
                    if (! in_array((int) $criterionId, $criterionIds, true)) {
                        continue;
                    }
                    $key = ['user_id' => (int) $userId, 'payroll_month' => $month, 'criterion_id' => (int) $criterionId];
                    $values = [
                        'actual_value' => ($v['actual'] ?? '') !== '' ? $v['actual'] : null,
                        'achievement' => ($v['achievement'] ?? '') !== '' ? $v['achievement'] : null,
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

    public function settings(Request $request, HrKpiEvaluator $evaluator): View
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);

        $templates = DB::table('hr_kpi_templates')->orderBy('id')->get();
        $template = $evaluator->template((string) $request->query('template', (string) optional($templates->first())->code));
        abort_unless($template, 404);

        return view('hr.kpi.settings', [
            'templates' => $templates,
            'template' => $template,
            'criteria' => DB::table('hr_kpi_criteria')->where('template_id', $template->id)->orderBy('sort_order')->orderBy('id')->get(),
            'calcTypes' => HrKpiEvaluator::CALC_TYPES,
            'canEdit' => PayrollAccess::canEvaluateKpi($request->user()),
        ]);
    }

    public function saveSettings(Request $request, HrKpiEvaluator $evaluator): RedirectResponse
    {
        abort_unless(PayrollAccess::canEvaluateKpi($request->user()), 403, 'Chỉ Ban Giám đốc hoặc Admin được sửa cài đặt KPI.');

        $validated = $request->validate([
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

        return redirect()->route('hr.kpi.settings', ['template' => $template->code])->with('success', 'Đã lưu cài đặt '.$template->name.'.');
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
        if ($code !== null && $code !== '' && $code !== HrKpiEvaluator::TECHNICAL) {
            $template = $evaluator->template($code);
            if ($template) {
                $result = $evaluator->score($template, $evaluator->criteria((int) $template->id), $evaluator->scores([$userId], $month)->get($userId, collect()));
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
