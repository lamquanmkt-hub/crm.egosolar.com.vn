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
        if (! $user) {
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
            && ! $isAdmin
            && ! $isHrOrAccounting
            && ! $isTechnicalManager;

        // Kỹ thuật viên tuyệt đối không được truy cập -> 403 Forbidden
        if ($isTechnician) {
            abort(403, 'Bạn không có quyền truy cập trang Cài đặt KPI.');
        }

        if (! $isAdmin && ! $isHrOrAccounting && ! $isTechnicalManager) {
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
        if (! $permissions['canEdit']) {
            abort(403, 'Bạn không có quyền tạo hoặc chỉnh sửa cấu hình KPI.');
        }

        $request->validate([
            'version_name' => 'required|string|max:190',
            'effective_date' => 'required|date',
            'base_salary_rate' => 'required|numeric|min:0|max:100',
            'kpi_salary_rate' => 'required|numeric|min:0|max:100',
            'round_rule' => 'required|string|in:none,1000,10000,ceil_1000',
            'criteria' => 'required|array',
            // Form cũ không gửi chế độ: giữ trả theo bậc như trước.
            'payout_mode' => 'nullable|string|in:'.implode(',', array_keys(TechnicalKpiConfig::PAYOUT_MODE_LABELS)),
            // Bảng bậc chỉ bắt buộc khi trả theo bậc; chế độ tỷ lệ không dùng bậc để tính tiền.
            'payout_tiers' => 'required_unless:payout_mode,'.TechnicalKpiConfig::PAYOUT_PROPORTIONAL.'|array',
            'payout_tiers.*.rate' => 'nullable|numeric|min:0|max:2',
        ]);
        $payoutMode = (string) ($request->input('payout_mode') ?: TechnicalKpiConfig::PAYOUT_TIERED);

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
            'payout_mode' => $payoutMode,
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
        $standard = app(KpiStandardEvaluator::class)->forMonth(Carbon::parse($request->input('effective_date'))->format('Y-m'));
        $warrantyReady = collect($standard->criteria())->firstWhere('code', 'warranty')['weight'] !== null;
        $formattedCriteria = [];
        $totalActiveWeight = 0;

        foreach ($criteriaInputs as $idx => $c) {
            $code = (string) ($c['code'] ?? 'crit_'.($idx + 1));
            $isWarranty = ($code === 'warranty');

            $weight = (float) ($c['weight'] ?? 0);
            if ($weight > 1.0) {
                $weight = $weight / 100.0;
            }

            $isCalculated = ! empty($c['is_calculated']) && (! $isWarranty || $warrantyReady);
            $status = (string) ($c['status'] ?? ($isWarranty && ! $warrantyReady ? 'draft' : 'applied'));

            if ($isWarranty && ! $warrantyReady) {
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
                'pending_reason' => $isWarranty && ! $warrantyReady ? 'Nháp — Chờ lãnh đạo xác nhận' : null,
                'effective_date' => $request->input('effective_date'),
            ];
        }

        if ($warrantyReady && ! $this->matchesConfirmedWeights($formattedCriteria, $standard)) {
            return back()->withInput()->withErrors(['criteria_weight' => 'Bộ kỹ thuật từ 10/2026 phải đủ 6 tiêu chí, trọng số 25/15/15/10/5/30, tổng 100%.']);
        }

        // Kiểm tra tổng trọng số các tiêu chí đang áp dụng phải bằng 100% (dung sai 0.001)
        if (abs($totalActiveWeight - 1.0) > 0.001) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'criteria_weight' => 'Tổng trọng số các tiêu chí đang áp dụng phải bằng 100% (Hiện tại là: '.number_format($totalActiveWeight * 100, 1, ',', '.').'%).',
                ]);
        }

        // Chuẩn hóa mức quy đổi. Chế độ tỷ lệ không gửi bậc: giữ bậc mặc định chỉ để tham chiếu, không dùng tính tiền.
        $tiersInput = $payoutMode === TechnicalKpiConfig::PAYOUT_PROPORTIONAL ? [] : (array) $request->input('payout_tiers', []);
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
            ->with('success', 'Đã lưu cấu hình phiên bản Nháp v'.$config->version.' thành công. Bản nháp này chưa ảnh hưởng đến bảng lương cho đến khi được Ban lãnh đạo phê duyệt.');
    }

    /**
     * Phê duyệt và áp dụng một phiên bản cấu hình.
     */
    public function approve(Request $request, $id)
    {
        $permissions = $this->checkAccess($request);
        if (! $permissions['canApprove']) {
            abort(403, 'Chỉ Ban lãnh đạo hoặc Quản trị viên mới có quyền phê duyệt áp dụng cấu hình KPI.');
        }

        $config = TechnicalKpiConfig::findOrFail($id);
        $standard = app(KpiStandardEvaluator::class)->forMonth($config->effective_date?->format('Y-m') ?? '2026-09');
        if (collect($standard->criteria())->firstWhere('code', 'warranty')['weight'] !== null
            && ! $this->matchesConfirmedWeights((array) $config->criteria_config, $standard)) {
            return back()->withErrors(['criteria_weight' => 'Không thể duyệt bộ từ 10/2026 thiếu tiêu chí hoặc sai trọng số 25/15/15/10/5/30.']);
        }

        DB::transaction(function () use ($config): void {
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
        });

        return redirect()
            ->route('ky-thuat.kpis.config', ['version_id' => $config->id])
            ->with('success', 'Đã phê duyệt và kích hoạt áp dụng phiên bản KPI v'.$config->version.' ('.$config->version_name.') thành công!');
    }

    private function matchesConfirmedWeights(array $criteria, KpiStandardEvaluator $standard): bool
    {
        $active = collect($criteria)->filter(fn ($c) => ! empty($c['is_calculated']) && ($c['status'] ?? '') === 'applied');
        $expected = collect($standard->criteria())->filter(fn ($c) => $c['weight'] !== null);
        if ($active->count() !== $expected->count() || $active->pluck('code')->unique()->count() !== $expected->count()) {
            return false;
        }

        return $expected->every(function ($criterion) use ($active): bool {
            $match = $active->firstWhere('code', $criterion['code']);

            return $match && abs((float) ($match['weight'] ?? 0) - (float) $criterion['weight']) < 0.0001;
        });
    }

    /**
     * Xem trước kết quả tính toán theo một phiên bản cấu hình trước khi phê duyệt.
     */
    public function preview(Request $request, $id = null)
    {
        $this->checkAccess($request);

        $config = $id ? TechnicalKpiConfig::findOrFail($id) : TechnicalKpiConfig::getActiveForDate();
        if (! $config) {
            return response()->json(['error' => 'Không tìm thấy cấu hình'], 404);
        }

        // Lương mẫu giả 15.000.000 đ ở các mốc biên (null = thiếu dữ liệu KPI, khác 0%).
        $previewSample = [];
        foreach ([null, 0.0, 0.68, 0.75, 0.85, 0.875, 0.90, 0.95, 1.00, 1.05] as $percent) {
            $previewSample[] = [
                'name' => 'KPI '.($percent === null ? 'chưa đủ dữ liệu' : rtrim(rtrim(number_format($percent * 100, 2, ',', '.'), '0'), ',').'%'),
                'agreed_salary' => 15000000,
                'kpi_percent' => $percent,
            ];
        }

        $results = [];
        foreach ($previewSample as $sample) {
            $payout = $config->computePayout($sample['agreed_salary'], $sample['kpi_percent']);
            $results[] = array_merge($sample, $payout);
        }

        return response()->json([
            'version' => $config->version,
            'version_name' => $config->version_name,
            'status' => $config->status,
            'payout_mode' => $config->payoutMode(),
            'payout_mode_label' => TechnicalKpiConfig::PAYOUT_MODE_LABELS[$config->payoutMode()],
            'salary_structure' => $config->salary_structure,
            'preview_results' => $results,
        ]);
    }
}
