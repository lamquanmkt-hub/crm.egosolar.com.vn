<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\TechnicalKpiConfig;
use App\Models\User;
use App\Support\EgoCompanyLock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * KPI kỹ thuật tính theo số liệu tháng:
 * - Trọng số lấy từ cấu hình đã duyệt; tiêu chí được vượt 100% khi Thực tế > Kế hoạch.
 * - Trưởng phòng cộng/trừ điểm (1 điểm = 1% KPI) kèm lý do.
 * - Lương thoả thuận lấy từ Cài đặt KPI; phiếu đã duyệt giữ số đã chốt.
 * - Vượt 100% trả theo tỷ lệ, trần để trống = không giới hạn.
 * - Tiến độ tự lấy từ dự án /du-an khi chưa nhập tay.
 */
final class TechnicalKpiMonthlyInputTest extends TestCase
{
    use DatabaseTransactions;

    private const MONTH = '2026-09';

    private const CODES = ['timeline', 'quality', 'survey', 'hse', 'evn_app'];

    private User $admin;

    private User $manager;

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('technical_kpi_configs')->update(['is_current' => false, 'status' => 'archived']);
        $this->admin = $this->userWithRole('admin');
        $this->manager = $this->userWithRole('technical_manager');
        $this->engineer = $this->userWithRole('ky_thuat', ['name' => 'Kỹ sư Test KPI']);
    }

    /** Cấu hình KPI đã duyệt: mặc định 30/25/15/15/15, 70/30, cho vượt 100%, trần tuỳ chọn. */
    private function config(array $weights = [], bool $allowExceed = true, ?float $maxRate = null): TechnicalKpiConfig
    {
        $weights += ['timeline' => 0.30, 'quality' => 0.25, 'survey' => 0.15, 'hse' => 0.15, 'evn_app' => 0.15];
        $no = 0;

        return TechnicalKpiConfig::query()->create([
            'version' => 900 + random_int(1, 99),
            'version_name' => 'Test',
            'status' => 'applied',
            'is_current' => true,
            'effective_date' => '2026-01-01',
            'salary_structure' => ['base_salary_rate' => 0.7, 'kpi_salary_rate' => 0.3, 'allow_exceed_100' => $allowExceed, 'max_kpi_rate' => $maxRate, 'round_rule' => '1000'],
            'criteria_config' => collect($weights)->map(fn ($w, $code) => [
                'no' => ++$no, 'code' => $code, 'name' => 'Tiêu chí '.$code, 'weight' => $w,
                'formula' => '', 'source' => '', 'threshold' => '', 'is_calculated' => $w > 0, 'status' => $w > 0 ? 'applied' : 'draft',
            ])->values()->all(),
            'payout_tiers' => [
                'under_75' => ['rate' => 0.0], 'from_75_to_90' => ['rate' => 0.8],
                'from_90_to_100' => ['rate' => 1.0], 'above_100' => ['rate' => 1.0],
            ],
            'approved_at' => now(),
        ]);
    }

    private function salary(float $amount, string $month = '2026-01', ?User $user = null): void
    {
        DB::table('technical_kpi_salaries')->insert([
            'user_id' => ($user ?? $this->engineer)->id, 'agreed_salary' => $amount, 'effective_month' => $month,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param array<string, array{0: float, 1: float}> $values code => [plan, actual] */
    private function inputs(array $values, ?User $as = null)
    {
        $scores = [];
        foreach ($values as $code => [$plan, $actual]) {
            $scores[$this->engineer->id][$code] = ['plan' => $plan, 'actual' => $actual];
        }

        return $this->actingAs($as ?? $this->manager)->post(route('ky-thuat.kpis.input.store'), ['month' => self::MONTH, 'scores' => $scores]);
    }

    private function allPerfect(): array
    {
        return array_fill_keys(self::CODES, [2, 2]);
    }

    /** @return array<string, mixed> dòng của kỹ sư test trên Bảng KPI */
    private function kpiRow(): array
    {
        $engineers = $this->actingAs($this->admin)
            ->get(route('ky-thuat.kpis.index', ['period' => 'month', 'month' => self::MONTH]))
            ->assertOk()
            ->viewData('engineers');

        $row = collect($engineers)->firstWhere('id', $this->engineer->id);
        $this->assertNotNull($row, 'Kỹ sư test không có trong Bảng KPI');

        return $row;
    }

    // ------------------------------------------------------------------ tính điểm

    public function test_manual_inputs_compute_kpi_with_config_weights(): void
    {
        $this->config();
        $this->inputs($this->allPerfect())->assertSessionHasNoErrors();

        $row = $this->kpiRow();

        $this->assertEqualsWithDelta(1.0, $row['kpi_percent'], 1e-9);
        $this->assertSame([], array_values(array_filter($row['missing'], fn ($m) => $m !== 'Lương thoả thuận')));
    }

    public function test_criterion_can_exceed_100_percent_when_actual_beats_plan(): void
    {
        $this->config();
        $this->inputs(['timeline' => [3, 4]] + $this->allPerfect());

        // 0.30 × 4/3 + 0.70 × 1 = 1.10
        $this->assertEqualsWithDelta(1.10, $this->kpiRow()['kpi_percent'], 1e-9);
    }

    public function test_adjustment_points_change_total_and_require_reason(): void
    {
        $this->config();
        $this->inputs($this->allPerfect());

        $this->actingAs($this->manager)->post(route('ky-thuat.kpis.input.adjust'), [
            'month' => self::MONTH, 'user_id' => $this->engineer->id, 'points' => -10, 'reason' => '',
        ])->assertSessionHasErrors('reason');

        $this->actingAs($this->manager)->post(route('ky-thuat.kpis.input.adjust'), [
            'month' => self::MONTH, 'user_id' => $this->engineer->id, 'points' => -10, 'reason' => 'Không đeo dây an toàn khi lên mái',
        ])->assertSessionHasNoErrors();

        $row = $this->kpiRow();
        $this->assertEqualsWithDelta(0.90, $row['kpi_percent'], 1e-9);
        $this->assertEqualsWithDelta(-10.0, $row['result']['adjustment_points'], 1e-9);
    }

    public function test_weights_come_from_approved_config_not_hardcoded_file(): void
    {
        $this->config(['timeline' => 0.5, 'quality' => 0.5, 'survey' => 0, 'hse' => 0, 'evn_app' => 0]);
        $this->inputs(['timeline' => [2, 2], 'quality' => [2, 1]]);

        // 0.5 × 1 + 0.5 × 0.5 = 0.75 — chỉ 2 tiêu chí có trọng số, không đòi dữ liệu 3 tiêu chí kia.
        $this->assertEqualsWithDelta(0.75, $this->kpiRow()['kpi_percent'], 1e-9);
    }

    public function test_missing_criterion_keeps_kpi_uncomputed_and_lists_what_is_missing(): void
    {
        $this->config();
        $this->inputs(['timeline' => [2, 2], 'quality' => [2, 2]]);

        $row = $this->kpiRow();
        $this->assertNull($row['kpi_percent']);
        $this->assertContains('Lương thoả thuận', $row['missing']);
        $this->assertContains('Tiêu chí hse', $row['missing']);
    }

    // ------------------------------------------------------------------ tiền lương

    public function test_salary_falls_back_to_previously_entered_payroll_then_official_salary(): void
    {
        $this->config();
        $this->inputs($this->allPerfect());

        // Lương chính thức trong hồ sơ nhân viên (nguồn cũ).
        DB::table('users')->where('id', $this->engineer->id)->update(['official_salary' => 9_000_000]);
        $this->assertEqualsWithDelta(9_000_000, $this->kpiRow()['agreed_salary'], 0.01);

        // Lương đã nhập ở phiếu lương KPI trước khi có Cài đặt KPI → vẫn được dùng, không bị mất.
        DB::table('technical_kpi_payrolls')->insert([
            'user_id' => $this->engineer->id, 'employee_name' => $this->engineer->name, 'payroll_month' => self::MONTH, 'gross_salary' => 12_000_000, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertEqualsWithDelta(12_000_000, $this->kpiRow()['agreed_salary'], 0.01);

        // Nhập ở Cài đặt KPI thì số mới được ưu tiên.
        $this->salary(14_000_000, '2026-09');
        $this->assertEqualsWithDelta(14_000_000, $this->kpiRow()['agreed_salary'], 0.01);
    }

    public function test_salary_from_kpi_settings_takes_priority_over_draft_payroll(): void
    {
        $this->config();
        $this->salary(15_000_000, '2026-01');
        $this->salary(17_000_000, '2026-08');
        $this->salary(20_000_000, '2026-10'); // chưa tới hiệu lực trong kỳ 09
        DB::table('technical_kpi_payrolls')->insert([
            'user_id' => $this->engineer->id, 'employee_name' => $this->engineer->name, 'payroll_month' => self::MONTH, 'gross_salary' => 12_000_000, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->inputs($this->allPerfect());

        $row = $this->kpiRow();
        $this->assertEqualsWithDelta(17_000_000, $row['agreed_salary'], 0.01);
        $this->assertEqualsWithDelta(11_900_000, $row['base_salary'], 0.01);
        $this->assertEqualsWithDelta(5_100_000, $row['kpi_base_salary'], 0.01);
        $this->assertEqualsWithDelta(5_100_000, $row['real_kpi_salary'], 0.01);
        $this->assertEqualsWithDelta(17_000_000, $row['total_income'], 0.01);
    }

    public function test_exceeding_kpi_pays_proportionally_without_cap(): void
    {
        $this->config(allowExceed: true, maxRate: null);
        $this->salary(15_000_000);
        $this->inputs(['timeline' => [3, 4]] + $this->allPerfect()); // KPI 110%

        $row = $this->kpiRow();
        // Quỹ KPI 4.500.000 × 110% = 4.950.000
        $this->assertEqualsWithDelta(4_950_000, $row['real_kpi_salary'], 0.01);
    }

    public function test_payout_cap_is_applied_when_configured(): void
    {
        $capped = $this->config(allowExceed: true, maxRate: 1.05);
        $this->assertEqualsWithDelta(4_725_000, $capped->computePayout(15_000_000, 1.10)['real_kpi_salary'], 0.01);

        $unlimited = $this->config(allowExceed: true, maxRate: null);
        $this->assertEqualsWithDelta(9_000_000, $unlimited->computePayout(15_000_000, 2.0)['real_kpi_salary'], 0.01);
    }

    public function test_payout_tiers_below_100_are_unchanged(): void
    {
        $config = $this->config();

        $this->assertEqualsWithDelta(0, $config->computePayout(15_000_000, 0.70)['real_kpi_salary'], 0.01);
        $this->assertEqualsWithDelta(3_600_000, $config->computePayout(15_000_000, 0.80)['real_kpi_salary'], 0.01);
        $this->assertEqualsWithDelta(4_500_000, $config->computePayout(15_000_000, 0.95)['real_kpi_salary'], 0.01);
    }

    // ------------------------------------------------------------------ tự động từ /du-an

    public function test_timeline_is_auto_computed_from_du_an_projects(): void
    {
        $this->config();
        $project = fn (string $deadline, string $installed) => (int) DB::table('project_test_projects')->insertGetId([
            'code' => 'KPI-T-'.uniqid(), 'company_id' => EgoCompanyLock::id(), 'name' => 'Dự án test KPI', 'created_by' => $this->admin->id,
            'lead_technician_id' => $this->engineer->id, 'target_completion_at' => $deadline, 'installed_at' => $installed,
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $project('2026-09-10', '2026-09-08');  // đúng hạn
        $project('2026-09-12', '2026-09-20');  // trễ

        $timeline = collect($this->kpiRow()['result']['criteria'])->firstWhere('code', 'timeline');

        $this->assertSame(1, $timeline['passed']);
        $this->assertSame(2, $timeline['total']);
        $this->assertEqualsWithDelta(0.5, $timeline['rate'], 1e-9);
    }

    public function test_timeline_uses_completion_logged_in_du_an_workflow_when_installed_at_is_empty(): void
    {
        $this->config();
        // Dự án mới: không có installed_at; kỹ thuật báo hoàn tất thi công → lịch sử chuyển "Chờ nghiệm thu".
        $onTime = (int) DB::table('project_test_projects')->insertGetId([
            'code' => 'KPI-W-'.uniqid(), 'company_id' => EgoCompanyLock::id(), 'name' => 'Đúng hạn', 'created_by' => $this->admin->id,
            'lead_technician_id' => $this->engineer->id, 'target_completion_at' => '2026-09-20', 'status' => 'acceptance_pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $late = (int) DB::table('project_test_projects')->insertGetId([
            'code' => 'KPI-W-'.uniqid(), 'company_id' => EgoCompanyLock::id(), 'name' => 'Trễ', 'created_by' => $this->admin->id,
            'lead_technician_id' => $this->engineer->id, 'target_completion_at' => '2026-09-05', 'status' => 'acceptance_pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([[$onTime, '2026-09-18 16:00:00'], [$late, '2026-09-12 09:00:00']] as [$id, $at]) {
            DB::table('project_test_histories')->insert([
                'project_id' => $id, 'action' => 'Kỹ thuật hoàn tất thi công và gửi nghiệm thu', 'from_status' => 'installing',
                'to_status' => 'acceptance_pending', 'created_at' => $at, 'updated_at' => $at,
            ]);
        }

        $timeline = collect($this->kpiRow()['result']['criteria'])->firstWhere('code', 'timeline');
        $this->assertSame(1, $timeline['passed']);
        $this->assertSame(2, $timeline['total']);
    }

    public function test_manual_input_overrides_auto_timeline(): void
    {
        $this->config();
        DB::table('project_test_projects')->insert([
            'code' => 'KPI-T-'.uniqid(), 'company_id' => EgoCompanyLock::id(), 'name' => 'Dự án trễ', 'created_by' => $this->admin->id,
            'lead_technician_id' => $this->engineer->id, 'target_completion_at' => '2026-09-05', 'installed_at' => '2026-09-25',
            'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->inputs(['timeline' => [1, 1]]);

        $timeline = collect($this->kpiRow()['result']['criteria'])->firstWhere('code', 'timeline');
        $this->assertEqualsWithDelta(1.0, $timeline['rate'], 1e-9);
    }

    /** Dự án module Công trình cũ (project_test_*) có kỹ sư test phụ trách, nghiệm thu trong tháng 09. */
    private function acceptedProject(array $acceptance = [], bool $reworked = false, ?array $material = null): int
    {
        $id = (int) DB::table('project_test_projects')->insertGetId([
            'code' => 'KPI-A-'.uniqid(), 'company_id' => EgoCompanyLock::id(), 'name' => 'Dự án nghiệm thu', 'created_by' => $this->admin->id,
            'lead_technician_id' => $this->engineer->id, 'status' => 'warranty_active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_test_acceptances')->insert($acceptance + [
            'project_id' => $id, 'submitted_by' => $this->manager->id, 'accepted_at' => '2026-09-15', 'status' => 'approved',
            'monitoring_link' => null, 'monitoring_account' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($reworked) {
            DB::table('project_test_histories')->insert([
                'project_id' => $id, 'action' => 'Trả về thi công lại', 'from_status' => 'acceptance_pending', 'to_status' => 'installing',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if ($material !== null) {
            [$planned, $extra, $returned] = $material;
            $request = (int) DB::table('project_test_material_requests')->insertGetId([
                'project_id' => $id, 'code' => 'VT-'.uniqid(), 'requested_by' => $this->engineer->id, 'request_kind' => 'standard', 'status' => 'issued',
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('project_test_material_items')->insert([
                'material_request_id' => $request, 'item_name' => 'Tấm pin', 'quantity' => $planned, 'issued_quantity' => $planned,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([['additional', $extra, 'completed'], ['return', $returned, 'completed']] as [$type, $qty, $status]) {
                if ($qty <= 0) {
                    continue;
                }
                $ac = (int) DB::table('project_test_material_aftercare_requests')->insertGetId([
                    'project_id' => $id, 'material_request_id' => $request, 'code' => 'AC-'.uniqid(), 'type' => $type, 'status' => $status,
                    'requested_by' => $this->engineer->id, 'technical_note' => 'test', 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('project_test_material_aftercare_items')->insert([
                    'aftercare_request_id' => $ac, 'item_name' => 'Tấm pin', 'quantity' => $qty, 'processed_quantity' => $qty,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $id;
    }

    private function criterion(string $code): array
    {
        return collect($this->kpiRow()['result']['criteria'])->firstWhere('code', $code);
    }

    public function test_quality_is_auto_from_acceptance_first_pass(): void
    {
        $this->config();
        $this->acceptedProject();
        $this->acceptedProject(reworked: true);

        $quality = $this->criterion('quality');
        $this->assertSame(1, $quality['passed']);
        $this->assertSame(2, $quality['total']);
    }

    public function test_evn_app_is_auto_from_monitoring_link(): void
    {
        $this->config();
        $this->acceptedProject(['monitoring_link' => 'https://app.solar.test/plant/1']);
        $this->acceptedProject(['monitoring_account' => 'chunha@solar']);
        $this->acceptedProject();

        $evn = $this->criterion('evn_app');
        $this->assertSame(2, $evn['passed']);
        $this->assertSame(3, $evn['total']);
    }

    public function test_material_deviation_is_auto_from_material_requests(): void
    {
        $this->config();
        $this->acceptedProject(material: [100, 2, 0]);   // lệch 2% → đạt (< 3%)
        $this->acceptedProject(material: [100, 10, 5]);  // lệch 5% → không đạt
        $this->acceptedProject();                        // không có phiếu vật tư → không tính

        $survey = $this->criterion('survey');
        $this->assertSame(1, $survey['passed']);
        $this->assertSame(2, $survey['total']);
    }

    // ------------------------------------------------------------------ quy trình dự án /du-an (workflow V2)

    /**
     * Công trình /du-an (sites + project_workflow_steps) có bước Nghiệm thu được duyệt trong tháng 09,
     * kỹ sư test được phân công ở bước Thi công.
     *
     * @param  array  $acceptanceData  dữ liệu đánh giá KPI lưu ở bước nghiệm thu
     * @param  bool  $surveyComplete  đủ hồ sơ khảo sát + bản vẽ sơ bộ + bảng khối lượng vật tư
     * @param  array|null  $proposals  [SL đề xuất ban đầu, SL đề xuất bổ sung]
     */
    private function workflowSite(array $acceptanceData = [], bool $surveyComplete = true, ?array $proposals = null,
        int $reworks = 0, bool $needsGrid = false, ?string $monitoringLink = null, string $approvedAt = '2026-09-20 10:00:00'): int
    {
        $siteId = (int) DB::table('sites')->insertGetId([
            'name' => 'Công trình KPI '.uniqid(), 'project_code' => 'DA-KPI-'.uniqid(), 'company_id' => EgoCompanyLock::id(),
            'monitoring_link' => $monitoringLink, 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $steps = [];
        foreach (['survey' => 1, 'proposal' => 2, 'contract' => 3, 'legal' => 4, 'construction' => 5, 'acceptance' => 6] as $code => $seq) {
            $data = match ($code) {
                'legal' => ['requires_grid_connection' => $needsGrid],
                'acceptance' => $acceptanceData,
                default => [],
            };
            $steps[$code] = (int) DB::table('project_workflow_steps')->insertGetId([
                'site_id' => $siteId, 'step_code' => $code, 'sequence' => $seq, 'status' => 'approved',
                'approved_at' => $code === 'acceptance' ? $approvedAt : '2026-09-01 08:00:00',
                'data' => json_encode($data), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('project_workflow_assignments')->insert([
            'workflow_step_id' => $steps['construction'], 'user_id' => $this->engineer->id, 'assignment_role' => 'primary',
            'status' => 'submitted', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($surveyComplete) {
            $docs = ['survey' => ['survey_report' => 1, 'site_photos' => 5, 'measurement_sheet' => 1, 'risk_assessment' => 1],
                'proposal' => ['preliminary_drawing' => 1, 'material_boq' => 1]];
            foreach ($docs as $step => $codes) {
                foreach ($codes as $docCode => $count) {
                    for ($i = 0; $i < $count; $i++) {
                        DB::table('project_workflow_documents')->insert([
                            'workflow_step_id' => $steps[$step], 'site_id' => $siteId, 'document_code' => $docCode, 'title' => $docCode,
                            'path' => 'kpi-test/'.$docCode.$i.'.pdf', 'original_name' => $docCode.$i.'.pdf', 'version' => 1,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        foreach (['INITIAL' => $proposals[0] ?? 0, 'ADDITIONAL' => $proposals[1] ?? 0] as $type => $qty) {
            if ($qty <= 0) {
                continue;
            }
            $proposalId = (int) DB::table('project_material_proposals')->insertGetId([
                'site_id' => $siteId, 'proposal_type' => $type, 'status' => 'EXPORTED', 'created_by' => $this->engineer->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('project_material_proposal_items')->insert([
                'proposal_id' => $proposalId, 'requested_name' => 'Tấm pin', 'requested_qty' => $qty, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        for ($i = 0; $i < $reworks; $i++) {
            DB::table('project_workflow_events')->insert([
                'site_id' => $siteId, 'workflow_step_id' => $steps['acceptance'], 'actor_id' => $this->manager->id,
                'action' => 'step_revision_requested', 'from_status' => 'submitted', 'to_status' => 'revision', 'note' => 'Làm lại', 'created_at' => now(),
            ]);
        }

        return $siteId;
    }

    private function hseAll(bool $value = true): array
    {
        return array_fill_keys(array_keys(\App\Services\TechnicalKpi\ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS), $value);
    }

    public function test_workflow_quality_counts_revisions_and_customer_complaints(): void
    {
        $this->config();
        $this->workflowSite(['kpi_customer_complaint' => false]);
        $this->workflowSite(['kpi_customer_complaint' => false], reworks: 1);
        $this->workflowSite(['kpi_customer_complaint' => true, 'kpi_customer_complaint_note' => 'Để rác trên mái']);

        $quality = $this->criterion('quality');
        $this->assertSame(1, $quality['passed']);
        $this->assertSame(3, $quality['total']);
        $details = collect($quality['evidence'])->pluck('detail')->implode(' | ');
        $this->assertStringContainsString('bị trả lại 1 lần', $details);
        $this->assertStringContainsString('Để rác trên mái', $details);
    }

    public function test_workflow_hse_uses_acceptance_checklist_and_is_empty_until_reviewed(): void
    {
        $this->config();
        $this->workflowSite($this->hseAll());
        $this->workflowSite(['kpi_site_cleaned' => false] + $this->hseAll());
        $this->workflowSite(); // chưa đánh giá HSE → không tính

        $hse = $this->criterion('hse');
        $this->assertSame(1, $hse['passed']);
        $this->assertSame(2, $hse['total']);
    }

    public function test_workflow_evn_requires_grid_test_only_when_connection_needed(): void
    {
        $this->config();
        $this->workflowSite(['kpi_evn_grid_tested' => true, 'kpi_app_installed' => true], needsGrid: true);     // đạt
        $this->workflowSite(['kpi_evn_grid_tested' => false, 'kpi_app_installed' => true], needsGrid: true);    // thiếu đóng điện
        $this->workflowSite(['kpi_app_installed' => false], monitoringLink: 'https://app.solar.test/plant/3'); // không cần đấu nối, có App
        $this->workflowSite(['kpi_evn_grid_tested' => true, 'kpi_app_installed' => false]);                    // chưa cài App

        $evn = $this->criterion('evn_app');
        $this->assertSame(2, $evn['passed']);
        $this->assertSame(4, $evn['total']);
    }

    public function test_workflow_survey_needs_complete_documents_and_low_material_deviation(): void
    {
        $this->config();
        $this->workflowSite(proposals: [100, 2]);                                         // đủ hồ sơ, lệch 2% → đạt
        $this->workflowSite(proposals: [100, 5]);                                         // lệch 5% → không đạt
        $this->workflowSite(surveyComplete: false, proposals: [100, 0]);                  // thiếu hồ sơ khảo sát
        $this->workflowSite(['kpi_material_deviation_percent' => 1.5], proposals: [100, 9]); // số quyết toán nhập tay ưu tiên → đạt

        $survey = $this->criterion('survey');
        $this->assertSame(2, $survey['passed']);
        $this->assertSame(4, $survey['total']);
        $this->assertStringContainsString('Biên bản khảo sát', collect($survey['evidence'])->pluck('result')->implode(' | '));
    }

    public function test_workflow_projects_approved_outside_month_are_ignored(): void
    {
        $this->config();
        $this->workflowSite($this->hseAll(), approvedAt: '2026-08-31 17:00:00');

        $this->assertNull($this->criterion('hse')['rate']);
    }

    public function test_acceptance_step_form_saves_kpi_review(): void
    {
        $siteId = $this->workflowSite();
        $site = \App\Models\Projects\Site::findOrFail($siteId);
        $url = route('projects-unified.workflow.data.save', [$site, 'acceptance']);

        $this->actingAs($this->admin)->post($url, ['kpi_review_submitted' => 1, 'kpi_customer_complaint' => 1])
            ->assertSessionHasErrors('kpi_customer_complaint_note');

        $this->actingAs($this->admin)->post($url, [
            'kpi_review_submitted' => 1, 'kpi_customer_complaint' => 0, 'kpi_evn_grid_tested' => 1, 'kpi_app_installed' => 1,
            'kpi_material_deviation_percent' => '2.5',
        ] + $this->hseAll())->assertSessionHasNoErrors();

        $data = json_decode((string) DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'acceptance')->value('data'), true);
        $this->assertFalse($data['kpi_customer_complaint']);
        $this->assertTrue($data['kpi_site_cleaned']);
        $this->assertTrue($data['kpi_evn_grid_tested']);
        $this->assertEqualsWithDelta(2.5, $data['kpi_material_deviation_percent'], 1e-9);
        $this->assertSame($this->admin->id, $data['kpi_reviewed_by']);

        // Bỏ tick một mục HSE → HSE không đạt.
        $this->actingAs($this->admin)->post($url, ['kpi_review_submitted' => 1, 'kpi_customer_complaint' => 0] + array_diff_key($this->hseAll(), ['kpi_roof_intact' => 1]))
            ->assertSessionHasNoErrors();
        $data = json_decode((string) DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'acceptance')->value('data'), true);
        $this->assertFalse($data['kpi_roof_intact']);
        $this->assertSame('', $data['kpi_material_deviation_percent']);

        $this->config();
        $this->assertSame(0, $this->criterion('hse')['passed']);
    }

    public function test_assigned_engineer_cannot_review_own_kpi_at_acceptance(): void
    {
        $siteId = $this->workflowSite();
        $stepId = (int) DB::table('project_workflow_steps')->where('site_id', $siteId)->where('step_code', 'acceptance')->value('id');
        DB::table('project_workflow_assignments')->insert([
            'workflow_step_id' => $stepId, 'user_id' => $this->engineer->id, 'assignment_role' => 'primary',
            'status' => 'in_progress', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $site = \App\Models\Projects\Site::findOrFail($siteId);

        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.data.save', [$site, 'acceptance']), [
            'kpi_review_submitted' => 1, 'kpi_customer_complaint' => 0,
        ] + $this->hseAll())->assertForbidden();

        // Vẫn lưu được dữ liệu bước bình thường (không kèm đánh giá KPI).
        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.data.save', [$site, 'acceptance']), [
            'step_note' => 'Đã bàn giao',
        ] + $this->hseAll())->assertSessionHasNoErrors();

        $data = json_decode((string) DB::table('project_workflow_steps')->where('id', $stepId)->value('data'), true);
        $this->assertArrayNotHasKey('kpi_site_cleaned', $data);
        $this->assertArrayNotHasKey('kpi_reviewed_by', $data);
    }

    public function test_hse_has_no_auto_source_and_needs_manual_input(): void
    {
        $this->config();
        $this->acceptedProject(['monitoring_link' => 'https://app.solar.test/plant/9'], material: [50, 0, 0]);

        $this->assertNull($this->criterion('hse')['rate']);
        $this->assertContains('Tiêu chí hse', $this->kpiRow()['missing']);

        $this->inputs(['hse' => [1, 1]]);
        $this->assertEqualsWithDelta(1.0, $this->criterion('hse')['rate'], 1e-9);
    }

    public function test_projects_accepted_outside_month_are_ignored(): void
    {
        $this->config();
        $this->acceptedProject(['accepted_at' => '2026-08-30', 'monitoring_link' => 'https://app.solar.test/plant/2']);

        $this->assertNull($this->criterion('evn_app')['rate']);
    }

    // ------------------------------------------------------------------ nhập liệu & quyền

    public function test_input_validation_requires_both_plan_and_actual_and_positive_plan(): void
    {
        $this->config();

        $this->inputs(['timeline' => [2, null]])->assertSessionHasErrors();
        $this->inputs(['quality' => [0, 1]])->assertSessionHasErrors();
        $this->assertSame(0, DB::table('technical_kpi_monthly_scores')->where('user_id', $this->engineer->id)->count());
    }

    public function test_clearing_inputs_removes_saved_row(): void
    {
        $this->config();
        $this->inputs(['timeline' => [2, 2]]);
        $this->assertSame(1, DB::table('technical_kpi_monthly_scores')->where('user_id', $this->engineer->id)->count());

        $this->actingAs($this->manager)->post(route('ky-thuat.kpis.input.store'), [
            'month' => self::MONTH, 'scores' => [$this->engineer->id => ['timeline' => ['plan' => '', 'actual' => '']]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('technical_kpi_monthly_scores')->where('user_id', $this->engineer->id)->count());
    }

    public function test_only_managers_can_input_and_only_admin_hr_accounting_can_set_salary(): void
    {
        $this->config();

        $this->actingAs($this->engineer)->get(route('ky-thuat.kpis.input'))->assertForbidden();
        $this->inputs(['timeline' => [2, 2]], $this->engineer)->assertForbidden();
        $this->actingAs($this->manager)->get(route('ky-thuat.kpis.input', ['month' => self::MONTH]))->assertOk()->assertSee('Kỹ sư Test KPI');

        $payload = ['effective_month' => self::MONTH, 'salaries' => [$this->engineer->id => ['amount' => 16_000_000]]];
        $this->actingAs($this->manager)->post(route('ky-thuat.kpis.config.salaries'), $payload)->assertForbidden();

        $this->actingAs($this->userWithRole('hr'))->post(route('ky-thuat.kpis.config.salaries'), $payload)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(16_000_000, (float) DB::table('technical_kpi_salaries')->where('user_id', $this->engineer->id)->value('agreed_salary'), 0.01);
    }

    public function test_config_page_saves_empty_cap_as_unlimited(): void
    {
        $this->config();
        $criteria = collect(self::CODES)->values()->map(fn ($code, $i) => [
            'no' => $i + 1, 'code' => $code, 'name' => $code, 'weight' => [30, 25, 15, 15, 15][$i], 'is_calculated' => 1, 'status' => 'applied',
        ])->all();

        $this->actingAs($this->admin)->post(route('ky-thuat.kpis.config.draft'), [
            'version_name' => 'Không giới hạn', 'effective_date' => '2026-10-01',
            'base_salary_rate' => 70, 'kpi_salary_rate' => 30, 'round_rule' => '1000',
            'allow_exceed_100' => 1, 'max_kpi_rate' => '',
            'criteria' => $criteria,
            'payout_tiers' => ['under_75' => ['rate' => 0], 'from_75_to_90' => ['rate' => 0.8], 'from_90_to_100' => ['rate' => 1], 'above_100' => ['rate' => 1]],
        ])->assertSessionHasNoErrors();

        $draft = TechnicalKpiConfig::query()->where('version_name', 'Không giới hạn')->firstOrFail();
        $this->assertNull($draft->salary_structure['max_kpi_rate']);
        $this->assertTrue($draft->salary_structure['allow_exceed_100']);
    }
}
