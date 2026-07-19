@extends('layouts.app')

@section('content')
@php
    $isEdit = $mode === 'edit';

    $sections = [
        'main' => [
            'code' => 'I',
            'title' => 'Vật tư / thiết bị chính',
            'hint' => 'Tấm pin, inverter, pin lưu trữ, thiết bị chính.',
            'color' => '#2563eb',
        ],
        'sub' => [
            'code' => 'II',
            'title' => 'Vật tư / thiết bị phụ',
            'hint' => 'Dây AC/DC, rail, kẹp, MC4, ống điện, vật tư phụ.',
            'color' => '#0891b2',
        ],
        'ac_cabinet' => [
            'code' => 'III',
            'title' => 'Tủ AC',
            'hint' => 'Tủ điện AC, MCB, chống sét SPD, ATS, dây và phụ kiện.',
            'color' => '#7c3aed',
        ],
        'grounding' => [
            'code' => 'IV',
            'title' => 'Hệ thống tiếp địa',
            'hint' => 'Cọc tiếp địa, dây tiếp địa, phụ kiện tiếp địa.',
            'color' => '#16a34a',
        ],
        'other' => [
            'code' => 'V',
            'title' => 'Các hạng mục khác',
            'hint' => 'Nhân công, khảo sát, vận chuyển, máy thi công, chi phí khác.',
            'color' => '#ea580c',
        ],
        'om' => [
            'code' => 'VI',
            'title' => 'Bảo hành & O&M',
            'hint' => 'Bảo hành, vận hành, bảo trì, vệ sinh tấm pin.',
            'color' => '#0f766e',
        ],
    ];

    $groupedItems = collect($items)->groupBy(function ($item) {
        return $item->section_key ?: 'main';
    });

    $fmt = fn($n) => number_format((float) $n, 0, ',', '.');
@endphp

