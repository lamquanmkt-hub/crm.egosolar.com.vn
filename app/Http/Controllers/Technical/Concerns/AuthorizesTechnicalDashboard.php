<?php

declare(strict_types=1);

namespace App\Http\Controllers\Technical\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * QUYỀN VÀO KHU "DASHBOARD KỸ THUẬT" (Admin / Giám đốc — chỉ xem).
 *
 * Dùng chung cho cả BỐN trang: Tổng quan, Kế hoạch, Báo cáo tuần/tháng, KPIs.
 * Điều kiện GIỐNG HỆT Dashboard đã có từ trước, không nới lỏng:
 *   - `User::isAdmin()`  (Admin = Giám đốc, gom từ đợt P0), HOẶC
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

        if ($user->isAdmin()) {
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
            'Khu Dashboard kết quả Kỹ thuật dành cho Ban giám đốc. Trưởng phòng vui lòng dùng "Tổng quan phòng".',
        );

        return $user;
    }
}
