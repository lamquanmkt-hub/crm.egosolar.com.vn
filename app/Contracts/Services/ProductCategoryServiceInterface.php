<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Inventory\Catalog\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Hợp đồng service quản lý danh mục sản phẩm (ProductCategory).
 */
interface ProductCategoryServiceInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function getAll(): EloquentCollection;

    public function getTree(): EloquentCollection;

    public function getAllWithParent(): EloquentCollection;

    public function find(int $id): ?ProductCategory;

    public function findWithProducts(int $id): ?ProductCategory;

    public function create(array $data): ProductCategory;

    public function update(int $id, array $data): bool;

    public function delete(int $id): bool;
}
