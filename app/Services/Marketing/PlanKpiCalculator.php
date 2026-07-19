<?php

namespace App\Services\Marketing;

class PlanKpiCalculator
{
    public function calculate(array $target, array $actual): array
    {
        $roas = $actual['spend'] > 0
            ? round($actual['revenue'] / $actual['spend'], 2)
            : 0;

        $cpl = $actual['leads'] > 0
            ? round($actual['spend'] / $actual['leads'], 0)
            : 0;

        return [
            'actual_roas' => $roas,
            'actual_cpl'  => $cpl,
            'progress_budget' => $this->percent($actual['spend'], $target['budget']),
            'progress_leads'  => $this->percent($actual['leads'], $target['leads']),
            'progress_rev'    => $this->percent($actual['revenue'], $target['revenue']),
        ];
    }

    private function percent($a, $b)
    {
        if (!$b || $b == 0) return 0;
        return round(($a / $b) * 100, 1);
    }
}