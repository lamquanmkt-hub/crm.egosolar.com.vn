<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\BrandRepositoryInterface;
use App\Models\Inventory\Catalog\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Repository Eloquent thao tác dữ liệu thương hiệu.
 */
class BrandRepository implements BrandRepositoryInterface
{
    /**
     * Lấy danh sách thương hiệu phân trang, hỗ trợ tìm kiếm theo tên/slug.
     */
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $q = Brand::query();
        if ($search) {
            $q->where(function ($x) use ($search) {
                $x->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $q->orderBy('name')->paginate($perPage);
    }

    /**
     * Lấy danh sách thương hiệu đang hoạt động để làm tuỳ chọn.
     */
    public function optionsActive(): Collection
    {
        return Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * Tạo mới thương hiệu.
     */
    public function create(array $data): Brand
    {
        return Brand::create($data);
    }

    /**
     * Cập nhật thương hiệu.
     */
    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand;
    }

    /**
     * Xoá thương hiệu.
     */
    public function delete(Brand $brand): bool
    {
        return (bool) $brand->delete();
    }
}
