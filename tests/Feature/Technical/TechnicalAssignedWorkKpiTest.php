<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\User;
use App\Services\TechnicalKpi\AssignedWorkKpiService;
use App\Services\TechnicalKpi\KpiStandardEvaluator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TechnicalAssignedWorkKpiTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function job(User $owner, array $overrides = []): int
    {
        $planId = DB::table('technical_week_plans')->where('user_id', $owner->id)->whereDate('week_start', $this->currentWeekStart())->value('id')
            ?? $this->makeWeekPlan($owner);
        return $this->makePlanItem($owner, (int) $planId, $overrides + [
            'title' => 'TEST việc giao tính KPI', 'is_manager_assigned' => 1,
            'assigned_at' => '2026-10-05 10:00:00', 'assigned_by' => $this->technicalManager()->id,
        ]);
    }

    private function report(User $owner, int $itemId, ?string $at, array $overrides = []): int
    {
        return $this->makeDailyReport($owner, $overrides + [
            'plan_item_id' => $itemId, 'submitted_at' => $at, 'status' => $at ? 'submitted' : 'draft',
        ]);
    }

    private function score(User $owner, array $months = ['2026-10'], ?int $siteId = null): array
    {
        $e = app(KpiStandardEvaluator::class)->useConfig(null, $months[0]);
        $d = $e->collect($owner->id, $months, $siteId);
        return collect($e->score($d['projects'], $d['evidence'], $d['warranty'], $d['manual'], $d['adjustments'], $d['assigned_work'])['criteria'])->firstWhere('code', 'timeline');
    }

    public function test_twenty_five_points_are_split_per_job_and_submission_deadline_is_inclusive(): void
    {
        $u = $this->technician();
        $this->report($u, $this->job($u), '2026-10-06 10:00:00'); // exactly 24h
        $this->report($u, $this->job($u), '2026-10-06 10:00:01'); // one second late
        $this->job($u); // no report
        $this->report($u, $this->job($u), null); // draft
        $r = $this->score($u);
        $this->assertSame(1, $r['passed']);
        $this->assertSame(4, $r['total']);
        $this->assertEqualsWithDelta(0.0625, $r['score'], 1e-9); // 1/4 * 25 = 6.25 points
        $this->assertSame(['pass', 'fail', 'fail', 'fail'], array_column($r['evidence'], 'state'));
        $this->assertStringContainsString('6,25/6,25 điểm', $r['evidence'][0]['result']);
        $this->assertStringContainsString('0/6,25 điểm', $r['evidence'][1]['result']);
    }

    public function test_pending_work_does_not_lose_points_and_zero_assignments_are_not_automatic_full_marks(): void
    {
        $u = $this->technician();
        $this->assertNull($this->score($u)['score']);
        $this->job($u, ['assigned_at' => '2026-10-07 11:00:00']);
        $r = $this->score($u);
        $this->assertNull($r['score']);
        $this->assertSame('info', $r['evidence'][0]['state']);
        $this->report($u, $this->job($u), '2026-10-06 09:00:00');
        $this->assertEqualsWithDelta(0.25, $this->score($u)['score'], 1e-9);
    }

    public function test_only_manager_assigned_work_counts_and_manual_monthly_values_cannot_hide_missing_reports(): void
    {
        $u = $this->technician();
        $this->job($u, ['is_manager_assigned' => 0]);
        $this->job($u, ['status' => 'cancelled']);
        $this->job($u);
        DB::table('technical_kpi_monthly_scores')->insert([
            'user_id' => $u->id, 'payroll_month' => '2026-10', 'criterion_code' => 'timeline',
            'plan_value' => 1, 'actual_value' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $r = $this->score($u);
        $this->assertSame(1, $r['total']);
        $this->assertSame(0, $r['passed']);
        $this->assertEqualsWithDelta(0, $r['score'], 1e-9);
        $this->assertCount(2, $r['evidence']);
    }

    public function test_wrong_employee_or_company_report_cannot_earn_points(): void
    {
        $u = $this->technician();
        $item = $this->job($u);
        $this->report($this->technician(), $item, '2026-10-06 09:00:00');
        $this->report($u, $item, '2026-10-06 09:00:00', ['company_id' => 999999]);
        $this->job($u, ['company_id' => 999999]);
        $this->assertSame(1, $this->score($u)['total']);
        $this->assertSame(0, $this->score($u)['passed']);
    }

    public function test_first_submission_is_kept_when_report_is_returned_and_resubmitted_later(): void
    {
        $u = $this->technician();
        $reportId = $this->report($u, $this->job($u), '2026-10-07 11:00:00', ['status' => 'revision_requested']);
        DB::table('technical_daily_report_histories')->insert([
            'report_id' => $reportId, 'user_id' => $u->id, 'user_name' => $u->name,
            'action' => 'submit', 'status_after' => 'submitted',
            'created_at' => '2026-10-06 09:00:00', 'updated_at' => '2026-10-06 09:00:00',
        ]);
        $this->assertSame(1, $this->score($u)['passed']);
        $this->assertStringContainsString('06/10/2026 09:00:00', $this->score($u)['evidence'][0]['detail']);
    }

    public function test_multiple_reports_for_one_job_do_not_duplicate_points(): void
    {
        $u = $this->technician();
        $item = $this->job($u);
        $this->report($u, $item, '2026-10-06 08:00:00');
        $this->report($u, $item, '2026-10-06 09:00:00');
        $r = $this->score($u);
        $this->assertSame(1, $r['total']);
        $this->assertSame(1, $r['passed']);
    }

    public function test_missing_or_invalid_timestamps_require_verification(): void
    {
        $u = $this->technician();
        $this->job($u, ['assigned_at' => null]);
        $this->report($u, $this->job($u), '2026-10-05 09:59:59');
        $r = $this->score($u);
        $this->assertNull($r['score']);
        $this->assertSame(['missing', 'missing'], array_column($r['evidence'], 'state'));
    }

    public function test_overnight_deadline_is_in_next_month_and_quarter_counts_job_only_once(): void
    {
        $u = $this->technician();
        $this->report($u, $this->job($u, ['assigned_at' => '2026-10-31 18:00:00']), '2026-11-01 18:00:00');
        Carbon::setTestNow('2026-11-02 12:00:00');
        $this->assertNull($this->score($u)['score']);
        $this->assertSame(1, $this->score($u, ['2026-11'])['passed']);
        $this->assertSame(1, $this->score($u, ['2026-10', '2026-11', '2026-12'])['total']);
    }

    public function test_historical_month_does_not_count_a_report_sent_later_and_september_keeps_old_source(): void
    {
        $u = $this->technician();
        $this->report($u, $this->job($u), '2026-11-01 08:00:00');
        Carbon::setTestNow('2026-11-02 12:00:00');
        $this->assertSame(0, $this->score($u)['passed']);
        $sep = app(KpiStandardEvaluator::class)->forMonth('2026-09');
        $this->assertNotSame('assigned_reports', collect($sep->criteria())->firstWhere('code', 'timeline')['source_mode'] ?? null);
        $this->assertEqualsWithDelta(0.30, collect($sep->criteria())->firstWhere('code', 'timeline')['weight'], 1e-9);
    }

    public function test_kpi_page_shows_submission_time_points_and_link_to_report(): void
    {
        $u = $this->technician();
        $reportId = $this->report($u, $this->job($u), '2026-10-06 10:00:00');
        $html = (string) $this->actingAs($this->admin())->get(route('ky-thuat.kpis.index', ['month' => '2026-10', 'user_id' => $u->id]))->assertOk()->getContent();
        $this->assertStringContainsString('Tiến độ công việc được giao', $html);
        $this->assertStringContainsString('Nộp đúng hạn', $html);
        $this->assertStringContainsString('25/25 điểm', $html);
        $this->assertStringContainsString('href="'.e(route('technical.daily-reports.show', $reportId)).'"', $html);
    }

    public function test_unsubmitted_job_links_to_its_employee_week_and_site_filter_is_respected(): void
    {
        $u = $this->technician();
        $siteId = $this->makeSite();
        $itemId = $this->job($u, ['site_id' => $siteId]);
        $this->report($u, $this->job($u, ['site_id' => $this->makeSite()]), '2026-10-06 09:00:00');
        $r = $this->score($u, ['2026-10'], $siteId);
        $this->assertSame(1, $r['total']);
        $this->assertSame(0, $r['passed']);
        $html = (string) $this->actingAs($this->admin())->get(route('ky-thuat.kpis.index', ['month' => '2026-10', 'user_id' => $u->id, 'site_id' => $siteId]))->assertOk()->getContent();
        $url = route('technical.manager.detail', ['member' => $u->id, 'week' => $this->currentWeekStart()->toDateString(), 'item' => $itemId]).'#adj-'.$itemId;
        $this->assertStringContainsString('href="'.e($url).'"', $html);
        $this->assertStringContainsString('Quá hạn chưa nộp', $html);
    }

    public function test_new_timeline_is_read_only_on_monthly_input_and_cannot_be_replaced_by_posting_values(): void
    {
        $u = $this->technician();
        $admin = $this->admin();
        $html = (string) $this->actingAs($admin)->get(route('ky-thuat.kpis.input', ['month' => '2026-10']))->assertOk()->getContent();
        $this->assertStringContainsString('Tự động từ việc được giao', $html);
        $this->assertStringNotContainsString('name="scores['.$u->id.'][timeline][plan]"', $html);
        $this->post(route('ky-thuat.kpis.input.store'), ['month' => '2026-10', 'scores' => [$u->id => ['timeline' => ['plan' => 1, 'actual' => 1]]]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('technical_kpi_monthly_scores', ['user_id' => $u->id, 'payroll_month' => '2026-10', 'criterion_code' => 'timeline']);
    }
}
