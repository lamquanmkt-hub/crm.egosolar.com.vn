<?php

declare(strict_types=1);

namespace Tests\Feature\Technical;

use App\Services\Technical\TechnicalGuideRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * HỆ THỐNG TRANG "CÁCH SỬ DỤNG" CỦA MODULE KỸ THUẬT.
 *
 * Bộ test này khoá đúng những gì dễ hỏng nhất:
 *   1. `/ky-thuat/huong-dan` trả 200 cho cả ba vai trò và nội dung KHÁC NHAU
 *      theo quyền (nhân viên không có card nhóm Trưởng phòng / Admin trong HTML).
 *   2. Slug nhóm quản lý trả 403 cho nhân viên dù gõ thẳng URL.
 *   3. Slug không tồn tại trả 404.
 *   4. Nút "Cách sử dụng" xuất hiện đúng trang, đúng slug.
 *   5. Chống open-redirect ở tham số `?return=`.
 *
 * Mọi test gọi route THẬT qua `actingAs()->get()`; không có test chỉ so chuỗi
 * tĩnh và không hardcode email.
 */
final class TechnicalGuidePagesTest extends TestCase
{
    use DatabaseTransactions;
    use TechnicalWorkFixtures;

    private const GUIDES_INDEX = '/ky-thuat/huong-dan';

    /** Slug chỉ dành cho nhóm Trưởng phòng. */
    private const MANAGER_SLUGS = [
        'truong-phong-ke-hoach-giao-viec',
        'truong-phong-duyet-bao-cao',
        'truong-phong-tong-ket-tuan',
    ];

    /** Slug chỉ dành cho nhóm Admin / Ban giám đốc. */
    private const ADMIN_SLUGS = [
        'admin-ke-hoach-giao-viec',
        'admin-mo-lai-bao-cao',
        'admin-kpi-ky-thuat',
    ];

    /** Slug nhóm Nhân viên — cả ba vai trò đều xem được. */
    private const STAFF_SLUGS = [
        'nhan-vien-tong-quan',
        'nhan-vien-ke-hoach-tuan',
        'nhan-vien-bao-cao-ngay',
        'nhan-vien-bao-cao-phat-sinh',
    ];

    public function test_guides_index_returns_200_for_all_three_roles(): void
    {
        foreach (['technician', 'technicalManager', 'admin'] as $factory) {
            $user = $this->{$factory}();

            $this->actingAs($user)
                ->get(self::GUIDES_INDEX)
                ->assertOk()
                ->assertSee('Hướng dẫn sử dụng module Kỹ thuật', false);
        }
    }

    public function test_index_content_differs_by_role(): void
    {
        $staffHtml = $this->actingAs($this->technician())->get(self::GUIDES_INDEX)->assertOk()->getContent();
        $managerHtml = $this->actingAs($this->technicalManager())->get(self::GUIDES_INDEX)->assertOk()->getContent();
        $adminHtml = $this->actingAs($this->admin())->get(self::GUIDES_INDEX)->assertOk()->getContent();

        // Nhân viên: thấy nhóm Nhân viên, KHÔNG có card nhóm quản lý trong HTML.
        $this->assertStringContainsString('Dành cho Nhân viên Kỹ thuật', $staffHtml);
        $this->assertStringNotContainsString('Dành cho Trưởng phòng Kỹ thuật', $staffHtml);
        $this->assertStringNotContainsString('Dành cho Admin / Ban giám đốc', $staffHtml);

        foreach (array_merge(self::MANAGER_SLUGS, self::ADMIN_SLUGS) as $slug) {
            $this->assertStringNotContainsString('/huong-dan/'.$slug, $staffHtml);
        }

        // Trưởng phòng: Nhân viên + Trưởng phòng, KHÔNG có nhóm Admin.
        $this->assertStringContainsString('Dành cho Nhân viên Kỹ thuật', $managerHtml);
        $this->assertStringContainsString('Dành cho Trưởng phòng Kỹ thuật', $managerHtml);
        $this->assertStringNotContainsString('Dành cho Admin / Ban giám đốc', $managerHtml);

        foreach (self::ADMIN_SLUGS as $slug) {
            $this->assertStringNotContainsString('/huong-dan/'.$slug, $managerHtml);
        }

        // Admin: thấy cả ba nhóm.
        foreach (['Dành cho Nhân viên Kỹ thuật', 'Dành cho Trưởng phòng Kỹ thuật', 'Dành cho Admin / Ban giám đốc'] as $label) {
            $this->assertStringContainsString($label, $adminHtml);
        }
    }

