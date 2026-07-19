<?php

namespace App\Policies;

use App\Models\Core\Warehouse;
use App\Models\User;

class WarehousePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('warehouse.stock_check');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.stock_check');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('warehouse.manage');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.manage');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.manage')
            && !$warehouse->hasInventory();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.manage');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouse.manage');
    }
}
