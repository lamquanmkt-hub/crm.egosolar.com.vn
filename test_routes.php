<?php
$user = \App\Models\User::where('email', 'admin@egosolar.vn')->first();
\Illuminate\Support\Facades\Auth::login($user);

$urls = [
    '/inventory/products',
    '/inventory/products/1/edit',
    '/inventory/products/output',
    '/inventory/product-serials',
    '/inventory/serial-warranties',
    '/inventory/warehouses',
    '/inventory/assets',
    '/crm/projects'
];

foreach ($urls as $url) {
    echo "--------------------------\n";
    echo "Testing: $url\n";
    $results = [];
    foreach (['', '?company_id=1', '?company_id=999'] as $query) {
        $fullUrl = $url . $query;
        $request = Illuminate\Http\Request::create($fullUrl, 'GET');
        $app = app();
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $content = $response->getContent();
        $len = strlen($content);
        $status = $response->getStatusCode();
        echo " - [ $query ] Status: $status | Length: $len\n";
        $kernel->terminate($request, $response);
    }
}
