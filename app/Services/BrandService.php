<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\BrandServiceInterface;
use App\Models\Inventory\Catalog\Brand;
use App\Repositories\Interfaces\BrandRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service xử lý nghiệp vụ thương hiệu (Brand).
 */
class BrandService implements BrandServiceInterface
{
    /**
     * Khởi tạo service với repository thương hiệu.
     */
    public function __construct(
        private readonly BrandRepositoryInterface $brands
    ) {}

    /**
     * Lấy danh sách thương hiệu phân trang, có tìm kiếm.
     */
    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->brands->paginate($search, $perPage);
    }

    /**
     * Lấy danh sách thương hiệu đang hoạt động cho dropdown.
     */
    public function options(): Collection
    {
        return $this->brands->optionsActive();
    }

    /**
     * Tạo thương hiệu mới trong transaction.
     */
    public function create(array $data): Brand
    {
        return DB::transaction(fn () => $this->brands->create($this->payload($data)));
    }

    /**
     * Cập nhật thương hiệu trong transaction.
     */
    public function update(Brand $brand, array $data): Brand
    {
        return DB::transaction(fn () => $this->brands->update($brand, $this->payload($data)));
    }

    /**
     * Xóa thương hiệu trong transaction.
     */
    public function delete(Brand $brand): bool
    {
        return DB::transaction(fn () => $this->brands->delete($brand));
    }

    /**
     * Chuẩn hóa dữ liệu đầu vào thành payload lưu DB.
     */
    private function payload(array $data): array
    {
        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
