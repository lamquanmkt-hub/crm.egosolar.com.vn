<?php

declare(strict_types=1);

namespace App\Services\Hr\Payroll;

/**
 * Phân quyền Bảng lương & KPI văn phòng.
 *   - Lập bảng lương, hồ sơ lương, cài đặt lương: HR, Kế toán, Ban Giám đốc, Admin.
 *   - Duyệt / mở khoá bảng lương; chấm và cài đặt KPI; nhập tay hệ số KPI: Ban Giám đốc, Admin.
 *   - Không ai tự sửa lương, hồ sơ lương hay KPI của chính mình.
 *   - Nhân viên chỉ xem phiếu lương của chính mình ở kỳ đã duyệt.
 */
final class PayrollAccess
{
    public const MANAGE_ROLES = ['admin', 'super_admin', 'director', 'ceo', 'management', 'hr', 'accounting'];

    public const APPROVE_ROLES = ['admin', 'super_admin', 'director', 'ceo', 'management'];

    public static function canManage($user): bool
    {
        return self::hasAny($user, self::MANAGE_ROLES);
    }

    public static function canApprove($user): bool
    {
        return self::hasAny($user, self::APPROVE_ROLES);
    }

    public static function canEvaluateKpi($user, ?int $employeeId = null): bool
    {
        return self::hasAny($user, self::APPROVE_ROLES) && ! self::isSelf($user, $employeeId);
    }

    /** Sửa dòng lương / hồ sơ lương của một nhân viên: người lập bảng lương, trừ chính mình. */
    public static function canEditEmployee($user, int $employeeId): bool
    {
        return self::canManage($user) && ! self::isSelf($user, $employeeId);
    }

    private static function isSelf($user, ?int $employeeId): bool
    {
        return $employeeId !== null && $user && (int) $user->id === $employeeId;
    }

    private static function hasAny($user, array $roles): bool
    {
        return $user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles);
    }
}
