@extends('layouts.app')

@section('content')
@php
    $panels = $panels ?? [];
    $inverters = $inverters ?? [];
    $batteries = $batteries ?? [];
@endphp

<div class="solar-page">
    <div class="solar-shell">
        <div class="hero card">
            <div>
                <div class="hero-badge">Solar Quick Quote</div>
                <h1>Công cụ tính nhanh điện mặt trời có lưu trữ</h1>
                <p>Nhập tiền điện hàng tháng, hệ thống tự quy đổi theo giá điện trung bình 3.000 VNĐ/kWh và ra full cấu hình: số tấm pin, inverter đúng pha, pin lưu trữ theo mức dùng điện, vật tư phụ, tủ điện, nhân công, giao hàng.</p>
            </div>
            <a href="{{ route('solar.settings') }}" class="top-link">Cài đặt công thức</a>
        </div>

        <div id="calc-toast" class="calc-toast"></div>

        <div class="main-grid">
            <div class="left-panel card">
                <div class="panel-title">Nhập nhanh</div>

                <div class="field big-field">
                    <label>Tiền điện hàng tháng (VNĐ)</label>
                    <input type="number" id="monthly_bill" placeholder="Ví dụ: 3000000" autofocus>
                    <small class="field-note">Mặc định quy đổi: 3.000 VNĐ/kWh.</small>
                </div>

                <div class="quick-row">
                    <button class="chip" type="button" onclick="setBill(2000000)">2 triệu</button>
                    <button class="chip" type="button" onclick="setBill(3000000)">3 triệu</button>
                    <button class="chip" type="button" onclick="setBill(5000000)">5 triệu</button>
                    <button class="chip" type="button" onclick="setBill(8000000)">8 triệu</button>
                </div>

                <div class="field two-col">
                    <div>
                        <label>Loại hệ thống</label>
                        <select id="system_type" onchange="toggleBatteryFields()">
                            <option value="hybrid" selected>Hybrid có pin</option>
                            <option value="on_grid">Bám tải on-grid</option>
                            <option value="battery">Lưu trữ mạnh</option>
                        </select>
                    </div>
                    <div>
                        <label>Pha inverter</label>
                        <select id="phase" onchange="filterInverterOptions()">
                            <option value="auto">Tự động</option>
                            <option value="1">1 pha</option>
                            <option value="3">3 pha</option>
                        </select>
                    </div>
                </div>

                <details class="advanced-box">
                    <summary>Tùy chọn nâng cao</summary>

                    <div class="field">
                        <label>Tỉnh / thành</label>
                        <select id="province_id">
                            <option value="">-- Không chọn --</option>
                            @foreach($provinces as $province)
                                <option value="{{ $province->id }}">
                                    {{ $province->name }} @if($province->region) - {{ $province->region }} @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field two-col">
                        <div>
                            <label>Thói quen dùng điện</label>
                            <select id="usage_type">
                                <option value="day" selected>Dùng ban ngày nhiều</option>
                                <option value="balanced">Dùng cân bằng</option>
                                <option value="night">Dùng ban đêm nhiều</option>
                            </select>
                        </div>
                        <div>
                            <label>Diện tích mái (m²)</label>
                            <input type="number" id="roof_area" placeholder="Ví dụ: 55">
                        </div>
                    </div>

                    <input type="hidden" id="electricity_mode" value="residential">

                    <div class="field two-col">
                        <div>
                            <label>Sản lượng kWh/tháng</label>
                            <input type="number" id="monthly_kwh" placeholder="Bỏ trống để tự tính">
                        </div>
                        <div>
                            <label>Tấm pin từ kho</label>
                            <select id="panel_product_id">
                                <option value="">Tự chọn 630Wp gần nhất</option>
                                @foreach($panels as $item)
                                    <option value="{{ $item['id'] }}">
                                        {{ number_format((float)($item['power_wp'] ?? 0), 0, ',', '.') }}Wp - {{ $item['display_name'] }} - {{ number_format((float)($item['price'] ?? 0), 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label>Inverter từ kho</label>
                        <select id="inverter_product_id">
                            <option value="">Tự chọn theo công suất</option>
                            @foreach($inverters as $item)
                                <option value="{{ $item['id'] }}" data-phase="{{ $item['phase'] ?? '' }}">
                                    {{ $item['phase_label'] ?? 'Chưa rõ pha' }} - {{ number_format((float)($item['capacity_kw'] ?? 0), 2, ',', '.') }}kW - {{ $item['display_name'] }} - {{ number_format((float)($item['price'] ?? 0), 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field battery-field">
                        <label>Pin lưu trữ từ kho</label>
                        <select id="battery_product_id">
                            <option value="">Tự chọn theo tiền điện/tồn kho</option>
                            @foreach($batteries as $item)
                                <option value="{{ $item['id'] }}">
                                    {{ number_format((float)($item['capacity_kwh'] ?? 0), 2, ',', '.') }}kWh - {{ $item['display_name'] }} - {{ number_format((float)($item['price'] ?? 0), 0, ',', '.') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </details>

                <div class="settings-box">
                    <div><span>Giá điện TB</span><strong>{{ number_format($defaults['electricity_price'], 0, ',', '.') }} VNĐ/kWh</strong></div>
                    <div><span>Tấm pin mặc định</span><strong>{{ number_format($defaults['panel_power_wp'], 0, ',', '.') }} Wp</strong></div>
                    <div><span>Sản lượng quy đổi</span><strong>{{ number_format($defaults['generation_per_kwp_year'], 0, ',', '.') }} kWh/kWp/năm</strong></div>
                    <div><span>Nhân công</span><strong>{{ number_format($defaults['labor_per_kwp'], 0, ',', '.') }} VNĐ/kWp</strong></div>
                </div>

                <div class="action-row">
                    <button class="btn-primary" type="button" id="btnCalc" onclick="calculateSolar()">Tính báo giá nhanh</button>
                    <button class="btn-secondary" type="button" onclick="resetForm()">Nhập lại</button>
                </div>
            </div>

            <div class="right-panel">
                <div class="summary-grid">
                    <div class="summary-card green">
                        <span>Gói đề xuất</span>
                        <strong id="recommended_kwp">0.00 kWp</strong>
                        <small id="configuration_text">-</small>
                    </div>
                    <div class="summary-card blue">
                        <span>Tiền điện / Điện dùng</span>
                        <strong id="monthly_bill_result">0 VNĐ</strong>
                        <small id="monthly_kwh_result">0 kWh/tháng</small>
                    </div>
                    <div class="summary-card orange">
                        <span>Tổng đầu tư</span>
                        <strong id="total_investment">0 VNĐ</strong>
                        <small id="payback_year">-</small>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-card"><span>Số lượng tấm pin</span><strong id="panel_count">0</strong></div>
                    <div class="info-card"><span>Inverter</span><strong id="inverter_text">-</strong></div>
                    <div class="info-card"><span>Tỉ lệ DC/AC</span><strong id="dc_ac_ratio">-</strong></div>
                    <div class="info-card"><span>Pin lưu trữ</span><strong id="battery_text">-</strong></div>
                    <div class="info-card"><span>Diện tích cần</span><strong id="estimated_area">0 m²</strong></div>
                    <div class="info-card"><span>Vật tư phụ</span><strong id="material_cost">0 VNĐ</strong></div>
                    <div class="info-card"><span>Tủ điện</span><strong id="electric_cabinet_cost">0 VNĐ</strong></div>
                    <div class="info-card"><span>Nhân công</span><strong id="labor_cost">0 VNĐ</strong></div>
                    <div class="info-card"><span>Giao hàng</span><strong id="shipping_cost">0 VNĐ</strong></div>
                </div>

                <div class="card quote-card">
                    <div class="section-head">
                        <div>
                            <div class="section-title">Dự toán vật tư cơ bản</div>
                            <div class="section-subtitle">Không show quá nhiều thông số kỹ thuật, chỉ đủ để sales báo nhanh.</div>
                        </div>
                        <div class="mini-total" id="mini_total">0 VNĐ</div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Hạng mục</th>
                                    <th>SL</th>
                                    <th>Đơn giá</th>
                                    <th>Thành tiền</th>
                                    <th>Ghi chú</th>
                                </tr>
                            </thead>
                            <tbody id="quote_items_body">
                                <tr><td colspan="5" class="empty-cell">Nhập tiền điện để tính báo giá.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card compare-card">
                    <div class="section-title">So sánh 3 gói gần nhất</div>
                    <div class="compare-grid" id="comparison_plans">
                        <div class="compare-item empty">Chưa có dữ liệu</div>
                    </div>
                </div>

                <div class="card advice-card">
                    <div class="section-title">Gợi ý tư vấn nhanh</div>
                    <ul id="advice_list">
                        <li>Nhập tiền điện hàng tháng để hệ thống tự đề xuất cấu hình.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.solar-page{padding:16px 18px;background:#f6f8fb;min-height:calc(100vh - 80px)}
.solar-shell{max-width:1280px;margin:0 auto}
.card,.summary-card,.info-card{background:#fff;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.06);border:1px solid #e2e8f0}
.hero{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;padding:18px;margin-bottom:14px}
.hero-badge{display:inline-block;background:#ecfeff;color:#0f766e;padding:5px 10px;border-radius:999px;font-size:11px;font-weight:800;margin-bottom:8px}
.hero h1{margin:0 0 6px;font-size:28px;line-height:1.15;color:#0f172a}
.hero p{margin:0;max-width:880px;color:#64748b;line-height:1.55;font-size:14px}
.top-link{text-decoration:none;background:#0f172a;color:#fff;padding:10px 14px;border-radius:12px;font-weight:700;font-size:13px;white-space:nowrap}
.main-grid{display:grid;grid-template-columns:360px 1fr;gap:14px}
.left-panel{padding:16px;height:max-content}
.panel-title,.section-title{font-size:16px;font-weight:800;color:#0f172a;margin-bottom:10px}
.section-subtitle{font-size:12px;color:#64748b;margin-top:-4px}
.field{margin-bottom:12px}
.field label{display:block;font-weight:750;font-size:13px;margin-bottom:6px;color:#334155}
.field input,.field select{width:100%;height:43px;border:1px solid #cbd5e1;border-radius:12px;padding:0 12px;font-size:14px;background:#fff;outline:none}
.field input:focus,.field select:focus{border-color:#38bdf8;box-shadow:0 0 0 3px rgba(56,189,248,.15)}
.big-field input{height:52px;font-size:18px;font-weight:800;color:#0f172a}
.field-note{display:block;margin-top:5px;color:#64748b;font-size:11px;line-height:1.35}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.quick-row{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0 14px}
.chip{border:none;background:#e0f2fe;color:#075985;border-radius:999px;padding:8px 12px;font-weight:800;cursor:pointer;font-size:12px}
.advanced-box{margin:6px 0 12px;padding:11px;border:1px dashed #cbd5e1;border-radius:14px;background:#f8fafc}
.advanced-box summary{cursor:pointer;font-weight:800;color:#0f172a;font-size:13px}
.advanced-box[open] summary{margin-bottom:12px}
.settings-box{padding:12px;background:#f8fafc;border-radius:14px;border:1px solid #e2e8f0;margin-top:12px}
.settings-box div{display:flex;justify-content:space-between;gap:8px;padding:4px 0;font-size:12px}
.settings-box span{color:#64748b}.settings-box strong{color:#0f172a;text-align:right}
.action-row{display:flex;gap:10px;margin-top:14px}
.btn-primary,.btn-secondary{border:none;border-radius:12px;padding:11px 14px;font-weight:800;cursor:pointer;font-size:13px}
.btn-primary{background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;flex:1}
.btn-primary:disabled{opacity:.65;cursor:not-allowed}
.btn-secondary{background:#e2e8f0;color:#0f172a}
.summary-grid{display:grid;grid-template-columns:1.2fr 1fr 1.1fr;gap:12px;margin-bottom:12px}
.summary-card{padding:16px;color:#fff;min-height:112px}
.summary-card span{display:block;font-size:12px;opacity:.95;margin-bottom:7px;font-weight:700}
.summary-card strong{display:block;font-size:23px;line-height:1.18}
.summary-card small{display:block;margin-top:7px;font-size:12px;opacity:.95;line-height:1.35}
.summary-card.green{background:linear-gradient(135deg,#16a34a,#22c55e)}
.summary-card.blue{background:linear-gradient(135deg,#2563eb,#3b82f6)}
.summary-card.orange{background:linear-gradient(135deg,#ea580c,#fb923c)}
.info-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.info-card{padding:13px;min-height:82px}
.info-card span{display:block;color:#64748b;font-size:12px;margin-bottom:6px;font-weight:700}
.info-card strong{display:block;font-size:15px;color:#0f172a;line-height:1.35;word-break:break-word}
.quote-card,.compare-card,.advice-card{padding:15px;margin-top:12px}
.section-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:10px}
.mini-total{font-weight:900;color:#ea580c;background:#fff7ed;border:1px solid #fed7aa;border-radius:999px;padding:8px 12px;white-space:nowrap}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse}
thead th{background:#f8fafc;color:#334155;font-size:12px;text-align:left;padding:10px;border-bottom:1px solid #e2e8f0;white-space:nowrap}
tbody td{padding:10px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#334155;vertical-align:top}
tbody td.money{text-align:right;font-weight:800;color:#0f172a;white-space:nowrap}
tbody td.qty{white-space:nowrap;font-weight:700;color:#0f172a}
.empty-cell{text-align:center;color:#94a3b8}
.compare-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.compare-item{padding:14px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0}
.compare-item .title{font-size:13px;color:#64748b;margin-bottom:8px;font-weight:800}
.compare-item .big{font-size:22px;font-weight:900;color:#0f172a;margin-bottom:8px}
.compare-item .row{display:flex;justify-content:space-between;gap:8px;font-size:12px;padding:4px 0;color:#334155}
.compare-item .row strong{text-align:right;color:#0f172a}
.compare-item.empty{grid-column:1/-1;text-align:center;color:#94a3b8}
.advice-card ul{margin:0;padding-left:18px;line-height:1.65;color:#334155;font-size:13px}
.calc-toast{display:none;margin-bottom:12px;padding:11px 13px;border-radius:12px;font-weight:800;font-size:13px}
.calc-toast.show{display:block}
.calc-toast.success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}
.calc-toast.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
@media(max-width:1200px){.main-grid{grid-template-columns:1fr}.info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.summary-grid,.compare-grid,.info-grid,.two-col{grid-template-columns:1fr}.hero h1{font-size:23px}}
</style>

<script>
function formatMoney(value) {
    return Number(value || 0).toLocaleString('vi-VN') + ' VNĐ';
}

function formatNum(value, digits = 2) {
    return Number(value || 0).toLocaleString('vi-VN', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits
    });
}

function shortName(value, limit = 72) {
    value = value || '-';
    return value.length > limit ? value.substring(0, limit) + '...' : value;
}

function showToast(message, type = 'success') {
    const el = document.getElementById('calc-toast');
    el.className = 'calc-toast show ' + type;
    el.innerText = message;
    setTimeout(() => el.className = 'calc-toast', 3000);
}

function setBill(value) {
    document.getElementById('monthly_bill').value = value;
    document.getElementById('monthly_kwh').value = '';
    calculateSolar();
}

function toggleBatteryFields() {
    const systemType = document.getElementById('system_type').value;
    const disabled = systemType === 'on_grid';
    document.querySelectorAll('.battery-field select').forEach(el => {
        el.disabled = disabled;
        if (disabled) el.value = '';
    });
}

function filterInverterOptions() {
    const phase = document.getElementById('phase').value;
    const select = document.getElementById('inverter_product_id');
    Array.from(select.options).forEach(option => {
        if (!option.value) {
            option.hidden = false;
            return;
        }
        const optionPhase = option.getAttribute('data-phase') || '';
        option.hidden = phase !== 'auto' && optionPhase !== phase;
    });
    const current = select.options[select.selectedIndex];
    if (current && current.hidden) select.value = '';
}

function resetForm() {
    ['monthly_bill','monthly_kwh','roof_area'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('province_id').value = '';
    document.getElementById('usage_type').value = 'day';
    document.getElementById('system_type').value = 'hybrid';
    document.getElementById('phase').value = 'auto';
    document.getElementById('panel_product_id').value = '';
    document.getElementById('inverter_product_id').value = '';
    document.getElementById('battery_product_id').value = '';

    document.getElementById('recommended_kwp').innerText = '0.00 kWp';
    document.getElementById('configuration_text').innerText = '-';
    document.getElementById('monthly_bill_result').innerText = '0 VNĐ';
    document.getElementById('monthly_kwh_result').innerText = '0 kWh/tháng';
    document.getElementById('total_investment').innerText = '0 VNĐ';
    document.getElementById('payback_year').innerText = '-';
    document.getElementById('panel_count').innerText = '0';
    document.getElementById('inverter_text').innerText = '-';
    document.getElementById('dc_ac_ratio').innerText = '-';
    document.getElementById('battery_text').innerText = '-';
    document.getElementById('estimated_area').innerText = '0 m²';
    document.getElementById('material_cost').innerText = '0 VNĐ';
    document.getElementById('electric_cabinet_cost').innerText = '0 VNĐ';
    document.getElementById('labor_cost').innerText = '0 VNĐ';
    document.getElementById('shipping_cost').innerText = '0 VNĐ';
    document.getElementById('mini_total').innerText = '0 VNĐ';
    document.getElementById('quote_items_body').innerHTML = '<tr><td colspan="5" class="empty-cell">Nhập tiền điện để tính báo giá.</td></tr>';
    document.getElementById('comparison_plans').innerHTML = '<div class="compare-item empty">Chưa có dữ liệu</div>';
    document.getElementById('advice_list').innerHTML = '<li>Nhập tiền điện hàng tháng để hệ thống tự đề xuất cấu hình.</li>';

    filterInverterOptions();
    toggleBatteryFields();
}

async function calculateSolar() {
    const btn = document.getElementById('btnCalc');
    btn.disabled = true;
    btn.innerText = 'Đang tính...';

    try {
        const response = await fetch("{{ route('solar.calculator.calculate') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            body: JSON.stringify({
                province_id: document.getElementById('province_id').value,
                electricity_mode: document.getElementById('electricity_mode').value,
                usage_type: document.getElementById('usage_type').value,
                system_type: document.getElementById('system_type').value,
                phase: document.getElementById('phase').value,
                panel_product_id: document.getElementById('panel_product_id').value,
                inverter_product_id: document.getElementById('inverter_product_id').value,
                battery_product_id: document.getElementById('battery_product_id').value,
                monthly_bill: document.getElementById('monthly_bill').value,
                monthly_kwh: document.getElementById('monthly_kwh').value,
                roof_area: document.getElementById('roof_area').value
            })
        });

        const result = await response.json();
        if (!response.ok || !result.success) throw result;

        const d = result.data;
        document.getElementById('recommended_kwp').innerText = formatNum(d.recommended_kwp) + ' kWp';
        document.getElementById('configuration_text').innerText = d.configuration_text;
        document.getElementById('monthly_bill_result').innerText = formatMoney(d.monthly_bill);
        document.getElementById('monthly_kwh_result').innerText = formatNum(d.monthly_kwh, 0) + ' kWh/tháng';
        document.getElementById('total_investment').innerText = formatMoney(d.total_investment);
        document.getElementById('mini_total').innerText = formatMoney(d.total_investment);
        document.getElementById('payback_year').innerText = d.payback_year ? ('Hoàn vốn khoảng năm ' + d.payback_year) : 'Dùng để báo nhanh';

        document.getElementById('panel_count').innerText = Number(d.estimated_panel_count || 0).toLocaleString('vi-VN') + ' tấm x ' + Number(d.panel_power_wp || 0).toLocaleString('vi-VN') + 'Wp';
        const inverterPrefix = Number(d.inverter_qty || 1) > 1 ? (Number(d.inverter_qty).toLocaleString('vi-VN') + ' x ') : '';
        document.getElementById('inverter_text').innerText = inverterPrefix + shortName(d.inverter_product_name, 80);
        document.getElementById('dc_ac_ratio').innerText = d.dc_ac_ratio ? ('1 : ' + formatNum(d.dc_ac_ratio, 2)) : '-';
        const batteryPrefix = Number(d.battery_qty || 0) > 1 ? (Number(d.battery_qty).toLocaleString('vi-VN') + ' x ') : '';
        document.getElementById('battery_text').innerText = d.system_type === 'on_grid' ? 'Không dùng pin' : (batteryPrefix + formatNum(d.battery_capacity, 1) + ' kWh - ' + shortName(d.battery_product_name, 48));
        document.getElementById('estimated_area').innerText = formatNum(d.estimated_area, 1) + ' m²';
        document.getElementById('material_cost').innerText = formatMoney(d.material_cost);
        document.getElementById('electric_cabinet_cost').innerText = formatMoney(d.electric_cabinet_cost);
        document.getElementById('labor_cost').innerText = formatMoney(d.labor_cost);
        document.getElementById('shipping_cost').innerText = d.shipping_cost > 0 ? formatMoney(d.shipping_cost) : 'Đã gồm';

        renderQuoteItems(d.quote_items);
        renderComparison(d.comparison_plans);
        renderAdvice(d.advice);

        showToast('Đã ra full dự toán theo tiền điện và giá kho/báo giá mẫu.');
    } catch (error) {
        if (error.errors) {
            const firstError = Object.values(error.errors)[0][0];
            showToast(firstError, 'error');
        } else {
            showToast(error.message || 'Có lỗi khi tính báo giá.', 'error');
        }
    } finally {
        btn.disabled = false;
        btn.innerText = 'Tính báo giá nhanh';
    }
}

function renderQuoteItems(items) {
    const body = document.getElementById('quote_items_body');
    if (!items || !items.length) {
        body.innerHTML = '<tr><td colspan="5" class="empty-cell">Chưa có dữ liệu</td></tr>';
        return;
    }
    body.innerHTML = items.map(item => `
        <tr>
            <td><strong>${item.name}</strong></td>
            <td class="qty">${Number(item.qty || 0).toLocaleString('vi-VN')} ${item.unit || ''}</td>
            <td class="money">${formatMoney(item.unit_price)}</td>
            <td class="money">${formatMoney(item.total)}</td>
            <td>${item.note || ''}</td>
        </tr>
    `).join('');
}

function renderComparison(plans) {
    const container = document.getElementById('comparison_plans');
    if (!plans || !plans.length) {
        container.innerHTML = '<div class="compare-item empty">Chưa có dữ liệu</div>';
        return;
    }
    container.innerHTML = plans.map(plan => `
        <div class="compare-item">
            <div class="title">${Number(plan.panel_count || 0).toLocaleString('vi-VN')} tấm pin</div>
            <div class="big">${formatNum(plan.kwp)} kWp</div>
            <div class="row"><span>Inverter</span><strong>${shortName(plan.inverter_name, 34)}</strong></div>
            <div class="row"><span>Pin</span><strong>${formatNum(plan.battery_capacity, 1)} kWh</strong></div>
            <div class="row"><span>Sản lượng/năm</span><strong>${formatNum(plan.yearly_generation, 0)} kWh</strong></div>
            <div class="row"><span>Tiết kiệm năm 1</span><strong>${formatMoney(plan.year1_saving)}</strong></div>
            <div class="row"><span>Tổng đầu tư</span><strong>${formatMoney(plan.total_investment)}</strong></div>
        </div>
    `).join('');
}

function renderAdvice(advice) {
    const ul = document.getElementById('advice_list');
    ul.innerHTML = (advice || []).map(item => `<li>${item}</li>`).join('');
}

document.addEventListener('DOMContentLoaded', function () {
    filterInverterOptions();
    toggleBatteryFields();
});
</script>
@endsection
