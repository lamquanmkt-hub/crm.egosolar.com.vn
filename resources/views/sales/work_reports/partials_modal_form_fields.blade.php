@php
    $v = function($name, $default = '') use ($row) {
        return old($name, $row ? ($row->$name ?? $default) : $default);
    };
    $dtv = function($name, $default = null) use ($row) {
        $value = old($name, $row ? ($row->$name ?? null) : $default);
        return $value ? \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
    };
    $modalKey = $row ? 'edit-' . $row->id : 'create';
@endphp

<input type="hidden" name="open_modal" value="{{ $modalKey }}">

<div class="swr-quick">
    <label class="swr-quick-label">
        <i class="bi bi-magic"></i> Dán nhanh data từ Zalo / Facebook / Form
    </label>
    <textarea class="swr-input js-quick-paste-text" placeholder="Dán nguyên đoạn:
Phone number: 091 930 30 39
Email: tqkhai_kk@yahoo.com
Full name: Khai Tran Quang
Tiền điện trung bình: Trên 5 triệu
Muốn lắp: Ngay trong tháng này
Khu vực: Cần Thơ
Diện tích mái: Trên 60m2
Ngân sách: Trên 200 triệu"></textarea>
    <div class="d-flex gap-2 flex-wrap mt-2">
        <button type="button" class="swr-btn sm js-quick-paste-btn"><i class="bi bi-stars"></i> Tự điền</button>
        <button type="button" class="swr-btn sm light js-quick-paste-clear-btn">Xoá ô dán</button>
    </div>
</div>

<div class="swr-section-title"><i class="bi bi-person-vcard"></i> Khách hàng</div>

<div class="swr-field half">
    <label>Tên khách *</label>
    <input class="swr-input" name="customer_name" value="{{ $v('customer_name') }}" required>
</div>
<div class="swr-field">
    <label>SĐT</label>
    <input class="swr-input" name="customer_phone" value="{{ $v('customer_phone') }}">
</div>
<div class="swr-field">
    <label>Email</label>
    <input class="swr-input" type="email" name="customer_email" value="{{ $v('customer_email') }}">
</div>

