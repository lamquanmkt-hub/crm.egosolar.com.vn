<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_consignments')) {
            Schema::table('customer_consignments', function (Blueprint $table) {
                $columns = [
                    'sales_user_id' => fn () => $table->unsignedBigInteger('sales_user_id')->nullable()->index('cc_sales_idx'),
                    'approval_note' => fn () => $table->text('approval_note')->nullable(),
                    'rejected_by' => fn () => $table->unsignedBigInteger('rejected_by')->nullable(),
                    'rejected_at' => fn () => $table->timestamp('rejected_at')->nullable(),
                    'reject_reason' => fn () => $table->text('reject_reason')->nullable(),
                    'revision_requested_by' => fn () => $table->unsignedBigInteger('revision_requested_by')->nullable(),
                    'revision_requested_at' => fn () => $table->timestamp('revision_requested_at')->nullable(),
                    'revision_reason' => fn () => $table->text('revision_reason')->nullable(),
                    'warehouse_issue_date' => fn () => $table->date('warehouse_issue_date')->nullable(),
                    'warehouse_issue_note' => fn () => $table->text('warehouse_issue_note')->nullable(),
                ];
                foreach ($columns as $name => $callback) {
                    if (! Schema::hasColumn('customer_consignments', $name)) {
                        $callback();
                    }
                }
            });

            // Cho phép đơn ký gửi độc lập, không bắt buộc liên kết đơn bán hàng.
            if (Schema::hasColumn('customer_consignments', 'order_id')) {
                try {
                    DB::statement('ALTER TABLE `customer_consignments` MODIFY `order_id` BIGINT UNSIGNED NULL');
                } catch (Throwable) {
                }
            }
            if (Schema::hasColumn('customer_consignments', 'sales_user_id')) {
                DB::table('customer_consignments')
                    ->whereNull('sales_user_id')
                    ->update(['sales_user_id' => DB::raw('COALESCE(created_by, submitted_by)')]);
            }
        }

        if (Schema::hasTable('customer_consignment_items')) {
            Schema::table('customer_consignment_items', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_consignment_items', 'issued_quantity')) {
                    $table->integer('issued_quantity')->default(0)->after('quantity');
                }
                if (! Schema::hasColumn('customer_consignment_items', 'returned_quantity')) {
                    $table->integer('returned_quantity')->default(0)->after('issued_quantity');
                }
                if (! Schema::hasColumn('customer_consignment_items', 'sold_quantity')) {
                    $table->integer('sold_quantity')->default(0)->after('returned_quantity');
                }
                if (! Schema::hasColumn('customer_consignment_items', 'item_note')) {
                    $table->text('item_note')->nullable();
                }
            });
            if (Schema::hasColumn('customer_consignment_items', 'order_item_id')) {
                try {
                    DB::statement('ALTER TABLE `customer_consignment_items` MODIFY `order_item_id` BIGINT UNSIGNED NULL');
                } catch (Throwable) {
                }
            }
        }

        // Đồng bộ lại quyền mới ngay cả khi migration gốc đã chạy trước đó.
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $pivotTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        if (Schema::hasTable($permissionsTable) && Schema::hasTable($rolesTable) && Schema::hasTable($pivotTable)) {
            $permissions = [
                'page.consignments', 'menu.consignments', 'consignments.view',
                'consignments.create', 'consignments.submit', 'consignments.approve',
                'consignments.review', 'consignments.warehouse_issue', 'consignments.cancel',
            ];
            foreach ($permissions as $name) {
                DB::table($permissionsTable)->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
            $all = $permissions;
            $matrix = [
                'admin' => $all, 'management' => $all, 'manager' => $all,
                'director' => $all, 'giam_doc' => $all, 'ceo' => $all,
                'sales_manager' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.create', 'consignments.submit'],
                'sales' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.create', 'consignments.submit'],
                'warehouse' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.warehouse_issue'],
                'kho' => ['page.consignments', 'menu.consignments', 'consignments.view', 'consignments.warehouse_issue'],
            ];
            $ids = DB::table($permissionsTable)->where('guard_name', 'web')->whereIn('name', $permissions)->pluck('id', 'name');
            foreach ($matrix as $roleName => $names) {
                $roleId = DB::table($rolesTable)->where('name', $roleName)->where('guard_name', 'web')->value('id');
                if (! $roleId) continue;
                foreach ($names as $name) {
                    if ($permissionId = ($ids[$name] ?? null)) {
                        DB::table($pivotTable)->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
                    }
                }
            }
            try { app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions(); } catch (Throwable) {}
        }
    }

    public function down(): void
    {
    }
};
