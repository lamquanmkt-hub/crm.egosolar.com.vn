<?php

namespace App\Policies;

use App\Models\Tasks\Task;
use App\Models\User;

/**
 * Policy phân quyền các thao tác trên công việc (Task).
 */
class TaskPolicy
{
    /**
     * Những role được quyền giao việc
     */
    private function canAssign(User $user): bool
    {
        // Nếu dùng Spatie Permission
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole([
                'admin',
                'sales_manager',
                'marketing',
                'marketing_manager',
            ]);
        }

        // Nếu role lưu trong cột users.role
        $role = strtolower((string) ($user->role ?? ''));

        return in_array($role, [
            'admin',
            'sales_manager',
            'marketing',
            'marketing_manager',
        ]);
    }

    /**
     * Chỉ người có quyền phân công việc được xem danh sách công việc.
     */
    public function viewAny(User $user): bool
    {
        return $this->canAssign($user);
    }

    /**
     * Người phân công, người yêu cầu hoặc người được giao được xem công việc.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->canAssign($user)
            || $task->requester_id === $user->id
            || $task->assignee_id === $user->id;
    }

    /**
     * Chỉ người có quyền phân công được tạo công việc.
     */
    public function create(User $user): bool
    {
        return $this->canAssign($user);
    }

    /**
     * Người có quyền phân công hoặc người được giao được cập nhật công việc.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->canAssign($user)
            || $task->assignee_id === $user->id;
    }

    /**
     * Chỉ người có quyền phân công được xoá công việc.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->canAssign($user);
    }

    /**
     * Chỉ người có quyền phân công được khôi phục công việc.
     */
    public function restore(User $user, Task $task): bool
    {
        return $this->canAssign($user);
    }

    /**
     * Chỉ người có quyền phân công được xoá vĩnh viễn công việc.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $this->canAssign($user);
    }
}
