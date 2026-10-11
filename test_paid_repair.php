<?php
$user = \App\Models\User::where('email', 'admin@egosolar.vn')->first();
\Illuminate\Support\Facades\Auth::login($user);

$url = '/ky-thuat/sua-chua-tinh-phi';
echo "Testing: $url\n";
foreach (['', '?company_id=1', '?company_id=999'] as $query) {
    $fullUrl = $url . $query;
    $request = Illuminate\Http\Request::create($fullUrl, 'GET');
    $app = app();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    ob_start();
    $response = $kernel->handle($request);
    $content = $response->getContent();
    ob_end_clean();

    $len = strlen($content);
    $status = $response->getStatusCode();
    echo " - [ $query ] Status: $status | Length: $len\n";
    if ($status === 500) {
        echo "   Error: " . $response->exception->getMessage() . "\n";
    }
    $kernel->terminate($request, $response);
}
