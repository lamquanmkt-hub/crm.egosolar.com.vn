<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalPlanItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ĐƠN GIẢN HOÁ MODULE KỸ THUẬT (2026-09).
 *
 * Khoá lại ba điều người dùng nhìn thấy đầu tiên:
 *   1. Menu Kỹ thuật của NHÂN VIÊN chỉ còn 3 mục: Tổng quan / Kế hoạch / Báo cáo.
 *   2. Không lối vào nào dẫn về "Kế hoạch (bản cũ)" / "Báo cáo (bản cũ)".
 *   3. Báo cáo KHÔNG bắt buộc phải có kế hoạch trước.
 *
 * Menu được kiểm trên DOM THẬT (render trang /ky-thuat) chứ không đọc config,
 * vì đúng chỗ này từng lệch: config V4 đã gọn nhưng nhánh dự phòng trong
 * `partials/sidebar.blade.php` vẫn in ra menu cũ.
 */
class TechnicalSimplifiedNavigationTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /*
    |--------------------------------------------------------------------------
    | Đọc menu Kỹ thuật từ DOM
    |--------------------------------------------------------------------------
    */

    /**
     * Nhãn các mục trong nhóm Kỹ thuật của sidebar, gộp từ CẢ HAI nguồn menu
     * đang tồn tại trong source (menu workspace V4 và nhánh dự phòng cũ).
     *
     * @return array<int, string>
     */
    private function technicalMenuLabels(string $html): array
    {
        $labels = [];

        /* Nguồn 1 — menu workspace V4 (partials/workspace-menu-excel-v3). */
        foreach (['KỸ THUẬT', 'QUẢN LÝ KỸ THUẬT', 'BÁO CÁO KỸ THUẬT'] as $sectionLabel) {
            $needle = '<li class="ego-excel-section ego-excel-item" data-ego-excel-menu="true"><span>'.$sectionLabel.'</span></li>';
            $start = strpos($html, $needle);

            if ($start === false) {
                continue;
            }

            $start += strlen($needle);
            $end = strpos($html, 'ego-excel-section', $start);
            $segment = substr($html, $start, $end === false ? null : $end - $start);

            preg_match_all('/<span class="ego-txt">([^<]*)<\/span>/u', $segment, $matches);
            $labels = array_merge($labels, array_map('trim', $matches[1]));
        }

        /* Nguồn 2 — nhánh dự phòng `#menuKyThuat` của sidebar cũ. */
        $start = strpos($html, 'id="menuKyThuat"');

        if ($start !== false) {
            $end = strpos($html, '</ul>', $start);
            $segment = substr($html, $start, $end === false ? null : $end - $start);

            preg_match_all('/<a href="[^"]*"[^>]*>([^<]*)<\/a>/u', $segment, $matches);
            $labels = array_merge($labels, array_map('trim', $matches[1]));
        }

        return array_values(array_unique(array_filter($labels, static fn (string $l): bool => $l !== '')));
    }

    private function sidebarHtml(\App\Models\User $user, string $url): string
    {
        $response = $this->actingAs($user)->get($url);

        if ($response->isRedirect()) {
            $response = $this->actingAs($user)->get($response->headers->get('Location'));
        }

        $response->assertOk();

        return $response->getContent();
    }

    /*
    |--------------------------------------------------------------------------
    | 1–3. Menu nhân viên
    |--------------------------------------------------------------------------
    */

    public function test_technician_sidebar_shows_exactly_three_technical_entries(): void
    {
        $labels = $this->technicalMenuLabels(
            $this->sidebarHtml($this->technician(), route('ky-thuat.tong-quan')),
        );

        $this->assertSame(['Tổng quan', 'Kế hoạch', 'Báo cáo'], $labels);
    }

    public function test_technician_sidebar_hides_the_legacy_and_secondary_entries(): void
    {
        $labels = $this->technicalMenuLabels(
            $this->sidebarHtml($this->technician(), route('ky-thuat.tong-quan')),
        );

        foreach ([
            'Kế hoạch (bản cũ)',
            'Báo cáo (bản cũ)',
            'KPIs',
            'Công việc của tôi',
            'Lịch công việc',
            'Quản lý kỹ thuật',
            'Đề xuất đổi hàng BH',
            'Công việc hôm nay',
            'Lịch sử báo cáo',
        ] as $hidden) {
            $this->assertNotContains($hidden, $labels, 'Menu Kỹ thuật của nhân viên không được còn mục "'.$hidden.'".');
        }
    }

    public function test_plan_menu_entry_points_to_the_v2_week_plan_route(): void
    {
        $html = $this->sidebarHtml($this->technician(), route('ky-thuat.tong-quan'));

        $this->assertStringContainsString(route('technical.week-plan.index'), $html);
        $this->assertStringContainsString('/ky-thuat/ke-hoach-tuan', $html);

        /* Không lối vào nào của luồng chính trỏ về trang kế hoạch bản cũ. */
        $this->assertStringNotContainsString('href="'.route('ky-thuat.ke-hoach').'"', $html);
        $this->assertStringNotContainsString('href="'.route('ky-thuat.bao-cao').'"', $html);
    }

    /*
    |--------------------------------------------------------------------------
    | 9. Menu trưởng phòng và Admin
    |--------------------------------------------------------------------------
    */

    public function test_manager_sidebar_shows_the_five_coordination_entries(): void
    {
        $labels = $this->technicalMenuLabels(
            $this->sidebarHtml($this->technicalManager(), route('ky-thuat.tong-quan')),
        );

        $this->assertSame(
            ['Tổng quan', 'Kế hoạch nhân viên', 'Giao việc', 'Báo cáo', 'Tổng kết tuần'],
            $labels,
        );
    }

    /**
     * CẬP NHẬT 2026-09 (đợt V6): Admin / Giám đốc có ĐÚNG BỐN mục, đúng thứ tự,
     * mỗi mục một URL riêng — với tên MỚI "Kế hoạch & Giao việc" và
     * "Báo cáo ngày/tuần" (mục Báo cáo nay trỏ `/ky-thuat/bao-cao-ngay`, dùng
     * chung với trưởng phòng và nhân viên). Assertion cũ
     * (`['Tổng quan', 'Kế hoạch', 'Báo cáo tuần/tháng', 'KPIs']`) khoá đúng đặc
     * tả đã bị nghiệp vụ mới thay thế — không phải nới lỏng bảo mật/phân quyền.
     */
    public function test_admin_sidebar_shows_the_four_entries(): void
    {
        $labels = $this->technicalMenuLabels(
            $this->sidebarHtml($this->admin(), route('technical.dashboard')),
        );

        $this->assertSame(
            ['Tổng quan', 'Kế hoạch & Giao việc', 'Báo cáo ngày/tuần', 'KPIs'],
            $labels,
        );
    }

    public function test_manager_can_still_open_the_management_pages(): void
    {
        $manager = $this->technicalManager();

        $this->actingAs($manager)->get(route('technical.manager.overview'))->assertOk();
        $this->actingAs($manager)->get(route('technical.manager.board'))->assertOk();
        $this->actingAs($manager)->get(route('technical.manager.weekly-summary'))->assertOk();
        $this->actingAs($manager)->get(route('technical.daily-reports.index'))->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 10–11. Phân quyền chéo + không vòng lặp chuyển hướng
    |--------------------------------------------------------------------------
    */

    public function test_technician_is_blocked_from_manager_and_dashboard_pages(): void
    {
        $tech = $this->technician();

        $this->actingAs($tech)->get(route('technical.manager.overview'))->assertForbidden();
        $this->actingAs($tech)->get(route('technical.manager.board'))->assertForbidden();
        $this->actingAs($tech)->get(route('technical.dashboard'))->assertForbidden();
    }

    public function test_technical_root_never_loops_for_the_three_roles(): void
    {
        foreach ([$this->technician(), $this->technicalManager(), $this->admin()] as $user) {
            $url = route('ky-thuat.tong-quan');
            $steps = 0;

            do {
                $response = $this->actingAs($user)->get($url);
                $steps++;

                if ($response->isRedirect()) {
                    $url = $response->headers->get('Location');
                }
            } while ($response->isRedirect() && $steps <= 3);

            $this->assertLessThanOrEqual(2, $steps, 'Chuỗi chuyển hướng từ /ky-thuat quá dài (có thể là vòng lặp).');
            $response->assertOk();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4–7. Báo cáo không bắt buộc có kế hoạch
    |--------------------------------------------------------------------------
    */

    public function test_technician_without_any_plan_can_open_the_unplanned_report_form(): void
    {
        $tech = $this->technician();

        $this->actingAs($tech)
            ->get(route('technical.daily-reports.create', ['mode' => 'phat-sinh']))
            ->assertOk()
            ->assertSee('Báo cáo việc phát sinh');
    }

    public function test_empty_screens_offer_both_actions_instead_of_a_dead_end(): void
    {
        $tech = $this->technician();
        $guide = 'Hôm nay chưa có công việc trong kế hoạch. Bạn vẫn có thể báo cáo việc phát sinh hoặc lập kế hoạch mới.';

        foreach ([
            route('technical.today'),
            route('ky-thuat.tong-quan'),
            route('technical.daily-reports.index'),
        ] as $url) {
            $response = $this->actingAs($tech)->get($url);
            $response->assertOk();
            $response->assertSee($guide);
            $response->assertSee('Báo cáo việc phát sinh');
            $response->assertSee('Lập kế hoạch');
            $response->assertSee('/ky-thuat/ke-hoach-tuan', false);
        }
    }

    public function test_unplanned_report_requires_a_reason(): void
    {
        $tech = $this->technician();
        $before = DB::table('technical_daily_reports')->count();

        $this->actingAs($tech)
            ->from(route('technical.daily-reports.create', ['mode' => 'phat-sinh']))
            ->post(route('technical.daily-reports.store'), [
                'report_date' => Carbon::today()->toDateString(),
                'content' => 'Xử lý sự cố mất điện đột xuất tại công trình.',
                'progress_percent' => 100,
                'is_unplanned' => 1,
            ])
            ->assertSessionHasErrors('unplanned_reason');

        $this->assertSame($before, DB::table('technical_daily_reports')->count());
    }

    /**
     * Báo cáo phát sinh KHÔNG tạo dòng kế hoạch giả và KHÔNG ghi vào dữ liệu
     * nguồn (Công trình / Task / Bảo trì) — đếm số dòng trước và sau.
     */
    public function test_unplanned_report_does_not_touch_plans_or_source_tables(): void
    {
        $tech = $this->technician();

        $counts = [];
        foreach ([
            'technical_plan_items',
            'technical_week_plans',
            'project_workflow_steps',
            'project_workflow_assignments',
            'sites',
            'tasks',
            'solar_maintenance_schedules',
        ] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), [
                'report_date' => Carbon::today()->toDateString(),
                'content' => 'Khách báo inverter kêu to, kiểm tra và siết lại đầu cốt.',
                'progress_percent' => 100,
                'work_hours' => 2,
                'is_unplanned' => 1,
                'unplanned_reason' => 'Khách hàng báo sự cố đột xuất ngoài kế hoạch tuần.',
            ])
            ->assertRedirect();

        foreach ($counts as $table => $before) {
            $this->assertSame($before, DB::table($table)->count(), 'Báo cáo phát sinh đã ghi thêm dòng vào bảng '.$table.'.');
        }

        $report = TechnicalDailyReport::query()->where('user_id', $tech->id)->latest('id')->first();

        $this->assertNotNull($report);
        $this->assertTrue((bool) $report->is_unplanned);
        $this->assertNull($report->plan_item_id);
        $this->assertNull($report->week_plan_id);
    }

    public function test_planned_report_still_links_to_the_plan_row(): void
    {
        $tech = $this->technician();
        $weekStart = $this->currentWeekStart();
        $planId = $this->makeWeekPlan($tech, $weekStart);
        $itemId = $this->makePlanItem($tech, $planId, ['plan_date' => Carbon::today()->toDateString()]);

        $this->actingAs($tech)
            ->post(route('technical.daily-reports.store'), [
                'report_date' => Carbon::today()->toDateString(),
                'plan_item_id' => $itemId,
                'content' => 'Đã hoàn thành đúng đầu việc trong kế hoạch tuần.',
                'progress_percent' => 100,
            ])
            ->assertRedirect();

        $report = TechnicalDailyReport::query()->where('user_id', $tech->id)->latest('id')->first();

        $this->assertNotNull($report);
        $this->assertSame($itemId, (int) $report->plan_item_id);
        $this->assertSame($planId, (int) $report->week_plan_id);
        $this->assertFalse((bool) $report->is_unplanned);
    }

    /*
    |--------------------------------------------------------------------------
    | Trang Kế hoạch: ba tab nội bộ, không thêm mục sidebar
    |--------------------------------------------------------------------------
    */

    public function test_week_plan_page_has_three_internal_tabs(): void
    {
        $tech = $this->technician();

        $response = $this->actingAs($tech)->get(route('technical.week-plan.index'));
        $response->assertOk();
        $response->assertSee('Kế hoạch tuần');
        $response->assertSee('Tuần này');
        $response->assertSee('Tuần trước');
        $response->assertSee('Lịch sử kế hoạch');

        $this->actingAs($tech)->get(route('technical.week-plan.index', ['tab' => 'prev']))->assertOk();

        $planId = $this->makeWeekPlan($tech, $this->currentWeekStart()->copy()->subWeek());
        $this->makePlanItem($tech, $planId, ['plan_date' => Carbon::today()->subWeek()->toDateString()]);

        $this->actingAs($tech)
            ->get(route('technical.week-plan.index', ['tab' => 'history']))
            ->assertOk()
            ->assertSee('Lịch sử kế hoạch của tôi');
    }

    /** Lịch sử kế hoạch chỉ hiện kế hoạch của CHÍNH nhân viên. */
    public function test_plan_history_is_scoped_to_the_owner(): void
    {
        $tech = $this->technician();
        $other = $this->technician();

        $this->makeWeekPlan($other, $this->currentWeekStart()->copy()->subWeeks(2));

        $response = $this->actingAs($tech)->get(route('technical.week-plan.index', ['tab' => 'history']));
        $response->assertOk();

        $history = $response->viewData('history');

        foreach ($history as $row) {
            $this->assertSame((int) $tech->id, (int) $row->plan->user_id);
        }
    }

    /** Báo cáo phát sinh được đánh dấu để thống kê, không hoá thành việc kế hoạch. */
    public function test_unplanned_report_is_flagged_for_statistics(): void
    {
        $tech = $this->technician();

        $this->actingAs($tech)->post(route('technical.daily-reports.store'), [
            'report_date' => Carbon::today()->toDateString(),
            'content' => 'Hỗ trợ đội thi công xử lý sự cố tủ điện.',
            'progress_percent' => 80,
            'is_unplanned' => 1,
            'unplanned_reason' => 'Việc phát sinh do sự cố tại hiện trường.',
        ])->assertRedirect();

        $this->assertSame(
            1,
            TechnicalDailyReport::query()
                ->where('user_id', $tech->id)
                ->where('is_unplanned', 1)
                ->count(),
        );

        $this->assertSame(
            0,
            TechnicalPlanItem::query()->where('user_id', $tech->id)->count(),
        );
    }
}
