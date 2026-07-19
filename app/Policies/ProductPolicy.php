<?php

namespace App\Policies;

use App\Models\Inventory\Catalog\Product;
use App\Models\User;

class ProductPolicy
{
    private function isAdminOrAccountingOrWarehouse(User $user): bool
    {
        // Spatie roles: admin, accounting (kế toán), warehouse (kho)
        return $user->hasAnyRole(['admin', 'accounting', 'warehouse']);
    }

    public function viewAny(User $user): bool
    {
        // ai được xem danh sách sản phẩm (giữ như bạn đang dùng)
        return $this->isAdminOrAccountingOrWarehouse($user)
            || $user->can('product.view')
            || $user->can('products.manage');
    }

    public function view(User $user, Product $product): bool
    {
        return $this->isAdminOrAccountingOrWarehouse($user)
            || $user->can('product.view')
            || $user->can('products.manage');
    }

    public function create(User $user): bool
    {
        // tạo/sửa/xoá có thể giữ theo manage
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'warehouse'])
            || $user->can('products.manage');
    }

    // ✅ QUAN TRỌNG: Quyền xem GIÁ VỐN
    public function viewCost(User $user): bool
    {
        return $this->isAdminOrAccountingOrWarehouse($user);
    }
}
