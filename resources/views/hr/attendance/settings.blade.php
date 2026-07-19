@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 attendance-settings-page">

    {{-- HERO --}}
    <div class="setting-hero mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="setting-kicker mb-2">Admin Attendance Settings</div>
                <h1 class="setting-title mb-2">Cài đặt chấm công</h1>
                <div class="setting-subtitle">
                    Quản lý giờ làm việc, cấu hình đi muộn, điều kiện đủ công và chính sách GPS cho toàn hệ thống.
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('hr.attendance.index') }}" class="btn setting-btn setting-btn-light">
                    <i class="bi bi-arrow-left me-1"></i> Quay lại thống kê
                </a>
            </div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-12 col-md-3">
                <div class="setting-hero-card">
                    <div class="setting-hero-label">Giờ bắt đầu hiện tại</div>
                    <div class="setting-hero-value">
                        {{ \Carbon\Carbon::parse($setting->work_start_time)->format('H:i') }}
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="setting-hero-card">
                    <div class="setting-hero-label">Giờ kết thúc hiện tại</div>
                    <div class="setting-hero-value">
                        {{ \Carbon\Carbon::parse($setting->work_end_time)->format('H:i') }}
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="setting-hero-card">
                    <div class="setting-hero-label">Chính sách GPS</div>
                    <div class="setting-hero-value">
                        {{ $setting->require_gps ? 'Bắt buộc' : 'Không bắt buộc' }}
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-3">
                <div class="setting-hero-card">
                    <div class="setting-hero-label">Phạt đi muộn</div>
                    <div class="setting-hero-value">
                        {{ number_format((int) ($setting->late_penalty_per_time ?? 0), 0, ',', '.') }} đ / lần
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success setting-alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <div class="fw-bold mb-2">Có lỗi xảy ra:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('hr.attendance.settings.update') }}">
        @csrf

        <div class="row g-4">

            {{-- CỘT TRÁI --}}
            <div class="col-12 col-xl-8">
                <div class="card setting-glass-card h-100">
                    <div class="card-header bg-transparent border-0 px-4 pt-4 pb-2">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <h5 class="fw-bold mb-1">Thiết lập ca làm việc</h5>
                                <div class="text-muted small">Cấu hình thời gian chuẩn để hệ thống tính đi muộn, về sớm và đủ công.</div>
                            </div>
                            <span class="setting-chip">Core Config</span>
                        </div>
                    </div>

                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label setting-label">Giờ bắt đầu làm</label>
                                <div class="setting-input-wrap">
                                    <span class="setting-input-icon"><i class="bi bi-sunrise"></i></span>
                                    <input type="time"
                                           name="work_start_time"
                                           class="form-control setting-input"
                                           value="{{ old('work_start_time', \Carbon\Carbon::parse($setting->work_start_time)->format('H:i')) }}">
                                </div>
                                <div class="setting-help">Nhân viên check-in sau mốc này sẽ bắt đầu bị tính đi muộn.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label setting-label">Giờ kết thúc làm</label>
                                <div class="setting-input-wrap">
                                    <span class="setting-input-icon"><i class="bi bi-sunset"></i></span>
                                    <input type="time"
                                           name="work_end_time"
                                           class="form-control setting-input"
                                           value="{{ old('work_end_time', \Carbon\Carbon::parse($setting->work_end_time)->format('H:i')) }}">
                                </div>
                                <div class="setting-help">Nhân viên check-out trước mốc này sẽ bị tính về sớm.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label setting-label">Số phút cho phép đi muộn</label>
                                <div class="setting-input-wrap">
                                    <span class="setting-input-icon"><i class="bi bi-clock-history"></i></span>
                                    <input type="number"
                                           name="late_grace_minutes"
                                           min="0"
                                           class="form-control setting-input"
                                           value="{{ old('late_grace_minutes', $setting->late_grace_minutes) }}">
                                </div>
                                <div class="setting-help">Ví dụ 5 phút nghĩa là 08:05 mới bắt đầu tính đi muộn.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label setting-label">Mức phạt mỗi lần đi muộn</label>
                                <div class="setting-input-wrap">
                                    <span class="setting-input-icon"><i class="bi bi-cash-stack"></i></span>
                                    <input type="number"
                                           name="late_penalty_per_time"
                                           min="0"
                                           class="form-control setting-input"
                                           value="{{ old('late_penalty_per_time', $setting->late_penalty_per_time ?? 0) }}">
                                </div>
                                <div class="setting-help">Ví dụ 50000 nghĩa là mỗi lần đi muộn sẽ bị trừ 50.000đ.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label setting-label">Số phút tối thiểu tính đủ công</label>
                                <div class="setting-input-wrap">
                                    <span class="setting-input-icon"><i class="bi bi-hourglass-split"></i></span>
                                    <input type="number"
                                           name="min_work_minutes"
                                           min="1"
                                           class="form-control setting-input"
                                           value="{{ old('min_work_minutes', $setting->min_work_minutes) }}">
                                </div>
                                <div class="setting-help">Ví dụ 480 phút tương đương 8 tiếng làm việc hợp lệ.</div>
                            </div>
                        </div>


                        {{-- LỊCH CÔNG CHUẨN --}}
                        <div class="setting-divider my-4"></div>

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Lịch công chuẩn</h5>
                                <div class="text-muted small">
                                    Cấu hình ngày nào phải đi làm để hệ thống tính công chuẩn, thiếu công và ngày nghỉ.
                                </div>
                            </div>
                            <span class="setting-chip">Work Calendar</span>
                        </div>

                        <div class="calendar-config-box mb-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="fw-bold mb-2">Ngày làm việc trong tuần</div>
                                    <div class="calendar-weekday-grid">
                                        @foreach([
                                            'workday_monday' => 'Thứ 2',
                                            'workday_tuesday' => 'Thứ 3',
                                            'workday_wednesday' => 'Thứ 4',
                                            'workday_thursday' => 'Thứ 5',
                                            'workday_friday' => 'Thứ 6',
                                        ] as $field => $label)
                                            <label class="calendar-check-card">
                                                <input type="checkbox"
                                                       name="{{ $field }}"
                                                       value="1"
                                                       {{ old($field, $setting->{$field} ?? true) ? 'checked' : '' }}>
                                                <span>{{ $label }}</span>
                                            </label>
                                        @endforeach

                                        <label class="calendar-check-card calendar-check-muted">
                                            <input type="checkbox"
                                                   name="workday_sunday"
                                                   value="1"
                                                   {{ old('workday_sunday', $setting->workday_sunday ?? false) ? 'checked' : '' }}>
                                            <span>Chủ nhật</span>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label setting-label">Chính sách làm việc thứ 7</label>
                                    <select name="saturday_mode" class="form-select setting-input calendar-select" id="saturday-mode">
                                        @foreach([
                                            'off' => 'Nghỉ tất cả thứ 7',
                                            'all' => 'Làm tất cả thứ 7',
                                            'odd' => 'Làm thứ 7 tuần lẻ',
                                            'even' => 'Làm thứ 7 tuần chẵn',
                                            'custom' => 'Chọn ngày thứ 7 cụ thể',
                                        ] as $value => $label)
                                            <option value="{{ $value }}" {{ old('saturday_mode', $setting->saturday_mode ?? 'odd') === $value ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="setting-help">
                                        Gợi ý: nếu công ty làm thứ 7 so le, chọn tuần lẻ hoặc tuần chẵn.
                                    </div>
                                </div>

                                <div class="col-md-6" id="saturday-custom-wrap">
                                    <label class="form-label setting-label">Ngày thứ 7 làm cụ thể</label>
                                    <input type="text"
                                           name="saturday_custom_dates"
                                           class="form-control setting-input calendar-plain-input"
                                           placeholder="Ví dụ: 2026-04-04, 2026-04-18"
                                           value="{{ old('saturday_custom_dates', is_array($setting->saturday_custom_dates ?? null) ? implode(', ', $setting->saturday_custom_dates) : ($setting->saturday_custom_dates ?? '')) }}">
                                    <div class="setting-help">
                                        Chỉ dùng khi chọn “Chọn ngày thứ 7 cụ thể”. Nhập dạng YYYY-MM-DD, cách nhau bằng dấu phẩy.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- NGÀY NGHỈ / NGÀY LỄ --}}
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Ngày nghỉ / ngày lễ trong tháng</h5>
                                <div class="text-muted small">
                                    Các ngày này sẽ không tính là ngày phải chấm công.
                                </div>
                            </div>
                            <span class="setting-chip">
                                {{ $holidayStart->format('d/m') }} - {{ $holidayEnd->format('d/m/Y') }}
                            </span>
                        </div>

                        <div class="holiday-panel">
                            <div class="row g-3 align-items-end mb-3">
                                <div class="col-md-4">
                                    <label class="form-label setting-label">Tháng cấu hình ngày nghỉ</label>
                                    <input type="month"
                                           name="holiday_month"
                                           class="form-control setting-input calendar-plain-input"
                                           value="{{ old('holiday_month', $holidayMonth ?? now()->format('Y-m')) }}">
                                </div>
                                <div class="col-md-8">
                                    <div class="holiday-note-box">
                                        Ví dụ tháng có 30/04, 01/05 hoặc ngày nghỉ nội bộ thì thêm ở đây. Dashboard và Excel sẽ bỏ các ngày này khỏi công chuẩn.
                                    </div>
                                </div>
                            </div>


                            @php
                                $holidayMap = $holidays->keyBy(function ($holiday) {
                                    return optional($holiday->holiday_date)->format('Y-m-d');
                                });

                                $calendarStart = $holidayStart->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
                                $calendarEnd = $holidayEnd->copy()->endOfWeek(\Carbon\Carbon::SUNDAY);
                                $calendarDays = [];

                                for ($day = $calendarStart->copy(); $day->lte($calendarEnd); $day->addDay()) {
                                    $calendarDays[] = $day->copy();
                                }
                            @endphp

                            
                            @php
                                $holidayDateSetForStats = $holidays->keyBy(function ($holiday) {
                                    return optional($holiday->holiday_date)->format('Y-m-d');
                                });

                                $rawSaturdayCustomDatesForStats = $setting->saturday_custom_dates ?? [];

                                if (is_string($rawSaturdayCustomDatesForStats)) {
                                    $decodedSaturdayCustomDates = json_decode($rawSaturdayCustomDatesForStats, true);
                                    $rawSaturdayCustomDatesForStats = is_array($decodedSaturdayCustomDates)
                                        ? $decodedSaturdayCustomDates
                                        : preg_split('/[\s,;]+/', $rawSaturdayCustomDatesForStats);
                                }

                                $saturdayCustomDatesForStats = collect($rawSaturdayCustomDatesForStats ?: [])
                                    ->filter()
                                    ->map(function ($date) {
                                        try {
                                            return \Carbon\Carbon::parse($date)->toDateString();
                                        } catch (\Throwable $e) {
                                            return null;
                                        }
                                    })
                                    ->filter()
                                    ->values()
                                    ->all();

                                $monthTotalDaysForStats = $holidayStart->daysInMonth;
                                $workdaysBeforeHolidayForStats = 0;
                                $standardWorkdaysForStats = 0;
                                $holidayDeductedForStats = 0;
                                $saturdayWorkdaysForStats = 0;

                                for ($statDay = $holidayStart->copy(); $statDay->lte($holidayEnd); $statDay->addDay()) {
                                    $dateKeyForStats = $statDay->toDateString();
                                    $isScheduledWorkdayForStats = false;

                                    if ($statDay->dayOfWeekIso === 1) {
                                        $isScheduledWorkdayForStats = (bool) ($setting->workday_monday ?? true);
                                    } elseif ($statDay->dayOfWeekIso === 2) {
                                        $isScheduledWorkdayForStats = (bool) ($setting->workday_tuesday ?? true);
                                    } elseif ($statDay->dayOfWeekIso === 3) {
                                        $isScheduledWorkdayForStats = (bool) ($setting->workday_wednesday ?? true);
                                    } elseif ($statDay->dayOfWeekIso === 4) {
                                        $isScheduledWorkdayForStats = (bool) ($setting->workday_thursday ?? true);
                                    } elseif ($statDay->dayOfWeekIso === 5) {
                                        $isScheduledWorkdayForStats = (bool) ($setting->workday_friday ?? true);
                                    } elseif ($statDay->dayOfWeekIso === 6) {
                                        $saturdayModeForStats = $setting->saturday_mode ?? 'odd';
                                        $weekOfMonthForStats = (int) ceil($statDay->day / 7);

                                        $isScheduledWorkdayForStats = match($saturdayModeForStats) {
                                            'all' => true,
                                            'odd' => $weekOfMonthForStats % 2 === 1,
                                            'even' => $weekOfMonthForStats % 2 === 0,
                                            'custom' => in_array($dateKeyForStats, $saturdayCustomDatesForStats, true),
                                            default => false,
                                        };

                                        if ($isScheduledWorkdayForStats) {
                                            $saturdayWorkdaysForStats++;
                                        }
                                    } elseif ($statDay->dayOfWeekIso === 7) {
                                        $isScheduledWorkdayForStats = (bool) ($setting->workday_sunday ?? false);
                                    }

                                    $isHolidayForStats = $holidayDateSetForStats->has($dateKeyForStats);

                                    if ($isScheduledWorkdayForStats) {
                                        $workdaysBeforeHolidayForStats++;

                                        if ($isHolidayForStats) {
                                            $holidayDeductedForStats++;
                                        } else {
                                            $standardWorkdaysForStats++;
                                        }
                                    }
                                }

                                $holidayCountForStats = $holidays->count();
                                $offDaysForStats = $monthTotalDaysForStats - $standardWorkdaysForStats;

                                $saturdayModeLabelForStats = match($setting->saturday_mode ?? 'odd') {
                                    'off' => 'Nghỉ tất cả thứ 7',
                                    'all' => 'Làm tất cả thứ 7',
                                    'odd' => 'Làm thứ 7 tuần lẻ',
                                    'even' => 'Làm thứ 7 tuần chẵn',
                                    'custom' => 'Chọn thứ 7 cụ thể',
                                    default => 'Làm thứ 7 tuần lẻ',
                                };
                            @endphp

                            <div class="workday-summary-grid mb-3">
                                <div class="workday-summary-card primary">
                                    <div class="workday-summary-label">Công chuẩn tháng</div>
                                    <div class="workday-summary-value">{{ $standardWorkdaysForStats }}</div>
                                    <div class="workday-summary-note">Ngày cần chấm công sau khi trừ nghỉ/lễ</div>
                                </div>

                                <div class="workday-summary-card">
                                    <div class="workday-summary-label">Công theo lịch</div>
                                    <div class="workday-summary-value">{{ $workdaysBeforeHolidayForStats }}</div>
                                    <div class="workday-summary-note">Trước khi trừ ngày nghỉ/lễ</div>
                                </div>

                                <div class="workday-summary-card danger">
                                    <div class="workday-summary-label">Ngày nghỉ trừ công</div>
                                    <div class="workday-summary-value">{{ $holidayDeductedForStats }}</div>
                                    <div class="workday-summary-note">Ngày nghỉ rơi vào ngày làm việc</div>
                                </div>

                                <div class="workday-summary-card">
                                    <div class="workday-summary-label">Thứ 7 đi làm</div>
                                    <div class="workday-summary-value">{{ $saturdayWorkdaysForStats }}</div>
                                    <div class="workday-summary-note">{{ $saturdayModeLabelForStats }}</div>
                                </div>

                                <div class="workday-summary-card muted">
                                    <div class="workday-summary-label">Tổng ngày nghỉ</div>
                                    <div class="workday-summary-value">{{ $offDaysForStats }}</div>
                                    <div class="workday-summary-note">Cuối tuần + ngày nghỉ/lễ</div>
                                </div>
                            </div>

