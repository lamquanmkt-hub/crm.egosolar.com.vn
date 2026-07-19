<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MaterialRequestStatus;
use App\Models\Projects\MaterialRequest;
use App\Models\User;

class MaterialRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaterialRequest $mr): bool
    {
        return $user->id === $mr->created_by
            || $user->hasRole('manager')
            || $user->hasRole(['admin', 'accounting', 'warehouse', 'kho', 'ky_thuat']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['ky_thuat', 'admin', 'warehouse', 'kho']);
    }

    public function update(User $user, MaterialRequest $mr): bool
    {
        return $mr->status === MaterialRequestStatus::DRAFT
            && (
                $user->id === $mr->created_by
                || $user->hasRole(['admin', 'ky_thuat', 'warehouse', 'kho'])
            );
    }

    public function delete(User $user, MaterialRequest $mr): bool
    {
        return $mr->status === MaterialRequestStatus::DRAFT
            && (
                $user->id === $mr->created_by
                || $user->hasRole('admin')
            );
    }

    public function restore(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('admin');
    }

    // ✅ QUAN TRỌNG: GỬI DUYỆT
    public function submit(User $user, MaterialRequest $mr): bool
{
    return strtoupper((string)$mr->status) === 'DRAFT'
        && (int)$user->id === (int)$mr->created_by;
}
    public function approve(User $user, MaterialRequest $mr): bool
    {
        return $mr->status === MaterialRequestStatus::SUBMITTED
            && $user->hasRole('manager');
    }

    public function accountingApprove(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('accounting')
            && $mr->status === MaterialRequestStatus::SUBMITTED;
    }

    public function accountingReject(User $user, MaterialRequest $mr): bool
    {
        return $this->accountingApprove($user, $mr);
    }

    public function adminApprove(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('admin')
            && $mr->status === MaterialRequestStatus::ACC_APPROVED;
    }

    public function adminReject(User $user, MaterialRequest $mr): bool
    {
        return $this->adminApprove($user, $mr);
    }

    public function export(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole(['warehouse', 'kho'])
            && $mr->status === MaterialRequestStatus::ADMIN_APPROVED;
    }
}
