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

    @php
        $stepActions = [];
        if ($can['approve']) { $stepActions['approval'] = ['modal' => 'mApprove', 'label' => 'Duyệt / xử lý']; }
        if ($can['resubmit']) { $stepActions['approval'] = ['modal' => 'mResubmit', 'label' => 'Bổ sung & gửi lại']; }
        if ($can['reserve']) { $stepActions['reserve'] = ['modal' => 'mReserve', 'label' => 'Chọn serial & giữ hàng']; }
        if ($can['issue']) { $stepActions['issue'] = ['modal' => 'mIssue', 'label' => 'Xuất kho']; }
        if ($can['tech_receive']) { $stepActions['replace'] = ['modal' => 'mReceive', 'label' => 'Xác nhận đã nhận hàng']; }
        if ($can['replace']) { $stepActions['replace'] = ['modal' => 'mReplace', 'label' => 'Xác nhận đã thay']; }
        if ($can['faulty_return']) { $stepActions['faulty_return'] = ['modal' => 'mFaulty', 'label' => 'Thu hồi hàng lỗi']; }
        if ($can['complete']) { $stepActions['complete'] = ['modal' => 'mComplete', 'label' => 'Hoàn tất']; }
    @endphp
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
            <h2>Thao tác</h2>
            <div style="display:flex;flex-direction:column;gap:8px">
                @if($can['approve'])<button type="button" class="wx-btn primary" data-wx-open="mApprove"><i class="bi bi-check2-circle"></i> Duyệt</button>
                    <button type="button" class="wx-btn secondary" data-wx-open="mInfo">Yêu cầu bổ sung</button>
                    <button type="button" class="wx-btn ghost" data-wx-open="mReject">Từ chối</button>@endif
                @if($can['resubmit'])<button type="button" class="wx-btn primary" data-wx-open="mResubmit">Bổ sung & gửi duyệt lại</button>@endif
                @if($can['reopen'])<button type="button" class="wx-btn secondary" data-wx-open="mReopen">Mở lại phiếu bị từ chối</button>@endif
                @if($can['reserve'])<button type="button" class="wx-btn primary" data-wx-open="mReserve"><i class="bi bi-lock"></i> {{ $claim->status === 'reserved' ? 'Đổi serial giữ hàng' : 'Chọn serial & giữ hàng' }}</button>@endif
                @if($can['issue'])<button type="button" class="wx-btn primary" data-wx-open="mIssue"><i class="bi bi-box-arrow-up-right"></i> Xuất kho</button>
                    <button type="button" class="wx-btn ghost" data-wx-open="mRelease">Nhả hàng</button>@endif
                @if($can['tech_receive'])<button type="button" class="wx-btn primary" data-wx-open="mReceive">Xác nhận đã nhận hàng</button>@endif
                @if($can['replace'])<button type="button" class="wx-btn primary" data-wx-open="mReplace">Xác nhận đã thay thiết bị</button>@endif
                @if($can['faulty_return'])<button type="button" class="wx-btn primary" data-wx-open="mFaulty">Thu hồi thiết bị lỗi</button>@endif
                @if($can['defer_return'])<button type="button" class="wx-btn ghost" data-wx-open="mDefer">Hoãn thu hồi (đổi trước – thu sau)</button>@endif
                @if($can['complete'])<button type="button" class="wx-btn primary" data-wx-open="mComplete"><i class="bi bi-flag"></i> Hoàn tất</button>@endif
                @if($can['cancel'])<button type="button" class="wx-btn ghost" data-wx-open="mCancel">Hủy phiếu</button>@endif
                @if($can['evidence'])<button type="button" class="wx-btn secondary" data-wx-open="mEvidence"><i class="bi bi-cloud-arrow-up"></i> Tải minh chứng</button>@endif
                @if(! collect($can)->except(['override', 'approve_blocked_self'])->contains(true))
                    <p style="font-size:12.5px;color:#64748b;margin:0">Hiện chưa có thao tác nào dành cho bạn ở bước “{{ $statuses[$claim->status] ?? $claim->status }}”.</p>
                @endif
            </div>
        </div>
    </aside>
    </div>
</div>
</div>
{{-- ===================== POPUP THAO TÁC ===================== --}}
@if($can['approve'])
<x-wx-modal id="mApprove" title="Duyệt đề xuất đổi hàng" :action="route('ky-thuat.warranty-exchange.approve', $claim)" submit="DUYỆT & CHUYỂN KHO">
    <div class="wx2-info">Sau khi duyệt, phiếu chuyển sang “Chờ Kho xử lý” và Kho nhận việc chọn serial thay thế.</div>
    @if($can['approve_blocked_self'])<div class="wx2-warn">Bạn là người tạo/phụ trách/đề nghị ngoại lệ của phiếu này nên KHÔNG được tự duyệt.@if($can['override']) Chỉ được duyệt bằng emergency override kèm lý do.@endif</div>@endif
    <div class="wx2-form">
        <label>Ý kiến duyệt<textarea name="approval_note" rows="3"></textarea></label>
        @if($can['approve_blocked_self'] && $can['override'])<label>Emergency override — lý do bắt buộc<textarea name="override_reason" rows="2"></textarea></label>@endif
    </div>
