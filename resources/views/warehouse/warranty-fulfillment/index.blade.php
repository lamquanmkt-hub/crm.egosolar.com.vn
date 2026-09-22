@extends('layouts.app')

@section('title', 'Xuất hàng bảo hành & sửa chữa')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/warranty-workflow-v2.css') }}?v={{ file_exists(public_path('css/warranty-workflow-v2.css')) ? filemtime(public_path('css/warranty-workflow-v2.css')) : time() }}">
@endsection

@section('content')
@php
    $tabs = ['waiting' => 'Chờ Kho xử lý', 'reserved' => 'Đã giữ hàng', 'issued' => 'Đã xuất', 'return' => 'Thu hồi / Hoàn kho'];
@endphp
<div class="wx-page"><div class="wx-shell">

    <header class="wx-hero" style="padding:14px 18px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
        <div><div class="wx-kicker">KHO</div><h1 style="font-size:22px">XUẤT HÀNG BẢO HÀNH &amp; SỬA CHỮA</h1>
            <p>Xử lý toàn bộ nghiệp vụ Kho cho đổi hàng bảo hành và sửa chữa tính phí — không cần sang menu Kỹ thuật.</p></div>
        <a href="{{ route('guides.show', 'kho-bh-sc') }}" class="wx-btn secondary" style="white-space:nowrap"><i class="bi bi-question-circle"></i> Hướng dẫn sử dụng</a>
    </header>

    <section class="wx-panel" style="padding:12px 14px">
        <form method="GET" action="{{ route('warehouse.warranty-fulfillment.index') }}" class="wx2-filter">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label>Loại phiếu<select name="type"><option value="">Tất cả</option>
                <option value="exchange" @selected($filters['type']==='exchange')>Đổi hàng bảo hành</option>
                <option value="repair" @selected($filters['type']==='repair')>Sửa chữa tính phí</option></select></label>
            <label>Kho<select name="warehouse_id"><option value="">Tất cả</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected((int)$filters['warehouse_id']===$w->id)>{{ $w->name }}</option>@endforeach</select></label>
            <label>Sản phẩm / linh kiện<input name="product" value="{{ $filters['product'] }}"></label>
            <label>Kỹ thuật phụ trách<select name="assigned_to"><option value="">Tất cả</option>@foreach($technicians as $t)<option value="{{ $t->id }}" @selected((int)$filters['assigned_to']===$t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label>Khách hàng / SĐT<input name="customer" value="{{ $filters['customer'] }}"></label>
            <label>Từ ngày<input type="date" name="from" value="{{ $filters['from'] }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ $filters['to'] }}"></label>
            <label>Mã phiếu / serial<input name="q" value="{{ $filters['q'] }}"></label>
            <div class="wx2-actions"><button class="wx-btn secondary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>
                <a class="wx-btn ghost" href="{{ route('warehouse.warranty-fulfillment.index', ['tab' => $tab]) }}">Xóa lọc</a></div>
        </form>
    </section>

    <nav class="wx2-tabs" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0">
        @foreach($tabs as $k => $label)
            <a href="{{ route('warehouse.warranty-fulfillment.index', array_merge(request()->except(['tab','page']), ['tab' => $k])) }}"
               class="wx-btn {{ $tab === $k ? 'primary' : 'ghost' }} tiny">{{ $label }} ({{ $counts[$k] }})</a>
        @endforeach
    </nav>

    <section class="wx-panel" style="padding:0;overflow:hidden">
        <div class="wx-table-wrap"><table class="wx2-table">
            <thead><tr><th>Mã phiếu</th><th>Loại</th><th>Khách hàng</th><th>Sản phẩm / linh kiện</th><th>Serial cũ</th><th>Serial thay thế</th><th>Kho</th><th>Trạng thái</th><th>Ngày yêu cầu</th><th>Người yêu cầu</th><th>Kỹ thuật phụ trách</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php($wh = $warehouses->firstWhere('id', $row['warehouse_id']))
                <tr>
                    <td><b>{{ $row['claim_code'] }}</b></td>
                    <td><span class="wx-pill {{ $row['type']==='exchange' ? 'violet' : 'cyan' }}">{{ $row['type_label'] }}</span></td>
                    <td>{{ $row['customer'] ?: '—' }}<br><small>{{ $row['customer_phone'] }}</small></td>
                    <td>{{ \Illuminate\Support\Str::limit($row['product'] ?: '—', 60) }}</td>
                    <td>@if($row['serial_old'])<code>{{ $row['serial_old'] }}</code>@else — @endif</td>
                    <td>@if($row['serial_new'])<code>{{ $row['serial_new'] }}</code>@else — @endif</td>
                    <td>{{ $wh->name ?? '—' }}</td>
                    <td><span class="wx-pill gray">{{ $row['status_label'] }}</span></td>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['requested_at'])->format('d/m/Y H:i') }}</td>
                    <td>{{ $row['creator'] }}</td>
                    <td>{{ $row['assignee'] }}</td>
                    <td class="wx2-actions-cell">
                        <button type="button" class="wx-btn tiny ghost" data-wx-open="mView{{ $row['claim_id'] }}">Chi tiết</button>
                        @if($row['type'] === 'exchange')
                            @if($tab === 'waiting')<button type="button" class="wx-btn tiny primary" data-wx-open="mReserve{{ $row['claim_id'] }}">Giữ hàng</button>@endif
                            @if($tab === 'reserved')
                                <button type="button" class="wx-btn tiny primary" data-wx-open="mIssue{{ $row['claim_id'] }}">Xuất hàng</button>
                                <button type="button" class="wx-btn tiny ghost" data-wx-open="mRelease{{ $row['claim_id'] }}">Hủy giữ hàng</button>
                            @endif
                            @if($tab === 'return' && in_array($row['status'], ['waiting_faulty_return', 'completed'], true))
                                <button type="button" class="wx-btn tiny primary" data-wx-open="mFaulty{{ $row['claim_id'] }}">Thu hồi hàng lỗi</button>
                            @endif
                        @else
                            @if($tab === 'waiting')<button type="button" class="wx-btn tiny primary" data-wx-open="mPReserve{{ $row['claim_id'] }}">Giữ linh kiện</button>@endif
                            @if($tab === 'reserved')<button type="button" class="wx-btn tiny primary" data-wx-open="mPIssue{{ $row['claim_id'] }}">Xuất linh kiện</button>@endif
                            @if($tab === 'return')<button type="button" class="wx-btn tiny primary" data-wx-open="mPReturn{{ $row['claim_id'] }}">Hoàn linh kiện dư</button>@endif
                        @endif
                        <button type="button" class="wx-btn tiny ghost" data-wx-open="mNote{{ $row['claim_id'] }}">Ghi chú</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="12"><div class="wx-empty"><i class="bi bi-inbox"></i><strong>Không có phiếu nào ở mục này.</strong></div></td></tr>
            @endforelse
            </tbody></table></div>
        @if($rows->hasPages())<div class="wx-pagination">{{ $rows->onEachSide(1)->links() }}</div>@endif
    </section>
</div></div>

{{-- Popup: Xem chi tiết (nạp bằng AJAX vào 1 modal dùng chung) --}}
<div class="modal fade wx-modal-v2" id="mViewDetail" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Chi tiết yêu cầu</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="mViewDetailBody">Đang tải…</div>
        <div class="modal-footer"><button type="button" class="wx-btn ghost" data-bs-dismiss="modal">Đóng</button></div>
    </div></div>
</div>

@foreach($rows as $row)
    {{-- nút "Chi tiết" của từng dòng mở modal dùng chung, nạp dữ liệu qua route Kho::show --}}
    <button type="button" hidden id="mView{{ $row['claim_id'] }}Trigger" data-wx-detail-url="{{ route('warehouse.warranty-fulfillment.show', $row['claim_id']) }}"></button>

    <x-wx-modal id="mNote{{ $row['claim_id'] }}" title="Ghi chú Kho — {{ $row['claim_code'] }}" :action="route('warehouse.warranty-fulfillment.note', $row['claim_id'])" submit="LƯU GHI CHÚ" size="md">
        <label>Nội dung ghi chú <b style="color:#dc2626">*</b><textarea name="note" rows="3" required></textarea></label>
    </x-wx-modal>

    @if($row['type'] === 'exchange')
        @if($tab === 'waiting')
        <x-wx-modal id="mReserve{{ $row['claim_id'] }}" title="Chọn serial thay thế & giữ hàng — {{ $row['claim_code'] }}" :action="route('ky-thuat.warranty-exchange.reserve', $row['claim_id'])" submit="GIỮ HÀNG" size="md">
            <div class="wx2-info">Sản phẩm cần đổi: <b>{{ $row['product'] ?: '—' }}</b>. Serial lỗi: <code>{{ $row['serial_old'] }}</code></div>
            <div class="wx2-form">
                <label>Kho xuất <b style="color:#dc2626">*</b><select name="warehouse_id" required><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
                <label>Serial thay thế (cùng sản phẩm, còn trong kho) <b style="color:#dc2626">*</b><input name="serial_code" required placeholder="Nhập/scan serial thiết bị mới"></label>
                <label>Ghi chú<textarea name="note" rows="2"></textarea></label>
            </div>
        </x-wx-modal>
        @endif
        @if($tab === 'reserved')
        <x-wx-modal id="mIssue{{ $row['claim_id'] }}" title="Xác nhận xuất kho — {{ $row['claim_code'] }}" :action="route('ky-thuat.warranty-exchange.issue', $row['claim_id'])" submit="XÁC NHẬN XUẤT KHO" size="md">
            <div class="wx2-info">Serial thay thế đã giữ: <b>{{ $row['serial_new'] }}</b></div>
            <label>Ghi chú<textarea name="note" rows="2"></textarea></label>
        </x-wx-modal>
        <x-wx-modal id="mRelease{{ $row['claim_id'] }}" title="Hủy giữ hàng — {{ $row['claim_code'] }}" :action="route('ky-thuat.warranty-exchange.release', $row['claim_id'])" submit="NHẢ HÀNG" :danger="true" size="md">
            <label>Lý do <b style="color:#dc2626">*</b><textarea name="reason" rows="2" required></textarea></label>
        </x-wx-modal>
        @endif
        @if($tab === 'return')
        <x-wx-modal id="mFaulty{{ $row['claim_id'] }}" title="Nhận thiết bị lỗi thu hồi — {{ $row['claim_code'] }}" :action="route('ky-thuat.warranty-exchange.faulty-return', $row['claim_id'])" submit="XÁC NHẬN ĐÃ THU HỒI" size="md">
            <div class="wx2-form">
                <label>Kho nhận <b style="color:#dc2626">*</b><select name="warehouse_id" required><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
                <label>Tình trạng thiết bị <b style="color:#dc2626">*</b><select name="condition" required>@foreach(\App\Support\Warranty\WarrantyFlow::FAULTY_CONDITIONS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></label>
                <label>Người mang về <b style="color:#dc2626">*</b><input name="returned_by" required></label>
                <label>Ghi chú<textarea name="note" rows="2"></textarea></label>
            </div>
        </x-wx-modal>
        @endif
    @else
        @if($tab === 'waiting')
        <x-wx-modal id="mPReserve{{ $row['claim_id'] }}" title="Giữ linh kiện — {{ $row['claim_code'] }}" :action="route('ky-thuat.repair.parts.reserve', $row['claim_id'])" submit="GIỮ LINH KIỆN" size="md">
            <div class="wx2-info" style="white-space:pre-line">{{ $row['parts_summary'] }}</div>
            <label>Kho xuất <b style="color:#dc2626">*</b><select name="warehouse_id" required><option value="">Chọn kho…</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></label>
        </x-wx-modal>
        @endif
        @if($tab === 'reserved')
        <x-wx-modal id="mPIssue{{ $row['claim_id'] }}" title="Xuất linh kiện cho Kỹ thuật — {{ $row['claim_code'] }}" :action="route('ky-thuat.repair.parts.issue', $row['claim_id'])" submit="XÁC NHẬN XUẤT LINH KIỆN" size="md">
            <div class="wx2-info" style="white-space:pre-line">{{ $row['parts_summary'] }}</div>
        </x-wx-modal>
        @endif
        @if($tab === 'return')
        <x-wx-modal id="mPReturn{{ $row['claim_id'] }}" title="Hoàn kho linh kiện dư — {{ $row['claim_code'] }}" :action="route('ky-thuat.repair.parts.return', $row['claim_id'])" submit="HOÀN KHO" size="md">
            <div class="wx2-info" style="white-space:pre-line">{{ $row['parts_summary'] }}</div>
        </x-wx-modal>
        @endif
    @endif
@endforeach
@endsection

@section('scripts')
<script src="{{ asset('js/warranty-modals-v2.js') }}?v={{ file_exists(public_path('js/warranty-modals-v2.js')) ? filemtime(public_path('js/warranty-modals-v2.js')) : time() }}"></script>
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-wx-open^="mView"]');
    if (!btn) return;
    e.preventDefault();
    e.stopImmediatePropagation();
    var claimId = btn.getAttribute('data-wx-open').replace('mView', '');
    var trigger = document.getElementById('mView' + claimId + 'Trigger');
    var url = trigger ? trigger.getAttribute('data-wx-detail-url') : null;
    var body = document.getElementById('mViewDetailBody');
    var modalEl = document.getElementById('mViewDetail');
    body.innerHTML = 'Đang tải…';
    window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    if (!url) return;
    fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            var html = '<dl class="wx2-grid">'
                + '<div><dt>Mã phiếu</dt><dd>' + d.claim_code + '</dd></div>'
                + '<div><dt>Trạng thái</dt><dd>' + d.status_label + '</dd></div>'
                + '<div><dt>Khách hàng</dt><dd>' + (d.customer_name || '—') + ' — ' + (d.customer_phone || '') + '</dd></div>'
                + '<div><dt>Thiết bị</dt><dd>' + (d.device || '—') + '</dd></div>'
                + '<div><dt>Serial cũ</dt><dd>' + (d.serial_code || '—') + '</dd></div>'
                + '<div><dt>Serial thay thế</dt><dd>' + (d.reserved_serial_code || '—') + '</dd></div>'
                + '<div><dt>Mô tả lỗi</dt><dd>' + (d.issue_description || '—') + '</dd></div>'
                + '<div><dt>Kỹ thuật phụ trách</dt><dd>' + (d.assignee || '—') + '</dd></div>'
                + '<div><dt>Người tạo</dt><dd>' + (d.creator || '—') + '</dd></div>'
                + '<div><dt>Ngày nhận</dt><dd>' + (d.received_at || '—') + '</dd></div>'
                + '</dl>';
            if (d.parts && d.parts.length) {
                html += '<table class="wx2-table"><thead><tr><th>Linh kiện</th><th>Yêu cầu</th><th>Giữ</th><th>Xuất</th><th>Dùng</th><th>Hoàn</th><th>Kho</th></tr></thead><tbody>'
                    + d.parts.map(function (p) { return '<tr><td>' + p.name + '</td><td>' + p.qty_planned + '</td><td>' + p.qty_reserved + '</td><td>' + p.qty_issued + '</td><td>' + p.qty_used + '</td><td>' + p.qty_returned + '</td><td>' + (p.warehouse_name || '—') + '</td></tr>'; }).join('')
                    + '</tbody></table>';
            }
            body.innerHTML = html;
        })
        .catch(function () { body.innerHTML = 'Không tải được chi tiết.'; });
});
</script>
@endsection