<div class="holiday-calendar-widget mb-3">
                                <div class="holiday-calendar-head">
                                    <div>
                                        <div class="holiday-calendar-title">
                                            Lịch ngày nghỉ tháng {{ $holidayStart->format('m/Y') }}
                                        </div>
                                        <div class="holiday-calendar-desc">
                                            Bấm vào ngày để chọn/bỏ ngày nghỉ. Ngày được chọn sẽ không tính vào công chuẩn.
                                        </div>
                                    </div>
                                    <div class="holiday-calendar-legend">
                                        <span><i class="legend-dot workday"></i> Ngày phải đi làm</span>
                                        <span><i class="legend-dot selected"></i> Nghỉ lễ / nghỉ công ty</span>
                                        <span><i class="legend-dot weekend"></i> Nghỉ theo lịch</span>
                                    </div>
                                </div>

                                <div class="holiday-calendar-weekdays">
                                    <div>T2</div>
                                    <div>T3</div>
                                    <div>T4</div>
                                    <div>T5</div>
                                    <div>T6</div>
                                    <div>T7</div>
                                    <div>CN</div>
                                </div>

                                <div class="holiday-calendar-grid">
                                    @foreach($calendarDays as $calendarDay)
                                        @php
                                            $dateKey = $calendarDay->format('Y-m-d');
                                            $holiday = $holidayMap->get($dateKey);
                                            $isCurrentMonth = $calendarDay->month === $holidayStart->month;
                                            $isWeekend = $calendarDay->isWeekend();

                                            $isScheduledWorkday = false;

                                            if ($calendarDay->dayOfWeekIso === 1) {
                                                $isScheduledWorkday = (bool) ($setting->workday_monday ?? true);
                                            } elseif ($calendarDay->dayOfWeekIso === 2) {
                                                $isScheduledWorkday = (bool) ($setting->workday_tuesday ?? true);
                                            } elseif ($calendarDay->dayOfWeekIso === 3) {
                                                $isScheduledWorkday = (bool) ($setting->workday_wednesday ?? true);
                                            } elseif ($calendarDay->dayOfWeekIso === 4) {
                                                $isScheduledWorkday = (bool) ($setting->workday_thursday ?? true);
                                            } elseif ($calendarDay->dayOfWeekIso === 5) {
                                                $isScheduledWorkday = (bool) ($setting->workday_friday ?? true);
                                            } elseif ($calendarDay->dayOfWeekIso === 6) {
                                                $saturdayModeForCalendar = $setting->saturday_mode ?? 'odd';
                                                $weekOfMonthForCalendar = (int) ceil($calendarDay->day / 7);

                                                $isScheduledWorkday = match($saturdayModeForCalendar) {
                                                    'all' => true,
                                                    'odd' => $weekOfMonthForCalendar % 2 === 1,
                                                    'even' => $weekOfMonthForCalendar % 2 === 0,
                                                    'custom' => in_array($dateKey, $saturdayCustomDatesForStats ?? [], true),
                                                    default => false,
                                                };
                                            } elseif ($calendarDay->dayOfWeekIso === 7) {
                                                $isScheduledWorkday = (bool) ($setting->workday_sunday ?? false);
                                            }

                                            if ($isScheduledWorkday) {
                                                $defaultDayLabel = $calendarDay->dayOfWeekIso === 6 ? 'Đi làm T7' : 'Đi làm';
                                            } else {
                                                $defaultDayLabel = $isWeekend ? 'Nghỉ cuối tuần' : 'Nghỉ theo lịch';
                                            }
                                        @endphp

                                        <button type="button"
                                                class="holiday-calendar-day
                                                    {{ $isCurrentMonth ? '' : 'is-out-month' }}
                                                    {{ $isWeekend ? 'is-weekend' : '' }}
                                                    {{ $isScheduledWorkday ? 'is-workday' : 'is-offday' }}
                                                    {{ $holiday ? 'is-selected' : '' }}"
                                                data-date="{{ $dateKey }}"
                                                data-display-date="{{ $calendarDay->format('d/m/Y') }}"
                                                data-existing-id="{{ $holiday->id ?? '' }}"
                                                data-existing-name="{{ $holiday->name ?? '' }}"
                                                data-default-label="{{ $defaultDayLabel }}"
                                                {{ $isCurrentMonth ? '' : 'disabled' }}>
                                            <span class="day-number">{{ $calendarDay->format('d') }}</span>
                                            <span class="day-name">
                                                @if($holiday)
                                                    {{ $holiday->name }}
                                                @else
                                                    {{ $defaultDayLabel }}
                                                @endif
                                            </span>
                                        </button>
                                    @endforeach
                                </div>

                                <div id="holiday-hidden-inputs"></div>
                            </div>

                            <div class="table-responsive holiday-table-wrap legacy-holiday-table mb-3">
                                <table class="table align-middle mb-0 holiday-table">
                                    <thead>
                                        <tr>
                                            <th style="width:150px;">Ngày</th>
                                            <th>Tên ngày nghỉ</th>
                                            <th style="width:150px;">Loại</th>
                                            <th style="width:100px;">Có lương</th>
                                            <th>Ghi chú</th>
                                            <th style="width:80px;">Xoá</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($holidays as $holiday)
                                            <tr>
                                                <td>{{ optional($holiday->holiday_date)->format('d/m/Y') }}</td>
                                                <td class="fw-semibold">{{ $holiday->name }}</td>
                                                <td>
                                                    @php
                                                        $holidayTypeLabel = match($holiday->type) {
                                                            'holiday' => 'Nghỉ lễ',
                                                            'compensatory' => 'Nghỉ bù',
                                                            'company' => 'Nghỉ công ty',
                                                            default => 'Khác',
                                                        };
                                                    @endphp
                                                    <span class="holiday-badge">{{ $holidayTypeLabel }}</span>
                                                </td>
                                                <td>{{ $holiday->is_paid ? 'Có' : 'Không' }}</td>
                                                <td class="text-muted small">{{ $holiday->note ?: '-' }}</td>
                                                <td>
                                                    <label class="holiday-delete-check">
                                                        <input type="checkbox" name="delete_holiday_ids[]" value="{{ $holiday->id }}">
                                                        <span>Xoá</span>
                                                    </label>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    Chưa có ngày nghỉ/lễ trong tháng này.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>

                                    <tbody id="new-holiday-rows">
                                        <tr class="new-holiday-row">
                                            <td>
                                                <input type="date" name="new_holiday_date[]" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input type="text" name="new_holiday_name[]" class="form-control form-control-sm" placeholder="VD: Giỗ tổ Hùng Vương">
                                            </td>
                                            <td>
                                                <select name="new_holiday_type[]" class="form-select form-select-sm">
                                                    <option value="holiday">Nghỉ lễ</option>
                                                    <option value="compensatory">Nghỉ bù</option>
                                                    <option value="company">Nghỉ công ty</option>
                                                    <option value="other">Khác</option>
                                                </select>
                                            </td>
                                            <td class="text-center">
                                                <input type="checkbox" name="new_holiday_is_paid[0]" value="1" checked>
                                            </td>
                                            <td>
                                                <input type="text" name="new_holiday_note[]" class="form-control form-control-sm" placeholder="Ghi chú">
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <button type="button" class="btn setting-btn setting-btn-light" id="add-holiday-row">
                                <i class="bi bi-plus-circle me-1"></i> Thêm dòng ngày nghỉ
                            </button>
                        </div>

                        <div class="setting-divider my-4"></div>

                        <div class="row g-4">
                            <div class="col-12">
                                <div class="setting-switch-card">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                        <div class="d-flex gap-3 align-items-start">
                                            <div class="setting-switch-icon">
                                                <i class="bi bi-geo-alt"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold fs-6 mb-1">Bắt buộc lấy GPS khi chấm công</div>
                                                <div class="text-muted small">
                                                    Khi bật, hệ thống sẽ yêu cầu có vị trí GPS chính xác lúc check-in/check-out. Nếu nhân viên từ chối quyền vị trí hoặc GPS chưa đủ chính xác thì sẽ không thể chấm công.
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-check form-switch m-0">
                                            <input class="form-check-input setting-switch"
                                                   type="checkbox"
                                                   role="switch"
                                                   id="require_gps"
                                                   name="require_gps"
                                                   value="1"
                                                   {{ old('require_gps', $setting->require_gps) ? 'checked' : '' }}>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            {{-- CỘT PHẢI --}}
            <div class="col-12 col-xl-4">
                <div class="card setting-glass-card mb-4">
                    <div class="card-header bg-transparent border-0 px-4 pt-4 pb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="fw-bold mb-0">Tóm tắt nhanh</h5>
                            <span class="setting-chip">Preview</span>
                        </div>
                    </div>

                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="setting-summary-box mb-3">
                            <div class="setting-summary-label">Ca làm việc</div>
                            <div class="setting-summary-value" id="preview-working-time">
                                {{ \Carbon\Carbon::parse($setting->work_start_time)->format('H:i') }}
                                -
                                {{ \Carbon\Carbon::parse($setting->work_end_time)->format('H:i') }}
                            </div>
                        </div>

                        <div class="setting-summary-box mb-3">
                            <div class="setting-summary-label">Đi muộn sau</div>
                            <div class="setting-summary-value" id="preview-grace">
                                {{ (int) $setting->late_grace_minutes }} phút
                            </div>
                        </div>

                        <div class="setting-summary-box mb-3">
                            <div class="setting-summary-label">Phạt đi muộn</div>
                            <div class="setting-summary-value" id="preview-late-penalty">
                                {{ number_format((int) ($setting->late_penalty_per_time ?? 0), 0, ',', '.') }} đ / lần
                            </div>
                        </div>

                        <div class="setting-summary-box mb-3">
                            <div class="setting-summary-label">Đủ công từ</div>
                            <div class="setting-summary-value" id="preview-work-minutes">
                                {{ (int) $setting->min_work_minutes }} phút
                            </div>
                        </div>

                        <div class="setting-summary-box">
                            <div class="setting-summary-label">GPS</div>
                            <div class="setting-summary-value" id="preview-gps">
                                {{ $setting->require_gps ? 'Bắt buộc' : 'Không bắt buộc' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card setting-glass-card">
                    <div class="card-header bg-transparent border-0 px-4 pt-4 pb-2">
                        <h5 class="fw-bold mb-0">Gợi ý cấu hình</h5>
                    </div>

                    <div class="card-body px-4 pb-4 pt-2">
                        <div class="setting-tip-item">
                            <div class="setting-tip-dot tip-cyan"></div>
                            <div>
                                <div class="fw-semibold">Giờ chuẩn phổ biến</div>
                                <div class="small text-muted">08:00 - 17:30 hoặc 08:30 - 18:00</div>
                            </div>
                        </div>

                        <div class="setting-tip-item">
                            <div class="setting-tip-dot tip-green"></div>
                            <div>
                                <div class="fw-semibold">Grace period</div>
                                <div class="small text-muted">Nên để 5 phút để tránh sai lệch nhỏ.</div>
                            </div>
                        </div>

                        <div class="setting-tip-item">
                            <div class="setting-tip-dot tip-orange"></div>
                            <div>
                                <div class="fw-semibold">Đủ công</div>
                                <div class="small text-muted">480 phút tương đương 8 tiếng làm việc.</div>
                            </div>
                        </div>

                        <div class="setting-tip-item">
                            <div class="setting-tip-dot tip-red"></div>
                            <div>
                                <div class="fw-semibold">Phạt đi muộn</div>
                                <div class="small text-muted">Có thể dùng để tự động trừ lương theo số lần đi muộn trong tháng.</div>
                            </div>
                        </div>

                        <div class="setting-tip-item mb-0">
                            <div class="setting-tip-dot tip-purple"></div>
                            <div>
                                <div class="fw-semibold">GPS</div>
                                <div class="small text-muted">Nên bật khi cần kiểm soát chấm công ngoài văn phòng.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ACTION BAR --}}
            <div class="col-12">
                <div class="setting-action-bar">
                    <div class="text-muted small">
                        Sau khi lưu, cấu hình mới sẽ áp dụng cho các lần chấm công tiếp theo.
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ route('hr.attendance.index') }}" class="btn setting-btn setting-btn-light">
                            Huỷ
                        </a>
                        <button class="btn setting-btn setting-btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu cài đặt
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<style>
.attendance-settings-page{
    --st-primary:#0ea5e9;
    --st-cyan:#22d3ee;
    --st-ink:#0f172a;
    --st-text:#334155;
    --st-muted:#64748b;
}

