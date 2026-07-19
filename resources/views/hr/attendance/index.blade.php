@extends('layouts.app')

@section('content')
<div class="container-fluid py-4 attendance-stats-page">

    <div class="attendance-hero mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="attendance-kicker mb-2">Admin Attendance Analytics</div>
                <h2 class="attendance-title mb-1">Thống kê chấm công</h2>
                <div class="attendance-subtitle">
                    Theo dõi tình hình chấm công nhân sự, số ngày hợp lệ, tỷ lệ hoàn tất và các trường hợp bất thường.
                </div>
            </div>

<div class="d-flex gap-2 flex-wrap">
    @if(\Illuminate\Support\Facades\Route::has('hr.attendance.settings'))
        <a href="{{ route('hr.attendance.settings') }}" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-gear me-1"></i> Cài đặt
        </a>
    @endif


    <a href="{{ route('hr.attendance.export', request()->query()) }}" class="btn btn-success rounded-pill px-4">
        <i class="bi bi-file-earmark-excel me-1"></i> Xuất Excel
    </a>

    <a href="{{ route('hr.attendance.export-pdf', request()->query()) }}" class="btn btn-danger rounded-pill px-4">
        <i class="bi bi-file-earmark-pdf me-1"></i> Xuất PDF
    </a>

    <a href="{{ route('hr.attendance.my') }}" class="btn btn-outline-primary rounded-pill px-4">
        <i class="bi bi-person-check me-1"></i> Chấm công của tôi
    </a>
</div>
        </div>

        <div class="row g-3 mt-2">
            <div class="col-12 col-md-4">
                <div class="attendance-hero-box">
                    <div class="attendance-hero-label">Tháng thống kê</div>
                    <div class="attendance-hero-value">{{ \Carbon\Carbon::parse($start)->format('m/Y') }}</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="attendance-hero-box">
                    <div class="attendance-hero-label">Khoảng dữ liệu</div>
                    <div class="attendance-hero-value">
                        {{ $start->format('d/m/Y') }} - {{ $end->format('d/m/Y') }}
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="attendance-hero-box">
                    <div class="attendance-hero-label">Tỷ lệ hoàn tất</div>
                    <div class="attendance-hero-value">{{ $summary['completion_rate'] }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card attendance-glass mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tháng</label>
                    <input type="month" name="month" value="{{ $month }}" class="form-control attendance-input">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Nhân viên</label>
                    <select name="user_id" class="form-select attendance-input">
                        <option value="">Tất cả nhân viên</option>
                        @foreach($employees as $employee)
                            <option value="{{ $employee->id }}" {{ request('user_id') == $employee->id ? 'selected' : '' }}>
                                {{ $employee->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Phòng ban</label>
                    <select name="department_id" class="form-select attendance-input">
                        <option value="">Tất cả phòng ban</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                {{ $department->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select name="status" class="form-select attendance-input">
                        <option value="">Tất cả trạng thái</option>
                        @foreach(['checked_in','late','completed','early_leave','incomplete','absent'] as $status)
                            <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                {{ match($status) {
                                    'checked_in' => 'Đã check-in',
                                    'late' => 'Đi muộn',
                                    'completed' => 'Hoàn tất',
                                    'early_leave' => 'Về sớm',
                                    'incomplete' => 'Thiếu check-out',
                                    'absent' => 'Vắng mặt',
                                    default => $status
                                } }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end pt-2">
                    <a href="{{ route('hr.attendance.index') }}" class="btn btn-light rounded-pill px-4">
                        Reset
                    </a>
                    <button class="btn attendance-filter-btn rounded-pill px-4">
                        <i class="bi bi-funnel me-1"></i> Lọc thống kê
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-2 mb-3 attendance-metrics-row">
        <div class="col-md-6 col-xl-3">
            <div class="card attendance-kpi attendance-kpi-blue h-100">
                <div class="card-body">
                    <div class="attendance-kpi-label">Số nhân viên có dữ liệu</div>
                    <div class="attendance-kpi-value">{{ $summary['employees'] }}</div>
                    <div class="attendance-kpi-sub">Trong kỳ đã lọc</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card attendance-kpi attendance-kpi-green h-100">
                <div class="card-body">
                    <div class="attendance-kpi-label">Ngày chấm công hợp lệ</div>
                    <div class="attendance-kpi-value">{{ $summary['valid_days'] }}</div>
                    <div class="attendance-kpi-sub">Có check-in</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card attendance-kpi attendance-kpi-cyan h-100">
                <div class="card-body">
                    <div class="attendance-kpi-label">Nhân sự check-in hôm nay</div>
                    <div class="attendance-kpi-value">{{ $summary['checked_in_today'] }}</div>
                    <div class="attendance-kpi-sub">Theo ngày hiện tại</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="card attendance-kpi attendance-kpi-orange h-100">
                <div class="card-body">
                    <div class="attendance-kpi-label">Tổng giờ công</div>
                    <div class="attendance-kpi-value">{{ $summary['total_hours'] }}</div>
                    <div class="attendance-kpi-sub">Giờ trong kỳ</div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-xl-4">
            <div class="card attendance-mini-card h-100">
                <div class="card-body">
                    <div class="attendance-mini-label">Hoàn tất</div>
                    <div class="attendance-mini-value text-success">{{ $summary['completed'] }}</div>
                    <div class="attendance-mini-sub">Bản ghi đã check-out đầy đủ</div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-xl-4">
            <div class="card attendance-mini-card h-100">
                <div class="card-body">
                    <div class="attendance-mini-label">Đi muộn</div>
                    <div class="attendance-mini-value text-warning">{{ $summary['late'] }}</div>
                    <div class="attendance-mini-sub">Số lượt trong kỳ</div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-xl-4">
            <div class="card attendance-mini-card h-100">
                <div class="card-body">
                    <div class="attendance-mini-label">Thiếu check-out</div>
                    <div class="attendance-mini-value text-danger">{{ $summary['incomplete'] }}</div>
                    <div class="attendance-mini-sub">Cần kiểm tra lại</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bảng tổng hợp theo nhân viên --}}
    <div class="card attendance-glass mb-4">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center flex-wrap gap-2 pt-4 px-4">
            <div>
                <h5 class="fw-bold mb-1">Thống kê chấm công theo nhân viên</h5>
                <div class="text-muted small">
                    Hiển thị đầy đủ nhân viên trong bộ lọc, kể cả người chưa phát sinh chấm công trong tháng.
                </div>
            </div>
            <span class="attendance-chip">Employee Summary</span>
        </div>

        <div class="card-body pt-3 px-4 pb-4">
            <div class="table-responsive attendance-table-wrap">
                <table class="table align-middle attendance-table mb-0">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Phòng ban</th>
                            <th>Chức vụ</th>
                            <th>Ngày công hợp lệ</th>
                            <th>Hoàn tất</th>
                            <th>Đi muộn</th>
                            <th>Về sớm</th>
                            <th>Thiếu check-out</th>
                            <th>Tổng giờ công</th>
                            <th>Tỷ lệ đúng giờ</th>
                            <th>Xếp hạng</th>
                            <th>Chi tiết</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($employeeStats as $item)
                            <tr>
                                <td class="fw-semibold">{{ $item->employee_name }}</td>
                                <td>{{ $item->department_name }}</td>
                                <td>{{ $item->position_name }}</td>
                                <td>{{ $item->valid_days }}</td>
                                <td>{{ $item->completed_days }}</td>
                                <td>{{ $item->late_days }}</td>
                                <td>{{ $item->early_leave_days }}</td>
                                <td>{{ $item->incomplete_days }}</td>
                                <td>{{ $item->total_hours }} giờ</td>
                                <td>{{ $item->ontime_rate }}%</td>
                                <td>
                                    <span class="badge bg-{{ $item->rank_badge }}">
                                        {{ $item->rank_label }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('hr.attendance.index', [
    'month' => $month,
    'user_id' => $item->user_id,
    'department_id' => request('department_id'),
    'status' => request('status'),
]) }}#attendance-detail-records"
   class="attendance-eye-btn"
   title="Xem chi tiết">
    <i class="bi bi-eye"></i>
</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    Không có nhân viên nào trong bộ lọc này
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Bảng chi tiết theo ngày --}}
    <div class="card attendance-glass" id="attendance-detail-records">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center flex-wrap gap-2 pt-4 px-4">
            <div>
                <h5 class="fw-bold mb-1">Chi tiết bảng công theo ngày</h5>
                <div class="text-muted small">
                    Danh sách từng ngày chấm công của nhân viên theo bộ lọc hiện tại.
                </div>
            </div>
            <span class="attendance-chip">Daily Detail Records</span>
        </div>

        <div class="card-body pt-3 px-4 pb-4">
            <div class="table-responsive attendance-table-wrap">
                <table class="table align-middle attendance-table mb-0">
                    <thead>
                        <tr>
                            <th>Ngày</th>
                            <th>Nhân viên</th>
                            <th>Phòng ban</th>
                            <th>Check-in</th>
                            <th>Địa chỉ vào</th>
                            <th>Check-out</th>
                            <th>Địa chỉ ra</th>
                            <th>Đi muộn</th>
                            <th>Về sớm</th>
                            <th>Giờ công</th>
                            <th>Ghi chú / đơn nghỉ</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td class="fw-semibold">{{ $record->work_date->format('d/m/Y') }}</td>
                                <td>{{ $record->user->name ?? '-' }}</td>
                                <td>{{ optional($record->user->department)->name ?? '-' }}</td>
                                <td>{{ optional($record->check_in_at)->format('H:i:s') ?? '-' }}</td>
                                <td class="text-muted small" style="min-width: 240px;">
                                    {{ $record->check_in_address ?? '-' }}
                                </td>
                                <td>{{ optional($record->check_out_at)->format('H:i:s') ?? '-' }}</td>
                                <td class="text-muted small" style="min-width: 240px;">
                                    {{ $record->check_out_address ?? '-' }}
                                </td>
                                <td>{{ $record->late_minutes }} phút</td>
                                <td>{{ $record->early_leave_minutes }} phút</td>
                                <td>{{ round($record->work_minutes / 60, 2) }} giờ</td>
                                <td class="small" style="min-width: 280px; white-space: pre-line;">
                                    @if($record->note)
                                        <span class="{{ str_contains((string) $record->note, 'Đơn HR') ? 'text-success fw-semibold' : 'text-muted' }}">{{ $record->note }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $record->status_badge_class }}">
                                        {{ $record->status_label }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-5 text-muted">
                                    Chưa có dữ liệu chi tiết trong bộ lọc này
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $records->links() }}
            </div>
        </div>
    </div>
