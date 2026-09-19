<?php

namespace App\Services\CRM\Commission;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SalesCompensationV2Service
{
    public const STATUSES = ['draft', 'pending', 'approved', 'locked'];

    public function companyId(): int
    {
        try {
            if (class_exists(\App\Support\EgoCompanyLock::class)) {
                return max(0, (int) \App\Support\EgoCompanyLock::id());
            }
        } catch (\Throwable) {
            // Fallback below.
        }

        return max(0, (int) (session('company_id') ?? session('selected_company_id') ?? 0));
    }

    public function permissions($user): array
    {
        $has = static function (array $roles) use ($user): bool {
            if (! $user) {
                return false;
            }

            if (method_exists($user, 'hasAnyRole')) {
                return $user->hasAnyRole($roles);
            }

            if (method_exists($user, 'hasRole')) {
                foreach ($roles as $role) {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                }
            }

            return isset($user->role) && in_array((string) $user->role, $roles, true);
        };

        return [
            'manage' => $has(['admin', 'sales_manager']),
            'approve' => $has(['admin', 'management']),
            'lock' => $has(['admin', 'accounting']),
            'view_all' => $has(['admin', 'management', 'accounting', 'sales_manager']),
            'is_sales' => $has(['sales']) && ! $has(['admin', 'sales_manager']),
        ];
    }

    public function assertSchema(): void
    {
        foreach ([
            'crm_compensation_months',
            'crm_compensation_staff_settings',
            'crm_compensation_order_overrides',
            'crm_compensation_adjustments',
            'crm_compensation_snapshots',
            'crm_compensation_audit_logs',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Thiếu bảng {$table}. Hãy chạy php artisan migrate --force.");
            }
        }
    }

    public function normalizeMonth(?string $month): string
    {
        $month = trim((string) $month);

        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return now()->format('Y-m');
        }

