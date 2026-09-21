<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tạo permission `technical.reports.approve` và gán cho role Admin (Giám đốc)
 * cùng role trưởng phòng Kỹ thuật đang có sẵn trong hệ thống.
 *
 * Mục đích: duyệt / yêu cầu sửa / mở lại báo cáo ngày kỹ thuật được quyết định
 * bằng ROLE + PERMISSION có sẵn, không bằng email hay danh sách cứng trong code.
 *
 * Viết theo đúng khuôn của `2026_09_19_120500_add_payment_request_override_permission`:
 * - up() CHỈ THÊM, chạy lại nhiều lần cho kết quả như nhau (idempotent).
 * - down() chỉ gỡ đúng thứ up() đã thêm và không xoá permission nếu nó còn
 *   được gán trực tiếp cho user hoặc cho role khác do người vận hành tự tạo.
 */
return new class extends Migration
{
    private const PERMISSION = 'technical.reports.approve';

    private const GUARD = 'web';

    /** Role được cấp sẵn quyền duyệt báo cáo kỹ thuật. */
    private const TECHNICAL_MANAGER_ROLES = [
        'technical_manager',
        'technical_leader',
        'truong_phong_ky_thuat',
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

        // Đang được gán trực tiếp cho user => dữ liệu phân quyền thật, không đụng.
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
            self::TECHNICAL_MANAGER_ROLES,
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
