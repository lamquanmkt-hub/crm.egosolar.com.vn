<?php

namespace App\Services\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service tổng hợp dữ liệu dashboard cho kế hoạch marketing theo tháng.
 */
class PlanDashboardService
{
    /**
     * Tổng hợp dashboard kế hoạch: mục tiêu, thực tế, KPI và phân bổ chi tiêu theo kênh.
     */
    public function build(int $planId): array
    {
        $plan = DB::table('mkt_plans')->where('id', $planId)->first();
        abort_if(!$plan, 404);

        $from = Carbon::parse($plan->month)->startOfMonth();
        $to   = Carbon::parse($plan->month)->endOfMonth();

        $actualRow = DB::table('mkt_actual_kpi_daily')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                SUM(spend) as spend,
                SUM(leads) as leads,
                SUM(revenue) as revenue
            ')
            ->first();

        $actual = [
            'spend'   => (float)($actualRow->spend ?? 0),
            'leads'   => (float)($actualRow->leads ?? 0),
            'revenue' => (float)($actualRow->revenue ?? 0),
        ];

        $target = [
            'budget'  => (float)$plan->total_budget,
            'leads'   => (float)$plan->target_leads,
            'revenue' => (float)$plan->target_revenue,
        ];

        $calculator = new PlanKpiCalculator();
        $kpi = $calculator->calculate($target, $actual);

        $channelBreakdown = DB::table('mkt_actual_kpi_daily')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('channel, SUM(spend) as spend')
            ->groupBy('channel')
            ->orderByDesc('spend')
            ->get();

        return [
            'plan' => $plan,
            'target' => $target,
            'actual' => $actual,
            'kpi' => $kpi,
            'channels' => $channelBreakdown,
        ];
    }
}