@extends('layouts.app')

@section('content')
@php
    /* EGO_SITE_COMPANY_CREATE_DATA_START */
    $egoSiteCompanyOptions = collect();
    if (\Illuminate\Support\Facades\Schema::hasTable('companies')) {
        $egoSiteCompanyQuery = \Illuminate\Support\Facades\DB::table('companies')->select('id', 'code', 'name');
        if (\Illuminate\Support\Facades\Schema::hasColumn('companies', 'is_active')) {
            $egoSiteCompanyQuery->where('is_active', 1);
        }
        $egoSiteCompanyOptions = $egoSiteCompanyQuery->orderBy('id')->get();
    }
    $egoOldSiteCompanyId = old('company_id', session('ego_company_id', ''));
    /* EGO_SITE_COMPANY_CREATE_DATA_END */

    $oldTerms = old('payment_terms');

    if (!$oldTerms || !is_array($oldTerms) || count($oldTerms) === 0) {
        $oldTerms = [
            ['name' => 'Đợt 1 - Đặt cọc', 'percent' => 30, 'amount' => '', 'due_date' => '', 'note' => ''],
            ['name' => 'Đợt 2 - Triển khai', 'percent' => 40, 'amount' => '', 'due_date' => '', 'note' => ''],
            ['name' => 'Đợt 3 - Nghiệm thu / bàn giao', 'percent' => 30, 'amount' => '', 'due_date' => '', 'note' => ''],
        ];
    }
@endphp

