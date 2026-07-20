<?php

namespace App\Contracts\Repositories;

/**
 * Interface khai báo các thao tác repository cho người dùng.
 */
interface UserRepositoryInterface
{
    /**
     * Lấy danh sách người dùng phân trang.
     *
     * @param  int  $limit
     * @return mixed
     */
    public function paginate($limit = 20);

    /**
     * Tìm người dùng theo ID.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function find($id);

    /**
     * Tạo mới người dùng.
     *
     * @return mixed
     */
    public function create(array $data);

    /**
     * Cập nhật người dùng.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function update($id, array $data);

    /**
     * Xoá người dùng.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function delete($id);

    /**
     * Trả về query builder của model.
     *
     * @return mixed
     */
    public function query();
}
