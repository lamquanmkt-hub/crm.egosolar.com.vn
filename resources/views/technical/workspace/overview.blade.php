@extends('layouts.app')
@section('title', 'Tổng quan Kỹ thuật')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/technical-workspace.css') }}?v={{ file_exists(public_path('css/technical-workspace.css')) ? filemtime(public_path('css/technical-workspace.css')) : time() }}">
@endpush
@php
    $statusMeta = [
        'office' => ['label' => 'Tại văn phòng', 'class' => 'is-office', 'icon' => 'bi-building'],
        'installation' => ['label' => 'Đi thi công', 'class' => 'is-installation', 'icon' => 'bi-tools'],
        'maintenance' => ['label' => 'Đi bảo trì', 'class' => 'is-maintenance', 'icon' => 'bi-shield-check'],
        'survey' => ['label' => 'Đi khảo sát', 'class' => 'is-survey', 'icon' => 'bi-rulers'],
        'field_work' => ['label' => 'Công tác ngoài', 'class' => 'is-field', 'icon' => 'bi-geo-alt'],
        'leave' => ['label' => 'Nghỉ phép', 'class' => 'is-leave', 'icon' => 'bi-calendar-x'],
        'absent' => ['label' => 'Cần xác minh', 'class' => 'is-danger', 'icon' => 'bi-exclamation-circle'],
        'unplanned' => ['label' => 'Chưa có kế hoạch', 'class' => 'is-muted', 'icon' => 'bi-calendar2'],
    ];
    $attentionTotal = $summary['unconfirmed'] + $summary['absent'] + $summary['conflicts'];
