<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalPlanItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Báo cáo ngày gắn với dòng kế hoạch tuần + công việc phát sinh.
 */
final class TechnicalPlanReportLinkTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /** @return array<string, mixed> */
    private function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'report_date' => Carbon::today()->toDateString(),
            'content' => 'Đã lắp đặt xong 10 tấm pin theo kế hoạch.',
            'progress_percent' => 80,
            'work_hours' => 6,
        ], $overrides);
    }

    /** Báo cáo gắn ĐÚNG dòng kế hoạch và kế thừa liên kết nguồn. */
    public function test_report_links_to_the_selected_plan_item(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);

        $itemId = $this->makePlanItem($tech, $planId, [
            'plan_date' => Carbon::today()->toDateString(),
            'title' => 'Lắp đặt inverter',
            'site_id' => $siteId,
            'site_name' => 'Công trình liên kết',
        ]);

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->reportPayload([
                'plan_item_id' => $itemId,
                'result_achieved' => 'Hoàn thành 8/10 tấm.',
                'submit' => 1,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $report = TechnicalDailyReport::where('user_id', $tech->id)->firstOrFail();

        $this->assertSame($itemId, (int) $report->plan_item_id);
        $this->assertSame($planId, (int) $report->week_plan_id);
        $this->assertSame($siteId, (int) $report->site_id);
        $this->assertSame('Lắp đặt inverter', $report->work_title);
        $this->assertSame('Hoàn thành 8/10 tấm.', $report->result_achieved);
        $this->assertFalse((bool) $report->is_unplanned);
        $this->assertTrue($report->isFinalized(), 'Báo cáo "hoàn tất" phải được tính là đã nộp.');
    }

    /** Không thể báo cáo lên dòng kế hoạch của người khác. */
    public function test_cannot_report_on_someone_elses_plan_item(): void
    {
        $mine = $this->technician();
        $other = $this->technician();
        $weekStart = $this->currentWeekStart();

        $otherPlan = $this->makeWeekPlan($other, $weekStart);
        $itemId = $this->makePlanItem($other, $otherPlan, ['plan_date' => Carbon::today()->toDateString()]);

        $this->actingAs($mine)
            ->post(route('technical.daily-reports.store'), $this->reportPayload(['plan_item_id' => $itemId]))
            ->assertForbidden();

        $this->assertSame(0, TechnicalDailyReport::where('user_id', $mine->id)->count());
    }

    /** Việc phát sinh BẮT BUỘC có lý do phát sinh. */
    public function test_unplanned_report_requires_a_reason(): void
    {
        $tech = $this->technician();

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->reportPayload(['is_unplanned' => 1]))
            ->assertSessionHasErrors('unplanned_reason');

        $this->assertSame(0, TechnicalDailyReport::where('user_id', $tech->id)->count());

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->reportPayload([
                'is_unplanned' => 1,
                'unplanned_reason' => 'Khách hàng báo sự cố mất điện đột xuất.',
                'submit' => 1,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $report = TechnicalDailyReport::where('user_id', $tech->id)->firstOrFail();

        $this->assertTrue((bool) $report->is_unplanned);
        $this->assertSame('Khách hàng báo sự cố mất điện đột xuất.', $report->unplanned_reason);
        $this->assertNull($report->plan_item_id, 'Việc phát sinh KHÔNG được gán vào dòng kế hoạch tuần.');
    }

    /** Chọn dòng kế hoạch thì báo cáo không còn được coi là việc phát sinh. */
    public function test_a_planned_item_report_is_never_marked_unplanned(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);
        $itemId = $this->makePlanItem($tech, $planId, ['plan_date' => Carbon::today()->toDateString()]);

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), $this->reportPayload([
                'plan_item_id' => $itemId,
                'is_unplanned' => 1,
                'unplanned_reason' => 'Cố tình đánh dấu sai',
            ]))
            ->assertRedirect();

        $report = TechnicalDailyReport::where('user_id', $tech->id)->firstOrFail();

        $this->assertFalse((bool) $report->is_unplanned);
        $this->assertNull($report->unplanned_reason);
        $this->assertSame($itemId, (int) $report->plan_item_id);
    }

    /** Xoá dòng kế hoạch KHÔNG xoá báo cáo đã gắn. */
    public function test_deleting_a_plan_item_never_deletes_its_report(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);
        $itemId = $this->makePlanItem($tech, $planId, ['plan_date' => Carbon::today()->toDateString()]);

        $reportId = $this->makeDailyReport($tech, [
            'plan_item_id' => $itemId,
            'week_plan_id' => $planId,
        ]);

        // Xoá cứng ở tầng DB để kiểm chứng ràng buộc khoá ngoại nullOnDelete.
        TechnicalPlanItem::findOrFail($itemId)->forceDelete();

        $this->assertDatabaseHas('technical_daily_reports', ['id' => $reportId]);
        $this->assertNull(TechnicalDailyReport::findOrFail($reportId)->plan_item_id);
    }

    /** Trang tạo báo cáo gợi ý đúng các việc trong kế hoạch của ngày. */
    public function test_create_form_lists_the_plan_items_of_the_day(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);

        $this->makePlanItem($tech, $planId, [
            'plan_date' => Carbon::today()->toDateString(),
            'title' => 'VIEC TRONG KE HOACH HOM NAY',
        ]);

        $this->actingAs($tech)
            ->get(route('technical.daily-reports.create'))
            ->assertOk()
            ->assertSee('VIEC TRONG KE HOACH HOM NAY');
    }

    /** Ánh xạ trạng thái sang ngôn ngữ nghiệp vụ mới. */
    public function test_business_status_mapping(): void
    {
        $this->assertSame('Nháp', TechnicalDailyReport::BUSINESS_STATUS_LABELS[TechnicalDailyReport::STATUS_DRAFT]);
        $this->assertSame('Hoàn tất (đã nộp)', TechnicalDailyReport::BUSINESS_STATUS_LABELS[TechnicalDailyReport::STATUS_SUBMITTED]);
        $this->assertSame('Hoàn tất (đã nộp)', TechnicalDailyReport::BUSINESS_STATUS_LABELS[TechnicalDailyReport::STATUS_APPROVED]);
        $this->assertSame('Cần cập nhật lại', TechnicalDailyReport::BUSINESS_STATUS_LABELS[TechnicalDailyReport::STATUS_REVISION]);

        $this->assertSame(
            [TechnicalDailyReport::STATUS_SUBMITTED, TechnicalDailyReport::STATUS_APPROVED],
            TechnicalDailyReport::FINALIZED_STATUSES,
        );
    }
}
