<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Service quản lý người dùng (tạo, cập nhật, xoá, phân trang theo quyền).
 */
class UserService
{
    /**
     * Khởi tạo service với repository người dùng.
     */
    public function __construct(protected UserRepositoryInterface $users) {}

    /**
     * Tạo người dùng mới (hash mật khẩu, chỉ admin được gán role).
     */
    public function createUser(array $data)
    {
        try {
            $data['password'] = Hash::make($data['password']);
            $user = $this->users->create($data);
            if (! empty($data['roles']) && Auth::user()->hasRole('admin')) {
                $user->syncRoles($data['roles']);
            }

            return $user;
        } catch (\Exception $e) {
            report($e);
            throw new \Exception('Không thể tạo user: '.$e->getMessage());
        }
    }

    /**
     * Cập nhật người dùng; chỉ hash mật khẩu khi có nhập, non-admin không đổi được role.
     */
    public function updateUser($id, array $data)
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        // Nếu không phải admin, bỏ role khỏi dữ liệu
        if (! Auth::user()->hasRole('admin')) {
            unset($data['roles']);
        }

        return $this->users->update($id, $data);
    }

    /**
     * Xoá người dùng theo ID.
     */
    public function deleteUser($id)
    {
        return $this->users->delete($id);
    }

    /**
     * Lấy thông tin người dùng theo ID.
     */
    public function getUser($id)
    {
        return $this->users->find($id);
    }

    /**
     * Phân trang danh sách người dùng; non-admin chỉ thấy chính mình.
     */
    public function paginateUsers($perPage = 15)
    {
        if (Auth::user()->hasRole('admin')) {
            return $this->users->paginate($perPage);
        }

        // Non-admin chỉ xem mình
        return $this->users->query()->where('id', Auth::id())->paginate(1);
    }
}
