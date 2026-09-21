@extends('layouts.app')

@section('title', 'Phiếu đổi hàng bảo hành '.($claim->claim_code ?: '#'.$claim->id))

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/warranty-workflow-v2.css') }}?v={{ file_exists(public_path('css/warranty-workflow-v2.css')) ? filemtime(public_path('css/warranty-workflow-v2.css')) : time() }}">
@endsection

@section('content')
@php
    $tone = ['pending_approval'=>'violet','needs_more_information'=>'amber','rejected'=>'red','approved'=>'green','waiting_stock'=>'orange','reserved'=>'cyan','issued'=>'blue',
             'technician_received'=>'blue','replacing'=>'amber','waiting_faulty_return'=>'orange','faulty_returned'=>'cyan','completed'=>'green','cancelled'=>'gray'];
    $site = $claim->site; $order = $claim->order;
    $fmtDt = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d/m/Y H:i') : '—';
    $uname = fn ($id) => $id ? ($userNames[$id] ?? '#'.$id) : '—';
    $faultyLabel = ['pending' => 'Chờ thu hồi', 'returned' => 'Đã thu hồi', 'deferred' => 'Hoãn thu hồi (đang theo dõi)'][$claim->faulty_return_status] ?? '—';
@endphp
<div class="wx-page">
<div class="wx-shell">
    @if(session('success'))<div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())
        <div class="wx-alert danger"><i class="bi bi-exclamation-triangle-fill"></i>
            <div><strong>Không thực hiện được</strong><ul>@foreach($errors->getBags() as $bag)@foreach($bag->all() as $e)<li>{{ $e }}</li>@endforeach @endforeach</ul></div></div>
    @endif

    @include('technical.warranty._tabs')

    <header class="wx-detail-head" style="margin-bottom:12px">
        <div class="wx-detail-title">
            <a href="{{ route('ky-thuat.warranty-exchange.index') }}"><i class="bi bi-arrow-left"></i> Danh sách đề xuất đổi hàng</a>
            <div class="wx-kicker">ĐỔI HÀNG BẢO HÀNH · 11 BƯỚC</div>
            <h1 style="font-size:22px">{{ $claim->claim_code ?: '#'.$claim->id }}</h1>
            <p>{{ $site ? (($site->project_code ? $site->project_code.' · ' : '').$site->name) : ($order && $order->order_code ? 'Đơn hàng '.$order->order_code : 'Chưa có công trình/đơn hàng') }}</p>
        </div>
        <div class="wx-detail-badges">
            <span class="wx-pill {{ $tone[$claim->status] ?? 'gray' }}">{{ $statuses[$claim->status] ?? $claim->status }}</span>
            <span class="wx-pill mini">{{ $priorities[$claim->priority] ?? $claim->priority }}</span>
            @if($claim->warranty_exception)<span class="wx-pill mini amber">Ngoại lệ BH</span>@endif
        </div>
    </header>

    @include('technical.warranty._timeline')

    <div class="wx2-layout">
    <main>
        <section class="wx2-card">
            <h2>Thông tin phiếu</h2>
            <dl class="wx2-grid">
                <div><dt>Khách hàng</dt><dd>{{ $device && $device->customer_name ? $device->customer_name : ($site->contact_name ?? '—') }}<br><small>{{ $device->customer_phone ?? ($site->contact_phone ?? '') }}</small></dd></div>
                <div><dt>Công trình</dt><dd>{{ $site ? ($site->project_code ? $site->project_code.' · ' : '').$site->name : '—' }}</dd></div>
                <div><dt>Đơn hàng</dt><dd>{{ $order->order_code ?? ($device->order_code ?? '—') }}</dd></div>
                <div><dt>Sản phẩm</dt><dd>{{ $device->product_name ?? '—' }}<br><small>{{ $device->sku ?? '' }}</small></dd></div>
                <div><dt>Serial lỗi</dt><dd><code>{{ $claim->serial_code }}</code></dd></div>
                <div><dt>Bảo hành</dt><dd>{{ ['active'=>'Đang hiệu lực','replaced'=>'Đã được thay thế','expired'=>'Hết hạn'][$device->warranty_status ?? ''] ?? ($device->warranty_status ?? 'Chưa rõ') }}<br><small>{{ $device->warranty_start_at ?? '—' }} → {{ $device->warranty_end_at ?? '—' }}</small></dd></div>
                <div><dt>Ngày tiếp nhận</dt><dd>{{ optional($claim->received_at)->format('d/m/Y') ?: '—' }}</dd></div>
                <div><dt>Người phụ trách</dt><dd>{{ $claim->assignee->name ?? $claim->assigned_name ?? 'Chưa phân công' }}</dd></div>
                <div><dt>Người tạo</dt><dd>{{ $claim->creator->name ?? '—' }}</dd></div>
                <div><dt>Người duyệt</dt><dd>{{ $claim->approver->name ?? '—' }}<br><small>{{ $fmtDt($claim->approved_at) }}</small></dd></div>
                @if($canViewCosts)<div><dt>Chi phí dự kiến / thực tế</dt><dd>{{ number_format((float) $claim->estimated_cost, 0, ',', '.') }} / {{ number_format((float) $claim->actual_cost, 0, ',', '.') }} đ</dd></div>@endif
            </dl>
        </section>

        @if($claim->warranty_exception)
        <section class="wx2-card" style="border-color:#fde68a">
            <h2>Ngoại lệ bảo hành</h2>
            <dl class="wx2-grid">
                <div><dt>Lý do</dt><dd>{{ $claim->exception_reason }}</dd></div>
                <div><dt>Người đề nghị</dt><dd>{{ $uname($claim->exception_requested_by) }}<br><small>{{ $fmtDt($claim->exception_requested_at) }}</small></dd></div>
                <div><dt>Người duyệt ngoại lệ</dt><dd>{{ $uname($claim->exception_approved_by) }}<br><small>{{ $fmtDt($claim->exception_approved_at) }}</small></dd></div>
                @if($claim->exception_override)<div><dt>Emergency override</dt><dd>Có — {{ $claim->override_reason }}</dd></div>@endif
            </dl>
        </section>
        @endif

        <section class="wx2-card">
            <h2>Hiện tượng, chẩn đoán & đề xuất</h2>
            <h3>Hiện tượng / lỗi</h3><p>{{ $claim->issue_description }}</p>
            <h3>Chẩn đoán kỹ thuật</h3><p>{{ $claim->diagnosis }}</p>
            <h3>Lý do & phương án đề xuất đổi</h3><p>{{ $claim->proposed_solution }}</p>
            @if($claim->decision_reason)<div class="wx2-warn"><b>Ý kiến người duyệt:</b> {{ $claim->decision_reason }} <small>({{ $uname($claim->decision_by) }} · {{ $fmtDt($claim->decision_at) }})</small></div>@endif
            @if($canViewInternal && $claim->internal_note)<h3>Ghi chú nội bộ</h3><p style="white-space:pre-line">{{ $claim->internal_note }}</p>@endif
        </section>

        <section class="wx2-card">
            <h2>Kho · Serial thay thế · Thu hồi</h2>
            @if($reservation)<div class="wx2-info"><i class="bi bi-lock-fill"></i> Đang GIỮ HÀNG serial <b>{{ $reservation->serial_code }}</b> từ {{ $fmtDt($reservation->reserved_at) }}.</div>@endif
            <dl class="wx2-grid">
                <div><dt>Serial thay thế</dt><dd>@if($claim->replacement_serial_code)<code>{{ $claim->replacement_serial_code }}</code>@elseif($claim->reserved_serial_code)<code>{{ $claim->reserved_serial_code }}</code> (đã giữ)@else Chưa chọn @endif</dd></div>
                <div><dt>Xuất kho</dt><dd>{{ $fmtDt($claim->issued_at) }}<br><small>{{ $uname($claim->issued_by) }}</small></dd></div>
                <div><dt>Kỹ thuật nhận hàng</dt><dd>{{ $fmtDt($claim->tech_received_at) }}<br><small>{{ $uname($claim->tech_received_by) }}@if($claim->tech_delivered_by) · người giao: {{ $claim->tech_delivered_by }}@endif</small></dd></div>
                <div><dt>Thay thiết bị cho khách</dt><dd>{{ $fmtDt($claim->replaced_at) }}<br><small>{{ $uname($claim->replaced_by) }}@if($claim->replace_note) · {{ $claim->replace_note }}@endif</small></dd></div>
                <div><dt>Thu hồi thiết bị lỗi</dt><dd>{{ $faultyLabel }}<br><small>@if($claim->returned_at){{ $fmtDt($claim->returned_at) }} · kho nhận: {{ $uname($claim->faulty_received_by) }} · người trả: {{ $claim->faulty_returned_by }} · tình trạng: {{ $faultyConditions[$claim->faulty_condition] ?? $claim->faulty_condition }}@endif
                    @if($claim->faulty_deferred_reason) · hoãn: {{ $claim->faulty_deferred_reason }}@endif</small></dd></div>
                <div><dt>Cặp serial cũ ↔ mới</dt><dd>@if($link)<code>{{ $link->old_serial_code }}</code> → <code>{{ $link->new_serial_code }}</code><br><small>{{ $fmtDt($link->replaced_at) }}</small>@else Chưa ghi nhận @endif</dd></div>
            </dl>
            @if($movements->isNotEmpty())
                <h3>Phiếu kho</h3>
                <table class="wx2-table"><thead><tr><th>Mã</th><th>Loại</th><th>Serial</th><th>Kho</th><th>Trạng thái</th><th>Người xử lý</th></tr></thead><tbody>
                @foreach($movements as $m)
                    <tr><td>{{ $m->movement_code }}</td><td>{{ ['warranty_out'=>'Xuất đổi','faulty_return'=>'Thu hồi lỗi','supplier_send'=>'Gửi NCC','replacement_receive'=>'Nhận thay thế'][$m->movement_type] ?? $m->movement_type }}</td>
                        <td><code>{{ $m->serial_code }}</code></td><td>{{ $m->warehouse_name }}</td><td>{{ ['pending'=>'Chờ','approved'=>'Đã giữ/duyệt','completed'=>'Hoàn thành','cancelled'=>'Đã hủy'][$m->status] ?? $m->status }}</td>
                        <td>{{ $m->completed_by_name ?: '—' }}<small>{{ $fmtDt($m->completed_at) }}</small></td></tr>
                @endforeach</tbody></table>
            @endif
        </section>

        <section class="wx2-card">
            <h2>Điều kiện hoàn tất</h2>
            <ul class="wx2-check">
                @foreach($checklist as $item)
                    <li><i class="bi {{ $item['ok'] ? 'bi-check-circle-fill ok' : ($item['required'] ? 'bi-x-circle-fill no' : 'bi-dash-circle opt') }}"></i>
                        <span>{{ $item['label'] }}@if(!$item['required']) <small style="color:#94a3b8">(không bắt buộc)</small>@endif @if($item['detail'])<small style="color:#64748b"> · {{ $item['detail'] }}</small>@endif</span></li>
                @endforeach
            </ul>
        </section>

        @if($priorClaims->isNotEmpty())
        <section class="wx2-card"><h2>Lịch sử sửa chữa/đổi của serial này</h2>
            <ul class="wx2-hist">@foreach($priorClaims as $pc)<li>{{ $pc->claim_code }} · {{ $pc->claim_type === 'paid_repair' ? 'Sửa chữa tính phí' : ($pc->claim_type === 'replacement' ? 'Đổi hàng' : $pc->claim_type) }} · {{ $pc->status }} · {{ \Illuminate\Support\Carbon::parse($pc->created_at)->format('d/m/Y') }}</li>@endforeach</ul></section>
        @endif

        @include('technical.warranty._evidence')

        <section class="wx2-card"><h2>Lịch sử thao tác (không thể sửa/xóa)</h2>@include('technical.warranty._history')</section>
    </main>

    <aside>
        <div class="wx2-card">
            <h2>Hành động</h2>

            @if($can['approve'])
                <div class="wx2-actions-box"><h4>Trưởng phòng duyệt</h4>
                    @if($can['approve_blocked_self'])<div class="wx2-warn">Bạn là người tạo/phụ trách/đề nghị ngoại lệ của phiếu này nên KHÔNG được tự duyệt. Cần Trưởng phòng/Giám đốc khác.</div>@endif
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.approve', $claim) }}">@csrf
                        <label>Ý kiến duyệt<textarea name="approval_note" rows="2"></textarea></label>
                        @if($can['override'] && $can['approve_blocked_self'])<label>Emergency override — lý do bắt buộc<textarea name="override_reason" rows="2" required></textarea></label>@endif
                        @if(! $can['approve_blocked_self'] || $can['override'])<button class="wx-btn primary small" type="submit"><i class="bi bi-check2-circle"></i>Duyệt & chuyển Kho</button>@endif
                    </form>
                    <form class="wx2-form" style="margin-top:8px" method="POST" action="{{ route('ky-thuat.warranty-exchange.request-info', $claim) }}">@csrf
                        <label>Yêu cầu bổ sung — lý do bắt buộc<textarea name="reason" rows="2" required></textarea></label>
                        <button class="wx-btn secondary small" type="submit">Yêu cầu bổ sung</button></form>
                    <form class="wx2-form" style="margin-top:8px" method="POST" action="{{ route('ky-thuat.warranty-exchange.reject', $claim) }}" onsubmit="return confirm('Từ chối đề xuất này?')">@csrf
                        <label>Từ chối — lý do bắt buộc<textarea name="reason" rows="2" required></textarea></label>
                        <button class="wx-btn ghost small" type="submit">Từ chối</button></form>
                </div>
            @endif

            @if($can['resubmit'])
                <div class="wx2-actions-box"><h4>Bổ sung & gửi duyệt lại</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.resubmit', $claim) }}">@csrf
                        <label>Hiện tượng<textarea name="issue_description" rows="2">{{ $claim->issue_description }}</textarea></label>
                        <label>Chẩn đoán<textarea name="diagnosis" rows="2">{{ $claim->diagnosis }}</textarea></label>
                        <label>Phương án đề xuất<textarea name="proposed_solution" rows="2">{{ $claim->proposed_solution }}</textarea></label>
                        <button class="wx-btn primary small" type="submit">Gửi duyệt lại</button></form></div>
            @endif

            @if($can['reopen'])
                <div class="wx2-actions-box"><h4>Mở lại phiếu bị từ chối</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.reopen', $claim) }}">@csrf
                        <label>Lý do mở lại<textarea name="reason" rows="2" required></textarea></label><button class="wx-btn secondary small" type="submit">Mở lại</button></form></div>
            @endif

            @if($can['reserve'])
                <div class="wx2-actions-box"><h4>Kho chọn serial thay thế & GIỮ HÀNG</h4>
                    <div class="wx2-info">Serial phải: đang tồn kho, đúng kho, CÙNG sản phẩm/model, chưa giữ cho phiếu khác.</div>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.reserve', $claim) }}">@csrf
                        <label>Kho xuất<select name="warehouse_id" required><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
                        <label>Serial thay thế<input name="serial_code" list="wxRepl" required placeholder="Chọn serial tồn kho…"></label>
                        <datalist id="wxRepl">@foreach($replacementCandidates as $cand)<option value="{{ $cand->serial_code }}">{{ $cand->warehouse_name }} · {{ $cand->serial_code }}</option>@endforeach</datalist>
                        <label>Ghi chú kho<textarea name="note" rows="2"></textarea></label>
                        <button class="wx-btn primary small" type="submit"><i class="bi bi-lock"></i>{{ $claim->status === 'reserved' ? 'Đổi serial & giữ hàng' : 'Giữ hàng' }}</button></form>
                </div>
            @endif
            @if($can['issue'])
                <div class="wx2-actions-box"><h4>Kho xuất thiết bị thay thế</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.issue', $claim) }}" onsubmit="return confirm('Xác nhận XUẤT KHO? Tồn kho sẽ giảm.')">@csrf
                        <label>Ghi chú xuất kho / người nhận<textarea name="note" rows="2"></textarea></label>
                        <button class="wx-btn primary small" type="submit"><i class="bi bi-box-arrow-up-right"></i>Xác nhận xuất kho</button></form>
                    <form class="wx2-form" style="margin-top:8px" method="POST" action="{{ route('ky-thuat.warranty-exchange.release', $claim) }}">@csrf
                        <label>Nhả hàng — lý do<textarea name="reason" rows="2" required></textarea></label><button class="wx-btn ghost small" type="submit">Nhả hàng</button></form>
                </div>
            @endif
            @if($can['tech_receive'])
                <div class="wx2-actions-box"><h4>Kỹ thuật xác nhận đã NHẬN hàng</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.tech-receive', $claim) }}">@csrf
                        <div class="wx2-row2"><label>Ngày nhận<input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
                            <label>Người giao<input name="delivered_by" placeholder="Nhân viên Kho…"></label></div>
                        <label>Ghi chú tình trạng hàng<textarea name="note" rows="2"></textarea></label>
                        <button class="wx-btn primary small" type="submit">Đã nhận hàng</button></form></div>
            @endif
            @if($can['replace'])
                <div class="wx2-actions-box"><h4>Xác nhận ĐÃ THAY thiết bị cho khách</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.confirm-replaced', $claim) }}">@csrf
                        <div class="wx2-row2"><label>Ngày thay<input type="datetime-local" name="replaced_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
                            <label>Kết quả<select name="result"><option value="success">Thay thành công</option><option value="issue">Có vấn đề</option></select></label></div>
                        <label>Ghi chú<textarea name="note" rows="2"></textarea></label>
                        <button class="wx-btn primary small" type="submit">Xác nhận đã thay</button></form></div>
            @endif
            @if($can['faulty_return'])
                <div class="wx2-actions-box"><h4>Kho nhận thiết bị lỗi thu hồi</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.faulty-return', $claim) }}">@csrf
                        <label>Kho nhận<select name="warehouse_id" required><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
                        <label>Người mang thiết bị về<input name="returned_by" required></label>
                        <label>Tình trạng<select name="condition">@foreach($faultyConditions as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach</select></label>
                        <label>Ghi chú / biên bản<textarea name="note" rows="2"></textarea></label>
                        <button class="wx-btn primary small" type="submit">Xác nhận đã nhận thiết bị lỗi</button></form></div>
            @endif
            @if($can['defer_return'])
                <div class="wx2-actions-box"><h4>Hoãn thu hồi (đổi trước – thu sau)</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.defer-return', $claim) }}">@csrf
                        <label>Lý do hoãn<textarea name="reason" rows="2" required></textarea></label><button class="wx-btn ghost small" type="submit">Ghi nhận hoãn (vẫn theo dõi)</button></form></div>
            @endif
            @if($can['complete'])
                <div class="wx2-actions-box"><h4>Hoàn tất phiếu</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.complete', $claim) }}" onsubmit="return confirm('Hoàn tất phiếu? Không thể hoàn tất lần 2.')">@csrf
                        <label>Kết quả xử lý<textarea name="resolution" rows="2" required></textarea></label>
                        @if($canViewCosts)<label>Chi phí thực tế<input type="number" min="0" step="1000" name="actual_cost"></label>@endif
                        <button class="wx-btn primary small" type="submit"><i class="bi bi-flag"></i>Hoàn tất</button></form></div>
            @endif
            @if($can['cancel'])
                <div class="wx2-actions-box"><h4>Hủy phiếu</h4>
                    <form class="wx2-form" method="POST" action="{{ route('ky-thuat.warranty-exchange.cancel', $claim) }}" onsubmit="return confirm('Hủy phiếu? Hàng đang giữ sẽ được nhả.')">@csrf
                        <label>Lý do hủy<textarea name="reason" rows="2" required></textarea></label><button class="wx-btn ghost small" type="submit">Hủy phiếu</button></form></div>
            @endif

            @if(! collect($can)->except(['override', 'approve_blocked_self'])->contains(true))
                <p style="font-size:12.5px;color:#64748b;margin:0">Hiện chưa có hành động nào dành cho bạn ở bước “{{ $statuses[$claim->status] ?? $claim->status }}”.</p>
            @endif
        </div>
    </aside>
    </div>
</div>
</div>
@endsection
