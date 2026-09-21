@extends('layouts.app')

@section('title', 'Sửa chữa tính phí '.($claim->claim_code ?: '#'.$claim->id))

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/warranty-workflow-v2.css') }}?v={{ file_exists(public_path('css/warranty-workflow-v2.css')) ? filemtime(public_path('css/warranty-workflow-v2.css')) : time() }}">
@endsection

@section('content')
@php
    $tone = ['diagnosing'=>'amber','quotation_draft'=>'cyan','waiting_customer_confirmation'=>'violet','quotation_rejected'=>'red','approved_for_repair'=>'green','waiting_parts'=>'orange',
             'repairing'=>'amber','qa_testing'=>'blue','qa_failed'=>'red','ready_handover'=>'green','handed_over'=>'cyan','completed'=>'green','cancelled'=>'gray'];
    $money = fn ($v) => number_format((float) $v, 0, ',', '.').' đ';
    $fmtDt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $qStatus = ['draft'=>'Nháp','sent'=>'Đã gửi khách','approved'=>'Khách đồng ý','rejected'=>'Khách từ chối','superseded'=>'Đã thay bằng version mới'];
    $partStatus = ['planned'=>'Chờ Kho giữ','reserved'=>'Đã giữ','issued'=>'Đã xuất','closed'=>'Đã quyết toán','released'=>'Đã nhả'];
    $eligLabel = ['out_of_warranty'=>'Hết bảo hành','no_record'=>'Không có hồ sơ bảo hành','in_warranty_out_of_scope'=>'Còn bảo hành, lỗi ngoài phạm vi','external_device'=>'Thiết bị ngoài hệ thống'][$claim->warranty_eligibility] ?? '—';
    $cur = $current;
    $curItems = $cur ? ($quotationItems[$cur->id] ?? collect()) : collect();
    $formItems = $cur && $cur->status === 'draft' ? $curItems->map(fn ($i) => ['product_id' => $i->product_id, 'name' => $i->name, 'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price])->all() : [];
    while (count($formItems) < 2) { $formItems[] = ['product_id' => '', 'name' => '', 'quantity' => '', 'unit_price' => '']; }
    $stepActions = [];
    if ($can['diagnose']) { $stepActions['diagnosis'] = ['modal' => 'mDiag', 'label' => 'Chẩn đoán']; }
    if ($can['quote']) { $stepActions['quotation'] = ['modal' => 'mQuote', 'label' => 'Lập báo giá']; }
    if ($can['decide']) { $stepActions['customer'] = ['modal' => 'mDecision', 'label' => 'Ghi nhận khách xác nhận']; }
    if ($can['reserve_parts']) { $stepActions['parts'] = ['modal' => 'mPartsReserve', 'label' => 'Giữ linh kiện']; }
    if ($can['issue_parts']) { $stepActions['parts'] = ['modal' => 'mPartsIssue', 'label' => 'Xuất linh kiện']; }
    if ($can['start']) { $stepActions['parts'] = $stepActions['parts'] ?? ['modal' => 'mStart', 'label' => 'Bắt đầu sửa']; $stepActions['repair'] = ['modal' => 'mStart', 'label' => 'Bắt đầu sửa']; }
    if ($can['update']) { $stepActions['repair'] = ['modal' => 'mProgress', 'label' => 'Cập nhật sửa chữa']; }
    if ($can['qa']) { $stepActions['qa'] = ['modal' => 'mQa', 'label' => 'Kiểm tra sau sửa']; }
    if ($can['handover']) { $stepActions['handover'] = ['modal' => 'mHandover', 'label' => 'Bàn giao']; }
    if ($can['complete']) { $stepActions['complete'] = ['modal' => 'mComplete', 'label' => 'Hoàn tất']; }
@endphp
<div class="wx-page"><div class="wx-shell">
    @if(session('success'))<div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif

    @include('technical.warranty._tabs')

    <header class="wx-detail-head" style="margin-bottom:12px">
        <div class="wx-detail-title">
            <a href="{{ route('ky-thuat.repair.index') }}"><i class="bi bi-arrow-left"></i> Danh sách sửa chữa</a>
            <div class="wx-kicker">SỬA CHỮA TÍNH PHÍ · 9 BƯỚC</div>
            <h1 style="font-size:22px">{{ $claim->claim_code }}</h1>
            <p>{{ $claim->customer_name }} · {{ trim(($claim->device_type ?: '').' '.($claim->device_brand ?: '').' '.($claim->device_model ?: '')) }}</p>
        </div>
        <div class="wx-detail-badges"><span class="wx-pill {{ $tone[$claim->status] ?? 'gray' }}">{{ $statuses[$claim->status] ?? $claim->status }}</span><span class="wx-pill mini">{{ $eligLabel }}</span></div>
    </header>

    @include('technical.warranty._timeline')

    <div class="wx2-layout">
    <main>
        <section class="wx2-card"><h2>Khách hàng & thiết bị</h2>
            <dl class="wx2-grid">
                <div><dt>Khách hàng</dt><dd>{{ $claim->customer_name ?: '—' }}@if($claim->customer_company)<br><small>{{ $claim->customer_company }}</small>@endif</dd></div>
                <div><dt>SĐT / Email</dt><dd>{{ $claim->customer_phone ?: '—' }}<br><small>{{ $claim->customer_email }}</small></dd></div>
                <div><dt>Địa chỉ</dt><dd>{{ $claim->customer_address ?: '—' }}</dd></div>
                <div><dt>Thiết bị</dt><dd>{{ trim(($claim->device_type ?: '').' '.($claim->device_brand ?: '')) ?: '—' }}<br><small>Model: {{ $claim->device_model ?: '—' }}</small></dd></div>
                <div><dt>Serial</dt><dd>@if($claim->serial_code)<code>{{ $claim->serial_code }}</code>@else Không có @endif</dd></div>
                <div><dt>Bảo hành</dt><dd>{{ $eligLabel }}@if($claim->out_of_scope_reason)<br><small>Lý do ngoài phạm vi: {{ $claim->out_of_scope_reason }}</small>@endif</dd></div>
                <div><dt>Ngày nhận</dt><dd>{{ optional($claim->received_at)->format('d/m/Y') }}</dd></div>
                <div><dt>Người giao / nhận</dt><dd>{{ $claim->delivered_by ?: '—' }} / {{ $claim->creator->name ?? '—' }}</dd></div>
                <div><dt>Kỹ thuật phụ trách</dt><dd>{{ $claim->assignee->name ?? 'Chưa phân công' }}</dd></div>
                <div><dt>Báo giá hiện tại</dt><dd>{{ $cur ? $money($cur->total_amount) : '—' }}@if($claim->final_cost)<br><small>Chi phí cuối: {{ $money($claim->final_cost) }}</small>@endif</dd></div>
            </dl>
            <h3>Tình trạng khi tiếp nhận</h3><p>{{ $claim->received_condition ?: '—' }}</p>
            @if($claim->device_accessories)<h3>Phụ kiện giao kèm</h3><p>{{ $claim->device_accessories }}</p>@endif
            <h3>Lỗi khách báo</h3><p>{{ $claim->issue_description }}</p>
            @if($canViewInternal && $claim->internal_note)<h3>Ghi chú nội bộ</h3><p style="white-space:pre-line">{{ $claim->internal_note }}</p>@endif
        </section>

        @if($reference)
        <section class="wx2-card wx-readonly"><h2>Thông tin tham chiếu trong hệ thống</h2>
            <dl class="wx2-grid">
                <div><dt>Sản phẩm CRM</dt><dd>{{ $reference['product_name'] }}<br><small>{{ $reference['sku'] }}</small></dd></div>
                <div><dt>Bảo hành</dt><dd>{{ $reference['warranty_label'] }}<br><small>{{ $reference['warranty_start_at'] }} → {{ $reference['warranty_end_at'] }}</small></dd></div>
                @if($reference['order_code'])<div><dt>Thiết bị từng được bán</dt><dd>Đơn {{ $reference['order_code'] }}</dd></div>@endif
                @if($reference['site_name'])<div><dt>Từng lắp tại</dt><dd>{{ $reference['site_name'] }}</dd></div>@endif
            </dl>
        </section>
        @endif

        @if($claim->diagnosis)
        <section class="wx2-card"><h2>Chẩn đoán kỹ thuật</h2>
            <h3>Hiện tượng kiểm tra</h3><p>{{ $claim->diagnosis }}</p>
            <h3>Nguyên nhân</h3><p>{{ $claim->diagnosis_cause }}</p>
            @if($claim->diagnosis_conclusion)<h3>Kết luận</h3><p>{{ $claim->diagnosis_conclusion }}</p>@endif
            @if($claim->parts_needed)<h3>Linh kiện dự kiến cần thay</h3><p>{{ $claim->parts_needed }}</p>@endif
            <h3>Phương án sửa</h3><p>{{ $claim->proposed_solution }}</p>
            <small>Dự kiến {{ $claim->est_repair_hours ?? '—' }} giờ @if($claim->tech_note && $canViewInternal) · Ghi chú: {{ $claim->tech_note }}@endif</small>
        </section>
        @endif

        <section class="wx2-card"><h2>Báo giá sửa chữa</h2>
            @if($cur)
                <div class="wx2-info">Version hiện tại: <b>v{{ $cur->version }}</b> — {{ $qStatus[$cur->status] ?? $cur->status }}@if($cur->decision_at) · khách xác nhận {{ $fmtDt($cur->decision_at) }} ({{ $methods[$cur->decision_method] ?? $cur->decision_method }})@endif @if($cur->locked_at)· <b>đã khóa</b>@endif</div>
                <table class="wx2-table"><thead><tr><th>Nội dung</th><th class="wx2-money">SL</th><th class="wx2-money">Đơn giá</th><th class="wx2-money">Thành tiền</th></tr></thead><tbody>
                @foreach($curItems as $i)<tr><td>{{ $i->name }}<small>{{ $i->sku }}</small></td><td class="wx2-money">{{ (float) $i->quantity }}</td><td class="wx2-money">{{ $money($i->unit_price) }}</td><td class="wx2-money">{{ $money($i->line_total) }}</td></tr>@endforeach
                <tr><td colspan="3" class="wx2-money">Tổng linh kiện</td><td class="wx2-money">{{ $money($cur->parts_total) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Công sửa</td><td class="wx2-money">{{ $money($cur->labor_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Phí onsite</td><td class="wx2-money">{{ $money($cur->onsite_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Vận chuyển</td><td class="wx2-money">{{ $money($cur->shipping_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Chi phí phát sinh</td><td class="wx2-money">{{ $money($cur->extra_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Giảm giá</td><td class="wx2-money">− {{ $money($cur->discount_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money"><b>TỔNG CHI PHÍ SỬA CHỮA</b></td><td class="wx2-money"><b>{{ $money($cur->total_amount) }}</b></td></tr></tbody></table>
                <small style="color:#64748b">Chi phí = Linh kiện + Công sửa + Onsite + Vận chuyển + Phát sinh − Giảm giá (hệ thống tự tính).</small>
            @else<p style="color:#64748b;font-size:13px">Chưa có báo giá.</p>@endif
            @if($quotations->count() > 1)<h3>Lịch sử version</h3><ul class="wx2-hist">@foreach($quotations as $q)<li>v{{ $q->version }} · {{ $qStatus[$q->status] ?? $q->status }} · {{ $money($q->total_amount) }} · {{ $fmtDt($q->created_at) }}</li>@endforeach</ul>@endif
        </section>

        @if($parts->isNotEmpty())
        <section class="wx2-card"><h2>Linh kiện</h2>
            <table class="wx2-table"><thead><tr><th>Linh kiện</th><th>Kho</th><th class="wx2-money">Kế hoạch</th><th class="wx2-money">Đã xuất</th><th class="wx2-money">Đã dùng</th><th class="wx2-money">Hoàn kho</th><th>Trạng thái</th></tr></thead><tbody>
            @foreach($parts as $p)<tr><td>{{ $p->name }}</td><td>{{ $p->warehouse_name ?: '—' }}</td><td class="wx2-money">{{ (float) $p->qty_planned }}</td><td class="wx2-money">{{ (float) $p->qty_issued }}</td><td class="wx2-money">{{ (float) $p->qty_used }}</td><td class="wx2-money">{{ (float) $p->qty_returned }}</td><td>{{ $partStatus[$p->status] ?? $p->status }}</td></tr>@endforeach</tbody></table>
        </section>
        @endif

        @if($claim->repair_started_at)
        <section class="wx2-card"><h2>Sửa chữa</h2>
            <dl class="wx2-grid"><div><dt>Bắt đầu</dt><dd>{{ $fmtDt($claim->repair_started_at) }}</dd></div><div><dt>Thời gian sửa thực tế</dt><dd>{{ $claim->repair_hours_actual ?? '—' }} giờ</dd></div></dl>
            <h3>Nội dung đã thực hiện</h3><p style="white-space:pre-line">{{ $claim->repair_work_done ?: '—' }}</p>
            @if($claim->repair_issues)<h3>Vấn đề phát sinh</h3><p>{{ $claim->repair_issues }}</p>@endif
        </section>
        @endif

        @if($qa->isNotEmpty())
        <section class="wx2-card"><h2>Kiểm tra sau sửa (QA)</h2><ul class="wx2-hist">
            @foreach($qa as $q)<li><b style="color:{{ $q->result === 'pass' ? '#16a34a' : '#dc2626' }}">{{ $q->result === 'pass' ? 'ĐẠT' : 'KHÔNG ĐẠT' }}</b> · {{ $q->tester }} · {{ $fmtDt($q->tested_at) }}
                @if($q->measurements)<div class="meta">Thông số: {{ $q->measurements }}</div>@endif @if($q->note)<div class="meta">{{ $q->note }}</div>@endif</li>@endforeach</ul></section>
        @endif

        @if($claim->handed_over_at)
        <section class="wx2-card"><h2>Bàn giao khách hàng</h2>
            <dl class="wx2-grid"><div><dt>Ngày bàn giao</dt><dd>{{ $fmtDt($claim->handed_over_at) }}</dd></div><div><dt>Người nhận</dt><dd>{{ $claim->handover_receiver_name }}</dd></div>
                <div><dt>Tình trạng</dt><dd>{{ $claim->handover_condition }}</dd></div><div><dt>Kết quả cuối</dt><dd>{{ $claim->handover_result }}</dd></div>
                <div><dt>Hướng dẫn sử dụng</dt><dd>{{ $claim->handover_guidance ?: '—' }}</dd></div></dl></section>
        @endif

        <section class="wx2-card"><h2>Điều kiện hoàn tất</h2><ul class="wx2-check">
            @foreach($checklist as $it)<li><i class="bi {{ $it['ok'] ? 'bi-check-circle-fill ok' : 'bi-x-circle-fill no' }}"></i><span>{{ $it['label'] }}@if($it['detail']) <small style="color:#64748b">· {{ $it['detail'] }}</small>@endif</span></li>@endforeach</ul></section>

        @if($priorClaims->isNotEmpty())<section class="wx2-card"><h2>Lịch sử sửa chữa/đổi của serial này</h2><ul class="wx2-hist">@foreach($priorClaims as $pc)<li>{{ $pc->claim_code }} · {{ $pc->claim_type === 'paid_repair' ? 'Sửa chữa tính phí' : ($pc->claim_type === 'replacement' ? 'Đổi hàng' : $pc->claim_type) }} · {{ $pc->status }} · {{ \Illuminate\Support\Carbon::parse($pc->created_at)->format('d/m/Y') }}</li>@endforeach</ul></section>@endif

        @include('technical.warranty._evidence')
        <section class="wx2-card"><h2>Lịch sử thao tác (không thể sửa/xóa)</h2>@include('technical.warranty._history')</section>
    </main>

    <aside>
        <div class="wx2-card"><h2>Thao tác</h2>
            <div style="display:flex;flex-direction:column;gap:8px">
                @if($can['diagnose'])<button type="button" class="wx-btn primary" data-wx-open="mDiag">Chẩn đoán kỹ thuật</button>@endif
                @if($can['quote'])<button type="button" class="wx-btn primary" data-wx-open="mQuote">{{ $cur && $cur->status !== 'draft' ? 'Lập báo giá version mới' : 'Lập / sửa báo giá' }}</button>@endif
                @if($can['decide'])<button type="button" class="wx-btn primary" data-wx-open="mDecision">Khách xác nhận báo giá</button>@endif
                @if($can['reserve_parts'])<button type="button" class="wx-btn primary" data-wx-open="mPartsReserve">Giữ linh kiện</button>@endif
                @if($can['issue_parts'])<button type="button" class="wx-btn primary" data-wx-open="mPartsIssue">Xuất linh kiện</button>@endif
                @if($can['return_parts'])<button type="button" class="wx-btn secondary" data-wx-open="mPartsReturn">Hoàn kho linh kiện dư</button>@endif
                @if($can['start'])<button type="button" class="wx-btn primary" data-wx-open="mStart">{{ $claim->status === 'qa_failed' ? 'Sửa lại sau QA' : 'Bắt đầu sửa chữa' }}</button>@endif
                @if($can['update'])<button type="button" class="wx-btn primary" data-wx-open="mProgress">Cập nhật sửa chữa</button>
                    <button type="button" class="wx-btn secondary" data-wx-open="mQaSubmit">Chuyển sang kiểm tra (QA)</button>@endif
                @if($can['qa'])<button type="button" class="wx-btn primary" data-wx-open="mQa">Kiểm tra sau sửa</button>@endif
                @if($can['handover'])<button type="button" class="wx-btn primary" data-wx-open="mHandover">Bàn giao khách hàng</button>@endif
                @if($can['complete'])<button type="button" class="wx-btn primary" data-wx-open="mComplete"><i class="bi bi-flag"></i> Hoàn tất</button>@endif
                @if($can['cancel'])<button type="button" class="wx-btn ghost" data-wx-open="mCancel">Hủy phiếu</button>@endif
                @if($can['evidence'])<button type="button" class="wx-btn secondary" data-wx-open="mEvidence"><i class="bi bi-cloud-arrow-up"></i> Tải ảnh / file</button>@endif
                @if(! collect($can)->contains(true))<p style="font-size:12.5px;color:#64748b;margin:0">Hiện chưa có thao tác nào dành cho bạn ở bước “{{ $statuses[$claim->status] ?? $claim->status }}”.</p>@endif
            </div>
        </div>
    </aside>
    </div>
</div></div>

{{-- ===================== POPUP THAO TÁC ===================== --}}
@if($can['diagnose'])
<x-wx-modal id="mDiag" title="Kỹ thuật chẩn đoán" :action="route('ky-thuat.repair.diagnosis', $claim)" submit="LƯU CHẨN ĐOÁN">
    <div class="wx2-form">
        <label>Hiện tượng kiểm tra <b style="color:#dc2626">*</b><textarea name="diagnosis" rows="2">{{ $claim->diagnosis }}</textarea></label>
        <label>Nguyên nhân lỗi <b style="color:#dc2626">*</b><textarea name="diagnosis_cause" rows="2">{{ $claim->diagnosis_cause }}</textarea></label>
        <label>Kết luận<textarea name="diagnosis_conclusion" rows="2">{{ $claim->diagnosis_conclusion }}</textarea></label>
        <label>Linh kiện dự kiến cần thay<textarea name="parts_needed" rows="2">{{ $claim->parts_needed }}</textarea></label>
        <label>Phương án sửa chữa <b style="color:#dc2626">*</b><textarea name="proposed_solution" rows="2">{{ $claim->proposed_solution }}</textarea></label>
        <div class="wx2-row2"><label>Thời gian dự kiến (giờ)<input type="number" step="0.5" min="0" name="est_repair_hours" value="{{ $claim->est_repair_hours }}"></label><label>Ghi chú<input name="tech_note" value="{{ $claim->tech_note }}"></label></div>
        <div class="wx2-info">Ảnh/file chẩn đoán: dùng “Tải ảnh / file” ở cột thao tác.</div>
    </div>
</x-wx-modal>
@endif
@if($can['quote'])
<x-wx-modal id="mQuote" size="xl" :title="'Lập báo giá sửa chữa'.($cur && $cur->status !== 'draft' ? ' (version mới)' : '')" :action="route('ky-thuat.repair.quotation', $claim)" submit="LƯU NHÁP" :chain="route('ky-thuat.repair.quotation.send', $claim)" chain-label="GỬI KHÁCH XÁC NHẬN">
    @if($cur && $cur->status !== 'draft')<div class="wx2-warn">Báo giá v{{ $cur->version }} đã {{ $qStatus[$cur->status] ?? $cur->status }}. Lưu sẽ tạo <b>version mới</b> và khách phải xác nhận lại.</div>@endif
    <div class="wx-section"><h4>Linh kiện</h4>
        <table class="wx2-table wx2-items"><thead><tr><th>Nội dung</th><th style="width:80px">SL</th><th style="width:120px">Đơn giá</th><th class="wx2-money" style="width:120px">Thành tiền</th></tr></thead>
        <tbody id="qItems">
        @foreach($formItems as $idx => $it)
            <tr><td><select class="q-prod" name="items[{{ $idx }}][product_id]"><option value="">— tự nhập —</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected((string)($it['product_id'] ?? '')===(string)$p->id)>{{ $p->name }}</option>@endforeach</select>
                <input class="q-name" name="items[{{ $idx }}][name]" value="{{ $it['name'] ?? '' }}" placeholder="Tên linh kiện / hạng mục"></td>
                <td><input class="q-qty" name="items[{{ $idx }}][quantity]" value="{{ $it['quantity'] ?? '' }}"></td><td><input class="q-price" name="items[{{ $idx }}][unit_price]" value="{{ $it['unit_price'] ?? '' }}"></td><td class="wx2-money q-line">0 đ</td></tr>
        @endforeach
        </tbody></table>
        <button type="button" id="qAddRow" class="wx-btn tiny secondary" style="margin-top:6px">+ Thêm linh kiện</button>
        <template id="qRowTpl"><tr><td><select class="q-prod" name="items[__IDX__][product_id]"><option value="">— tự nhập —</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select><input class="q-name" name="items[__IDX__][name]" placeholder="Tên linh kiện / hạng mục"></td><td><input class="q-qty" name="items[__IDX__][quantity]"></td><td><input class="q-price" name="items[__IDX__][unit_price]"></td><td class="wx2-money q-line">0 đ</td></tr></template>
    </div>
    <div class="wx-section"><h4>Chi phí khác</h4>
        <div class="wx2-form"><div class="wx2-row2"><label>Công sửa<input name="labor_amount" value="{{ $cur->labor_amount ?? 0 }}"></label><label>Phí onsite<input name="onsite_amount" value="{{ $cur->onsite_amount ?? 0 }}"></label></div>
            <div class="wx2-row2"><label>Vận chuyển<input name="shipping_amount" value="{{ $cur->shipping_amount ?? 0 }}"></label><label>Chi phí phát sinh<input name="extra_amount" value="{{ $cur->extra_amount ?? 0 }}"></label></div>
            <label>Giảm giá<input name="discount_amount" value="{{ $cur->discount_amount ?? 0 }}"></label>
            <label>Ghi chú báo giá<textarea name="note" rows="2">{{ $cur->note ?? '' }}</textarea></label></div>
    </div>
    <div class="wx2-info" style="font-size:14px">Tạm tính: linh kiện <b id="qParts">0 đ</b> → <b>TỔNG <span id="qTotal">0 đ</span></b> <small>(hệ thống tính lại chính xác khi lưu)</small></div>
</x-wx-modal>
@endif
@if($can['decide'])
<x-wx-modal id="mDecision" title="Khách xác nhận báo giá" :action="route('ky-thuat.repair.decision', $claim)" submit="XÁC NHẬN">
    <div class="wx2-info">Khách hàng: <b>{{ $claim->customer_name }}</b> · Báo giá v{{ $cur->version ?? '' }} · Tổng tiền <b>{{ $cur ? $money($cur->total_amount) : '' }}</b></div>
    <div class="wx2-form">
        <label>Quyết định của khách<select name="decision"><option value="approved">Đồng ý sửa</option><option value="rejected">Không đồng ý</option></select></label>
        <div class="wx2-row2"><label>Phương thức xác nhận<select name="method">@foreach($methods as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
            <label>Ngày xác nhận<input type="datetime-local" name="decided_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label></div>
        <label>Ghi chú<textarea name="note" rows="2"></textarea></label>
    </div>
</x-wx-modal>
@endif
@if($can['reserve_parts'])
<x-wx-modal id="mPartsReserve" title="Kho giữ linh kiện" :action="route('ky-thuat.repair.parts.reserve', $claim)" submit="GIỮ LINH KIỆN" size="md">
    <div class="wx2-form"><label>Kho<select name="warehouse_id"><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label></div>
</x-wx-modal>
@endif
@if($can['issue_parts'])
<x-wx-modal id="mPartsIssue" title="Xuất linh kiện cho Kỹ thuật" :action="route('ky-thuat.repair.parts.issue', $claim)" submit="XÁC NHẬN XUẤT LINH KIỆN" size="md">
    <ul class="wx2-hist">@foreach($parts->where('status', 'reserved') as $p)<li>{{ $p->name }} × {{ (float) $p->qty_reserved }} · kho {{ $p->warehouse_name }}</li>@endforeach</ul>
    <div class="wx2-warn">Xuất linh kiện làm giảm tồn kho. Người nhận: kỹ thuật phụ trách ({{ $claim->assignee->name ?? '—' }}).</div>
</x-wx-modal>
@endif
@if($can['return_parts'])
<x-wx-modal id="mPartsReturn" title="Hoàn kho linh kiện dư" :action="route('ky-thuat.repair.parts.return', $claim)" submit="HOÀN KHO" size="md">
    <div class="wx2-info">Hoàn kho phần chênh lệch: <b>đã xuất − đã dùng − đã hoàn</b> của từng linh kiện.</div>
</x-wx-modal>
@endif
@if($can['start'])
<x-wx-modal id="mStart" :title="$claim->status === 'qa_failed' ? 'Sửa lại sau QA không đạt' : 'Bắt đầu sửa chữa'" :action="route('ky-thuat.repair.start', $claim)" submit="BẮT ĐẦU SỬA" size="md">
    <div class="wx2-info">Chỉ bắt đầu khi linh kiện đã được Kho xuất đầy đủ (nếu có).</div>
</x-wx-modal>
@endif
@if($can['update'])
<x-wx-modal id="mProgress" title="Cập nhật sửa chữa" :action="route('ky-thuat.repair.progress', $claim)" submit="LƯU TIẾN ĐỘ">
    <div class="wx2-form">
        <label>Công việc đã làm <b style="color:#dc2626">*</b><textarea name="repair_work_done" rows="3">{{ $claim->repair_work_done }}</textarea></label>
        @foreach($parts->where('qty_issued', '>', 0) as $p)<label>Linh kiện thực tế đã dùng: {{ $p->name }} (Kho xuất {{ (float) $p->qty_issued }})<input type="number" step="0.001" min="0" name="parts_used[{{ $p->product_id }}]" value="{{ (float) $p->qty_used }}"></label>@endforeach
        <div class="wx2-row2"><label>Thời gian sửa (giờ)<input type="number" step="0.5" min="0" name="repair_hours_actual" value="{{ $claim->repair_hours_actual }}"></label><label>Vấn đề phát sinh<input name="repair_issues" value="{{ $claim->repair_issues }}"></label></div>
    </div>
</x-wx-modal>
<x-wx-modal id="mQaSubmit" title="Chuyển sang kiểm tra sau sửa" :action="route('ky-thuat.repair.qa.submit', $claim)" submit="CHUYỂN SANG QA" size="md">
    <div class="wx2-info">Phải có nội dung sửa chữa đã ghi nhận. Không thể bàn giao nếu chưa qua QA.</div>
</x-wx-modal>
@endif
@if($can['qa'])
<x-wx-modal id="mQa" title="Kiểm tra sau sửa (QA)" :action="route('ky-thuat.repair.qa', $claim)" submit="GHI NHẬN KẾT QUẢ">
    <div class="wx2-form">
        <label>Kết quả<select name="result"><option value="pass">Đạt</option><option value="fail">Không đạt — quay lại sửa</option></select></label>
        <label>Thông số kiểm tra / checklist<textarea name="measurements" rows="3" placeholder="Vdc, Pac, nhiệt độ, chạy thử…"></textarea></label>
        <label>Ghi chú (bắt buộc nếu không đạt)<textarea name="note" rows="2"></textarea></label>
        <label>Người kiểm tra<input value="{{ auth()->user()->name }}" disabled></label>
    </div>
</x-wx-modal>
@endif
@if($can['handover'])
<x-wx-modal id="mHandover" title="Bàn giao thiết bị cho khách" :action="route('ky-thuat.repair.handover', $claim)" submit="XÁC NHẬN BÀN GIAO">
    <div class="wx2-form">
        <div class="wx2-row2"><label>Ngày giao<input type="datetime-local" name="handed_over_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label><label>Người giao<input value="{{ auth()->user()->name }}" disabled></label></div>
        <label>Người nhận <b style="color:#dc2626">*</b><input name="handover_receiver_name" value="{{ $claim->customer_name }}"></label>
        <label>Tình trạng thiết bị <b style="color:#dc2626">*</b><input name="handover_condition"></label>
        <label>Kết quả cuối <b style="color:#dc2626">*</b><textarea name="handover_result" rows="2"></textarea></label>
        <label>Hướng dẫn sử dụng<textarea name="handover_guidance" rows="2"></textarea></label>
        <label>Ghi chú<textarea name="handover_note" rows="2"></textarea></label>
    </div>
</x-wx-modal>
@endif
@if($can['complete'])
<x-wx-modal id="mComplete" title="Hoàn tất phiếu sửa chữa" :action="route('ky-thuat.repair.complete', $claim)" submit="HOÀN TẤT PHIẾU" size="md">
    <h3 style="font-size:13px">Checklist trước khi đóng phiếu</h3>
    <ul class="wx2-check">@foreach($checklist as $it)<li><i class="bi {{ $it['ok'] ? 'bi-check-circle-fill ok' : 'bi-x-circle-fill no' }}"></i><span>{{ $it['label'] }}</span></li>@endforeach</ul>
    <div class="wx2-warn">Hoàn tất sẽ khóa báo giá cuối và lưu snapshot chi phí. Không thể hoàn tất lần 2.</div>
</x-wx-modal>
@endif
@if($can['cancel'])
<x-wx-modal id="mCancel" title="Hủy phiếu sửa chữa" :action="route('ky-thuat.repair.cancel', $claim)" submit="XÁC NHẬN HỦY" :danger="true" size="md">
    <div class="wx2-form"><label>Lý do hủy (bắt buộc)<textarea name="reason" rows="3"></textarea></label></div>
</x-wx-modal>
@endif
@if($can['evidence'])
<x-wx-modal id="mEvidence" title="Tải ảnh / file minh chứng" :action="route('ky-thuat.warranty-exchange.evidence.upload', $claim->id)" submit="TẢI LÊN" size="md" :files="true">
    <div class="wx2-form"><label>Chọn tệp (JPG/PNG/WEBP/PDF, tối đa {{ config('warranty.evidence_max_files') }} tệp) — lưu riêng tư<input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label></div>
</x-wx-modal>
@endif
@endsection

@section('scripts')
<script>window.WX = {};</script>
<script src="{{ asset('js/warranty-modals-v2.js') }}?v={{ file_exists(public_path('js/warranty-modals-v2.js')) ? filemtime(public_path('js/warranty-modals-v2.js')) : time() }}"></script>
@endsection
