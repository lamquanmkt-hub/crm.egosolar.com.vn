<?php

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ProductCategoryRepositoryInterface;
use App\Models\Inventory\Catalog\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Repository Eloquent thao tác dữ liệu danh mục sản phẩm.
 */
class ProductCategoryRepository implements ProductCategoryRepositoryInterface
{
    /**
     * Lấy tất cả danh mục sản phẩm sắp theo tên.
     */
    public function all(): iterable
    {
        return ProductCategory::orderBy('name')->get();
    }

    /**
     * Lấy danh mục kèm quan hệ cha/con/sản phẩm, phân trang.
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ProductCategory::with(['parent', 'children', 'products'])
            ->withCount(['children', 'products'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Tìm danh mục sản phẩm theo ID.
     */
    public function find(int $id): ?ProductCategory
    {
        return ProductCategory::find($id);
    }

    /**
     * Tạo mới danh mục sản phẩm.
     */
    public function create(array $data): ProductCategory
    {
        return ProductCategory::create($data);
    }

    /**
     * Cập nhật danh mục sản phẩm.
     */
    public function update(int $id, array $data): bool
    {
        $category = ProductCategory::findOrFail($id);

        return $category->update($data);
    }

    /**
     * Xoá danh mục sản phẩm.
     */
    public function delete(int $id): bool
    {
        $category = ProductCategory::findOrFail($id);

        return $category->delete();
    }

    /**
     * Tìm danh mục theo ID kèm danh sách sản phẩm.
     */
    public function findWithProducts(int $id): ?ProductCategory
    {
        return ProductCategory::with('products')->find($id);
    }

    /**
     * Lấy tất cả danh mục sản phẩm sắp xếp theo tên.
     */
    public function allOrdered()
    {
        return ProductCategory::orderBy('name')->get();
    }
}
