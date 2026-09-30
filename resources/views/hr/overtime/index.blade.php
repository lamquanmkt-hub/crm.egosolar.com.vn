@extends('layouts.app')

@section('title', 'Tăng ca')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-attendance-promax.css') }}?v={{ filemtime(public_path('css/ego-attendance-promax.css')) }}">
    <style>
        #egoAttendancePromax .ot-kpis{grid-template-columns:repeat(5,minmax(0,1fr))}
        #egoAttendancePromax .ot-panel-body{padding:14px 16px}
        #egoAttendancePromax .ot-filter{display:flex;flex-wrap:wrap;align-items:end;gap:10px}
        #egoAttendancePromax .ot-filter label{display:block;margin-bottom:4px;color:#5b7284;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
        #egoAttendancePromax .ot-filter input,#egoAttendancePromax .ot-filter select{min-width:170px;border:1px solid #d2e2e8;border-radius:10px;padding:8px 10px;font-size:12px;background:#fff}
        #egoAttendancePromax .ot-person strong{display:block}
        #egoAttendancePromax .ot-person small,#egoAttendancePromax .ot-muted{color:#7a8f9c;font-size:10px}
        #egoAttendancePromax .ot-reason{min-width:220px;max-width:340px;white-space:normal;line-height:1.45}
        #egoAttendancePromax .ot-actions{display:flex;flex-direction:column;gap:6px;min-width:210px}
        #egoAttendancePromax .ot-actions form{display:flex;gap:6px}
        #egoAttendancePromax .ot-actions input{flex:1;min-width:0;border:1px solid #d2e2e8;border-radius:9px;padding:6px 8px;font-size:11px}
        #egoAttendancePromax .ot-btn-ok,#egoAttendancePromax .ot-btn-no{border:0;border-radius:9px;padding:6px 10px;font-weight:800;font-size:11px;white-space:nowrap}
        #egoAttendancePromax .ot-btn-ok{background:#eaf9f3;color:#08745a}
        #egoAttendancePromax .ot-btn-ok:hover{background:#d2f2e5}
        #egoAttendancePromax .ot-btn-no{background:#fff0f3;color:#ae3447}
        #egoAttendancePromax .ot-btn-no:hover{background:#ffdfe6}
        #egoAttendancePromax .ot-pager{padding:12px 16px}
        @media(max-width:1050px){#egoAttendancePromax .ot-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
    </style>
@endpush

@section('content')
@php
    $fmtHour = static fn ($n) => rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',');
    $currentUser = auth()->user();
@endphp

