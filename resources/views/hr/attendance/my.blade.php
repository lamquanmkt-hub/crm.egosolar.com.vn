@extends('layouts.app')

@section('title', 'Chấm công của tôi')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
    <style>
        #egoAttendancePromax .at-correction-btn{border:0;border-radius:10px;padding:7px 10px;background:#e7f8fb;color:#087e91;font-weight:800;font-size:12px;white-space:nowrap}
        #egoAttendancePromax .at-correction-btn:hover{background:#cceff4}
        #egoAttendancePromax .at-correction-pending{display:inline-flex;align-items:center;gap:5px;border-radius:999px;padding:6px 9px;background:#fff4d6;color:#946200;font-weight:800;font-size:11px;white-space:nowrap;text-decoration:none}
        .at-correction-dialog{width:min(620px,calc(100vw - 28px));border:0;border-radius:22px;padding:0;box-shadow:0 28px 80px rgba(2,31,50,.3);color:#102a43}
        .at-correction-dialog::backdrop{background:rgba(2,24,38,.65);backdrop-filter:blur(3px)}
        .at-correction-dialog__head{display:flex;justify-content:space-between;gap:16px;padding:22px 24px;background:linear-gradient(135deg,#073b56,#0699a7);color:#fff}
        .at-correction-dialog__head h3{margin:0;font-size:20px;font-weight:800}
        .at-correction-dialog__head p{margin:5px 0 0;opacity:.8;font-size:13px}
        .at-correction-dialog__close{width:34px;height:34px;border:0;border-radius:50%;background:rgba(255,255,255,.16);color:#fff;font-size:20px}
        .at-correction-dialog__body{padding:22px 24px}
        .at-correction-original{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px}
        .at-correction-original div{padding:12px 14px;border:1px solid #dce8ed;border-radius:14px;background:#f7fbfc}
        .at-correction-original span,.at-correction-field label{display:block;margin-bottom:5px;color:#607b89;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
        .at-correction-original strong{font-size:17px}
        .at-correction-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .at-correction-field{margin-bottom:15px}
        .at-correction-field input,.at-correction-field textarea{width:100%;border:1px solid #d2e2e8;border-radius:12px;padding:11px 12px;outline:0}
        .at-correction-field input:focus,.at-correction-field textarea:focus{border-color:#09a7b2;box-shadow:0 0 0 3px rgba(9,167,178,.12)}
        .at-correction-field small{display:block;margin-top:6px;color:#78909c;font-size:11px;line-height:1.45}
        .at-correction-file{background:#f7fbfc;cursor:pointer}
        .at-correction-dialog__actions{display:flex;justify-content:flex-end;gap:10px;margin-top:6px}
        @media(max-width:575px){.at-correction-grid,.at-correction-original{grid-template-columns:1fr}.at-correction-dialog__body{padding:18px}}
    </style>
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

                <a class="at-btn at-btn--glass" href="{{ route('hr.attendance-corrections.index', ['tab' => 'mine']) }}">
                    <i class="bi bi-clock-history"></i>Sửa chấm công
                    @if($myPendingCorrectionCount > 0)
                        <span class="at-badge-count">{{ $myPendingCorrectionCount }}</span>
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

                @if($canReviewCorrections)
                    <a class="at-btn at-btn--glass" href="{{ route('hr.attendance-corrections.index', ['tab' => 'approval', 'status' => 'pending']) }}">
                        <i class="bi bi-person-check"></i>Duyệt sửa công
                        @if($pendingCorrectionApprovalCount > 0)
                            <span class="at-badge-count">{{ $pendingCorrectionApprovalCount }}</span>
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
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $record)
                                @php
                                    $pendingCorrection = $record->correctionRequests->firstWhere('status', 'pending');
                                    $isActiveToday = $record->work_date->isToday() && blank($record->check_out_at);
                                @endphp
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
                                    <td>
                                        @if($pendingCorrection)
                                            <a class="at-correction-pending" href="{{ route('hr.attendance-corrections.index', ['tab' => 'mine']) }}">
                                                <i class="bi bi-hourglass-split"></i>Đang chờ HR
                                            </a>
                                        @elseif($isActiveToday)
                                            <span class="tw:text-[rgba(33,37,41,0.75)]! small tw:whitespace-nowrap">Hoàn tất ca trước</span>
                                        @else
                                            <button type="button" class="at-correction-btn" onclick="document.getElementById('attendance-correction-{{ $record->id }}').showModal()">
                                                <i class="bi bi-pencil-square"></i> Yêu cầu sửa
                                            </button>

                                            <dialog class="at-correction-dialog" id="attendance-correction-{{ $record->id }}">
                                                <form method="POST" action="{{ route('hr.attendance-corrections.store') }}" enctype="multipart/form-data">
                                                    @csrf
                                                    <input type="hidden" name="attendance_record_id" value="{{ $record->id }}">

                                                    <div class="at-correction-dialog__head">
                                                        <div>
                                                            <h3>Yêu cầu sửa chấm công</h3>
                                                            <p>Ngày {{ $record->work_date->format('d/m/Y') }} · HR sẽ kiểm tra trước khi cập nhật.</p>
                                                        </div>
                                                        <button type="button" class="at-correction-dialog__close" aria-label="Đóng" onclick="this.closest('dialog').close()">×</button>
                                                    </div>

                                                    <div class="at-correction-dialog__body">
                                                        <div class="at-correction-original">
                                                            <div><span>Giờ vào hiện tại</span><strong>{{ optional($record->check_in_at)->format('H:i') ?? 'Chưa có' }}</strong></div>
                                                            <div><span>Giờ ra hiện tại</span><strong>{{ optional($record->check_out_at)->format('H:i') ?? 'Chưa có' }}</strong></div>
                                                        </div>

                                                        <div class="at-correction-grid">
                                                            <div class="at-correction-field">
                                                                <label for="correction-in-{{ $record->id }}">Giờ check-in đề nghị *</label>
                                                                <input id="correction-in-{{ $record->id }}" type="time" name="requested_check_in_time" value="{{ optional($record->check_in_at)->format('H:i') }}" required>
                                                            </div>
                                                            <div class="at-correction-field">
                                                                <label for="correction-out-{{ $record->id }}">Giờ check-out đề nghị</label>
                                                                <input id="correction-out-{{ $record->id }}" type="time" name="requested_check_out_time" value="{{ optional($record->check_out_at)->format('H:i') }}">
                                                            </div>
                                                        </div>

                                                        <div class="at-correction-field">
                                                            <label for="correction-reason-{{ $record->id }}">Lý do điều chỉnh *</label>
                                                            <textarea id="correction-reason-{{ $record->id }}" name="reason" rows="4" maxlength="2000" required placeholder="Ví dụ: Quên check-out, hệ thống ghi nhận sai giờ...">{{ old('attendance_record_id') == $record->id ? old('reason') : '' }}</textarea>
                                                        </div>

                                                        <div class="at-correction-field">
                                                            <label for="correction-files-{{ $record->id }}">Ảnh/file giải trình</label>
                                                            <input class="at-correction-file" id="correction-files-{{ $record->id }}" type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,image/heic,image/heif,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                                            <small><i class="bi bi-paperclip"></i> Tối đa 5 file, mỗi file không quá 10 MB. Hỗ trợ ảnh, PDF, Word, Excel và TXT.</small>
                                                        </div>

                                                        <div class="at-correction-dialog__actions">
                                                            <button type="button" class="at-btn at-btn--light" onclick="this.closest('dialog').close()">Đóng</button>
                                                            <button type="submit" class="at-btn at-btn--primary"><i class="bi bi-send"></i>Gửi HR duyệt</button>
                                                        </div>
                                                    </div>
                                                </form>
                                            </dialog>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10"><div class="at-empty">Chưa có dữ liệu chấm công trong tháng.</div></td></tr>
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
    @if($errors->any() && old('attendance_record_id'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.getElementById('attendance-correction-{{ (int) old('attendance_record_id') }}')?.showModal();
            });
        </script>
    @endif
@endpush
