<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $table = 'sites';
    protected $guarded = [];

    protected static function booted(): void
    {

        /* EGO_SITE_MODEL_CREATED_BY_START */
        static::creating(function ($site) {
            try {
                if (auth()->check()
                    && \Illuminate\Support\Facades\Schema::hasColumn($site->getTable(), 'created_by')
                    && empty($site->created_by)) {
                    $site->created_by = auth()->id();
                }
            } catch (\Throwable $e) {
                //
            }
        });
        /* EGO_SITE_MODEL_CREATED_BY_END */

        /* EGO_SITE_SALES_OWNER_START */
        static::creating(function ($site) {
            try {
                if (auth()->check()
                    && \Illuminate\Support\Facades\Schema::hasColumn($site->getTable(), 'created_by')
                    && empty($site->created_by)) {
                    $site->created_by = auth()->id();
                }
            } catch (\Throwable $e) {
                //
            }
        });

        static::addGlobalScope('ego_sales_only_own_sites', function (\Illuminate\Database\Eloquent\Builder $builder) {
            try {
                if (app()->runningInConsole() || !auth()->check()) {
                    return;
                }

                $user = auth()->user();

                $roles = [];

                foreach (['role', 'type', 'position', 'department'] as $field) {
                    if (!empty($user->{$field})) {
                        $roles[] = strtolower((string) $user->{$field});
                    }
                }

                if (method_exists($user, 'getRoleNames')) {
                    foreach ($user->getRoleNames() as $roleName) {
                        $roles[] = strtolower((string) $roleName);
                    }
                }

                $roles = array_unique(array_filter($roles));

                $isAdmin = ((int)($user->is_admin ?? 0) === 1)
                    || in_array('admin', $roles, true)
                    || in_array('administrator', $roles, true);

                $isAccounting = count(array_intersect($roles, [
                    'accounting', 'ketoan', 'ke_toan', 'kế toán', 'ketoan_truong'
                ])) > 0;

                $isTechnicalOrWarehouse = count(array_intersect($roles, [
                    'ky_thuat', 'kỹ thuật', 'technical', 'warehouse', 'kho', 'manager'
                ])) > 0;

                $isSales = count(array_intersect($roles, [
                    'sales', 'sale', 'sales_manager', 'kinh_doanh', 'kinh doanh', 'nhan_vien_kinh_doanh'
                ])) > 0;

                if ($isSales && !$isAdmin && !$isAccounting && !$isTechnicalOrWarehouse) {
                    $table = $builder->getModel()->getTable();

                    if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'created_by')) {
                        $builder->where($table . '.created_by', $user->id);
                    }
                }
            } catch (\Throwable $e) {
                //
            }
        });
        /* EGO_SITE_SALES_OWNER_END */
}

}
