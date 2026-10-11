{{-- Local demonstration only: use the actual evaluator, never manufacture a total. --}}
@php
    $demoSite = $demoSite ?? ($site ?? null);
    $demoStep = $demoStep ?? 'overview';
@endphp
@if($demoSite && (string) $demoSite->project_code === 'TEST-KPI-20261006' && $demoSite->lead_engineer_id)
    @php
        $demoMonth = '2026-10';
        $demoEvaluator = app(\App\Services\TechnicalKpi\KpiStandardEvaluator::class)
            ->useConfig(\App\Models\TechnicalKpiConfig::getActiveForDate($demoMonth.'-01'), $demoMonth);
        $demoSources = $demoEvaluator->collect((int) $demoSite->lead_engineer_id, [$demoMonth], (int) $demoSite->id);
        $demoScore = $demoEvaluator->score($demoSources['projects'], $demoSources['evidence'], $demoSources['warranty'], $demoSources['manual'], $demoSources['adjustments'], $demoSources['assigned_work']);
        $demoCodes = match ($demoStep) {
            'survey', 'proposal', 'materials' => ['survey'],
            'construction' => ['timeline', 'hse'],
            'acceptance' => ['quality', 'evn_app'],
            'warranty' => ['warranty'],
            'contract', 'legal', 'finance' => [],
            default => ['timeline', 'quality', 'survey', 'hse', 'evn_app', 'warranty'],
        };
        $demoRows = collect($demoScore['criteria'])->whereIn('code', $demoCodes);
        $demoPoints = fn ($value) => rtrim(rtrim(number_format($value * 100, 1, ',', '.'), '0'), ',');
    @endphp
    <section data-kpi-demo-progress style="padding:16px 18px;margin-bottom:16px;border:1px solid #95d6d5;border-radius:12px;background:#f0fafa;color:#164b56">
        <strong>Ghi chú KPI — dữ liệu TEST · tháng 10/2026</strong>
        <p style="margin:6px 0">Điểm thành phần đã tính: <b>{{ $demoPoints($demoScore['partial_score'] ?? 0) }}/100</b>.
            @if($demoScore['total'] === null) <b>Chưa chốt tổng KPI</b>: còn thiếu {{ implode(', ', $demoScore['missing_summary']) }}.
            @else Tổng KPI hiện tại: <b>{{ $demoPoints($demoScore['total']) }}%</b>.
            @endif
        </p>
        @foreach($demoRows as $demoRow)
            <p style="margin:4px 0"><b>{{ $demoRow['name'] }}:</b>
                @if($demoRow['score'] === null) CHƯA NHẬN ĐIỂM — {{ \App\Services\TechnicalKpi\KpiStandardEvaluator::STATUS_LABELS[$demoRow['status']] }}.
                @else ĐÃ TÍNH {{ $demoPoints($demoRow['score']) }}/{{ $demoPoints($demoRow['weight']) }} điểm — {{ \App\Services\TechnicalKpi\KpiStandardEvaluator::STATUS_LABELS[$demoRow['status']] }}.
                @endif
                @if($demoRow['score'] === null && !empty($demoRow['missing'])) {{ implode(' ', $demoRow['missing']) }} @endif
            </p>
        @endforeach
        @if(in_array($demoStep, ['contract', 'legal', 'finance'], true))
            <p style="margin:4px 0">Bước này không có điểm KPI riêng. Hạn hợp đồng là dữ liệu hỗ trợ chấm tiến độ khi nghiệm thu.</p>
        @elseif($demoStep === 'materials')
            <p style="margin:4px 0">Vật tư không có điểm riêng. Mẫu hiện dùng sai lệch 1% nhập tại nghiệm thu; chưa có phiếu kho và chưa chứng minh tự động đối chiếu từng mã vật tư/đơn vị.</p>
        @elseif($demoStep === 'warranty')
            <p style="margin:4px 0">Kế hoạch, phân công, từng mục thực hiện và phát sinh đều không cộng điểm riêng. Kỹ thuật kiểm tra hồ sơ/minh chứng, nhập kết quả → Hoàn tất đợt → trưởng phòng xác nhận khiếu nại tại Tổng quan. KS06 chỉ nhận điểm khi đủ cả ba điều kiện SLA.</p>
        @endif
        <small>Trạng thái trên đọc lại từ dữ liệu nguồn mỗi lần mở trang. Hồ sơ, ảnh và xác nhận mẫu đều là giả lập; đây là điểm kiểm thử, không phải duyệt trả lương.</small>
        <div style="margin-top:8px"><a href="{{ route('ky-thuat.kpis.index', ['month'=>$demoMonth, 'user_id'=>$demoSite->lead_engineer_id]) }}">Xem cách tính KPI tháng 10</a></div>
    </section>
@endif
