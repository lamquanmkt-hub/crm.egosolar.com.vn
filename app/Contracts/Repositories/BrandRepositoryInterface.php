<?php

namespace App\Contracts\Repositories;

use App\Models\Inventory\Catalog\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface khai báo các thao tác repository cho thương hiệu.
 */
interface BrandRepositoryInterface
{
    /**
     * Lấy danh sách thương hiệu phân trang.
     */
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator;

    /**
     * Lấy danh sách thương hiệu đang hoạt động để làm tuỳ chọn.
     */
    public function optionsActive(): Collection;

    /**
     * Tạo mới thương hiệu.
     */
    public function create(array $data): Brand;

    /**
     * Cập nhật thương hiệu.
     */
    public function update(Brand $brand, array $data): Brand;

    /**
     * Xoá thương hiệu.
     */
    public function delete(Brand $brand): bool;
}
