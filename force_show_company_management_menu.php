<?php

$file = '/home/crmegoso/public_html/resources/views/partials/sidebar.blade.php';

if (!file_exists($file)) {
    exit("Không tìm thấy file sidebar: {$file}\n");
}

$code = file_get_contents($file);

$backup = $file . '.bak_force_company_management_' . date('Ymd_His');
copy($file, $backup);

echo "Backup: {$backup}\n";

/*
|--------------------------------------------------------------------------
| Xóa menu Quản lý công ty cũ nếu đã từng chèn nhưng bị ẩn
|--------------------------------------------------------------------------
*/
$code = preg_replace(
    '/\s*@if\([^\n]*company-management\.index[^\n]*\)[\s\S]*?@endif/s',
    '',
    $code
);

$code = preg_replace(
    '/\s*<li[^>]*data-ego-company-management-menu="products"[\s\S]*?<\/li>/',
    '',
    $code
);

/*
|--------------------------------------------------------------------------
| Cho menu Sản phẩm active khi vào /company-management
|--------------------------------------------------------------------------
*/
$code = str_replace(
    "active_route(['products.input','products.output','products.history','products.create','categories.*','warehouses.*','brands.*','price-tiers.*'])",
    "active_route(['products.input','products.output','products.history','products.create','categories.*','warehouses.*','brands.*','price-tiers.*','company-management.*'])",
    $code
);

$code = str_replace(
    "request()->routeIs('price-tiers.*') ? 'true' : 'false'",
    "request()->routeIs('price-tiers.*') || request()->is('company-management*') ? 'true' : 'false'",
    $code
);

$code = str_replace(
    "request()->routeIs('price-tiers.*')) ? 'show' : ''",
    "request()->routeIs('price-tiers.*') || request()->is('company-management*')) ? 'show' : ''",
    $code
);

/*
|--------------------------------------------------------------------------
| Chèn Quản lý công ty ngay dưới Danh sách kho
|--------------------------------------------------------------------------
*/
$needle = <<<'BLADE'
            @if($u->hasAnyRole(['admin','warehouse']) || $u->can('warehouse.manage') || $u->can('warehouse.view'))
                <li>
                    <a href="{{ route('warehouses.index') }}"
                       class="ego-sublink {{ active_route('warehouses.index') }}"
                       data-ego-type="nav">
                        Danh sách kho
                    </a>
                </li>
            @endif
BLADE;

$insert = <<<'BLADE'
            @if($u->hasAnyRole(['admin','warehouse']) || $u->can('warehouse.manage') || $u->can('warehouse.view'))
                <li>
                    <a href="{{ route('warehouses.index') }}"
                       class="ego-sublink {{ active_route('warehouses.index') }}"
                       data-ego-type="nav">
                        Danh sách kho
                    </a>
                </li>
            @endif

            <li data-ego-company-management-menu="products">
                <a href="{{ url('/company-management') }}"
                   class="ego-sublink {{ request()->is('company-management*') ? 'active' : '' }}"
                   data-ego-type="nav">
                    Quản lý công ty
                </a>
            </li>
BLADE;

if (!str_contains($code, $needle)) {
    exit("Không tìm thấy đoạn Danh sách kho để chèn. Gửi lại file sidebar hiện tại cho tôi.\n");
}

$code = str_replace($needle, $insert, $code);

file_put_contents($file, $code);

echo "Đã thêm menu: Sản phẩm -> Quản lý công ty\n";
