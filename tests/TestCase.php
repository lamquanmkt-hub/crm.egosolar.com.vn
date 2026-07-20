<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * TestCase gốc cho toàn bộ test suite.
 *
 * Feature test chạy trên DB `egosolar_test` (bản sao schema production,
 * dựng từ database/schema/mysql-schema.sql — xem phpunit.xml).
 * Guard bên dưới chặn tuyệt đối việc chạy test lên bất kỳ DB nào khác
 * (đặc biệt là DB production) để không thể mất dữ liệu thật.
 */
abstract class TestCase extends BaseTestCase
{
    /** Tên DB duy nhất được phép dùng khi test có chạm DB. */
    private const ALLOWED_TEST_DATABASE = 'egosolar_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSafeTestDatabase();
    }

    /**
     * Chặn test nếu connection mặc định không trỏ vào DB test cho phép.
     */
    private function assertSafeTestDatabase(): void
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');

        if ($database !== '' && $database !== ':memory:' && $database !== self::ALLOWED_TEST_DATABASE) {
            self::fail(sprintf(
                'TỪ CHỐI chạy test: DB đang cấu hình là "%s" — chỉ cho phép "%s" để bảo vệ dữ liệu.',
                $database,
                self::ALLOWED_TEST_DATABASE,
            ));
        }
    }

    /**
     * Tạo user kèm role Spatie (tạo role nếu chưa có) — dùng cho feature test
     * cần vượt qua Policy phân quyền theo role.
     *
     * @param  string  $role  Tên role, ví dụ 'admin', 'sales', 'accounting'
     * @param  array<string, mixed>  $attributes  Ghi đè thuộc tính user
     */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Xoá sạch dữ liệu các bảng chỉ định (tắt FK tạm thời) — dùng khi test
     * cần trạng thái bảng rỗng xác định trước.
     */
    protected function truncateTables(string ...$tables): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }
}
