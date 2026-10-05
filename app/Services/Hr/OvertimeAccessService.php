<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Phân quyền đơn đăng ký tăng ca.
 *
 * - Mọi nhân viên đăng nhập được gửi và xem đơn của chính mình.
 * - Người duyệt chỉ chọn trong nhóm quản lý / trưởng phòng / HR / admin, không phải chính người gửi.
 * - HR / Admin / Kế toán quản lý toàn bộ đơn; người được chọn duyệt chỉ duyệt đơn giao cho mình.
 * - Không ai được tự duyệt đơn của chính mình (kể cả HR / Admin).
 * - Người gửi sửa / xoá được đơn của mình khi đơn còn chờ duyệt.
 */
class OvertimeAccessService
{
    /** Xem và duyệt toàn bộ đơn tăng ca. */
    private const MANAGE_ALL_ROLES = ['admin', 'hr', 'accounting', 'management'];

    /** Được chọn làm người duyệt tăng ca. */
    private const APPROVER_ROLES = [
        'admin', 'management', 'hr', 'hr_manager',
        'manager', 'department_manager', 'director', 'ceo',
        'sales_manager', 'marketing_manager', 'technical_manager', 'technical_leader', 'ky_thuat_manager',
        'warehouse_manager', 'maintenance_manager', 'accounting_manager', 'leader', 'lead',
    ];

    public function canManageAll(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::MANAGE_ALL_ROLES);
    }

    public function isApproverRole(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::APPROVER_ROLES);
    }

    /** Có đơn tăng ca nào để duyệt hay không (hiện nút "Duyệt tăng ca"). */
    public function canReview(?User $user): bool
    {
        return $this->canManageAll($user) || $this->isApproverRole($user);
    }

    /** Duyệt / từ chối được đơn này: HR/Admin/Kế toán hoặc đúng người được chọn duyệt, không phải đơn của chính mình. */
    public function canApprove(?User $user, OvertimeRequest $overtime): bool
    {
        if ($user === null || (int) $overtime->user_id === (int) $user->id) {
            return false;
        }

        return $this->canManageAll($user) || (int) $overtime->approver_id === (int) $user->id;
    }

    /** Sửa / xoá được đơn này: chỉ người gửi, và chỉ khi đơn còn đang chờ duyệt. */
    public function canModify(?User $user, OvertimeRequest $overtime): bool
    {
        return $user !== null
            && (int) $overtime->user_id === (int) $user->id
            && $overtime->status === 'pending';
    }

    /** Đơn đang chờ mà user này duyệt được. */
    public function scopePendingForReviewer(Builder $query, User $user): Builder
    {
        $query->where('status', 'pending')->where('user_id', '!=', $user->id);

        if (! $this->canManageAll($user)) {
            $query->where('approver_id', $user->id);
        }

        return $query;
    }

    /** Danh sách người được chọn duyệt (đang hoạt động, thuộc nhóm quản lý), bỏ chính người gửi. */
    public function approverOptions(?int $excludeUserId = null): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', self::APPROVER_ROLES))
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('is_active', 1))
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
