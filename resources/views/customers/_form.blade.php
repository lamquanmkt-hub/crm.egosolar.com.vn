@php
    $isEdit = isset($customer) && $customer;

    $action = $isEdit
        ? route('customers.update', $customer->id)
        : route('customers.store');
@endphp

<form
    method="POST"
    action="{{ $action }}"
    class="cx-customer-form"
    data-customer-form
    autocomplete="off"
    novalidate
>
    @csrf

    @if($isEdit)
        @method('PUT')

        <input
            type="hidden"
            name="customer_id"
            value="{{ $customer->id }}"
        >
    @endif

    <header class="cx-modal-hero">
        <div class="cx-modal-hero__icon">
            <i class="bi bi-person-{{ $isEdit ? 'gear' : 'plus' }}"></i>
        </div>

        <div class="cx-modal-hero__copy">
            <span>
                {{ $isEdit ? 'CẬP NHẬT HỒ SƠ' : 'KHÁCH HÀNG MỚI' }}
            </span>

            <h2>
                {{ $isEdit ? 'Chỉnh sửa khách hàng' : 'Thêm khách hàng' }}
            </h2>

            <p>
                Quản lý thông tin liên hệ, phân loại và dữ liệu hóa đơn.
            </p>
        </div>

        <button
            type="button"
            class="cx-modal-close"
            data-bs-dismiss="modal"
            aria-label="Đóng"
        >
            <i class="bi bi-x-lg"></i>
        </button>
    </header>

    <nav class="cx-form-tabs" aria-label="Các nhóm thông tin">
        <button
            type="button"
            class="cx-form-tab is-active"
            data-customer-tab="basic"
        >
            <i class="bi bi-person-vcard"></i>
            <span>Thông tin</span>
        </button>

        <button
            type="button"
            class="cx-form-tab"
            data-customer-tab="classify"
        >
            <i class="bi bi-diagram-3"></i>
            <span>Phân loại</span>
        </button>

        <button
            type="button"
            class="cx-form-tab"
            data-customer-tab="contact"
        >
            <i class="bi bi-chat-square-dots"></i>
            <span>Liên hệ</span>
        </button>

        <button
            type="button"
            class="cx-form-tab"
            data-customer-tab="billing"
        >
            <i class="bi bi-receipt"></i>
            <span>Hóa đơn</span>
        </button>
    </nav>

    <div class="cx-modal-form-body">
        <div
            class="cx-duplicate-alert"
            data-duplicate-alert
        ></div>

        <section
            class="cx-form-pane is-active"
            data-customer-pane="basic"
        >
            <div class="cx-pane-heading">
                <div>
                    <span>01</span>

                    <div>
                        <h3>Thông tin cơ bản</h3>
                        <p>
                            Thông tin nhận diện và liên hệ chính.
                        </p>
                    </div>
                </div>
            </div>

            <div class="cx-form-grid">
                <div class="cx-field is-full">
                    <label>
                        Tên khách hàng
                        <em>*</em>
                    </label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-person"></i>

                        <input
                            class="cx-input"
                            name="name"
                            value="{{ old('name', $customer->name ?? '') }}"
                            required
                            autocomplete="off"
                            placeholder="Nhập tên khách hàng"
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>
                        Số điện thoại
                        <em>*</em>
                    </label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-telephone"></i>

                        <input
                            class="cx-input"
                            name="phone"
                            value="{{ old('phone', $customer->phone ?? '') }}"
                            required
                            inputmode="tel"
                            autocomplete="off"
                            placeholder="09xx xxx xxx"
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>Email</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-envelope"></i>

                        <input
                            type="email"
                            class="cx-input"
                            name="email"
                            value="{{ old('email', $customer->email ?? '') }}"
                            autocomplete="off"
                            placeholder="email@domain.com"
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>Biệt danh</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-person-badge"></i>

                        <input
                            class="cx-input"
                            name="nickname"
                            value="{{ old('nickname', $customer->nickname ?? '') }}"
                            autocomplete="off"
                            placeholder="Tên gọi dễ nhớ"
                        >
                    </div>
                </div>

                <div class="cx-field is-full">
                    <label>Địa chỉ</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-geo-alt"></i>

                        <input
                            class="cx-input"
                            name="address"
                            value="{{ old('address', $customer->address ?? '') }}"
                            autocomplete="off"
                            data-lpignore="true"
                            placeholder="Địa chỉ khách hàng"
                        >
                    </div>
                </div>
            </div>
        </section>

        <section
            class="cx-form-pane"
            data-customer-pane="classify"
        >
            <div class="cx-pane-heading">
                <div>
                    <span>02</span>

                    <div>
                        <h3>Phân loại và phụ trách</h3>

                        <p>
                            Xác định nhóm khách và nhân sự chăm sóc.
                        </p>
                    </div>
                </div>
            </div>

            <div class="cx-form-grid">
                <div class="cx-field">
                    <label>Loại khách hàng</label>

                    <select
                        class="cx-select"
                        name="customer_type_id"
                    >
                        <option value="">
                            Chưa phân loại
                        </option>

                        @foreach($customerTypes as $type)
                            <option
                                value="{{ $type->id }}"
                                @selected(
                                    (string) old(
                                        'customer_type_id',
                                        $customer->customer_type_id ?? ''
                                    ) === (string) $type->id
                                )
                            >
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="cx-field">
                    <label>Trạng thái khách</label>

                    <select
                        class="cx-select"
                        name="customer_status"
                    >
                        @foreach([
                            'lead' => 'Lead',
                            'member' => 'Member',
                            'retail' => 'Khách lẻ',
                        ] as $value => $label)
                            <option
                                value="{{ $value }}"
                                @selected(
                                    old(
                                        'customer_status',
                                        $customer->customer_status ?? 'lead'
                                    ) === $value
                                )
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="cx-field">
                    <label>Khu vực</label>

                    <select
                        class="cx-select"
                        name="region_id"
                    >
                        <option value="">
                            Chưa chọn khu vực
                        </option>

                        @foreach($regions as $region)
                            <option
                                value="{{ $region->id }}"
                                @selected(
                                    (string) old(
                                        'region_id',
                                        $customer->region_id ?? ''
                                    ) === (string) $region->id
                                )
                            >
                                {{ $region->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="cx-field">
                    <label>Người phụ trách</label>

                    <select
                        class="cx-select"
                        name="owner_id"
                    >
                        <option value="">
                            Chưa phân công
                        </option>

                        @foreach($users as $user)
                            <option
                                value="{{ $user->id }}"
                                @selected(
                                    (string) old(
                                        'owner_id',
                                        $customer->owner_id ?? ''
                                    ) === (string) $user->id
                                )
                            >
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="cx-field is-full">
                    <label class="cx-switch-row">
                        <input
                            type="checkbox"
                            name="is_potential"
                            value="1"
                            @checked(
                                old(
                                    'is_potential',
                                    $customer->is_potential ?? false
                                )
                            )
                        >

                        <span class="cx-switch-control"></span>

                        <span>
                            <strong>
                                Khách hàng tiềm năng
                            </strong>

                            <small>
                                Ưu tiên chăm sóc và theo dõi.
                            </small>
                        </span>
                    </label>
                </div>

                <div class="cx-field is-full">
                    <label>Ghi chú nội bộ</label>

                    <textarea
                        class="cx-textarea"
                        name="group_note"
                        rows="5"
                        placeholder="Nhu cầu, đặc điểm hoặc lưu ý về khách hàng..."
                    >{{ old('group_note', $customer->group_note ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section
            class="cx-form-pane"
            data-customer-pane="contact"
        >
            <div class="cx-pane-heading">
                <div>
                    <span>03</span>

                    <div>
                        <h3>Kênh liên hệ</h3>

                        <p>
                            Facebook, Zalo và các kênh chăm sóc khác.
                        </p>
                    </div>
                </div>
            </div>

            <div class="cx-form-grid">
                <div class="cx-field">
                    <label>Tên Facebook</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-facebook"></i>

                        <input
                            class="cx-input"
                            name="facebook_name"
                            value="{{ old('facebook_name', $customer->facebook_name ?? '') }}"
                            autocomplete="off"
                            placeholder="Tên tài khoản Facebook"
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>Link Facebook</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-link-45deg"></i>

                        <input
                            type="url"
                            class="cx-input"
                            name="facebook_link"
                            value="{{ old('facebook_link', $customer->facebook_link ?? '') }}"
                            autocomplete="off"
                            placeholder="https://facebook.com/..."
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>Zalo ID</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-chat"></i>

                        <input
                            class="cx-input"
                            name="zalo_id"
                            value="{{ old('zalo_id', $customer->zalo_id ?? '') }}"
                            autocomplete="off"
                            placeholder="Số điện thoại hoặc Zalo ID"
                        >
                    </div>
                </div>

                {{-- EGO_CUSTOMER_CHATBOT_FIELD_V32 --}}
                <div class="cx-field is-full">
                    <label>Link Chat Bot AI</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-robot"></i>

                        <input
                            type="url"
                            class="cx-input"
                            name="ai_chatbot_link"
                            value="{{ old(
                                'ai_chatbot_link',
                                $customer->ai_chatbot_link ?? ''
                            ) }}"
                            autocomplete="off"
                            placeholder="https://chatbot.example.com/conversation/..."
                        >
                    </div>

                    <small class="cx-field-help">
                        Link mở trực tiếp cuộc hội thoại hoặc trợ lý AI dành cho khách hàng.
                    </small>
                </div>

                <div class="cx-form-tip is-full">
                    <i class="bi bi-lightbulb"></i>

                    <div>
                        <strong>
                            Mẹo quản lý liên hệ
                        </strong>

                        <span>
                            Số điện thoại được sử dụng để kiểm tra khách trùng và liên kết công trình.
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section
            class="cx-form-pane"
            data-customer-pane="billing"
        >
            <div class="cx-pane-heading">
                <div>
                    <span>04</span>

                    <div>
                        <h3>Thông tin xuất hóa đơn</h3>

                        <p>
                            Dữ liệu mặc định khi tạo báo giá và đơn hàng.
                        </p>
                    </div>
                </div>
            </div>

            <div class="cx-form-grid">
                <div class="cx-field">
                    <label>Tên công ty / cá nhân</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-building"></i>

                        <input
                            class="cx-input"
                            name="billing_company_name"
                            value="{{ old('billing_company_name', $customer->billing_company_name ?? '') }}"
                            autocomplete="off"
                            placeholder="Tên trên hóa đơn"
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>Mã số thuế</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-upc-scan"></i>

                        <input
                            class="cx-input"
                            name="billing_tax_code"
                            value="{{ old('billing_tax_code', $customer->billing_tax_code ?? '') }}"
                            autocomplete="off"
                            placeholder="Mã số thuế"
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>Email nhận hóa đơn</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-envelope-paper"></i>

                        <input
                            type="email"
                            class="cx-input"
                            name="billing_email"
                            value="{{ old('billing_email', $customer->billing_email ?? '') }}"
                            autocomplete="off"
                            placeholder="Email kế toán"
                        >
                    </div>
                </div>

                <div class="cx-field">
                    <label>Địa chỉ xuất hóa đơn</label>

                    <div class="cx-input-wrap">
                        <i class="bi bi-geo-alt"></i>

                        <input
                            class="cx-input"
                            name="billing_address"
                            value="{{ old('billing_address', $customer->billing_address ?? '') }}"
                            autocomplete="off"
                            data-lpignore="true"
                            placeholder="Địa chỉ trên hóa đơn"
                        >
                    </div>
                </div>
            </div>
        </section>
    </div>

    <footer class="cx-modal-form-footer">
        <div class="cx-form-progress">
            <span data-customer-step-text>
                Bước 1/4 · Thông tin
            </span>

            <div>
                <i class="is-active"></i>
                <i></i>
                <i></i>
                <i></i>
            </div>
        </div>

        <div class="cx-modal-footer-actions">
            <button
                type="button"
                class="cx-btn cx-btn--soft"
                data-customer-prev
                hidden
            >
                <i class="bi bi-arrow-left"></i>
                Trước
            </button>

            <button
                type="button"
                class="cx-btn cx-btn--soft"
                data-bs-dismiss="modal"
            >
                Hủy
            </button>

            <button
                type="button"
                class="cx-btn cx-btn--primary"
                data-customer-next
            >
                Tiếp theo
                <i class="bi bi-arrow-right"></i>
            </button>

            <button
                type="submit"
                class="cx-btn cx-btn--primary"
                data-customer-submit
                hidden
            >
                <i class="bi bi-check2-circle"></i>
                {{ $isEdit ? 'Lưu thay đổi' : 'Thêm khách hàng' }}
            </button>
        </div>
    </footer>
</form>
