<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MaterialRequestStatus;
use App\Models\Projects\MaterialRequest;
use App\Models\User;

/**
 * Policy phân quyền quy trình phiếu yêu cầu vật tư (tạo, duyệt, xuất kho).
 */
class MaterialRequestPolicy
{
    /**
     * Cho phép xem danh sách phiếu yêu cầu vật tư hay không.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Người tạo, manager hoặc các vai trò nội bộ được xem phiếu.
     */
    public function view(User $user, MaterialRequest $mr): bool
    {
        return $user->id === $mr->created_by
            || $user->hasRole('manager')
            || $user->hasRole(['admin', 'accounting', 'warehouse', 'kho', 'ky_thuat']);
    }

    /**
     * Kỹ thuật/admin/kho được tạo phiếu yêu cầu vật tư.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['ky_thuat', 'admin', 'warehouse', 'kho']);
    }

    /**
     * Chỉ được sửa phiếu còn nháp (người tạo hoặc admin/kỹ thuật/kho).
     */
    public function update(User $user, MaterialRequest $mr): bool
    {
        return $mr->status === MaterialRequestStatus::DRAFT
            && (
                $user->id === $mr->created_by
                || $user->hasRole(['admin', 'ky_thuat', 'warehouse', 'kho'])
            );
    }

    /**
     * Chỉ được xoá phiếu còn nháp (người tạo hoặc admin).
     */
    public function delete(User $user, MaterialRequest $mr): bool
    {
        return $mr->status === MaterialRequestStatus::DRAFT
            && (
                $user->id === $mr->created_by
                || $user->hasRole('admin')
            );
    }

    /**
     * Chỉ admin được khôi phục phiếu.
     */
    public function restore(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Chỉ admin được xoá vĩnh viễn phiếu.
     */
    public function forceDelete(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('admin');
    }

    // ✅ QUAN TRỌNG: GỬI DUYỆT
    /**
     * Người tạo được gửi duyệt khi phiếu còn nháp.
     */
    public function submit(User $user, MaterialRequest $mr): bool
    {
        return strtoupper((string) $mr->status) === 'DRAFT'
            && (int) $user->id === (int) $mr->created_by;
    }

    /**
     * Manager duyệt phiếu ở trạng thái đã gửi (SUBMITTED).
     */
    public function approve(User $user, MaterialRequest $mr): bool
    {
        return $mr->status === MaterialRequestStatus::SUBMITTED
            && $user->hasRole('manager');
    }

    /**
     * Kế toán duyệt phiếu ở trạng thái đã gửi (SUBMITTED).
     */
    public function accountingApprove(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('accounting')
            && $mr->status === MaterialRequestStatus::SUBMITTED;
    }

    /**
     * Kế toán từ chối phiếu (cùng điều kiện với duyệt kế toán).
     */
    public function accountingReject(User $user, MaterialRequest $mr): bool
    {
        return $this->accountingApprove($user, $mr);
    }

    /**
     * Admin duyệt phiếu đã được kế toán duyệt (ACC_APPROVED).
     */
    public function adminApprove(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole('admin')
            && $mr->status === MaterialRequestStatus::ACC_APPROVED;
    }

    /**
     * Admin từ chối phiếu (cùng điều kiện với duyệt admin).
     */
    public function adminReject(User $user, MaterialRequest $mr): bool
    {
        return $this->adminApprove($user, $mr);
    }

    /**
     * Kho được xuất vật tư khi phiếu đã được admin duyệt.
     */
    public function export(User $user, MaterialRequest $mr): bool
    {
        return $user->hasRole(['warehouse', 'kho'])
            && $mr->status === MaterialRequestStatus::ADMIN_APPROVED;
    }
}