</x-wx-modal>
<x-wx-modal id="mInfo" title="Yêu cầu bổ sung" :action="route('ky-thuat.warranty-exchange.request-info', $claim)" submit="GỬI YÊU CẦU BỔ SUNG" size="md">
    <div class="wx2-form"><label>Cần bổ sung gì? (bắt buộc)<textarea name="reason" rows="4"></textarea></label></div>
</x-wx-modal>
<x-wx-modal id="mReject" title="Từ chối đề xuất" :action="route('ky-thuat.warranty-exchange.reject', $claim)" submit="XÁC NHẬN TỪ CHỐI" :danger="true" size="md">
    <div class="wx2-warn">Từ chối sẽ đóng phiếu (có thể mở lại có lý do).</div>
    <div class="wx2-form"><label>Lý do từ chối (bắt buộc)<textarea name="reason" rows="4"></textarea></label></div>
</x-wx-modal>
@endif
@if($can['resubmit'])
<x-wx-modal id="mResubmit" title="Bổ sung & gửi duyệt lại" :action="route('ky-thuat.warranty-exchange.resubmit', $claim)" submit="GỬI DUYỆT LẠI">
    @if($claim->decision_reason)<div class="wx2-warn"><b>Yêu cầu của người duyệt:</b> {{ $claim->decision_reason }}</div>@endif
    <div class="wx2-form">
        <label>Hiện tượng<textarea name="issue_description" rows="2">{{ $claim->issue_description }}</textarea></label>
        <label>Chẩn đoán<textarea name="diagnosis" rows="2">{{ $claim->diagnosis }}</textarea></label>
        <label>Phương án đề xuất<textarea name="proposed_solution" rows="2">{{ $claim->proposed_solution }}</textarea></label>
    </div>
</x-wx-modal>
@endif
@if($can['reopen'])
<x-wx-modal id="mReopen" title="Mở lại phiếu bị từ chối" :action="route('ky-thuat.warranty-exchange.reopen', $claim)" submit="MỞ LẠI" size="md">
    <div class="wx2-form"><label>Lý do mở lại (bắt buộc)<textarea name="reason" rows="3"></textarea></label></div>
</x-wx-modal>
@endif
@if($can['reserve'])
<x-wx-modal id="mReserve" title="Kho chọn serial thay thế & giữ hàng" :action="route('ky-thuat.warranty-exchange.reserve', $claim)" submit="GIỮ HÀNG">
    <div class="wx2-info">Serial phải: đang tồn kho sẵn sàng, đúng kho, <b>cùng sản phẩm/model</b> ({{ $device->product_name ?? '' }}), khác serial lỗi, chưa giữ cho phiếu khác.</div>
    <div class="wx2-form">
        <label>Kho xuất<select name="warehouse_id"><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
        <label>Serial thay thế<input name="serial_code" list="wxRepl" placeholder="Chọn / nhập serial tồn kho…"></label>
        <datalist id="wxRepl">@foreach($replacementCandidates as $cand)<option value="{{ $cand->serial_code }}">{{ $cand->warehouse_name }} · {{ $cand->serial_code }}</option>@endforeach</datalist>
        <label>Ghi chú kho<textarea name="note" rows="2"></textarea></label>
    </div>
</x-wx-modal>
@endif
@if($can['issue'])
<x-wx-modal id="mIssue" title="Xác nhận XUẤT KHO thiết bị thay thế" :action="route('ky-thuat.warranty-exchange.issue', $claim)" submit="XÁC NHẬN XUẤT KHO" size="md">
    <div class="wx2-warn">Xuất kho sẽ chuyển serial <b>{{ $claim->reserved_serial_code }}</b> sang “đã bán/đã giao” và giảm tồn kho. Không thể xuất lần 2.</div>
    <div class="wx2-form"><label>Ghi chú xuất kho / người nhận<textarea name="note" rows="2"></textarea></label></div>
</x-wx-modal>
<x-wx-modal id="mRelease" title="Nhả hàng đã giữ" :action="route('ky-thuat.warranty-exchange.release', $claim)" submit="NHẢ HÀNG" size="md">
    <div class="wx2-form"><label>Lý do nhả hàng (bắt buộc)<textarea name="reason" rows="3"></textarea></label></div>
</x-wx-modal>
@endif
@if($can['tech_receive'])
<x-wx-modal id="mReceive" title="Kỹ thuật xác nhận đã NHẬN hàng" :action="route('ky-thuat.warranty-exchange.tech-receive', $claim)" submit="ĐÃ NHẬN HÀNG" size="md">
    <div class="wx2-form">
        <div class="wx2-row2"><label>Ngày nhận<input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label><label>Người giao<input name="delivered_by" placeholder="Nhân viên Kho…"></label></div>
        <label>Tình trạng hàng nhận / ghi chú<textarea name="note" rows="2"></textarea></label>
    </div>
