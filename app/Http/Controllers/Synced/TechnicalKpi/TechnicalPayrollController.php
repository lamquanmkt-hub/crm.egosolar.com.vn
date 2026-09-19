<?php

namespace App\Http\Controllers\Synced\TechnicalKpi;

use App\Http\Controllers\Controller;
use App\Services\TechnicalKpi\ProjectKpiLinkService;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        return SchemaCache::hasTable($table);
    }

    /**
     * Kiểm tra bảng có cột chỉ định không.
     */
    private function hasColumn(string $table, string $column): bool
    {
        return SchemaCache::hasTable($table) && SchemaCache::hasColumn($table, $column);
    }

    /**
     * Lọc mảng dữ liệu chỉ giữ các key trùng với cột của bảng.
     */
    private function filterColumns(string $table, array $data): array
    {
        if (! SchemaCache::hasTable($table)) {
            return $data;
        }

        $columns = SchemaCache::columns($table);

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
     * Danh sách nhân sự Kỹ thuật đang hoạt động.
     *
     * Không phụ thuộc duy nhất vào role "technical" vì CRM đang dùng song song
     * ky_thuat / technical / technician / technical_staff...
     * Đồng thời nhận diện theo phòng ban/chức vụ để nhân sự HR đã xếp vào
     * phòng Kỹ thuật vẫn xuất hiện dù role chưa được chuẩn hoá.
     */
    private function employees()
    {
        if (! $this->tableExists('users')) {
            return collect();
        }

        $technicalRoles = [
            'ky_thuat',
            'technical',
            'technician',
            'technical_staff',
            'engineer',
            'technical_leader',
            'technical_manager',
            'truong_phong_ky_thuat',
        ];

        $hasRoleClassifier = $this->tableExists('roles')
            && $this->tableExists('model_has_roles');

        $hasDepartmentClassifier = $this->tableExists('departments')
            && $this->hasColumn('users', 'department_id')
            && $this->hasColumn('departments', 'id');

        $hasPositionClassifier = $this->tableExists('positions')
            && $this->hasColumn('users', 'position_id')
            && $this->hasColumn('positions', 'id');

        if (! $hasRoleClassifier && ! $hasDepartmentClassifier && ! $hasPositionClassifier) {
            return collect();
        }

        $query = DB::table('users');

        if ($hasPositionClassifier) {
            $query->leftJoin('positions', 'positions.id', '=', 'users.position_id');
            $positionSelect = DB::raw('COALESCE(positions.name, "Kỹ thuật") as position_name');
        } else {
            $positionSelect = DB::raw('"Kỹ thuật" as position_name');
        }

        $query->where(function ($scope) use (
            $technicalRoles,
            $hasRoleClassifier,
            $hasDepartmentClassifier,
            $hasPositionClassifier
        ) {
            $hasCondition = false;

            if ($hasRoleClassifier) {
                $scope->whereExists(function ($roleQuery) use ($technicalRoles) {
                    $roleQuery->selectRaw('1')
                        ->from('model_has_roles as mhr')
                        ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                        ->whereColumn('mhr.model_id', 'users.id')
                        ->where('mhr.model_type', \App\Models\User::class)
                        ->whereIn('r.name', $technicalRoles);
                });
                $hasCondition = true;
            }

            if ($hasDepartmentClassifier) {
                $method = $hasCondition ? 'orWhereExists' : 'whereExists';
                $scope->{$method}(function ($departmentQuery) {
                    $departmentQuery->selectRaw('1')
                        ->from('departments as d')
                        ->whereColumn('d.id', 'users.department_id')
                        ->where(function ($nameQuery) {
                            $nameQuery
                                ->where('d.name', 'like', '%Kỹ thuật%')
                                ->orWhere('d.name', 'like', '%Ky thuat%')
                                ->orWhere('d.name', 'like', '%Technical%')
                                ->orWhere('d.code', 'like', '%ky_thuat%')
                                ->orWhere('d.code', 'like', '%technical%');
                        });
                });
                $hasCondition = true;
            }

            if ($hasPositionClassifier) {
                $method = $hasCondition ? 'orWhere' : 'where';
                $scope->{$method}(function ($positionQuery) {
                    $positionQuery
                        ->where('positions.name', 'like', '%Kỹ thuật%')
                        ->orWhere('positions.name', 'like', '%Ky thuat%')
                        ->orWhere('positions.name', 'like', '%Technical%')
                        ->orWhere('positions.name', 'like', '%Engineer%');
                });
            }
        });

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
            ->distinct()
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
     * Bộ KPI động dùng thống nhất cho Cấu hình -> Chấm KPI -> Dashboard -> Phiếu lương.
     * Khi đã có bảng cấu hình, số tiêu chí không bị giới hạn 5 dòng.
     */
    private function kpiTemplate(): array
    {
        $defaults = [
            'default_1' => [
                'definition_id' => null,
                'key' => 'default_1',
                'sort_order' => 1,
                'name' => 'Tiến độ hoàn thành lắp đặt hệ thống',
                'subject' => 'Tiến độ',
                'unit' => 'Công trình',
                'weight' => 0.30,
                'type' => 'actual_div_plan',
                'rule' => 'TH / KH. TH là số công trình hoàn thành đúng hạn; KH là tổng công trình đến hạn trong kỳ.',
                'source' => 'Tiến độ / nghiệm thu công trình',
                'default_plan' => 1,
                'default_actual' => 1,
            ],
            'default_2' => [
                'definition_id' => null,
                'key' => 'default_2',
                'sort_order' => 2,
                'name' => 'Chất lượng thi công & thẩm mỹ',
                'subject' => 'Chất lượng',
                'unit' => 'Công trình',
                'weight' => 0.25,
                'type' => 'actual_div_plan',
                'rule' => 'TH / KH. TH là số công trình nghiệm thu đạt ngay lần đầu; KH là tổng công trình nghiệm thu.',
                'source' => 'Biên bản nghiệm thu / phản hồi khách hàng',
                'default_plan' => 1,
                'default_actual' => 1,
            ],
            'default_3' => [
                'definition_id' => null,
                'key' => 'default_3',
                'sort_order' => 3,
                'name' => 'Khảo sát kỹ thuật & khối lượng',
                'subject' => 'Khảo sát / Vật tư',
                'unit' => '% hao hụt',
                'weight' => 0.15,
                'type' => 'material_waste',
                'rule' => '0% = 120%; >0–2% = 100%; >2–4% = 85%; >4–6% = 70%; >6% = 0%.',
                'source' => 'Vật tư xuất kho - vật tư hoàn trả nguyên vẹn',
                'default_plan' => 0,
                'default_actual' => 0,
            ],
            'default_4' => [
                'definition_id' => null,
                'key' => 'default_4',
                'sort_order' => 4,
                'name' => 'An toàn lao động (HSE) & vệ sinh',
                'subject' => 'HSE',
                'unit' => 'Công trình',
                'weight' => 0.15,
                'type' => 'actual_div_plan',
                'rule' => 'TH / KH. TH là số công trình đạt checklist HSE; vi phạm nghiêm trọng có thể trừ thêm 10–20 điểm KPI.',
                'source' => 'Checklist HSE / biên bản sự cố',
                'default_plan' => 1,
                'default_actual' => 1,
            ],
            'default_5' => [
                'definition_id' => null,
                'key' => 'default_5',
                'sort_order' => 5,
                'name' => 'Hỗ trợ thủ tục EVN & cài đặt App',
                'subject' => 'EVN / App',
                'unit' => 'Công trình',
                'weight' => 0.15,
                'type' => 'actual_div_plan',
                'rule' => 'TH / KH. TH là số công trình hoàn tất các hạng mục EVN/App cần thực hiện; KH là số công trình có yêu cầu.',
                'source' => 'Nghiệm thu / bàn giao / cấu hình App',
                'default_plan' => 1,
                'default_actual' => 1,
            ],
        ];

        if (! $this->tableExists('technical_payroll_kpi_items')) {
            return $defaults;
        }

        $rows = DB::table('technical_payroll_kpi_items')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return $defaults;
        }

        $allowedTypes = [
            'actual_div_plan',
            'plan_div_actual',
            'minus_quality',
            'minus_safety',
            'material_waste',
        ];

        return $rows->mapWithKeys(function ($row, $idx) use ($allowedTypes) {
            $definitionId = (int) $row->id;
            $key = 'cfg_'.$definitionId;
            $name = trim((string) ($row->name ?? '')) ?: 'Tiêu chí KPI '.($idx + 1);
            $type = in_array((string) ($row->calc_type ?? ''), $allowedTypes, true)
                ? (string) $row->calc_type
                : 'actual_div_plan';
            $sourceCode = $this->hasColumn('technical_payroll_kpi_items', 'source_code')
                ? (trim((string) ($row->source_code ?? ProjectKpiLinkService::SOURCE_MANUAL)) ?: ProjectKpiLinkService::SOURCE_MANUAL)
                : ProjectKpiLinkService::SOURCE_MANUAL;
            $sourceLabel = ProjectKpiLinkService::sourceOptions()[$sourceCode] ?? 'Cấu hình KPI kỹ thuật';

            return [
                $key => [
                    'definition_id' => $definitionId,
                    'key' => $key,
                    'sort_order' => (int) ($row->sort_order ?? ($idx + 1)),
                    'name' => $name,
                    'subject' => $name,
                    'unit' => trim((string) ($row->unit ?? '')),
                    'weight' => (float) ($row->weight ?? 0),
                    'type' => $type,
                    'source_code' => $sourceCode,
                    'rule' => trim((string) ($row->note ?? '')),
                    'source' => $sourceLabel,
                    'default_plan' => (float) ($row->plan_value ?? 0),
                    'default_actual' => (float) ($row->actual_value ?? 0),
                ],
            ];
        })->toArray();
    }

    /**
     * Tổng trọng số của bộ KPI hiện hành.
     */
    private function kpiWeightTotal(array $kpis): float
    {
        return (float) collect($kpis)->sum(fn ($kpi) => (float) ($kpi['weight'] ?? 0));
    }

    /**
     * Không âm thầm quay về bộ mặc định khi cấu hình sai trọng số.
     * Người quản trị phải sửa cấu hình về đúng 100% trước khi chấm/cập nhật KPI.
     */
    private function assertValidKpiTemplate(array $kpis): void
    {
        if (empty($kpis)) {
            throw ValidationException::withMessages([
                'kpis' => 'Chưa có tiêu chí KPI đang hoạt động.',
            ]);
        }

        $weightTotal = $this->kpiWeightTotal($kpis);
        if (abs($weightTotal - 1.0) > 0.0001) {
            throw ValidationException::withMessages([
                'kpis' => 'Tổng trọng số KPI hiện tại là '.number_format($weightTotal * 100, 2, ',', '.').'%. Hãy vào Cấu hình KPI và chỉnh tổng trọng số về đúng 100% trước khi chấm KPI.',
            ]);
        }
    }

    /**
     * Tạo template từ snapshot của một phiếu KPI đã lưu để khi sửa lịch sử không bị lệch
     * theo cấu hình KPI mới ở các tháng sau.
     */
    private function payrollKpiTemplate(int $payrollId): array
    {
        if (! $this->tableExists('technical_kpi_payroll_items')) {
            return $this->kpiTemplate();
        }

        $rows = DB::table('technical_kpi_payroll_items')
            ->where('payroll_id', $payrollId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return $this->kpiTemplate();
        }

        $current = collect($this->kpiTemplate());

        return $rows->mapWithKeys(function ($row, $idx) use ($current) {
            $definitionId = $this->hasColumn('technical_kpi_payroll_items', 'kpi_definition_id')
                ? (int) ($row->kpi_definition_id ?? 0)
                : 0;

            $currentMatch = null;
            if ($definitionId > 0) {
                $currentMatch = $current->first(fn ($kpi) => (int) ($kpi['definition_id'] ?? 0) === $definitionId);
            }

            if (! $currentMatch) {
                $savedName = Str::lower(Str::ascii(trim((string) ($row->kpi_name ?? ''))));
                $currentMatch = $current->first(function ($kpi) use ($savedName) {
                    return Str::lower(Str::ascii(trim((string) ($kpi['name'] ?? '')))) === $savedName;
                });
            }

            $calcType = $this->hasColumn('technical_kpi_payroll_items', 'calc_type')
                ? trim((string) ($row->calc_type ?? ''))
                : '';
            if ($calcType === '') {
                $calcType = (string) data_get($currentMatch, 'type', 'actual_div_plan');
            }

            $key = 'saved_'.(int) $row->id;

            return [
                $key => [
                    'definition_id' => $definitionId ?: data_get($currentMatch, 'definition_id'),
                    'key' => $key,
                    'sort_order' => (int) ($row->sort_order ?? ($idx + 1)),
                    'name' => (string) ($row->kpi_name ?? data_get($currentMatch, 'name', 'KPI '.($idx + 1))),
                    'subject' => (string) ($row->subject_name ?? data_get($currentMatch, 'subject', 'KPI '.($idx + 1))),
                    'unit' => (string) ($row->unit_name ?? data_get($currentMatch, 'unit', '')),
                    'weight' => (float) ($row->weight ?? data_get($currentMatch, 'weight', 0)),
                    'type' => $calcType,
                    'source_code' => $this->hasColumn('technical_kpi_payroll_items', 'source_code')
                        ? (trim((string) ($row->source_code ?? 'manual')) ?: 'manual')
                        : (string) data_get($currentMatch, 'source_code', 'manual'),
                    'rule' => (string) ($row->rule_note ?? data_get($currentMatch, 'rule', '')),
                    'source' => (string) ($row->data_source ?? data_get($currentMatch, 'source', 'Snapshot KPI')),
                    'default_plan' => (float) ($row->plan_value ?? 0),
                    'default_actual' => (float) ($row->actual_value ?? 0),
                ],
            ];
        })->toArray();
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
            return $plan == 0 ? 0 : min(1, $actual / $plan);
        }

        if ($type === 'material_waste') {
            if ($actual <= 0) {
                return 1.20;
            }

            if ($actual <= 2) {
                return 1.00;
            }

            if ($actual <= 4) {
                return 0.85;
            }

            if ($actual <= 6) {
                return 0.70;
            }

            return 0;
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
    private function calculate(Request $request, $employee, ?array $template = null): array
    {
        $settings = $this->settingsMap();

        $baseRate = (float) ($settings['base_salary_rate'] ?? 0.7);
        $kpiRate = (float) ($settings['kpi_salary_rate'] ?? 0.3);
        $grossSalary = (float) $request->input('gross_salary', 0);

        $badFeedback = (int) $request->input('feedback_bad_count', 0);
        $neutralFeedback = (int) $request->input('feedback_neutral_count', 0);
        $goodFeedback = (int) $request->input('feedback_good_count', 0);

        $projectMetrics = app(ProjectKpiLinkService::class)->metricsForUserMonth(
            (int) ($employee->id ?? 0),
            (string) $request->input('payroll_month', now()->format('Y-m'))
        );
        $manualPenaltyPoints = max(0, min(100, (float) $request->input('penalty_points', 0)));
        $projectPenaltyPoints = max(0, min(100, (float) ($projectMetrics['project_penalty_points'] ?? 0)));
        // Điểm phạt công trình là nguồn có bằng chứng; dùng mức cao hơn để tránh cộng trùng nếu quản lý đã nhập tay cùng lỗi.
        $penaltyPoints = max($manualPenaltyPoints, $projectPenaltyPoints);

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
        $kpis = $template ?? $this->kpiTemplate();
        $this->assertValidKpiTemplate($kpis);

        $totalScore = 0;
        $totalWeight = 0;
        $items = [];

        foreach ($kpis as $index => $kpi) {
            $plan = (float) data_get($itemsInput, $index.'.plan', $kpi['default_plan'] ?? 0);
            $actual = (float) data_get($itemsInput, $index.'.actual', $kpi['default_actual'] ?? 0);
            $sourceCode = (string) ($kpi['source_code'] ?? ProjectKpiLinkService::SOURCE_MANUAL);
            $sourceMetric = $projectMetrics[$sourceCode] ?? null;

            // Khi nguồn Công trình có dữ liệu, hệ thống lấy số thật và bỏ qua số nhập tay.
            // Nếu chưa có bằng chứng công trình, vẫn giữ số cũ để không làm mất KPI lịch sử/đang vận hành.
            if ($sourceCode !== ProjectKpiLinkService::SOURCE_MANUAL && is_array($sourceMetric) && ! empty($sourceMetric['available'])) {
                $plan = (float) ($sourceMetric['plan'] ?? $plan);
                $actual = (float) ($sourceMetric['actual'] ?? $actual);
            }

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
                'kpi_definition_id' => $kpi['definition_id'] ?? null,
                'sort_order' => (int) ($kpi['sort_order'] ?? 0),
                'kpi_name' => $kpi['name'],
                'subject_name' => $kpi['subject'],
                'unit_name' => $kpi['unit'],
                'calc_type' => $kpi['type'],
                'source_code' => $sourceCode,
                'plan_value' => $plan,
                'actual_value' => $actual,
                'achievement_rate' => $rate,
                'weight' => $kpi['weight'],
                'kpi_score' => $score,
                'rating' => $rating,
                'rule_note' => $kpi['rule'],
                'data_source' => ($sourceCode !== ProjectKpiLinkService::SOURCE_MANUAL && is_array($sourceMetric) && ! empty($sourceMetric['available']))
                    ? (string) ($sourceMetric['label'] ?? 'Dữ liệu Công trình')
                    : $kpi['source'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $totalScore += $score;
            $totalWeight += $kpi['weight'];
        }

        $totalKpiPercent = $totalWeight > 0 ? $totalScore / $totalWeight : 0;
        $totalKpiPercent = min(($settings['kpi_max_rate'] ?? 1.3), $totalKpiPercent);
        $totalKpiPercent = max(0, $totalKpiPercent - ($penaltyPoints / 100));

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
            'penaltyPoints' => $penaltyPoints,
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
        $kpis = $this->kpiTemplate();
        $kpiConfigWeight = $this->kpiWeightTotal($kpis);
        $kpiConfigValid = ! empty($kpis) && abs($kpiConfigWeight - 1.0) <= 0.0001;

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

        return view('synced.kythuat.luong', compact(
            'settings',
            'employees',
            'payrolls',
            'summary',
            'kpis',
            'kpiConfigWeight',
            'kpiConfigValid'
        ));
    }

    /**
     * Trang KPIs kỹ thuật riêng: dashboard theo tháng, xếp hạng và 5 nhóm KPI Solar.
     *
     * Trang dashboard dùng chung bộ 5 KPI với màn hình chấm KPI và trang cấu hình.
     */
    public function kpis(Request $request)
    {
        $this->ensureDefaultSettings();

        $selectedMonth = (string) $request->query('month', now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
            $selectedMonth = now()->format('Y-m');
        }

        $selectedUserId = $request->filled('user_id') ? (int) $request->query('user_id') : null;
        $selectedStatus = trim((string) $request->query('status', ''));
        $employees = $this->employees();
        $template = $this->kpiTemplate();
        $kpiConfigWeight = $this->kpiWeightTotal($template);
        $kpiConfigValid = ! empty($template) && abs($kpiConfigWeight - 1.0) <= 0.0001;

        $icons = ['bi-stopwatch', 'bi-gem', 'bi-box-seam', 'bi-shield-check', 'bi-phone', 'bi-clipboard2-check', 'bi-graph-up-arrow', 'bi-stars', 'bi-tools'];
        $kpiCriteria = collect($template)->values()->map(function ($kpi, $idx) use ($icons) {
            $type = (string) ($kpi['type'] ?? 'actual_div_plan');
            $icon = match ($type) {
                'material_waste' => 'bi-box-seam',
                'minus_safety' => 'bi-shield-check',
                'minus_quality' => 'bi-patch-check',
                default => $icons[$idx % count($icons)],
            };

            return [
                'key' => (string) ($kpi['key'] ?? ('kpi_'.$idx)),
                'definition_id' => $kpi['definition_id'] ?? null,
                'name' => (string) ($kpi['name'] ?? 'KPI '.($idx + 1)),
                'subject' => (string) ($kpi['subject'] ?? $kpi['name'] ?? 'KPI '.($idx + 1)),
                'weight' => (float) ($kpi['weight'] ?? 0) * 100,
                'icon' => $icon,
                'standard' => (string) ($kpi['rule'] ?? ''),
                'type' => $type,
                'sort_order' => (int) ($kpi['sort_order'] ?? ($idx + 1)),
            ];
        })->all();

        $payrolls = collect();

        if ($this->tableExists('technical_kpi_payrolls')) {
            $query = DB::table('technical_kpi_payrolls');

            if ($this->hasColumn('technical_kpi_payrolls', 'payroll_month')) {
                $query->where('payroll_month', $selectedMonth);
            }
            if ($selectedUserId && $this->hasColumn('technical_kpi_payrolls', 'user_id')) {
                $query->where('user_id', $selectedUserId);
            }
            if (
                in_array($selectedStatus, ['draft', 'approved'], true)
                && $this->hasColumn('technical_kpi_payrolls', 'status')
            ) {
                $query->where('status', $selectedStatus);
            }

            $query->orderByDesc($this->hasColumn('technical_kpi_payrolls', 'total_kpi_percent') ? 'total_kpi_percent' : 'id');
            $payrolls = $query->limit(200)->get();
        }

        $itemsByPayroll = collect();
        if ($payrolls->isNotEmpty() && $this->tableExists('technical_kpi_payroll_items')) {
            $itemsByPayroll = DB::table('technical_kpi_payroll_items')
                ->whereIn('payroll_id', $payrolls->pluck('id')->all())
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->groupBy('payroll_id');
        }

        $projectDashboard = app(ProjectKpiLinkService::class)->dashboardForPayrolls($payrolls, $selectedMonth);

        foreach ($payrolls as $row) {
            $savedItems = $itemsByPayroll->get($row->id, collect());
            $breakdown = [];

            foreach ($kpiCriteria as $criterion) {
                $match = null;
                $definitionId = (int) ($criterion['definition_id'] ?? 0);

                if ($definitionId > 0 && $this->hasColumn('technical_kpi_payroll_items', 'kpi_definition_id')) {
                    $match = $savedItems->first(fn ($item) => (int) ($item->kpi_definition_id ?? 0) === $definitionId);
                }

                if (! $match) {
                    $criterionName = Str::lower(Str::ascii(trim((string) ($criterion['name'] ?? ''))));
                    $match = $savedItems->first(function ($item) use ($criterionName) {
                        return Str::lower(Str::ascii(trim((string) ($item->kpi_name ?? '')))) === $criterionName;
                    });
                }

                if (! $match) {
                    $sortOrder = (int) ($criterion['sort_order'] ?? 0);
                    $match = $savedItems->first(fn ($item) => (int) ($item->sort_order ?? 0) === $sortOrder);
                }

                $breakdown[$criterion['key']] = $match ? [
                    'name' => (string) ($match->kpi_name ?? $criterion['name']),
                    'score' => (float) ($match->kpi_score ?? 0) * 100,
                    'weight' => (float) ($match->weight ?? 0) * 100,
                    'rate' => (float) ($match->achievement_rate ?? 0) * 100,
                ] : null;
            }

            $row->kpi_breakdown = $breakdown;
            $row->penalty_points = $this->hasColumn('technical_kpi_payrolls', 'penalty_points')
                ? (float) ($row->penalty_points ?? 0)
                : null;

            $projectLink = $projectDashboard[(int) ($row->user_id ?? 0)] ?? [];
            $row->kpi_project_count = (int) ($projectLink['project_count'] ?? 0);
            $row->kpi_project_issue_count = (int) ($projectLink['issue_count'] ?? 0);
            $row->kpi_projects = collect($projectLink['projects'] ?? [])->values();
        }

        /*
         * Dashboard KPI phải hiển thị toàn bộ nhân sự Kỹ thuật, kể cả người chưa
         * có technical_kpi_payrolls trong tháng. Trước đây bảng render trực tiếp
         * từ $payrolls nên tháng mới luôn trống dù HR đã có nhân sự Kỹ thuật.
         */
        $payrollByUser = $payrolls
            ->filter(fn ($row) => (int) ($row->user_id ?? 0) > 0)
            ->keyBy(fn ($row) => (int) $row->user_id);

        $displayRows = collect();

        if ($selectedStatus === '' || $selectedStatus === 'not_scored') {
            foreach ($employees as $employee) {
                $employeeId = (int) ($employee->id ?? 0);

                if ($selectedUserId && $employeeId !== $selectedUserId) {
                    continue;
                }

                $existing = $payrollByUser->get($employeeId);

                if ($existing) {
                    if ($selectedStatus === '') {
                        $displayRows->push($existing);
                    }
                    continue;
                }

                $displayRows->push((object) [
                    'id' => null,
                    'user_id' => $employeeId,
                    'employee_name' => (string) ($employee->name ?? ('Kỹ sư #'.$employeeId)),
                    'position_name' => (string) ($employee->position_name ?? 'Kỹ thuật'),
                    'payroll_month' => $selectedMonth,
                    'status' => 'not_scored',
                    'total_kpi_percent' => null,
                    'kpi_breakdown' => [],
                    'kpi_project_count' => 0,
                    'kpi_project_issue_count' => 0,
                    'kpi_projects' => collect(),
                    'penalty_points' => null,
                ]);
            }

            // Không làm mất hồ sơ KPI lịch sử nếu user đã đổi role/phòng ban.
            if ($selectedStatus === '') {
                foreach ($payrolls as $row) {
                    $userId = (int) ($row->user_id ?? 0);
                    if (! $displayRows->contains(fn ($item) => (int) ($item->user_id ?? 0) === $userId)) {
                        $displayRows->push($row);
                    }
                }
            }
        } else {
            $displayRows = $payrolls->values();
        }

        $displayRows = $displayRows
            ->sortBy(function ($row) {
                $hasKpi = $row->id !== null ? 0 : 1;
                $name = Str::lower(Str::ascii((string) ($row->employee_name ?? '')));

                return sprintf('%d-%s', $hasKpi, $name);
            })
            ->values();

        $summaryPayrolls = $selectedStatus === 'not_scored' ? collect() : $payrolls;

        $avgKpi = $summaryPayrolls->isNotEmpty()
            ? (float) $summaryPayrolls->avg(fn ($row) => (float) ($row->total_kpi_percent ?? 0))
            : 0;

        $summary = [
            'avg_kpi' => $avgKpi,
            'achieved' => $summaryPayrolls->filter(fn ($row) => (float) ($row->total_kpi_percent ?? 0) >= 0.90)->count(),
            'excellent' => $summaryPayrolls->filter(fn ($row) => (float) ($row->total_kpi_percent ?? 0) >= 1.00)->count(),
            'needs_improvement' => $summaryPayrolls->filter(fn ($row) => (float) ($row->total_kpi_percent ?? 0) < 0.75)->count(),
            'pending' => $this->hasColumn('technical_kpi_payrolls', 'status')
                ? $summaryPayrolls->filter(fn ($row) => (string) ($row->status ?? 'draft') !== 'approved')->count()
                : 0,
            'total_kpi_salary' => $summaryPayrolls->sum(fn ($row) => (float) ($row->real_kpi_salary ?? 0)),
            'total_records' => $summaryPayrolls->count(),
            'project_total' => $summaryPayrolls->sum(fn ($row) => (int) ($row->kpi_project_count ?? 0)),
            'project_issues' => $summaryPayrolls->sum(fn ($row) => (int) ($row->kpi_project_issue_count ?? 0)),
            'technical_people' => $employees->count(),
            'not_scored' => $displayRows->filter(fn ($row) => ($row->status ?? '') === 'not_scored')->count(),
        ];

        [$year, $month] = explode('-', $selectedMonth);
        $monthLabel = 'Tháng '.ltrim($month, '0').'/'.$year;

        return view('synced.kythuat.kpis', compact(
            'employees',
            'payrolls',
            'displayRows',
            'summary',
            'selectedMonth',
            'selectedUserId',
            'selectedStatus',
            'monthLabel',
            'kpiCriteria',
            'kpiConfigWeight',
            'kpiConfigValid'
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

        return view('synced.kythuat.luong_settings', compact('settings'));
    }

    /**
     * Lưu cài đặt KPI: cập nhật, xóa và thêm dòng cài đặt mới.
     */
    public function saveSettings(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

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
            'penalty_points' => $calc['penaltyPoints'],
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

        $calc = $this->calculate($request, $employee, $this->payrollKpiTemplate((int) $id));

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
            'penalty_points' => $calc['penaltyPoints'],
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

        [$slipFields, $slipValues, $slipTotals] = $this->buildSlipData($payroll);

        return view('synced.kythuat.luong_show', compact(
            'payroll',
            'items',
            'slipFields',
            'slipValues',
            'slipTotals'
        ));
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
        $kpis = $this->payrollKpiTemplate((int) $id);

        return view('synced.kythuat.luong_edit', compact(
            'payroll',
            'items',
            'settings',
            'employees',
            'kpis'
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
    public function saveKpiItems(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        if (! SchemaCache::hasTable('technical_payroll_kpi_items')) {
            return back()->with('error', 'Chưa có bảng technical_payroll_kpi_items. Hãy chạy migration trước.');
        }

        $items = collect($request->input('kpi_items', []));
        $activeRows = $items->filter(function ($row) {
            return (int) ($row['delete'] ?? 0) !== 1
                && (int) ($row['is_enabled'] ?? 0) === 1
                && trim((string) ($row['name'] ?? '')) !== '';
        });

        if ($activeRows->isEmpty()) {
            return back()->withInput()->with('error', 'Phải có ít nhất 1 tiêu chí KPI đang hoạt động.');
        }

        $weightTotalPercent = (float) $activeRows->sum(fn ($row) => (float) ($row['weight_percent'] ?? 0));
        if (abs($weightTotalPercent - 100.0) > 0.01) {
            return back()->withInput()->with('error', 'Tổng trọng số đang là '.number_format($weightTotalPercent, 2, ',', '.').'%. Chỉ được lưu khi tổng trọng số bằng đúng 100%.');
        }

        $allowedTypes = ['actual_div_plan', 'plan_div_actual', 'material_waste', 'minus_quality', 'minus_safety'];
        $allowedSources = array_keys(ProjectKpiLinkService::sourceOptions());
        foreach ($activeRows as $row) {
            $type = trim((string) ($row['calc_type'] ?? 'actual_div_plan'));
            if (! in_array($type, $allowedTypes, true)) {
                return back()->withInput()->with('error', 'Có tiêu chí KPI sử dụng cách tính không hợp lệ.');
            }
            $sourceCode = trim((string) ($row['source_code'] ?? ProjectKpiLinkService::SOURCE_MANUAL));
            if (! in_array($sourceCode, $allowedSources, true)) {
                return back()->withInput()->with('error', 'Có tiêu chí KPI sử dụng nguồn dữ liệu không hợp lệ.');
            }
        }

        DB::transaction(function () use ($items) {
            $now = now();

            foreach ($items as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : null;
                $delete = (int) ($row['delete'] ?? 0) === 1;

                if ($delete && $id) {
                    DB::table('technical_payroll_kpi_items')->where('id', $id)->delete();

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
                    'source_code' => trim((string) ($row['source_code'] ?? ProjectKpiLinkService::SOURCE_MANUAL)) ?: ProjectKpiLinkService::SOURCE_MANUAL,
                    'note' => trim((string) ($row['note'] ?? '')),
                    'sort_order' => max(1, (int) ($row['sort_order'] ?? 1)),
                    'is_enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
                    'updated_at' => $now,
                ];

                if ($id) {
                    DB::table('technical_payroll_kpi_items')->where('id', $id)->update($payload);
                } else {
                    $payload['created_at'] = $now;
                    DB::table('technical_payroll_kpi_items')->insert($payload);
                }
            }
        });

        return back()->with('success', 'Đã đồng bộ cấu hình KPI. Dashboard và màn hình chấm KPI sẽ dùng đúng '.count($activeRows).' tiêu chí đang hoạt động.');
    }

    /**
     * Xóa một dòng KPI chi tiết, hỗ trợ trả JSON cho request AJAX.
     */
    public function destroyKpiItem($id)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        if (! SchemaCache::hasTable('technical_payroll_kpi_items')) {
            return request()->expectsJson()
                ? response()->json(['message' => 'Chưa có bảng KPI.'], 404)
                : back()->with('error', 'Chưa có bảng KPI.');
        }

        $row = DB::table('technical_payroll_kpi_items')->where('id', $id)->first();
        if (! $row) {
            return request()->expectsJson()
                ? response()->json(['message' => 'Không tìm thấy tiêu chí KPI.'], 404)
                : back()->with('error', 'Không tìm thấy tiêu chí KPI.');
        }

        if ((int) ($row->is_enabled ?? 0) === 1) {
            $remainingWeight = (float) DB::table('technical_payroll_kpi_items')
                ->where('is_enabled', 1)
                ->where('id', '<>', $id)
                ->sum('weight');

            if (abs($remainingWeight - 1.0) > 0.0001) {
                $message = 'Không thể xóa riêng dòng này vì tổng trọng số còn lại sẽ không bằng 100%. Hãy xóa dòng và phân bổ lại trọng số cùng lúc trên trang Cấu hình KPI rồi bấm Lưu.';

                return request()->expectsJson()
                    ? response()->json(['message' => $message], 422)
                    : back()->with('error', $message);
            }
        }

        DB::table('technical_payroll_kpi_items')->where('id', $id)->delete();

        return request()->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'Đã xóa tiêu chí KPI.');
    }

    /*
    |--------------------------------------------------------------------------
    | Phiếu lương cấu hình động
    |--------------------------------------------------------------------------
    */

    private function slipSourceColumns(): array
    {
        return [
            '' => 'Nhập tay theo từng phiếu',
            'employee_name' => 'Nhân viên',
            'position_name' => 'Chức vụ',
            'payroll_month' => 'Kỳ lương',
            'gross_salary' => 'Lương thỏa thuận',
            'base_salary' => 'Lương cố định',
            'kpi_base_salary' => 'Quỹ KPI',
            'real_kpi_salary' => 'Lương KPI thực nhận',
            'kpi_difference' => 'Chênh lệch KPI',
            'total_income' => 'Tổng thu nhập hệ thống',
            'total_kpi_percent' => 'KPI tổng',
            'feedback_bad_count' => 'Feedback xấu',
            'feedback_neutral_count' => 'Feedback trung lập',
            'feedback_good_count' => 'Feedback tốt',
            'note' => 'Ghi chú',
        ];
    }

    private function resolveSlipFieldValue(object $field, object $payroll, $override = null)
    {
        if ($override) {
            if (($field->field_type ?? 'money') === 'text') {
                return $override->text_value ?? '';
            }

            return $override->numeric_value !== null ? (float) $override->numeric_value : 0;
        }

        $source = trim((string) ($field->source_column ?? ''));
        if ($source !== '' && property_exists($payroll, $source)) {
            $value = $payroll->{$source};
            if (($field->field_type ?? '') === 'percent' && is_numeric($value)) {
                $value = (float) $value;

                return abs($value) <= 3 ? $value * 100 : $value;
            }

            return $value;
        }

        return ($field->field_type ?? 'money') === 'text' ? '' : (float) ($field->default_value ?? 0);
    }

    private function buildSlipData(object $payroll): array
    {
        if (! $this->tableExists('technical_payroll_slip_fields')) {
            $fallback = (float) ($payroll->total_income ?? 0);

            return [collect(), collect(), ['income' => $fallback, 'deduction' => 0, 'net' => $fallback]];
        }

        $fields = DB::table('technical_payroll_slip_fields')
            ->where('is_enabled', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $overrides = $this->tableExists('technical_payroll_slip_values')
            ? DB::table('technical_payroll_slip_values')->where('payroll_id', $payroll->id)->get()->keyBy('field_id')
            : collect();

        $values = collect();
        $income = 0.0;
        $deduction = 0.0;

        foreach ($fields as $field) {
            $override = $overrides->get($field->id);
            $value = $this->resolveSlipFieldValue($field, $payroll, $override);
            $values->put($field->id, (object) ['value' => $value, 'is_override' => (bool) $override]);

            if ((int) ($field->is_in_total ?? 0) !== 1 || ! is_numeric($value)) {
                continue;
            }
            if (($field->group_key ?? '') === 'income') {
                $income += (float) $value;
            } elseif (($field->group_key ?? '') === 'deduction') {
                $deduction += abs((float) $value);
            }
        }

        if ($fields->where('is_in_total', 1)->isEmpty()) {
            $income = (float) ($payroll->total_income ?? 0);
        }

        return [$fields, $values, ['income' => $income, 'deduction' => $deduction, 'net' => $income - $deduction]];
    }

    public function slipSettings()
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        if (! $this->tableExists('technical_payroll_slip_fields')) {
            return back()->with('error', 'Chưa có bảng cấu hình phiếu lương. Hãy chạy migration trước.');
        }

        $fields = DB::table('technical_payroll_slip_fields')->orderBy('sort_order')->orderBy('id')->get();
        $sourceColumns = $this->slipSourceColumns();

        return view('synced.kythuat.luong_slip_settings', compact('fields', 'sourceColumns'));
    }

    public function saveSlipSettings(Request $request)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        if (! $this->tableExists('technical_payroll_slip_fields')) {
            return back()->with('error', 'Chưa có bảng technical_payroll_slip_fields.');
        }

        $allowedGroups = ['info', 'income', 'deduction', 'summary'];
        $allowedTypes = ['money', 'number', 'percent', 'text'];
        $allowedSources = array_keys($this->slipSourceColumns());
        $now = now();

        foreach ($request->input('fields', []) as $row) {
            $id = isset($row['id']) ? (int) $row['id'] : null;
            if ($id && (int) ($row['delete'] ?? 0) === 1) {
                if ($this->tableExists('technical_payroll_slip_values')) {
                    DB::table('technical_payroll_slip_values')->where('field_id', $id)->delete();
                }
                DB::table('technical_payroll_slip_fields')->where('id', $id)->delete();

                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $key = Str::slug(trim((string) ($row['field_key'] ?? '')), '_');
            if ($key === '') {
                $key = Str::slug($label, '_');
            }
            if ($key === '') {
                $key = 'field_'.time().'_'.($id ?: random_int(100, 999));
            }

            $group = in_array($row['group_key'] ?? '', $allowedGroups, true) ? $row['group_key'] : 'income';
            $type = in_array($row['field_type'] ?? '', $allowedTypes, true) ? $row['field_type'] : 'money';
            $source = trim((string) ($row['source_column'] ?? ''));
            if (! in_array($source, $allowedSources, true)) {
                $source = '';
            }

            $payload = [
                'field_key' => $key,
                'label' => $label,
                'group_key' => $group,
                'field_type' => $type,
                'source_column' => $source !== '' ? $source : null,
                'default_value' => in_array($type, ['money', 'number', 'percent'], true) ? (float) ($row['default_value'] ?? 0) : null,
                'is_in_total' => (int) ($row['is_in_total'] ?? 0) === 1,
                'is_enabled' => (int) ($row['is_enabled'] ?? 0) === 1,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'note' => trim((string) ($row['note'] ?? '')),
                'updated_at' => $now,
            ];

            if ($id) {
                if (DB::table('technical_payroll_slip_fields')->where('field_key', $key)->where('id', '<>', $id)->exists()) {
                    $payload['field_key'] = $key.'_'.substr(md5((string) $id), 0, 5);
                }
                DB::table('technical_payroll_slip_fields')->where('id', $id)->update($payload);
            } else {
                if (DB::table('technical_payroll_slip_fields')->where('field_key', $key)->exists()) {
                    $payload['field_key'] = $key.'_'.time();
                }
                $payload['created_at'] = $now;
                DB::table('technical_payroll_slip_fields')->insert($payload);
            }
        }

        return back()->with('success', 'Đã cập nhật cấu hình phiếu lương.');
    }

    public function destroySlipField($id)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        if ($this->tableExists('technical_payroll_slip_values')) {
            DB::table('technical_payroll_slip_values')->where('field_id', (int) $id)->delete();
        }
        if ($this->tableExists('technical_payroll_slip_fields')) {
            DB::table('technical_payroll_slip_fields')->where('id', (int) $id)->delete();
        }

        return back()->with('success', 'Đã xóa dòng khỏi cấu hình phiếu lương.');
    }

    public function saveSlipValues(Request $request, $id)
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);
        if (! $this->tableExists('technical_kpi_payrolls')) {
            abort(404);
        }
        $payroll = DB::table('technical_kpi_payrolls')->where('id', $id)->first();
        abort_if(! $payroll, 404);

        if (! $this->tableExists('technical_payroll_slip_fields') || ! $this->tableExists('technical_payroll_slip_values')) {
            return back()->with('error', 'Chưa có bảng cấu hình/giá trị phiếu lương.');
        }

        $fields = DB::table('technical_payroll_slip_fields')->get()->keyBy('id');
        foreach ($request->input('values', []) as $fieldId => $raw) {
            $fieldId = (int) $fieldId;
            $field = $fields->get($fieldId);
            if (! $field) {
                continue;
            }
            $raw = is_string($raw) ? trim($raw) : $raw;

            if ($raw === '' || $raw === null) {
                DB::table('technical_payroll_slip_values')->where('payroll_id', $id)->where('field_id', $fieldId)->delete();

                continue;
            }

            $payload = ['updated_by' => auth()->id(), 'updated_at' => now()];
            if (($field->field_type ?? '') === 'text') {
                $payload['text_value'] = (string) $raw;
                $payload['numeric_value'] = null;
            } else {
                $clean = str_replace([',', ' '], ['', ''], (string) $raw);
                $payload['numeric_value'] = is_numeric($clean) ? (float) $clean : 0;
                $payload['text_value'] = null;
            }

            $existing = DB::table('technical_payroll_slip_values')->where('payroll_id', $id)->where('field_id', $fieldId)->exists();
            if ($existing) {
                DB::table('technical_payroll_slip_values')->where('payroll_id', $id)->where('field_id', $fieldId)->update($payload);
            } else {
                DB::table('technical_payroll_slip_values')->insert(array_merge($payload, [
                    'payroll_id' => (int) $id,
                    'field_id' => $fieldId,
                    'created_at' => now(),
                ]));
            }
        }

        $payroll = DB::table('technical_kpi_payrolls')->where('id', $id)->first();
        [, , $totals] = $this->buildSlipData($payroll);
        DB::table('technical_kpi_payrolls')->where('id', $id)->update($this->filterColumns('technical_kpi_payrolls', [
            'total_income' => $totals['net'],
            'updated_by' => auth()->id(),
            'updated_at' => now(),
        ]));

        return back()->with('success', 'Đã cập nhật phiếu lương.');
    }
}
