<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ProductCategoryRepositoryInterface;
use App\Contracts\Services\ProductCategoryServiceInterface;
use App\Models\Inventory\Catalog\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Service Layer cho ProductCategory
 * Áp dụng SOLID principles:
 * - Single Responsibility: Chỉ xử lý business logic cho ProductCategory
 * - Dependency Inversion: Phụ thuộc vào interface, không phụ thuộc vào implementation
 */
class ProductCategoryService implements ProductCategoryServiceInterface
{
    protected ProductCategoryRepositoryInterface $repository;

    /**
     * Dependency Injection - Constructor Injection
     */
    public function __construct(ProductCategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Lấy danh sách categories có phân trang
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Lấy tất cả categories (không phân trang)
     */
    public function getAll(): EloquentCollection
    {
        return $this->repository->all();
    }

    /**
     * Lấy danh sách categories dạng tree (hierarchical)
     */
    public function getTree(): EloquentCollection
    {
        $categories = $this->repository->all();

        return $this->buildTree($categories);
    }

    /**
     * Lấy danh sách categories dạng flat với parent name
     */
    public function getAllWithParent(): EloquentCollection
    {
        return ProductCategory::with('parent')->orderBy('name')->get();
    }

    /**
     * Tìm category theo ID
     */
    public function find(int $id): ?ProductCategory
    {
        return $this->repository->find($id);
    }

    /**
     * Tìm category với products
     */
    public function findWithProducts(int $id): ?ProductCategory
    {
        return $this->repository->findWithProducts($id);
    }

    /**
     * Tạo category mới
     */
    public function create(array $data): ProductCategory
    {
        // Validate parent_id không được trỏ vào chính nó
        if (isset($data['parent_id']) && $data['parent_id']) {
            $this->validateParentId($data['parent_id']);
        }

        return $this->repository->create($data);
    }

    /**
     * Cập nhật category
     *
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        // Validate parent_id không được trỏ vào chính nó hoặc con của nó
        if (isset($data['parent_id']) && $data['parent_id']) {
            $this->validateParentId($data['parent_id'], $id);
        }

        return $this->repository->update($id, $data);
    }

    /**
     * Xóa category
     *
     * @throws \Exception
     */
    public function delete(int $id): bool
    {
        $category = $this->repository->find($id);

        if (! $category) {
            throw new \Exception('Category không tồn tại');
        }

        // Kiểm tra xem category có sản phẩm không
        if ($category->products()->count() > 0) {
            throw new \Exception('Không thể xóa category vì đang có sản phẩm thuộc category này');
        }

        // Kiểm tra xem category có category con không
        if ($category->children()->count() > 0) {
            throw new \Exception('Không thể xóa category vì đang có category con');
        }

        return $this->repository->delete($id);
    }

    /**
     * Xây dựng cây phân cấp từ collection
     */
    protected function buildTree(EloquentCollection $categories, ?int $parentId = null): EloquentCollection
    {
        return $categories->filter(function ($category) use ($parentId) {
            return $category->parent_id == $parentId;
        })->map(function ($category) use ($categories) {
            $category->children = $this->buildTree($categories, $category->id);

            return $category;
        });
    }

    /**
     * Validate parent_id không được trỏ vào chính nó hoặc con của nó
     *
     * @throws \Exception
     */
    protected function validateParentId(int $parentId, ?int $excludeId = null): void
    {
        if ($excludeId && $parentId == $excludeId) {
            throw new \Exception('Category không thể là parent của chính nó');
        }

        if ($excludeId) {
            $category = $this->repository->find($excludeId);
            if ($category) {
                $descendants = $this->getDescendants($category);
                if ($descendants->contains('id', $parentId)) {
                    throw new \Exception('Category không thể là parent của category con của nó');
                }
            }
        }
    }

    /**
     * Lấy tất cả descendants của một category
     */
    protected function getDescendants(ProductCategory $category): EloquentCollection
    {
        $descendants = collect();
        $children = $category->children;

        foreach ($children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($this->getDescendants($child));
        }

        return $descendants;
    }
}
