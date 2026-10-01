<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * QUYỀN VÀO KHU "DASHBOARD KỸ THUẬT" (Admin / Giám đốc / Trưởng phòng / nhân sự kỹ thuật — chỉ xem).
 *
 * Dùng chung cho cả BỐN trang: Tổng quan, Kế hoạch, Báo cáo tuần/tháng, KPIs.
 * Điều kiện:
 *   - `User::isAdmin()`  (Admin = Giám đốc, gom từ đợt P0), HOẶC
 *   - role trưởng phòng (`ego_menu_v4.technical_head_roles`) hoặc nhân sự kỹ thuật
 *     (`technical.staff_roles`) — mở thêm cho họ XEM; các trang vẫn CHỈ ĐỌC, HOẶC
 *   - permission `technical.dashboard.view`.
 *
 * Người không đủ điều kiện nhận **403**, KHÔNG chuyển hướng về trang khác:
 * chuyển hướng sẽ che mất lỗi phân quyền và làm người dùng tưởng mình có quyền.
 */
trait AuthorizesTechnicalDashboard
{
    protected function authorizeTechnicalDashboard(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user !== null, 403);

        $viewerRoles = array_merge(
            (array) config('ego_menu_v4.technical_head_roles', []),
            (array) config('technical.staff_roles', []),
        );

        if ($user->isAdmin() || $user->hasAnyRole($viewerRoles)) {
            return $user;
        }

        $allowed = false;

        try {
            $allowed = (bool) $user->can(\App\Http\Controllers\Technical\TechnicalDashboardController::PERMISSION_VIEW);
        } catch (\Throwable) {
            $allowed = false;
        }

        abort_unless(
            $allowed,
            403,
            'Khu Dashboard kết quả Kỹ thuật chỉ dành cho Ban giám đốc và bộ phận Kỹ thuật.',
        );

        return $user;
    }
}
