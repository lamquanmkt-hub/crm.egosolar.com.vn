<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class GiftAccess
{
    private const MANAGER_ROLES = [
        'admin', 'management', 'hr', 'hr_manager', 'human_resources',
    ];

    private const STOCK_ROLES = [
        'admin', 'management', 'hr', 'hr_manager', 'human_resources',
        'warehouse', 'kho',
    ];

    private const ALL_REQUEST_ROLES = [
        'admin', 'management', 'hr', 'hr_manager', 'human_resources',
        'warehouse', 'kho', 'sales_manager',
    ];

    public static function canManage(User $user): bool
    {
        return self::hasAnyRole($user, self::MANAGER_ROLES);
    }

    public static function canHandleStock(User $user): bool
    {
        return self::hasAnyRole($user, self::STOCK_ROLES);
    }

    public static function canApprove(User $user): bool
    {
        return self::canManage($user);
    }

    public static function canSeeCost(User $user): bool
    {
        return self::canHandleStock($user);
    }

    public static function canSeeAllRequests(User $user): bool
    {
        return self::hasAnyRole($user, self::ALL_REQUEST_ROLES);
    }

    public static function canRequest(User $user): bool
    {
        return (bool) $user->getKey();
    }

    public static function hasAnyRole(User $user, array $roles): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        if (method_exists($user, 'hasRole')) {
            foreach ($roles as $role) {
                if ($user->hasRole($role)) {
                    return true;
                }
            }
        }

        $legacyRole = strtolower(trim((string) ($user->role ?? '')));

        return $legacyRole !== '' && in_array($legacyRole, $roles, true);
    }
}
