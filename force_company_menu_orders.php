<?php

$file = 'resources/views/layouts/partials/sidebar.blade.php';

if (!file_exists($file)) {
    exit("Không tìm thấy file sidebar: {$file}\n");
}

$code = file_get_contents($file);
$backup = $file . '.bak_force_company_' . date('Ymd_His');
copy($file, $backup);

echo "Backup: {$backup}\n";

/*
|--------------------------------------------------------------------------
| 1. Đảm bảo route /companies tồn tại
|--------------------------------------------------------------------------
*/
$routeFile = 'routes/web.php';

if (file_exists($routeFile)) {
    $routes = file_get_contents($routeFile);

    if (!str_contains($routes, "companies.index") && !str_contains($routes, "Route::resource('companies'")) {
        $companyRoutes = <<<'ROUTE'

/*
|--------------------------------------------------------------------------
| Companies - Thông tin công ty in PDF
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {
    Route::get('/companies', [\App\Http\Controllers\CompanyController::class, 'index'])
        ->name('companies.index');

    Route::get('/companies/{company}/edit', [\App\Http\Controllers\CompanyController::class, 'edit'])
        ->name('companies.edit');

    Route::put('/companies/{company}', [\App\Http\Controllers\CompanyController::class, 'update'])
        ->name('companies.update');
});

ROUTE;

        if (str_contains($routes, "require __DIR__ . '/hr.php';")) {
            $routes = str_replace("    require __DIR__ . '/hr.php';", $companyRoutes . "\n    require __DIR__ . '/hr.php';", $routes);
        } else {
            $routes .= $companyRoutes;
        }

        file_put_contents($routeFile, $routes);
        echo "Đã thêm route /companies\n";
    } else {
        echo "Route companies đã tồn tại\n";
    }
}

/*
|--------------------------------------------------------------------------
| 2. Cho menu Đơn hàng active khi ở /companies
|--------------------------------------------------------------------------
*/
$code = str_replace(
    "active_route(['orders.*','payment-methods.*'])",
    "active_route(['orders.*','payment-methods.*','companies.*'])",
    $code
);

$code = str_replace(
    "request()->routeIs('orders.*') || request()->routeIs('payment-methods.*') ? 'true' : 'false'",
    "request()->routeIs('orders.*') || request()->routeIs('payment-methods.*') || request()->is('companies*') ? 'true' : 'false'",
    $code
);

$code = str_replace(
    "(request()->routeIs('orders.*') || request()->routeIs('payment-methods.*')) ? 'show' : ''",
    "(request()->routeIs('orders.*') || request()->routeIs('payment-methods.*') || request()->is('companies*')) ? 'show' : ''",
    $code
);

/*
|--------------------------------------------------------------------------
| 3. Xóa menu công ty cũ nếu từng chèn sai
|--------------------------------------------------------------------------
*/
$code = preg_replace(
    '/\s*<li[^>]*data-ego-company-menu="orders"[\s\S]*?<\/li>/',
    '',
    $code
);

/*
|--------------------------------------------------------------------------
| 4. Chèn menu Thông tin công ty ngay dưới Phương thức thanh toán
|--------------------------------------------------------------------------
*/
$pattern = '/(<li>\s*<a href="\{\{\s*route\(\'payment-methods\.index\'\)\s*\}\}"[\s\S]*?Phương thức thanh toán[\s\S]*?<\/a>\s*<\/li>)/';

$menu = <<<'BLADE'
$1

                    <li data-ego-company-menu="orders">
                        <a href="{{ url('/companies') }}"
                           class="ego-sublink {{ request()->is('companies*') ? 'active' : '' }}"
                           data-ego-type="nav">
                            Thông tin công ty
                        </a>
                    </li>
BLADE;

$newCode = preg_replace($pattern, $menu, $code, 1, $count);

if ($count < 1) {
    exit("Không chèn được menu. Không tìm thấy đoạn Phương thức thanh toán.\n");
}

file_put_contents($file, $newCode);

echo "Đã ép thêm menu: Đơn hàng -> Thông tin công ty\n";
