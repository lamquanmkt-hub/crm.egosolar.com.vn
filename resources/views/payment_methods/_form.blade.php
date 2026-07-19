<div class="row">
    {{-- Tên phương thức --}}
    <div class="col-md-6 mb-3">
        <label for="method_name" class="form-label fw-bold">Tên phương thức <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control @error('method_name') is-invalid @enderror"
               id="method_name"
               name="method_name"
               value="{{ old('method_name', $method->method_name ?? '') }}"
               placeholder="VD: Chuyển khoản ngân hàng">
        @error('method_name')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Mã code --}}
    <div class="col-md-6 mb-3">
        <label for="code" class="form-label fw-bold">Mã (Code) <span class="text-danger">*</span></label>
        <input type="text"
               class="form-control text-uppercase @error('code') is-invalid @enderror"
               id="code"
               name="code"
               value="{{ old('code', $method->code ?? '') }}"
               placeholder="VD: BANKING">
        <div class="form-text">Mã nên viết liền không dấu (VD: COD, CASH).</div>
        @error('code')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Mô tả --}}
    <div class="col-12 mb-3">
        <label for="description" class="form-label fw-bold">Mô tả chi tiết</label>
        <textarea class="form-control @error('description') is-invalid @enderror"
                  id="description"
                  name="description"
                  rows="3">{{ old('description', $method->description ?? '') }}</textarea>
        @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    {{-- Trạng thái hoạt động --}}
    <div class="col-12 mb-3">
        <div class="form-check form-switch">
            {{-- Trick: input hidden để gửi value 0 khi checkbox không được check --}}
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input"
                   type="checkbox"
                   id="is_active"
                   name="is_active"
                   value="1"
                    {{ old('is_active', $method->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Kích hoạt sử dụng</label>
        </div>
    </div>
</div>