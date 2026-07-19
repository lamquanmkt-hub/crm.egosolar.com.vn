<?php
namespace App\Repositories\Eloquent;
use App\Models\Inventory\Catalog\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BrandRepository implements BrandRepositoryInterface
{
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
    public function optionsActive(): Collection
    {
        return Brand::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }
    public function create(array $data): Brand
    {
        return Brand::create($data);
    }
    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);
        return $brand;
    }
    public function delete(Brand $brand): bool
    {
        return (bool) $brand->delete();
    }
}