</div>

<style>
.attendance-stats-page{
    --att-primary:#0ea5e9;
    --att-cyan:#22d3ee;
    --att-ink:#0f172a;
    --att-muted:#64748b;
}

.attendance-hero{
    padding:28px;
    border-radius:28px;
    background:
        radial-gradient(circle at top right, rgba(34,211,238,.16), transparent 28%),
        radial-gradient(circle at left bottom, rgba(14,165,233,.10), transparent 28%),
        linear-gradient(135deg,#ffffff 0%, #f8fcff 52%, #f4f8ff 100%);
    border:1px solid rgba(226,232,240,.9);
    box-shadow:0 18px 60px rgba(15,23,42,.06);
}

.attendance-kicker{
    display:inline-flex;
    align-items:center;
    padding:8px 14px;
    border-radius:999px;
    background:rgba(14,165,233,.10);
    color:#0369a1;
    font-size:.82rem;
    font-weight:800;
}

.attendance-title{
    font-size:2.2rem;
    line-height:1.1;
    font-weight:900;
    color:var(--att-ink);
}

.attendance-subtitle{
    color:var(--att-muted);
    font-size:1rem;
}

.attendance-hero-box{
    height:100%;
    padding:18px 20px;
    border-radius:20px;
    background:rgba(255,255,255,.74);
    border:1px solid rgba(226,232,240,.9);
}

.attendance-hero-label{
    color:var(--att-muted);
    font-size:.82rem;
    font-weight:700;
    margin-bottom:8px;
}

.attendance-hero-value{
    color:var(--att-ink);
    font-weight:800;
    font-size:1.06rem;
}

.attendance-glass{
    border:none !important;
    border-radius:26px !important;
    background:linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.96));
    box-shadow:0 18px 60px rgba(15,23,42,.06) !important;
    border:1px solid rgba(226,232,240,.9) !important;
    overflow:hidden;
}

