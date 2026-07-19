@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Tạo yêu cầu Đổi/Trả - Đơn #{{ $order->code ?? $order->id }}</h4>
        <a href="{{ route('orders.show', $order->id) }}" class="btn btn-light">Quay lại</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <div><b>Có lỗi:</b></div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('orders.returns.store', $order->id) }}">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Loại yêu cầu</label>
                    <select name="type" class="form-select" required>
                        <option value="exchange" @selected(old('type')=='exchange')>Đổi hàng</option>
                        <option value="return" @selected(old('type')=='return')>Trả hàng</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Lý do</label>
                    <textarea name="reason" class="form-control" rows="5" required>{{ old('reason') }}</textarea>
                    <div class="form-text">Mô tả tình trạng hàng, lỗi, thiếu phụ kiện, v.v...</div>
                </div>

                <button class="btn btn-warning w-100" type="submit">
                    Gửi yêu cầu Đổi/Trả
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
