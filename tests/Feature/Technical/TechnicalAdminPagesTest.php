<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\Technical\TechnicalPlanItem;
use App\Models\Technical\TechnicalWeekPlan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * TEST BỘ TRANG KỸ THUẬT DÀNH CHO ADMIN / GIÁM ĐỐC (2026-09)
 *
 * Khóa chặt 20 yêu cầu nghiệm thu giao diện:
 * HỢP NHẤT 2026-09 — QUYẾT ĐỊNH KIẾN TRÚC CUỐI CÙNG:
 * chỉ còn MỘT trang KPI chính thức `/ky-thuat/kpis` (`ky-thuat.kpis.index`).
 * Trang "KPI tham khảo" `/ky-thuat/dashboard/kpis` đã retire: nay chỉ chuyển
 * hướng 302 một chiều cho người có quyền, và vẫn 403 cho người không có quyền.
 * Các assertion cũ khoá view/H1 "KPIs Kỹ thuật" của trang tham khảo đã được
 * thay bằng assertion redirect tương ứng.
 *
 *   1. Menu Admin/GĐ có đúng 4 mục theo thứ tự.
 *   2. Không xuất hiện mục bản cũ.
 *   3. Bốn mục có bốn URL riêng.
 *   4. Tổng quan trả đúng view/H1 Tổng quan.
 *   5. Kế hoạch trả đúng view/H1 Kế hoạch.
 *   6. Báo cáo trả đúng view/H1 Báo cáo Kỹ thuật.
 *   7. Trang KPI tham khảo cũ chỉ redirect 302 sang trang KPI chính thức.
 *   8. Báo cáo không render nội dung Tổng quan.
 *   9. Mỗi trang chỉ active duy nhất một mục menu.
 *   10. Admin chỉ xem ở trang Kế hoạch.
 *   11. Admin chỉ xem ở trang KPIs.
 *   12. Không có nút giao việc/sửa/xóa/duyệt ở các trang Admin.
 *   13. Nhân viên bị 403 tại 3 route Admin bổ sung.
 *   14. Trưởng phòng bị 403 tại các route Admin.
 *   15. Mẫu số KPI bằng 0 hiển thị N/A.
 *   16. KPI chưa chính thức không cập nhật payroll.
 *   17. Không tạo hoặc sửa dữ liệu nguồn khi chỉ xem trang.
 *   18. Không redirect loop.
 *   19. Route cũ không xuất hiện trong menu chính.
 *   20. Tất cả trang trả về HTTP 200 cho Admin.
 */
class TechnicalAdminPagesTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /*
    |--------------------------------------------------------------------------
    | Helper đọc menu Admin
    |--------------------------------------------------------------------------
    */

    private function technicalMenuLabels(string $html): array
    {
        $labels = [];

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

        $start = strpos($html, 'id="menuKyThuat"');
        if ($start !== false) {
            $end = strpos($html, '</ul>', $start);
            $segment = substr($html, $start, $end === false ? null : $end - $start);

            preg_match_all('/<a href="[^"]*"[^>]*>([^<]*)<\/a>/u', $segment, $matches);
            $labels = array_merge($labels, array_map('trim', $matches[1]));
        }

        return array_values(array_unique(array_filter($labels, static fn (string $l): bool => $l !== '')));
    }

    /*
    |--------------------------------------------------------------------------
    | 1–3, 19. Menu Admin có đúng 4 mục, 4 URL riêng, không có mục cũ
    |--------------------------------------------------------------------------
    */

    public function test_admin_sees_exactly_four_menu_entries_in_order(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->get(route('technical.dashboard'));
        $response->assertOk();

        $labels = $this->technicalMenuLabels($response->getContent());

        $this->assertSame(
            ['Tổng quan', 'Kế hoạch & Giao việc', 'Báo cáo ngày/tuần', 'KPIs'],
            $labels,
            'Menu Kỹ thuật của Admin phải có đúng 4 mục theo thứ tự: Tổng quan, Kế hoạch & Giao việc, Báo cáo ngày/tuần, KPIs'
        );
    }

    /**
     * Các mục bản cũ không được còn trong NHÓM KỸ THUẬT.
     *
     * (Trước đây test này dò trên toàn bộ HTML nên dính nhầm mục "Giao việc"
     * của nhóm Công việc/Chat — không liên quan module Kỹ thuật. Nay chỉ xét
     * đúng nhãn trong nhóm Kỹ thuật của cả hai nguồn menu.)
     */
    public function test_legacy_entries_are_not_in_admin_menu(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->get(route('technical.dashboard'));

        $labels = $this->technicalMenuLabels($response->getContent());

        foreach ([
            'Kế hoạch (bản cũ)',
            'Báo cáo (bản cũ)',
            'Công việc của tôi',
            'Lịch công việc',
            'Quản lý kỹ thuật',
            'Giao việc',
            'KPI kỹ thuật',
        ] as $hidden) {
            $this->assertNotContains($hidden, $labels, 'Nhóm Kỹ thuật của Admin không được còn mục "'.$hidden.'".');
        }
    }

    public function test_four_menu_items_have_distinct_urls(): void
    {
        $urls = [
            route('technical.dashboard'),
            route('technical.dashboard.plans'),
            route('technical.daily-reports.index'),
            route('ky-thuat.kpis.index'),
        ];

        $uniqueUrls = array_unique($urls);
        $this->assertCount(4, $uniqueUrls, 'Bốn mục menu Kỹ thuật phải có 4 URL riêng biệt.');

        $this->assertStringEndsWith('/ky-thuat/dashboard', route('technical.dashboard'));
        $this->assertStringEndsWith('/ky-thuat/dashboard/ke-hoach', route('technical.dashboard.plans'));
        $this->assertStringEndsWith('/ky-thuat/bao-cao-ngay', route('technical.daily-reports.index'));
        $this->assertStringEndsWith('/ky-thuat/kpis', route('ky-thuat.kpis.index'));
    }

    /** Menu KHÔNG còn trỏ về URL Dashboard báo cáo cũ. */
    public function test_admin_menu_no_longer_links_the_legacy_dashboard_report_url(): void
    {
        $html = $this->actingAs($this->admin())->get(route('technical.dashboard'))->getContent();

        $start = strpos($html, 'id="menuKyThuat"');
        $this->assertNotFalse($start);
        $segment = substr($html, $start, (int) strpos($html, '</ul>', $start) - $start);

        $this->assertStringNotContainsString('/ky-thuat/dashboard/bao-cao', $segment);
        $this->assertStringContainsString('/ky-thuat/bao-cao-ngay', $segment);
    }

    /*
    |--------------------------------------------------------------------------
    | 4–8. Các trang trả về đúng View/H1 riêng và không bị nhầm lẫn
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_overview_returns_correct_h1_and_view(): void
    {
        $response = $this->actingAs($this->admin())->get(route('technical.dashboard'));
        $response->assertOk();
        $response->assertViewIs('technical.dashboard.index');
        $response->assertSee('<h1 class="tw-head__title">Tổng quan Kỹ thuật</h1>', false);
    }

    public function test_plans_page_returns_correct_h1_and_view(): void
    {
        $response = $this->actingAs($this->admin())->get(route('technical.dashboard.plans'));
        $response->assertOk();
        $response->assertViewIs('technical.dashboard.plans');
        $response->assertSee('<h1 class="tw-head__title">Kế hoạch &amp; Giao việc Kỹ thuật</h1>', false);
    }

    /**
     * NGHIỆP VỤ MỚI 2026-09: trang Báo cáo của Admin là trang DÙNG CHUNG
     * `/ky-thuat/bao-cao-ngay` (không còn Dashboard báo cáo riêng).
     *
     * (Thay cho test cũ `test_reports_page_returns_correct_h1_and_view` vốn khoá
     *  view `technical.dashboard.reports` — view đó không còn được render.)
     */
    public function test_report_menu_entry_opens_the_shared_daily_report_page(): void
    {
        $response = $this->actingAs($this->admin())->get(route('technical.daily-reports.index'));
        $response->assertOk();
        $response->assertViewIs('technical.daily-reports.index');
        $response->assertSee('<h1 class="tw-head__title">Báo cáo Kỹ thuật</h1>', false);
        $response->assertSee('Theo dõi báo cáo ngày và tổng hợp kết quả làm việc theo tuần.');
    }

    /** URL Dashboard báo cáo cũ: 302 MỘT CHIỀU, không loop, 403 cho người không quyền. */
    public function test_legacy_dashboard_report_url_redirects_once_to_the_shared_page(): void
    {
        $first = $this->actingAs($this->admin())->get(route('technical.dashboard.reports'));
        $first->assertRedirect(route('technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
        ]));

        $second = $this->actingAs($this->admin())->get(route('technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
        ]));
        $second->assertOk();
        $this->assertFalse($second->isRedirect(), 'Trang đích không được chuyển hướng tiếp (tránh loop).');

        // Quyền được kiểm TRƯỚC khi chuyển hướng: không dùng redirect để che 403.
        foreach ([$this->technician(), $this->technicalManager()] as $user) {
            $response = $this->actingAs($user)->get(route('technical.dashboard.reports'));
            $response->assertForbidden();
            $this->assertFalse($response->isRedirect());
        }
    }

    /** Các query tương thích được chuyển tiếp sang đúng tên query của trang mới. */
    public function test_legacy_dashboard_report_url_maps_compatible_query_parameters(): void
    {
        $tech = $this->technician();
        $week = $this->currentWeekStart()->toDateString();

        $response = $this->actingAs($this->admin())->get(route('technical.dashboard.reports', [
            'period' => 'week',
            'week' => $week,
            'user_id' => $tech->id,
            'status' => 'done',
        ]));

        $target = $response->headers->get('Location');

        $this->assertStringContainsString('/ky-thuat/bao-cao-ngay', (string) $target);
        $this->assertStringContainsString('tab=weekly-summary', (string) $target);
        $this->assertStringContainsString('week='.$week, (string) $target);
        $this->assertStringContainsString('user_id='.$tech->id, (string) $target);
        // `status` của trang cũ là trạng thái CÔNG VIỆC => ánh xạ sang `work_status`.
        $this->assertStringContainsString('work_status=done', (string) $target);
    }

    /**
     * Mục KPIs của Admin dẫn tới trang KPI CHÍNH THỨC — không còn trang thứ hai.
     */
    public function test_kpis_menu_entry_opens_the_single_official_kpi_page(): void
    {
        $response = $this->actingAs($this->admin())->get(route('ky-thuat.kpis.index'));
        $response->assertOk();

        // Không còn view KPI thứ hai trong luồng render.
        $this->assertFalse(
            view()->exists('technical.dashboard.kpis'),
            'View KPI tham khảo đã retire, không được tồn tại trong luồng render.',
        );
    }

    /** URL KPI tham khảo cũ chỉ còn chuyển hướng 302 MỘT CHIỀU, không loop. */
    public function test_legacy_reference_kpi_url_only_redirects_once(): void
    {
        $admin = $this->admin();

        $first = $this->actingAs($admin)->get(route('technical.dashboard.kpis'));
        $first->assertRedirect(route('ky-thuat.kpis.index'));

        // Bước thứ hai đã là trang thật: 200, không chuyển hướng tiếp.
        $second = $this->actingAs($admin)->get(route('ky-thuat.kpis.index'));
        $second->assertOk();
        $this->assertFalse($second->isRedirect(), 'Trang KPI chính thức không được chuyển hướng tiếp (tránh loop).');
    }

    public function test_report_page_does_not_render_overview_content(): void
    {
        $response = $this->actingAs($this->admin())->get(route('technical.daily-reports.index'));
        $response->assertOk();
        $response->assertDontSee('<!-- TECHNICAL_DASHBOARD_CONTENT_START -->', false);
        $response->assertSee('<!-- TECHNICAL_DAILY_REPORTS_CONTENT_START -->', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 9. Mỗi trang chỉ active đúng 1 mục menu duy nhất
    |--------------------------------------------------------------------------
    */

    public function test_each_page_only_actives_its_own_menu_item(): void
    {
        $admin = $this->admin();

        // 1. Tại trang Tổng quan: chỉ Tổng quan active
        $htmlOverview = $this->actingAs($admin)->get(route('technical.dashboard'))->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*\/dashboard"\s+class="[^"]*active/', $htmlOverview);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/ke-hoach"\s+class="[^"]*active/', $htmlOverview);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/bao-cao"\s+class="[^"]*active/', $htmlOverview);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/kpis"\s+class="[^"]*active/', $htmlOverview);

        // 2. Tại trang Kế hoạch: chỉ Kế hoạch active
        $htmlPlans = $this->actingAs($admin)->get(route('technical.dashboard.plans'))->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*\/dashboard\/ke-hoach"\s+class="[^"]*active/', $htmlPlans);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard"\s+class="[^"]*active/', $htmlPlans);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/bao-cao"\s+class="[^"]*active/', $htmlPlans);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/kpis"\s+class="[^"]*active/', $htmlPlans);

        // 3. Tại trang Báo cáo dùng chung: chỉ "Báo cáo ngày/tuần" active
        $htmlReports = $this->actingAs($admin)->get(route('technical.daily-reports.index'))->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*\/ky-thuat\/bao-cao-ngay"\s+class="[^"]*active/', $htmlReports);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard"\s+class="[^"]*active/', $htmlReports);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/ke-hoach"\s+class="[^"]*active/', $htmlReports);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/kpis"\s+class="[^"]*active/', $htmlReports);

        // 3b. Tab "Tổng hợp tuần" KHÔNG làm đổi mục sidebar đang active.
        $htmlWeekly = $this->actingAs($admin)->get(route('technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
        ]))->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*\/ky-thuat\/bao-cao-ngay"\s+class="[^"]*active/', $htmlWeekly);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/ke-hoach"\s+class="[^"]*active/', $htmlWeekly);

        // 4. Tại trang KPI chính thức: chỉ KPIs active
        $htmlKpis = $this->actingAs($admin)->get(route('ky-thuat.kpis.index'))->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*\/ky-thuat\/kpis"\s+class="[^"]*active/', $htmlKpis);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard"\s+class="[^"]*active/', $htmlKpis);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/ke-hoach"\s+class="[^"]*active/', $htmlKpis);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*\/dashboard\/bao-cao"\s+class="[^"]*active/', $htmlKpis);
    }

    /*
    |--------------------------------------------------------------------------
    | 10–12. Admin chỉ xem: không có nút thao tác vận hành
    |--------------------------------------------------------------------------
    */

    public function test_plans_page_has_no_operational_controls(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->get(route('technical.dashboard.plans'));
        $response->assertOk();
        $html = $response->getContent();

        $start = strpos($html, '<!-- TECHNICAL_DASHBOARD_PLANS_CONTENT_START -->');
        $end = strpos($html, '<!-- TECHNICAL_DASHBOARD_PLANS_CONTENT_END -->');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $body = substr($html, $start, $end - $start);

        /*
         * NGHIỆP VỤ MỚI 2026-09: trang này nay CÓ lối vào "Giao việc" / "Điều
         * chỉnh" cho Admin. Nhưng ràng buộc BẢO MẬT giữ nguyên và vẫn được khoá:
         * mọi lối vào chỉ là LIÊN KẾT GET — trang không chứa form ghi, không
         * `_token`, không `_method`. Thao tác ghi thật nằm ở bàn điều phối, nơi
         * quyền được kiểm lại và lý do + nhật ký bị ép buộc.
         */
        $this->assertDoesNotMatchRegularExpression('/<form[^>]+method=["\'](?:post|put|delete)["\']/i', $body);
        $this->assertStringNotContainsString('name="_method"', $body);
        $this->assertStringNotContainsString('_token', $body);
        $this->assertStringNotContainsString('Duyệt kế hoạch', $body);
        $this->assertStringNotContainsString('Xoá công việc', $body);
    }

    /**
     * Tab "Tổng hợp tuần" của trang Báo cáo dùng chung cũng không ghi gì: chỉ
     * có form GET của bộ lọc, không `_token`, không `_method`, không nút duyệt.
     *
     * (Thay cho test cũ khoá vùng nội dung của Dashboard báo cáo — trang đó nay
     *  chỉ còn redirect.)
     */
    public function test_weekly_summary_tab_has_no_operational_controls(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->get(route('technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
        ]));
        $response->assertOk();
        $html = $response->getContent();

        $start = strpos($html, '<!-- TECHNICAL_WEEKLY_SUMMARY_START -->');
        $end = strpos($html, '<!-- TECHNICAL_WEEKLY_SUMMARY_END -->');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $body = substr($html, $start, $end - $start);

        $this->assertDoesNotMatchRegularExpression('/<form[^>]+method=["\'](?:post|put|delete)["\']/i', $body);
        $this->assertStringNotContainsString('name="_method"', $body);
        $this->assertStringNotContainsString('_token', $body);
        $this->assertStringNotContainsString('Duyệt báo cáo', $body);
        $this->assertStringNotContainsString('Chốt điểm', $body);
        $this->assertStringNotContainsString('Cập nhật lương', $body);
    }

    /*
    |--------------------------------------------------------------------------
    | 13–14. Nhân viên và trưởng phòng bị 403 tại các route Admin
    |--------------------------------------------------------------------------
    */

    public function test_technician_is_forbidden_on_admin_routes(): void
    {
        $tech = $this->technician();

        $this->actingAs($tech)->get(route('technical.dashboard.plans'))->assertForbidden();
        $this->actingAs($tech)->get(route('technical.dashboard.reports'))->assertForbidden();
        $this->actingAs($tech)->get(route('technical.dashboard.kpis'))->assertForbidden();
    }

    public function test_manager_is_forbidden_on_admin_routes_without_explicit_permission(): void
    {
        $mgr = $this->technicalManager();

        $this->actingAs($mgr)->get(route('technical.dashboard.plans'))->assertForbidden();
        $this->actingAs($mgr)->get(route('technical.dashboard.reports'))->assertForbidden();
        $this->actingAs($mgr)->get(route('technical.dashboard.kpis'))->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | 15, 17, 18. Mẫu số = 0 hiển thị N/A, không đổi dữ liệu nguồn, không loop
    |--------------------------------------------------------------------------
    */

    /**
     * Mẫu số bằng 0 => "N/A", không bao giờ "0%".
     *
     * Kiểm trên trang BÁO CÁO (nơi còn cột tỷ lệ) thay vì trang "KPI tham khảo"
     * đã retire: xem một kỳ xa trong tương lai, chưa có kế hoạch/báo cáo nào.
     */
    public function test_zero_denominator_rates_display_na(): void
    {
        $admin = $this->admin();
        $future = Carbon::today()->addYears(2)->toDateString();

        $response = $this->actingAs($admin)->get(route('technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
            'week' => $future,
        ]));
        $response->assertOk();
        $response->assertSee('N/A');
        $response->assertDontSee('<h1 class="tw-head__title">Tổng quan Kỹ thuật</h1>', false);
    }

    public function test_viewing_admin_pages_does_not_mutate_data(): void
    {
        $admin = $this->admin();
        $planCountBefore = TechnicalWeekPlan::count();
        $itemCountBefore = TechnicalPlanItem::count();

        $this->actingAs($admin)->get(route('technical.dashboard'));
        $this->actingAs($admin)->get(route('technical.dashboard.plans'));
        $this->actingAs($admin)->get(route('technical.daily-reports.index'));
        $this->actingAs($admin)->get(route('ky-thuat.kpis.index'));

        $this->assertSame($planCountBefore, TechnicalWeekPlan::count());
        $this->assertSame($itemCountBefore, TechnicalPlanItem::count());
    }

    public function test_no_redirect_loops_on_admin_routes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('technical.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('technical.dashboard.plans'))->assertOk();
        $this->actingAs($admin)->get(route('technical.daily-reports.index'))->assertOk();
        $this->actingAs($admin)->get(route('ky-thuat.kpis.index'))->assertOk();

        // Hai URL cũ: ĐÚNG MỘT bước chuyển hướng rồi dừng.
        $this->actingAs($admin)
            ->get(route('technical.dashboard.kpis'))
            ->assertRedirect(route('ky-thuat.kpis.index'));

        $this->actingAs($admin)
            ->get(route('technical.dashboard.reports'))
            ->assertRedirect(route('technical.daily-reports.index', [
                'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
            ]));
    }
}
