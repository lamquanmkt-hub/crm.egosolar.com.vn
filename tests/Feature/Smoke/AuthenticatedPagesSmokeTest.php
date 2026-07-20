<?php

declare(strict_types=1);

namespace Tests\Feature\Smoke;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Smoke test diện rộng: quét mọi route GET không tham số và khẳng định
 * KHÔNG trang nào trả 5xx khi đăng nhập bằng admin.
 *
 * Hợp đồng ở đây là "ứng dụng không vỡ", không phải "trang trả đúng 200":
 * 200/302/403/404 đều hợp lệ tuỳ phân quyền và dữ liệu; 5xx thì luôn là lỗi.
 *
 * Đây là lưới an toàn cho các đợt refactor đổi namespace/di chuyển file hàng loạt.
 *
 * ⚠️ GIỚI HẠN ĐÃ BIẾT: test chạy trên DB gần như rỗng nên CHỈ bắt được lỗi
 * không phụ thuộc dữ liệu. Ví dụ thật: `/notifications` từng trả 500 trên
 * production nhưng qua được test này, vì dòng lỗi nằm trong nhánh @else của
 * view — chỉ chạy khi CÓ thông báo (xem NotificationsPageTest).
 * Trang nào có nhánh render khác nhau theo dữ liệu thì phải có test riêng
 * kèm seed, đừng tin mỗi smoke test.
 */
final class AuthenticatedPagesSmokeTest extends TestCase
{
    use DatabaseTransactions;

    /** Tiền tố route bỏ qua: tải file, export nặng, hoặc gây side-effect. */
    private const SKIP_PREFIXES = [
        '_debugbar', '_ignition', 'telescope', 'horizon',
        'livewire', 'sanctum', 'up',
    ];

    /** Route con dễ nặng/tải file — bỏ để test chạy nhanh và ổn định. */
    private const SKIP_CONTAINS = [
        'export', 'download', 'pdf', 'preview', 'file', 'stream', 'logout',
    ];

    /** Số trang tối thiểu phải quét được, chặn test tự biến thành rỗng. */
    private const MIN_PAGES = 40;

    /**
     * Trang ĐANG hỏng sẵn TRƯỚC đợt refactor 2026-07-20 (đã đối chiếu commit
     * gốc 8a2fa03 và bản deploy a0332f7 — không phải regression).
     *
     * Cố ý liệt kê tường minh thay vì lọc âm thầm, để lỗi vẫn nhìn thấy được.
     * Sửa xong cái nào thì xoá khỏi danh sách này.
     *
     * @var array<string, string> uri => nguyên nhân
     */
    private const KNOWN_BROKEN = [
        'orders/my/dashboard' => 'Route trỏ OrderController@myOrders — method chưa từng tồn tại',
        'marketing/dashboard' => "View gọi route('marketing.budget') — route chưa từng được định nghĩa",
        'marketing/report/seo' => 'Thiếu view marketing/reports/seo.blade.php',
        'marketing/report/overview' => 'Thiếu view marketing/reports/overview.blade.php',
    ];

    /** Không trang GET nào được trả 5xx. */
    public function test_no_get_page_returns_server_error(): void
    {
        $admin = $this->userWithRole('admin');
        $uris = $this->parameterlessGetUris();

        $this->assertGreaterThanOrEqual(
            self::MIN_PAGES,
            count($uris),
            'Quét được quá ít trang — logic lọc route hỏng, test đang không kiểm tra gì.',
        );

        $broken = [];
        $unexpectedlyWorking = [];

        foreach ($uris as $uri) {
            try {
                $status = $this->actingAs($admin)->get('/'.ltrim($uri, '/'))->getStatusCode();
            } catch (\Throwable $e) {
                $status = 500;
            }

            $isKnownBroken = array_key_exists($uri, self::KNOWN_BROKEN);

            if ($status >= 500 && ! $isKnownBroken) {
                $broken[] = "/{$uri} — HTTP {$status}";
            }

            if ($status < 500 && $isKnownBroken) {
                $unexpectedlyWorking[] = $uri;
            }
        }

        $this->assertSame(
            [],
            $broken,
            'Có trang MỚI trả 5xx — nhiều khả năng là regression từ refactor.',
        );

        $this->assertSame(
            [],
            $unexpectedlyWorking,
            'Trang trong KNOWN_BROKEN đã chạy được — hãy xoá nó khỏi danh sách để test tiếp tục bảo vệ.',
        );
    }

    /**
     * Các URI GET không chứa tham số động, đã loại route hạ tầng/tải file.
     *
     * @return list<string>
     */
    private function parameterlessGetUris(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $uri = $route->uri();

            if (Str::contains($uri, '{')) {
                continue; // cần tham số -> bỏ
            }

            if (Str::startsWith($uri, self::SKIP_PREFIXES)) {
                continue;
            }

            if (Str::contains(strtolower($uri), self::SKIP_CONTAINS)) {
                continue;
            }

            $uris[] = $uri;
        }

        return array_values(array_unique($uris));
    }
}
