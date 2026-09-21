@extends('layouts.app')

@section('title', 'Tổng quan phòng Kỹ thuật')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
@php
    $notPlanned = $rows->filter(fn (array $r): bool => ! $r['has_week_plan']);
@endphp

<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Tổng quan phòng Kỹ thuật</h1>
            <p class="tw-head__sub">
                Tuần {{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}.
                Số liệu tổng hợp từ kế hoạch tuần và báo cáo ngày của nhân viên.
            </p>
        </div>
        <div class="tw-head__actions">
            @include('technical.guides.partials.help-button', ['slug' => 'truong-phong-tong-ket-tuan'])
            <a href="{{ route('technical.manager.board', ['week' => $weekStart->toDateString()]) }}" class="btn btn-primary">
                <i class="bi bi-grid-3x3"></i> Kế hoạch nhân viên
            </a>
            <a href="{{ route('technical.manager.weekly-summary', ['week' => $weekStart->toDateString()]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-clipboard-data"></i> Tổng kết tuần
            </a>
        </div>
    </div>

    @include('technical.workboard.partials.flash')

    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.overview', ['week' => $prevWeek]) }}">
                <i class="bi bi-chevron-left"></i> Tuần trước
            </a>
            <a class="btn btn-sm {{ $weekStart->toDateString() === $thisWeek ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.manager.overview', ['week' => $thisWeek]) }}">Tuần hiện tại</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.overview', ['week' => $nextWeek]) }}">
                Tuần sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <span class="tp-weekbar__range">
            <i class="bi bi-calendar-range"></i> {{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}
        </span>
    </div>

    <div class="tp-cards">
        @php
            $cards = [
                ['Nhân sự kỹ thuật', $rows->count(), 'bi-people', ''],
                ['Chưa lập kế hoạch tuần', $notPlanned->count(), 'bi-calendar-x', 'tw-stat--warn'],
                ['Công việc kế hoạch', $summary['planned_items'], 'bi-list-check', ''],
                ['Hoàn thành', $summary['done_items'], 'bi-check2-circle', 'tw-stat--ok'],
                ['Quá hạn', $summary['overdue_items'], 'bi-exclamation-octagon', 'tw-stat--danger'],
                ['Phát sinh', $summary['unplanned_items'], 'bi-lightning-charge', 'tw-stat--warn'],
                ['Báo cáo đã nộp', $summary['reported_items'], 'bi-journal-check', 'tw-stat--ok'],
                ['Báo cáo chưa nộp', $summary['unreported_items'], 'bi-journal-x', 'tw-stat--warn'],
            ];
        @endphp

        @foreach($cards as [$label, $value, $icon, $tone])
            <div class="tw-stat {{ $tone }}">
                <span class="tw-stat__icon"><i class="bi {{ $icon }}"></i></span>
                <span>
                    <span class="tw-stat__value d-block">{{ number_format($value) }}</span>
                    <span class="tw-stat__label d-block">{{ $label }}</span>
                </span>
            </div>
        @endforeach
    </div>

    @if($notPlanned->isNotEmpty())
        <div class="tp-check tp-check--warn mb-3">
            <p class="tp-check__title">
                <i class="bi bi-person-exclamation"></i>
                {{ $notPlanned->count() }} nhân viên chưa lập kế hoạch tuần này
            </p>
            <ul>
                @foreach($notPlanned as $row)
                    <li>
                        {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }} —
                        <a href="{{ route('technical.manager.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">
                            mở kế hoạch
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Theo nhân viên</h2>
        </div>
        @include('technical.partials.staff-table', ['rows' => $rows, 'detailMode' => 'manager', 'weekStart' => $weekStart])
    </div>

</div>
@endsection
