<?php

namespace App\Policies;

use App\Models\Inventory\Catalog\ProductCategory;
use App\Models\User;

/**
 * Policy cho ProductCategory
 * Áp dụng Authorization Pattern và Single Responsibility Principle
 */
class ProductCategoryPolicy
{
    /**
     * Xác định user có thể xem danh sách categories không
     * @param User $user
     * @return bool
     */
    public function viewAny(User $user): bool
    {
        return $user->can('product.view') || $user->can('categories.manage');
    }

    /**
     * Xác định user có thể xem category không
     * @param User $user
     * @param ProductCategory $category
     * @return bool
     */
    public function view(User $user, ProductCategory $category): bool
    {
        return $user->can('product.view') || $user->can('categories.manage');
    }

    /**
     * Xác định user có thể tạo category không
     * @param User $user
     * @return bool
     */
    public function create(User $user): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể cập nhật category không
     * @param User $user
     * @param ProductCategory $category
     * @return bool
     */
    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể xóa category không
     * @param User $user
     * @param ProductCategory $category
     * @return bool
     */
    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể restore category không
     * @param User $user
     * @param ProductCategory $category
     * @return bool
     */
    public function restore(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể force delete category không
     * @param User $user
     * @param ProductCategory $category
     * @return bool
     */
    public function forceDelete(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }
}

