@php
    $selectedCustomerId = old('customer_id', $profile->customer_id ?? '');
    $selectedStatus = old('status', $profile->status ?? 'draft');
    $selectedTier = old('price_tier_id', $profile->price_tier_id ?? '');
@endphp

<div class="cp-form-grid">
    <div class="cp-field span-2">
        <label>Tên khách hàng đã tạo trước đó</label>
        <select class="cp-select" name="customer_id" id="cp-customer-select" data-customer-select>
            <option value="">-- Chọn khách hàng --</option>
            @foreach($customers as $customer)
                <option
                    value="{{ $customer->id }}"
                    data-name="{{ $customer->display_name }}"
                    data-phone="{{ $customer->phone }}"
                    data-email="{{ $customer->email }}"
                    data-address="{{ $customer->address }}"
                    data-tax="{{ $customer->tax_code }}"
                    data-tier="{{ $customer->price_tier_id }}"
                    {{ (string) $selectedCustomerId === (string) $customer->id ? 'selected' : '' }}>
                    {{ $customer->display_name }} @if($customer->phone) - {{ $customer->phone }} @endif
                </option>
            @endforeach
        </select>
        <div class="cp-small cp-muted" style="margin-top:5px">Chọn khách hàng ở đây sẽ tự đổ tên đại lý, SĐT, email, MST nếu có.</div>
    </div>

    <div class="cp-field">
        <label>Trạng thái *</label>
        <select class="cp-select" name="status" required>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" {{ $selectedStatus === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="cp-field">
        <label>Cấp đại lý / Bậc giá</label>
        <select class="cp-select" name="price_tier_id" id="cp-price-tier-select">
            <option value="">-- Chọn cấp --</option>
            @foreach($priceTiers as $tier)
                <option value="{{ $tier->id }}" data-name="{{ $tier->name }}" {{ (string) $selectedTier === (string) $tier->id ? 'selected' : '' }}>{{ $tier->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="cp-field">
        <label>Tên đại lý *</label>
        <input class="cp-input" name="agent_name" id="cp-agent-name" value="{{ old('agent_name', $profile->agent_name ?? '') }}" required placeholder="VD: Đại lý An Khang Xanh">
    </div>

    <div class="cp-field">
        <label>Cấp đại lý ghi chú</label>
        <input class="cp-input" name="agent_level" id="cp-agent-level" value="{{ old('agent_level', $profile->agent_level ?? '') }}" placeholder="VD: Đại lý 1 / Đại lý cấp cao">
    </div>

    <div class="cp-field">
        <label>Tiền đặt cọc</label>
        <input class="cp-input" name="deposit_amount" inputmode="decimal" data-money value="{{ old('deposit_amount', isset($profile->deposit_amount) ? rtrim(rtrim(number_format((float) $profile->deposit_amount, 2, '.', ''), '0'), '.') : '0') }}" placeholder="VD: 50000000">
    </div>

    <div class="cp-field">
        <label>Ngày đặt cọc</label>
        <input class="cp-input" type="date" name="deposit_date" value="{{ old('deposit_date', optional($profile->deposit_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="cp-field">
        <label>Số điện thoại</label>
        <input class="cp-input" name="phone" id="cp-phone" value="{{ old('phone', $profile->phone ?? '') }}">
    </div>

    <div class="cp-field">
        <label>Email</label>
        <input class="cp-input" name="email" id="cp-email" value="{{ old('email', $profile->email ?? '') }}">
    </div>

    <div class="cp-field">
        <label>Mã số thuế</label>
        <input class="cp-input" name="tax_code" id="cp-tax" value="{{ old('tax_code', $profile->tax_code ?? '') }}">
    </div>

    <div class="cp-field">
        <label>Mã hợp đồng / mã hồ sơ</label>
        <input class="cp-input" name="contract_code" value="{{ old('contract_code', $profile->contract_code ?? '') }}" placeholder="VD: DL-2026-001">
    </div>

    <div class="cp-field">
        <label>Ngày hợp đồng</label>
        <input class="cp-input" type="date" name="contract_date" value="{{ old('contract_date', optional($profile->contract_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="cp-field">
        <label>Ngày chăm sóc tiếp</label>
        <input class="cp-input" type="date" name="next_followup_date" value="{{ old('next_followup_date', optional($profile->next_followup_date ?? null)->format('Y-m-d')) }}">
    </div>

    <div class="cp-field">
        <label>Người đại diện</label>
        <input class="cp-input" name="representative_name" value="{{ old('representative_name', $profile->representative_name ?? '') }}">
    </div>

    <div class="cp-field">
        <label>Chức vụ</label>
        <input class="cp-input" name="representative_position" value="{{ old('representative_position', $profile->representative_position ?? '') }}">
    </div>

    <div class="cp-field span-2">
        <label>Địa chỉ</label>
        <textarea class="cp-textarea" name="address" id="cp-address" placeholder="Địa chỉ đại lý / công ty">{{ old('address', $profile->address ?? '') }}</textarea>
    </div>

    <div class="cp-field span-2">
        <label>Ghi chú hồ sơ</label>
        <textarea class="cp-textarea" name="note" placeholder="Điều khoản, hạng mục còn thiếu, lịch sử trao đổi...">{{ old('note', $profile->note ?? '') }}</textarea>
    </div>

    <div class="cp-field">
        <label>Loại giấy tờ upload</label>
        <select class="cp-select" name="document_type">
            @foreach($documentTypes as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="cp-field">
        <label>Các giấy tờ liên quan</label>
        <input class="cp-input" type="file" name="documents[]" multiple>
        <div class="cp-small cp-muted" style="margin-top:5px">Hỗ trợ ảnh, PDF, Word, Excel, TXT, ZIP/RAR. Mỗi file tối đa 20MB.</div>
    </div>

    <div class="cp-field">
        <label>Ghi chú file</label>
        <input class="cp-input" name="document_note" placeholder="VD: bản scan hợp đồng">
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const customerSelect = document.querySelector('[data-customer-select]');
        const tierSelect = document.getElementById('cp-price-tier-select');
        const agentLevel = document.getElementById('cp-agent-level');

        function fillFromCustomer(force) {
            if (!customerSelect) return;
            const option = customerSelect.options[customerSelect.selectedIndex];
            if (!option) return;

            const fields = [
                ['cp-agent-name', option.dataset.name],
                ['cp-phone', option.dataset.phone],
                ['cp-email', option.dataset.email],
                ['cp-address', option.dataset.address],
                ['cp-tax', option.dataset.tax],
            ];

            fields.forEach(function (item) {
                const el = document.getElementById(item[0]);
                if (el && (force || !el.value)) el.value = item[1] || '';
            });

            if (tierSelect && option.dataset.tier && (force || !tierSelect.value)) {
                tierSelect.value = option.dataset.tier;
                fillLevelFromTier(false);
            }
        }

        function fillLevelFromTier(force) {
            if (!tierSelect || !agentLevel) return;
            const option = tierSelect.options[tierSelect.selectedIndex];
            if (option && option.dataset.name && (force || !agentLevel.value)) {
                agentLevel.value = option.dataset.name;
            }
        }

        customerSelect?.addEventListener('change', function () { fillFromCustomer(true); });
        tierSelect?.addEventListener('change', function () { fillLevelFromTier(true); });

        document.querySelectorAll('[data-money]').forEach(function (input) {
            input.addEventListener('blur', function () {
                let value = String(input.value || '').replace(/\s|đ|Đ|₫/g, '');
                if (!value) return;
                if (value.includes(',') && value.includes('.')) value = value.replace(/\./g, '').replace(',', '.');
                else if ((value.match(/\./g) || []).length > 1) value = value.replace(/\./g, '');
                else if (value.includes(',')) value = value.replace(',', '.');
                value = value.replace(/[^0-9.\-]/g, '');
                const number = parseFloat(value);
                if (!isNaN(number)) input.value = number;
            });
        });
    });
</script>
