<?php
/**
 * Giả lập một tài khoản mở một trang và in ra mã trạng thái + lý do (KHÔNG ghi dữ liệu).
 *
 * Dùng:  php scripts/ops/check_user_page.php <email> [/duong-dan]
 * Ví dụ: php scripts/ops/check_user_page.php hr@egosolar.vn /company-documents
 *
 * Nếu kết quả là 200 mà người dùng vẫn thấy 403 ngoài trình duyệt thì 403 đến từ máy chủ web
 * (ModSecurity / .htaccess / cPanel), không phải từ ứng dụng.
 */
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$email = $argv[1] ?? null;
$path = $argv[2] ?? '/company-documents';
if (! $email) {
    fwrite(STDERR, "Dung: php scripts/ops/check_user_page.php <email> [/duong-dan]\n");
    exit(1);
}

$user = App\Models\User::where('email', $email)->first();
if (! $user) {
    fwrite(STDERR, "Khong tim thay tai khoan $email\n");
    exit(2);
}

echo 'Tai khoan : '.$user->name.' <'.$user->email.'> id='.$user->id.' is_active='.(int) $user->is_active."\n";
echo 'Role      : '.($user->roles->pluck('name')->implode(', ') ?: '(KHONG CO ROLE)')."\n";
echo 'Trang     : '.$path."\n";

app('auth')->guard('web')->setUser($user);
$response = $kernel->handle(Illuminate\Http\Request::create($path, 'GET'));
$status = $response->getStatusCode();
echo 'Ket qua   : HTTP '.$status;
if ($response->isRedirect()) {
    echo ' -> '.$response->headers->get('Location');
}
echo "\n";
if (isset($response->exception) && $response->exception) {
    echo 'Ly do     : '.get_class($response->exception).': '.$response->exception->getMessage()."\n";
}
if ($status === 403) {
    $trace = $response->exception?->getTrace()[0] ?? null;
    echo 'Noi chan  : '.($response->exception ? basename($response->exception->getFile()).':'.$response->exception->getLine() : '?')."\n";
}