<div class="swr-field">
    <label>Loại khách</label>
    <select class="swr-input" name="customer_type">
        @foreach($customerTypes as $key => $label)
            <option value="{{ $key }}" {{ $v('customer_type', 'retail') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="swr-field">
    <label>Trạng thái khách</label>
    <select class="swr-input" name="customer_stage">
        @foreach($customerStages as $key => $label)
            <option value="{{ $key }}" {{ $v('customer_stage', 'new_need_confirm') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="swr-field">
    <label>Khu vực</label>
    <input class="swr-input" name="region_text" value="{{ $v('region_text') }}">
</div>
<div class="swr-field">
    <label>Công ty</label>
    <input class="swr-input" name="customer_company" value="{{ $v('customer_company') }}">
</div>
<div class="swr-field">
    <label>Nguồn data</label>
    <select class="swr-input" name="data_source_id">
        <option value="">Chưa rõ</option>
        @foreach($sources as $source)
            <option value="{{ $source->id }}" {{ (string)$v('data_source_id') === (string)$source->id ? 'selected' : '' }}>{{ $source->name }}</option>
        @endforeach
    </select>
</div>

<input type="hidden" name="customer_address" value="{{ $v('customer_address') }}">
<input type="hidden" name="facebook_name" value="{{ $v('facebook_name') }}">
<input type="hidden" name="facebook_link" value="{{ $v('facebook_link') }}">
<input type="hidden" name="zalo_id" value="{{ $v('zalo_id') }}">

<div class="swr-section-title"><i class="bi bi-headset"></i> Sales xử lý</div>

@if($canManage)
    <div class="swr-field">
        <label>Sales phụ trách</label>
        <select class="swr-input" name="assigned_to">
            @foreach($salesUsers as $user)
                <option value="{{ $user->id }}" {{ (string)$v('assigned_to', auth()->id()) === (string)$user->id ? 'selected' : '' }}>{{ $user->name }}</option>
            @endforeach
        </select>
    </div>
@endif

<div class="swr-field">
    <label>Nhận data</label>
    <input class="swr-input" type="datetime-local" name="data_received_at" value="{{ $dtv('data_received_at', now()) }}">
</div>
<div class="swr-field">
    <label>Kênh</label>
    <select class="swr-input" name="contact_channel">
        @foreach($channels as $key => $label)
            <option value="{{ $key }}" {{ $v('contact_channel', 'call') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="swr-field">
    <label>Trạng thái CRM</label>
    <select class="swr-input" name="status">
        @foreach($statuses as $key => $label)
            <option value="{{ $key }}" {{ $v('status', 'new') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="swr-field">
    <label>Độ nóng</label>
    <select class="swr-input" name="priority">
        @foreach($priorities as $key => $label)
            <option value="{{ $key }}" {{ $v('priority', 'normal') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="swr-field">
    <label>Kết quả</label>
    <select class="swr-input" name="outcome">
        <option value="">Chưa chọn</option>
        @foreach($outcomes as $key => $label)
            <option value="{{ $key }}" {{ $v('outcome') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="swr-field">
    <label>Doanh thu kỳ vọng</label>
    <input class="swr-input" type="number" step="1000" min="0" name="revenue_expectation" value="{{ $v('revenue_expectation') }}">
</div>

<div class="swr-section-title"><i class="bi bi-telephone-outbound"></i> Gọi điện</div>

<div class="swr-field">
    <label>Gọi lần 1 lúc</label>
    <input class="swr-input" type="datetime-local" name="call_1_at" value="{{ $dtv('call_1_at') }}">
</div>
<div class="swr-field">
    <label>KQ gọi lần 1</label>
    <select class="swr-input" name="call_1_result">
        @foreach($callResults as $key => $label)
            <option value="{{ $key }}" {{ $v('call_1_result', 'not_called') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="swr-field">
    <label>Gọi lần 2 lúc</label>
    <input class="swr-input" type="datetime-local" name="call_2_at" value="{{ $dtv('call_2_at') }}">
</div>
<div class="swr-field">
    <label>KQ gọi lần 2</label>
    <select class="swr-input" name="call_2_result">
        @foreach($callResults as $key => $label)
            <option value="{{ $key }}" {{ $v('call_2_result', 'not_called') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="swr-section-title"><i class="bi bi-file-earmark-text"></i> Báo giá</div>

<div class="swr-field">
    <label>Tình trạng báo giá</label>
    <select class="swr-input" name="quote_status">
        @foreach($quoteStatuses as $key => $label)
            <option value="{{ $key }}" {{ $v('quote_status', 'not_sent') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="swr-field">
    <label>Ngày gửi báo giá</label>
    <input class="swr-input" type="datetime-local" name="quote_sent_at" value="{{ $dtv('quote_sent_at') }}">
</div>
<div class="swr-field half">
    <label>Sản phẩm / giải pháp đã báo</label>
    <textarea class="swr-input" name="quoted_products">{{ $v('quoted_products') }}</textarea>
</div>

<div class="swr-section-title"><i class="bi bi-chat-dots"></i> Nhu cầu và follow-up</div>

<div class="swr-field half">
    <label>Nhu cầu khách hàng</label>
    <textarea class="swr-input" name="customer_need">{{ $v('customer_need') }}</textarea>
</div>
<div class="swr-field half">
    <label>Đã tư vấn thế nào</label>
    <textarea class="swr-input" name="consultation_summary">{{ $v('consultation_summary') }}</textarea>
</div>

<div class="swr-field half">
    <label>Phản hồi khách</label>
    <textarea class="swr-input" name="customer_feedback">{{ $v('customer_feedback') }}</textarea>
</div>
<div class="swr-field half">
    <label>Việc cần làm tiếp theo</label>
    <input class="swr-input" name="next_action" value="{{ $v('next_action') }}">
</div>

<div class="swr-field">
    <label>Ngân sách</label>
    <input class="swr-input" name="budget_range" value="{{ $v('budget_range') }}">
</div>
<div class="swr-field">
    <label>Timeline</label>
    <input class="swr-input" name="project_timeline" value="{{ $v('project_timeline') }}">
</div>
<div class="swr-field">
    <label>Công suất kW</label>
    <input class="swr-input" type="number" step="0.01" min="0" name="system_size_kw" value="{{ $v('system_size_kw') }}">
</div>
<div class="swr-field">
    <label>Hẹn follow-up</label>
    <input class="swr-input" type="datetime-local" name="next_followup_at" value="{{ $dtv('next_followup_at') }}">
</div>

<div class="swr-field half">
    <label>Lý do mất / data lỗi</label>
    <input class="swr-input" name="lost_reason" value="{{ $v('lost_reason') }}">
</div>

<input type="hidden" name="first_call_at" value="{{ $dtv('first_call_at') }}">
<input type="hidden" name="last_contact_at" value="{{ $dtv('last_contact_at') }}">
<input type="hidden" name="proof_links" value="">


{{-- EGO_SALES_MANAGER_DROPDOWN_START --}}
@php
    $egoSalesManagerOptions = collect();

    try {
        $egoSalesManagerOptions = \App\Models\User::query()
            ->get()
            ->filter(function ($u) {
                $roles = [];

                foreach (['role', 'type', 'position', 'department'] as $field) {
                    if (!empty($u->{$field})) {
                        $roles[] = mb_strtolower((string) $u->{$field});
                    }
                }

                if (method_exists($u, 'getRoleNames')) {
                    foreach ($u->getRoleNames() as $roleName) {
                        $roles[] = mb_strtolower((string) $roleName);
                    }
                }

                $roleText = implode('|', array_unique(array_filter($roles)));

                return str_contains($roleText, 'sales_manager')
                    || str_contains($roleText, 'sales manager')
                    || str_contains($roleText, 'trưởng phòng sales')
                    || str_contains($roleText, 'truong_phong_sales')
                    || str_contains($roleText, 'manager_sales');
            })
            ->map(function ($u) {
                return [
                    'id' => (string) $u->id,
                    'name' => (string) ($u->name ?? $u->email ?? ('User #' . $u->id)),
                ];
            })
            ->values();
    } catch (\Throwable $e) {
        $egoSalesManagerOptions = collect();
    }
@endphp

<script>
(function () {
    var managers = @json($egoSalesManagerOptions);

    function cleanText(value) {
        return String(value || '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function selectLooksLikeSalesOwner(select) {
        var name = cleanText(select.getAttribute('name'));
        var id = cleanText(select.getAttribute('id'));
        var text = cleanText(select.closest('form, .modal, .card, section, div') ? select.closest('form, .modal, .card, section, div').textContent : '');

        return name.indexOf('sales') !== -1
            || name.indexOf('assigned') !== -1
            || name.indexOf('owner') !== -1
            || id.indexOf('sales') !== -1
            || text.indexOf('sales phụ trách') !== -1
            || text.indexOf('sales phu trach') !== -1;
    }

    function hasOption(select, value) {
        return Array.prototype.slice.call(select.options).some(function (opt) {
            return String(opt.value) === String(value);
        });
    }

    function addManagersToSelect(select) {
        if (!select || !selectLooksLikeSalesOwner(select)) return;

        managers.forEach(function (manager) {
            if (!manager || !manager.id || hasOption(select, manager.id)) return;

            var option = document.createElement('option');
            option.value = manager.id;
            option.textContent = manager.name + ' - Sales Manager';
            option.setAttribute('data-ego-sales-manager', '1');

            select.appendChild(option);
        });
    }

    function run() {
        if (!Array.isArray(managers) || managers.length === 0) return;

        document.querySelectorAll('select').forEach(addManagersToSelect);
    }

    document.addEventListener('DOMContentLoaded', run);

    setTimeout(run, 300);
    setTimeout(run, 900);
    setTimeout(run, 1800);

    document.addEventListener('click', function () {
        setTimeout(run, 150);
        setTimeout(run, 500);
    }, true);
})();
</script>
{{-- EGO_SALES_MANAGER_DROPDOWN_END --}}
