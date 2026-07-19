@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
        <div class="card-body p-0">
            <div class="p-4 text-white" style="background: linear-gradient(135deg, #0f172a, #1d4ed8);">
                <h3 class="mb-1 fw-bold">{{ isset($account) ? 'Cập nhật tài khoản' : 'Tạo quỹ / tài khoản' }}</h3>
                <div class="opacity-75">Thiết lập quỹ tiền mặt, ngân hàng hoặc ví điện tử để quản lý số dư thật</div>
            </div>

            <div class="p-4">
                <form action="{{ isset($account) ? route('finance.accounts.update', $account) : route('finance.accounts.store') }}" method="POST">
                    @csrf
                    @if(isset($account))
                        @method('PUT')
                    @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Tên tài khoản</label>
                            <input type="text" name="name" class="form-control rounded-pill" value="{{ old('name', $account->name ?? '') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Mã tài khoản</label>
                            <input type="text" name="code" class="form-control rounded-pill" value="{{ old('code', $account->code ?? '') }}">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Loại</label>
                            <select name="type" class="form-select rounded-pill" required>
                                <option value="cash" @selected(old('type', $account->type ?? '') == 'cash')>Tiền mặt</option>
                                <option value="bank" @selected(old('type', $account->type ?? '') == 'bank')>Ngân hàng</option>
                                <option value="ewallet" @selected(old('type', $account->type ?? '') == 'ewallet')>Ví điện tử</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Số dư ban đầu</label>
                            <input type="number" step="0.01" min="0" name="opening_balance" class="form-control rounded-pill"
                                   value="{{ old('opening_balance', $account->opening_balance ?? 0) }}"
                                   {{ isset($account) ? 'disabled' : '' }}>
                            @if(isset($account))
                                <small class="text-muted">Số dư đầu chỉ thiết lập khi tạo mới.</small>
                            @endif
                        </div>

                        <div class="col-12">
                            <label class="form-label">Ghi chú</label>
                            <textarea name="note" rows="4" class="form-control rounded-4">{{ old('note', $account->note ?? '') }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                                       @checked(old('is_active', $account->is_active ?? true))>
                                <label class="form-check-label" for="is_active">
                                    Kích hoạt tài khoản
                                </label>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2">
                            <button class="btn btn-primary rounded-pill px-4">
                                {{ isset($account) ? 'Cập nhật' : 'Tạo tài khoản' }}
                            </button>
                            <a href="{{ route('finance.accounts.index') }}" class="btn btn-light rounded-pill px-4">
                                Quay lại
                            </a>
                        </div>
                    </div>
                </form>

                @if($errors->any())
                    <div class="alert alert-danger mt-4 rounded-4">
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection