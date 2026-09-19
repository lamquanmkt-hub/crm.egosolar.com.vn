<?php

namespace App\Services\Synced\Technical;

use App\Models\Site;
use App\Models\SolarMaintenanceSchedule;
use App\Models\User;
use App\Support\Synced\EgoCompanyScope;
use App\Support\SchemaCache;
use App\Support\SolarMaintenanceAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
            ->selectRaw("SUM(CASE WHEN status IN ('draft','scheduled','unassigned','assigned','customer_confirmed','postponed') THEN 1 ELSE 0 END) AS scheduled_bucket")
            ->selectRaw("SUM(CASE WHEN status IN ('travelling','in_progress','waiting_material','waiting_submission','revision_requested','approved','waiting_customer') THEN 1 ELSE 0 END) AS processing_bucket")
            ->selectRaw("SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS approval_bucket")
            ->selectRaw("SUM(CASE WHEN status IN ('cancelled') THEN 1 ELSE 0 END) AS cancelled_bucket")
            ->selectRaw("SUM(CASE WHEN status = 'completed' AND YEAR(COALESCE(completed_date, DATE(completed_at), scheduled_date)) = YEAR(CURDATE()) AND MONTH(COALESCE(completed_date, DATE(completed_at), scheduled_date)) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS completed_this_month")
            ->selectRaw("SUM(CASE WHEN status = 'completed' AND YEAR(COALESCE(completed_date, DATE(completed_at), scheduled_date)) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(COALESCE(completed_date, DATE(completed_at), scheduled_date)) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN 1 ELSE 0 END) AS completed_last_month")
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
            'scheduled_bucket' => (int) ($row->scheduled_bucket ?? 0),
            'processing_bucket' => (int) ($row->processing_bucket ?? 0),
            'approval_bucket' => (int) ($row->approval_bucket ?? 0),
            'cancelled_bucket' => (int) ($row->cancelled_bucket ?? 0),
            'completed_this_month' => (int) ($row->completed_this_month ?? 0),
            'completed_last_month' => (int) ($row->completed_last_month ?? 0),
            'completion_rate' => (int) (($row->total ?? 0) > 0 ? round(((int) ($row->completed ?? 0) / (int) $row->total) * 100) : 0),
        ];
    }

    /**
     * Danh sách lịch quan trọng dành cho trang điều hành Ban Giám đốc.
     * Không phụ thuộc bộ lọc tháng của trang danh sách; ưu tiên quá hạn, hôm nay và 7 ngày tới.
     */
    public function overviewSchedules(User $user, int $limit = 8): Collection
    {
        $query = SolarMaintenanceSchedule::query()
            ->with([
                'site:id,name,address,system_kwp,system_kw_ac,system_type,company_id',
                'assignees.user:id,name,email,phone_number,department_id,position_id',
            ]);

        $this->scopeVisibleTo($query, $user);

        return $query
            ->whereNotIn('status', ['cancelled'])
            ->orderByRaw("CASE
                WHEN status NOT IN ('completed','cancelled') AND scheduled_date < CURDATE() THEN 0
                WHEN status NOT IN ('completed','cancelled') AND scheduled_date = CURDATE() THEN 1
                WHEN status NOT IN ('completed','cancelled') AND scheduled_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 2
                WHEN status NOT IN ('completed','cancelled') THEN 3
                ELSE 4
            END")
            ->orderByRaw("CASE WHEN priority='urgent' THEN 0 WHEN priority='high' THEN 1 WHEN priority='normal' THEN 2 ELSE 3 END")
            ->orderBy('scheduled_date')
            ->orderByDesc('id')
            ->limit(max(1, min($limit, 20)))
            ->get();
    }

    /**
     * Tổng hợp tải công việc của kỹ thuật viên để Giám đốc nhìn thấy ngay ai đang phụ trách,
     * ai có lịch quá hạn và tỷ lệ hoàn thành của từng người.
     */
    public function technicianWorkload(User $viewer, int $limit = 6): Collection
    {
        $technicians = $this->technicalUsers();

        return $technicians->map(function (User $technician) use ($viewer) {
            $base = SolarMaintenanceSchedule::query();
            $this->scopeVisibleTo($base, $viewer);
            $base->where(function (Builder $assigned) use ($technician) {
                $assigned->where('assigned_to', $technician->id)
                    ->orWhereHas('assignees', fn (Builder $assignee) => $assignee->where('user_id', $technician->id));
            })->whereNotIn('status', ['cancelled']);

            $total = (clone $base)->count();
            $active = (clone $base)->whereNotIn('status', ['completed', 'cancelled'])->count();
            $activeSites = (clone $base)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('site_id')
                ->distinct('site_id')
                ->count('site_id');
            $overdue = (clone $base)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereDate('scheduled_date', '<', today())
                ->count();
            $todayCount = (clone $base)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereDate('scheduled_date', today())
                ->count();
            $completed = (clone $base)->where('status', 'completed')->count();

            return [
                'user' => $technician,
                'total' => $total,
                'active' => $active,
                'active_sites' => $activeSites,
                'overdue' => $overdue,
                'today' => $todayCount,
                'completion_rate' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            ];
        })
            ->sortByDesc(fn (array $row) => ($row['active'] * 1000) + ($row['overdue'] * 100) + $row['total'])
            ->take(max(1, min($limit, 20)))
            ->values();
    }

    /**
     * Thống kê các đợt bảo trì theo round_no để hiển thị tiến độ từng đợt.
     */
    public function roundOverview(User $user, int $limit = 4): Collection
    {
        $maxQuery = SolarMaintenanceSchedule::query();
        $this->scopeVisibleTo($maxQuery, $user);
        $maxRounds = (int) ($maxQuery->max('total_rounds') ?: 1);
        $roundCount = max(1, min($limit, $maxRounds));
        $rows = collect();

        for ($round = 1; $round <= $roundCount; $round++) {
            $query = SolarMaintenanceSchedule::query();
            $this->scopeVisibleTo($query, $user);
            $query->whereRaw('COALESCE(NULLIF(round_no, 0), 1) = ?', [$round])
                ->whereNotIn('status', ['cancelled']);

            $stats = $query->selectRaw('COUNT(*) AS total')
                ->selectRaw('MIN(scheduled_date) AS start_date')
                ->selectRaw('MAX(scheduled_date) AS end_date')
                ->selectRaw("SUM(CASE WHEN status NOT IN ('draft','unassigned') THEN 1 ELSE 0 END) AS scheduled_count")
                ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count")
                ->first();

            $startDate = ! empty($stats->start_date) ? Carbon::parse($stats->start_date)->startOfDay() : null;
            $endDate = ! empty($stats->end_date) ? Carbon::parse($stats->end_date)->startOfDay() : null;
            $total = (int) ($stats->total ?? 0);
            $scheduledCount = (int) ($stats->scheduled_count ?? 0);

            $rows->push([
                'round' => $round,
                'total' => $total,
                'scheduled_count' => $scheduledCount,
                'completed_count' => (int) ($stats->completed_count ?? 0),
                'progress' => $total > 0 ? (int) round(($scheduledCount / $total) * 100) : 0,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_to_start' => $startDate ? today()->diffInDays($startDate, false) : null,
            ]);
        }

        return $rows;
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
            'technical', 'technician', 'technical_staff', 'technical_leader',
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
            ->when(SchemaCache::hasColumn('users', 'is_active'), function ($q) {
                $q->where(function ($active) {
                    $active->where('is_active', 1)->orWhereNull('is_active');
                });
            })
            ->where(function ($q) use ($technicalRoles) {
                $q->whereHas('roles', fn ($roles) => $roles->whereIn('name', $technicalRoles));

                if (SchemaCache::hasTable('departments')) {
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

                if (SchemaCache::hasTable('positions')) {
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

        if (SchemaCache::hasColumn('users', 'company_id') && EgoCompanyScope::currentId() > 0) {
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
        // Kỹ thuật xem toàn bộ lịch O&M; quyền sửa/duyệt vẫn do Policy kiểm soát.
        if (SolarMaintenanceAccess::isTechnician($user)) {
            return $query;
        }

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
