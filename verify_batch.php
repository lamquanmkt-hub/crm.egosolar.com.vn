<?php
$user = \App\Models\User::where('email', 'admin@egosolar.vn')->first();
\Illuminate\Support\Facades\Auth::login($user);

echo "\n=== B?T Ð?U TEST VERIFICATION ===\n";

$request = Illuminate\Http\Request::create('/inventory/products', 'GET');
$app = app();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "[1] Test Product input() route ... ";
ob_start();
$response = $kernel->handle($request);
ob_end_clean();
echo $response->getStatusCode() === 200 ? "OK" : "Failed (" . $response->getStatusCode() . ")";
echo "\n";

echo "[2] Test Product output() route ... ";
$request2 = Illuminate\Http\Request::create('/inventory/products/output', 'GET');
ob_start();
$response2 = $kernel->handle($request2);
ob_end_clean();
echo $response2->getStatusCode() === 200 ? "OK" : "Failed (" . $response2->getStatusCode() . ")";
echo "\n";

echo "[3] Test ?company_id=999 ... ";
$request3 = Illuminate\Http\Request::create('/inventory/products?company_id=999', 'GET');
ob_start();
$response3 = $kernel->handle($request3);
ob_end_clean();
// Controller might handle it differently but the scope should prevent it.
echo "Status: " . $response3->getStatusCode() . "\n";

echo "[4] Test Warehouse Inventory ... ";
$wh = DB::table('crm_warehouses')->where('company_id', 1)->first();
if ($wh) {
    $request4 = Illuminate\Http\Request::create('/warehouses/' . $wh->id . '/inventory', 'GET');
    ob_start();
    $response4 = $kernel->handle($request4);
    ob_end_clean();
    echo "Status: " . $response4->getStatusCode() . "\n";
} else {
    echo "No warehouse found.\n";
}

echo "[5] Test Warranty Claim (hijack company) ... ";
$request5 = Illuminate\Http\Request::create('/serial-warranty/claim', 'POST', [
    'serial_code' => 'TEST_CODE_OTHER_COMP',
    'issue_description' => 'Test',
]);
ob_start();
$response5 = $kernel->handle($request5);
ob_end_clean();
echo "Status: " . $response5->getStatusCode() . "\n";
$errors = session()->get('error');
if ($errors) {
    echo "   Error: $errors\n";
}

echo "=== K?T THÚC TEST ===\n";
