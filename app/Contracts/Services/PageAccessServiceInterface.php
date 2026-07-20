<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Hợp đồng Phân quyền truy cập trang theo role/permission, dùng bởi middleware EnforcePageAccess.
 *
 * Sinh từ implementation PageAccessService của chính dự án này (KHÔNG copy từ crm-shop —
 * signature hai codebase đã phân kỳ).
 */
interface PageAccessServiceInterface
{
    public function definitions(): array;

    public function permissionForRequest(Request $request): ?string;

    public function isAdmin(User $user): bool;

    public function pageControlEnabled(User $user): bool;

    public function canAccess(User $user, string $pagePermission): bool;

    public function canSatisfyLegacyRole(User $user, Request $request, string $roleExpression): bool;

    public function deniedNavigationRules(User $user): array;

    public function permissionGroups(Collection $permissions): array;

    public function displayRoleName($role): string;
}
