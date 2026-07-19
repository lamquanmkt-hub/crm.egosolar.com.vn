<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class WarehousePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'warehouse']);

        $permissions = [
            // 👉 thêm khách hàng
            'customer.view_all',
            'customer.create',

            // 👉 tạo & xem đơn hàng
            'order.create',
            'order.view_all',

            // giữ quyền cũ
            'product.view',
            'warehouse.view',
            'warehouse.stock_check',
            'warehouse.export',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $role->syncPermissions($permissions);
    }
}
