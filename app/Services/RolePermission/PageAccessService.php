<?php

namespace App\Services\RolePermission;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Service kiểm soát quyền truy cập trang theo role/permission (page.*) từ cấu hình role_permissions.
 */
class PageAccessService
{
    private ?bool $rolesHavePageFlag = null;

    /**
     * Lấy định nghĩa các quyền trang từ config role_permissions.page_permissions.
     */
    public function definitions(): array
    {
        return config('role_permissions.page_permissions', []);
    }

    /**
     * Xác định quyền trang tương ứng với request theo tên route, path chính xác hoặc prefix.
     */
    public function permissionForRequest(Request $request): ?string
    {
        $routeName = optional($request->route())->getName();
        $path = '/' . ltrim($request->path(), '/');
        $path = $path === '//' ? '/' : $path;

        foreach ($this->definitions() as $permission => $definition) {
            foreach ($definition['routes'] ?? [] as $pattern) {
                if ($routeName && Str::is($pattern, $routeName)) {
                    return $permission;
                }
            }

            if (in_array($path, $definition['exact_paths'] ?? [], true)) {
                return $permission;
            }

            foreach ($definition['path_prefixes'] ?? [] as $prefix) {
                $prefix = '/' . trim($prefix, '/');
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return $permission;
                }
            }
        }

