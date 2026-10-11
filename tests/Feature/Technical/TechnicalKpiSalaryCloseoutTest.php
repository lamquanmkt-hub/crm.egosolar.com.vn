<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Projects\Site;
use App\Models\TechnicalKpiConfig;
use App\Models\User;
use App\Services\Hr\Kpi\HrKpiEvaluator;
use App\Services\Hr\Payroll\PayrollCalculator;
use App\Services\Hr\Payroll\PayrollSettings;
use App\Services\Projects\AcceptanceKpiService;
use App\Services\Projects\ProjectWorkflowV2Service;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use App\Services\TechnicalKpi\ProjectKpiLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Đợt kpi-salary-closeout (07/10/2026):
 *   F1 tiền KPI = Quỹ KPI × % KPI thực đạt (chế độ có phiên bản, bản cũ giữ bậc), dùng chung kỹ thuật/preview/HR;
 *   F2 (R1) xác nhận không sống lại khi sửa rồi đổi về; F3 (R2) khóa kỳ chặn mọi đường sửa nguồn, kể cả Admin;
 *   F4 công trình mới chạy luồng nhập → gửi → trả/xác nhận → duyệt → KPI → lương.
 * Dữ liệu giả trong transaction (rollback), chỉ chạy trên DB egosolar_test.
 */
final class TechnicalKpiSalaryCloseoutTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private const MONTH = '2026-10';

    private User $adminUser;

    private User $manager;

    private User $engineer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-20 09:00:00'));
        Storage::fake('public');
        $this->adminUser = $this->person('admin');
        $this->manager = $this->person('technical_manager');
        $this->engineer = $this->person('ky_thuat');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function person(string $role): User
    {
        return $this->userWithRole($role, ['name' => 'Lương giả '.Str::upper(Str::random(5)), 'email' => 'luong-'.Str::lower(Str::random(12)).'@example.test']);
    }

    /** Bộ trọng số kỹ thuật từ 10/2026 (25/15/15/10/5/30). */
    private function criteria(): array
    {
        $no = 0;

        return collect(['timeline' => 0.25, 'quality' => 0.15, 'survey' => 0.15, 'hse' => 0.10, 'evn_app' => 0.05, 'warranty' => 0.30])
            ->map(fn ($w, $code) => ['no' => ++$no, 'code' => $code, 'name' => 'Tiêu chí '.$code, 'weight' => $w, 'formula' => '', 'source' => '',
                'threshold' => '', 'is_calculated' => true, 'status' => 'applied'])->values()->all();
    }

    private function config(?string $mode, string $status = 'applied', array $struct = []): TechnicalKpiConfig
    {
        $structure = $struct + ['base_salary_rate' => 0.7, 'kpi_salary_rate' => 0.3, 'allow_exceed_100' => false, 'round_rule' => '1000'];
        if ($mode !== null) {
            $structure['payout_mode'] = $mode;
        }

        return TechnicalKpiConfig::query()->create([
            'version' => 800 + random_int(1, 99), 'version_name' => 'Giả lập '.($mode ?? 'lịch sử'), 'status' => $status,
            'is_current' => $status === 'applied', 'effective_date' => '2026-10-01', 'salary_structure' => $structure,
            'criteria_config' => $this->criteria(),
            'payout_tiers' => ['under_75' => ['rate' => 0.0], 'from_75_to_90' => ['rate' => 0.8], 'from_90_to_100' => ['rate' => 1.0], 'above_100' => ['rate' => 1.0]],
            'approved_at' => $status === 'draft' ? null : now(), 'approved_by' => $status === 'draft' ? null : $this->adminUser->id,
        ]);
    }

    // ================================================================== F1 — tiền KPI theo tỷ lệ

    public function test_proportional_payout_multiplies_kpi_fund_by_actual_rate_with_rounding_trace(): void
    {
        $config = $this->config(TechnicalKpiConfig::PAYOUT_PROPORTIONAL);
        $p = $config->computePayout(15_000_000, 0.875);
        $this->assertSame(10_500_000.0, $p['base_salary']);
        $this->assertSame(4_500_000.0, $p['kpi_base_salary']);
        $this->assertSame(0.875, $p['payout_rate']);
        $this->assertSame(3_937_500.0, $p['real_kpi_salary_raw']);
        $this->assertSame(3_938_000.0, $p['real_kpi_salary'], 'Làm tròn 1.000 đ.');
        $this->assertSame(14_437_500.0, $p['total_income_raw']);
        $this->assertSame(14_438_000.0, $p['total_income']);

        // null (thiếu dữ liệu) khác 0%; dưới 75% vẫn trả theo tỷ lệ; đúng biên 75/90/100.
        $this->assertNull($config->computePayout(15_000_000, null)['real_kpi_salary']);
        foreach ([[0.0, 0.0], [0.68, 3_060_000.0], [0.75, 3_375_000.0], [0.90, 4_050_000.0], [1.0, 4_500_000.0]] as [$pct, $money]) {
            $this->assertSame($money, $config->computePayout(15_000_000, $pct)['real_kpi_salary'], (string) $pct);
            $this->assertEqualsWithDelta($pct, $config->payoutRate($pct)['rate'], 1e-12);
        }
        // Trên 100%: theo cờ/trần đã cấu hình, không tự thêm thưởng.
        $this->assertSame(1.0, $config->payoutRate(1.05)['rate'], 'Không cho vượt 100% thì giữ 100%.');
        $unlimited = $this->config(TechnicalKpiConfig::PAYOUT_PROPORTIONAL, 'draft', ['allow_exceed_100' => true, 'max_kpi_rate' => null]);
        $this->assertSame(1.05, $unlimited->payoutRate(1.05)['rate']);
        $capped = $this->config(TechnicalKpiConfig::PAYOUT_PROPORTIONAL, 'draft', ['allow_exceed_100' => true, 'max_kpi_rate' => 1.02]);
        $this->assertSame(1.02, $capped->payoutRate(1.05)['rate']);
    }

    public function test_config_without_mode_keeps_historical_tier_payout(): void
    {
        $legacy = $this->config(null);
        $this->assertSame(TechnicalKpiConfig::PAYOUT_TIERED, $legacy->payoutMode());
        $this->assertSame(0.8, $legacy->payoutRate(0.875)['rate']);
        $this->assertSame(3_600_000.0, $legacy->computePayout(15_000_000, 0.875)['real_kpi_salary']);
        $this->assertSame(0.0, $legacy->computePayout(15_000_000, 0.70)['real_kpi_salary']);
    }

    public function test_draft_form_saves_mode_without_tier_inputs_and_preview_uses_the_draft(): void
    {
        $base = $this->config(null);
        $payload = ['version_name' => 'Nháp tỷ lệ', 'effective_date' => '2026-10-01', 'base_salary_rate' => 70, 'kpi_salary_rate' => 30,
            'round_rule' => '1000', 'criteria' => $this->criteria()];

        // Form cũ (không gửi chế độ, không gửi bậc) vẫn bị yêu cầu bảng bậc như trước.
        $this->actingAs($this->adminUser)->post(route('ky-thuat.kpis.config.draft'), $payload)->assertSessionHasErrors('payout_tiers');

        $this->actingAs($this->adminUser)->post(route('ky-thuat.kpis.config.draft'), $payload + ['payout_mode' => 'proportional'])
            ->assertSessionHasNoErrors();
        $draft = TechnicalKpiConfig::query()->where('version_name', 'Nháp tỷ lệ')->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->approved_at);
        $this->assertSame(TechnicalKpiConfig::PAYOUT_PROPORTIONAL, $draft->payoutMode());
        $this->assertSame(['base_salary_rate' => 0.7, 'kpi_salary_rate' => 0.3, 'round_rule' => '1000'],
            array_intersect_key($draft->salary_structure, array_flip(['base_salary_rate', 'kpi_salary_rate', 'round_rule'])));
        $this->assertTrue($base->fresh()->is_current, 'Bản đang áp dụng không bị đổi khi lưu nháp.');

        $json = $this->actingAs($this->adminUser)->getJson(route('ky-thuat.kpis.config.preview', $draft->id))->assertOk()->json();
        $this->assertSame('proportional', $json['payout_mode']);
        $row = collect($json['preview_results'])->firstWhere('kpi_percent', 0.875);
        $this->assertSame(0.875, (float) $row['payout_rate']);
        $this->assertSame(3_937_500.0, (float) $row['real_kpi_salary_raw']);
        $this->assertSame(3_938_000.0, (float) $row['real_kpi_salary']);
        $this->assertNull(collect($json['preview_results'])->first(fn ($r) => $r['kpi_percent'] === null)['real_kpi_salary']);

        // Trang cấu hình hiển thị chế độ và khóa bảng bậc ở chế độ tỷ lệ.
        $this->actingAs($this->adminUser)->get(route('ky-thuat.kpis.config', ['version_id' => $draft->id]))->assertOk()
            ->assertSee('data-cfg-payout-mode="proportional"', false)->assertSee('Theo % KPI thực đạt')
            ->assertSee('<fieldset id="cfg-tiers" disabled', false);
    }

    public function test_prepare_draft_command_inherits_active_config_and_only_changes_mode(): void
    {
        $base = $this->config(null, 'applied', ['allow_exceed_100' => true, 'max_kpi_rate' => 1.2, 'round_rule' => '10000']);
        $this->artisan('kpi:payout-draft')->assertSuccessful();
        $this->assertSame(0, TechnicalKpiConfig::query()->where('status', 'draft')->count(), 'Xem trước không ghi.');

        $this->artisan('kpi:payout-draft', ['--apply' => true])->assertSuccessful();
        $draft = TechnicalKpiConfig::query()->where('status', 'draft')->sole();
        $this->assertSame(((int) TechnicalKpiConfig::max('version')), (int) $draft->version);
        $this->assertSame('2026-10-01', $draft->effective_date->toDateString());
        $expected = $base->salary_structure + ['payout_mode' => 'proportional', 'effective_date' => '2026-10-01'];
        ksort($expected);
        $actual = $draft->salary_structure;
        ksort($actual);
        $this->assertSame($expected, $actual);
        $this->assertSame($base->criteria_config, $draft->criteria_config);
        $this->assertTrue($base->fresh()->is_current);
        $this->assertSame('applied', $base->fresh()->status);

        $this->artisan('kpi:payout-draft', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, TechnicalKpiConfig::query()->where('status', 'draft')->count(), 'Chạy lại không tạo trùng.');
    }

    // ================================================================== Công trình giả + luồng mới

    private function site(?User $engineer = null): Site
    {
        $engineer ??= $this->engineer;
        $site = Site::query()->findOrFail($this->makeSite(null, ['lead_engineer_id' => $engineer->id]));
        app(ProjectWorkflowV2Service::class)->ensure($site);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('sequence', '<', 6)->update([
            'status' => 'approved', 'approved_by' => $this->adminUser->id, 'approved_at' => '2026-10-01 08:00:00',
        ]);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'acceptance')->update(['status' => 'in_progress']);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'legal')->update(['data' => json_encode([
            'requires_grid_connection' => true, 'requires_grid_connection_decided_by' => $this->adminUser->id,
            'requires_grid_connection_decided_at' => '2026-10-01 08:00:00',
        ])]);
        DB::table('project_workflow_assignments')->insert([
            'workflow_step_id' => $this->stepId($site), 'user_id' => $engineer->id, 'assignment_role' => 'primary',
            'status' => 'in_progress', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['customer_acceptance', 'completion_photos', 'test_certificates'] as $code) {
            DB::table('project_workflow_documents')->insert([
                'workflow_step_id' => $this->stepId($site), 'site_id' => $site->id, 'document_code' => $code, 'title' => $code,
                'path' => 'kpi-test/'.$code.'.pdf', 'original_name' => $code.'.pdf', 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $site;
    }

    private function stepId(Site $site, string $code = 'acceptance'): int
    {
        return (int) DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', $code)->value('id');
    }

    private function row(Site $site): object
    {
        return DB::table('project_workflow_steps')->where('id', $this->stepId($site))->first();
    }

    private function kpiState(Site $site): array
    {
        return json_decode((string) $this->row($site)->data, true)['kpi_acceptance'] ?? [];
    }

    private function allPass(): array
    {
        return ['complaint' => 'none', 'complaint_note' => '', 'hse' => array_fill_keys(array_keys(ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS), 'pass'),
            'hse_note' => '', 'grid_tested' => 'pass', 'app_handover' => 'pass', 'evn_note' => ''];
    }

    private function declare(User $user, Site $site, array $kpi, string $intent = 'draft')
    {
        return $this->actingAs($user)->post(route('projects-unified.workflow.kpi.save', [$site, 'acceptance']), ['intent' => $intent, 'kpi' => $kpi]);
    }

    private function confirm(User $user, Site $site, array $groups)
    {
        $fp = collect(app(AcceptanceKpiService::class)->summary($this->row($site)))->map(fn ($s) => $s['fingerprint'])->all();

        return $this->actingAs($user)->post(route('projects-unified.workflow.kpi.confirm', [$site, 'acceptance']), ['groups' => $groups, 'fingerprints' => $fp]);
    }

    private function approveAcceptance(Site $site): void
    {
        $this->actingAs($this->adminUser)->post(route('projects-unified.workflow.approve', [$site, 'acceptance']), ['approval_type' => 'admin'])->assertRedirect();
        $this->assertSame('approved', $this->row($site)->status);
    }

    private function summary(Site $site): array
    {
        return app(AcceptanceKpiService::class)->summary($this->row($site));
    }

    private function confirmedSite(?User $engineer = null): Site
    {
        $site = $this->site($engineer);
        $this->declare($engineer ?? $this->engineer, $site, $this->allPass(), 'submit')->assertSessionHasNoErrors();
        $this->confirm($this->manager, $site, ['quality', 'hse', 'evn'])->assertSessionHasNoErrors();
        $this->approveAcceptance($site);

        return $site;
    }

    // ================================================================== F2 — R1

    /** Review gốc R1 (REVIEW-20261006): sửa nháp rồi đổi về không làm xác nhận cũ sống lại. */
    public function test_review_reverting_draft_does_not_revive_a_previous_confirmation(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass(), 'submit')->assertSessionHasNoErrors();
        $this->confirm($this->manager, $site, ['quality', 'hse', 'evn'])->assertSessionHasNoErrors();
        $this->assertNotNull($this->summary($site)['hse']['confirmation']);

        $this->declare($this->engineer, $site, ['hse' => ['kpi_safety_gear' => 'fail']])->assertSessionHasNoErrors();
        $this->assertNull($this->summary($site)['hse']['confirmation']);
        $this->declare($this->engineer, $site, ['hse' => ['kpi_safety_gear' => 'pass']])->assertSessionHasNoErrors();
        $summary = $this->summary($site);
        $this->assertNull($summary['hse']['confirmation'], 'Bản nháp đổi về giá trị cũ phải gửi và kiểm tra lại.');
        $this->assertNotSame('submitted', $summary['hse']['status'], 'Lượt gửi cũ của nhóm đã sửa cũng không sống lại.');
        // Trưởng phòng không xác nhận lại được khi chưa gửi lại.
        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasErrors('acceptance_kpi');
        // Nhóm không sửa giữ xác nhận; lịch sử giữ người/giờ.
        $this->assertSame('confirmed', $summary['quality']['status']);
        $this->assertSame('confirmed', $summary['evn']['status']);
        $history = $this->kpiState($site)['confirmation_history']['hse'];
        $this->assertCount(1, $history);
        $this->assertSame($this->manager->id, $history[0]['by']);
        $this->assertTrue(DB::table('project_workflow_events')->where('workflow_step_id', $this->stepId($site))->where('action', 'acceptance_kpi_confirmation_invalidated')->exists());

        // Gửi lại → xác nhận lại mới có hiệu lực.
        $this->declare($this->engineer, $site, [], 'submit')->assertSessionHasNoErrors();
        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $this->summary($site)['hse']['status']);
    }

    public function test_evidence_grid_decision_or_revision_change_then_restore_does_not_revive_confirmation(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass(), 'submit');
        $this->confirm($this->manager, $site, ['quality', 'hse', 'evn'])->assertSessionHasNoErrors();

        // Minh chứng EVN mới rồi "khôi phục" (gỡ bản ghi như khi xóa ngoài giao diện) → EVN vẫn hết hiệu lực.
        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.documents.upload', [$site, 'acceptance']), [
            'document_code' => 'test_certificates', 'file' => UploadedFile::fake()->create('them.pdf', 10, 'application/pdf'),
        ])->assertRedirect();
        $this->assertNull($this->summary($site)['evn']['confirmation']);
        DB::table('project_workflow_documents')->where('workflow_step_id', $this->stepId($site))->where('original_name', 'them.pdf')->delete();
        $summary = $this->summary($site);
        $this->assertNull($summary['evn']['confirmation'], 'Khôi phục bộ minh chứng cũ không làm xác nhận sống lại.');
        $this->assertSame('confirmed', $summary['quality']['status']);
        $this->assertSame('confirmed', $summary['hse']['status']);

        // Quyết định đấu nối đổi rồi đổi về.
        $site2 = $this->site($this->person('ky_thuat'));
        $engineer2 = User::query()->findOrFail(DB::table('project_workflow_assignments')->where('workflow_step_id', $this->stepId($site2))->value('user_id'));
        $this->declare($engineer2, $site2, $this->allPass(), 'submit');
        $this->confirm($this->manager, $site2, ['evn', 'hse'])->assertSessionHasNoErrors();
        $legal = route('projects-unified.workflow.data.save', [$site2, 'legal']);
        $this->actingAs($this->adminUser)->post($legal, ['requires_grid_connection' => '0'])->assertSessionHasNoErrors();
        $this->actingAs($this->adminUser)->post($legal, ['requires_grid_connection' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($this->summary($site2)['evn']['confirmation']);
        $this->assertSame('confirmed', $this->summary($site2)['hse']['status']);

        // Trả lại Thi công làm xác nhận chất lượng (đạt lần đầu) hết hiệu lực.
        DB::table('project_workflow_steps')->where('id', $this->stepId($site2, 'construction'))->update(['status' => 'submitted']);
        $this->actingAs($this->adminUser)->post(route('projects-unified.workflow.revise', [$site2, 'construction']), [
            'approval_type' => 'admin', 'reason' => 'Thi công lại mép tôn', 'recipient_id' => $engineer2->id,
        ])->assertRedirect();
        $this->assertNull($this->summary($site2)['quality']['confirmation']);
    }

    // ================================================================== F3 — R2

    private function lockTechnicalPayroll(User $engineer): void
    {
        DB::table('technical_kpi_payrolls')->insert([
            'user_id' => $engineer->id, 'employee_name' => $engineer->name, 'payroll_month' => self::MONTH,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function sourceSnapshot(Site $site): array
    {
        return [
            'steps' => DB::table('project_workflow_steps')->where('site_id', $site->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'docs' => DB::table('project_workflow_documents')->where('site_id', $site->id)->count(),
            'events' => DB::table('project_workflow_events')->where('site_id', $site->id)->count(),
            'assignments' => DB::table('project_workflow_assignments as a')->join('project_workflow_steps as w', 'w.id', '=', 'a.workflow_step_id')
                ->where('w.site_id', $site->id)->orderBy('a.id')->get(['a.user_id', 'a.is_active', 'a.status'])->map(fn ($r) => (array) $r)->all(),
            'lead' => (int) DB::table('sites')->where('id', $site->id)->value('lead_engineer_id'),
            'files' => count(Storage::disk('public')->allFiles()),
        ];
    }

    /** Review gốc R2: Admin không sửa khai báo/thêm minh chứng khi kỳ KPI đã duyệt. */
    public function test_review_admin_cannot_edit_declaration_or_add_evidence_after_payroll_period_is_approved(): void
    {
        $site = $this->confirmedSite();
        $this->lockTechnicalPayroll($this->engineer);
        $service = app(AcceptanceKpiService::class);
        $this->assertTrue($service->periodLocked($this->row($site)));
        $before = $this->sourceSnapshot($site);

        $this->declare($this->adminUser, $site, ['hse' => ['kpi_safety_gear' => 'fail']])->assertSessionHasErrors('acceptance_kpi');
        $this->actingAs($this->adminUser)->post(route('projects-unified.workflow.documents.upload', [$site, 'acceptance']), [
            'document_code' => 'test_certificates', 'file' => UploadedFile::fake()->create('locked.pdf', 20, 'application/pdf'),
        ])->assertStatus(422);
        $this->assertSame($before, $this->sourceSnapshot($site), 'Nguồn kỳ đã chốt giữ nguyên DB, sự kiện và file.');
        $this->assertSame('confirmed', $this->summary($site)['evn']['status']);
        // Giao diện: Admin thấy lý do khóa kỳ, không còn form nhập.
        $this->actingAs($this->adminUser)->get(route('projects-unified.show', ['site' => $site->id, 'step' => 'acceptance']))->assertOk()
            ->assertSee('data-pak-period-lock', false)->assertDontSee('id="pak-declare-form"', false);
    }

    public function test_period_lock_blocks_every_source_mutation_for_admin_manager_and_engineer(): void
    {
        $site = $this->confirmedSite();
        $this->lockTechnicalPayroll($this->engineer);
        $before = $this->sourceSnapshot($site);
        $other = $this->person('ky_thuat');

        $requests = [
            [$this->adminUser, 'kpi.save', 'acceptance', ['intent' => 'submit', 'kpi' => ['hse_note' => 'Sửa sau chốt']]],
            [$this->manager, 'kpi.confirm', 'acceptance', ['groups' => ['hse'], 'fingerprints' => ['hse' => str_repeat('a', 40)]]],
            [$this->adminUser, 'kpi.return', 'acceptance', ['reason' => 'Trả sau khi chốt kỳ']],
            [$this->adminUser, 'data.save', 'legal', ['requires_grid_connection' => '0']],
            [$this->adminUser, 'data.save', 'acceptance', ['kpi_material_deviation_percent' => '9', 'step_note' => 'Sửa sau chốt']],
            [$this->adminUser, 'revise', 'acceptance', ['approval_type' => 'admin', 'reason' => 'Mở lại', 'recipient_id' => $this->engineer->id]],
            [$this->adminUser, 'approve', 'acceptance', ['approval_type' => 'admin']],
            [$this->adminUser, 'assign', 'acceptance', ['primary_assignee_id' => $other->id, 'due_at' => '2026-10-25 17:00:00', 'requirement' => 'Đổi người', 'approved_action' => 'reopen']],
            [$this->adminUser, 'exception', 'acceptance', ['to_step' => 'warranty', 'reason' => 'Ngoại lệ sau chốt']],
            [$this->engineer, 'documents.upload', 'acceptance', ['document_code' => 'completion_photos', 'file' => UploadedFile::fake()->create('a.jpg', 10, 'image/jpeg')]],
            [$this->adminUser, 'documents.upload', 'survey', ['document_code' => 'survey_report', 'file' => UploadedFile::fake()->create('s.pdf', 10, 'application/pdf')]],
        ];
        foreach ($requests as [$user, $route, $step, $payload]) {
            $response = $this->actingAs($user)->post(route('projects-unified.workflow.'.$route, [$site, $step]), $payload);
            $this->assertTrue(in_array($response->getStatusCode(), [403, 422], true) || session()->has('errors'), $route.' '.$step.' phải bị chặn');
        }
        $this->actingAs($this->adminUser)->post(route('projects-unified.engineer.update', $site), ['lead_engineer_id' => $other->id])->assertStatus(422);

        $this->assertSame($before, $this->sourceSnapshot($site), 'Không request nào làm đổi DB/file/sự kiện.');
    }

    public function test_hr_approved_period_using_technical_kpi_locks_but_excel_snapshot_or_other_users_do_not(): void
    {
        $site = $this->confirmedSite();
        $service = app(AcceptanceKpiService::class);
        $period = fn (array $snapshot) => DB::table('hr_payroll_periods')->insertGetId([
            'payroll_month' => self::MONTH, 'status' => 'approved', 'approved_at' => now(), 'settings_snapshot' => json_encode($snapshot),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $line = fn (int $periodId, User $user, ?string $template) => DB::table('hr_payroll_lines')->insert([
            'period_id' => $periodId, 'user_id' => $user->id, 'employee_name' => $user->name, 'kpi_template' => $template,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Mỗi tháng một kỳ lương (unique): cùng một kỳ, đổi nguồn/trạng thái theo từng bước.
        $periodId = $period(['import' => ['mode' => 'excel_snapshot']]);
        $line($periodId, $this->engineer, 'technical');
        $this->assertNull($service->kpiSourceLock($site->id), 'Kỳ Excel không đọc nguồn kỹ thuật.');
        DB::table('hr_payroll_periods')->where('id', $periodId)->update(['settings_snapshot' => json_encode([])]);
        DB::table('hr_payroll_lines')->where('period_id', $periodId)->update(['kpi_template' => null]);
        $line($periodId, $this->person('ky_thuat'), 'technical');
        $this->assertNull($service->kpiSourceLock($site->id), 'Dòng của người khác/không dùng KPI kỹ thuật không khóa.');
        DB::table('hr_payroll_lines')->where('period_id', $periodId)->where('user_id', $this->engineer->id)->update(['kpi_template' => 'technical']);
        DB::table('hr_payroll_periods')->where('id', $periodId)->update(['status' => 'draft']);
        $this->assertNull($service->kpiSourceLock($site->id), 'Kỳ HR chưa duyệt không khóa.');
        DB::table('hr_payroll_periods')->where('id', $periodId)->update(['status' => 'approved']);
        $lock = $service->kpiSourceLock($site->id);
        $this->assertSame(self::MONTH, $lock['month']);
        $this->assertStringContainsString('bảng lương nhân sự', $lock['reasons'][0]);
    }

    public function test_lock_is_scoped_to_people_and_month_and_admin_keeps_step_exception_when_unlocked(): void
    {
        $locked = $this->confirmedSite();
        $this->lockTechnicalPayroll($this->engineer);
        // Công trình của người khác cùng tháng: không khóa; Admin vẫn sửa được bước đã duyệt (ngoại lệ hiện có).
        $free = $this->confirmedSite($this->person('ky_thuat'));
        $this->assertNull(app(AcceptanceKpiService::class)->kpiSourceLock($free->id));
        $this->declare($this->adminUser, $free, ['hse_note' => 'Admin bổ sung khi kỳ chưa chốt'])->assertSessionHasNoErrors();
        $this->assertSame('Admin bổ sung khi kỳ chưa chốt', $this->kpiState($free)['values']['hse_note']);
        // Phiếu lương tháng khác của cùng người không khóa công trình tháng 10.
        DB::table('technical_kpi_payrolls')->where('user_id', $this->engineer->id)->update(['payroll_month' => '2026-11']);
        $this->assertNull(app(AcceptanceKpiService::class)->kpiSourceLock($locked->id));
    }

    // ================================================================== F4 — lệnh tạo công trình TEST mới

    public function test_demo_site_command_is_guarded_idempotent_and_ready_for_the_new_flow(): void
    {
        $manifest = sys_get_temp_dir().'/kpi-salary-site-'.uniqid().'.json';
        $this->artisan('kpi:demo-salary-site', ['--apply' => true, '--manifest' => $manifest])->assertFailed(); // chưa có tài khoản mẫu
        $engineer = $this->technician(['email' => \App\Console\Commands\SeedKpiChecklistDemo::ENGINEER_EMAIL]);
        $this->technicalManager(['email' => \App\Console\Commands\SeedKpiChecklistDemo::MANAGER_EMAIL]);
        $this->artisan('kpi:demo-salary-site')->assertSuccessful();
        $this->assertFalse(DB::table('sites')->where('project_code', \App\Console\Commands\SeedKpiSalaryDemoSite::CODE)->exists(), 'Xem trước không ghi.');

        $this->artisan('kpi:demo-salary-site', ['--apply' => true, '--manifest' => $manifest])->assertSuccessful();
        $site = Site::query()->where('project_code', \App\Console\Commands\SeedKpiSalaryDemoSite::CODE)->firstOrFail();
        $before = DB::table('project_workflow_steps')->where('site_id', $site->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $this->assertSame('in_progress', $this->row($site)->status);
        $this->assertSame(15, DB::table('project_workflow_documents')->where('site_id', $site->id)->count());
        $this->assertCount(15, Storage::disk('public')->allFiles('project-workflow/'.$site->id), 'File giả ghi qua disk public (fake trong test).');
        $this->assertDirectoryDoesNotExist(storage_path('app/public/project-workflow/'.$site->id), 'Test không ghi ra storage dùng chung.');
        $this->assertArrayNotHasKey('kpi_acceptance', json_decode((string) $this->row($site)->data, true), 'Không tự nhập kết quả KPI.');
        $this->assertTrue(app(AcceptanceKpiService::class)->gridDecision($site->id)['required']);

        $this->artisan('kpi:demo-salary-site', ['--apply' => true, '--manifest' => $manifest])->assertSuccessful();
        $this->assertSame(1, DB::table('sites')->where('project_code', \App\Console\Commands\SeedKpiSalaryDemoSite::CODE)->count());
        $this->assertSame($before, DB::table('project_workflow_steps')->where('site_id', $site->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all());

        // Kỹ thuật mẫu nhập và gửi được ngay theo luồng mới.
        $this->declare($engineer, $site, $this->allPass(), 'submit')->assertSessionHasNoErrors();
        $this->assertSame('submitted', $this->kpiState($site)['status']);
        @unlink($manifest);
    }

    // ================================================================== F4 + F5 — công trình mới đến lương

    /** 4 việc đến hạn (2 đúng hạn) + 5 tiêu chí khác nhập tháng đạt → 12,5 + 75 = 87,5%. */
    private function kpiAt875(User $engineer, Site $site): void
    {
        $plan = $this->makeWeekPlan($engineer, Carbon::parse('2026-10-12'));
        foreach (['2026-10-12 08:00:00' => '2026-10-12 17:00:00', '2026-10-13 08:00:00' => '2026-10-14 08:00:00',
            '2026-10-14 08:00:00' => '2026-10-15 10:00:00', '2026-10-15 08:00:00' => null] as $assigned => $sent) {
            $item = $this->makePlanItem($engineer, $plan, ['title' => 'Việc giả '.$assigned, 'is_manager_assigned' => 1, 'assigned_at' => $assigned,
                'assigned_by' => $this->manager->id, 'plan_date' => substr($assigned, 0, 10), 'site_id' => $site->id]);
            if ($sent) {
                $this->makeDailyReport($engineer, ['plan_item_id' => $item, 'status' => 'submitted', 'submitted_at' => $sent]);
            }
        }
        // Khảo sát/bảo hành chưa có nguồn công trình trong fixture: nhập tháng giả để cô lập phần tiền (không bù tiêu chí đã có nguồn).
        foreach (['survey', 'warranty'] as $code) {
            DB::table('technical_kpi_monthly_scores')->insert(['user_id' => $engineer->id, 'payroll_month' => self::MONTH, 'criterion_code' => $code,
                'plan_value' => 1, 'actual_value' => 1, 'updated_by' => $this->manager->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_new_site_flow_feeds_kpi_and_same_payout_rate_reaches_technical_page_and_hr(): void
    {
        $applied = $this->config(TechnicalKpiConfig::PAYOUT_PROPORTIONAL);
        DB::table('technical_kpi_salaries')->insert(['user_id' => $this->engineer->id, 'agreed_salary' => 15_000_000, 'effective_month' => self::MONTH, 'created_at' => now(), 'updated_at' => now()]);

        // Công trình mới: nháp → gửi → trả bổ sung → gửi lại → xác nhận → Admin duyệt. Không có khóa legacy nào.
        $site = $this->site();
        $this->declare($this->engineer, $site, array_replace_recursive($this->allPass(), ['hse' => ['kpi_roof_intact' => '']]))->assertSessionHasNoErrors();
        $this->declare($this->engineer, $site, ['hse' => ['kpi_roof_intact' => 'pass']], 'submit')->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('projects-unified.workflow.kpi.return', [$site, 'acceptance']), ['reason' => 'Bổ sung ghi chú HSE', 'groups' => ['hse']])
            ->assertSessionHasNoErrors();
        $this->declare($this->engineer, $site, ['hse_note' => 'Đã bổ sung'], 'submit')->assertSessionHasNoErrors();
        $this->confirm($this->manager, $site, ['quality', 'hse', 'evn'])->assertSessionHasNoErrors();
        $this->approveAcceptance($site);
        $data = json_decode((string) $this->row($site)->data, true);
        foreach (['kpi_reviewed_by', 'kpi_customer_complaint', 'kpi_no_incident', 'kpi_app_installed'] as $legacyKey) {
            $this->assertArrayNotHasKey($legacyKey, $data, 'Luồng mới không ghi khóa legacy '.$legacyKey);
        }
        $this->kpiAt875($this->engineer, $site);

        $evaluator = app(KpiStandardEvaluator::class)->useConfig($applied, self::MONTH);
        $d = $evaluator->collect($this->engineer->id, [self::MONTH]);
        $result = $evaluator->score($d['projects'], $d['evidence'], $d['warranty'], $d['manual'], $d['adjustments'], $d['assigned_work']);
        $criteria = collect($result['criteria'])->keyBy('code');
        $this->assertSame('acceptance_kpi', app(ProjectKpiLinkService::class)->acceptanceKpiResult($this->row($site))['format']);
        foreach (['quality' => 0.15, 'hse' => 0.10, 'evn_app' => 0.05] as $code => $score) {
            $this->assertEqualsWithDelta($score, $criteria[$code]['score'], 1e-9, $code);
            $this->assertStringContainsString('xác nhận: '.$this->manager->name, $criteria[$code]['evidence'][0]['result'], $code);
        }
        $this->assertEqualsWithDelta(0.125, $criteria['timeline']['score'], 1e-9);
        $this->assertEqualsWithDelta(0.875, $result['total'], 1e-9);

        // Trang KPI kỹ thuật: Quỹ 4.500.000 × 0,875 → 3.937.500 trước làm tròn, 3.938.000 sau làm tròn 1.000.
        $row = collect($this->actingAs($this->adminUser)->get(route('ky-thuat.kpis.index', ['period' => 'month', 'month' => self::MONTH]))
            ->assertOk()->viewData('engineers'))->firstWhere('id', $this->engineer->id);
        $this->assertEqualsWithDelta(0.875, $row['kpi_percent'], 1e-9);
        $this->assertSame(0.875, round((float) $row['payout_rate'], 6));
        $this->assertSame(3_937_500.0, round((float) $row['real_kpi_salary_raw'], 2));
        $this->assertSame(3_938_000.0, (float) $row['real_kpi_salary']);
        $this->assertSame(14_438_000.0, (float) $row['total_income']);

        // HR: cùng điểm quy đổi → hệ số 0,875 (không nhân % lần 2). Đủ công: lương KPI = 4.500.000 × 1 × 0,875.
        $hr = app(HrKpiEvaluator::class)->forEmployee($this->engineer->id, self::MONTH, HrKpiEvaluator::TECHNICAL);
        $this->assertSame(87.5, $hr['percent']);
        $this->assertSame(0.875, $hr['rate']);
        $calc = app(PayrollCalculator::class);
        $input = ['standard_days' => 26, 'paid_days' => 26, 'unpaid_days' => 0, 'base_salary' => 10_500_000, 'kpi_salary' => 4_500_000,
            'decision_bonus' => 0, 'travel_allowance' => 0, 'phone_allowance' => 0, 'meal_allowance' => 0, 'insurance_salary' => 0,
            'insurance_enabled' => false, 'dependents' => 0, 'pit_mode' => 'none', 'kpi_rate' => $hr['rate'], 'other_income' => 0,
            'trip_allowance' => 0, 'ot_amount' => 0, 'advance' => 0, 'late_penalty' => 0, 'other_deduction' => 0];
        $full = $calc->calculate($input, PayrollSettings::all());
        $this->assertSame(3_937_500.0, $full['kpi_pay']);
        $this->assertSame(14_437_500.0, $full['gross_income'], 'HR làm tròn tới đồng; trang kỹ thuật làm tròn 1.000 đ theo cấu hình.');
        // Ngày công khác: HR nhân thêm tỷ lệ công (quy tắc hiện có), không phải nhân KPI lần 2.
        $half = $calc->calculate(['paid_days' => 13] + $input, PayrollSettings::all());
        $this->assertSame(1_968_750.0, $half['kpi_pay']);
    }
}
