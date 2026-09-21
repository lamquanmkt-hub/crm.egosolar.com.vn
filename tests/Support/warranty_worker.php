<?php

/**
 * Worker cho test concurrency (KHÔNG phải test): chạy 1 thao tác nghiệp vụ trong tiến trình riêng để tranh chấp thật với các worker khác.
 * Dùng: php tests/Support/warranty_worker.php '<json>'   — chỉ chạy trên DB test egosolar_test.
 */
chdir(dirname(__DIR__, 2));
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if ((string) config('database.connections.'.config('database.default').'.database') !== 'egosolar_test') {
    echo json_encode(['ok' => false, 'error' => 'REFUSED: not the test database']);
    exit(2);
}

$in = json_decode($argv[1] ?? '{}', true);
while (microtime(true) < ($in['at'] ?? 0)) { // hàng rào thời gian: tất cả worker chạy cùng thời điểm
    usleep(200);
}

try {
    $user = App\Models\User::findOrFail((int) $in['user_id']);
    $out = App\Support\Warranty\Retry::onDeadlock(function () use ($in, $user) {
    switch ($in['action']) {
        case 'exchange_create':
            $claim = app(App\Services\Warranty\WarrantyExchangeService::class)->create($user, $in['data'], ['serial' => (object) $in['serial']] + $in['ctx']);
            $out = ['ok' => true, 'claim' => $claim->id];
            break;
        case 'repair_create':
            $claim = app(App\Services\Warranty\RepairService::class)->create($user, $in['data'], $in['ctx'] + ['serial_info' => null]);
            $out = ['ok' => true, 'claim' => $claim->id];
            break;
        case 'reserve_serial':
            app(App\Services\Warranty\WarrantyExchangeService::class)->reserveSerial((int) $in['claim_id'], $user, $in['serial_code'], (int) $in['warehouse_id']);
            $out = ['ok' => true];
            break;
        case 'reserve_parts':
            app(App\Services\Warranty\RepairService::class)->reserveParts((int) $in['claim_id'], $user, (int) $in['warehouse_id']);
            $out = ['ok' => true];
            break;
        case 'issue_serial':
            app(App\Services\Warranty\WarrantyExchangeService::class)->issue((int) $in['claim_id'], $user, null);
            $out = ['ok' => true];
            break;
        default:
            $out = ['ok' => false, 'error' => 'unknown action'];
    }
    return $out;
    });
} catch (App\Support\Warranty\WarrantyException $e) {
    $out = ['ok' => false, 'business' => true, 'error' => $e->getMessage()];
} catch (Throwable $e) {
    $out = ['ok' => false, 'business' => false, 'error' => get_class($e).': '.$e->getMessage()];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
