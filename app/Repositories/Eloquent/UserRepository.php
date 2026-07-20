<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

/**
 * Repository Eloquent thao tác dữ liệu người dùng.
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Lấy danh sách người dùng kèm vai trò, phân trang.
     */
    public function paginate($limit = 20)
    {
        return User::with('roles')->paginate($limit);
    }

    /**
     * Tìm người dùng theo ID.
     */
    public function find($id)
    {
        return User::with('roles')->findOrFail($id);
    }

    /**
     * Tạo người dùng mới và gán vai trò nếu có.
     */
    public function create(array $data)
    {
        $user = User::create($data);
        if (! empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user;
    }

    /**
     * Cập nhật người dùng và đồng bộ vai trò nếu có.
     */
    public function update($id, array $data)
    {
        $user = $this->find($id);
        $user->update($data);
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        return $user;
    }

    /**
     * Xoá người dùng.
     */
    public function delete($id)
    {
        return $this->find($id)->delete();
    }

    /**
     * Trả về query builder của model User.
     */
    public function query()
    {
        return User::query();
    }
}
