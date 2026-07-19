@php
    $value = fn($name, $default = null) => old($name, $report->$name ?? $default);
    $dt = fn($v) => $v ? \Carbon\Carbon::parse($v)->format('Y-m-d\TH:i') : '';
    $linksValue = old('proof_links', is_array($report->proof_links ?? null) ? implode("\n", $report->proof_links) : ($report->proof_links ?? ''));
@endphp

<style>
.swr-wrap{max-width:1180px;margin:0 auto;padding:6px}
.swr-card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06);padding:18px}
.swr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.swr-field label{display:block;font-weight:900;margin-bottom:6px;color:#0f172a}
.swr-input{width:100%;border:1px solid #d1d5db;border-radius:12px;padding:10px 12px;background:#fff}
.swr-btn{border:0;border-radius:12px;padding:11px 16px;background:#0f766e;color:#fff;font-weight:900;text-decoration:none;display:inline-flex}
.swr-btn.dark{background:#0f172a}.swr-btn.light{background:#e2e8f0;color:#0f172a}
.swr-muted{color:#64748b}.swr-full{grid-column:1/-1}
.swr-section{grid-column:1/-1;margin-top:8px;padding-top:14px;border-top:1px solid #e5e7eb}
.swr-section h2{margin:0;font-size:18px;font-weight:900}
@media(max-width:800px){.swr-grid{grid-template-columns:1fr}}
</style>

<div class="swr-wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:10px;flex-wrap:wrap">
        <div>
            <h1 style="font-size:28px;font-weight:900;margin:0">{{ $title }}</h1>
            <div class="swr-muted">Phase này chỉ lưu bên Sales, chưa đồng bộ sang Khách hàng.</div>
        </div>
        <a class="swr-btn dark" href="{{ route('sales.work-reports.index') }}">Quay lại</a>
    </div>

    @if($errors->any())
        <div class="swr-card" style="background:#fee2e2;color:#991b1b;margin-bottom:14px">
            <strong>Kiểm tra lại thông tin:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="swr-card swr-grid" method="POST" action="{{ $action }}">
        @csrf
        @if(!empty($method))
            @method($method)
        @endif


        {{-- EGO_QUICK_PASTE_START --}}
        <div class="swr-section">
            <h2>0. Dán nhanh data từ tin nhắn / form</h2>
        </div>

        <div class="swr-field swr-full" style="background:#f8fafc;border:1px dashed #94a3b8;border-radius:16px;padding:14px">
            <label>Dán nguyên đoạn thông tin khách vào đây</label>
            <textarea
                class="swr-input"
                id="quick_paste_text"
                rows="8"
                placeholder="VD: Phone number: 091 930 30 39
Email: abc@yahoo.com
Full name: Khai Tran Quang
Tiền điện trung bình: Trên 5 triệu
Muốn lắp: Ngay trong tháng này
Khu vực: Cần Thơ
Diện tích mái: Trên 60m2
Ngân sách: Trên 200 triệu"></textarea>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px">
                <button type="button" class="swr-btn" id="quick_paste_btn">Tự điền vào báo cáo</button>
                <button type="button" class="swr-btn light" id="quick_paste_clear_btn">Xoá ô dán nhanh</button>
            </div>

            <div class="swr-muted" style="margin-top:8px">
                Có thể copy nguyên đoạn từ Zalo, Facebook, form lead. Hệ thống sẽ tự bóc tên, SĐT, email, khu vực, ngân sách, thời gian lắp và nhu cầu.
            </div>
        </div>
        {{-- EGO_QUICK_PASTE_END --}}

        <div class="swr-section"><h2>1. Thông tin khách / data</h2></div>

        <div class="swr-field">
            <label>Tên khách *</label>
            <input class="swr-input" name="customer_name" value="{{ $value('customer_name') }}" required placeholder="VD: Anh Minh Solar">
        </div>

        <div class="swr-field">
            <label>Số điện thoại</label>
            <input class="swr-input" name="customer_phone" value="{{ $value('customer_phone') }}" placeholder="090...">
        </div>

        <div class="swr-field">
            <label>Email</label>
            <input class="swr-input" type="email" name="customer_email" value="{{ $value('customer_email') }}">
        </div>

        <div class="swr-field">
            <label>Công ty / cửa hàng</label>
            <input class="swr-input" name="customer_company" value="{{ $value('customer_company') }}">
        </div>

        <div class="swr-field">
            <label>Loại khách</label>
            <select class="swr-input" name="customer_type">
                @foreach($customerTypes as $key => $label)
                    <option value="{{ $key }}" {{ $value('customer_type', 'personal') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="swr-field">
            <label>Khu vực / tỉnh thành</label>
            <input class="swr-input" name="region_text" value="{{ $value('region_text') }}" placeholder="VD: Bình Dương, Đắk Lắk...">
        </div>

        <div class="swr-field swr-full">
            <label>Địa chỉ</label>
            <input class="swr-input" name="customer_address" value="{{ $value('customer_address') }}">
        </div>

        <div class="swr-field">
            <label>Facebook name</label>
            <input class="swr-input" name="facebook_name" value="{{ $value('facebook_name') }}">
        </div>

        <div class="swr-field">
            <label>Facebook link</label>
            <input class="swr-input" name="facebook_link" value="{{ $value('facebook_link') }}">
        </div>

        <div class="swr-field">
            <label>Zalo ID / SĐT Zalo</label>
            <input class="swr-input" name="zalo_id" value="{{ $value('zalo_id') }}">
        </div>

        <div class="swr-field">
            <label>Nguồn data</label>
            <select class="swr-input" name="data_source_id">
                <option value="">-- Chưa rõ nguồn --</option>
                @foreach($sources as $source)
                    <option value="{{ $source->id }}" {{ (string)$value('data_source_id') === (string)$source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="swr-section"><h2>2. Sales xử lý data</h2></div>

        @if($canManage)
            <div class="swr-field">
                <label>Nhân viên sales phụ trách</label>
                <select class="swr-input" name="assigned_to">
                    @foreach($salesUsers as $user)
                        <option value="{{ $user->id }}" {{ (string)$value('assigned_to', auth()->id()) === (string)$user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="swr-field">
            <label>Nhận data lúc nào</label>
            <input class="swr-input" type="datetime-local" name="data_received_at" value="{{ old('data_received_at', $dt($report->data_received_at ?? now())) }}">
        </div>

        <div class="swr-field">
            <label>Gọi/nhắn lần đầu lúc nào</label>
            <input class="swr-input" type="datetime-local" name="first_call_at" value="{{ old('first_call_at', $dt($report->first_call_at ?? null)) }}">
        </div>

        <div class="swr-field">
            <label>Tương tác gần nhất</label>
            <input class="swr-input" type="datetime-local" name="last_contact_at" value="{{ old('last_contact_at', $dt($report->last_contact_at ?? null)) }}">
        </div>

        <div class="swr-field">
            <label>Kênh liên hệ</label>
            <select class="swr-input" name="contact_channel">
                @foreach($channels as $key => $label)
                    <option value="{{ $key }}" {{ $value('contact_channel', 'call') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="swr-field">
            <label>Trạng thái</label>
            <select class="swr-input" name="status">
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ $value('status', 'new') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="swr-field">
            <label>Độ nóng data</label>
            <select class="swr-input" name="priority">
                @foreach($priorities as $key => $label)
                    <option value="{{ $key }}" {{ $value('priority', 'normal') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="swr-field">
            <label>Kết quả tương tác</label>
            <select class="swr-input" name="outcome">
                <option value="">-- Chưa chọn --</option>
                @foreach($outcomes as $key => $label)
                    <option value="{{ $key }}" {{ $value('outcome') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="swr-section"><h2>3. Nhu cầu, tư vấn, phản hồi</h2></div>

        <div class="swr-field swr-full">
            <label>Nhu cầu khách hàng</label>
            <textarea class="swr-input" name="customer_need" rows="4" placeholder="Khách cần gì? Lắp hệ bao nhiêu kW? Mục tiêu tiết kiệm điện hay bán điện?">{{ $value('customer_need') }}</textarea>
        </div>

        <div class="swr-field">
            <label>Công suất dự kiến kW</label>
            <input class="swr-input" type="number" step="0.01" min="0" name="system_size_kw" value="{{ $value('system_size_kw') }}">
        </div>

        <div class="swr-field">
            <label>Ngân sách dự kiến</label>
            <input class="swr-input" name="budget_range" value="{{ $value('budget_range') }}" placeholder="VD: 300-500 triệu">
        </div>

        <div class="swr-field">
            <label>Timeline dự án</label>
            <input class="swr-input" name="project_timeline" value="{{ $value('project_timeline') }}" placeholder="VD: muốn lắp trong tháng này">
        </div>

        <div class="swr-field">
            <label>Doanh thu kỳ vọng</label>
            <input class="swr-input" type="number" step="1000" min="0" name="revenue_expectation" value="{{ $value('revenue_expectation') }}">
        </div>

        <div class="swr-field swr-full">
            <label>Đã tư vấn thế nào</label>
            <textarea class="swr-input" name="consultation_summary" rows="5" placeholder="Ghi rõ đã tư vấn sản phẩm, giải pháp, giá, chính sách, bảo hành...">{{ $value('consultation_summary') }}</textarea>
        </div>

        <div class="swr-field swr-full">
            <label>Sản phẩm / giải pháp đã báo</label>
            <textarea class="swr-input" name="quoted_products" rows="3">{{ $value('quoted_products') }}</textarea>
        </div>

        <div class="swr-field swr-full">
            <label>Phản hồi của khách</label>
            <textarea class="swr-input" name="customer_feedback" rows="4">{{ $value('customer_feedback') }}</textarea>
        </div>

        <div class="swr-section"><h2>4. Follow-up / bằng chứng</h2></div>

        <div class="swr-field">
            <label>Hẹn chăm sóc lại lúc nào</label>
            <input class="swr-input" type="datetime-local" name="next_followup_at" value="{{ old('next_followup_at', $dt($report->next_followup_at ?? null)) }}">
        </div>

        <div class="swr-field">
            <label>Việc cần làm tiếp theo</label>
            <input class="swr-input" name="next_action" value="{{ $value('next_action') }}" placeholder="VD: gửi báo giá, gọi lại, hẹn khảo sát...">
        </div>

        <div class="swr-field swr-full">
            <label>Lý do thất bại / data lỗi nếu có</label>
            <textarea class="swr-input" name="lost_reason" rows="3">{{ $value('lost_reason') }}</textarea>
        </div>

        <div class="swr-field swr-full">
            <label>Link minh chứng, mỗi dòng một link</label>
            <textarea class="swr-input" name="proof_links" rows="3" placeholder="Link ảnh chụp Zalo, Facebook, báo giá...">{{ $linksValue }}</textarea>
        </div>

        <div class="swr-full" style="display:flex;gap:10px;justify-content:flex-end;margin-top:8px">
            <a class="swr-btn light" href="{{ route('sales.work-reports.index') }}">Huỷ</a>
            <button class="swr-btn" type="submit">Lưu báo cáo</button>
        </div>
    </form>
</div>


{{-- EGO_QUICK_PASTE_SCRIPT_START --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const quickBox = document.getElementById('quick_paste_text');
    const fillBtn = document.getElementById('quick_paste_btn');
    const clearBtn = document.getElementById('quick_paste_clear_btn');

    if (!quickBox || !fillBtn) return;

    function field(name) {
        return document.querySelector('[name="' + name + '"]');
    }

    function setValue(name, value, overwrite = false) {
        const el = field(name);
        if (!el || value === null || value === undefined) return;

        value = String(value).trim();
        if (!value) return;

        if (overwrite || !String(el.value || '').trim()) {
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function appendValue(name, value) {
        const el = field(name);
        if (!el || !value) return;

        value = String(value).trim();
        if (!value) return;

        const current = String(el.value || '').trim();
        if (!current) {
            el.value = value;
        } else if (!current.includes(value)) {
            el.value = current + "\n" + value;
        }

        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function normalizeSpaces(text) {
        return String(text || '')
            .replace(/\u00A0/g, ' ')
            .replace(/[ \t]+/g, ' ')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .trim();
    }

    function stripVietnamese(str) {
        return String(str || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLowerCase();
    }

    function cleanPhone(phone) {
        if (!phone) return '';
        let p = phone.replace(/[^\d+]/g, '');
        if (p.startsWith('+84')) p = '0' + p.slice(3);
        if (p.startsWith('84') && p.length >= 11) p = '0' + p.slice(2);
        return p;
    }

    function getLineByKeywords(lines, keywords) {
        for (const line of lines) {
            const clean = stripVietnamese(line);
            if (keywords.some(k => clean.includes(k))) {
                return line.trim();
            }
        }
        return '';
    }

    function valueAfterColon(line) {
        if (!line) return '';
        const parts = line.split(':');
        if (parts.length >= 2) {
            return parts.slice(1).join(':').trim();
        }
        return line.trim();
    }

    function valueAfterQuestionColon(line) {
        if (!line) return '';
        const idx = line.lastIndexOf('?:');
        if (idx >= 0) {
            return line.slice(idx + 2).trim();
        }
        return valueAfterColon(line);
    }

    function moneyPriority(text) {
        const n = stripVietnamese(text);
        if (
            n.includes('tren 200 trieu') ||
            n.includes('hon 200 trieu') ||
            n.includes('> 200') ||
            n.includes('200 trieu') ||
            n.includes('300 trieu') ||
            n.includes('500 trieu') ||
            n.includes('1 ty')
        ) {
            return 'hot';
        }

        if (
            n.includes('tren 100 trieu') ||
            n.includes('100 trieu') ||
            n.includes('150 trieu')
        ) {
            return 'high';
        }

        return '';
    }

    function timelinePriority(text) {
        const n = stripVietnamese(text);
        if (
            n.includes('ngay trong thang nay') ||
            n.includes('trong thang nay') ||
            n.includes('lap ngay') ||
            n.includes('cang som cang tot') ||
            n.includes('gap')
        ) {
            return 'hot';
        }
        return '';
    }

    function extract() {
        const raw = normalizeSpaces(quickBox.value);
        if (!raw) {
            alert('Bạn hãy dán nội dung khách hàng vào ô trước.');
            return;
        }

        const lines = raw.split('\n').map(x => x.trim()).filter(Boolean);

        const emailMatch = raw.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i);
        const phoneLabelMatch = raw.match(/(?:phone number|so dien thoai|sdt|điện thoại|dien thoai)\s*:?\s*([+0-9][0-9\s.\-]{7,})/i);
        const anyPhoneMatch = raw.match(/(?:\+84|84|0)[0-9\s.\-]{8,12}/);

        const fullNameLine = getLineByKeywords(lines, ['full name', 'ho ten', 'ten khach', 'ten:']);
        const electricLine = getLineByKeywords(lines, ['tien dien', 'dien trung binh', 'moi thang']);
        const timelineLine = getLineByKeywords(lines, ['muon lap', 'thoi gian nao', 'khi nao lap', 'lap dien mat troi trong thoi gian']);
        const regionLine = getLineByKeywords(lines, ['khu vuc', 'dia chi lap dat', 'dia chi', 'ban dang o']);
        const roofLine = getLineByKeywords(lines, ['dien tich mai', 'mai uoc tinh', 'dien tich']);
        const budgetLine = getLineByKeywords(lines, ['ngan sach', 'du kien dau tu', 'dau tu la bao nhieu']);

        const name = fullNameLine ? valueAfterColon(fullNameLine) : '';
        const email = emailMatch ? emailMatch[0] : '';
        const phone = cleanPhone(phoneLabelMatch ? phoneLabelMatch[1] : (anyPhoneMatch ? anyPhoneMatch[0] : ''));

        const electricBill = electricLine ? valueAfterQuestionColon(electricLine) : '';
        const timeline = timelineLine ? valueAfterQuestionColon(timelineLine) : '';
        const region = regionLine ? valueAfterQuestionColon(regionLine) : '';
        const roofArea = roofLine ? valueAfterQuestionColon(roofLine) : '';
        const budget = budgetLine ? valueAfterQuestionColon(budgetLine) : '';

        setValue('customer_name', name);
        setValue('customer_phone', phone);
        setValue('customer_email', email);
        setValue('region_text', region);
        setValue('customer_address', region);
        setValue('budget_range', budget);
        setValue('project_timeline', timeline);

        let need = [];
        if (electricBill) need.push('Tiền điện trung bình mỗi tháng: ' + electricBill);
        if (timeline) need.push('Thời gian muốn lắp: ' + timeline);
        if (region) need.push('Khu vực / địa chỉ lắp đặt: ' + region);
        if (roofArea) need.push('Diện tích mái ước tính: ' + roofArea);
        if (budget) need.push('Ngân sách dự kiến: ' + budget);

        if (need.length) {
            appendValue('customer_need', need.join("\n"));
        } else {
            appendValue('customer_need', raw);
        }

        appendValue('consultation_summary', 'Data khách tự điền từ form/tin nhắn. Sales cần gọi xác nhận nhu cầu, khảo sát mái, điện trung bình và ngân sách.');
        setValue('next_action', 'Gọi xác nhận nhu cầu và tư vấn giải pháp điện mặt trời');

        const priorityByMoney = moneyPriority(budget);
        const priorityByTimeline = timelinePriority(timeline);

        if (priorityByMoney === 'hot' || priorityByTimeline === 'hot') {
            setValue('priority', 'hot', true);
        } else if (priorityByMoney === 'high') {
            setValue('priority', 'high', true);
        }

        setValue('status', 'new', true);

        alert('Đã tự điền thông tin vào báo cáo. Bạn kiểm tra lại rồi bấm Lưu báo cáo.');
    }

    fillBtn.addEventListener('click', extract);

    quickBox.addEventListener('paste', function () {
        setTimeout(function () {
            if (quickBox.value.trim()) {
                extract();
            }
        }, 100);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            quickBox.value = '';
            quickBox.focus();
        });
    }
});
</script>
{{-- EGO_QUICK_PASTE_SCRIPT_END --}}



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
