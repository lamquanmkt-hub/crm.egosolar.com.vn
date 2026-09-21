@extends('layouts.app')

@section('title', 'Tổng quan Kỹ thuật')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
{{--
    DASHBOARD ADMIN / GIÁM ĐỐC — TRANG CHỈ XEM.
    Quy ước bắt buộc của màn hình này:
      - Không có form POST/PUT/DELETE nào.
      - Không có nút giao việc, sửa kế hoạch, duyệt báo cáo, can thiệp tiến độ.
      - Chỉ có: bộ lọc (GET), chuyển kỳ và link mở chi tiết dữ liệu nguồn.
--}}
<!-- TECHNICAL_DASHBOARD_CONTENT_START -->
<div class="container-fluid py-3 tw-wrap">

    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Trang chủ</a></li>
            <li class="breadcrumb-item">Kỹ thuật</li>
            <li class="breadcrumb-item active" aria-current="page">Tổng quan</li>
        </ol>
    </nav>

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Tổng quan Kỹ thuật</h1>
            <p class="tw-head__sub">
                Dashboard kết quả Kỹ thuật — Chế độ xem tổng hợp {{ $mode === 'month' ? 'theo tháng' : 'theo tuần' }} — {{ $rangeLabel }}.
                Trang này chỉ hiển thị kết quả giám sát, không thao tác vận hành.
            </p>
        </div>
        <div class="tw-head__actions">
            @include('technical.guides.partials.help-button', ['slug' => 'admin-ke-hoach-giao-viec'])
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ---------- Chọn kỳ ---------- --}}
    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard', array_merge(request()->except(['date']), ['date' => $prev])) }}">
                <i class="bi bi-chevron-left"></i> Kỳ trước
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard', array_merge(request()->except(['date']), ['date' => $current])) }}">
                Kỳ hiện tại
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard', array_merge(request()->except(['date']), ['date' => $next])) }}">
                Kỳ sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <div class="tp-weekbar__nav">
            <a class="btn btn-sm {{ $mode === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.dashboard', array_merge(request()->except(['mode']), ['mode' => 'week'])) }}">
                Theo tuần
            </a>
            <a class="btn btn-sm {{ $mode === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.dashboard', array_merge(request()->except(['mode']), ['mode' => 'month'])) }}">
                Theo tháng
            </a>
        </div>

        <span class="tp-weekbar__range"><i class="bi bi-calendar-range"></i> {{ $rangeLabel }}</span>
    </div>

    {{-- ---------- Bộ lọc (GET, không phải thao tác vận hành) ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__body">
            <form method="GET" action="{{ route('technical.dashboard') }}" class="tp-filters">
                <input type="hidden" name="mode" value="{{ $mode }}">
                <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">

                <div>
                    <label class="form-label small" for="d-user">Nhân viên</label>
                    <select class="form-select form-select-sm" id="d-user" name="user_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="d-site">Công trình</label>
                    <select class="form-select form-select-sm" id="d-site" name="site_id">
                        <option value="">Tất cả công trình</option>
                        @foreach($siteOptions as $site)
                            <option value="{{ $site->id }}" @selected((int) ($filters['site_id'] ?? 0) === (int) $site->id)>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="d-status">Trạng thái công việc</label>
                    <select class="form-select form-select-sm" id="d-status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-funnel"></i> Áp dụng bộ lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ---------- ĐÚNG 6 thẻ ở hàng đầu (3 thẻ/hàng desktop, 2 tablet, 1–2 mobile) ---------- --}}
    <div class="tp-cards tp-cards--six">
        @foreach($cards as $card)
            <div class="tw-stat {{ $card['tone'] }}">
                <span class="tw-stat__icon"><i class="bi {{ $card['icon'] }}"></i></span>
                <span>
                    <span class="tw-stat__value d-block">{{ number_format($card['value']) }}</span>
                    <span class="tw-stat__label d-block">{{ $card['label'] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- Khối tỷ lệ NGẮN — công thức nằm trong tooltip/popover, không in dài trên trang. --}}
    @php
        $rateFormula = 'Tỷ lệ hoàn thành = số việc hoàn thành / số việc đã lên kế hoạch (trừ việc đã huỷ). '
            .'Tỷ lệ nộp báo cáo = số dòng kế hoạch đã tới ngày và có báo cáo hoàn tất / số dòng kế hoạch đã tới ngày. '
            .'Mẫu số bằng 0 hiển thị N/A.';
    @endphp
    <div class="tw-note mb-3">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Tỷ lệ hoàn thành {{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($summary['completion_rate']) }}</strong>
            · Tỷ lệ nộp báo cáo {{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($summary['report_rate']) }}
            · Giờ kế hoạch {{ $planner->minutesLabel($summary['estimated_minutes']) }}
            / giờ thực tế {{ $planner->minutesLabel($summary['actual_minutes']) }}
            <button type="button" class="btn btn-link btn-sm p-0 align-baseline tp-info"
                    data-bs-toggle="popover" data-bs-trigger="focus click hover"
                    data-bs-placement="top" data-bs-title="Cách tính"
                    data-bs-content="{{ $rateFormula }}"
                    title="{{ $rateFormula }}"
                    aria-label="Giải thích cách tính các tỷ lệ">
                <i class="bi bi-info-circle"></i>
            </button>
        </div>
    </div>

    {{-- ---------- Chi tiết: các số liệu đã chuyển khỏi hàng thẻ đầu ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__head"><h2 class="tw-card__title">Chi tiết</h2></div>
        <div class="tw-card__body">
            <dl class="tp-detail-list">
                @foreach($detailCards as $detail)
                    <div class="tp-detail-list__row">
                        <dt>{{ $detail['label'] }}</dt>
                        <dd>{{ number_format($detail['value']) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="tw-card mb-3">
        <div class="tw-card__head"><h2 class="tw-card__title">Biểu đồ tuần/tháng</h2></div>
        <div class="tw-card__body">
            @include('technical.partials.charts', ['trend' => $trend, 'rows' => $rows, 'summary' => $summary])
        </div>
    </div>

    <div class="tw-card mb-3">
        <div class="tw-card__head"><h2 class="tw-card__title">Kết quả theo nhân viên</h2></div>
        @include('technical.partials.staff-table', ['rows' => $rows, 'detailMode' => 'readonly'])
    </div>

    <div class="tw-card">
        <div class="tw-card__head"><h2 class="tw-card__title">Kết quả theo công trình</h2></div>
        @include('technical.partials.site-table', ['sites' => $sites])
    </div>

</div>
<!-- TECHNICAL_DASHBOARD_CONTENT_END -->
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.bootstrap || !window.bootstrap.Popover) { return; }
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
            new window.bootstrap.Popover(el);
        });
    });
</script>
@endpush
