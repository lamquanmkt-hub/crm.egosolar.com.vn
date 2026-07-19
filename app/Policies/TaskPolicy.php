<?php

namespace App\Policies;

use App\Models\Tasks\Task;
use App\Models\User;

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
                'marketing_manager'
            ]);
        }

        // Nếu role lưu trong cột users.role
        $role = strtolower((string) ($user->role ?? ''));

        return in_array($role, [
            'admin',
            'sales_manager',
            'marketing',
            'marketing_manager'
        ]);
    }

    public function viewAny(User $user): bool
    {
        return $this->canAssign($user);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->canAssign($user)
            || $task->requester_id === $user->id
            || $task->assignee_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->canAssign($user);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->canAssign($user)
            || $task->assignee_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->canAssign($user);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->canAssign($user);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->canAssign($user);
    }
}
