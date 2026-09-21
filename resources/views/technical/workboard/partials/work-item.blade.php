{{--
    Một dòng công việc trong danh sách.
    Dùng chung cho Tổng quan và Công việc của tôi — không nhân bản markup.

    @var \App\Support\Technical\TechnicalWorkItem $item
--}}
@php
    $tone = $item->sourceTone();
    $reportLabels = [
        'none' => ['Chưa báo cáo', 'text-secondary', 'bi-circle'],
        \App\Models\Technical\TechnicalDailyReport::STATUS_DRAFT => ['Báo cáo nháp', 'text-secondary', 'bi-pencil'],
        \App\Models\Technical\TechnicalDailyReport::STATUS_SUBMITTED => ['Đã gửi · chờ duyệt', 'text-warning', 'bi-clock-history'],
        \App\Models\Technical\TechnicalDailyReport::STATUS_APPROVED => ['Báo cáo đã duyệt', 'text-success', 'bi-check-circle-fill'],
        \App\Models\Technical\TechnicalDailyReport::STATUS_REVISION => ['Bị yêu cầu sửa', 'text-danger', 'bi-arrow-counterclockwise'],
    ];
    [$reportLabel, $reportClass, $reportIcon] = $reportLabels[$item->reportStatus] ?? $reportLabels['none'];
@endphp

<div class="tw-item tw-item--{{ $tone }}">
    <div class="tw-item__bar"></div>

    <div class="tw-item__main">
        <p class="tw-item__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</p>

        <div class="tw-item__meta">
            <span class="tw-source tw-source--{{ $tone }}">
                <i class="bi bi-diagram-3"></i>{{ $item->sourceLabel() }}
            </span>

            @if($item->siteName)
                <span><i class="bi bi-buildings"></i>{{ $item->siteCode ? $item->siteCode.' · ' : '' }}{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->siteName) }}</span>
            @else
                <span class="text-muted"><i class="bi bi-buildings"></i>Chưa gắn công trình</span>
            @endif

            <span><i class="bi bi-person"></i>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->assignedUserName ?: 'Chưa phân công') }}</span>

            @if($item->dueDate)
                <span class="{{ $item->isOverdue ? 'tw-overdue' : '' }}">
                    <i class="bi bi-calendar-event"></i>
                    Hạn {{ $item->dueDate->format('d/m/Y') }}
                    @if($item->isOverdue) (quá hạn) @endif
                </span>
            @else
                <span class="text-muted"><i class="bi bi-calendar-x"></i>Chưa có hạn</span>
            @endif

            <span class="{{ $reportClass }}"><i class="bi {{ $reportIcon }}"></i>{{ $reportLabel }}</span>
        </div>
    </div>

    <div class="tw-item__side">
        <span class="badge text-bg-light border">{{ $item->statusGroupLabel() }}</span>

        <div class="tw-progress" role="img"
             aria-label="Tiến độ {{ $item->progress }}%"
             title="Tiến độ {{ $item->progress }}%">
            <div class="tw-progress__bar" style="width: {{ max(0, min(100, $item->progress)) }}%"></div>
        </div>
        <small class="text-muted">{{ $item->progress }}%</small>

        <div class="tw-item__actions">
            @if($item->url)
                <a href="{{ $item->url }}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> Xem
                </a>
            @endif

            @if($item->isActive() && $item->assignedUserId === auth()->id())
                <a href="{{ route('technical.daily-reports.create', ['source_type' => $item->sourceType, 'source_id' => $item->sourceId]) }}"
                   class="btn btn-sm btn-primary">
                    <i class="bi bi-journal-plus"></i> Báo cáo
                </a>
            @endif
        </div>
    </div>
</div>
