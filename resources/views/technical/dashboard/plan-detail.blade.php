@extends('layouts.app')

@section('title', 'Kế hoạch Kỹ thuật — '.\App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name))

@push('styles')
    @include('technical.partials.plan-styles')
@endpush

@section('content')
{{--
    CHI TIẾT KẾ HOẠCH 7 NGÀY CỦA MỘT NHÂN VIÊN.

    NGHIỆP VỤ MỚI 2026-09: Admin/Giám đốc và trưởng phòng được giao thêm việc và
    điều chỉnh ngay từ đây — nhưng bằng LIÊN KẾT GET dẫn sang đúng bàn điều phối
    (`technical.manager.detail`), nơi form ghi thật sự sống và nơi lý do + nhật
    ký được ép buộc. Trang này vẫn không có form POST, không @csrf.
    Mục sidebar đang active vẫn là "Kế hoạch & Giao việc".
--}}
<!-- TECHNICAL_DASHBOARD_PLANS_CONTENT_START -->
<div class="container-fluid py-3 tw-wrap">

    @include('technical.partials.dashboard-breadcrumb', [
        'trail' => [
            'Kỹ thuật' => route('technical.dashboard'),
            'Kế hoạch & Giao việc' => route('technical.dashboard.plans', ['week' => $weekStart->toDateString()]),
            \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) => null,
        ],
    ])

    @php
        /*
            Người có quyền quản trị kế hoạch (Admin/Giám đốc, trưởng phòng) được
            thấy lối vào thao tác. Mọi lối vào đều là liên kết GET sang bàn điều
            phối — không có form ghi trên trang này.
        */
        $planEditorAllowed = (bool) ($canAssign ?? false);

        $planTotalItems = $itemsByDay->flatten()->count();
        $planTotalMinutes = (int) $itemsByDay->flatten()->sum(fn ($i) => (int) ($i->estimated_minutes ?? 0));
    @endphp

    @include('technical.partials.plan-header', [
        'variant' => 'admin',
        'title' => 'Kế hoạch Kỹ thuật — '.\App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name),
        'subtitle' => 'Kế hoạch 7 ngày, tuần '.$weekStart->format('d/m/Y').' – '.$weekEnd->format('d/m/Y').'.',
        'weekParam' => $weekStart->toDateString(),
        'memberId' => $member->id,
    ])

    {{-- ---------- Tóm tắt kế hoạch tuần ---------- --}}
    <div class="tw-card mb-3">
        <div class="tw-card__body">
            <dl class="tp-summary">
                <div><dt>Nhân viên</dt><dd>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($member->name) }}</dd></div>
                <div><dt>Tuần</dt><dd>{{ $weekStart->format('d/m/Y') }} – {{ $weekEnd->format('d/m/Y') }}</dd></div>
                <div><dt>Trạng thái</dt><dd>{{ $plan === null ? \App\Models\Technical\TechnicalWeekPlan::LABEL_NOT_STARTED : $plan->statusLabel() }}</dd></div>
                <div><dt>Tổng giờ dự kiến</dt><dd>{{ $planner->minutesLabel($planTotalMinutes) }}</dd></div>
                <div><dt>Tổng số công việc</dt><dd>{{ $planTotalItems }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="tp-weekbar">
        <div class="tp-weekbar__nav">
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.plans.detail', ['member' => $member->id, 'week' => $prevWeek]) }}">
                <i class="bi bi-chevron-left"></i> Kỳ trước
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.plans.detail', ['member' => $member->id, 'week' => $thisWeek]) }}">
                Kỳ hiện tại
            </a>
            <a class="btn btn-sm btn-outline-secondary"
               href="{{ route('technical.dashboard.plans.detail', ['member' => $member->id, 'week' => $nextWeek]) }}">
                Kỳ sau <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <span class="tp-state {{ $plan === null ? 'tp-state--danger' : 'tp-state--info' }}">
            {{ $plan === null ? \App\Models\Technical\TechnicalWeekPlan::LABEL_NOT_STARTED : $plan->statusLabel() }}
        </span>

        <span class="tp-weekbar__spacer"></span>

        <a class="btn btn-sm btn-outline-secondary"
           href="{{ route('technical.dashboard.plans', ['week' => $weekStart->toDateString()]) }}">
            <i class="bi bi-arrow-left"></i> Về danh sách kế hoạch
        </a>
    </div>

    {{-- Cảnh báo trùng lịch / quá tải — dùng lại đúng bộ kiểm tra của Kế hoạch tuần. --}}
    @include('technical.partials.week-checks', ['check' => $check])

    @foreach($days as $day)
        @php
            $key = $day->toDateString();
            $dayItems = $itemsByDay->get($key, collect());
            $mark = $dayMarks->get($key);
        @endphp

        <div class="tp-day {{ $day->isToday() ? 'tp-day--today' : '' }}">
            <div class="tp-day__head">
                <span class="tp-day__title">{{ mb_strtoupper($planner->weekdayLabel($day)) }}</span>
                <span class="tp-day__date">– {{ $day->format('d/m/Y') }}</span>
                <span class="tp-day__meta">
                    <span class="tp-state">{{ $dayItems->count() }} việc</span>
                    @if($mark)<span class="tp-state tp-state--info">{{ $mark->markLabel() }}</span>@endif
                    @if($planEditorAllowed)
                        <a class="btn btn-sm btn-outline-primary tp-day__add"
                           href="{{ route('technical.manager.board', ['open' => 'assign', 'week' => $weekStart->toDateString(), 'user_id' => $member->id, 'date' => $day->toDateString()]) }}">
                            <i class="bi bi-plus-lg"></i> Thêm công việc
                        </a>
                    @endif
                </span>
            </div>

            <div class="tp-day__body">
                @if($dayItems->isEmpty())
                    <div class="tw-empty"><i class="bi bi-dash-circle"></i><p>Không có việc trong ngày này</p></div>
                @else
                    <table class="tp-table">
                        <thead>
                            <tr>
                                <th>Nội dung</th>
                                <th class="tp-col-mid">Công trình</th>
                                <th class="tp-col-mid">Nguồn công việc</th>
                                <th class="tp-col-narrow">Thời gian dự kiến</th>
                                <th class="tp-col-narrow">Trạng thái</th>
                                <th class="tp-col-mid">Ghi chú</th>
                                @if($planEditorAllowed)<th class="tp-col-narrow">Thao tác</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dayItems as $item)
                                <tr>
                                    <td>
                                        <span class="tp-table__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</span>
                                        <span class="tp-table__sub">{{ $item->dayPartLabel() }} · {{ $item->priorityLabel() }}</span>
                                    </td>
                                    <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</td>
                                    <td>{{ $item->sourceLabel() }}</td>
                                    <td>{{ $planner->minutesLabel($item->estimated_minutes) }}</td>
                                    <td><span class="tp-state">{{ $item->statusLabel() }}</span></td>
                                    <td>{{ $item->note ?: '—' }}</td>
                                    @if($planEditorAllowed)
                                        <td>
                                            <a href="{{ route('technical.manager.detail', ['member' => $member->id, 'week' => $weekStart->toDateString(), 'item' => $item->id]) }}#adj-{{ $item->id }}">
                                                <i class="bi bi-pencil-square"></i> Điều chỉnh
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="tp-cards-list">
                        @foreach($dayItems as $item)
                            <div class="tp-jobcard">
                                <p class="tp-jobcard__title">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->title) }}</p>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Công trình</span><span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($item->site_name ?? '—') }}</span></div>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Nguồn công việc</span><span class="tp-jobcard__value">{{ $item->sourceLabel() }}</span></div>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Thời gian dự kiến</span><span class="tp-jobcard__value">{{ $planner->minutesLabel($item->estimated_minutes) }}</span></div>
                                <div class="tp-jobcard__row"><span class="tp-jobcard__label">Trạng thái</span><span class="tp-jobcard__value">{{ $item->statusLabel() }}</span></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endforeach

    {{-- ---------- Lịch sử điều chỉnh (chỉ đọc) ---------- --}}
    <div class="tw-card">
        <div class="tw-card__head"><h2 class="tw-card__title">Lịch sử điều chỉnh kế hoạch</h2></div>

        <div class="tw-scroll tp-data-scroll">
            <table class="tp-data">
                <thead>
                    <tr>
                        <th>Thời điểm</th>
                        <th>Người thao tác</th>
                        <th>Hành động</th>
                        <th>Lý do</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($histories as $history)
                        <tr>
                            <td>{{ $history->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($history->user->name ?? $history->user_name ?? '—') }}</td>
                            <td>{{ $history->actionLabel() }}</td>
                            <td>{{ $history->reason ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="tw-empty">
                                    <i class="bi bi-clock-history"></i>
                                    <p>Chưa có điều chỉnh nào được ghi nhận quanh tuần này</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="tp-cards-list">
            @forelse($histories as $history)
                <article class="tp-jobcard">
                    <p class="tp-jobcard__title">{{ $history->actionLabel() }}</p>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Thời điểm</span><span class="tp-jobcard__value">{{ $history->created_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Người thao tác</span><span class="tp-jobcard__value">{{ \App\Services\Technical\TechnicalWeekPlanService::displayLabel($history->user->name ?? $history->user_name ?? '—') }}</span></div>
                    <div class="tp-jobcard__row"><span class="tp-jobcard__label">Lý do</span><span class="tp-jobcard__value">{{ $history->reason ?: '—' }}</span></div>
                </article>
            @empty
                <div class="tw-empty"><p>Chưa có điều chỉnh nào được ghi nhận quanh tuần này</p></div>
            @endforelse
        </div>
    </div>

</div>
<!-- TECHNICAL_DASHBOARD_PLANS_CONTENT_END -->
@endsection
