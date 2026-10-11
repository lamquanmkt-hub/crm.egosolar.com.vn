<?php

declare(strict_types=1);

namespace App\Services\Tasks;

/**
 * Quyền module Công việc dùng chung (/chat/tasks). Logic chuyển nguyên từ TaskController để checklist
 * KPI HCNS dùng cùng một kiểm tra khi hiện link về công việc nguồn; không mở rộng quyền.
 */
final class TaskPermissions
{
    private const ASSIGN_ROLES = [
        'admin', 'manager', 'management', 'director', 'general_director',
        'ban_giam_doc', 'giam_doc', 'sales_manager', 'marketing_manager',
        'accounting', 'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
    ];

    private const EXECUTIVE_ROLES = [
        'admin', 'manager', 'management', 'director', 'general_director',
        'ban_giam_doc', 'giam_doc', 'ceo',
    ];

    private const TECHNICAL_MANAGER_ROLES = ['technical_manager', 'technical_leader', 'truong_phong_ky_thuat'];

    /** Người dùng có quyền giao việc/quản lý công việc. */
    public static function canAssign($user): bool
    {
        if (! $user) {
            return false;
        }

        /*
         * EGO_TECHNICAL_TASK_ACCESS:
         * Tài khoản có role kỹ thuật nhưng mang chức danh Trưởng phòng,
         * Manager hoặc Leader vẫn được quyền giao và quản lý việc của phòng.
         */
        if (self::isTechnicalManager($user)) {
            return true;
        }

        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole(self::ASSIGN_ROLES);
            } catch (\Throwable) {
            }
        }

        $role = strtolower((string) ($user->role ?? $user->type ?? ''));

        return in_array($role, self::ASSIGN_ROLES, true);
    }

    public static function isExecutive($user): bool
    {
        if (! $user) {
            return false;
        }

        return method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(self::EXECUTIVE_ROLES)
            : in_array(strtolower((string) ($user->role ?? '')), self::EXECUTIVE_ROLES, true);
    }

    public static function isTechnicalManager($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole(self::TECHNICAL_MANAGER_ROLES)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        $position = mb_strtolower((string) optional($user->position)->name);
        $department = mb_strtolower((string) optional($user->department)->name);

        return (str_contains($position, 'trưởng') || str_contains($position, 'manager') || str_contains($position, 'leader'))
            && (str_contains($department, 'kỹ thuật') || str_contains($department, 'technical'));
    }

    /** Xem chi tiết công việc: người nhận, người giao, người duyệt hoặc người có quyền giao việc. */
    public static function canView($user, object $task): bool
    {
        if (! $user) {
            return false;
        }
        $id = (int) $user->id;

        return (int) ($task->assignee_id ?? 0) === $id
            || (int) ($task->requester_id ?? 0) === $id
            || (int) ($task->approver_id ?? 0) === $id
            || self::canAssign($user);
    }

    /** Duyệt (xác nhận) kết quả: người duyệt chỉ định, người giao, Ban giám đốc, hoặc người giao việc khi chưa chỉ định người duyệt. */
    public static function canApprove($user, object $task): bool
    {
        if (! $user) {
            return false;
        }

        return (int) ($task->approver_id ?? 0) === (int) $user->id
            || (int) ($task->requester_id ?? 0) === (int) $user->id
            || self::isExecutive($user)
            || (self::canAssign($user) && empty($task->approver_id));
    }
}
