<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Controller tính lương KPI cho nhân viên kỹ thuật (cài đặt, bảng lương, duyệt).
 */
class TechnicalPayrollController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Kiểm tra bảng có tồn tại trong database không.
     */
    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    /**
     * Kiểm tra bảng có cột chỉ định không.
     */
    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, $column);
    }

    /**
     * Lọc mảng dữ liệu chỉ giữ các key trùng với cột của bảng.
     */
    private function filterColumns(string $table, array $data): array
    {
        if (! Schema::hasTable($table)) {
            return $data;
        }

        $columns = Schema::getColumnListing($table);

        return collect($data)
            ->only($columns)
            ->toArray();
    }

    /**
     * Giá trị mặc định của các hệ số tính lương KPI kỹ thuật.
     */
    private function settingDefaults(): array
    {
        return [
            'base_salary_rate' => 0.7000,
            'kpi_salary_rate' => 0.3000,
            'bad_feedback_penalty' => 0.1000,
            'good_feedback_bonus' => 0.0500,
            'customer_feedback_max' => 1.3000,
            'quality_error_penalty' => 0.0500,
            'safety_error_penalty' => 0.0500,
            'equipment_error_penalty' => 0.0500,
            'success_project_bonus' => 0.1000,
            'kpi_max_rate' => 1.3000,
        ];
    }

    /**
     * Danh sách dòng cài đặt KPI mặc định để seed vào bảng.
     */
    private function settingSeedRows(): array
    {
        return [
            [
                'setting_key' => 'base_salary_rate',
                'setting_label' => 'Tỷ lệ lương cố định',
                'setting_value' => 0.7000,
                'setting_unit' => '%',
                'note' => 'Mặc định 70%',
            ],
            [
                'setting_key' => 'kpi_salary_rate',
                'setting_label' => 'Tỷ lệ lương KPI',
                'setting_value' => 0.3000,
                'setting_unit' => '%',
                'note' => 'Mặc định 30%',
            ],
            [
                'setting_key' => 'bad_feedback_penalty',
                'setting_label' => 'Mỗi feedback không tốt trừ',
                'setting_value' => 0.1000,
                'setting_unit' => '%',
                'note' => 'Mặc định trừ 10%',
            ],
            [
                'setting_key' => 'good_feedback_bonus',
                'setting_label' => 'Mỗi feedback tốt cộng',
                'setting_value' => 0.0500,
                'setting_unit' => '%',
                'note' => 'Mặc định cộng 5%',
            ],
            [
                'setting_key' => 'customer_feedback_max',
                'setting_label' => 'Trần điểm feedback khách hàng',
                'setting_value' => 1.3000,
                'setting_unit' => '%',
                'note' => 'Mặc định tối đa 130%',
            ],
            [
                'setting_key' => 'quality_error_penalty',
                'setting_label' => 'Mỗi lỗi chất lượng trừ',
                'setting_value' => 0.0500,
                'setting_unit' => '%',
                'note' => 'Mặc định trừ 5%',
            ],
            [
                'setting_key' => 'safety_error_penalty',
                'setting_label' => 'Mỗi sự cố an toàn trừ',
                'setting_value' => 0.0500,
                'setting_unit' => '%',
                'note' => 'Mặc định trừ 5%',
            ],
            [
                'setting_key' => 'equipment_error_penalty',
                'setting_label' => 'Mỗi lỗi thiết bị trừ',
                'setting_value' => 0.0500,
                'setting_unit' => '%',
                'note' => 'Mặc định trừ 5%',
            ],
            [
                'setting_key' => 'success_project_bonus',
                'setting_label' => 'Mỗi công trình hỗ trợ chốt cộng',
                'setting_value' => 0.1000,
                'setting_unit' => '%',
                'note' => 'Mặc định cộng 10%',
            ],
            [
                'setting_key' => 'kpi_max_rate',
                'setting_label' => 'Trần KPI tổng',
                'setting_value' => 1.3000,
                'setting_unit' => '%',
                'note' => 'Mặc định tối đa 130%',
            ],
        ];
    }

    /**
     * Seed các cài đặt KPI mặc định vào bảng technical_kpi_settings nếu có.
     */
    private function ensureDefaultSettings(): void
    {
        if (! $this->tableExists('technical_kpi_settings')) {
            return;
        }

        foreach ($this->settingSeedRows() as $row) {
            DB::table('technical_kpi_settings')->updateOrInsert(
                ['setting_key' => $row['setting_key']],
                [
                    'setting_label' => $row['setting_label'],
                    'setting_value' => $row['setting_value'],
                    'setting_unit' => $row['setting_unit'],
                    'note' => $row['note'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    /**
     * Lấy map cài đặt KPI (giá trị trong DB đè lên giá trị mặc định).
     */
    private function settingsMap(): array
    {
        $defaults = $this->settingDefaults();

        if (! $this->tableExists('technical_kpi_settings')) {
            return $defaults;
        }

        $dbSettings = DB::table('technical_kpi_settings')
            ->pluck('setting_value', 'setting_key')
            ->map(fn ($value) => (float) $value)
            ->toArray();

        return array_merge($defaults, $dbSettings);
    }

    /**
     * Danh sách nhân viên kỹ thuật đang hoạt động kèm tên chức vụ.
     */
    private function employees()
    {
        if (! $this->tableExists('users')) {
            return collect();
        }

        $query = DB::table('users');

        if (
            $this->tableExists('roles')
            && $this->tableExists('model_has_roles')
        ) {
            $query
                ->join('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id');
                })
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'ky_thuat');
        }

        if (
            $this->tableExists('positions')
            && $this->hasColumn('users', 'position_id')
            && $this->hasColumn('positions', 'id')
        ) {
            $query->leftJoin('positions', 'positions.id', '=', 'users.position_id');

            $positionSelect = DB::raw('COALESCE(positions.name, "Kỹ thuật") as position_name');
        } else {
            $positionSelect = DB::raw('"Kỹ thuật" as position_name');
        }

        if ($this->hasColumn('users', 'is_active')) {
            $query->where('users.is_active', 1);
        }

        return $query
            ->select(
                'users.id',
                'users.name',
                'users.email',
                $positionSelect
            )
            ->orderBy('users.name')
            ->get();
    }

    /**
     * Lấy thông tin một nhân viên kỹ thuật theo id kèm tên chức vụ.
     */
    private function employeeById($userId)
    {
        if (! $this->tableExists('users')) {
            return null;
        }

        $query = DB::table('users');

        if (
            $this->tableExists('positions')
            && $this->hasColumn('users', 'position_id')
            && $this->hasColumn('positions', 'id')
        ) {
            $query->leftJoin('positions', 'positions.id', '=', 'users.position_id');

            $positionSelect = DB::raw('COALESCE(positions.name, "Kỹ thuật") as position_name');
        } else {
            $positionSelect = DB::raw('"Kỹ thuật" as position_name');
        }

        return $query
            ->where('users.id', $userId)
            ->select(
                'users.id',
                'users.name',
                'users.email',
                $positionSelect
            )
            ->first();
    }

    /**
     * Bộ khung 9 chỉ tiêu KPI kỹ thuật (tên, trọng số, cách tính, nguồn dữ liệu).
     */
    private function kpiTemplate(): array
    {
        return [
            1 => [
                'name' => 'Tiến độ thi công tổng thể',
                'subject' => 'Quản lý dự án',
                'unit' => 'Ngày',
                'weight' => 0.20,
                'type' => 'plan_div_actual',
                'rule' => 'KH / TH. Ít ngày hơn là tốt.',
                'source' => 'Báo cáo tiến độ hoàn thành',
            ],
            2 => [
                'name' => 'Chất lượng công trình',
                'subject' => 'Đội ngũ thi công',
                'unit' => 'Lỗi',
                'weight' => 0.10,
                'type' => 'minus_quality',
                'rule' => 'Mỗi lỗi bị trừ theo setting.',
                'source' => 'Báo cáo kiểm tra chất lượng',
            ],
            3 => [
                'name' => 'An toàn lao động',
                'subject' => 'An toàn lao động',
                'unit' => 'Sự cố',
                'weight' => 0.10,
                'type' => 'minus_safety',
                'rule' => 'Mỗi sự cố bị trừ theo setting.',
                'source' => 'Báo cáo tai nạn lao động',
            ],
            4 => [
                'name' => 'Mức độ hài lòng khách hàng',
                'subject' => 'Khách hàng / QA',
                'unit' => 'Feedback',
                'weight' => 0.10,
                'type' => 'customer_feedback',
                'rule' => 'Feedback không tốt trừ, trung lập không cộng/trừ, feedback tốt cộng.',
                'source' => 'Feedback khách hàng',
            ],
            5 => [
                'name' => 'Kiểm tra bảo hành định kỳ',
                'subject' => 'Bộ phận bảo trì',
                'unit' => 'Lần',
                'weight' => 0.10,
                'type' => 'actual_div_plan',
                'rule' => 'TH / KH.',
                'source' => 'Báo cáo lịch bảo hành',
            ],
            6 => [
                'name' => 'Số giờ làm thêm',
                'subject' => 'Quản lý nhân sự',
                'unit' => 'Giờ',
                'weight' => 0.10,
                'type' => 'ot_rule',
                'rule' => 'OT càng ít càng tốt.',
                'source' => 'Báo cáo chấm công / OT',
            ],
            7 => [
                'name' => 'Công trình hỗ trợ chốt thành công',
                'subject' => 'Kinh doanh + kỹ thuật',
                'unit' => 'Công trình',
                'weight' => 0.10,
                'type' => 'success_project',
                'rule' => 'Mỗi công trình cộng theo setting, có trần.',
                'source' => 'Báo cáo doanh thu',
            ],
            8 => [
                'name' => 'Bảo quản máy móc, thiết bị',
                'subject' => 'Quản lý thiết bị',
                'unit' => 'Hư hỏng',
                'weight' => 0.10,
                'type' => 'minus_equipment',
                'rule' => 'Mỗi hư hỏng/mất mát bị trừ theo setting.',
                'source' => 'Báo cáo quản lý thiết bị',
            ],
            9 => [
                'name' => 'Tuân thủ quy định chấm công',
                'subject' => 'Quản lý nhân sự',
                'unit' => 'Ngày công',
                'weight' => 0.10,
                'type' => 'actual_div_plan',
                'rule' => 'Ngày công thực tế / ngày công chuẩn.',
                'source' => 'Báo cáo chấm công',
            ],
        ];
    }

    /**
     * Tính tỷ lệ đạt của một chỉ tiêu KPI theo loại công thức.
     */
    private function calculateRate(string $type, float $plan, float $actual, array $settings, float $customerFeedbackRate): float
    {
        if ($type === 'plan_div_actual') {
            return $actual == 0 ? 0 : $plan / $actual;
        }

        if ($type === 'actual_div_plan') {
            return $plan == 0 ? 0 : $actual / $plan;
        }

        if ($type === 'minus_quality') {
            return max(0, 1 - (($settings['quality_error_penalty'] ?? 0.05) * $actual));
        }

        if ($type === 'minus_safety') {
            return max(0, 1 - (($settings['safety_error_penalty'] ?? 0.05) * $actual));
        }

        if ($type === 'minus_equipment') {
            return max(0, 1 - (($settings['equipment_error_penalty'] ?? 0.05) * $actual));
        }

        if ($type === 'success_project') {
            return min(
                ($settings['kpi_max_rate'] ?? 1.3),
                $actual * ($settings['success_project_bonus'] ?? 0.1)
            );
        }

        if ($type === 'customer_feedback') {
            return $customerFeedbackRate;
        }

        if ($type === 'ot_rule') {
            if ($actual == 0) {
                return 1;
            }

            if ($plan == 0) {
                return max(0, 1 - ($actual * 0.02));
            }

            return $plan / $actual;
        }

        return 0;
    }

    /**
     * Tính toàn bộ bảng lương KPI: điểm từng chỉ tiêu, tổng KPI, lương cứng và lương KPI thực nhận.
     */
    private function calculate(Request $request, $employee): array
    {
        $settings = $this->settingsMap();

        $baseRate = (float) ($settings['base_salary_rate'] ?? 0.7);
        $kpiRate = (float) ($settings['kpi_salary_rate'] ?? 0.3);
        $grossSalary = (float) $request->input('gross_salary', 0);

        $badFeedback = (int) $request->input('feedback_bad_count', 0);
        $neutralFeedback = (int) $request->input('feedback_neutral_count', 0);
        $goodFeedback = (int) $request->input('feedback_good_count', 0);

        $badPenalty = (float) ($settings['bad_feedback_penalty'] ?? 0.1);
        $goodBonus = (float) ($settings['good_feedback_bonus'] ?? 0.05);
        $feedbackMax = (float) ($settings['customer_feedback_max'] ?? 1.3);

        $customerFeedbackRate = max(
            0,
            min(
                $feedbackMax,
                1 + ($goodFeedback * $goodBonus) - ($badFeedback * $badPenalty)
            )
        );

        $itemsInput = $request->input('kpis', []);
        $kpis = $this->kpiTemplate();

        $totalScore = 0;
        $totalWeight = 0;
        $items = [];

        foreach ($kpis as $index => $kpi) {
            $plan = (float) data_get($itemsInput, $index.'.plan', 0);
            $actual = (float) data_get($itemsInput, $index.'.actual', 0);

            if ($kpi['type'] === 'customer_feedback') {
                $plan = $badFeedback + $neutralFeedback + $goodFeedback;
                $actual = $goodFeedback - $badFeedback;
            }

            $rate = $this->calculateRate(
                $kpi['type'],
                $plan,
                $actual,
                $settings,
                $customerFeedbackRate
            );

            $score = $rate * $kpi['weight'];

            if ($rate >= 1) {
                $rating = 'Đạt';
            } elseif ($rate >= 0.9) {
                $rating = 'Gần đạt';
            } else {
                $rating = 'Chưa đạt';
            }

            $items[] = [
                'sort_order' => $index,
                'kpi_name' => $kpi['name'],
                'subject_name' => $kpi['subject'],
                'unit_name' => $kpi['unit'],
                'plan_value' => $plan,
                'actual_value' => $actual,
                'achievement_rate' => $rate,
                'weight' => $kpi['weight'],
                'kpi_score' => $score,
                'rating' => $rating,
                'rule_note' => $kpi['rule'],
                'data_source' => $kpi['source'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $totalScore += $score;
            $totalWeight += $kpi['weight'];
        }

        $totalKpiPercent = $totalWeight > 0 ? $totalScore / $totalWeight : 0;
        $totalKpiPercent = min(($settings['kpi_max_rate'] ?? 1.3), $totalKpiPercent);

        $baseSalary = $grossSalary * $baseRate;
        $kpiBaseSalary = $grossSalary * $kpiRate;
        $realKpiSalary = $kpiBaseSalary * $totalKpiPercent;
        $kpiDifference = $realKpiSalary - $kpiBaseSalary;
        $totalIncome = $baseSalary + $realKpiSalary;

        return [
            'settings' => $settings,
            'grossSalary' => $grossSalary,
            'baseRate' => $baseRate,
            'kpiRate' => $kpiRate,
            'badFeedback' => $badFeedback,
            'neutralFeedback' => $neutralFeedback,
            'goodFeedback' => $goodFeedback,
            'customerFeedbackRate' => $customerFeedbackRate,
            'totalKpiPercent' => $totalKpiPercent,
            'baseSalary' => $baseSalary,
            'kpiBaseSalary' => $kpiBaseSalary,
            'realKpiSalary' => $realKpiSalary,
            'kpiDifference' => $kpiDifference,
            'totalIncome' => $totalIncome,
            'items' => $items,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    /**
     * Trang tổng quan lương KPI kỹ thuật: cài đặt, nhân viên, danh sách bảng lương.
     */
    public function index(Request $request)
    {
        $this->ensureDefaultSettings();

        $settings = $this->settingsMap();
        $employees = $this->employees();

        if ($this->tableExists('technical_kpi_payrolls')) {
            $payrolls = DB::table('technical_kpi_payrolls')
                ->orderByDesc('id')
                ->limit(50)
                ->get();

            $summary = [
                'total_income' => (float) DB::table('technical_kpi_payrolls')->sum('total_income'),
                'total_records' => (int) DB::table('technical_kpi_payrolls')->count(),
                'avg_kpi' => (float) DB::table('technical_kpi_payrolls')->avg('total_kpi_percent'),
                'pending' => $this->hasColumn('technical_kpi_payrolls', 'status')
                    ? (int) DB::table('technical_kpi_payrolls')->where('status', 'draft')->count()
                    : 0,
            ];
        } else {
            $payrolls = collect();

            $summary = [
                'total_income' => 0,
                'total_records' => 0,
                'avg_kpi' => 0,
                'pending' => 0,
            ];
        }

        return view('kythuat.luong', compact(
            'settings',
            'employees',
            'payrolls',
            'summary'
        ));
    }

    /**
     * Trang cài đặt hệ số KPI kỹ thuật.
     */
    public function settings()
    {
        $this->ensureDefaultSettings();

        $settings = $this->tableExists('technical_kpi_settings')
            ? DB::table('technical_kpi_settings')->orderBy('id')->get()
            : collect();

        return view('kythuat.luong_settings', compact('settings'));
    }

    /**
     * Lưu cài đặt KPI: cập nhật, xóa và thêm dòng cài đặt mới.
     */
    public function saveSettings(Request $request)
    {
        if (! $this->tableExists('technical_kpi_settings')) {
            return back()->with('error', 'Chưa có bảng technical_kpi_settings trong database.');
        }

        foreach ($request->input('settings', []) as $key => $row) {
            $valuePercent = (float) ($row['value'] ?? 0);
            $valueDecimal = $valuePercent / 100;

            DB::table('technical_kpi_settings')
                ->where('setting_key', $key)
                ->update([
                    'setting_label' => $row['label'] ?? $key,
                    'setting_value' => $valueDecimal,
                    'note' => $row['note'] ?? null,
                    'updated_at' => now(),
                ]);
        }

        foreach ($request->input('delete_settings', []) as $key => $shouldDelete) {
            if ($shouldDelete) {
                DB::table('technical_kpi_settings')
                    ->where('setting_key', $key)
                    ->delete();
            }
        }

        foreach ($request->input('new_settings', []) as $row) {
            $label = trim($row['label'] ?? '');
            $key = trim($row['key'] ?? '');
            $valuePercent = (float) ($row['value'] ?? 0);
            $note = trim($row['note'] ?? '');

            if ($label === '') {
                continue;
            }

            if ($key === '') {
                $key = 'custom_'.Str::slug($label, '_');
            }

            $key = Str::slug($key, '_');

            if ($key === '') {
                $key = 'custom_setting_'.time();
            }

            DB::table('technical_kpi_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_label' => $label,
                    'setting_value' => $valuePercent / 100,
                    'setting_unit' => '%',
                    'note' => $note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return redirect()
            ->route('ky-thuat.luong.settings')
            ->with('success', 'Đã cập nhật cài đặt KPI.');
    }

    /*
    |--------------------------------------------------------------------------
    | Store / Update
    |--------------------------------------------------------------------------
    */

    /**
     * Tạo bảng lương KPI mới cho nhân viên kỹ thuật kèm chi tiết từng chỉ tiêu.
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'payroll_month' => 'required|string|max:7',
            'gross_salary' => 'required|numeric|min:0',
        ]);

        if (! $this->tableExists('technical_kpi_payrolls')) {
            return back()->with('error', 'Chưa có bảng technical_kpi_payrolls trong database.');
        }

        $employee = $this->employeeById($request->user_id);

        if (! $employee) {
            return back()->with('error', 'Không tìm thấy nhân viên kỹ thuật.');
        }

        $calc = $this->calculate($request, $employee);

        $payrollData = [
            'user_id' => $employee->id,
            'employee_name' => $employee->name,
            'position_name' => $employee->position_name,
            'payroll_month' => $request->payroll_month,
            'month_label' => $request->month_label,
            'gross_salary' => $calc['grossSalary'],
            'base_rate' => $calc['baseRate'],
            'kpi_rate' => $calc['kpiRate'],
            'feedback_bad_count' => $calc['badFeedback'],
            'feedback_neutral_count' => $calc['neutralFeedback'],
            'feedback_good_count' => $calc['goodFeedback'],
            'customer_feedback_rate' => $calc['customerFeedbackRate'],
            'total_kpi_percent' => $calc['totalKpiPercent'],
            'base_salary' => $calc['baseSalary'],
            'kpi_base_salary' => $calc['kpiBaseSalary'],
            'real_kpi_salary' => $calc['realKpiSalary'],
            'kpi_difference' => $calc['kpiDifference'],
            'total_income' => $calc['totalIncome'],
            'note' => $request->note,
            'status' => 'draft',
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $payrollId = DB::table('technical_kpi_payrolls')
            ->insertGetId($this->filterColumns('technical_kpi_payrolls', $payrollData));

        if ($this->tableExists('technical_kpi_payroll_items')) {
            foreach ($calc['items'] as $item) {
                $item['payroll_id'] = $payrollId;

                DB::table('technical_kpi_payroll_items')
                    ->insert($this->filterColumns('technical_kpi_payroll_items', $item));
            }
        }

        return redirect()
            ->route('ky-thuat.luong.show', $payrollId)
            ->with('success', 'Đã lưu bảng lương KPI kỹ thuật.');
    }

    /**
     * Tính lại và cập nhật bảng lương KPI, thay toàn bộ dòng chi tiết.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'payroll_month' => 'required|string|max:7',
            'gross_salary' => 'required|numeric|min:0',
        ]);

        if (! $this->tableExists('technical_kpi_payrolls')) {
            return back()->with('error', 'Chưa có bảng technical_kpi_payrolls trong database.');
        }

        $employee = $this->employeeById($request->user_id);

        if (! $employee) {
            return back()->with('error', 'Không tìm thấy nhân viên kỹ thuật.');
        }

        $calc = $this->calculate($request, $employee);

        $payrollData = [
            'user_id' => $employee->id,
            'employee_name' => $employee->name,
            'position_name' => $employee->position_name,
            'payroll_month' => $request->payroll_month,
            'month_label' => $request->month_label,
            'gross_salary' => $calc['grossSalary'],
            'base_rate' => $calc['baseRate'],
            'kpi_rate' => $calc['kpiRate'],
            'feedback_bad_count' => $calc['badFeedback'],
            'feedback_neutral_count' => $calc['neutralFeedback'],
            'feedback_good_count' => $calc['goodFeedback'],
            'customer_feedback_rate' => $calc['customerFeedbackRate'],
            'total_kpi_percent' => $calc['totalKpiPercent'],
            'base_salary' => $calc['baseSalary'],
            'kpi_base_salary' => $calc['kpiBaseSalary'],
            'real_kpi_salary' => $calc['realKpiSalary'],
            'kpi_difference' => $calc['kpiDifference'],
            'total_income' => $calc['totalIncome'],
            'note' => $request->note,
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ];

        DB::table('technical_kpi_payrolls')
            ->where('id', $id)
            ->update($this->filterColumns('technical_kpi_payrolls', $payrollData));

        if ($this->tableExists('technical_kpi_payroll_items')) {
            DB::table('technical_kpi_payroll_items')
                ->where('payroll_id', $id)
                ->delete();

            foreach ($calc['items'] as $item) {
                $item['payroll_id'] = $id;

                DB::table('technical_kpi_payroll_items')
                    ->insert($this->filterColumns('technical_kpi_payroll_items', $item));
            }
        }

        return redirect()
            ->route('ky-thuat.luong.show', $id)
            ->with('success', 'Đã cập nhật bảng lương KPI kỹ thuật.');
    }

    /*
    |--------------------------------------------------------------------------
    | Show / Edit / Approve / Delete
    |--------------------------------------------------------------------------
    */

    /**
     * Xem chi tiết một bảng lương KPI.
     */
    public function show($id)
    {
        if (! $this->tableExists('technical_kpi_payrolls')) {
            abort(404);
        }

        $payroll = DB::table('technical_kpi_payrolls')
            ->where('id', $id)
            ->first();

        abort_if(! $payroll, 404);

        $items = $this->tableExists('technical_kpi_payroll_items')
            ? DB::table('technical_kpi_payroll_items')
                ->where('payroll_id', $id)
                ->orderBy('sort_order')
                ->get()
            : collect();

        return view('kythuat.luong_show', compact('payroll', 'items'));
    }

    /**
     * Form sửa bảng lương KPI kèm cài đặt và danh sách nhân viên.
     */
    public function edit($id)
    {
        if (! $this->tableExists('technical_kpi_payrolls')) {
            abort(404);
        }

        $payroll = DB::table('technical_kpi_payrolls')
            ->where('id', $id)
            ->first();

        abort_if(! $payroll, 404);

        $items = $this->tableExists('technical_kpi_payroll_items')
            ? DB::table('technical_kpi_payroll_items')
                ->where('payroll_id', $id)
                ->orderBy('sort_order')
                ->get()
            : collect();

        $settings = $this->settingsMap();
        $employees = $this->employees();

        return view('kythuat.luong_edit', compact(
            'payroll',
            'items',
            'settings',
            'employees'
        ));
    }

    /**
     * Duyệt bảng lương KPI (chuyển trạng thái approved).
     */
    public function approve($id)
    {
        if (! $this->tableExists('technical_kpi_payrolls')) {
            abort(404);
        }

        $data = [
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('technical_kpi_payrolls')
            ->where('id', $id)
            ->update($this->filterColumns('technical_kpi_payrolls', $data));

        return back()->with('success', 'Đã duyệt bảng lương KPI.');
    }

    /**
     * Xóa một bảng lương KPI.
     */
    public function destroy($id)
    {
        if (! $this->tableExists('technical_kpi_payrolls')) {
            abort(404);
        }

        DB::table('technical_kpi_payrolls')
            ->where('id', $id)
            ->delete();

        return redirect()
            ->route('ky-thuat.luong.index')
            ->with('success', 'Đã xóa bảng lương KPI.');
    }

    /**
     * Lưu danh sách dòng KPI chi tiết tùy chỉnh (thêm/sửa/xóa), chỉ cho admin/kế toán/manager.
     */
    public function saveKpiItems(\Illuminate\Http\Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'accounting', 'manager']), 403);

        if (! \Illuminate\Support\Facades\Schema::hasTable('technical_payroll_kpi_items')) {
            return back()->with('error', 'Chưa có bảng technical_payroll_kpi_items. Hãy chạy migration trước.');
        }

        $items = $request->input('kpi_items', []);
        $now = now();

        foreach ($items as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : null;
            $delete = (int) ($row['delete'] ?? 0) === 1;

            if ($delete && $id) {
                \Illuminate\Support\Facades\DB::table('technical_payroll_kpi_items')
                    ->where('id', $id)
                    ->delete();

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $payload = [
                'name' => $name,
                'unit' => trim((string) ($row['unit'] ?? '')),
                'plan_value' => (float) ($row['plan_value'] ?? 0),
                'actual_value' => (float) ($row['actual_value'] ?? 0),
                'weight' => max(0, (float) ($row['weight_percent'] ?? 0) / 100),
                'calc_type' => trim((string) ($row['calc_type'] ?? 'actual_div_plan')),
                'note' => trim((string) ($row['note'] ?? '')),
                'sort_order' => max(1, (int) ($row['sort_order'] ?? 1)),
                'is_enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
                'updated_at' => $now,
            ];

            if ($id) {
                \Illuminate\Support\Facades\DB::table('technical_payroll_kpi_items')
                    ->where('id', $id)
                    ->update($payload);
            } else {
                $payload['created_at'] = $now;
                \Illuminate\Support\Facades\DB::table('technical_payroll_kpi_items')
                    ->insert($payload);
            }
        }

        return back()->with('success', 'Đã cập nhật danh sách dòng KPI chi tiết.');
    }

    /**
     * Xóa một dòng KPI chi tiết, hỗ trợ trả JSON cho request AJAX.
     */
    public function destroyKpiItem($id)
    {
        abort_unless(auth()->user()->hasAnyRole(['admin', 'accounting', 'manager']), 403);

        if (! \Illuminate\Support\Facades\Schema::hasTable('technical_payroll_kpi_items')) {
            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Chưa có bảng technical_payroll_kpi_items.',
                ], 422);
            }

            return back()->with('error', 'Chưa có bảng technical_payroll_kpi_items.');
        }

        $item = \Illuminate\Support\Facades\DB::table('technical_payroll_kpi_items')
            ->where('id', (int) $id)
            ->first();

        if (! $item) {
            if (request()->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Dòng KPI không tồn tại hoặc đã bị xóa.',
                ], 404);
            }

            return back()->with('error', 'Dòng KPI không tồn tại hoặc đã bị xóa.');
        }

        \Illuminate\Support\Facades\DB::table('technical_payroll_kpi_items')
            ->where('id', (int) $id)
            ->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Đã xóa dòng KPI.',
            ]);
        }

        return back()->with('success', 'Đã xóa dòng KPI.');
    }
}
