<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tạo permission `payment_requests.override_locked` và gán cho role `admin`.
 *
 * Thay thế cơ chế hardcode email trước đây: Admin (Giám đốc) được sửa/xóa
 * ĐNTT và công nợ đã hoàn tất thông qua role admin; nhân sự khác chỉ được
 * khi Admin gán riêng permission này trong màn hình Phân quyền.
 *
 * Nguyên tắc an toàn của migration này:
 * - up() CHỈ THÊM. Nếu permission (hoặc phân công role) đã tồn tại từ trước
 *   thì giữ nguyên, không ghi đè `created_at`/`updated_at` của bản ghi cũ.
 *   Chạy lại nhiều lần cho kết quả giống hệt nhau (idempotent).
 * - down() CHỈ GỠ ĐÚNG THỨ up() ĐÃ THÊM, và tuyệt đối không xóa permission
 *   nếu nó còn đang được gán trực tiếp cho user hoặc cho role khác ngoài
 *   nhóm admin — tránh phá dữ liệu phân quyền do người vận hành tự tạo.
 */
return new class extends Migration
{
    private const PERMISSION = 'payment_requests.override_locked';

    private const GUARD = 'web';

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

        // Chỉ insert khi chưa có. KHÔNG updateOrInsert để không đụng vào
        // timestamps của bản ghi đã tồn tại từ trước.
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

        foreach ($this->adminRoleIds($rolesTable) as $roleId) {
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

        // 1) Nếu permission đang được gán TRỰC TIẾP cho user nào đó thì dừng
        //    hẳn: đó là dữ liệu phân quyền thật, không thuộc phạm vi migration.
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

        // 2) Gỡ khỏi đúng các role admin mà up() đã gán.
        $adminRoleIds = $this->adminRoleIds($rolesTable);

        if ($adminRoleIds !== []) {
            DB::table($rolePermissionTable)
                ->where('permission_id', $permissionId)
                ->whereIn('role_id', $adminRoleIds)
                ->delete();
        }

        // 3) Nếu vẫn còn role khác (do người vận hành tự gán) đang dùng
        //    permission này thì GIỮ LẠI bản ghi permission.
        $stillUsed = DB::table($rolePermissionTable)
            ->where('permission_id', $permissionId)
            ->exists();

        if (! $stillUsed) {
            DB::table($permissionsTable)->where('id', $permissionId)->delete();
        }

        $this->forgetPermissionCache();
    }

    /**
     * Id của các role được coi là admin theo config hiện hành.
     *
     * @return array<int, int>
     */
    private function adminRoleIds(string $rolesTable): array
    {
        $names = (array) config('role_permissions.admin_roles', ['admin']);

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
