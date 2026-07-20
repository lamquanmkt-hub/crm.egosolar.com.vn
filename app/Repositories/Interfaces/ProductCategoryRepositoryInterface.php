<?php

namespace App\Repositories\Interfaces;

use App\Models\Inventory\Catalog\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface khai báo các thao tác repository cho danh mục sản phẩm.
 */
interface ProductCategoryRepositoryInterface
{
    /**
     * Get all categories (non-paginated)
     */
    public function all(): iterable;

    /**
     * Paginate product categories
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    /**
     * Find one category by ID
     */
    public function find(int $id): ?ProductCategory;

    /**
     * Create a new category
     */
    public function create(array $data): ProductCategory;

    /**
     * Update category by ID
     */
    public function update(int $id, array $data): bool;

    /**
     * Delete a category by ID
     */
    public function delete(int $id): bool;

    /**
     * Get category with products (optional)
     */
    public function findWithProducts(int $id): ?ProductCategory;

    /**
     * Lấy tất cả danh mục sản phẩm sắp xếp theo tên.
     *
     * @return mixed
     */
    public function allOrdered();
}
