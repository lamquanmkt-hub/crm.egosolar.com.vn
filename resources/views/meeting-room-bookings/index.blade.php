@extends('layouts.app')

@section('title', 'Booking phòng họp')

@push('styles')
    <link
        rel="stylesheet"
        href="{{ asset('css/meeting-room-booking-promax.css') }}?v={{ filemtime(public_path('css/meeting-room-booking-promax.css')) }}"
    >
@endpush

@php
    $hasCreateErrors = session('open_booking_modal') === 'create'
        || ($errors->any() && ! session('editing_booking_id'));

    $queryForMonth = static function (string $monthValue) use ($room, $usageStatus): array {
        return array_filter([
            'month' => $monthValue,
            'room_name' => $room,
            'usage_status' => $usageStatus,
        ], static fn ($value) => $value !== '');
    };

    $dayNames = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];
    $mobileBookingGroups = $monthBookings->groupBy(
        static fn ($booking) => \Carbon\Carbon::parse($booking->start_at)->toDateString()
    );
@endphp

@section('content')
<div
    class="mrb3-page"
    id="meetingRoomBookingPage"
    data-open-create="{{ $hasCreateErrors ? '1' : '0' }}"
    data-open-edit="{{ session('open_booking_modal') === 'edit' ? (int) session('editing_booking_id') : '' }}"
    data-update-template="{{ route('meeting-room-bookings.update', ['booking' => '__BOOKING__']) }}"
    data-delete-template="{{ route('meeting-room-bookings.destroy', ['booking' => '__BOOKING__']) }}"
