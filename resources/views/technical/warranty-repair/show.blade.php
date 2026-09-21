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
    $site = $claim->site;
    $qStatus = ['draft'=>'Nháp','sent'=>'Đã gửi khách','approved'=>'Khách đồng ý','rejected'=>'Khách từ chối','superseded'=>'Đã thay bằng version mới'];
    $partStatus = ['planned'=>'Chờ Kho giữ','reserved'=>'Đã giữ','issued'=>'Đã xuất','closed'=>'Đã quyết toán','released'=>'Đã nhả'];
    $eligLabel = ['out_of_warranty'=>'Hết bảo hành','no_record'=>'Không có hồ sơ bảo hành','in_warranty_out_of_scope'=>'Còn bảo hành nhưng lỗi ngoài phạm vi'][$claim->warranty_eligibility] ?? '—';
    $cur = $current;
    $curItems = $cur ? ($quotationItems[$cur->id] ?? collect()) : collect();
    $formItems = old('items', $cur && $cur->status === 'draft' ? $curItems->map(fn ($i) => ['product_id' => $i->product_id, 'name' => $i->name, 'quantity' => (float) $i->quantity, 'unit_price' => (float) $i->unit_price])->all() : []);
    while (count($formItems) < 3) { $formItems[] = ['product_id' => '', 'name' => '', 'quantity' => '', 'unit_price' => '']; }
