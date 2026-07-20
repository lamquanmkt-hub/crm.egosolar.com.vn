<?php

namespace App\Policies;

use App\Models\CRM\Customers\Customer;
use App\Models\User;

/**
 * Policy phân quyền các thao tác trên khách hàng.
 */
class CustomerPolicy
{
    /**
     * Kiểm tra user có vai trò kho/warehouse hoặc admin hay không.
     */
    private function isKhoOrWarehouse(User $user): bool
    {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kho', 'warehouse', 'admin'])) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('kho') || $user->hasRole('warehouse') || $user->hasRole('admin');
        }

        $roleText = strtolower(implode(' ', array_filter([
            $user->role ?? null,
            $user->role_name ?? null,
            $user->department ?? null,
            $user->position ?? null,
            $user->type ?? null,
        ])));

        return str_contains($roleText, 'kho')
            || str_contains($roleText, 'warehouse')
            || str_contains($roleText, 'admin');
    }

    /**
     * Admin có permission "*" => bypass mọi quyền trong policy.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->can('*') ? true : null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($this->isKhoOrWarehouse($user)) {
            return true;
        }

        return $user->canAny([
            // Sales
            'customer.view_own',
            // Sales Manager
            'customer.view_sales_all',
            // Accounting / Management / Admin
            'customer.view_all',
        ]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Customer $customer): bool
    {
        // Toàn quyền xem
        if ($this->isKhoOrWarehouse($user) || $user->can('customer.view_all')) {
            return true;
        }

        // Sales Manager: xem khách của Sales
        if ($user->can('customer.view_sales_all')) {
            return true;
            // Nếu cần giới hạn theo team:
            // return $customer->sales_id === $user->id
            //     || $user->isManagerOf($customer->sales_id);
        }

        // Sales: chỉ xem khách của mình
        return $user->can('customer.view_own')
            && $customer->owner_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('customer.create');

    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Customer $customer): bool
    {
        // Admin
        if ($user->can('customer.update_all')) {
            return true;
        }

        // Sales Manager: cập nhật khách của Sales
        if ($user->can('customer.update_sales')) {
            return true;
            // Nếu cần giới hạn theo team:
            // return $user->isManagerOf($customer->owner_id);
        }

        // Sales: cập nhật khách của mình
        return $user->can('customer.update_own')
            && $customer->owner_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customer.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Customer $customer): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Customer $customer): bool
    {
        return false;
    }
}
