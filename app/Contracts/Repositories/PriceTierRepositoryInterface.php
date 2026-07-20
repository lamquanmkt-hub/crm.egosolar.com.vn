<?php

namespace App\Contracts\Repositories;

use App\Models\Inventory\Pricing\PriceTier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface khai báo các thao tác repository cho bậc giá.
 */
interface PriceTierRepositoryInterface
{
    /**
     * Lấy danh sách bậc giá phân trang.
     */
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator;

    /**
     * Lấy danh sách bậc giá đang hoạt động để làm tuỳ chọn.
     */
    public function optionsActive(): Collection;

    /**
     * Tạo mới bậc giá.
     */
    public function create(array $data): PriceTier;

    /**
     * Cập nhật bậc giá.
     */
    public function update(PriceTier $tier, array $data): PriceTier;

    /**
     * Xoá bậc giá.
     */
    public function delete(PriceTier $tier): bool;
}
