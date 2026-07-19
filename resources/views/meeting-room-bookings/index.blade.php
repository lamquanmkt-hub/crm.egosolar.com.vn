@extends('layouts.app')

@section('content')
<style>
    .br-wrap{padding:0 4px 34px;font-family:"Be Vietnam Pro",system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    .br-head{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:14px}
    .br-title{margin:0;font-size:26px;font-weight:950;color:#0f172a;letter-spacing:-.04em}
    .br-sub{margin-top:5px;font-size:13px;font-weight:750;color:#64748b}
    .br-actions{display:flex;gap:8px;flex-wrap:wrap}
    .br-btn,.br-btn-outline,.br-btn-danger{height:38px;border-radius:12px;padding:0 14px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:7px;font-size:12px;font-weight:950;text-decoration:none;cursor:pointer;white-space:nowrap}
    .br-btn{background:#0f766e;color:#fff;box-shadow:0 10px 22px rgba(15,118,110,.18)}
    .br-btn:hover{color:#fff;background:#0d6b64}
    .br-btn-outline{background:#fff;color:#0f172a;border-color:#dbe3ef;box-shadow:0 8px 18px rgba(15,23,42,.04)}
    .br-btn-danger{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .br-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0 14px}
    .br-stat{position:relative;overflow:hidden;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:15px 16px;box-shadow:0 12px 30px rgba(15,23,42,.055)}
    .br-stat:after{content:"";position:absolute;right:-22px;top:-24px;width:82px;height:82px;border-radius:999px;background:linear-gradient(135deg,rgba(15,118,110,.13),rgba(14,165,233,.08))}
    .br-stat-label{position:relative;z-index:1;font-size:11px;font-weight:950;text-transform:uppercase;color:#64748b}
    .br-stat-value{position:relative;z-index:1;margin-top:7px;font-size:25px;font-weight:950;color:#0f766e}
    .br-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .br-field{display:flex;flex-direction:column;gap:6px}
    .br-field.full{grid-column:1/-1}
    .br-label{font-size:12px;font-weight:900;color:#475569}
    .br-input,.br-select,.br-textarea{width:100%;border:1px solid #dbe3ef;border-radius:12px;background:#fff;color:#0f172a;font-size:12px;font-weight:750;outline:none}
    .br-input,.br-select{height:39px;padding:0 11px}
    .br-textarea{min-height:72px;padding:10px 11px;resize:vertical}
    .br-input:focus,.br-select:focus,.br-textarea:focus{border-color:#14b8a6;box-shadow:0 0 0 3px rgba(20,184,166,.12)}
    .br-card{background:#fff;border:1px solid #e2e8f0;border-radius:20px;box-shadow:0 16px 36px rgba(15,23,42,.055);overflow:hidden;margin-bottom:14px}
    .br-card-head{padding:15px 17px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#ffffff,#f8fafc);display:flex;justify-content:space-between;gap:12px;align-items:center}
    .br-card-title{margin:0;font-size:16px;font-weight:950;color:#0f172a}
    .br-card-note{margin-top:3px;font-size:12px;font-weight:750;color:#64748b}
    .br-card-body{padding:16px 17px}
    .br-form-foot{margin-top:12px;display:flex;justify-content:flex-end;gap:8px}
    .br-filter{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end}
    .br-table-wrap{border:1px solid #e2e8f0;border-radius:16px;overflow:auto;background:#fff}
    .br-table{width:100%;border-collapse:collapse;min-width:1120px}
    .br-table th{background:#f1f5f9;color:#334155;font-size:11px;text-transform:uppercase;letter-spacing:.03em;font-weight:950;padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;white-space:nowrap}
    .br-table td{padding:9px 10px;border-bottom:1px solid #edf2f7;vertical-align:top;font-size:12px;font-weight:750;color:#0f172a}
    .br-table tr:last-child td{border-bottom:0}
    .br-actions-row{display:flex;gap:6px;flex-wrap:wrap}
    .br-pill{display:inline-flex;align-items:center;height:27px;border-radius:999px;padding:0 10px;border:1px solid #ccfbf1;background:#ecfeff;color:#0f766e;font-size:11px;font-weight:950;white-space:nowrap}
    .br-pill.pending{background:#fffbeb;color:#b45309;border-color:#fde68a}
    .br-pill.approved{background:#ecfeff;color:#0f766e;border-color:#99f6e4}
    .br-pill.done{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
    .br-pill.cancelled{background:#fff1f2;color:#be123c;border-color:#fecdd3}
    .br-alert{margin-bottom:12px;padding:10px 12px;border:1px solid #bbf7d0;background:#f0fdf4;color:#166534;border-radius:14px;font-size:12px;font-weight:850}
    .br-error{margin-bottom:12px;padding:10px 12px;border:1px solid #fecaca;background:#fff1f2;color:#be123c;border-radius:14px;font-size:12px;font-weight:850}
    .br-empty{padding:30px 14px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc;color:#64748b;font-size:12px;font-weight:750;text-align:center}
    .br-today{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
    .br-time-card{border:1px solid #e2e8f0;border-radius:15px;padding:12px;background:#f8fafc}
    .br-time{font-size:12px;font-weight:950;color:#0f766e}.br-time-title{margin-top:4px;font-size:13px;font-weight:950;color:#0f172a}.br-time-note{margin-top:3px;font-size:12px;font-weight:750;color:#64748b}
    .br-edit-form{display:grid;grid-template-columns:150px 1.2fr 145px 145px 115px 110px auto;gap:7px;align-items:start;min-width:980px}
    .br-modal{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.46);backdrop-filter:blur(4px)}
    .br-modal.show{display:flex}
    .br-modal-panel{width:min(980px,96vw);max-height:90vh;overflow:auto;background:#fff;border:1px solid #e2e8f0;border-radius:22px;box-shadow:0 28px 90px rgba(15,23,42,.28)}
    .br-modal-head{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:16px 18px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#ffffff,#f8fafc)}
    .br-modal-title{margin:0;font-size:18px;font-weight:950;color:#0f172a;letter-spacing:-.03em}
    .br-modal-note{margin-top:4px;font-size:12px;font-weight:750;color:#64748b}
    .br-modal-body{padding:16px 18px 18px}
    .br-close{width:34px;height:34px;border-radius:12px;border:1px solid #dbe3ef;background:#fff;color:#0f172a;font-size:20px;line-height:1;font-weight:900;cursor:pointer}
    .br-close:hover{background:#f8fafc}
    .br-mini-create{display:flex;justify-content:space-between;align-items:center;gap:12px;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:12px 14px;margin-bottom:14px;box-shadow:0 12px 30px rgba(15,23,42,.045)}
    .br-mini-title{font-size:14px;font-weight:950;color:#0f172a}.br-mini-note{margin-top:2px;font-size:12px;font-weight:750;color:#64748b}
    @media(max-width:1200px){.br-stats,.br-grid,.br-filter{grid-template-columns:repeat(2,minmax(0,1fr))}.br-today{grid-template-columns:1fr}}
    @media(max-width:760px){.br-head{display:block}.br-stats,.br-grid,.br-filter{grid-template-columns:1fr}.br-btn,.br-btn-outline,.br-btn-danger{width:100%}}
</style>

@php
    $fmtDateTime = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $inputDateTime = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d\\TH:i') : '';

    $organizerOptions = $organizers ?? [];
    if (!count($organizerOptions) && optional(auth()->user())->name) {
        $organizerOptions = [optional(auth()->user())->name => optional(auth()->user())->name];
    }
@endphp

<div class="br-wrap">
    <div class="br-head">
        <div>
            <h1 class="br-title">Booking phòng họp</h1>
            <div class="br-sub">Đặt lịch phòng họp, kiểm tra lịch trùng, theo dõi trạng thái và quản lý lịch sử sử dụng phòng.</div>
        </div>
        <div class="br-actions">
            <button class="br-btn" type="button" data-open-booking>+ Tạo booking</button>
            <a class="br-btn-outline" href="{{ route('dashboard') }}">Trang chủ</a>
            <a class="br-btn-outline" href="{{ route('hr.operations.index') }}">HC & Vận hành</a>
        </div>
    </div>

    @if(session('success'))
        <div class="br-alert">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="br-error">{{ $errors->first() }}</div>
    @endif

    <div class="br-stats">
        <div class="br-stat"><div class="br-stat-label">Lịch hôm nay</div><div class="br-stat-value">{{ $stats['today'] ?? 0 }}</div></div>
        <div class="br-stat"><div class="br-stat-label">Đang xử lý</div><div class="br-stat-value">{{ $stats['processing'] ?? 0 }}</div></div>
        <div class="br-stat"><div class="br-stat-label">Tổng lịch tháng</div><div class="br-stat-value">{{ $stats['month'] ?? 0 }}</div></div>
        <div class="br-stat"><div class="br-stat-label">Đã hủy</div><div class="br-stat-value">{{ $stats['cancelled'] ?? 0 }}</div></div>
    </div>

    <div class="br-mini-create">
        <div>
            <div class="br-mini-title">Tạo booking phòng họp</div>
            <div class="br-mini-note">Form đã chuyển vào popup để màn hình gọn hơn, danh sách lịch nhìn thoáng hơn.</div>
        </div>
        <button class="br-btn" type="button" data-open-booking>+ Tạo booking</button>
    </div>

    <div class="br-modal" id="bookingModal" aria-hidden="true">
        <div class="br-modal-panel" role="dialog" aria-modal="true" aria-labelledby="bookingModalTitle">
            <div class="br-modal-head">
                <div>
                    <h3 class="br-modal-title" id="bookingModalTitle">Tạo booking phòng họp</h3>
                    <div class="br-modal-note">Nhập đúng khung giờ để hệ thống tự chặn lịch trùng phòng.</div>
                </div>
                <button class="br-close" type="button" data-close-booking aria-label="Đóng">×</button>
            </div>
            <div class="br-modal-body">
            <form method="POST" action="{{ route('meeting-room-bookings.store') }}">
                @csrf
                <div class="br-grid">
                    <div class="br-field">
                        <label class="br-label">Phòng họp *</label>
                        <select class="br-select" name="room_name" required>
                            @foreach($rooms as $r)
                                <option value="{{ $r }}" {{ old('room_name') === $r ? 'selected' : '' }}>{{ $r }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="br-field">
                        <label class="br-label">Nội dung cuộc họp *</label>
                        <input class="br-input" name="title" value="{{ old('title') }}" placeholder="VD: Họp sales tuần" required>
                    </div>
                    <div class="br-field">
                        <label class="br-label">Người đặt</label>
                        @php $selectedOrganizer = old('organizer_name', optional(auth()->user())->name); @endphp
                        <select class="br-select" name="organizer_name">
                            <option value="">Chọn người đặt</option>
                            @foreach($organizerOptions as $orgValue => $orgLabel)
                                <option value="{{ $orgValue }}" {{ $selectedOrganizer === $orgValue ? 'selected' : '' }}>{{ $orgLabel }}</option>
                            @endforeach
                            @if($selectedOrganizer && !array_key_exists($selectedOrganizer, $organizerOptions))
                                <option value="{{ $selectedOrganizer }}" selected>{{ $selectedOrganizer }}</option>
                            @endif
                        </select>
                    </div>
                    <div class="br-field">
                        <label class="br-label">Phòng ban</label>
                        <input class="br-input" name="department" value="{{ old('department') }}" placeholder="VD: Sales / Marketing">
                    </div>
                    <div class="br-field">
                        <label class="br-label">Số người</label>
                        <input class="br-input" type="number" min="1" name="attendees" value="{{ old('attendees', 1) }}">
                    </div>
                    <div class="br-field">
                        <label class="br-label">Bắt đầu *</label>
                        <input class="br-input" type="datetime-local" name="start_at" value="{{ old('start_at', now()->format('Y-m-d\\T09:00')) }}" required>
                    </div>
                    <div class="br-field">
                        <label class="br-label">Kết thúc *</label>
                        <input class="br-input" type="datetime-local" name="end_at" value="{{ old('end_at', now()->format('Y-m-d\\T10:00')) }}" required>
                    </div>
                    <div class="br-field">
                        <label class="br-label">Trạng thái</label>
                        <select class="br-select" name="status">
                            @foreach($statuses as $k => $v)
                                <option value="{{ $k }}" {{ old('status', 'pending') === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="br-field full">
                        <label class="br-label">Ghi chú</label>
                        <textarea class="br-textarea" name="note" placeholder="Thiết bị cần chuẩn bị, nội dung lưu ý...">{{ old('note') }}</textarea>
                    </div>
                </div>
                <div class="br-form-foot">
                    <button class="br-btn" type="submit">+ Tạo booking</button>
                </div>
            </form>
            </div>
        </div>
    </div>

    <div class="br-card">
        <div class="br-card-head">
            <div>
                <h3 class="br-card-title">Lịch phòng họp hôm nay</h3>
                <div class="br-card-note">Tổng quan nhanh các lịch đã được đặt trong ngày.</div>
            </div>
        </div>
        <div class="br-card-body">
            @if($todayBookings->count())
                <div class="br-today">
                    @foreach($todayBookings as $item)
                        <div class="br-time-card">
                            <div class="br-time">{{ $fmtDateTime($item->start_at) }} - {{ \Carbon\Carbon::parse($item->end_at)->format('H:i') }}</div>
                            <div class="br-time-title">{{ $item->room_name }} · {{ $item->title }}</div>
                            <div class="br-time-note">{{ $item->organizer_name ?: '—' }} · <span class="br-pill {{ $item->status }}">{{ $statuses[$item->status] ?? $item->status }}</span></div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="br-empty">Hôm nay chưa có lịch booking phòng họp.</div>
            @endif
        </div>
    </div>

    <div class="br-card">
        <div class="br-card-head">
            <div>
                <h3 class="br-card-title">Danh sách booking</h3>
                <div class="br-card-note">Lọc theo ngày, phòng họp và trạng thái để theo dõi nhanh.</div>
            </div>
        </div>
        <div class="br-card-body">
            <form method="GET" class="br-filter" action="{{ route('meeting-room-bookings.index') }}">
                <div class="br-field">
                    <label class="br-label">Ngày</label>
                    <input class="br-input" type="date" name="date" value="{{ $date }}">
                </div>
                <div class="br-field">
                    <label class="br-label">Phòng họp</label>
                    <select class="br-select" name="room_name">
                        <option value="">Tất cả phòng</option>
                        @foreach($rooms as $r)
                            <option value="{{ $r }}" {{ $room === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="br-field">
                    <label class="br-label">Trạng thái</label>
                    <select class="br-select" name="status">
                        <option value="">Tất cả trạng thái</option>
                        @foreach($statuses as $k => $v)
                            <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="br-field">
                    <label class="br-label">&nbsp;</label>
                    <button class="br-btn" type="submit">Lọc lịch</button>
                </div>
            </form>

            <div class="mt-3 br-table-wrap">
                <table class="br-table">
                    <thead>
                        <tr>
                            <th>Phòng họp</th>
                            <th>Nội dung</th>
                            <th>Người đặt</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                            <th>Cập nhật nhanh</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bookings as $item)
                            <tr>
                                <td><strong>{{ $item->room_name }}</strong><br><span class="text-muted">{{ $item->department ?: '—' }}</span></td>
                                <td>{{ $item->title }}<br><span class="text-muted">{{ $item->attendees ?? 1 }} người · {{ $item->note ?: 'Không ghi chú' }}</span></td>
                                <td>{{ $item->organizer_name ?: '—' }}</td>
                                <td>{{ $fmtDateTime($item->start_at) }}<br>đến {{ \Carbon\Carbon::parse($item->end_at)->format('H:i d/m/Y') }}</td>
                                <td><span class="br-pill {{ $item->status }}">{{ $statuses[$item->status] ?? $item->status }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('meeting-room-bookings.update', $item->id) }}" class="br-edit-form">
                                        @csrf
                                        @method('PUT')
                                        <select class="br-select" name="room_name">
                                            @foreach($rooms as $r)
                                                <option value="{{ $r }}" {{ $item->room_name === $r ? 'selected' : '' }}>{{ $r }}</option>
                                            @endforeach
                                        </select>
                                        <input class="br-input" name="title" value="{{ $item->title }}">
                                                                                <select class="br-select" name="organizer_name">
                                            <option value="">—</option>
                                            @foreach($organizerOptions as $orgValue => $orgLabel)
                                                <option value="{{ $orgValue }}" {{ $item->organizer_name === $orgValue ? 'selected' : '' }}>{{ $orgLabel }}</option>
                                            @endforeach
                                            @if($item->organizer_name && !array_key_exists($item->organizer_name, $organizerOptions))
                                                <option value="{{ $item->organizer_name }}" selected>{{ $item->organizer_name }}</option>
                                            @endif
                                        </select>
                                        <input class="br-input" type="datetime-local" name="start_at" value="{{ $inputDateTime($item->start_at) }}">
                                        <input class="br-input" type="datetime-local" name="end_at" value="{{ $inputDateTime($item->end_at) }}">
                                        <select class="br-select" name="status">
                                            @foreach($statuses as $k => $v)
                                                <option value="{{ $k }}" {{ $item->status === $k ? 'selected' : '' }}>{{ $v }}</option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" name="department" value="{{ $item->department }}">
                                        <input type="hidden" name="attendees" value="{{ $item->attendees ?? 1 }}">
                                        <input type="hidden" name="note" value="{{ $item->note }}">
                                        <button class="br-btn" type="submit">Lưu</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('meeting-room-bookings.destroy', $item->id) }}" onsubmit="return confirm('Xóa booking này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="br-btn-danger" type="submit">Xóa</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="br-empty">Chưa có booking phòng họp theo bộ lọc hiện tại.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $bookings->links() }}
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('bookingModal');
        if (!modal) return;
        var openButtons = document.querySelectorAll('[data-open-booking]');
        var closeButtons = document.querySelectorAll('[data-close-booking]');
        var openModal = function () {
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            var firstInput = modal.querySelector('select, input, textarea, button');
            if (firstInput) setTimeout(function(){ firstInput.focus(); }, 80);
        };
        var closeModal = function () {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };
        openButtons.forEach(function (btn) { btn.addEventListener('click', openModal); });
        closeButtons.forEach(function (btn) { btn.addEventListener('click', closeModal); });
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
        @if($errors->any())
            openModal();
        @endif
    });
</script>

@endsection
