@php
    $isEdit = isset($customer);
    $action = $isEdit
        ? route('customers.update', $customer->id)
        : route('customers.store');
@endphp

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title">
        <i class="bi bi-person-{{ $isEdit ? 'gear' : 'plus' }}"></i>
        {{ $isEdit ? 'Cập nhật khách hàng' : 'Thêm mới khách hàng' }}
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
    <form method="POST" action="{{ $action }}" id="customerForm" class="ego-customer-form-pro">
        @csrf
        @if($isEdit) @method('PUT') @endif

        {{-- Thông báo lỗi --}}
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show">
                <strong>Có lỗi xảy ra:</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- SECTION 1: Thông tin cơ bản --}}
        <div class="card mb-3 ego-customer-section ego-sec-basic">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-person-vcard"></i> Thông tin cơ bản</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Tên khách hàng <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               class="form-control @error('name') is-invalid @enderror"
                               value="{{ old('name', $customer->name ?? '') }}"
                               placeholder="VD: Nguyễn Văn A"
                               required
                               autofocus>
                        @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Số điện thoại <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               name="phone"
                               id="phone"
                               class="form-control @error('phone') is-invalid @enderror"
                               value="{{ old('phone', $customer->phone ?? '') }}"
                               placeholder="0901234567"
                               inputmode="numeric"
                               autocomplete="tel"
                               maxlength="10"
                               required>

                        <div class="invalid-feedback">
                            Số điện thoại phải đủ 10 số và bắt đầu bằng 03/05/07/08/09.
                        </div>

                        @error('phone')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Biệt danh</label>
                        <input type="text"
                               name="nickname"
                               class="form-control @error('nickname') is-invalid @enderror"
                               value="{{ old('nickname', $customer->nickname ?? '') }}"
                               placeholder="VD: Anh A Bình Dương">
                        <small class="text-muted">Tên gọi dễ nhớ để phân biệt khách</small>
                        @error('nickname')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email"
                               name="email"
                               class="form-control @error('email') is-invalid @enderror"
                               value="{{ old('email', $customer->email ?? '') }}"
                               placeholder="example@email.com">
                        @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 2: Thông tin mạng xã hội --}}
        <div class="card mb-3 ego-customer-section ego-sec-social">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-share"></i> Thông tin mạng xã hội</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên Facebook</label>
                        <input type="text"
                               name="facebook_name"
                               class="form-control"
                               value="{{ old('facebook_name', $customer->facebook_name ?? '') }}"
                               placeholder="Tên hiển thị trên Facebook">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Link Facebook</label>
                        <input type="url"
                               name="facebook_link"
                               class="form-control"
                               value="{{ old('facebook_link', $customer->facebook_link ?? '') }}"
                               placeholder="https://facebook.com/...">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Zalo ID</label>
                        <input type="text"
                               name="zalo_id"
                               class="form-control"
                               value="{{ old('zalo_id', $customer->zalo_id ?? '') }}"
                               placeholder="Số điện thoại hoặc ID Zalo">
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 3: Thông tin hoá đơn --}}
        <div class="card mb-3 ego-customer-section ego-sec-billing">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-receipt"></i> Thông tin hoá đơn</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Tên công ty / Cá nhân</label>
                        <input type="text"
                               name="billing_company_name"
                               class="form-control @error('billing_company_name') is-invalid @enderror"
                               value="{{ old('billing_company_name', $customer->billing_company_name ?? '') }}"
                               placeholder="Ví dụ: CÔNG TY ABC">
                        @error('billing_company_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Mã số thuế</label>
                        <input type="text"
                               name="billing_tax_code"
                               class="form-control @error('billing_tax_code') is-invalid @enderror"
                               value="{{ old('billing_tax_code', $customer->billing_tax_code ?? '') }}"
                               placeholder="Nhập mã số thuế">
                        @error('billing_tax_code')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email nhận hoá đơn</label>
                        <input type="email"
                               name="billing_email"
                               class="form-control @error('billing_email') is-invalid @enderror"
                               value="{{ old('billing_email', $customer->billing_email ?? '') }}"
                               placeholder="example@email.com">
                        @error('billing_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Địa chỉ xuất hoá đơn</label>
                        <input type="text"
                               name="billing_address"
                               class="form-control @error('billing_address') is-invalid @enderror"
                               value="{{ old('billing_address', $customer->billing_address ?? '') }}"
                               placeholder="Nhập địa chỉ xuất hoá đơn">
                        @error('billing_address')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 4: Phân loại khách hàng --}}
        <div class="card mb-3 ego-customer-section ego-sec-type">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-tags"></i> Phân loại khách hàng</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">
                            Loại khách hàng <span class="text-danger">*</span>
                        </label>
                        <select name="customer_type_id"
                                class="form-select @error('customer_type_id') is-invalid @enderror"
                                required>
                            <option value="">-- Chọn loại khách --</option>
                            @foreach($customerTypes as $ct)
                                <option value="{{ $ct->id }}"
                                    {{ old('customer_type_id', $customer->customer_type_id ?? '') == $ct->id ? 'selected' : '' }}>
                                    {{ $ct->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Cá nhân / Đại lý / Dự án...</small>
                        @error('customer_type_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Trạng thái khách hàng</label>
                        <select name="customer_status" class="form-select">
                            <option value="lead" {{ old('customer_status', $customer->customer_status ?? 'lead') == 'lead' ? 'selected' : '' }}>
                                Lead (Khách ADS)
                            </option>
                            <option value="member" {{ old('customer_status', $customer->customer_status ?? '') == 'member' ? 'selected' : '' }}>
                                Member (Khách tự phát triển)
                            </option>
                            <option value="retail" {{ old('customer_status', $customer->customer_status ?? '') == 'retail' ? 'selected' : '' }}>
                                Khách lẻ
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Khu vực</label>
                        <select name="region_id" class="form-select">
                            <option value="">-- Chọn khu vực --</option>
                            @foreach($regions as $r)
                                <option value="{{ $r->id }}"
                                    {{ old('region_id', $customer->region_id ?? '') == $r->id ? 'selected' : '' }}>
                                    {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Người phụ trách (Chi chủ)</label>
                        <select name="owner_id" class="form-select">
                            <option value="">-- Chọn người phụ trách --</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}"
                                    {{ old('owner_id', $customer->owner_id ?? '') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="is_potential"
                                   value="1"
                                   id="isPotentialCheck"
                                {{ old('is_potential', $customer->is_potential ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isPotentialCheck">
                                <strong>⭐ Đánh dấu là khách hàng tiềm năng</strong>
                            </label>
                        </div>
                        <small class="text-muted">Khách có khả năng mua cao, cần ưu tiên chăm sóc</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- SECTION 5: Thông tin bổ sung --}}
        <div class="card mb-3 ego-customer-section ego-sec-extra">
            <div class="card-header bg-light">
                <h6 class="mb-0"><i class="bi bi-info-circle"></i> Thông tin bổ sung</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Địa chỉ</label>
                        <textarea name="address"
                                  class="form-control"
                                  rows="2"
                                  placeholder="Số nhà, đường, phường/xã, quận/huyện, tỉnh/thành phố">{{ old('address', $customer->address ?? '') }}</textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Ghi chú nhóm</label>
                        <input type="text"
                               name="group_note"
                               class="form-control"
                               value="{{ old('group_note', $customer->group_note ?? '') }}"
                               placeholder="VD: Nhóm khách VIP khu vực Bình Dương">
                        <small class="text-muted">Phân loại nhóm để dễ quản lý</small>
                    </div>
                </div>
            </div>
        </div>

        @if($isEdit)
            {{-- SECTION 6: Thông tin Lead liên quan (chỉ hiển thị khi edit) --}}
            <div class="card mb-3 border-info ego-customer-section ego-sec-lead">
                <div class="card-header bg-info bg-opacity-10">
                    <h6 class="mb-0 text-info">
                        <i class="bi bi-link-45deg"></i> Thông tin Lead liên quan
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        <strong>Lưu ý:</strong> Thông tin về nguồn, ngày liên hệ, trạng thái và ghi chú được quản lý tại bảng Lead.
                        <br>
                        <a href="#" class="btn btn-sm btn-info mt-2">
                            <i class="bi bi-list"></i> Xem danh sách Lead của khách này
                        </a>
                    </div>

                    @if(isset($customer->latestLead))
                        <div class="mt-3">
                            <h6 class="fw-bold">Lead gần nhất:</h6>
                            <ul class="list-unstyled mb-0">
                                <li><strong>Nguồn:</strong> {{ $customer->latestLead->source->name ?? 'N/A' }}</li>
                                <li><strong>Ngày liên hệ:</strong> {{ $customer->latestLead->contact_date ?? '-' }}</li>
                                <li><strong>Trạng thái:</strong> {{ $customer->latestLead->status->name ?? 'N/A' }}</li>
                                @if($customer->latestLead->note)
                                    <li><strong>Ghi chú:</strong> {{ $customer->latestLead->note }}</li>
                                @endif
                            </ul>
                        </div>
                    @else
                        <p class="text-muted mb-0 mt-2">Chưa có Lead nào được tạo cho khách hàng này.</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle"></i> Hủy
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-{{ $isEdit ? 'check' : 'plus' }}-circle"></i>
                {{ $isEdit ? 'Cập nhật' : 'Thêm mới' }}
            </button>
        </div>
    </form>
</div>



<!-- EGO_CUSTOMER_HORIZONTAL_PRO_START -->
<style id="ego-customer-horizontal-pro">
#customerModal .modal-dialog{
    max-width:min(1240px, 96vw) !important;
}

#customerModal .modal-content{
    border:0 !important;
    border-radius:26px !important;
    overflow:hidden !important;
    background:#f8fafc !important;
    box-shadow:0 34px 90px rgba(15,23,42,.32) !important;
}

#customerModal .modal-header{
    border:0 !important;
    padding:18px 24px !important;
    background:
        radial-gradient(circle at 92% 20%, rgba(255,255,255,.28), transparent 26%),
        linear-gradient(135deg,#0891b2 0%, #2563eb 48%, #1d4ed8 100%) !important;
}

#customerModal .modal-title{
    display:flex !important;
    align-items:center !important;
    gap:10px !important;
    color:#fff !important;
    font-size:22px !important;
    font-weight:900 !important;
    letter-spacing:-.02em !important;
}

#customerModal .btn-close{
    filter:brightness(0) invert(1) !important;
    opacity:.95 !important;
}

#customerModal .modal-body{
    padding:18px !important;
    background:
        radial-gradient(circle at top left, rgba(14,165,233,.10), transparent 32%),
        linear-gradient(180deg,#f8fafc 0%, #eef6fb 100%) !important;
    max-height:calc(100vh - 125px) !important;
    overflow-y:auto !important;
}

#customerForm.ego-customer-form-pro{
    display:grid !important;
    grid-template-columns:repeat(12, minmax(0,1fr)) !important;
    gap:14px !important;
    align-items:start !important;
}

#customerForm.ego-customer-form-pro > .alert{
    grid-column:1 / -1 !important;
}

