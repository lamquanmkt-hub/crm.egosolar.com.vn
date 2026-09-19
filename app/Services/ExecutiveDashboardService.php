<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\EgoCompanyScope;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Service dashboard điều hành: doanh thu, tiền thu, công nợ, cảnh báo, tồn kho, hậu mãi.
 */
final class ExecutiveDashboardService
{
    private const CACHE_SECONDS = 300;

    /** @var array<string, bool> */
    private array $tableCache = [];

    /** @var array<string, bool> */
    private array $columnCache = [];

    /**
     * Build one consistent executive dashboard payload.
     *
     * Revenue is recognised from order total_amount and site contract_amount
     * inside the selected period. Collected cash is recognised by the actual
     * payment/receipt date. Receivables are current outstanding balances.
     */
    public function build(User $user, array $rawFilters = []): array
    {
        $access = $this->resolveAccess($user);
        $range = $this->resolveRange($rawFilters);
        $filters = $this->normaliseFilters($rawFilters, $access, $user);
        $companyId = EgoCompanyScope::currentId();

        $cacheKey = 'executive-dashboard:v3:'.sha1(json_encode([
            'company_id' => $companyId,
            'scope_user_id' => $access['own_only'] ? (int) $user->id : 0,
            'roles' => $access['roles'],
            'filters' => $filters,
            'range' => [
                $range['from']->toDateString(),
                $range['to']->toDateString(),
                $range['previous_from']->toDateString(),
                $range['previous_to']->toDateString(),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Cache::remember(
            $cacheKey,
            now()->addSeconds(self::CACHE_SECONDS),
            fn (): array => $this->compute($user, $access, $filters, $range)
        );
    }

    /**
     * Tính toàn bộ payload dashboard cho kỳ hiện tại và kỳ so sánh.
     */
    private function compute(User $user, array $access, array $filters, array $range): array
    {
        $current = $this->periodMetrics(
            $user,
            $access,
            $filters,
            $range['from'],
            $range['to']
        );

        $previous = $this->periodMetrics(
            $user,
            $access,
            $filters,
            $range['previous_from'],
            $range['previous_to']
        );

        $receivables = $this->receivableSnapshot($user, $access, $filters);
        $alerts = $this->alerts($user, $access, $filters, $range, $receivables);
        $trend = $this->trend($user, $access, $filters, $range);
        $pipeline = $this->pipeline($user, $access, $filters, $range);
        $inventory = $this->inventory($access, $filters);
        $afterSales = $this->afterSales($access, $filters);
        $team = $this->teamPerformance($user, $access, $filters, $range);
        $activities = $this->recentActivities($user, $access, $filters);
        $salesUsers = $this->salesUsers($user, $access);

        $revenueChange = $this->percentChange(
            $current['total_revenue'],
            $previous['total_revenue']
        );
        $collectedChange = $this->percentChange(
            $current['collected'],
            $previous['collected']
        );

        return [
            'executive' => [
                'access' => $access,
                'filters' => $filters,
                'range' => [
                    'period' => $range['period'],
                    'from' => $range['from']->toDateString(),
                    'to' => $range['to']->toDateString(),
                    'label' => $range['label'],
                    'previous_label' => $range['previous_label'],
                ],
                'company' => [
                    'id' => EgoCompanyScope::currentId(),
                    'name' => EgoCompanyScope::currentName(),
                ],
                'sales_users' => $salesUsers,
                'kpis' => [
                    'revenue' => [
                        'value' => $current['total_revenue'],
                        'previous' => $previous['total_revenue'],
                        'change' => $revenueChange,
                        'commercial' => $current['commercial_revenue'],
                        'project' => $current['project_revenue'],
                    ],
                    'collected' => [
                        'value' => $current['collected'],
                        'previous' => $previous['collected'],
                        'change' => $collectedChange,
                        'rate' => $current['total_revenue'] > 0
                            ? min(100.0, $current['collected'] / $current['total_revenue'] * 100)
                            : 0.0,
                        'commercial' => $current['commercial_collected'],
                        'project' => $current['project_collected'],
                    ],
                    'receivable' => $receivables,
                    'operations' => [
                        'orders' => $current['orders'],
                        'sites' => $current['sites'],
                        'at_risk' => $alerts['risk_count'],
                        'at_risk_value' => $alerts['risk_value'],
                    ],
                ],
                'alerts' => $alerts['items'],
                'chart' => $trend,
                'composition' => [
                    'labels' => ['Đơn hàng thương mại', 'Công trình'],
                    'values' => [
                        $current['commercial_revenue'],
                        $current['project_revenue'],
                    ],
                ],
                'debt_aging' => $receivables['aging'],
                'top_debtors' => $receivables['top_debtors'],
                'cash_forecast' => $receivables['forecast'],
                'pipeline' => $pipeline,
                'inventory' => $inventory,
                'after_sales' => $afterSales,
                'team' => $team,
                'activities' => $activities,
                'generated_at' => now()->format('H:i, d/m/Y'),
            ],
        ];
    }

    /**
     * Xác định quyền truy cập dashboard dựa trên role của user.
     */
    private function resolveAccess(User $user): array
    {
        $roles = collect();

        try {
            if (method_exists($user, 'getRoleNames')) {
                $roles = $roles->merge($user->getRoleNames());
            }
        } catch (\Throwable) {
            // Keep fallback fields below.
        }

        foreach (['role', 'type'] as $field) {
            if (! empty($user->{$field})) {
                $roles->push((string) $user->{$field});
            }
        }

        $roles = $roles
            ->map(fn ($role) => $this->slug((string) $role))
            ->filter()
            ->unique()
            ->values();

        $executiveRoles = collect([
            'admin', 'super_admin', 'management', 'manager', 'director',
            'ban_giam_doc', 'giam_doc', 'accounting', 'ke_toan',
            'sales_manager', 'truong_phong_sales',
        ]);

        $salesRoles = collect(['sales', 'sale', 'kinh_doanh', 'nhan_vien_kinh_doanh']);
        $isExecutive = $roles->intersect($executiveRoles)->isNotEmpty();
        $isSales = $roles->intersect($salesRoles)->isNotEmpty();
        $ownOnly = $isSales && ! $isExecutive;

        return [
            'roles' => $roles->all(),
            'is_executive' => $isExecutive,
            'can_view_financial' => $isExecutive,
            'can_filter_sales' => $isExecutive,
            'own_only' => $ownOnly,
            'scope_label' => $ownOnly ? 'Dữ liệu cá nhân' : 'Dữ liệu công ty',
        ];
    }

    /**
     * Chuẩn hóa bộ lọc đầu vào (kỳ, nguồn, sales) theo quyền của user.
     */
    private function normaliseFilters(array $raw, array $access, User $user): array
    {
        $period = in_array((string) ($raw['period'] ?? 'month'), [
            'today', 'week', 'month', 'quarter', 'year', 'custom',
        ], true) ? (string) ($raw['period'] ?? 'month') : 'month';

        $source = in_array((string) ($raw['source'] ?? 'all'), [
            'all', 'orders', 'sites',
        ], true) ? (string) ($raw['source'] ?? 'all') : 'all';

        $salesId = null;
        if ($access['own_only']) {
            $salesId = (int) $user->id;
        } elseif ($access['can_filter_sales'] && ! empty($raw['sales_id'])) {
            $salesId = max(1, (int) $raw['sales_id']);
        }

        return [
            'period' => $period,
            'from' => $this->safeDate($raw['from'] ?? $raw['from_date'] ?? null),
            'to' => $this->safeDate($raw['to'] ?? $raw['to_date'] ?? null),
            'sales_id' => $salesId,
            'source' => $source,
        ];
    }

    /**
     * Xác định khoảng thời gian hiện tại và kỳ liền trước để so sánh.
     */
    private function resolveRange(array $raw): array
    {
        $period = in_array((string) ($raw['period'] ?? 'month'), [
            'today', 'week', 'month', 'quarter', 'year', 'custom',
        ], true) ? (string) ($raw['period'] ?? 'month') : 'month';

        $now = now();
        $fromInput = $this->safeDate($raw['from'] ?? $raw['from_date'] ?? null);
        $toInput = $this->safeDate($raw['to'] ?? $raw['to_date'] ?? null);

        if (($period === 'custom' || ($fromInput && $toInput)) && $fromInput && $toInput) {
            $from = Carbon::parse($fromInput)->startOfDay();
            $to = Carbon::parse($toInput)->endOfDay();
            $period = 'custom';
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

        $days = max(1, $from->diffInDays($to) + 1);
        $previousTo = $from->copy()->subSecond();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'previous_from' => $previousFrom,
            'previous_to' => $previousTo,
            'label' => $from->format('d/m/Y').' – '.$to->format('d/m/Y'),
            'previous_label' => $previousFrom->format('d/m/Y').' – '.$previousTo->format('d/m/Y'),
            'days' => $days,
        ];
    }

    /**
     * Tính doanh thu, tiền đã thu, số đơn và số công trình trong một khoảng thời gian.
     */
    private function periodMetrics(
        User $user,
        array $access,
        array $filters,
        Carbon $from,
        Carbon $to
    ): array {
        $includeOrders = $filters['source'] !== 'sites';
        $includeSites = $filters['source'] !== 'orders';

        $commercialRevenue = 0.0;
        $commercialCollected = 0.0;
        $orders = 0;

        if ($includeOrders && $this->hasTable('crm_orders')) {
            $orderQuery = $this->orderQuery($user, $access, $filters, $from, $to);
            $orders = (int) (clone $orderQuery)->count('o.id');
            $commercialRevenue = $this->hasColumn('crm_orders', 'total_amount')
                ? (float) (clone $orderQuery)->sum('o.total_amount')
                : 0.0;

            if (
                $this->hasTable('crm_payments')
                && $this->hasColumn('crm_payments', 'amount')
                && $this->hasColumn('crm_payments', 'payment_date')
            ) {
                $paymentQuery = DB::table('crm_payments as p')
                    ->join('crm_orders as o', 'o.id', '=', 'p.order_id')
                    ->whereBetween('p.payment_date', [
                        $from->toDateString(),
                        $to->toDateString(),
                    ]);

                $this->scopeOrderCompanyAndUser($paymentQuery, $user, $access, $filters, 'o');
                $this->excludeCancelledOrders($paymentQuery, 'o');
                $commercialCollected = (float) $paymentQuery->sum('p.amount');
            }
        }

        $projectRevenue = 0.0;
        $projectCollected = 0.0;
        $sites = 0;

        if ($includeSites && $this->hasTable('sites')) {
            $siteQuery = $this->siteQuery($user, $access, $filters, $from, $to);
            $sites = (int) (clone $siteQuery)->count('s.id');
            $projectRevenue = $this->hasColumn('sites', 'contract_amount')
                ? (float) (clone $siteQuery)->sum('s.contract_amount')
                : 0.0;

            if (
                $this->hasTable('receipts')
                && $this->hasColumn('receipts', 'amount')
                && $this->hasColumn('receipts', 'site_id')
            ) {
                $dateColumn = $this->firstColumn('receipts', [
                    'paid_at', 'payment_date', 'receipt_date', 'created_at',
                ]);

                if ($dateColumn) {
                    $receiptQuery = DB::table('receipts as r')
                        ->join('sites as s', 's.id', '=', 'r.site_id')
                        ->whereBetween('r.'.$dateColumn, [
                            $from->toDateTimeString(),
                            $to->toDateTimeString(),
                        ]);

                    $this->applyCompany($receiptQuery, 'sites', 's');
                    $this->scopeSiteUser($receiptQuery, $user, $access, $filters, 's');
                    $projectCollected = (float) $receiptQuery->sum('r.amount');
                }
            }
        }

        return [
            'commercial_revenue' => $commercialRevenue,
            'project_revenue' => $projectRevenue,
            'total_revenue' => $commercialRevenue + $projectRevenue,
            'commercial_collected' => $commercialCollected,
            'project_collected' => $projectCollected,
            'collected' => $commercialCollected + $projectCollected,
            'orders' => $orders,
            'sites' => $sites,
        ];
    }

    /**
     * Tổng hợp công nợ phải thu hiện tại từ đơn thương mại và công trình.
     */
    private function receivableSnapshot(User $user, array $access, array $filters): array
    {
        $commercial = $filters['source'] === 'sites'
            ? $this->emptyDebtPayload()
            : $this->commercialReceivables($user, $access, $filters);
        $projects = $filters['source'] === 'orders'
            ? $this->emptyDebtPayload()
            : $this->projectReceivables($user, $access, $filters);

        $aging = [
            'current' => $commercial['aging']['current'] + $projects['aging']['current'],
            '1_7' => $commercial['aging']['1_7'] + $projects['aging']['1_7'],
            '8_30' => $commercial['aging']['8_30'] + $projects['aging']['8_30'],
            'over_30' => $commercial['aging']['over_30'] + $projects['aging']['over_30'],
        ];

        return [
            'value' => $commercial['total'] + $projects['total'],
            'overdue' => $commercial['overdue'] + $projects['overdue'],
            'overdue_30' => $aging['over_30'],
            'not_due' => $aging['current'],
            'aging' => $aging,
            'forecast' => [
                'today' => $commercial['forecast']['today'] + $projects['forecast']['today'],
                'next_7_days' => $commercial['forecast']['next_7_days'] + $projects['forecast']['next_7_days'],
                'next_30_days' => $commercial['forecast']['next_30_days'] + $projects['forecast']['next_30_days'],
            ],
            'top_debtors' => $commercial['top_debtors'],
            'commercial' => $commercial['total'],
            'project' => $projects['total'],
        ];
    }

    /**
     * Tính công nợ đơn thương mại từ bảng công nợ khách, kèm tuổi nợ và dự báo thu.
     */
    private function commercialReceivables(User $user, array $access, array $filters): array
    {
        $empty = $this->emptyDebtPayload();

        if (
            ! $this->hasTable('crm_customer_debts')
            || ! $this->hasColumn('crm_customer_debts', 'debt_amount')
        ) {
            return $this->derivedOrderReceivables($user, $access, $filters);
        }

        try {
            $query = DB::table('crm_customer_debts as d')
                ->leftJoin('crm_orders as o', 'o.id', '=', 'd.order_id')
                ->leftJoin('crm_customers as c', 'c.id', '=', 'd.customer_id')
                ->where('d.debt_amount', '>', 0);

            $this->scopeOrderCompanyAndUser($query, $user, $access, $filters, 'o');
            $this->excludeCancelledOrders($query, 'o');

            $total = (float) (clone $query)->sum('d.debt_amount');
            $today = now()->toDateString();
            $day7 = now()->addDays(7)->toDateString();
            $day30 = now()->addDays(30)->toDateString();

            $agingRow = (clone $query)->selectRaw(
                'SUM(CASE WHEN d.due_date IS NULL OR d.due_date >= ? THEN d.debt_amount ELSE 0 END) AS current_amount,
                 SUM(CASE WHEN d.due_date < ? AND d.due_date >= ? THEN d.debt_amount ELSE 0 END) AS d1_7,
                 SUM(CASE WHEN d.due_date < ? AND d.due_date >= ? THEN d.debt_amount ELSE 0 END) AS d8_30,
                 SUM(CASE WHEN d.due_date < ? THEN d.debt_amount ELSE 0 END) AS over_30',
                [
                    $today,
                    $today, now()->subDays(7)->toDateString(),
                    now()->subDays(7)->toDateString(), now()->subDays(30)->toDateString(),
                    now()->subDays(30)->toDateString(),
                ]
            )->first();

            $forecastRow = (clone $query)->selectRaw(
                'SUM(CASE WHEN d.due_date = ? THEN d.debt_amount ELSE 0 END) AS today_amount,
                 SUM(CASE WHEN d.due_date > ? AND d.due_date <= ? THEN d.debt_amount ELSE 0 END) AS next_7,
                 SUM(CASE WHEN d.due_date > ? AND d.due_date <= ? THEN d.debt_amount ELSE 0 END) AS next_30',
                [$today, $today, $day7, $today, $day30]
            )->first();

            $topDebtors = (clone $query)
                ->selectRaw("COALESCE(c.name, CONCAT('Khách #', d.customer_id)) AS customer_name")
                ->selectRaw('SUM(d.debt_amount) AS debt_amount')
                ->selectRaw('SUM(CASE WHEN d.due_date < ? THEN d.debt_amount ELSE 0 END) AS overdue_amount', [$today])
                ->selectRaw('MIN(d.due_date) AS nearest_due_date')
                ->groupBy('d.customer_id', 'c.name')
                ->orderByDesc('debt_amount')
                ->limit(6)
                ->get()
                ->map(fn ($row) => [
                    'name' => (string) $row->customer_name,
                    'debt' => (float) $row->debt_amount,
                    'overdue' => (float) $row->overdue_amount,
                    'due_date' => $row->nearest_due_date,
                ])
                ->values()
                ->all();

            $aging = [
                'current' => (float) ($agingRow->current_amount ?? 0),
                '1_7' => (float) ($agingRow->d1_7 ?? 0),
                '8_30' => (float) ($agingRow->d8_30 ?? 0),
                'over_30' => (float) ($agingRow->over_30 ?? 0),
            ];

            return [
                'total' => $total,
                'overdue' => $aging['1_7'] + $aging['8_30'] + $aging['over_30'],
                'aging' => $aging,
                'forecast' => [
                    'today' => (float) ($forecastRow->today_amount ?? 0),
                    'next_7_days' => (float) ($forecastRow->next_7 ?? 0),
                    'next_30_days' => (float) ($forecastRow->next_30 ?? 0),
                ],
                'top_debtors' => $topDebtors,
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * Suy ra công nợ từ chênh lệch tổng đơn và tiền đã thanh toán khi thiếu bảng công nợ.
     */
    private function derivedOrderReceivables(User $user, array $access, array $filters): array
    {
        $empty = $this->emptyDebtPayload();
        if (! $this->hasTable('crm_orders') || ! $this->hasTable('crm_payments')) {
            return $empty;
        }

        try {
            $payments = DB::table('crm_payments')
                ->select('order_id')
                ->selectRaw('SUM(amount) AS paid_amount')
                ->groupBy('order_id');

            $query = DB::table('crm_orders as o')
                ->leftJoinSub($payments, 'pay', 'pay.order_id', '=', 'o.id')
                ->whereRaw('GREATEST(o.total_amount - COALESCE(pay.paid_amount, 0), 0) > 0');

            $this->scopeOrderCompanyAndUser($query, $user, $access, $filters, 'o');
            $this->excludeCancelledOrders($query, 'o');

            $total = (float) (clone $query)->selectRaw(
                'SUM(GREATEST(o.total_amount - COALESCE(pay.paid_amount, 0), 0)) AS amount'
            )->value('amount');

            $over30 = (float) (clone $query)
                ->whereDate('o.order_date', '<', now()->subDays(30)->toDateString())
                ->selectRaw('SUM(GREATEST(o.total_amount - COALESCE(pay.paid_amount, 0), 0)) AS amount')
                ->value('amount');

            return [
                'total' => $total,
                'overdue' => $over30,
                'aging' => [
                    'current' => max(0, $total - $over30),
                    '1_7' => 0.0,
                    '8_30' => 0.0,
                    'over_30' => $over30,
                ],
                'forecast' => $empty['forecast'],
                'top_debtors' => [],
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * Tính công nợ công trình từ các đợt thanh toán theo hợp đồng.
     */
    private function projectReceivables(User $user, array $access, array $filters): array
    {
        $empty = $this->emptyDebtPayload();
        if (! $this->hasTable('site_payment_terms') || ! $this->hasTable('sites')) {
            return $empty;
        }

        try {
            $receiptSub = (
                $this->hasTable('receipts')
                && $this->hasColumn('receipts', 'site_payment_term_id')
                && $this->hasColumn('receipts', 'amount')
            )
                ? DB::table('receipts')
                    ->select('site_payment_term_id')
                    ->selectRaw('SUM(amount) AS paid_amount')
                    ->whereNotNull('site_payment_term_id')
                    ->groupBy('site_payment_term_id')
                : null;

            $query = DB::table('site_payment_terms as t')
                ->join('sites as s', 's.id', '=', 't.site_id');

            if ($receiptSub) {
                $query->leftJoinSub($receiptSub, 'rp', 'rp.site_payment_term_id', '=', 't.id');
            }

            $paidExpr = $receiptSub ? 'COALESCE(rp.paid_amount, 0)' : '0';
            $remainExpr = "GREATEST(COALESCE(t.amount, 0) - {$paidExpr}, 0)";
            $query->whereRaw("{$remainExpr} > 0");

            $this->applyCompany($query, 'sites', 's');
            $this->scopeSiteUser($query, $user, $access, $filters, 's');

            $today = now()->toDateString();
            $day7 = now()->addDays(7)->toDateString();
            $day30 = now()->addDays(30)->toDateString();

            $row = (clone $query)->selectRaw(
                "SUM({$remainExpr}) AS total_amount,
                 SUM(CASE WHEN t.due_date IS NULL OR t.due_date >= ? THEN {$remainExpr} ELSE 0 END) AS current_amount,
                 SUM(CASE WHEN t.due_date < ? AND t.due_date >= ? THEN {$remainExpr} ELSE 0 END) AS d1_7,
                 SUM(CASE WHEN t.due_date < ? AND t.due_date >= ? THEN {$remainExpr} ELSE 0 END) AS d8_30,
                 SUM(CASE WHEN t.due_date < ? THEN {$remainExpr} ELSE 0 END) AS over_30,
                 SUM(CASE WHEN t.due_date = ? THEN {$remainExpr} ELSE 0 END) AS today_amount,
                 SUM(CASE WHEN t.due_date > ? AND t.due_date <= ? THEN {$remainExpr} ELSE 0 END) AS next_7,
                 SUM(CASE WHEN t.due_date > ? AND t.due_date <= ? THEN {$remainExpr} ELSE 0 END) AS next_30",
                [
                    $today,
                    $today, now()->subDays(7)->toDateString(),
                    now()->subDays(7)->toDateString(), now()->subDays(30)->toDateString(),
                    now()->subDays(30)->toDateString(),
                    $today,
                    $today, $day7,
                    $today, $day30,
                ]
            )->first();

            $aging = [
                'current' => (float) ($row->current_amount ?? 0),
                '1_7' => (float) ($row->d1_7 ?? 0),
                '8_30' => (float) ($row->d8_30 ?? 0),
                'over_30' => (float) ($row->over_30 ?? 0),
            ];

            return [
                'total' => (float) ($row->total_amount ?? 0),
                'overdue' => $aging['1_7'] + $aging['8_30'] + $aging['over_30'],
                'aging' => $aging,
                'forecast' => [
                    'today' => (float) ($row->today_amount ?? 0),
                    'next_7_days' => (float) ($row->next_7 ?? 0),
                    'next_30_days' => (float) ($row->next_30 ?? 0),
                ],
                'top_debtors' => [],
            ];
        } catch (\Throwable) {
            return $empty;
        }
    }

    /**
     * Tổng hợp cảnh báo vận hành (chờ duyệt, chờ xuất kho, nợ quá hạn, đổi trả...).
     */
    private function alerts(User $user, array $access, array $filters, array $range, array $debt): array
    {
        $items = [];
        $riskCount = 0;
        $riskValue = 0.0;

        $push = function (
            string $key,
            string $title,
            string $description,
            int $count,
            float $value,
            string $severity,
            ?string $url
        ) use (&$items, &$riskCount, &$riskValue): void {
            if ($count <= 0 && $value <= 0) {
                return;
            }

            $items[] = compact(
                'key', 'title', 'description', 'count', 'value', 'severity', 'url'
            );
            $riskCount += $count;
            $riskValue += $value;
        };

        if ($this->hasTable('crm_orders')) {
            $base = DB::table('crm_orders as o');
            $this->scopeOrderCompanyAndUser($base, $user, $access, $filters, 'o');
            $this->excludeCancelledOrders($base, 'o');

            $management = (clone $base)->where('o.current_department', 'management');
            $push(
                'management',
                'Đơn chờ Ban Giám đốc duyệt',
                'Cần phê duyệt để đơn tiếp tục sang kho.',
                (int) (clone $management)->count(),
                $this->hasColumn('crm_orders', 'total_amount')
                    ? (float) (clone $management)->sum('o.total_amount') : 0.0,
                'urgent',
                $this->routeUrl('orders.index', ['status' => 'duyet2'])
            );

            $warehouse = (clone $base)
                ->where('o.current_department', 'warehouse')
                ->where(function (Builder $query): void {
                    $query->whereNull('o.inventory_issued')->orWhere('o.inventory_issued', 0);
                });
            $push(
                'warehouse',
                'Đơn chờ xuất kho',
                'Đã duyệt nhưng chưa ghi nhận trừ tồn kho.',
                (int) (clone $warehouse)->count(),
                $this->hasColumn('crm_orders', 'total_amount')
                    ? (float) (clone $warehouse)->sum('o.total_amount') : 0.0,
                'warning',
                $this->routeUrl('orders.index', ['status' => 'kho'])
            );

            if ($this->hasColumn('crm_orders', 'estimated_delivery')) {
                $lateShipping = (clone $base)
                    ->where('o.inventory_issued', 1)
                    ->whereDate('o.estimated_delivery', '<', now()->toDateString())
                    ->where(function (Builder $query): void {
                        $query->whereNull('o.shipping_status')
                            ->orWhereNotIn('o.shipping_status', [
                                'delivered', 'completed', 'returned', 'cancelled',
                            ]);
                    });
                $push(
                    'late_shipping',
                    'Đơn có nguy cơ giao trễ',
                    'Đã xuất kho nhưng chưa hoàn tất giao hàng đúng hạn.',
                    (int) (clone $lateShipping)->count(),
                    $this->hasColumn('crm_orders', 'total_amount')
                        ? (float) (clone $lateShipping)->sum('o.total_amount') : 0.0,
                    'urgent',
                    $this->routeUrl('orders.index')
                );
            }
        }

        $push(
            'debt_30',
            'Công nợ quá hạn trên 30 ngày',
            'Ưu tiên thu hồi các khoản nợ có tuổi nợ cao.',
            $debt['overdue_30'] > 0 ? 1 : 0,
            (float) $debt['overdue_30'],
            'urgent',
            $this->routeUrl('orders.index', ['payment_filter' => 'debt_30'])
        );

        if ($this->hasTable('order_returns')) {
            $query = DB::table('order_returns as r')
                ->whereNotIn('r.status', ['completed', 'rejected', 'cancelled']);
            $this->applyCompany($query, 'order_returns', 'r');
            $push(
                'returns',
                'Yêu cầu đổi trả đang xử lý',
                'Theo dõi thu hồi, kiểm tra hàng và nhập hoàn.',
                (int) (clone $query)->count(),
                $this->hasColumn('order_returns', 'total_return_amount')
                    ? (float) (clone $query)->sum('r.total_return_amount') : 0.0,
                'warning',
                $this->routeUrl('order-returns.dashboard')
            );
        }

        if ($this->hasTable('order_refunds')) {
            $query = DB::table('order_refunds as rf')
                ->leftJoin('order_returns as r', 'r.id', '=', 'rf.order_return_id')
                ->whereNotIn('rf.status', ['processed', 'completed', 'rejected', 'cancelled']);
            if ($this->hasTable('order_returns')) {
                $this->applyCompany($query, 'order_returns', 'r');
            }
            $push(
                'refunds',
                'Hoàn tiền chưa xử lý',
                'Kế toán cần phê duyệt hoặc thực hiện hoàn tiền.',
                (int) (clone $query)->count(),
                $this->hasColumn('order_refunds', 'amount')
                    ? (float) (clone $query)->sum('rf.amount') : 0.0,
                'urgent',
                $this->routeUrl('order-returns.dashboard')
            );
        }

        if ($this->hasTable('solar_maintenance_schedules')) {
            $query = DB::table('solar_maintenance_schedules as m')
                ->whereDate('m.scheduled_date', '<', now()->toDateString())
                ->whereNotIn('m.status', ['completed', 'done', 'cancelled']);
            $this->applyCompany($query, 'solar_maintenance_schedules', 'm');
            $push(
                'maintenance',
                'Bảo trì/bảo hành quá hạn',
                'Lịch kỹ thuật đã quá ngày dự kiến nhưng chưa hoàn thành.',
                (int) (clone $query)->count(),
                0.0,
                'warning',
                $this->routeUrl('ky-thuat.maintenance.index')
            );
        }

        if ($this->hasTable('payment_requests')) {
            $query = DB::table('payment_requests as pr')
                ->whereIn('pr.status', ['submitted', 'admin_approved']);
            $this->applyCompany($query, 'payment_requests', 'pr');
            $push(
                'payment_requests',
                'Đề nghị thanh toán chờ duyệt',
                'Các phiếu chi đang chờ Ban Giám đốc hoặc Kế toán.',
                (int) (clone $query)->count(),
                $this->hasColumn('payment_requests', 'amount')
                    ? (float) (clone $query)->sum('pr.amount') : 0.0,
                'notice',
                $this->routeUrl('payment_requests.index')
            );
        }

        if ($this->hasTable('crm_product_stock')) {
            $query = DB::table('crm_product_stock as st')->where('st.qty', '<=', 5);
            $this->applyCompany($query, 'crm_product_stock', 'st');
            $push(
                'low_stock',
                'Sản phẩm sắp hết hoặc hết hàng',
                'Ngưỡng cảnh báo hiện tại: tồn khả dụng từ 5 trở xuống.',
                (int) (clone $query)->count(),
                0.0,
                'notice',
                $this->routeUrl('warehouses.index')
            );
        }

        usort($items, static function (array $a, array $b): int {
            $priority = ['urgent' => 0, 'warning' => 1, 'notice' => 2];

            return ($priority[$a['severity']] ?? 9) <=> ($priority[$b['severity']] ?? 9);
        });

        return [
            'items' => array_slice($items, 0, 8),
            'risk_count' => $riskCount,
            'risk_value' => $riskValue,
        ];
    }

    /**
     * Xây dựng dữ liệu biểu đồ xu hướng doanh thu và tiền thu theo ngày/tháng.
     */
    private function trend(User $user, array $access, array $filters, array $range): array
    {
        $daily = $range['days'] <= 62;
        $format = $daily ? 'Y-m-d' : 'Y-m';
        $dbFormat = $daily ? '%Y-%m-%d' : '%Y-%m';
        $points = collect();

        if ($daily) {
            foreach (CarbonPeriod::create($range['from']->copy()->startOfDay(), $range['to']->copy()->startOfDay()) as $date) {
                $points->push($date->format($format));
            }
        } else {
            $cursor = $range['from']->copy()->startOfMonth();
            $end = $range['to']->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $points->push($cursor->format($format));
                $cursor->addMonth();
            }
        }

        if ($points->count() > 18) {
            $points = $points->slice(-18)->values();
        }

        $orderRevenue = collect();
        $projectRevenue = collect();
        $collected = collect();

        if ($filters['source'] !== 'sites' && $this->hasTable('crm_orders')) {
            $dateColumn = $this->firstColumn('crm_orders', ['order_date', 'created_at']);
            if ($dateColumn && $this->hasColumn('crm_orders', 'total_amount')) {
                try {
                    $query = $this->orderQuery($user, $access, $filters, $range['from'], $range['to']);
                    $orderRevenue = $query
                        ->selectRaw("DATE_FORMAT(o.{$dateColumn}, '{$dbFormat}') AS period_key")
                        ->selectRaw('SUM(o.total_amount) AS amount')
                        ->groupBy('period_key')
                        ->pluck('amount', 'period_key');
                } catch (\Throwable) {
                    $orderRevenue = collect();
                }
            }

            if ($this->hasTable('crm_payments') && $this->hasColumn('crm_payments', 'amount')) {
                $paymentDate = $this->firstColumn('crm_payments', ['payment_date', 'created_at']);
                if ($paymentDate) {
                    try {
                        $query = DB::table('crm_payments as p')
                            ->join('crm_orders as o', 'o.id', '=', 'p.order_id')
                            ->whereBetween('p.'.$paymentDate, [
                                $range['from']->toDateTimeString(),
                                $range['to']->toDateTimeString(),
                            ]);
                        $this->scopeOrderCompanyAndUser($query, $user, $access, $filters, 'o');
                        $this->excludeCancelledOrders($query, 'o');
                        $rows = $query
                            ->selectRaw("DATE_FORMAT(p.{$paymentDate}, '{$dbFormat}') AS period_key")
                            ->selectRaw('SUM(p.amount) AS amount')
                            ->groupBy('period_key')
                            ->pluck('amount', 'period_key');
                        $collected = $rows;
                    } catch (\Throwable) {
                        // Ignore missing date compatibility.
                    }
                }
            }
        }

        if ($filters['source'] !== 'orders' && $this->hasTable('sites')) {
            $siteDate = $this->siteDateExpression('s');
            if ($siteDate && $this->hasColumn('sites', 'contract_amount')) {
                try {
                    $query = $this->siteQuery($user, $access, $filters, $range['from'], $range['to']);
                    $projectRevenue = $query
                        ->selectRaw("DATE_FORMAT({$siteDate}, '{$dbFormat}') AS period_key")
                        ->selectRaw('SUM(s.contract_amount) AS amount')
                        ->groupBy('period_key')
                        ->pluck('amount', 'period_key');
                } catch (\Throwable) {
                    $projectRevenue = collect();
                }
            }

            if ($this->hasTable('receipts') && $this->hasColumn('receipts', 'amount')) {
                $receiptDate = $this->firstColumn('receipts', ['paid_at', 'payment_date', 'receipt_date', 'created_at']);
                if ($receiptDate && $this->hasColumn('receipts', 'site_id')) {
                    try {
                        $query = DB::table('receipts as r')
                            ->join('sites as s', 's.id', '=', 'r.site_id')
                            ->whereBetween('r.'.$receiptDate, [
                                $range['from']->toDateTimeString(),
                                $range['to']->toDateTimeString(),
                            ]);
                        $this->applyCompany($query, 'sites', 's');
                        $this->scopeSiteUser($query, $user, $access, $filters, 's');
                        $rows = $query
                            ->selectRaw("DATE_FORMAT(r.{$receiptDate}, '{$dbFormat}') AS period_key")
                            ->selectRaw('SUM(r.amount) AS amount')
                            ->groupBy('period_key')
                            ->pluck('amount', 'period_key');

                        foreach ($rows as $key => $value) {
                            $collected[$key] = (float) ($collected[$key] ?? 0) + (float) $value;
                        }
                    } catch (\Throwable) {
                        // Ignore optional receipts structure.
                    }
                }
            }
        }

        return [
            'labels' => $points->map(function (string $key) use ($daily): string {
                return $daily
                    ? Carbon::parse($key)->format('d/m')
                    : Carbon::createFromFormat('Y-m', $key)->format('m/Y');
            })->all(),
            'commercial' => $points->map(fn ($key) => (float) ($orderRevenue[$key] ?? 0))->all(),
            'project' => $points->map(fn ($key) => (float) ($projectRevenue[$key] ?? 0))->all(),
            'collected' => $points->map(fn ($key) => (float) ($collected[$key] ?? 0))->all(),
        ];
    }

    /**
     * Thống kê số lượng và giá trị đơn hàng theo từng bộ phận xử lý.
     */
    private function pipeline(User $user, array $access, array $filters, array $range): array
    {
        $definitions = [
            'sales' => 'Sales',
            'sales_manager' => 'Sales Manager',
            'accounting' => 'Kế toán',
            'management' => 'Ban Giám đốc',
            'warehouse' => 'Kho',
            'completed' => 'Hoàn thành',
        ];

        if (! $this->hasTable('crm_orders')) {
            return collect($definitions)->map(fn ($label, $key) => [
                'key' => $key, 'label' => $label, 'count' => 0, 'value' => 0, 'overdue' => 0,
            ])->values()->all();
        }

        try {
            $query = $this->orderQuery($user, $access, $filters, $range['from'], $range['to']);
            $rows = $query
                ->selectRaw("COALESCE(o.current_department, 'sales') AS department")
                ->selectRaw('COUNT(o.id) AS total_count')
                ->selectRaw('COALESCE(SUM(o.total_amount), 0) AS total_value')
                ->selectRaw("SUM(CASE WHEN o.estimated_delivery IS NOT NULL AND DATE(o.estimated_delivery) < ? AND COALESCE(o.current_department, '') NOT IN ('completed','cancelled') THEN 1 ELSE 0 END) AS overdue_count", [now()->toDateString()])
                ->groupBy('department')
                ->get()
                ->keyBy('department');

            return collect($definitions)->map(function (string $label, string $key) use ($rows): array {
                $row = $rows->get($key);

                return [
                    'key' => $key,
                    'label' => $label,
                    'count' => (int) ($row->total_count ?? 0),
                    'value' => (float) ($row->total_value ?? 0),
                    'overdue' => (int) ($row->overdue_count ?? 0),
                    'url' => $this->routeUrl('orders.index', [
                        'status' => match ($key) {
                            'sales_manager' => 'duyet1',
                            'accounting' => 'ketoan',
                            'management' => 'duyet2',
                            'warehouse' => 'kho',
                            default => $key,
                        },
                        'from_date' => $range['from']->toDateString(),
                        'to_date' => $range['to']->toDateString(),
                    ]),
                ];
            })->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Tổng hợp số liệu tồn kho: tổng tồn, số SKU, hàng giữ chỗ, sắp hết hàng.
     */
    private function inventory(array $access, array $filters): array
    {
        $payload = [
            'total_qty' => 0.0,
            'sku_count' => 0,
            'reserved_qty' => 0.0,
            'low_count' => 0,
            'out_count' => 0,
            'low_stock' => [],
            'url' => $this->routeUrl('warehouses.index'),
        ];

        if (! $this->hasTable('crm_product_stock')) {
            return $payload;
        }

        try {
            $base = DB::table('crm_product_stock as st');
            $this->applyCompany($base, 'crm_product_stock', 'st');
            $payload['total_qty'] = (float) (clone $base)->sum('st.qty');
            $payload['sku_count'] = (int) (clone $base)->distinct()->count('st.product_id');
            $payload['low_count'] = (int) (clone $base)->whereBetween('st.qty', [1, 5])->count();
            $payload['out_count'] = (int) (clone $base)->where('st.qty', '<=', 0)->count();

            if ($this->hasTable('crm_order_item_stock_allocations') && $this->hasTable('crm_orders')) {
                $reserved = DB::table('crm_order_item_stock_allocations as a')
                    ->join('crm_orders as o', 'o.id', '=', 'a.order_id')
                    ->where(function (Builder $query): void {
                        $query->whereNull('o.inventory_issued')->orWhere('o.inventory_issued', 0);
                    });
                $this->applyCompany($reserved, 'crm_orders', 'o');
                $payload['reserved_qty'] = (float) $reserved->sum('a.qty');
            }

            $list = DB::table('crm_product_stock as st')
                ->leftJoin('crm_product_catalog as p', 'p.id', '=', 'st.product_id')
                ->leftJoin('crm_warehouses as w', 'w.id', '=', 'st.warehouse_id')
                ->where('st.qty', '<=', 5);
            $this->applyCompany($list, 'crm_product_stock', 'st');
            $payload['low_stock'] = $list
                ->selectRaw("COALESCE(p.name, CONCAT('Sản phẩm #', st.product_id)) AS product_name")
                ->selectRaw("COALESCE(p.sku, '—') AS sku")
                ->selectRaw("COALESCE(w.name, 'Chưa rõ kho') AS warehouse_name")
                ->addSelect('st.qty')
                ->orderBy('st.qty')
                ->limit(6)
                ->get()
                ->map(fn ($row) => [
                    'name' => (string) $row->product_name,
                    'sku' => (string) $row->sku,
                    'warehouse' => (string) $row->warehouse_name,
                    'qty' => (float) $row->qty,
                ])
                ->all();
        } catch (\Throwable) {
            // Return safe empty payload.
        }

        return $payload;
    }

    /**
     * Tổng hợp số liệu hậu mãi: đổi trả, hoàn tiền, lịch bảo trì.
     */
    private function afterSales(array $access, array $filters): array
    {
        $payload = [
            'returns_open' => 0,
            'returns_value' => 0.0,
            'refunds_pending' => 0,
            'refunds_value' => 0.0,
            'maintenance_today' => 0,
            'maintenance_next_7' => 0,
            'maintenance_overdue' => 0,
            'returns_url' => $this->routeUrl('order-returns.dashboard'),
            'maintenance_url' => $this->routeUrl('ky-thuat.maintenance.index'),
        ];

        try {
            if ($this->hasTable('order_returns')) {
                $query = DB::table('order_returns as r')
                    ->whereNotIn('r.status', ['completed', 'rejected', 'cancelled']);
                $this->applyCompany($query, 'order_returns', 'r');
                $payload['returns_open'] = (int) (clone $query)->count();
                if ($this->hasColumn('order_returns', 'total_return_amount')) {
                    $payload['returns_value'] = (float) (clone $query)->sum('r.total_return_amount');
                }
            }

            if ($this->hasTable('order_refunds')) {
                $query = DB::table('order_refunds as rf')
                    ->leftJoin('order_returns as r', 'r.id', '=', 'rf.order_return_id')
                    ->whereNotIn('rf.status', ['processed', 'completed', 'rejected', 'cancelled']);
                if ($this->hasTable('order_returns')) {
                    $this->applyCompany($query, 'order_returns', 'r');
                }
                $payload['refunds_pending'] = (int) (clone $query)->count();
                if ($this->hasColumn('order_refunds', 'amount')) {
                    $payload['refunds_value'] = (float) (clone $query)->sum('rf.amount');
                }
            }

            if ($this->hasTable('solar_maintenance_schedules')) {
                $base = DB::table('solar_maintenance_schedules as m')
                    ->whereNotIn('m.status', ['completed', 'done', 'cancelled']);
                $this->applyCompany($base, 'solar_maintenance_schedules', 'm');
                $payload['maintenance_today'] = (int) (clone $base)
                    ->whereDate('m.scheduled_date', now()->toDateString())
                    ->count();
                $payload['maintenance_next_7'] = (int) (clone $base)
                    ->whereBetween('m.scheduled_date', [
                        now()->addDay()->toDateString(),
                        now()->addDays(7)->toDateString(),
                    ])->count();
                $payload['maintenance_overdue'] = (int) (clone $base)
                    ->whereDate('m.scheduled_date', '<', now()->toDateString())
                    ->count();
            }
        } catch (\Throwable) {
            // Return safe defaults.
        }

        return $payload;
    }

    /**
     * Thống kê hiệu suất từng sales: số đơn, doanh thu, đã thu, công nợ.
     */
    private function teamPerformance(User $user, array $access, array $filters, array $range): array
    {
        if (! $this->hasTable('crm_orders') || ! $this->hasTable('users')) {
            return [];
        }

        try {
            $paymentsAll = $this->hasTable('crm_payments')
                ? DB::table('crm_payments')
                    ->select('order_id')
                    ->selectRaw('SUM(amount) AS paid_amount')
                    ->groupBy('order_id')
                : null;

            $query = $this->orderQuery($user, $access, $filters, $range['from'], $range['to'])
                ->leftJoin('users as u', 'u.id', '=', 'o.created_by');

            if ($paymentsAll) {
                $query->leftJoinSub($paymentsAll, 'pay', 'pay.order_id', '=', 'o.id');
            }

            $paidExpr = $paymentsAll ? 'COALESCE(pay.paid_amount, 0)' : '0';

            return $query
                ->selectRaw("COALESCE(u.name, CONCAT('User #', o.created_by)) AS name")
                ->selectRaw('o.created_by AS user_id')
                ->selectRaw('COUNT(o.id) AS orders_count')
                ->selectRaw('SUM(o.total_amount) AS revenue')
                ->selectRaw("SUM(LEAST({$paidExpr}, o.total_amount)) AS collected")
                ->selectRaw("SUM(GREATEST(o.total_amount - {$paidExpr}, 0)) AS debt")
                ->groupBy('o.created_by', 'u.name')
                ->orderByDesc('revenue')
                ->limit(8)
                ->get()
                ->map(fn ($row) => [
                    'user_id' => (int) $row->user_id,
                    'name' => (string) $row->name,
                    'orders' => (int) $row->orders_count,
                    'revenue' => (float) $row->revenue,
                    'collected' => (float) $row->collected,
                    'debt' => (float) $row->debt,
                    'collection_rate' => (float) $row->revenue > 0
                        ? min(100.0, (float) $row->collected / (float) $row->revenue * 100)
                        : 0.0,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Lấy các hoạt động gần đây (đơn mới, thanh toán, đổi trả, bảo trì).
     */
    private function recentActivities(User $user, array $access, array $filters): array
    {
        $items = collect();

        try {
            if ($this->hasTable('crm_orders')) {
                $query = DB::table('crm_orders as o')
                    ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
                    ->orderByDesc('o.created_at')
                    ->limit(4);
                $this->scopeOrderCompanyAndUser($query, $user, $access, $filters, 'o');
                $this->excludeCancelledOrders($query, 'o');
                foreach ($query->get([
                    'o.id', 'o.order_code', 'o.total_amount', 'o.created_at', 'u.name as actor',
                ]) as $row) {
                    $items->push([
                        'type' => 'order',
                        'title' => 'Đơn hàng mới '.($row->order_code ?: ('#'.$row->id)),
                        'description' => 'Giá trị '.$this->money((float) $row->total_amount),
                        'actor' => (string) ($row->actor ?? 'Hệ thống'),
                        'time' => $row->created_at,
                        'url' => $this->routeUrl('orders.show', ['id' => $row->id]),
                    ]);
                }
            }

            if ($this->hasTable('crm_payments') && $this->hasTable('crm_orders')) {
                $query = DB::table('crm_payments as p')
                    ->join('crm_orders as o', 'o.id', '=', 'p.order_id')
                    ->leftJoin('users as u', 'u.id', '=', 'p.recorded_by')
                    ->orderByDesc('p.created_at')
                    ->limit(4);
                $this->scopeOrderCompanyAndUser($query, $user, $access, $filters, 'o');
                foreach ($query->get([
                    'p.amount', 'p.created_at', 'o.id as order_id', 'o.order_code', 'u.name as actor',
                ]) as $row) {
                    $items->push([
                        'type' => 'payment',
                        'title' => 'Ghi nhận thanh toán '.($row->order_code ?: ('#'.$row->order_id)),
                        'description' => $this->money((float) $row->amount),
                        'actor' => (string) ($row->actor ?? 'Hệ thống'),
                        'time' => $row->created_at,
                        'url' => $this->routeUrl('orders.show', ['id' => $row->order_id]),
                    ]);
                }
            }

            if ($this->hasTable('order_returns')) {
                $query = DB::table('order_returns as r')
                    ->orderByDesc('r.created_at')
                    ->limit(3);
                $this->applyCompany($query, 'order_returns', 'r');
                foreach ($query->get(['r.id', 'r.return_code', 'r.status', 'r.created_at']) as $row) {
                    $items->push([
                        'type' => 'return',
                        'title' => 'Yêu cầu đổi trả '.($row->return_code ?: ('#'.$row->id)),
                        'description' => 'Trạng thái: '.(string) $row->status,
                        'actor' => 'Hậu mãi',
                        'time' => $row->created_at,
                        'url' => $this->routeUrl('order-returns.show', ['orderReturn' => $row->id]),
                    ]);
                }
            }

            if ($this->hasTable('solar_maintenance_schedules')) {
                $query = DB::table('solar_maintenance_schedules as m')
                    ->orderByDesc('m.updated_at')
                    ->limit(3);
                $this->applyCompany($query, 'solar_maintenance_schedules', 'm');
                foreach ($query->get(['m.id', 'm.schedule_code', 'm.site_name', 'm.status', 'm.updated_at']) as $row) {
                    $items->push([
                        'type' => 'maintenance',
                        'title' => 'Lịch kỹ thuật '.($row->schedule_code ?: ('#'.$row->id)),
                        'description' => (string) ($row->site_name ?: $row->status),
                        'actor' => 'Kỹ thuật',
                        'time' => $row->updated_at,
                        'url' => $this->routeUrl('ky-thuat.maintenance.show', ['schedule' => $row->id]),
                    ]);
                }
            }
        } catch (\Throwable) {
            // Return whatever was gathered before an optional table failed.
        }

        return $items
            ->sortByDesc(fn (array $item) => strtotime((string) ($item['time'] ?? '')) ?: 0)
            ->take(9)
            ->values()
            ->map(function (array $item): array {
                $item['time_label'] = $this->relativeTime($item['time'] ?? null);

                return $item;
            })
            ->all();
    }

    /**
     * Lấy danh sách user cho bộ lọc sales (chỉ khi có quyền lọc).
     */
    private function salesUsers(User $user, array $access): array
    {
        if (! $access['can_filter_sales'] || ! $this->hasTable('users')) {
            return [];
        }

        try {
            return DB::table('users')
                ->where(function (Builder $query): void {
                    $query->where('is_active', 1)->orWhereNull('is_active');
                })
                ->orderBy('name')
                ->limit(300)
                ->get(['id', 'name'])
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Tạo query đơn hàng đã áp scope công ty/user và khoảng thời gian.
     */
    private function orderQuery(
        User $user,
        array $access,
        array $filters,
        Carbon $from,
        Carbon $to
    ): Builder {
        $query = DB::table('crm_orders as o');
        $this->scopeOrderCompanyAndUser($query, $user, $access, $filters, 'o');
        $this->excludeCancelledOrders($query, 'o');

        $dateColumn = $this->firstColumn('crm_orders', ['order_date', 'created_at']);
        if ($dateColumn) {
            $query->whereBetween('o.'.$dateColumn, [
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ]);
        }

        return $query;
    }

    /**
     * Tạo query công trình đã áp scope công ty/user, loại trừ đơn hủy, theo thời gian.
     */
    private function siteQuery(
        User $user,
        array $access,
        array $filters,
        Carbon $from,
        Carbon $to
    ): Builder {
        $query = DB::table('sites as s');
        $this->applyCompany($query, 'sites', 's');
        $this->scopeSiteUser($query, $user, $access, $filters, 's');
        if ($this->hasColumn('sites', 'status')) {
            $query->where(function (Builder $sub): void {
                $sub->whereNull('s.status')
                    ->orWhereNotIn('s.status', ['cancelled', 'canceled', 'huy', 'hủy']);
            });
        }

        $dateExpression = $this->siteDateExpression('s');
        if ($dateExpression) {
            $query->whereBetween(DB::raw($dateExpression), [
                $from->toDateTimeString(),
                $to->toDateTimeString(),
            ]);
        }

        return $query;
    }

    /**
     * Áp bộ lọc công ty, sales và soft-delete cho query đơn hàng.
     */
    private function scopeOrderCompanyAndUser(
        Builder $query,
        User $user,
        array $access,
        array $filters,
        string $alias
    ): void {
        $this->applyCompany($query, 'crm_orders', $alias);

        $salesId = $access['own_only'] ? (int) $user->id : ($filters['sales_id'] ?? null);
        if ($salesId && $this->hasColumn('crm_orders', 'created_by')) {
            $query->where($alias.'.created_by', (int) $salesId);
        }

        if ($this->hasColumn('crm_orders', 'deleted_at')) {
            $query->whereNull($alias.'.deleted_at');
        }
    }

    /**
     * Áp bộ lọc sales cho query công trình.
     */
    private function scopeSiteUser(
        Builder $query,
        User $user,
        array $access,
        array $filters,
        string $alias
    ): void {
        $salesId = $access['own_only'] ? (int) $user->id : ($filters['sales_id'] ?? null);
        if ($salesId && $this->hasColumn('sites', 'created_by')) {
            $query->where($alias.'.created_by', (int) $salesId);
        }
    }

    /**
     * Loại trừ các đơn đã hủy khỏi query.
     */
    private function excludeCancelledOrders(Builder $query, string $alias): void
    {
        if ($this->hasColumn('crm_orders', 'current_department')) {
            $query->where(function (Builder $sub) use ($alias): void {
                $sub->whereNull($alias.'.current_department')
                    ->orWhereNotIn($alias.'.current_department', [
                        'cancelled', 'canceled', 'da_huy', 'huy',
                    ]);
            });
        }
    }

    /**
     * Áp scope công ty hiện tại cho query nếu có thể.
     */
    private function applyCompany(Builder $query, string $table, string $alias): void
    {
        try {
            EgoCompanyScope::applyToQuery($query, $table, $alias);
        } catch (\Throwable) {
            // No company filter only when the legacy table cannot be scoped.
        }
    }

    /**
     * Tạo biểu thức SQL lấy ngày của công trình (ưu tiên ngày ký hợp đồng).
     */
    private function siteDateExpression(string $alias): ?string
    {
        $columns = [];
        foreach (['contract_signed_at', 'created_at'] as $column) {
            if ($this->hasColumn('sites', $column)) {
                $columns[] = $alias.'.'.$column;
            }
        }

        if ($columns === []) {
            return null;
        }

        return count($columns) === 1
            ? $columns[0]
            : 'COALESCE('.implode(', ', $columns).')';
    }

    /**
     * Trả về cấu trúc công nợ rỗng mặc định.
     */
    private function emptyDebtPayload(): array
    {
        return [
            'total' => 0.0,
            'overdue' => 0.0,
            'aging' => ['current' => 0.0, '1_7' => 0.0, '8_30' => 0.0, 'over_30' => 0.0],
            'forecast' => ['today' => 0.0, 'next_7_days' => 0.0, 'next_30_days' => 0.0],
            'top_debtors' => [],
        ];
    }

    /**
     * Sinh URL từ tên route nếu route tồn tại, ngược lại trả về null.
     */
    private function routeUrl(string $name, array $parameters = []): ?string
    {
        try {
            return Route::has($name) ? route($name, array_filter(
                $parameters,
                static fn ($value) => $value !== null && $value !== ''
            )) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Trả về cột đầu tiên tồn tại trong bảng theo danh sách ứng viên.
     */
    private function firstColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $column) {
            if ($this->hasColumn($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Kiểm tra bảng tồn tại (có cache trong request).
     */
    private function hasTable(string $table): bool
    {
        return $this->tableCache[$table]
            ??= (function () use ($table): bool {
                try {
                    return Schema::hasTable($table);
                } catch (\Throwable) {
                    return false;
                }
            })();
    }

    /**
     * Kiểm tra cột tồn tại trong bảng (có cache trong request).
     */
    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        return $this->columnCache[$key]
            ??= (function () use ($table, $column): bool {
                try {
                    return $this->hasTable($table) && Schema::hasColumn($table, $column);
                } catch (\Throwable) {
                    return false;
                }
            })();
    }

    /**
     * Parse chuỗi ngày an toàn, trả về null nếu không hợp lệ.
     */
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

    /**
     * Chuyển chuỗi tiếng Việt về dạng slug không dấu, nối bằng gạch dưới.
     */
    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $map = [
            'đ' => 'd', 'Đ' => 'd',
            'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a', 'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a', 'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e', 'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o', 'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o', 'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u', 'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ];

        return preg_replace('/_+/', '_', str_replace([' ', '-'], '_', strtr($value, $map))) ?: '';
    }

    /**
     * Tính phần trăm thay đổi giữa kỳ hiện tại và kỳ trước.
     */
    private function percentChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.0001) {
            return $current > 0 ? 100.0 : null;
        }

        return ($current - $previous) / abs($previous) * 100;
    }

    /**
     * Định dạng số tiền kiểu Việt Nam kèm ký hiệu đ.
     */
    private function money(float $value): string
    {
        return number_format($value, 0, ',', '.').' đ';
    }

    /**
     * Chuyển thời gian về dạng tương đối dễ đọc (diffForHumans).
     */
    private function relativeTime(mixed $date): string
    {
        if (! $date) {
            return 'Không rõ thời gian';
        }

        try {
            return Carbon::parse($date)->diffForHumans();
        } catch (\Throwable) {
            return (string) $date;
        }
    }
}