    public function test_staff_guides_are_readable_by_every_role(): void
    {
        foreach (self::STAFF_SLUGS as $slug) {
            foreach (['technician', 'technicalManager', 'admin'] as $factory) {
                $this->actingAs($this->{$factory}())
                    ->get(self::GUIDES_INDEX.'/'.$slug)
                    ->assertOk();
            }
        }
    }

    public function test_technician_gets_403_on_manager_and_admin_slugs_even_with_direct_url(): void
    {
        $technician = $this->technician();

        foreach (array_merge(self::MANAGER_SLUGS, self::ADMIN_SLUGS) as $slug) {
            $this->actingAs($technician)
                ->get(self::GUIDES_INDEX.'/'.$slug)
                ->assertForbidden();
        }
    }

    public function test_manager_can_read_manager_slugs_but_not_admin_slugs(): void
    {
        $manager = $this->technicalManager();

        foreach (self::MANAGER_SLUGS as $slug) {
            $this->actingAs($manager)->get(self::GUIDES_INDEX.'/'.$slug)->assertOk();
        }

        foreach (self::ADMIN_SLUGS as $slug) {
            $this->actingAs($manager)->get(self::GUIDES_INDEX.'/'.$slug)->assertForbidden();
        }
    }

    public function test_admin_can_read_every_slug(): void
    {
        $admin = $this->admin();

        foreach (array_merge(self::STAFF_SLUGS, self::MANAGER_SLUGS, self::ADMIN_SLUGS) as $slug) {
            $this->actingAs($admin)->get(self::GUIDES_INDEX.'/'.$slug)->assertOk();
        }
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->actingAs($this->admin())
            ->get(self::GUIDES_INDEX.'/khong-ton-tai-dau')
            ->assertNotFound();
    }

    public function test_user_outside_technical_module_is_forbidden(): void
    {
        $this->actingAs($this->outsider())
            ->get(self::GUIDES_INDEX)
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(self::GUIDES_INDEX)->assertRedirect();
    }

    /**
     * Nút "Cách sử dụng" phải xuất hiện trên ĐÚNG các trang nghiệp vụ đã khai
     * báo, với slug tương ứng của từng trang.
     */
    public function test_help_button_appears_on_staff_pages_with_right_slug(): void
    {
        $technician = $this->technician();

        $expected = [
            '/ky-thuat' => 'nhan-vien-tong-quan',
            '/ky-thuat/ke-hoach-tuan' => 'nhan-vien-ke-hoach-tuan',
            '/ky-thuat/bao-cao-ngay' => 'nhan-vien-bao-cao-ngay',
            '/ky-thuat/bao-cao-ngay/tao?mode=phat-sinh' => 'nhan-vien-bao-cao-phat-sinh',
        ];

        foreach ($expected as $url => $slug) {
            $html = $this->actingAs($technician)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-tg-help="'.$slug.'"', $html, 'Thiếu nút hướng dẫn ở '.$url);
            $this->assertStringContainsString('Cách sử dụng', $html, 'Thiếu nhãn nút hướng dẫn ở '.$url);
        }

        /*
         * Form viết báo cáo tự chuyển sang chế độ "phát sinh" khi nhân viên
         * chưa có đầu việc nguồn nào (hành vi sẵn có: không để màn hình cụt),
         * nên trang này dẫn tới một trong hai bài báo cáo tuỳ chế độ.
         */
        $createHtml = $this->actingAs($technician)->get('/ky-thuat/bao-cao-ngay/tao')->assertOk()->getContent();

        $this->assertTrue(
            str_contains($createHtml, 'data-tg-help="nhan-vien-bao-cao-ngay"')
            || str_contains($createHtml, 'data-tg-help="nhan-vien-bao-cao-phat-sinh"'),
            'Trang viết báo cáo thiếu nút hướng dẫn.'
        );
    }

    public function test_help_button_appears_on_manager_pages_with_right_slug(): void
    {
        $manager = $this->technicalManager();

        $expected = [
            '/ky-thuat/quan-ly/tong-quan' => 'truong-phong-tong-ket-tuan',
            '/ky-thuat/quan-ly/ke-hoach' => 'truong-phong-ke-hoach-giao-viec',
            '/ky-thuat/quan-ly/tong-ket-tuan' => 'truong-phong-tong-ket-tuan',
            '/ky-thuat/bao-cao-ngay' => 'truong-phong-duyet-bao-cao',
        ];

        foreach ($expected as $url => $slug) {
            $html = $this->actingAs($manager)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-tg-help="'.$slug.'"', $html, 'Thiếu nút hướng dẫn ở '.$url);
        }
    }

