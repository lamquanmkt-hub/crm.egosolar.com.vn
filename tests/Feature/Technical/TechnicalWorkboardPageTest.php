<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Các trang của bàn làm việc Kỹ thuật: phạm vi dữ liệu, phân quyền, hiệu năng.
 */
final class TechnicalWorkboardPageTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    /** Route mới không được trùng tên với bất kỳ route cũ nào. */
    public function test_new_technical_routes_exist_and_do_not_collide(): void
    {
        foreach ([
            'technical.work.my',
            'technical.work.calendar',
            'technical.work.management',
            'technical.daily-reports.index',
            'technical.daily-reports.create',
            'technical.daily-reports.store',
            'technical.daily-reports.show',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Thiếu route mới: {$name}");
        }

        // Các route Kỹ thuật cũ vẫn phải còn nguyên.
        foreach ([
            'ky-thuat.tong-quan',
            'ky-thuat.ke-hoach',
            'ky-thuat.bao-cao',
            'ky-thuat.kpis.index',
            'ky-thuat.warranty-exchange.index',
            'ky-thuat.luong.index',
        ] as $name) {
            $this->assertTrue(Route::has($name), "Route Kỹ thuật cũ bị mất: {$name}");
        }

        // URI của báo cáo ngày mới KHÔNG được đụng URI báo cáo cũ.
        $this->assertSame('ky-thuat/bao-cao', Route::getRoutes()->getByName('ky-thuat.bao-cao')->uri());
        $this->assertSame('ky-thuat/bao-cao-ngay', Route::getRoutes()->getByName('technical.daily-reports.index')->uri());

        // Không tự sinh thêm tên route trùng nào ngoài cặp ky-thuat.luong.*
        // vốn đã trùng từ trước (thuộc phạm vi giai đoạn khác, không sửa ở đây).
        $counts = [];
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if ($name !== null) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        $duplicates = array_keys(array_filter($counts, static fn (int $c): bool => $c > 1));
        sort($duplicates);

        $this->assertSame(['ky-thuat.luong.store', 'ky-thuat.luong.update'], $duplicates);
    }

    public function test_guest_is_redirected_from_technical_workboard(): void
    {
        $this->get(route('technical.work.my'))->assertRedirect();
    }

    /** Người ngoài phòng Kỹ thuật không vào được bàn làm việc. */
    public function test_non_technical_user_is_forbidden(): void
    {
        $this->actingAs($this->outsider())
            ->get(route('technical.work.my'))
            ->assertForbidden();
    }

    /** Kỹ thuật viên CHỈ thấy việc được giao cho mình, kể cả khi đổi user_id trên URL. */
    public function test_technician_only_sees_own_work_even_when_forcing_user_id(): void
    {
        $mine = $this->technician(['name' => 'Ky Thuat Cua Toi']);
        $other = $this->technician(['name' => 'Nguoi Khac Hoan Toan']);
        $siteId = $this->makeSite();

        $this->makeTask($siteId, (int) $mine->id, ['title' => 'VIEC CUA TOI']);
        $this->makeTask($siteId, (int) $other->id, ['title' => 'VIEC NGUOI KHAC']);

        $response = $this->actingAs($mine)
            ->get(route('technical.work.my', ['user_id' => $other->id]));

        $response->assertOk()
            ->assertSee('VIEC CUA TOI')
            ->assertDontSee('VIEC NGUOI KHAC');
    }

    /** Admin (Giám đốc) thấy toàn bộ trong company scope — nhưng không thấy công ty khác. */
    public function test_admin_sees_every_item_inside_company_scope_only(): void
    {
        $admin = $this->admin();
        $techA = $this->technician();
        $techB = $this->technician();

        $ourSite = $this->makeSite();
        $foreignSite = $this->makeSite($this->companyId() + 9_999);

        $this->makeTask($ourSite, (int) $techA->id, ['title' => 'VIEC CONG TY A']);
        $this->makeTask($ourSite, (int) $techB->id, ['title' => 'VIEC CONG TY B']);
        $this->makeTask($foreignSite, (int) $techA->id, [
            'title' => 'VIEC CONG TY KHAC',
            'company_id' => $this->companyId() + 9_999,
        ]);

        $this->actingAs($admin)
            ->get(route('technical.work.my'))
            ->assertOk()
            ->assertSee('VIEC CONG TY A')
            ->assertSee('VIEC CONG TY B')
            ->assertDontSee('VIEC CONG TY KHAC');
    }

    /** Trang tổng quan hiển thị SỐ THẬT, không còn mặc định 0. */
    public function test_overview_shows_real_counts_from_the_three_sources(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();

        $this->makeWorkflowAssignment($siteId, (int) $tech->id);
        $this->makeTask($siteId, (int) $tech->id);
        $this->makeMaintenance($siteId, (int) $tech->id);

        $response = $this->actingAs($tech)->get(route('ky-thuat.tong-quan'));

        $response->assertOk();

        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['total_items']);
        $this->assertSame(1, $summary['active_sites']);

        /*
         * Đơn giản hoá 2026-09: khối chữ "KPI tạm tính" đã được gỡ khỏi Tổng
         * quan nhân viên (trang chỉ còn 4 chỉ số + danh sách ưu tiên + 2 nút).
         * Khẳng định gốc — KHÔNG hiển thị điểm KPI giả — được giữ nguyên bằng
         * cách kiểm ngược: trang không in ra nhãn điểm KPI nào.
         */
        $response->assertDontSee('Điểm KPI');
        $response->assertSee('Mở kế hoạch');
        $response->assertSee('Viết báo cáo');
    }

    /** Trang "Quản lý kỹ thuật" chỉ dành cho người có quyền quản lý. */
    public function test_management_page_is_restricted_to_managers(): void
    {
        $this->actingAs($this->technician())
            ->get(route('technical.work.management'))
            ->assertForbidden();

        $this->actingAs($this->technicalManager())
            ->get(route('technical.work.management'))
            ->assertOk();

        $this->actingAs($this->admin())
            ->get(route('technical.work.management'))
            ->assertOk();
    }

    public function test_calendar_renders_week_and_month_from_the_same_feed(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();
        $this->makeMaintenance($siteId, (int) $tech->id);

        $week = $this->actingAs($tech)->get(route('technical.work.calendar'));
        $week->assertOk();
        $this->assertSame('week', $week->viewData('mode'));
        $this->assertSame(1, $week->viewData('total'));

        $month = $this->actingAs($tech)->get(route('technical.work.calendar', ['mode' => 'month']));
        $month->assertOk();
        $this->assertSame('month', $month->viewData('mode'));
        $this->assertSame(1, $month->viewData('total'));
    }

    /**
     * Chống N+1 trên dashboard: số truy vấn KHÔNG được tăng theo số dòng.
     *
     * So sánh trực tiếp số query khi có 3 đầu việc và khi có 30 đầu việc.
     */
    public function test_overview_does_not_issue_queries_per_row(): void
    {
        $tech = $this->technician();
        $siteId = $this->makeSite();

        for ($i = 0; $i < 3; $i++) {
            $this->makeTask($siteId, (int) $tech->id);
        }

        $small = $this->countQueriesForOverview($tech);

        for ($i = 0; $i < 27; $i++) {
            $this->makeTask($siteId, (int) $tech->id);
        }

        $large = $this->countQueriesForOverview($tech);

        $this->assertLessThanOrEqual(
            $small + 2,
            $large,
            "Số truy vấn tăng theo số dòng (N+1): {$small} → {$large}.",
        );
    }

    private function countQueriesForOverview($user): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('ky-thuat.tong-quan'))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    }
}