.setting-hero{
    padding: 28px;
    border-radius: 30px;
    background:
        radial-gradient(circle at top right, rgba(34,211,238,.18), transparent 30%),
        radial-gradient(circle at left bottom, rgba(59,130,246,.10), transparent 32%),
        linear-gradient(135deg, #ffffff 0%, #f7fbff 52%, #f2f8ff 100%);
    border: 1px solid rgba(226,232,240,.95);
    box-shadow: 0 20px 60px rgba(15,23,42,.06);
}

.setting-kicker{
    display:inline-flex;
    align-items:center;
    padding:8px 14px;
    border-radius:999px;
    background:rgba(14,165,233,.10);
    color:#0369a1;
    font-size:.82rem;
    font-weight:800;
}

.setting-title{
    font-size: clamp(2rem, 3vw, 2.8rem);
    line-height:1.05;
    font-weight:900;
    color:var(--st-ink);
    letter-spacing:-.03em;
}

.setting-subtitle{
    color:var(--st-muted);
    font-size:1rem;
    font-weight:500;
}

.setting-hero-card{
    height:100%;
    padding:18px 20px;
    border-radius:20px;
    background:rgba(255,255,255,.72);
    border:1px solid rgba(226,232,240,.9);
    backdrop-filter: blur(8px);
}

.setting-hero-label{
    color:var(--st-muted);
    font-size:.82rem;
    font-weight:700;
    margin-bottom:8px;
}

.setting-hero-value{
    color:var(--st-ink);
    font-size:1.12rem;
    font-weight:800;
}

.setting-glass-card{
    border:none !important;
    border-radius:28px !important;
    background:linear-gradient(180deg, rgba(255,255,255,.97), rgba(248,250,252,.97));
    border:1px solid rgba(226,232,240,.9) !important;
    box-shadow:0 18px 60px rgba(15,23,42,.06) !important;
    overflow:hidden;
}

.setting-chip{
    display:inline-flex;
    align-items:center;
    padding:7px 12px;
    border-radius:999px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#64748b;
    font-size:.78rem;
    font-weight:800;
}

.setting-label{
    font-weight:800;
    color:var(--st-text);
    margin-bottom:10px;
}

.setting-input-wrap{
    position:relative;
}

.setting-input-icon{
    position:absolute;
    left:16px;
    top:50%;
    transform:translateY(-50%);
    color:#64748b;
    z-index:2;
    font-size:1rem;
}

.setting-input{
    height:54px;
    border-radius:18px;
    border:1px solid #dbe4ee;
    padding-left:46px;
    box-shadow:none !important;
    font-weight:600;
}

.setting-input:focus{
    border-color:#38bdf8;
    box-shadow:0 0 0 4px rgba(56,189,248,.12) !important;
}

.setting-help{
    font-size:.83rem;
    color:#64748b;
    margin-top:8px;
}

.setting-divider{
    height:1px;
    background:linear-gradient(90deg, transparent, #e2e8f0, transparent);
}

.setting-switch-card{
    border-radius:22px;
    background:linear-gradient(135deg,#f8fbff 0%, #f4faff 100%);
    border:1px solid #e6eef7;
    padding:20px;
}

.setting-switch-icon{
    width:52px;
    height:52px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(135deg,#dbeafe,#e0f2fe);
    color:#2563eb;
    font-size:1.35rem;
    flex:0 0 auto;
}

.setting-switch.form-check-input{
    width:56px;
    height:30px;
    cursor:pointer;
}

.setting-summary-box{
    padding:16px 18px;
    border-radius:18px;
    background:#f8fbff;
    border:1px solid #e8eef5;
}

.setting-summary-label{
    color:#64748b;
    font-size:.82rem;
    font-weight:700;
    margin-bottom:8px;
}

.setting-summary-value{
    color:#0f172a;
    font-size:1.15rem;
    font-weight:900;
}

.setting-tip-item{
    display:flex;
    gap:12px;
    align-items:flex-start;
    padding:14px 0;
    border-bottom:1px solid #eef2f7;
}

.setting-tip-dot{
    width:12px;
    height:12px;
    border-radius:999px;
    margin-top:5px;
    flex:0 0 auto;
}

.tip-cyan{ background:#06b6d4; }
.tip-green{ background:#10b981; }
.tip-orange{ background:#f97316; }
.tip-purple{ background:#8b5cf6; }
.tip-red{ background:#ef4444; }

.setting-action-bar{
    padding:20px 24px;
    border-radius:24px;
    background:linear-gradient(135deg,#ffffff 0%, #f8fbff 100%);
    border:1px solid rgba(226,232,240,.9);
    box-shadow:0 12px 36px rgba(15,23,42,.04);
    display:flex;
    align-items:center;
    justify-content:space-between;
    flex-wrap:wrap;
    gap:16px;
}

.setting-btn{
    border-radius:999px;
    padding:11px 20px;
    font-weight:800;
    border:1px solid transparent;
    box-shadow:none !important;
}

.setting-btn-light{
    background:#fff;
    color:#0f172a;
    border-color:#dbe4ee;
}

.setting-btn-light:hover{
    background:#f8fbff;
    color:#0f172a;
}

.setting-btn-primary{
    background:linear-gradient(135deg,#22d3ee 0%, #0ea5e9 100%);
    color:#fff;
}

.setting-btn-primary:hover{
    color:#fff;
}

.setting-alert-success{
    background:linear-gradient(135deg,#ecfdf5 0%, #d1fae5 100%);
    color:#065f46;
}

@media (max-width: 767px){
    .setting-hero{
        padding:20px;
        border-radius:22px;
    }

    .setting-title{
        font-size:1.9rem;
    }

    .setting-action-bar{
        padding:18px;
    }
}

/* EGO_ATTENDANCE_CALENDAR_SETTING_START */
.calendar-config-box,
.holiday-panel{
    padding:18px;
    border-radius:22px;
    background:linear-gradient(135deg,#f8fbff 0%, #ffffff 100%);
    border:1px solid #e6eef7;
}

.calendar-weekday-grid{
    display:grid;
    grid-template-columns:repeat(6, minmax(0, 1fr));
    gap:10px;
}

.calendar-check-card{
    min-height:46px;
    border-radius:14px;
    border:1px solid #dbeafe;
    background:#fff;
    display:flex;
    align-items:center;
    gap:9px;
    padding:10px 12px;
    cursor:pointer;
    font-weight:800;
    color:#334155;
}

.calendar-check-card input{
    width:16px;
    height:16px;
}

.calendar-check-card:has(input:checked){
    border-color:#38bdf8;
    background:#ecfeff;
    color:#075985;
}

.calendar-check-muted{
    border-color:#e2e8f0;
    background:#f8fafc;
}

.calendar-select{
    padding-left:16px !important;
}

.calendar-plain-input{
    padding-left:16px !important;
}

.holiday-note-box{
    padding:13px 15px;
    border-radius:16px;
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#9a3412;
    font-size:.86rem;
    font-weight:600;
}

.holiday-table-wrap{
    border:1px solid #e2e8f0;
    border-radius:16px;
    overflow:hidden;
    background:#fff;
}

.holiday-table thead th{
    background:#f8fafc;
    color:#475569;
    font-size:.75rem;
    text-transform:uppercase;
    letter-spacing:.03em;
    font-weight:900;
    border-bottom:1px solid #e2e8f0;
}

.holiday-table td,
.holiday-table th{
    padding:11px 12px;
    border-color:#edf2f7;
}

.holiday-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 9px;
    border-radius:999px;
    background:#e0f2fe;
    color:#0369a1;
    font-size:.76rem;
    font-weight:800;
}

.holiday-delete-check{
    display:inline-flex;
    align-items:center;
    gap:5px;
    font-size:.8rem;
    color:#dc2626;
    cursor:pointer;
}

@media (max-width: 992px){
    .calendar-weekday-grid{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 576px){
    .calendar-weekday-grid{
        grid-template-columns:1fr;
    }
}
/* EGO_ATTENDANCE_CALENDAR_SETTING_END */


/* EGO_HOLIDAY_CALENDAR_PICKER_START */
.holiday-calendar-widget{
    border:1px solid #dbeafe;
    border-radius:22px;
    background:linear-gradient(135deg,#ffffff 0%, #f8fbff 100%);
    padding:16px;
    box-shadow:0 10px 26px rgba(15,23,42,.035);
}

.holiday-calendar-head{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:14px;
}

.holiday-calendar-title{
    font-size:1rem;
    font-weight:900;
    color:#0f172a;
}

.holiday-calendar-desc{
    margin-top:3px;
    font-size:.84rem;
    color:#64748b;
}

.holiday-calendar-legend{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    font-size:.76rem;
    font-weight:800;
    color:#64748b;
}

.holiday-calendar-legend span{
    display:inline-flex;
    align-items:center;
    gap:5px;
}

.legend-dot{
    width:10px;
    height:10px;
    border-radius:999px;
    display:inline-block;
}

.legend-dot.normal{ background:#e2e8f0; }
.legend-dot.selected{ background:#0ea5e9; }
.legend-dot.weekend{ background:#fed7aa; }

.holiday-calendar-weekdays,
.holiday-calendar-grid{
    display:grid;
    grid-template-columns:repeat(7, minmax(0, 1fr));
    gap:8px;
}

.holiday-calendar-weekdays{
    margin-bottom:8px;
}

.holiday-calendar-weekdays div{
    text-align:center;
    font-size:.74rem;
    font-weight:900;
    color:#64748b;
    padding:6px 0;
}

.holiday-calendar-day{
    min-height:74px;
    border:1px solid #e2e8f0;
    border-radius:16px;
    background:#fff;
    text-align:left;
    padding:10px;
    cursor:pointer;
    transition:all .16s ease;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    gap:6px;
}

.holiday-calendar-day:hover{
    transform:translateY(-1px);
    border-color:#38bdf8;
    box-shadow:0 10px 20px rgba(14,165,233,.12);
}

.holiday-calendar-day .day-number{
    width:28px;
    height:28px;
    border-radius:999px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    font-size:.82rem;
    font-weight:900;
    color:#0f172a;
    background:#f8fafc;
}

.holiday-calendar-day .day-name{
    font-size:.72rem;
    font-weight:800;
    color:#64748b;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.holiday-calendar-day.is-weekend{
    background:#fff7ed;
    border-color:#fed7aa;
}

.holiday-calendar-day.is-weekend .day-name{
    color:#c2410c;
}

.holiday-calendar-day.is-selected{
    background:linear-gradient(135deg,#0ea5e9,#06b6d4);
    border-color:#0284c7;
    box-shadow:0 12px 26px rgba(14,165,233,.22);
}

.holiday-calendar-day.is-selected .day-number{
    background:rgba(255,255,255,.92);
    color:#0369a1;
}

.holiday-calendar-day.is-selected .day-name{
    color:#fff;
}

.holiday-calendar-day.is-out-month{
    opacity:.28;
    cursor:not-allowed;
}

.legacy-holiday-table,
#add-holiday-row{
    display:none !important;
}

@media (max-width: 768px){
    .holiday-calendar-weekdays,
    .holiday-calendar-grid{
        gap:5px;
    }

    .holiday-calendar-day{
        min-height:58px;
        border-radius:12px;
        padding:7px;
    }

    .holiday-calendar-day .day-name{
        display:none;
    }
}
/* EGO_HOLIDAY_CALENDAR_PICKER_END */


/* EGO_WORKDAY_SUMMARY_SETTING_START */
.workday-summary-grid{
    display:grid;
    grid-template-columns:repeat(5, minmax(0, 1fr));
    gap:10px;
}

.workday-summary-card{
    padding:14px 15px;
    border-radius:18px;
    background:#ffffff;
    border:1px solid #e2e8f0;
    box-shadow:0 8px 20px rgba(15,23,42,.035);
}

.workday-summary-card.primary{
    background:linear-gradient(135deg,#e0f2fe,#ecfeff);
    border-color:#7dd3fc;
}

.workday-summary-card.danger{
    background:linear-gradient(135deg,#fff7ed,#fffbeb);
    border-color:#fed7aa;
}

.workday-summary-card.muted{
    background:#f8fafc;
}

.workday-summary-label{
    color:#64748b;
    font-size:.76rem;
    font-weight:900;
    margin-bottom:6px;
}

.workday-summary-value{
    color:#0f172a;
    font-size:1.55rem;
    line-height:1;
    font-weight:950;
    letter-spacing:-.04em;
    margin-bottom:6px;
}

.workday-summary-card.primary .workday-summary-value{
    color:#0369a1;
}

.workday-summary-card.danger .workday-summary-value{
    color:#c2410c;
}

.workday-summary-note{
    color:#64748b;
    font-size:.72rem;
    font-weight:700;
    line-height:1.25;
}

@media (max-width: 1400px){
    .workday-summary-grid{
        grid-template-columns:repeat(3, minmax(0, 1fr));
    }
}

@media (max-width: 768px){
    .workday-summary-grid{
        grid-template-columns:1fr;
    }
}
/* EGO_WORKDAY_SUMMARY_SETTING_END */


/* EGO_HOLIDAY_CALENDAR_CLARIFY_START */
.legend-dot.workday{
    background:#22c55e;
}

.holiday-calendar-day.is-workday{
    background:#f0fdf4 !important;
    border-color:#bbf7d0 !important;
}

.holiday-calendar-day.is-workday .day-number{
    background:#dcfce7 !important;
    color:#166534 !important;
}

.holiday-calendar-day.is-workday .day-name{
    color:#166534 !important;
}

.holiday-calendar-day.is-offday{
    background:#fff7ed !important;
    border-color:#fed7aa !important;
}

.holiday-calendar-day.is-offday .day-number{
    background:#ffedd5 !important;
    color:#c2410c !important;
}

.holiday-calendar-day.is-offday .day-name{
    color:#c2410c !important;
}

.holiday-calendar-day.is-selected{
    background:linear-gradient(135deg,#ef4444,#f97316) !important;
    border-color:#dc2626 !important;
    box-shadow:0 12px 26px rgba(239,68,68,.22) !important;
}

.holiday-calendar-day.is-selected .day-number{
    background:rgba(255,255,255,.95) !important;
    color:#b91c1c !important;
}

.holiday-calendar-day.is-selected .day-name{
    color:#fff !important;
}

.holiday-calendar-day.is-out-month{
    background:#f8fafc !important;
    border-color:#e2e8f0 !important;
}

.holiday-calendar-day.is-out-month .day-number,
.holiday-calendar-day.is-out-month .day-name{
    color:#cbd5e1 !important;
}
/* EGO_HOLIDAY_CALENDAR_CLARIFY_END */

</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const startInput = document.querySelector('input[name="work_start_time"]');
    const endInput = document.querySelector('input[name="work_end_time"]');
    const graceInput = document.querySelector('input[name="late_grace_minutes"]');
    const latePenaltyInput = document.querySelector('input[name="late_penalty_per_time"]');
    const minWorkInput = document.querySelector('input[name="min_work_minutes"]');
    const gpsInput = document.querySelector('input[name="require_gps"]');

    const previewWorkingTime = document.getElementById('preview-working-time');
    const previewGrace = document.getElementById('preview-grace');
    const previewLatePenalty = document.getElementById('preview-late-penalty');
    const previewWorkMinutes = document.getElementById('preview-work-minutes');
    const previewGps = document.getElementById('preview-gps');

    function syncPreview() {
        if (previewWorkingTime && startInput && endInput) {
            previewWorkingTime.textContent = `${startInput.value || '--:--'} - ${endInput.value || '--:--'}`;
        }

        if (previewGrace && graceInput) {
            previewGrace.textContent = `${graceInput.value || 0} phút`;
        }

        if (previewLatePenalty && latePenaltyInput) {
            const value = Number(latePenaltyInput.value || 0).toLocaleString('vi-VN');
            previewLatePenalty.textContent = `${value} đ / lần`;
        }

        if (previewWorkMinutes && minWorkInput) {
            previewWorkMinutes.textContent = `${minWorkInput.value || 0} phút`;
        }

        if (previewGps && gpsInput) {
            previewGps.textContent = gpsInput.checked ? 'Bắt buộc' : 'Không bắt buộc';
        }
    }

    [startInput, endInput, graceInput, latePenaltyInput, minWorkInput, gpsInput].forEach(el => {
        if (el) {
            el.addEventListener('input', syncPreview);
            el.addEventListener('change', syncPreview);
        }
    });

    syncPreview();
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const saturdayMode = document.getElementById('saturday-mode');
    const saturdayCustomWrap = document.getElementById('saturday-custom-wrap');
    const addHolidayBtn = document.getElementById('add-holiday-row');
    const newHolidayRows = document.getElementById('new-holiday-rows');

    function toggleSaturdayCustom() {
        if (!saturdayMode || !saturdayCustomWrap) return;
        saturdayCustomWrap.style.display = saturdayMode.value === 'custom' ? '' : 'none';
    }

    if (saturdayMode) {
        saturdayMode.addEventListener('change', toggleSaturdayCustom);
        toggleSaturdayCustom();
    }

    if (addHolidayBtn && newHolidayRows) {
        addHolidayBtn.addEventListener('click', function () {
            const index = newHolidayRows.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.className = 'new-holiday-row';
            tr.innerHTML = `
                <td>
                    <input type="date" name="new_holiday_date[]" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="text" name="new_holiday_name[]" class="form-control form-control-sm" placeholder="VD: Nghỉ công ty">
                </td>
                <td>
                    <select name="new_holiday_type[]" class="form-select form-select-sm">
                        <option value="holiday">Nghỉ lễ</option>
                        <option value="compensatory">Nghỉ bù</option>
                        <option value="company">Nghỉ công ty</option>
                        <option value="other">Khác</option>
                    </select>
                </td>
                <td class="text-center">
                    <input type="checkbox" name="new_holiday_is_paid[${index}]" value="1" checked>
                </td>
                <td>
                    <input type="text" name="new_holiday_note[]" class="form-control form-control-sm" placeholder="Ghi chú">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-light remove-holiday-row">Xoá</button>
                </td>
            `;
            newHolidayRows.appendChild(tr);
        });

        newHolidayRows.addEventListener('click', function (event) {
            if (event.target.classList.contains('remove-holiday-row')) {
                event.target.closest('tr').remove();
            }
        });
    }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const hiddenBox = document.getElementById('holiday-hidden-inputs');
    const holidayDays = document.querySelectorAll('.holiday-calendar-day');
    const holidayMonthInput = document.querySelector('input[name="holiday_month"]');

    const newSelectedDates = new Map();
    const deletedExistingIds = new Set();

    function createHidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        hiddenBox.appendChild(input);
    }

    function renderHiddenInputs() {
        if (!hiddenBox) return;

        hiddenBox.innerHTML = '';

        let index = 0;
        newSelectedDates.forEach((item) => {
            createHidden('new_holiday_date[]', item.date);
            createHidden('new_holiday_name[]', item.name || 'Ngày nghỉ');
            createHidden('new_holiday_type[]', item.type || 'company');
            createHidden(`new_holiday_is_paid[${index}]`, '1');
            createHidden('new_holiday_note[]', item.note || '');
            index++;
        });

        deletedExistingIds.forEach((id) => {
            createHidden('delete_holiday_ids[]', id);
        });
    }

    holidayDays.forEach((button) => {
        button.addEventListener('click', function () {
            if (button.disabled) return;

            const date = button.dataset.date;
            const displayDate = button.dataset.displayDate;
            const existingId = button.dataset.existingId;
            const existingName = button.dataset.existingName || '';

            if (existingId) {
                if (button.classList.contains('is-selected')) {
                    button.classList.remove('is-selected');
                    button.querySelector('.day-name').textContent = button.dataset.defaultLabel || (button.classList.contains('is-offday') ? 'Nghỉ theo lịch' : 'Đi làm');
                    deletedExistingIds.add(existingId);
                } else {
                    button.classList.add('is-selected');
                    button.querySelector('.day-name').textContent = existingName || 'Ngày nghỉ';
                    deletedExistingIds.delete(existingId);
                }

                renderHiddenInputs();
                return;
            }

            if (button.classList.contains('is-selected')) {
                button.classList.remove('is-selected');
                button.querySelector('.day-name').textContent = button.dataset.defaultLabel || (button.classList.contains('is-offday') ? 'Nghỉ theo lịch' : 'Đi làm');
                newSelectedDates.delete(date);
                renderHiddenInputs();
                return;
            }

            const defaultName = button.classList.contains('is-workday') ? 'Ngày nghỉ' : 'Nghỉ theo lịch';
            const name = window.prompt(`Tên ngày nghỉ ${displayDate}:`, defaultName);

            if (name === null) {
                return;
            }

            button.classList.add('is-selected');
            button.querySelector('.day-name').textContent = name.trim() || defaultName;

            newSelectedDates.set(date, {
                date: date,
                name: name.trim() || defaultName,
                type: 'company',
                note: ''
            });

            renderHiddenInputs();
        });
    });

    if (holidayMonthInput) {
        holidayMonthInput.addEventListener('change', function () {
            if (!holidayMonthInput.value) return;

            const url = new URL(window.location.href);
            url.searchParams.set('holiday_month', holidayMonthInput.value);
            window.location.href = url.toString();
        });
    }
});
</script>

@endsection