@endphp
<div class="wx-page"><div class="wx-shell">
    @if(session('success'))<div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="wx-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Không thực hiện được</strong><ul>@foreach($errors->getBags() as $bag)@foreach($bag->all() as $e)<li>{{ $e }}</li>@endforeach @endforeach</ul></div></div>@endif

    @include('technical.warranty._tabs')

    <header class="wx-detail-head" style="margin-bottom:12px">
        <div class="wx-detail-title">
            <a href="{{ route('ky-thuat.repair.index') }}"><i class="bi bi-arrow-left"></i> Danh sách sửa chữa</a>
            <div class="wx-kicker">SỬA CHỮA TÍNH PHÍ · 10 BƯỚC</div>
            <h1 style="font-size:22px">{{ $claim->claim_code }}</h1>
            <p>{{ $site ? (($site->project_code ? $site->project_code.' · ' : '').$site->name) : 'Không gắn công trình' }}</p>
        </div>
        <div class="wx-detail-badges"><span class="wx-pill {{ $tone[$claim->status] ?? 'gray' }}">{{ $statuses[$claim->status] ?? $claim->status }}</span><span class="wx-pill mini">{{ $eligLabel }}</span></div>
    </header>

    @include('technical.warranty._timeline')

    <div class="wx2-layout">
    <main>
        <section class="wx2-card"><h2>Thông tin phiếu</h2>
            <dl class="wx2-grid">
                <div><dt>Khách hàng</dt><dd>{{ $device->customer_name ?? ($site->contact_name ?? '—') }}<br><small>{{ $device->customer_phone ?? ($site->contact_phone ?? '') }}</small></dd></div>
                <div><dt>Công trình / Đơn hàng</dt><dd>{{ $site->name ?? '—' }} / {{ $claim->order->order_code ?? ($device->order_code ?? '—') }}</dd></div>
                <div><dt>Sản phẩm</dt><dd>{{ $device->product_name ?? '—' }}<br><small>{{ $device->sku ?? '' }}</small></dd></div>
                <div><dt>Serial</dt><dd><code>{{ $claim->serial_code }}</code></dd></div>
                <div><dt>Bảo hành</dt><dd>{{ $eligLabel }}<br><small>{{ $device->warranty_start_at ?? '—' }} → {{ $device->warranty_end_at ?? '—' }}</small>@if($claim->out_of_scope_reason)<br><small>Lý do ngoài phạm vi: {{ $claim->out_of_scope_reason }}</small>@endif</dd></div>
                <div><dt>Ngày tiếp nhận</dt><dd>{{ optional($claim->received_at)->format('d/m/Y') }}</dd></div>
                <div><dt>Người phụ trách</dt><dd>{{ $claim->assignee->name ?? 'Chưa phân công' }}</dd></div>
                <div><dt>Người tạo</dt><dd>{{ $claim->creator->name ?? '—' }}</dd></div>
                <div><dt>Chi phí (báo giá hiện tại)</dt><dd>{{ $cur ? $money($cur->total_amount) : '—' }}@if($claim->final_cost)<br><small>Chi phí cuối: {{ $money($claim->final_cost) }}</small>@endif</dd></div>
            </dl>
            <h3>Hiện tượng</h3><p>{{ $claim->issue_description }}</p>
            @if($claim->diagnosis)<h3>Chẩn đoán</h3><p>{{ $claim->diagnosis }}</p><h3>Nguyên nhân</h3><p>{{ $claim->diagnosis_cause }}</p><h3>Phương án sửa</h3><p>{{ $claim->proposed_solution }}</p>
                @if($claim->parts_needed)<h3>Linh kiện cần thay</h3><p>{{ $claim->parts_needed }}</p>@endif
                <small>Dự kiến {{ $claim->est_repair_hours ?? '—' }} giờ @if($claim->tech_note && $canViewInternal) · Ghi chú kỹ thuật: {{ $claim->tech_note }}@endif</small>@endif
            @if($canViewInternal && $claim->internal_note)<h3>Ghi chú nội bộ</h3><p style="white-space:pre-line">{{ $claim->internal_note }}</p>@endif
        </section>

        <section class="wx2-card"><h2>Báo giá sửa chữa</h2>
            @if($cur)
                <div class="wx2-info">Version hiện tại: <b>v{{ $cur->version }}</b> — {{ $qStatus[$cur->status] ?? $cur->status }}@if($cur->decision_at) · khách xác nhận {{ $fmtDt($cur->decision_at) }} ({{ $methods[$cur->decision_method] ?? $cur->decision_method }})@endif @if($cur->locked_at)· <b>đã khóa</b>@endif</div>
                <table class="wx2-table"><thead><tr><th>Linh kiện / hạng mục</th><th class="wx2-money">SL</th><th class="wx2-money">Đơn giá</th><th class="wx2-money">Thành tiền</th></tr></thead><tbody>
                @foreach($curItems as $i)<tr><td>{{ $i->name }}<small>{{ $i->sku }}</small></td><td class="wx2-money">{{ (float) $i->quantity }}</td><td class="wx2-money">{{ $money($i->unit_price) }}</td><td class="wx2-money">{{ $money($i->line_total) }}</td></tr>@endforeach
                <tr><td colspan="3" class="wx2-money">Tổng linh kiện</td><td class="wx2-money">{{ $money($cur->parts_total) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Công sửa</td><td class="wx2-money">{{ $money($cur->labor_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Phí onsite</td><td class="wx2-money">{{ $money($cur->onsite_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Vận chuyển</td><td class="wx2-money">{{ $money($cur->shipping_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Chi phí phát sinh</td><td class="wx2-money">{{ $money($cur->extra_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money">Giảm giá</td><td class="wx2-money">− {{ $money($cur->discount_amount) }}</td></tr>
                <tr><td colspan="3" class="wx2-money"><b>TỔNG CHI PHÍ SỬA CHỮA</b></td><td class="wx2-money"><b>{{ $money($cur->total_amount) }}</b></td></tr></tbody></table>
                <small style="color:#64748b">Chi phí = Linh kiện + Công sửa + Phí onsite + Vận chuyển + Phát sinh − Giảm giá (hệ thống tự tính).</small>
            @else<p style="color:#64748b;font-size:13px">Chưa có báo giá.</p>@endif
            @if($quotations->count() > 1)
                <h3>Lịch sử version</h3><ul class="wx2-hist">@foreach($quotations as $q)<li>v{{ $q->version }} · {{ $qStatus[$q->status] ?? $q->status }} · {{ $money($q->total_amount) }} · {{ $fmtDt($q->created_at) }}</li>@endforeach</ul>
            @endif
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
        <div class="wx2-card"><h2>Hành động</h2>

        @if($can['diagnose'])
            <div class="wx2-actions-box"><h4>Bước 3 · Chẩn đoán kỹ thuật</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.diagnosis', $claim) }}">@csrf
                    <label>Tình trạng / chẩn đoán<textarea name="diagnosis" rows="2" required>{{ old('diagnosis', $claim->diagnosis) }}</textarea></label>
                    <label>Nguyên nhân lỗi<textarea name="diagnosis_cause" rows="2" required>{{ old('diagnosis_cause', $claim->diagnosis_cause) }}</textarea></label>
                    <label>Linh kiện cần thay<textarea name="parts_needed" rows="2">{{ old('parts_needed', $claim->parts_needed) }}</textarea></label>
                    <label>Phương án sửa<textarea name="proposed_solution" rows="2" required>{{ old('proposed_solution', $claim->proposed_solution) }}</textarea></label>
                    <div class="wx2-row2"><label>Thời gian dự kiến (giờ)<input type="number" step="0.5" min="0" name="est_repair_hours" value="{{ old('est_repair_hours', $claim->est_repair_hours) }}"></label>
                        <label>Ghi chú kỹ thuật<input name="tech_note" value="{{ old('tech_note', $claim->tech_note) }}"></label></div>
                    <button class="wx-btn primary small" type="submit">Lưu chẩn đoán</button></form></div>
        @endif

        @if($can['quote'])
            <div class="wx2-actions-box"><h4>Bước 4 · Lập báo giá {{ $cur && $cur->status !== 'draft' ? '(tạo version mới — khách phải xác nhận lại)' : '' }}</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.quotation', $claim) }}">@csrf
                    <table class="wx2-table wx2-items"><thead><tr><th>Linh kiện</th><th>SL</th><th>Đơn giá</th></tr></thead><tbody id="qItems">
                    @foreach($formItems as $idx => $it)
                        <tr><td><select name="items[{{ $idx }}][product_id]" onchange="var o=this.options[this.selectedIndex];if(this.value){this.closest('tr').querySelector('.nm').value=o.text}"><option value="">— tự nhập —</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected((string)($it['product_id'] ?? '')===(string)$p->id)>{{ $p->name }}</option>@endforeach</select>
                            <input class="nm" name="items[{{ $idx }}][name]" value="{{ $it['name'] ?? '' }}" placeholder="Tên hạng mục"></td>
                            <td><input name="items[{{ $idx }}][quantity]" value="{{ $it['quantity'] ?? '' }}" style="width:60px"></td><td><input name="items[{{ $idx }}][unit_price]" value="{{ $it['unit_price'] ?? '' }}" style="width:90px"></td></tr>
                    @endforeach</tbody></table>
                    <div class="wx2-row2"><label>Công sửa<input name="labor_amount" value="{{ old('labor_amount', $cur->labor_amount ?? 0) }}"></label><label>Phí onsite<input name="onsite_amount" value="{{ old('onsite_amount', $cur->onsite_amount ?? 0) }}"></label>
                        <label>Vận chuyển<input name="shipping_amount" value="{{ old('shipping_amount', $cur->shipping_amount ?? 0) }}"></label><label>Chi phí phát sinh<input name="extra_amount" value="{{ old('extra_amount', $cur->extra_amount ?? 0) }}"></label>
                        <label>Giảm giá<input name="discount_amount" value="{{ old('discount_amount', $cur->discount_amount ?? 0) }}"></label></div>
                    <label>Ghi chú báo giá<textarea name="note" rows="2">{{ old('note', $cur->note ?? '') }}</textarea></label>
                    <small style="color:#64748b">Tổng tiền do hệ thống tính khi lưu.</small>
                    <button class="wx-btn primary small" type="submit">Lưu báo giá</button></form>
                @if($can['send'])<form class="wx2-form" style="margin-top:8px" method="POST" action="{{ route('ky-thuat.repair.quotation.send', $claim) }}">@csrf<button class="wx-btn secondary small" type="submit"><i class="bi bi-send"></i>Gửi báo giá cho khách</button></form>@endif
            </div>
        @endif

        @if($can['decide'])
            <div class="wx2-actions-box"><h4>Bước 5 · Khách xác nhận báo giá v{{ $cur->version ?? '' }}</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.decision', $claim) }}">@csrf
                    <div class="wx2-row2"><label>Quyết định<select name="decision"><option value="approved">Khách đồng ý</option><option value="rejected">Khách từ chối</option></select></label>
                        <label>Phương thức<select name="method">@foreach($methods as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label></div>
                    <label>Ngày xác nhận<input type="datetime-local" name="decided_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
                    <label>Ghi chú<textarea name="note" rows="2"></textarea></label><button class="wx-btn primary small" type="submit">Ghi nhận</button></form></div>
        @endif

        @if($can['reserve_parts'])
            <div class="wx2-actions-box"><h4>Kho · Giữ linh kiện</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.parts.reserve', $claim) }}">@csrf
                    <label>Kho<select name="warehouse_id" required><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
                    <button class="wx-btn primary small" type="submit">Giữ linh kiện</button></form></div>
        @endif
        @if($can['issue_parts'])
            <div class="wx2-actions-box"><h4>Kho · Xuất linh kiện</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.parts.issue', $claim) }}" onsubmit="return confirm('Xuất linh kiện? Tồn kho sẽ giảm.')">@csrf<button class="wx-btn primary small" type="submit">Xác nhận xuất linh kiện</button></form></div>
        @endif
        @if($can['return_parts'])
            <div class="wx2-actions-box"><h4>Kho · Hoàn kho linh kiện dư</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.parts.return', $claim) }}">@csrf<button class="wx-btn secondary small" type="submit">Hoàn kho phần dư (đã xuất − đã dùng)</button></form></div>
        @endif

        @if($can['start'])
            <div class="wx2-actions-box"><h4>Bước 7 · {{ $claim->status === 'qa_failed' ? 'Sửa lại sau QA không đạt' : 'Bắt đầu sửa chữa' }}</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.start', $claim) }}">@csrf<button class="wx-btn primary small" type="submit"><i class="bi bi-tools"></i>Bắt đầu sửa</button></form></div>
        @endif
        @if($can['update'])
            <div class="wx2-actions-box"><h4>Cập nhật sửa chữa</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.progress', $claim) }}">@csrf
                    <label>Nội dung đã thực hiện<textarea name="repair_work_done" rows="3" required>{{ old('repair_work_done', $claim->repair_work_done) }}</textarea></label>
                    @foreach($parts->where('qty_issued', '>', 0) as $p)<label>Đã dùng: {{ $p->name }} (xuất {{ (float) $p->qty_issued }})<input type="number" step="0.001" min="0" name="parts_used[{{ $p->product_id }}]" value="{{ old('parts_used.'.$p->product_id, (float) $p->qty_used) }}"></label>@endforeach
                    <div class="wx2-row2"><label>Thời gian sửa (giờ)<input type="number" step="0.5" min="0" name="repair_hours_actual" value="{{ old('repair_hours_actual', $claim->repair_hours_actual) }}"></label><label>Vấn đề phát sinh<input name="repair_issues" value="{{ old('repair_issues', $claim->repair_issues) }}"></label></div>
                    <button class="wx-btn secondary small" type="submit">Lưu tiến độ</button></form>
                <form class="wx2-form" style="margin-top:8px" method="POST" action="{{ route('ky-thuat.repair.qa.submit', $claim) }}">@csrf<button class="wx-btn primary small" type="submit">Chuyển sang kiểm tra (QA)</button></form></div>
        @endif
        @if($can['qa'])
            <div class="wx2-actions-box"><h4>Bước 8 · Kiểm tra sau sửa</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.qa', $claim) }}">@csrf
                    <label>Kết quả<select name="result"><option value="pass">Đạt</option><option value="fail">Không đạt (quay lại sửa)</option></select></label>
                    <label>Thông số kiểm tra<textarea name="measurements" rows="2"></textarea></label><label>Ghi chú (bắt buộc nếu không đạt)<textarea name="note" rows="2"></textarea></label>
                    <button class="wx-btn primary small" type="submit">Ghi nhận kết quả</button></form></div>
        @endif
        @if($can['handover'])
            <div class="wx2-actions-box"><h4>Bước 9 · Bàn giao khách hàng</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.handover', $claim) }}">@csrf
                    <div class="wx2-row2"><label>Ngày bàn giao<input type="datetime-local" name="handed_over_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label><label>Người nhận<input name="handover_receiver_name" required></label></div>
                    <label>Tình trạng thiết bị<input name="handover_condition" required></label><label>Kết quả cuối<textarea name="handover_result" rows="2" required></textarea></label>
                    <label>Hướng dẫn sử dụng<textarea name="handover_guidance" rows="2"></textarea></label><label>Ghi chú<textarea name="handover_note" rows="2"></textarea></label>
                    <button class="wx-btn primary small" type="submit">Bàn giao</button></form></div>
        @endif
        @if($can['complete'])
            <div class="wx2-actions-box"><h4>Bước 10 · Hoàn tất</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.complete', $claim) }}" onsubmit="return confirm('Hoàn tất & khóa báo giá cuối? Không thể hoàn tất lần 2.')">@csrf<button class="wx-btn primary small" type="submit"><i class="bi bi-flag"></i>Hoàn tất phiếu</button></form></div>
        @endif
        @if($can['cancel'])
            <div class="wx2-actions-box"><h4>Hủy phiếu</h4>
                <form class="wx2-form" method="POST" action="{{ route('ky-thuat.repair.cancel', $claim) }}" onsubmit="return confirm('Hủy phiếu sửa chữa?')">@csrf<label>Lý do<textarea name="reason" rows="2" required></textarea></label><button class="wx-btn ghost small" type="submit">Hủy phiếu</button></form></div>
        @endif
        @if(! collect($can)->contains(true))<p style="font-size:12.5px;color:#64748b;margin:0">Hiện chưa có hành động nào dành cho bạn ở bước “{{ $statuses[$claim->status] ?? $claim->status }}”.</p>@endif
        </div>
    </aside>
    </div>
</div></div>
@endsection
