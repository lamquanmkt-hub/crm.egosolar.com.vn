<?php

namespace App\Http\Controllers;

use App\Models\SalesDailyKpi;
use App\Models\User;
use App\Services\Sales\SalesCommissionExcelExporter;
use App\Services\Sales\SalesCommissionScope;
use App\Services\Sales\SalesKpiSettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller quản lý hoa hồng sales và KPI hằng ngày của đội kinh doanh.
 */
class SalesCommissionController extends Controller
{
    /**
     * Khởi tạo controller, inject service cấu hình KPI và exporter hoa hồng.
     */
    public function __construct(
        private readonly SalesKpiSettingsService $kpiSettings,
        private readonly SalesCommissionExcelExporter $commissionExporter,
    ) {}

    private const CACHE_VERSION = 'v20_index_no_cache_no_engine';

    /**
     * Tính khoảng thời gian đầu/cuối theo kỳ (tháng, quý, năm) từ tháng gốc.
     */
    private function periodRange(string $period, string $month): array
    {
        $base = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        return match ($period) {
            'year' => [$base->copy()->startOfYear(), $base->copy()->endOfYear()],
            'quarter' => [$base->copy()->firstOfQuarter(), $base->copy()->lastOfQuarter()],
            default => [$base->copy()->startOfMonth(), $base->copy()->endOfMonth()],
        };
    }

