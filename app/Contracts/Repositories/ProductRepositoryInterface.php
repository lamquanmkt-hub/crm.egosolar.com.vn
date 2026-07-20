<?php

namespace App\Contracts\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Interface khai báo các thao tác repository cho sản phẩm.
 */
interface ProductRepositoryInterface
{
    /**
     * Lấy danh sách sản phẩm theo bộ lọc.
     */
    public function getAll(?int $warehouseId = null, ?int $categoryId = null, ?string $search = null): LengthAwarePaginator;

    /**
     * Tìm sản phẩm theo ID.
     *
     * @param  mixed  $id
     * @return mixed
     */
    public function find($id);

    /**
     * Tạo mới sản phẩm.
     *
     * @return mixed
     */
    public function create(array $data);

    /**
     * Cập nhật sản phẩm.
     *
     * @param  mixed  $product
     * @return mixed
     */
    public function update($product, array $data);

    /**
     * Xoá sản phẩm.
     *
     * @param  mixed  $product
     * @return mixed
     */
    public function delete($product);
}
