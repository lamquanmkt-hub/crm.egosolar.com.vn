<?php

namespace App\Access;

use App\Enums\Permission;

/**
 * PermissionHelper - Helper functions for permissions
 * 
 * Usage in Controllers:
 * if (PermissionHelper::can(Permission::ORDER_VIEW_ALL)) {
 *     // Show order list
 * }
 */
class PermissionHelper
{
    /**
     * Check if user has permission
     */
    public static function can(Permission $permission): bool
    {
        return auth()->check() && auth()->user()->can($permission->value);
    }

    /**
     * Check if user has any permission
     */
    public static function canAny(Permission ...$permissions): bool
    {
        return auth()->check() && auth()->user()->canAny(
            ...array_map(fn (Permission $p) => $p->value, $permissions)
        );
    }

    /**
     * Check if user has all permissions
     */
    public static function canAll(Permission ...$permissions): bool
    {
        return auth()->check() && auth()->user()->can(
            array_map(fn (Permission $p) => $p->value, $permissions)
        );
    }

    /**
     * Check if user has role
     */
    public static function hasRole(string $role): bool
    {
        return auth()->check() && auth()->user()->hasRole($role);
    }

    /**
     * Check if user has any role
     */
    public static function hasAnyRole(string ...$roles): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(...$roles);
    }

    /**
     * Get user roles
     */
    public static function getRoles(): array
    {
        return auth()->check() ? auth()->user()->roles->pluck('name')->toArray() : [];
    }

    /**
     * Get user permissions
     */
    public static function getPermissions(): array
    {
        return auth()->check() ? auth()->user()->getPermissionsViaRoles()->pluck('name')->toArray() : [];
    }
}