</x-wx-modal>
@endif
@if($can['replace'])
<x-wx-modal id="mReplace" title="Xác nhận ĐÃ THAY thiết bị cho khách" :action="route('ky-thuat.warranty-exchange.confirm-replaced', $claim)" submit="XÁC NHẬN ĐÃ THAY" size="md">
    <div class="wx2-info">Hệ thống sẽ lưu cặp serial <b>{{ $claim->serial_code }}</b> → <b>{{ $claim->replacement_serial_code }}</b>.</div>
    <div class="wx2-form">
        <div class="wx2-row2"><label>Ngày thay<input type="datetime-local" name="replaced_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
            <label>Kết quả<select name="result"><option value="success">Thay thành công</option><option value="issue">Có vấn đề</option></select></label></div>
        <label>Ghi chú<textarea name="note" rows="2"></textarea></label>
    </div>
</x-wx-modal>
@endif
@if($can['faulty_return'])
<x-wx-modal id="mFaulty" title="Kho nhận thiết bị lỗi thu hồi" :action="route('ky-thuat.warranty-exchange.faulty-return', $claim)" submit="XÁC NHẬN ĐÃ THU HỒI">
    <div class="wx2-form">
        <div class="wx2-row2"><label>Kho nhận<select name="warehouse_id"><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
            <label>Tình trạng thiết bị<select name="condition">@foreach($faultyConditions as $k => $lbl)<option value="{{ $k }}">{{ $lbl }}</option>@endforeach</select></label></div>
        <label>Người mang thiết bị về<input name="returned_by"></label>
        <label>Ghi chú / biên bản<textarea name="note" rows="2"></textarea></label>
    </div>
</x-wx-modal>
@endif
@if($can['defer_return'])
<x-wx-modal id="mDefer" title="Hoãn thu hồi thiết bị lỗi" :action="route('ky-thuat.warranty-exchange.defer-return', $claim)" submit="GHI NHẬN HOÃN" size="md">
    <div class="wx2-info">Phiếu vẫn được theo dõi “chờ thu hồi”; Kho có thể nhận thiết bị lỗi muộn sau khi hoàn tất.</div>
    <div class="wx2-form"><label>Lý do hoãn (bắt buộc)<textarea name="reason" rows="3"></textarea></label></div>
</x-wx-modal>
@endif
@if($can['complete'])
<x-wx-modal id="mComplete" title="Hoàn tất phiếu đổi hàng" :action="route('ky-thuat.warranty-exchange.complete', $claim)" submit="HOÀN TẤT PHIẾU">
    <h3 style="font-size:13px">Checklist trước khi đóng phiếu</h3>
    <ul class="wx2-check" style="margin-bottom:10px">@foreach($checklist as $item)<li><i class="bi {{ $item['ok'] ? 'bi-check-circle-fill ok' : ($item['required'] ? 'bi-x-circle-fill no' : 'bi-dash-circle opt') }}"></i><span>{{ $item['label'] }}</span></li>@endforeach</ul>
    <div class="wx2-form">
        <label>Kết quả xử lý (bắt buộc)<textarea name="resolution" rows="3"></textarea></label>
        @if($canViewCosts)<label>Chi phí thực tế<input type="number" min="0" step="1000" name="actual_cost"></label>@endif
    </div>
</x-wx-modal>
@endif
@if($can['cancel'])
<x-wx-modal id="mCancel" title="Hủy phiếu" :action="route('ky-thuat.warranty-exchange.cancel', $claim)" submit="XÁC NHẬN HỦY" :danger="true" size="md">
    <div class="wx2-warn">Hàng đang giữ (nếu có) sẽ được nhả và phiếu kho đang mở bị hủy.</div>
    <div class="wx2-form"><label>Lý do hủy (bắt buộc)<textarea name="reason" rows="3"></textarea></label></div>
</x-wx-modal>
@endif
@if($can['evidence'])
<x-wx-modal id="mEvidence" title="Tải file minh chứng" :action="route('ky-thuat.warranty-exchange.evidence.upload', $claim->id)" submit="TẢI LÊN" size="md" :files="true">
    <div class="wx2-form"><label>Chọn tệp (JPG/PNG/WEBP/PDF, tối đa {{ config('warranty.evidence_max_files') }} tệp, 20MB/tệp) — lưu riêng tư<input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label></div>
</x-wx-modal>
@endif
@endsection

@section('scripts')
<script>window.WX = {};</script>
<script src="{{ asset('js/warranty-modals-v2.js') }}?v={{ file_exists(public_path('js/warranty-modals-v2.js')) ? filemtime(public_path('js/warranty-modals-v2.js')) : time() }}"></script>
@endsection
