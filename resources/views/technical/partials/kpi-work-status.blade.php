@php
    $kwsService = app(\App\Services\TechnicalKpi\KpiActionHintService::class);
    $kwsHint = isset($kpiReport) ? $kwsService->report($kpiReport) : $kwsService->item($kpiItem ?? null);
    $kwsTone = match($kwsHint['state']) { 'pass' => 'success', 'fail' => 'danger', 'missing' => 'warning', default => 'secondary' };
@endphp
<div class="small mt-2 mb-2" data-kpi-work-status>
    <strong class="text-{{ $kwsTone }}">KPI: {{ $kwsHint['label'] }}</strong>
    <div class="text-muted" style="overflow-wrap:anywhere">{{ $kwsHint['detail'] }}</div>
    <a href="{{ $kwsHint['url'] }}">Xem điểm và checklist tháng {{ \Illuminate\Support\Carbon::parse($kwsHint['month'].'-01')->format('m/Y') }}</a>
</div>