@endphp
@section('content')
<div class="tw-page"><div class="tw-shell">
    <section class="tw-hero tw-hero--compact">
        <div class="tw-hero__row">
            <div>
                <div class="tw-kicker">PHÒNG KỸ THUẬT · ĐIỀU HÀNH TẬP TRUNG</div>
                <h1>Tổng quan Kỹ thuật</h1>
                <p>Theo dõi nhân sự, lịch công trình và bảo trì từ một nguồn dữ liệu thống nhất.</p>
            </div>
            <div class="tw-title-actions">
                <form method="GET" class="tw-date-switcher">
                    <input type="date" name="date" value="{{ $date->toDateString() }}" aria-label="Ngày tổng quan">
                    <button type="submit"><i class="bi bi-arrow-repeat"></i>Cập nhật</button>
                </form>
                <a class="tw-btn" href="{{ route('technical-workspace.operations.daily-report', ['date' => $date->toDateString()]) }}"><i class="bi bi-clipboard-data"></i>Báo cáo chi tiết</a>
            </div>
        </div>
        @include('technical.workspace.partials-nav')
    </section>

    <section class="tw-source-strip" aria-label="Nguồn dữ liệu báo cáo">
        <div class="tw-source-strip__title"><i class="bi bi-lightning-charge-fill"></i><span>Tự động tổng hợp lúc <strong>{{ $generatedAt->format('H:i') }}</strong></span></div>
        <div><span>Chấm công</span><strong>{{ $sourceSummary['attendance'] }}</strong></div>
        <div><span>Lịch thi công</span><strong>{{ $sourceSummary['installation'] }}</strong></div>
        <div><span>Lịch bảo trì</span><strong>{{ $sourceSummary['maintenance'] }}</strong></div>
        <div><span>Khảo sát</span><strong>{{ $sourceSummary['survey'] }}</strong></div>
        <div><span>Nghỉ phép</span><strong>{{ $sourceSummary['leave'] }}</strong></div>
    </section>

    <section class="tw-kpis tw-kpis--5">
        <article class="tw-kpi tw-kpi--blue"><i class="bi bi-people"></i><div><span>Tổng nhân sự Kỹ thuật</span><strong>{{ number_format($summary['total']) }}</strong><small>Đang hoạt động trong hệ thống</small></div></article>
        <article class="tw-kpi tw-kpi--green"><i class="bi bi-person-check"></i><div><span>Đã xác định trạng thái</span><strong>{{ number_format($summary['present'] + $summary['leave']) }}</strong><small>Có mặt, công tác hoặc nghỉ phép</small></div></article>
        <article class="tw-kpi tw-kpi--cyan"><i class="bi bi-buildings"></i><div><span>Đi công trình</span><strong>{{ number_format($summary['installation'] + $summary['survey']) }}</strong><small>Thi công và khảo sát</small></div></article>
        <article class="tw-kpi tw-kpi--violet"><i class="bi bi-shield-check"></i><div><span>Đi bảo trì</span><strong>{{ number_format($summary['maintenance']) }}</strong><small>Lịch O&amp;M ngày {{ $date->format('d/m') }}</small></div></article>
        <article class="tw-kpi {{ $attentionTotal ? 'tw-kpi--red' : 'tw-kpi--neutral' }}"><i class="bi bi-exclamation-triangle"></i><div><span>Cần xử lý</span><strong>{{ number_format($attentionTotal) }}</strong><small>Chờ xác nhận, vắng hoặc trùng giờ</small></div></article>
    </section>

    <div class="tw-two-col tw-overview-grid">
        <section class="tw-card">
            <div class="tw-card__head"><div><h2>Tình hình nhân sự ngày {{ $date->format('d/m/Y') }}</h2><p>Lịch công trình vẫn được tính đúng; trạng thái chưa xác nhận hiển thị thành cảnh báo riêng.</p></div><span class="tw-pill {{ $attentionTotal ? 'warning' : '' }}">{{ $attentionTotal ? $attentionTotal.' cần kiểm tra' : 'Đã kiểm tra đủ' }}</span></div>
            <div class="tw-table-wrap"><table class="tw-table tw-table--people">
                <thead><tr><th>Nhân sự</th><th>Trạng thái chính</th><th>Phân công</th><th>Chấm công</th><th>Kiểm tra</th></tr></thead>
                <tbody>@forelse($rows as $row)
                    @php($meta = $statusMeta[$row['status']] ?? ['label' => $row['status'], 'class' => '', 'icon' => 'bi-person'])
                    <tr>
                        <td><div class="tw-person"><span>{{ mb_strtoupper(mb_substr($row['name'],0,1)) }}</span><div><strong>{{ $row['name'] }}</strong><small>ID #{{ $row['user_id'] }}</small></div></div></td>
                        <td><span class="tw-operation-status {{ $meta['class'] }}"><i class="bi {{ $meta['icon'] }}"></i>{{ $meta['label'] }}</span></td>
                        <td>@if($row['primary_event'])<div class="tw-name">{{ $row['primary_event']->title }}</div><div class="tw-sub">{{ optional($row['primary_event']->starts_at)->format('H:i') }} · {{ $row['primary_event']->address ?: 'Chưa có địa điểm' }}</div>@else<span class="tw-sub">Không có lịch ngoài công trình</span>@endif</td>
                        <td>@if($row['attendance'])<div class="tw-name">{{ $row['attendance']->check_in_at ? \Carbon\Carbon::parse($row['attendance']->check_in_at)->format('H:i') : '—' }} → {{ $row['attendance']->check_out_at ? \Carbon\Carbon::parse($row['attendance']->check_out_at)->format('H:i') : 'Chưa ra' }}</div>@else<span class="tw-sub">Chưa ghi nhận</span>@endif</td>
                        <td>@if($row['has_conflict'])<span class="tw-pill danger">Trùng giờ</span>@elseif($row['needs_verification'])<span class="tw-pill warning">Cần xác nhận</span>@else<span class="tw-pill">Hợp lệ</span>@endif</td>
                    </tr>
                @empty<tr><td colspan="5"><div class="tw-empty"><i class="bi bi-people"></i><strong>Chưa có nhân sự Kỹ thuật</strong><span>Kiểm tra lại role và trạng thái tài khoản.</span></div></td></tr>@endforelse</tbody>
            </table></div>
        </section>

        <aside class="tw-side-stack">
            <section class="tw-card">
                <div class="tw-card__head"><div><h2>Việc cần chú ý</h2><p>Ưu tiên xử lý trong ngày.</p></div></div>
                <div class="tw-attention-list">
                    <div class="{{ $summary['unconfirmed'] ? 'is-warning' : '' }}"><i class="bi bi-person-exclamation"></i><span><strong>{{ $summary['unconfirmed'] }} lịch chưa xác nhận</strong><small>Có phân công nhưng chưa có chấm công</small></span></div>
                    <div class="{{ $summary['absent'] ? 'is-danger' : '' }}"><i class="bi bi-person-x"></i><span><strong>{{ $summary['absent'] }} người cần xác minh</strong><small>Không lịch, không chấm công, không nghỉ phép</small></span></div>
                    <div class="{{ $summary['conflicts'] ? 'is-danger' : '' }}"><i class="bi bi-calendar2-x"></i><span><strong>{{ $summary['conflicts'] }} trường hợp trùng giờ</strong><small>Chỉ cảnh báo khi thời gian thực sự giao nhau</small></span></div>
                </div>
            </section>
            <section class="tw-card">
                <div class="tw-card__head"><div><h2>Công trình đang vận hành</h2><p>Ảnh chụp nhanh theo trạng thái nguồn.</p></div></div>
                <div class="tw-project-snapshot">
                    <div><span>Đang chạy</span><strong>{{ number_format($projectStats['active']) }}</strong></div>
                    <div><span>Khảo sát</span><strong>{{ number_format($projectStats['survey']) }}</strong></div>
                    <div><span>Thi công</span><strong>{{ number_format($projectStats['installation']) }}</strong></div>
                    <div><span>Bảo hành</span><strong>{{ number_format($projectStats['warranty']) }}</strong></div>
                </div>
            </section>
        </aside>
    </div>

    <section class="tw-card">
        <div class="tw-card__head"><div><h2>Lịch Kỹ thuật 7 ngày tới</h2><p>Danh sách nhanh; thay đổi phải thực hiện tại hồ sơ nguồn.</p></div><span class="tw-pill">{{ $upcomingEvents->count() }} lịch</span></div>
        <div class="tw-table-wrap"><table class="tw-table">
            <thead><tr><th>Thời gian</th><th>Loại lịch</th><th>Công trình/Công việc</th><th>Nhân sự</th><th>Trạng thái</th></tr></thead>
            <tbody>@forelse($upcomingEvents as $event)<tr>
                <td><div class="tw-name">{{ $event->starts_at?->format('d/m/Y') }}</div><div class="tw-sub">{{ $event->starts_at?->format('H:i') }} → {{ $event->ends_at?->format('H:i') ?: '—' }}</div></td>
                <td><span class="tw-event-type is-{{ $event->event_type }}">{{ ['survey'=>'Khảo sát','installation'=>'Thi công','maintenance'=>'Bảo trì'][$event->event_type] ?? $event->event_type }}</span></td>
                <td><div class="tw-name">{{ $event->title }}</div><div class="tw-sub">{{ $event->address ?: 'Chưa có địa điểm' }}</div></td>
                <td>{{ $event->participants->pluck('user.name')->filter()->join(', ') ?: 'Chưa phân công' }}</td>
                <td><span class="tw-pill {{ in_array($event->status,['cancelled','postponed'],true) ? 'danger' : '' }}">{{ str_replace('_',' ', $event->status) }}</span></td>
            </tr>@empty<tr><td colspan="5"><div class="tw-empty"><i class="bi bi-calendar2-check"></i><strong>Chưa có lịch trong 7 ngày tới</strong></div></td></tr>@endforelse</tbody>
        </table></div>
    </section>
</div></div>
@endsection
