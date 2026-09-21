@extends('layouts.app')

@section('title', 'Tổng quan Kỹ thuật')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1.0.0' }}">
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Tổng quan Kỹ thuật</h1>
            <p class="tw-head__sub">
                Số liệu tổng hợp trực tiếp từ Công trình, Task nội bộ và Bảo trì/Bảo hành — hôm nay {{ $todayLabel }}.
            </p>
        </div>
        <div class="tw-head__actions">
            @include('technical.guides.partials.help-button', ['slug' => 'nhan-vien-tong-quan'])
            <a href="{{ route('technical.week-plan.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-calendar-week"></i> Mở kế hoạch
            </a>
            <a href="{{ route('technical.daily-reports.create') }}" class="btn btn-primary">
                <i class="bi bi-journal-plus"></i> Viết báo cáo
            </a>
        </div>
    </div>

    @include('technical.workboard.partials.nav')
    @include('technical.workboard.partials.flash')

    {{--
        ĐƠN GIẢN HOÁ (2026-09): chỉ giữ 4 chỉ số nhân viên thực sự cần nhìn mỗi
        ngày. Các số còn lại (công trình đang phụ trách, bảo hành đang xử lý,
        báo cáo chờ duyệt) vẫn tính ở `TechnicalWorkFeedService::summary()` và
        hiển thị đầy đủ ở màn hình quản lý / dashboard — không mất số liệu.
    --}}
    <div class="tw-stats tw-stats--4">
        @php
            $cards = [
                ['Việc hôm nay', $summary['today_items'], 'bi-calendar-check', '', \App\Services\Technical\TechnicalWorkFeedService::FILTER_TODAY],
                ['Việc đang thực hiện', $summary['in_progress_items'], 'bi-hourglass-split', 'tw-stat--info', null],
                ['Việc quá hạn', $summary['overdue_items'], 'bi-exclamation-octagon', 'tw-stat--danger', \App\Services\Technical\TechnicalWorkFeedService::FILTER_OVERDUE],
                ['Báo cáo chưa nộp', $summary['unreported_items'], 'bi-journal-x', 'tw-stat--warn', \App\Services\Technical\TechnicalWorkFeedService::FILTER_UNREPORTED],
            ];
        @endphp

        @foreach($cards as [$label, $value, $icon, $modifier, $filterKey])
            @php
                $href = $filterKey
                    ? route('ky-thuat.tong-quan', array_merge(request()->except(['filter', 'page']), ['filter' => $filterKey]))
                    : null;
            @endphp
            @if($href)
                <a class="tw-stat {{ $modifier }} text-decoration-none text-reset" href="{{ $href }}">
                    <span class="tw-stat__icon"><i class="bi {{ $icon }}"></i></span>
                    <span>
                        <span class="tw-stat__value d-block">{{ number_format($value) }}</span>
                        <span class="tw-stat__label d-block">{{ $label }}</span>
                    </span>
                </a>
            @else
                <div class="tw-stat {{ $modifier }}">
                    <span class="tw-stat__icon"><i class="bi {{ $icon }}"></i></span>
                    <span>
                        <span class="tw-stat__value d-block">{{ number_format($value) }}</span>
                        <span class="tw-stat__label d-block">{{ $label }}</span>
                    </span>
                </div>
            @endif
        @endforeach
    </div>

    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Ưu tiên hôm nay</h2>

            <div class="tw-chips">
                @foreach($filterOptions as $key => $label)
                    <a class="tw-chip {{ ($filters['filter'] ?? '') === $key ? 'is-active' : '' }}"
                       href="{{ route('ky-thuat.tong-quan', array_merge(request()->except(['filter', 'page']), ['filter' => $key])) }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        @if($canManage && $teamMembers->isNotEmpty())
            <div class="tw-card__body border-bottom">
                <form method="GET" action="{{ route('ky-thuat.tong-quan') }}" class="tw-filters">
                    <input type="hidden" name="filter" value="{{ $filters['filter'] ?? 'all' }}">
                    <div>
                        <label class="form-label small text-muted mb-1" for="tw-user">Nhân sự</label>
                        <select id="tw-user" name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">Tất cả nhân sự</option>
                            @foreach($teamMembers as $member)
                                <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Lọc</button></noscript>
                </form>
            </div>
        @endif

        <div class="tw-card__body tw-card__body--flush">
            @forelse($items as $item)
                @include('technical.workboard.partials.work-item', ['item' => $item])
            @empty
                <div class="tw-empty">
                    <i class="bi bi-clipboard-check"></i>
                    <p>Không có công việc nào khớp bộ lọc</p>
                    <small>Hôm nay chưa có công việc trong kế hoạch. Bạn vẫn có thể báo cáo việc phát sinh hoặc lập kế hoạch mới.</small>
                    <div class="tw-empty__actions mt-3">
                        <a href="{{ route('technical.daily-reports.create', ['mode' => 'phat-sinh']) }}" class="btn btn-primary">
                            <i class="bi bi-lightning-charge"></i> Báo cáo việc phát sinh
                        </a>
                        <a href="{{ route('technical.week-plan.index') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-calendar-week"></i> Lập kế hoạch
                        </a>
                    </div>
                </div>
            @endforelse
        </div>

        @if($items->hasPages())
            <div class="tw-card__body border-top">
                {{ $items->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
