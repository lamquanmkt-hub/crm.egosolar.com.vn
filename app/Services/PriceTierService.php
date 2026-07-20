<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PriceTierRepositoryInterface;
use App\Contracts\Services\PriceTierServiceInterface;
use App\Models\Inventory\Pricing\PriceTier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service xử lý nghiệp vụ loại giá (PriceTier).
 */
class PriceTierService implements PriceTierServiceInterface
{
    /**
     * Khởi tạo service với repository loại giá.
     */
    public function __construct(
        private readonly PriceTierRepositoryInterface $tiers
    ) {}

    /**
     * Lấy danh sách loại giá có phân trang, hỗ trợ tìm kiếm.
     */
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->tiers->paginate($search, $perPage);
    }

    /**
     * Lấy danh sách loại giá đang hoạt động cho dropdown.
     */
    public function options(): Collection
    {
        return $this->tiers->optionsActive();
    }

    /**
     * Tạo mới loại giá trong transaction.
     */
    public function create(array $data): PriceTier
    {
        return DB::transaction(fn () => $this->tiers->create($this->payload($data)));
    }

    /**
     * Cập nhật loại giá trong transaction.
     */
    public function update(PriceTier $tier, array $data): PriceTier
    {
        return DB::transaction(fn () => $this->tiers->update($tier, $this->payload($data)));
    }

    /**
     * Xoá loại giá; chặn xoá nếu đang được dùng trong bảng giá sản phẩm.
     */
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

    /**
     * Chuẩn hoá dữ liệu đầu vào thành payload lưu DB.
     */
    private function payload(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
