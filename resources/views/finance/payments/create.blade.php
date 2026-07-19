@extends('layouts.app')

@section('content')
<div class="container py-4">
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

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4">
            <div class="fw-semibold mb-2">Vui lòng kiểm tra lại dữ liệu:</div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="p-4 text-white" style="background: linear-gradient(135deg, #dc2626, #b91c1c);">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h3 class="mb-1 fw-bold">Tạo phiếu chi</h3>
                        <div class="opacity-75">Ghi nhận khoản chi và tự động trừ số dư quỹ / tài khoản</div>
                    </div>
                    <a href="{{ route('finance.payments.index') }}" class="btn btn-light rounded-pill px-4 fw-semibold">
                        Quay lại danh sách
                    </a>
                </div>
            </div>

            <div class="p-4">
                <form action="{{ route('finance.payments.store') }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Quỹ / Tài khoản</label>
                            <select name="account_id" class="form-select rounded-pill" required>
                                <option value="">-- Chọn tài khoản --</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>
                                        {{ $account->name }} - {{ number_format($account->current_balance, 0, ',', '.') }}đ
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Ngày chi</label>
                            <input type="date" name="payment_date" class="form-control rounded-pill"
                                   value="{{ old('payment_date', now()->format('Y-m-d')) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Người nhận</label>
                            <input type="text" name="payee_name" class="form-control rounded-pill"
                                   value="{{ old('payee_name') }}" placeholder="Nhập tên người nhận">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Số điện thoại</label>
                            <input type="text" name="payee_phone" class="form-control rounded-pill"
                                   value="{{ old('payee_phone') }}" placeholder="Nhập số điện thoại">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Loại chi</label>
                            <select name="category" class="form-select rounded-pill" required>
                                <option value="">-- Chọn loại chi --</option>
                                @foreach($categories as $value => $label)
                                    <option value="{{ $value }}" @selected(old('category') == $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phương thức</label>
                            <select name="payment_method" class="form-select rounded-pill" required>
                                <option value="">-- Chọn phương thức --</option>
                                @foreach($paymentMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method') == $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Số tiền</label>
                            <input type="number" step="0.01" min="0.01" name="amount"
                                   class="form-control rounded-pill"
                                   value="{{ old('amount') }}"
                                   placeholder="Nhập số tiền" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Ghi chú</label>
                            <textarea name="note" rows="4" class="form-control rounded-4" placeholder="Nội dung chi...">{{ old('note') }}</textarea>
                        </div>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-danger rounded-pill px-4 fw-semibold">
                                Lưu phiếu chi
                            </button>
                            <a href="{{ route('finance.payments.index') }}" class="btn btn-light rounded-pill px-4">
                                Hủy
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection