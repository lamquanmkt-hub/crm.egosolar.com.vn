<?php

namespace App\Http\Middleware;

use App\Services\RolePermission\PageAccessService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\RoleMiddleware;

class RoleWeb extends RoleMiddleware
{
    public function handle($request, Closure $next, $role, $guard = 'web')
    {
        $authGuard = Auth::guard($guard);
        $user = $authGuard->user();

        if (!$user) {
            throw UnauthorizedException::notLoggedIn();
        }

        $roles = is_array($role)
            ? $role
            : array_values(array_filter(array_map('trim', explode('|', (string) $role))));

        if ($user->hasAnyRole($roles)) {
            return $next($request);
        }

        if (app(PageAccessService::class)->canSatisfyLegacyRole($user, $request, (string) $role)) {
            return $next($request);
        }

        throw UnauthorizedException::forRoles($roles);
    }
}
