<?php

namespace App\Services\Marketing;

/**
 * Bộ tính KPI marketing (ROAS, CPL, tiến độ) từ số liệu mục tiêu và thực tế.
 */
class PlanKpiCalculator
{
    /**
     * Tính ROAS, CPL thực tế và phần trăm tiến độ ngân sách/lead/doanh thu.
     */
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

    /**
     * Tính phần trăm a/b (làm tròn 1 chữ số), trả 0 nếu mẫu số rỗng.
     */
    private function percent($a, $b)
    {
        if (!$b || $b == 0) return 0;
        return round(($a / $b) * 100, 1);
    }
}