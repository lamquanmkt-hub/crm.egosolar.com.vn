@extends('layouts.app')

@section('content')
@php
    $fmtHour = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1 fw-bold">Đăng ký tăng ca</h3>
            <div class="text-muted">Theo dõi đơn tăng ca và duyệt để ghi chú vào bảng chấm công</div>
        </div>

        <a href="{{ route('hr.overtime.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-plus-circle me-1"></i> Tạo đơn tăng ca
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <div class="text-muted small fw-bold">Tổng đơn</div>
                <div class="fs-3 fw-bold">{{ number_format($summary['total'] ?? 0) }}</div>
            </div></div>
        </div>

        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <div class="text-muted small fw-bold">Chờ duyệt</div>
                <div class="fs-3 fw-bold">{{ number_format($summary['pending'] ?? 0) }}</div>
            </div></div>
        </div>

        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <div class="text-muted small fw-bold">Đã duyệt</div>
                <div class="fs-3 fw-bold">{{ number_format($summary['approved'] ?? 0) }}</div>
            </div></div>
        </div>

        <div class="col-6 col-xl">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <div class="text-muted small fw-bold">Từ chối</div>
                <div class="fs-3 fw-bold">{{ number_format($summary['rejected'] ?? 0) }}</div>
            </div></div>
        </div>

        <div class="col-12 col-xl">
            <div class="card border-0 shadow-sm rounded-4"><div class="card-body">
                <div class="text-muted small fw-bold">Giờ tăng ca duyệt</div>
                <div class="fs-3 fw-bold">{{ $fmtHour($summary['hours'] ?? 0) }} giờ</div>
            </div></div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Tháng</label>
                    <input type="month" name="month" value="{{ $month }}" class="form-control rounded-4">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select name="status" class="form-select rounded-4">
                        <option value="">-- Tất cả --</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Từ chối</option>
                    </select>
                </div>

                @if($canManage)
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Nhân viên</label>
                        <select name="user_id" class="form-select rounded-4">
                            <option value="">-- Tất cả --</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" {{ (int)$userId === (int)$employee->id ? 'selected' : '' }}>
                                    {{ $employee->name }} @if(optional($employee->department)->name) - {{ optional($employee->department)->name }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-dark rounded-4 px-4 w-100" type="submit">Lọc</button>
                    <a href="{{ route('hr.overtime.index') }}" class="btn btn-light rounded-4 px-3">Xóa</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nhân viên</th>
                        <th>Ngày</th>
                        <th>Thời gian</th>
                        <th>Số giờ</th>
                        <th>Người duyệt</th>
                        <th>Lý do</th>
                        <th>Trạng thái</th>
                        <th style="width:240px">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $item)
                        @php
                            $currentUser = auth()->user();
                            $canApproveThis = $canManage || (int)$item->approver_id === (int)$currentUser->id;
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $item->user->name ?? '-' }}</div>
                                <div class="text-muted small">{{ optional(optional($item->user)->department)->name ?? '-' }}</div>
                            </td>
                            <td class="fw-bold">{{ optional($item->overtime_date)->format('d/m/Y') }}</td>
                            <td>{{ optional($item->start_at)->format('H:i') }} - {{ optional($item->end_at)->format('H:i') }}</td>
                            <td class="fw-bold">{{ $fmtHour($item->hours) }} giờ</td>
                            <td>{{ $item->approver->name ?? 'HR / Admin' }}</td>
                            <td style="min-width:260px">{{ $item->reason ?: '-' }}</td>
                            <td>
                                <span class="badge bg-{{ $item->status_badge_class }}">{{ $item->status_label }}</span>
                                @if($item->approval_note)
                                    <div class="small text-muted mt-1">{{ $item->approval_note }}</div>
                                @endif
                            </td>
                            <td>
                                @if($item->status === 'pending' && $canApproveThis)
                                    <form method="POST" action="{{ route('hr.overtime.approve', $item) }}" class="mb-2">
                                        @csrf
                                        <input type="text" name="approval_note" class="form-control form-control-sm rounded-3 mb-1" placeholder="Ghi chú duyệt nếu có">
                                        <button class="btn btn-success btn-sm rounded-3 w-100" type="submit">Duyệt</button>
                                    </form>

                                    <form method="POST" action="{{ route('hr.overtime.reject', $item) }}">
                                        @csrf
                                        <input type="text" name="approval_note" class="form-control form-control-sm rounded-3 mb-1" placeholder="Lý do từ chối">
                                        <button class="btn btn-danger btn-sm rounded-3 w-100" type="submit">Từ chối</button>
                                    </form>
                                @else
                                    <span class="text-muted small">Không có thao tác</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">Chưa có đơn tăng ca nào.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-3 border-top">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection
