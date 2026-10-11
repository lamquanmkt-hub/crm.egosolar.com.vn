<?php
$user = \App\Models\User::where('email', 'admin@egosolar.vn')->first();
\Illuminate\Support\Facades\Auth::login($user);

echo "\n--- TEST 1: Warehouse Inventory Leak ---\n";
$warehouse = \App\Models\Core\Warehouse::where('company_id', 1)->first();
if ($warehouse) {
    $request = Illuminate\Http\Request::create('/warehouses/' . $warehouse->id . '/inventory', 'GET');
    $app = app();
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    
    ob_start();
    $response = $kernel->handle($request);
    $content = $response->getContent();
    ob_end_clean();
    
    echo "Status: " . $response->getStatusCode() . "\n";
}

echo "\n--- TEST 2: Serial Warranty Issue with wrong company ---\n";
// Gi? s? có m?t serial c?a công ty khác (company_id != 1)
$otherCompanyProduct = \App\Models\Inventory\Catalog\Product::where('company_id', '!=', 1)->first();
if ($otherCompanyProduct) {
    $code = 'TEST_CODE_OTHER_COMP';
    // Fake data directly in DB to test validation
    $identifierId = DB::table('crm_serial_identifiers')->insertGetId(['type' => 'serial', 'code' => $code]);
    $unitId = DB::table('crm_serial_units')->insertGetId(['product_id' => $otherCompanyProduct->id, 'warehouse_id' => null]);
    DB::table('crm_serial_unit_identifiers')->insert(['serial_unit_id' => $unitId, 'serial_identifier_id' => $identifierId, 'is_primary' => 1]);
    DB::table('crm_serial_unit_states')->insert(['serial_unit_id' => $unitId, 'company_id' => $otherCompanyProduct->company_id, 'state' => 'in_stock']);

    $request2 = Illuminate\Http\Request::create('/serial-warranty/issue', 'POST', [
        'serials' => $code,
        'warranty_months' => 12
    ]);
    ob_start();
    $response2 = $kernel->handle($request2);
    $content2 = $response2->getContent();
    ob_end_clean();
    
    echo "Status issue: " . $response2->getStatusCode() . "\n";
    // Check session errors
    $errors = session()->get('error') ?? session()->get('errors');
    if ($errors) {
        if (is_string($errors)) echo "Error string: $errors\n";
        else print_r($errors->all());
    }
    
    // Clean up
    DB::table('crm_serial_unit_states')->where('serial_unit_id', $unitId)->delete();
    DB::table('crm_serial_unit_identifiers')->where('serial_unit_id', $unitId)->delete();
    DB::table('crm_serial_units')->where('id', $unitId)->delete();
    DB::table('crm_serial_identifiers')->where('id', $identifierId)->delete();
} else {
    echo "No product from other company found.\n";
}
