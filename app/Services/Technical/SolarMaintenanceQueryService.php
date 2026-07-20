<?php

namespace App\Services\Technical;

use App\Models\Site;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\EgoCompanyScope;
use App\Support\SolarMaintenanceAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Service truy vấn dữ liệu lịch bảo trì điện mặt trời (danh sách, thống kê, tìm công trình, nhân sự kỹ thuật).
 */
class SolarMaintenanceQueryService
{
    /**
     * Lấy danh sách lịch bảo trì có phân trang, ưu tiên lịch quá hạn và sắp đến hạn.
     */
    public function schedules(Request $request, User $user)
    {
        $query = SolarMaintenanceSchedule::query()
            ->with([
                'site:id,name,contact_name,contact_phone,address,system_kwp,system_kw_ac,battery_kwh,system_type,phase,installed_at,warranty_to,monitoring_link,monitoring_account,company_id',
                'assignees.user:id,name,email,phone_number,department_id,position_id',
            ])
            ->withCount('attachments');

        $this->scopeVisibleTo($query, $user);
        $this->applyFilters($query, $request);

        return $query
            ->orderByRaw("CASE
                WHEN status NOT IN ('completed','cancelled') AND scheduled_date < CURDATE() THEN 0
                WHEN status NOT IN ('completed','cancelled') AND scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1
                ELSE 2
            END")
            ->orderBy('scheduled_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * Thống kê số lượng lịch theo nhóm: hôm nay, quá hạn, sắp tới, đang làm, chờ duyệt, hoàn thành...
     */
    public function summary(User $user): array
    {
        $query = SolarMaintenanceSchedule::query();
        $this->scopeVisibleTo($query, $user);

        $row = $query->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN scheduled_date = CURDATE() AND status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS today")
            ->selectRaw("SUM(CASE WHEN scheduled_date < CURDATE() AND status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS overdue")
            ->selectRaw("SUM(CASE WHEN scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS upcoming")
            ->selectRaw("SUM(CASE WHEN status IN ('travelling','in_progress') THEN 1 ELSE 0 END) AS in_progress")
            ->selectRaw("SUM(CASE WHEN status IN ('waiting_material','waiting_submission','pending_approval','revision_requested','waiting_customer') THEN 1 ELSE 0 END) AS waiting")
            ->selectRaw("SUM(CASE WHEN status = 'unassigned' THEN 1 ELSE 0 END) AS unassigned")
            ->selectRaw("SUM(CASE WHEN status = 'pending_approval' OR approval_status = 'pending' THEN 1 ELSE 0 END) AS pending_approval")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'today' => (int) ($row->today ?? 0),
            'overdue' => (int) ($row->overdue ?? 0),
            'upcoming' => (int) ($row->upcoming ?? 0),
            'in_progress' => (int) ($row->in_progress ?? 0),
            'waiting' => (int) ($row->waiting ?? 0),
            'unassigned' => (int) ($row->unassigned ?? 0),
            'pending_approval' => (int) ($row->pending_approval ?? 0),
            'completed' => (int) ($row->completed ?? 0),
        ];
    }

    /**
     * Lấy 50 công trình mới nhất (giới hạn theo công ty nếu không phải admin).
     */
    public function recentSites(User $user)
    {
        $companyId = EgoCompanyScope::currentId();

        return Site::withoutGlobalScopes()
            ->select($this->siteColumns())
            ->when($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user), fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('id')
            ->limit(50)
            ->get();
    }

    /**
     * Tìm công trình theo tên, người liên hệ, SĐT hoặc địa chỉ (tối đa 30 kết quả).
     */
    public function searchSites(string $keyword, User $user)
    {
        $companyId = EgoCompanyScope::currentId();

        return Site::withoutGlobalScopes()
            ->select($this->siteColumns())
            ->when($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user), fn ($q) => $q->where('company_id', $companyId))
            ->when($keyword !== '', function ($q) use ($keyword) {
                $like = '%'.$keyword.'%';
                $q->where(function ($sub) use ($like) {
                    $sub->where('name', 'like', $like)
                        ->orWhere('contact_name', 'like', $like)
                        ->orWhere('contact_phone', 'like', $like)
                        ->orWhere('address', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    }

    /**
     * Lấy danh sách nhân sự kỹ thuật khả dụng (theo role, phòng ban, chức vụ liên quan kỹ thuật/bảo hành).
     */
    public function technicalUsers()
    {
        $technicalRoles = [
            'ky_thuat', 'technical', 'technician', 'technical_staff', 'technical_leader',
            'technical_manager', 'maintenance_manager', 'ky_thuat_manager',
            'quan_ly_ky_thuat', 'bao_hanh', 'maintenance',
        ];

        $query = User::query()
            ->select('id', 'name', 'email', 'phone_number', 'department_id', 'position_id')
            ->with([
                'department:id,name,code',
                'position:id,name,code',
                'roles:id,name',
            ])
            ->when(Schema::hasColumn('users', 'is_active'), function ($q) {
                $q->where(function ($active) {
                    $active->where('is_active', 1)->orWhereNull('is_active');
                });
            })
            ->where(function ($q) use ($technicalRoles) {
                $q->whereHas('roles', fn ($roles) => $roles->whereIn('name', $technicalRoles));

                if (Schema::hasTable('departments')) {
                    $q->orWhereHas('department', function ($department) {
                        $department->where(function ($name) {
                            $name->where('name', 'like', '%kỹ thuật%')
                                ->orWhere('name', 'like', '%ky thuat%')
                                ->orWhere('name', 'like', '%technical%')
                                ->orWhere('name', 'like', '%bảo hành%')
                                ->orWhere('name', 'like', '%bao hanh%')
                                ->orWhere('code', 'like', '%TECH%')
                                ->orWhere('code', 'like', '%KT%');
                        });
                    });
                }

                if (Schema::hasTable('positions')) {
                    $q->orWhereHas('position', function ($position) {
                        $position->where(function ($name) {
                            $name->where('name', 'like', '%kỹ thuật%')
                                ->orWhere('name', 'like', '%ky thuat%')
                                ->orWhere('name', 'like', '%technical%')
                                ->orWhere('name', 'like', '%bảo hành%')
                                ->orWhere('name', 'like', '%bao hanh%')
                                ->orWhere('code', 'like', '%TECH%')
                                ->orWhere('code', 'like', '%KT%');
                        });
                    });
                }
            });

        if (Schema::hasColumn('users', 'company_id') && EgoCompanyScope::currentId() > 0) {
            $query->where(function ($company) {
                $company->where('company_id', EgoCompanyScope::currentId())
                    ->orWhereNull('company_id');
            });
        }

        return $query->orderBy('name')->get()
            ->filter(fn (User $user) => SolarMaintenanceAccess::isSelectableTechnician($user))
            ->values();
    }

    /**
     * Áp dụng cùng một phạm vi dữ liệu cho danh sách, trang công trình và trang chi tiết.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $companyId = EgoCompanyScope::currentId();

        if ($companyId > 0 && ! SolarMaintenanceAccess::isAdmin($user)) {
            $query->where(function (Builder $companyQuery) use ($companyId, $user) {
                $companyQuery->where('solar_maintenance_schedules.company_id', $companyId)
                    ->orWhere(function (Builder $fallback) use ($companyId) {
                        $fallback->whereNull('solar_maintenance_schedules.company_id')
                            ->whereHas('site', fn (Builder $site) => $site->where('company_id', $companyId));
                    });

                // Quản lý được phép nhìn dữ liệu cũ chưa có company_id và không còn công trình,
                // để liên kết hoặc xử lý lại. Kỹ thuật viên thường không nhìn thấy nhóm này.
                if (SolarMaintenanceAccess::isManager($user)) {
                    $companyQuery->orWhere(function (Builder $unknown) {
                        $unknown->whereNull('solar_maintenance_schedules.company_id')
                            ->where(function (Builder $missingSite) {
                                $missingSite->whereNull('solar_maintenance_schedules.site_id')
                                    ->orWhereDoesntHave('site');
                            });
                    });
                }
            });
        }

        if (SolarMaintenanceAccess::isTechnicianOnly($user)) {
            $query->where(function (Builder $assigned) use ($user) {
                $assigned->where('assigned_to', $user->id)
                    ->orWhereHas('assignees', fn (Builder $assignee) => $assignee->where('user_id', $user->id));
            });
        }

        return $query;
    }

    /**
     * Danh sách cột công trình dùng chung cho các truy vấn site.
     */
    private function siteColumns(): array
    {
        return [
            'id', 'name', 'contact_name', 'contact_phone', 'address', 'system_kwp',
            'system_kw_ac', 'battery_kwh', 'system_type', 'phase', 'installed_at',
            'warranty_to', 'monitoring_link', 'monitoring_account', 'company_id',
        ];
    }

    /**
     * Áp dụng các bộ lọc từ request: từ khoá, trạng thái, loại, ưu tiên, người phụ trách, quá hạn, khoảng ngày/tháng.
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $search = trim((string) $request->input('q', ''));
        $month = trim((string) $request->input('month', now()->format('Y-m')));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('schedule_code', 'like', $like)
                    ->orWhere('site_name', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhereHas('site', function (Builder $siteQuery) use ($like) {
                        $siteQuery->where('name', 'like', $like)
                            ->orWhere('contact_name', 'like', $like)
                            ->orWhere('contact_phone', 'like', $like);
                    });
            });
        }

        foreach (['status', 'type', 'priority'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('assignee_id')) {
            $assigneeId = (int) $request->input('assignee_id');
            $query->where(function (Builder $q) use ($assigneeId) {
                $q->where('assigned_to', $assigneeId)
                    ->orWhereHas('assignees', fn (Builder $sub) => $sub->where('user_id', $assigneeId));
            });
        }

        if ($request->boolean('overdue')) {
            $query->whereNotIn('status', ['completed', 'cancelled'])
                ->whereDate('scheduled_date', '<', today());
        }

        if ($dateFrom || $dateTo) {
            if ($dateFrom) {
                $query->whereDate('scheduled_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('scheduled_date', '<=', $dateTo);
            }
        } elseif ($month !== '') {
            $query->whereYear('scheduled_date', substr($month, 0, 4))
                ->whereMonth('scheduled_date', substr($month, 5, 2));
        }
    }
}
