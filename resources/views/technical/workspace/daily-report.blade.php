@extends('layouts.app')
@section('title', 'Báo cáo ngày Kỹ thuật')
@push('styles')<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">@endpush
@php
    $statusMeta = [
        'office'=>['Tại văn phòng','is-office','bi-building'],
        'installation'=>['Đi thi công','is-installation','bi-tools'],
        'maintenance'=>['Đi bảo trì','is-maintenance','bi-shield-check'],
        'survey'=>['Đi khảo sát','is-survey','bi-rulers'],
        'field_work'=>['Công tác ngoài','is-field','bi-geo-alt'],
        'leave'=>['Nghỉ phép','is-leave','bi-calendar-x'],
        'absent'=>['Cần xác minh','is-danger','bi-exclamation-circle'],
        'unplanned'=>['Chưa có kế hoạch','is-muted','bi-calendar2'],
    ];
    $attentionTotal = $summary['unconfirmed'] + $summary['absent'] + $summary['conflicts'];
@endphp
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero tw-hero--compact">
        <div class="tw-hero__row"><div>
            <div class="tw-kicker">PHÒNG KỸ THUẬT · BÁO CÁO NGÀY TỰ ĐỘNG</div>
            <h1>Báo cáo ngày {{ $date->format('d/m/Y') }}</h1>
            <p>Tự động đối chiếu lịch thi công, bảo trì, khảo sát, chấm công và nghỉ phép đã duyệt.</p>
        </div><div class="tw-title-actions">
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.reports.history',['mode'=>'daily']) }}"><i class="bi bi-clock-history"></i>Lịch sử</a>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.reports.export',['mode'=>'daily','date'=>$date->toDateString()]) }}"><i class="bi bi-file-earmark-excel"></i>Xuất Excel</a>
            <form method="POST" action="{{ route('technical-workspace.reports.snapshot.store') }}">
                @csrf
                <input type="hidden" name="mode" value="daily">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button class="tw-btn" type="submit"><i class="bi bi-cloud-check"></i>Lưu báo cáo</button>
            </form>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.operations.daily-report',['date'=>$date->copy()->subDay()->toDateString()]) }}"><i class="bi bi-chevron-left"></i>Ngày trước</a>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.operations.daily-report',['date'=>$date->copy()->addDay()->toDateString()]) }}">Ngày sau<i class="bi bi-chevron-right"></i></a>
        </div></div>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-source-strip">
        <div class="tw-source-strip__title"><i class="bi bi-lightning-charge-fill"></i><span>Tổng hợp lúc <strong>{{ $generatedAt->format('H:i:s') }}</strong></span></div>
        <div><span>Chấm công</span><strong>{{ $sourceSummary['attendance'] }}</strong></div>
        <div><span>Lịch ngoài</span><strong>{{ $sourceSummary['schedules'] }}</strong></div>
        <div><span>Nghỉ phép</span><strong>{{ $sourceSummary['leave'] }}</strong></div>
        <div><span>Cần kiểm tra</span><strong>{{ $attentionTotal }}</strong></div>
    </section>

    <section class="tw-card"><div class="tw-card__body">
        <form method="GET" class="tw-filter">
            <label>Ngày báo cáo<input class="tw-input" type="date" name="date" value="{{ $date->toDateString() }}"></label>
            <button class="tw-btn"><i class="bi bi-arrow-repeat"></i>Tổng hợp báo cáo</button>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.operations.daily-report') }}">Hôm nay</a>
            <a class="tw-btn tw-btn--soft" href="{{ route('technical-workspace.overview',['date'=>$date->toDateString()]) }}"><i class="bi bi-speedometer2"></i>Về tổng quan</a>
        </form>
    </div></section>

    <section class="tw-kpis tw-kpis--5">
        <article class="tw-kpi tw-kpi--blue"><i class="bi bi-people"></i><div><span>Tổng Kỹ thuật</span><strong>{{ $summary['total'] }}</strong><small>Tài khoản đang hoạt động</small></div></article>
        <article class="tw-kpi tw-kpi--green"><i class="bi bi-person-check"></i><div><span>Có mặt</span><strong>{{ $summary['present'] }}</strong><small>Văn phòng hoặc công tác</small></div></article>
        <article class="tw-kpi tw-kpi--cyan"><i class="bi bi-buildings"></i><div><span>Đi công trình</span><strong>{{ $summary['installation'] + $summary['survey'] }}</strong><small>Thi công và khảo sát</small></div></article>
        <article class="tw-kpi tw-kpi--amber"><i class="bi bi-calendar-x"></i><div><span>Nghỉ phép</span><strong>{{ $summary['leave'] }}</strong><small>Đã được duyệt</small></div></article>
        <article class="tw-kpi {{ $attentionTotal ? 'tw-kpi--red' : 'tw-kpi--neutral' }}"><i class="bi bi-exclamation-triangle"></i><div><span>Cần kiểm tra</span><strong>{{ $attentionTotal }}</strong><small>{{ $summary['conflicts'] }} trường hợp trùng giờ</small></div></article>
    </section>

    @if($attentionTotal)
        <section class="tw-alert tw-alert--warning"><i class="bi bi-info-circle"></i><div><strong>Báo cáo có {{ $attentionTotal }} cảnh báo cần đối chiếu</strong><span>Lịch công trình vẫn được tính vào số đi công trình; cảnh báo chưa chấm công được hiển thị riêng.</span></div></section>
    @endif

    <section class="tw-card">
        <div class="tw-card__head"><div><h2>Chi tiết nhân sự</h2><p>Mỗi người có một trạng thái chính và một cột kiểm tra độc lập.</p></div><span class="tw-pill">{{ $rows->count() }} nhân sự</span></div>
        <div class="tw-table-wrap"><table class="tw-table tw-table--daily">
            <thead><tr><th>Nhân sự</th><th>Trạng thái</th><th>Phân công trong ngày</th><th>Địa điểm</th><th>Chấm công</th><th>Kiểm tra</th></tr></thead>
            <tbody>@forelse($rows as $row) @php($meta=$statusMeta[$row['status']] ?? [$row['status'],'','bi-person'])
                <tr>
                    <td><div class="tw-person"><span>{{ mb_strtoupper(mb_substr($row['name'],0,1)) }}</span><div><strong>{{ $row['name'] }}</strong><small>ID #{{ $row['user_id'] }}</small></div></div></td>
                    <td><span class="tw-operation-status {{ $meta[1] }}"><i class="bi {{ $meta[2] }}"></i>{{ $meta[0] }}</span></td>
                    <td>@forelse($row['events'] as $event)<div class="tw-event-line"><span class="tw-event-type is-{{ $event->event_type }}">{{ ['survey'=>'Khảo sát','installation'=>'Thi công','maintenance'=>'Bảo trì'][$event->event_type] ?? $event->event_type }}</span><span>{{ $event->starts_at?->format('H:i') }} · {{ $event->title }}</span></div>@empty<span class="tw-sub">Không có lịch ngoài công trình</span>@endforelse</td>
                    <td>{{ $row['primary_event']?->address ?: '—' }}</td>
                    <td>@if($row['attendance'])<div class="tw-name">{{ $row['attendance']->check_in_at ? \Carbon\Carbon::parse($row['attendance']->check_in_at)->format('H:i:s') : '—' }} → {{ $row['attendance']->check_out_at ? \Carbon\Carbon::parse($row['attendance']->check_out_at)->format('H:i:s') : 'Chưa ra' }}</div><div class="tw-sub">{{ str_replace('_',' ',(string)$row['attendance']->status) }}</div>@else<span class="tw-sub">Chưa có dữ liệu</span>@endif</td>
                    <td>@if($row['has_conflict'])<span class="tw-pill danger">Trùng giờ</span>@elseif($row['needs_verification'])<span class="tw-pill warning">Cần xác nhận</span>@elseif($row['status']==='unplanned')<span class="tw-pill">Chưa đến ngày</span>@else<span class="tw-pill">Hợp lệ</span>@endif</td>
                </tr>
            @empty<tr><td colspan="6"><div class="tw-empty"><i class="bi bi-people"></i><strong>Chưa có dữ liệu nhân sự Kỹ thuật</strong><span>Kiểm tra role Kỹ thuật hoặc phạm vi công ty.</span></div></td></tr>@endforelse</tbody>
        </table></div>
    </section>
</div></div>
@endsection
