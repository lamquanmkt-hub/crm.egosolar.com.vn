<?php

namespace App\Http\Middleware;

use App\Services\RolePermission\PageAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePageAccess
{
    public function __construct(private readonly PageAccessService $pageAccess)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $permission = $this->pageAccess->permissionForRequest($request);

        if ($permission === null || $this->pageAccess->canAccess($user, $permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Bạn không được cấp quyền truy cập chức năng này.',
                'required_permission' => $permission,
            ], 403);
        }

        abort(403, 'Bạn không được cấp quyền truy cập trang này. Vui lòng liên hệ quản trị viên.');
    }
}
