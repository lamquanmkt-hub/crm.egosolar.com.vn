<?php
namespace App\Repositories\Interfaces;
use App\Models\Inventory\Pricing\PriceTier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface PriceTierRepositoryInterface
{
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator;
    public function optionsActive(): Collection;
    public function create(array $data): PriceTier;
    public function update(PriceTier $tier, array $data): PriceTier;
    public function delete(PriceTier $tier): bool;
}
