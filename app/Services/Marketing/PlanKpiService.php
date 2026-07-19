<?php

namespace App\Services\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PlanKpiService
{
    public function build(int $planId): array
    {
        $plan = DB::table('mkt_plans')->where('id', $planId)->first();
        abort_if(!$plan, 404);

        $from = Carbon::parse($plan->month)->startOfMonth();
        $to   = Carbon::parse($plan->month)->endOfMonth();

        $actual = DB::table('mkt_actual_kpi_daily')
            ->whereBetween('date', [$from, $to])
            ->selectRaw('
                SUM(spend) as spend,
                SUM(leads) as leads,
                SUM(revenue) as revenue
            ')
            ->first();

        $spend = (float)($actual->spend ?? 0);
        $leads = (float)($actual->leads ?? 0);
        $rev   = (float)($actual->revenue ?? 0);

        $roas = $spend > 0 ? round($rev / $spend, 2) : 0;
        $cpl  = $leads > 0 ? round($spend / $leads, 0) : 0;

        return [
            'plan' => $plan,

            'target_budget' => (float)$plan->total_budget,
            'target_leads'  => (float)$plan->target_leads,
            'target_rev'    => (float)$plan->target_revenue,
            'target_roas'   => (float)$plan->target_roas,

            'actual_spend'  => $spend,
            'actual_leads'  => $leads,
            'actual_rev'    => $rev,
            'actual_roas'   => $roas,
            'actual_cpl'    => $cpl,

            'progress_budget' => $this->percent($spend, $plan->total_budget),
            'progress_leads'  => $this->percent($leads, $plan->target_leads),
            'progress_rev'    => $this->percent($rev, $plan->target_revenue),
        ];
    }

    private function percent($a, $b)
    {
        if (!$b || $b == 0) return 0;
        return round(($a / $b) * 100, 1);
    }
}