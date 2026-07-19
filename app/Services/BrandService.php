<?php
namespace App\Services;
use App\Models\Inventory\Catalog\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BrandService
{
    public function __construct(
        private readonly BrandRepositoryInterface $brands
    ) {}
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->brands->paginate($search, $perPage);
    }
    public function options(): Collection
    {
        return $this->brands->optionsActive();
    }
    public function create(array $data): Brand
    {
        return DB::transaction(fn() => $this->brands->create($this->payload($data)));
    }
    public function update(Brand $brand, array $data): Brand
    {
        return DB::transaction(fn() => $this->brands->update($brand, $this->payload($data)));
    }
    public function delete(Brand $brand): bool
    {
        return DB::transaction(fn() => $this->brands->delete($brand));
    }
    private function payload(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool)($data['is_active'] ?? true),
        ];
    }
}
