<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalPlanItem;
use App\Services\Technical\TechnicalPlanVsActualService;
use App\Services\Technical\TechnicalTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Dashboard kết quả Kỹ thuật (Admin / Giám đốc) + service so sánh kế hoạch ↔
 * kết quả.
 */
final class TechnicalDashboardTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /* ------------------------------------------------------------- Quyền */

    public function test_admin_can_open_the_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('technical.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard kết quả Kỹ thuật');
    }

    public function test_plain_technician_cannot_open_the_dashboard(): void
    {
        $this->actingAs($this->technician())
            ->get(route('technical.dashboard'))
            ->assertForbidden();

        $this->actingAs($this->outsider())
            ->get(route('technical.dashboard'))
            ->assertForbidden();
    }

    /** `/ky-thuat` đưa Admin thẳng về Dashboard (chế độ chỉ xem). */
    public function test_technical_root_redirects_admin_to_the_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get(route('ky-thuat.tong-quan'))
            ->assertRedirect(route('technical.dashboard'));
    }

    /** `/ky-thuat` đưa trưởng phòng về Tổng quan phòng. */
    public function test_technical_root_redirects_manager_to_team_overview(): void
    {
        $this->actingAs($this->technicalManager())
            ->get(route('ky-thuat.tong-quan'))
            ->assertRedirect(route('technical.manager.overview'));
    }

    /**
     * Dashboard Admin là trang CHỈ XEM: không có form thao tác vận hành,
     * không có nút giao việc / sửa kế hoạch / duyệt báo cáo / can thiệp tiến độ.
     */
    public function test_admin_dashboard_has_no_operational_controls(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        $planId = $this->makeWeekPlan($tech, $weekStart);
        $this->makePlanItem($tech, $planId, ['plan_date' => $weekStart->toDateString()]);

        $html = $this->actingAs($admin)
            ->get(route('technical.dashboard', ['week' => $weekStart->toDateString()]))
            ->assertOk()
            ->getContent();

        /*
         * Chỉ xét VÙNG NỘI DUNG của Dashboard (giữa hai mốc trong view), không
         * xét layout dùng chung — sidebar và thanh tài khoản có nút của module
         * khác, không thuộc phạm vi khẳng định này.
         */
        $start = strpos($html, 'TECHNICAL_DASHBOARD_CONTENT_START');
        $end = strpos($html, 'TECHNICAL_DASHBOARD_CONTENT_END');

        $this->assertNotFalse($start, 'Thiếu mốc bắt đầu vùng nội dung Dashboard.');
        $this->assertNotFalse($end, 'Thiếu mốc kết thúc vùng nội dung Dashboard.');

        $body = substr($html, $start, $end - $start);

        /*
         * Layout dùng chung có form POST riêng của nó (đăng xuất, đổi công ty…),
         * nên chỉ xét các form TRỎ VÀO module Kỹ thuật: không được có form nào
         * ngoài GET. Bộ lọc của Dashboard là form GET.
         */
        preg_match_all('/<form\b[^>]*>/i', $body, $matches);

        foreach ($matches[0] as $formTag) {
            $this->assertMatchesRegularExpression(
                '/method\s*=\s*"GET"/i',
                $formTag,
                'Dashboard Admin chỉ được phép có form GET (bộ lọc): '.$formTag,
            );
        }

        $this->assertStringNotContainsString('name="_method"', $body);
        $this->assertStringNotContainsString('csrf-token', $body);

        // Không có URL của bất kỳ route thao tác nào.
        foreach ([
            '/ky-thuat/quan-ly/ke-hoach/',
            '/giao-viec',
            '/yeu-cau-cap-nhat',
            '/ky-thuat/ke-hoach-tuan/viec',
            '/ky-thuat/ke-hoach-tuan/hoan-tat',
            '/duyet',
            '/yeu-cau-sua',
            '/mo-lai',
        ] as $fragment) {
            $this->assertStringNotContainsString(
                $fragment,
                $body,
                "Dashboard Admin không được chứa lối thao tác: {$fragment}",
            );
        }

        // Không có nhãn của nút thao tác vận hành.
        foreach (['Giao việc', 'Duyệt báo cáo', 'Điều chỉnh kế hoạch', 'Hoàn tất kế hoạch'] as $label) {
            $this->assertStringNotContainsString($label, $body, "Dashboard Admin không được có nút \"{$label}\".");
        }
    }

    /**
     * Rút gọn 2026-09: hàng thẻ đầu CHỈ còn 6 thẻ.
     *
     * Bốn số liệu cũ không bị mất — chúng chuyển xuống khối "Chi tiết"
     * (`detailCards`), nên test kiểm cả hai nơi để không hụt số liệu.
     */
    public function test_dashboard_exposes_exactly_six_summary_cards(): void
    {
        $response = $this->actingAs($this->admin())->get(route('technical.dashboard'));
        $response->assertOk();

        $cards = $response->viewData('cards');
        $this->assertCount(6, $cards);

        $labels = array_column($cards, 'label');

        foreach ([
            'Tổng nhân viên kỹ thuật',
            'Đã lập kế hoạch',
            'Công việc hoàn thành',
            'Công việc quá hạn',
            'Báo cáo đã nộp',
            'Báo cáo chưa nộp',
        ] as $label) {
            $this->assertContains($label, $labels);
        }

        $detailLabels = array_column($response->viewData('detailCards'), 'label');

        foreach ([
            'Chưa lập kế hoạch',
            'Tổng công việc đã lên kế hoạch',
            'Công việc chưa hoàn thành',
            'Công việc phát sinh',
        ] as $label) {
            $this->assertContains($label, $detailLabels, 'Số liệu "'.$label.'" phải còn ở khối Chi tiết.');
        }
    }

    public function test_dashboard_supports_month_mode(): void
    {
        $response = $this->actingAs($this->admin())
            ->get(route('technical.dashboard', ['mode' => 'month']));

        $response->assertOk();
        $this->assertSame('month', $response->viewData('mode'));
    }

    /** Admin/Giám đốc, quản lý chung và trưởng phòng không được tính là nhân viên kỹ thuật. */
    public function test_technical_team_only_contains_active_technical_staff(): void
    {
        $technician = $this->technician();
        $inactiveTechnician = $this->technician(['is_active' => false]);
        $manager = $this->technicalManager();
        $admin = $this->admin();
        $outsider = $this->outsider();

        /** @var TechnicalTeamService $team */
        $team = app(TechnicalTeamService::class);
        $ids = $team->memberIds();

        $this->assertContains((int) $technician->id, $ids);
        $this->assertNotContains((int) $inactiveTechnician->id, $ids);
        $this->assertNotContains((int) $manager->id, $ids);
        $this->assertNotContains((int) $admin->id, $ids);
        $this->assertNotContains((int) $outsider->id, $ids);
    }

    /* ------------------------------------------- So sánh kế hoạch ↔ kết quả */

    /**
     * Số liệu tổng hợp phải khớp CHÍNH XÁC với dữ liệu dựng sẵn.
     *
     * Dựng cho một nhân viên, trong tuần hiện tại, các dòng kế hoạch:
     *   1 done (không dời)    -> done, on_time
     *   1 done (đã dời ngày)  -> done, late
     *   1 not_done            -> not_done
     *   1 moved               -> moved
     *   1 planned (hôm qua)   -> open + overdue
     *   1 cancelled           -> KHÔNG tính vào mẫu số
     * và 1 báo cáo phát sinh (is_unplanned).
     */
    public function test_plan_vs_actual_numbers_are_exact(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);

        $yesterday = \Illuminate\Support\Carbon::today()->subDay();
        $inWeek = $yesterday->greaterThanOrEqualTo($weekStart)
            ? $yesterday
            : $weekStart->copy();

        $doneId = $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->toDateString(),
            'status' => TechnicalPlanItem::STATUS_DONE,
            'estimated_minutes' => 120,
        ]);

        $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->copy()->addDay()->toDateString(),
            'status' => TechnicalPlanItem::STATUS_DONE,
            'moved_from_date' => $weekStart->toDateString(),
            'estimated_minutes' => 60,
        ]);

        $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->copy()->addDays(2)->toDateString(),
            'status' => TechnicalPlanItem::STATUS_NOT_DONE,
            'estimated_minutes' => 60,
        ]);

        $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->copy()->addDays(3)->toDateString(),
            'status' => TechnicalPlanItem::STATUS_MOVED,
            'estimated_minutes' => 60,
        ]);

        $this->makePlanItem($tech, $planId, [
            'plan_date' => $inWeek->toDateString(),
            'status' => TechnicalPlanItem::STATUS_PLANNED,
            'estimated_minutes' => 60,
        ]);

        $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->copy()->addDays(4)->toDateString(),
            'status' => TechnicalPlanItem::STATUS_CANCELLED,
            'estimated_minutes' => 600,
        ]);

        // Một báo cáo HOÀN TẤT gắn đúng dòng kế hoạch đã xong.
        $this->makeDailyReport($tech, [
            'plan_item_id' => $doneId,
            'week_plan_id' => $planId,
            'report_date' => $weekStart->toDateString(),
            'status' => TechnicalDailyReport::STATUS_SUBMITTED,
            'work_hours' => 2.5,
        ]);

        // Một báo cáo PHÁT SINH (không gắn kế hoạch).
        $this->makeDailyReport($tech, [
            'plan_item_id' => null,
            'report_date' => $weekStart->toDateString(),
            'status' => TechnicalDailyReport::STATUS_SUBMITTED,
            'is_unplanned' => 1,
            'unplanned_reason' => 'Khách báo sự cố đột xuất',
            'work_hours' => 1.5,
        ]);

        /** @var TechnicalPlanVsActualService $stats */
        $stats = app(TechnicalPlanVsActualService::class);
        $summary = $stats->summary($weekStart, $weekStart->copy()->addDays(6), ['user_id' => (int) $tech->id]);

        $this->assertSame(5, $summary['planned_items'], 'Việc đã huỷ phải bị loại khỏi mẫu số.');
        $this->assertSame(2, $summary['done_items']);
        $this->assertSame(1, $summary['on_time_items']);
        $this->assertSame(1, $summary['late_items']);
        $this->assertSame(1, $summary['not_done_items']);
        $this->assertSame(1, $summary['moved_items']);
        $this->assertSame(1, $summary['open_items']);
        $this->assertSame(1, $summary['unplanned_items']);
        $this->assertSame(360, $summary['estimated_minutes'], '120+60+60+60+60, không tính việc đã huỷ.');
        $this->assertSame(240, $summary['actual_minutes'], '(2.5 + 1.5) giờ = 240 phút.');
        $this->assertSame(40.0, $summary['completion_rate'], '2/5 = 40%.');
    }

    /** Mẫu số 0 phải trả về null (N/A), tuyệt đối không phải 0%. */
    public function test_rates_are_null_when_there_is_nothing_to_measure(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();

        /** @var TechnicalPlanVsActualService $stats */
        $stats = app(TechnicalPlanVsActualService::class);
        $summary = $stats->summary($weekStart, $weekStart->copy()->addDays(6), ['user_id' => (int) $tech->id]);

        $this->assertNull($summary['completion_rate']);
        $this->assertNull($summary['report_rate']);
        $this->assertSame('N/A', TechnicalPlanVsActualService::rateLabel($summary['completion_rate']));
        $this->assertSame('50%', TechnicalPlanVsActualService::rateLabel(50.0));
    }

    /** Dashboard không rò dữ liệu của công ty khác. */
    public function test_dashboard_never_counts_another_company(): void
    {
        $admin = $this->admin();
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);

        $this->makePlanItem($tech, $planId, ['plan_date' => $weekStart->toDateString()]);
        $this->makePlanItem($tech, $planId, [
            'plan_date' => $weekStart->copy()->addDay()->toDateString(),
            'company_id' => $this->companyId() + 9_999,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('technical.dashboard', ['user_id' => $tech->id, 'date' => $weekStart->toDateString()]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('summary')['planned_items']);
    }
}
