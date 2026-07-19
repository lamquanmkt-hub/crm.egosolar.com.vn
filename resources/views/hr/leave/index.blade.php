@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h3 class="mb-1 fw-bold">Đơn nghỉ phép / làm online</h3>
            <div class="text-muted">Theo dõi danh sách đơn và duyệt đơn nhân sự</div>
        </div>
        <a href="{{ route('hr.leave.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-plus-circle me-1"></i> Tạo đơn mới
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Trạng thái</label>
                    <select name="status" class="form-select rounded-4">
                        <option value="">Tất cả</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Từ chối</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Loại đơn</label>
                    <select name="request_type" class="form-select rounded-4">
                        <option value="">Tất cả</option>
                        <option value="leave" {{ request('request_type') === 'leave' ? 'selected' : '' }}>Nghỉ phép</option>
                        <option value="wfh" {{ request('request_type') === 'wfh' ? 'selected' : '' }}>Làm online</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-dark w-100 rounded-4">Lọc</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Loại đơn</th>
                            <th>Loại nghỉ</th>
                            <th>Từ ngày</th>
                            <th>Đến ngày</th>
                            <th>Số ngày</th>
                            <th>Người duyệt</th>
                            <th>Trạng thái</th>
                            <th>Lý do</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->user->name ?? '-' }}</div>
                                    <div class="small text-muted">{{ optional($item->user->department)->name ?? '-' }}</div>
                                </td>
                                <td>{{ $item->request_type_label }}</td>
                                <td>{{ $item->leave_type_label }}</td>
                                <td>{{ $item->start_date?->format('d/m/Y') }}</td>
                                <td>{{ $item->end_date?->format('d/m/Y') }}</td>
                                <td>{{ rtrim(rtrim(number_format((float)$item->days, 2, '.', ''), '0'), '.') }}</td>
                                <td>{{ $item->approver->name ?? '-' }}</td>
                                <td>
                                    <span class="badge bg-{{ $item->status_badge_class }}">
                                        {{ $item->status_label }}
                                    </span>
                                </td>
                                <td style="min-width: 220px;">
                                    <div>{{ $item->reason ?: '-' }}</div>
                                    @if($item->approval_note)
                                        <div class="small text-muted mt-1">
                                            Ghi chú duyệt: {{ $item->approval_note }}
                                        </div>
                                    @endif
                                </td>
                                <td style="min-width: 220px;">
                                    @if($item->status === 'pending' && (auth()->user()->hasRole('admin') || auth()->user()->hasRole('sales_manager') || auth()->user()->hasRole('marketing_manager') || auth()->id() == $item->approver_id))
                                        <div class="d-flex flex-column gap-2">
                                            <form method="POST" action="{{ route('hr.leave.approve', $item) }}">
                                                @csrf
                                                <input type="text" name="approval_note" class="form-control form-control-sm rounded-3 mb-2" placeholder="Ghi chú duyệt (nếu có)">
                                                <button class="btn btn-success btn-sm w-100 rounded-3">
                                                    <i class="bi bi-check-circle me-1"></i> Duyệt
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('hr.leave.reject', $item) }}">
                                                @csrf
                                                <input type="text" name="approval_note" class="form-control form-control-sm rounded-3 mb-2" placeholder="Lý do từ chối (nếu có)">
                                                <button class="btn btn-danger btn-sm w-100 rounded-3">
                                                    <i class="bi bi-x-circle me-1"></i> Từ chối
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-muted small">Không có thao tác</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">Chưa có đơn nào</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</div>
@endsection