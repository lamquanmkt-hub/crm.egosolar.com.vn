<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Projects\Site;
use App\Models\User;
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
 * Luồng Nghiệm thu → KPI kỹ thuật (đợt technical-acceptance A1–A5):
 * kỹ thuật được giao nhập/gửi → Trưởng phòng KT xác nhận hoặc trả bổ sung → KS02/KS04/KS05 nhận đúng nguồn.
 *
 * Dữ liệu hoàn toàn giả, trong transaction (rollback sau mỗi test), chỉ chạy trên DB egosolar_test.
 * Kỳ 10/2026 dùng bộ trọng số 25/15/15/10/5/30.
 */
final class TechnicalAcceptanceKpiFlowTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-10-06 09:00:00'));
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
        return $this->userWithRole($role, [
            'name' => 'Nghiệm thu giả '.Str::upper(Str::random(5)),
            'email' => 'nt-'.Str::lower(Str::random(12)).'@example.test',
        ]);
    }

    /**
     * Công trình đã qua các bước trước, bước Nghiệm thu đang thực hiện và giao cho $engineer.
     *
     * @param  bool|null  $grid  quyết định cần đấu nối ở Hồ sơ pháp lý (null = chưa xác định)
     */
    private function site(?User $engineer = null, ?bool $grid = true, bool $evidence = true, array $siteAttributes = []): Site
    {
        $engineer ??= $this->engineer;
        $site = Site::query()->findOrFail($this->makeSite(null, $siteAttributes));
        app(ProjectWorkflowV2Service::class)->ensure($site);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('sequence', '<', 6)->update([
            'status' => 'approved', 'approved_by' => $this->adminUser->id, 'approved_at' => '2026-10-01 08:00:00',
        ]);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'acceptance')->update(['status' => 'in_progress']);
        if ($grid !== null) {
            DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'legal')->update(['data' => json_encode([
                'requires_grid_connection' => $grid, 'requires_grid_connection_decided_by' => $this->adminUser->id,
                'requires_grid_connection_decided_at' => '2026-10-01 08:00:00',
            ])]);
        }
        $stepId = $this->stepId($site);
        DB::table('project_workflow_assignments')->insert([
            'workflow_step_id' => $stepId, 'user_id' => $engineer->id, 'assignment_role' => 'primary',
            'status' => 'in_progress', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($evidence) {
            foreach (['customer_acceptance', 'completion_photos', 'test_certificates'] as $code) {
                DB::table('project_workflow_documents')->insert([
                    'workflow_step_id' => $stepId, 'site_id' => $site->id, 'document_code' => $code, 'title' => $code,
                    'path' => 'kpi-test/'.$code.'.pdf', 'original_name' => $code.'.pdf', 'version' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        return $site;
    }

    private function stepId(Site $site, string $code = 'acceptance'): int
    {
        return (int) DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', $code)->value('id');
    }

    private function stepData(Site $site, string $code = 'acceptance'): array
    {
        return json_decode((string) DB::table('project_workflow_steps')->where('id', $this->stepId($site, $code))->value('data'), true) ?: [];
    }

    private function row(Site $site): object
    {
        return DB::table('project_workflow_steps')->where('id', $this->stepId($site))->first();
    }

    private function allPass(): array
    {
        return [
            'complaint' => 'none', 'complaint_note' => '',
            'hse' => array_fill_keys(array_keys(ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS), 'pass'), 'hse_note' => '',
            'grid_tested' => 'pass', 'app_handover' => 'pass', 'evn_note' => '',
        ];
    }

    private function declare(User $user, Site $site, array $kpi, string $intent = 'draft')
    {
        return $this->actingAs($user)->post(route('projects-unified.workflow.kpi.save', [$site, 'acceptance']), ['intent' => $intent, 'kpi' => $kpi]);
    }

    private function fingerprints(Site $site): array
    {
        return collect(app(AcceptanceKpiService::class)->summary($this->row($site)))->map(fn ($s) => $s['fingerprint'])->all();
    }

    private function confirm(User $user, Site $site, array $groups, ?array $fingerprints = null)
    {
        return $this->actingAs($user)->post(route('projects-unified.workflow.kpi.confirm', [$site, 'acceptance']), [
            'groups' => $groups, 'fingerprints' => $fingerprints ?? $this->fingerprints($site),
        ]);
    }

    private function approveAcceptance(Site $site): void
    {
        $this->actingAs($this->adminUser)->post(route('projects-unified.workflow.approve', [$site, 'acceptance']), ['approval_type' => 'admin'])
            ->assertRedirect();
        $this->assertSame('approved', $this->row($site)->status);
    }

    private function criterion(User $engineer, string $code, string $month = self::MONTH): array
    {
        $evaluator = app(KpiStandardEvaluator::class)->useConfig(null, $month);
        $data = $evaluator->collect($engineer->id, [$month]);
        $result = $evaluator->score($data['projects'], $data['evidence'], $data['warranty'], $data['manual'], $data['adjustments']);

        return collect($result['criteria'])->firstWhere('code', $code);
    }

    /** Một công trình đã xác nhận đủ 3 nhóm và được Admin duyệt nghiệm thu. */
    private function confirmedSite(array $kpi = [], ?bool $grid = true, ?User $engineer = null): Site
    {
        $engineer ??= $this->engineer;
        $site = $this->site($engineer, $grid);
        $this->declare($engineer, $site, array_replace_recursive($this->allPass(), $kpi), 'submit')->assertSessionHasNoErrors();
        $this->confirm($this->manager, $site, array_keys(AcceptanceKpiService::GROUPS))->assertSessionHasNoErrors();
        $this->approveAcceptance($site);

        return $site;
    }

    // ------------------------------------------------------------------ 1. nháp / gửi chưa kiểm tra

    public function test_draft_and_submitted_results_do_not_score_until_manager_confirms(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass())->assertRedirect()->assertSessionHasNoErrors();

        $data = $this->stepData($site);
        $this->assertSame('draft', $data['kpi_acceptance']['status']);
        $this->assertSame([], $data['kpi_acceptance']['confirmations']);
        $this->assertArrayNotHasKey('kpi_reviewed_at', $data, 'Lưu nháp không được đóng dấu đã kiểm tra.');

        $this->approveAcceptance($site);
        foreach (['quality', 'hse', 'evn_app'] as $code) {
            $c = $this->criterion($this->engineer, $code);
            $this->assertSame('no_data', $c['status'], $code);
            $this->assertNull($c['score'], $code);
            $this->assertSame('missing', $c['evidence'][0]['state'], $code);
            $this->assertStringContainsString('nháp', $c['evidence'][0]['result'], $code);
        }

        // Admin (ngoại lệ) gửi thay → vẫn chưa tính cho tới khi Trưởng phòng xác nhận.
        $this->declare($this->adminUser, $site, [], 'submit')->assertSessionHasNoErrors();
        $this->assertStringContainsString('chờ Trưởng phòng', $this->criterion($this->engineer, 'hse')['evidence'][0]['result']);

        $this->confirm($this->manager, $site, ['quality', 'hse', 'evn'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0.15, $this->criterion($this->engineer, 'quality')['score'], 1e-9);
        $this->assertEqualsWithDelta(0.10, $this->criterion($this->engineer, 'hse')['score'], 1e-9);
        $this->assertEqualsWithDelta(0.05, $this->criterion($this->engineer, 'evn_app')['score'], 1e-9);
    }

    public function test_incomplete_declaration_is_missing_not_failed(): void
    {
        $site = $this->site();
        $kpi = $this->allPass();
        $kpi['hse']['kpi_roof_intact'] = '';
        $this->declare($this->engineer, $site, $kpi, 'submit')->assertSessionHasNoErrors();
        $this->assertNull($this->stepData($site)['kpi_acceptance']['values']['hse']['kpi_roof_intact']);

        // Nhóm HSE thiếu một mục: không xác nhận được (cả khi gửi POST trực tiếp).
        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasErrors('acceptance_kpi');
        $this->assertArrayNotHasKey('hse', $this->stepData($site)['kpi_acceptance']['confirmations']);
        $this->confirm($this->manager, $site, ['quality', 'evn'])->assertSessionHasNoErrors();
        $this->approveAcceptance($site);

        $hse = $this->criterion($this->engineer, 'hse');
        $this->assertSame('no_data', $hse['status']);
        $this->assertSame(0, $hse['passed']);
        $this->assertSame('missing', $hse['evidence'][0]['state']);
    }

    // ------------------------------------------------------------------ 2. quyền

    public function test_only_assignee_declares_and_only_reviewer_outside_the_work_confirms(): void
    {
        $site = $this->site();
        $otherEngineer = $this->person('ky_thuat');

        $this->declare($otherEngineer, $site, $this->allPass())->assertForbidden();
        $this->declare($this->manager, $site, $this->allPass())->assertForbidden();
        $this->assertArrayNotHasKey('kpi_acceptance', $this->stepData($site));

        // Client gửi kèm người/giờ xác nhận → bị bỏ qua.
        $forged = $this->allPass() + ['confirmations' => ['hse' => ['by' => $this->manager->id]], 'kpi_reviewed_by' => $this->manager->id];
        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.kpi.save', [$site, 'acceptance']), [
            'intent' => 'submit', 'kpi' => $forged, 'confirmations' => ['hse' => 'x'], 'kpi_reviewed_by' => $this->manager->id,
        ])->assertSessionHasNoErrors();
        $data = $this->stepData($site);
        $this->assertSame([], $data['kpi_acceptance']['confirmations']);
        $this->assertArrayNotHasKey('kpi_reviewed_by', $data);
        $this->assertArrayNotHasKey('confirmations', $data['kpi_acceptance']['values']);

        // Kỹ thuật (người thực hiện) không tự xác nhận; kỹ thuật khác cũng không.
        $this->confirm($this->engineer, $site, ['hse'])->assertForbidden();
        $this->confirm($otherEngineer, $site, ['hse'])->assertForbidden();
        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.kpi.return', [$site, 'acceptance']), ['reason' => 'Tự trả lại'])->assertForbidden();

        // Trưởng phòng hợp lệ: người/giờ do server ghi.
        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasNoErrors();
        $confirmation = $this->stepData($site)['kpi_acceptance']['confirmations']['hse'];
        $this->assertSame($this->manager->id, $confirmation['by']);
        $this->assertSame('2026-10-06 09:00:00', $confirmation['at']);
        $this->assertSame('pass', $confirmation['result']);
        $this->assertFalse($confirmation['admin_exception']);
        $this->assertTrue(DB::table('project_workflow_events')->where('workflow_step_id', $this->stepId($site))
            ->where('action', 'acceptance_kpi_confirmed')->where('actor_id', $this->manager->id)->exists());
    }

    public function test_manager_who_performed_the_step_cannot_confirm_but_admin_exception_is_traced(): void
    {
        $managerDoer = $this->person('technical_manager');
        $site = $this->site($managerDoer);
        $this->declare($managerDoer, $site, $this->allPass(), 'submit')->assertSessionHasNoErrors();
        $this->confirm($managerDoer, $site, ['hse'])->assertForbidden();

        $adminDoer = $this->person('admin');
        $site2 = $this->site($adminDoer);
        $this->declare($adminDoer, $site2, $this->allPass(), 'submit')->assertSessionHasNoErrors();
        $this->confirm($adminDoer, $site2, ['hse'])->assertSessionHasNoErrors();
        $this->assertTrue($this->stepData($site2)['kpi_acceptance']['confirmations']['hse']['admin_exception']);
        $payload = json_decode((string) DB::table('project_workflow_events')->where('workflow_step_id', $this->stepId($site2))
            ->where('action', 'acceptance_kpi_confirmed')->value('payload'), true);
        $this->assertTrue($payload['admin_exception']);
    }

    public function test_site_outside_company_is_not_reachable(): void
    {
        $siteId = $this->makeSite($this->companyId() === 1 ? 2 : 1);
        $this->actingAs($this->manager)->post('/du-an/'.$siteId.'/quy-trinh/acceptance/ket-qua-nghiem-thu/xac-nhan', [
            'groups' => ['hse'], 'fingerprints' => ['hse' => str_repeat('a', 40)],
        ])->assertNotFound();
        $this->actingAs($this->engineer)->post('/du-an/'.$siteId.'/quy-trinh/acceptance/ket-qua-nghiem-thu', ['intent' => 'submit', 'kpi' => $this->allPass()])
            ->assertNotFound();
        $this->assertSame(0, DB::table('project_workflow_steps')->where('site_id', $siteId)->count());
    }

    public function test_kpi_routes_only_exist_for_acceptance_step(): void
    {
        $site = $this->site();
        $this->actingAs($this->adminUser)->post(route('projects-unified.workflow.kpi.save', [$site, 'construction']), ['intent' => 'draft'])->assertNotFound();
    }

    // ------------------------------------------------------------------ 3. lưu từng nhóm / null / cờ ngoài bước

    public function test_saving_one_group_keeps_other_groups_legal_flag_and_can_clear_to_null(): void
    {
        $site = $this->site(grid: true);
        $this->declare($this->engineer, $site, ['complaint' => 'none'])->assertSessionHasNoErrors();
        $this->declare($this->engineer, $site, ['hse' => ['kpi_no_incident' => 'pass']])->assertSessionHasNoErrors();
        $values = $this->stepData($site)['kpi_acceptance']['values'];
        $this->assertSame('none', $values['complaint']);
        $this->assertSame('pass', $values['hse']['kpi_no_incident']);
        $this->assertNull($values['hse']['kpi_safety_gear']);

        // Bỏ lựa chọn → lưu null (không bị bộ lọc giữ giá trị cũ).
        $this->declare($this->engineer, $site, ['complaint' => ''])->assertSessionHasNoErrors();
        $values = $this->stepData($site)['kpi_acceptance']['values'];
        $this->assertArrayHasKey('complaint', $values);
        $this->assertNull($values['complaint']);
        $this->assertSame('pass', $values['hse']['kpi_no_incident']);

        // Lưu dữ liệu các bước khác không còn ép cờ của Hồ sơ pháp lý / bước khác thành false.
        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.data.save', [$site, 'acceptance']), ['step_note' => 'Đã bàn giao'])
            ->assertSessionHasNoErrors();
        DB::table('project_workflow_steps')->where('id', $this->stepId($site, 'contract'))->update(['data' => json_encode(['technical_feasibility_confirmed' => true])]);
        $this->actingAs($this->adminUser)->post(route('projects-unified.workflow.data.save', [$site, 'contract']), ['step_note' => 'Ghi chú HĐ'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->stepData($site, 'legal')['requires_grid_connection']);
        $this->assertTrue($this->stepData($site, 'contract')['technical_feasibility_confirmed']);
        $acceptance = $this->stepData($site);
        foreach (['requires_grid_connection', 'requires_insurance', 'has_incident', 'technical_feasibility_confirmed', 'uses_replacement_material'] as $flag) {
            $this->assertArrayNotHasKey($flag, $acceptance, $flag);
        }

        // Quyết định cần đấu nối: ba trạng thái, người/giờ do server ghi; '' = chưa xác định (null).
        $legalUrl = route('projects-unified.workflow.data.save', [$site, 'legal']);
        $this->actingAs($this->adminUser)->post($legalUrl, ['requires_grid_connection' => '0', 'requires_grid_connection_decided_by' => 999])
            ->assertRedirect(route('projects-unified.show', ['site' => $site->id, 'step' => 'contract']).'#workflow');
        $legal = $this->stepData($site, 'legal');
        $this->assertFalse($legal['requires_grid_connection']);
        $this->assertSame($this->adminUser->id, $legal['requires_grid_connection_decided_by']);
        $this->actingAs($this->adminUser)->post($legalUrl, ['requires_grid_connection' => ''])->assertSessionHasNoErrors();
        $legal = $this->stepData($site, 'legal');
        $this->assertArrayHasKey('requires_grid_connection', $legal);
        $this->assertNull($legal['requires_grid_connection']);
        $this->assertNull($legal['requires_grid_connection_decided_at']);
        $this->assertNull(app(AcceptanceKpiService::class)->gridDecision($site->id)['required']);
    }

    public function test_old_acceptance_form_fields_no_longer_stamp_kpi_review(): void
    {
        $site = $this->site();
        $this->actingAs($this->manager)->post(route('projects-unified.workflow.data.save', [$site, 'acceptance']), [
            'kpi_review_submitted' => 1, 'kpi_customer_complaint' => 0, 'kpi_app_installed' => 1, 'kpi_no_incident' => 1,
        ])->assertSessionHasNoErrors();
        $data = $this->stepData($site);
        foreach (['kpi_reviewed_by', 'kpi_reviewed_at', 'kpi_customer_complaint', 'kpi_app_installed', 'kpi_no_incident', 'kpi_acceptance'] as $key) {
            $this->assertArrayNotHasKey($key, $data, $key);
        }

        // % vật tư (nguồn hiện tại): chỉ Trưởng phòng/Admin; để trống = quay về số tự tính (null).
        $url = route('projects-unified.workflow.data.save', [$site, 'acceptance']);
        $this->actingAs($this->engineer)->post($url, ['kpi_material_deviation_percent' => '1.5'])->assertForbidden();
        $this->actingAs($this->manager)->post($url, ['kpi_material_deviation_percent' => '2.5'])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(2.5, $this->stepData($site)['kpi_material_deviation_percent'], 1e-9);
        $this->assertSame($this->manager->id, $this->stepData($site)['kpi_material_updated_by']);
        $this->actingAs($this->manager)->post($url, ['kpi_material_deviation_percent' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->stepData($site)['kpi_material_deviation_percent']);
    }

    // ------------------------------------------------------------------ 4. KS02 chất lượng

    public function test_quality_uses_checked_complaint_and_real_revision_history(): void
    {
        // Chưa ghi nhận phản ánh → thiếu (không suy "không phàn nàn").
        $e1 = $this->person('ky_thuat');
        $site = $this->site($e1);
        $kpi = $this->allPass();
        $kpi['complaint'] = '';
        $this->declare($e1, $site, $kpi, 'submit');
        $this->confirm($this->manager, $site, ['quality'])->assertSessionHasErrors('acceptance_kpi');
        $this->approveAcceptance($site);
        $this->assertSame('no_data', $this->criterion($e1, 'quality')['status']);

        // Có phản ánh nhưng thiếu nội dung → không xác nhận được; có nội dung → không đạt.
        $e2 = $this->person('ky_thuat');
        $site = $this->site($e2);
        $this->declare($e2, $site, ['complaint' => 'has', 'complaint_note' => ''] + $this->allPass(), 'submit');
        $this->confirm($this->manager, $site, ['quality'])->assertSessionHasErrors('acceptance_kpi');
        $this->declare($e2, $site, ['complaint' => 'has', 'complaint_note' => 'Khách phản ánh mái còn vết bẩn'], 'submit');
        $this->confirm($this->manager, $site, ['quality'])->assertSessionHasNoErrors();
        $this->approveAcceptance($site);
        $quality = $this->criterion($e2, 'quality');
        $this->assertSame(0, $quality['passed']);
        $this->assertSame(1, $quality['total']);
        $this->assertEqualsWithDelta(0.0, $quality['score'], 1e-9);
        $this->assertStringContainsString('vết bẩn', $quality['evidence'][0]['detail']);

        // Bị trả lại ở Thi công (lịch sử thật) → không đạt dù không có phản ánh.
        $e3 = $this->person('ky_thuat');
        $site = $this->site($e3);
        DB::table('project_workflow_events')->insert([
            'site_id' => $site->id, 'workflow_step_id' => $this->stepId($site, 'construction'), 'actor_id' => $this->adminUser->id,
            'action' => 'step_revision_requested', 'from_status' => 'submitted', 'to_status' => 'revision', 'note' => 'Làm lại', 'created_at' => now(),
        ]);
        $this->declare($e3, $site, $this->allPass(), 'submit');
        $this->confirm($this->manager, $site, ['quality'])->assertSessionHasNoErrors();
        $this->assertSame('fail', $this->stepData($site)['kpi_acceptance']['confirmations']['quality']['result']);
        $this->approveAcceptance($site);
        $this->assertSame(0, $this->criterion($e3, 'quality')['passed']);
        $this->assertStringContainsString('bị trả lại 1 lần', $this->criterion($e3, 'quality')['evidence'][0]['detail']);
    }

    // ------------------------------------------------------------------ 5. KS04 HSE

    public function test_hse_needs_all_five_items_confirmed(): void
    {
        $fail = $this->allPass();
        $fail['hse']['kpi_site_cleaned'] = 'fail';
        $e1 = $this->person('ky_thuat');
        $this->confirmedSite($fail, engineer: $e1);
        $hse = $this->criterion($e1, 'hse');
        $this->assertSame('fail', $hse['status']);
        $this->assertEqualsWithDelta(0.0, $hse['score'], 1e-9);

        $e2 = $this->person('ky_thuat');
        $this->confirmedSite(engineer: $e2);
        $hse = $this->criterion($e2, 'hse');
        $this->assertSame('pass', $hse['status']);
        $this->assertEqualsWithDelta(0.10, $hse['score'], 1e-9, 'Đủ 5 mục HSE đã xác nhận = 10 điểm kỳ 10/2026.');
        $this->assertStringContainsString('xác nhận: '.$this->manager->name, $hse['evidence'][0]['result']);
    }

    // ------------------------------------------------------------------ 6. KS05 EVN/App

    public function test_monitoring_link_alone_is_not_evn_app_pass(): void
    {
        $e1 = $this->person('ky_thuat');
        $site = $this->site($e1, true, true, ['monitoring_link' => 'https://app.solar.test/plant/1']);
        $this->approveAcceptance($site);
        $evn = $this->criterion($e1, 'evn_app');
        $this->assertSame('no_data', $evn['status']);
        $this->assertSame(0, $evn['passed']);

        // Dự án module cũ có link App: cũng chỉ là minh chứng hỗ trợ → thiếu.
        $e2 = $this->person('ky_thuat');
        $projectId = DB::table('project_test_projects')->insertGetId([
            'code' => 'EVN-'.Str::upper(Str::random(6)), 'company_id' => $this->companyId(), 'name' => 'Dự án cũ giả lập',
            'lead_technician_id' => $e2->id, 'created_by' => $e2->id, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('project_test_acceptances')->insert([
            'project_id' => $projectId, 'submitted_by' => $e2->id, 'accepted_at' => '2026-10-03', 'status' => 'approved',
            'monitoring_link' => 'https://app.solar.test/plant/2', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $evn = $this->criterion($e2, 'evn_app');
        $this->assertSame('no_data', $evn['status']);
        $this->assertStringContainsString('minh chứng hỗ trợ', $evn['evidence'][0]['result']);
    }

    public function test_evn_needs_grid_decision_and_grid_test_when_required(): void
    {
        // Chưa xác định cần đấu nối → không tự miễn, không xác nhận được.
        $e1 = $this->person('ky_thuat');
        $site = $this->site($e1, grid: null);
        $this->declare($e1, $site, $this->allPass(), 'submit');
        $this->confirm($this->manager, $site, ['evn'])->assertSessionHasErrors('acceptance_kpi');
        $this->approveAcceptance($site);
        $evn = $this->criterion($e1, 'evn_app');
        $this->assertSame('no_data', $evn['status']);

        // Cần đấu nối nhưng chưa ghi nhận đóng điện → thiếu.
        $e2 = $this->person('ky_thuat');
        $site = $this->site($e2, grid: true);
        $kpi = $this->allPass();
        $kpi['grid_tested'] = '';
        $this->declare($e2, $site, $kpi, 'submit');
        $this->confirm($this->manager, $site, ['evn'])->assertSessionHasErrors('acceptance_kpi');

        // Không cần đấu nối + App đạt đã xác nhận → 5 điểm; cần đấu nối + đủ → 5 điểm.
        $e3 = $this->person('ky_thuat');
        $this->confirmedSite(['grid_tested' => ''], grid: false, engineer: $e3);
        $this->assertEqualsWithDelta(0.05, $this->criterion($e3, 'evn_app')['score'], 1e-9);
        $e4 = $this->person('ky_thuat');
        $this->confirmedSite(grid: true, engineer: $e4);
        $evn = $this->criterion($e4, 'evn_app');
        $this->assertEqualsWithDelta(0.05, $evn['score'], 1e-9);
        $this->assertStringContainsString('xác nhận:', $evn['evidence'][0]['result']);
    }

    // ------------------------------------------------------------------ 7. phiên bản / trả bổ sung / khóa

    public function test_edit_or_new_evidence_after_confirmation_invalidates_only_that_version(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass(), 'submit');
        $this->confirm($this->manager, $site, ['quality', 'hse', 'evn'])->assertSessionHasNoErrors();

        // Sửa một mục HSE: xác nhận HSE cũ không còn áp dụng; nhóm khác giữ.
        $this->declare($this->engineer, $site, ['hse' => ['kpi_safety_gear' => 'fail']])->assertSessionHasNoErrors();
        $summary = app(AcceptanceKpiService::class)->summary($this->row($site));
        $this->assertNull($summary['hse']['confirmation']);
        $this->assertTrue($summary['hse']['stale']);
        $this->assertSame('confirmed', $summary['quality']['status']);
        $this->assertSame('confirmed', $summary['evn']['status']);
        // Đổi kỳ vọng (đợt salary-closeout, sửa R1): xác nhận cũ chuyển hẳn sang lịch sử thay vì nằm lại trong
        // `confirmations` chờ khớp vân tay — vẫn giữ lịch sử, nhưng không còn khả năng tự sống lại.
        $state = $this->stepData($site)['kpi_acceptance'];
        $this->assertArrayNotHasKey('hse', $state['confirmations']);
        $this->assertSame($this->manager->id, $state['confirmation_history']['hse'][0]['by'], 'Giữ lịch sử xác nhận cũ.');
        $this->assertSame('declaration_saved', $state['confirmation_history']['hse'][0]['invalidated_cause']);

        // Thêm file chứng chỉ thử nghiệm (minh chứng EVN) → xác nhận EVN cũ không áp dụng.
        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.documents.upload', [$site, 'acceptance']), [
            'document_code' => 'test_certificates', 'file' => UploadedFile::fake()->create('thu-nghiem.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $summary = app(AcceptanceKpiService::class)->summary($this->row($site));
        $this->assertNull($summary['evn']['confirmation']);
        $this->assertSame('confirmed', $summary['quality']['status']);

        $this->approveAcceptance($site);
        $this->assertSame('no_data', $this->criterion($this->engineer, 'hse')['status']);
        $this->assertSame('no_data', $this->criterion($this->engineer, 'evn_app')['status']);
        $this->assertEqualsWithDelta(0.15, $this->criterion($this->engineer, 'quality')['score'], 1e-9);
    }

    public function test_reviewer_cannot_confirm_a_version_replaced_while_reviewing(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass(), 'submit');
        $seen = $this->fingerprints($site);

        $this->declare($this->engineer, $site, ['hse' => ['kpi_roof_intact' => 'fail']], 'submit');
        $this->confirm($this->manager, $site, ['hse'], $seen)->assertStatus(302)->assertSessionHasErrors('acceptance_kpi');
        $this->assertArrayNotHasKey('hse', $this->stepData($site)['kpi_acceptance']['confirmations']);

        // Sửa nhưng chưa gửi lại: bản đang hiển thị không còn là bản đã gửi.
        $this->declare($this->engineer, $site, ['hse' => ['kpi_roof_intact' => 'pass']]);
        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasErrors('acceptance_kpi');
    }

    public function test_return_requires_reason_keeps_history_and_needs_resubmission(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass(), 'submit');
        $this->confirm($this->manager, $site, ['quality'])->assertSessionHasNoErrors();
        $url = route('projects-unified.workflow.kpi.return', [$site, 'acceptance']);

        $this->actingAs($this->manager)->post($url, ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->manager)->post($url, ['reason' => 'Bổ sung ảnh dọn vệ sinh mái', 'groups' => ['hse']])->assertSessionHasNoErrors();
        $state = $this->stepData($site)['kpi_acceptance'];
        $this->assertSame('returned', $state['status']);
        $this->assertSame(['hse'], $state['returned']['groups']);
        $this->assertSame($this->manager->id, $state['returned']['by']);
        $this->assertArrayHasKey('quality', $state['confirmations'], 'Trả nhóm HSE không xóa xác nhận nhóm khác.');
        $this->assertTrue(DB::table('project_workflow_events')->where('workflow_step_id', $this->stepId($site))->where('action', 'acceptance_kpi_returned')->exists());

        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasErrors('acceptance_kpi');
        // Nhóm không bị trả vẫn đang chờ kiểm tra và xác nhận được.
        $summary = app(AcceptanceKpiService::class)->summary($this->row($site));
        $this->assertSame('returned', $summary['hse']['status']);
        $this->assertSame('submitted', $summary['evn']['status']);
        $this->confirm($this->manager, $site, ['evn'])->assertSessionHasNoErrors();
        $this->declare($this->engineer, $site, [], 'submit')->assertSessionHasNoErrors();
        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasNoErrors();
        // Trả bổ sung KPI không phải "Trả lại" thi công: không làm chất lượng thành không đạt.
        $this->assertSame(0, app(AcceptanceKpiService::class)->revisionCount($site->id));
    }

    public function test_approved_acceptance_locks_declaration_upload_and_return_for_non_admin(): void
    {
        $site = $this->confirmedSite();
        $before = $this->stepData($site)['kpi_acceptance'];

        $this->declare($this->engineer, $site, ['hse' => ['kpi_safety_gear' => 'fail']], 'submit')->assertSessionHasErrors('acceptance_kpi');
        $this->actingAs($this->engineer)->post(route('projects-unified.workflow.documents.upload', [$site, 'acceptance']), [
            'document_code' => 'completion_photos', 'file' => UploadedFile::fake()->create('anh-moi.jpg', 30, 'image/jpeg'),
        ])->assertStatus(422);
        $this->actingAs($this->manager)->post(route('projects-unified.workflow.kpi.return', [$site, 'acceptance']), ['reason' => 'Trả sau khi đã duyệt'])
            ->assertSessionHasErrors('acceptance_kpi');
        $this->actingAs($this->manager)->post(route('projects-unified.workflow.data.save', [$site, 'legal']), ['requires_grid_connection' => '0'])
            ->assertForbidden();
        $this->assertSame($before, $this->stepData($site)['kpi_acceptance']);
        $this->assertEqualsWithDelta(0.10, $this->criterion($this->engineer, 'hse')['score'], 1e-9);

        // Admin giữ ngoại lệ hiện có, có ghi lịch sử.
        $this->declare($this->adminUser, $site, ['hse_note' => 'Admin bổ sung ghi chú'])->assertSessionHasNoErrors();
        $payload = json_decode((string) DB::table('project_workflow_events')->where('workflow_step_id', $this->stepId($site))
            ->where('action', 'acceptance_kpi_draft_saved')->where('actor_id', $this->adminUser->id)->value('payload'), true);
        $this->assertTrue($payload['admin_exception']);
    }

    // ------------------------------------------------------------------ 8. track duyệt Admin / kỳ

    public function test_manager_confirmation_does_not_approve_acceptance_and_admin_track_is_kept(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass(), 'submit');
        $this->confirm($this->manager, $site, ['quality', 'hse', 'evn'])->assertSessionHasNoErrors();

        $row = $this->row($site);
        $this->assertSame('in_progress', $row->status);
        $this->assertNull($row->approved_by);
        $this->assertSame(0, DB::table('project_workflow_approvals')->where('workflow_step_id', $row->id)->count());
        $this->assertSame(['admin'], array_keys((array) config('project_workflow_v2.steps.acceptance.approval_tracks')));

        // Chưa duyệt nghiệm thu → chưa có trong kỳ KPI.
        $this->assertSame('no_data', $this->criterion($this->engineer, 'hse')['status']);
        $this->assertSame([], $this->criterion($this->engineer, 'hse')['evidence']);

        DB::table('project_workflow_steps')->where('id', $row->id)->update(['status' => 'submitted']);
        $this->actingAs($this->manager)->post(route('projects-unified.workflow.approve', [$site, 'acceptance']), ['approval_type' => 'admin'])->assertForbidden();
        $this->approveAcceptance($site);
        $this->assertSame(1, $this->criterion($this->engineer, 'hse')['passed']);
        $this->assertSame([], $this->criterion($this->engineer, 'hse', '2026-09')['evidence'], 'Không tính vào tháng khác.');

        // Cùng công trình có minh chứng duyệt tay trong kỳ → chỉ tính một nguồn.
        DB::table('technical_kpi_project_evidence')->insert([
            'site_id' => $site->id, 'user_id' => $this->engineer->id, 'payroll_month' => self::MONTH, 'hse_pass' => 1,
            'quality_first_pass' => 1, 'approved_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertCount(1, $this->criterion($this->engineer, 'hse')['evidence']);
    }

    public function test_confirmation_is_refused_into_an_approved_payroll_month(): void
    {
        $site = $this->site();
        $this->declare($this->engineer, $site, $this->allPass(), 'submit');
        $this->approveAcceptance($site);
        DB::table('technical_kpi_payrolls')->insert([
            'user_id' => $this->engineer->id, 'employee_name' => $this->engineer->name, 'payroll_month' => self::MONTH,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasErrors('acceptance_kpi');
        $this->assertSame([], $this->stepData($site)['kpi_acceptance']['confirmations']);
    }

    public function test_legacy_reviewed_data_keeps_scoring_and_is_labelled_as_old_source(): void
    {
        $site = $this->site();
        DB::table('project_workflow_steps')->where('id', $this->stepId($site))->update([
            'status' => 'approved', 'approved_by' => $this->adminUser->id, 'approved_at' => '2026-10-05 15:00:00',
            'data' => json_encode(array_fill_keys(array_keys(ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS), true) + [
                'kpi_customer_complaint' => false, 'kpi_app_installed' => true, 'kpi_evn_grid_tested' => true,
                'kpi_reviewed_by' => $this->manager->id, 'kpi_reviewed_at' => '2026-10-05 15:00:00',
            ]),
        ]);
        $this->assertEqualsWithDelta(0.10, $this->criterion($this->engineer, 'hse')['score'], 1e-9);
        $this->assertEqualsWithDelta(0.05, $this->criterion($this->engineer, 'evn_app')['score'], 1e-9);
        $this->actingAs($this->manager)->get(route('projects-unified.show', ['site' => $site->id, 'step' => 'acceptance']))
            ->assertOk()->assertSee('đánh giá dạng cũ');
    }

    // ------------------------------------------------------------------ giao diện bước Nghiệm thu

    public function test_acceptance_screen_shows_role_specific_actions_and_kpi_note(): void
    {
        $site = $this->site();
        $url = route('projects-unified.show', ['site' => $site->id, 'step' => 'acceptance']);

        $this->actingAs($this->engineer)->get($url)->assertOk()
            ->assertSee('KẾT QUẢ NGHIỆM THU TÍNH KPI')->assertSee('Gửi kiểm tra')->assertSee('name="kpi[hse][kpi_no_incident]"', false)
            ->assertDontSee('Xác nhận nhóm đã chọn');

        $this->declare($this->engineer, $site, $this->allPass(), 'submit');
        $this->actingAs($this->manager)->get($url)->assertOk()
            ->assertSee('Xác nhận nhóm đã chọn')->assertSee('Trả bổ sung')->assertDontSee('name="kpi[hse][kpi_no_incident]"', false)
            ->assertSee('Chờ kiểm tra');

        $this->confirm($this->manager, $site, ['hse'])->assertSessionHasNoErrors();
        $this->approveAcceptance($site);
        $this->actingAs($this->engineer)->get($url)->assertOk()
            ->assertSee('Đã xác nhận — đạt')->assertSee('được tính <strong>đạt</strong>', false)
            ->assertSee('trọng số 10%');
    }
}
