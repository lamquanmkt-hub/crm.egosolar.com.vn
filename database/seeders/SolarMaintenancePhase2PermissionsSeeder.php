<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SolarMaintenancePhase2PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = [
            'maintenance.view',
            'maintenance.create',
            'maintenance.update',
            'maintenance.assign',
            'maintenance.submit',
            'maintenance.approve',
            'maintenance.reject',
            'maintenance.request_revision',
            'maintenance.reopen',
            'maintenance.delete',
            'maintenance.restore',
            'maintenance.files.view',
            'maintenance.files.upload',
            'maintenance.files.delete',
            'maintenance.materials.manage',
            'maintenance.reports.view',
            'maintenance.settings.manage',
        ];

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $managerPermissions = array_values(array_diff($all, [
            'maintenance.delete',
            'maintenance.restore',
        ]));

        $technicalManager = Role::findOrCreate('technical_manager', 'web');
        $technicalManager->syncPermissions($managerPermissions);

        foreach (['admin', 'administrator', 'super_admin'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($all);
            }
        }

        foreach (['manager', 'ky_thuat_manager', 'quan_ly_ky_thuat', 'maintenance_manager'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($managerPermissions);
            }
        }

        $technicalPermissions = [
            'maintenance.view',
            'maintenance.update',
            'maintenance.assign',
            'maintenance.submit',
            'maintenance.files.view',
            'maintenance.files.upload',
        ];

        foreach (['ky_thuat', 'technical', 'technician', 'technical_staff', 'technical_leader', 'bao_hanh', 'maintenance'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo($technicalPermissions);
            }
        }

        foreach (['accounting', 'ketoan', 'ke_toan', 'warehouse', 'kho', 'sales', 'sale', 'sales_manager', 'cskh'] as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
            if ($role) {
                $role->givePermissionTo(['maintenance.view', 'maintenance.files.view']);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
