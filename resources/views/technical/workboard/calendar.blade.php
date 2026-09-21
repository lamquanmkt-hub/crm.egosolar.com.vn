@extends('layouts.app')

@section('title', 'Lịch công việc kỹ thuật')

@push('styles')
    <link rel="stylesheet"
          href="{{ asset('css/ego-technical-work.css') }}?v={{ file_exists(public_path('css/ego-technical-work.css')) ? filemtime(public_path('css/ego-technical-work.css')) : '1.0.0' }}">
@endpush

@section('content')
@php
    $dows = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
    $baseQuery = array_merge(request()->except(['date', 'mode']), ['mode' => $mode]);
@endphp
<div class="container-fluid py-3 tw-wrap">

    <div class="tw-head">
        <div>
            <h1 class="tw-head__title">Lịch công việc kỹ thuật</h1>
            <p class="tw-head__sub">
                {{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }} · {{ number_format($total) }} đầu việc.
                Cùng một nguồn dữ liệu với Tổng quan và Công việc của tôi.
            </p>
        </div>

        <div class="tw-head__actions">
            <div class="btn-group" role="group" aria-label="Chế độ xem">
                <a class="btn btn-sm {{ $mode === 'week' ? 'btn-primary' : 'btn-outline-secondary' }}"
                   href="{{ route('technical.work.calendar', array_merge(request()->except(['mode']), ['mode' => 'week'])) }}">Tuần</a>
                <a class="btn btn-sm {{ $mode === 'month' ? 'btn-primary' : 'btn-outline-secondary' }}"
                   href="{{ route('technical.work.calendar', array_merge(request()->except(['mode']), ['mode' => 'month'])) }}">Tháng</a>
            </div>

            <div class="btn-group" role="group" aria-label="Điều hướng thời gian">
                <a class="btn btn-sm btn-outline-secondary"
                   href="{{ route('technical.work.calendar', array_merge($baseQuery, ['date' => $prev])) }}"
                   title="{{ $mode === 'month' ? 'Tháng trước' : 'Tuần trước' }}">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="{{ route('technical.work.calendar', array_merge($baseQuery, ['date' => now()->toDateString()])) }}">
                    Hôm nay
                </a>
                <a class="btn btn-sm btn-outline-secondary"
                   href="{{ route('technical.work.calendar', array_merge($baseQuery, ['date' => $next])) }}"
                   title="{{ $mode === 'month' ? 'Tháng sau' : 'Tuần sau' }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    @include('technical.workboard.partials.nav')
    @include('technical.workboard.partials.flash')

    <div class="tw-card">
        <div class="tw-card__head">
            <div class="tw-legend">
                <span><i style="background:#2563eb"></i> Công trình</span>
                <span><i style="background:#7c3aed"></i> Task nội bộ</span>
                <span><i style="background:#0f9d7b"></i> Bảo trì / Bảo hành</span>
            </div>

            @if($canManage && $teamMembers->isNotEmpty())
                <form method="GET" action="{{ route('technical.work.calendar') }}" class="d-flex gap-2 align-items-center">
                    <input type="hidden" name="mode" value="{{ $mode }}">
                    <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">
                    <label class="small text-muted mb-0" for="cal-user">Nhân sự</label>
                    <select id="cal-user" name="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Tất cả</option>
                        @foreach($teamMembers as $member)
                            <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Lọc</button></noscript>
                </form>
            @endif
        </div>

        <div class="tw-card__body tw-card__body--flush">

            {{-- Lưới lịch: desktop / tablet --}}
            <div class="p-3">
                <div class="tw-cal">
                    @foreach($dows as $dow)
                        <div class="tw-cal__dow">{{ $dow }}</div>
                    @endforeach

                    @foreach($days as $day)
                        <div class="tw-cal__day {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['in_focus'] ? '' : 'is-muted' }}">
                            <span class="tw-cal__date">{{ $day['date']->format('d/m') }}</span>

                            @foreach($day['items']->take(4) as $item)
                                <a class="tw-event tw-event--{{ $item->sourceTone() }}"
                                   href="{{ $item->url ?: route('technical.work.my', ['date' => $day['key']]) }}"
                                   @if($item->url) target="_blank" rel="noopener" @endif
                                   title="{{ $item->title }} · {{ $item->siteName }} · {{ $item->assignedUserName }}">
                                    {{ $item->title }}
                                </a>
                            @endforeach

                            @if($day['items']->count() > 4)
                                <a class="small text-muted text-decoration-none"
                                   href="{{ route('technical.work.my', array_merge(request()->only('user_id'), ['date' => $day['key']])) }}">
                                    +{{ $day['items']->count() - 4 }} việc khác
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Danh sách theo ngày: màn hình nhỏ --}}
            <div class="tw-agenda">
                @php $agendaDays = collect($days)->filter(fn ($d) => $d['items']->isNotEmpty()); @endphp

                @forelse($agendaDays as $day)
                    <div class="tw-agenda__day">
                        <div class="tw-agenda__date">
                            {{ $day['date']->format('d/m/Y') }}
                            <small>· {{ $day['items']->count() }} việc @if($day['is_today']) · hôm nay @endif</small>
                        </div>
                        @foreach($day['items'] as $item)
                            <a class="tw-event tw-event--{{ $item->sourceTone() }} mb-1"
                               href="{{ $item->url ?: route('technical.work.my', ['date' => $day['key']]) }}"
                               @if($item->url) target="_blank" rel="noopener" @endif>
                                {{ $item->title }} · {{ $item->siteName ?: 'Chưa gắn công trình' }}
                            </a>
                        @endforeach
                    </div>
                @empty
                    <div class="tw-empty">
                        <i class="bi bi-calendar-x"></i>
                        <p>Không có công việc nào trong khoảng này</p>
                        <small>Chuyển sang tuần/tháng khác hoặc bỏ bộ lọc nhân sự.</small>
                    </div>
                @endforelse
            </div>

            @if($total === 0)
                <div class="tw-empty d-none d-md-block">
                    <i class="bi bi-calendar-x"></i>
                    <p>Không có công việc nào trong khoảng này</p>
                    <small>Công việc chỉ hiện lên lịch khi có ngày thực hiện hoặc hạn hoàn thành.</small>
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
