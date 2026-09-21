@extends('layouts.app')

@section('title', 'Kế hoạch nhân viên')

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
<div class="container-fluid py-3 tw-wrap">

    @include('technical.partials.plan-header', [
        'variant' => 'manager',
        'drawer' => true,
        'title' => 'Kế hoạch nhân viên',
        'subtitle' => 'Lập và giao kế hoạch làm việc của đội Kỹ thuật theo tuần. Giờ làm việc chuẩn: '.$planner->minutesLabel($dailyMinutes).'/ngày.',
        'weekParam' => $weekStart->toDateString(),
        'guideSlug' => 'truong-phong-ke-hoach-giao-viec',
        'extra' => '<a href="'.e(route('technical.manager.weekly-summary', ['week' => $weekStart->toDateString()])).'" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-data"></i> Tổng kết tuần</a>',
    ])

    @include('technical.workboard.partials.flash')

    @if(session('success') && session('success_link'))
        <p class="mb-3">
            <a class="btn btn-sm btn-outline-primary" href="{{ session('success_link') }}">
                <i class="bi bi-eye"></i> {{ session('success_link_label', 'Xem chi tiết') }}
            </a>
        </p>
    @endif

    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.board', array_merge(request()->except('week'), ['week' => $prevWeek])) }}">
                <i class="bi bi-chevron-left"></i> Tuần trước
            </a>
            <a class="btn btn-sm {{ $weekStart->toDateString() === $thisWeek ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('technical.manager.board', array_merge(request()->except('week'), ['week' => $thisWeek])) }}">
                Tuần hiện tại
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('technical.manager.board', array_merge(request()->except('week'), ['week' => $nextWeek])) }}">
                Tuần sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>
        <span class="tp-weekbar__range">
            <i class="bi bi-calendar-range"></i>
            {{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}
        </span>
    </div>

    <div class="tw-card mb-3">
        <div class="tw-card__body">
            <form method="GET" action="{{ route('technical.manager.board') }}" class="tp-filters">
                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                <div>
                    <label class="form-label small" for="f-user">Nhân viên</label>
                    <select class="form-select form-select-sm" id="f-user" name="user_id">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small" for="f-site">Công trình</label>
                    <select class="form-select form-select-sm" id="f-site" name="site_id">
                        <option value="">Tất cả công trình</option>
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}" @selected((int) ($filters['site_id'] ?? 0) === (int) $site->id)>
                                {{ $site->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small" for="f-status">Trạng thái việc</label>
                    <select class="form-select form-select-sm" id="f-status" name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach($statusOptions as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-funnel"></i> Lọc
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="tw-card">
        <div class="tw-card__head">
            <h2 class="tw-card__title">Ma trận kế hoạch tuần</h2>
            <span class="form-text mb-0">Công cụ THEO DÕI lịch toàn đội. Bấm một ô ngày để giao việc ngay cho nhân viên vào đúng ngày đó.</span>
        </div>

        <div class="tp-matrix-scroll">
            <table class="tp-matrix">
                <thead>
                    <tr>
                        <th>Nhân viên</th>
                        @foreach($days as $day)
                            <th>
                                {{ $planner->weekdayLabel($day) }}<br>
                                <span class="tp-data__muted">{{ $day->format('d/m') }}</span>
                            </th>
                        @endforeach
                        <th>Tổng giờ</th>
                        <th>Tình trạng</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($matrix as $row)
                        <tr>
                            <td class="tp-matrix__name">
                                <a href="{{ route('technical.manager.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">
                                    {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}
                                </a>

                                {{-- Menu ba chấm: gom bốn thao tác của một nhân viên, đặt ngay
                                     cạnh tên để ma trận không phải gánh thêm một cột. --}}
                                <div class="dropdown tp-matrix__menu">
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false"
                                            aria-label="Thao tác với kế hoạch của {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('technical.manager.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">
                                                <i class="bi bi-eye"></i> Xem chi tiết
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" data-tp-assign-cell data-tp-open-create="1"
                                               data-tp-user="{{ $row['user_id'] }}"
                                               href="{{ route('technical.manager.board', ['open' => 'create', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id']]) }}">
                                                <i class="bi bi-plus-lg"></i> Tạo kế hoạch
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" data-tp-assign-cell
                                               data-tp-user="{{ $row['user_id'] }}"
                                               href="{{ route('technical.manager.board', ['open' => 'assign', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id']]) }}">
                                                <i class="bi bi-person-plus"></i> Giao thêm việc
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('technical.manager.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}#tp-adjust">
                                                <i class="bi bi-sliders"></i> Điều chỉnh
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>

                            @foreach($days as $day)
                                @php $cell = $row['cells'][$day->toDateString()] ?? null; @endphp
                                <td class="{{ ($cell['overloaded'] ?? false) ? 'tp-cell--overload' : '' }} {{ ($cell['overdue'] ?? 0) > 0 ? 'tp-cell--overdue' : '' }}">
                                    {{-- Bấm ô ngày = mở drawer Giao việc với nhân viên + ngày CHỌN SẴN.
                                         Không-JS: href deep-link mở đúng drawer đó ở server. --}}
                                    <a class="tp-cell {{ ($cell && $cell['count'] > 0) ? '' : 'tp-cell--empty' }}"
                                       data-tp-assign-cell
                                       data-tp-user="{{ $row['user_id'] }}"
                                       data-tp-date="{{ $day->toDateString() }}"
                                       title="Giao việc cho {{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }} ngày {{ $day->format('d/m') }}"
                                       href="{{ route('technical.manager.board', ['open' => 'assign', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id'], 'date' => $day->toDateString()]) }}">
                                        @if($cell && $cell['count'] > 0)
                                            <span class="tp-cell__count">{{ $cell['count'] }} việc</span>
                                            <span class="tp-cell__site">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($cell['main_site'] ?? 'Không gắn công trình') }}</span>
                                            <span class="tp-cell__hours">{{ $planner->minutesLabel($cell['minutes']) }}</span>
                                            @if($cell['overloaded'])
                                                <span class="tp-cell__hours"><i class="bi bi-exclamation-triangle"></i> Quá tải</span>
                                            @endif
                                            @if($cell['overdue'] > 0)
                                                <span class="tp-cell__hours"><i class="bi bi-clock-history"></i> {{ $cell['overdue'] }} quá hạn</span>
                                            @endif
                                        @else
                                            <span class="tp-cell__count">—</span>
                                            <span class="tp-cell__site">Giao việc</span>
                                        @endif
                                    </a>
                                </td>
                            @endforeach

                            <td class="tp-data__num">{{ $planner->minutesLabel($row['total_minutes']) }}</td>
                            <td>
                                @if($row['total_items'] === 0)
                                    <span class="tp-state tp-state--danger">Chưa lập kế hoạch</span>
                                @elseif($row['plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_FINALIZED)
                                    <span class="tp-state tp-state--success">Đã hoàn tất</span>
                                @elseif($row['plan_status'] === \App\Models\Technical\TechnicalWeekPlan::STATUS_ADJUSTED)
                                    <span class="tp-state tp-state--warning">Đã điều chỉnh</span>
                                @else
                                    <span class="tp-state tp-state--info">Đang lập</span>
                                @endif

                                @if($row['has_overload'])
                                    <span class="tp-state tp-state--warning">Quá tải</span>
                                @endif
                                @if($row['update_requested'])
                                    <span class="tp-state tp-state--danger">Chờ cập nhật</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($days) + 3 }}">
                                <div class="tw-empty">
                                    <i class="bi bi-people"></i>
                                    <p>Chưa có nhân sự kỹ thuật nào trong phạm vi quản lý</p>
                                    <small>Nhân sự được nhận diện qua role kỹ thuật hoặc phòng ban Kỹ thuật.</small>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile: không ép ma trận desktop xuống màn hình hẹp. Mỗi nhân viên là
             một accordion, bên trong chỉ giữ đúng phần tóm tắt theo ngày. --}}
        <div class="tp-matrix-mobile">
            @forelse($matrix as $row)
                <details class="tp-person-card" @if($loop->first) open @endif>
                    <summary class="tp-person-card__head">
                        <span>
                            <strong>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($row['user_name']) }}</strong>
                            <small>{{ $row['total_items'] }} việc · {{ $planner->minutesLabel($row['total_minutes']) }}</small>
                        </span>
                        <span class="tp-state {{ $row['total_items'] === 0 ? 'tp-state--danger' : 'tp-state--info' }}">
                            {{ $row['total_items'] === 0 ? 'Chưa lập' : ($row['update_requested'] ? 'Chờ cập nhật' : 'Xem 7 ngày') }}
                        </span>
                    </summary>

                    <div class="tp-person-card__days">
                        @foreach($days as $day)
                            @php $cell = $row['cells'][$day->toDateString()] ?? null; @endphp
                            <a class="tp-person-day {{ ($cell['overloaded'] ?? false) ? 'tp-person-day--warn' : '' }} {{ ($cell['overdue'] ?? 0) > 0 ? 'tp-person-day--danger' : '' }}"
                               data-tp-assign-cell
                               data-tp-user="{{ $row['user_id'] }}"
                               data-tp-date="{{ $day->toDateString() }}"
                               href="{{ route('technical.manager.board', ['open' => 'assign', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id'], 'date' => $day->toDateString()]) }}">
                                <span class="tp-person-day__date">{{ $planner->weekdayLabel($day) }} <small>{{ $day->format('d/m') }}</small></span>
                                @if($cell && $cell['count'] > 0)
                                    <strong>{{ $cell['count'] }} việc · {{ $planner->minutesLabel($cell['minutes']) }}</strong>
                                    <span>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($cell['main_site'] ?? 'Không gắn công trình') }}</span>
                                    @if($cell['overloaded'])<em>Quá tải</em>@endif
                                    @if($cell['overdue'] > 0)<em>{{ $cell['overdue'] }} quá hạn</em>@endif
                                @else
                                    <span>Chưa có việc</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    <div class="tp-person-card__tools">
                        <a class="btn btn-sm btn-outline-primary"
                           href="{{ route('technical.manager.detail', ['member' => $row['user_id'], 'week' => $weekStart->toDateString()]) }}">
                            Xem chi tiết và điều phối
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" data-tp-assign-cell data-tp-open-create="1"
                           data-tp-user="{{ $row['user_id'] }}"
                           href="{{ route('technical.manager.board', ['open' => 'create', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id']]) }}">
                            Tạo kế hoạch
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" data-tp-assign-cell
                           data-tp-user="{{ $row['user_id'] }}"
                           href="{{ route('technical.manager.board', ['open' => 'assign', 'week' => $weekStart->toDateString(), 'user_id' => $row['user_id']]) }}">
                            Giao thêm việc
                        </a>
                    </div>
                </details>
            @empty
                <div class="tw-empty"><p>Chưa có nhân sự kỹ thuật trong phạm vi quản lý</p></div>
            @endforelse
        </div>
    </div>

    @include('technical.manager.partials.plan-drawers')

</div>
@endsection
