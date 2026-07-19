<?php

namespace App\Policies;

use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;

class SolarMaintenanceSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return SolarMaintenanceAccess::canViewAny($user);
    }

    public function view(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (!SolarMaintenanceAccess::canViewAny($user) || !$this->sameCompany($user, $schedule)) {
            return false;
        }

        if (!SolarMaintenanceAccess::isTechnicianOnly($user)) {
            return true;
        }

        return $this->isAssigned($user, $schedule);
    }

    public function create(User $user): bool
    {
        return SolarMaintenanceAccess::canManage($user);
    }

    public function update(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (!$this->sameCompany($user, $schedule)) {
            return false;
        }

        return SolarMaintenanceAccess::isManager($user)
            || SolarMaintenanceAccess::hasPermission($user, 'maintenance.update')
            || (SolarMaintenanceAccess::isTechnician($user) && $this->isAssigned($user, $schedule));
    }

    public function changeStatus(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function uploadAttachment(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->update($user, $schedule)
            || ($this->sameCompany($user, $schedule)
                && SolarMaintenanceAccess::hasPermission($user, 'maintenance.files.upload'));
    }

    public function submitForApproval(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        if (!$this->sameCompany($user, $schedule)) {
            return false;
        }

        return SolarMaintenanceAccess::isManager($user)
            || SolarMaintenanceAccess::hasPermission($user, 'maintenance.submit')
            || $this->isAssigned($user, $schedule);
    }

    public function approve(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->sameCompany($user, $schedule)
            && SolarMaintenanceAccess::canApprove($user)
            && !$this->isAssigned($user, $schedule);
    }

    public function requestRevision(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->approve($user, $schedule);
    }

    public function reject(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->approve($user, $schedule);
    }

    public function reopen(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return $this->sameCompany($user, $schedule)
            && (SolarMaintenanceAccess::isManager($user)
                || SolarMaintenanceAccess::hasPermission($user, 'maintenance.reopen'));
    }

    public function delete(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return SolarMaintenanceAccess::isAdmin($user) && $this->sameCompany($user, $schedule);
    }

    public function restore(User $user, SolarMaintenanceSchedule $schedule): bool
    {
        return SolarMaintenanceAccess::isAdmin($user) && $this->sameCompany($user, $schedule);
    }

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

    private function resolvedCompanyId(SolarMaintenanceSchedule $schedule): int
    {
        $scheduleCompanyId = (int) ($schedule->company_id ?? 0);
        if ($scheduleCompanyId > 0) {
            return $scheduleCompanyId;
        }

        if ($schedule->relationLoaded('site')) {
            return (int) ($schedule->site?->company_id ?? 0);
        }

        if (!$schedule->site_id) {
            return 0;
        }

        return (int) ($schedule->site()->withoutGlobalScopes()->value('company_id') ?? 0);
    }

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
