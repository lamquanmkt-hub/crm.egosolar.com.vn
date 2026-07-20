<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy phân quyền các thao tác trên người dùng.
 */
class UserPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Chỉ admin được tạo người dùng.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Chỉ admin được xem danh sách người dùng.
     */
    public function viewAny($user)
    {
        return $user->hasRole('admin');
    }

    /**
     * Admin hoặc chính chủ tài khoản được xem thông tin người dùng.
     */
    public function view($user, $target): bool
    {
        return $user->hasRole('admin') || $user->id === $target->id;
    }

    /**
     * Admin hoặc chính chủ tài khoản được cập nhật thông tin.
     */
    public function update(User $user, User $target): bool
    {
        return $user->hasRole('admin') || $user->id === $target->id;
    }

    /**
     * Chỉ admin được xoá người dùng và không được tự xoá chính mình.
     */
    public function delete($user, $target): bool
    {
        return $user->hasRole('admin') && $user->id !== $target->id;
    }
}
