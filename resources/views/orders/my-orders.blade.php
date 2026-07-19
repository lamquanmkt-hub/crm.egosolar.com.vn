
@php
    $egoPendingOrdersCount = $egoPendingOrdersCount ?? 0;
    $egoPendingMaterialRequestsCount = $egoPendingMaterialRequestsCount ?? 0;

    try {
        $egoPendingOrdersCount = (int) \Illuminate\Support\Facades\DB::table('crm_order_approvals')
            ->where('status', 'pending')
            ->distinct()
            ->count('order_id');

        $egoPendingMaterialRequestsCount = (int) \Illuminate\Support\Facades\DB::table('material_requests')
            ->whereIn('status', ['SUBMITTED', 'ADMIN_APPROVED'])
            ->count();
    } catch (\Throwable $e) {
        $egoPendingOrdersCount = 0;
        $egoPendingMaterialRequestsCount = 0;
    }
@endphp

@extends('layouts.app')
@section('title', 'Dashboard đơn hàng của tôi')
@section('content')
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="fw-bold text-uppercase text-secondary">DASHBOARD ĐƠN HÀNG CỦA TÔI</h1>
            <a href="{{ route('orders.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Tạo đơn mới
            </a>
        </div>
        {{-- Thống kê tổng quan --}}
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-4 border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted small mb-1">Tổng đơn hàng</p>
                                <h3 class="mb-0 fw-bold">{{ $statistics['total_orders'] }}</h3>
                            </div>
                            <div class="text-primary fs-1">
                                <i class="bi bi-cart-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-4 border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted small mb-1">Đang xử lý</p>
                                <h3 class="mb-0 fw-bold text-warning">{{ $statistics['pending'] }}</h3>
                            </div>
                            <div class="text-warning fs-1">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-4 border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted small mb-1">Hoàn thành</p>
                                <h3 class="mb-0 fw-bold text-success">{{ $statistics['completed'] }}</h3>
                            </div>
                            <div class="text-success fs-1">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-start border-4 border-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="text-muted small mb-1">Doanh thu</p>
                                <h3 class="mb-0 fw-bold text-info">
                                    {{ number_format($statistics['total_revenue'], 0, ',', '.') }}đ
                                </h3>
                            </div>
                            <div class="text-info fs-1">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            {{-- Đơn hàng gần đây --}}
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white d-flex justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Đơn hàng gần đây</h5>
                        <a href="{{ route('orders.index') }}" class="text-white text-decoration-none">
                            Xem tất cả <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Bộ phận</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($recentOrders as $order)
                                    <tr>
                                        <td>
                                            <a href="{{ route('orders.show', $order->id) }}" class="text-decoration-none fw-semibold">
                                                {{ $order->order_code }}
                                            </a>
                                        </td>
                                        <td>
                                            <div>{{ $order->lead->customer->name }}</div>
                                            <small class="text-muted">{{ $order->lead->customer->phone }}</small>
                                        </td>
                                        <td class="fw-bold">{{ number_format($order->total_amount, 0, ',', '.') }}đ</td>
                                        <td>
                                        <span class="badge" style="background-color: {{ $order->currentStatusType->color ?? '#6c757d' }}">
                                            {{ $order->currentStatusType->name ?? 'N/A' }}
                                        </span>
                                        </td>
                                        <td>
                                            @php
                                                $deptBadges = [
                                                    'sales' => 'secondary',
                                                    'ketoan' => 'info',
                                                    'duyet1' => 'warning',
                                                    'duyet2' => 'primary',
                                                    'kho' => 'dark',
                                                    'completed' => 'success'
                                                ];
                                                $badgeClass = $deptBadges[$order->current_department] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $badgeClass }}">
                                            {{ ucfirst($order->current_department) }}
                                        </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('orders.show', $order->id) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">
                                            Chưa có đơn hàng nào
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            {{-- Thông báo --}}
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning d-flex justify-content-between">
                        <h5 class="mb-0"><i class="bi bi-bell"></i> Thông báo mới</h5>
                        @if($pendingNotifications->count() > 0)
                            <span class="badge bg-danger">{{ $pendingNotifications->count() }}</span>
                        @endif
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        @forelse($pendingNotifications as $notif)
                            <div class="alert alert-{{ $notif->is_read ? 'secondary' : 'info' }} alert-dismissible fade show mb-2">
                                <small class="text-muted d-block mb-1">
                                    <i class="bi bi-clock"></i> {{ $notif->created_at->diffForHumans() }}
                                </small>
                                <h6 class="alert-heading mb-1">{{ $notif->title }}</h6>
                                <p class="mb-2 small">{{ $notif->message }}</p>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('orders.show', $notif->order_id) }}" class="btn btn-sm btn-primary">
                                        Xem đơn
                                    </a>
                                    @if(!$notif->is_read)
                                        <form action="{{ route('notifications.mark-read', $notif->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                Đánh dấu đã đọc
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-bell-slash fs-1"></i>
                                <p class="mt-2">Không có thông báo mới</p>
                            </div>
                        @endforelse
                    </div>
                    @if($pendingNotifications->count() > 0)
                        <div class="card-footer text-center">
                            <form action="{{ route('notifications.mark-all-read') }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-link">
                                    Đánh dấu tất cả đã đọc
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        {{-- Biểu đồ tiến độ --}}
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Tiến độ xử lý đơn hàng</h5>
                    </div>
                    <div class="card-body">
                        <div class="progress" style="height: 30px;">
                            @php
                                $total = $statistics['total_orders'] ?: 1;
                                $completedPercent = round(($statistics['completed'] / $total) * 100);
                                $pendingPercent = round(($statistics['pending'] / $total) * 100);
                            @endphp
                            <div class="progress-bar bg-success" role="progressbar"
                                 style="width: {{ $completedPercent }}%"
                                 aria-valuenow="{{ $completedPercent }}" aria-valuemin="0" aria-valuemax="100">
                                Hoàn thành: {{ $completedPercent }}%
                            </div>
                            <div class="progress-bar bg-warning" role="progressbar"
                                 style="width: {{ $pendingPercent }}%"
                                 aria-valuenow="{{ $pendingPercent }}" aria-valuemin="0" aria-valuemax="100">
                                Đang xử lý: {{ $pendingPercent }}%
                            </div>
                        </div>
                        <div class="mt-3 text-center">
                            <p class="mb-0 text-muted">
                                Tỷ lệ hoàn thành: <strong class="text-success">{{ $completedPercent }}%</strong> |
                                Đang xử lý: <strong class="text-warning">{{ $pendingPercent }}%</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

