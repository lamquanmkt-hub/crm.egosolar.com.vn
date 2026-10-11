<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Projects\Site;
use App\Models\User;
use App\Services\Projects\AcceptanceKpiService;
use App\Services\Projects\ProjectWorkflowV2Service;
use App\Services\TechnicalKpi\ProjectKpiLinkService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TechnicalAcceptanceLegacyDisplayTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private function fixture(array $overrides = []): array
    {
        $manager = $this->technicalManager(['name' => 'TEST Người xác nhận cũ']);
        $site = Site::query()->findOrFail($this->makeSite());
        app(ProjectWorkflowV2Service::class)->ensure($site);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('sequence', '<', 6)->update([
            'status' => 'approved', 'approved_by' => $manager->id, 'approved_at' => '2026-10-01 08:00:00',
        ]);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'legal')->update([
            'data' => json_encode(['requires_grid_connection' => true]),
        ]);
        $data = array_replace(array_fill_keys(array_keys(ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS), true), [
            'kpi_reviewed_by' => $manager->id, 'kpi_reviewed_at' => '2026-10-05 15:00:00',
            'kpi_customer_complaint' => false, 'kpi_app_installed' => true, 'kpi_evn_grid_tested' => true,
        ], $overrides);
        DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'acceptance')->update([
            'status' => 'approved', 'approved_by' => $manager->id, 'approved_at' => '2026-10-05 15:00:00',
            'data' => json_encode($data),
        ]);
        $row = DB::table('project_workflow_steps')->where('site_id', $site->id)->where('step_code', 'acceptance')->first();

        return [$site, $row];
    }

    private function render(User $viewer, Site $site, object $row): string
    {
        $this->actingAs($viewer);

        return view('projects-unified.partials.workflow-kpi-review', [
            'site' => $site, 'wfRow' => $row,
            'wfDocuments' => collect(), 'wfIsAdmin' => $viewer->hasRole('admin'),
            'wfPermissions' => [],
        ])->render();
    }

    public function test_confirmed_legacy_is_visible_without_empty_new_form_for_engineer(): void
    {
        [$site, $row] = $this->fixture();
        $before = $row->data;
        $html = $this->render($this->technician(), $site, $row);
        $this->assertStringContainsString('Kết quả đã xác nhận theo hồ sơ cũ', $html);
        $this->assertStringContainsString('TEST Người xác nhận cũ', $html);
        $this->assertStringContainsString('15:00 05/10/2026', $html);
        $this->assertStringContainsString('Không có phản ánh', $html);
        $this->assertSame(3, substr_count($html, 'Đã xác nhận — đạt'));
        $this->assertStringNotContainsString('pak-declare-form', $html);
        $this->assertStringNotContainsString('Chờ người thực hiện gửi kiểm tra', $html);
        $this->assertStringNotContainsString('Còn thiếu:', $html);
        $this->assertSame($before, DB::table('project_workflow_steps')->where('id', $row->id)->value('data'));
    }

    public function test_admin_sees_explicit_new_declaration_separate_from_legacy(): void
    {
        [$site, $row] = $this->fixture();
        $html = $this->render($this->admin(), $site, $row);
        $this->assertStringContainsString('data-pak-legacy', $html);
        $this->assertStringContainsString('Nhập lại kết quả theo luồng mới', $html);
        $this->assertStringContainsString('Khi lưu bản mới, hệ thống chuyển sang dùng bản mới', $html);
        $this->assertStringContainsString('Bản khai báo mới:', $html);
        $this->assertStringContainsString('pak-declare-form', $html);
        $this->assertStringContainsString('name="kpi[complaint]" value="" checked', $html);
        $this->assertStringNotContainsString('name="kpi[complaint]" value="none" checked', $html);
    }

    public function test_incomplete_or_unstamped_legacy_never_looks_fully_confirmed(): void
    {
        $key = array_key_first(ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS);
        [$site, $row] = $this->fixture([$key => false]);
        $html = $this->render($this->technician(), $site, $row);
        $this->assertStringContainsString('Hồ sơ cũ còn thiếu dữ liệu', $html);
        $this->assertStringContainsString('Chưa có xác nhận đạt', $html);
        $this->assertSame(2, substr_count($html, 'Đã xác nhận — đạt'));
        [$site, $row] = $this->fixture(['kpi_reviewed_by' => null]);
        $html = $this->render($this->admin(), $site, $row);
        $this->assertStringNotContainsString('data-pak-legacy', $html);
        $this->assertStringNotContainsString('Kết quả đã xác nhận theo hồ sơ cũ', $html);
    }

    public function test_new_draft_does_not_display_old_confirmation_as_active(): void
    {
        [$site, $row] = $this->fixture([AcceptanceKpiService::KEY => [
            'values' => ['complaint' => 'none'], 'version' => 1, 'status' => 'draft',
        ]]);
        $html = $this->render($this->admin(), $site, $row);
        $result = app(ProjectKpiLinkService::class)->acceptanceKpiResult($row);
        $this->assertStringNotContainsString('data-pak-legacy', $html);
        $this->assertStringContainsString('Đang nhập (nháp)', $html);
        $this->assertNull($result['quality_first_pass']);
        $this->assertNull($result['hse_pass']);
        $this->assertNull($result['evn_app_completed']);
    }
}
