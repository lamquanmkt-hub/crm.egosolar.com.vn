<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "CHỖ TẠO KẾ HOẠCH" PHẢI RÕ RÀNG TRÊN CẢ BA MÀN HÌNH KẾ HOẠCH (đợt V5).
 *
 * Người dùng báo: "Chỉ thấy màn xem kế hoạch, không thấy nút / flow tạo kế
 * hoạch". Bộ test này khoá lại kết quả sau khi sửa:
 *
 *   1. Nhân viên  → nút "Tạo kế hoạch của tôi", mở sẵn khung thêm việc.
 *   2. Trưởng phòng → HAI nút riêng "Tạo kế hoạch" + "Giao việc" (V7).
 *   3. Admin/Giám đốc → "Tạo kế hoạch" + "Giao việc".
 *   4. Trang Admin không chứa FORM GHI (các CTA là liên kết GET sang bàn điều
 *      phối, nơi quyền được kiểm lại và lý do + nhật ký bị ép buộc).
 *
 * CẬP NHẬT NGHIỆP VỤ 2026-09 — điểm 3 ĐẢO NGƯỢC so với đợt V5-RECOVERY:
 * đặc tả cũ ("Admin chỉ giám sát, không CTA") đã bị thay bằng "Admin/Giám đốc
 * được tạo kế hoạch và giao việc như trưởng phòng". Hai test khoá đặc tả cũ
 * (`test_admin_plan_page_has_no_create_or_assign_cta`,
 *  `test_admin_plan_detail_page_has_no_action_buttons`) được thay bằng hai test
 * khoá đặc tả mới bên dưới. Các assertion về BẢO MẬT (không form POST, mở trang
 * không ghi dữ liệu, 403 chéo) GIỮ NGUYÊN, không nới lỏng.
 */
class TechnicalPlanCtaTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private const LABEL_STAFF = 'Tạo kế hoạch của tôi';

    /**
     * V7 (2026-09): nút GỘP "Tạo / Giao kế hoạch" ĐÃ BỊ BỎ — header của quản lý
     * có hai nút riêng mở thẳng form thật. Hằng số này giữ lại để khẳng định
     * nhãn cũ KHÔNG còn xuất hiện ở bất kỳ màn hình nào.
     */
    private const LABEL_MANAGER_LEGACY = 'Tạo / Giao kế hoạch';

    /*
    |--------------------------------------------------------------------------
    | Nhãn CTA theo vai trò
    |--------------------------------------------------------------------------
    */

    public function test_staff_plan_page_shows_a_create_my_plan_button(): void
    {
        $tech = $this->technician();

        $response = $this->actingAs($tech)->get(route('technical.week-plan.index'));

        $response->assertOk();
        $response->assertSee(self::LABEL_STAFF);
        $response->assertDontSee(self::LABEL_MANAGER_LEGACY);
        // CTA trỏ đúng màn hình tự lập kế hoạch, mở sẵn khung thêm việc.
        $response->assertSee('add=1', false);
    }

    public function test_staff_create_link_opens_the_add_work_form(): void
    {
        $tech = $this->technician();

        $closed = $this->actingAs($tech)->get(route('technical.week-plan.index'))->getContent();
        $opened = $this->actingAs($tech)->get(route('technical.week-plan.index', ['add' => 1]))->getContent();

        $this->assertStringContainsString('id="tp-add-form"', $closed);
        $this->assertFalse($this->detailsIsOpen($closed, 'tp-add-form'), 'Khung thêm việc phải đóng khi không có ?add=1.');
        $this->assertTrue($this->detailsIsOpen($opened, 'tp-add-form'), '?add=1 phải mở sẵn khung thêm việc.');
    }

    /**
     * V7: header trang ma trận có ĐÚNG HAI nút riêng, KHÔNG còn nút gộp.
     *
     * Test cũ `..._shows_a_create_or_assign_button` khoá nhãn gộp
     * "Tạo / Giao kế hoạch" — chính là hành vi mà nhiệm vụ này thay thế, nên
     * được viết lại chứ không xoá.
     */
    public function test_manager_plan_board_shows_two_separate_buttons(): void
    {
        $manager = $this->technicalManager();

        $response = $this->actingAs($manager)->get(route('technical.manager.board'));

        $response->assertOk();
        $response->assertSee('data-tp-cta="create-plan"', false);
        $response->assertSee('data-tp-cta="assign-work"', false);
        $response->assertDontSee(self::LABEL_MANAGER_LEGACY);
        $response->assertDontSee(self::LABEL_STAFF);
    }

    /**
     * V7: liên kết cũ `?focus=assign` KHÔNG còn cuộn tới khung chọn nhân viên
     * (khung đó đã bị gỡ) mà mở THẲNG drawer Giao việc.
     */
    public function test_legacy_focus_assign_now_opens_the_assign_drawer(): void
    {
        $manager = $this->technicalManager();
        $tech = $this->technician();

        $board = $this->actingAs($manager)->get(route('technical.manager.board', ['focus' => 'assign']));
        $board->assertOk();
        $board->assertSee('id="tpAssignDrawer"', false);
        $board->assertSee('data-tp-open="1"', false);
        $board->assertDontSee('id="tp-assign-picker"', false);

        // Khung "Giao thêm việc" ở trang chi tiết vẫn còn (lối tắt, không bắt buộc).
        $detail = $this->actingAs($manager)->get(route('technical.manager.detail', [
            'member' => $tech->id,
            'focus' => 'assign',
        ]));
        $detail->assertOk();
        $this->assertTrue(
            $this->detailsIsOpen($detail->getContent(), 'tp-assign'),
            'Trang chi tiết vẫn mở sẵn khung giao việc khi có ?focus=assign.',
        );
    }

    /** `<details id="...">` có đang mở không (thuộc tính `open` đứng sau id). */
    private function detailsIsOpen(string $html, string $id): bool
    {
        return (bool) preg_match('/id="'.preg_quote($id, '/').'"[^>]*\sopen[\s>]/', $html);
    }

    /**
     * NGHIỆP VỤ MỚI 2026-09: trang Kế hoạch & Giao việc của Admin/Giám đốc có
     * nút chính "Tạo kế hoạch" và nút phụ "Giao việc", cả hai dẫn về bàn điều
     * phối đã có (không dựng form thứ hai).
     */
    public function test_admin_plan_page_shows_create_and_assign_cta(): void
    {
        $this->seedPlan();

        $response = $this->actingAs($this->admin())->get(route('technical.dashboard.plans'));

        $response->assertOk();
        $response->assertSee('data-tp-cta="create-plan"', false);
        $response->assertSee('data-tp-cta="assign-work"', false);
        $response->assertSee('Tạo kế hoạch');
        $response->assertSee('Giao việc');

        // V7: CTA deep-link mở THẲNG đúng drawer ở bàn điều phối.
        $response->assertSee('open=create', false);
        $response->assertSee('open=assign', false);
        $response->assertDontSee('focus=assign', false);

        // Câu "chỉ giám sát, không can thiệp vận hành" đã được GỠ khỏi trang.
        $response->assertDontSee('chỉ giám sát');
        $response->assertDontSee('không can thiệp vận hành');
    }

    /** Nhân viên thường KHÔNG được thấy lối vào giao việc toàn đội. */
    public function test_staff_never_sees_a_team_wide_assign_cta(): void
    {
        $response = $this->actingAs($this->technician())->get(route('technical.week-plan.index'));

        $response->assertOk();
        $response->assertDontSee('data-tp-cta="assign-work"', false);
        $response->assertDontSee(self::LABEL_MANAGER_LEGACY);
    }

    /** Trang CHI TIẾT kế hoạch của Admin có lối vào giao thêm việc / điều chỉnh. */
    public function test_admin_plan_detail_page_shows_assign_and_adjust_entries(): void
    {
        $tech = $this->seedPlan();

        $response = $this->actingAs($this->admin())->get(route('technical.dashboard.plans.detail', [
            'member' => $tech->id,
            'week' => $this->currentWeekStart()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('Thêm công việc');
        $response->assertSee('Điều chỉnh');
        $response->assertSee('data-tp-cta="create-plan"', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Trang Admin vẫn chỉ xem
    |--------------------------------------------------------------------------
    */

    public function test_admin_plan_page_stays_read_only_even_with_the_create_button(): void
    {
        $this->seedPlan();

        $html = $this->actingAs($this->admin())->get(route('technical.dashboard.plans'))->getContent();
        $start = strpos($html, 'TECHNICAL_DASHBOARD_PLANS_CONTENT_START');
        $end = strpos($html, 'TECHNICAL_DASHBOARD_PLANS_CONTENT_END');
        $block = substr($html, (int) $start, (int) $end - (int) $start);

        foreach (['method="POST"', 'method="post"', 'name="_method"', '_token'] as $needle) {
            $this->assertStringNotContainsString($needle, $block);
        }
    }

    /** Nút CTA chỉ là liên kết GET — bấm vào không được tạo dữ liệu nào. */
    public function test_opening_the_admin_plan_page_writes_nothing(): void
    {
        $this->seedPlan();

        $before = [
            'technical_plan_items' => DB::table('technical_plan_items')->count(),
            'technical_week_plans' => DB::table('technical_week_plans')->count(),
            'technical_plan_histories' => DB::table('technical_plan_histories')->count(),
        ];

        $this->actingAs($this->admin())->get(route('technical.dashboard.plans'))->assertOk();

        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Cột "Rủi ro / Xung đột" + ánh xạ trạng thái
    |--------------------------------------------------------------------------
    */

    public function test_risk_column_counts_overlapping_items(): void
    {
        $tech = $this->technician();
        $planId = $this->makeWeekPlan($tech, $this->currentWeekStart());
        $day = $this->currentWeekStart()->toDateString();

        foreach ([['08:00', '11:00'], ['10:00', '12:00']] as [$start, $end]) {
            $this->makePlanItem($tech, $planId, [
                'plan_date' => $day,
                'day_part' => 'custom',
                'start_time' => $start,
                'end_time' => $end,
                'estimated_minutes' => 120,
            ]);
        }

        $response = $this->actingAs($this->admin())->get(route('technical.dashboard.plans', [
            'user_id' => $tech->id,
            'week' => $this->currentWeekStart()->toDateString(),
        ]));

        $response->assertOk();

        $row = $response->viewData('rows')->first();
        $this->assertSame(2, (int) $row['conflict_items']);
        $this->assertGreaterThan(0, (int) $row['risk_items']);

        // Cảnh báo hiển thị ngay ở cột Trạng thái của bảng kế hoạch.
        $response->assertSee('cảnh báo');
    }

    public function test_plan_status_filter_uses_the_real_backend_taxonomy(): void
    {
        $options = \App\Services\Technical\TechnicalDashboardReadService::planStatusOptions();

        $this->assertSame(
            ['Chưa lập', 'Đang lập', 'Đã hoàn tất', 'Đã được Trưởng phòng điều chỉnh'],
            array_values($options),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 403 chéo
    |--------------------------------------------------------------------------
    */

    public function test_staff_and_manager_cannot_open_the_admin_plan_page(): void
    {
        foreach ([$this->technician(), $this->technicalManager()] as $user) {
            $response = $this->actingAs($user)->get(route('technical.dashboard.plans'));
            $response->assertForbidden();
            $this->assertFalse($response->isRedirect());
        }
    }

    private function seedPlan(): \App\Models\User
    {
        $tech = $this->technician();
        $planId = $this->makeWeekPlan($tech, $this->currentWeekStart());
        $this->makePlanItem($tech, $planId, [
            'plan_date' => $this->currentWeekStart()->toDateString(),
            'site_id' => $this->makeSite(),
            'site_name' => 'Công trình kiểm thử',
        ]);

        return $tech;
    }
}