        return null;
    }

    /**
     * Kiểm tra người dùng có thuộc nhóm role admin theo cấu hình.
     */
    public function isAdmin(User $user): bool
    {
        return $user->hasAnyRole(config('role_permissions.admin_roles', ['admin']));
    }

    /**
     * Kiểm tra có bật kiểm soát quyền trang cho người dùng (admin luôn miễn kiểm soát).
     */
    public function pageControlEnabled(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return false;
        }

        $user->loadMissing('roles');

        if ($this->rolesHavePageFlag()) {
            return $user->roles->contains(
                fn ($role) => (bool) ($role->page_access_enabled ?? false)
            );
        }

        if (!config('role_permissions.legacy_compatibility', true)) {
            return true;
        }

        return $user->getAllPermissions()
            ->contains(fn ($permission) => str_starts_with($permission->name, 'page.'));
    }

    /**
     * Kiểm tra người dùng được truy cập trang: admin hoặc chưa bật kiểm soát thì luôn cho phép.
     */
    public function canAccess(User $user, string $pagePermission): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (!$this->pageControlEnabled($user)) {
            return true;
        }

        return $user->can($pagePermission);
    }

    /**
     * Cho phép vượt qua middleware role cũ nếu người dùng có quyền trang tương ứng (legacy fallback).
     */
    public function canSatisfyLegacyRole(User $user, Request $request, string $roleExpression): bool
    {
        if (!$this->pageControlEnabled($user)) {
            return false;
        }

        $allowedExpressions = collect(config('role_permissions.legacy_role_fallbacks', []))
            ->map(fn ($item) => $this->normalizeRoleExpression((string) $item));

        if (!$allowedExpressions->contains($this->normalizeRoleExpression($roleExpression))) {
            return false;
        }

        $permission = $this->permissionForRequest($request);

        return $permission !== null && $user->can($permission);
    }

    /**
     * Lấy danh sách rule các trang người dùng KHÔNG được truy cập (dùng ẩn menu điều hướng).
     */
    public function deniedNavigationRules(User $user): array
    {
        if (!$this->pageControlEnabled($user)) {
            return [];
        }

        $rules = [];

        foreach ($this->definitions() as $permission => $definition) {
            if ($user->can($permission)) {
                continue;
            }

            $rules[] = [
                'permission' => $permission,
                'exact' => array_values($definition['exact_paths'] ?? []),
                'prefixes' => array_values($definition['path_prefixes'] ?? []),
                'exclude_prefixes' => array_values($definition['exclude_prefixes'] ?? []),
            ];
        }

        return $rules;
    }

    /**
     * Gom danh sách permission thành các nhóm hiển thị (nhóm quyền trang đưa lên đầu).
     */
    public function permissionGroups(Collection $permissions): array
    {
        $groups = [];
        $pageDefinitions = $this->definitions();

        foreach ($permissions as $permission) {
            $name = $permission->name;
            $isPage = isset($pageDefinitions[$name]);
            $meta = $isPage
                ? $pageDefinitions[$name]
                : $this->metaForBusinessPermission($name);

            $groupKey = $isPage ? '__page_access' : ($meta['group_key'] ?? 'other');

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => $isPage ? 'Quyền truy cập trang' : ($meta['group_label'] ?? 'Quyền khác'),
                    'icon' => $isPage ? 'bi-grid-1x2' : ($meta['group_icon'] ?? 'bi-shield-check'),
                    'is_page_group' => $isPage,
                    'permissions' => [],
                ];
            }

            $groups[$groupKey]['permissions'][] = [
                'permission' => $permission,
                'label' => $meta['label'] ?? $this->humanize($name),
                'description' => $meta['description'] ?? $name,
                'icon' => $meta['icon'] ?? 'bi-check2-circle',
                'is_page' => $isPage,
            ];
        }

        if (isset($groups['__page_access'])) {
            $pageGroup = $groups['__page_access'];
            unset($groups['__page_access']);
            $groups = ['__page_access' => $pageGroup] + $groups;
        }

        return array_values($groups);
    }

    /**
     * Lấy tên hiển thị tiếng Việt của role (ưu tiên display_name).
     */
    public function displayRoleName($role): string
    {
        if (!empty($role->display_name)) {
            return $role->display_name;
        }

        return match ($role->name) {
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
            default => Str::headline(str_replace(['-', '.'], '_', $role->name)),
        };
    }

    /**
     * Xây metadata hiển thị (nhóm, nhãn, icon) cho permission nghiệp vụ theo cấu hình.
     */
    private function metaForBusinessPermission(string $name): array
    {
        $parts = explode('.', $name);
        $prefix = $parts[0] ?? 'other';
        $action = implode('.', array_slice($parts, 1));
        $group = config("role_permissions.group_labels.{$prefix}", [
            'label' => Str::headline(str_replace(['-', '_'], ' ', $prefix)),
            'icon' => 'bi-shield-check',
        ]);

        $actionKey = str_replace('.', '_', $action);
        $actionLabel = config("role_permissions.action_labels.{$actionKey}");

        if (!$actionLabel) {
            $last = end($parts) ?: $name;
            $actionLabel = config('role_permissions.action_labels.' . str_replace('-', '_', $last));
        }

        return [
            'group_key' => $prefix,
            'group_label' => $group['label'],
            'group_icon' => $group['icon'],
            'label' => $actionLabel ?: $this->humanize($name),
            'description' => $name,
            'icon' => 'bi-check2-circle',
        ];
    }

    /**
     * Chuyển chuỗi kỹ thuật (dấu chấm, gạch) thành dạng tiêu đề dễ đọc.
     */
    private function humanize(string $value): string
    {
        return Str::headline(str_replace(['.', '-', '_'], ' ', $value));
    }

    /**
     * Kiểm tra bảng roles có cột page_access_enabled hay không (cache trong request).
     */
    private function rolesHavePageFlag(): bool
    {
        if ($this->rolesHavePageFlag === null) {
            $this->rolesHavePageFlag = Schema::hasColumn(
                config('permission.table_names.roles', 'roles'),
                'page_access_enabled'
            );
        }

        return $this->rolesHavePageFlag;
    }

    /**
     * Chuẩn hoá biểu thức role (a|b|c): trim, bỏ trùng, sắp xếp để so sánh ổn định.
     */
    private function normalizeRoleExpression(string $expression): string
    {
        $roles = array_values(array_unique(array_filter(array_map(
            fn ($role) => trim($role),
            explode('|', $expression)
        ))));

        sort($roles);

        return implode('|', $roles);
    }
}
