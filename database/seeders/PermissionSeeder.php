<?php

namespace Database\Seeders;

use App\Access\RolePermissionMatrix;
use App\Enums\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

/**
 * PermissionSeeder - Sync permissions from code to database
 * 
 * Run: php artisan db:seed --class=PermissionSeeder
 * 
 * This ensures database is always in sync with Permission enum
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cache to prevent issues
        app()['cache']->forget('spatie.permission.cache');

        // Sync all permissions
        $this->syncPermissions();

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        echo "\n✅ Permissions synced successfully!\n";
    }

    /**
     * Create/update permissions from Permission enum
     */
    private function syncPermissions(): void
    {
        foreach (Permission::cases() as $permission) {
            PermissionModel::firstOrCreate(
                ['name' => $permission->value, 'guard_name' => 'web'],
                ['description' => $permission->description()]
            );
        }

        echo "✓ Permissions synced\n";
    }

    /**
     * Assign permissions to roles
     */
    private function assignPermissionsToRoles(): void
    {
        foreach (RolePermissionMatrix::getAllRoles() as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['description' => RolePermissionMatrix::getRoleDescription($roleName)]
            );

            $permissions = array_map(
                fn (Permission $p) => $p->value,
                RolePermissionMatrix::getPermissions($roleName)
            );

            // Sync permissions (remove old, add new)
            $role->syncPermissions($permissions);

            echo "✓ Role '{$roleName}' synced\n";
        }
    }
}
