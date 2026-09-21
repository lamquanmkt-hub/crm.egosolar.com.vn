@extends('layouts.app')

@section('title', 'Tổng kết tuần phòng Kỹ thuật')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Tổng kết tuần</h1>
            <p class="tw-head__sub">
                {{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}.
                So sánh kế hoạch với kết quả thực hiện của phòng Kỹ thuật.
            </p>
        </div>
        <div class="tw-head__actions">
            @include('technical.guides.partials.help-button', ['slug' => 'truong-phong-tong-ket-tuan'])
            <a href="{{ route('technical.manager.board', ['week' => $weekStart->toDateString()]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-grid-3x3"></i> Kế hoạch nhân viên
            </a>
        </div>
    </div>

    @include('technical.workboard.partials.flash')

    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.weekly-summary', ['week' => $prevWeek]) }}">
                <i class="bi bi-chevron-left"></i> Tuần trước
            </a>
            <a class="btn btn-sm {{ $weekStart->toDateString() === $thisWeek ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.manager.weekly-summary', ['week' => $thisWeek]) }}">Tuần hiện tại</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.weekly-summary', ['week' => $nextWeek]) }}">
                Tuần sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <span class="tp-weekbar__spacer"></span>

        <span class="tp-state">
            Tỷ lệ hoàn thành:
            {{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($summary['completion_rate']) }}
        </span>
        <span class="tp-state">
            Tỷ lệ nộp báo cáo:
            {{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($summary['report_rate']) }}
        </span>
    </div>

    <div class="tp-cards">
        @php
            $cards = [
                ['Công việc kế hoạch', $summary['planned_items'], 'bi-list-check', ''],
                ['Hoàn thành', $summary['done_items'], 'bi-check2-circle', 'tw-stat--ok'],
                ['Chưa hoàn thành', $summary['not_done_items'] + $summary['open_items'], 'bi-hourglass-split', 'tw-stat--info'],
                ['Chuyển ngày', $summary['moved_items'], 'bi-arrow-right-circle', 'tw-stat--warn'],
                ['Quá hạn', $summary['overdue_items'], 'bi-exclamation-octagon', 'tw-stat--danger'],
                ['Phát sinh', $summary['unplanned_items'], 'bi-lightning-charge', 'tw-stat--warn'],
                ['Giờ dự kiến', $planner->minutesLabel($summary['estimated_minutes']), 'bi-clock', ''],
                ['Giờ thực tế', $planner->minutesLabel($summary['actual_minutes']), 'bi-stopwatch', ''],
            ];
        @endphp

        @foreach($cards as [$label, $value, $icon, $tone])
            <div class="tw-stat {{ $tone }}">
                <span class="tw-stat__icon"><i class="bi {{ $icon }}"></i></span>
                <span>
                    <span class="tw-stat__value d-block">{{ is_int($value) ? number_format($value) : $value }}</span>
                    <span class="tw-stat__label d-block">{{ $label }}</span>
                </span>
            </div>
        @endforeach
    </div>

    @include('technical.partials.charts', ['trend' => $trend, 'rows' => $rows, 'summary' => $summary])

    <div class="tw-card mb-3">
        <div class="tw-card__head"><h2 class="tw-card__title">Theo nhân viên</h2></div>
        @include('technical.partials.staff-table', ['rows' => $rows, 'detailMode' => 'manager', 'weekStart' => $weekStart])
    </div>

    <div class="tw-card">
        <div class="tw-card__head"><h2 class="tw-card__title">Theo công trình</h2></div>
        @include('technical.partials.site-table', ['sites' => $sites])
    </div>

</div>
@endsection
