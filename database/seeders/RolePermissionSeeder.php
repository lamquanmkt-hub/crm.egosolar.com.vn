<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
		$users = [
			['name' => 'Admin', 'email' => 'admin@egosolar.test', 'password' => '12345678', 'role' => 'admin'],
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
		}

		echo "✅ RolePermissionSeeder: seeded successfully.\n";
	}
}
