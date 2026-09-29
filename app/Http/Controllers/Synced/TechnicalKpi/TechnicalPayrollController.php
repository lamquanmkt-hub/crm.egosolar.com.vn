<?php

namespace App\Http\Controllers\Synced\TechnicalKpi;

use App\Http\Controllers\Controller;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
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
    private function employees(?int $departmentId = null)
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

        $departmentSelect = DB::raw('"Phòng Kỹ thuật" as department_name');
        if ($hasDepartmentClassifier) {
            $query->leftJoin('departments', 'departments.id', '=', 'users.department_id');
            $departmentSelect = DB::raw('COALESCE(departments.name, "Phòng Kỹ thuật") as department_name');
        }

        $codeSelect = DB::raw('CONCAT("KT-", LPAD(users.id, 3, "0")) as employee_code');
        if ($this->tableExists('hr_employee_profiles') && $this->hasColumn('hr_employee_profiles', 'employee_code')) {
            $query->leftJoin('hr_employee_profiles', 'hr_employee_profiles.employee_id', '=', 'users.id');
            $codeSelect = DB::raw('COALESCE(NULLIF(hr_employee_profiles.employee_code, ""), CONCAT("KT-", LPAD(users.id, 3, "0"))) as employee_code');
        } elseif ($this->hasColumn('users', 'employee_code')) {
            $codeSelect = DB::raw('COALESCE(NULLIF(users.employee_code, ""), CONCAT("KT-", LPAD(users.id, 3, "0"))) as employee_code');
        } elseif ($this->hasColumn('users', 'code')) {
            $codeSelect = DB::raw('COALESCE(NULLIF(users.code, ""), CONCAT("KT-", LPAD(users.id, 3, "0"))) as employee_code');
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

        if ($departmentId && $this->hasColumn('users', 'department_id')) {
            $query->where('users.department_id', $departmentId);
        }

        if ($this->hasColumn('users', 'is_active')) {
            $query->where('users.is_active', 1);
        }

        return $query
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.department_id',
                $positionSelect,
                $departmentSelect,
                $codeSelect
            )
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }


    /**
     * Lấy thông tin một nhân viên kỹ thuật theo id kèm tên chức vụ, phòng ban và mã nhân viên.
     */
    private function employeeById($userId)
    {
        if (! $this->tableExists('users')) {
            return null;
        }

        $query = DB::table('users');

        $hasPositionClassifier = $this->tableExists('positions')
            && $this->hasColumn('users', 'position_id')
            && $this->hasColumn('positions', 'id');

        $hasDepartmentClassifier = $this->tableExists('departments')
            && $this->hasColumn('users', 'department_id')
            && $this->hasColumn('departments', 'id');

        if ($hasPositionClassifier) {
            $query->leftJoin('positions', 'positions.id', '=', 'users.position_id');
            $positionSelect = DB::raw('COALESCE(positions.name, "Kỹ thuật") as position_name');
        } else {
            $positionSelect = DB::raw('"Kỹ thuật" as position_name');
        }

        $departmentSelect = DB::raw('"Phòng Kỹ thuật" as department_name');
        if ($hasDepartmentClassifier) {
            $query->leftJoin('departments', 'departments.id', '=', 'users.department_id');
            $departmentSelect = DB::raw('COALESCE(departments.name, "Phòng Kỹ thuật") as department_name');
        }

        $codeSelect = DB::raw('CONCAT("KT-", LPAD(users.id, 3, "0")) as employee_code');
        if ($this->tableExists('hr_employee_profiles') && $this->hasColumn('hr_employee_profiles', 'employee_code')) {
            $query->leftJoin('hr_employee_profiles', 'hr_employee_profiles.employee_id', '=', 'users.id');
            $codeSelect = DB::raw('COALESCE(NULLIF(hr_employee_profiles.employee_code, ""), CONCAT("KT-", LPAD(users.id, 3, "0"))) as employee_code');
        } elseif ($this->hasColumn('users', 'employee_code')) {
            $codeSelect = DB::raw('COALESCE(NULLIF(users.employee_code, ""), CONCAT("KT-", LPAD(users.id, 3, "0"))) as employee_code');
        } elseif ($this->hasColumn('users', 'code')) {
            $codeSelect = DB::raw('COALESCE(NULLIF(users.code, ""), CONCAT("KT-", LPAD(users.id, 3, "0"))) as employee_code');
        }

        return $query
            ->where('users.id', $userId)
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.department_id',
                $positionSelect,
                $departmentSelect,
                $codeSelect
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
     * Trang KPIs kỹ thuật (/ky-thuat/kpis) theo tài liệu chuẩn của Ban lãnh đạo
     * (config/technical_kpi_standard.php — 6 tiêu chí, tiêu chí 6 chờ xác nhận).
     *
     * CHỈ ĐỌC: không seed cài đặt, không ghi điểm. Mọi điểm được tính trực tiếp
     * từ dữ liệu CRM đã xác minh; tiêu chí thiếu nguồn hiển thị "Chưa đủ dữ liệu".
     */
    public function kpis(Request $request, KpiStandardEvaluator $evaluator)
    {
        [$periodType, $periodValue, $months, $periodLabel] = $evaluator->resolvePeriod(
            (string) $request->query('period', 'month'),
            $request->query('month'),
            $request->query('quarter')
        );

        $currentUser = $request->user();
        $isAdmin = false;
        $isManager = false;
        $isTechnician = false;

        if ($currentUser) {
            $isAdmin = (method_exists($currentUser, 'isAdmin') && $currentUser->isAdmin())
                || (method_exists($currentUser, 'hasAnyRole') && $currentUser->hasAnyRole(config('role_permissions.admin_roles', ['admin'])))
                || (method_exists($currentUser, 'hasAnyRole') && $currentUser->hasAnyRole(['director', 'ceo', 'management', 'super_admin']));

            if (! $isAdmin) {
                $isManager = (method_exists($currentUser, 'hasAnyRole') && $currentUser->hasAnyRole([
                    'technical_manager',
                    'technical_leader',
                    'truong_phong_ky_thuat',
                    'manager',
                    'management',
                ])) || (class_exists(\App\Services\Workspace\WorkspaceLevelService::class)
                    && app(\App\Services\Workspace\WorkspaceLevelService::class)->isAtLeast($currentUser, 'team_lead'));
            }

            if (! $isAdmin && ! $isManager) {
                $isTechnician = true;
            }
        }

        // Quyền xem:
        // - Admin: xem toàn bộ nhân viên
        // - Quản lý/trưởng nhóm: xem nhân viên thuộc phạm vi phụ trách
        // - Kỹ thuật viên: chỉ thấy hồ sơ KPI của chính mình, không thấy danh sách toàn bộ nhân viên
        $canViewList = ! $isTechnician;

        $departmentFilter = ($isManager && ! $isAdmin && $currentUser && ! empty($currentUser->department_id))
            ? (int) $currentUser->department_id
            : null;

        $employees = $this->employees($departmentFilter);
        if ($employees->isEmpty()) {
            $employees = $this->employees();
        }

        // Kỹ thuật viên: ép buộc user_id là chính mình
        if ($isTechnician && $currentUser) {
            $selectedUserId = (int) $currentUser->id;
            $employees = $employees->where('id', $currentUser->id)->values();
            if ($employees->isEmpty() && ($picked = $this->employeeById($currentUser->id))) {
                $employees = collect([$picked]);
            }
        } else {
            $selectedUserId = $request->filled('user_id') ? (int) $request->query('user_id') : null;
        }

        $selectedDepartmentId = $request->filled('department_id') ? (int) $request->query('department_id') : null;
        $selectedApprovalStatus = (string) $request->query('approval_status', '');
        $selectedSiteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $selectedDataStatus = (string) $request->query('data_status', '');
        if (! array_key_exists($selectedDataStatus, KpiStandardEvaluator::DATA_LABELS)) {
            $selectedDataStatus = '';
        }

        $settings = $this->settingsMap();

        $departments = $this->tableExists('departments')
            ? DB::table('departments')->select('id', 'name')->orderBy('name')->get()
            : collect();

        $sites = $this->tableExists('sites')
            ? DB::table('sites')->select('id', 'project_code', 'name')->orderBy('name')->limit(1000)->get()
            : collect();

        // Scope đánh giá
        $scope = $employees;
        if ($selectedUserId && $scope->where('id', $selectedUserId)->isEmpty() && ($picked = $this->employeeById($selectedUserId))) {
            $scope = $scope->concat([$picked]);
        }

        $periodDate = !empty($months[0]) ? $months[0] . '-01' : now()->toDateString();
        $activeConfig = \App\Models\TechnicalKpiConfig::getActiveForDate($periodDate);
        $hasConfig = !empty($activeConfig);

        $canConfigureKpi = $currentUser && (
            (method_exists($currentUser, 'isAdmin') && $currentUser->isAdmin())
            || (method_exists($currentUser, 'hasAnyRole') && $currentUser->hasAnyRole(['admin', 'super_admin', 'director', 'ceo', 'management', 'hr', 'accounting', 'technical_manager']))
        );

        // Đánh giá từng kỹ sư trong kỳ và gắn với số liệu lương
        $engineers = collect();
        foreach ($scope as $employee) {
            $data = $this->kpiCollectFor($evaluator, $employee, $months, $selectedSiteId);
            $result = $evaluator->score($data['projects'], $data['evidence'], $data['warranty']);

            // Tra cứu bảng lương hiện hành trong kỳ nếu có
            $existingPayroll = null;
            if ($this->tableExists('technical_kpi_payrolls')) {
                $existingPayroll = DB::table('technical_kpi_payrolls')
                    ->where('user_id', $employee->id)
                    ->whereIn('payroll_month', $months)
                    ->orderByDesc('id')
                    ->first();
            }

            // Tra cứu bảng lương gần nhất để lấy mức lương cơ sở thỏa thuận nếu chưa có kỳ này
            $latestPayroll = null;
            if ($this->tableExists('technical_kpi_payrolls')) {
                $latestPayroll = DB::table('technical_kpi_payrolls')
                    ->where('user_id', $employee->id)
                    ->orderByDesc('id')
                    ->first();
            }

            // Mức lương thỏa thuận: từ kỳ hiện hành -> kỳ gần nhất -> users.official_salary -> null (KHÔNG dùng 15.000.000 đ mặc định)
            $agreedSalary = null;
            if ($existingPayroll && !empty($existingPayroll->gross_salary) && (float) $existingPayroll->gross_salary > 0) {
                $agreedSalary = (float) $existingPayroll->gross_salary;
            } elseif ($latestPayroll && !empty($latestPayroll->gross_salary) && (float) $latestPayroll->gross_salary > 0) {
                $agreedSalary = (float) $latestPayroll->gross_salary;
            } elseif (!empty($employee->official_salary) && (float) $employee->official_salary > 0) {
                $agreedSalary = (float) $employee->official_salary;
            }

            if ($existingPayroll && $existingPayroll->status === 'approved') {
                $payrollStatus = 'approved';
            } elseif ($existingPayroll && $existingPayroll->status === 'draft') {
                $payrollStatus = 'draft';
            } else {
                $payrollStatus = 'pending_calc';
            }

            // Tỷ lệ KPI đạt được (%)
            $kpiPercent = null;
            if ($existingPayroll && $existingPayroll->total_kpi_percent !== null) {
                $kpiPercent = (float) $existingPayroll->total_kpi_percent;
            } elseif ($result['total'] !== null) {
                $kpiPercent = (float) $result['total'];
            }

            // Tính toán chi tiết các cấu phần theo cấu hình KPI hiệu lực (KHÔNG hardcode 70/30)
            if ($hasConfig && $activeConfig) {
                $payout = $activeConfig->computePayout($agreedSalary, $kpiPercent);
                $baseRate = $payout['base_rate'];
                $kpiRate = $payout['kpi_rate'];
                $baseSalary = $payout['base_salary'];
                $kpiBaseSalary = $payout['kpi_base_salary'];
                $realKpiSalary = $payout['real_kpi_salary'];
                $totalIncome = $payout['total_income'];
                $tierLabel = $payout['tier_label'];
            } else {
                $baseRate = null;
                $kpiRate = null;
                $baseSalary = null;
                $kpiBaseSalary = null;
                $realKpiSalary = null;
                $totalIncome = null;
                $tierLabel = null;
            }

            // Nếu kỳ lương đã duyệt (approved) trong quá khứ thì bảo toàn số liệu đã chốt
            if ($existingPayroll && $existingPayroll->status === 'approved') {
                if ($existingPayroll->base_salary !== null) {
                    $baseSalary = (float) $existingPayroll->base_salary;
                }
                if ($existingPayroll->real_kpi_salary !== null) {
                    $realKpiSalary = (float) $existingPayroll->real_kpi_salary;
                }
                if ($existingPayroll->total_income !== null) {
                    $totalIncome = (float) $existingPayroll->total_income;
                }
            }

            $engineers->push([
                'id' => (int) $employee->id,
                'name' => (string) ($employee->name ?? ('Kỹ sư #'.$employee->id)),
                'code' => (string) ($employee->employee_code ?? ('KT-'.str_pad((string) $employee->id, 3, '0', STR_PAD_LEFT))),
                'position' => (string) ($employee->position_name ?? 'Kỹ thuật'),
                'department' => (string) ($employee->department_name ?? 'Phòng Kỹ thuật'),
                'department_id' => (int) ($employee->department_id ?? 0),
                'agreed_salary' => $agreedSalary,
                'gross_salary' => $agreedSalary,
                'base_rate' => $baseRate,
                'kpi_rate' => $kpiRate,
                'base_salary' => $baseSalary,
                'kpi_base_salary' => $kpiBaseSalary,
                'kpi_percent' => $kpiPercent,
                'real_kpi_salary' => $realKpiSalary,
                'total_income' => $totalIncome,
                'tier_label' => $tierLabel,
                'payroll_status' => $payrollStatus,
                'payroll_record' => $existingPayroll,
                'result' => $result,
                'data' => $data,
            ]);
        }

        $selectedEngineer = $selectedUserId ? $engineers->firstWhere('id', $selectedUserId) : null;
        $allEngineers = $engineers;

        if ($selectedDepartmentId) {
            $engineers = $engineers->filter(fn ($e) => (int) $e['department_id'] === $selectedDepartmentId)->values();
        }

        if ($selectedApprovalStatus !== '') {
            $engineers = $engineers->filter(fn ($e) => $e['payroll_status'] === $selectedApprovalStatus)->values();
        }

        if ($selectedDataStatus !== '') {
            $engineers = $engineers->filter(fn ($e) => $e['result']['data_status'] === $selectedDataStatus)->values();
        }

        // Kết quả hiển thị ở thẻ/bảng tiêu chí: một kỹ sư, hoặc gộp số liệu toàn phòng.
        $isTeam = ! $selectedUserId;
        $focus = $isTeam ? $engineers : collect([$selectedEngineer])->filter()->values();
        $pooled = $this->kpiPool($focus->pluck('data'));
        $detail = $evaluator->score($pooled['projects'], $pooled['evidence'], $pooled['warranty']);

        // So sánh giữa kỹ sư: người đủ dữ liệu xếp theo điểm, người thiếu dữ liệu đứng sau.
        $ranking = $engineers
            ->sortBy(fn ($e) => [$e['result']['total'] === null ? 1 : 0, -1 * (float) ($e['result']['total'] ?? 0), Str::ascii($e['name'])])
            ->values();

        $hasAnyBaseSalary = $engineers->whereNotNull('base_salary')->isNotEmpty();
        $totalBaseSalary = $hasAnyBaseSalary ? (float) $engineers->whereNotNull('base_salary')->sum('base_salary') : null;

        $hasAnyKpiMoney = $engineers->whereNotNull('real_kpi_salary')->isNotEmpty();
        $totalKpiSalary = $hasAnyKpiMoney ? (float) $engineers->whereNotNull('real_kpi_salary')->sum('real_kpi_salary') : null;

        $hasAnyTotalIncome = $engineers->whereNotNull('total_income')->isNotEmpty();
        $totalProjectedIncome = $hasAnyTotalIncome ? (float) $engineers->whereNotNull('total_income')->sum('total_income') : null;
        $pendingApprovalCount = (int) $engineers->where('payroll_status', 'draft')->count();

        $payrollSummary = [
            'total_engineers' => $engineers->count(),
            'total_base_salary' => $totalBaseSalary,
            'total_kpi_salary' => $totalKpiSalary,
            'total_projected_income' => $totalProjectedIncome,
            'pending_approval_count' => $pendingApprovalCount,
        ];

        $teamSummary = [
            'engineers' => $engineers->count(),
            'full' => $engineers->where('result.data_status', KpiStandardEvaluator::DATA_FULL)->count(),
            'partial' => $engineers->where('result.data_status', KpiStandardEvaluator::DATA_PARTIAL)->count(),
            'none' => $engineers->where('result.data_status', KpiStandardEvaluator::DATA_NONE)->count(),
            'avg_total' => null,
            'tiers' => [],
        ];
        $scored = $engineers->filter(fn ($e) => $e['result']['total'] !== null);
        if ($scored->isNotEmpty()) {
            $teamSummary['avg_total'] = (float) $scored->avg(fn ($e) => $e['result']['total']);
        }
        foreach ($evaluator->bonusTiers() as $tier) {
            $teamSummary['tiers'][$tier['label']] = $scored->filter(fn ($e) => ($e['result']['tier']['label'] ?? null) === $tier['label'])->count();
        }

        // Xu hướng 6 tháng (kết thúc ở tháng cuối của kỳ) cho đúng phạm vi đang xem.
        $trend = [];
        foreach ($evaluator->trailingMonths(end($months), 6) as $month) {
            $monthData = in_array($month, $months, true) && count($months) === 1
                ? $pooled
                : $this->kpiPool($focus->map(fn ($e) => $this->kpiCollectFor(
                    $evaluator,
                    (object) ['id' => $e['id'], 'name' => $e['name']],
                    [$month],
                    $selectedSiteId
                )));
            $monthResult = $evaluator->score($monthData['projects'], $monthData['evidence'], null);
            [$y, $m] = explode('-', $month);
            $trend[] = [
                'month' => $month,
                'label' => 'T'.ltrim($m, '0').'/'.substr($y, 2),
                'total' => $monthResult['total'],
                'with_data' => $monthResult['with_data'],
                'weighted_count' => $monthResult['weighted_count'],
            ];
        }

        // Phiếu lương KPI đã chấm ở hệ hiện hành — chỉ để tham chiếu, không trộn vào điểm chuẩn.
        $legacyPayrolls = collect();
        if ($this->tableExists('technical_kpi_payrolls') && $this->hasColumn('technical_kpi_payrolls', 'payroll_month')) {
            $legacyPayrolls = DB::table('technical_kpi_payrolls')
                ->whereIn('payroll_month', $months)
                ->when($selectedUserId, fn ($q) => $q->where('user_id', $selectedUserId))
                ->orderBy('employee_name')
                ->limit(100)
                ->get(['id', 'user_id', 'employee_name', 'payroll_month', 'total_kpi_percent', 'status']);
        }

        return view('synced.kythuat.kpis', [
            'hasConfig' => $hasConfig,
            'activeConfig' => $activeConfig,
            'canConfigureKpi' => $canConfigureKpi,
            'criteriaStandard' => $activeConfig ? ($activeConfig->criteria_config ?? $evaluator->criteria()) : $evaluator->criteria(),
            'weightTotal' => $evaluator->weightTotal(),
            'bonusTiers' => $evaluator->bonusTiers(),
            'materialScale' => (array) config('technical_kpi_standard.material_scale', []),
            'violationNote' => (string) config('technical_kpi_standard.serious_violation_note', ''),
            'standardSource' => (array) config('technical_kpi_standard.source', []),
            'statusLabels' => KpiStandardEvaluator::STATUS_LABELS,
            'dataLabels' => KpiStandardEvaluator::DATA_LABELS,
            'departments' => $departments,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedApprovalStatus' => $selectedApprovalStatus,
            'payrollSummary' => $payrollSummary,
            'employees' => $employees,
            'allEngineers' => $allEngineers,
            'engineers' => $engineers,
            'sites' => $sites,
            'periodType' => $periodType,
            'periodValue' => $periodValue,
            'periodLabel' => $periodLabel,
            'months' => $months,
            'selectedMonth' => $periodType === 'month' ? $periodValue : end($months),
            'selectedQuarter' => $periodType === 'quarter' ? $periodValue : sprintf('%s-Q%d', substr($periodValue, 0, 4), (int) ceil(((int) substr($periodValue, 5, 2)) / 3)),
            'selectedUserId' => $selectedUserId,
            'selectedSiteId' => $selectedSiteId,
            'selectedDataStatus' => $selectedDataStatus,
            'isTeam' => $isTeam,
            'selectedEngineer' => $selectedEngineer,
            'detail' => $detail,
            'ranking' => $ranking,
            'teamSummary' => $teamSummary,
            'trend' => $trend,
            'sources' => $this->kpiSourceStatus($months),
            'legacyPayrolls' => $legacyPayrolls,
            'canViewList' => $canViewList,
            'isAdmin' => $isAdmin,
            'isManager' => $isManager,
            'isTechnician' => $isTechnician,
        ]);
    }

    /** Gom dữ liệu nguồn của một kỹ sư, gắn tên kỹ sư vào từng công trình để hiển thị minh chứng. */
    private function kpiCollectFor(KpiStandardEvaluator $evaluator, object $employee, array $months, ?int $siteId): array
    {
        $data = $evaluator->collect((int) $employee->id, $months, $siteId);
        $name = (string) ($employee->name ?? '');
        $data['projects'] = $data['projects']->map(fn ($p) => $p + ['user_name' => $name])->values();

        return $data;
    }

    /** Gộp dữ liệu nhiều kỹ sư để chấm "toàn phòng" (cộng tử số / mẫu số, không lấy trung bình điểm). */
    private function kpiPool($collections): array
    {
        $projects = collect();
        $evidence = collect();
        $warranty = null;

        foreach ($collections as $data) {
            $projects = $projects->concat($data['projects'] ?? []);
            $evidence = $evidence->concat($data['evidence'] ?? []);
            if (! empty($data['warranty'])) {
                $warranty ??= ['total' => 0, 'resolved' => 0, 'rows' => collect()];
                $warranty['total'] += (int) $data['warranty']['total'];
                $warranty['resolved'] += (int) $data['warranty']['resolved'];
                $warranty['rows'] = $warranty['rows']->concat($data['warranty']['rows']);
            }
        }

        return ['projects' => $projects->values(), 'evidence' => $evidence->values(), 'warranty' => $warranty];
    }

    /**
     * Trạng thái các điểm liên kết dữ liệu CRM của bộ KPI (chỉ đọc).
     * status: connected (đã liên kết, có dữ liệu kỳ này) | empty (đã liên kết, kỳ này chưa có dữ liệu)
     *         | missing (chưa có nguồn / chưa liên kết với công trình–kỹ sư).
     */
    private function kpiSourceStatus(array $months): array
    {
        $evidenceCount = function (string $column) use ($months): ?int {
            if (! $this->hasColumn('technical_kpi_project_evidence', $column)) {
                return null;
            }

            return (int) DB::table('technical_kpi_project_evidence')
                ->whereIn('payroll_month', $months)
                ->whereNotNull('approved_at')
                ->whereNotNull($column)
                ->count();
        };
        $state = fn (?int $count) => $count === null ? 'missing' : ($count > 0 ? 'connected' : 'empty');

        $assignments = 0;
        if ($this->hasColumn('project_workflow_assignments', 'user_id')) {
            $assignments += (int) DB::table('project_workflow_assignments')
                ->when($this->hasColumn('project_workflow_assignments', 'is_active'), fn ($q) => $q->where('is_active', 1))
                ->count();
        }
        if ($this->hasColumn('sites', 'lead_engineer_id')) {
            $assignments += (int) DB::table('sites')->whereNotNull('lead_engineer_id')->count();
        }
        $hasAssignmentSource = $this->tableExists('project_workflow_assignments') || $this->hasColumn('sites', 'lead_engineer_id');

        $datesCount = $this->hasColumn('sites', 'target_completion_at') && $this->hasColumn('sites', 'completed_at')
            ? (int) DB::table('sites')->whereNotNull('target_completion_at')->whereNotNull('completed_at')->count()
            : null;

        $acceptanceCount = $this->hasColumn('project_workflow_steps', 'step_code')
            ? (int) DB::table('project_workflow_steps')->where('step_code', 'acceptance')->count()
            : null;

        $warrantyCount = $this->hasColumn('crm_serial_warranty_claims', 'assigned_to') && $this->hasColumn('crm_serial_warranty_claims', 'resolved_at')
            ? (int) DB::table('crm_serial_warranty_claims')->whereNotNull('assigned_to')->count()
            : null;

        return [
            [
                'label' => 'Phân công kỹ sư – công trình',
                'criteria' => 'Điều kiện chung',
                'where' => 'Phân công workflow công trình / kỹ sư phụ trách chính',
                'status' => $hasAssignmentSource ? ($assignments > 0 ? 'connected' : 'empty') : 'missing',
                'note' => $assignments > 0 ? $assignments.' lượt phân công' : 'Chưa có công trình nào gán kỹ sư — mọi tiêu chí sẽ thiếu dữ liệu',
            ],
            [
                'label' => 'Ngày kế hoạch và ngày hoàn thành',
                'criteria' => 'Tiêu chí 1',
                'where' => 'Hạn bước workflow / hạn hoàn thành & ngày hoàn thành công trình',
                'status' => $state($datesCount),
                'note' => $datesCount === null ? 'Chưa có cột ngày' : $datesCount.' công trình có đủ hai ngày',
            ],
            [
                'label' => 'Trạng thái nghiệm thu',
                'criteria' => 'Tiêu chí 1, 2',
                'where' => 'Bước nghiệm thu (workflow) + minh chứng "nghiệm thu lần đầu"',
                'status' => $state($evidenceCount('quality_first_pass')),
                'note' => ($acceptanceCount ?? 0).' bước nghiệm thu · '.((int) $evidenceCount('quality_first_pass')).' minh chứng đã duyệt trong kỳ',
            ],
            [
                'label' => 'Số lần sửa / rework',
                'criteria' => 'Tiêu chí 2',
                'where' => '—',
                'status' => 'missing',
                'note' => 'Chưa có nguồn dữ liệu đếm số lần sửa theo công trình',
            ],
            [
                'label' => 'Phản ánh khách hàng',
                'criteria' => 'Tiêu chí 2',
                'where' => $this->tableExists('crm_customer_ratings') ? 'Đánh giá khách hàng (CRM)' : '—',
                'status' => 'missing',
                'note' => $this->tableExists('crm_customer_ratings')
                    ? 'Có bảng đánh giá khách hàng nhưng chưa gắn với công trình/kỹ sư'
                    : 'Chưa có nguồn dữ liệu phản ánh',
            ],
            [
                'label' => 'Dữ liệu khảo sát và vật tư',
                'criteria' => 'Tiêu chí 3',
                'where' => 'Minh chứng KPI công trình · % sai lệch/hao hụt vật tư',
                'status' => $state($evidenceCount('material_waste_percent')),
                'note' => ((int) $evidenceCount('material_waste_percent')).' minh chứng đã duyệt trong kỳ',
            ],
            [
                'label' => 'Checklist an toàn / HSE',
                'criteria' => 'Tiêu chí 4',
                'where' => 'Minh chứng KPI công trình · checklist HSE',
                'status' => $state($evidenceCount('hse_pass')),
                'note' => ((int) $evidenceCount('hse_pass')).' minh chứng đã duyệt trong kỳ',
            ],
            [
                'label' => 'Trạng thái EVN và cài App',
                'criteria' => 'Tiêu chí 5',
                'where' => 'Minh chứng KPI công trình · EVN/App',
                'status' => $state($evidenceCount('evn_app_required')),
                'note' => ((int) $evidenceCount('evn_app_required')).' minh chứng đã duyệt trong kỳ',
            ],
            [
                'label' => 'Phiếu bảo hành và thời gian xử lý',
                'criteria' => 'Tiêu chí 6 (chờ xác nhận)',
                'where' => $warrantyCount === null ? '—' : 'Phiếu bảo hành serial (người xử lý, ngày tiếp nhận, ngày xử lý)',
                'status' => $warrantyCount === null ? 'missing' : ($warrantyCount > 0 ? 'connected' : 'empty'),
                'note' => $warrantyCount === null ? 'Chưa có nguồn' : $warrantyCount.' phiếu đã gán kỹ sư · chưa dùng để tính điểm',
            ],
        ];
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
