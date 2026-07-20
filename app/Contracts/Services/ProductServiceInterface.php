<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Inventory\Catalog\Product;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Hợp đồng service quản lý sản phẩm (Product).
 */
interface ProductServiceInterface
{
    public function getProductsForSelect(int $warehouseId): array;

    public function list(?int $warehouseId = null, ?int $categoryId = null, ?string $search = null, ?int $companyId = null): LengthAwarePaginator;

    public function formSelections(): array;

    public function create(array $data);

    public function update(Product $product, array $data);

    public function delete(Product $product);

    public function prepareFormData(?Product $product): array;
}
