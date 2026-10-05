<?php

declare(strict_types=1);

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Services\Hr\Payroll\PayrollAccess;
use App\Services\Hr\Payroll\PayrollGenerator;
use App\Support\SchemaCache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Hồ sơ lương từng nhân viên (thay sheet "BẢNG THEO DÕI LAO ĐỘNG" của file Excel):
 * mỗi lần đổi lương lưu một bản ghi theo tháng hiệu lực, bảng lương lấy bản gần nhất.
 */
final class SalaryProfileController extends Controller
{
    public const PIT_MODES = [
        'progressive' => 'Lũy tiến (HĐ từ 3 tháng)',
        'flat_10' => 'Khấu trừ 10% (thời vụ / thử việc)',
        'none' => 'Không khấu trừ',
    ];

    public function index(Request $request, HrKpiEvaluator $kpi): View
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403, 'Chỉ HR, Kế toán, Ban Giám đốc hoặc Admin được xem hồ sơ lương.');

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month')) ? (string) $request->query('month') : now()->format('Y-m');
        $profiles = PayrollGenerator::profilesFor($month);
        $history = DB::table('hr_salary_profiles')->orderByDesc('effective_month')->get()->groupBy('user_id');

        $employees = DB::table('users')
            ->when(SchemaCache::hasColumn('users', 'is_active'), fn ($q) => $q->where(fn ($w) => $w->where('is_active', 1)->orWhereNull('is_active')->orWhereIn('id', $profiles->keys()->all() ?: [0])))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('hr.payroll.profiles', [
            'month' => $month,
            'employees' => $employees,
            'profiles' => $profiles,
            'history' => $history,
            'kpiTemplates' => $kpi->templateOptions(),
            'pitModes' => self::PIT_MODES,
        ]);
    }

    public function store(Request $request, HrKpiEvaluator $kpi): RedirectResponse
    {
        abort_unless(PayrollAccess::canManage($request->user()), 403);

        $money = ['required', 'numeric', 'min:0', 'max:10000000000'];
        $validated = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'effective_month' => ['required', 'date_format:Y-m'],
            'base_salary' => $money,
            'kpi_salary' => $money,
            'decision_bonus' => $money,
            'travel_allowance' => $money,
            'phone_allowance' => $money,
            'meal_allowance' => $money,
            'insurance_salary' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'insurance_enabled' => ['nullable', 'boolean'],
            'dependents' => ['required', 'integer', 'min:0', 'max:20'],
            'pit_mode' => ['required', Rule::in(array_keys(self::PIT_MODES))],
            'kpi_template' => ['nullable', Rule::in(array_keys($kpi->templateOptions()))],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'effective_month.required' => 'Chọn tháng bắt đầu áp dụng.',
            '*.numeric' => 'Số tiền phải là số.',
        ]);

        abort_unless(PayrollAccess::canEditEmployee($request->user(), (int) $validated['user_id']), 403, 'Không được tự sửa hồ sơ lương của chính mình.');

        $key = ['user_id' => (int) $validated['user_id'], 'effective_month' => $validated['effective_month']];
        $values = [
            'base_salary' => $validated['base_salary'],
            'kpi_salary' => $validated['kpi_salary'],
            'decision_bonus' => $validated['decision_bonus'],
            'travel_allowance' => $validated['travel_allowance'],
            'phone_allowance' => $validated['phone_allowance'],
            'meal_allowance' => $validated['meal_allowance'],
            'insurance_salary' => $validated['insurance_salary'] ?? null,
            'insurance_enabled' => $request->boolean('insurance_enabled'),
            'dependents' => (int) $validated['dependents'],
            'pit_mode' => $validated['pit_mode'],
            'kpi_template' => ($validated['kpi_template'] ?? null) ?: null,
            'note' => $validated['note'] ?? null,
            'updated_by' => $request->user()->id,
            'updated_at' => now(),
        ];

        if (DB::table('hr_salary_profiles')->where($key)->exists()) {
            DB::table('hr_salary_profiles')->where($key)->update($values);
        } else {
            DB::table('hr_salary_profiles')->insert($key + $values + ['created_by' => $request->user()->id, 'created_at' => now()]);
        }

        $name = DB::table('users')->where('id', $key['user_id'])->value('name');

        return redirect()->route('hr.payroll.profiles', ['month' => $validated['effective_month']])
            ->with('success', "Đã lưu hồ sơ lương của {$name}, áp dụng từ tháng {$validated['effective_month']}.");
    }
}
