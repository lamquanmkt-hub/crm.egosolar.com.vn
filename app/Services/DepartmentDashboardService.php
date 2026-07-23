<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\EgoCompanyScope;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Dữ liệu dashboard cho từng phòng ban.
 *
 * Mục tiêu:
 * - Không dùng cùng một dashboard tổng cho mọi role.
 * - Quản lý chỉ xem phạm vi phòng ban.
 * - Nhân viên chỉ xem dữ liệu cá nhân/được giao khi bảng dữ liệu hỗ trợ.
 * - Mọi truy vấn đều kiểm tra table/column trước để tương thích source hiện tại.
 */
final class DepartmentDashboardService
{
    /** @var array<string, bool> */
    private array $tableCache = [];

    /** @var array<string, bool> */
    private array $columnCache = [];

    /** @var array<string, string|null> */
    private array $dateColumnCache = [];

    public function resolveWorkspace(User $user): string
    {
        $roles = $this->roles($user);

        $priority = [
            'executive' => [
                'admin', 'super_admin', 'management', 'manager', 'director',
                'ban_giam_doc', 'giam_doc', 'ceo',
            ],
            'sales_manager' => [
                'sales_manager', 'sales_leader', 'truong_phong_sales',
                'truong_phong_kinh_doanh',
            ],
            'marketing_manager' => [
                'marketing_manager', 'marketing_leader', 'truong_phong_marketing',
            ],
            'technical_manager' => [
                'technical_manager', 'technical_leader', 'truong_phong_ky_thuat',
            ],
            'finance' => ['accounting', 'finance', 'ke_toan', 'ketoan'],
            'warehouse' => ['warehouse', 'kho', 'warehouse_manager'],
            'hr' => ['hr', 'human_resource', 'nhan_su', 'hanh_chinh_nhan_su'],
            'marketing' => ['marketing', 'marketing_staff'],
            'technical' => [
                'ky_thuat', 'technical', 'technician', 'technical_staff',
            ],
            'sales' => [
                'sales', 'sale', 'sales_staff', 'kinh_doanh',
                'nhan_vien_kinh_doanh',
            ],
        ];

        foreach ($priority as $workspace => $aliases) {
            if ($roles->intersect($aliases)->isNotEmpty()) {
                return $workspace;
            }
        }

        $departmentCode = $this->slug((string) optional($user->department)->code);
        $departmentName = $this->slug((string) optional($user->department)->name);
        $department = trim($departmentCode.'_'.$departmentName, '_');
        $position = $this->slug((string) optional($user->position)->name);
        $isLeader = Str::contains($position, ['truong_phong', 'quan_ly', 'manager', 'leader']);

        return match (true) {
            Str::contains($department, ['management', 'ban_giam_doc', 'giam_doc']) => 'executive',
            Str::contains($department, ['marketing']) => $isLeader ? 'marketing_manager' : 'marketing',
            Str::contains($department, ['ky_thuat', 'technical']) => $isLeader ? 'technical_manager' : 'technical',
            Str::contains($department, ['ke_toan', 'accounting', 'finance']) => 'finance',
            Str::contains($department, ['warehouse', 'kho']) => 'warehouse',
            Str::contains($department, ['nhan_su', 'hanh_chinh', 'human_resource']) => 'hr',
            Str::contains($department, ['sales', 'kinh_doanh']) => $isLeader ? 'sales_manager' : 'sales',
            default => 'personal',
        };
    }

    public function build(User $user, Request $request, ?string $workspace = null): array
    {
        $workspace ??= $this->resolveWorkspace($user);
        $range = $this->resolveRange($request);

        return match ($workspace) {
            'sales_manager' => $this->sales($user, $range, true),
            'sales' => $this->sales($user, $range, false),
            'marketing_manager' => $this->marketing($user, $range, true),
            'marketing' => $this->marketing($user, $range, false),
            'technical_manager' => $this->technical($user, $range, true),
            'technical' => $this->technical($user, $range, false),
            'finance' => $this->finance($user, $range),
            'warehouse' => $this->warehouse($user, $range),
            'hr' => $this->hr($user, $range),
            default => $this->personal($user, $range),
        };
    }

