@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold">Lịch sử thanh toán</h2>
            <div class="text-muted">Theo dõi trạng thái ghi nhận thanh toán của đơn hàng</div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('finance.customer-debts.index') }}" class="btn btn-outline-secondary rounded-pill">
                Danh sách công nợ
            </a>
            <a href="{{ route('finance.customer-debts.by-customer') }}" class="btn btn-outline-primary rounded-pill">
                Theo khách hàng
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th class="text-end">Tổng tiền</th>
                            <th class="text-end">Đã thanh toán</th>
                            <th class="text-end">Còn nợ</th>
                            <th>Trạng thái</th>
                            <th>Ngày đơn</th>
                            <th>Cập nhật</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>{{ $orders->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $order->order_code ?? ('#' . $order->id) }}</td>
                                <td>{{ $order->finance_customer_name }}</td>
                                <td class="text-end fw-semibold">{{ number_format($order->finance_total_amount, 0, ',', '.') }} đ</td>
                                <td class="text-end text-success fw-semibold">{{ number_format($order->finance_paid_amount, 0, ',', '.') }} đ</td>
                                <td class="text-end text-danger fw-semibold">{{ number_format($order->finance_debt_amount, 0, ',', '.') }} đ</td>
                                <td>
                                    @if(($order->payment_recorded ?? 0) == 1)
                                        <span class="badge bg-success-subtle text-success rounded-pill px-3 py-2">Đã ghi nhận</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2">Chưa ghi nhận</span>
                                    @endif
                                </td>
                                <td>{{ $order->order_date ? \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') : optional($order->created_at)->format('d/m/Y') }}</td>
                                <td>{{ optional($order->updated_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>
@endsection