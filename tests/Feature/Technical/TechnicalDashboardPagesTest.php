<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Models\User;
use App\Services\Technical\TechnicalDashboardReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * BỐN TRANG ADMIN / GIÁM ĐỐC CỦA MODULE KỸ THUẬT (bổ sung 2026-09).
 *
 * Khoá lại đúng những gì người dùng đã báo lỗi sau nghiệm thu:
 *   1. Menu Admin phải có ĐÚNG 4 mục, đúng thứ tự, 4 URL khác nhau.
 *   2. "Báo cáo tuần/tháng" KHÔNG được render lại nội dung của "Tổng quan".
 *   3. Mỗi trang chỉ có MỘT mục menu active.
 *   4. Admin chỉ xem: không nút/form thao tác trong vùng nội dung.
 *   5. Nhân viên và trưởng phòng nhận 403 (không phải redirect) ở ba route mới.
 *   6. Mở trang KPIs không đụng payroll và không ghi dữ liệu nguồn.
 */
class TechnicalDashboardPagesTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /**
     * Bốn URL của bốn mục menu Admin.
     *
     * ĐỢT V5: mục "KPIs" trỏ về trang KPI CHÍNH THỨC `/ky-thuat/kpis`
     * (`ky-thuat.kpis.index`) thay cho trang "KPI tham khảo" cũ
     * `/ky-thuat/dashboard/kpis` — trang cũ nay chỉ còn chuyển hướng 302.
     */
    private const ADMIN_URLS = [
        'technical.dashboard' => '/ky-thuat/dashboard',
        'technical.dashboard.plans' => '/ky-thuat/dashboard/ke-hoach',
        'technical.daily-reports.index' => '/ky-thuat/bao-cao-ngay',
        'ky-thuat.kpis.index' => '/ky-thuat/kpis',
    ];

    /** Các route CHỈ Admin/Giám đốc mới được vào (Tổng quan đã có test riêng). */
    private const ADMIN_ONLY_ROUTES = [
        'technical.dashboard.plans',
        'technical.dashboard.reports',
        'technical.dashboard.kpis',
    ];

    /** Hai trang của Admin còn giữ view riêng (Báo cáo & KPIs dùng trang chung). */
    private const ADMIN_VIEW_ROUTES = [
        'technical.dashboard',
        'technical.dashboard.plans',
    ];

    /*
    |--------------------------------------------------------------------------
    | Trợ giúp
    |--------------------------------------------------------------------------
    */

    /** @return array<int, string> Nhãn các mục Kỹ thuật trong sidebar dự phòng. */
    private function fallbackMenuLabels(string $html): array
    {
        $start = strpos($html, 'id="menuKyThuat"');

        if ($start === false) {
            return [];
        }

        $end = strpos($html, '</ul>', $start);
        $segment = substr($html, $start, $end === false ? null : $end - $start);

        preg_match_all('/<a href="[^"]*"[^>]*>([^<]*)<\/a>/u', $segment, $matches);

        return array_values(array_filter(array_map('trim', $matches[1]), static fn (string $l): bool => $l !== ''));
    }

    /** @return array<int, string> Nhãn đang active trong nhóm Kỹ thuật (sidebar dự phòng). */
    private function activeFallbackLabels(string $html): array
    {
        $start = strpos($html, 'id="menuKyThuat"');

        if ($start === false) {
            return [];
        }

        $end = strpos($html, '</ul>', $start);
        $segment = substr($html, $start, $end === false ? null : $end - $start);

        preg_match_all('/<a href="[^"]*" class="ego-sublink active"[^>]*>([^<]*)<\/a>/u', $segment, $matches);

        return array_map('trim', $matches[1]);
    }

    private function contentBlock(string $html, string $marker): string
    {
        $start = strpos($html, 'TECHNICAL_DASHBOARD_'.$marker.'_CONTENT_START');
        $end = strpos($html, 'TECHNICAL_DASHBOARD_'.$marker.'_CONTENT_END');

        $this->assertNotFalse($start, 'Không tìm thấy mốc mở của vùng nội dung '.$marker.'.');
        $this->assertNotFalse($end, 'Không tìm thấy mốc đóng của vùng nội dung '.$marker.'.');

        return substr($html, $start, $end - $start);
    }

    private function adminHtml(User $admin, string $routeName, array $params = []): string
    {
        $response = $this->actingAs($admin)->get(route($routeName, $params));
        $response->assertOk();

        return $response->getContent();
    }

    /** Một nhân viên kỹ thuật có kế hoạch tuần này để các trang có dữ liệu thật. */
    private function seedTechnicianWithPlan(): User
    {
        $tech = $this->technician();
        $planId = $this->makeWeekPlan($tech, $this->currentWeekStart());

        $this->makePlanItem($tech, $planId, [
            'plan_date' => $this->currentWeekStart()->toDateString(),
            'status' => 'done',
            'site_id' => $this->makeSite(),
            'site_name' => 'Công trình kiểm thử',
        ]);

        return $tech;
    }

    /*
    |--------------------------------------------------------------------------
    | 1–3. Menu Admin: 4 mục, 4 URL riêng, không còn mục bản cũ
    |--------------------------------------------------------------------------
    */

    public function test_admin_menu_has_exactly_four_entries_in_order(): void
    {
        $html = $this->adminHtml($this->admin(), 'technical.dashboard');

        $this->assertSame(
            ['Tổng quan', 'Kế hoạch & Giao việc', 'Báo cáo ngày/tuần', 'KPIs'],
            $this->fallbackMenuLabels($html),
        );
    }

    public function test_admin_menu_hides_every_legacy_and_operational_entry(): void
    {
        $labels = $this->fallbackMenuLabels($this->adminHtml($this->admin(), 'technical.dashboard'));

        foreach ([
            'Kế hoạch (bản cũ)',
            'Báo cáo (bản cũ)',
            'Công việc của tôi',
            'Lịch công việc',
            'Quản lý kỹ thuật',
            'Giao việc',
            'KPI kỹ thuật',
        ] as $hidden) {
            $this->assertNotContains($hidden, $labels, 'Menu Kỹ thuật của Admin không được còn mục "'.$hidden.'".');
        }
    }

    public function test_the_four_menu_entries_point_to_four_distinct_urls(): void
    {
        $urls = [];

        foreach (self::ADMIN_URLS as $routeName => $path) {
            $url = route($routeName);
            $this->assertStringEndsWith($path, $url, 'Route '.$routeName.' không nằm ở URL đã thống nhất.');
            $urls[] = $url;
        }

        $this->assertSame($urls, array_values(array_unique($urls)), 'Bốn mục menu phải có bốn URL khác nhau.');
    }

    /**
     * Mục "KPIs" phải trỏ về trang KPI CHÍNH THỨC, còn hai màn hình BẢN CŨ
     * (`ky-thuat.ke-hoach`, `ky-thuat.bao-cao`) vẫn không được xuất hiện.
     *
     * (Trước V5 test này chặn cả `/ky-thuat/kpis`; nay `/ky-thuat/kpis` là
     * trang KPI chính thức nên liên kết tới nó là ĐÚNG, không còn là "bản cũ".)
     */
    public function test_admin_menu_links_the_official_kpi_page_and_no_legacy_screen(): void
    {
        $html = $this->adminHtml($this->admin(), 'technical.dashboard');
        $start = strpos($html, 'id="menuKyThuat"');
        $segment = substr($html, (int) $start, (int) strpos($html, '</ul>', (int) $start) - (int) $start);

        $this->assertStringContainsString('/ky-thuat/kpis', $segment);
        $this->assertStringNotContainsString('/ky-thuat/dashboard/kpis', $segment);
        $this->assertStringNotContainsString('/ky-thuat/ke-hoach"', $segment);
        $this->assertStringNotContainsString('/ky-thuat/bao-cao"', $segment);
        // 2026-09: Dashboard báo cáo cũ đã rời khỏi menu, thay bằng trang dùng chung.
        $this->assertStringNotContainsString('/ky-thuat/dashboard/bao-cao', $segment);
        $this->assertStringContainsString('/ky-thuat/bao-cao-ngay', $segment);
    }

    /*
    |--------------------------------------------------------------------------
    | 4–8. Mỗi trang có H1 / view / route riêng
    |--------------------------------------------------------------------------
    */

    public function test_each_page_renders_its_own_view_and_heading(): void
    {
        $admin = $this->admin();

        $expected = [
            'technical.dashboard' => ['technical.dashboard.index', 'Dashboard kết quả Kỹ thuật'],
            'technical.dashboard.plans' => ['technical.dashboard.plans', 'Kế hoạch & Giao việc Kỹ thuật'],
            'technical.daily-reports.index' => ['technical.daily-reports.index', 'Báo cáo Kỹ thuật'],
        ];

        foreach ($expected as $routeName => [$view, $heading]) {
            $response = $this->actingAs($admin)->get(route($routeName));

            $response->assertOk();
            $response->assertViewIs($view);
            $response->assertSee('<h1 class="tw-head__title">', false);
            $response->assertSee($heading);
        }
    }

    /** Lỗi gốc: bấm "Báo cáo" ra đúng nội dung của "Tổng quan". */
    public function test_report_page_is_not_a_copy_of_the_overview_page(): void
    {
        $admin = $this->admin();

        $overview = $this->actingAs($admin)->get(route('technical.dashboard'));
        $report = $this->actingAs($admin)->get(route('technical.daily-reports.index'));

        $overview->assertOk();
        $report->assertOk();

        $this->assertNotSame($overview->viewData('mode') !== null, false);
        $this->assertSame('technical.dashboard.index', $overview->original->name());
        $this->assertSame('technical.daily-reports.index', $report->original->name());

        $report->assertSee('Báo cáo Kỹ thuật');
        $report->assertDontSee('Dashboard kết quả Kỹ thuật');

        $overview->assertSee('Dashboard kết quả Kỹ thuật');
        $overview->assertDontSee('Báo cáo Kỹ thuật');
    }

    /**
     * KHÔNG còn HAI trang "tổng hợp tuần" song song: URL Dashboard báo cáo cũ
     * chỉ chuyển hướng MỘT CHIỀU sang tab "Tổng hợp tuần" của trang dùng chung.
     */
    public function test_legacy_dashboard_report_url_redirects_to_the_weekly_summary_tab(): void
    {
        $target = route('technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
        ]);

        $this->actingAs($this->admin())
            ->get(route('technical.dashboard.reports'))
            ->assertRedirect($target);

        // Trang đích KHÔNG chuyển hướng ngược => không có vòng lặp.
        $second = $this->actingAs($this->admin())->get($target);
        $second->assertOk();
        $this->assertFalse($second->isRedirect());

        foreach ([$this->technician(), $this->technicalManager(), $this->outsider()] as $user) {
            $response = $this->actingAs($user)->get(route('technical.dashboard.reports'));
            $response->assertForbidden();
            $this->assertFalse($response->isRedirect());
        }
    }

    public function test_each_page_marks_exactly_one_menu_entry_active(): void
    {
        $admin = $this->admin();
        $tech = $this->seedTechnicianWithPlan();

        $cases = [
            ['technical.dashboard', [], 'Tổng quan'],
            ['technical.dashboard.plans', [], 'Kế hoạch & Giao việc'],
            ['technical.dashboard.plans.detail', ['member' => $tech->id], 'Kế hoạch & Giao việc'],
            ['technical.daily-reports.index', [], 'Báo cáo ngày/tuần'],
            [
                'technical.daily-reports.index',
                ['tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY],
                'Báo cáo ngày/tuần',
            ],
            ['ky-thuat.kpis.index', [], 'KPIs'],
        ];

        foreach ($cases as [$routeName, $params, $expectedLabel]) {
            $active = $this->activeFallbackLabels($this->adminHtml($admin, $routeName, $params));

            $this->assertCount(1, $active, 'Trang '.$routeName.' phải có đúng MỘT mục Kỹ thuật active.');
            $this->assertSame($expectedLabel, $active[0], 'Trang '.$routeName.' active sai mục.');
        }
    }

    public function test_admin_pages_never_redirect(): void
    {
        $admin = $this->admin();

        foreach (array_keys(self::ADMIN_URLS) as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 10–12. Chỉ xem: không form/nút thao tác
    |--------------------------------------------------------------------------
    */

    public function test_plan_page_contains_no_write_form_or_action_button(): void
    {
        $this->seedTechnicianWithPlan();

        $block = $this->contentBlock(
            $this->adminHtml($this->admin(), 'technical.dashboard.plans'),
            'PLANS',
        );

        $this->assertReadOnlyBlock($block, 'Kế hoạch');
    }

    public function test_plan_detail_page_contains_no_write_form_or_action_button(): void
    {
        $tech = $this->seedTechnicianWithPlan();

        $block = $this->contentBlock(
            $this->adminHtml($this->admin(), 'technical.dashboard.plans.detail', ['member' => $tech->id]),
            'PLANS',
        );

        $this->assertReadOnlyBlock($block, 'Chi tiết kế hoạch');
    }

    /**
     * `/ky-thuat/dashboard/kpis` đã được thay bằng trang KPI chính thức:
     * Admin được chuyển hướng 302, người khác vẫn nhận 403 (không redirect).
     */
    public function test_legacy_reference_kpi_page_redirects_admin_to_the_official_kpi_page(): void
    {
        $this->actingAs($this->admin())
            ->get(route('technical.dashboard.kpis'))
            ->assertRedirect(route('ky-thuat.kpis.index'));

        $this->actingAs($this->admin())
            ->get(route('technical.dashboard.kpis.detail', ['member' => $this->technician()->id]))
            ->assertRedirect(route('ky-thuat.kpis.index'));

        foreach ([$this->technician(), $this->technicalManager(), $this->outsider()] as $user) {
            $response = $this->actingAs($user)->get(route('technical.dashboard.kpis'));
            $response->assertForbidden();
            $this->assertFalse($response->isRedirect());
        }
    }

    /**
     * Tab "Tổng hợp tuần" của trang Báo cáo dùng chung không ghi gì.
     *
     * (Thay cho test cũ trên Dashboard báo cáo — trang đó nay chỉ redirect.)
     */
    public function test_weekly_summary_tab_contains_no_write_form_or_action_button(): void
    {
        $this->seedTechnicianWithPlan();

        $html = $this->adminHtml($this->admin(), 'technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
        ]);

        $start = strpos($html, '<!-- TECHNICAL_WEEKLY_SUMMARY_START -->');
        $end = strpos($html, '<!-- TECHNICAL_WEEKLY_SUMMARY_END -->');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $this->assertReadOnlyBlock(substr($html, $start, $end - $start), 'Tổng hợp tuần');
    }

    private function assertReadOnlyBlock(string $block, string $page): void
    {
        foreach (['method="POST"', 'method="post"', 'name="_method"', '_token'] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $block,
                'Trang '.$page.' của Admin phải chỉ xem — không được chứa "'.$needle.'".',
            );
        }

        foreach (['Lưu điều chỉnh', 'Duyệt báo cáo', 'Xoá', 'Gửi yêu cầu'] as $label) {
            $this->assertStringNotContainsString(
                '>'.$label,
                $block,
                'Trang '.$page.' của Admin không được có nút "'.$label.'".',
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 13–14. Phân quyền: 403 chứ không redirect
    |--------------------------------------------------------------------------
    */

    public function test_technician_receives_403_on_every_admin_route(): void
    {
        $tech = $this->technician();

        foreach (self::ADMIN_ONLY_ROUTES as $routeName) {
            $response = $this->actingAs($tech)->get(route($routeName));
            $response->assertForbidden();
            $this->assertFalse($response->isRedirect(), $routeName.' phải trả 403, không được chuyển hướng.');
        }

        $this->actingAs($tech)->get(route('technical.dashboard.plans.detail', ['member' => $tech->id]))->assertForbidden();
        $this->actingAs($tech)->get(route('technical.dashboard.kpis.detail', ['member' => $tech->id]))->assertForbidden();
    }

    public function test_technical_manager_receives_403_on_every_admin_route(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();

        foreach (self::ADMIN_ONLY_ROUTES as $routeName) {
            $response = $this->actingAs($manager)->get(route($routeName));
            $response->assertForbidden();
            $this->assertFalse($response->isRedirect(), $routeName.' phải trả 403, không được chuyển hướng.');
        }

        $this->actingAs($manager)->get(route('technical.dashboard.plans.detail', ['member' => $tech->id]))->assertForbidden();
        $this->actingAs($manager)->get(route('technical.dashboard.kpis.detail', ['member' => $tech->id]))->assertForbidden();
    }

    public function test_outsider_receives_403_on_every_admin_route(): void
    {
        $outsider = $this->outsider();

        foreach (self::ADMIN_ONLY_ROUTES as $routeName) {
            $this->actingAs($outsider)->get(route($routeName))->assertForbidden();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 15–17. Số liệu: N/A, không ghi payroll, không ghi dữ liệu nguồn
    |--------------------------------------------------------------------------
    */

    /**
     * Mẫu số bằng 0 phải ra "N/A", không bao giờ ra "0%".
     *
     * (V5: hai helper `onTimeRate`/`hourRate` đi cùng trang "KPI tham khảo" đã
     * bị gỡ; quy ước N/A vẫn do `TechnicalPlanVsActualService::rateLabel` giữ.)
     */
    public function test_zero_denominator_rates_render_as_na(): void
    {
        $this->assertSame('N/A', \App\Services\Technical\TechnicalPlanVsActualService::rateLabel(null));
    }

    /** Mở trang KPI CHÍNH THỨC không được ghi thêm dòng vào bảng lương/KPI. */
    public function test_opening_the_kpi_page_never_writes_to_payroll_tables(): void
    {
        $this->seedTechnicianWithPlan();
        $admin = $this->admin();

        $tables = array_values(array_filter([
            'technical_kpi_payrolls',
            'technical_payroll_kpi_items',
            'technical_kpi_project_evidence',
        ], static fn (string $t): bool => Schema::hasTable($t)));

        $this->assertNotSame([], $tables, 'Không tìm thấy bảng KPI/payroll nào để đối chứng.');

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
        }

        $this->actingAs($admin)->get(route('ky-thuat.kpis.index'))->assertOk();
        $this->actingAs($admin)->get(route('ky-thuat.kpis.index', ['month' => Carbon::today()->format('Y-m')]))->assertOk();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), 'Trang KPIs đã ghi thêm dòng vào bảng '.$table.'.');
        }
    }

    /** Mở bốn trang chỉ xem không được tạo / sửa bất kỳ dữ liệu nguồn nào. */
    public function test_opening_the_admin_pages_never_writes_source_data(): void
    {
        $tech = $this->seedTechnicianWithPlan();
        $admin = $this->admin();

        $tables = [
            'technical_plan_items',
            'technical_week_plans',
            'technical_daily_reports',
            'technical_plan_histories',
            'project_workflow_steps',
            'project_workflow_assignments',
            'sites',
            'tasks',
            'solar_maintenance_schedules',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = DB::table($table)->count();
        }

        foreach (array_keys(self::ADMIN_URLS) as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }

        $this->actingAs($admin)->get(route('technical.dashboard.plans.detail', ['member' => $tech->id]))->assertOk();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), 'Trang chỉ xem đã ghi thêm dòng vào bảng '.$table.'.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bộ lọc, chuyển kỳ và company scope
    |--------------------------------------------------------------------------
    */

    public function test_plan_page_filters_by_member_and_plan_status(): void
    {
        $tech = $this->seedTechnicianWithPlan();
        $other = $this->technician();
        $admin = $this->admin();

        $filtered = $this->actingAs($admin)->get(route('technical.dashboard.plans', ['user_id' => $tech->id]));
        $filtered->assertOk();

        $rows = $filtered->viewData('rows');
        $this->assertCount(1, $rows);
        $this->assertSame((int) $tech->id, (int) $rows->first()['user_id']);

        // Lọc theo trạng thái "Chưa lập" phải loại người đã có kế hoạch.
        $none = $this->actingAs($admin)->get(route('technical.dashboard.plans', [
            'plan_status' => TechnicalDashboardReadService::PLAN_STATUS_NONE,
        ]));
        $none->assertOk();

        $ids = $none->viewData('rows')->pluck('user_id')->map(fn ($id): int => (int) $id)->all();
        $this->assertNotContains((int) $tech->id, $ids);
        $this->assertContains((int) $other->id, $ids);
    }

    /**
     * Tab "Tổng hợp tuần" chuyển tuần trước / hiện tại / sau đúng khoảng ngày.
     *
     * (Thay cho test cũ "chuyển kỳ tuần/tháng" của Dashboard báo cáo: trang mới
     *  làm việc theo TUẦN, `period=month` của URL cũ được ánh xạ về tuần chứa
     *  ngày đầu tháng — xem `TechnicalDashboardReportController::mapQuery()`.)
     */
    public function test_weekly_summary_tab_moves_between_weeks(): void
    {
        $admin = $this->admin();
        $thisWeek = $this->currentWeekStart();

        foreach ([-1, 0, 1] as $offset) {
            $week = $thisWeek->copy()->addWeeks($offset);

            $response = $this->actingAs($admin)->get(route('technical.daily-reports.index', [
                'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
                'week' => $week->toDateString(),
            ]));

            $response->assertOk();
            $this->assertSame($week->toDateString(), $response->viewData('from')->toDateString());
            $this->assertSame(6, (int) $response->viewData('from')->diffInDays($response->viewData('to')));
        }
    }

    /**
     * Số liệu tab "Tổng hợp tuần" lấy từ BÁO CÁO NGÀY THẬT — đối chiếu với một
     * truy vấn SELECT trực tiếp, không tin con số do view tự dựng.
     */
    public function test_weekly_summary_numbers_match_a_direct_select(): void
    {
        $tech = $this->seedTechnicianWithPlan();
        $weekStart = $this->currentWeekStart();

        $this->makeDailyReport($tech, [
            'report_date' => $weekStart->toDateString(),
            'status' => 'approved',
            'work_hours' => 3.5,
        ]);
        $this->makeDailyReport($tech, [
            'report_date' => $weekStart->copy()->addDay()->toDateString(),
            'status' => 'submitted',
            'work_hours' => 2,
        ]);

        $response = $this->actingAs($this->admin())->get(route('technical.daily-reports.index', [
            'tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY,
            'week' => $weekStart->toDateString(),
            'user_id' => $tech->id,
        ]));
        $response->assertOk();

        $expected = DB::table('technical_daily_reports')
            ->where('company_id', $this->companyId())
            ->where('user_id', $tech->id)
            ->whereNull('deleted_at')
            ->whereBetween('report_date', [
                $weekStart->toDateString(),
                $weekStart->copy()->addDays(6)->toDateString(),
            ])
            ->whereIn('status', ['submitted', 'approved'])
            ->get();

        $row = $response->viewData('rows')->firstWhere('user_id', (int) $tech->id);

        $this->assertNotNull($row, 'Nhân viên phải xuất hiện trong bảng tổng hợp tuần.');
        $this->assertSame($expected->count(), (int) $row['report_count']);
        $this->assertSame(
            (int) round((float) $expected->sum('work_hours') * 60),
            (int) $row['actual_minutes'],
        );

        $counts = $response->viewData('reportCounts');
        $this->assertSame($expected->count(), (int) $counts['finalized']);
    }

    public function test_plan_page_moves_to_the_previous_and_next_week(): void
    {
        $admin = $this->admin();
        $thisWeek = $this->currentWeekStart();

        $prev = $this->actingAs($admin)->get(route('technical.dashboard.plans', [
            'week' => $thisWeek->copy()->subWeek()->toDateString(),
        ]));
        $prev->assertOk();
        $this->assertSame($thisWeek->copy()->subWeek()->toDateString(), $prev->viewData('weekStart')->toDateString());

        $next = $this->actingAs($admin)->get(route('technical.dashboard.plans', [
            'week' => $thisWeek->copy()->addWeek()->toDateString(),
        ]));
        $next->assertOk();
        $this->assertSame($thisWeek->copy()->addWeek()->toDateString(), $next->viewData('weekStart')->toDateString());
    }

    /** Company scope: dữ liệu công ty khác không được lọt vào ba trang mới. */
    public function test_admin_pages_never_leak_data_from_another_company(): void
    {
        $tech = $this->technician();
        $otherCompanyId = $this->companyId() + 1000;

        $planId = $this->makeWeekPlan($tech, $this->currentWeekStart(), ['company_id' => $otherCompanyId]);
        $this->makePlanItem($tech, $planId, [
            'company_id' => $otherCompanyId,
            'plan_date' => $this->currentWeekStart()->toDateString(),
            'title' => 'VIEC CONG TY KHAC KHONG DUOC HIEN',
            'estimated_minutes' => 999,
        ]);

        $admin = $this->admin();

        $pages = [
            ['technical.dashboard.plans', []],
            ['technical.daily-reports.index', []],
            [
                'technical.daily-reports.index',
                ['tab' => \App\Http\Controllers\Technical\TechnicalDailyReportController::TAB_WEEKLY],
            ],
        ];

        foreach ($pages as [$routeName, $params]) {
            $response = $this->actingAs($admin)->get(route($routeName, $params));
            $response->assertOk();
            $response->assertDontSee('VIEC CONG TY KHAC KHONG DUOC HIEN');
        }

        // Dòng của công ty khác không được cộng vào số liệu của nhân viên này.
        $row = $this->actingAs($admin)
            ->get(route('technical.dashboard.plans', ['user_id' => $tech->id]))
            ->viewData('rows')
            ->first();

        $this->assertSame(0, (int) $row['item_count']);
        $this->assertSame(0, (int) $row['estimated_minutes']);
    }
}
