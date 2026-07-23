@extends('layouts.app')

@section('title', 'Chấm công của tôi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
@endpush

@section('content')
@php
    $validDays = $records->filter(fn ($record) => filled($record->check_in_at))->count();
    $lateDays = $records->filter(fn ($record) => (int) $record->late_minutes > 0)->count();
    $completedDays = $records->where('status', 'completed')->count();
    $totalHours = round($records->sum('work_minutes') / 60, 1);
    $onTimeDays = $records->filter(fn ($record) => filled($record->check_in_at) && (int) $record->late_minutes === 0)->count();
    $onTimeRate = $validDays > 0 ? round(($onTimeDays / $validDays) * 100) : 0;

    $statusClass = static function ($status): string {
        return match ((string) $status) {
            'completed' => 'success',
            'checked_in' => 'primary',
            'late', 'early_leave' => 'warning',
            'incomplete' => 'danger',
            default => 'secondary',
        };
    };
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        @if(session('success'))
            <div class="at-alert"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="at-alert at-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="at-alert at-alert--danger">
                <i class="bi bi-exclamation-circle"></i>
                <div>
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-fingerprint"></i></div>
                <div>
                    <span>CHẤM CÔNG CÁ NHÂN</span>
                    <h1>Ngày làm việc của tôi</h1>
                    <p>Check-in, check-out và theo dõi lịch sử công trong một màn hình.</p>
                </div>
            </div>

            <div class="at-actions">
                <a class="at-btn at-btn--light" href="{{ route('hr.leave.create') }}">
                    <i class="bi bi-file-earmark-plus"></i>Tạo đơn
                </a>

                <a class="at-btn at-btn--glass" href="{{ route('hr.leave.index', ['tab' => 'mine']) }}">
                    <i class="bi bi-folder2-open"></i>Đơn của tôi
                    @if($myPendingLeaveCount > 0)
                        <span class="at-badge-count">{{ $myPendingLeaveCount }}</span>
                    @endif
                </a>

                @if($canReviewLeave)
                    <a class="at-btn at-btn--glass" href="{{ route('hr.leave.index', ['tab' => 'approval', 'status' => 'pending']) }}">
                        <i class="bi bi-check2-square"></i>Duyệt đơn nhân sự
                        @if($pendingApprovalCount > 0)
                            <span class="at-badge-count">{{ $pendingApprovalCount }}</span>
                        @endif
                    </a>
                @endif

                @if($canViewCompanyAttendance)
                    <a class="at-btn at-btn--glass" href="{{ route('hr.attendance.index') }}">
                        <i class="bi bi-table"></i>Bảng công
                    </a>
                @endif
            </div>
        </header>

        <section class="at-kpis">
            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-calendar-check"></i></div>
                <label>Ngày có chấm công</label>
                <strong>{{ $validDays }}</strong>
                <small>Trong tháng {{ $start->format('m/Y') }}</small>
            </article>

            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-alarm"></i></div>
                <label>Đúng giờ</label>
                <strong>{{ $onTimeRate }}%</strong>
                <small>{{ $onTimeDays }} ngày đúng giờ</small>
            </article>

            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-clock-history"></i></div>
                <label>Đi muộn</label>
                <strong>{{ $lateDays }}</strong>
                <small>{{ $completedDays }} ngày hoàn tất</small>
            </article>

            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-hourglass-split"></i></div>
                <label>Tổng giờ công</label>
                <strong>{{ number_format($totalHours, 1, ',', '.') }}</strong>
                <small>Giờ đã ghi nhận</small>
            </article>
        </section>

        <div class="at-layout">
            <section class="at-panel at-today">
                <div class="at-card-head">
                    <div>
                        <h2><i class="bi bi-geo-alt"></i>Hôm nay</h2>
                        <p>Ghi nhận thời gian và vị trí làm việc.</p>
                    </div>
                    <span class="at-date-pill"><i class="bi bi-calendar3"></i>{{ now()->format('d/m/Y') }}</span>
                </div>

                <div class="at-card-body">
                    <div class="at-clock">
                        <div class="at-clock__time" data-live-clock>{{ now()->format('H:i:s') }}</div>
                        <div class="at-clock__day" data-live-day>{{ now()->translatedFormat('l, d/m/Y') }}</div>
                    </div>

                    <div class="at-timeline">
                        <div class="at-time-box">
                            <span>Check-in</span>
                            <strong>{{ optional($todayRecord?->check_in_at)->format('H:i') ?? '--:--' }}</strong>
                            <small>{{ $todayRecord?->check_in_address ?: 'Chưa ghi nhận địa chỉ' }}</small>
                        </div>
                        <div class="at-timeline__center"></div>
                        <div class="at-time-box is-right">
                            <span>Check-out</span>
                            <strong>{{ optional($todayRecord?->check_out_at)->format('H:i') ?? '--:--' }}</strong>
                            <small>{{ $todayRecord?->check_out_address ?: 'Chưa ghi nhận địa chỉ' }}</small>
                        </div>
                    </div>

                    <div class="at-status">
                        <span>Trạng thái hôm nay</span>
                        <strong>{{ $todayRecord?->status_label ?? 'Chưa check-in' }}</strong>
                    </div>

                    <form method="POST" action="{{ route('hr.attendance.checkin') }}" class="at-form" data-attendance-form data-action-label="check-in">
                        @csrf
                        <input type="hidden" name="lat">
                        <input type="hidden" name="lng">
                        <textarea name="note" class="at-note" rows="2" placeholder="Ghi chú check-in (không bắt buộc)"></textarea>
                        <button type="submit" class="at-btn at-btn--primary" @disabled($todayRecord?->check_in_at)>
                            <i class="bi bi-box-arrow-in-right"></i>
                            {{ $todayRecord?->check_in_at ? 'Đã check-in hôm nay' : 'Check-in ngay' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('hr.attendance.checkout') }}" class="at-form" data-attendance-form data-action-label="check-out">
                        @csrf
                        <input type="hidden" name="lat">
                        <input type="hidden" name="lng">
                        <textarea name="note" class="at-note" rows="2" placeholder="Ghi chú check-out (không bắt buộc)"></textarea>
                        <button type="submit" class="at-btn at-btn--primary" @disabled(! $todayRecord?->check_in_at || $todayRecord?->check_out_at)>
                            <i class="bi bi-box-arrow-right"></i>
                            {{ $todayRecord?->check_out_at ? 'Đã check-out hôm nay' : 'Check-out ngay' }}
                        </button>
                    </form>

                    <div class="at-location-tip">
                        <i class="bi bi-shield-check"></i>
                        <span>Vị trí chỉ được lấy khi bấm nút chấm công. Hãy cho phép GPS khi trình duyệt yêu cầu.</span>
                    </div>
                </div>
            </section>

            <section class="at-panel at-history">
                <div class="at-card-head">
                    <div>
                        <h2><i class="bi bi-clock-history"></i>Lịch sử chấm công</h2>
                        <p>Chi tiết thời gian vào, ra và giờ công cá nhân.</p>
                    </div>
                </div>

                <div class="at-history-toolbar">
                    <form method="GET" class="at-filter">
                        <div>
                            <label>Tháng theo dõi</label>
                            <input type="month" name="month" value="{{ $month }}">
                        </div>
                        <button class="at-btn at-btn--primary"><i class="bi bi-funnel"></i>Áp dụng</button>
                    </form>

                    <a class="at-btn at-btn--light" href="{{ route('hr.leave.index', ['tab' => 'mine']) }}">
                        <i class="bi bi-calendar2-week"></i>Xem đơn nghỉ phép
                    </a>
                </div>

                <div class="at-table-wrap">
                    <table class="at-table">
                        <thead>
                            <tr>
                                <th>Ngày</th>
                                <th>Check-in</th>
                                <th>Địa chỉ vào</th>
                                <th>Check-out</th>
                                <th>Địa chỉ ra</th>
                                <th>Muộn</th>
                                <th>Về sớm</th>
                                <th>Giờ công</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                                <tr>
                                    <td><strong>{{ $record->work_date->format('d/m/Y') }}</strong></td>
                                    <td>{{ optional($record->check_in_at)->format('H:i:s') ?? '—' }}</td>
                                    <td><div class="at-address">{{ $record->check_in_address ?: '—' }}</div></td>
                                    <td>{{ optional($record->check_out_at)->format('H:i:s') ?? '—' }}</td>
                                    <td><div class="at-address">{{ $record->check_out_address ?: '—' }}</div></td>
                                    <td>{{ (int) $record->late_minutes }} phút</td>
                                    <td>{{ (int) $record->early_leave_minutes }} phút</td>
                                    <td>{{ number_format($record->work_minutes / 60, 2, ',', '.') }} giờ</td>
                                    <td>
                                        <span class="at-status-pill at-status-pill--{{ $statusClass($record->status) }}">
                                            {{ $record->status_label }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9"><div class="at-empty">Chưa có dữ liệu chấm công trong tháng.</div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ego-attendance-promax.js') }}?v={{ filemtime(public_path('js/ego-attendance-promax.js')) }}" defer></script>
@endpush