#customerForm .ego-customer-section{
    margin:0 !important;
    border:1px solid rgba(148,163,184,.20) !important;
    border-radius:22px !important;
    overflow:hidden !important;
    background:rgba(255,255,255,.92) !important;
    box-shadow:0 16px 34px rgba(15,23,42,.07) !important;
}

#customerForm .ego-sec-basic,
#customerForm .ego-sec-social,
#customerForm .ego-sec-billing,
#customerForm .ego-sec-type{
    grid-column:span 6 !important;
}

#customerForm .ego-sec-extra,
#customerForm .ego-sec-lead{
    grid-column:1 / -1 !important;
}

#customerForm .card-header{
    border:0 !important;
    padding:14px 16px !important;
    background:
        linear-gradient(135deg, rgba(14,165,233,.12), rgba(37,99,235,.05)),
        #fff !important;
}

#customerForm .card-header h6{
    display:flex !important;
    align-items:center !important;
    gap:9px !important;
    margin:0 !important;
    color:#0f172a !important;
    font-size:15px !important;
    font-weight:900 !important;
}

#customerForm .card-header h6 i{
    width:30px !important;
    height:30px !important;
    border-radius:11px !important;
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    color:#0369a1 !important;
    background:rgba(14,165,233,.13) !important;
    border:1px solid rgba(14,165,233,.20) !important;
}