    /**
     * Sinh khóa scope cache theo vai trò của user (sales / manager / all).
     */
    private function scopeKey($user): string
    {
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('sales')) {
                return 'sales_'.$user->id;
            }
            if ($user->hasRole('sales_manager')) {
                return 'manager_'.$user->id;
            }
        }

        return 'all';
    }

    /**
     * Lấy danh sách id nhân viên thuộc team của một sales manager, null nếu không có cột manager_id.
     */
    private function getTeamIdsForManager(int $managerId)
    {
        try {
            if (! Schema::hasColumn('users', 'manager_id')) {
                return null;
            }

            return DB::table('users')
                ->where('manager_id', $managerId)
                ->pluck('id');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Giới hạn query chỉ lấy user có role sales hoặc sales_manager.
     */
    private function onlySalesRolesFilter($query, string $salesColumn = 'sc.sales_user_id')
    {
        return $query->whereExists(function ($sub) use ($salesColumn) {
            $sub->select(DB::raw(1))
                ->from('model_has_roles as mhr')
                ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                ->whereColumn('mhr.model_id', $salesColumn)
                ->where('mhr.model_type', \App\Models\User::class)
                ->whereIn('r.name', ['sales', 'sales_manager']);
        });
    }

    /**
     * Xác định khoảng ngày lọc từ request (all_time, date_from/to hoặc theo kỳ).
     */
    private function resolveDateRange(Request $request): array
    {
        $allTime = (int) $request->get('all_time', 0) === 1;
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if ($allTime) {
            return [null, null];
        }

        if ($dateFrom || $dateTo) {
            $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::minValue();
            $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::maxValue();

            return [$from, $to];
        }

        $month = $request->get('month', now()->format('Y-m'));
        $period = $request->get('period', 'month');

        if (! in_array($period, ['month', 'quarter', 'year'], true)) {
            $period = 'month';
        }

        [$from, $to] = $this->periodRange($period, $month);

        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }

    /**
     * Subquery tính tổng tiền đơn hàng trước VAT theo nhiều phương án cột an toàn.
     */
    private function orderTotalBeforeVatSubquery()
    {
        /*
        |--------------------------------------------------------------------------
        | ULTRA SAFE - Base hoa hồng trước VAT
        |--------------------------------------------------------------------------
        | Không dùng GREATEST().
        | Không gọi cột không tồn tại.
        | Không join crm_product_prices để tránh query nặng/sai OR.
        |
        | Ưu tiên:
        | 1. Cột trước VAT trên crm_order_items nếu có
        | 2. Giá trước VAT trên crm_product_catalog: price_agent / price_retail / price
        | 3. Chia theo VAT % nếu có
        | 4. total_amount - tax_amount nếu order có tax_amount
        | 5. fallback total_amount
        */
        $orderCols = Schema::hasTable('crm_orders') ? Schema::getColumnListing('crm_orders') : [];
        $itemCols = Schema::hasTable('crm_order_items') ? Schema::getColumnListing('crm_order_items') : [];
        $productCols = Schema::hasTable('crm_product_catalog') ? Schema::getColumnListing('crm_product_catalog') : [];

        $hasOrder = fn ($col) => in_array($col, $orderCols, true);
        $hasItem = fn ($col) => in_array($col, $itemCols, true);
        $hasProduct = fn ($col) => in_array($col, $productCols, true);

        $orderTotalCol = null;
        foreach (['total_amount', 'final_amount', 'grand_total', 'total', 'amount'] as $col) {
            if ($hasOrder($col)) {
                $orderTotalCol = $col;
                break;
            }
        }

        $orderTotalExpr = $orderTotalCol ? "COALESCE(o.`{$orderTotalCol}`, 0)" : '0';

        $taxCol = null;
        foreach (['tax_amount', 'vat_amount', 'total_vat', 'total_tax'] as $col) {
            if ($hasOrder($col)) {
                $taxCol = $col;
                break;
            }
        }

        $taxExpr = $taxCol ? "COALESCE(o.`{$taxCol}`, 0)" : '0';

        if (! Schema::hasTable('crm_order_items')) {
            return DB::table('crm_orders as o')
                ->select([
                    DB::raw('o.id as order_id'),
                    DB::raw("
                        CASE
                            WHEN {$taxExpr} > 0 AND {$orderTotalExpr} >= {$taxExpr}
                                THEN {$orderTotalExpr} - {$taxExpr}
                            ELSE {$orderTotalExpr}
                        END as total_before_vat
                    "),
                ]);
        }

        $qtyExpr = $hasItem('quantity')
            ? 'COALESCE(oi.`quantity`, 1)'
            : ($hasItem('qty') ? 'COALESCE(oi.`qty`, 1)' : '1');

        $lineAfterExpr = '0';

        foreach (['line_total', 'total_amount', 'total', 'amount'] as $col) {
            if ($hasItem($col)) {
                $lineAfterExpr = "COALESCE(oi.`{$col}`, 0)";
                break;
            }
        }

        if ($lineAfterExpr === '0') {
            $unitCol = null;
            foreach (['unit_price', 'price', 'sale_price'] as $col) {
                if ($hasItem($col)) {
                    $unitCol = $col;
                    break;
                }
            }

            if ($unitCol) {
                $discountExpr = $hasItem('discount_amount') ? 'COALESCE(oi.`discount_amount`, 0)' : '0';
                $lineAfterExpr = "CASE WHEN ((COALESCE(oi.`{$unitCol}`, 0) * {$qtyExpr}) - {$discountExpr}) > 0 THEN ((COALESCE(oi.`{$unitCol}`, 0) * {$qtyExpr}) - {$discountExpr}) ELSE 0 END";
            }
        }

        $cases = [];

        foreach ([
            'line_total_before_vat',
            'total_before_vat',
            'subtotal_before_vat',
            'before_vat_total',
            'amount_before_vat',
            'net_total',
            'total_ex_vat',
            'total_excluding_vat',
            'total_without_vat',
        ] as $col) {
            if ($hasItem($col)) {
                $cases[] = "WHEN COALESCE(oi.`{$col}`, 0) > 0 THEN COALESCE(oi.`{$col}`, 0)";
            }
        }

        foreach ([
            'unit_price_before_vat',
            'price_before_vat',
            'price_ex_vat',
            'unit_price_ex_vat',
            'price_without_vat',
            'net_unit_price',
            'base_price',
        ] as $col) {
            if ($hasItem($col)) {
                $cases[] = "WHEN COALESCE(oi.`{$col}`, 0) > 0 THEN COALESCE(oi.`{$col}`, 0) * {$qtyExpr}";
            }
        }

        $canJoinProduct = Schema::hasTable('crm_product_catalog') && $hasItem('product_id');

        if ($canJoinProduct) {
            foreach ([
                'price_before_vat',
                'unit_price_before_vat',
                'price_ex_vat',
                'price_without_vat',
                'price_agent',
                'price_retail',
                'price',
            ] as $col) {
                if ($hasProduct($col)) {
                    $cases[] = "WHEN COALESCE(pc.`{$col}`, 0) > 0 THEN COALESCE(pc.`{$col}`, 0) * {$qtyExpr}";
                }
            }
        }

        $itemVatExpr = '0';
        foreach (['vat_percent', 'vat_rate', 'tax_percent', 'tax_rate', 'vat'] as $col) {
            if ($hasItem($col)) {
                $itemVatExpr = "COALESCE(oi.`{$col}`, 0)";
                break;
            }
        }

        $productVatExpr = '0';
        if ($canJoinProduct) {
            foreach (['vat_percent', 'vat_rate', 'tax_percent', 'tax_rate', 'vat'] as $col) {
                if ($hasProduct($col)) {
                    $productVatExpr = "COALESCE(pc.`{$col}`, 0)";
                    break;
                }
            }
        }

        $cases[] = "WHEN {$itemVatExpr} > 0 THEN {$lineAfterExpr} / (1 + ({$itemVatExpr} / 100))";
        $cases[] = "WHEN {$productVatExpr} > 0 THEN {$lineAfterExpr} / (1 + ({$productVatExpr} / 100))";

        $caseSql = 'CASE '.implode("\n", $cases)." ELSE {$lineAfterExpr} END";

        $itemSub = DB::table('crm_order_items as oi');

        if ($canJoinProduct) {
            $itemSub->leftJoin('crm_product_catalog as pc', 'pc.id', '=', 'oi.product_id');
        }

        $itemSub = $itemSub
            ->select([
                'oi.order_id',
                DB::raw("SUM({$caseSql}) as item_before_vat"),
                DB::raw("SUM({$lineAfterExpr}) as item_after_vat"),
            ])
            ->groupBy('oi.order_id');

        return DB::table('crm_orders as o')
            ->leftJoinSub($itemSub, 'x', function ($join) {
                $join->on('x.order_id', '=', 'o.id');
            })
            ->select([
                DB::raw('o.id as order_id'),
                DB::raw("
                    CASE
                        WHEN COALESCE(x.item_before_vat, 0) > 0
                            THEN COALESCE(x.item_before_vat, 0)

                        WHEN {$taxExpr} > 0 AND {$orderTotalExpr} >= {$taxExpr}
                            THEN {$orderTotalExpr} - {$taxExpr}

                        ELSE {$orderTotalExpr}
                    END as total_before_vat
                "),
            ]);
    }

    /**
     * Subquery tổng hợp thanh toán theo đơn (tổng đã thu, ngày thanh toán cuối).
     */
    private function paymentSummarySubquery()
    {
        return DB::table('crm_payments as p')
            ->select([
                'p.order_id',
                DB::raw('SUM(COALESCE(p.amount, 0)) as paid_total'),
                DB::raw('MAX(COALESCE(p.payment_date, p.created_at)) as final_payment_date'),
            ])
            ->groupBy('p.order_id');
    }

    /**
     * Dựng query chính tính hoa hồng: đơn đã thu đủ tiền, base trước VAT, tỷ lệ theo trạng thái khách.
     */
    private function buildQuery(Request $request, $user, ?Carbon $from, ?Carbon $to)
    {
        $paymentSub = $this->paymentSummarySubquery();

        /*
        |--------------------------------------------------------------------------
        | Base hoa hồng = trước VAT
        |--------------------------------------------------------------------------
        | Ưu tiên:
        | 1. crm_product_prices.price theo product_id + price_tier_id
        | 2. Nếu có VAT % trên dòng đơn thì line_total / (1 + VAT%)
        | 3. Nếu có VAT % trên sản phẩm thì line_total / (1 + VAT%)
        | 4. Nếu order có tax_amount thì phân bổ theo tỷ lệ trước/sau VAT
        | 5. Fallback line_total
        */
        $hasProductPrices = Schema::hasTable('crm_product_prices')
            && Schema::hasColumn('crm_product_prices', 'product_id')
            && Schema::hasColumn('crm_product_prices', 'price_tier_id')
            && Schema::hasColumn('crm_product_prices', 'price');

        $priceSub = null;

        if ($hasProductPrices) {
            $priceSub = DB::table('crm_product_prices')
                ->select([
                    'product_id',
                    'price_tier_id',
                    DB::raw('MAX(COALESCE(price, 0)) as price'),
                ])
                ->groupBy('product_id', 'price_tier_id');
        }

        $qtyExpr = 'COALESCE(oi.quantity, 1)';
        $lineAfterExpr = "COALESCE(oi.line_total, (COALESCE(oi.unit_price,0) * {$qtyExpr}) - COALESCE(oi.discount_amount,0))";

        $ppPriceExpr = $hasProductPrices ? 'COALESCE(pp.price,0)' : '0';
        $itemVatExpr = Schema::hasColumn('crm_order_items', 'vat_percent') ? 'COALESCE(oi.vat_percent,0)' : '0';
        $productVatExpr = Schema::hasColumn('crm_product_catalog', 'vat_percent') ? 'COALESCE(pc.vat_percent,0)' : '0';

        $orderBeforeRateExpr = '
            CASE
                WHEN COALESCE(o.tax_amount,0) > 0 AND COALESCE(o.total_amount,0) > 0
                    THEN (COALESCE(o.total_amount,0) - COALESCE(o.tax_amount,0)) / COALESCE(o.total_amount,1)
                ELSE 0
            END
        ';

        $lineBeforeSql = "
            CASE
                WHEN {$ppPriceExpr} > 0
                    THEN {$ppPriceExpr} * {$qtyExpr}

                WHEN {$itemVatExpr} > 0
                    THEN {$lineAfterExpr} / (1 + ({$itemVatExpr} / 100))

                WHEN {$productVatExpr} > 0
                    THEN {$lineAfterExpr} / (1 + ({$productVatExpr} / 100))

                WHEN ({$orderBeforeRateExpr}) > 0
                    THEN {$lineAfterExpr} * ({$orderBeforeRateExpr})

                ELSE {$lineAfterExpr}
            END
        ";

        $baseAmountSql = "COALESCE(SUM({$lineBeforeSql}), 0)";

        $q = DB::table('crm_orders as o')
            ->joinSub($paymentSub, 'pay', function ($join) {
                $join->on('pay.order_id', '=', 'o.id');
            })
            ->leftJoin('crm_leads as l', 'l.id', '=', 'o.lead_id')
            ->leftJoin('crm_customers as c', 'c.id', '=', 'l.customer_id')
            ->join('users as u', 'u.id', '=', 'o.created_by')
            ->leftJoin('companies as co', 'co.id', '=', 'o.company_id')
            ->leftJoin('crm_order_items as oi', 'oi.order_id', '=', 'o.id')
            ->leftJoin('crm_product_catalog as pc', 'pc.id', '=', 'oi.product_id')
            ->where('u.name', '!=', SalesCommissionScope::EXCLUDED_SALES_NAME)
            ->whereNotNull('pay.final_payment_date')
            ->whereRaw('COALESCE(pay.paid_total, 0) >= COALESCE(o.total_amount, 0)');

        if ($hasProductPrices && $priceSub) {
            $q->leftJoinSub($priceSub, 'pp', function ($join) {
                $join->on('pp.product_id', '=', 'oi.product_id')
                    ->on('pp.price_tier_id', '=', 'oi.price_tier_id');
            });
        }

        $q = $this->onlySalesRolesFilter($q, 'o.created_by');

        if ($from && $to) {
            $q->whereBetween('pay.final_payment_date', [
                $from->format('Y-m-d H:i:s'),
                $to->format('Y-m-d H:i:s'),
            ]);
        }

        if ($request->filled('customer_status')) {
            $q->where('c.customer_status', $request->get('customer_status'));
        }

        if ($request->filled('sales_id')) {
            $q->where('o.created_by', (int) $request->get('sales_id'));
        }

        if ($request->filled('q')) {
            $kw = trim((string) $request->get('q'));
            $q->where(function ($qq) use ($kw) {
                $qq->where('o.order_code', 'like', "%{$kw}%")
                    ->orWhere('c.name', 'like', "%{$kw}%")
                    ->orWhere('u.name', 'like', "%{$kw}%");
            });
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('sales')) {
            $q->where('o.created_by', $user->id);
        } elseif (method_exists($user, 'hasRole') && $user->hasRole('sales_manager')) {
            $teamIds = $this->getTeamIdsForManager($user->id);
            if ($teamIds && $teamIds->count() > 0) {
                $q->whereIn('o.created_by', $teamIds);
            }
        }

        $rateCase = "
            CASE
                WHEN c.customer_status = 'lead'   THEN 0.5
                WHEN c.customer_status = 'member' THEN 1
                WHEN c.customer_status = 'retail' THEN 2
                ELSE 0
            END
        ";

        $q->select([
            DB::raw('o.id as id'),
            DB::raw('o.id as order_id'),
            DB::raw('l.customer_id as customer_id'),
            DB::raw('o.created_by as sales_user_id'),
            DB::raw("'auto' as status"),
            'o.order_code',
            DB::raw('pay.final_payment_date as completed_date'),
            DB::raw('pay.paid_total as paid_total'),
            DB::raw("COALESCE(c.name, '-') as customer_name"),
            DB::raw("COALESCE(c.customer_status, '') as customer_status"),
            'u.name as sales_name',
            DB::raw("COALESCE(co.name, '-') as company_name"),
        ])
            ->selectRaw("{$baseAmountSql} as total_amount")
            ->selectRaw("GROUP_CONCAT(DISTINCT pc.barcode ORDER BY pc.barcode SEPARATOR ', ') as product_codes")
            ->selectRaw("GROUP_CONCAT(DISTINCT pc.name ORDER BY pc.name SEPARATOR ', ') as product_names")
            ->selectRaw("{$rateCase} as rate_percent")
            ->selectRaw("(({$baseAmountSql}) * ({$rateCase}) / 100) as commission_calc")
            ->groupBy(
                'o.id',
                'l.customer_id',
                'o.created_by',
                'o.order_code',
                'c.name',
                'c.customer_status',
                'u.name',
                'co.name',
                'pay.final_payment_date',
                'pay.paid_total'
            );

        if ($request->filled('min_commission')) {
            $min = (float) $request->get('min_commission');
            $q->havingRaw("(({$baseAmountSql}) * ({$rateCase}) / 100) >= ?", [$min]);
        }

        return $q;
    }

    /**
     * Trang danh sách hoa hồng sales theo kỳ, kèm tổng hợp, xếp hạng và chính sách.
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $period = $request->get('period', 'month');
        if (! in_array($period, ['month', 'quarter', 'year'], true)) {
            $period = 'month';
        }

        $month = $request->get('month', now()->format('Y-m'));
        [$from, $to] = $this->resolveDateRange($request);

        /*
        |--------------------------------------------------------------------------
        | FAST SAFE INDEX
        |--------------------------------------------------------------------------
        | debug=1 đã load được vì chỉ chạy buildQuery().
        | Trang thường trước đó bị kẹt vì Cache::store('redis') + applyDashboard()
        | + recalculateRows từng đơn. Bản này bỏ hẳn đoạn đó để trang load ổn định.
        */
        $rows = $this->buildQuery($request, $user, $from, $to)
            ->orderByDesc('completed_date')
            ->get()
            ->map(function ($row) {
                $row->total_amount = (float) ($row->total_amount ?? 0);
                $row->paid_total = (float) ($row->paid_total ?? 0);
                $row->commission_calc = (float) ($row->commission_calc ?? 0);
                $row->rate_percent = (float) ($row->rate_percent ?? 0);

                // Các field view có thể đang gọi sau khi engine recalculate
                $row->revenue_before_vat = $row->total_amount;
                $row->revenue_after_vat = $row->paid_total;
                $row->fifo_cost = (float) ($row->fifo_cost ?? 0);
                $row->gross_profit = (float) ($row->gross_profit ?? 0);
                $row->commission_source = $row->commission_source ?? 'Base trước VAT';
                $row->commission_breakdown = $row->commission_breakdown ?? 'Tính nhanh theo doanh thu trước VAT';

                return $row;
            });

        if ($request->filled('min_commission')) {
            $min = (float) $request->get('min_commission');
            $rows = $rows->filter(fn ($row) => (float) ($row->commission_calc ?? 0) >= $min)->values();
        }

        if ((int) $request->get('debug', 0) === 1) {
            return response()->json([
                'from' => $from?->format('Y-m-d H:i:s'),
                'to' => $to?->format('Y-m-d H:i:s'),
                'count' => $rows->count(),
                'totalRevenue' => (float) $rows->sum('total_amount'),
                'totalCommission' => (float) $rows->sum('commission_calc'),
                'rows' => $rows,
            ]);
        }

        $totalRevenue = (float) $rows->sum('total_amount');
        $totalCommission = (float) $rows->sum('commission_calc');
        $totalOrders = (int) $rows->count();
        $uniqueSales = (int) $rows->pluck('sales_user_id')->unique()->count();

        $ranking = $rows->groupBy('sales_user_id')->map(function ($g) {
            return [
                'sales_user_id' => $g->first()->sales_user_id,
                'sales_name' => $g->first()->sales_name,
                'orders' => $g->count(),
                'revenue' => (float) $g->sum('total_amount'),
                'commission' => (float) $g->sum('commission_calc'),
            ];
        })->sortByDesc('commission')->values()->take(10);

        /*
        |--------------------------------------------------------------------------
        | Policy/rules nhẹ cho sidebar cấu hình
        |--------------------------------------------------------------------------
        | Không gọi CommissionEngineService để tránh ensureSchema/recalculate nặng.
        */
        $defaultPolicy = (object) [
            'period_month' => $month,
            'project_rate_percent' => 4,
            'trade_rate_percent' => 1,
            'panel_fixed_amount' => 15000,
            'only_paid' => 1,
            'only_shipped' => 0,
            'only_completed' => 0,
            'hold_if_debt' => 1,
            'note' => null,
        ];

        $policy = $defaultPolicy;

        if (\Illuminate\Support\Facades\Schema::hasTable('crm_commission_policies')) {
            $policyRow = DB::table('crm_commission_policies')
                ->where('period_month', $month)
                ->orderByDesc('id')
                ->first();

            if ($policyRow) {
                $policy = (object) array_merge((array) $defaultPolicy, (array) $policyRow);
            }
        }

        $rules = collect();

        if (\Illuminate\Support\Facades\Schema::hasTable('crm_commission_rules') && isset($policy->id)) {
            $rules = DB::table('crm_commission_rules')
                ->where('policy_id', (int) $policy->id)
                ->where('is_active', 1)
                ->orderBy('priority')
                ->orderBy('id')
                ->get();
        }

        $commissionRuleStats = [
            'project' => (int) $rules->where('commission_type', 'project')->count(),
            'trade_product' => (int) $rules->where('commission_type', 'trade_product')->count(),
            'solar_panel' => (int) $rules->where('commission_type', 'solar_panel')->count(),
            'total' => (int) $rules->count(),
        ];

        $salesOptions = DB::table('users')
            ->select('users.id', 'users.name')
            ->where('users.name', '!=', SalesCommissionScope::EXCLUDED_SALES_NAME)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('model_has_roles as mhr')
                    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
                    ->whereColumn('mhr.model_id', 'users.id')
                    ->where('mhr.model_type', \App\Models\User::class)
                    ->whereIn('r.name', ['sales', 'sales_manager']);
            })
            ->orderBy('users.name')
            ->get();

        return view('sales.commissions.index', [
            'period' => $period,
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'totalRevenue' => $totalRevenue,
            'totalCommission' => $totalCommission,
            'totalOrders' => $totalOrders,
            'uniqueSales' => $uniqueSales,
            'ranking' => $ranking,
            'salesOptions' => $salesOptions,
            'salesId' => $request->get('sales_id'),
            'policy' => $policy,
            'rules' => $rules,
            'commissionRuleStats' => $commissionRuleStats,
        ]);
    }

    /**
     * Xuất danh sách hoa hồng ra file Excel (dựng file trong SalesCommissionExcelExporter).
     */
    public function exportExcel(Request $request)
    {
        return $this->commissionExporter->download(
            (string) $request->get('month', ''),
            (int) $request->get('sales_id', 0),
        );
    }

    /**
     * Xuất danh sách hoa hồng ra file PDF.
     */
    public function exportPdf(Request $request)
    {
        $user = auth()->user();
        [$from, $to] = $this->resolveDateRange($request);

        $rows = $this->buildQuery($request, $user, $from, $to)
            ->orderByDesc('completed_date')
            ->get();

        $engine = app(\App\Services\CommissionEngineService::class);
        $engine->ensureSchema();
        $month = $request->get('month', now()->format('Y-m'));
        $policy = $engine->currentPolicy($month);
        $rules = $engine->rulesForPolicy((int) $policy->id);
        $rows = $engine->recalculateRows($rows, $policy, $rules);

        $totalCommission = (float) $rows->sum('commission_calc');
        $totalOrders = (int) $rows->count();
        $period = $request->get('period', 'month');
        $generatedAt = now();

        $pdf = Pdf::loadView('sales.commissions.pdf', [
            'rows' => $rows,
            'totalCommission' => $totalCommission,
            'totalOrders' => $totalOrders,
            'from' => $from,
            'to' => $to,
            'period' => $period,
            'generatedAt' => $generatedAt,
        ])
            ->setPaper('a4', 'portrait');

        return $pdf->download('hoa_hong_'.now()->format('Ymd_His').'.pdf');
    }

    /**
     * Trang cấu hình chính sách hoa hồng theo tháng (hỗ trợ sao chép tháng trước).
     */
    public function commissionSettings(Request $request)
    {
        $month = $request->get('month', now()->format('Y-m'));
        $engine = app(\App\Services\CommissionEngineService::class);

        if ((int) $request->get('copy_previous', 0) === 1) {
            $engine->copyPreviousMonth($month);

            return redirect()
                ->route('sales.commissions.settings', ['month' => $month])
                ->with('success', 'Đã sao chép chính sách từ tháng trước.');
        }

        return view('sales.commissions.settings', $engine->settingsViewData($month));
    }

    /**
     * Lưu chính sách hoa hồng và xóa cache.
     */
    public function commissionSettingsSave(Request $request)
    {
        $engine = app(\App\Services\CommissionEngineService::class);
        $month = $engine->saveSettings($request);

        \Illuminate\Support\Facades\Cache::flush();

        return redirect()
            ->route('sales.commissions.settings', ['month' => $month])
            ->with('success', 'Đã lưu chính sách hoa hồng tháng '.$month.'.');
    }

    /**
     * Tính kết quả KPI ngày: số mục thiếu, tiền phạt, phần trăm hoàn thành, trạng thái.
     */
    private function calculateDailyKpi(array $payload): array
    {
        $settings = $this->kpiSettings->salesKpiSettings();

        $postsCount = max(0, (int) ($payload['posts_count'] ?? 0));
        $callsAnswered = max(0, (int) ($payload['calls_answered'] ?? 0));
        $companyDataCalled = max(0, (int) ($payload['company_data_called'] ?? 0));
        $followedUpAllPrevious = (bool) ($payload['followed_up_all_previous'] ?? false);

        $checks = [];

        if ($this->kpiSettings->kpiSettingEnabled($settings, 'enable_posts')) {
            $checks['posts'] = $postsCount >= (int) $settings['posts_target'];
        }

        if ($this->kpiSettings->kpiSettingEnabled($settings, 'enable_calls')) {
            $checks['calls'] = $callsAnswered >= (int) $settings['calls_target'];
        }

        if ($this->kpiSettings->kpiSettingEnabled($settings, 'enable_company_data')) {
            $checks['company_data'] = $companyDataCalled >= (int) $settings['company_data_target'];
        }

        if ($this->kpiSettings->kpiSettingEnabled($settings, 'enable_follow_up')) {
            $checks['follow_up'] = $followedUpAllPrevious;
        }

        $extraMetrics = $this->kpiSettings->normalizeExtraMetrics(is_array($payload['extra_metrics'] ?? null) ? $payload['extra_metrics'] : []);

        foreach ($this->kpiSettings->salesKpiExtraMetricDefinitions() as $module) {
            if (! $this->kpiSettings->kpiSettingEnabled($settings, $module['enabled_key'])) {
                continue;
            }

            $metricKey = $module['metric_key'];
            $targetKey = $module['target_key'];

            $value = (int) ($extraMetrics[$metricKey] ?? 0);
            $target = (int) ($settings[$targetKey] ?? 0);

            $checks[$metricKey] = $value >= $target;
        }

        $passed = collect($checks)->filter()->count();
        $totalChecks = max(1, count($checks));
        $missing = count($checks) === 0 ? 0 : ($totalChecks - $passed);

        $completionPercent = count($checks) === 0 ? 100 : round(($passed / $totalChecks) * 100, 2);
        $penaltyAmount = $this->kpiSettings->kpiSettingEnabled($settings, 'enable_penalty')
            ? $missing * (int) $settings['penalty_per_missing']
            : 0;

        $status = match (true) {
            $missing === 0 => 'completed',
            $completionPercent >= 75 => 'warning',
            default => 'incomplete',
        };

        return [
            'posts_count' => $postsCount,
            'calls_answered' => $callsAnswered,
            'company_data_called' => $companyDataCalled,
            'followed_up_all_previous' => $followedUpAllPrevious,
            'missing_kpi_count' => $missing,
            'penalty_amount' => $penaltyAmount,
            'completion_percent' => $completionPercent,
            'status' => $status,
            'active_checks_count' => count($checks),
        ];
    }

    /**
     * Danh sách user sales/sales_manager cho dropdown, loại trừ tài khoản hợp tác/cộng tác.
     */
    private function salesUserOptions()
    {
        $excludedKeywords = [
            'hợp tác',
            'hop tac',
            'hop_tac',
            'cộng tác',
            'cong tac',
            'cong_tac',
            'collaborator',
            'partner',
        ];

        $roleSub = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->select(
                DB::raw('mhr.model_id as user_id'),
                DB::raw("GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') as role_names"),
                DB::raw("MIN(CASE WHEN r.name = 'sales_manager' THEN 0 ELSE 1 END) as role_sort")
            )
            ->where('mhr.model_type', \App\Models\User::class)
            ->whereIn('r.name', ['sales_manager', 'sales'])
            ->groupBy('mhr.model_id');

        $rows = DB::table('users')
            ->joinSub($roleSub, 'rr', function ($join) {
                $join->on('rr.user_id', '=', 'users.id');
            })
            ->select('users.*', 'rr.role_names', 'rr.role_sort')
            ->orderBy('rr.role_sort')
            ->orderBy('users.name')
            ->get();

        return $rows
            ->reject(function ($user) use ($excludedKeywords) {
                $text = implode(' ', array_map(function ($value) {
                    if (is_null($value)) {
                        return '';
                    }

                    if (is_scalar($value)) {
                        return (string) $value;
                    }

                    return '';
                }, (array) $user));

                $haystack = function_exists('mb_strtolower')
                    ? mb_strtolower($text)
                    : strtolower($text);

                foreach ($excludedKeywords as $keyword) {
                    $needle = function_exists('mb_strtolower')
                        ? mb_strtolower($keyword)
                        : strtolower($keyword);

                    if ($needle !== '' && strpos($haystack, $needle) !== false) {
                        return true;
                    }
                }

                return false;
            })
            ->map(function ($user) {
                return (object) [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role_names' => $user->role_names ?? '',
                    'role_sort' => $user->role_sort ?? 1,
                ];
            })
            ->values();
    }

    /**
     * Dashboard KPI sales: tổng quan tháng, theo ngày, lịch sử và trend theo quyền user.
     */
    public function kpiDashboard(Request $request)
    {

        /* EGO_FIX_IS_SALES_ONLY_START */
        $egoKpiUser = auth()->user();
        $egoKpiRoles = [];

        if ($egoKpiUser) {
            foreach (['role', 'type', 'position', 'department'] as $egoField) {
                if (! empty($egoKpiUser->{$egoField})) {
                    $egoKpiRoles[] = mb_strtolower((string) $egoKpiUser->{$egoField});
                }
            }

            if (method_exists($egoKpiUser, 'getRoleNames')) {
                foreach ($egoKpiUser->getRoleNames() as $egoRoleName) {
                    $egoKpiRoles[] = mb_strtolower((string) $egoRoleName);
                }
            }
        }

        $egoKpiRoleText = implode('|', array_unique(array_filter($egoKpiRoles)));

        $egoKpiHasRole = function (array $roles) use ($egoKpiUser, $egoKpiRoleText): bool {
            foreach ($roles as $role) {
                $roleLower = mb_strtolower((string) $role);

                if (str_contains($egoKpiRoleText, $roleLower)) {
                    return true;
                }

                if ($egoKpiUser && method_exists($egoKpiUser, 'hasRole') && $egoKpiUser->hasRole($role)) {
                    return true;
                }

                if ($egoKpiUser && method_exists($egoKpiUser, 'hasAnyRole') && $egoKpiUser->hasAnyRole([$role])) {
                    return true;
                }
            }

            return false;
        };

        $isAdmin = $isAdmin ?? (
            ((int) ($egoKpiUser->is_admin ?? 0) === 1)
            || $egoKpiHasRole(['admin', 'administrator', 'super_admin'])
        );

        $isSalesManager = $isSalesManager ?? $egoKpiHasRole(['sales_manager', 'sales manager', 'truong_phong_sales', 'trưởng phòng sales']);

        $isAccounting = $isAccounting ?? $egoKpiHasRole(['accounting', 'ketoan', 'ke_toan', 'kế toán']);

        $isSalesOnly = $isSalesOnly ?? (
            $egoKpiHasRole(['sales', 'sale', 'kinh_doanh', 'kinh doanh', 'nhan_vien_kinh_doanh'])
            && ! $isAdmin
            && ! $isSalesManager
            && ! $isAccounting
        );
        /* EGO_FIX_IS_SALES_ONLY_END */

        $authUser = auth()->user();
        $isPrivileged = $authUser->hasAnyRole(['admin', 'sales_manager', 'accounting']);
        $isSalesOnly = ! $isPrivileged && $isSalesOnly;

        $workDate = $request->get('work_date', now()->toDateString());
        $month = $request->get('month', now()->format('Y-m'));
        $selectedUserId = $request->filled('user_id') ? (int) $request->get('user_id') : null;

        $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth()->toDateString();
        $monthEnd = Carbon::createFromFormat('Y-m', $month)->endOfMonth()->toDateString();

        $salesOptions = $this->salesUserOptions();

        /*
        |--------------------------------------------------------------------------
        | Base scope theo role
        |--------------------------------------------------------------------------
        */
        $baseQuery = SalesDailyKpi::query()->with('user:id,name');

        if ($isSalesOnly) {
            $baseQuery->where('user_id', $authUser->id);
            $selectedUserId = $authUser->id;
        } elseif ($authUser->hasRole('sales_manager')) {
            $teamIds = $this->getTeamIdsForManager($authUser->id);
            if ($teamIds && $teamIds->count() > 0) {
                $baseQuery->whereIn('user_id', $teamIds);

                if ($selectedUserId && ! $teamIds->contains((int) $selectedUserId)) {
                    $selectedUserId = null;
                }
            } else {
                // Sales manager không có manager_id/team thì xem toàn bộ nhân sự, không ép về chính mình.
                $selectedUserId = $request->filled('user_id') ? $selectedUserId : null;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 1. Tổng quan tháng
        |--------------------------------------------------------------------------
        */
        $monthlyOverview = (clone $baseQuery)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->selectRaw('
            COUNT(DISTINCT user_id) as total_staff,
            COALESCE(SUM(posts_count), 0) as total_posts,
            COALESCE(SUM(calls_answered), 0) as total_calls,
            COALESCE(SUM(company_data_called), 0) as total_company_data,
            COALESCE(SUM(missing_kpi_count), 0) as total_missing_kpi,
            COALESCE(SUM(penalty_amount), 0) as total_penalty,
            COALESCE(AVG(completion_percent), 0) as avg_completion
        ')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 2. Tổng hợp từng nhân sự theo tháng
        |--------------------------------------------------------------------------
        */
        $staffMonthlyRows = (clone $baseQuery)
            ->whereBetween('work_date', [$monthStart, $monthEnd])
            ->select(
                'user_id',
                DB::raw('SUM(posts_count) as total_posts'),
                DB::raw('SUM(calls_answered) as total_calls'),
                DB::raw('SUM(company_data_called) as total_company_data'),
                DB::raw('SUM(missing_kpi_count) as total_missing_kpi'),
                DB::raw('SUM(penalty_amount) as total_penalty'),
                DB::raw('AVG(completion_percent) as avg_completion'),
                DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_days"),
                DB::raw("SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warning_days"),
                DB::raw("SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete_days"),
                DB::raw('COUNT(*) as working_days')
            )
            ->groupBy('user_id')
            ->get()
            ->sortByDesc('avg_completion')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | 3. Tổng quan theo ngày đang lọc
        |--------------------------------------------------------------------------
        */
        $dailySummary = (clone $baseQuery)
            ->whereDate('work_date', $workDate)
            ->selectRaw('
            COUNT(*) as total_rows,
            COALESCE(SUM(posts_count), 0) as total_posts,
            COALESCE(SUM(calls_answered), 0) as total_calls,
            COALESCE(SUM(company_data_called), 0) as total_company_data,
            COALESCE(SUM(missing_kpi_count), 0) as total_missing_kpi,
            COALESCE(SUM(penalty_amount), 0) as total_penalty,
            COALESCE(AVG(completion_percent), 0) as avg_completion
        ')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | 4. Danh sách team theo ngày
        |--------------------------------------------------------------------------
        */
        $dailyRowsQuery = (clone $baseQuery)
            ->whereDate('work_date', $workDate);

        if ($selectedUserId) {
            $dailyRowsQuery->where('user_id', $selectedUserId);
        }

        $dailyRows = $dailyRowsQuery
            ->orderByDesc('completion_percent')
            ->orderBy('user_id')
            ->paginate(20)
            ->withQueryString();

        if ($isPrivileged && ! $request->filled('user_id')) {
            // Privileged mặc định xem tất cả nhân sự.
            $selectedUserId = null;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. User được chọn / snapshot hiện tại
        |--------------------------------------------------------------------------
        */
        if ($isSalesOnly) {
            // Sales thường chỉ được xem / nhập KPI của chính mình
            $selectedUserId = (int) $authUser->id;
        } elseif ($selectedUserId) {
            // Admin / sales_manager chỉ focus user khi thật sự chọn user_id
            $allowedIds = $salesOptions->pluck('id')->map(fn ($id) => (int) $id);

            if (! $allowedIds->contains((int) $selectedUserId)) {
                $selectedUserId = null;
            }
        } else {
            // Admin / sales_manager mặc định xem toàn bộ nhân sự
            $selectedUserId = null;
        }

        $selectedUser = $selectedUserId ? User::find($selectedUserId) : null;

        if ($selectedUserId) {
            $currentEntry = SalesDailyKpi::firstOrNew([
                'user_id' => $selectedUserId,
                'work_date' => $workDate,
            ]);

            $preview = $this->calculateDailyKpi([
                'posts_count' => $currentEntry->posts_count ?? 0,
                'calls_answered' => $currentEntry->calls_answered ?? 0,
                'company_data_called' => $currentEntry->company_data_called ?? 0,
                'followed_up_all_previous' => $currentEntry->followed_up_all_previous ?? false,
                'extra_metrics' => $this->kpiSettings->decodeExtraMetrics($currentEntry),
            ]);
        } else {
            $currentEntry = new SalesDailyKpi;

            $preview = [
                'posts_count' => 0,
                'calls_answered' => 0,
                'company_data_called' => 0,
                'followed_up_all_previous' => false,
                'missing_kpi_count' => (int) ($dailySummary->total_missing_kpi ?? 0),
                'penalty_amount' => (int) ($dailySummary->total_penalty ?? 0),
                'completion_percent' => round((float) ($dailySummary->avg_completion ?? 0), 2),
                'status' => 'team',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | 6. Lịch sử gần đây
        |--------------------------------------------------------------------------
        */
        $history = (clone $baseQuery)
            ->when($selectedUserId, function ($q) use ($selectedUserId) {
                $q->where('user_id', $selectedUserId);
            })
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(12, ['*'], 'history_page')
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | 7. Trend 7 ngày của user đang chọn
        |--------------------------------------------------------------------------
        */
        $latestRows = $selectedUserId
            ? SalesDailyKpi::query()
                ->with('user:id,name')
                ->where('user_id', $selectedUserId)
                ->latest('work_date')
                ->limit(7)
                ->get()
                ->sortBy('work_date')
                ->values()
            : collect();

        $extraMetrics = [];

        return view('sales.kpi.index', [
            'authUser' => $authUser,
            'selectedUser' => $selectedUser,
            'selectedUserId' => $selectedUserId,
            'workDate' => $workDate,
            'month' => $month,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'currentEntry' => $currentEntry,
            'preview' => $preview,
            'history' => $history,
            'latestRows' => $latestRows,
            'salesOptions' => $salesOptions,
            'isPrivileged' => $isPrivileged,
            'monthlyOverview' => $monthlyOverview,
            'staffMonthlyRows' => $staffMonthlyRows,
            'dailySummary' => $dailySummary,
            'dailyRows' => $dailyRows,
            'targets' => $this->kpiSettings->salesKpiTargets(),
            'kpiExtraModules' => $this->kpiSettings->salesKpiExtraMetricDefinitions(),
            'extraMetrics' => $extraMetrics,
        ]);
    }

    /**
     * Form nhập KPI ngày của cá nhân (admin/manager được chọn user khác).
     */
    public function kpiMyForm(Request $request)
    {
        $authUser = auth()->user();
        $canSelectSalesUser = method_exists($authUser, 'hasAnyRole')
            && $authUser->hasAnyRole(['admin', 'sales_manager']);

        $workDate = $request->get('work_date', now()->toDateString());

        $salesOptions = $canSelectSalesUser
            ? $this->salesUserOptions()
            : collect([(object) ['id' => $authUser->id, 'name' => $authUser->name]]);

        $selectedUserId = $canSelectSalesUser
            ? (int) $request->get('user_id', $authUser->id)
            : (int) $authUser->id;

        $selectedOption = $salesOptions->firstWhere('id', $selectedUserId);

        if (! $selectedOption) {
            $selectedOption = $salesOptions->first();
            $selectedUserId = (int) ($selectedOption->id ?? $authUser->id);
        }

        $selectedUser = User::find($selectedUserId) ?: $authUser;

        $currentEntry = SalesDailyKpi::with('postLinks')
            ->firstOrNew([
                'user_id' => $selectedUserId,
                'work_date' => $workDate,
            ]);

        $extraMetrics = $this->kpiSettings->decodeExtraMetrics($currentEntry);

        $preview = $this->calculateDailyKpi([
            'posts_count' => $currentEntry->posts_count ?? 0,
            'calls_answered' => $currentEntry->calls_answered ?? 0,
            'company_data_called' => $currentEntry->company_data_called ?? 0,
            'followed_up_all_previous' => $currentEntry->followed_up_all_previous ?? false,
            'extra_metrics' => $extraMetrics,
        ]);

        $history = SalesDailyKpi::query()
            ->where('user_id', $selectedUserId)
            ->withCount('postLinks')
            ->orderByDesc('work_date')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('sales.kpi.my', [
            'authUser' => $authUser,
            'selectedUser' => $selectedUser,
            'selectedUserId' => $selectedUserId,
            'canSelectSalesUser' => $canSelectSalesUser,
            'salesOptions' => $salesOptions,
            'workDate' => $workDate,
            'currentEntry' => $currentEntry,
            'preview' => $preview,
            'history' => $history,
            'targets' => $this->kpiSettings->salesKpiTargets(),
            'kpiExtraModules' => $this->kpiSettings->salesKpiExtraMetricDefinitions(),
            'extraMetrics' => $extraMetrics,
        ]);
    }

    /**
     * Lưu KPI ngày của sales: validate, tính kết quả, cập nhật chỉ số mở rộng và link bài đăng.
     */
    public function kpiMyStore(Request $request)
    {
        $authUser = auth()->user();
        $canSelectSalesUser = method_exists($authUser, 'hasAnyRole')
            && $authUser->hasAnyRole(['admin', 'sales_manager']);

        $salesOptions = $canSelectSalesUser
            ? $this->salesUserOptions()
            : collect([(object) ['id' => $authUser->id, 'name' => $authUser->name]]);

        $targetUserId = $canSelectSalesUser
            ? (int) $request->input('user_id', $authUser->id)
            : (int) $authUser->id;

        if (! $salesOptions->firstWhere('id', $targetUserId)) {
            $targetUserId = (int) ($salesOptions->first()->id ?? $authUser->id);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'work_date' => ['required', 'date'],
            'posts_count' => ['nullable', 'integer', 'min:0'],
            'calls_answered' => ['nullable', 'integer', 'min:0'],
            'company_data_called' => ['nullable', 'integer', 'min:0'],
            'followed_up_all_previous' => ['nullable', 'in:0,1'],
            'notes' => ['nullable', 'string'],
            'facebook_links_text' => ['nullable', 'string', 'max:20000'],
            'extra_metrics' => ['nullable', 'array'],
            'extra_metrics.*' => ['nullable', 'integer', 'min:0'],
            'post_links' => ['nullable', 'array'],
            'post_links.*' => ['nullable', 'url'],
        ]);

        $validated['posts_count'] = (int) ($validated['posts_count'] ?? 0);
        $validated['calls_answered'] = (int) ($validated['calls_answered'] ?? 0);
        $validated['company_data_called'] = (int) ($validated['company_data_called'] ?? 0);
        $validated['followed_up_all_previous'] = (int) ($validated['followed_up_all_previous'] ?? 0);

        $validated['extra_metrics'] = $this->kpiSettings->normalizeExtraMetrics($validated['extra_metrics'] ?? []);

        $calc = $this->calculateDailyKpi($validated);

        $kpi = SalesDailyKpi::updateOrCreate(
            [
                'user_id' => $targetUserId,
                'work_date' => $validated['work_date'],
            ],
            [
                'posts_count' => $validated['posts_count'],
                'calls_answered' => $validated['calls_answered'],
                'company_data_called' => $validated['company_data_called'],
                'followed_up_all_previous' => (bool) $validated['followed_up_all_previous'],
                'notes' => $validated['notes'] ?? null,
                'missing_kpi_count' => $calc['missing_kpi_count'],
                'penalty_amount' => $calc['penalty_amount'],
                'completion_percent' => $calc['completion_percent'],
                'status' => $calc['status'],
            ]
        );

        if (Schema::hasColumn('sales_daily_kpis', 'extra_metrics')) {
            DB::table('sales_daily_kpis')
                ->where('id', $kpi->id)
                ->update([
                    'extra_metrics' => json_encode($validated['extra_metrics'], JSON_UNESCAPED_UNICODE),
                ]);
        }

        $links = collect(preg_split('/\R+/', (string) ($validated['facebook_links_text'] ?? '')))
            ->map(fn ($url) => trim((string) $url))
            ->filter()
            ->unique()
            ->values();

        $kpi->postLinks()->delete();

        foreach ($links as $url) {
            $kpi->postLinks()->create([
                'post_url' => $url,
            ]);
        }

        return redirect()
            ->route('sales.kpi.my', ['work_date' => $validated['work_date'], 'user_id' => $targetUserId])
            ->with('success', 'Đã lưu KPI của bạn thành công.');
    }

    /**
     * Trang cấu hình KPI sales (module core và module gợi ý).
     */
    public function kpiSettings()
    {
        $settings = $this->kpiSettings->salesKpiSettings();

        $coreModules = [
            ['key' => 'posts', 'enabled_key' => 'enable_posts', 'target_key' => 'posts_target', 'title' => 'Bài đăng group', 'unit' => 'bài/ngày', 'icon' => 'bi-megaphone', 'tone' => 'blue', 'description' => 'Số bài đăng group điện mặt trời mỗi ngày.'],
            ['key' => 'calls', 'enabled_key' => 'enable_calls', 'target_key' => 'calls_target', 'title' => 'Cuộc gọi nghe máy', 'unit' => 'call/ngày', 'icon' => 'bi-telephone-outbound', 'tone' => 'cyan', 'description' => 'Số cuộc gọi khách hàng nghe máy được tính KPI.'],
            ['key' => 'company_data', 'enabled_key' => 'enable_company_data', 'target_key' => 'company_data_target', 'title' => 'Data công ty đã gọi', 'unit' => 'data/ngày', 'icon' => 'bi-database-check', 'tone' => 'violet', 'description' => 'Số data công ty cấp mà sales đã xử lý trong ngày.'],
            ['key' => 'follow_up', 'enabled_key' => 'enable_follow_up', 'target_key' => null, 'title' => 'Follow up khách cũ', 'unit' => 'bắt buộc', 'icon' => 'bi-chat-dots', 'tone' => 'green', 'description' => 'Checklist follow up toàn bộ khách hôm trước.'],
        ];

        $suggestedModules = [
            ['enabled_key' => 'enable_new_leads', 'target_key' => 'new_leads_target', 'title' => 'Lead mới', 'unit' => 'lead/ngày', 'icon' => 'bi-person-plus', 'description' => 'Khách hàng/lead mới tự khai thác hoặc được phân bổ.'],
            ['enabled_key' => 'enable_quotes', 'target_key' => 'quotes_target', 'title' => 'Báo giá gửi khách', 'unit' => 'báo giá/ngày', 'icon' => 'bi-file-earmark-text', 'description' => 'Số báo giá gửi khách trong ngày.'],
            ['enabled_key' => 'enable_customer_care', 'target_key' => 'customer_care_target', 'title' => 'Chăm sóc khách hàng', 'unit' => 'khách/ngày', 'icon' => 'bi-heart', 'description' => 'Số khách được chăm sóc, nhắc lại, hỏi nhu cầu.'],
            ['enabled_key' => 'enable_meetings', 'target_key' => 'meetings_target', 'title' => 'Lịch hẹn / meeting', 'unit' => 'lịch/ngày', 'icon' => 'bi-calendar2-check', 'description' => 'Lịch tư vấn, khảo sát, demo hoặc gặp khách.'],
            ['enabled_key' => 'enable_zalo_messages', 'target_key' => 'zalo_messages_target', 'title' => 'Tin nhắn Zalo', 'unit' => 'tin/ngày', 'icon' => 'bi-send', 'description' => 'Tin nhắn chăm sóc, tư vấn, follow qua Zalo.'],
            ['enabled_key' => 'enable_debt_follow', 'target_key' => 'debt_follow_target', 'title' => 'Follow công nợ', 'unit' => 'case/ngày', 'icon' => 'bi-wallet2', 'description' => 'Theo dõi khách còn công nợ hoặc cần nhắc thanh toán.'],
            ['enabled_key' => 'enable_order_follow', 'target_key' => 'order_follow_target', 'title' => 'Bám đơn hàng', 'unit' => 'đơn/ngày', 'icon' => 'bi-box-seam', 'description' => 'Theo dõi đơn đang xử lý, giao hàng, thanh toán, xuất kho.'],
            ['enabled_key' => 'enable_technical_coordination', 'target_key' => 'technical_coordination_target', 'title' => 'Phối hợp kỹ thuật', 'unit' => 'việc/ngày', 'icon' => 'bi-tools', 'description' => 'Phối hợp khảo sát, kỹ thuật, cấu hình hệ thống.'],
            ['enabled_key' => 'enable_overdue_tasks', 'target_key' => 'overdue_tasks_target', 'title' => 'Task quá hạn tối đa', 'unit' => 'task', 'icon' => 'bi-alarm', 'description' => 'Giới hạn số công việc quá hạn được phép tồn.'],
            ['enabled_key' => 'enable_training', 'target_key' => 'training_target', 'title' => 'Học sản phẩm', 'unit' => 'mục/ngày', 'icon' => 'bi-mortarboard', 'description' => 'Học sản phẩm, chính sách, kịch bản tư vấn.'],
            ['enabled_key' => 'enable_quality_score', 'target_key' => 'quality_score_target', 'title' => 'Điểm chất lượng', 'unit' => 'điểm', 'icon' => 'bi-stars', 'description' => 'Chất lượng tư vấn, ghi chú CRM, thái độ chăm sóc.'],
            ['enabled_key' => 'enable_revenue_pipeline', 'target_key' => 'revenue_pipeline_target', 'title' => 'Pipeline doanh số', 'unit' => 'VNĐ', 'icon' => 'bi-graph-up-arrow', 'description' => 'Giá trị cơ hội/báo giá đang theo đuổi.'],
        ];

        return view('sales.kpi.settings', compact('settings', 'coreModules', 'suggestedModules'));
    }

    /**
     * Validate và lưu cấu hình KPI sales vào file JSON, xóa cache.
     */
    public function kpiSettingsSave(Request $request)
    {
        $rules = [
            'posts_target' => ['required', 'integer', 'min:0'],
            'calls_target' => ['required', 'integer', 'min:0'],
            'company_data_target' => ['required', 'integer', 'min:0'],
            'penalty_per_missing' => ['required', 'integer', 'min:0'],
            'new_leads_target' => ['nullable', 'integer', 'min:0'],
            'quotes_target' => ['nullable', 'integer', 'min:0'],
            'customer_care_target' => ['nullable', 'integer', 'min:0'],
            'meetings_target' => ['nullable', 'integer', 'min:0'],
            'zalo_messages_target' => ['nullable', 'integer', 'min:0'],
            'debt_follow_target' => ['nullable', 'integer', 'min:0'],
            'order_follow_target' => ['nullable', 'integer', 'min:0'],
            'technical_coordination_target' => ['nullable', 'integer', 'min:0'],
            'overdue_tasks_target' => ['nullable', 'integer', 'min:0'],
            'training_target' => ['nullable', 'integer', 'min:0'],
            'quality_score_target' => ['nullable', 'integer', 'min:0'],
            'revenue_pipeline_target' => ['nullable', 'integer', 'min:0'],
            'lock_after_days' => ['nullable', 'integer', 'min:0'],
            'settings_note' => ['nullable', 'string', 'max:1000'],
        ];

        foreach ($this->kpiSettings->defaultSalesKpiSettings() as $key => $default) {
            if (strpos($key, 'enable_') === 0) {
                $rules[$key] = ['nullable', 'in:0,1'];
            }
        }

        $request->validate($rules);

        $settings = $this->kpiSettings->defaultSalesKpiSettings();
        foreach ($settings as $key => $default) {
            if (strpos($key, 'enable_') === 0) {
                $settings[$key] = $request->boolean($key) ? 1 : 0;

                continue;
            }

            if ($request->has($key)) {
                $settings[$key] = is_numeric($default)
                    ? (int) $request->input($key, $default)
                    : (string) $request->input($key, $default);
            }
        }

        $path = $this->kpiSettings->salesKpiSettingsPath();
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        Cache::flush();

        return redirect()
            ->route('sales.kpi.settings')
            ->with('success', 'Đã lưu cấu hình KPI Sales. Các hạng mục core được bật/tắt sẽ ảnh hưởng cách tính KPI hiện tại.');
    }
}
