@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-pill px-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-pill px-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="card border-0 shadow-lg rounded-4 overflow-hidden mb-4">
        <div class="card-body p-4 text-white" style="background: linear-gradient(135deg, #0ea5e9, #2563eb);">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h3 class="mb-1 fw-bold">Quỹ & Tài Khoản</h3>
                    <div class="opacity-75">Quản lý tiền mặt, ngân hàng, ví điện tử và số dư thực tế</div>
                </div>
                <a href="{{ route('finance.accounts.create') }}" class="btn btn-light rounded-pill px-4 fw-semibold">
                    + Tạo tài khoản
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small">Tổng tài khoản</div>
                    <div class="fs-4 fw-bold">{{ number_format($stats['total_accounts']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small">Đang hoạt động</div>
                    <div class="fs-4 fw-bold text-success">{{ number_format($stats['active_accounts']) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small">Tổng số dư</div>
                    <div class="fs-4 fw-bold text-primary">{{ number_format($stats['total_balance'], 0, ',', '.') }}đ</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body">
                    <div class="text-muted small">Tiền mặt</div>
                    <div class="fs-4 fw-bold text-info">{{ number_format($stats['cash_balance'], 0, ',', '.') }}đ</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-5">
                    <input type="text" name="keyword" value="{{ request('keyword') }}" class="form-control rounded-pill" placeholder="Tìm theo tên, mã, ghi chú...">
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select rounded-pill">
                        <option value="">-- Loại tài khoản --</option>
                        <option value="cash" @selected(request('type') == 'cash')>Tiền mặt</option>
                        <option value="bank" @selected(request('type') == 'bank')>Ngân hàng</option>
                        <option value="ewallet" @selected(request('type') == 'ewallet')>Ví điện tử</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select rounded-pill">
                        <option value="">-- Trạng thái --</option>
                        <option value="active" @selected(request('status') == 'active')>Hoạt động</option>
                        <option value="inactive" @selected(request('status') == 'inactive')>Ngưng</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary rounded-pill">Lọc</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-0">
            @if($accounts->count())
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">Tên tài khoản</th>
                                <th class="py-3">Mã</th>
                                <th class="py-3">Loại</th>
                                <th class="py-3">Số dư đầu</th>
                                <th class="py-3">Số dư hiện tại</th>
                                <th class="py-3">Trạng thái</th>
                                <th class="text-end px-4 py-3">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accounts as $account)
                                <tr>
                                    <td class="px-4">
                                        <div class="fw-semibold">{{ $account->name }}</div>
                                        @if($account->note)
                                            <div class="text-muted small">{{ $account->note }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $account->code ?: '—' }}</td>
                                    <td>{{ $account->type_label }}</td>
                                    <td>{{ number_format($account->opening_balance, 0, ',', '.') }}đ</td>
                                    <td class="fw-bold text-primary">{{ number_format($account->current_balance, 0, ',', '.') }}đ</td>
                                    <td>
                                        @if($account->is_active)
                                            <span class="badge bg-success-subtle text-success rounded-pill px-3">Hoạt động</span>
                                        @else
                                            <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3">Ngưng</span>
                                        @endif
                                    </td>
                                    <td class="text-end px-4">
                                        

                                        <form action="{{ route('finance.accounts.destroy', $account) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Xóa tài khoản này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger rounded-pill px-3">Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="p-3">
                    {{ $accounts->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <div class="mb-2 fw-semibold">Chưa có quỹ / tài khoản nào</div>
                    <div class="text-muted mb-3">Bắt đầu bằng cách tạo quỹ tiền mặt hoặc tài khoản ngân hàng đầu tiên.</div>
                    <a href="{{ route('finance.accounts.create') }}" class="btn btn-primary rounded-pill px-4">
                        + Tạo ngay
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection