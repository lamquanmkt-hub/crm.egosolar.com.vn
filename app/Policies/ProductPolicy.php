<?php

namespace App\Policies;

use App\Models\Inventory\Catalog\Product;
use App\Models\User;

/**
 * Policy phân quyền các thao tác trên sản phẩm.
 */
class ProductPolicy
{
    /**
     * Kiểm tra user có vai trò admin/kế toán/kho hay không.
     */
    private function isAdminOrAccountingOrWarehouse(User $user): bool
    {
        // Spatie roles: admin, accounting (kế toán), warehouse (kho)
        return $user->hasAnyRole(['admin', 'accounting', 'warehouse']);
    }

    /**
     * Cho phép xem danh sách sản phẩm hay không.
     */
    public function viewAny(User $user): bool
    {
        // ai được xem danh sách sản phẩm (giữ như bạn đang dùng)
        return $this->isAdminOrAccountingOrWarehouse($user)
            || $user->can('product.view')
            || $user->can('products.manage');
    }

    /**
     * Cho phép xem chi tiết sản phẩm hay không.
     */
    public function view(User $user, Product $product): bool
    {
        return $this->isAdminOrAccountingOrWarehouse($user)
            || $user->can('product.view')
            || $user->can('products.manage');
    }

    /**
     * Cho phép tạo mới sản phẩm hay không.
     */
    public function create(User $user): bool
    {
        // tạo/sửa/xoá có thể giữ theo manage
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    /**
     * Cho phép cập nhật sản phẩm hay không.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    /**
     * Cho phép xoá sản phẩm hay không.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    /**
     * Cho phép khôi phục sản phẩm đã xoá hay không.
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    /**
     * Cho phép xoá vĩnh viễn sản phẩm hay không.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    // ✅ QUAN TRỌNG: Quyền xem GIÁ VỐN
    /**
     * Cho phép xem giá vốn sản phẩm (admin/kế toán/kho).
     */
    public function viewCost(User $user): bool
    {
        return $this->isAdminOrAccountingOrWarehouse($user);
    }
}
