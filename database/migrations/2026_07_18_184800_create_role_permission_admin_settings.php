<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $rolePermissionTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        if (!Schema::hasColumn($rolesTable, 'display_name')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->string('display_name', 120)->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn($rolesTable, 'description')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->text('description')->nullable()->after('display_name');
            });
        }

        if (!Schema::hasColumn($rolesTable, 'is_system')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->boolean('is_system')->default(false)->after('description');
            });
        }

        if (!Schema::hasColumn($rolesTable, 'page_access_enabled')) {
            Schema::table($rolesTable, function (Blueprint $table) {
                $table->boolean('page_access_enabled')->default(false)->after('is_system');
            });
        }

        if (!Schema::hasTable('role_permission_audits')) {
            Schema::create('role_permission_audits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 120)->index();
                $table->string('subject_type', 120)->nullable()->index();
                $table->unsignedBigInteger('subject_id')->nullable()->index();
                $table->string('subject_name')->nullable();
                $table->json('before_data')->nullable();
                $table->json('after_data')->nullable();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        $pagePermissions = array_keys(config('role_permissions.page_permissions', []));
        $systemPermissions = [
            'settings.roles.view',
            'settings.roles.manage',
            'settings.users.manage',
        ];

        foreach (array_merge($pagePermissions, $systemPermissions) as $permissionName) {
            DB::table($permissionsTable)->updateOrInsert(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        $displayNames = [
            'admin' => 'Quản trị viên',
            'management' => 'Ban Giám đốc',
            'accounting' => 'Kế toán',
            'warehouse' => 'Kho',
            'sales' => 'Nhân viên Sales',
            'sales_manager' => 'Quản lý Sales',
            'marketing' => 'Nhân viên Marketing',
            'marketing_manager' => 'Quản lý Marketing',
            'ky_thuat' => 'Kỹ thuật',
            'technical_manager' => 'Quản lý Kỹ thuật',
            'hr' => 'Nhân sự',
        ];

        foreach ($displayNames as $name => $displayName) {
            DB::table($rolesTable)
                ->where('name', $name)
                ->update([
                    'display_name' => DB::raw('COALESCE(display_name, ' . DB::getPdo()->quote($displayName) . ')'),
                    'is_system' => true,
                    'updated_at' => now(),
                ]);
        }

        $adminRoleId = DB::table($rolesTable)
            ->where('name', 'admin')
            ->where('guard_name', 'web')
            ->value('id');

        if ($adminRoleId) {
            DB::table($rolesTable)
                ->where('id', $adminRoleId)
                ->update(['page_access_enabled' => true, 'updated_at' => now()]);

            $permissionIds = DB::table($permissionsTable)
                ->whereIn('name', array_merge($pagePermissions, $systemPermissions))
                ->pluck('id');

            foreach ($permissionIds as $permissionId) {
                DB::table($rolePermissionTable)->updateOrInsert([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
        }
    }

    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $permissionsTable = config('permission.table_names.permissions', 'permissions');

        Schema::dropIfExists('role_permission_audits');

        $permissionNames = array_merge(
            array_keys(config('role_permissions.page_permissions', [])),
            ['settings.roles.view', 'settings.roles.manage', 'settings.users.manage']
        );

        DB::table($permissionsTable)->whereIn('name', $permissionNames)->delete();

        foreach (['page_access_enabled', 'is_system', 'description', 'display_name'] as $column) {
            if (Schema::hasColumn($rolesTable, $column)) {
                Schema::table($rolesTable, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
