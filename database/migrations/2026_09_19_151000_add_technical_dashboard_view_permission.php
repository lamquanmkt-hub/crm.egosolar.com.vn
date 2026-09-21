<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permission `technical.dashboard.view` — Dashboard kết quả Kỹ thuật (chỉ xem).
 *
 * Mặc định gán cho role Admin / Ban giám đốc. Trưởng phòng KHÔNG được gán ở đây:
 * họ dùng trang "Tổng quan phòng" / "Tổng kết tuần" là trang vận hành của mình.
 * (Nếu vận hành muốn cho một cá nhân xem Dashboard thì gán permission này.)
 *
 * Viết theo đúng khuôn `2026_09_19_140500_add_technical_report_approve_permission`:
 * up() chỉ thêm và chạy lại được nhiều lần; down() chỉ gỡ đúng thứ up() đã thêm.
 */
return new class extends Migration
{
    private const PERMISSION = 'technical.dashboard.view';

    private const GUARD = 'web';

    /** Role Ban giám đốc đang có sẵn trong hệ thống. */
    private const EXECUTIVE_ROLES = [
        'management', 'director', 'ceo', 'giam_doc', 'ban_giam_doc',
    ];

    public function up(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $rolePermissionTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        if (
            ! Schema::hasTable($permissionsTable)
            || ! Schema::hasTable($rolesTable)
            || ! Schema::hasTable($rolePermissionTable)
        ) {
            return;
        }

        $permissionId = DB::table($permissionsTable)
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if (! $permissionId) {
            $permissionId = DB::table($permissionsTable)->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! $permissionId) {
            return;
        }

        foreach ($this->targetRoleIds($rolesTable) as $roleId) {
            $alreadyGranted = DB::table($rolePermissionTable)
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (! $alreadyGranted) {
                DB::table($rolePermissionTable)->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $this->forgetPermissionCache();
    }

    public function down(): void
    {
        $permissionsTable = config('permission.table_names.permissions', 'permissions');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $rolePermissionTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $modelPermissionTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');

        if (! Schema::hasTable($permissionsTable) || ! Schema::hasTable($rolesTable)) {
            return;
        }

        $permissionId = DB::table($permissionsTable)
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->value('id');

        if (! $permissionId) {
            return;
        }

        if (Schema::hasTable($modelPermissionTable)) {
            $assignedToUsers = DB::table($modelPermissionTable)
                ->where('permission_id', $permissionId)
                ->exists();

            if ($assignedToUsers) {
                $this->forgetPermissionCache();

                return;
            }
        }

        if (! Schema::hasTable($rolePermissionTable)) {
            return;
        }

        $roleIds = $this->targetRoleIds($rolesTable);

        if ($roleIds !== []) {
            DB::table($rolePermissionTable)
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }

        $stillUsed = DB::table($rolePermissionTable)
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $stillUsed) {
            DB::table($permissionsTable)->where('id', $permissionId)->delete();
        }

        $this->forgetPermissionCache();
    }

    /** @return array<int, int> */
    private function targetRoleIds(string $rolesTable): array
    {
        $names = array_values(array_unique(array_merge(
            (array) config('role_permissions.admin_roles', ['admin']),
            self::EXECUTIVE_ROLES,
        )));

        if ($names === []) {
            return [];
        }

        return DB::table($rolesTable)
            ->whereIn('name', $names)
            ->where('guard_name', self::GUARD)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function forgetPermissionCache(): void
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (Throwable) {
        }
    }
};
