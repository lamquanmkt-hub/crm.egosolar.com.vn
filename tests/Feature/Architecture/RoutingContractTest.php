<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Tests\TestCase;

/**
 * Hợp đồng cấu trúc: mọi controller phải tồn tại VÀ dựng được qua container.
 *
 * Vì sao cần: `php artisan route:cache` chỉ kiểm tra class có tồn tại, KHÔNG
 * kiểm tra dependency của constructor có resolve được. Khi đổi namespace
 * service (đợt refactor 2026-07-20), một lớp được tham chiếu bằng tên ngắn
 * cùng namespace đã gãy mà route:cache vẫn qua — test này chặn đúng ca đó.
 */
final class RoutingContractTest extends TestCase
{
    /** Số route tối thiểu phải có; giảm mạnh nghĩa là đã mất route khi refactor. */
    private const MIN_EXPECTED_ROUTES = 480;

    /** Mọi controller gắn với route đều phải dựng được qua container. */
    public function test_every_routed_controller_resolves_through_container(): void
    {
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getAction('controller');

            if (! is_string($action) || ! Str::contains($action, '@')) {
                continue; // closure route
            }

            [$class] = explode('@', $action);

            if (! class_exists($class)) {
                $failures[] = "{$class} — class không tồn tại (route: {$route->uri()})";

                continue;
            }

            try {
                app($class);
            } catch (\Throwable $e) {
                $failures[] = "{$class} — không resolve được: ".Str::limit($e->getMessage(), 120);
            }
        }

        $this->assertSame([], array_values(array_unique($failures)));
    }

    /** Số controller tối thiểu — chặn test tự biến thành rỗng nếu logic quét hỏng. */
    private const MIN_EXPECTED_CONTROLLERS = 80;

    /** Mọi file controller trong app/ đều dựng được, kể cả chưa gắn route. */
    public function test_every_controller_class_resolves(): void
    {
        $classes = $this->controllerClasses();

        $this->assertGreaterThanOrEqual(
            self::MIN_EXPECTED_CONTROLLERS,
            count($classes),
            'Quét được quá ít controller — logic quét hỏng, test này đang không kiểm tra gì.',
        );

        $failures = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract() || $reflection->isInterface()) {
                continue;
            }

            try {
                app($class);
            } catch (\Throwable $e) {
                $failures[] = "{$class}: ".Str::limit($e->getMessage(), 120);
            }
        }

        $this->assertSame([], $failures);
    }

    /** Số lượng route không được tụt — chặn mất route khi đổi namespace hàng loạt. */
    public function test_route_count_does_not_regress(): void
    {
        $this->assertGreaterThanOrEqual(
            self::MIN_EXPECTED_ROUTES,
            count(Route::getRoutes()),
            'Số route giảm dưới ngưỡng — nhiều khả năng refactor đã làm mất route.',
        );
    }

    /** Các route xương sống phải luôn tồn tại. */
    public function test_core_route_names_exist(): void
    {
        $required = [
            'orders.index',
            'orders.create',
            'products.index',
            'finance.supplier-debts.index',
            'sales.commissions.index',
            'dashboard',
        ];

        foreach ($required as $name) {
            $this->assertTrue(
                Route::has($name),
                "Thiếu route bắt buộc: {$name}",
            );
        }
    }

    /**
     * Liệt kê mọi class controller dưới app/Http/Controllers.
     *
     * @return list<class-string>
     */
    private function controllerClasses(): array
    {
        $base = app_path('Http/Controllers');
        $classes = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace([app_path().DIRECTORY_SEPARATOR, '.php'], '', $file->getPathname());
            $class = 'App\\'.str_replace(DIRECTORY_SEPARATOR, '\\', $relative);

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        sort($classes);

        return $classes;
    }
}
