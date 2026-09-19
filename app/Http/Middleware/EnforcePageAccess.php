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
        /* EGO_SERIAL_WARRANTY_WAREHOUSE_ACCESS_START */
        if ($request->is('serial-warranty') || $request->is('serial-warranty/*')) {
            $egoSerialUser = $request->user();

            if ($egoSerialUser && (
            (method_exists($egoSerialUser, 'hasAnyRole') && $egoSerialUser->hasAnyRole(['admin', 'warehouse', 'kho'])) ||
            (method_exists($egoSerialUser, 'hasRole') && (
                $egoSerialUser->hasRole('admin') ||
                $egoSerialUser->hasRole('warehouse') ||
                $egoSerialUser->hasRole('kho')
            )) ||
            (isset($egoSerialUser->role) && in_array(strtolower((string) $egoSerialUser->role), ['admin', 'warehouse', 'kho'], true))
        )) {
                return $next($request);
            }
        }
        /* EGO_SERIAL_WARRANTY_WAREHOUSE_ACCESS_END */

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        /* EGO_EMPLOYEE_SELF_SERVICE_PUBLIC_ROUTES_V1
         * Các trang tự phục vụ nhân sự luôn mở cho mọi tài khoản đã đăng nhập.
         * Quyền duyệt/chuyển người duyệt vẫn được kiểm tra trong Controller.
         */
        $routeName = optional($request->route())->getName();

        /* EGO_SHARED_MODULES_ALL_AUTH_ROLES_V2_START
         * Các module nội bộ dùng chung luôn mở cho mọi tài khoản đã đăng nhập.
         * Quyền duyệt/xóa/chỉnh sửa chi tiết vẫn do Controller hiện tại kiểm tra.
         */
        if (
            $routeName
            && \Illuminate\Support\Str::is([
                'payment_requests.*',
                'payment-requests.*',
                'de-xuat.*',
                'company-documents.*',
            ], $routeName)
        ) {
            return $next($request);
        }
        /* EGO_SHARED_MODULES_ALL_AUTH_ROLES_V2_END */

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

        /*
         * EGO_WAREHOUSE_PROJECT_MATERIAL_ACCESS_V1
         *
         * Role Kho được mở đúng màn hình xử lý vật tư công trình.
         * Controller vẫn kiểm tra công ty, công trình và phiếu vật tư.
         * Không mở các tab tài chính hoặc chức năng kỹ thuật khác.
         */
        $isWarehouseOnly = $user->hasAnyRole(['warehouse', 'kho'])
            && ! $user->hasAnyRole([
                'admin',
                'management',
                'manager',
                'technical_manager',
                'technical_leader',
                'sales_manager',
                'sales',
                'sales_staff',
                'ky_thuat',
            ]);

        $isWarehouseMaterialRoute = $routeName
            && \Illuminate\Support\Str::is([
                'project-test.warehouse.*',
                'warehouse-projects.*',
            ], $routeName);

        $isWarehouseProjectMaterialPage = $routeName === 'project-test.show'
            && $request->query('tab') === 'materials';

        if (
            $isWarehouseOnly
            && ($isWarehouseMaterialRoute || $isWarehouseProjectMaterialPage)
        ) {
            return $next($request);
        }

        // Công trình chuẩn /du-an: Sales được vào module; phạm vi dữ liệu và quyền ghi
        // tiếp tục bị khóa bởi EnsureUnifiedProjectAccess. Không mở chéo công trình nội bộ.
        if ($routeName && \Illuminate\Support\Str::is('projects-unified.*', $routeName)) {
            $isSales = method_exists($user, 'hasAnyRole')
                && $user->hasAnyRole(['sales', 'sales_manager', 'sales_staff']);
            if ($isSales) {
                return $next($request);
            }
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
