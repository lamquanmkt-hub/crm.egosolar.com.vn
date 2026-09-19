<?php
namespace App\Repositories\Interfaces;
use App\Models\Inventory\Catalog\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface BrandRepositoryInterface
{
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator;
    public function optionsActive(): Collection;
    public function create(array $data): Brand;
    public function update(Brand $brand, array $data): Brand;
    public function delete(Brand $brand): bool;
}
