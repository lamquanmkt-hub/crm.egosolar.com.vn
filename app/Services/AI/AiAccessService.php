<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Collection;

final class AiAccessService
{
    public function canUse(User $user): bool
    {
        return $this->isAdmin($user) || $user->can((string) config('ego_ai.permissions.use', 'ai.use'));
    }

    public function isAdmin(User $user): bool
    {
        return method_exists($user, 'hasRole') && $user->hasRole('admin');
    }

    public function isManagement(User $user): bool
    {
        return method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'management']);
    }

    /** @return Collection<int, string> */
    public function roleNames(User $user): Collection
    {
        if (method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->map(fn ($role) => strtolower((string) $role))->values();
        }

        return collect(array_filter([strtolower((string) ($user->role ?? ''))]));
    }

    public function canModule(User $user, string $module): bool
    {
        if (! $this->canUse($user)) {
            return false;
        }

        $definition = config("ego_ai.modules.{$module}");
        if (! is_array($definition)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        $permission = (string) ($definition['permission'] ?? '');
        if ($permission === '' || ! $user->can($permission)) {
            return false;
        }

        if ($this->scopeFor($user, $module) === 'none') {
            return false;
        }

        $pagePermissions = array_values(array_filter((array) ($definition['page_permissions'] ?? [])));
        if ($pagePermissions === []) {
            return true;
        }

        foreach ($pagePermissions as $pagePermission) {
            if ($user->can((string) $pagePermission)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<string, mixed>> */
    public function allowedModules(User $user): array
    {
        $modules = [];
        foreach ((array) config('ego_ai.modules', []) as $key => $definition) {
            if (! $this->canModule($user, (string) $key)) {
                continue;
            }

            $modules[(string) $key] = [
                'key' => (string) $key,
                'label' => (string) ($definition['label'] ?? $key),
                'permission' => (string) ($definition['permission'] ?? ''),
                'scope' => $this->scopeFor($user, (string) $key),
                'scope_label' => $this->scopeLabel($this->scopeFor($user, (string) $key)),
                'icon' => (string) ($definition['icon'] ?? 'bi-stars'),
            ];
        }

        return $modules;
    }

    public function scopeFor(User $user, string $module): string
    {
        if ($this->isAdmin($user)) {
            return 'company';
        }

        $roles = $this->roleNames($user);
        $priority = [
            'management',
            'accounting',
            'warehouse',
            'sales_manager',
            'technical_manager',
            'marketing_manager',
            'hr',
            'sales',
            'ky_thuat',
            'marketing',
        ];

        foreach ($priority as $role) {
            if (! $roles->contains($role)) {
                continue;
            }

            $matrix = (array) config("ego_ai.role_scopes.{$role}", []);
            if (isset($matrix[$module])) {
                return (string) $matrix[$module];
            }
            if (isset($matrix['*'])) {
                return (string) $matrix['*'];
            }
        }

        return (string) config("ego_ai.role_scopes.default.{$module}", 'none');
    }

    public function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'company' => 'Toàn công ty',
            'department' => 'Phòng ban của bạn',
            'self' => 'Dữ liệu của bạn',
            default => 'Không được phép',
        };
    }

    /** @return array<int, int> */
    public function scopedUserIds(User $user, string $scope): array
    {
        if ($scope === 'company') {
            return [];
        }

        if ($scope === 'self') {
            return [(int) $user->id];
        }

        if ($scope === 'department') {
            if (! $user->department_id) {
                return [(int) $user->id];
            }

            return User::query()
                ->where('department_id', $user->department_id)
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        }

        return [];
    }

    public function dailyLimit(User $user): int
    {
        $roles = $this->roleNames($user);
        foreach (['admin', 'management', 'accounting', 'sales_manager', 'technical_manager', 'marketing_manager', 'hr', 'warehouse', 'sales', 'ky_thuat', 'marketing'] as $role) {
            if ($roles->contains($role)) {
                return (int) config("ego_ai.daily_limits.{$role}", 80);
            }
        }

        return (int) config('ego_ai.daily_limits.default', 80);
    }

    /** @return array<string, mixed> */
    public function profile(User $user): array
    {
        $modules = $this->allowedModules($user);

        return [
            'roles' => $this->roleNames($user)->values()->all(),
            'department_id' => $user->department_id ? (int) $user->department_id : null,
            'department_name' => optional($user->department)->name,
            'daily_limit' => $this->dailyLimit($user),
            'modules' => array_values($modules),
        ];
    }
}
