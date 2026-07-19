@extends('layouts.app')

@section('title', 'Tạo phiếu thu')

@section('content')
<div class="container py-4" style="max-width: 900px">
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-4">

            <h4 class="fw-bold mb-3">💰 Tạo phiếu thu</h4>
            <div class="text-muted mb-4">
                Nhập thông tin để tạo phiếu thu mới
            </div>

            @if ($errors->any())
                <div class="alert alert-danger rounded-3">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('finance.receipts.store') }}">
                @csrf

                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Ngày thu</label>
                        <input type="date" name="receipt_date" class="form-control rounded-pill"
                               value="{{ old('receipt_date', now()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Người nộp</label>
                        <input type="text" name="payer_name" class="form-control rounded-pill"
                               placeholder="VD: Nguyễn Văn A" value="{{ old('payer_name') }}" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">SĐT</label>
                        <input type="text" name="payer_phone" class="form-control rounded-pill"
                               value="{{ old('payer_phone') }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Loại thu</label>
                        <select name="category" class="form-select rounded-pill">
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Phương thức</label>
                        <select name="payment_method" class="form-select rounded-pill">
                            @foreach($paymentMethods as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Số tiền</label>
                        <input type="number" name="amount" class="form-control rounded-pill"
                               placeholder="VD: 5000000" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Ghi chú</label>
                        <input type="text" name="note" class="form-control rounded-pill">
                    </div>

                    <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                        <a href="{{ route('finance.receipts.index') }}" class="btn btn-light rounded-pill px-4">
                            Quay lại
                        </a>
                        <button class="btn btn-primary rounded-pill px-4">
                            Lưu phiếu
                        </button>
                    </div>

                </div>
            </form>

        </div>
    </div>
</div>
@endsection