@extends('layouts.app')

@section('title', 'Công việc hôm nay')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Công việc hôm nay</h1>
            <p class="tw-head__sub">
                {{ $weekdayLabel }}, {{ $date->format('d/m/Y') }} — các thao tác ở đây chỉ cập nhật
                dòng kế hoạch của bạn, không thay đổi dữ liệu gốc của Công trình.
            </p>
        </div>
        <div class="tw-head__actions">
            <a href="{{ route('technical.week-plan.index', ['week' => $date->toDateString()]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-calendar-week"></i> Mở kế hoạch
            </a>
            <a href="{{ route('technical.daily-reports.create', ['date' => $date->toDateString()]) }}" class="btn btn-primary">
                <i class="bi bi-journal-plus"></i> Viết báo cáo
            </a>
        </div>
    </div>

    @include('technical.workboard.partials.flash')

    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.today', ['date' => $prevDate]) }}">
                <i class="bi bi-chevron-left"></i> Hôm trước
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.today') }}">Hôm nay</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.today', ['date' => $nextDate]) }}">
                Hôm sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <span class="tp-weekbar__spacer"></span>
        <span class="tp-state">{{ $items->count() }} việc theo kế hoạch</span>
    </div>

    <div class="tw-card">
        <div class="tw-card__body tw-card__body--flush">
            @if($items->isEmpty())
                <div class="tw-empty">
                    <i class="bi bi-calendar-check"></i>
                    <p>Hôm nay bạn chưa có việc nào trong kế hoạch</p>
                    <small>Hôm nay chưa có công việc trong kế hoạch. Bạn vẫn có thể báo cáo việc phát sinh hoặc lập kế hoạch mới.</small>
                    <div class="tw-empty__actions mt-3">
                        <a href="{{ route('technical.daily-reports.create', ['mode' => 'phat-sinh', 'date' => $date->toDateString()]) }}" class="btn btn-primary">
                            <i class="bi bi-lightning-charge"></i> Báo cáo việc phát sinh
                        </a>
                        <a href="{{ route('technical.week-plan.index', ['week' => $date->toDateString()]) }}" class="btn btn-outline-secondary">
                            <i class="bi bi-calendar-week"></i> Lập kế hoạch
                        </a>
                    </div>
                </div>
            @else
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th class="tp-col-narrow">Thời gian</th>
                            <th>Nội dung công việc</th>
                            <th class="tp-col-mid">Công trình</th>
                            <th>Mục tiêu hôm nay</th>
                            <th class="tp-col-narrow">Tiến độ</th>
                            <th class="tp-col-narrow">Trạng thái</th>
                            <th class="tp-col-narrow">Báo cáo</th>
                            <th class="tp-col-actions">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @php $report = $reportMap[$item->id] ?? null; @endphp
                            <tr>
                                <td>{{ $item->dayPartLabel() }}</td>
                                <td>
                                    <span class="tp-table__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</span>
                                    <span class="tp-table__sub">{{ $item->sourceLabel() }} · {{ $item->priorityLabel() }}</span>
                                </td>
                                <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</td>
                                <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->objective ?: '—') }}</td>
                                <td>{{ $item->progress_percent }}%</td>
                                <td><span class="tp-state">{{ $item->statusLabel() }}</span></td>
                                <td>
                                    @if($report)
                                        <a href="{{ route('technical.daily-reports.show', $report->id) }}">
                                            {{ \App\Models\Technical\TechnicalDailyReport::BUSINESS_STATUS_LABELS[$report->status] ?? $report->status }}
                                        </a>
                                    @else
                                        <span class="tp-data__muted">Chưa có</span>
                                    @endif
                                </td>
                                <td class="tp-table__actions">
                                    <a class="btn btn-sm btn-outline-secondary" href="#today-{{ $item->id }}"
                                       data-bs-toggle="collapse" role="button"
                                       aria-expanded="false" aria-controls="today-{{ $item->id }}">
                                        <i class="bi bi-gear"></i> Cập nhật
                                    </a>
                                </td>
                            </tr>
                            <tr class="collapse" id="today-{{ $item->id }}">
                                <td colspan="8">
                                    @include('technical.week-plan.partials.today-actions', [
                                        'item' => $item,
                                        'report' => $report,
                                        'statusOptions' => $statusOptions,
                                    ])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="tp-cards-list">
                    @foreach($items as $item)
                        @php $report = $reportMap[$item->id] ?? null; @endphp
                        <div class="tp-jobcard">
                            <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</p>
                            <div class="tp-jobcard__row">
                                <span class="tp-jobcard__label">Thời gian</span>
                                <span class="tp-jobcard__value">{{ $item->dayPartLabel() }}</span>
                            </div>
                            <div class="tp-jobcard__row">
                                <span class="tp-jobcard__label">Công trình</span>
                                <span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</span>
                            </div>
                            <div class="tp-jobcard__row">
                                <span class="tp-jobcard__label">Mục tiêu</span>
                                <span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->objective ?: '—') }}</span>
                            </div>
                            <div class="tp-jobcard__row">
                                <span class="tp-jobcard__label">Tiến độ</span>
                                <span class="tp-jobcard__value">{{ $item->progress_percent }}%</span>
                            </div>
                            <div class="tp-jobcard__row">
                                <span class="tp-jobcard__label">Trạng thái</span>
                                <span class="tp-jobcard__value">{{ $item->statusLabel() }}</span>
                            </div>
                            <div class="collapse show mt-2">
                                @include('technical.week-plan.partials.today-actions', [
                                    'item' => $item,
                                    'report' => $report,
                                    'statusOptions' => $statusOptions,
                                    'idPrefix' => 'm',
                                ])
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
