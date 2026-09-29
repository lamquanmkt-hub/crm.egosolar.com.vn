<?php

declare(strict_types=1);

namespace App\Http\Controllers\Synced\TechnicalKpi;

use App\Http\Controllers\Controller;
use App\Models\TechnicalKpiConfig;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Dữ liệu KPI kỹ thuật nhập theo tháng:
 * - Nhập số liệu KPI tháng (Kế hoạch / Thực tế từng tiêu chí + điểm cộng/trừ): Trưởng phòng KT, Ban Giám đốc, Admin.
 * - Lương thoả thuận từng kỹ sư (theo tháng hiệu lực): Admin, HR, Kế toán.
 * Trang KPI (/ky-thuat/kpis) đọc các số liệu này để tự tính % KPI và lương.
 */
final class TechnicalKpiInputController extends Controller
{
    private const MANAGER_ROLES = ['admin', 'super_admin', 'director', 'ceo', 'management', 'technical_manager', 'technical_leader', 'truong_phong_ky_thuat'];

    private const SALARY_ROLES = ['admin', 'super_admin', 'director', 'ceo', 'management', 'hr', 'accounting'];

    public static function canInput($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::MANAGER_ROLES);
    }

    public static function canEditSalary($user): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole(self::SALARY_ROLES);
    }

    // ------------------------------------------------------------------ Nhập số liệu KPI tháng

    public function index(Request $request, KpiStandardEvaluator $evaluator): View
    {
        abort_unless(self::canInput($request->user()), 403, 'Chỉ Trưởng phòng Kỹ thuật, Ban Giám đốc hoặc Admin được nhập số liệu KPI.');

        $month = $this->month($request->query('month'));
        $config = TechnicalKpiConfig::getActiveForDate($month.'-01');
        $evaluator->useConfig($config);
        $criteria = collect($evaluator->criteria())->filter(fn ($c) => $c['weight'] !== null)->values();

        $employees = $this->employees();
        $ids = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();

        $saved = DB::table('technical_kpi_monthly_scores')
            ->where('payroll_month', $month)
            ->whereIn('user_id', $ids)
            ->get()
            ->groupBy('user_id');

        $adjustments = DB::table('technical_kpi_adjustments as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.created_by')
            ->where('a.payroll_month', $month)
            ->whereIn('a.user_id', $ids)
            ->orderBy('a.id')
            ->get(['a.*', 'u.name as created_by_name'])
            ->groupBy('user_id');

        // Gợi ý tự động (từ dự án /du-an và minh chứng đã duyệt) để Trưởng phòng đối chiếu trước khi nhập.
        $rows = $employees->map(function ($employee) use ($evaluator, $month, $saved, $adjustments) {
            $data = $evaluator->collect((int) $employee->id, [$month]);
            $auto = collect($evaluator->score($data['projects'], $data['evidence'], $data['warranty'])['criteria'])->keyBy('code');

            return [
                'id' => (int) $employee->id,
                'name' => (string) $employee->name,
                'code' => (string) ($employee->employee_code ?? ''),
                'position' => (string) ($employee->position_name ?? ''),
                'saved' => collect($saved->get($employee->id, []))->keyBy('criterion_code'),
                'auto' => $auto,
                'projects' => $data['projects'],
                'adjustments' => $adjustments->get($employee->id, collect()),
                'salary' => TechnicalPayrollController::kpiAgreedSalary((int) $employee->id, $month),
            ];
        });

        return view('synced.kythuat.kpi_input', [
            'month' => $month,
            'config' => $config,
            'criteria' => $criteria,
            'rows' => $rows,
            'canEditSalary' => self::canEditSalary($request->user()),
        ]);
    }

    public function store(Request $request, KpiStandardEvaluator $evaluator): RedirectResponse
    {
        abort_unless(self::canInput($request->user()), 403, 'Chỉ Trưởng phòng Kỹ thuật, Ban Giám đốc hoặc Admin được nhập số liệu KPI.');

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'scores' => ['nullable', 'array'],
            'scores.*' => ['array'],
            'scores.*.*.plan' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'scores.*.*.actual' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'scores.*.*.note' => ['nullable', 'string', 'max:1000'],
        ], [
            'scores.*.*.plan.numeric' => 'Kế hoạch phải là số.',
            'scores.*.*.actual.numeric' => 'Thực tế phải là số.',
            'scores.*.*.plan.min' => 'Kế hoạch không được âm.',
            'scores.*.*.actual.min' => 'Thực tế không được âm.',
        ]);

        $month = $validated['month'];
        $evaluator->useConfig(TechnicalKpiConfig::getActiveForDate($month.'-01'));
        $codes = collect($evaluator->criteria())->filter(fn ($c) => $c['weight'] !== null)->pluck('code')->all();
        $allowedUsers = $this->employees()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $errors = [];
        $writes = [];
        foreach ((array) ($validated['scores'] ?? []) as $userId => $byCode) {
            $userId = (int) $userId;
            if (! in_array($userId, $allowedUsers, true)) {
                continue;
            }
            foreach ((array) $byCode as $code => $v) {
                if (! in_array($code, $codes, true)) {
                    continue;
                }
                $plan = isset($v['plan']) && $v['plan'] !== '' ? (float) $v['plan'] : null;
                $actual = isset($v['actual']) && $v['actual'] !== '' ? (float) $v['actual'] : null;
                if (($plan === null) !== ($actual === null)) {
                    $errors["scores.$userId.$code.plan"] = 'Nhập đủ cả Kế hoạch và Thực tế (hoặc để trống cả hai).';

                    continue;
                }
                if ($plan !== null && $plan <= 0) {
                    $errors["scores.$userId.$code.plan"] = 'Kế hoạch phải lớn hơn 0.';

                    continue;
                }
                $writes[] = [$userId, $code, $plan, $actual, trim((string) ($v['note'] ?? '')) ?: null];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($writes, $month, $request): void {
            foreach ($writes as [$userId, $code, $plan, $actual, $note]) {
                $key = ['user_id' => $userId, 'payroll_month' => $month, 'criterion_code' => $code];
                if ($plan === null) {
                    DB::table('technical_kpi_monthly_scores')->where($key)->delete();

                    continue;
                }
                $this->upsert('technical_kpi_monthly_scores', $key, [
                    'plan_value' => $plan,
                    'actual_value' => $actual,
                    'note' => $note,
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        return redirect()->route('ky-thuat.kpis.input', ['month' => $month])->with('success', 'Đã lưu số liệu KPI tháng '.$month.'.');
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        abort_unless(self::canInput($request->user()), 403, 'Chỉ Trưởng phòng Kỹ thuật, Ban Giám đốc hoặc Admin được cộng/trừ điểm KPI.');

        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'user_id' => ['required', 'integer'],
            'points' => ['required', 'numeric', 'between:-100,100', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'points.required' => 'Nhập số điểm cộng (dương) hoặc trừ (âm).',
            'points.not_in' => 'Số điểm phải khác 0.',
            'reason.required' => 'Bắt buộc nhập lý do cộng/trừ điểm.',
            'reason.min' => 'Lý do tối thiểu 5 ký tự.',
        ]);

        abort_unless($this->employees()->pluck('id')->map(fn ($id) => (int) $id)->contains((int) $validated['user_id']), 422, 'Nhân viên không thuộc danh sách kỹ thuật.');

        DB::table('technical_kpi_adjustments')->insert([
            'user_id' => (int) $validated['user_id'],
            'payroll_month' => $validated['month'],
            'points' => (float) $validated['points'],
            'reason' => trim($validated['reason']),
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('ky-thuat.kpis.input', ['month' => $validated['month']])->with('success', 'Đã ghi nhận điều chỉnh điểm KPI.');
    }

    public function destroyAdjustment(Request $request, int $adjustment): RedirectResponse
    {
        abort_unless(self::canInput($request->user()), 403);

        $row = DB::table('technical_kpi_adjustments')->where('id', $adjustment)->first();
        abort_unless($row, 404);
        DB::table('technical_kpi_adjustments')->where('id', $adjustment)->delete();

        return redirect()->route('ky-thuat.kpis.input', ['month' => $row->payroll_month])->with('success', 'Đã xoá điều chỉnh điểm KPI.');
    }

    // ------------------------------------------------------------------ Lương thoả thuận (Cài đặt KPI)

    public function storeSalaries(Request $request): RedirectResponse
    {
        abort_unless(self::canEditSalary($request->user()), 403, 'Chỉ Admin, HR hoặc Kế toán được nhập lương thoả thuận.');

        $validated = $request->validate([
            'effective_month' => ['required', 'date_format:Y-m'],
            'salaries' => ['required', 'array'],
            'salaries.*.amount' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'salaries.*.note' => ['nullable', 'string', 'max:500'],
        ], [
            'effective_month.required' => 'Chọn tháng bắt đầu áp dụng.',
            'salaries.*.amount.numeric' => 'Lương phải là số.',
        ]);

        $allowedUsers = $this->employees()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $month = $validated['effective_month'];
        $saved = 0;

        DB::transaction(function () use ($validated, $allowedUsers, $month, $request, &$saved): void {
            foreach ($validated['salaries'] as $userId => $v) {
                $userId = (int) $userId;
                $amount = isset($v['amount']) && $v['amount'] !== '' ? (float) $v['amount'] : null;
                if ($amount === null || ! in_array($userId, $allowedUsers, true)) {
                    continue;
                }
                $this->upsert('technical_kpi_salaries', ['user_id' => $userId, 'effective_month' => $month], [
                    'agreed_salary' => $amount,
                    'note' => trim((string) ($v['note'] ?? '')) ?: null,
                    'updated_by' => $request->user()->id,
                ], ['created_by' => $request->user()->id]);
                $saved++;
            }
        });

        return redirect()->to(route('ky-thuat.kpis.config').'#luong-thoa-thuan')->with('success', "Đã lưu lương thoả thuận cho {$saved} nhân viên, áp dụng từ tháng {$month}.");
    }

    /** Dữ liệu cho mục Lương thoả thuận trong trang Cài đặt KPI. */
    public function salaryRows(): Collection
    {
        $employees = $this->employees();
        $ids = $employees->pluck('id')->map(fn ($id) => (int) $id)->all();
        $history = DB::table('technical_kpi_salaries')->whereIn('user_id', $ids)->orderByDesc('effective_month')->get()->groupBy('user_id');
        $current = now()->format('Y-m');

        return $employees->map(fn ($e) => [
            'id' => (int) $e->id,
            'name' => (string) $e->name,
            'code' => (string) ($e->employee_code ?? ''),
            'position' => (string) ($e->position_name ?? ''),
            'current' => TechnicalPayrollController::kpiAgreedSalary((int) $e->id, $current),
            'history' => $history->get($e->id, collect()),
        ]);
    }

    /** Có dòng theo khoá thì cập nhật, chưa có thì thêm mới (giữ nguyên created_at/created_by của dòng cũ). */
    private function upsert(string $table, array $key, array $values, array $onInsert = []): void
    {
        if (DB::table($table)->where($key)->exists()) {
            DB::table($table)->where($key)->update($values + ['updated_at' => now()]);

            return;
        }

        DB::table($table)->insert($key + $values + $onInsert + ['created_at' => now(), 'updated_at' => now()]);
    }

    private function employees(): Collection
    {
        return app(TechnicalPayrollController::class)->kpiEmployees();
    }

    private function month(?string $value): string
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value) ? (string) $value : now()->format('Y-m');
    }
}
