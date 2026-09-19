@if($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">Có lỗi xảy ra:</div>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}">
    @csrf

    @if($method !== 'POST')
        @method($method)
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">
            Thông tin pháp lý
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Tên công ty <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="{{ old('name', $company->name) }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mã công ty <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control"
                           value="{{ old('code', $company->code) }}" placeholder="VD: EGO_INT" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Mã số thuế</label>
                    <input type="text" name="tax_code" class="form-control"
                           value="{{ old('tax_code', $company->tax_code) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="{{ old('email', $company->email) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Số điện thoại</label>
                    <input type="text" name="phone" class="form-control"
                           value="{{ old('phone', $company->phone) }}">
                </div>

                <div class="col-12">
                    <label class="form-label">Địa chỉ</label>
                    <textarea name="address" class="form-control" rows="3">{{ old('address', $company->address) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mt-3">
        <div class="card-header bg-white fw-semibold">
            Thông tin thanh toán in trên PDF
        </div>

        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Số tài khoản</label>
                    <input type="text" name="bank_account" class="form-control"
                           value="{{ old('bank_account', $company->bank_account) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Ngân hàng</label>
                    <input type="text" name="bank_name" class="form-control"
                           value="{{ old('bank_name', $company->bank_name) }}">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tên tài khoản</label>
                    <input type="text" name="bank_holder" class="form-control"
                           value="{{ old('bank_holder', $company->bank_holder) }}">
                </div>

                <div class="col-12">
                    <label class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input"
                               {{ old('is_active', $company->exists ? $company->is_active : true) ? 'checked' : '' }}>
                        <span class="form-check-label">Đang hoạt động</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('company-management.index') }}" class="btn btn-light">Hủy</a>
            <button type="submit" class="btn btn-primary">{{ $buttonText }}</button>
        </div>
    </div>
</form>