#customerForm .card-body{
    padding:16px !important;
}

#customerForm .row.g-3{
    --bs-gutter-x:14px !important;
    --bs-gutter-y:13px !important;
}

#customerForm .form-label{
    color:#334155 !important;
    font-size:13px !important;
    font-weight:800 !important;
    margin-bottom:7px !important;
}

#customerForm .form-control,
#customerForm .form-select{
    height:44px !important;
    min-height:44px !important;
    border-radius:15px !important;
    border:1px solid #d9e4ef !important;
    background:#fff !important;
    color:#0f172a !important;
    font-size:14px !important;
    padding:10px 14px !important;
    box-shadow:none !important;
}

#customerForm textarea.form-control{
    height:auto !important;
    min-height:86px !important;
}

#customerForm .form-control:focus,
#customerForm .form-select:focus{
    border-color:#38bdf8 !important;
    box-shadow:0 0 0 4px rgba(56,189,248,.14) !important;
}

#customerForm small,
#customerForm .text-muted,
#customerForm .form-text{
    color:#64748b !important;
    font-size:12px !important;
    font-weight:500 !important;
}

#customerForm .form-check{
    padding:14px 16px 14px 52px !important;
    border-radius:18px !important;
    background:linear-gradient(135deg, rgba(250,204,21,.13), rgba(14,165,233,.07)) !important;
    border:1px solid rgba(148,163,184,.22) !important;
}

