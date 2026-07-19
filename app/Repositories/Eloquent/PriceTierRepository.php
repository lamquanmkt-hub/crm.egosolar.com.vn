<?php
namespace App\Repositories\Eloquent;
use App\Models\Inventory\Pricing\PriceTier;
use App\Repositories\Interfaces\PriceTierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PriceTierRepository implements PriceTierRepositoryInterface
{
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $q = PriceTier::query();
        if ($search) {
            $q->where(function ($x) use ($search) {
                $x->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }
        return $q->orderBy('priority')
            ->orderBy('name')
            ->paginate($perPage);
    }
    public function optionsActive(): Collection
    {
        return PriceTier::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'priority']);
    }
    public function create(array $data): PriceTier
    {
        return PriceTier::create($data);
    }
    public function update(PriceTier $tier, array $data): PriceTier
    {
        $tier->update($data);
        return $tier;
    }
    public function delete(PriceTier $tier): bool
    {
        return (bool) $tier->delete();
    }
}
