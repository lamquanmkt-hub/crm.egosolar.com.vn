<?php

namespace App\Repositories\Eloquent;

use App\Models\Inventory\Pricing\PriceTier;
use App\Repositories\Interfaces\PriceTierRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Repository Eloquent thao tác dữ liệu bậc giá.
 */
class PriceTierRepository implements PriceTierRepositoryInterface
{
    /**
     * Lấy danh sách bậc giá phân trang, hỗ trợ tìm kiếm theo tên/mã.
     */
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

    /**
     * Lấy danh sách bậc giá đang hoạt động để làm tuỳ chọn.
     */
    public function optionsActive(): Collection
    {
        return PriceTier::query()
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'priority']);
    }

    /**
     * Tạo mới bậc giá.
     */
    public function create(array $data): PriceTier
    {
        return PriceTier::create($data);
    }

    /**
     * Cập nhật bậc giá.
     */
    public function update(PriceTier $tier, array $data): PriceTier
    {
        $tier->update($data);

        return $tier;
    }

    /**
     * Xoá bậc giá.
     */
    public function delete(PriceTier $tier): bool
    {
        return (bool) $tier->delete();
    }
}
