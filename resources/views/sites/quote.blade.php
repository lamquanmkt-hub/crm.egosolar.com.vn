@extends('layouts.app')

@section('content')
@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', '.');
@endphp

<style>
    .quote-wrap {
        max-width: 1280px;
        margin: 0 auto;
        padding: 24px;
    }

    .quote-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 22px;
        margin-bottom: 18px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.06);
    }

    .quote-title {
        font-size: 28px;
        font-weight: 800;
        margin: 0;
        color: #0f172a;
    }

    .quote-subtitle {
        color: #64748b;
        margin-top: 6px;
    }

    .quote-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
    }

    .quote-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }

    .quote-field label {
        display: block;
        font-size: 13px;
        font-weight: 700;
        color: #334155;
        margin-bottom: 6px;
    }

    .quote-field input,
    .quote-field select,
    .quote-field textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 10px 12px;
        background: #fff;
        color: #0f172a;
        outline: none;
    }

    .quote-field textarea {
        min-height: 86px;
        resize: vertical;
    }

    .quote-section-title {
        font-size: 18px;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 14px;
    }

    .quote-table {
        width: 100%;
        border-collapse: collapse;
        overflow: hidden;
        border-radius: 14px;
    }

    .quote-table th {
        background: #e0f2fe;
        color: #0f172a;
        font-size: 13px;
        text-align: left;
        padding: 10px;
        border: 1px solid #bae6fd;
    }

    .quote-table td {
        padding: 8px;
        border: 1px solid #e2e8f0;
        vertical-align: top;
    }

    .quote-table input,
    .quote-table textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 8px;
    }

    .btn-main {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 0;
        border-radius: 12px;
        padding: 10px 16px;
        font-weight: 800;
        cursor: pointer;
        background: linear-gradient(135deg, #14b8a6, #2563eb);
        color: #fff;
        text-decoration: none;
    }

    .btn-light {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #cbd5e1;
        border-radius: 12px;
        padding: 10px 16px;
        font-weight: 800;
        cursor: pointer;
        background: #fff;
        color: #0f172a;
        text-decoration: none;
    }

    .btn-danger {
        border: 0;
        border-radius: 10px;
        padding: 8px 10px;
        background: #fee2e2;
        color: #b91c1c;
        font-weight: 800;
        cursor: pointer;
    }

    .summary-box {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .summary-item {
        border-radius: 14px;
        padding: 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .summary-item div:first-child {
        color: #64748b;
        font-size: 13px;
        font-weight: 700;
    }

    .summary-item div:last-child {
        color: #0f172a;
        font-size: 22px;
        font-weight: 900;
        margin-top: 6px;
    }

    @media (max-width: 992px) {
        .quote-grid,
        .quote-grid-2,
        .summary-box {
            grid-template-columns: 1fr;
        }

        .quote-table {
            font-size: 13px;
        }
    }
</style>

<div class="quote-wrap">
    <div class="quote-card">
        <div style="display:flex; justify-content:space-between; gap:16px; align-items:flex-start;">
            <div>
                <h1 class="quote-title">Báo giá công trình</h1>
                <div class="quote-subtitle">
                    Công trình: <strong>{{ $site->name }}</strong>
                </div>
            </div>

            <a href="{{ route('sites.show', $site->id) }}" class="btn-light">← Quay lại công trình</a>
        </div>
    </div>

    @if(session('success'))
        <div class="quote-card" style="border-color:#86efac; background:#f0fdf4; color:#166534; font-weight:800;">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="quote-card" style="border-color:#fecaca; background:#fef2f2; color:#991b1b;">
            <strong>Có lỗi:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('sites.quote.update', $site->id) }}">
        @csrf
        @method('PUT')

        <div class="quote-card">
            <h2 class="quote-section-title">1. Thông tin báo giá</h2>

            <div class="quote-grid">
                <div class="quote-field">
                    <label>Số báo giá</label>
                    <input name="quote_no" value="{{ old('quote_no', $site->quote_no) }}" placeholder="BGCT-20260505-0001">
                </div>

                <div class="quote-field">
                    <label>Ngày báo giá</label>
                    <input type="date" name="quote_date" value="{{ old('quote_date', optional($site->quote_date)->format('Y-m-d') ?? date('Y-m-d')) }}">
                </div>

                <div class="quote-field">
                    <label>Hiệu lực đến</label>
                    <input type="date" name="quote_valid_until" value="{{ old('quote_valid_until', optional($site->quote_valid_until)->format('Y-m-d') ?? date('Y-m-d', strtotime('+15 days'))) }}">
                </div>

                <div class="quote-field">
                    <label>Trạng thái</label>
                    <select name="quote_status">
                        @foreach(['draft' => 'Nháp', 'sent' => 'Đã gửi khách', 'approved' => 'Khách duyệt', 'cancelled' => 'Huỷ'] as $key => $label)
                            <option value="{{ $key }}" @selected(old('quote_status', $site->quote_status ?? 'draft') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="quote-card">
            <h2 class="quote-section-title">2. Thông tin khách hàng</h2>

            <div class="quote-grid">
                <div class="quote-field">
                    <label>Tên khách hàng / Người liên hệ</label>
                    <input name="contact_name" value="{{ old('contact_name', $site->contact_name) }}" disabled>
                </div>

                <div class="quote-field">
                    <label>Số điện thoại</label>
                    <input name="contact_phone" value="{{ old('contact_phone', $site->contact_phone) }}" disabled>
                </div>

                <div class="quote-field">
                    <label>Email</label>
                    <input name="quote_customer_email" value="{{ old('quote_customer_email', $site->quote_customer_email) }}">
                </div>

                <div class="quote-field">
                    <label>Mã số thuế</label>
                    <input name="quote_customer_tax_code" value="{{ old('quote_customer_tax_code', $site->quote_customer_tax_code) }}">
                </div>
            </div>

            <div class="quote-grid-2" style="margin-top:14px;">
                <div class="quote-field">
                    <label>Tên công ty xuất hoá đơn</label>
                    <input name="quote_customer_company" value="{{ old('quote_customer_company', $site->quote_customer_company) }}">
                </div>

                <div class="quote-field">
                    <label>Địa chỉ công trình</label>
                    <input value="{{ $site->address }}" disabled>
                </div>
            </div>
        </div>

        <div class="quote-card">
            <h2 class="quote-section-title">3. Mô tả hệ thống / dự án</h2>

            <div class="quote-grid">
                <div class="quote-field">
                    <label>Công suất DC/kWp</label>
                    <input value="{{ $site->system_kwp }}" disabled>
                </div>

                <div class="quote-field">
                    <label>Công suất AC/kW</label>
                    <input value="{{ $site->system_kw_ac }}" disabled>
                </div>

                <div class="quote-field">
                    <label>Dung lượng lưu trữ/kWh</label>
                    <input value="{{ $site->battery_kwh }}" disabled>
                </div>

                <div class="quote-field">
                    <label>Loại hệ thống</label>
                    <input value="{{ $site->system_type }}" disabled>
                </div>
            </div>

            <div class="quote-grid-2" style="margin-top:14px;">
                <div class="quote-field">
                    <label>Cấu hình hệ thống</label>
                    <textarea name="quote_config_summary">{{ old('quote_config_summary', $site->quote_config_summary) }}</textarea>
                </div>

                <div class="quote-field">
                    <label>Ứng dụng / ghi chú kỹ thuật</label>
                    <textarea name="quote_application_note">{{ old('quote_application_note', $site->quote_application_note) }}</textarea>
                </div>
            </div>
        </div>

        <div class="quote-card">
            <h2 class="quote-section-title">4. Bảng vật tư / thiết bị báo giá</h2>

            <div style="overflow:auto;">
                <table class="quote-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:70px;">Mục</th>
                            <th style="min-width:220px;">Tên VT/HH</th>
                            <th style="width:120px;">Thương hiệu</th>
                            <th style="width:120px;">Model</th>
                            <th style="width:80px;">ĐVT</th>
                            <th style="width:90px;">SL</th>
                            <th style="width:130px;">Đơn giá</th>
                            <th style="width:140px;">Tổng</th>
                            <th style="min-width:180px;">Thông số / ghi chú</th>
                            <th style="width:70px;">Xoá</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($quoteItems as $i => $item)
                            <tr>
                                <td>
                                    <input name="items[{{ $i }}][section_code]" value="{{ $item->section_code }}">
                                </td>
                                <td>
                                    <input name="items[{{ $i }}][name]" value="{{ $item->name }}">
                                </td>
                                <td>
                                    <input name="items[{{ $i }}][brand]" value="{{ $item->brand }}">
                                </td>
                                <td>
                                    <input name="items[{{ $i }}][model]" value="{{ $item->model }}">
                                </td>
                                <td>
                                    <input name="items[{{ $i }}][unit]" value="{{ $item->unit }}">
                                </td>
                                <td>
                                    <input class="js-qty" name="items[{{ $i }}][qty_decimal]" value="{{ $item->qty_decimal ?? $item->qty }}">
                                </td>
                                <td>
                                    <input class="js-price" name="items[{{ $i }}][unit_price]" value="{{ $fmt($item->unit_price ?? 0) }}">
                                </td>
                                <td>
                                    <input class="js-total" value="{{ $fmt($item->line_total ?? 0) }}" readonly>
                                </td>
                                <td>
                                    <textarea name="items[{{ $i }}][specs_text]">{{ $item->specs_text }}</textarea>
                                </td>
                                <td>
                                    <button type="button" class="btn-danger" onclick="removeRow(this)">X</button>
                                </td>
                            </tr>
                        @empty
                            @php
                                $defaults = [
                                    ['I', 'Tấm Pin NLMT', 'AE SOLAR', '', 'Tấm', 22, 3000000],
                                    ['I', 'Inverter Hybrid 12kW 3 Pha', 'GoodWe', 'GW12000-ET-L-G10', 'Bộ', 1, 55000000],
                                    ['I', 'Pin lưu trữ Lithium GoodWe 5kWh', 'GoodWe', 'LX A5.0-10', 'Bộ', 4, 22000000],
                                    ['II', 'Vật tư / thiết bị lắp tấm pin', 'EGO', '', 'Hệ', 1, 12000000],
                                    ['III', 'Tủ điện AC + MCB + SPD + ATS', 'EGO', '', 'Cái', 1, 4500000],
                                    ['IV', 'Hệ thống tiếp địa', 'EGO', '', 'Hệ', 1, 2000000],
                                    ['V', 'Nhân công khảo sát, thiết kế, lắp đặt, setup hệ thống', 'EGO', '', 'kWp', 13, 1000000],
                                    ['V', 'Máy thi công + vận chuyển', 'EGO', '', 'Hệ', 1, 8000000],
                                ];
                            @endphp

                            @foreach($defaults as $i => $d)
                                <tr>
                                    <td><input name="items[{{ $i }}][section_code]" value="{{ $d[0] }}"></td>
                                    <td><input name="items[{{ $i }}][name]" value="{{ $d[1] }}"></td>
                                    <td><input name="items[{{ $i }}][brand]" value="{{ $d[2] }}"></td>
                                    <td><input name="items[{{ $i }}][model]" value="{{ $d[3] }}"></td>
                                    <td><input name="items[{{ $i }}][unit]" value="{{ $d[4] }}"></td>
                                    <td><input class="js-qty" name="items[{{ $i }}][qty_decimal]" value="{{ $d[5] }}"></td>
                                    <td><input class="js-price" name="items[{{ $i }}][unit_price]" value="{{ $fmt($d[6]) }}"></td>
                                    <td><input class="js-total" value="0" readonly></td>
                                    <td><textarea name="items[{{ $i }}][specs_text]"></textarea></td>
                                    <td><button type="button" class="btn-danger" onclick="removeRow(this)">X</button></td>
                                </tr>
                            @endforeach
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:14px;">
                <button type="button" class="btn-light" onclick="addRow()">+ Thêm dòng vật tư</button>
            </div>
        </div>

        <div class="quote-card">
            <h2 class="quote-section-title">5. Tổng tiền</h2>

            <div class="quote-grid">
                <div class="quote-field">
                    <label>Chiết khấu</label>
                    <input class="js-money" id="discount" name="quote_discount_amount" value="{{ old('quote_discount_amount', $fmt($site->quote_discount_amount ?? 0)) }}">
                </div>

                <div class="quote-field">
                    <label>VAT %</label>
                    <input id="vatPercent" name="quote_vat_percent" value="{{ old('quote_vat_percent', $site->quote_vat_percent ?? 0) }}">
                </div>
            </div>

            <div class="summary-box" style="margin-top:16px;">
                <div class="summary-item">
                    <div>Tạm tính</div>
                    <div id="subtotalView">0 đ</div>
                </div>

                <div class="summary-item">
                    <div>Chiết khấu</div>
                    <div id="discountView">0 đ</div>
                </div>

                <div class="summary-item">
                    <div>VAT</div>
                    <div id="vatView">0 đ</div>
                </div>

                <div class="summary-item" style="background:#ecfeff; border-color:#67e8f9;">
                    <div>Tổng thanh toán</div>
                    <div id="grandView">0 đ</div>
                </div>
            </div>
        </div>

        <div class="quote-card">
            <h2 class="quote-section-title">6. Điều kiện thanh toán</h2>

            <div class="quote-grid">
                @foreach($paymentTerms as $i => $term)
                    <div class="quote-field">
                        <label>Đợt {{ $i + 1 }}</label>
                        <input name="payment_terms[{{ $i }}][name]" value="{{ $term->name }}">
                        <input style="margin-top:8px;" name="payment_terms[{{ $i }}][percent]" value="{{ $term->percent }}" placeholder="% thanh toán">
                        <textarea style="margin-top:8px;" name="payment_terms[{{ $i }}][note]" placeholder="Ghi chú">{{ $term->note ?? '' }}</textarea>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="quote-card">
            <h2 class="quote-section-title">7. Điều kiện thương mại / bảo hành / O&M</h2>

            <div class="quote-grid-2">
                <div class="quote-field">
                    <label>Phạm vi công việc</label>
                    <textarea name="quote_scope">{{ old('quote_scope', $site->quote_scope ?? "Khảo sát, thiết kế kỹ thuật, cung cấp vật tư thiết bị, thi công lắp đặt, đo kiểm, cấu hình giám sát, nghiệm thu và bàn giao hệ thống.") }}</textarea>
                </div>

                <div class="quote-field">
                    <label>Điều kiện thương mại</label>
                    <textarea name="quote_commercial_terms">{{ old('quote_commercial_terms', $site->quote_commercial_terms ?? "1. Thời gian hoàn thành dự án: 2-4 ngày tuỳ điều kiện mặt bằng.\n2. Hàng hoá mới 100%, đúng chủng loại và thông số kỹ thuật.\n3. Báo giá trọn gói, không tính phát sinh trừ khi có thay đổi thiết kế hoặc tăng công suất lắp đặt.") }}</textarea>
                </div>

                <div class="quote-field">
                    <label>Bảo hành</label>
                    <textarea name="quote_warranty_terms">{{ old('quote_warranty_terms', $site->quote_warranty_terms ?? "Bảo hành toàn bộ công trình 24 tháng. Tấm pin, inverter, pin lưu trữ bảo hành theo chính sách của hãng sản xuất.") }}</textarea>
                </div>

                <div class="quote-field">
                    <label>Vận hành & bảo trì O&M</label>
                    <textarea name="quote_om_terms">{{ old('quote_om_terms', $site->quote_om_terms ?? "Miễn phí kiểm tra định kỳ 6 tháng/lần trong 24 tháng. Vệ sinh tấm pin tối đa 3 lần/năm. Theo dõi hệ thống qua phần mềm giám sát PV thông minh.") }}</textarea>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:12px; justify-content:flex-end; margin-bottom:40px;">
            <a href="{{ route('sites.show', $site->id) }}" class="btn-light">Huỷ</a>
            <button class="btn-main" type="submit">Lưu báo giá công trình</button>
        </div>
    </form>
</div>

<script>
    function parseMoney(value) {
        value = String(value || '').replace(/\./g, '').replace(/,/g, '.');
        const number = parseFloat(value);
        return isNaN(number) ? 0 : number;
    }

    function formatMoney(value) {
        return Math.round(value).toLocaleString('vi-VN') + ' đ';
    }

    function recalc() {
        let subtotal = 0;

        document.querySelectorAll('#itemsTable tbody tr').forEach(function(row) {
            const qty = parseMoney(row.querySelector('.js-qty')?.value || 0);
            const price = parseMoney(row.querySelector('.js-price')?.value || 0);
            const total = qty * price;

            const totalInput = row.querySelector('.js-total');
            if (totalInput) {
                totalInput.value = Math.round(total).toLocaleString('vi-VN');
            }

            subtotal += total;
        });

        const discount = parseMoney(document.getElementById('discount')?.value || 0);
        const vatPercent = parseMoney(document.getElementById('vatPercent')?.value || 0);
        const beforeVat = Math.max(subtotal - discount, 0);
        const vat = beforeVat * vatPercent / 100;
        const grand = beforeVat + vat;

        document.getElementById('subtotalView').innerText = formatMoney(subtotal);
        document.getElementById('discountView').innerText = formatMoney(discount);
        document.getElementById('vatView').innerText = formatMoney(vat);
        document.getElementById('grandView').innerText = formatMoney(grand);
    }

    function removeRow(button) {
        button.closest('tr').remove();
        recalc();
    }

    function addRow() {
        const tbody = document.querySelector('#itemsTable tbody');
        const index = tbody.querySelectorAll('tr').length;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input name="items[${index}][section_code]" value=""></td>
            <td><input name="items[${index}][name]" value=""></td>
            <td><input name="items[${index}][brand]" value=""></td>
            <td><input name="items[${index}][model]" value=""></td>
            <td><input name="items[${index}][unit]" value=""></td>
            <td><input class="js-qty" name="items[${index}][qty_decimal]" value="1"></td>
            <td><input class="js-price" name="items[${index}][unit_price]" value="0"></td>
            <td><input class="js-total" value="0" readonly></td>
            <td><textarea name="items[${index}][specs_text]"></textarea></td>
            <td><button type="button" class="btn-danger" onclick="removeRow(this)">X</button></td>
        `;

        tbody.appendChild(tr);
        bindCalc();
        recalc();
    }

    function bindCalc() {
        document.querySelectorAll('.js-qty, .js-price, #discount, #vatPercent').forEach(function(input) {
            input.removeEventListener('input', recalc);
            input.addEventListener('input', recalc);
        });
    }

    bindCalc();
    recalc();
</script>
@endsection