>
    <div class="mrb3-shell">
        <header class="mrb3-header">
            <div class="mrb3-header__title">
                <span class="mrb3-header__eyebrow">
                    <i class="bi bi-calendar3"></i>
                    Lịch phòng họp
                </span>
                <h1>Booking phòng họp</h1>
                <p>Đặt lịch theo tháng, tự động chặn trùng phòng và khung giờ.</p>
            </div>

            <div class="mrb3-header__actions">
                <div class="mrb3-mini-stats" aria-label="Thống kê tháng">
                    <div class="mrb3-mini-stat">
                        <span>Tổng lịch</span>
                        <strong>{{ number_format($stats['month_total']) }}</strong>
                    </div>
                    <div class="mrb3-mini-stat is-unused">
                        <span>Chưa sử dụng</span>
                        <strong>{{ number_format($stats['unused']) }}</strong>
                    </div>
                    <div class="mrb3-mini-stat is-used">
                        <span>Đã sử dụng</span>
                        <strong>{{ number_format($stats['used']) }}</strong>
                    </div>
                </div>

                <button
                    type="button"
                    class="mrb3-btn mrb3-btn--primary"
                    data-create-booking
                >
                    <i class="bi bi-plus-lg"></i>
                    Tạo booking
                </button>
            </div>
        </header>

        @if(session('success'))
            <div class="mrb3-alert mrb3-alert--success" role="status">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->has('schedule_conflict'))
            <div class="mrb3-alert mrb3-alert--danger" role="alert">
                <i class="bi bi-calendar-x-fill"></i>
                <span>{{ $errors->first('schedule_conflict') }}</span>
            </div>
        @endif

        <section class="mrb3-calendar-card">
            <div class="mrb3-toolbar">
                <div class="mrb3-month-nav">
                    <a
                        class="mrb3-icon-btn"
                        href="{{ route('meeting-room-bookings.index', $queryForMonth($previousMonth)) }}"
                        aria-label="Tháng trước"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </a>

                    <div class="mrb3-month-nav__label">
                        <span>Lịch tháng</span>
                        <strong>{{ $monthLabel }}</strong>
                    </div>

                    <a
                        class="mrb3-icon-btn"
                        href="{{ route('meeting-room-bookings.index', $queryForMonth($nextMonth)) }}"
                        aria-label="Tháng sau"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </a>

                    @if($monthStart->format('Y-m') !== $currentMonth)
                        <a
                            class="mrb3-today-link"
                            href="{{ route('meeting-room-bookings.index', $queryForMonth($currentMonth)) }}"
                        >
                            Hôm nay
                        </a>
                    @endif
                </div>

                <form method="GET" class="mrb3-filters">
                    <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">

                    <label class="mrb3-filter">
                        <i class="bi bi-door-open"></i>
                        <select name="room_name" aria-label="Lọc theo phòng">
                            <option value="">Tất cả phòng</option>
                            @foreach($rooms as $roomOption)
                                <option value="{{ $roomOption }}" @selected($room === $roomOption)>
                                    {{ $roomOption }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="mrb3-filter">
                        <i class="bi bi-activity"></i>
                        <select name="usage_status" aria-label="Lọc trạng thái sử dụng">
                            <option value="">Tất cả trạng thái</option>
                            @foreach($usageStatuses as $value => $label)
                                <option value="{{ $value }}" @selected($usageStatus === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <button class="mrb3-btn mrb3-btn--filter" type="submit">
                        <i class="bi bi-funnel"></i>
                        Lọc
                    </button>

                    @if($room !== '' || $usageStatus !== '')
                        <a
                            href="{{ route('meeting-room-bookings.index', ['month' => $monthStart->format('Y-m')]) }}"
                            class="mrb3-clear-filter"
                        >
                            Xóa lọc
                        </a>
                    @endif
                </form>
            </div>

            <div class="mrb3-calendar" aria-label="Lịch booking theo tháng">
                <div class="mrb3-weekdays">
                    @foreach($dayNames as $dayName)
                        <div>{{ $dayName }}</div>
                    @endforeach
                </div>

                <div class="mrb3-month-grid">
                    @foreach($calendarDays as $calendarDay)
                        @php
                            $dateKey = $calendarDay->toDateString();
                            $dayBookings = $bookingsByDate->get($dateKey, collect());
                            $isCurrentMonth = $calendarDay->month === $monthStart->month;
                            $isToday = $calendarDay->isToday();
                            $visibleBookings = $dayBookings->take(3);
                            $remainingCount = max(0, $dayBookings->count() - $visibleBookings->count());
                        @endphp

                        <article
                            class="mrb3-day {{ $isCurrentMonth ? '' : 'is-outside' }} {{ $isToday ? 'is-today' : '' }}"
                            data-create-date="{{ $dateKey }}"
                        >
                            <div class="mrb3-day__head">
                                <span class="mrb3-day__number">{{ $calendarDay->day }}</span>
                                @if($dayBookings->isNotEmpty())
                                    <span class="mrb3-day__count">{{ $dayBookings->count() }}</span>
                                @endif
                            </div>

                            <div class="mrb3-day__events">
                                @foreach($visibleBookings as $booking)
                                    @php
                                        $bookingStart = \Carbon\Carbon::parse($booking->start_at);
                                        $bookingEnd = \Carbon\Carbon::parse($booking->end_at);
                                        $isUsed = (string) $booking->usage_status === 'used';
                                    @endphp

                                    <button
                                        type="button"
                                        class="mrb3-event {{ $isUsed ? 'is-used' : 'is-unused' }}"
                                        data-edit-booking
                                        data-id="{{ $booking->id }}"
                                        data-room="{{ $booking->room_name }}"
                                        data-title="{{ $booking->title }}"
                                        data-organizer="{{ (string) $booking->organizer_name }}"
                                        data-department="{{ (string) $booking->department }}"
                                        data-attendees="{{ (int) $booking->attendees }}"
                                        data-start="{{ $bookingStart->format('Y-m-d\TH:i') }}"
                                        data-end="{{ $bookingEnd->format('Y-m-d\TH:i') }}"
                                        data-usage="{{ (string) $booking->usage_status }}"
                                        data-note="{{ (string) $booking->note }}"
                                        title="{{ $bookingStart->format('H:i') }}–{{ $bookingEnd->format('H:i') }} · {{ $booking->title }}"
                                    >
                                        <span class="mrb3-event__time">{{ $bookingStart->format('H:i') }}</span>
                                        <span class="mrb3-event__title">{{ $booking->title }}</span>
                                        <span class="mrb3-event__room">{{ $booking->room_name }}</span>
                                    </button>
                                @endforeach

                                @if($remainingCount > 0)
                                    <span class="mrb3-more-events">+{{ $remainingCount }} lịch khác</span>
                                @endif
                            </div>

                            <button
                                type="button"
                                class="mrb3-day__add"
                                data-create-booking
                                data-date="{{ $dateKey }}"
                                aria-label="Tạo booking ngày {{ $calendarDay->format('d/m/Y') }}"
                            >
                                <i class="bi bi-plus"></i>
                            </button>
                        </article>
                    @endforeach
                </div>
            </div>

            <div class="mrb3-mobile-agenda">
                @forelse($mobileBookingGroups as $dateKey => $dateBookings)
                    @php
                        $agendaDate = \Carbon\Carbon::parse($dateKey);
                    @endphp
                    <section class="mrb3-agenda-day">
                        <div class="mrb3-agenda-day__date">
                            <strong>{{ $agendaDate->format('d') }}</strong>
                            <span>Tháng {{ $agendaDate->format('m') }}</span>
                        </div>

                        <div class="mrb3-agenda-day__items">
                            @foreach($dateBookings as $booking)
                                @php
                                    $bookingStart = \Carbon\Carbon::parse($booking->start_at);
                                    $bookingEnd = \Carbon\Carbon::parse($booking->end_at);
                                    $isUsed = (string) $booking->usage_status === 'used';
                                @endphp
                                <button
                                    type="button"
                                    class="mrb3-agenda-item {{ $isUsed ? 'is-used' : 'is-unused' }}"
                                    data-edit-booking
                                    data-id="{{ $booking->id }}"
                                    data-room="{{ $booking->room_name }}"
                                    data-title="{{ $booking->title }}"
                                    data-organizer="{{ (string) $booking->organizer_name }}"
                                    data-department="{{ (string) $booking->department }}"
                                    data-attendees="{{ (int) $booking->attendees }}"
                                    data-start="{{ $bookingStart->format('Y-m-d\TH:i') }}"
                                    data-end="{{ $bookingEnd->format('Y-m-d\TH:i') }}"
                                    data-usage="{{ (string) $booking->usage_status }}"
                                    data-note="{{ (string) $booking->note }}"
                                >
                                    <span class="mrb3-agenda-item__time">
                                        {{ $bookingStart->format('H:i') }}–{{ $bookingEnd->format('H:i') }}
                                    </span>
                                    <strong>{{ $booking->title }}</strong>
                                    <small>{{ $booking->room_name }}</small>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="mrb3-empty">
                        <i class="bi bi-calendar2-week"></i>
                        <strong>Tháng này chưa có booking</strong>
                        <span>Chọn “Tạo booking” để thêm lịch mới.</span>
                    </div>
                @endforelse
            </div>

            @if($monthBookings->isEmpty())
                <div class="mrb3-empty mrb3-empty--desktop">
                    <i class="bi bi-calendar2-week"></i>
                    <strong>Tháng này chưa có booking</strong>
                    <span>Chọn một ngày trên lịch hoặc bấm “Tạo booking”.</span>
                </div>
            @endif
        </section>
    </div>

    @include('meeting-room-bookings.partials.booking-modal', [
        'modalId' => 'createBookingModal',
        'formId' => 'createBookingForm',
        'title' => 'Tạo booking mới',
        'submitLabel' => 'Lưu booking',
        'action' => route('meeting-room-bookings.store'),
        'method' => 'POST',
        'isEdit' => false,
    ])

    @include('meeting-room-bookings.partials.booking-modal', [
        'modalId' => 'editBookingModal',
        'formId' => 'editBookingForm',
        'title' => 'Chỉnh sửa booking',
        'submitLabel' => 'Cập nhật',
        'action' => '#',
        'method' => 'PUT',
        'isEdit' => true,
    ])

    <form method="POST" id="deleteBookingForm" class="d-none">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@push('scripts')
    <script
        src="{{ asset('js/meeting-room-booking-promax.js') }}?v={{ filemtime(public_path('js/meeting-room-booking-promax.js')) }}"
        defer
    ></script>
@endpush