        return $month;
    }

    public function policy(string $month, bool $create = true): object
    {
        $this->assertSchema();
        $month = $this->normalizeMonth($month);
        $companyId = $this->companyId();

        $row = DB::table('crm_compensation_months')
            ->where('company_id', $companyId)
            ->where('period_month', $month)
            ->first();

        if ($row || ! $create) {
            return $row ?: (object) [];
        }

        $id = DB::table('crm_compensation_months')->insertGetId([
            'company_id' => $companyId,
            'period_month' => $month,
            'name' => 'Chính sách thu nhập '.$month,
            'status' => 'draft',
            'version' => 1,
            'sales_base_salary' => 7000000,
            'sales_responsibility_allowance' => 3000000,
            'sales_travel_allowance' => 2000000,
            'sales_target_revenue' => 500000000,
            'sales_threshold_percent' => 80,
            'lead_rate_percent' => 0.5,
            'member_rate_percent' => 1,
            'retail_rate_percent' => 1,
            'other_rate_percent' => 1,
            'project_rate_percent' => 3,
            'over_target_rate_percent' => 1,
            'new_dealer_bonus' => 2000000,
            'new_dealer_min_order' => 30000000,
            'new_dealer_min_products' => 2,
            'leader_base_salary' => 9000000,
            'leader_management_allowance' => 4000000,
            'leader_travel_mode' => 'percent',
            'leader_travel_value' => 1,
            'leader_target_revenue' => 1000000000,
            'leader_threshold_percent' => 80,
            'leader_team_rate_percent' => 0.3,
            'leader_over_target_rate_percent' => 1,
            'leader_personal_commission' => 1,
            'calculate_on' => 'paid_before_vat',
            'kpi_calculate_on' => 'commission_base',
            'include_partial_payment' => 1,
            'hold_if_debt' => 0,
            'require_approved_policy' => 1,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit($month, 'policy.created', 'crm_compensation_months', $id, null, ['period_month' => $month]);

        return DB::table('crm_compensation_months')->where('id', $id)->first();
    }

    public function salesUsers(): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        $query = DB::table('users as u')
            ->select('u.id', 'u.name', 'u.email')
            ->selectRaw("GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ',') as role_names")
            ->leftJoin('model_has_roles as mhr', function ($join): void {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', '=', User::class);
            })
            ->leftJoin('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where(function ($q): void {
                $q->whereIn('r.name', ['sales', 'sales_manager'])
                    ->orWhereRaw("LOWER(COALESCE(r.name, '')) LIKE '%sales%'");
            })
            ->when(Schema::hasColumn('users', 'is_active'), fn ($q) => $q->where('u.is_active', 1))
            ->groupBy('u.id', 'u.name', 'u.email')
            ->orderBy('u.name');

        return $query->get()->map(function ($row) {
            $roles = array_filter(explode(',', (string) ($row->role_names ?? '')));
            $row->role_type = in_array('sales_manager', $roles, true) ? 'leader' : 'sale';

            return $row;
        });
    }

    public function staffSettings(string $month, object $policy): Collection
    {
        $month = $this->normalizeMonth($month);
        $companyId = $this->companyId();
        $users = $this->salesUsers();

        $stored = DB::table('crm_compensation_staff_settings')
            ->where('company_id', $companyId)
            ->where('period_month', $month)
            ->get()
            ->keyBy('user_id');

        return $users->map(function ($user) use ($stored, $policy, $month, $companyId) {
            $existing = $stored->get($user->id);
            $roleType = (string) ($existing->role_type ?? $user->role_type ?? 'sale');
            $isLeader = $roleType === 'leader';

            return (object) [
                'id' => $existing->id ?? null,
                'company_id' => $companyId,
                'period_month' => $month,
                'user_id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) ($user->email ?? ''),
                'role_names' => (string) ($user->role_names ?? ''),
                'role_type' => $roleType,
                'manager_id' => $existing->manager_id ?? null,
                'base_salary' => (float) ($existing->base_salary ?? ($isLeader ? $policy->leader_base_salary : $policy->sales_base_salary)),
                'responsibility_allowance' => (float) ($existing->responsibility_allowance ?? ($isLeader ? $policy->leader_management_allowance : $policy->sales_responsibility_allowance)),
                'travel_allowance' => (float) ($existing->travel_allowance ?? ($isLeader ? 0 : $policy->sales_travel_allowance)),
                'target_revenue' => (float) ($existing->target_revenue ?? ($isLeader ? $policy->leader_target_revenue : $policy->sales_target_revenue)),
                'kpi_percent' => (float) ($existing->kpi_percent ?? 100),
                'went_to_market' => (bool) ($existing->went_to_market ?? false),
                'is_active' => (bool) ($existing->is_active ?? true),
                'note' => (string) ($existing->note ?? ''),
            ];
        });
    }

    public function dashboard(string $month, array $filters = [], $user = null, bool $forceLive = false): array
    {
        $this->assertSchema();
        $month = $this->normalizeMonth($month);
        $policy = $this->policy($month);

        if (! $forceLive && ($policy->status ?? '') === 'locked') {
            $snapshot = DB::table('crm_compensation_snapshots')
                ->where('company_id', $this->companyId())
                ->where('period_month', $month)
                ->where('snapshot_key', 'dashboard')
                ->first();

            if ($snapshot) {
                $decoded = json_decode((string) $snapshot->payload, true);

                if (is_array($decoded)) {
                    $decoded['is_snapshot'] = true;
                    $decoded['policy'] = (array) $policy;
                    $decoded['permissions'] = $this->permissions($user);
                    $decoded['sales_options'] = $this->salesUsers()->map(fn ($x) => (array) $x)->values()->all();

                    return $this->applyDashboardFilters($decoded, $filters, $user);
                }
            }
        }

        return $this->liveDashboard($month, $filters, $user, $policy);
    }

    private function liveDashboard(string $month, array $filters, $user, object $policy): array
    {
        $companyId = $this->companyId();
        $staff = $this->staffSettings($month, $policy);
        $staffByUser = $staff->keyBy('user_id');
        $permissions = $this->permissions($user);

        $overrides = DB::table('crm_compensation_order_overrides')
            ->where('company_id', $companyId)
            ->where('period_month', $month)
            ->get()
            ->keyBy('order_id');

        $adjustments = DB::table('crm_compensation_adjustments as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.user_id')
            ->where('a.company_id', $companyId)
            ->where('a.period_month', $month)
            ->select('a.*', 'u.name as user_name')
            ->orderByDesc('a.id')
            ->get();

        $adjustmentsByUser = $adjustments->groupBy('user_id');
        $rows = $this->rawOrders($month, $filters, $user, $permissions);

        $prepared = [];

        foreach ($rows as $row) {
            $override = $overrides->get($row->order_id);
            $staffSetting = $staffByUser->get((int) $row->sales_id);

            if (! $staffSetting || ! $staffSetting->is_active) {
                continue;
            }

            $orderTotal = max(0, (float) $row->order_total);
            $taxAmount = max(0, (float) $row->tax_amount);
            $itemBefore = max(0, (float) $row->item_before_vat);
            $orderBefore = $taxAmount > 0 && $orderTotal >= $taxAmount
                ? $orderTotal - $taxAmount
                : ($itemBefore > 0 ? $itemBefore : $orderTotal);

            $paidBefore = max(0, (float) $row->paid_before_period);
            $paidInPeriod = max(0, (float) $row->paid_in_period);
            $paidLifetime = max(0, (float) $row->paid_lifetime);
            $remainingAtStart = max(0, $orderTotal - $paidBefore);

            if ((bool) $policy->include_partial_payment) {
                $eligiblePaid = min($paidInPeriod, $remainingAtStart);
            } else {
                $becameFullyPaid = $paidBefore < $orderTotal && ($paidBefore + $paidInPeriod) >= $orderTotal;
                $eligiblePaid = $becameFullyPaid ? $remainingAtStart : 0;
            }

            $eligibleBeforeVat = $orderTotal > 0
                ? $eligiblePaid * ($orderBefore / $orderTotal)
                : 0;

            $commissionBase = match ((string) $policy->calculate_on) {
                'paid_after_vat' => $eligiblePaid,
                'order_before_vat' => $orderBefore,
                default => $eligibleBeforeVat,
            };

            $customerGroup = $this->normalizeCustomerGroup(
                (string) ($override->customer_group ?? $row->customer_status ?? 'other')
            );

            $orderType = in_array((string) ($override->order_type ?? ''), ['distribution', 'project'], true)
                ? (string) $override->order_type
                : 'distribution';

            $orderDateForRevenue = $row->order_date ?: $row->created_at;
            $isOrderInPeriod = false;
            try {
                $isOrderInPeriod = $orderDateForRevenue
                    ? Carbon::parse($orderDateForRevenue)->format('Y-m') === $month
                    : false;
            } catch (\Throwable) {
                $isOrderInPeriod = false;
            }

            $prepared[] = [
                'order_id' => (int) $row->order_id,
                'order_code' => (string) $row->order_code,
                'order_date' => (string) ($row->order_date ?? ''),
                'activity_date' => (string) ($row->last_payment_date ?: $row->order_date ?: $row->created_at),
                'customer_id' => (int) ($row->customer_id ?? 0),
                'customer_name' => (string) ($row->customer_name ?? '-'),
                'customer_group' => $customerGroup,
                'sales_id' => (int) $row->sales_id,
                'sales_name' => (string) $row->sales_name,
                'order_type' => $orderType,
                'is_order_in_period' => $isOrderInPeriod,
                'order_total' => round($orderTotal, 2),
                'order_revenue' => round($isOrderInPeriod ? $orderTotal : 0, 2),
                'order_before_vat' => round($orderBefore, 2),
                'order_before_vat_in_period' => round($isOrderInPeriod ? $orderBefore : 0, 2),
                'paid_before_period' => round($paidBefore, 2),
                'paid_in_period' => round($paidInPeriod, 2),
                'eligible_paid' => round($eligiblePaid, 2),
                'paid_lifetime' => round($paidLifetime, 2),
                'debt' => round(max(0, $orderTotal - $paidLifetime), 2),
                'commission_base' => round(max(0, $commissionBase), 2),
                'kpi_base' => 0.0,
                'product_count' => (int) ($row->product_count ?? 0),
                'item_quantity' => (float) ($row->item_quantity ?? 0),
                'product_names' => (string) ($row->product_names ?? ''),
                'rate_override_percent' => isset($override->rate_override_percent) ? (float) $override->rate_override_percent : null,
                'commission_override_amount' => isset($override->commission_override_amount) ? (float) $override->commission_override_amount : null,
                'is_new_dealer' => (bool) ($override->is_new_dealer ?? false),
                'new_dealer_bonus_override' => isset($override->new_dealer_bonus_override) ? (float) $override->new_dealer_bonus_override : null,
                'is_excluded' => (bool) ($override->is_excluded ?? false),
                'override_reason' => (string) ($override->reason ?? ''),
                'commission' => 0.0,
                'held_commission' => 0.0,
                'dealer_bonus' => 0.0,
                'rate_percent' => 0.0,
                'commission_note' => '',
            ];
        }

        usort($prepared, fn (array $a, array $b) => strcmp($a['activity_date'], $b['activity_date']));

        $kpiMode = in_array((string) ($policy->kpi_calculate_on ?? ''), ['order_revenue', 'paid_in_period', 'commission_base'], true)
            ? (string) $policy->kpi_calculate_on
            : 'commission_base';

        $distributionTotals = [];
        $runningKpiBySales = [];
        foreach ($prepared as &$row) {
            $row['kpi_base'] = match ($kpiMode) {
                'order_revenue' => (float) $row['order_revenue'],
                'paid_in_period' => (float) $row['paid_in_period'],
                default => (float) $row['commission_base'],
            };
            $row['kpi_before'] = (float) ($runningKpiBySales[$row['sales_id']] ?? 0);

            if (! $row['is_excluded'] && $row['order_type'] === 'distribution') {
                $distributionTotals[$row['sales_id']] = ($distributionTotals[$row['sales_id']] ?? 0) + $row['kpi_base'];
                $runningKpiBySales[$row['sales_id']] = $row['kpi_before'] + $row['kpi_base'];
            }
        }
        unset($row);

        foreach ($prepared as &$row) {
            $staffSetting = $staffByUser->get($row['sales_id']);
            $target = max(0, (float) ($staffSetting->target_revenue ?? $policy->sales_target_revenue));
            $totalDistribution = (float) ($distributionTotals[$row['sales_id']] ?? 0);
            $achievement = $target > 0 ? ($totalDistribution / $target) * 100 : 100;

            if ($row['is_excluded'] || $row['commission_base'] <= 0) {
                $row['commission_note'] = $row['is_excluded'] ? 'Đơn bị loại theo điều chỉnh' : 'Chưa có tiền đủ điều kiện trong tháng';
                continue;
            }

            if ($row['commission_override_amount'] !== null) {
                $row['commission'] = max(0, $row['commission_override_amount']);
                $row['rate_percent'] = $row['commission_base'] > 0
                    ? ($row['commission'] / $row['commission_base']) * 100
                    : 0;
                $row['commission_note'] = 'Số tiền hoa hồng được chỉnh riêng cho đơn';
            } elseif ($row['order_type'] === 'project') {
                $rate = $row['rate_override_percent'] ?? (float) $policy->project_rate_percent;
                $row['rate_percent'] = $rate;
                $row['commission'] = $row['commission_base'] * $rate / 100;
                $row['commission_note'] = 'Công trình nhà dân, không áp ngưỡng KPI phân phối';
            } else {
                if ($achievement + 0.00001 < (float) $policy->sales_threshold_percent) {
                    $row['commission_note'] = 'Doanh số tháng chưa đạt ngưỡng '.number_format((float) $policy->sales_threshold_percent, 0).'% KPI';
                    continue;
                }

                $normalRate = $row['rate_override_percent'] ?? $this->customerRate($row['customer_group'], $policy);
                $beforeKpi = (float) ($row['kpi_before'] ?? 0);
                $base = (float) $row['commission_base'];
                $rowKpiBase = max(0, (float) $row['kpi_base']);

                if ($target > 0 && $rowKpiBase > 0) {
                    $withinKpi = max(0, min($rowKpiBase, $target - $beforeKpi));
                    $withinRatio = min(1, max(0, $withinKpi / $rowKpiBase));
                    $withinTarget = $base * $withinRatio;
                } else {
                    $withinTarget = $base;
                }

                $overTarget = max(0, $base - $withinTarget);
                $commission = ($withinTarget * $normalRate / 100)
                    + ($overTarget * (float) $policy->over_target_rate_percent / 100);

                $row['commission'] = $commission;
                $row['rate_percent'] = $base > 0 ? ($commission / $base) * 100 : 0;
                $row['commission_note'] = $overTarget > 0
                    ? 'Gồm phần trong chỉ tiêu và phần vượt chỉ tiêu'
                    : 'Theo nhóm khách '.$this->customerGroupLabel($row['customer_group']);

            }

            if ((bool) $policy->hold_if_debt && $row['debt'] > 0) {
                $row['held_commission'] = $row['commission'];
                $row['commission'] = 0;
                $row['commission_note'] .= ' • Đang giữ do còn công nợ';
            }

            if (
                $row['is_new_dealer']
                && $row['order_total'] >= (float) $policy->new_dealer_min_order
                && $row['item_quantity'] >= (float) $policy->new_dealer_min_products
            ) {
                $row['dealer_bonus'] = $row['new_dealer_bonus_override']
                    ?? (float) $policy->new_dealer_bonus;
            }

            $row['commission'] = round($row['commission'], 2);
            $row['held_commission'] = round($row['held_commission'], 2);
            $row['dealer_bonus'] = round($row['dealer_bonus'], 2);
            $row['rate_percent'] = round($row['rate_percent'], 4);
        }
        unset($row);

        $ordersBySales = collect($prepared)->groupBy('sales_id');
        $staffRows = [];

        foreach ($staff as $setting) {
            if (! $setting->is_active) {
                continue;
            }

            $userOrders = $ordersBySales->get($setting->user_id, collect());
            $activeOrders = $userOrders->where('is_excluded', false);
            $distributionOrders = $activeOrders->where('order_type', 'distribution');
            $projectOrders = $activeOrders->where('order_type', 'project');

            $totalOrderRevenue = (float) $activeOrders->sum('order_revenue');
            $totalPaidInPeriod = (float) $activeOrders->sum('paid_in_period');
            $totalPaidLifetime = (float) $activeOrders->sum('paid_lifetime');
            $totalDebt = (float) $activeOrders->sum('debt');
            $commissionBaseTotal = (float) $activeOrders->sum('commission_base');

            $distributionOrderRevenue = (float) $distributionOrders->sum('order_revenue');
            $distributionPaidInPeriod = (float) $distributionOrders->sum('paid_in_period');
            $distributionRevenue = (float) $distributionOrders->sum('commission_base');
            $projectRevenue = (float) $projectOrders->sum('commission_base');
            $kpiRevenue = match ($kpiMode) {
                'order_revenue' => $distributionOrderRevenue,
                'paid_in_period' => $distributionPaidInPeriod,
                default => $distributionRevenue,
            };

            $personalCommission = (float) $userOrders->sum('commission');
            $heldCommission = (float) $userOrders->sum('held_commission');
            $dealerBonus = (float) $userOrders->sum('dealer_bonus');
            $target = max(0, (float) $setting->target_revenue);
            $achievement = $target > 0 ? ($kpiRevenue / $target) * 100 : 100;
            $responsibility = max(0, (float) $setting->responsibility_allowance) * min(100, max(0, (float) $setting->kpi_percent)) / 100;
            $travel = $setting->went_to_market ? max(0, (float) $setting->travel_allowance) : 0;

            $userAdjustments = $adjustmentsByUser->get($setting->user_id, collect());
            $adjustmentTotal = (float) $userAdjustments->sum(fn ($a) => $this->signedAdjustment($a));

            $staffRows[$setting->user_id] = [
                'user_id' => (int) $setting->user_id,
                'name' => (string) $setting->name,
                'email' => (string) $setting->email,
                'role_type' => (string) $setting->role_type,
                'manager_id' => $setting->manager_id ? (int) $setting->manager_id : null,
                'base_salary' => round((float) $setting->base_salary, 2),
                'responsibility_allowance' => round($responsibility, 2),
                'travel_allowance' => round($travel, 2),
                'target_revenue' => round($target, 2),
                'kpi_percent' => round((float) $setting->kpi_percent, 2),
                'went_to_market' => (bool) $setting->went_to_market,
                'total_order_revenue' => round($totalOrderRevenue, 2),
                'paid_in_period' => round($totalPaidInPeriod, 2),
                'paid_lifetime' => round($totalPaidLifetime, 2),
                'debt' => round($totalDebt, 2),
                'commission_base' => round($commissionBaseTotal, 2),
                'distribution_order_revenue' => round($distributionOrderRevenue, 2),
                'distribution_paid_in_period' => round($distributionPaidInPeriod, 2),
                'distribution_revenue' => round($distributionRevenue, 2),
                'project_revenue' => round($projectRevenue, 2),
                'kpi_revenue' => round($kpiRevenue, 2),
                'kpi_basis' => $kpiMode,
                'achievement_percent' => round($achievement, 2),
                'personal_commission' => round($personalCommission, 2),
                'team_commission' => 0.0,
                'held_commission' => round($heldCommission, 2),
                'dealer_bonus' => round($dealerBonus, 2),
                'adjustment_total' => round($adjustmentTotal, 2),
                'orders_count' => $userOrders->count(),
                'total_income' => 0.0,
            ];
        }

        foreach ($staffRows as $leaderId => &$leader) {
            if ($leader['role_type'] !== 'leader') {
                continue;
            }

            $members = collect($staffRows)->filter(fn ($x) => (int) ($x['manager_id'] ?? 0) === (int) $leaderId);
            $teamRevenue = (float) $members->sum('kpi_revenue');
            $teamOrderRevenue = (float) $members->sum('total_order_revenue');
            $teamPaidInPeriod = (float) $members->sum('paid_in_period');
            $teamDebt = (float) $members->sum('debt');
            $teamCommissionBase = (float) $members->sum('commission_base');
            $leaderTarget = max(0, (float) $leader['target_revenue']);
            $teamAchievement = $leaderTarget > 0 ? ($teamRevenue / $leaderTarget) * 100 : 100;
            $teamCommission = 0.0;

            if ($teamAchievement + 0.00001 >= (float) $policy->leader_threshold_percent) {
                if ($leaderTarget > 0 && $teamRevenue > 0) {
                    $withinKpi = min($teamRevenue, $leaderTarget);
                    $withinRatio = min(1, max(0, $withinKpi / $teamRevenue));
                    $withinCommissionBase = $teamCommissionBase * $withinRatio;
                } else {
                    $withinCommissionBase = $teamCommissionBase;
                }

                $overCommissionBase = max(0, $teamCommissionBase - $withinCommissionBase);
                $teamCommission = ($withinCommissionBase * (float) $policy->leader_team_rate_percent / 100)
                    + ($overCommissionBase * (float) $policy->leader_over_target_rate_percent / 100);
            }

            $leaderTravel = 0.0;
            if ($leader['went_to_market']) {
                $leaderTravel = (string) $policy->leader_travel_mode === 'fixed'
                    ? (float) $policy->leader_travel_value
                    : $teamCommissionBase * (float) $policy->leader_travel_value / 100;
            }

            if (! (bool) $policy->leader_personal_commission) {
                $leader['personal_commission'] = 0.0;
            }

            $leader['team_revenue'] = round($teamRevenue, 2);
            $leader['team_order_revenue'] = round($teamOrderRevenue, 2);
            $leader['team_paid_in_period'] = round($teamPaidInPeriod, 2);
            $leader['team_debt'] = round($teamDebt, 2);
            $leader['team_commission_base'] = round($teamCommissionBase, 2);
            $leader['team_members'] = $members->count();
            $leader['achievement_percent'] = round($teamAchievement, 2);
            $leader['team_commission'] = round($teamCommission, 2);
            $leader['travel_allowance'] = round($leaderTravel, 2);
        }
        unset($leader);

        foreach ($staffRows as &$summary) {
            $summary['total_income'] = round(
                $summary['base_salary']
                + $summary['responsibility_allowance']
                + $summary['travel_allowance']
                + $summary['personal_commission']
                + $summary['team_commission']
                + $summary['dealer_bonus']
                + $summary['adjustment_total'],
                2
            );
        }
        unset($summary);

        $staffRows = collect(array_values($staffRows))
            ->sortByDesc('total_income')
            ->values()
            ->all();

        usort($prepared, fn (array $a, array $b) => strcmp($b['activity_date'], $a['activity_date']));

        $preparedCollection = collect($prepared);
        $activePrepared = $preparedCollection->where('is_excluded', false);
        $totalOrderRevenue = round((float) $activePrepared->sum('order_revenue'), 2);
        $totalPaidInPeriod = round((float) $activePrepared->sum('paid_in_period'), 2);
        $totalPaidLifetime = round((float) $activePrepared->sum('paid_lifetime'), 2);
        $totalDebt = round((float) $activePrepared->sum('debt'), 2);
        $totalCommissionBase = round((float) $activePrepared->sum('commission_base'), 2);

        $totals = [
            'order_total' => $totalOrderRevenue,
            'total_order_revenue' => $totalOrderRevenue,
            'order_before_vat' => round((float) $activePrepared->sum('order_before_vat_in_period'), 2),
            'paid_in_period' => $totalPaidInPeriod,
            'paid_lifetime' => $totalPaidLifetime,
            'debt' => $totalDebt,
            'eligible_before_vat' => $totalCommissionBase,
            'commission_base' => $totalCommissionBase,
            'kpi_calculate_on' => $kpiMode,
            'commission' => round((float) collect($staffRows)->sum(fn ($x) => $x['personal_commission'] + $x['team_commission']), 2),
            'held_commission' => round((float) collect($prepared)->sum('held_commission'), 2),
            'dealer_bonus' => round((float) collect($prepared)->sum('dealer_bonus'), 2),
            'fixed_income' => round((float) collect($staffRows)->sum(fn ($x) => $x['base_salary'] + $x['responsibility_allowance'] + $x['travel_allowance']), 2),
            'adjustments' => round((float) collect($staffRows)->sum('adjustment_total'), 2),
            'total_income' => round((float) collect($staffRows)->sum('total_income'), 2),
            'orders_count' => count($prepared),
            'staff_count' => count($staffRows),
        ];

        $data = [
            'month' => $month,
            'policy' => (array) $policy,
            'permissions' => $permissions,
            'is_snapshot' => false,
            'orders' => $prepared,
            'staff_rows' => $staffRows,
            'totals' => $totals,
            'adjustments' => $adjustments->map(fn ($x) => (array) $x)->values()->all(),
            'overrides' => $overrides->map(fn ($x) => (array) $x)->values()->all(),
            'staff_settings' => $staff->map(fn ($x) => (array) $x)->values()->all(),
            'sales_options' => $this->salesUsers()->map(fn ($x) => (array) $x)->values()->all(),
            'audit_logs' => $this->auditLogs($month)->map(fn ($x) => (array) $x)->values()->all(),
        ];

        return $this->applyDashboardFilters($data, $filters, $user);
    }

    private function applyDashboardFilters(array $data, array $filters, $user): array
    {
        $permissions = $this->permissions($user);
        $salesId = isset($filters['sales_id']) ? (int) $filters['sales_id'] : 0;
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $orderType = (string) ($filters['order_type'] ?? '');
        $customerGroup = (string) ($filters['customer_group'] ?? '');

        if ($permissions['is_sales']) {
            $salesId = (int) $user->id;
        }

        $data['orders'] = array_values(array_filter($data['orders'] ?? [], function (array $row) use ($salesId, $q, $orderType, $customerGroup): bool {
            if ($salesId > 0 && (int) $row['sales_id'] !== $salesId) {
                return false;
            }
            if ($orderType !== '' && $row['order_type'] !== $orderType) {
                return false;
            }
            if ($customerGroup !== '' && $row['customer_group'] !== $customerGroup) {
                return false;
            }
            if ($q !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $row['order_code'] ?? '',
                    $row['customer_name'] ?? '',
                    $row['sales_name'] ?? '',
                    $row['product_names'] ?? '',
                ]));
                if (! str_contains($haystack, $q)) {
                    return false;
                }
            }

            return true;
        }));

        if ($salesId > 0) {
            $data['staff_rows'] = array_values(array_filter(
                $data['staff_rows'] ?? [],
                fn (array $row): bool => (int) $row['user_id'] === $salesId
            ));
        } elseif ($permissions['is_sales']) {
            $data['staff_rows'] = array_values(array_filter(
                $data['staff_rows'] ?? [],
                fn (array $row): bool => (int) $row['user_id'] === (int) $user->id
            ));
        }

        return $data;
    }

    private function rawOrders(string $month, array $filters, $user, array $permissions): Collection
    {
        foreach (['crm_orders', 'crm_payments', 'crm_order_items', 'crm_product_catalog', 'crm_leads', 'crm_customers', 'users', 'model_has_roles', 'roles'] as $table) {
            if (! Schema::hasTable($table)) {
                return collect();
            }
        }

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $paymentDate = 'COALESCE(p.payment_date, DATE(p.created_at))';
        $paymentSub = DB::table('crm_payments as p')
            ->select('p.order_id')
            ->selectRaw("SUM(CASE WHEN {$paymentDate} < ? THEN COALESCE(p.amount,0) ELSE 0 END) as paid_before_period", [$start])
            ->selectRaw("SUM(CASE WHEN {$paymentDate} BETWEEN ? AND ? THEN COALESCE(p.amount,0) ELSE 0 END) as paid_in_period", [$start, $end])
            ->selectRaw('SUM(COALESCE(p.amount,0)) as paid_lifetime')
            ->selectRaw("MAX(CASE WHEN {$paymentDate} BETWEEN ? AND ? THEN {$paymentDate} ELSE NULL END) as last_payment_date", [$start, $end])
            ->groupBy('p.order_id');

        $itemSub = DB::table('crm_order_items as oi')
            ->select('oi.order_id')
            ->selectRaw('SUM(CASE WHEN COALESCE(oi.vat_percent,0) > 0 THEN COALESCE(oi.line_total,0) / (1 + COALESCE(oi.vat_percent,0)/100) ELSE COALESCE(oi.line_total,0) END) as item_before_vat')
            ->selectRaw('SUM(COALESCE(oi.quantity,0)) as item_quantity')
            ->selectRaw('COUNT(DISTINCT oi.product_id) as product_count')
            ->selectRaw("GROUP_CONCAT(DISTINCT COALESCE(oi.product_name, pc.name) ORDER BY COALESCE(oi.product_name, pc.name) SEPARATOR ', ') as product_names")
            ->leftJoin('crm_product_catalog as pc', 'pc.id', '=', 'oi.product_id')
            ->groupBy('oi.order_id');

        $query = DB::table('crm_orders as o')
            ->leftJoinSub($paymentSub, 'pay', fn ($join) => $join->on('pay.order_id', '=', 'o.id'))
            ->leftJoinSub($itemSub, 'items', fn ($join) => $join->on('items.order_id', '=', 'o.id'))
            ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'o.created_by')
            ->select([
                'o.id as order_id',
                'o.order_code',
                'o.order_date',
                'o.created_at',
                'o.company_id',
                'o.created_by as sales_id',
                'o.total_amount as order_total',
                'o.tax_amount',
                'l.customer_id',
                'c.name as customer_name',
                'c.customer_status',
                'u.name as sales_name',
            ])
            ->selectRaw('COALESCE(pay.paid_before_period,0) as paid_before_period')
            ->selectRaw('COALESCE(pay.paid_in_period,0) as paid_in_period')
            ->selectRaw('COALESCE(pay.paid_lifetime,0) as paid_lifetime')
            ->selectRaw('pay.last_payment_date')
            ->selectRaw('COALESCE(items.item_before_vat,0) as item_before_vat')
            ->selectRaw('COALESCE(items.item_quantity,0) as item_quantity')
            ->selectRaw('COALESCE(items.product_count,0) as product_count')
            ->selectRaw("COALESCE(items.product_names,'') as product_names")
            ->whereNull('o.deleted_at')
            ->where(function ($q) use ($start, $end): void {
                $q->whereBetween(DB::raw('COALESCE(o.order_date, DATE(o.created_at))'), [$start, $end])
                    ->orWhereRaw('COALESCE(pay.paid_in_period,0) > 0');
            })
            ->whereExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('model_has_roles as mhr')
                    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                    ->whereColumn('mhr.model_id', 'o.created_by')
                    ->where('mhr.model_type', User::class)
                    ->whereIn('r.name', ['sales', 'sales_manager']);
            });

        $companyId = $this->companyId();
        if ($companyId > 0 && Schema::hasColumn('crm_orders', 'company_id')) {
            $query->where(function ($q) use ($companyId): void {
                $q->where('o.company_id', $companyId)->orWhereNull('o.company_id');
            });
        }

        if ($permissions['is_sales']) {
            $query->where('o.created_by', (int) $user->id);
        }

        if (! empty($filters['sales_id'])) {
            $query->where('o.created_by', (int) $filters['sales_id']);
        }

        if (! empty($filters['q'])) {
            $keyword = trim((string) $filters['q']);
            $query->where(function ($q) use ($keyword): void {
                $q->where('o.order_code', 'like', "%{$keyword}%")
                    ->orWhere('c.name', 'like', "%{$keyword}%")
                    ->orWhere('u.name', 'like', "%{$keyword}%")
                    ->orWhere('items.product_names', 'like', "%{$keyword}%");
            });
        }

        return $query->orderByDesc(DB::raw('COALESCE(pay.last_payment_date, o.order_date, DATE(o.created_at))'))->get();
    }

    public function savePolicy(string $month, array $payload, $user): object
    {
        $policy = $this->policy($month);
        $permissions = $this->permissions($user);

        if (! $permissions['manage']) {
            abort(403, 'Bạn không có quyền sửa chính sách tháng.');
        }
        if (in_array((string) $policy->status, ['approved', 'locked'], true)) {
            throw new RuntimeException('Chính sách đã duyệt hoặc đã khóa. Hãy trả về nháp/mở khóa trước khi sửa.');
        }

        $before = (array) $policy;
        $data = [
            'name' => trim((string) ($payload['name'] ?? 'Chính sách thu nhập '.$month)),
            'sales_base_salary' => $this->money($payload['sales_base_salary'] ?? 0),
            'sales_responsibility_allowance' => $this->money($payload['sales_responsibility_allowance'] ?? 0),
            'sales_travel_allowance' => $this->money($payload['sales_travel_allowance'] ?? 0),
            'sales_target_revenue' => $this->money($payload['sales_target_revenue'] ?? 0),
            'sales_threshold_percent' => $this->percent($payload['sales_threshold_percent'] ?? 80),
            'lead_rate_percent' => $this->percent($payload['lead_rate_percent'] ?? 0.5),
            'member_rate_percent' => $this->percent($payload['member_rate_percent'] ?? 1),
            'retail_rate_percent' => $this->percent($payload['retail_rate_percent'] ?? 1),
            'other_rate_percent' => $this->percent($payload['other_rate_percent'] ?? 1),
            'project_rate_percent' => $this->percent($payload['project_rate_percent'] ?? 3),
            'over_target_rate_percent' => $this->percent($payload['over_target_rate_percent'] ?? 1),
            'new_dealer_bonus' => $this->money($payload['new_dealer_bonus'] ?? 0),
            'new_dealer_min_order' => $this->money($payload['new_dealer_min_order'] ?? 0),
            'new_dealer_min_products' => max(0, (int) ($payload['new_dealer_min_products'] ?? 2)),
            'leader_base_salary' => $this->money($payload['leader_base_salary'] ?? 0),
            'leader_management_allowance' => $this->money($payload['leader_management_allowance'] ?? 0),
            'leader_travel_mode' => in_array(($payload['leader_travel_mode'] ?? ''), ['fixed', 'percent'], true) ? $payload['leader_travel_mode'] : 'percent',
            'leader_travel_value' => $this->money($payload['leader_travel_value'] ?? 0),
            'leader_target_revenue' => $this->money($payload['leader_target_revenue'] ?? 0),
            'leader_threshold_percent' => $this->percent($payload['leader_threshold_percent'] ?? 80),
            'leader_team_rate_percent' => $this->percent($payload['leader_team_rate_percent'] ?? 0.3),
            'leader_over_target_rate_percent' => $this->percent($payload['leader_over_target_rate_percent'] ?? 1),
            'leader_personal_commission' => ! empty($payload['leader_personal_commission']),
            'calculate_on' => in_array(($payload['calculate_on'] ?? ''), ['paid_before_vat', 'paid_after_vat', 'order_before_vat'], true) ? $payload['calculate_on'] : 'paid_before_vat',
            'kpi_calculate_on' => in_array(($payload['kpi_calculate_on'] ?? ''), ['order_revenue', 'paid_in_period', 'commission_base'], true) ? $payload['kpi_calculate_on'] : 'commission_base',
            'include_partial_payment' => ! empty($payload['include_partial_payment']),
            'hold_if_debt' => ! empty($payload['hold_if_debt']),
            'require_approved_policy' => ! empty($payload['require_approved_policy']),
            'note' => trim((string) ($payload['note'] ?? '')),
            'version' => ((int) $policy->version) + 1,
            'updated_at' => now(),
        ];

        DB::table('crm_compensation_months')->where('id', $policy->id)->update($data);
        $this->clearSnapshot($month);
        $after = (array) DB::table('crm_compensation_months')->where('id', $policy->id)->first();
        $this->audit($month, 'policy.updated', 'crm_compensation_months', (int) $policy->id, $before, $after);

        return (object) $after;
    }

    public function saveStaff(string $month, array $rows, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['manage']) {
            abort(403);
        }
        if (in_array((string) $policy->status, ['approved', 'locked'], true)) {
            throw new RuntimeException('Không thể sửa nhân sự khi chính sách đã duyệt/khóa.');
        }

        $companyId = $this->companyId();
        $before = DB::table('crm_compensation_staff_settings')
            ->where('company_id', $companyId)->where('period_month', $month)->get()->toArray();

        DB::transaction(function () use ($rows, $companyId, $month): void {
            foreach ($rows as $userId => $row) {
                $userId = (int) $userId;
                if ($userId <= 0) {
                    continue;
                }

                $data = [
                    'company_id' => $companyId,
                    'period_month' => $month,
                    'user_id' => $userId,
                    'role_type' => in_array(($row['role_type'] ?? ''), ['sale', 'leader'], true) ? $row['role_type'] : 'sale',
                    'manager_id' => ! empty($row['manager_id']) ? (int) $row['manager_id'] : null,
                    'base_salary' => $this->money($row['base_salary'] ?? 0),
                    'responsibility_allowance' => $this->money($row['responsibility_allowance'] ?? 0),
                    'travel_allowance' => $this->money($row['travel_allowance'] ?? 0),
                    'target_revenue' => $this->money($row['target_revenue'] ?? 0),
                    'kpi_percent' => min(100, $this->percent($row['kpi_percent'] ?? 100)),
                    'went_to_market' => ! empty($row['went_to_market']),
                    'is_active' => ! empty($row['is_active']),
                    'note' => trim((string) ($row['note'] ?? '')),
                    'updated_at' => now(),
                ];

                DB::table('crm_compensation_staff_settings')->updateOrInsert(
                    ['company_id' => $companyId, 'period_month' => $month, 'user_id' => $userId],
                    $data + ['created_at' => now()]
                );
            }
        });

        $this->clearSnapshot($month);
        $after = DB::table('crm_compensation_staff_settings')
            ->where('company_id', $companyId)->where('period_month', $month)->get()->toArray();
        $this->audit($month, 'staff.updated', 'crm_compensation_staff_settings', null, $before, $after);
    }

    public function saveOverride(string $month, int $orderId, array $payload, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['manage']) {
            abort(403);
        }
        if (($policy->status ?? '') === 'locked') {
            throw new RuntimeException('Tháng đã khóa, không thể điều chỉnh đơn hàng.');
        }

        $companyId = $this->companyId();
        $before = DB::table('crm_compensation_order_overrides')
            ->where('company_id', $companyId)->where('period_month', $month)->where('order_id', $orderId)->first();

        $data = [
            'company_id' => $companyId,
            'period_month' => $month,
            'order_id' => $orderId,
            'sales_id' => ! empty($payload['sales_id']) ? (int) $payload['sales_id'] : null,
            'order_type' => in_array(($payload['order_type'] ?? ''), ['distribution', 'project'], true) ? $payload['order_type'] : 'distribution',
            'customer_group' => $this->normalizeCustomerGroup((string) ($payload['customer_group'] ?? 'other')),
            'rate_override_percent' => ($payload['rate_override_percent'] ?? '') !== '' ? $this->percent($payload['rate_override_percent']) : null,
            'commission_override_amount' => ($payload['commission_override_amount'] ?? '') !== '' ? $this->money($payload['commission_override_amount']) : null,
            'is_new_dealer' => ! empty($payload['is_new_dealer']),
            'new_dealer_bonus_override' => ($payload['new_dealer_bonus_override'] ?? '') !== '' ? $this->money($payload['new_dealer_bonus_override']) : null,
            'is_excluded' => ! empty($payload['is_excluded']),
            'reason' => trim((string) ($payload['reason'] ?? '')),
            'created_by' => auth()->id(),
            'updated_at' => now(),
        ];

        DB::table('crm_compensation_order_overrides')->updateOrInsert(
            ['company_id' => $companyId, 'period_month' => $month, 'order_id' => $orderId],
            $data + ['created_at' => now()]
        );

        $this->clearSnapshot($month);
        $after = DB::table('crm_compensation_order_overrides')
            ->where('company_id', $companyId)->where('period_month', $month)->where('order_id', $orderId)->first();
        $this->audit($month, 'override.saved', 'crm_compensation_order_overrides', $orderId, $before ? (array) $before : null, (array) $after, $data['reason']);
    }

    public function deleteOverride(string $month, int $orderId, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['manage']) {
            abort(403);
        }
        if (($policy->status ?? '') === 'locked') {
            throw new RuntimeException('Tháng đã khóa.');
        }

        $companyId = $this->companyId();
        $before = DB::table('crm_compensation_order_overrides')
            ->where('company_id', $companyId)->where('period_month', $month)->where('order_id', $orderId)->first();
        DB::table('crm_compensation_order_overrides')
            ->where('company_id', $companyId)->where('period_month', $month)->where('order_id', $orderId)->delete();
        $this->clearSnapshot($month);
        $this->audit($month, 'override.deleted', 'crm_compensation_order_overrides', $orderId, $before ? (array) $before : null, null);
    }

    public function addAdjustment(string $month, array $payload, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['manage']) {
            abort(403);
        }
        if (($policy->status ?? '') === 'locked') {
            throw new RuntimeException('Tháng đã khóa.');
        }

        $type = in_array(($payload['adjustment_type'] ?? ''), ['bonus', 'deduction', 'allowance', 'correction'], true)
            ? $payload['adjustment_type'] : 'bonus';
        $id = DB::table('crm_compensation_adjustments')->insertGetId([
            'company_id' => $this->companyId(),
            'period_month' => $month,
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'adjustment_type' => $type,
            'amount' => abs($this->money($payload['amount'] ?? 0)),
            'title' => trim((string) ($payload['title'] ?? 'Điều chỉnh thu nhập')),
            'reason' => trim((string) ($payload['reason'] ?? '')),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->clearSnapshot($month);
        $this->audit($month, 'adjustment.created', 'crm_compensation_adjustments', $id, null, $payload);
    }

    public function deleteAdjustment(string $month, int $id, $user): void
    {
        if (! $this->permissions($user)['manage']) {
            abort(403);
        }
        $policy = $this->policy($month);
        if (($policy->status ?? '') === 'locked') {
            throw new RuntimeException('Tháng đã khóa.');
        }

        $row = DB::table('crm_compensation_adjustments')
            ->where('company_id', $this->companyId())->where('period_month', $month)->where('id', $id)->first();
        DB::table('crm_compensation_adjustments')->where('id', $id)->delete();
        $this->clearSnapshot($month);
        $this->audit($month, 'adjustment.deleted', 'crm_compensation_adjustments', $id, $row ? (array) $row : null, null);
    }

    public function copyPrevious(string $month, $user): object
    {
        if (! $this->permissions($user)['manage']) {
            abort(403);
        }

        $month = $this->normalizeMonth($month);
        $companyId = $this->companyId();
        $current = $this->policy($month);
        if (($current->status ?? '') !== 'draft' || (int) $current->version > 1) {
            throw new RuntimeException('Chỉ sao chép khi tháng hiện tại còn là bản nháp mới.');
        }

        $previousMonth = Carbon::createFromFormat('Y-m', $month)->subMonth()->format('Y-m');
        $previous = DB::table('crm_compensation_months')
            ->where('company_id', $companyId)->where('period_month', $previousMonth)->first();
        if (! $previous) {
            throw new RuntimeException('Không tìm thấy chính sách tháng trước.');
        }

        $skip = ['id', 'period_month', 'status', 'version', 'created_by', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'locked_by', 'locked_at', 'rejected_by', 'rejected_at', 'rejected_reason', 'created_at', 'updated_at'];
        $data = array_diff_key((array) $previous, array_flip($skip));
        $data['name'] = 'Chính sách thu nhập '.$month.' (sao chép '.$previousMonth.')';
        $data['version'] = 2;
        $data['updated_at'] = now();
        DB::table('crm_compensation_months')->where('id', $current->id)->update($data);

        $previousStaff = DB::table('crm_compensation_staff_settings')
            ->where('company_id', $companyId)->where('period_month', $previousMonth)->get();
        foreach ($previousStaff as $staff) {
            $copy = (array) $staff;
            unset($copy['id']);
            $copy['period_month'] = $month;
            $copy['created_at'] = now();
            $copy['updated_at'] = now();
            DB::table('crm_compensation_staff_settings')->updateOrInsert(
                ['company_id' => $companyId, 'period_month' => $month, 'user_id' => $copy['user_id']],
                $copy
            );
        }

        $this->audit($month, 'policy.copied', 'crm_compensation_months', (int) $current->id, null, ['from' => $previousMonth]);

        return $this->policy($month, false);
    }

    public function submit(string $month, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['manage']) {
            abort(403);
        }
        if (! in_array((string) $policy->status, ['draft'], true)) {
            throw new RuntimeException('Chỉ bản nháp mới được gửi duyệt.');
        }
        $this->updateStatus($policy, 'pending', [
            'submitted_by' => $user->id,
            'submitted_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'rejected_reason' => null,
        ], 'policy.submitted');
    }

    public function approve(string $month, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['approve']) {
            abort(403);
        }
        if ((string) $policy->status !== 'pending') {
            throw new RuntimeException('Chỉ chính sách chờ duyệt mới được phê duyệt.');
        }
        $this->updateStatus($policy, 'approved', [
            'approved_by' => $user->id,
            'approved_at' => now(),
        ], 'policy.approved');
    }

    public function reject(string $month, string $reason, $user): void
    {
        $policy = $this->policy($month);
        if (! ($this->permissions($user)['approve'] || $this->permissions($user)['lock'])) {
            abort(403);
        }
        if (! in_array((string) $policy->status, ['pending', 'approved'], true)) {
            throw new RuntimeException('Trạng thái hiện tại không thể trả về nháp.');
        }
        $this->updateStatus($policy, 'draft', [
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejected_reason' => trim($reason),
            'approved_by' => null,
            'approved_at' => null,
        ], 'policy.rejected', $reason);
    }

    public function lock(string $month, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['lock']) {
            abort(403);
        }
        if ((string) $policy->status !== 'approved') {
            throw new RuntimeException('Chỉ chính sách đã duyệt mới được khóa tháng.');
        }

        $dashboard = $this->dashboard($month, [], $user, true);
        unset($dashboard['permissions'], $dashboard['audit_logs']);
        DB::table('crm_compensation_snapshots')->updateOrInsert(
            ['company_id' => $this->companyId(), 'period_month' => $month, 'snapshot_key' => 'dashboard'],
            [
                'policy_version' => (int) $policy->version,
                'payload' => json_encode($dashboard, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_by' => $user->id,
                'locked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->updateStatus($policy, 'locked', [
            'locked_by' => $user->id,
            'locked_at' => now(),
        ], 'policy.locked');
    }

    public function unlock(string $month, string $reason, $user): void
    {
        $policy = $this->policy($month);
        if (! $this->permissions($user)['lock']) {
            abort(403);
        }
        if ((string) $policy->status !== 'locked') {
            throw new RuntimeException('Tháng chưa khóa.');
        }

        DB::table('crm_compensation_snapshots')
            ->where('company_id', $this->companyId())->where('period_month', $month)->delete();
        $this->updateStatus($policy, 'approved', [
            'locked_by' => null,
            'locked_at' => null,
        ], 'policy.unlocked', $reason);
    }

    private function updateStatus(object $policy, string $status, array $extra, string $action, ?string $note = null): void
    {
        $before = (array) $policy;
        DB::table('crm_compensation_months')->where('id', $policy->id)->update($extra + [
            'status' => $status,
            'updated_at' => now(),
        ]);
        $after = (array) DB::table('crm_compensation_months')->where('id', $policy->id)->first();
        $this->audit((string) $policy->period_month, $action, 'crm_compensation_months', (int) $policy->id, $before, $after, $note);
    }

    public function auditLogs(string $month): Collection
    {
        return DB::table('crm_compensation_audit_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.created_by')
            ->where('l.company_id', $this->companyId())
            ->where('l.period_month', $month)
            ->select('l.*', 'u.name as created_by_name')
            ->orderByDesc('l.id')
            ->limit(100)
            ->get();
    }

    private function audit(string $month, string $action, ?string $subjectType, ?int $subjectId, mixed $before, mixed $after, ?string $note = null): void
    {
        if (! Schema::hasTable('crm_compensation_audit_logs')) {
            return;
        }

        DB::table('crm_compensation_audit_logs')->insert([
            'company_id' => $this->companyId(),
            'period_month' => $month,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'after_data' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'note' => $note,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function clearSnapshot(string $month): void
    {
        DB::table('crm_compensation_snapshots')
            ->where('company_id', $this->companyId())
            ->where('period_month', $month)
            ->delete();
    }

    private function customerRate(string $group, object $policy): float
    {
        return match ($group) {
            'lead' => (float) $policy->lead_rate_percent,
            'member' => (float) $policy->member_rate_percent,
            'retail' => (float) $policy->retail_rate_percent,
            default => (float) $policy->other_rate_percent,
        };
    }

    public function normalizeCustomerGroup(string $group): string
    {
        $group = mb_strtolower(trim($group));

        if (str_contains($group, 'lead') || str_contains($group, 'ads')) {
            return 'lead';
        }
        if (str_contains($group, 'member') || str_contains($group, 'đại lý') || str_contains($group, 'dai ly')) {
            return 'member';
        }
        if (str_contains($group, 'retail') || str_contains($group, 'khách lẻ') || str_contains($group, 'khach le')) {
            return 'retail';
        }

        return 'other';
    }

    public function customerGroupLabel(string $group): string
    {
        return match ($group) {
            'lead' => 'Lead/ADS',
            'member' => 'Member/Đại lý cũ',
            'retail' => 'Khách lẻ',
            default => 'Khác',
        };
    }

    private function signedAdjustment(object $adjustment): float
    {
        $amount = abs((float) $adjustment->amount);

        return (string) $adjustment->adjustment_type === 'deduction' ? -$amount : $amount;
    }

    private function money(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = preg_replace('/[^0-9\-\.]/', '', str_replace(',', '', (string) $value));

        return round((float) ($normalized ?: 0), 2);
    }

    private function percent(mixed $value): float
    {
        return min(100, max(0, round($this->money($value), 4)));
    }
}