<style>
    :root {
        --bg: #f4f7fb;
        --card: #ffffff;
        --text: #0f172a;
        --muted: #64748b;
        --line: #dbe5f0;
        --blue: #2563eb;
        --red: #dc2626;
    }

    body {
        background: var(--bg);
    }

    .q-page {
        max-width: 1180px;
        margin: 0 auto;
        padding: 18px 22px 96px;
    }

    .q-top {
        background: linear-gradient(135deg, #05324d, #075985 48%, #0f766e);
        border-radius: 20px;
        padding: 18px 20px;
        color: #fff;
        margin-bottom: 14px;
        box-shadow: 0 14px 34px rgba(15, 23, 42, .16);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
    }

    .q-top h1 {
        margin: 0;
        font-size: 25px;
        line-height: 1.15;
        font-weight: 950;
    }

    .q-top p {
        margin: 5px 0 0;
        font-size: 13px;
        color: rgba(255,255,255,.82);
    }

    .q-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .btnx {
        border: 0;
        border-radius: 12px;
        padding: 10px 13px;
        font-size: 13px;
        font-weight: 900;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        white-space: nowrap;
    }

    .btn-white {
        background: #fff;
        color: var(--text);
    }

    .btn-primary {
        background: linear-gradient(135deg, #14b8a6, #2563eb);
        color: #fff;
    }

    .btn-soft {
        background: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
    }

    .btn-danger {
        background: #fee2e2;
        color: #b91c1c;
        padding: 8px 10px;
    }

    .cardx {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 18px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .05);
        margin-bottom: 14px;
        overflow: hidden;
    }

    .card-head {
        padding: 13px 16px;
        border-bottom: 1px solid var(--line);
        background: linear-gradient(180deg, #fff, #f8fafc);
    }

    .card-head h2 {
        margin: 0;
        color: var(--text);
        font-size: 17px;
        font-weight: 950;
    }

    .card-head p {
        margin: 3px 0 0;
        color: var(--muted);
        font-size: 12px;
    }

    .card-body {
        padding: 14px 16px;
    }

    .grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .grid-4 {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }

    .field {
        margin-bottom: 10px;
    }

    .field label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 5px;
        color: #334155;
        font-size: 12px;
        font-weight: 900;
    }

    .field label span {
        color: var(--red);
    }

    .field input,
    .field select,
    .field textarea {
        width: 100%;
        border: 1px solid #cbd5e1;
        border-radius: 11px;
        padding: 9px 10px;
        outline: none;
        background: #fff;
        color: var(--text);
        font-size: 13px;
    }

    .field textarea {
        min-height: 68px;
        resize: vertical;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
        border-color: #38bdf8;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, .12);
    }

    .section-card {
        background: #fff;
        border: 1px solid var(--line);
        border-radius: 16px;
        margin-bottom: 12px;
        overflow: hidden;
    }

    .section-head {
        display: block;
        padding: 13px 14px 0;
        background: #f8fafc;
        border-bottom: 1px solid var(--line);
    }

    .section-title {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        padding-bottom: 12px;
    }

    .badge-code {
        min-width: 36px;
        height: 36px;
        border-radius: 12px;
        color: #fff;
        display: grid;
        place-items: center;
        font-weight: 950;
        font-size: 14px;
    }

    .section-title b {
        display: block;
        font-size: 15px;
        color: var(--text);
    }

    .section-title small {
        display: block;
        margin-top: 2px;
        color: var(--muted);
        font-size: 12px;
    }

    .section-tools {
        width: 100%;
        display: grid;
        grid-template-columns: minmax(320px, 1fr) 110px 130px;
        gap: 8px;
        align-items: end;
        padding: 11px 0 12px;
        border-top: 1px dashed #d6e2ef;
    }

    .section-tools select {
        width: 100%;
        height: 38px;
        border: 1px solid #cbd5e1;
        border-radius: 11px;
        padding: 9px 10px;
        font-size: 13px;
        background: #fff;
    }

    .section-tools .btnx {
        height: 38px;
        padding: 8px 10px;
        font-size: 12px;
    }

    .table-wrap {
        overflow: auto;
    }

    .q-table {
        width: 100%;
        min-width: 940px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .q-table th {
        background: #eff6ff;
        color: #1e3a8a;
        text-align: left;
        padding: 9px 8px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
        border-bottom: 1px solid #bfdbfe;
        white-space: nowrap;
    }

    .q-table td {
        padding: 8px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: top;
    }

    .q-table input,
    .q-table textarea {
        width: 100%;
        border: 1px solid #dbe3ef;
        border-radius: 10px;
        padding: 8px;
        font-size: 13px;
        outline: none;
    }

    .q-table textarea {
        min-height: 39px;
        resize: vertical;
    }

    .money-input {
        text-align: right;
        font-weight: 800;
    }

    .js-price {
        background: #f8fafc;
        color: #334155;
        font-weight: 900;
    }

    .js-qty {
        text-align: center;
        font-weight: 900;
    }

    .line-total {
        background: #fff !important;
        border-color: #38bdf8 !important;
        text-align: right;
        font-weight: 950;
        color: #075985;
    }

    .empty-row td {
        padding: 15px;
        text-align: center;
        color: var(--muted);
        background: #fbfdff;
        font-size: 13px;
    }

    .terms-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }

    .terms-grid textarea {
        min-height: 105px;
    }

    .bottom-total {
        position: fixed;
        left: 274px;
        right: 20px;
        bottom: 14px;
        z-index: 50;
        background: rgba(255,255,255,.95);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(203,213,225,.9);
        border-radius: 18px;
        box-shadow: 0 18px 44px rgba(15,23,42,.18);
        padding: 12px;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: center;
    }

    .total-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(120px, 1fr));
        gap: 8px;
    }

    .total-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 13px;
        padding: 9px 11px;
    }

    .total-box small {
        display: block;
        color: var(--muted);
        font-size: 11px;
        font-weight: 900;
        margin-bottom: 3px;
    }

    .total-box b {
        color: var(--text);
        font-size: 16px;
        font-weight: 950;
    }

    .total-main {
        background: linear-gradient(135deg, #ecfeff, #eff6ff);
        border-color: #7dd3fc;
    }

    .total-main b {
        color: #075985;
        font-size: 22px;
    }

    .bottom-actions {
        display: flex;
        gap: 8px;
    }

    .error-box {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
        padding: 12px 14px;
        border-radius: 14px;
        margin-bottom: 12px;
        font-size: 13px;
    }

    @media (max-width: 1200px) {
        .q-page {
            padding-bottom: 170px;
        }

        .grid-2,
        .grid-4,
        .terms-grid {
            grid-template-columns: 1fr;
        }

        .section-tools {
            grid-template-columns: 1fr;
        }

        .section-tools .btnx {
            width: 100%;
        }

        .bottom-total {
            left: 12px;
            right: 12px;
            grid-template-columns: 1fr;
        }

        .total-grid {
            grid-template-columns: 1fr;
        }

        .bottom-actions .btnx {
            flex: 1;
        }
    }

    .js-price {
        background: #fff !important;
        border-color: #38bdf8 !important;
        color: #075985 !important;
        font-weight: 950 !important;
    }

    .line-total {
        background: #f8fafc !important;
        border-color: #dbe3ef !important;
        color: #0f172a !important;
        cursor: not-allowed !important;
    }

</style>

<div class="q-page">
    <div class="q-top">
        <div>
            <h1>{{ $isEdit ? 'Sửa báo giá' : 'Tạo báo giá gửi khách' }}</h1>
            <p>Form nhỏ, chia nhóm vật tư rõ ràng, nhập thành tiền gửi khách.</p>
        </div>

        <div class="q-actions">
            <a class="btnx btn-white" href="{{ route('sales-quotations.index') }}">← Danh sách</a>

            @if($isEdit)
                <a class="btnx btn-white" href="{{ route('sales-quotations.pdf', $quotation) }}">PDF</a>
                <a class="btnx btn-white" href="{{ route('sales-quotations.excel', $quotation) }}">Excel</a>
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="error-box">
            <b>Vui lòng kiểm tra lại:</b>
            <ul style="margin-bottom:0;">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('sales-quotations.update', $quotation) : route('sales-quotations.store') }}">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="cardx">
            <div class="card-head">
                <h2>1. Thông tin chung</h2>
                <p>Thông tin khách hàng, dự án và ngày báo giá.</p>
            </div>

            <div class="card-body">
                <div class="grid-4">
                    <div class="field">
                        <label>Chọn khách hàng</label>
                        <select id="customerSelect" name="customer_id">
                            <option value="">-- Khách mới / tự nhập --</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected(old('customer_id', $quotation->customer_id) == $c->id)>
                                    {{ $c->name ?? $c->customer_name ?? 'Không tên' }} - {{ $c->phone ?? $c->customer_phone ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Tên khách hàng <span>*</span></label>
                        <input name="customer_name" id="customer_name" value="{{ old('customer_name', $quotation->customer_name) }}" placeholder="Tên khách">
                    </div>

                    <div class="field">
                        <label>Số điện thoại</label>
                        <input name="customer_phone" id="customer_phone" value="{{ old('customer_phone', $quotation->customer_phone) }}">
                    </div>

                    <div class="field">
                        <label>Email</label>
                        <input name="customer_email" id="customer_email" value="{{ old('customer_email', $quotation->customer_email) }}">
                    </div>
                </div>

                <div class="grid-4">
                    <div class="field">
                        <label>Ngày báo giá</label>
                        <input type="date" name="quote_date" value="{{ old('quote_date', optional($quotation->quote_date)->format('Y-m-d') ?: date('Y-m-d')) }}">
                    </div>

                    <div class="field">
                        <label>Hiệu lực đến</label>
                        <input type="date" name="valid_until" value="{{ old('valid_until', optional($quotation->valid_until)->format('Y-m-d') ?: date('Y-m-d', strtotime('+15 days'))) }}">
                    </div>

                    <div class="field">
                        <label>Trạng thái</label>
                        <select name="status">
                            @foreach(['draft'=>'Nháp','sent'=>'Đã gửi','approved'=>'Khách duyệt','cancelled'=>'Huỷ'] as $k=>$v)
                                <option value="{{ $k }}" @selected(old('status', $quotation->status ?: 'draft') === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Mã số thuế</label>
                        <input name="billing_tax_code" id="billing_tax_code" value="{{ old('billing_tax_code', $quotation->billing_tax_code) }}">
                    </div>
                </div>

                <div class="grid-2">
                    <div class="field">
                        <label>Tên dự án / công trình</label>
                        <input name="project_name" value="{{ old('project_name', $quotation->project_name) }}" placeholder="Ví dụ: Hệ điện mặt trời 15kWp">
                    </div>

                    <div class="field">
                        <label>Địa chỉ dự án</label>
                        <input name="project_address" value="{{ old('project_address', $quotation->project_address) }}" placeholder="Địa chỉ lắp đặt">
                    </div>
                </div>

                <div class="grid-4">
                    <div class="field">
                        <label>Loại hệ thống</label>
                        <select name="system_type">
                            @foreach(['on_grid'=>'Hoà lưới','hybrid'=>'Hoà lưới lưu trữ','off_grid'=>'Độc lập','bess_ci'=>'BESS C&I'] as $k=>$v)
                                <option value="{{ $k }}" @selected(old('system_type', $quotation->system_type) === $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Công suất hệ thống (kWp)</label>
                        <input type="number" step="0.01" name="system_kwp" value="{{ old('system_kwp', $quotation->system_kwp) }}">
                    </div>

                    


                    <div class="field">
                        <label>kWh lưu trữ</label>
                        <input type="number" step="0.01" name="battery_kwh" value="{{ old('battery_kwh', $quotation->battery_kwh) }}">
                    </div>
                </div>

                <div class="field">
                    <label>Cấu hình / mô tả hệ thống</label>
                    <textarea name="config_summary" placeholder="Ví dụ: 22 tấm pin AE Solar 630Wp + 01 inverter GoodWe 12kW + 04 pin lưu trữ 5kWh">{{ old('config_summary', $quotation->config_summary) }}</textarea>
                </div>

                <input type="hidden" name="system_kw_ac" value="0">
                <input type="hidden" name="customer_address" id="customer_address" value="{{ old('customer_address', $quotation->customer_address) }}">
                <input type="hidden" name="billing_company_name" id="billing_company_name" value="{{ old('billing_company_name', $quotation->billing_company_name) }}">
                <input type="hidden" name="billing_address" id="billing_address" value="{{ old('billing_address', $quotation->billing_address) }}">
            </div>
        </div>

        <div class="cardx">
            <div class="card-head">
                <h2>2. Hạng mục báo giá</h2>
                <p>Cột VAT đã ẩn, hệ thống mặc định VAT = 0.</p>
            </div>

            <div class="card-body">
                @foreach($sections as $key => $section)
                    @php
                        $sectionItems = $groupedItems->get($key, collect());
                    @endphp

                    <div class="section-card" data-section-card="{{ $key }}">
                        <div class="section-head">
                            <div class="section-title">
                                <div class="badge-code" style="background: {{ $section['color'] }};">{{ $section['code'] }}</div>
                                <div>
                                    <b>{{ $section['title'] }}</b>
                                    <small>{{ $section['hint'] }}</small>
                                </div>
                            </div>

                            <div class="section-tools">
                                <select class="section-product-select" data-section="{{ $key }}">
                                    <option value="">-- Chọn sản phẩm --</option>
                                    @foreach($products as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} - {{ $p->sku }}</option>
                                    @endforeach
                                </select>

                                <button type="button" class="btnx btn-soft" onclick="addSelectedProduct('{{ $key }}')">+ Thêm SP</button>
                                <button type="button" class="btnx btn-primary" onclick="addRow('{{ $key }}')">+ Dòng tự nhập</button>
                            </div>
                        </div>

                        <div class="table-wrap">
                            <table class="q-table">
                                <thead>
                                    <tr>
                                        <th style="width:44px;">#</th>
                                        <th style="width:250px;">Tên gửi khách</th>
                                        <th style="width:110px;">SKU</th>
                                        <th style="width:78px;">ĐVT</th>
                                        <th style="width:125px;">Đơn giá</th>
                                        <th style="width:90px;">Số lượng</th>
                                        <th style="width:145px;">Thành tiền</th>
                                        <th style="width:260px;">Thông số / ghi chú</th>
                                        <th style="width:54px;"></th>
                                    </tr>
                                </thead>

                                <tbody data-section-body="{{ $key }}">
                                    @forelse($sectionItems as $item)
                                        @php
                                            $rowKey = $key . '_' . $loop->index;
                                        @endphp
                                        <tr>
                                            <td class="row-number"></td>

                                            <td>
                                                <input type="hidden" name="items[{{ $rowKey }}][section_key]" value="{{ $key }}">
                                                <input type="hidden" name="items[{{ $rowKey }}][section_title]" value="{{ $section['title'] }}">
                                                <input type="hidden" class="js-product-id" name="items[{{ $rowKey }}][product_id]" value="{{ $item->product_id }}">
                                                <input class="js-name" name="items[{{ $rowKey }}][product_name]" value="{{ $item->product_name }}" placeholder="Tên sản phẩm / hạng mục">
                                            </td>

                                            <td>
                                                <input class="js-sku" name="items[{{ $rowKey }}][sku]" value="{{ $item->sku }}">
                                            </td>

                                            <td>
                                                <input class="js-unit" name="items[{{ $rowKey }}][unit]" value="{{ $item->unit ?: 'Bộ' }}">
                                            </td>

                                            <td>
                                                <input type="text" class="js-price money-input" name="items[{{ $rowKey }}][unit_price]" value="{{ $fmt($item->unit_price) }}">
                                            </td>

                                            <td>
                                                <input type="text" class="js-qty" name="items[{{ $rowKey }}][qty]" value="{{ rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ',') }}">
                                            </td>

                                            <td>
                                                <input type="text" class="js-total line-total" name="items[{{ $rowKey }}][line_total]" value="{{ $fmt(((float)$item->unit_price * (float)$item->qty)) }}" readonly>
                                                <input type="hidden" class="js-vat" name="items[{{ $rowKey }}][vat_percent]" value="0">
                                            </td>

                                            <td>
                                                <textarea class="js-specs" name="items[{{ $rowKey }}][specs_text]" placeholder="Thông số kỹ thuật">{{ $item->specs_text }}</textarea>
                                                <input type="hidden" class="js-image" name="items[{{ $rowKey }}][image_url]" value="{{ $item->image_url }}">
                                            </td>

                                            <td>
                                                <button type="button" class="btnx btn-danger" onclick="removeRow(this)">×</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="empty-row">
                                            <td colspan="9">Chưa có dòng nào. Bấm “+ Thêm SP” hoặc “+ Dòng tự nhập”.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="cardx">
            <div class="card-head">
                <h2>3. Điều khoản gửi khách</h2>
                <p>Nội dung in cuối PDF/Excel.</p>
            </div>

            <div class="card-body">
                <div class="terms-grid">
                    <div class="field">
                        <label>Điều kiện thanh toán</label>
                        <textarea name="payment_terms">{{ old('payment_terms', $quotation->payment_terms) }}</textarea>
                    </div>

                    <div class="field">
                        <label>Điều kiện thương mại</label>
                        <textarea name="commercial_terms">{{ old('commercial_terms', $quotation->commercial_terms) }}</textarea>
                    </div>

                    <div class="field">
                        <label>Bảo hành</label>
                        <textarea name="warranty_terms">{{ old('warranty_terms', $quotation->warranty_terms) }}</textarea>
                    </div>

                    <div class="field">
                        <label>Vận hành & bảo trì O&M</label>
                        <textarea name="om_terms">{{ old('om_terms', $quotation->om_terms) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <input type="hidden" id="discount" name="discount_amount" value="{{ old('discount_amount', $quotation->discount_amount ?: 0) }}">
        <input type="hidden" id="vatPercent" name="vat_percent" value="0">

        <div class="bottom-total">
            <div class="total-grid">
                <div class="total-box">
                    <small>Số dòng</small>
                    <b id="rowCount">0</b>
                </div>

                <div class="total-box">
                    <small>Tạm tính</small>
                    <b id="subtotalView">0 đ</b>
                </div>

                <div class="total-box total-main">
                    <small>Tổng báo giá</small>
                    <b id="grandView">0 đ</b>
                </div>
            </div>

            <div class="bottom-actions">
                <a class="btnx btn-white" href="{{ route('sales-quotations.index') }}">Hủy</a>
                <button type="submit" class="btnx btn-primary">Lưu báo giá</button>
            </div>
        </div>
    </form>
</div>

<script>
const products = @json($products->values());
const customers = @json($customers->values());
const sections = @json($sections);
const isEditQuotation = @json($isEdit);

let rowIndex = 1000;

function rawNumber(value) {
    let s = String(value ?? '').trim();
    s = s.replace(/\u00a0/g, '').replace(/\s+/g, '').replace(/[đĐ₫]/g, '');
    s = s.replace(/[^\d,.\-]/g, '');

    if (!s || s === '-' || s === ',' || s === '.') {
        return 0;
    }

    if (s.includes('.') && s.includes(',')) {
        s = s.replace(/\./g, '').replace(',', '.');
    } else if (/^-?\d{1,3}(\.\d{3})+$/.test(s)) {
        s = s.replace(/\./g, '');
    } else if (s.includes(',')) {
        s = s.replace(/,+$/g, '').replace(',', '.');
    }

    const n = Number(s);
    return Number.isFinite(n) ? n : 0;
}

function intNumber(value) {
    return Math.max(0, Math.round(rawNumber(value)));
}

function moneyPlain(value) {
    const n = Math.round(Number(value || 0));
    return n > 0 ? n.toLocaleString('vi-VN') : '';
}

function moneyView(value) {
    return Math.round(Number(value || 0)).toLocaleString('vi-VN') + ' đ';
}

function productPrice(product) {
    return Number(product.price_retail_vat || product.price_agent_vat || product.price_retail || product.price_agent || 0);
}

function productById(id) {
    return products.find(product => String(product.id) === String(id));
}

function customerById(id) {
    return customers.find(customer => String(customer.id) === String(id));
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

function removeEmptyRow(sectionKey) {
    const body = document.querySelector(`[data-section-body="${sectionKey}"]`);
    const empty = body ? body.querySelector('.empty-row') : null;

    if (empty) {
        empty.remove();
    }
}

function ensureEmptyRow(sectionKey) {
    const body = document.querySelector(`[data-section-body="${sectionKey}"]`);

    if (!body) return;

    if (body.querySelectorAll('tr:not(.empty-row)').length > 0) {
        return;
    }

    body.innerHTML = `<tr class="empty-row"><td colspan="9">Chưa có dòng nào. Bấm “+ Thêm SP” hoặc “+ Dòng tự nhập”.</td></tr>`;
}

function addSelectedProduct(sectionKey) {
    const select = document.querySelector(`.section-product-select[data-section="${sectionKey}"]`);
    const product = productById(select.value);

    addRow(sectionKey, product || null);

    select.value = '';
}

function addRow(sectionKey, product = null) {
    removeEmptyRow(sectionKey);

    const body = document.querySelector(`[data-section-body="${sectionKey}"]`);
    const section = sections[sectionKey];
    const index = rowIndex++;

    const productId = product ? product.id : '';
    const productName = product ? (product.name || '') : '';
    const sku = product ? (product.sku || '') : '';
    const unit = product ? (product.unit || 'Bộ') : 'Bộ';
    const price = product ? productPrice(product) : 0;
    const specs = product ? (product.description || product.note || '') : '';
    const image = product ? (product.image_url || '') : '';
    const total = price;

    const tr = document.createElement('tr');

    tr.innerHTML = `
        <td class="row-number"></td>

        <td>
            <input type="hidden" name="items[${index}][section_key]" value="${sectionKey}">
            <input type="hidden" name="items[${index}][section_title]" value="${section.title}">
            <input type="hidden" class="js-product-id" name="items[${index}][product_id]" value="${productId}">
            <input class="js-name" name="items[${index}][product_name]" value="${escapeHtml(productName)}" placeholder="Tên sản phẩm / hạng mục">
        </td>

        <td>
            <input class="js-sku" name="items[${index}][sku]" value="${escapeHtml(sku)}">
        </td>

        <td>
            <input class="js-unit" name="items[${index}][unit]" value="${escapeHtml(unit)}">
        </td>

        <td>
            <input type="text" class="js-price money-input" name="items[${index}][unit_price]" value="${moneyPlain(price)}">
        </td>

        <td>
            <input type="text" class="js-qty" name="items[${index}][qty]" value="1">
        </td>

        <td>
            <input type="text" class="js-total line-total" name="items[${index}][line_total]" value="${moneyPlain(total)}" readonly>
            <input type="hidden" class="js-vat" name="items[${index}][vat_percent]" value="0">
        </td>

        <td>
            <textarea class="js-specs" name="items[${index}][specs_text]" placeholder="Thông số kỹ thuật">${escapeHtml(specs)}</textarea>
            <input type="hidden" class="js-image" name="items[${index}][image_url]" value="${escapeHtml(image)}">
        </td>

        <td>
            <button type="button" class="btnx btn-danger" onclick="removeRow(this)">×</button>
        </td>
    `;

    body.appendChild(tr);
    bindEvents();
    recalc();
}

function removeRow(button) {
    const card = button.closest('[data-section-card]');
    const sectionKey = card.getAttribute('data-section-card');

    button.closest('tr').remove();

    ensureEmptyRow(sectionKey);
    recalc();
}

function calcPriceFromTotal(row) {
    const qtyInput = row.querySelector('.js-qty');
    const priceInput = row.querySelector('.js-price');
    const totalInput = row.querySelector('.js-total');
    const vatInput = row.querySelector('.js-vat');

    if (!qtyInput || !priceInput || !totalInput) return;

    const qty = intNumber(qtyInput.value);
    const total = rawNumber(totalInput.value);

    if (vatInput) vatInput.value = '0';

    qtyInput.value = qty || '';

    if (qty > 0 && total > 0) {
        priceInput.value = moneyPlain(total / qty);
    } else {
        priceInput.value = '';
    }
}

function calcTotalFromPrice(row) {
    const qtyInput = row.querySelector('.js-qty');
    const priceInput = row.querySelector('.js-price');
    const totalInput = row.querySelector('.js-total');
    const vatInput = row.querySelector('.js-vat');

    if (!qtyInput || !priceInput || !totalInput) return;

    const qty = intNumber(qtyInput.value);
    const price = rawNumber(priceInput.value);

    if (vatInput) vatInput.value = '0';

    qtyInput.value = qty || '';

    totalInput.value = qty > 0 && price > 0 ? moneyPlain(qty * price) : '';
}

function bindEvents() {
    document.querySelectorAll('.js-name').forEach(input => {
        input.oninput = recalc;
    });

    document.querySelectorAll('.js-price').forEach(input => {
        input.removeAttribute('readonly');
        input.type = 'text';
        input.inputMode = 'numeric';

        input.onfocus = function () {
            this.value = rawNumber(this.value) ? String(Math.round(rawNumber(this.value))) : '';
            this.select();
        };

        input.oninput = function () {
            const row = this.closest('tr');
            calcTotalFromPrice(row);
            recalc();
        };

        input.onblur = function () {
            this.value = rawNumber(this.value) ? moneyPlain(rawNumber(this.value)) : '';
            const row = this.closest('tr');
            calcTotalFromPrice(row);
            recalc();
        };
    });

    document.querySelectorAll('.js-qty').forEach(input => {
        input.type = 'text';
        input.inputMode = 'numeric';

        input.oninput = function () {
            const row = this.closest('tr');
            this.value = intNumber(this.value) || '';
            calcTotalFromPrice(row);
            recalc();
        };

        input.onchange = input.oninput;
    });

    document.querySelectorAll('.js-total').forEach(input => {
        input.type = 'text';
        input.inputMode = 'numeric';
        input.readOnly = true;
        input.placeholder = 'Tự tính';
    });
}

function recalc() {
    let subtotal = 0;
    let count = 0;

    document.querySelectorAll('[data-section-body]').forEach(body => {
        let sectionNo = 1;

        body.querySelectorAll('tr:not(.empty-row)').forEach(row => {
            const no = row.querySelector('.row-number');
            if (no) {
                no.innerHTML = `<b>${sectionNo}</b>`;
            }

            sectionNo++;

            const name = row.querySelector('.js-name')?.value || '';

            calcTotalFromPrice(row);

            const total = rawNumber(row.querySelector('.js-total')?.value || 0);
            subtotal += total;

            const vatInput = row.querySelector('.js-vat');
            if (vatInput) vatInput.value = '0';

            if (name.trim() !== '') {
                count++;
            }
        });
    });

    document.getElementById('rowCount').innerText = count;
    document.getElementById('subtotalView').innerText = moneyView(subtotal);
    document.getElementById('grandView').innerText = moneyView(subtotal);
}

function prepareBeforeSubmit() {
    document.querySelectorAll('[data-section-body] tr:not(.empty-row)').forEach(row => {
        calcTotalFromPrice(row);

        const qtyInput = row.querySelector('.js-qty');
        const priceInput = row.querySelector('.js-price');
        const totalInput = row.querySelector('.js-total');
        const vatInput = row.querySelector('.js-vat');

        if (qtyInput) qtyInput.value = String(intNumber(qtyInput.value) || 0);
        if (priceInput) priceInput.value = String(Math.round(rawNumber(priceInput.value)) || 0);
        if (totalInput) totalInput.value = String(Math.round(rawNumber(totalInput.value)) || 0);
        if (vatInput) vatInput.value = '0';
    });
}

function setRowValue(row, selector, value) {
    const input = row.querySelector(selector);

    if (input) {
        input.value = value ?? '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}

const quoteDefaultRowsBySection = {
    sub: [
        {
            name: 'Vật tư / thiết bị lắp tấm pin',
            sku: '',
            unit: 'Hệ',
            qty: 1,
            total: 12000000,
            specs: 'Dây AC/DC, rail, kẹp, MC4, ống điện, vật tư phụ, nhân công lắp đặt phụ trợ.'
        }
    ],
    ac_cabinet: [
        {
            name: 'Tủ AC',
            sku: '',
            unit: 'Cái',
            qty: 1,
            total: 4500000,
            specs: 'Vỏ tủ điện AC, MCB AC, chống sét lan truyền SPD, ATS, dây điện và phụ kiện.'
        }
    ],
    grounding: [
        {
            name: 'Hệ thống tiếp địa',
            sku: '',
            unit: 'Hệ',
            qty: 1,
            total: 2000000,
            specs: 'Cọc tiếp địa, dây tiếp địa vàng xanh, phụ kiện tiếp địa.'
        }
    ],
    other: [
        {
            name: 'Nhân công khảo sát, thiết kế kỹ thuật, lắp đặt và setup hệ thống',
            sku: '',
            unit: 'kW',
            qty: 13,
            total: 13000000,
            specs: 'An toàn lao động, thử nghiệm vật tư thiết bị, đo kiểm, đấu nối, vận hành và bàn giao.'
        },
        {
            name: 'Máy thi công + vận chuyển',
            sku: '',
            unit: 'Hệ',
            qty: 1,
            total: 8000000,
            specs: 'Chi phí máy thi công, vận chuyển vật tư thiết bị tới công trình.'
        }
    ],
    om: [
        {
            name: 'Bảo hành và vận hành trong thời hạn 02 năm',
            sku: '',
            unit: 'Hệ',
            qty: 1,
            total: 0,
            specs: 'Bao gồm kiểm tra, bảo dưỡng, vệ sinh tấm pin theo chính sách.'
        },
        {
            name: 'Vận hành và bảo trì O&M',
            sku: '',
            unit: 'Năm',
            qty: 1,
            total: 0,
            specs: 'Theo dõi hệ thống, kiểm tra DC/AC, MC4, inverter, tủ AC, tiếp địa và vệ sinh tấm pin.'
        }
    ]
};

function sectionHasRealRows(sectionKey) {
    const body = document.querySelector(`[data-section-body="${sectionKey}"]`);
    return body ? body.querySelectorAll('tr:not(.empty-row)').length > 0 : true;
}

function addPresetQuoteRow(sectionKey, data) {
    addRow(sectionKey, null);

    const body = document.querySelector(`[data-section-body="${sectionKey}"]`);
    const rows = body ? body.querySelectorAll('tr:not(.empty-row)') : [];
    const row = rows[rows.length - 1];

    if (!row) return;

    const qty = intNumber(data.qty || 1) || 1;
    const price = data.price !== undefined
        ? rawNumber(data.price)
        : (rawNumber(data.total || 0) > 0 ? rawNumber(data.total || 0) / qty : 0);

    setRowValue(row, '.js-name', data.name);
    setRowValue(row, '.js-sku', data.sku);
    setRowValue(row, '.js-unit', data.unit);
    setRowValue(row, '.js-qty', qty);
    setRowValue(row, '.js-price', moneyPlain(price));
    setRowValue(row, '.js-vat', 0);
    setRowValue(row, '.js-specs', data.specs);
    calcTotalFromPrice(row);
    recalc();
}

function initDefaultQuoteRows() {
    if (isEditQuotation) return;

    Object.keys(quoteDefaultRowsBySection).forEach(sectionKey => {
        if (sectionHasRealRows(sectionKey)) return;

        quoteDefaultRowsBySection[sectionKey].forEach(row => {
            addPresetQuoteRow(sectionKey, row);
        });
    });

    recalc();
}

const customerSelect = document.getElementById('customerSelect');
if (customerSelect) {
    customerSelect.addEventListener('change', function () {
        const customer = customerById(this.value);
        if (!customer) return;

        document.getElementById('customer_name').value = customer.name || customer.customer_name || '';
        document.getElementById('customer_phone').value = customer.phone || customer.customer_phone || '';
        document.getElementById('customer_email').value = customer.email || customer.customer_email || '';
        document.getElementById('customer_address').value = customer.address || customer.customer_address || '';
        document.getElementById('billing_company_name').value = customer.billing_company_name || customer.company_name || '';
        document.getElementById('billing_tax_code').value = customer.billing_tax_code || customer.tax_code || '';
    });
}

document.querySelector('form')?.addEventListener('submit', prepareBeforeSubmit, true);

bindEvents();
initDefaultQuoteRows();
recalc();
</script>
@endsection
