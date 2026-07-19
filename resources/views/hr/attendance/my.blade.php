@extends('layouts.app')

@section('content')
<style>
    .attendance-page {
        font-size: 13px;
    }

    .attendance-card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .07);
    }

    .attendance-btn {
        border-radius: 14px;
        font-weight: 850;
        padding: 10px 14px;
    }

    .attendance-note {
        border-radius: 14px;
        font-size: 13px;
    }

    .attendance-status-box {
        border-radius: 18px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .attendance-table th {
        white-space: nowrap;
        font-size: 13px;
    }

    .attendance-table td {
        font-size: 13px;
        vertical-align: middle;
    }

    .attendance-top-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .attendance-action-btn {
        height: 40px;
        border-radius: 999px;
        font-size: 13px;
        font-weight: 850;
        padding: 0 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
    }

    .attendance-dropdown {
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 18px 45px rgba(15, 23, 42, .14);
        padding: 8px;
        min-width: 260px;
    }

    .attendance-dropdown .dropdown-item {
        border-radius: 12px;
        padding: 10px 12px;
        font-size: 13px;
        font-weight: 750;
    }

    .attendance-dropdown .dropdown-item:hover {
        background: #eff6ff;
    }

    .ego-location-help {
        position: fixed;
        left: 18px;
        right: 18px;
        bottom: 18px;
        z-index: 99999;
        max-width: 560px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 20px;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .24);
        overflow: hidden;
        animation: egoLocationSlideUp .18s ease-out;
    }

    @keyframes egoLocationSlideUp {
        from {
            opacity: 0;
            transform: translateY(14px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .ego-location-head {
        padding: 14px 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(135deg, #020617, #075985 55%, #0f766e);
        color: #fff;
    }

    .ego-location-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        background: rgba(255, 255, 255, .16);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        flex: 0 0 auto;
    }

    .ego-location-title {
        font-weight: 950;
        font-size: 15px;
        line-height: 1.25;
    }

    .ego-location-sub {
        font-size: 12px;
        opacity: .82;
        margin-top: 2px;
    }

    .ego-location-body {
        padding: 14px 16px;
    }

    .ego-location-message {
        color: #475569;
        line-height: 1.5;
        margin-bottom: 12px;
    }

    .ego-location-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .ego-location-actions .btn {
        border-radius: 999px;
        font-size: 13px;
        font-weight: 850;
        padding: 8px 14px;
    }

    .ego-location-tip {
        margin-top: 10px;
        padding: 10px 12px;
        border-radius: 14px;
        background: #f8fafc;
        color: #64748b;
        font-size: 12px;
        line-height: 1.45;
    }

    .ego-location-permission {
        margin-top: 8px;
        font-size: 12px;
        color: #0369a1;
        font-weight: 800;
    }

    @media (max-width: 768px) {
        .attendance-page {
            font-size: 12.5px;
        }

        .attendance-card .card-body {
            padding: 16px !important;
        }

        .attendance-table th,
        .attendance-table td {
            font-size: 12px;
        }

        .attendance-top-actions {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr;
        }

        .attendance-action-btn {
            width: 100%;
        }

        .attendance-dropdown {
            width: 100%;
            min-width: 100%;
        }

        .ego-location-help {
            left: 10px;
            right: 10px;
            bottom: 12px;
            border-radius: 18px;
        }

        .ego-location-head {
            padding: 13px;
        }

        .ego-location-body {
            padding: 13px;
        }

        .ego-location-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        .ego-location-actions .btn {
            width: 100%;
        }
    }
</style>

<div class="container-fluid py-4 attendance-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1 fw-bold">Chấm công của tôi</h3>
            <div class="text-muted">Check-in / Check-out và xem lịch sử chấm công cá nhân</div>
        </div>

        <div class="attendance-top-actions">
            <div class="dropdown">
                <button class="btn btn-success attendance-action-btn dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <i class="bi bi-file-earmark-plus"></i>
                    Tạo đơn
                </button>

                <div class="dropdown-menu dropdown-menu-end attendance-dropdown">
                    <a class="dropdown-item" href="{{ route('hr.online-work.create') }}">
                        <i class="bi bi-laptop me-2 text-primary"></i>
                        Đơn xin làm online
                    </a>

                    <a class="dropdown-item" href="{{ route('hr.leave.create') }}">
                        <i class="bi bi-calendar-x me-2 text-danger"></i>
                        Đơn xin nghỉ phép
                    </a>
                </div>
            </div>

            <div class="dropdown">
                <button class="btn btn-warning attendance-action-btn dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false">
                    <i class="bi bi-question-circle"></i>
                    Hướng dẫn chấm công
                </button>

                <div class="dropdown-menu dropdown-menu-end attendance-dropdown">
                    <a class="dropdown-item" href="{{ route('hr.attendance.guide.mobile') }}">
                        <i class="bi bi-phone me-2 text-success"></i>
                        Chấm công trên ĐT
                    </a>

                    <a class="dropdown-item" href="{{ route('hr.attendance.guide.desktop') }}">
                        <i class="bi bi-display me-2 text-primary"></i>
                        Chấm công trên máy tính
                    </a>
                </div>
            </div>

            <a href="{{ route('hr.attendance.index') }}" class="btn btn-outline-primary attendance-action-btn">
                <i class="bi bi-table"></i>
                Bảng công
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4">
            <div class="fw-bold mb-2">Có lỗi xảy ra:</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card attendance-card h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="fw-bold mb-0">Hôm nay</h5>
                        <span class="badge text-bg-light border">{{ now()->format('d/m/Y') }}</span>
                    </div>

                    <div class="small text-muted mb-3">Thông tin chấm công trong ngày và vị trí ghi nhận.</div>

                    <div class="attendance-status-box p-3 mb-3">
                        <div class="mb-2">
                            <div class="text-muted small">Check-in</div>
                            <div class="fw-semibold">{{ optional($todayRecord?->check_in_at)->format('H:i:s') ?? 'Chưa có' }}</div>
                            <div class="small text-muted mt-1">
                                {{ $todayRecord?->check_in_address ?? 'Chưa có địa chỉ check-in' }}
                            </div>
                        </div>

                        <hr>

                        <div class="mb-2">
                            <div class="text-muted small">Check-out</div>
                            <div class="fw-semibold">{{ optional($todayRecord?->check_out_at)->format('H:i:s') ?? 'Chưa có' }}</div>
                            <div class="small text-muted mt-1">
                                {{ $todayRecord?->check_out_address ?? 'Chưa có địa chỉ check-out' }}
                            </div>
                        </div>

                        <hr>

                        <div>
                            <div class="text-muted small">Trạng thái</div>
                            <span class="badge bg-{{ $todayRecord?->status_badge_class ?? 'secondary' }}">
                                {{ $todayRecord?->status_label ?? 'Vắng mặt' }}
                            </span>
                        </div>
                    </div>

                    <form method="POST"
                          action="{{ route('hr.attendance.checkin') }}"
                          class="attendance-form mb-3"
                          data-action-label="check-in">
                        @csrf

                        <input type="hidden" name="lat" class="geo-lat">
                        <input type="hidden" name="lng" class="geo-lng">

                        <textarea name="note"
                                  class="form-control mb-2 attendance-note"
                                  rows="2"
                                  placeholder="Ghi chú check-in (nếu có)"></textarea>

                        <button type="submit"
                                class="btn btn-success w-100 attendance-btn attendance-submit-btn"
                                {{ $todayRecord?->check_in_at ? 'disabled' : '' }}>
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            {{ $todayRecord?->check_in_at ? 'Đã check-in hôm nay' : 'Check-in ngay' }}
                        </button>
                    </form>

                    <form method="POST"
                          action="{{ route('hr.attendance.checkout') }}"
                          class="attendance-form"
                          data-action-label="check-out">
                        @csrf

                        <input type="hidden" name="lat" class="geo-lat">
                        <input type="hidden" name="lng" class="geo-lng">

                        <textarea name="note"
                                  class="form-control mb-2 attendance-note"
                                  rows="2"
                                  placeholder="Ghi chú check-out (nếu có)"></textarea>

                        <button type="submit"
                                class="btn btn-primary w-100 attendance-btn attendance-submit-btn"
                                {{ !$todayRecord?->check_in_at || $todayRecord?->check_out_at ? 'disabled' : '' }}>
                            <i class="bi bi-box-arrow-right me-1"></i>
                            {{ $todayRecord?->check_out_at ? 'Đã check-out hôm nay' : 'Check-out ngay' }}
                        </button>
                    </form>

                    <div class="alert alert-light border rounded-4 mt-3 mb-0 small">
                        Hệ thống bắt buộc lấy vị trí khi bạn bấm nút check-in / check-out.
                        Nếu điện thoại không hiện hỏi quyền vị trí sau vài giây, hệ thống sẽ dừng chờ và hiện nút thử lại.
                        Khi trình duyệt hỏi quyền vị trí, hãy bấm <strong>Cho phép</strong>.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card attendance-card">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h5 class="fw-bold mb-1">Lịch sử chấm công</h5>
                            <div class="text-muted small">Theo dõi lịch sử check-in / check-out trong tháng</div>
                        </div>
                    </div>

                    <form method="GET" class="row g-3 align-items-end mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Tháng</label>
                            <input type="month" name="month" value="{{ $month }}" class="form-control rounded-4">
                        </div>

                        <div class="col-md-2">
                            <button class="btn btn-dark w-100 rounded-4">Lọc</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table align-middle attendance-table">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th>Check-in</th>
                                    <th>Địa chỉ vào</th>
                                    <th>Check-out</th>
                                    <th>Địa chỉ ra</th>
                                    <th>Đi muộn</th>
                                    <th>Về sớm</th>
                                    <th>Công</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($records as $record)
                                    <tr>
                                        <td class="fw-semibold">{{ $record->work_date->format('d/m/Y') }}</td>
                                        <td>{{ optional($record->check_in_at)->format('H:i:s') ?? '-' }}</td>
                                        <td style="min-width: 220px;" class="small text-muted">
                                            {{ $record->check_in_address ?? '-' }}
                                        </td>
                                        <td>{{ optional($record->check_out_at)->format('H:i:s') ?? '-' }}</td>
                                        <td style="min-width: 220px;" class="small text-muted">
                                            {{ $record->check_out_address ?? '-' }}
                                        </td>
                                        <td>{{ $record->late_minutes }} phút</td>
                                        <td>{{ $record->early_leave_minutes }} phút</td>
                                        <td>{{ round($record->work_minutes / 60, 2) }} giờ</td>
                                        <td>
                                            <span class="badge bg-{{ $record->status_badge_class }}">
                                                {{ $record->status_label }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-4 text-muted">
                                            Chưa có dữ liệu chấm công
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.attendance-form');

    let lastForm = null;
    let activeGeoRequest = false;

    function resetButton(button) {
        if (!button) return;

        button.disabled = false;

        if (button.dataset.originalText) {
            button.innerHTML = button.dataset.originalText;
        }
    }

    function setButtonLoading(button) {
        if (!button) return;

        button.dataset.originalText = button.dataset.originalText || button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang lấy vị trí...';
    }

    function removeLocationHelp() {
        const oldBox = document.getElementById('egoLocationHelp');
        if (oldBox) oldBox.remove();
    }

    function showLocationHelp(message, type) {
        removeLocationHelp();

        const box = document.createElement('div');
        box.id = 'egoLocationHelp';
        box.className = 'ego-location-help';

        const icon = type === 'denied' ? 'bi-shield-lock' : 'bi-geo-alt-fill';

        box.innerHTML = `
            <div class="ego-location-head">
                <div class="ego-location-icon">
                    <i class="bi ${icon}"></i>
                </div>

                <div>
                    <div class="ego-location-title">Cần quyền vị trí để chấm công</div>
                    <div class="ego-location-sub">Hệ thống cần GPS để ghi nhận địa điểm check-in / check-out.</div>
                </div>
            </div>

            <div class="ego-location-body">
                <div class="ego-location-message">${message}</div>

                <div class="ego-location-actions">
                    <button type="button" class="btn btn-success" id="egoRetryLocationBtn">
                        <i class="bi bi-arrow-clockwise me-1"></i> Thử lại
                    </button>

                    <button type="button" class="btn btn-outline-secondary" id="egoCloseLocationBtn">
                        Đóng
                    </button>
                </div>

                <div class="ego-location-tip">
                    <b>Cách bật lại trên điện thoại:</b><br>
                    Bấm biểu tượng ổ khóa / chữ i trên thanh địa chỉ → Quyền trang web → Vị trí → Cho phép.
                    Sau đó quay lại trang và bấm <b>Thử lại</b>.
                </div>

                <div class="ego-location-permission" id="egoPermissionStatus">
                    Đang kiểm tra trạng thái quyền vị trí...
                </div>
            </div>
        `;

        document.body.appendChild(box);

        const retryBtn = document.getElementById('egoRetryLocationBtn');
        const closeBtn = document.getElementById('egoCloseLocationBtn');

        if (retryBtn) {
            retryBtn.addEventListener('click', function () {
                removeLocationHelp();

                if (lastForm) {
                    handleAttendanceSubmit(lastForm, true);
                }
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                removeLocationHelp();
            });
        }

        updatePermissionText();
    }

    async function updatePermissionText() {
        const el = document.getElementById('egoPermissionStatus');
        if (!el) return;

        if (!navigator.permissions || !navigator.permissions.query) {
            el.innerHTML = 'Nếu không thấy popup xin quyền, hãy bật thủ công trong cài đặt trình duyệt.';
            return;
        }

        try {
            const result = await navigator.permissions.query({ name: 'geolocation' });

            if (result.state === 'granted') {
                el.innerHTML = 'Trạng thái hiện tại: <b style="color:#16a34a">Đã cho phép vị trí</b>. Nếu vẫn lỗi, hãy bật GPS chính xác cao.';
            } else if (result.state === 'prompt') {
                el.innerHTML = 'Trạng thái hiện tại: <b style="color:#ca8a04">Chưa chọn quyền</b>. Khi trình duyệt hỏi, hãy bấm Cho phép.';
            } else if (result.state === 'denied') {
                el.innerHTML = 'Trạng thái hiện tại: <b style="color:#dc2626">Đang bị chặn vị trí</b>. Bạn cần bật lại trong biểu tượng ổ khóa của trình duyệt.';
            }
        } catch (e) {
            el.innerHTML = 'Nếu không thấy popup xin quyền, hãy bật thủ công trong cài đặt trình duyệt.';
        }
    }

    function getLocationMessage(error, actionLabel) {
        let message = 'Không thể lấy vị trí hiện tại. Bạn phải cho phép vị trí thì mới ' + actionLabel + ' được.';
        let type = 'error';

        if (!error) {
            return { message, type };
        }

        if (error.code === 1) {
            message = 'Bạn đang chặn quyền vị trí. Hãy bấm biểu tượng ổ khóa trên trình duyệt, chọn Vị trí = Cho phép, rồi bấm Thử lại.';
            type = 'denied';
        } else if (error.code === 2) {
            message = 'Điện thoại chưa xác định được vị trí. Hãy bật GPS, bật chế độ chính xác cao, đứng nơi thoáng hơn rồi thử lại.';
        } else if (error.code === 3 || error.code === 'TIMEOUT') {
            message = 'Quá 5 giây vẫn chưa lấy được vị trí. Nếu chưa thấy popup xin quyền, hãy bấm Thử lại hoặc bật quyền vị trí thủ công.';
        }

        return { message, type };
    }

    function getLocationWithHardTimeout(successCallback, errorCallback, timeoutMs) {
        if (!navigator.geolocation) {
            errorCallback({
                code: 0,
                message: 'Trình duyệt không hỗ trợ GPS.'
            });
            return;
        }

        let finished = false;

        const timer = setTimeout(function () {
            if (finished) return;

            finished = true;

            errorCallback({
                code: 'TIMEOUT',
                message: 'Quá thời gian lấy vị trí.'
            });
        }, timeoutMs);

        navigator.geolocation.getCurrentPosition(
            function (position) {
                if (finished) return;

                finished = true;
                clearTimeout(timer);
                successCallback(position);
            },
            function (error) {
                if (finished) return;

                finished = true;
                clearTimeout(timer);
                errorCallback(error);
            },
            {
                enableHighAccuracy: true,
                timeout: timeoutMs,
                maximumAge: 0
            }
        );
    }

    function handleAttendanceSubmit(form, isRetry) {
        const submitButton = form.querySelector('.attendance-submit-btn');
        const latInput = form.querySelector('.geo-lat');
        const lngInput = form.querySelector('.geo-lng');
        const actionLabel = form.dataset.actionLabel || 'thao tác';

        if (activeGeoRequest && !isRetry) {
            return;
        }

        activeGeoRequest = true;
        lastForm = form;

        removeLocationHelp();
        setButtonLoading(submitButton);

        getLocationWithHardTimeout(
            function (position) {
                activeGeoRequest = false;

                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;
                const accuracy = position.coords.accuracy || 9999;

                // Đã bỏ chặn độ chính xác GPS 100m.
                // Vẫn lấy latitude/longitude để hệ thống lưu địa chỉ check-in/check-out,
                // nhưng không chặn nhân viên nếu GPS báo sai số lớn hơn 100m.

                if (latInput) latInput.value = latitude;
                if (lngInput) lngInput.value = longitude;

                form.submit();
            },
            function (error) {
                activeGeoRequest = false;

                resetButton(submitButton);

                const info = getLocationMessage(error, actionLabel);
                showLocationHelp(info.message, info.type);
            },
            5000
        );
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            handleAttendanceSubmit(form, false);
        });
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            updatePermissionText();
        }
    });
});
</script>
@endsection