<div class="container-fluid px-4 py-3 ego-sites-form">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3 ego-header">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="page-icon"><i class="bi bi-buildings"></i></span>
                <h4 class="fw-bold mb-0">Tạo công trình</h4>
            </div>
            <div class="text-muted small">
                Tạo nhanh công trình. Thiết bị/vật tư chi tiết sẽ quản lý bằng đơn vật tư riêng.
            </div>
        </div>

        <a href="{{ url('/cong-trinh') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm" style="border-radius:16px;">
            <div class="fw-semibold mb-1">
                <i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:
            </div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ url('/cong-trinh') }}" id="siteCreateForm">
        @csrf

        <div class="row g-3">
            <div class="col-lg-8">

                {{-- THÔNG TIN CÔNG TRÌNH --}}
                <div class="card border-0 shadow-ego ego-card">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="icon-pill"><i class="bi bi-buildings"></i></span>
                            <div>
                                <div class="fw-bold">Thông tin công trình</div>
                                <div class="text-muted small">Các trường có dấu * là bắt buộc.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            {{-- EGO_SITE_COMPANY_CREATE_FIELD_START --}}
                            <div class="col-md-4">
                                <label class="form-label">Công ty <span class="text-danger">*</span></label>
                                <select name="company_id" class="form-select" required>
                                    <option value="">-- Chọn công ty --</option>
                                    @foreach($egoSiteCompanyOptions as $company)
                                        <option value="{{ $company->id }}" {{ (string)$egoOldSiteCompanyId === (string)$company->id ? 'selected' : '' }}>
                                            {{ trim(($company->code ?? '') . ' - ' . ($company->name ?? '')) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            {{-- EGO_SITE_COMPANY_CREATE_FIELD_END --}}

                            <div class="col-md-5">
                                <label class="form-label">Tên công trình <span class="text-danger">*</span></label>
                                <input name="name" class="form-control" required value="{{ old('name') }}"
                                       placeholder="VD: Công trình nhà Anh A">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Trạng thái</label>
                                <select name="status" class="form-select">
                                    <option value="" {{ old('status')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="planning" {{ old('status')=='planning'?'selected':'' }}>Chuẩn bị</option>
                                    <option value="installing" {{ old('status')=='installing'?'selected':'' }}>Đang triển khai</option>
                                    <option value="done" {{ old('status')=='done'?'selected':'' }}>Đã hoàn thành</option>
                                    <option value="warranty" {{ old('status')=='warranty'?'selected':'' }}>Đang bảo hành</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Địa chỉ</label>
                                <input name="address" class="form-control" value="{{ old('address') }}"
                                       placeholder="VD: 123 Lê Văn Lương, TP.HCM">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Người liên hệ</label>
                                <input name="contact_name" class="form-control" value="{{ old('contact_name') }}"
                                       placeholder="VD: Anh A">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">SĐT liên hệ</label>
                                <input name="contact_phone" class="form-control" value="{{ old('contact_phone') }}"
                                       placeholder="VD: 0909xxxxxx">
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Ghi chú</label>
                                <textarea name="note" class="form-control" rows="3"
                                          placeholder="Ghi chú nội bộ...">{{ old('note') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- TÀI CHÍNH --}}
                <div class="card border-0 shadow-ego ego-card mt-3 finance-card">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="icon-pill finance-icon"><i class="bi bi-cash-coin"></i></span>
                            <div>
                                <div class="fw-bold">Tài chính công trình</div>
                                <div class="text-muted small">Doanh thu dự án và các đợt thanh toán.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="finance-hero mb-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label">Giá trị hợp đồng / Doanh thu dự án</label>
                                    <div class="input-money">
                                        <input name="contract_amount" id="contract_amount"
                                               type="number" step="any" min="0"
                                               class="form-control text-end fw-bold"
                                               value="{{ old('contract_amount', 0) }}"
                                               placeholder="VD: 150000000">
                                        <span>đ</span>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Ngày ký hợp đồng</label>
                                    <input name="contract_signed_at" type="date" class="form-control"
                                           value="{{ old('contract_signed_at') }}">
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Ghi chú tài chính</label>
                                    <input name="finance_note" class="form-control"
                                           value="{{ old('finance_note') }}"
                                           placeholder="VD: cọc 30%, nghiệm thu 70%">
                                </div>
                            </div>
                        </div>


                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div>
                                <div class="fw-bold">Các đợt thanh toán</div>
                                <div class="text-muted small">Nhập % hoặc số tiền. Nếu có %, hệ thống tự tính.</div>
                            </div>

                            <button type="button" class="btn btn-outline-ego" id="btnAddPaymentTerm">
                                <i class="bi bi-plus-lg"></i> Thêm đợt
                            </button>
                        </div>

                        <div class="table-responsive payment-terms-wrap">
                            <table class="table align-middle mb-0 ego-table payment-terms-table">
                                <thead class="table-light">
                                <tr>
                                    <th style="width:54px" class="text-center">#</th>
                                    <th style="min-width:230px">Tên đợt</th>
                                    <th style="width:120px" class="text-end">%</th>
                                    <th style="width:190px" class="text-end">Số tiền</th>
                                    <th style="width:170px">Ngày dự kiến</th>
                                    <th style="min-width:220px">Ghi chú</th>
                                    <th style="width:80px" class="text-end">Xóa</th>
                                </tr>
                                </thead>

                                <tbody id="paymentTermsTbody">
                                @foreach($oldTerms as $i => $term)
                                    <tr class="payment-term-row">
                                        <td class="text-center term-idx">{{ $i + 1 }}</td>

                                        <td>
                                            <input name="payment_terms[{{ $i }}][name]" class="form-control"
                                                   value="{{ $term['name'] ?? '' }}">
                                        </td>

                                        <td>
                                            <input name="payment_terms[{{ $i }}][percent]" type="number"
                                                   min="0" max="100" step="any"
                                                   class="form-control text-end term-percent"
                                                   value="{{ $term['percent'] ?? '' }}">
                                        </td>

                                        <td>
                                            <input name="payment_terms[{{ $i }}][amount]" type="number"
                                                   min="0" step="any"
                                                   class="form-control text-end term-amount"
                                                   value="{{ $term['amount'] ?? '' }}">
                                        </td>

                                        <td>
                                            <input name="payment_terms[{{ $i }}][due_date]" type="date"
                                                   class="form-control"
                                                   value="{{ $term['due_date'] ?? '' }}">
                                        </td>

                                        <td>
                                            <input name="payment_terms[{{ $i }}][note]" class="form-control"
                                                   value="{{ $term['note'] ?? '' }}">
                                        </td>

                                        <td class="text-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger btnRemoveTerm"
                                                    {{ count($oldTerms) === 1 ? 'disabled' : '' }}>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="finance-mini-summary mt-3">
                            <div class="mini-box">
                                <div class="text-muted small">Tổng hợp đồng</div>
                                <div class="fw-bold" id="financeContractMini">0 đ</div>
                            </div>
                            <div class="mini-box">
                                <div class="text-muted small">Tổng các đợt</div>
                                <div class="fw-bold" id="financeTermsMini">0 đ</div>
                            </div>
                            <div class="mini-box">
                                <div class="text-muted small">Chênh lệch</div>
                                <div class="fw-bold" id="financeDiffMini">0 đ</div>
                            </div>
                            <div class="mini-box">
                                <div class="text-muted small">Số đợt</div>
                                <div class="fw-bold" id="financeTermCountMini">{{ count($oldTerms) }}</div>
                            </div>
                        </div>
                    </div>
                </div>


                {{-- CHI PHÍ PHÁT SINH CÔNG TRÌNH --}}
                <div class="card border-0 shadow-ego ego-card mt-3 site-cost-card">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="icon-pill finance-icon"><i class="bi bi-wallet2"></i></span>
                                <div>
                                    <div class="fw-bold">Chi phí phát sinh công trình</div>
                                    <div class="text-muted small">Nhân công, vận chuyển và các khoản chi phí khác.</div>
                                </div>
                            </div>
                            <span class="badge bg-light text-dark border rounded-pill px-3 py-2">
                                Tự cộng chi phí khác
                            </span>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-4 border bg-white h-100">
                                    <label class="form-label fw-semibold">Chi phí nhân công</label>
                                    <div class="input-money">
                                        <input name="labor_cost" type="number" step="any" min="0"
                                               class="form-control text-end fw-bold"
                                               value="{{ old('labor_cost', 0) }}"
                                               placeholder="VD: 5000000">
                                        <span>đ</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 rounded-4 border bg-white h-100">
                                    <label class="form-label fw-semibold">Chi phí vận chuyển</label>
                                    <div class="input-money">
                                        <input name="transport_cost" type="number" step="any" min="0"
                                               class="form-control text-end fw-bold"
                                               value="{{ old('transport_cost', 0) }}"
                                               placeholder="VD: 1000000">
                                        <span>đ</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="p-3 rounded-4 border" style="background:linear-gradient(135deg, rgba(11,201,170,.07), #fff);">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                        <div>
                                            <div class="fw-bold">Chi phí khác</div>
                                            <div class="text-muted small">Có thể thêm nhiều dòng, hệ thống tự cộng tổng.</div>
                                        </div>

                                        <button type="button" class="btn btn-outline-ego btn-sm" data-add-other-cost>
                                            <i class="bi bi-plus-lg"></i> Thêm dòng
                                        </button>
                                    </div>

                                    <input type="hidden" name="other_cost" value="{{ old('other_cost', 0) }}" data-other-cost-hidden>
                                    <input type="hidden" name="other_cost_note" value="{{ old('other_cost_note') }}" data-other-cost-note-hidden>

                                    <div data-other-cost-rows>
                                        <div class="row g-2 align-items-end other-cost-row mb-2" data-other-cost-row>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted mb-1">Số tiền</label>
                                                <div class="input-money">
                                                    <input type="number" step="any" min="0"
                                                           class="form-control text-end"
                                                           value="{{ old('other_cost', 0) }}"
                                                           placeholder="VD: 2000000"
                                                           data-other-cost-amount>
                                                    <span>đ</span>
                                                </div>
                                            </div>

                                            <div class="col-md-7">
                                                <label class="form-label small text-muted mb-1">Nội dung</label>
                                                <input type="text" class="form-control"
                                                       value="{{ old('other_cost_note') }}"
                                                       placeholder="VD: Phụ kiện, ăn ở, bốc xếp..."
                                                       data-other-cost-note>
                                            </div>

                                            <div class="col-md-1 d-grid">
                                                <button type="button" class="btn btn-outline-danger" data-remove-other-cost title="Xóa dòng">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-end mt-3">
                                        <div class="px-3 py-2 rounded-4 bg-white border">
                                            <span class="text-muted small">Tổng chi phí khác:</span>
                                            <span class="fw-bold ms-2" data-other-cost-total>0 đ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                    (function(){
                        function money(n){
                            n = Number(n || 0);
                            return n.toLocaleString('vi-VN') + ' đ';
                        }

                        document.querySelectorAll('.site-cost-card').forEach(function(card){
                            const rowsWrap = card.querySelector('[data-other-cost-rows]');
                            const addBtn = card.querySelector('[data-add-other-cost]');
                            const hiddenAmount = card.querySelector('[data-other-cost-hidden]');
                            const hiddenNote = card.querySelector('[data-other-cost-note-hidden]');
                            const totalEl = card.querySelector('[data-other-cost-total]');

                            if (!rowsWrap || !hiddenAmount || !hiddenNote) return;

                            function rows(){
                                return Array.from(rowsWrap.querySelectorAll('[data-other-cost-row]'));
                            }

                            function createRow(){
                                const row = document.createElement('div');
                                row.className = 'row g-2 align-items-end other-cost-row mb-2';
                                row.setAttribute('data-other-cost-row', '');

                                row.innerHTML = `
                                    <div class="col-md-4">
                                        <div class="input-money">
                                            <input type="number" step="any" min="0"
                                                   class="form-control text-end"
                                                   placeholder="Số tiền"
                                                   data-other-cost-amount>
                                            <span>đ</span>
                                        </div>
                                    </div>

                                    <div class="col-md-7">
                                        <input type="text" class="form-control"
                                               placeholder="Nội dung chi phí"
                                               data-other-cost-note>
                                    </div>

                                    <div class="col-md-1 d-grid">
                                        <button type="button" class="btn btn-outline-danger" data-remove-other-cost title="Xóa dòng">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                `;

                                return row;
                            }

                            function sync(){
                                let total = 0;
                                const notes = [];

                                rows().forEach(function(row, index){
                                    const amountInput = row.querySelector('[data-other-cost-amount]');
                                    const noteInput = row.querySelector('[data-other-cost-note]');

                                    const amount = Number(amountInput?.value || 0);
                                    const note = (noteInput?.value || '').trim();

                                    total += amount;

                                    if (amount > 0 || note) {
                                        notes.push((index + 1) + '. ' + (note || 'Chi phí khác') + ' - ' + money(amount));
                                    }
                                });

                                hiddenAmount.value = Math.round(total);
                                hiddenNote.value = notes.join('\n');

                                if (totalEl) totalEl.textContent = money(total);

                                const currentRows = rows();
                                currentRows.forEach(function(row){
                                    const btn = row.querySelector('[data-remove-other-cost]');
                                    if (btn) btn.disabled = currentRows.length <= 1;
                                });
                            }

                            addBtn?.addEventListener('click', function(){
                                rowsWrap.appendChild(createRow());
                                sync();
                            });

                            rowsWrap.addEventListener('click', function(e){
                                const btn = e.target.closest('[data-remove-other-cost]');
                                if (!btn) return;

                                const row = btn.closest('[data-other-cost-row]');
                                if (row && rows().length > 1) {
                                    row.remove();
                                }

                                sync();
                            });

                            rowsWrap.addEventListener('input', sync);
                            sync();
                        });
                    })();
                    </script>
                </div>


                {{-- HỆ THỐNG --}}
                <div class="card border-0 shadow-ego ego-card mt-3">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="d-flex align-items-center gap-2">
                            <span class="icon-pill"><i class="bi bi-lightning-charge"></i></span>
                            <div>
                                <div class="fw-bold">Thông tin hệ thống</div>
                                <div class="text-muted small">
                                    Nhập inverter, pin lưu trữ, tấm pin. Hệ kWp sẽ tự tính từ số tấm × W/tấm.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="system-box mb-3">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label">Hiệu tấm pin</label>
                                    <input name="panel_brand" class="form-control"
                                           value="{{ old('panel_brand') }}"
                                           placeholder="VD: Jinko / Longi / AE...">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Công suất tấm pin (W/tấm)</label>
                                    <input name="solar_panel_wp" id="solar_panel_wp"
                                           type="number" min="0" step="1"
                                           class="form-control text-end"
                                           value="{{ old('solar_panel_wp') }}"
                                           placeholder="VD: 550">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Số lượng tấm</label>
                                    <input name="solar_panel_qty" id="solar_panel_qty"
                                           type="number" min="0" step="1"
                                           class="form-control text-end"
                                           value="{{ old('solar_panel_qty') }}"
                                           placeholder="VD: 12">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Hệ bao nhiêu kWp</label>
                                    <input name="system_kwp" id="system_kwp"
                                           type="number" min="0" step="any"
                                           class="form-control text-end fw-bold"
                                           value="{{ old('system_kwp') }}"
                                           placeholder="Tự tính">
                                    <div class="form-text">= W/tấm × số tấm / 1000</div>
                                </div>
                            </div>
                        </div>

                        <div class="system-box mb-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hiệu Inverter</label>
                                    <input name="inverter_brand" class="form-control"
                                           value="{{ old('inverter_brand') }}"
                                           placeholder="VD: Goodwe / Solis / Sungrow...">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Công suất Inverter (kW)</label>
                                    <input name="system_kw_ac" type="number" step="any" min="0"
                                           class="form-control text-end"
                                           value="{{ old('system_kw_ac') }}"
                                           placeholder="VD: 5">
                                </div>
                            </div>
                        </div>

                        <div class="system-box mb-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Hiệu pin lưu trữ</label>
                                    <input name="battery_brand" class="form-control"
                                           value="{{ old('battery_brand') }}"
                                           placeholder="VD: Goodwe / Dyness / Pylontech...">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Pin lưu trữ bao nhiêu kWh</label>
                                    <input name="battery_kwh" type="number" step="any" min="0"
                                           class="form-control text-end"
                                           value="{{ old('battery_kwh') }}"
                                           placeholder="VD: 10.24">
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Loại hệ</label>
                                <select name="system_type" class="form-select">
                                    <option value="" {{ old('system_type')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="on_grid"  {{ old('system_type')=='on_grid'?'selected':'' }}>On-grid</option>
                                    <option value="hybrid"   {{ old('system_type')=='hybrid'?'selected':'' }}>Hybrid</option>
                                    <option value="off_grid" {{ old('system_type')=='off_grid'?'selected':'' }}>Off-grid</option>
                                    <option value="other" {{ old('system_type')=='other'?'selected':'' }}>Khác</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Điện áp</label>
                                <select name="phase" class="form-select">
                                    <option value="" {{ old('phase')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="1_phase" {{ old('phase')=='1_phase'?'selected':'' }}>1 pha</option>
                                    <option value="3_phase" {{ old('phase')=='3_phase'?'selected':'' }}>3 pha</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Giai đoạn</label>
                                <select name="stage" class="form-select">
                                    <option value="" {{ old('stage')==''?'selected':'' }}>-- Chưa chọn --</option>
                                    <option value="survey" {{ old('stage')=='survey'?'selected':'' }}>Khảo sát</option>
                                    <option value="design" {{ old('stage')=='design'?'selected':'' }}>Thiết kế</option>
                                    <option value="installation" {{ old('stage')=='installation'?'selected':'' }}>Thi công</option>
                                    <option value="acceptance" {{ old('stage')=='acceptance'?'selected':'' }}>Nghiệm thu</option>
                                    <option value="operation" {{ old('stage')=='operation'?'selected':'' }}>Vận hành</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Ngày bắt đầu triển khai</label>
                                <input name="deployment_started_at" type="date" class="form-control"
                                       value="{{ old('deployment_started_at') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Ngày hoàn thành</label>
                                <input name="completed_at" id="completed_at" type="date" class="form-control"
                                       value="{{ old('completed_at') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Bảo hành đến</label>
                                <input name="warranty_to" id="warranty_to" type="date" class="form-control"
                                       value="{{ old('warranty_to') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Mốc bảo hành 1</label>
                                <input name="warranty_reminder_1_at" id="warranty_reminder_1_at" type="date" class="form-control"
                                       value="{{ old('warranty_reminder_1_at') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Mốc bảo hành 2</label>
                                <input name="warranty_reminder_2_at" id="warranty_reminder_2_at" type="date" class="form-control"
                                       value="{{ old('warranty_reminder_2_at') }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Mốc bảo hành 3</label>
                                <input name="warranty_reminder_3_at" id="warranty_reminder_3_at" type="date" class="form-control"
                                       value="{{ old('warranty_reminder_3_at') }}">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Phụ trách kỹ thuật</label>
                                <input name="technician_name" class="form-control"
                                       value="{{ old('technician_name') }}"
                                       placeholder="VD: Anh Thư, Anh B...">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Link Monitoring</label>
                                <input name="monitoring_link" class="form-control"
                                       value="{{ old('monitoring_link') }}"
                                       placeholder="VD: https://...">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Tài khoản Monitoring</label>
                                <input name="monitoring_account" class="form-control"
                                       value="{{ old('monitoring_account') }}"
                                       placeholder="VD: user@email.com">
                            </div>

                            <input type="hidden" name="installed_at" id="installed_at" value="{{ old('installed_at') }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-ego ego-card sticky-lg-top" style="top:90px;">
                    <div class="card-header bg-white border-0 py-3">
                        <div class="fw-bold"><i class="bi bi-check2-circle"></i> Hoàn tất</div>
                        <div class="text-muted small">Kiểm tra thông tin trước khi lưu.</div>
                    </div>

                    <div class="card-body">
                        <div class="summary-box mb-3">
                            <div class="text-muted small">Hệ</div>
                            <div class="summary-money"><span id="summarySystemKwp">0</span> kWp</div>
                        </div>

                        <div class="summary-box mb-3">
                            <div class="text-muted small">Inverter</div>
                            <div class="fw-bold"><span id="summaryInverterBrand">—</span></div>
                            <div class="summary-sub"><span id="summaryInverterKw">0</span> kW</div>
                        </div>

                        <div class="summary-box mb-3">
                            <div class="text-muted small">Pin lưu trữ</div>
                            <div class="fw-bold"><span id="summaryBatteryBrand">—</span></div>
                            <div class="summary-sub"><span id="summaryBatteryKwh">0</span> kWh</div>
                        </div>

                        <div class="summary-box mb-3">
                            <div class="text-muted small">Tấm pin</div>
                            <div class="fw-bold"><span id="summaryPanelBrand">—</span></div>
                            <div class="summary-sub">
                                <span id="summaryPanelWp">0</span> W × <span id="summaryPanelQty">0</span> tấm
                            </div>
                        </div>

                        <hr>

                        <div class="summary-box mb-3">
                            <div class="text-muted small">Tổng hợp đồng</div>
                            <div class="summary-money" id="summaryContract">0 đ</div>
                        </div>

                        <div class="summary-box mb-3">
                            <div class="text-muted small">Tổng các đợt thanh toán</div>
                            <div class="summary-money" id="summaryTerms">0 đ</div>
                        </div>

                        <div class="summary-box mb-3">
                            <div class="text-muted small">Chênh lệch</div>
                            <div class="summary-money" id="summaryDiff">0 đ</div>
                        </div>

                        <div class="progress payment-progress mb-3">
                            <div class="progress-bar" id="paymentProgressBar" style="width:0%"></div>
                        </div>

                        <button type="submit" class="btn btn-ego w-100">
                            <i class="bi bi-save"></i> Lưu công trình
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
:root{
    --ego:#0BC9AA;
    --ego-dark:#08a88f;
    --ego-soft:#e8fbf7;
    --ink:#0f172a;
    --muted:#64748b;
    --line:#e6f4f1;
}
.ego-sites-form{
    background:radial-gradient(circle at top left, rgba(11,201,170,.12), transparent 28%), linear-gradient(180deg,#f7fffd 0%,#fff 55%);
    min-height:calc(100vh - 64px);
}
.page-icon,.icon-pill{
    display:inline-flex;width:38px;height:38px;border-radius:14px;align-items:center;justify-content:center;
    background:rgba(11,201,170,.12);color:#0f766e;border:1px solid rgba(11,201,170,.20);
}
.shadow-ego{box-shadow:0 14px 34px rgba(2,44,34,.07);}
.ego-card{border:1px solid var(--line)!important;border-radius:18px!important;overflow:hidden;background:rgba(255,255,255,.96);}
.ego-card .card-header{border-radius:18px 18px 0 0;}
.form-control,.form-select{border-radius:13px;border-color:rgba(15,23,42,.12);}
.form-control:focus,.form-select:focus{border-color:rgba(11,201,170,.7);box-shadow:0 0 0 .2rem rgba(11,201,170,.12);}
.btn-ego{
    background:linear-gradient(135deg,var(--ego),var(--ego-dark))!important;border:0!important;color:#fff!important;
    border-radius:14px!important;font-weight:800;box-shadow:0 12px 24px rgba(11,201,170,.24);
}
.btn-outline-ego{
    border-color:rgba(11,201,170,.42)!important;color:#0f766e!important;border-radius:13px!important;
    font-weight:800;background:#fff;
}
.btn-outline-ego:hover{background:var(--ego-soft)!important;}
.input-money{display:flex;align-items:center;border:1px solid rgba(15,23,42,.12);border-radius:13px;overflow:hidden;background:#fff;}
.input-money input{border:0!important;box-shadow:none!important;}
.input-money span{padding:0 12px;color:var(--muted);font-weight:800;}
.ego-table thead th{
    background:var(--ego-soft)!important;border-bottom:1px solid var(--line)!important;color:#0b3b36!important;
    font-weight:900!important;white-space:nowrap;
}
.ego-table td{border-color:var(--line)!important;vertical-align:middle;}
.finance-mini-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;}
.mini-box,.summary-box,.system-box{
    border:1px solid rgba(15,23,42,.07);background:#fff;border-radius:16px;padding:13px;
}
.system-box{background:linear-gradient(135deg, rgba(11,201,170,.055), #fff);}
.summary-money{font-size:22px;font-weight:950;color:var(--ink);letter-spacing:-.4px;}
.summary-sub{font-size:15px;color:var(--muted);font-weight:800;}
.payment-progress{height:12px;background:rgba(15,23,42,.08);border-radius:999px;overflow:hidden;}
.payment-progress .progress-bar{background:linear-gradient(90deg,var(--ego),#10b981);}
.payment-progress .progress-bar.warn{background:linear-gradient(90deg,#f59e0b,#ef4444);}
@media(max-width:991.98px){
    .finance-mini-summary{grid-template-columns:repeat(2,minmax(0,1fr));}
    .sticky-lg-top{position:static!important;}
}
@media(max-width:575.98px){
    .finance-mini-summary{grid-template-columns:1fr;}
}
</style>

<script>
(function(){
    const money = n => {
        n = Number(n || 0);
        return n.toLocaleString('vi-VN') + ' đ';
    };

    const qs = (s, root=document) => root.querySelector(s);
    const qsa = (s, root=document) => Array.from(root.querySelectorAll(s));

    const contractInput = qs('#contract_amount');
    const paymentTermsTbody = qs('#paymentTermsTbody');

    function setText(id, text){
        const el = qs('#' + id);
        if (el) el.textContent = text;
    }

    function renumberTerms(){
        qsa('.payment-term-row', paymentTermsTbody).forEach((row, i) => {
            const idx = row.querySelector('.term-idx');
            if (idx) idx.textContent = i + 1;

            qsa('input', row).forEach(input => {
                input.name = input.name.replace(/payment_terms\[\d+\]/, 'payment_terms[' + i + ']');
            });

            const removeBtn = row.querySelector('.btnRemoveTerm');
            if (removeBtn) removeBtn.disabled = qsa('.payment-term-row', paymentTermsTbody).length <= 1;
        });
    }

    function calcFinance(){
        const contract = Number(contractInput?.value || 0);
        let totalTerms = 0;

        qsa('.payment-term-row', paymentTermsTbody).forEach(row => {
            const percentInput = row.querySelector('.term-percent');
            const amountInput = row.querySelector('.term-amount');

            const percent = Number(percentInput?.value || 0);
            let amount = Number(amountInput?.value || 0);

            if (percent > 0 && contract > 0) {
                amount = Math.round(contract * percent / 100);
                if (amountInput && document.activeElement !== amountInput) {
                    amountInput.value = amount;
                }
            }

            totalTerms += amount;
        });

        const diff = contract - totalTerms;
        const progress = contract > 0 ? Math.min(100, Math.max(0, totalTerms / contract * 100)) : 0;

        setText('financeContractMini', money(contract));
        setText('financeTermsMini', money(totalTerms));
        setText('financeDiffMini', money(diff));
        setText('financeTermCountMini', String(qsa('.payment-term-row', paymentTermsTbody).length));

        setText('summaryContract', money(contract));
        setText('summaryTerms', money(totalTerms));
        setText('summaryDiff', money(diff));

        const bar = qs('#paymentProgressBar');
        if (bar) {
            bar.style.width = progress + '%';
            bar.classList.toggle('warn', diff !== 0 && contract > 0);
        }

        ['financeDiffMini','summaryDiff'].forEach(id => {
            const el = qs('#' + id);
            if (!el) return;
            el.classList.remove('text-success','text-danger');
            if (contract > 0 || totalTerms > 0) {
                el.classList.add(diff === 0 ? 'text-success' : 'text-danger');
            }
        });
    }

    function createTermRow(){
        const tpl = qs('.payment-term-row', paymentTermsTbody);
        const clone = tpl.cloneNode(true);
        qsa('input', clone).forEach(input => input.value = '');
        const removeBtn = clone.querySelector('.btnRemoveTerm');
        if (removeBtn) removeBtn.disabled = false;
        return clone;
    }

    qs('#btnAddPaymentTerm')?.addEventListener('click', () => {
        paymentTermsTbody.appendChild(createTermRow());
        renumberTerms();
        calcFinance();
    });

    paymentTermsTbody?.addEventListener('click', e => {
        const btn = e.target.closest('.btnRemoveTerm');
        if (!btn) return;

        const rows = qsa('.payment-term-row', paymentTermsTbody);
        if (rows.length <= 1) return;

        btn.closest('.payment-term-row')?.remove();
        renumberTerms();
        calcFinance();
    });

    paymentTermsTbody?.addEventListener('input', calcFinance);
    contractInput?.addEventListener('input', calcFinance);

    const panelBrand = qs('[name="panel_brand"]');
    const panelWp = qs('#solar_panel_wp');
    const panelQty = qs('#solar_panel_qty');
    const systemKwp = qs('#system_kwp');

    const inverterBrand = qs('[name="inverter_brand"]');
    const inverterKw = qs('[name="system_kw_ac"]');

    const batteryBrand = qs('[name="battery_brand"]');
    const batteryKwh = qs('[name="battery_kwh"]');

    function calcSystem(){
        const wp = Number(panelWp?.value || 0);
        const qty = Number(panelQty?.value || 0);
        const kwp = wp > 0 && qty > 0 ? (wp * qty / 1000) : Number(systemKwp?.value || 0);

        if (systemKwp && wp > 0 && qty > 0) {
            systemKwp.value = kwp.toFixed(3).replace(/\.?0+$/, '');
        }

        setText('summarySystemKwp', kwp ? kwp.toFixed(3).replace(/\.?0+$/, '') : '0');
        setText('summaryPanelBrand', panelBrand?.value || '—');
        setText('summaryPanelWp', panelWp?.value || '0');
        setText('summaryPanelQty', panelQty?.value || '0');

        setText('summaryInverterBrand', inverterBrand?.value || '—');
        setText('summaryInverterKw', inverterKw?.value || '0');

        setText('summaryBatteryBrand', batteryBrand?.value || '—');
        setText('summaryBatteryKwh', batteryKwh?.value || '0');
    }

    [panelBrand, panelWp, panelQty, systemKwp, inverterBrand, inverterKw, batteryBrand, batteryKwh].forEach(el => {
        el?.addEventListener('input', calcSystem);
        el?.addEventListener('change', calcSystem);
    });

    const completedAt = qs('#completed_at');
    const warrantyTo = qs('#warranty_to');
    const w1 = qs('#warranty_reminder_1_at');
    const w2 = qs('#warranty_reminder_2_at');
    const w3 = qs('#warranty_reminder_3_at');
    const installedAt = qs('#installed_at');

    function addMonths(dateString, months){
        if (!dateString) return '';
        const d = new Date(dateString + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return '';
        d.setMonth(d.getMonth() + months);
        return d.toISOString().slice(0, 10);
    }

    completedAt?.addEventListener('change', () => {
        const base = completedAt.value;
        if (!base) return;

        if (installedAt && !installedAt.value) installedAt.value = base;
        if (w1 && !w1.value) w1.value = addMonths(base, 6);
        if (w2 && !w2.value) w2.value = addMonths(base, 12);
        if (w3 && !w3.value) w3.value = addMonths(base, 24);
        if (warrantyTo && !warrantyTo.value) warrantyTo.value = addMonths(base, 60);
    });

    renumberTerms();
    calcFinance();
    calcSystem();
})();
</script>
@endsection