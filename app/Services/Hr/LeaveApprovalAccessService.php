<?php

namespace App\Services\Hr;

use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LeaveApprovalAccessService
{
    private const GLOBAL_ROLES = [
        'admin',
        'management',
        'hr',
        'accounting',
        'ketoan',
        'ke_toan',
    ];

    private const MANAGER_ROLES = [
        'admin',
        'management',
        'hr',
        'accounting',
        'manager',
        'department_manager',
        'sales_manager',
        'marketing_manager',
        'technical_manager',
        'ky_thuat_manager',
        'warehouse_manager',
        'maintenance_manager',
        'hr_manager',
        'accounting_manager',
        'leader',
        'lead',
    ];

    /**
     * Các role có thể được chọn trực tiếp làm người duyệt đơn nghỉ phép,
     * không phụ thuộc phòng ban của nhân viên tạo đơn.
     *
     * Lưu ý: đây chỉ là quyền được chọn/gán và duyệt các đơn được giao,
     * không biến role này thành quyền quản lý toàn bộ đơn nghỉ phép.
     */
    private const DIRECT_APPROVER_ROLES = [
        'sales_manager',
    ];

    public function canManageAll(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->hasAnyRole($user, self::GLOBAL_ROLES)) {
            return true;
        }

        return $this->hasAnyPermission($user, [
            'hr.leave.manage_all',
            'leave.manage_all',
        ]);
    }

    public function isDepartmentManager(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->hasAnyRole($user, self::MANAGER_ROLES)) {
            return true;
        }

        if ($this->hasAnyPermission($user, [
            'hr.leave.approve_department',
            'hr.leave.transfer',
        ])) {
            return true;
        }

        if (Schema::hasColumn('users', 'manager_id')) {
            return User::query()
                ->where('manager_id', $user->id)
                ->exists();
        }

        return false;
    }

    public function canReview(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($this->canManageAll($user) || $this->isDepartmentManager($user)) {
            return true;
        }

        return LeaveRequest::query()
            ->where('status', 'pending')
            ->where('approver_id', $user->id)
            ->exists();
    }

    public function scopeReviewable(Builder $query, User $user): Builder
    {
        if ($this->canManageAll($user)) {
            return $query;
        }

        $departmentId = (int) ($user->department_id ?? 0);
        $isDepartmentManager = $this->isDepartmentManager($user);
        $hasManagerColumn = Schema::hasColumn('users', 'manager_id');

        return $query->where(function (Builder $scope) use (
            $user,
            $departmentId,
            $isDepartmentManager,
            $hasManagerColumn
        ): void {
            $scope->where('approver_id', $user->id);

            if ($hasManagerColumn) {
                $scope->orWhereHas('user', function (Builder $employee) use ($user): void {
                    $employee->where('manager_id', $user->id);
                });
            }

            if ($isDepartmentManager && $departmentId > 0) {
                $scope->orWhereHas('user', function (Builder $employee) use ($departmentId): void {
                    $employee->where('department_id', $departmentId);
                });
            }
        });
    }

    public function canApprove(User $user, LeaveRequest $leave): bool
    {
        if ($this->canManageAll($user)) {
            return true;
        }

        if ((int) $leave->approver_id === (int) $user->id) {
            return true;
        }

        $leave->loadMissing('user');
        $employee = $leave->user;

        if (! $employee) {
            return false;
        }

        if (
            Schema::hasColumn('users', 'manager_id')
            && (int) ($employee->manager_id ?? 0) === (int) $user->id
        ) {
            return true;
        }

        return $this->isDepartmentManager($user)
            && (int) ($user->department_id ?? 0) > 0
            && (int) ($employee->department_id ?? 0) === (int) $user->department_id;
    }

    public function canTransfer(User $user, LeaveRequest $leave): bool
    {
        if ($leave->status !== 'pending') {
            return false;
        }

        if ($this->hasAnyPermission($user, ['hr.leave.transfer'])) {
            return true;
        }

        return $this->canApprove($user, $leave);
    }

    public function canView(User $user, LeaveRequest $leave): bool
    {
        return (int) $leave->user_id === (int) $user->id
            || $this->canApprove($user, $leave)
            || $this->canManageAll($user);
    }

    /**
     * @return Collection<int, User>
     */
    public function eligibleApprovers(User $requester): Collection
    {
        $users = User::query()
            ->with(['roles:id,name', 'department:id,name'])
            ->when(
                Schema::hasColumn('users', 'is_active'),
                fn (Builder $query) => $query->where('is_active', true)
            )
            ->where('id', '!=', $requester->id)
            ->orderBy('name')
            ->get();

        $directManagerId = Schema::hasColumn('users', 'manager_id')
            ? (int) ($requester->manager_id ?? 0)
            : 0;

        $departmentId = (int) ($requester->department_id ?? 0);

        $qualified = $users->filter(function (User $candidate) use (
            $directManagerId,
            $departmentId
        ): bool {
            if ($directManagerId > 0 && (int) $candidate->id === $directManagerId) {
                return true;
            }

            if ($this->canManageAll($candidate)) {
                return true;
            }

            // Sales Manager có thể được chọn trực tiếp làm người duyệt
            // cho nhân viên ở mọi phòng ban.
            if ($this->hasAnyRole($candidate, self::DIRECT_APPROVER_ROLES)) {
                return true;
            }

            return $departmentId > 0
                && (int) ($candidate->department_id ?? 0) === $departmentId
                && $this->isDepartmentManager($candidate);
        });

        if ($qualified->isEmpty()) {
            $qualified = $users->filter(
                fn (User $candidate): bool => $this->isDepartmentManager($candidate)
            );
        }

        if ($qualified->isEmpty()) {
            $qualified = $users;
        }

        return $qualified
            ->sortBy(function (User $candidate) use ($directManagerId, $departmentId): string {
                $priority = 3;

                if ($directManagerId > 0 && (int) $candidate->id === $directManagerId) {
                    $priority = 0;
                } elseif (
                    $departmentId > 0
                    && (int) ($candidate->department_id ?? 0) === $departmentId
                    && $this->isDepartmentManager($candidate)
                ) {
                    $priority = 1;
                } elseif ($this->canManageAll($candidate)) {
                    $priority = 2;
                } elseif ($this->hasAnyRole($candidate, self::DIRECT_APPROVER_ROLES)) {
                    $priority = 2;
                }

                return sprintf('%d-%s', $priority, mb_strtolower((string) $candidate->name));
            })
            ->values();
    }

    public function defaultApprover(User $requester, Collection $approvers): ?User
    {
        if ($approvers->isEmpty()) {
            return null;
        }

        if (Schema::hasColumn('users', 'manager_id')) {
            $managerId = (int) ($requester->manager_id ?? 0);

            if ($managerId > 0) {
                $manager = $approvers->firstWhere('id', $managerId);

                if ($manager) {
                    return $manager;
                }
            }
        }

        $departmentId = (int) ($requester->department_id ?? 0);

        if ($departmentId > 0) {
            $departmentManager = $approvers->first(function (User $candidate) use ($departmentId): bool {
                return (int) ($candidate->department_id ?? 0) === $departmentId
                    && $this->isDepartmentManager($candidate);
            });

            if ($departmentManager) {
                return $departmentManager;
            }
        }

        return $approvers->first();
    }

    private function hasAnyRole(User $user, array $roles): bool
    {
        try {
            return method_exists($user, 'hasAnyRole') && $user->hasAnyRole($roles);
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            try {
                if (method_exists($user, 'can') && $user->can($permission)) {
                    return true;
                }
            } catch (\Throwable) {
                // Permission chưa tồn tại thì tiếp tục kiểm tra cơ chế khác.
            }
        }

        return false;
    }
}
