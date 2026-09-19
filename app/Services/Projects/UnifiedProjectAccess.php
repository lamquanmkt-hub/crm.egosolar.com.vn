<?php

declare(strict_types=1);

namespace App\Services\Projects;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** Sales ownership is enforced in SQL, never through a URL filter. */
final class UnifiedProjectAccess
{
    public function roles($user): array
    {
        if (! $user) { return []; }
        $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [];
        foreach (['role', 'type', 'position', 'department'] as $key) {
            if (isset($user->{$key}) && is_scalar($user->{$key})) {
                $roles[] = (string) $user->{$key};
            }
        }
        return array_values(array_unique(array_filter(array_map(
            static fn ($role): string => strtolower(trim((string) $role)), $roles
        ))));
    }

    public function isSalesScoped($user): bool
    {
        if (! $user || (int) ($user->is_admin ?? 0) === 1) { return false; }
        $roles = $this->roles($user);
        // Preserve explicit cross-department roles, not workspace selections.
        if (array_intersect($roles, [
            'admin', 'administrator', 'super_admin', 'management', 'manager',
            'director', 'general_director', 'ban_giam_doc', 'giam_doc',
            'accounting', 'ketoan', 'ke_toan', 'chief_accountant', 'ke_toan_truong',
            'accounting_manager', 'finance', 'finance_manager',
            'technical', 'ky_thuat', 'technical_staff', 'technician', 'engineer',
            'engineering', 'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
            'warehouse', 'kho', 'maintenance', 'bao_hanh',
        ])) { return false; }
        return (bool) array_intersect($roles, [
            'sales', 'sale', 'sales_staff', 'sales_manager',
            'kinh_doanh', 'kinh doanh', 'nhan_vien_kinh_doanh',
        ]);
    }

    public function isSalesManager($user): bool
    {
        return $this->isSalesScoped($user)
            && in_array('sales_manager', $this->roles($user), true);
    }

    public function applySalesScope(Builder $query, $user): void
    {
        if (! $this->isSalesScoped($user)) { return; }
        $table = $query->getModel()->getTable();
        // Fail closed during an incomplete deployment; never expose all sites.
        foreach (['request_source', 'created_by', 'sales_user_id'] as $column) {
            if (! Schema::hasColumn($table, $column)) {
                $query->whereRaw('1 = 0');
                return;
            }
        }
        $query->where($table.'.request_source', 'sales');
        if (! $this->isSalesManager($user)) {
            $id = (int) $user->id;
            $query->where(static function (Builder $owner) use ($table, $id): void {
                $owner->where($table.'.created_by', $id)
                    ->orWhere($table.'.sales_user_id', $id);
            });
        }
    }

    public function ownsSalesSite($site, $user): bool
    {
        if (! $this->isSalesScoped($user)) { return true; }
        if ((string) ($site->request_source ?? '') !== 'sales') { return false; }
        return $this->isSalesManager($user)
            || (int) ($site->created_by ?? 0) === (int) $user->id
            || (int) ($site->sales_user_id ?? 0) === (int) $user->id;
    }
}