.attendance-input{
    height:48px;
    border-radius:16px;
    border:1px solid #dbe4ee;
    box-shadow:none !important;
}

.attendance-input:focus{
    border-color:#38bdf8;
    box-shadow:0 0 0 4px rgba(56,189,248,.12) !important;
}

.attendance-filter-btn{
    background:linear-gradient(135deg,#22d3ee 0%, #0ea5e9 100%);
    color:#fff;
    border:none;
    font-weight:800;
}

.attendance-filter-btn:hover{
    color:#fff;
}

.attendance-kpi{
    border:none !important;
    border-radius:24px !important;
    box-shadow:0 14px 40px rgba(15,23,42,.06) !important;
}

.attendance-kpi .card-body{
    padding:22px;
}

.attendance-kpi-label{
    color:#64748b;
    font-weight:700;
    margin-bottom:8px;
}

.attendance-kpi-value{
    font-size:2rem;
    font-weight:900;
    line-height:1;
    color:#0f172a;
    margin-bottom:8px;
}

.attendance-kpi-sub{
    color:#64748b;
    font-size:.88rem;
}

.attendance-kpi-blue{ background:linear-gradient(135deg,#eef6ff,#dceeff); }
.attendance-kpi-green{ background:linear-gradient(135deg,#eafbf1,#d6f8e3); }
.attendance-kpi-cyan{ background:linear-gradient(135deg,#ecfeff,#cffafe); }
.attendance-kpi-orange{ background:linear-gradient(135deg,#fff4ea,#ffe6ca); }

.attendance-mini-card{
    border:none !important;
    border-radius:22px !important;
    background:#fff;
    box-shadow:0 12px 36px rgba(15,23,42,.05) !important;
    border:1px solid rgba(226,232,240,.9) !important;
}

.attendance-mini-label{
    color:#64748b;
    font-weight:700;
    margin-bottom:8px;
}

.attendance-mini-value{
    font-size:2rem;
    line-height:1;
    font-weight:900;
    margin-bottom:8px;
}

.attendance-mini-sub{
    color:#64748b;
    font-size:.88rem;
}

.attendance-chip{
    display:inline-flex;
    align-items:center;
    padding:8px 12px;
    border-radius:999px;
    background:#f8fafc;
    border:1px solid #e2e8f0;
    color:#64748b;
    font-size:.78rem;
    font-weight:800;
}

.attendance-table-wrap{
    border:1px solid #e8eef5;
    border-radius:20px;
    overflow:hidden;
}

.attendance-table thead th{
    background:#f8fbff;
    color:#475569;
    font-size:.82rem;
    font-weight:800;
    text-transform:uppercase;
    border-bottom:1px solid #e8eef5;
    padding:14px 16px;
    white-space:nowrap;
}

.attendance-table tbody td{
    padding:15px 16px;
    border-color:#eef2f7;
    color:#1e293b;
    vertical-align:middle;
}

.attendance-table tbody tr:hover{
    background:#fbfdff;
}
.attendance-eye-btn{
    width: 38px;
    height: 38px;
    border-radius: 999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #bfdbfe;
    background: #eff6ff;
    color: #2563eb;
    text-decoration: none;
    transition: all .18s ease;
}

.attendance-eye-btn:hover{
    background: #2563eb;
    color: #fff;
    border-color: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(37,99,235,.22);
}
@media (max-width: 767px){
    .attendance-hero{
        padding:20px;
        border-radius:22px;
    }

    .attendance-title{
        font-size:1.8rem;
    }

    .attendance-kpi-value,
    .attendance-mini-value{
        font-size:1.6rem;
    }
}

/* EGO_ATTENDANCE_COMPACT_UI_START */
.attendance-stats-page{
    font-size:14px;
    color:#0f172a;
}

.attendance-hero{
    padding:22px !important;
    border-radius:22px !important;
    box-shadow:0 12px 34px rgba(15,23,42,.055) !important;
}

.attendance-kicker{
    padding:6px 11px !important;
    font-size:.74rem !important;
    letter-spacing:.01em;
}

.attendance-title{
    font-size:1.72rem !important;
    line-height:1.18 !important;
    letter-spacing:-.03em;
}

.attendance-subtitle{
    font-size:.92rem !important;
    line-height:1.45;
}

.attendance-hero-box{
    padding:14px 16px !important;
    border-radius:16px !important;
}

.attendance-hero-label{
    font-size:.76rem !important;
    margin-bottom:5px !important;
}

.attendance-hero-value{
    font-size:.95rem !important;
}

.attendance-glass{
    border-radius:20px !important;
    box-shadow:0 10px 28px rgba(15,23,42,.045) !important;
}

.attendance-glass .card-body{
    padding:18px !important;
}

.attendance-input{
    height:42px !important;
    border-radius:12px !important;
    font-size:.9rem !important;
}

.attendance-stats-page .form-label{
    font-size:.82rem !important;
    margin-bottom:6px !important;
    color:#334155;
}

.attendance-stats-page .btn{
    min-height:38px;
    font-size:.86rem !important;
    font-weight:700;
}

.attendance-kpi{
    border-radius:18px !important;
    box-shadow:0 10px 26px rgba(15,23,42,.045) !important;
}

.attendance-kpi .card-body{
    padding:17px 18px !important;
}

.attendance-kpi-label,
.attendance-mini-label{
    font-size:.84rem !important;
    margin-bottom:7px !important;
}

.attendance-kpi-value,
.attendance-mini-value{
    font-size:1.55rem !important;
    letter-spacing:-.035em;
    margin-bottom:7px !important;
}

.attendance-kpi-sub,
.attendance-mini-sub{
    font-size:.8rem !important;
}

.attendance-mini-card{
    border-radius:18px !important;
    box-shadow:0 8px 24px rgba(15,23,42,.04) !important;
}

.attendance-mini-card .card-body{
    padding:17px 18px !important;
}

.attendance-chip{
    padding:6px 10px !important;
    font-size:.7rem !important;
}

.attendance-table-wrap{
    border-radius:16px !important;
}

.attendance-table thead th{
    font-size:.72rem !important;
    padding:11px 13px !important;
    background:#f8fafc !important;
}

.attendance-table tbody td{
    font-size:.86rem !important;
    padding:11px 13px !important;
}

.attendance-eye-btn{
    width:32px !important;
    height:32px !important;
}

@media (max-width: 767px){
    .attendance-title{
        font-size:1.45rem !important;
    }

    .attendance-hero{
        padding:18px !important;
    }

    .attendance-kpi-value,
    .attendance-mini-value{
        font-size:1.35rem !important;
    }
}
/* EGO_ATTENDANCE_COMPACT_UI_END */


/* EGO_ATTENDANCE_METRICS_SLIM_START */
.attendance-metrics-row{
    display:grid !important;
    grid-template-columns:repeat(7, minmax(0, 1fr)) !important;
    gap:10px !important;
    margin-bottom:18px !important;
}

.attendance-metrics-row > [class*="col-"]{
    width:100% !important;
    max-width:100% !important;
    padding:0 !important;
}

.attendance-metrics-row .card{
    min-height:86px !important;
    height:86px !important;
    border-radius:16px !important;
}

.attendance-metrics-row .card-body{
    padding:13px 14px !important;
}

.attendance-metrics-row .attendance-kpi-label,
.attendance-metrics-row .attendance-mini-label{
    font-size:.76rem !important;
    margin-bottom:5px !important;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

.attendance-metrics-row .attendance-kpi-value,
.attendance-metrics-row .attendance-mini-value{
    font-size:1.32rem !important;
    line-height:1 !important;
    margin-bottom:5px !important;
}

.attendance-metrics-row .attendance-kpi-sub,
.attendance-metrics-row .attendance-mini-sub{
    font-size:.72rem !important;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}

@media (max-width: 1400px){
    .attendance-metrics-row{
        grid-template-columns:repeat(4, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 992px){
    .attendance-metrics-row{
        grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
    }
}

@media (max-width: 576px){
    .attendance-metrics-row{
        grid-template-columns:1fr !important;
    }
}
/* EGO_ATTENDANCE_METRICS_SLIM_END */

</style>
@endsection