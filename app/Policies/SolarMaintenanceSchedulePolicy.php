<?php

namespace App\Policies;

use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;

/**
 * Policy phân quyền các thao tác trên lịch bảo trì điện mặt trời.
 */
class SolarMaintenanceSchedulePolicy
{
    /**
     * Cho phép xem danh sách lịch bảo trì điện mặt trời hay không.
     */
    public function viewAny(User $user): bool
    {
        return SolarMaintenanceAccess::canViewAny($user);
    }

    /**
     * Cho phép xem chi tiết lịch (kỹ thuật viên chỉ xem lịch được phân công).
     */
    public function view(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        // EGO_TECHNICAL_VIEW_ALL_POLICY_V2
        if (\App\Support\SolarMaintenanceAccess::isTechnician($user)) {
            return true;
        }

        if (! SolarMaintenanceAccess::canViewAny($user) || ! $this->sameCompany($user, $schedule)) {
            return false;
        }

        if (! SolarMaintenanceAccess::isTechnicianOnly($user)) {
            return true;
        }

        return $this->isAssigned($user, $schedule);
    }

    /**
     * Cho phép tạo mới lịch bảo trì điện mặt trời hay không.
     */
    public function create(User $user): bool
    {
        return SolarMaintenanceAccess::canManage($user);
    }

    /**
     * Manager/người có quyền hoặc kỹ thuật viên được phân công được cập nhật lịch.
     */
    public function update(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (! $this->sameCompany($user, $schedule)) {
            return false;
        }

        return SolarMaintenanceAccess::isManager($user)
            || SolarMaintenanceAccess::hasPermission($user, 'maintenance.update')
            || (SolarMaintenanceAccess::isTechnician($user) && $this->isAssigned($user, $schedule));
    }

    /**
     * Cho phép đổi trạng thái lịch (cùng điều kiện với quyền cập nhật).
     */
    public function changeStatus(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    /**
     * Cho phép tải tệp đính kèm lên lịch bảo trì hay không.
     */
    public function uploadAttachment(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        // Đồng bộ với màn hình O&M và các action workflow: mọi nhân sự Kỹ thuật
        // hoặc quản lý đều được nộp minh chứng/báo cáo, kể cả khi đợt chưa gán người.
        // Quyền này cố ý không phụ thuộc isAssigned() để khớp với UI/workflow:
        // kỹ thuật có thể bổ sung minh chứng trước khi nhóm thực hiện được phân công.
        if (SolarMaintenanceAccess::isTechnician($user)
            || SolarMaintenanceAccess::isManager($user)) {
            return true;
        }

        return $this->sameCompany($user, $schedule)
            && SolarMaintenanceAccess::hasPermission($user, 'maintenance.files.upload');
    }

    /**
     * Cho phép gửi lịch bảo trì đi duyệt hay không.
     */
    public function submitForApproval(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (! $this->sameCompany($user, $schedule)) {
            return false;
        }

        return SolarMaintenanceAccess::isManager($user)
            || SolarMaintenanceAccess::hasPermission($user, 'maintenance.submit')
            || $this->isAssigned($user, $schedule);
    }

    /**
     * Người duyệt phải cùng công ty và không phải người được phân công.
     */
    public function approve(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->sameCompany($user, $schedule)
            && SolarMaintenanceAccess::canApprove($user)
            && ! $this->isAssigned($user, $schedule);
    }

    /**
     * Cho phép yêu cầu chỉnh sửa lại (cùng điều kiện với quyền duyệt).
     */
    public function requestRevision(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->approve($user, $schedule);
    }

    /**
     * Cho phép từ chối lịch (cùng điều kiện với quyền duyệt).
     */
    public function reject(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->approve($user, $schedule);
    }

    /**
     * Manager/người có quyền được mở lại lịch.
     */
    public function reopen(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->sameCompany($user, $schedule)
            && (SolarMaintenanceAccess::isManager($user)
                || SolarMaintenanceAccess::hasPermission($user, 'maintenance.reopen'));
    }

    /**
     * Chỉ admin cùng công ty được xoá lịch.
     */
    public function delete(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return SolarMaintenanceAccess::isAdmin($user) && $this->sameCompany($user, $schedule);
    }

    /**
     * Chỉ admin cùng công ty được khôi phục lịch.
     */
    public function restore(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return SolarMaintenanceAccess::isAdmin($user) && $this->sameCompany($user, $schedule);
    }

    /**
     * Kiểm tra user và lịch có cùng công ty hay không (admin luôn đạt).
     */
    private function sameCompany(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (SolarMaintenanceAccess::isAdmin($user)) {
            return true;
        }

        $currentCompanyId = EgoCompanyScope::currentId();
        if ($currentCompanyId <= 0) {
            return true;
        }

        $resolvedCompanyId = $this->resolvedCompanyId($schedule);

        // Dữ liệu cũ chưa xác định công ty chỉ Trưởng phòng kỹ thuật / quản lý được xem để xử lý.
        if ($resolvedCompanyId <= 0) {
            return SolarMaintenanceAccess::isManager($user);
        }

        return $resolvedCompanyId === $currentCompanyId;
    }

    /**
     * Xác định company_id thực của lịch (ưu tiên lịch, sau đó công trình).
     */
    private function resolvedCompanyId(SolarMaintenanceSchedule $schedule): int
    {
        $scheduleCompanyId = (int) ($schedule->company_id ?? 0);
        if ($scheduleCompanyId > 0) {
            return $scheduleCompanyId;
        }

        if ($schedule->relationLoaded('site')) {
            return (int) ($schedule->site?->company_id ?? 0);
        }

        if (! $schedule->site_id) {
            return 0;
        }

        return (int) ($schedule->site()->withoutGlobalScopes()->value('company_id') ?? 0);
    }

    /**
     * Kiểm tra user có được phân công vào lịch hay không.
     */
    private function isAssigned(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if ((int) $schedule->assigned_to === (int) $user->id) {
            return true;
        }

        if ($schedule->relationLoaded('assignees')) {
            return $schedule->assignees->contains(
                fn ($item) => (int) $item->user_id === (int) $user->id
            );
        }

        return $schedule->assignees()->where('user_id', $user->id)->exists();
    }
}
