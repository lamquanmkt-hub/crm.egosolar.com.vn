<?php
$user = \App\Models\User::where('email', 'admin@egosolar.vn')->first();
\Illuminate\Support\Facades\Auth::login($user);
$request = Illuminate\Http\Request::create('/', 'GET');
$request->setUserResolver(fn() => $user);

$controller = app(\App\Http\Controllers\Technical\TechnicalWarrantyExchangeController::class);
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('assertCompany');
$method->setAccessible(true);

echo "Testing TechnicalWarrantyExchangeController::assertCompany\n";

try {
    \App\Support\Synced\EgoCompanyScope::setScopedId(2);
    $method->invoke($controller, 2, $request);
    echo "1. current=2, target=2 => OK\n";
} catch (\Exception $e) {
    echo "1. current=2, target=2 => FAILED: " . $e->getMessage() . "\n";
}

try {
    \App\Support\Synced\EgoCompanyScope::setScopedId(2);
    $method->invoke($controller, 1, $request);
    echo "2. current=2, target=1 => OK\n";
} catch (\Exception $e) {
    echo "2. current=2, target=1 => FAILED/ABORT: " . $e->getMessage() . "\n";
}

try {
    \App\Support\Synced\EgoCompanyScope::setScopedId(0);
    $method->invoke($controller, 2, $request);
    echo "3. current=0, target=2 => OK\n";
} catch (\Exception $e) {
    echo "3. current=0, target=2 => FAILED/ABORT: " . $e->getMessage() . "\n";
}

try {
    \App\Support\Synced\EgoCompanyScope::setScopedId(2);
    $method->invoke($controller, 0, $request);
    echo "4. current=2, target=0 => OK\n";
} catch (\Exception $e) {
    echo "4. current=2, target=0 => FAILED/ABORT: " . $e->getMessage() . "\n";
}

echo "Testing assertSerialCompany\n";
$methodSerial = $reflection->getMethod('assertSerialCompany');
$methodSerial->setAccessible(true);

try {
    \App\Support\Synced\EgoCompanyScope::setScopedId(2);
    $serial = (object)['state_company_id' => 0];
    $methodSerial->invoke($controller, $serial, 2, $request);
    echo "5. Serial NULL => OK\n";
} catch (\Exception $e) {
    echo "5. Serial NULL => FAILED/ABORT: " . $e->getMessage() . "\n";
}
