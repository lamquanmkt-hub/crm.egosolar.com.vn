@extends('layouts.app')

@section('title', 'Công việc hàng tuần')

@section('content')
<div class="container-fluid px-4 mt-3 weekly-page">

    {{-- Header + Filter --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Công việc hàng tuần</h3>
            <div class="text-muted small">Dashboard tự tổng hợp theo khoảng ngày</div>
        </div>

        <form class="d-flex flex-wrap gap-2 align-items-end" method="GET">
            <div>
                <label class="form-label small mb-1">Từ ngày</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from->format('Y-m-d') }}">
            </div>
            <div>
                <label class="form-label small mb-1">Đến ngày</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to->format('Y-m-d') }}">
            </div>
            <button class="btn btn-ego btn-sm px-3">
                <i class="bi bi-funnel"></i> Lọc
            </button>
        </form>
    </div>

    {{-- Dashboard --}}
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-3">
            <div class="cc-stat">
                <div class="cc-stat-title">Tổng task</div>
                <div class="cc-stat-value">{{ $total }}</div>
                <div class="cc-stat-sub">Trong khoảng ngày đã chọn</div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="cc-stat">
                <div class="cc-stat-title">Hoàn thành</div>
                <div class="cc-stat-value">{{ $done }}</div>
                <div class="cc-stat-sub">Done</div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="cc-stat">
                <div class="cc-stat-title">Đang làm</div>
                <div class="cc-stat-value">{{ $doing }}</div>
                <div class="cc-stat-sub">Doing</div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="cc-stat cc-stat-danger">
                <div class="cc-stat-title">Quá hạn</div>
                <div class="cc-stat-value">{{ $overdue }}</div>
                <div class="cc-stat-sub">Chưa done & quá due</div>
            </div>
        </div>
    </div>

    {{-- Priority strip --}}
    <div class="cc-strip mb-3">
        <div class="cc-strip-item">
            <span class="dot dot-high"></span> High: <b>{{ $priorityCount['high'] }}</b>
        </div>
        <div class="cc-strip-item">
            <span class="dot dot-medium"></span> Medium: <b>{{ $priorityCount['medium'] }}</b>
        </div>
        <div class="cc-strip-item">
            <span class="dot dot-low"></span> Low: <b>{{ $priorityCount['low'] }}</b>
        </div>
        <div class="ms-auto text-muted small">
            <i class="bi bi-info-circle"></i> Cột “Số ngày” tự tính: due - start
        </div>
    </div>

    {{-- Table --}}
    <div class="card cc-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 cc-table">
                    <thead class="table-light">
                    <tr>
                        <th>Tên công việc</th>
                        <th style="width:110px;">Priority</th>
                        <th style="width:140px;">Hạng mục</th>
                        <th style="width:180px;">Chịu trách nhiệm</th>
                        <th style="width:120px;">Bắt đầu</th>
                        <th style="width:120px;">Deadline</th>
                        <th style="width:120px;">Tiến độ</th>
                        <th style="width:110px;">Số ngày</th>
                        <th style="width:120px;">Trạng thái</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($tasks as $t)
                        @php
                            $days = null;
                            if ($t->start_date && $t->due_date) {
                                $days = \Carbon\Carbon::parse($t->start_date)->diffInDays(\Carbon\Carbon::parse($t->due_date)) + 1;
                            }

                            $pClass = match($t->priority){
                                'high' => 'pill-high',
                                'medium' => 'pill-medium',
                                default => 'pill-low'
                            };

                            $statusClass = match($t->status){
                                'done' => 'st-done',
                                'doing' => 'st-doing',
                                'overdue' => 'st-overdue',
                                default => 'st-pending'
                            };
                        @endphp

                        <tr>
                            <td class="fw-semibold">
                                {{ $t->title }}
                                @if(!empty($t->note))
                                    <div class="text-muted small mt-1">{{ $t->note }}</div>
                                @endif
                            </td>
                            <td><span class="pill {{ $pClass }}">{{ strtoupper($t->priority) }}</span></td>
                            <td class="text-muted">{{ $t->category ?? '-' }}</td>
                            <td>{{ $t->assignee ?? '-' }}</td>
                            <td class="text-muted">{{ $t->start_date ? \Carbon\Carbon::parse($t->start_date)->format('d/m/Y') : '-' }}</td>
                            <td class="text-muted">{{ $t->due_date ? \Carbon\Carbon::parse($t->due_date)->format('d/m/Y') : '-' }}</td>

                            <td>
                                <div class="progress cc-progress" role="progressbar" aria-valuenow="{{ (int)$t->progress }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ (int)$t->progress }}%"></div>
                                </div>
                                <div class="small text-muted mt-1">{{ (int)$t->progress }}%</div>
                            </td>

                            <td class="text-muted">{{ $days ?? '-' }}</td>
                            <td><span class="status {{ $statusClass }}">{{ strtoupper($t->status) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Chưa có task nào trong khoảng ngày này</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection

@push('styles')
<style>
.weekly-page{
    --ego: #0aa6b6;         /* xanh nước EGO */
    --ego-2: #087e8a;
    --bg-soft: rgba(10,166,182,.10);
    --card: #fff;
    --border: rgba(0,0,0,.07);
}

.weekly-page .cc-card{
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,.04);
}

.weekly-page .btn-ego{
    background: linear-gradient(135deg, var(--ego), var(--ego-2));
    color: #fff;
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 20px rgba(10,166,182,.22);
}
.weekly-page .btn-ego:hover{ filter: brightness(.98); color:#fff; }

.weekly-page .cc-stat{
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 18px;
    padding: 16px 16px;
    box-shadow: 0 10px 26px rgba(0,0,0,.03);
    position: relative;
    overflow: hidden;
}
.weekly-page .cc-stat:before{
    content:"";
    position:absolute;
    inset:-40% -30% auto auto;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(10,166,182,.22), transparent 60%);
    transform: rotate(20deg);
}
.weekly-page .cc-stat-danger:before{
    background: radial-gradient(circle, rgba(220,53,69,.18), transparent 60%);
}
.weekly-page .cc-stat-title{ font-size: 13px; color:#667; font-weight:700; letter-spacing:.2px; position:relative; }
.weekly-page .cc-stat-value{ font-size: 28px; font-weight:900; margin-top:6px; position:relative; }
.weekly-page .cc-stat-sub{ font-size: 12px; color:#889; position:relative; }

.weekly-page .cc-strip{
    display:flex;
    flex-wrap:wrap;
    gap:10px 16px;
    align-items:center;
    background: var(--bg-soft);
    border: 1px solid rgba(10,166,182,.18);
    border-radius: 16px;
    padding: 10px 12px;
}
.weekly-page .cc-strip-item{ font-size: 13px; color:#123; display:flex; align-items:center; gap:8px; }
.weekly-page .dot{ width:10px; height:10px; border-radius:999px; display:inline-block; }
.weekly-page .dot-high{ background:#dc3545; }
.weekly-page .dot-medium{ background:#ffc107; }
.weekly-page .dot-low{ background:#198754; }

.weekly-page .cc-table th{
    font-size: 13px;
    letter-spacing: .2px;
    white-space: nowrap;
}
.weekly-page .cc-table td{ vertical-align: middle; }

.weekly-page .pill{
    display:inline-flex;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .2px;
}
.weekly-page .pill-high{ background: rgba(220,53,69,.12); color:#b02a37; }
.weekly-page .pill-medium{ background: rgba(255,193,7,.16); color:#8a6d00; }
.weekly-page .pill-low{ background: rgba(25,135,84,.12); color:#146c43; }

.weekly-page .status{
    display:inline-flex;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 900;
    letter-spacing: .2px;
    color:#fff;
}
.weekly-page .st-done{ background:#198754; }
.weekly-page .st-doing{ background: var(--ego); }
.weekly-page .st-overdue{ background:#dc3545; }
.weekly-page .st-pending{ background:#6c757d; }

.weekly-page .cc-progress{
    height: 8px;
    border-radius: 999px;
    overflow:hidden;
    background: rgba(0,0,0,.06);
}
.weekly-page .progress-bar{
    background: linear-gradient(90deg, var(--ego), var(--ego-2));
}

.weekly-page .form-control, .weekly-page .form-select{
    border-radius: 12px;
    border: 1px solid var(--border);
}
</style>
@endpush
