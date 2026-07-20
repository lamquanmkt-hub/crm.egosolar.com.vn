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
     */
    public function viewAny(User $user): bool
    {
        return $user->can('product.view') || $user->can('categories.manage');
    }

    /**
     * Xác định user có thể xem category không
     */
    public function view(User $user, ProductCategory $category): bool
    {
        return $user->can('product.view') || $user->can('categories.manage');
    }

    /**
     * Xác định user có thể tạo category không
     */
    public function create(User $user): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể cập nhật category không
     */
    public function update(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể xóa category không
     */
    public function delete(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể restore category không
     */
    public function restore(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }

    /**
     * Xác định user có thể force delete category không
     */
    public function forceDelete(User $user, ProductCategory $category): bool
    {
        return $user->can('categories.manage');
    }
}
