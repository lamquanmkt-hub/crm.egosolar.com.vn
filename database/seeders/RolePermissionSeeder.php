<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Xóa cache role/permission cũ
        //        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        //
        //        $rolesPermissions = config('permissions');
        //
        //        // ====== Tạo permissions ======
        //        foreach ($rolesPermissions as $roleName => $perms) {
        //            if (isset($perms['*'])) continue;
        //
        //            foreach ($perms as $perm => $desc) {
        //                Permission::firstOrCreate([
        //                    'name' => $perm,
        //                    'guard_name' => 'web',
        //                ]);
        //            }
        //        }
        //
        //        // ====== Tạo roles và gắn permissions ======
        //        foreach ($rolesPermissions as $roleName => $perms) {
        //            $role = Role::firstOrCreate([
        //                'name' => $roleName,
        //                'guard_name' => 'web',
        //            ]);
        //
        //            if (isset($perms['*'])) {
        //                $role->syncPermissions(Permission::all());
        //            } else {
        //                $role->syncPermissions(array_keys($perms));
        //            }
        //        }

        // ====== Tạo users demo ======
        // Mật khẩu KHÔNG hardcode: lấy từ env SEED_ADMIN_PASSWORD, nếu thiếu thì
        // sinh ngẫu nhiên và in ra console một lần (tránh lộ mật khẩu mặc định trên production).
        $adminPassword = env('SEED_ADMIN_PASSWORD') ?: \Illuminate\Support\Str::random(16);

        $users = [
            ['name' => 'Admin', 'email' => 'admin@egosolar.test', 'password' => $adminPassword, 'role' => 'admin'],
            //			['name' => 'Marketing Team', 'email' => 'marketing@egosolar.vn', 'password' => '12345678', 'role' => 'marketing'],
            //			['name' => 'Sales Team', 'email' => 'sales@egosolar.test', 'password' => '12345678', 'role' => 'sales'],
            //			['name' => 'Kế toán', 'email' => 'ketoan@egosolar.test', 'password' => '12345678', 'role' => 'accounting'],
            //			['name' => 'Kho', 'email' => 'warehouse@egosolar.vn', 'password' => '12345678', 'role' => 'warehouse'],
            //            ['name' => 'Giám sát vùng', 'email' => 'giamsat@egosolar.vn', 'password' => '12345678', 'role' => 'sales_manager'],
            //            ['name' => 'Giám đốc', 'email' => 'manager@egosolar.test', 'password' => '12345678', 'role' => 'management'],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => Hash::make($userData['password'])]
            );
            $user->syncRoles($userData['role']);

            if ($user->wasRecentlyCreated && ! env('SEED_ADMIN_PASSWORD')) {
                echo "⚠️  Mật khẩu tạm cho {$userData['email']}: {$userData['password']} — đổi ngay sau khi đăng nhập.\n";
            }
        }

        echo "✅ RolePermissionSeeder: seeded successfully.\n";
    }
}
