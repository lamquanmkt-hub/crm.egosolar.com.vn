@extends('layouts.app')

@section('title', 'Kế hoạch & Giao việc Kỹ thuật')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
{{--
    KẾ HOẠCH & GIAO VIỆC KỸ THUẬT — trang của Admin / Giám đốc.

    NGHIỆP VỤ MỚI 2026-09: Admin/Giám đốc ĐƯỢC tạo kế hoạch và giao việc như
    trưởng phòng. Các thao tác GHI vẫn nằm ở đúng bàn điều phối đã có
    (`technical.manager.*`) — ở đây chỉ là LIÊN KẾT GET dẫn sang, nên trang này
    vẫn không chứa form POST / `_method` / `@csrf`. Nhờ vậy không có hai form
    giao việc song song và mọi điều chỉnh vẫn bắt buộc lý do + ghi nhật ký.
--}}
<!-- TECHNICAL_DASHBOARD_PLANS_CONTENT_START -->
<div class="container-fluid py-3 tw-wrap">

    @include('technical.partials.dashboard-breadcrumb', [
        'trail' => [
            'Kỹ thuật' => route('technical.dashboard'),
            'Kế hoạch & Giao việc' => null,
        ],
    ])

    @include('technical.partials.plan-header', [
        'variant' => 'admin',
        'title' => 'Kế hoạch & Giao việc Kỹ thuật',
        'subtitle' => 'Lập kế hoạch, phân công và theo dõi công việc của đội Kỹ thuật theo tuần.',
        'weekParam' => $weekStart->toDateString(),
        'guideSlug' => 'admin-ke-hoach-giao-viec',
    ])

    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    {{-- ---------- Chọn tuần: kỳ trước / hiện tại / sau ---------- --}}
    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.plans', array_merge(request()->except(['week', 'offset', 'page']), ['week' => $prevWeek])) }}">
                <i class="bi bi-chevron-left"></i> Kỳ trước
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.plans', array_merge(request()->except(['week', 'offset', 'page']), ['week' => $thisWeek])) }}">
                Kỳ hiện tại
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.plans', array_merge(request()->except(['week', 'offset', 'page']), ['week' => $nextWeek])) }}">
                Kỳ sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <span class="tp-weekbar__range"><i class="bi bi-calendar-range"></i> {{ $rangeLabel }}</span>
    </div>

    {{-- ---------- Bộ lọc (form GET, chỉ xem) ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__body">
            <form method="GET" action="{{ route('technical.dashboard.plans') }}" class="tp-filters">
                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">

                <div>
                    <label class="form-label small" for="p-user">Nhân viên</label>
                    <select class="form-select form-select-sm" id="p-user" name="user_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="p-site">Công trình</label>
                    <select class="form-select form-select-sm" id="p-site" name="site_id">
                        <option value="">Tất cả công trình</option>
                        @foreach($siteOptions as $site)
                            <option value="{{ $site->id }}" @selected((int) ($filters['site_id'] ?? 0) === (int) $site->id)>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="form-label small" for="p-status">Trạng thái kế hoạch</label>
                    <select class="form-select form-select-sm" id="p-status" name="plan_status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach($planStatusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['plan_status'] ?? '') === $key)>{{ $label }}</option>
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

    {{-- ---------- Sáu thẻ tổng hợp ---------- --}}
    <div class="tp-cards tp-cards--six mb-3">
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

    {{-- ---------- Bảng kế hoạch theo nhân viên ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Bảng theo dõi kế hoạch tuần</h2>
        </div>

        <div class="tw-scroll tp-data-scroll">
            <table class="tp-data">
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        <th>Tuần</th>
                        <th class="tp-data__num">Số ngày đã lập</th>
                        <th class="tp-data__num">Số công việc</th>
                        <th class="tp-data__num">Giờ dự kiến</th>
                        <th>Trạng thái</th>
                        <th class="text-center">Chi tiết</th>
                        @if($canAssign)<th class="text-center">Hành động</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td>
                                <strong>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</strong>
                            </td>
                            <td>{{ $rangeLabel }}</td>
                            <td class="tp-data__num">{{ $row['days_planned'] }} / 7 ngày</td>
                            <td class="tp-data__num">{{ $row['item_count'] }}</td>
                            <td class="tp-data__num">{{ $planner->minutesLabel($row['estimated_minutes']) }}</td>
                            <td>
                                @php
                                    $planTone = match ($row['plan_status_key']) {
                                        \App\Models\Technical\TechnicalWeekPlan::STATUS_FINALIZED => 'tp-state--success',
                                        \App\Models\Technical\TechnicalWeekPlan::STATUS_ADJUSTED => 'tp-state--warning',
                                        \App\Models\Technical\TechnicalWeekPlan::STATUS_DRAFT => 'tp-state--info',
                                        default => 'tp-state--danger',
                                    };
                                @endphp
                                <span class="tp-state {{ $planTone }}">{{ $row['plan_status_label'] }}</span>

                                @if($row['risk_items'] > 0)
                                    <span class="badge bg-warning text-dark ms-1"
                                          title="Có cảnh báo quá tải hoặc trùng lịch">
                                        <i class="bi bi-exclamation-triangle"></i> {{ $row['risk_items'] }} cảnh báo
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a class="btn btn-sm btn-outline-primary"
                                   href="{{ route('technical.dashboard.plans.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">
                                    <i class="bi bi-eye"></i> Chi tiết
                                </a>
                            </td>

                            @if($canAssign)
                                {{-- Menu ba chấm: gom hành động, không nhồi nhiều nút rời vào bảng. --}}
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false"
                                                aria-label="Hành động với kế hoạch của {{ $row['user_name'] }}">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('technical.dashboard.plans.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">
                                                    <i class="bi bi-eye"></i> Chi tiết
                                                </a>
                                            </li>
                                            {{-- 2026-09: mở THẲNG drawer Giao việc ở bàn điều phối,
                                                 nhân viên + tuần đã chọn sẵn. --}}
                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('technical.manager.board', ['open' => 'assign', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id']]) }}">
                                                    <i class="bi bi-person-plus"></i> Giao thêm việc
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('technical.manager.board', ['open' => 'create', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id']]) }}">
                                                    <i class="bi bi-plus-lg"></i> Tạo kế hoạch
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('technical.manager.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">
                                                    <i class="bi bi-pencil-square"></i> Điều chỉnh
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canAssign ? 8 : 7 }}">
                                <div class="tw-empty">
                                    <i class="bi bi-calendar-x"></i>
                                    <p>Không có nhân sự kỹ thuật nào khớp bộ lọc trong tuần này</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Bản thẻ cho màn hình hẹp (không tràn ngang). --}}
        <div class="tp-cards-list">
            @forelse($rows as $row)
                <article class="tp-jobcard">
                    <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</p>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Tuần</span><span class="tp-jobcard__value">{{ $rangeLabel }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Số ngày đã lập</span><span class="tp-jobcard__value">{{ $row['days_planned'] }} / 7</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Số công việc</span><span class="tp-jobcard__value">{{ $row['item_count'] }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Giờ dự kiến</span><span class="tp-jobcard__value">{{ $planner->minutesLabel($row['estimated_minutes']) }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Trạng thái</span><span class="tp-jobcard__value">{{ $row['plan_status_label'] }}</span></div>
                    <div class="tp-jobcard__row">
                        <span class="tp-jobcard__label">Chi tiết</span>
                        <span class="tp-jobcard__value">
                            <a href="{{ route('technical.dashboard.plans.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">Kế hoạch 7 ngày</a>
                        </span>
                    </div>
                    @if($canAssign)
                        <div class="tp-jobcard__actions">
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('technical.manager.board', ['open' => 'assign', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id']]) }}">
                                Giao thêm việc
                            </a>
                        </div>
                    @endif
                </article>
            @empty
                <div class="tw-empty"><p>Không có nhân sự kỹ thuật nào khớp bộ lọc trong tuần này</p></div>
            @endforelse
        </div>
    </div>

</div>
<!-- TECHNICAL_DASHBOARD_PLANS_CONTENT_END -->
@endsection
