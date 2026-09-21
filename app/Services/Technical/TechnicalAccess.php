<?php

declare(strict_types=1);

namespace App\Services\Technical;

use App\Models\User;
use App\Support\SolarMaintenanceAccess;

/**
 * Lớp mỏng trả lời đúng 3 câu hỏi của module Kỹ thuật (giai đoạn 1).
 *
 * KHÔNG tạo hệ phân quyền song song: mọi quyết định đều uỷ quyền cho cơ chế
 * đã có sẵn trong source —
 *   - `User::isAdmin()`            (Admin = Giám đốc, gom từ đợt P0)
 *   - `SolarMaintenanceAccess::*`  (role kỹ thuật / trưởng kỹ thuật đang dùng
 *                                   cho toàn bộ module Bảo trì - Bảo hành)
 *   - permission Spatie `technical.reports.approve` cho trường hợp Admin muốn
 *     trao quyền duyệt cho một cá nhân không thuộc role trưởng kỹ thuật.
 *
 * Quyền truy cập TRANG vẫn do `EnforcePageAccess` + `page.technical`
 * (config/role_permissions.php) quyết định như trước, không đụng tới.
 */
final class TechnicalAccess
{
    /** Permission tuỳ chọn để Admin trao quyền duyệt báo cáo cho cá nhân cụ thể. */
    public const PERMISSION_APPROVE_REPORTS = 'technical.reports.approve';

    /** Được vào module Kỹ thuật (kỹ thuật viên, trưởng kỹ thuật, Ban giám đốc). */
    public function canUseModule(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isAdmin()
            || SolarMaintenanceAccess::isTechnician($user)
            || SolarMaintenanceAccess::isExecutive($user);
    }

    /**
     * Được xem dữ liệu của người khác (trưởng kỹ thuật / Ban giám đốc / Admin)
     * và được duyệt hoặc trả lại báo cáo ngày.
     */
    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || SolarMaintenanceAccess::isManager($user)) {
            return true;
        }

        try {
            return (bool) $user->can(self::PERMISSION_APPROVE_REPORTS);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Kỹ thuật viên thuần: chỉ được thấy việc và báo cáo của chính mình. */
    public function isScopedToSelf(?User $user): bool
    {
        return $this->canUseModule($user) && ! $this->canManage($user);
    }

    /**
     * Id người dùng mà `$viewer` được phép xem dữ liệu.
     *
     * - null  => không giới hạn (trong phạm vi công ty đang hoạt động)
     * - int   => chỉ đúng một người
     */
    public function visibleUserId(?User $viewer, ?int $requestedUserId = null): ?int
    {
        if (! $viewer) {
            return -1; // không ai
        }

        if ($this->isScopedToSelf($viewer)) {
            return (int) $viewer->id;
        }

        return $requestedUserId !== null && $requestedUserId > 0 ? $requestedUserId : null;
    }
}
