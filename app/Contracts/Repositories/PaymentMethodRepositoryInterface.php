<?php

namespace App\Contracts\Repositories;

/**
 * Interface khai báo các thao tác repository cho phương thức thanh toán.
 */
interface PaymentMethodRepositoryInterface
{
    /**
     * Lấy danh sách phương thức thanh toán theo bộ lọc.
     *
     * @return mixed
     */
    public function getAll(array $filters = []);

    /**
     * Lấy các phương thức thanh toán đang hoạt động.
     *
     * @return mixed
     */
    public function getActiveMethods();

    /**
     * Tìm phương thức thanh toán theo ID.
     *
     * @return mixed
     */
    public function findById(int $id);

    /**
     * Tạo mới phương thức thanh toán.
     *
     * @return mixed
     */
    public function create(array $data);

    /**
     * Cập nhật phương thức thanh toán.
     *
     * @return mixed
     */
    public function update(int $id, array $data);

    /**
     * Xoá phương thức thanh toán.
     *
     * @return mixed
     */
    public function delete(int $id);
}