#customerForm .form-check-input{
    width:42px !important;
    height:22px !important;
    margin-left:-40px !important;
    margin-top:1px !important;
}

#customerForm .modal-footer{
    grid-column:1 / -1 !important;
    position:sticky !important;
    bottom:-18px !important;
    z-index:5 !important;
    margin:2px -18px -18px !important;
    padding:14px 18px !important;
    border-top:1px solid rgba(148,163,184,.22) !important;
    background:rgba(255,255,255,.92) !important;
    backdrop-filter:blur(14px) !important;
}

#customerForm .modal-footer .btn{
    border-radius:15px !important;
    min-height:44px !important;
    padding:10px 18px !important;
    font-weight:900 !important;
}

#customerForm .modal-footer .btn-primary{
    border:0 !important;
    background:linear-gradient(135deg,#06b6d4,#2563eb) !important;
    box-shadow:0 14px 28px rgba(37,99,235,.24) !important;
}

#customerForm .modal-footer .btn-secondary{
    color:#0f172a !important;
    background:#fff !important;
    border:1px solid #d9e4ef !important;
}

@media(max-width:991.98px){
    #customerModal .modal-dialog{
        max-width:96vw !important;
        margin:.75rem auto !important;
    }

    #customerForm .ego-sec-basic,
    #customerForm .ego-sec-social,
    #customerForm .ego-sec-billing,
    #customerForm .ego-sec-type,
    #customerForm .ego-sec-extra,
    #customerForm .ego-sec-lead{
        grid-column:1 / -1 !important;
    }
}
</style>
<!-- EGO_CUSTOMER_HORIZONTAL_PRO_END -->

