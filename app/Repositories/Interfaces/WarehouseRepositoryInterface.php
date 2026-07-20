<?php

namespace App\Repositories\Interfaces;

/**
 * Interface khai báo các thao tác repository cho kho.
 */
interface WarehouseRepositoryInterface
{
    /**
     * Lấy danh sách kho.
     *
     * @return mixed
     */
    public function all();

    /**
     * Lấy toàn bộ kho không phân trang.
     *
     * @return mixed
     */
    public function listAll();

    /**
     * Tìm kho theo ID.
     *
     * @return mixed
     */
    public function find(int $id);

    /**
     * Tạo mới kho.
     *
     * @return mixed
     */
    public function create(array $data);

    /**
     * Cập nhật kho.
     *
     * @param  mixed  $warehouse
     * @return mixed
     */
    public function update($warehouse, array $data);

    /**
     * Xoá kho.
     *
     * @param  mixed  $warehouse
     * @return mixed
     */
    public function delete($warehouse);
}
