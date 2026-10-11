<?php

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalPlanItem;
use App\Services\TechnicalKpi\KpiActionHintService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TechnicalKpiActionHintTest extends TestCase
{
    use DatabaseTransactions, TechnicalWorkFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_work_notes_distinguish_sent_late_and_pending_from_manual_progress_without_writing_sources(): void
    {
        $engineer = $this->technician();
        $plan = $this->makeWeekPlan($engineer);
        $base = ['is_manager_assigned' => 1, 'assigned_at' => '2026-10-06 10:00:00', 'plan_date' => '2026-10-06'];
        $good = $this->makePlanItem($engineer, $plan, $base);
        $late = $this->makePlanItem($engineer, $plan, $base);
        $pending = $this->makePlanItem($engineer, $plan, array_replace($base, ['assigned_at' => '2026-10-08 10:00:00', 'plan_date' => '2026-10-08', 'status' => 'done', 'progress_percent' => 100]));
        $this->makeDailyReport($engineer, ['plan_item_id' => $good, 'status' => 'submitted', 'submitted_at' => '2026-10-06 18:00:00']);
        $this->makeDailyReport($engineer, ['plan_item_id' => $late, 'status' => 'submitted', 'submitted_at' => '2026-10-07 12:00:00']);
        $this->makeDailyReport($engineer, ['plan_item_id' => $pending, 'status' => 'draft']);
        $before = DB::table('technical_plan_items')->whereIn('id', [$good, $late, $pending])->get()->toJson();

        $this->actingAs($engineer)->get(route('technical.week-plan.index', ['week' => '2026-10-05']))
            ->assertOk()->assertSee('data-kpi-work-rules', false)->assertSee('Nộp đúng hạn — nhận điểm')
            ->assertSee('Nộp muộn — mất phần điểm của việc')->assertSee('Còn trong hạn 24 giờ — chưa tính vào mẫu số');
        $this->assertSame($before, DB::table('technical_plan_items')->whereIn('id', [$good, $late, $pending])->get()->toJson());
        $this->assertSame('draft', DB::table('technical_daily_reports')->where('plan_item_id', $pending)->value('status'));
    }

    public function test_report_uses_first_send_history_and_self_created_work_is_not_awarded(): void
    {
        $engineer = $this->technician();
        $plan = $this->makeWeekPlan($engineer);
        $item = $this->makePlanItem($engineer, $plan, ['is_manager_assigned' => 1, 'assigned_at' => '2026-10-06 10:00:00']);
        $report = $this->makeDailyReport($engineer, ['plan_item_id' => $item, 'status' => 'submitted', 'submitted_at' => '2026-10-08 10:00:00']);
        DB::table('technical_daily_report_histories')->insert(['report_id' => $report, 'user_id' => $engineer->id,
            'action' => 'submit', 'created_at' => '2026-10-06 18:00:00', 'updated_at' => '2026-10-06 18:00:00']);
        $this->actingAs($engineer)->get(route('technical.daily-reports.show', $report))->assertOk()
            ->assertSee('Nộp đúng hạn — nhận điểm')->assertSee('06/10/2026 18:00:00');

        $own = $this->makePlanItem($engineer, $plan, ['is_manager_assigned' => 0]);
        $this->assertSame('info', app(KpiActionHintService::class)->item(TechnicalPlanItem::findOrFail($own))['state']);
        $this->actingAs($engineer)->get(route('technical.daily-reports.create', ['plan_item_id' => $own]))
            ->assertOk()->assertSee('Chưa tính KPI Tiến độ')->assertSee('Việc tự lập');
    }

    public function test_completed_warranty_without_complaint_review_stays_missing_and_views_do_not_confirm_it(): void
    {
        $engineer = $this->technician();
        $manager = $this->technicalManager();
        $id = $this->makeMaintenance($this->makeSite($engineer->id), $engineer->id, ['type' => 'incident',
            'assigned_to' => $engineer->id, 'status' => 'completed', 'customer_reported_at' => '2026-10-07 10:00:00',
            'first_responded_at' => '2026-10-07 10:05:00', 'execution_finished_at' => '2026-10-08 10:00:00',
            'complaint_status' => null, 'complaint_checked_at' => null, 'complaint_checked_by' => null]);
        $before = DB::table('solar_maintenance_schedules')->where('id', $id)->first();
        $this->actingAs($manager)->get(route('projects-unified.maintenance.show', $id))->assertOk()
            ->assertSee('data-kpi-warranty-status', false)->assertSee('chưa kiểm tra khiếu nại')->assertSee('KPI: Lưu kết quả');
        $this->assertEquals($before, DB::table('solar_maintenance_schedules')->where('id', $id)->first());
        $this->assertSame('missing', app(KpiActionHintService::class)->warranty($before)['state']);
        $before->complaint_status = 'none';
        $before->complaint_checked_by = $manager->id;
        $before->complaint_checked_at = '2026-10-08 10:01:00';
        $this->assertSame('pass', app(KpiActionHintService::class)->warranty($before)['state']);
        $before->first_responded_at = '2026-10-07 10:05:01';
        $this->assertSame('fail', app(KpiActionHintService::class)->warranty($before)['state']);
    }

    public function test_periodic_maintenance_does_not_promise_warranty_kpi_points(): void
    {
        $engineer = $this->technician();
        $id = $this->makeMaintenance($this->makeSite($engineer->id), $engineer->id);
        $this->actingAs($engineer)->get(route('projects-unified.maintenance.show', $id))->assertOk()
            ->assertDontSee('data-kpi-warranty-status', false)->assertDontSee('data-kpi-warranty-steps', false);
    }
}
