<?php

namespace App\Repositories\Interfaces;

/**
 * Interface khai báo các thao tác repository cho khách hàng.
 */
interface CustomerRepositoryInterface
{
    /**
     * Lấy danh sách khách hàng.
     *
     * @return mixed
     */
    public function all();

    /**
     * Lấy danh sách khách hàng kèm đầy đủ quan hệ, phân trang.
     *
     * @return mixed
     */
    public function getAllWithRelations();

    /**
     * Tìm khách hàng theo ID.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function find($id);

    /**
     * Tìm khách hàng theo ID kèm đầy đủ quan hệ chi tiết.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function findWithDetails($id);

    /**
     * Tìm kiếm khách hàng theo bộ lọc, phân trang.
     *
     * @return mixed
     */
    public function search(array $filters = []);

    /**
     * Tạo mới khách hàng.
     *
     * @return mixed
     */
    public function create(array $data);

    /**
     * Cập nhật khách hàng.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function update($id, array $data);

    /**
     * Xoá khách hàng.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function delete($id);

    /**
     * Đếm tổng số khách hàng.
     *
     * @return mixed
     */
    public function count();

    /**
     * Đếm số khách hàng đã từng mua hàng.
     *
     * @return mixed
     */
    public function countPurchased();
}
