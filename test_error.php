<?php
$user = \App\Models\User::where('email', 'admin@egosolar.vn')->first();
\Illuminate\Support\Facades\Auth::login($user);
$request = Illuminate\Http\Request::create('/ky-thuat/sua-chua-tinh-phi', 'GET');
$app = app();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
if ($response->getStatusCode() === 500) {
    echo $response->exception->getMessage();
}
