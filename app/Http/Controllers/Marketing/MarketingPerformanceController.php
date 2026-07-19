<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Marketing\MarketingBudget;
use App\Models\Marketing\MarketingMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarketingPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $month    = $request->get('month');     // YYYY-MM
        $platform = $request->get('platform');  // Facebook/Google/...

        // ===== Budget summary (theo tháng + kênh) =====
        $budgetQ = MarketingBudget::query();
        if ($month)    $budgetQ->where('month', $month . '-01');
        if ($platform) $budgetQ->where('platform', $platform);

        $budgetSummary = (clone $budgetQ)
            ->selectRaw('month, platform, SUM(budget) as budget, SUM(actual_spent) as spent')
            ->groupBy('month','platform')
            ->orderByDesc('month')
            ->get();

        // ===== Metric summary (theo tháng + kênh) =====
        $metricQ = MarketingMetric::query();
        if ($month) {
            $metricQ->whereBetween('date', [$month.'-01', $month.'-31']);
        }
        if ($platform) $metricQ->where('platform', $platform);

        $metricSummary = (clone $metricQ)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m-01') as month, platform,
                        SUM(spend) as spend, SUM(leads) as leads, SUM(orders) as orders, SUM(revenue) as revenue")
            ->groupBy('month','platform')
            ->orderByDesc('month')
            ->get();

        // ===== Merge thành 1 bảng Performance =====
        $map = [];

        foreach ($budgetSummary as $b) {
            $key = Carbon::parse($b->month)->format('Y-m') . '|' . $b->platform;
            $map[$key] = [
                'month' => Carbon::parse($b->month)->format('Y-m'),
                'platform' => $b->platform,
                'budget' => (int)$b->budget,
                'planned_spent' => (int)$b->spent, // đã chi theo budget (nếu bạn nhập)
                'spend' => 0,
                'leads' => 0,
                'orders' => 0,
                'revenue' => 0,
            ];
        }

        foreach ($metricSummary as $m) {
            $mMonth = Carbon::parse($m->month)->format('Y-m');
            $key = $mMonth . '|' . $m->platform;

            if (!isset($map[$key])) {
                $map[$key] = [
                    'month' => $mMonth,
                    'platform' => $m->platform,
                    'budget' => 0,
                    'planned_spent' => 0,
                    'spend' => 0,
                    'leads' => 0,
                    'orders' => 0,
                    'revenue' => 0,
                ];
            }

            $map[$key]['spend']   = (int)$m->spend;
            $map[$key]['leads']   = (int)$m->leads;
            $map[$key]['orders']  = (int)$m->orders;
            $map[$key]['revenue'] = (int)$m->revenue;
        }

        $performance = collect(array_values($map))
            ->sortByDesc('month')
            ->values();

        // KPI tổng (theo filter)
        $kpi = [
            'budget'  => $performance->sum('budget'),
            'spend'   => $performance->sum('spend'),
            'leads'   => $performance->sum('leads'),
            'orders'  => $performance->sum('orders'),
            'revenue' => $performance->sum('revenue'),
        ];

        $kpi['cpl']  = $kpi['leads']  > 0 ? round($kpi['spend'] / $kpi['leads']) : 0;
        $kpi['cpo']  = $kpi['orders'] > 0 ? round($kpi['spend'] / $kpi['orders']) : 0;
        $kpi['roas'] = $kpi['spend']  > 0 ? round($kpi['revenue'] / $kpi['spend'], 2) : 0;

        return view('marketing.performance', compact('month','platform','performance','kpi'));
    }
}
