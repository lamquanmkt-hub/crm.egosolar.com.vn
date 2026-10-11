<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Projects\Site;
use App\Models\User;
use App\Services\Projects\ManualMaterialKpiService;
use App\Services\Projects\ProjectWorkflowV2Service;
use App\Services\TechnicalKpi\KpiChecklistPresenter;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class TechnicalManualMaterialKpiTest extends TestCase
{
    use DatabaseTransactions, TechnicalWorkFixtures;

    private User $manager;
    private User $engineer;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');
        Storage::fake('public');
        $this->manager = $this->technicalManager();
        $this->engineer = $this->technician();
        $this->adminUser = $this->admin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function site(bool $evidence = true): Site
    {
        $site = Site::query()->findOrFail($this->makeSite(null, ['lead_engineer_id' => $this->engineer->id]));
        app(ProjectWorkflowV2Service::class)->ensure($site);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->update([
            'status' => 'approved', 'approved_by' => $this->adminUser->id, 'approved_at' => now(),
        ]);
        foreach (['survey', 'proposal'] as $step) {
            $row = DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', $step)->first();
            foreach (config('project_workflow_v2.steps.'.$step.'.documents') as $code => $definition) {
                for ($i = 0; $i < ($definition['min'] ?? 1); $i++) {
                    $this->document($site, $row, $code);
                }
            }
        }
        if ($evidence) {
            $this->document($site, $this->row($site), 'material_reconciliation');
        }

        return $site;
    }

    private function document(Site $site, object $row, string $code): void
    {
        DB::table('project_workflow_documents')->insert([
            'site_id' => $site->id, 'workflow_step_id' => $row->id, 'document_code' => $code,
            'title' => 'TEST '.$code, 'path' => 'test/'.$code.'.pdf', 'original_name' => 'TEST-'.$code.'.pdf',
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function row(Site $site): object
    {
        return DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'acceptance')->first();
    }

    private function service(): ManualMaterialKpiService
    {
        return app(ManualMaterialKpiService::class);
    }

    private function save(Site $site, User $actor, string $intent = 'confirm', $percent = 2, string $note = 'Bóc tách 100m, dùng 102m, sai lệch 2%.', array $extra = [])
    {
        $row = $this->row($site);

        return $this->actingAs($actor)->post(route('projects-unified.workflow.material-kpi.save', [$site, 'acceptance']), array_replace([
            'intent' => $intent, 'percent' => $percent, 'note' => $note,
            'version' => $this->service()->state($row)['version'], 'fingerprint' => $this->service()->fingerprint($row),
        ], $extra));
    }

    private function survey(): array
    {
        $evaluator = app(KpiStandardEvaluator::class)->useConfig(null, '2026-10');
        $data = $evaluator->collect($this->engineer->id, ['2026-10']);
        $result = $evaluator->score($data['projects'], $data['evidence'], $data['warranty'], $data['manual'], $data['adjustments']);

        return collect($result['criteria'])->firstWhere('code', 'survey');
    }

    public function test_only_manager_or_admin_can_write_and_viewer_has_no_form(): void
    {
        $site = $this->site();
        $this->save($site, $this->engineer)->assertForbidden();
        $this->save($site, $this->outsider())->assertNotFound(); // Sales scope conceals an unowned project.
        $this->actingAs($this->engineer)->get(route('projects-unified.show', [$site, 'step' => 'acceptance']))
            ->assertOk()->assertSee('ĐỐI CHIẾU VẬT TƯ TÍNH KPI')->assertDontSee('name="percent"', false);
        $this->actingAs($this->manager)->get(route('projects-unified.show', [$site, 'step' => 'acceptance']))
            ->assertOk()->assertSee('Lưu nháp vật tư')->assertSee('Xác nhận kết quả vật tư');
    }

    public function test_draft_and_blank_never_count_and_confirmation_uses_server_stamp(): void
    {
        $site = $this->site();
        $this->assertNull($this->survey()['score']);
        $this->save($site, $this->manager, 'draft')->assertSessionHasNoErrors();
        $this->assertNull($this->survey()['score']);
        $this->assertSame('draft', $this->survey()['evidence'][0]['source_status']);
        $this->save($site, $this->manager, 'draft', '')->assertSessionHasNoErrors();
        $this->assertNull($this->service()->state($this->row($site))['percent']);
        $this->save($site, $this->manager, 'confirm', 2, 'Đối chiếu 100m / 102m.', ['confirmed_by' => $this->engineer->id])->assertSessionHasNoErrors();
        $this->assertSame($this->manager->id, $this->service()->state($this->row($site))['confirmation']['by']);
        $this->assertEqualsWithDelta(0.15, $this->survey()['score'], 1e-9);
        $this->assertStringContainsString('Nguồn: Manager nhập tay', $this->survey()['evidence'][0]['detail']);
        $this->assertStringContainsString($this->manager->name, $this->survey()['evidence'][0]['detail']);
        $url = app(KpiChecklistPresenter::class)->sourceUrl('survey', $this->survey()['evidence'][0]);
        $this->assertStringContainsString('step=acceptance#material-kpi', $url);
    }

    public function test_confirm_requires_percent_note_and_attachment(): void
    {
        $site = $this->site(false);
        $this->save($site, $this->manager)->assertSessionHasErrors('acceptance_kpi');
        $this->assertSame('empty', $this->service()->state($this->row($site))['status']);
        $this->document($site, $this->row($site), 'material_reconciliation');
        $this->save($site, $this->manager, 'confirm', '')->assertSessionHasErrors('acceptance_kpi');
        $this->save($site, $this->manager, 'confirm', 2, '')->assertSessionHasErrors('acceptance_kpi');
        $this->save($site, $this->manager, 'confirm', -1)->assertSessionHasErrors('percent');
        $this->save($site, $this->manager, 'confirm', 0)->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(0.15, $this->survey()['score'], 1e-9);
    }

    public function test_boundary_is_strict_and_input_is_not_rounded_before_comparison(): void
    {
        $site = $this->site();
        $this->save($site, $this->manager, 'confirm', '2.999999')->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(2.999999, $this->service()->result($this->row($site))['percent'], 1e-9);
        $this->assertEqualsWithDelta(0.15, $this->survey()['score'], 1e-9);
        foreach ([3, 5] as $percent) {
            $this->save($site, $this->manager, 'confirm', $percent)->assertSessionHasNoErrors();
            $this->assertEquals(0, $this->survey()['score']);
            $this->assertSame('fail', $this->survey()['evidence'][0]['state']);
        }
    }

    public function test_edit_and_restore_do_not_revive_confirmation_and_stale_form_is_rejected(): void
    {
        $site = $this->site();
        $this->save($site, $this->manager)->assertSessionHasNoErrors();
        $version = $this->service()->state($this->row($site))['version'];
        $this->save($site, $this->manager, 'draft', 4)->assertSessionHasNoErrors();
        $this->save($site, $this->manager, 'draft', 2)->assertSessionHasNoErrors();
        $this->assertNull($this->survey()['score']);
        $this->assertCount(1, $this->service()->state($this->row($site))['history']);
        $this->save($site, $this->manager, 'confirm', 2, 'TEST', ['version' => $version])->assertSessionHasErrors('acceptance_kpi');
        $this->assertNull($this->survey()['score']);
    }

    public function test_upload_invalidates_confirmed_material_but_keeps_acceptance_approved(): void
    {
        $site = $this->site();
        $this->save($site, $this->manager)->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('projects-unified.workflow.documents.upload', [$site, 'acceptance']), [
            'document_code' => 'material_reconciliation', 'file' => UploadedFile::fake()->create('TEST-reconciliation.pdf', 3, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('approved', $this->row($site)->status);
        $this->assertNull($this->survey()['score']);
        $this->assertCount(1, $this->service()->state($this->row($site))['history']);
    }

    public function test_missing_survey_blocks_criterion_and_missing_one_project_is_not_ignored(): void
    {
        $site = $this->site();
        $this->save($site, $this->manager)->assertSessionHasNoErrors();
        $other = $this->site();
        $this->assertNull($this->survey()['score']);
        $this->assertCount(2, $this->survey()['evidence']);
        $this->save($other, $this->manager)->assertSessionHasNoErrors();
        DB::table('project_workflow_documents')->where('site_id', $other->id)->where('document_code', 'material_boq')->delete();
        $this->assertNull($this->survey()['score']);
        $this->assertStringContainsString('Khảo sát thiếu', implode(' ', $this->survey()['missing']));
    }

    public function test_locked_payroll_blocks_manager_admin_legacy_endpoint_and_upload_before_any_write(): void
    {
        $site = $this->site();
        $this->save($site, $this->manager)->assertSessionHasNoErrors();
        DB::table('technical_kpi_payrolls')->insert(['user_id' => $this->engineer->id, 'employee_name' => $this->engineer->name,
            'payroll_month' => '2026-10', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        $before = $this->row($site)->data;
        foreach ([$this->manager, $this->adminUser] as $actor) {
            $this->save($site, $actor)->assertSessionHasErrors('acceptance_kpi');
        }
        $this->actingAs($this->adminUser)->post(route('projects-unified.workflow.data.save', [$site, 'acceptance']), ['kpi_material_deviation_percent' => 1])->assertStatus(422);
        $this->actingAs($this->manager)->post(route('projects-unified.workflow.documents.upload', [$site, 'acceptance']), [
            'document_code' => 'material_reconciliation', 'file' => UploadedFile::fake()->create('TEST.pdf', 3, 'application/pdf'),
        ])->assertStatus(422);
        $this->assertSame($before, $this->row($site)->data);
        $this->assertCount(1, $this->service()->documents($this->row($site)));
    }

    public function test_older_source_is_preserved_until_explicitly_edited(): void
    {
        $site = $this->site();
        DB::table('project_workflow_steps')->where('id', $this->row($site)->id)->update([
            'approved_at' => '2026-10-07 09:00:00', 'data' => json_encode(['kpi_material_deviation_percent' => 2]),
        ]);
        $this->assertFalse($this->service()->requiresConfirmation($this->row($site)));
        $this->assertEqualsWithDelta(0.15, $this->survey()['score'], 1e-9);
        $this->save($site, $this->manager, 'draft')->assertSessionHasNoErrors();
        $this->assertNull($this->survey()['score']);
    }

    public function test_confirmation_does_not_approve_acceptance_or_count_before_admin_approval(): void
    {
        $site = $this->site();
        DB::table('project_workflow_steps')->where('id', $this->row($site)->id)->update(['status' => 'in_progress', 'approved_at' => null, 'approved_by' => null]);
        $this->save($site, $this->manager)->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $this->row($site)->status);
        $this->assertNull($this->survey()['score']);
    }
}
