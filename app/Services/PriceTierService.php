<?php
namespace App\Services;
use App\Models\Inventory\Pricing\PriceTier;
use App\Repositories\Interfaces\PriceTierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PriceTierService
{
    public function __construct(
        private readonly PriceTierRepositoryInterface $tiers
    ) {}
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->tiers->paginate($search, $perPage);
    }
    public function options(): Collection
    {
        return $this->tiers->optionsActive();
    }
    public function create(array $data): PriceTier
    {
        return DB::transaction(fn() => $this->tiers->create($this->payload($data)));
    }
    public function update(PriceTier $tier, array $data): PriceTier
    {
        return DB::transaction(fn() => $this->tiers->update($tier, $this->payload($data)));
    }
    public function delete(PriceTier $tier): bool
    {
        return DB::transaction(function () use ($tier) {
            // chặn xoá nếu tier đang được dùng trong crm_product_prices
            $inUse = DB::table('crm_product_prices')->where('price_tier_id', $tier->id)->exists();
            if ($inUse) {
                throw ValidationException::withMessages([
                    'price_tier' => 'Không thể xoá: Loại giá đang được dùng trong bảng giá sản phẩm.',
                ]);
            }
            return $this->tiers->delete($tier);
        });
    }
    private function payload(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'priority' => (int)($data['priority'] ?? 0),
            'is_active' => (bool)($data['is_active'] ?? true),
        ];
    }
}
