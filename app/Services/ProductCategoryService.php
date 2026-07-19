<?php

namespace App\Services;

use App\Models\Inventory\Catalog\ProductCategory;
use App\Repositories\Interfaces\ProductCategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Service Layer cho ProductCategory
 * Áp dụng SOLID principles:
 * - Single Responsibility: Chỉ xử lý business logic cho ProductCategory
 * - Dependency Inversion: Phụ thuộc vào interface, không phụ thuộc vào implementation
 */
class ProductCategoryService
{
    protected ProductCategoryRepositoryInterface $repository;

    /**
     * Dependency Injection - Constructor Injection
     * @param ProductCategoryRepositoryInterface $repository
     */
    public function __construct(ProductCategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Lấy danh sách categories có phân trang
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($perPage);
    }

    /**
     * Lấy tất cả categories (không phân trang)
     * @return EloquentCollection
     */
    public function getAll(): EloquentCollection
    {
        return $this->repository->all();
    }

    /**
     * Lấy danh sách categories dạng tree (hierarchical)
     * @return EloquentCollection
     */
    public function getTree(): EloquentCollection
    {
        $categories = $this->repository->all();
        return $this->buildTree($categories);
    }

    /**
     * Lấy danh sách categories dạng flat với parent name
     * @return EloquentCollection
     */
    public function getAllWithParent(): EloquentCollection
    {
        return ProductCategory::with('parent')->orderBy('name')->get();
    }

    /**
     * Tìm category theo ID
     * @param int $id
     * @return ProductCategory|null
     */
    public function find(int $id): ?ProductCategory
    {
        return $this->repository->find($id);
    }

    /**
     * Tìm category với products
     * @param int $id
     * @return ProductCategory|null
     */
    public function findWithProducts(int $id): ?ProductCategory
    {
        return $this->repository->findWithProducts($id);
    }

    /**
     * Tạo category mới
     * @param array $data
     * @return ProductCategory
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
     * @param int $id
     * @param array $data
     * @return bool
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
     * @param int $id
     * @return bool
     * @throws \Exception
     */
    public function delete(int $id): bool
    {
        $category = $this->repository->find($id);

        if (!$category) {
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
     * @param EloquentCollection $categories
     * @param int|null $parentId
     * @return EloquentCollection
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
     * @param int $parentId
     * @param int|null $excludeId
     * @return void
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
     * @param ProductCategory $category
     * @return EloquentCollection
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