<div id="egoAttendancePromax">
    <div class="at-shell">
        @if(session('success'))
            <div class="at-alert"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="at-alert at-alert--danger"><i class="bi bi-exclamation-circle"></i>{{ session('error') }}</div>
        @endif

        <header class="at-hero at-panel">
            <div class="at-heading">
                <div class="at-heading__icon"><i class="bi bi-moon-stars"></i></div>
                <div>
                    <span>CHẤM CÔNG · TĂNG CA</span>
                    <h1>{{ $tab === 'approval' ? 'Duyệt đơn tăng ca' : 'Tăng ca của tôi' }}</h1>
                    <p>Đăng ký làm ngoài giờ, theo dõi trạng thái duyệt. Giờ tăng ca đã duyệt được cộng vào tổng giờ công.</p>
                </div>
            </div>

            <div class="at-actions">
                <a class="at-btn at-btn--light" href="{{ route('hr.overtime.create') }}">
                    <i class="bi bi-plus-circle"></i>Đăng ký tăng ca
                </a>
                <a class="at-btn {{ $tab === 'mine' ? 'at-btn--light' : 'at-btn--glass' }}" href="{{ route('hr.overtime.index', ['tab' => 'mine', 'month' => $month]) }}">
                    <i class="bi bi-person"></i>Của tôi
                    @if($myPendingCount > 0)
                        <span class="at-badge-count">{{ $myPendingCount }}</span>
                    @endif
                </a>
                @if($canReview)
                    <a class="at-btn {{ $tab === 'approval' ? 'at-btn--light' : 'at-btn--glass' }}" href="{{ route('hr.overtime.index', ['tab' => 'approval', 'status' => 'pending', 'month' => $month]) }}">
                        <i class="bi bi-check2-square"></i>Cần duyệt
                        @if($pendingReviewCount > 0)
                            <span class="at-badge-count">{{ $pendingReviewCount }}</span>
                        @endif
                    </a>
                @endif
                <a class="at-btn at-btn--glass" href="{{ route('hr.attendance.my', ['month' => $month]) }}">
                    <i class="bi bi-fingerprint"></i>Chấm công của tôi
                </a>
            </div>
        </header>

        <section class="at-kpis ot-kpis">
            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-journal-text"></i></div>
                <label>Tổng đơn</label>
                <strong>{{ number_format($summary['total']) }}</strong>
                <small>Tháng {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('m/Y') }}</small>
            </article>
            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-hourglass-split"></i></div>
                <label>Chờ duyệt</label>
                <strong>{{ number_format($summary['pending']) }}</strong>
                <small>Đơn đang chờ</small>
            </article>
            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-check2-circle"></i></div>
                <label>Đã duyệt</label>
                <strong>{{ number_format($summary['approved']) }}</strong>
                <small>Đã ghi vào chấm công</small>
            </article>
            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-x-circle"></i></div>
                <label>Từ chối</label>
                <strong>{{ number_format($summary['rejected']) }}</strong>
                <small>Không tính giờ</small>
            </article>
            <article class="at-kpi at-panel">
                <div class="at-kpi__icon"><i class="bi bi-moon-stars"></i></div>
                <label>Giờ tăng ca đã duyệt</label>
                <strong>{{ $fmtHour($summary['hours']) }}</strong>
                <small>Giờ</small>
            </article>
        </section>

        <section class="at-panel">
            <div class="at-card-head">
                <div>
                    <h2><i class="bi bi-list-check"></i>{{ $tab === 'approval' ? 'Đơn cần duyệt' : 'Đơn tăng ca của tôi' }}</h2>
                    <p>{{ $tab === 'approval' ? 'Đơn nhân viên gửi cho bạn duyệt' . ($canManage ? ' (HR / Admin thấy mọi đơn)' : '') . '. Không thể tự duyệt đơn của chính mình.' : 'Đơn chờ duyệt hiển thị trên cùng.' }}</p>
                </div>
            </div>

            <div class="ot-panel-body">
                <form method="GET" class="ot-filter">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <div>
                        <label>Tháng</label>
                        <input type="month" name="month" value="{{ $month }}">
                    </div>
                    <div>
                        <label>Trạng thái</label>
                        <select name="status">
                            <option value="">Tất cả</option>
                            <option value="pending" @selected($status === 'pending')>Chờ duyệt</option>
                            <option value="approved" @selected($status === 'approved')>Đã duyệt</option>
                            <option value="rejected" @selected($status === 'rejected')>Từ chối</option>
                        </select>
                    </div>
                    @if($tab === 'approval' && $canManage)
                        <div>
                            <label>Nhân viên</label>
                            <select name="user_id">
                                <option value="">Tất cả</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) $userId === (int) $employee->id)>
                                        {{ $employee->name }}@if(optional($employee->department)->name) · {{ $employee->department->name }}@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button class="at-btn at-btn--primary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>
                    <a class="at-btn at-btn--light" href="{{ route('hr.overtime.index', ['tab' => $tab]) }}">Xoá lọc</a>
                </form>
            </div>

            <div class="at-table-wrap">
                <table class="at-table">
                    <thead>
                        <tr>
                            @if($tab === 'approval')<th>Nhân viên</th>@endif
                            <th>Ngày</th>
                            <th>Thời gian</th>
                            <th>Số giờ</th>
                            <th>Lý do</th>
                            <th>Người duyệt</th>
                            <th>Trạng thái</th>
                            @if($tab === 'approval')<th>Thao tác</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $item)
                            <tr>
                                @if($tab === 'approval')
                                    <td class="ot-person">
                                        <strong>{{ $item->user->name ?? '—' }}</strong>
                                        <small>{{ optional(optional($item->user)->department)->name ?? '' }}</small>
                                    </td>
                                @endif
                                <td><strong>{{ optional($item->overtime_date)->format('d/m/Y') }}</strong></td>
                                <td>{{ optional($item->start_at)->format('H:i') }} – {{ optional($item->end_at)->format('H:i') }}
                                    @if($item->end_at && $item->start_at && ! $item->end_at->isSameDay($item->start_at))
                                        <div class="ot-muted">qua đêm</div>
                                    @endif
                                </td>
                                <td><strong>{{ $fmtHour($item->hours) }} giờ</strong></td>
                                <td><div class="ot-reason">{{ $item->reason ?: '—' }}</div></td>
                                <td>{{ $item->approver->name ?? 'HR / Admin' }}</td>
                                <td>
                                    <span class="at-status-pill at-status-pill--{{ $item->status_badge_class }}">{{ $item->status_label }}</span>
                                    @if($item->approval_note)
                                        <div class="ot-muted" style="margin-top:4px">{{ $item->approval_note }}</div>
                                    @endif
                                    @if($item->approvedBy && $item->status !== 'pending')
                                        <div class="ot-muted">{{ $item->approvedBy->name }}</div>
                                    @endif
                                </td>
                                @if($tab === 'approval')
                                    <td>
                                        @if($item->status === 'pending' && $access->canApprove($currentUser, $item))
                                            <div class="ot-actions">
                                                <form method="POST" action="{{ route('hr.overtime.approve', $item) }}">
                                                    @csrf
                                                    <input type="text" name="approval_note" placeholder="Ghi chú duyệt (không bắt buộc)">
                                                    <button class="ot-btn-ok" type="submit"><i class="bi bi-check-lg"></i> Duyệt</button>
                                                </form>
                                                <form method="POST" action="{{ route('hr.overtime.reject', $item) }}">
                                                    @csrf
                                                    <input type="text" name="approval_note" placeholder="Lý do từ chối">
                                                    <button class="ot-btn-no" type="submit"><i class="bi bi-x-lg"></i> Từ chối</button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="ot-muted">—</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $tab === 'approval' ? 8 : 6 }}"><div class="at-empty">{{ $tab === 'approval' ? 'Không có đơn tăng ca cần duyệt.' : 'Bạn chưa có đơn tăng ca nào trong tháng này.' }}</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($requests->hasPages())
                <div class="ot-pager">{{ $requests->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
