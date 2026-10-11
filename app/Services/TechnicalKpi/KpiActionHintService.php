<?php

namespace App\Services\TechnicalKpi;

use App\Models\Technical\TechnicalDailyReport;
use App\Models\Technical\TechnicalPlanItem;
use App\Models\TechnicalKpiConfig;
use App\Support\EgoCompanyLock;
use App\Support\SchemaCache;
use Illuminate\Support\Carbon;

/** Read-only hints use the same classification as the KPI page. No separate scoring formula. */
class KpiActionHintService
{
    public function __construct(private readonly AssignedWorkKpiService $work) {}

    public function criterion(string $code, ?string $month = null): array
    {
        $month ??= now()->format('Y-m');
        $key = 'kpi-action-criteria:'.EgoCompanyLock::id().':'.$month;
        if (! request()->attributes->has($key)) {
            $config = SchemaCache::hasTable('technical_kpi_configs') ? TechnicalKpiConfig::getActiveForDate($month.'-01') : null;
            request()->attributes->set($key, app(KpiStandardEvaluator::class)->useConfig($config, $month)->criteria());
        }

        return collect(request()->attributes->get($key))->firstWhere('code', $code) ?? [];
    }

    public function report(TechnicalDailyReport $report): array
    {
        $item = $report->plan_item_id ? TechnicalPlanItem::query()->where('company_id', EgoCompanyLock::id())
            ->where('user_id', $report->user_id)->find($report->plan_item_id) : null;

        return $this->item($item);
    }

    public function item(?TechnicalPlanItem $item): array
    {
        $month = $item?->assigned_at ? $item->assigned_at->copy()->addHours(24)->format('Y-m')
            : ($item?->plan_date?->format('Y-m') ?? now()->format('Y-m'));
        $hint = ['state' => 'info', 'label' => 'Chưa tính KPI Tiến độ', 'detail' => '', 'month' => $month,
            'url' => url('/ky-thuat/kpis').'?'.http_build_query(['period' => 'month', 'month' => $month,
                'user_id' => $item?->user_id ?? auth()->id()]).'#kpx-checklist'];
        if (! $item || ! $item->is_manager_assigned) {
            $hint['detail'] = 'Việc tự lập, kế hoạch nháp hoặc báo cáo chưa gắn việc trưởng phòng giao chưa vào KPI Tiến độ.';

            return $hint;
        }
        $criterion = $this->criterion('timeline', $month);
        if (($criterion['source_mode'] ?? '') !== 'assigned_reports' || ($criterion['weight'] ?? null) === null) {
            $hint['detail'] = 'Kỳ này chưa áp dụng tiêu chí nộp việc được giao; xem quy tắc của kỳ trên trang KPI.';

            return $hint;
        }
        $key = 'kpi-action-work:'.EgoCompanyLock::id().':'.$item->user_id.':'.$month;
        if (! request()->attributes->has($key)) {
            $items = $this->work->collect((int) $item->user_id, [$month]);
            request()->attributes->set($key, $items === null ? null : $this->work->evaluate($items, (int) ($criterion['submission_hours'] ?? 24)));
        }
        $measured = request()->attributes->get($key);
        $row = collect($measured['evidence'] ?? [])->firstWhere('plan_item_id', (int) $item->id);
        if ($row) {
            $hint['state'] = $row['state'];
            $hint['label'] = $row['result'];
            $hint['detail'] = $row['detail'];
            if (($measured['missing'] ?? 0) > 0) {
                $hint['detail'] .= ' · Kỳ còn việc thiếu mốc hợp lệ, chưa chốt điểm Tiến độ.';
            }
        } else {
            $hint['detail'] = 'Chưa tìm thấy việc trong nguồn KPI của kỳ; kiểm tra giờ giao và phạm vi công ty.';
        }

        return $hint;
    }

    public function warranty(object $row): array
    {
        $month = Carbon::parse($row->customer_reported_at ?: ($row->scheduled_date ?: now()))->format('Y-m');
        $criterion = $this->criterion('warranty', $month);
        $hint = ['state' => 'info', 'label' => 'Chưa tính KPI Bảo hành', 'month' => $month, 'criterion' => $criterion];
        if (! isset($criterion['response_minutes'], $criterion['resolution_hours']) || ($criterion['weight'] ?? null) === null) {
            $hint['label'] = 'Kỳ này dùng quy tắc bảo hành cũ hoặc tiêu chí chưa áp dụng; xem trang KPI.';

            return $hint;
        }
        [$hint['state'], $hint['label']] = app(KpiStandardEvaluator::class)->warrantyState($row,
            (int) $criterion['response_minutes'], (int) $criterion['resolution_hours']);

        return $hint;
    }
}
