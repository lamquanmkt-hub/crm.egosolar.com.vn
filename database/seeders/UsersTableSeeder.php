<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder tạo các tài khoản nhóm mặc định (Admin, Marketing, Sales, Kế toán...).
 *
 * Bảo mật: KHÔNG hardcode mật khẩu. Mật khẩu lấy từ env SEED_USER_PASSWORD;
 * nếu thiếu sẽ sinh ngẫu nhiên cho từng tài khoản và in ra console một lần.
 * Seeder này chỉ dành cho môi trường local/staging — bị chặn trên production.
 */
class UsersTableSeeder extends Seeder
{
    /**
     * Chạy seed dữ liệu người dùng mặc định.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('UsersTableSeeder bị bỏ qua trên production (tài khoản do admin tạo thủ công).');

            return;
        }

        $accounts = [
            ['name' => 'Admin System', 'email' => 'admin@egosolar.vn'],
            ['name' => 'Marketing Team', 'email' => 'marketing@egosolar.vn'],
            ['name' => 'Sales Team', 'email' => 'sales@egosolar.vn'],
            ['name' => 'Ketoan Team', 'email' => 'ketoan@egosolar.vn'],
        ];

        foreach ($accounts as $account) {
            $password = env('SEED_USER_PASSWORD') ?: Str::random(16);

            $exists = DB::table('users')->where('email', $account['email'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('users')->insert([
                'name' => $account['name'],
                'email' => $account['email'],
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! env('SEED_USER_PASSWORD')) {
                $this->command?->warn("Mật khẩu tạm cho {$account['email']}: {$password} — đổi ngay sau khi đăng nhập.");
            }
        }
    }
}
