<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $rolePermissionTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        if (! Schema::hasTable($rolesTable) || ! Schema::hasTable($permissionsTable)) {
            return;
        }

        $pagePermissions = array_keys(config('role_permissions.page_permissions', []));
        $menuPermissions = array_keys(config('role_permissions.menu_permissions', []));
        $systemPermissions = [
            'settings.roles.view',
            'settings.roles.manage',
            'settings.users.manage',
            'settings.pages.manage',
            'settings.menus.manage',
            'settings.audit.view',
        ];

        foreach (array_merge($pagePermissions, $menuPermissions, $systemPermissions) as $permissionName) {
            DB::table($permissionsTable)->updateOrInsert(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        $commonPages = [
            'page.dashboard',
            'page.booking',
            'page.payment_requests',
            'page.proposals',
            'page.tasks',
            'page.chat',
            'page.company',
            'page.solar',
        ];

        $commonMenus = [
            'menu.dashboard',
            'menu.booking',
            'menu.payment_requests',
            'menu.proposals',
            'menu.tasks',
            'menu.company',
        ];

        $matrix = [
            'management' => [
                'pages' => array_values(array_diff($pagePermissions, ['page.settings', 'page.users'])),
                'menus' => array_values(array_diff($menuPermissions, ['menu.settings'])),
            ],
            'accounting' => [
                'pages' => array_merge($commonPages, [
                    'page.customers', 'page.orders', 'page.sites', 'page.products',
                    'page.warehouses', 'page.finance',
                ]),
                'menus' => array_merge($commonMenus, [
                    'menu.customers', 'menu.orders', 'menu.sites', 'menu.products', 'menu.finance',
                ]),
            ],
            'warehouse' => [
                'pages' => array_merge($commonPages, [
                    'page.customers', 'page.orders', 'page.sites', 'page.products', 'page.warehouses',
                ]),
                'menus' => array_merge($commonMenus, [
                    'menu.customers', 'menu.orders', 'menu.project_test', 'menu.sites', 'menu.products',
                ]),
            ],
            'sales' => [
                'pages' => array_merge($commonPages, [
                    'page.customers', 'page.orders', 'page.sites', 'page.sales', 'page.products',
                ]),
                'menus' => array_merge($commonMenus, [
                    'menu.customers', 'menu.orders', 'menu.project_test', 'menu.sites', 'menu.sales', 'menu.products',
                ]),
            ],
            'sales_manager' => [
                'pages' => array_merge($commonPages, [
                    'page.customers', 'page.orders', 'page.sites', 'page.sales', 'page.products',
                ]),
                'menus' => array_merge($commonMenus, [
                    'menu.customers', 'menu.orders', 'menu.project_test', 'menu.sites', 'menu.sales', 'menu.products',
                ]),
            ],
            'marketing' => [
                'pages' => array_merge($commonPages, ['page.marketing']),
                'menus' => array_merge($commonMenus, ['menu.marketing']),
            ],
            'marketing_manager' => [
                'pages' => array_merge($commonPages, ['page.marketing']),
                'menus' => array_merge($commonMenus, ['menu.marketing']),
            ],
            'ky_thuat' => [
                'pages' => array_merge($commonPages, [
                    'page.sites', 'page.technical', 'page.products', 'page.warehouses',
                ]),
                'menus' => array_merge($commonMenus, [
                    'menu.project_test', 'menu.sites', 'menu.technical', 'menu.products',
                ]),
            ],
            'technical_manager' => [
                'pages' => array_merge($commonPages, [
                    'page.sites', 'page.technical', 'page.products', 'page.warehouses',
                ]),
                'menus' => array_merge($commonMenus, [
                    'menu.project_test', 'menu.sites', 'menu.technical', 'menu.products',
                ]),
            ],
            'hr' => [
                'pages' => array_merge($commonPages, ['page.hr']),
                'menus' => array_merge($commonMenus, ['menu.hr']),
            ],
        ];

        $permissionIds = DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', array_merge($pagePermissions, $menuPermissions, $systemPermissions))
            ->pluck('id', 'name');

        $adminRoleId = DB::table($rolesTable)
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($adminRoleId) {
            DB::table($rolesTable)
                ->where('id', $adminRoleId)
                ->update(['page_access_enabled' => true, 'updated_at' => now()]);

            foreach (array_merge($pagePermissions, $menuPermissions, $systemPermissions) as $permissionName) {
                $permissionId = $permissionIds[$permissionName] ?? null;

                if ($permissionId) {
                    DB::table($rolePermissionTable)->updateOrInsert([
                        'role_id' => $adminRoleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }

        foreach ($matrix as $roleName => $access) {
            $roleId = DB::table($rolesTable)
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->value('id');

            if (! $roleId) {
                continue;
            }

            DB::table($rolesTable)
                ->where('id', $roleId)
                ->update(['page_access_enabled' => true, 'updated_at' => now()]);

            $names = array_values(array_unique(array_merge($access['pages'], $access['menus'])));

            foreach ($names as $permissionName) {
                $permissionId = $permissionIds[$permissionName] ?? null;

                if ($permissionId) {
                    DB::table($rolePermissionTable)->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');

        if (! Schema::hasTable($permissionsTable)) {
            return;
        }

        DB::table($permissionsTable)
            ->whereIn('name', array_merge(
                array_keys(config('role_permissions.menu_permissions', [])),
                ['settings.pages.manage', 'settings.menus.manage', 'settings.audit.view']
            ))
            ->delete();

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
        }
    }
};
