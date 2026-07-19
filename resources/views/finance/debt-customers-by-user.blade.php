@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="mb-1 fw-bold">Công nợ theo khách hàng</h2>
            <div class="text-muted">Tổng hợp doanh số, thanh toán và dư nợ theo từng khách hàng</div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('finance.customer-debts.index') }}" class="btn btn-outline-secondary rounded-pill">
                Danh sách công nợ
            </a>
            <a href="{{ route('finance.customer-debts.payment-history') }}" class="btn btn-outline-primary rounded-pill">
                Lịch sử thanh toán
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
                            <th>Khách hàng</th>
                            <th class="text-end">Số đơn</th>
                            <th class="text-end">Tổng tiền</th>
                            <th class="text-end">Đã thanh toán</th>
                            <th class="text-end">Còn nợ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($debts as $item)
                            <tr>
                                <td>{{ $debts->firstItem() + $loop->index }}</td>
                                <td class="fw-semibold">{{ $item->customer_name }}</td>
                                <td class="text-end">{{ number_format($item->total_orders) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($item->total_amount, 0, ',', '.') }} đ</td>
                                <td class="text-end text-success fw-semibold">{{ number_format($item->paid_amount, 0, ',', '.') }} đ</td>
                                <td class="text-end text-danger fw-semibold">{{ number_format($item->debt_amount, 0, ',', '.') }} đ</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $debts->links() }}
            </div>
        </div>
    </div>
</div>
@endsection