    /**
     * @return array{period:string,from:Carbon,to:Carbon,label:string,days:int}
     */
    private function resolveRange(Request $request): array
    {
        $period = (string) $request->input('period', 'month');
        if (! in_array($period, ['today', 'week', 'month', 'quarter', 'year', 'custom'], true)) {
            $period = 'month';
        }

        $now = now();
        $fromInput = $this->safeDate($request->input('from'));
        $toInput = $this->safeDate($request->input('to'));

        if ($period === 'custom' && $fromInput && $toInput) {
            $from = Carbon::parse($fromInput)->startOfDay();
            $to = Carbon::parse($toInput)->endOfDay();
        } else {
            [$from, $to] = match ($period) {
                'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
                'week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
                'quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
                'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
                default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            };
        }

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'label' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
            'days' => max(1, $from->diffInDays($to) + 1),
        ];
    }

    private function sales(User $user, array $range, bool $isManager): array
    {
        $teamIds = $this->teamIds(
            ['sales', 'sales_manager'],
            ['sales', 'kinh doanh']
        );
        if ($teamIds->isEmpty()) {
            $teamIds = collect([$user->id]);
        }

        $orders = 0;
        $revenue = 0.0;
        $unshipped = 0;
        $collected = 0.0;
        $leads = 0;
        $followups = 0;
        $trend = $this->emptySeries();
        $watchlist = collect();

        if ($this->hasTable('crm_orders')) {
            $query = DB::table('crm_orders as o');
            $this->applyCompany($query, 'crm_orders', 'o');
            if ($this->hasColumn('crm_orders', 'deleted_at')) {
                $query->whereNull('o.deleted_at');
            }
            $this->applyOwnerScope($query, 'o.created_by', $user, $isManager, $teamIds);
            $this->whereDateRange($query, 'crm_orders', 'o.order_date', 'o.created_at', $range);

            $orders = (int) (clone $query)->count('o.id');
            $revenue = $this->hasColumn('crm_orders', 'total_amount')
                ? (float) (clone $query)->sum('o.total_amount')
                : 0.0;

            $unshippedQuery = clone $query;
            if ($this->hasColumn('crm_orders', 'is_shipped')) {
                $unshippedQuery->where('o.is_shipped', 0);
            } elseif ($this->hasColumn('crm_orders', 'shipping_status')) {
                $unshippedQuery->whereNotIn('o.shipping_status', ['shipped', 'delivered']);
            }
            $unshipped = (int) $unshippedQuery->count('o.id');

            $trend = $this->series(
                clone $query,
                'crm_orders',
                ['order_date', 'created_at'],
                'total_amount',
                $range
            );

            $watchlist = (clone $query)
                ->orderByDesc($this->qualifiedDate('crm_orders', 'o', ['order_date', 'created_at']) ?? 'o.id')
                ->limit(6)
                ->get()
                ->map(fn ($row) => $this->watch(
                    'bi-receipt-cutoff',
                    (string) ($row->order_code ?? 'Đơn hàng #'.$row->id),
                    $this->formatDate($row->order_date ?? $row->created_at ?? null),
                    $this->shippingLabel((string) ($row->shipping_status ?? 'not_shipped')),
                    in_array((string) ($row->shipping_status ?? ''), ['shipped', 'delivered'], true) ? 'success' : 'warning',
                    $this->routeUrl('orders.show', ['id' => $row->id], '/orders/'.$row->id),
                    (float) ($row->total_amount ?? 0),
                    'money'
                ));
        }

        if ($this->hasTable('crm_payments') && $this->hasTable('crm_orders')) {
            $query = DB::table('crm_payments as p')
                ->join('crm_orders as o', 'o.id', '=', 'p.order_id');
            $this->applyCompany($query, 'crm_orders', 'o');
            $this->applyOwnerScope($query, 'o.created_by', $user, $isManager, $teamIds);
            $this->whereDateRange($query, 'crm_payments', 'p.payment_date', 'p.created_at', $range);
            if ($this->hasColumn('crm_payments', 'amount')) {
                $collected = (float) $query->sum('p.amount');
            }
        }

        if ($this->hasTable('crm_leads')) {
            $query = DB::table('crm_leads as l');
            if ($isManager) {
                $query->where(function (Builder $scope) use ($teamIds): void {
                    $scope->whereIn('l.assigned_to', $teamIds->all())
                        ->orWhereIn('l.created_by', $teamIds->all());
                });
            } else {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('l.assigned_to', $user->id)
                        ->orWhere('l.created_by', $user->id);
                });
            }
            $this->whereDateRange($query, 'crm_leads', 'l.contact_date', 'l.created_at', $range);
            $leads = (int) (clone $query)->count('l.id');
            if ($this->hasColumn('crm_leads', 'contact_date')) {
                $followups = (int) (clone $query)
                    ->whereDate('l.contact_date', '<=', now()->toDateString())
                    ->count('l.id');
            }
        }

        $overdueTasks = $this->taskCount($user, $isManager ? $teamIds : collect([$user->id]), true);
        $conversion = $leads > 0 ? min(100.0, round($orders / $leads * 100, 1)) : 0.0;

        return $this->base($user, $range, [
            'workspace' => $isManager ? 'sales_manager' : 'sales',
            'theme' => 'sales',
            'eyebrow' => $isManager ? 'SALES COMMAND CENTER' : 'MY SALES WORKSPACE',
            'title' => $isManager ? 'Trung tâm Kinh doanh' : 'Bàn làm việc Sales',
            'subtitle' => $isManager
                ? 'Doanh số, pipeline và việc cần xử lý của đúng đội Sales.'
                : 'Lead, khách hàng, lịch follow-up và doanh số thuộc chính tài khoản của bạn.',
            'role_label' => $isManager ? 'Quản lý Sales' : 'Nhân viên Sales',
            'scope_label' => $isManager ? 'Phạm vi: Đội Sales' : 'Phạm vi: Cá nhân',
            'privacy' => $isManager
                ? 'Không hiển thị dữ liệu Marketing, Kỹ thuật, HR hoặc số liệu toàn công ty.'
                : 'Không hiển thị doanh thu, lead hoặc đơn hàng của Sales khác.',
            'kpis' => [
                $this->kpi('Doanh thu trong kỳ', $revenue, 'money', 'bi-graph-up-arrow', 'blue', $orders.' đơn hàng', $this->routeUrl('orders.index', [], '/orders')),
                $this->kpi('Tiền đã thu', $collected, 'money', 'bi-wallet2', 'green', $revenue > 0 ? round($collected / $revenue * 100, 1).'% doanh thu' : 'Chưa phát sinh', '#'),
                $this->kpi('Lead đang theo dõi', $leads, 'number', 'bi-person-lines-fill', 'violet', $followups.' cần follow-up', $this->routeUrl('customers.index', [], '/customers')),
                $this->kpi('Tỷ lệ chuyển đổi', $conversion, 'percent', 'bi-bullseye', 'orange', $unshipped.' đơn chưa giao', $this->routeUrl('orders.index', [], '/orders')),
            ],
            'action_title' => 'Việc Sales cần xử lý',
            'action_subtitle' => 'Ưu tiên những mục ảnh hưởng trực tiếp đến khách hàng và doanh số.',
            'action_items' => [
                $this->attention('Lead cần follow-up', 'Liên hệ khách đến hạn hoặc quá hạn', $followups, 'bi-telephone-outbound', $followups > 0 ? 'warning' : 'success', $this->routeUrl('customers.index', [], '/customers')),
                $this->attention('Đơn chưa giao', 'Theo dõi kho và trạng thái bàn giao', $unshipped, 'bi-truck', $unshipped > 0 ? 'warning' : 'success', $this->routeUrl('orders.index', [], '/orders')),
                $this->attention('Công việc trễ hạn', 'Việc được giao cho Sales chưa hoàn thành', $overdueTasks, 'bi-alarm', $overdueTasks > 0 ? 'danger' : 'success', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
                $this->attention('Đơn hàng trong kỳ', 'Tổng số đơn theo bộ lọc hiện tại', $orders, 'bi-bag-check', 'info', $this->routeUrl('orders.index', [], '/orders')),
            ],
            'chart' => [
                'title' => 'Xu hướng doanh thu',
                'subtitle' => $isManager ? 'Doanh thu của đội Sales theo thời gian' : 'Doanh thu cá nhân theo thời gian',
                'format' => 'money',
                'labels' => $trend['labels'],
                'values' => $trend['values'],
            ],
            'watch_title' => 'Đơn hàng gần đây',
            'watch_subtitle' => 'Chỉ hiển thị dữ liệu thuộc phạm vi hiện tại',
            'watchlist' => $watchlist->all(),
            'actions' => [
                $this->action('Tạo đơn hàng', 'bi-plus-circle', 'orders.create', [], '/orders/create'),
                $this->action('Danh sách đơn', 'bi-receipt', 'orders.index', [], '/orders'),
                $this->action('Khách hàng', 'bi-people', 'customers.index', [], '/customers'),
                $this->action('KPI Sales', 'bi-speedometer2', 'sales.kpi.index', [], '/sales/kpi'),
                $this->action('Hoa hồng', 'bi-cash-coin', 'sales.commissions.index', [], '/sales/commissions'),
            ],
        ]);
    }

    private function marketing(User $user, array $range, bool $isManager): array
    {
        $teamIds = $this->teamIds(
            ['marketing', 'marketing_manager'],
            ['marketing']
        );
        if ($teamIds->isEmpty()) {
            $teamIds = collect([$user->id]);
        }

        $campaigns = 0;
        $activeCampaigns = 0;
        $budget = 0.0;
        $spent = 0.0;
        $leads = 0;
        $orders = 0;
        $overdueContent = 0;
        $pendingContent = 0;
        $unassignedLeads = 0;
        $trend = $this->emptySeries();
        $watchlist = collect();

        if ($this->hasTable('marketing_campaigns')) {
            $query = DB::table('marketing_campaigns as c');
            if (! $isManager && $this->hasColumn('marketing_campaigns', 'created_by')) {
                $query->where('c.created_by', $user->id);
            }
            $query->where(function (Builder $scope) use ($range): void {
                $scope->whereNull('c.start_date')
                    ->orWhereNull('c.end_date')
                    ->orWhere(function (Builder $overlap) use ($range): void {
                        $overlap->whereDate('c.start_date', '<=', $range['to']->toDateString())
                            ->whereDate('c.end_date', '>=', $range['from']->toDateString());
                    });
            });
            $campaigns = (int) (clone $query)->count('c.id');
            $activeCampaigns = (int) (clone $query)
                ->where(function (Builder $scope): void {
                    $scope->whereNull('c.start_date')->orWhereDate('c.start_date', '<=', now()->toDateString());
                })
                ->where(function (Builder $scope): void {
                    $scope->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', now()->toDateString());
                })
                ->count('c.id');

            $watchlist = (clone $query)
                ->orderByRaw('CASE WHEN c.end_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('c.end_date')
                ->limit(6)
                ->get()
                ->map(fn ($row) => $this->watch(
                    'bi-megaphone',
                    (string) ($row->name ?? 'Chiến dịch #'.$row->id),
                    trim(implode(' • ', array_filter([
                        (string) ($row->platform ?? ''),
                        $row->end_date ? 'Kết thúc '.$this->formatDate($row->end_date) : null,
                    ]))),
                    ($row->end_date && Carbon::parse($row->end_date)->isPast()) ? 'Đã kết thúc' : 'Đang theo dõi',
                    ($row->end_date && Carbon::parse($row->end_date)->isPast()) ? 'muted' : 'info',
                    $this->routeUrl('marketing.campaigns.index', [], '/marketing/campaigns')
                ));
        }

        if ($this->hasTable('marketing_budgets')) {
            $query = DB::table('marketing_budgets as b');
            if (! $isManager && $this->hasColumn('marketing_budgets', 'created_by')) {
                $query->where('b.created_by', $user->id);
            }
            $this->whereDateRange($query, 'marketing_budgets', 'b.month', 'b.created_at', $range);
            $budget = $this->hasColumn('marketing_budgets', 'budget') ? (float) (clone $query)->sum('b.budget') : 0.0;
            $spent = $this->hasColumn('marketing_budgets', 'actual_spent') ? (float) (clone $query)->sum('b.actual_spent') : 0.0;
        }

        if ($this->hasTable('marketing_metrics')) {
            $query = DB::table('marketing_metrics as m');
            if (! $isManager && $this->hasColumn('marketing_metrics', 'created_by')) {
                $query->where('m.created_by', $user->id);
            }
            $this->whereDateRange($query, 'marketing_metrics', 'm.date', 'm.created_at', $range);
            $spent = max($spent, $this->hasColumn('marketing_metrics', 'spend') ? (float) (clone $query)->sum('m.spend') : 0.0);
            $leads = $this->hasColumn('marketing_metrics', 'leads') ? (int) (clone $query)->sum('m.leads') : 0;
            $orders = $this->hasColumn('marketing_metrics', 'orders') ? (int) (clone $query)->sum('m.orders') : 0;
            $trend = $this->series(clone $query, 'marketing_metrics', ['date', 'date_from', 'created_at'], 'leads', $range);
        }

        if ($this->hasTable('marketing_leads')) {
            $query = DB::table('marketing_leads as ml');
            if (! $isManager) {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('ml.assigned_user_id', $user->id)
                        ->orWhere('ml.imported_by', $user->id);
                });
            }
            $this->whereDateRange($query, 'marketing_leads', 'ml.import_date', 'ml.created_at', $range);
            if ($leads === 0) {
                $leads = (int) (clone $query)->count('ml.id');
            }
            if ($this->hasColumn('marketing_leads', 'assigned_user_id')) {
                $unassignedLeads = (int) (clone $query)->whereNull('ml.assigned_user_id')->count('ml.id');
            }
        }

        if ($this->hasTable('content_calendars')) {
            $query = DB::table('content_calendars as cc');
            if (! $isManager) {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('cc.assignee_user_id', $user->id)
                        ->orWhere('cc.created_by', $user->id)
                        ->orWhere('cc.assignees', 'like', '%'.$user->id.'%');
                });
            }
            $pendingContent = (int) (clone $query)
                ->whereNotIn('cc.status', ['published', 'done', 'completed', 'approved'])
                ->count('cc.id');
            $overdueContent = (int) (clone $query)
                ->whereDate('cc.publish_date', '<', now()->toDateString())
                ->whereNotIn('cc.status', ['published', 'done', 'completed', 'approved'])
                ->count('cc.id');

            if ($watchlist->isEmpty()) {
                $watchlist = (clone $query)
                    ->orderBy('cc.publish_date')
                    ->limit(6)
                    ->get()
                    ->map(fn ($row) => $this->watch(
                        'bi-calendar2-event',
                        (string) ($row->title ?? 'Nội dung #'.$row->id),
                        trim(implode(' • ', array_filter([
                            (string) ($row->platform ?? ''),
                            $this->formatDate($row->publish_date ?? null),
                        ]))),
                        (string) ($row->status ?? 'draft'),
                        ($row->publish_date && Carbon::parse($row->publish_date)->isPast()) ? 'danger' : 'warning',
                        $this->routeUrl('marketing.content-calendar.index', [], '/marketing/content-calendar')
                    ));
            }
        }

        $cpl = $leads > 0 ? round($spent / $leads) : 0.0;
        $conversion = $leads > 0 ? round($orders / $leads * 100, 1) : 0.0;
        $overspend = max(0, $spent - $budget);

        return $this->base($user, $range, [
            'workspace' => $isManager ? 'marketing_manager' : 'marketing',
            'theme' => 'marketing',
            'eyebrow' => $isManager ? 'MARKETING CONTROL ROOM' : 'MY MARKETING WORKSPACE',
            'title' => $isManager ? 'Trung tâm Marketing' : 'Bàn làm việc Marketing',
            'subtitle' => $isManager
                ? 'Chiến dịch, ngân sách, lead và tiến độ nội dung của phòng Marketing.'
                : 'Chỉ hiển thị chiến dịch, nội dung và lead do bạn phụ trách.',
            'role_label' => $isManager ? 'Quản lý Marketing' : 'Nhân viên Marketing',
            'scope_label' => $isManager ? 'Phạm vi: Phòng Marketing' : 'Phạm vi: Cá nhân',
            'privacy' => 'Không hiển thị doanh thu tổng công ty, dữ liệu HR, Kho hoặc Kỹ thuật.',
            'kpis' => [
                $this->kpi('Chiến dịch đang chạy', $activeCampaigns, 'number', 'bi-megaphone', 'blue', $campaigns.' chiến dịch trong kỳ', $this->routeUrl('marketing.dashboard', [], '/marketing/dashboard')),
                $this->kpi('Ngân sách đã chi', $spent, 'money', 'bi-credit-card', 'violet', $budget > 0 ? round($spent / $budget * 100, 1).'% ngân sách' : 'Chưa đặt ngân sách', '#'),
                $this->kpi('Lead Marketing', $leads, 'number', 'bi-person-plus', 'green', number_format($cpl, 0, ',', '.').' đ/lead', $this->routeUrl('marketing.leads.index', [], '/marketing/leads')),
                $this->kpi('Chuyển đổi sang đơn', $conversion, 'percent', 'bi-funnel', 'orange', $orders.' đơn ghi nhận', '#'),
            ],
            'action_title' => 'Việc Marketing cần xử lý',
            'action_subtitle' => 'Những mục ảnh hưởng đến tiến độ nội dung, lead và ngân sách.',
            'action_items' => [
                $this->attention('Nội dung quá hạn', 'Lịch đăng đã qua nhưng chưa hoàn tất', $overdueContent, 'bi-calendar-x', $overdueContent > 0 ? 'danger' : 'success', $this->routeUrl('marketing.content-calendar.index', [], '/marketing/content-calendar')),
                $this->attention('Nội dung đang chờ', 'Bài viết/video đang trong quy trình', $pendingContent, 'bi-pencil-square', $pendingContent > 0 ? 'warning' : 'success', $this->routeUrl('marketing.content-calendar.index', [], '/marketing/content-calendar')),
                $this->attention('Lead chưa phân công', 'Lead cần chuyển cho người phụ trách', $unassignedLeads, 'bi-person-exclamation', $unassignedLeads > 0 ? 'warning' : 'success', $this->routeUrl('marketing.leads.index', [], '/marketing/leads')),
                $this->attention('Vượt ngân sách', 'Chi tiêu cao hơn ngân sách trong kỳ', $overspend, 'bi-exclamation-triangle', $overspend > 0 ? 'danger' : 'success', $this->routeUrl('marketing.budgets.index', [], '/marketing/budgets'), 'money'),
            ],
            'chart' => [
                'title' => 'Xu hướng Lead',
                'subtitle' => 'Số lead Marketing phát sinh theo thời gian',
                'format' => 'number',
                'labels' => $trend['labels'],
                'values' => $trend['values'],
            ],
            'watch_title' => $watchlist->isNotEmpty() ? 'Lịch Marketing cần theo dõi' : 'Chiến dịch gần đây',
            'watch_subtitle' => 'Các mốc gần nhất trong phạm vi hiện tại',
            'watchlist' => $watchlist->all(),
            'actions' => [
                $this->action('Dashboard Marketing', 'bi-speedometer2', 'marketing.dashboard', [], '/marketing/dashboard'),
                $this->action('Chiến dịch', 'bi-megaphone', 'marketing.campaigns.index', [], '/marketing/campaigns'),
                $this->action('Lead Marketing', 'bi-person-plus', 'marketing.leads.index', [], '/marketing/leads'),
                $this->action('Lịch nội dung', 'bi-calendar3', 'marketing.content-calendar.index', [], '/marketing/content-calendar'),
                $this->action('Ngân sách', 'bi-wallet2', 'marketing.budgets.index', [], '/marketing/budgets'),
            ],
        ]);
    }

    private function technical(User $user, array $range, bool $isManager): array
    {
        $teamIds = $this->teamIds(
            ['ky_thuat', 'technical_manager', 'technical', 'technician'],
            ['kỹ thuật', 'ky thuat', 'technical']
        );
        if ($teamIds->isEmpty()) {
            $teamIds = collect([$user->id]);
        }

        $runningSites = 0;
        $scheduled = 0;
        $overdue = 0;
        $pendingMaterials = 0;
        $todaySchedules = 0;
        $trend = $this->emptySeries();
        $watchlist = collect();

        if ($this->hasTable('sites')) {
            $query = DB::table('sites as s');
            $this->applyCompany($query, 'sites', 's');
            if (! $isManager) {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('s.created_by', $user->id)
                        ->orWhere('s.technician_name', 'like', '%'.$user->name.'%');
                });
            }
            $runningSites = (int) (clone $query)
                ->whereNotIn('s.status', ['completed', 'done', 'cancelled'])
                ->count('s.id');
        }

        if ($this->hasTable('solar_maintenance_schedules')) {
            $query = DB::table('solar_maintenance_schedules as m');
            $this->applyCompany($query, 'solar_maintenance_schedules', 'm');
            if ($this->hasColumn('solar_maintenance_schedules', 'deleted_at')) {
                $query->whereNull('m.deleted_at');
            }
            if (! $isManager) {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where('m.assigned_to', $user->id)
                        ->orWhere('m.created_by', $user->id)
                        ->orWhere('m.assigned_user_ids', 'like', '%'.$user->id.'%');
                });
            }

            $periodQuery = clone $query;
            $periodQuery->whereBetween('m.scheduled_date', [
                $range['from']->toDateString(),
                $range['to']->toDateString(),
            ]);
            $scheduled = (int) $periodQuery->count('m.id');
            $todaySchedules = (int) (clone $query)
                ->whereDate('m.scheduled_date', now()->toDateString())
                ->whereNotIn('m.status', ['completed', 'done', 'cancelled'])
                ->count('m.id');
            $overdue = (int) (clone $query)
                ->whereDate('m.scheduled_date', '<', now()->toDateString())
                ->whereNotIn('m.status', ['completed', 'done', 'cancelled'])
                ->count('m.id');

            $trend = $this->series(clone $periodQuery, 'solar_maintenance_schedules', ['scheduled_date', 'created_at'], null, $range);
            $watchlist = (clone $query)
                ->whereNotIn('m.status', ['completed', 'done', 'cancelled'])
                ->orderBy('m.scheduled_date')
                ->limit(7)
                ->get()
                ->map(fn ($row) => $this->watch(
                    'bi-tools',
                    (string) ($row->site_name ?? $row->customer_name ?? 'Lịch kỹ thuật #'.$row->id),
                    trim(implode(' • ', array_filter([
                        $this->formatDate($row->scheduled_date ?? null),
                        (string) ($row->address ?? ''),
                    ]))),
                    $this->technicalStatusLabel((string) ($row->status ?? 'scheduled')),
                    ($row->scheduled_date && Carbon::parse($row->scheduled_date)->isPast()) ? 'danger' : 'warning',
                    $this->routeUrl('technical.maintenance.show', ['schedule' => $row->id], '/technical/maintenance/'.$row->id)
                ));
        }

        if ($this->hasTable('material_requests')) {
            $query = DB::table('material_requests as mr');
            $this->applyCompany($query, 'material_requests', 'mr');
            if (! $isManager && $this->hasColumn('material_requests', 'created_by')) {
                $query->where('mr.created_by', $user->id);
            }
            $pendingMaterials = (int) (clone $query)
                ->whereNotIn(DB::raw('UPPER(mr.status)'), ['COMPLETED', 'DONE', 'ISSUED', 'CANCELLED'])
                ->count('mr.id');
        }

        $overdueTasks = $this->taskCount($user, $isManager ? $teamIds : collect([$user->id]), true);

        return $this->base($user, $range, [
            'workspace' => $isManager ? 'technical_manager' : 'technical',
            'theme' => 'technical',
            'eyebrow' => $isManager ? 'TECHNICAL COMMAND CENTER' : 'MY TECHNICAL WORKSPACE',
            'title' => $isManager ? 'Trung tâm Điều phối Kỹ thuật' : 'Bàn làm việc Kỹ thuật',
            'subtitle' => $isManager
                ? 'Điều phối lịch, công trình, vật tư và tải công việc của phòng Kỹ thuật.'
                : 'Chỉ hiển thị lịch, công trình, bảo trì và đơn vật tư do bạn phụ trách.',
            'role_label' => $isManager ? 'Trưởng phòng Kỹ thuật' : 'Nhân viên Kỹ thuật',
            'scope_label' => $isManager ? 'Phạm vi: Phòng Kỹ thuật' : 'Phạm vi: Công việc của tôi',
            'privacy' => 'Không hiển thị doanh thu, lợi nhuận, công nợ hoặc dữ liệu phòng ban khác.',
            'kpis' => [
                $this->kpi('Công trình đang chạy', $runningSites, 'number', 'bi-buildings', 'blue', 'Đúng phạm vi kỹ thuật', $this->routeUrl('sites.index', [], '/cong-trinh')),
                $this->kpi('Lịch trong kỳ', $scheduled, 'number', 'bi-calendar2-week', 'violet', $todaySchedules.' lịch hôm nay', $this->routeUrl('technical.maintenance.index', [], '/technical/maintenance')),
                $this->kpi('Quá hạn xử lý', $overdue + $overdueTasks, 'number', 'bi-exclamation-triangle', ($overdue + $overdueTasks) > 0 ? 'red' : 'green', 'Cần ưu tiên ngay', '#'),
                $this->kpi('Đơn vật tư chờ', $pendingMaterials, 'number', 'bi-box-seam', 'orange', 'Chưa xuất kho/hoàn tất', $this->routeUrl('material-requests.index', [], '/don-vat-tu')),
            ],
            'action_title' => 'Việc Kỹ thuật cần xử lý',
            'action_subtitle' => 'Tập trung lịch đến hạn, vật tư và công việc đang chặn tiến độ.',
            'action_items' => [
                $this->attention('Lịch hôm nay', 'Khảo sát, thi công hoặc bảo trì trong ngày', $todaySchedules, 'bi-calendar-check', $todaySchedules > 0 ? 'info' : 'success', $this->routeUrl('technical.maintenance.index', [], '/technical/maintenance')),
                $this->attention('Lịch quá hạn', 'Lịch chưa hoàn tất nhưng đã qua ngày', $overdue, 'bi-calendar-x', $overdue > 0 ? 'danger' : 'success', $this->routeUrl('technical.maintenance.index', [], '/technical/maintenance')),
                $this->attention('Đơn vật tư đang chờ', 'Theo dõi duyệt và xuất kho', $pendingMaterials, 'bi-box2-heart', $pendingMaterials > 0 ? 'warning' : 'success', $this->routeUrl('material-requests.index', [], '/don-vat-tu')),
                $this->attention('Công việc trễ hạn', 'Task kỹ thuật chưa hoàn thành đúng hạn', $overdueTasks, 'bi-list-task', $overdueTasks > 0 ? 'danger' : 'success', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
            ],
            'chart' => [
                'title' => 'Nhịp lịch Kỹ thuật',
                'subtitle' => 'Số lịch khảo sát, thi công và bảo trì theo thời gian',
                'format' => 'number',
                'labels' => $trend['labels'],
                'values' => $trend['values'],
            ],
            'watch_title' => 'Lịch cần theo dõi',
            'watch_subtitle' => 'Ưu tiên lịch gần nhất và lịch đang trễ',
            'watchlist' => $watchlist->all(),
            'actions' => [
                $this->action('Công trình', 'bi-buildings', 'sites.index', [], '/cong-trinh'),
                $this->action('Lịch Kỹ thuật', 'bi-calendar3', 'technical.maintenance.index', [], '/technical/maintenance'),
                $this->action('Đơn vật tư', 'bi-box-seam', 'material-requests.index', [], '/don-vat-tu'),
                $this->action('Công việc của tôi', 'bi-list-check', 'tasks.my', [], '/chat/tasks/my'),
                $this->action('Chấm công', 'bi-fingerprint', 'hr.attendance.my', [], '/nhan-su/cham-cong-cua-toi'),
            ],
        ]);
    }

    private function hr(User $user, array $range): array
    {
        $headcount = 0;
        $checkedIn = 0;
        $lateToday = 0;
        $pendingLeave = 0;
        $absentToday = 0;
        $incompleteCheckout = 0;
        $trend = $this->emptySeries();
        $watchlist = collect();

        if ($this->hasTable('users')) {
            $query = DB::table('users as u');
            if ($this->hasColumn('users', 'is_active')) {
                $query->where('u.is_active', 1);
            }
            $headcount = (int) $query->count('u.id');
        }

        if ($this->hasTable('attendance_records')) {
            $today = DB::table('attendance_records as a')
                ->whereDate('a.work_date', now()->toDateString());
            $checkedIn = (int) (clone $today)->whereNotNull('a.check_in_at')->count('a.id');
            $lateToday = (int) (clone $today)->where('a.late_minutes', '>', 0)->count('a.id');
            $incompleteCheckout = (int) (clone $today)
                ->whereNotNull('a.check_in_at')
                ->whereNull('a.check_out_at')
                ->count('a.id');
            $absentToday = max(0, $headcount - $checkedIn);

            $period = DB::table('attendance_records as a')
                ->whereBetween('a.work_date', [
                    $range['from']->toDateString(),
                    $range['to']->toDateString(),
                ]);
            $trend = $this->series($period, 'attendance_records', ['work_date'], null, $range);
        }

        if ($this->hasTable('leave_requests')) {
            $query = DB::table('leave_requests as lr');
            $pendingLeave = (int) (clone $query)->where('lr.status', 'pending')->count('lr.id');

            if ($this->hasTable('users')) {
                $watchlist = DB::table('leave_requests as lr')
                    ->leftJoin('users as u', 'u.id', '=', 'lr.user_id')
                    ->where('lr.status', 'pending')
                    ->orderBy('lr.start_date')
                    ->limit(7)
                    ->get()
                    ->map(fn ($row) => $this->watch(
                        'bi-calendar2-minus',
                        (string) ($row->name ?? 'Nhân sự #'.$row->user_id),
                        $this->formatDate($row->start_date ?? null).' → '.$this->formatDate($row->end_date ?? null),
                        'Chờ duyệt',
                        'warning',
                        $this->routeUrl('hr.leave.index', [], '/nhan-su/leave-requests'),
                        (float) ($row->days ?? 0),
                        'days'
                    ));
            }
        }

        $attendanceRate = $headcount > 0 ? round($checkedIn / $headcount * 100, 1) : 0.0;

        return $this->base($user, $range, [
            'workspace' => 'hr',
            'theme' => 'hr',
            'eyebrow' => 'PEOPLE OPERATIONS',
            'title' => 'Trung tâm Nhân sự',
            'subtitle' => 'Nhân sự, chấm công, nghỉ phép và các đầu việc HR cần xử lý trong một màn hình.',
            'role_label' => 'Nhân sự',
            'scope_label' => 'Phạm vi: Phòng Nhân sự',
            'privacy' => 'Không hiển thị đơn hàng, doanh thu, lợi nhuận hoặc dữ liệu Kỹ thuật.',
            'kpis' => [
                $this->kpi('Nhân sự hoạt động', $headcount, 'number', 'bi-people', 'blue', 'Headcount hiện tại', $this->routeUrl('hr.employees.index', [], '/nhan-su/nhan-vien')),
                $this->kpi('Check-in hôm nay', $checkedIn, 'number', 'bi-person-check', 'green', $attendanceRate.'% hiện diện', $this->routeUrl('hr.attendance.index', [], '/nhan-su/cham-cong')),
                $this->kpi('Nghỉ phép chờ duyệt', $pendingLeave, 'number', 'bi-calendar2-minus', 'orange', 'Cần xử lý sớm', $this->routeUrl('hr.leave.index', [], '/nhan-su/leave-requests')),
                $this->kpi('Đi muộn hôm nay', $lateToday, 'number', 'bi-clock-history', $lateToday > 0 ? 'red' : 'green', $absentToday.' chưa check-in', $this->routeUrl('hr.attendance.index', [], '/nhan-su/cham-cong')),
            ],
            'action_title' => 'Việc HR cần xử lý',
            'action_subtitle' => 'Các trường hợp cần duyệt hoặc kiểm tra ngay trong ngày.',
            'action_items' => [
                $this->attention('Đơn nghỉ phép chờ duyệt', 'Yêu cầu chưa có quyết định', $pendingLeave, 'bi-calendar2-minus', $pendingLeave > 0 ? 'warning' : 'success', $this->routeUrl('hr.leave.index', [], '/nhan-su/leave-requests')),
                $this->attention('Nhân sự đi muộn', 'Có số phút đi muộn trong ngày', $lateToday, 'bi-clock-history', $lateToday > 0 ? 'danger' : 'success', $this->routeUrl('hr.attendance.index', [], '/nhan-su/cham-cong')),
                $this->attention('Chưa check-out', 'Đã check-in nhưng chưa ghi nhận ra về', $incompleteCheckout, 'bi-box-arrow-right', $incompleteCheckout > 0 ? 'warning' : 'success', $this->routeUrl('hr.attendance.index', [], '/nhan-su/cham-cong')),
                $this->attention('Chưa check-in', 'Nhân sự hoạt động chưa có dữ liệu hôm nay', $absentToday, 'bi-person-x', $absentToday > 0 ? 'warning' : 'success', $this->routeUrl('hr.attendance.index', [], '/nhan-su/cham-cong')),
            ],
            'chart' => [
                'title' => 'Nhịp chấm công',
                'subtitle' => 'Số lượt chấm công phát sinh theo ngày',
                'format' => 'number',
                'labels' => $trend['labels'],
                'values' => $trend['values'],
            ],
            'watch_title' => 'Đơn nghỉ phép đang chờ',
            'watch_subtitle' => 'Sắp xếp theo ngày bắt đầu gần nhất',
            'watchlist' => $watchlist->all(),
            'actions' => [
                $this->action('Nhân viên', 'bi-people', 'hr.employees.index', [], '/nhan-su/nhan-vien'),
                $this->action('Chấm công', 'bi-fingerprint', 'hr.attendance.index', [], '/nhan-su/cham-cong'),
                $this->action('Nghỉ phép', 'bi-calendar2-minus', 'hr.leave.index', [], '/nhan-su/leave-requests'),
                $this->action('Tuyển dụng', 'bi-person-plus', 'hr.recruitment.index', [], '/nhan-su/tuyen-dung'),
                $this->action('Phòng ban', 'bi-diagram-3', 'hr.departments.index', [], '/nhan-su/phong-ban'),
            ],
        ]);
    }

    private function warehouse(User $user, array $range): array
    {
        $totalQty = 0;
        $skuCount = 0;
        $lowStock = 0;
        $outOfStock = 0;
        $pendingMaterials = 0;
        $pendingReceipts = 0;
        $trendLabels = [];
        $trendValues = [];
        $watchlist = collect();

        if ($this->hasTable('crm_product_stock')) {
            $query = DB::table('crm_product_stock as st');
            $this->applyCompany($query, 'crm_product_stock', 'st');
            $totalQty = (int) (clone $query)->sum('st.qty');
            $skuCount = (int) (clone $query)->distinct()->count('st.product_id');
            $lowStock = (int) (clone $query)->whereBetween('st.qty', [1, 5])->count('st.id');
            $outOfStock = (int) (clone $query)->where('st.qty', '<=', 0)->count('st.id');

            if ($this->hasTable('crm_product_catalog')) {
                $top = DB::table('crm_product_stock as st')
                    ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'st.product_id')
                    ->selectRaw('COALESCE(p.name, CONCAT("Sản phẩm #", st.product_id)) as label, SUM(st.qty) as total_qty')
                    ->groupBy('st.product_id', 'p.name')
                    ->orderByDesc('total_qty')
                    ->limit(8)
                    ->get();
                $trendLabels = $top->pluck('label')->map(fn ($value) => Str::limit((string) $value, 22))->all();
                $trendValues = $top->pluck('total_qty')->map(fn ($value) => (float) $value)->all();

                $watchlist = DB::table('crm_product_stock as st')
                    ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'st.product_id')
                    ->selectRaw('st.product_id, p.name, p.sku, SUM(st.qty) as qty')
                    ->groupBy('st.product_id', 'p.name', 'p.sku')
                    ->orderBy('qty')
                    ->limit(7)
                    ->get()
                    ->map(fn ($row) => $this->watch(
                        'bi-box-seam',
                        (string) ($row->name ?? 'Sản phẩm #'.$row->product_id),
                        (string) ($row->sku ?? 'Không có SKU'),
                        ((int) $row->qty <= 0) ? 'Hết hàng' : (((int) $row->qty <= 5) ? 'Sắp hết' : 'Còn hàng'),
                        ((int) $row->qty <= 0) ? 'danger' : (((int) $row->qty <= 5) ? 'warning' : 'success'),
                        $this->routeUrl('products.index', [], '/products'),
                        (float) $row->qty,
                        'number'
                    ));
            }
        }

        if ($this->hasTable('material_requests')) {
            $query = DB::table('material_requests as mr');
            $this->applyCompany($query, 'material_requests', 'mr');
            $pendingMaterials = (int) (clone $query)
                ->whereNotIn(DB::raw('UPPER(mr.status)'), ['COMPLETED', 'DONE', 'ISSUED', 'CANCELLED'])
                ->count('mr.id');
        }

        if ($this->hasTable('product_goods_receipts')) {
            $query = DB::table('product_goods_receipts as gr');
            $this->applyCompany($query, 'product_goods_receipts', 'gr');
            $pendingReceipts = (int) (clone $query)
                ->whereNotIn('gr.status', ['posted', 'completed', 'cancelled'])
                ->count('gr.id');
        }

        return $this->base($user, $range, [
            'workspace' => 'warehouse',
            'theme' => 'warehouse',
            'eyebrow' => 'WAREHOUSE CONTROL',
            'title' => 'Trung tâm Vận hành Kho',
            'subtitle' => 'Tồn kho, hàng sắp hết, phiếu chờ và luồng xuất nhập cần xử lý.',
            'role_label' => 'Kho',
            'scope_label' => 'Phạm vi: Kho hàng',
            'privacy' => 'Không hiển thị doanh thu, lợi nhuận, lương hoặc dữ liệu Marketing/HR.',
            'kpis' => [
                $this->kpi('Tổng số lượng tồn', $totalQty, 'number', 'bi-boxes', 'blue', $skuCount.' mã sản phẩm', $this->routeUrl('warehouses.index', [], '/warehouses')),
                $this->kpi('Sắp hết hàng', $lowStock, 'number', 'bi-exclamation-diamond', $lowStock > 0 ? 'orange' : 'green', 'Tồn từ 1–5', $this->routeUrl('products.index', [], '/products')),
                $this->kpi('Hết hàng', $outOfStock, 'number', 'bi-x-octagon', $outOfStock > 0 ? 'red' : 'green', 'Cần bổ sung', $this->routeUrl('products.index', [], '/products')),
                $this->kpi('Phiếu đang chờ', $pendingMaterials + $pendingReceipts, 'number', 'bi-arrow-left-right', 'violet', $pendingMaterials.' xuất • '.$pendingReceipts.' nhập', '#'),
            ],
            'action_title' => 'Việc Kho cần xử lý',
            'action_subtitle' => 'Tập trung các phiếu chờ và cảnh báo tồn kho.',
            'action_items' => [
                $this->attention('Đơn vật tư chờ', 'Phiếu công trình chưa hoàn tất xuất kho', $pendingMaterials, 'bi-box-arrow-up', $pendingMaterials > 0 ? 'warning' : 'success', $this->routeUrl('material-requests.index', [], '/don-vat-tu')),
                $this->attention('Phiếu nhập chờ', 'Phiếu nhà cung cấp chưa ghi nhận kho', $pendingReceipts, 'bi-box-arrow-in-down', $pendingReceipts > 0 ? 'warning' : 'success', $this->routeUrl('product-goods-receipts.index', [], '/products/goods-receipts')),
                $this->attention('SKU sắp hết', 'Số lượng tồn từ 1 đến 5', $lowStock, 'bi-exclamation-diamond', $lowStock > 0 ? 'warning' : 'success', $this->routeUrl('products.index', [], '/products')),
                $this->attention('SKU hết hàng', 'Không còn tồn khả dụng', $outOfStock, 'bi-x-octagon', $outOfStock > 0 ? 'danger' : 'success', $this->routeUrl('products.index', [], '/products')),
            ],
            'chart' => [
                'title' => 'Top tồn kho',
                'subtitle' => 'Các sản phẩm có số lượng tồn cao nhất',
                'format' => 'number',
                'labels' => $trendLabels,
                'values' => $trendValues,
            ],
            'watch_title' => 'Sản phẩm cần quan tâm',
            'watch_subtitle' => 'Ưu tiên hàng hết và sắp hết',
            'watchlist' => $watchlist->all(),
            'actions' => [
                $this->action('Quản lý kho', 'bi-house-gear', 'warehouses.index', [], '/warehouses'),
                $this->action('Nhập hàng', 'bi-box-arrow-in-down', 'products.input', [], '/products/input'),
                $this->action('Xuất hàng', 'bi-box-arrow-up', 'products.output', [], '/products/output'),
                $this->action('Phiếu nhập NCC', 'bi-truck', 'product-goods-receipts.index', [], '/products/goods-receipts'),
                $this->action('Đơn vật tư', 'bi-box-seam', 'material-requests.index', [], '/don-vat-tu'),
            ],
        ]);
    }

    private function finance(User $user, array $range): array
    {
        $collected = 0.0;
        $revenue = 0.0;
        $receivable = 0.0;
        $pendingPayments = 0;
        $pendingAmount = 0.0;
        $overdueRequests = 0;
        $trend = $this->emptySeries();
        $watchlist = collect();

        if ($this->hasTable('crm_orders')) {
            $query = DB::table('crm_orders as o');
            $this->applyCompany($query, 'crm_orders', 'o');
            if ($this->hasColumn('crm_orders', 'deleted_at')) {
                $query->whereNull('o.deleted_at');
            }
            $this->whereDateRange($query, 'crm_orders', 'o.order_date', 'o.created_at', $range);
            $revenue = $this->hasColumn('crm_orders', 'total_amount') ? (float) (clone $query)->sum('o.total_amount') : 0.0;
        }

        if ($this->hasTable('crm_payments') && $this->hasTable('crm_orders')) {
            $query = DB::table('crm_payments as p')
                ->join('crm_orders as o', 'o.id', '=', 'p.order_id');
            $this->applyCompany($query, 'crm_orders', 'o');
            $this->whereDateRange($query, 'crm_payments', 'p.payment_date', 'p.created_at', $range);
            $collected = $this->hasColumn('crm_payments', 'amount') ? (float) (clone $query)->sum('p.amount') : 0.0;
            $trend = $this->series(clone $query, 'crm_payments', ['payment_date', 'created_at'], 'amount', $range);
        }
        $receivable = max(0, $revenue - $collected);

        if ($this->hasTable('payment_requests')) {
            $query = DB::table('payment_requests as pr');
            $this->applyCompany($query, 'payment_requests', 'pr');
            $query->whereIn('pr.status', ['submitted', 'admin_approved']);
            $pendingPayments = (int) (clone $query)->count('pr.id');
            $pendingAmount = $this->hasColumn('payment_requests', 'amount') ? (float) (clone $query)->sum('pr.amount') : 0.0;
            if ($this->hasColumn('payment_requests', 'payment_due_date')) {
                $overdueRequests = (int) (clone $query)
                    ->whereDate('pr.payment_due_date', '<', now()->toDateString())
                    ->count('pr.id');
            }

            $watchlist = (clone $query)
                ->orderByRaw('CASE WHEN pr.payment_due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('pr.payment_due_date')
                ->limit(7)
                ->get()
                ->map(fn ($row) => $this->watch(
                    'bi-file-earmark-text',
                    (string) ($row->code ?? 'Đề nghị #'.$row->id),
                    trim(implode(' • ', array_filter([
                        (string) ($row->receiver_name ?? ''),
                        $row->payment_due_date ? 'Hạn '.$this->formatDate($row->payment_due_date) : null,
                    ]))),
                    $this->paymentRequestLabel((string) ($row->status ?? 'submitted')),
                    ($row->payment_due_date && Carbon::parse($row->payment_due_date)->isPast()) ? 'danger' : 'warning',
                    $this->routeUrl('payment-requests.show', ['payment_request' => $row->id], '/payment-requests/'.$row->id),
                    (float) ($row->amount ?? 0),
                    'money'
                ));
        }

        return $this->base($user, $range, [
            'workspace' => 'finance',
            'theme' => 'finance',
            'eyebrow' => 'FINANCE CONTROL',
            'title' => 'Trung tâm Tài chính',
            'subtitle' => 'Dòng tiền, công nợ và các đề nghị thanh toán cần xử lý.',
            'role_label' => 'Kế toán',
            'scope_label' => 'Phạm vi: Tài chính công ty',
            'privacy' => 'Chỉ role Kế toán/Admin được xem dữ liệu tài chính theo phân quyền hiện tại.',
            'kpis' => [
                $this->kpi('Doanh thu trong kỳ', $revenue, 'money', 'bi-graph-up-arrow', 'blue', 'Theo đơn hàng', '#'),
                $this->kpi('Tiền đã thu', $collected, 'money', 'bi-wallet2', 'green', $revenue > 0 ? round($collected / $revenue * 100, 1).'% doanh thu' : 'Chưa phát sinh', '#'),
                $this->kpi('Công nợ tạm tính', $receivable, 'money', 'bi-exclamation-circle', $receivable > 0 ? 'red' : 'green', 'Doanh thu trừ tiền thu', '#'),
                $this->kpi('Đề nghị thanh toán', $pendingPayments, 'number', 'bi-file-earmark-check', 'orange', number_format($pendingAmount, 0, ',', '.').' đ chờ xử lý', $this->routeUrl('payment-requests.index', [], '/payment-requests')),
            ],
            'action_title' => 'Việc Tài chính cần xử lý',
            'action_subtitle' => 'Đề nghị thanh toán và công nợ cần ưu tiên.',
            'action_items' => [
                $this->attention('Đề nghị đang chờ', 'Đã gửi hoặc đã được Admin duyệt', $pendingPayments, 'bi-file-earmark-text', $pendingPayments > 0 ? 'warning' : 'success', $this->routeUrl('payment-requests.index', [], '/payment-requests')),
                $this->attention('Đề nghị quá hạn', 'Đã qua ngày thanh toán dự kiến', $overdueRequests, 'bi-calendar-x', $overdueRequests > 0 ? 'danger' : 'success', $this->routeUrl('payment-requests.index', [], '/payment-requests')),
                $this->attention('Giá trị đang chờ', 'Tổng giá trị đề nghị chưa hoàn tất', $pendingAmount, 'bi-cash-stack', $pendingAmount > 0 ? 'warning' : 'success', $this->routeUrl('payment-requests.index', [], '/payment-requests'), 'money'),
                $this->attention('Công nợ tạm tính', 'Khoản chưa thu theo dữ liệu trong kỳ', $receivable, 'bi-bank', $receivable > 0 ? 'danger' : 'success', '#', 'money'),
            ],
            'chart' => [
                'title' => 'Xu hướng thu tiền',
                'subtitle' => 'Dòng tiền thu theo thời gian',
                'format' => 'money',
                'labels' => $trend['labels'],
                'values' => $trend['values'],
            ],
            'watch_title' => 'Đề nghị cần theo dõi',
            'watch_subtitle' => 'Sắp xếp theo hạn thanh toán',
            'watchlist' => $watchlist->all(),
            'actions' => [
                $this->action('Dashboard tài chính', 'bi-speedometer2', 'finance.index', [], '/finance'),
                $this->action('Đề nghị thanh toán', 'bi-file-earmark-text', 'payment-requests.index', [], '/payment-requests'),
                $this->action('Phiếu thu', 'bi-cash-coin', 'finance.receipts.index', [], '/finance/receipts'),
                $this->action('Công nợ khách hàng', 'bi-bank', 'finance.customer-debts.index', [], '/finance/customer-debts'),
            ],
        ]);
    }

    private function personal(User $user, array $range): array
    {
        $openTasks = 0;
        $doneTasks = 0;
        $overdueTasks = 0;
        $checkedIn = false;
        $workMinutes = 0;
        $trend = $this->emptySeries();
        $watchlist = collect();

        if ($this->hasTable('tasks')) {
            $query = DB::table('tasks as t')->where('t.assignee_id', $user->id);
            $this->applyCompany($query, 'tasks', 't');
            $openTasks = (int) (clone $query)
                ->whereNotIn('t.status', ['done', 'completed', 'approved', 'cancelled'])
                ->count('t.id');
            $doneTasks = (int) (clone $query)
                ->whereIn('t.status', ['done', 'completed', 'approved'])
                ->count('t.id');
            $overdueTasks = (int) (clone $query)
                ->whereNotNull('t.due_at')
                ->where('t.due_at', '<', now())
                ->whereNotIn('t.status', ['done', 'completed', 'approved', 'cancelled'])
                ->count('t.id');

            $period = clone $query;
            $this->whereDateRange($period, 'tasks', 't.created_at', 't.created_at', $range);
            $trend = $this->series($period, 'tasks', ['created_at'], null, $range);

            $watchlist = (clone $query)
                ->orderByRaw('CASE WHEN t.due_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('t.due_at')
                ->limit(7)
                ->get()
                ->map(fn ($row) => $this->watch(
                    'bi-list-check',
                    (string) ($row->title ?? 'Công việc #'.$row->id),
                    $row->due_at ? 'Hạn '.$this->formatDate($row->due_at) : 'Không đặt hạn',
                    $this->taskStatusLabel((string) ($row->status ?? 'new')),
                    ($row->due_at && Carbon::parse($row->due_at)->isPast()) ? 'danger' : 'warning',
                    $this->routeUrl('tasks.show', ['task' => $row->id], '/chat/tasks/'.$row->id),
                    (float) ($row->progress_percent ?? 0),
                    'percent'
                ));
        }

        if ($this->hasTable('attendance_records')) {
            $attendance = DB::table('attendance_records')
                ->where('user_id', $user->id)
                ->whereDate('work_date', now()->toDateString())
                ->first();
            $checkedIn = ! empty($attendance?->check_in_at);
            $workMinutes = (int) ($attendance?->work_minutes ?? 0);
        }

        return $this->base($user, $range, [
            'workspace' => 'personal',
            'theme' => 'personal',
            'eyebrow' => 'PERSONAL WORKSPACE',
            'title' => 'Không gian làm việc của tôi',
            'subtitle' => 'Công việc, chấm công và các đầu việc cá nhân trong một màn hình.',
            'role_label' => 'Nhân viên',
            'scope_label' => 'Phạm vi: Cá nhân',
            'privacy' => 'Chỉ hiển thị công việc và dữ liệu cá nhân. Admin có thể gán role để mở workspace chuyên môn.',
            'kpis' => [
                $this->kpi('Việc đang xử lý', $openTasks, 'number', 'bi-list-task', 'blue', 'Được giao cho bạn', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
                $this->kpi('Việc hoàn thành', $doneTasks, 'number', 'bi-check2-all', 'green', 'Tổng số công việc', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
                $this->kpi('Việc trễ hạn', $overdueTasks, 'number', 'bi-alarm', $overdueTasks > 0 ? 'red' : 'green', $overdueTasks > 0 ? 'Cần ưu tiên' : 'Đúng tiến độ', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
                $this->kpi('Chấm công hôm nay', $checkedIn ? 1 : 0, 'boolean', 'bi-fingerprint', $checkedIn ? 'green' : 'orange', $checkedIn ? round($workMinutes / 60, 1).' giờ làm' : 'Chưa check-in', $this->routeUrl('hr.attendance.my', [], '/nhan-su/cham-cong-cua-toi')),
            ],
            'action_title' => 'Việc cần tôi xử lý',
            'action_subtitle' => 'Tập trung công việc đến hạn và trạng thái chấm công.',
            'action_items' => [
                $this->attention('Việc đang xử lý', 'Công việc chưa hoàn thành', $openTasks, 'bi-list-task', $openTasks > 0 ? 'info' : 'success', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
                $this->attention('Việc trễ hạn', 'Đã qua hạn nhưng chưa hoàn thành', $overdueTasks, 'bi-alarm', $overdueTasks > 0 ? 'danger' : 'success', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
                $this->attention('Chấm công', $checkedIn ? 'Đã check-in hôm nay' : 'Chưa ghi nhận check-in', $checkedIn ? 1 : 0, 'bi-fingerprint', $checkedIn ? 'success' : 'warning', $this->routeUrl('hr.attendance.my', [], '/nhan-su/cham-cong-cua-toi'), 'boolean'),
                $this->attention('Việc hoàn thành', 'Tổng số việc đã hoàn tất', $doneTasks, 'bi-check2-circle', 'success', $this->routeUrl('tasks.my', [], '/chat/tasks/my')),
            ],
            'chart' => [
                'title' => 'Nhịp công việc',
                'subtitle' => 'Số công việc phát sinh theo thời gian',
                'format' => 'number',
                'labels' => $trend['labels'],
                'values' => $trend['values'],
            ],
            'watch_title' => 'Công việc ưu tiên',
            'watch_subtitle' => 'Sắp xếp theo thời hạn gần nhất',
            'watchlist' => $watchlist->all(),
            'actions' => [
                $this->action('Công việc của tôi', 'bi-list-check', 'tasks.my', [], '/chat/tasks/my'),
                $this->action('Chấm công', 'bi-fingerprint', 'hr.attendance.my', [], '/nhan-su/cham-cong-cua-toi'),
                $this->action('Nghỉ phép', 'bi-calendar2-minus', 'hr.leave.index', [], '/nhan-su/leave-requests'),
                $this->action('Tin nhắn', 'bi-chat-dots', 'chat.inbox', [], '/chat'),
            ],
        ]);
    }

    private function base(User $user, array $range, array $data): array
    {
        $companyName = EgoCompanyScope::currentName();
        if ($companyName === '' && $this->hasTable('companies') && EgoCompanyScope::currentId() > 0) {
            $companyName = (string) DB::table('companies')
                ->where('id', EgoCompanyScope::currentId())
                ->value('name');
        }

        return array_merge([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'department' => optional($user->department)->name ?: 'Chưa gán phòng ban',
                'position' => optional($user->position)->name ?: 'Nhân viên',
            ],
            'company' => $companyName ?: 'EGO Solar',
            'generated_at' => now()->format('H:i, d/m/Y'),
            'range' => [
                'period' => $range['period'],
                'from' => $range['from']->toDateString(),
                'to' => $range['to']->toDateString(),
                'label' => $range['label'],
            ],
            'kpis' => [],
            'action_items' => [],
            'chart' => $this->emptySeries(),
            'watchlist' => [],
            'actions' => [],
        ], $data);
    }

    private function kpi(
        string $label,
        float|int $value,
        string $format,
        string $icon,
        string $tone,
        string $hint,
        string $url
    ): array {
        return compact('label', 'value', 'format', 'icon', 'tone', 'hint', 'url');
    }

    private function attention(
        string $title,
        string $description,
        float|int $value,
        string $icon,
        string $tone,
        string $url,
        string $format = 'number'
    ): array {
        return compact('title', 'description', 'value', 'icon', 'tone', 'url', 'format');
    }

    private function watch(
        string $icon,
        string $title,
        string $meta,
        string $status,
        string $tone,
        string $url,
        float|int|null $value = null,
        string $format = 'number'
    ): array {
        return compact('icon', 'title', 'meta', 'status', 'tone', 'url', 'value', 'format');
    }

    private function action(
        string $label,
        string $icon,
        string $routeName,
        array $parameters,
        string $fallback
    ): array {
        return [
            'label' => $label,
            'icon' => $icon,
            'url' => $this->routeUrl($routeName, $parameters, $fallback),
        ];
    }

    private function roles(User $user): Collection
    {
        $roles = collect();
        try {
            if (method_exists($user, 'getRoleNames')) {
                $roles = $roles->merge($user->getRoleNames());
            }
        } catch (\Throwable) {
            // Fallback fields below.
        }

        foreach (['role', 'type'] as $field) {
            if (! empty($user->{$field})) {
                $roles->push((string) $user->{$field});
            }
        }

        return $roles
            ->map(fn ($role) => $this->slug((string) $role))
            ->filter()
            ->unique()
            ->values();
    }

    private function teamIds(array $roles, array $departmentNeedles): Collection
    {
        $ids = collect();

        if ($this->hasTable('model_has_roles') && $this->hasTable('roles')) {
            $ids = DB::table('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->where('mhr.model_type', User::class)
                ->whereIn('r.name', $roles)
                ->pluck('mhr.model_id');
        }

        if ($this->hasTable('users') && $this->hasTable('departments')) {
            $departmentIds = DB::table('departments')
                ->where(function (Builder $query) use ($departmentNeedles): void {
                    foreach ($departmentNeedles as $needle) {
                        $query->orWhere('name', 'like', '%'.$needle.'%')
                            ->orWhere('code', 'like', '%'.$needle.'%');
                    }
                })
                ->pluck('id');

            if ($departmentIds->isNotEmpty()) {
                $userQuery = DB::table('users')->whereIn('department_id', $departmentIds);
                if ($this->hasColumn('users', 'is_active')) {
                    $userQuery->where('is_active', 1);
                }
                $ids = $ids->merge($userQuery->pluck('id'));
            }
        }

        return $ids->map(fn ($id) => (int) $id)->filter()->unique()->values();
    }

    private function taskCount(User $user, Collection $userIds, bool $overdueOnly): int
    {
        if (! $this->hasTable('tasks')) {
            return 0;
        }

        $query = DB::table('tasks as t');
        $this->applyCompany($query, 'tasks', 't');
        $query->whereIn('t.assignee_id', $userIds->isNotEmpty() ? $userIds->all() : [$user->id]);
        $query->whereNotIn('t.status', ['done', 'completed', 'approved', 'cancelled']);

        if ($overdueOnly && $this->hasColumn('tasks', 'due_at')) {
            $query->whereNotNull('t.due_at')->where('t.due_at', '<', now());
        }

        return (int) $query->count('t.id');
    }

    private function applyOwnerScope(
        Builder $query,
        string $column,
        User $user,
        bool $isManager,
        Collection $teamIds
    ): void {
        if ($isManager) {
            $query->whereIn($column, $teamIds->isNotEmpty() ? $teamIds->all() : [$user->id]);
        } else {
            $query->where($column, $user->id);
        }
    }

    private function applyCompany(Builder $query, string $table, ?string $alias = null): void
    {
        $companyId = EgoCompanyScope::currentId();
        if ($companyId <= 0 || ! $this->hasColumn($table, 'company_id')) {
            return;
        }

        $query->where(($alias ?: $table).'.company_id', $companyId);
    }

    private function whereDateRange(
        Builder $query,
        string $table,
        string $preferred,
        string $fallback,
        array $range
    ): void {
        $preferredColumn = Str::after($preferred, '.');
        $fallbackColumn = Str::after($fallback, '.');
        $column = $this->hasColumn($table, $preferredColumn)
            ? $preferred
            : ($this->hasColumn($table, $fallbackColumn) ? $fallback : null);

        if ($column) {
            $query->whereBetween($column, [$range['from'], $range['to']]);
        }
    }

    /**
     * @return array{labels:array<int,string>,values:array<int,float>}
     */
    private function series(
        Builder $query,
        string $table,
        array $dateCandidates,
        ?string $valueColumn,
        array $range
    ): array {
        $dateColumn = $this->qualifiedDate($table, $this->detectAlias($query, $table), $dateCandidates);
        if (! $dateColumn) {
            return $this->emptySeries();
        }

        $days = (int) $range['days'];
        $bucket = $days <= 45 ? 'day' : ($days <= 180 ? 'week' : 'month');
        $formatSql = match ($bucket) {
            'week' => '%x-W%v',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $valueExpression = $valueColumn && $this->hasColumn($table, $valueColumn)
            ? 'SUM('.$this->quoteColumn($this->detectAlias($query, $table).'.'.$valueColumn).')'
            : 'COUNT(*)';

        try {
            $rows = $query
                ->selectRaw("DATE_FORMAT({$this->quoteColumn($dateColumn)}, ?) as bucket, {$valueExpression} as total", [$formatSql])
                ->groupBy('bucket')
                ->orderBy('bucket')
                ->get();
        } catch (\Throwable) {
            return $this->emptySeries();
        }

        return [
            'labels' => $rows->pluck('bucket')->map(fn ($label) => $this->humanBucket((string) $label, $bucket))->all(),
            'values' => $rows->pluck('total')->map(fn ($value) => (float) $value)->all(),
        ];
    }

    private function qualifiedDate(string $table, string $alias, array $candidates): ?string
    {
        $cacheKey = $table.'|'.implode(',', $candidates);
        if (array_key_exists($cacheKey, $this->dateColumnCache)) {
            $column = $this->dateColumnCache[$cacheKey];
            return $column ? $alias.'.'.$column : null;
        }

        foreach ($candidates as $candidate) {
            $column = Str::after((string) $candidate, '.');
            if ($this->hasColumn($table, $column)) {
                $this->dateColumnCache[$cacheKey] = $column;
                return $alias.'.'.$column;
            }
        }

        $this->dateColumnCache[$cacheKey] = null;
        return null;
    }

    private function detectAlias(Builder $query, string $table): string
    {
        $from = (string) $query->from;
        if (preg_match('/\s+as\s+([a-zA-Z0-9_]+)$/i', $from, $matches)) {
            return $matches[1];
        }
        if (preg_match('/\s+([a-zA-Z0-9_]+)$/', $from, $matches) && ! str_contains($from, '.')) {
            return $matches[1];
        }
        return $table;
    }

    private function quoteColumn(string $column): string
    {
        return implode('.', array_map(
            static fn (string $part): string => '`'.str_replace('`', '', $part).'`',
            explode('.', $column)
        ));
    }

    private function humanBucket(string $bucket, string $type): string
    {
        try {
            return match ($type) {
                'month' => Carbon::createFromFormat('Y-m', $bucket)->format('m/Y'),
                'week' => str_replace('-W', ' T', $bucket),
                default => Carbon::createFromFormat('Y-m-d', $bucket)->format('d/m'),
            };
        } catch (\Throwable) {
            return $bucket;
        }
    }

    private function routeUrl(string $name, array $parameters = [], string $fallback = '#'): string
    {
        try {
            return Route::has($name) ? route($name, $parameters) : url($fallback);
        } catch (\Throwable) {
            return $fallback === '#' ? '#' : url($fallback);
        }
    }

    private function hasTable(string $table): bool
    {
        return $this->tableCache[$table] ??= Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        return $this->columnCache[$key] ??= ($this->hasTable($table) && Schema::hasColumn($table, $column));
    }

    private function safeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function slug(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->ascii()
            ->replace(['-', ' '], '_')
            ->replaceMatches('/[^a-z0-9_]+/', '')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();
    }

    private function formatDate(mixed $value): string
    {
        if (empty($value)) {
            return 'Chưa đặt lịch';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function emptySeries(): array
    {
        return ['labels' => [], 'values' => []];
    }

    private function shippingLabel(string $status): string
    {
        return match (strtolower($status)) {
            'shipped' => 'Đã xuất kho',
            'delivered' => 'Đã giao',
            'shipping' => 'Đang giao',
            'returned' => 'Đã hoàn',
            default => 'Chờ giao',
        };
    }

    private function technicalStatusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'completed', 'done', 'finished' => 'Hoàn thành',
            'in_progress', 'working', 'started' => 'Đang thực hiện',
            'cancelled' => 'Đã hủy',
            default => 'Đang theo dõi',
        };
    }

    private function paymentRequestLabel(string $status): string
    {
        return match ($status) {
            'admin_approved' => 'Admin đã duyệt',
            'accounting_approved' => 'Kế toán đã duyệt',
            'admin_rejected', 'accounting_rejected' => 'Bị từ chối',
            default => 'Chờ xử lý',
        };
    }

    private function taskStatusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'done', 'completed', 'approved' => 'Hoàn thành',
            'in_progress', 'doing', 'working' => 'Đang thực hiện',
            'cancelled' => 'Đã hủy',
            default => 'Chờ xử lý',
        };
    }
}
