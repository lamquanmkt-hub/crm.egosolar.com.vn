<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DepartmentDashboardService;
use App\Services\ExecutiveDashboardService;
use Illuminate\Http\Request;

/**
 * Trang chủ tự động theo vai trò/phòng ban.
 *
 * Admin/Ban giám đốc giữ nguyên Executive Dashboard hiện tại.
 * Các phòng ban còn lại dùng Department Dashboard PROMAX V2,
 * dữ liệu được giới hạn từ backend theo đúng role và phạm vi người dùng.
 */
final class RoleHomeController extends Controller
{
    public function __construct(
        private readonly ExecutiveDashboardService $executiveDashboard,
        private readonly DepartmentDashboardService $departmentDashboard,
    ) {
        $this->middleware('auth');
    }

    public function index(Request $request): mixed
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['department', 'position']);

        $workspace = $this->departmentDashboard->resolveWorkspace($user);

        if ($workspace === 'executive') {
            $filters = $request->only([
                'period',
                'from',
                'to',
                'from_date',
                'to_date',
                'sales_id',
                'source',
            ]);

            return view(
                'dashboard.index',
                $this->executiveDashboard->build($user, $filters)
            );
        }

        return view('dashboard.role-home', [
            'dashboard' => $this->departmentDashboard->build(
                $user,
                $request,
                $workspace
            ),
        ]);
    }
}
