<?php

namespace App\Http\Middleware;

use App\Contracts\Services\PageAccessServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePageAccess
{
    public function __construct(private readonly PageAccessServiceInterface $pageAccess) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        /* EGO_EMPLOYEE_SELF_SERVICE_PUBLIC_ROUTES_V1
         * Các trang tự phục vụ nhân sự luôn mở cho mọi tài khoản đã đăng nhập.
         * Quyền duyệt/chuyển người duyệt vẫn được kiểm tra trong Controller.
         */
        $routeName = optional($request->route())->getName();

        if (
            $routeName
            && \Illuminate\Support\Str::is([
                'hr.attendance.my',
                'hr.attendance.checkin',
                'hr.attendance.checkout',
                'hr.attendance.guide.*',
                'hr.leave.index',
                'hr.leave.create',
                'hr.leave.store',
                'hr.leave.approve',
                'hr.leave.reject',
                'hr.leave.transfer-approver',
                'hr.leave.cancel',
                'hr.leave.attachments.*',
                'hr.online-work.create',
            ], $routeName)
        ) {
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
