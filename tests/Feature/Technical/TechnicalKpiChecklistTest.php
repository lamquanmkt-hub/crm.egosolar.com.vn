<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Console\Commands\SeedKpiChecklistDemo;
use App\Models\User;
use App\Services\TechnicalKpi\KpiChecklistPresenter;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Checklist nguồn dưới 6 tiêu chí ở chi tiết KPI từng người + lệnh tạo 5 việc TEST (đợt kpi-checklist-demo 07/10/2026).
 * Dữ liệu giả trong transaction (rollback), chỉ chạy trên DB egosolar_test.
 */
final class TechnicalKpiChecklistTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private string $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00');
        $this->manifest = sys_get_temp_dir().'/kpi-checklist-test-'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        @unlink($this->manifest);
        parent::tearDown();
    }

    /** Tài khoản/công trình/phiếu mẫu giống local (định danh ổn định), trong DB test. */
    private function demo(): array
    {
        $manager = $this->technicalManager(['email' => SeedKpiChecklistDemo::MANAGER_EMAIL, 'name' => 'TP mẫu checklist']);
        $engineer = $this->technician(['email' => SeedKpiChecklistDemo::ENGINEER_EMAIL, 'name' => 'KT mẫu checklist']);
        $siteId = $this->makeSite(null, ['project_code' => SeedKpiChecklistDemo::SITE_CODE, 'lead_engineer_id' => $engineer->id]);
        $this->makeWorkflowAssignment($siteId, $engineer->id, ['step_code' => 'warranty', 'sequence' => 7]);
        $this->makeMaintenance($siteId, $engineer->id, ['schedule_code' => SeedKpiChecklistDemo::SCHEDULE_CODE]);

        return [$manager, $engineer, $siteId];
    }

    private function runDemoSeed(bool $apply = true): int
    {
        return $this->artisan('kpi:demo-checklist', array_filter(['--apply' => $apply, '--manifest' => $this->manifest]))->run();
    }

    private function demoItems(): \Illuminate\Support\Collection
    {
        return DB::table('technical_plan_items')->where('title', 'like', SeedKpiChecklistDemo::PREFIX.'%')->orderBy('title')->get();
    }

    private function timeline(User $engineer): array
    {
        $e = app(KpiStandardEvaluator::class)->useConfig(null, '2026-10');
        $d = $e->collect($engineer->id, ['2026-10']);

        return collect($e->score($d['projects'], $d['evidence'], $d['warranty'], $d['manual'], $d['adjustments'], $d['assigned_work'])['criteria'])
            ->firstWhere('code', 'timeline');
    }

    private function page(User $viewer, int $userId): string
    {
        return (string) $this->actingAs($viewer)->get(route('ky-thuat.kpis.index', ['period' => 'month', 'month' => '2026-10', 'user_id' => $userId]))
            ->assertOk()->getContent();
    }

    public function test_dry_run_writes_nothing_and_apply_creates_five_jobs_with_first_send_history(): void
    {
        [$manager, $engineer, $siteId] = $this->demo();
        $this->assertSame(0, $this->runDemoSeed(false));
        $this->assertCount(0, $this->demoItems());

        $this->assertSame(0, $this->runDemoSeed());
        $items = $this->demoItems();
        $this->assertCount(5, $items);
        foreach ($items as $i => $item) {
            $code = sprintf('%02d', $i + 1);
            $this->assertSame(SeedKpiChecklistDemo::ITEMS[$code]['assigned'], (string) $item->assigned_at);
            $this->assertSame(1, (int) $item->is_manager_assigned);
            $this->assertSame($manager->id, (int) $item->assigned_by);
            $this->assertSame($engineer->id, (int) $item->user_id);
            $this->assertSame($siteId, (int) $item->site_id);
            $this->assertStringContainsString('Dữ liệu giả TEST', (string) $item->note);
        }
        $this->assertSame(5, DB::table('technical_plan_histories')->whereIn('plan_item_id', $items->pluck('id'))->where('action', 'assign')->count());

        $submits = DB::table('technical_daily_report_histories as h')->join('technical_daily_reports as r', 'r.id', '=', 'h.report_id')
            ->whereIn('r.plan_item_id', $items->pluck('id'))->where('h.action', 'submit')->orderBy('h.created_at')->pluck('h.created_at')->map(fn ($v) => (string) $v)->all();
        $this->assertSame(['2026-10-02 17:00:00', '2026-10-04 08:00:00', '2026-10-05 10:00:00'], $submits);
        $this->assertSame('draft', DB::table('technical_daily_reports')->where('plan_item_id', $items[4]->id)->value('status'));
        $this->assertSame(0, DB::table('technical_daily_reports')->where('plan_item_id', $items[3]->id)->count());
        $this->assertFileExists($this->manifest);
    }

    public function test_rerun_does_not_duplicate_or_change_times_and_keeps_later_user_actions(): void
    {
        [, $engineer] = $this->demo();
        $this->runDemoSeed();
        $before = $this->demoItems()->map(fn ($i) => [$i->id, (string) $i->assigned_at])->all();
        // Kỹ thuật gửi báo cáo nháp của việc 05 sau khi tạo mẫu.
        $draft = DB::table('technical_daily_reports')->where('plan_item_id', $this->demoItems()[4]->id)->first();
        DB::table('technical_daily_reports')->where('id', $draft->id)->update(['status' => 'submitted', 'submitted_at' => '2026-10-07 10:30:00']);

        $this->assertSame(0, $this->runDemoSeed());
        $this->assertSame($before, $this->demoItems()->map(fn ($i) => [$i->id, (string) $i->assigned_at])->all());
        $this->assertSame(4, DB::table('technical_daily_reports')->where('user_id', $engineer->id)->count());
        $this->assertSame('submitted', DB::table('technical_daily_reports')->where('id', $draft->id)->value('status'));
    }

    public function test_guard_refuses_when_demo_identity_does_not_match(): void
    {
        $this->assertSame(1, $this->runDemoSeed());
        [, $engineer] = $this->demo();
        DB::table('model_has_roles')->where('model_id', $engineer->id)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->assertSame(1, $this->runDemoSeed());
        $this->assertCount(0, $this->demoItems());
    }

    public function test_scoring_matches_hand_calculation_and_pending_job_does_not_lose_points(): void
    {
        [, $engineer] = $this->demo();
        $this->runDemoSeed();
        $t = $this->timeline($engineer);
        $this->assertSame(2, $t['passed']);
        $this->assertSame(4, $t['total']);
        $this->assertEqualsWithDelta(0.125, $t['score'], 1e-9, '2/4 × 25 = 12,5 điểm');
        $this->assertSame(['pass', 'pass', 'fail', 'fail', 'info'], array_column($t['evidence'], 'state'));
        $this->assertSame([6.25, 6.25, 6.25, 6.25], array_values(array_filter(array_map(fn ($e) => $e['points_possible'] ?? null, $t['evidence']))));
        $this->assertSame([6.25, 6.25, 0.0, 0.0], array_slice(array_map(fn ($e) => $e['points_earned'] ?? null, $t['evidence']), 0, 4));
        $this->assertSame('pending', $t['evidence'][4]['phase']);
        $this->assertSame(7200, $t['evidence'][2]['late_seconds']);

        // Sau hạn 24 giờ của việc 05 (08/10 09:00) mà chưa nộp: vào mẫu số và mất phần điểm.
        Carbon::setTestNow('2026-10-08 09:00:01');
        $t = $this->timeline($engineer);
        $this->assertSame(2, $t['passed']);
        $this->assertSame(5, $t['total']);
        $this->assertEqualsWithDelta(0.10, $t['score'], 1e-9);
    }

    public function test_kpi_page_shows_checklist_under_six_criteria_with_states_points_and_links(): void
    {
        [, $engineer] = $this->demo();
        $this->runDemoSeed();
        $admin = $this->admin();
        $html = $this->page($admin, $engineer->id);
        $items = $this->demoItems();
        $reports = DB::table('technical_daily_reports')->whereIn('plan_item_id', $items->pluck('id'))->pluck('id', 'plan_item_id');

        $this->assertStringContainsString('Checklist nguồn theo tiêu chí', $html);
        foreach (['timeline', 'quality', 'survey', 'hse', 'evn_app', 'warranty'] as $code) {
            $this->assertStringContainsString('data-kpx-checklist="'.$code.'"', $html, $code);
        }
        foreach (['Đã nộp đúng hạn', 'Đã nộp · muộn 2 giờ', 'Chưa nộp · quá hạn', 'Chưa nộp (có bản nháp) · còn hạn đến 08/10 09:00',
            'Nhận 6,25 điểm', 'Mất 6,25 điểm', 'Chưa tính — chưa đến hạn, không mất điểm', '12,5/25 điểm', 'Chưa có dữ liệu.'] as $text) {
            $this->assertStringContainsString($text, $html, $text);
        }
        $this->assertSame(2, substr_count($html, 'data-kpx-row-bucket="earned"'));
        $this->assertSame(2, substr_count($html, 'data-kpx-row-bucket="lost"'));
        foreach ([0, 1, 2] as $i) {
            $this->assertStringContainsString('href="'.route('technical.daily-reports.show', ['report' => $reports[$items[$i]->id]]).'"', $html);
        }
        // Quản lý mở dòng việc chưa nộp tại màn điều phối (không mở nháp của nhân viên).
        $this->assertStringContainsString(e(route('technical.manager.detail', ['member' => $engineer->id, 'week' => '2026-10-05', 'item' => $items[3]->id]).'#adj-'.$items[3]->id), $html);
        $this->assertStringNotContainsString('href="'.route('technical.daily-reports.show', ['report' => $reports[$items[4]->id]]).'"', $html);
    }

    public function test_engineer_sees_only_own_checklist_in_company_scope_and_own_draft_link(): void
    {
        [, $engineer] = $this->demo();
        $this->runDemoSeed();
        $other = $this->technician(['name' => 'KT khác checklist']);
        $plan = $this->makeWeekPlan($other, Carbon::parse('2026-10-05'));
        $this->makePlanItem($other, $plan, ['title' => 'Việc của người khác KHÔNG hiện', 'is_manager_assigned' => 1, 'assigned_at' => '2026-10-05 08:00:00', 'plan_date' => '2026-10-05']);
        $ownPlan = (int) DB::table('technical_week_plans')->where('user_id', $engineer->id)->whereDate('week_start', '2026-10-05')->value('id');
        $this->makePlanItem($engineer, $ownPlan, ['title' => 'Việc công ty khác KHÔNG hiện', 'company_id' => $this->companyId() === 1 ? 2 : 1,
            'is_manager_assigned' => 1, 'assigned_at' => '2026-10-06 08:00:00', 'plan_date' => '2026-10-06']);

        // Kỹ thuật đổi user_id trên URL vẫn chỉ thấy KPI của chính mình.
        $html = $this->page($engineer, $other->id);
        $this->assertStringContainsString(SeedKpiChecklistDemo::PREFIX.'01', $html);
        $this->assertStringNotContainsString('Việc của người khác KHÔNG hiện', $html);
        $this->assertStringNotContainsString('Việc công ty khác KHÔNG hiện', $html);
        $draftId = DB::table('technical_daily_reports')->where('plan_item_id', $this->demoItems()[4]->id)->value('id');
        $this->assertStringContainsString('href="'.route('technical.daily-reports.show', ['report' => $draftId]).'"', $html);
        $this->actingAs($engineer)->get(route('technical.daily-reports.show', ['report' => $draftId]))->assertOk();
    }

    public function test_legacy_acceptance_row_shows_checked_source_and_points_at_project_level(): void
    {
        [, $engineer, $siteId] = $this->demo();
        DB::table('project_workflow_steps')->insert([
            'site_id' => $siteId, 'step_code' => 'acceptance', 'sequence' => 6, 'status' => 'approved',
            'approved_at' => '2026-10-05 15:00:00', 'approved_by' => $this->admin()->id,
            'data' => json_encode(array_fill_keys(array_keys(\App\Services\TechnicalKpi\ProjectKpiLinkService::ACCEPTANCE_HSE_CHECKS), true)
                + ['kpi_reviewed_by' => $this->technicalManager()->id, 'kpi_reviewed_at' => '2026-10-05 15:00:00']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $e = app(KpiStandardEvaluator::class)->useConfig(null, '2026-10');
        $d = $e->collect($engineer->id, ['2026-10']);
        $criteria = $e->score($d['projects'], $d['evidence'], $d['warranty'], $d['manual'], $d['adjustments'], $d['assigned_work'])['criteria'];
        $blocks = collect(app(KpiChecklistPresenter::class)->build($criteria))->keyBy('code');

        $hse = $blocks['hse']['rows'][0];
        $this->assertSame('Đã đánh giá (dạng cũ)', $hse['work']);
        $this->assertSame('Nhận 10 điểm', $hse['kpi']);
        // Chất lượng dạng cũ chưa ghi nhận phản ánh: chưa đủ, chưa tính — không hiện đạt.
        $quality = $blocks['quality']['rows'][0];
        $this->assertSame('Dữ liệu cũ chưa đủ', $quality['work']);
        $this->assertSame('missing', $quality['bucket']);
        $this->assertSame('Chưa tính', $blocks['quality']['score_text']);
    }

    public function test_presenter_separates_work_status_from_kpi_status_for_unconfirmed_and_incomplete_criteria(): void
    {
        $criterion = fn (array $evidence, ?float $score, string $status) => [
            'no' => 4, 'code' => 'hse', 'name' => 'HSE', 'weight' => 0.10, 'score' => $score, 'status' => $status,
            'passed' => null, 'total' => null, 'missing' => ['Có công trình chưa xác nhận.'], 'evidence' => $evidence, 'source_mode' => null,
        ];
        $rows = app(KpiChecklistPresenter::class)->build([$criterion([
            ['project' => 'CT A', 'site_id' => 1, 'state' => 'missing', 'source_status' => 'submitted', 'result' => 'Đã gửi, chờ Trưởng phòng kiểm tra'],
            ['project' => 'CT B', 'site_id' => 2, 'state' => 'pass', 'source_status' => 'confirmed', 'result' => 'Đạt checklist HSE'],
            ['project' => 'CT C', 'site_id' => 3, 'state' => 'missing', 'source_status' => 'stale', 'result' => 'Nội dung đã đổi'],
        ], null, 'no_data')])[0]['rows'];

        $this->assertSame(['Chờ trưởng phòng xác nhận', 'Chưa tính — thiếu dữ liệu'], [$rows[0]['work'], $rows[0]['kpi']]);
        $this->assertSame(['Đã xác nhận', 'Đạt — chưa tính vì tiêu chí còn thiếu dữ liệu'], [$rows[1]['work'], $rows[1]['kpi']]);
        $this->assertSame('Xác nhận cũ hết hiệu lực — chờ gửi lại', $rows[2]['work']);
        $this->assertNotContains('earned', array_column($rows, 'bucket'), 'Không nhận điểm khi tiêu chí chưa đủ dữ liệu.');
    }
}
