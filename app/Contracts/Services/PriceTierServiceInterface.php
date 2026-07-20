<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Inventory\Pricing\PriceTier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Hợp đồng service quản lý loại giá (PriceTier).
 */
interface PriceTierServiceInterface
{
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator;

    public function options(): Collection;

    public function create(array $data): PriceTier;

    public function update(PriceTier $tier, array $data): PriceTier;

    public function delete(PriceTier $tier): bool;
}
