@if(\App\Http\Controllers\Technical\MaintenanceWarrantySlaController::isWarrantyType((string) $schedule->type)
    && \App\Support\SchemaCache::hasColumn('solar_maintenance_schedules', 'first_responded_at'))
    @php
        $kwHint = app(\App\Services\TechnicalKpi\KpiActionHintService::class)->warranty($schedule);
        $kwTone = match($kwHint['state']) { 'pass' => 'success', 'fail' => 'danger', 'missing' => 'warning', default => 'secondary' };
        $kwInScope = \App\Http\Controllers\Technical\MaintenanceWarrantySlaController::inKpiScope($schedule);
        $kwLeader = (int) $schedule->assigned_to ?: (int) \Illuminate\Support\Facades\DB::table('solar_maintenance_assignees')
            ->where('maintenance_schedule_id', $schedule->id)->where(fn ($q) => $q->where('is_leader', 1)->orWhere('role', 'leader'))->value('user_id');
        if (! $kwInScope || ! $kwLeader) {
            $kwHint['state'] = 'missing';
            $kwHint['label'] = $kwInScope ? 'Chưa có người phụ trách chính để gán KPI' : 'Ngoài phạm vi công ty đang chấm KPI';
            $kwTone = 'warning';
        }
        $kwUrl = url('/ky-thuat/kpis').'?'.http_build_query(array_filter(['period' => 'month', 'month' => $kwHint['month'],
            'user_id' => $kwLeader])).'#kpx-checklist';
    @endphp
    <aside class="alert alert-{{ $kwTone }} small mt-2 mb-2" data-kpi-warranty-status>
        <strong>KPI phiếu này: {{ $kwHint['label'] }}</strong>
        @if($kwInScope && ! $kwLeader)
            <div>Chưa có người phụ trách chính để gán KPI; quản lý cần phân công.</div>
        @endif
        @if($kwHint['state'] === 'pass')
            <div>Phiếu đủ điều kiện nhận phần điểm Bảo hành của người phụ trách chính. Điểm tháng còn phụ thuộc các phiếu khác; một phiếu đạt không tự nhận toàn bộ trọng số.</div>
        @elseif($kwHint['state'] === 'fail')
            <div>Phiếu không nhận phần điểm Bảo hành. Xem lý do phía trên; không trừ thêm một khoản điểm riêng.</div>
        @else
            <div>Chưa cộng điểm cho phiếu này. Cần đủ mốc khách báo, phản hồi, hoàn tất và kết quả kiểm tra khiếu nại.</div>
        @endif
        @if($kwInScope && $kwLeader)<a href="{{ $kwUrl }}">Xem điểm và checklist bảo hành tháng {{ \Illuminate\Support\Carbon::parse($kwHint['month'].'-01')->format('m/Y') }}</a>@endif
    </aside>
@endif
