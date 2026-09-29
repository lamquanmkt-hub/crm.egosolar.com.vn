<?php

namespace App\Http\Controllers\Synced\TechnicalKpi;

use App\Http\Controllers\Controller;
use App\Models\TechnicalKpiConfig;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TechnicalKpiConfigController extends Controller
{
    /**
     * Kiểm tra quyền truy cập cấu hình KPI.
     */
    private function checkAccess(Request $request): array
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $isAdmin = (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'super_admin', 'director', 'ceo', 'management']));

        $isHrOrAccounting = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['hr', 'accounting']);

        $isTechnicalManager = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['technical_manager', 'technical_leader', 'truong_phong_ky_thuat']);

        $isTechnician = method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['ky_thuat', 'technician', 'technical_staff', 'engineer'])
            && !$isAdmin
            && !$isHrOrAccounting
            && !$isTechnicalManager;

        // Kỹ thuật viên tuyệt đối không được truy cập -> 403 Forbidden
        if ($isTechnician) {
            abort(403, 'Bạn không có quyền truy cập trang Cài đặt KPI.');
        }

        if (!$isAdmin && !$isHrOrAccounting && !$isTechnicalManager) {
            abort(403, 'Bạn không có quyền truy cập trang Cài đặt KPI.');
        }

        return [
            'isAdmin' => $isAdmin,
            'isHrOrAccounting' => $isHrOrAccounting,
            'isTechnicalManager' => $isTechnicalManager,
            'canApprove' => $isAdmin,
            'canEdit' => $isAdmin || $isHrOrAccounting,
        ];
    }

    /**
     * Màn hình danh sách và chỉnh sửa cấu hình KPI.
     */
    public function index(Request $request)
    {
        $permissions = $this->checkAccess($request);

        $versions = TechnicalKpiConfig::with(['creator', 'approver'])
            ->orderByDesc('version')
            ->get();

        $selectedId = $request->query('version_id');
        if ($selectedId) {
            $currentConfig = $versions->firstWhere('id', (int) $selectedId);
        } else {
            $currentConfig = $versions->firstWhere('is_current', true) ?? $versions->first();
        }

        $activeConfig = $versions->firstWhere('is_current', true);

        $canEditSalary = TechnicalKpiInputController::canEditSalary($request->user());

        return view('synced.kythuat.kpi_config', array_merge($permissions, [
            'versions' => $versions,
            'config' => $currentConfig,
            'activeConfig' => $activeConfig,
            'canEditSalary' => $canEditSalary,
            'salaryRows' => $canEditSalary ? app(TechnicalKpiInputController::class)->salaryRows() : collect(),
        ]));
    }

    /**
     * Lưu một phiên bản nháp mới (Draft).
     */
    public function storeDraft(Request $request)
    {
        $permissions = $this->checkAccess($request);
        if (!$permissions['canEdit']) {
            abort(403, 'Bạn không có quyền tạo hoặc chỉnh sửa cấu hình KPI.');
        }

        $request->validate([
            'version_name' => 'required|string|max:190',
            'effective_date' => 'required|date',
            'base_salary_rate' => 'required|numeric|min:0|max:100',
            'kpi_salary_rate' => 'required|numeric|min:0|max:100',
            'round_rule' => 'required|string|in:none,1000,10000,ceil_1000',
            'criteria' => 'required|array',
            'payout_tiers' => 'required|array',
        ]);

        $request->validate(['max_kpi_rate' => 'nullable|numeric|min:0|max:10000']);
        $maxKpiRate = $request->filled('max_kpi_rate') ? (float) $request->input('max_kpi_rate') : null;
        if ($maxKpiRate !== null && $maxKpiRate > 10) {
            $maxKpiRate = $maxKpiRate / 100.0;
        }
        if ($maxKpiRate !== null && $maxKpiRate <= 0) {
            $maxKpiRate = null;
        }

        $baseRate = (float) $request->input('base_salary_rate');
        $kpiRate = (float) $request->input('kpi_salary_rate');

        // Chuẩn hóa tỷ lệ về dạng số thập phân nếu nhập dạng %
        if ($baseRate > 1.0) {
            $baseRate = $baseRate / 100.0;
        }
        if ($kpiRate > 1.0) {
            $kpiRate = $kpiRate / 100.0;
        }

        $salaryStructure = [
            'base_salary_rate' => round($baseRate, 4),
            'kpi_salary_rate' => round($kpiRate, 4),
            'allow_exceed_100' => (bool) $request->input('allow_exceed_100', false),
            // Để trống = không giới hạn; nhập 150 (%) hoặc 1.5 = tối đa 150% lương KPI.
            'max_kpi_rate' => $maxKpiRate,
            'round_rule' => (string) $request->input('round_rule', '1000'),
            'effective_date' => $request->input('effective_date'),
        ];

        // Chuẩn hóa danh sách tiêu chí
        $criteriaInputs = $request->input('criteria', []);
        $formattedCriteria = [];
        $totalActiveWeight = 0;

        foreach ($criteriaInputs as $idx => $c) {
            $code = (string) ($c['code'] ?? 'crit_' . ($idx + 1));
            $isWarranty = ($code === 'warranty');

            $weight = (float) ($c['weight'] ?? 0);
            if ($weight > 1.0) {
                $weight = $weight / 100.0;
            }

            $isCalculated = !empty($c['is_calculated']) && !$isWarranty;
            $status = (string) ($c['status'] ?? ($isWarranty ? 'draft' : 'applied'));

            if ($isWarranty) {
                $status = 'draft';
                $isCalculated = false;
                $weight = 0;
            }

            if ($isCalculated && $status === 'applied') {
                $totalActiveWeight += $weight;
            }

            $formattedCriteria[] = [
                'no' => (int) ($c['no'] ?? ($idx + 1)),
                'code' => $code,
                'name' => (string) ($c['name'] ?? ''),
                'weight' => round($weight, 4),
                'formula' => (string) ($c['formula'] ?? ''),
                'source' => (string) ($c['source'] ?? ''),
                'threshold' => (string) ($c['threshold'] ?? ''),
                'is_calculated' => $isCalculated,
                'status' => $status,
                'pending_reason' => $isWarranty ? 'Nháp — Chờ lãnh đạo xác nhận' : null,
                'effective_date' => $request->input('effective_date'),
            ];
        }

        // Kiểm tra tổng trọng số các tiêu chí đang áp dụng phải bằng 100% (dung sai 0.001)
        if (abs($totalActiveWeight - 1.0) > 0.001) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'criteria_weight' => 'Tổng trọng số các tiêu chí đang áp dụng phải bằng 100% (Hiện tại là: ' . number_format($totalActiveWeight * 100, 1, ',', '.') . '%).',
                ]);
        }

        // Chuẩn hóa mức quy đổi
        $tiersInput = $request->input('payout_tiers', []);
        $formattedTiers = [
            'under_75' => [
                'min' => 0.00,
                'max' => 0.75,
                'rate' => (float) ($tiersInput['under_75']['rate'] ?? 0.0),
                'label' => 'Không đạt (< 75%)',
                'note' => (string) ($tiersInput['under_75']['note'] ?? 'Không nhận tiền lương KPI'),
            ],
            'from_75_to_90' => [
                'min' => 0.75,
                'max' => 0.90,
                'rate' => (float) ($tiersInput['from_75_to_90']['rate'] ?? 0.8),
                'label' => 'Cần cải thiện (75% đến dưới 90%)',
                'note' => (string) ($tiersInput['from_75_to_90']['note'] ?? 'Hưởng 80% tiền lương KPI'),
            ],
            'from_90_to_100' => [
                'min' => 0.90,
                'max' => 1.00,
                'rate' => (float) ($tiersInput['from_90_to_100']['rate'] ?? 1.0),
                'label' => 'Đạt yêu cầu (90% đến dưới 100%)',
                'note' => (string) ($tiersInput['from_90_to_100']['note'] ?? 'Hưởng 100% tiền lương KPI'),
            ],
            'above_100' => [
                'min' => 1.00,
                'max' => 1.30,
                'rate' => (float) ($tiersInput['above_100']['rate'] ?? 1.0),
                'label' => 'Vượt chỉ tiêu (Từ 100% trở lên)',
                'note' => (string) ($tiersInput['above_100']['note'] ?? 'Hưởng 100% (cần duyệt nếu thưởng 110%-120%)'),
            ],
        ];

        $nextVersion = ((int) TechnicalKpiConfig::max('version')) + 1;

        $config = TechnicalKpiConfig::create([
            'version' => $nextVersion,
            'version_name' => (string) $request->input('version_name'),
            'status' => 'draft',
            'is_current' => false,
            'effective_date' => $request->input('effective_date'),
            'salary_structure' => $salaryStructure,
            'criteria_config' => $formattedCriteria,
            'payout_tiers' => $formattedTiers,
            'notes' => (string) $request->input('notes'),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('ky-thuat.kpis.config', ['version_id' => $config->id])
            ->with('success', 'Đã lưu cấu hình phiên bản Nháp v' . $config->version . ' thành công. Bản nháp này chưa ảnh hưởng đến bảng lương cho đến khi được Ban lãnh đạo phê duyệt.');
    }

    /**
     * Phê duyệt và áp dụng một phiên bản cấu hình.
     */
    public function approve(Request $request, $id)
    {
        $permissions = $this->checkAccess($request);
        if (!$permissions['canApprove']) {
            abort(403, 'Chỉ Ban lãnh đạo hoặc Quản trị viên mới có quyền phê duyệt áp dụng cấu hình KPI.');
        }

        $config = TechnicalKpiConfig::findOrFail($id);

        // Hủy trạng thái is_current của các cấu hình cũ
        TechnicalKpiConfig::where('is_current', true)
            ->where('id', '!=', $config->id)
            ->update([
                'is_current' => false,
                'status' => 'archived',
            ]);

        // Cập nhật cấu hình này thành áp dụng
        $config->update([
            'status' => 'applied',
            'is_current' => true,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('ky-thuat.kpis.config', ['version_id' => $config->id])
            ->with('success', 'Đã phê duyệt và kích hoạt áp dụng phiên bản KPI v' . $config->version . ' (' . $config->version_name . ') thành công!');
    }

    /**
     * Xem trước kết quả tính toán theo một phiên bản cấu hình trước khi phê duyệt.
     */
    public function preview(Request $request, $id = null)
    {
        $this->checkAccess($request);

        $config = $id ? TechnicalKpiConfig::findOrFail($id) : TechnicalKpiConfig::getActiveForDate();
        if (!$config) {
            return response()->json(['error' => 'Không tìm thấy cấu hình'], 404);
        }

        // Lấy danh sách nhân viên kỹ thuật có lương mẫu để preview
        $previewSample = [
            [
                'name' => 'Kỹ sư mẫu A (Lương 15.000.000 đ, KPI 85%)',
                'agreed_salary' => 15000000,
                'kpi_percent' => 0.85,
            ],
            [
                'name' => 'Kỹ sư mẫu B (Lương 18.000.000 đ, KPI 95%)',
                'agreed_salary' => 18000000,
                'kpi_percent' => 0.95,
            ],
            [
                'name' => 'Kỹ sư mẫu C (Lương 12.000.000 đ, KPI 105%)',
                'agreed_salary' => 12000000,
                'kpi_percent' => 1.05,
            ],
            [
                'name' => 'Kỹ sư mẫu D (Lương 15.000.000 đ, KPI 68% - Không đạt)',
                'agreed_salary' => 15000000,
                'kpi_percent' => 0.68,
            ],
        ];

        $results = [];
        foreach ($previewSample as $sample) {
            $payout = $config->computePayout($sample['agreed_salary'], $sample['kpi_percent']);
            $results[] = array_merge($sample, $payout);
        }

        return response()->json([
            'version' => $config->version,
            'version_name' => $config->version_name,
            'status' => $config->status,
            'salary_structure' => $config->salary_structure,
            'preview_results' => $results,
        ]);
    }
}
