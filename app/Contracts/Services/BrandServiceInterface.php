<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Inventory\Catalog\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Hợp đồng service quản lý thương hiệu (Brand).
 */
interface BrandServiceInterface
{
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator;

    public function options(): Collection;

    public function create(array $data): Brand;

    public function update(Brand $brand, array $data): Brand;

    public function delete(Brand $brand): bool;
}
