<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class SolarMaintenanceAccess
{
    public static function roles(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $roles = [];

        if (method_exists($user, 'getRoleNames')) {
            foreach ($user->getRoleNames() as $role) {
                $roles[] = self::normalize((string) $role);
            }
        }

        foreach (['role', 'type'] as $field) {
            if (! empty($user->{$field})) {
                $roles[] = self::normalize((string) $user->{$field});
            }
        }

        return array_values(array_unique(array_filter($roles)));
    }

    public static function hasAny(?User $user, array $roles): bool
    {
        $current = self::roles($user);
        $wanted = array_map([self::class, 'normalize'], $roles);

        return count(array_intersect($current, $wanted)) > 0;
    }

    public static function hasPermission(?User $user, string $permission): bool
    {
        if (! $user || ! method_exists($user, 'hasPermissionTo')) {
            return false;
        }

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return ((int) ($user->is_admin ?? 0) === 1)
            || self::hasAny($user, ['admin', 'administrator', 'super_admin']);
    }

    public static function isManager(?User $user): bool
    {
        return self::isAdmin($user)
            || self::hasPermission($user, 'maintenance.approve')
            || self::hasAny($user, [
                'technical_manager',
                'truong_phong_ky_thuat',
                'trưởng_phòng_kỹ_thuật',
                'maintenance_manager',
                'ky_thuat_manager',
                'quan_ly_ky_thuat',
                'manager',
            ]);
    }

    public static function isTechnician(?User $user): bool
    {
        return self::isManager($user)
            || self::hasPermission($user, 'maintenance.submit')
            || self::hasAny($user, [
                'ky_thuat',
                'technical',
                'technician',
                'technical_staff',
                'technical_leader',
                'bao_hanh',
                'maintenance',
            ])
            || self::hasTechnicalOrganization($user);
    }

    public static function isSelectableTechnician(?User $user): bool
    {
        if (! $user || ($user->getAttribute('is_active') !== null && ! (bool) $user->getAttribute('is_active'))) {
            return false;
        }

        return self::hasAny($user, [
            'ky_thuat',
            'technical',
            'technician',
            'technical_staff',
            'technical_leader',
            'technical_manager',
            'maintenance_manager',
            'ky_thuat_manager',
            'quan_ly_ky_thuat',
            'bao_hanh',
            'maintenance',
        ]) || self::hasTechnicalOrganization($user);
    }

    public static function canViewAny(?User $user): bool
    {
        return self::isTechnician($user)
            || self::hasPermission($user, 'maintenance.view')
            || self::hasAny($user, [
                'accounting', 'ketoan', 'ke_toan',
                'warehouse', 'kho',
                'sales', 'sale', 'sales_manager',
                'cskh', 'customer_service',
            ]);
    }

    public static function canManage(?User $user): bool
    {
        return self::isManager($user)
            || self::isTechnician($user)
            || self::hasPermission($user, 'maintenance.update');
    }

    public static function canApprove(?User $user): bool
    {
        return self::isAdmin($user)
            || self::isManager($user)
            || self::hasPermission($user, 'maintenance.approve');
    }

    public static function isTechnicianOnly(?User $user): bool
    {
        return self::isTechnician($user)
            && ! self::isManager($user)
            && ! self::isAdmin($user);
    }

    private static function hasTechnicalOrganization(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        try {
            $user->loadMissing(['department', 'position']);
        } catch (\Throwable) {
            // Relations may not be available in an old installation.
        }

        $values = [
            data_get($user, 'department.name'),
            data_get($user, 'department.code'),
            data_get($user, 'position.name'),
            data_get($user, 'position.code'),
        ];

        $joined = self::normalize(implode(' ', array_filter($values)));

        return str_contains($joined, 'ky_thuat')
            || str_contains($joined, 'technical')
            || str_contains($joined, 'bao_hanh')
            || str_contains($joined, 'maintenance');
    }

    private static function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower(trim($value), 'UTF-8'));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?: '';

        return trim($value, '_');
    }
}