    public function test_help_button_appears_on_admin_pages_with_right_slug(): void
    {
        $admin = $this->admin();

        $expected = [
            '/ky-thuat/dashboard' => 'admin-ke-hoach-giao-viec',
            '/ky-thuat/dashboard/ke-hoach' => 'admin-ke-hoach-giao-viec',
            '/ky-thuat/kpis' => 'admin-kpi-ky-thuat',
        ];

        foreach ($expected as $url => $slug) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('data-tg-help="'.$slug.'"', $html, 'Thiếu nút hướng dẫn ở '.$url);
        }
    }

    /** `?return=` là đường dẫn nội bộ hợp lệ thì được dùng làm nút "Quay lại". */
    public function test_valid_internal_return_is_used_for_back_button(): void
    {
        $html = $this->actingAs($this->technician())
            ->get(self::GUIDES_INDEX.'/nhan-vien-ke-hoach-tuan?return='.rawurlencode('/ky-thuat/ke-hoach-tuan?week=2026-09-21'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="/ky-thuat/ke-hoach-tuan?week=2026-09-21"', $html);
    }

    /** Chống open-redirect: URL ngoài bị bỏ qua, rơi về `route_hint` của bài. */
    public function test_external_return_is_rejected_and_falls_back_to_route_hint(): void
    {
        $technician = $this->technician();
        $fallback = route('technical.week-plan.index');

        $malicious = [
            'https://evil.com',
            'http://evil.com/x',
            '//evil.com',
            '/\\evil.com',
            'javascript:alert(1)',
            '/ky-thuat\\@evil.com',
        ];

        foreach ($malicious as $candidate) {
            $html = $this->actingAs($technician)
                ->get(self::GUIDES_INDEX.'/nhan-vien-ke-hoach-tuan?return='.rawurlencode($candidate))
                ->assertOk()
                ->getContent();

            $this->assertStringNotContainsString('evil.com', $html, 'Bị open-redirect với: '.$candidate);
            $this->assertStringContainsString('href="'.$fallback.'"', $html, 'Không fallback đúng với: '.$candidate);
        }
    }

    /** Registry tự nó phải từ chối mọi biến thể URL ngoài. */
    public function test_registry_safe_return_url_only_accepts_internal_paths(): void
    {
        /** @var TechnicalGuideRegistry $registry */
        $registry = app(TechnicalGuideRegistry::class);
        $guide = $registry->find('nhan-vien-ke-hoach-tuan');

        $this->assertIsArray($guide);

        $fallback = $registry->routeHintUrl($guide);

        $this->assertSame('/ky-thuat/ke-hoach-tuan', $registry->safeReturnUrl('/ky-thuat/ke-hoach-tuan', $guide));
        $this->assertSame('/a?b=1&c=2', $registry->safeReturnUrl('/a?b=1&c=2', $guide));

        foreach (['', null, '//evil.com', 'https://evil.com', 'evil.com', '/\\evil.com', "/ky-thuat\nHeader: x"] as $bad) {
            $this->assertSame($fallback, $registry->safeReturnUrl($bad, $guide), 'Chấp nhận nhầm: '.var_export($bad, true));
        }
    }

    /** Vai trò được suy ra từ TechnicalAccess, không hardcode email. */
    public function test_registry_resolves_role_from_technical_access(): void
    {
        /** @var TechnicalGuideRegistry $registry */
        $registry = app(TechnicalGuideRegistry::class);

        $this->assertSame(TechnicalGuideRegistry::ROLE_STAFF, $registry->roleFor($this->technician()));
        $this->assertSame(TechnicalGuideRegistry::ROLE_MANAGER, $registry->roleFor($this->technicalManager()));
        $this->assertSame(TechnicalGuideRegistry::ROLE_ADMIN, $registry->roleFor($this->admin()));
        $this->assertNull($registry->roleFor($this->outsider()));
        $this->assertNull($registry->roleFor(null));
    }

    /** Mọi slug khai báo trong config đều phải có Blade tương ứng. */
    public function test_every_registered_guide_has_a_view(): void
    {
        /** @var TechnicalGuideRegistry $registry */
        $registry = app(TechnicalGuideRegistry::class);

        foreach ($registry->all() as $guide) {
            $this->assertTrue(
                view()->exists('technical.guides.'.$guide['slug']),
                'Thiếu view cho slug: '.$guide['slug']
            );
        }
    }
}
