@extends('layouts.app')

@section('title', 'Báo cáo Kỹ thuật')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
{{--
    BÁO CÁO KỸ THUẬT TUẦN / THÁNG — CHỈ XEM.

    Trang RIÊNG, KHÔNG phải bản sao của Tổng quan: H1 riêng, breadcrumb riêng,
    mốc nội dung riêng. Mọi con số do controller/service tính sẵn; Blade không
    truy vấn và không tính công thức.
--}}
<!-- TECHNICAL_DASHBOARD_REPORTS_CONTENT_START -->
<div class="container-fluid py-3 tw-wrap">

    @include('technical.partials.dashboard-breadcrumb', [
        'trail' => [
            'Kỹ thuật' => route('technical.dashboard'),
            'Báo cáo tuần/tháng' => null,
        ],
    ])

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Báo cáo Kỹ thuật</h1>
            <p class="tw-head__sub">
                Tổng hợp kết quả làm việc của đội Kỹ thuật theo tuần hoặc tháng — {{ $rangeLabel }}.
                Chế độ chỉ xem dành cho Ban giám đốc.
            </p>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ---------- Chuyển tuần/tháng & chọn kỳ ---------- --}}
    @php
        $reportBase = request()->except(['period', 'mode', 'week', 'month', 'date', 'page']);
    @endphp

    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.reports', array_merge($reportBase, $prevParams)) }}">
                <i class="bi bi-chevron-left"></i> Kỳ trước
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.reports', array_merge($reportBase, $currentParams)) }}">
                Kỳ hiện tại
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.reports', array_merge($reportBase, $nextParams)) }}">
                Kỳ sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <div class="tp-weekbar__nav" role="group" aria-label="Chế độ kỳ báo cáo">
            <a class="btn btn-sm {{ $period === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.dashboard.reports', array_merge($reportBase, $weekParams)) }}">
                Theo tuần
            </a>
            <a class="btn btn-sm {{ $period === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.dashboard.reports', array_merge($reportBase, $monthParams)) }}">
                Theo tháng
            </a>
        </div>

        <span class="tp-weekbar__range"><i class="bi bi-calendar-range"></i> {{ $rangeLabel }}</span>
    </div>

    {{-- ---------- Bộ lọc (form GET, chỉ xem) ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__body">
            <form method="GET" action="{{ route('technical.dashboard.reports') }}" class="tp-filters">
                <input type="hidden" name="period" value="{{ $period }}">
                @if($period === 'month')
                    <input type="hidden" name="month" value="{{ $anchor->format('Y-m') }}">
                @else
                    <input type="hidden" name="week" value="{{ $from->toDateString() }}">
                @endif

                <div>
                    <label class="form-label small" for="r-user">Nhân viên</label>
                    <select class="form-select form-select-sm" id="r-user" name="user_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="r-site">Công trình</label>
                    <select class="form-select form-select-sm" id="r-site" name="site_id">
                        <option value="">Tất cả công trình</option>
                        @foreach($siteOptions as $site)
                            <option value="{{ $site->id }}" @selected((int) ($filters['site_id'] ?? 0) === (int) $site->id)>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="r-status">Trạng thái công việc</label>
                    <select class="form-select form-select-sm" id="r-status" name="status">
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

    {{-- ---------- Tám thẻ tổng hợp của kỳ ---------- --}}
    <div class="tp-cards tp-cards--eight mb-3">
        @foreach($cards as $card)
            <div class="tw-stat {{ $card['tone'] ?? '' }}">
                <span class="tw-stat__icon"><i class="bi {{ $card['icon'] }}"></i></span>
                <span>
                    <span class="tw-stat__value d-block">{{ !empty($card['is_text']) ? $card['value'] : number_format((int) $card['value']) }}</span>
                    <span class="tw-stat__label d-block">{{ $card['label'] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    {{-- ---------- Bốn biểu đồ (số thật) ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__head">
            <h2 class="tw-card__title">
                Biểu đồ phân tích ({{ $period === 'month' ? '8 tuần gần nhất' : '6 tuần gần nhất' }})
            </h2>
        </div>
        <div class="tw-card__body">
            @include('technical.partials.charts', ['trend' => $trend, 'rows' => $rows, 'summary' => $summary])
        </div>
    </div>

    {{-- ---------- Kết quả theo nhân viên ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Kết quả theo nhân viên</h2>
        </div>

        <div class="tw-scroll tp-data-scroll">
            <table class="tp-data">
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th class="tp-data__num">Công việc kế hoạch</th>
                        <th class="tp-data__num">Hoàn thành</th>
                        <th class="tp-data__num">Quá hạn</th>
                        <th class="tp-data__num">Phát sinh</th>
                        <th class="tp-data__num">Báo cáo đã nộp</th>
                        <th class="tp-data__num">Báo cáo chưa nộp</th>
                        <th class="tp-data__num">Tỷ lệ hoàn thành</th>
                        <th class="text-center">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><strong>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</strong></td>
                            <td class="tp-data__num">{{ $row['planned_items'] }}</td>
                            <td class="tp-data__num">{{ $row['done_items'] }}</td>
                            <td class="tp-data__num">{{ $row['overdue_items'] }}</td>
                            <td class="tp-data__num">{{ $row['unplanned_items'] }}</td>
                            <td class="tp-data__num">{{ $row['reported_items'] }}</td>
                            <td class="tp-data__num">{{ $row['unreported_items'] }}</td>
                            <td class="tp-data__num">{{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($row['completion_rate']) }}</td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('technical.daily-reports.index', ['user_id' => $row['user_id']]) }}">
                                    <i class="bi bi-journal-text"></i> Xem báo cáo
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <div class="tw-empty">
                                    <i class="bi bi-people"></i>
                                    <p>Không có nhân sự kỹ thuật nào khớp bộ lọc trong kỳ này</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="tp-cards-list">
            @forelse($rows as $row)
                <article class="tp-jobcard">
                    <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</p>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Kế hoạch</span><span class="tp-jobcard__value">{{ $row['planned_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Hoàn thành</span><span class="tp-jobcard__value">{{ $row['done_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Quá hạn</span><span class="tp-jobcard__value">{{ $row['overdue_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Phát sinh</span><span class="tp-jobcard__value">{{ $row['unplanned_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Báo cáo đã nộp</span><span class="tp-jobcard__value">{{ $row['reported_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Báo cáo chưa nộp</span><span class="tp-jobcard__value">{{ $row['unreported_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Tỷ lệ hoàn thành</span><span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalPlanVsActualService::rateLabel($row['completion_rate']) }}</span></div>
                </article>
            @empty
                <div class="tw-empty"><p>Không có nhân sự kỹ thuật nào khớp bộ lọc trong kỳ này</p></div>
            @endforelse
        </div>
    </div>

    {{-- ---------- Kết quả theo công trình ---------- --}}
    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Kết quả theo công trình</h2>
        </div>

        <div class="tw-scroll tp-data-scroll">
            <table class="tp-data">
                <thead>
                    <tr>
                        <th>Công trình</th>
                        <th>Người phụ trách</th>
                        <th class="tp-data__num">Tổng công việc</th>
                        <th class="tp-data__num">Hoàn thành</th>
                        <th class="tp-data__num">Chưa hoàn thành</th>
                        <th class="tp-data__num">Quá hạn</th>
                        <th class="tp-data__num">Giờ thực tế</th>
                        <th class="text-center">Chi tiết</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($sites as $site)
                        <tr>
                            <td><strong>{{ $site['site_name'] }}</strong></td>
                            <td>{{ $site['lead_name'] ?? '—' }}</td>
                            <td class="tp-data__num">{{ $site['total_items'] }}</td>
                            <td class="tp-data__num">{{ $site['done_items'] }}</td>
                            <td class="tp-data__num">{{ $site['not_done_items'] }}</td>
                            <td class="tp-data__num">{{ $site['overdue_items'] }}</td>
                            <td class="tp-data__num">{{ $planner->minutesLabel($site['actual_minutes']) }}</td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="{{ route('technical.daily-reports.index', ['site_id' => $site['site_id']]) }}">
                                    <i class="bi bi-journal-text"></i> Xem báo cáo
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="tw-empty">
                                    <i class="bi bi-buildings"></i>
                                    <p>Không có công trình nào phát sinh công việc trong kỳ này</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="tp-cards-list">
            @forelse($sites as $site)
                <article class="tp-jobcard">
                    <p class="tp-jobcard__title">{{ $site['site_name'] }}</p>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Người phụ trách</span><span class="tp-jobcard__value">{{ $site['lead_name'] ?? '—' }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Tổng công việc</span><span class="tp-jobcard__value">{{ $site['total_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Hoàn thành</span><span class="tp-jobcard__value">{{ $site['done_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Chưa hoàn thành</span><span class="tp-jobcard__value">{{ $site['not_done_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Quá hạn</span><span class="tp-jobcard__value">{{ $site['overdue_items'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Giờ thực tế</span><span class="tp-jobcard__value">{{ $planner->minutesLabel($site['actual_minutes']) }}</span></div>
                </article>
            @empty
                <div class="tw-empty"><p>Không có công trình nào phát sinh công việc trong kỳ này</p></div>
            @endforelse
        </div>
    </div>

</div>
<!-- TECHNICAL_DASHBOARD_REPORTS_CONTENT_END -->
@endsection
