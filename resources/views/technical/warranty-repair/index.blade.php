@extends('layouts.app')

@section('title', 'Sửa chữa sản phẩm tính phí')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/warranty-workflow-v2.css') }}?v={{ file_exists(public_path('css/warranty-workflow-v2.css')) ? filemtime(public_path('css/warranty-workflow-v2.css')) : time() }}">
@endsection

@section('content')
@php
    $tone = ['diagnosing'=>'amber','quotation_draft'=>'cyan','waiting_customer_confirmation'=>'violet','quotation_rejected'=>'red','approved_for_repair'=>'green','waiting_parts'=>'orange',
             'repairing'=>'amber','qa_testing'=>'blue','qa_failed'=>'red','ready_handover'=>'green','handed_over'=>'cyan','completed'=>'green','cancelled'=>'gray','waiting_change_confirmation'=>'violet','change_rejected'=>'red'];
    $money = fn ($v) => number_format((float) $v, 0, ',', '.').' đ';
@endphp
<div class="wx-page"><div class="wx-shell">
    @if(session('success'))<div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif

    @include('technical.warranty._tabs')

    <header class="wx-hero" style="padding:14px 18px">
        <div><div class="wx-kicker">KỸ THUẬT · BẢO HÀNH & SỬA CHỮA</div><h1 style="font-size:22px">Sửa chữa sản phẩm tính phí</h1>
            <p>Khách hàng + thiết bị + lỗi là dữ liệu chính — nhận cả thiết bị mua nơi khác, chưa có trong CRM, không cần đơn hàng hay công trình.</p></div>
        <div class="wx-hero-actions">@if($canCreate)<button class="wx-btn primary" type="button" data-wx-open="rpIntakeModal"><i class="bi bi-plus-lg"></i>Tiếp nhận sửa chữa</button>@endif</div>
    </header>

    <section class="wx-panel" style="padding:12px 14px">
        <form method="GET" action="{{ route('ky-thuat.repair.index') }}" class="wx2-filter">
            <label>Tìm kiếm<input type="search" name="q" value="{{ request('q') }}" placeholder="Mã phiếu, khách, SĐT, serial, model…"></label>
            <label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach($statuses as $k=>$l)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $l }}</option>@endforeach</select></label>
            <label>Kỹ thuật<select name="assigned_to"><option value="">Tất cả</option>@foreach($technicians as $t)<option value="{{ $t->id }}" @selected((string)request('assigned_to')===(string)$t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label>Khách hàng / SĐT<input name="customer" value="{{ request('customer') }}"></label>
            <label>Thiết bị / model<input name="device" value="{{ request('device') }}"></label>
            <label>Từ ngày nhận<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
            <div class="wx2-actions"><button class="wx-btn secondary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>@if(collect(request()->query())->except('intake_serial')->isNotEmpty())<a class="wx-btn ghost" href="{{ route('ky-thuat.repair.index') }}">Xóa lọc</a>@endif</div>
        </form>
    </section>

    <section class="wx-panel" style="padding:0;overflow:hidden">
        <div style="padding:12px 16px"><strong>{{ number_format($claims->total()) }} phiếu</strong></div>
        <div class="wx-table-wrap"><table class="wx2-table">
            <thead><tr><th>Mã phiếu</th><th>Khách hàng</th><th>SĐT</th><th>Thiết bị</th><th>Model</th><th>Serial</th><th>Lỗi</th><th>Trạng thái</th><th>Báo giá</th><th>Kỹ thuật</th><th>Ngày nhận</th><th>Thao tác</th></tr></thead>
            <tbody>
            @forelse($claims as $c)
                @php($q = $quotes[$c->current_quotation_id] ?? null)
                <tr><td><a class="wx-claim-code" href="{{ route('ky-thuat.repair.show', $c->id) }}">{{ $c->claim_code }}</a></td>
                    <td>{{ $c->customer_name ?: '—' }}</td><td>{{ $c->customer_phone ?: '—' }}</td>
                    <td>{{ trim(($c->device_type ?: '').' '.($c->device_brand ?: '')) ?: '—' }}</td><td>{{ $c->device_model ?: '—' }}</td>
                    <td>@if($c->serial_code)<code>{{ $c->serial_code }}</code>@else — @endif</td>
                    <td>{{ \Illuminate\Support\Str::limit($c->issue_description, 60) }}</td>
                    <td><span class="wx-pill {{ $tone[$c->status] ?? 'gray' }}">{{ $statuses[$c->status] ?? $c->status }}</span></td>
                    <td class="wx2-money">@if($q){{ $money($q->total_amount) }}<small>v{{ $q->version }}</small>@else — @endif</td>
                    <td>{{ $c->assignee?->name ?: '—' }}</td><td>{{ optional($c->received_at)->format('d/m/Y') }}</td>
                    <td><a class="wx-btn tiny secondary" href="{{ route('ky-thuat.repair.show', $c->id) }}">Mở</a></td></tr>
            @empty
                <tr><td colspan="12"><div class="wx-empty"><i class="bi bi-tools"></i><strong>Chưa có phiếu sửa chữa</strong><span>Bấm “Tiếp nhận sửa chữa” hoặc đổi bộ lọc.</span></div></td></tr>
            @endforelse
            </tbody></table></div>
        @if($claims->hasPages())<div class="wx-pagination">{{ $claims->links() }}</div>@endif
    </section>
</div></div>

@if($canCreate)
<x-wx-modal id="rpIntakeModal" size="xl" title="Tiếp nhận sửa chữa sản phẩm" :action="route('ky-thuat.repair.store')" submit="LƯU & TIẾP NHẬN" :files="true">
    <input type="hidden" name="customer_id" value="">
    <div class="wx-section">
        <h4>A · Khách hàng</h4>
        <div id="rpCustTag" class="wx2-info" hidden></div>
        <div class="wx2-form">
            <div class="wx2-row2"><label>Tên khách hàng <b style="color:#dc2626">*</b><input name="customer_name" autocomplete="off" placeholder="Gõ tên để tìm khách CRM hoặc nhập khách mới"></label>
                <label>Số điện thoại <b style="color:#dc2626">*</b><input name="customer_phone" autocomplete="off"></label></div>
            <div id="rpCustSuggest"></div>
            <div class="wx2-row2"><label>Email<input name="customer_email" type="email"></label><label>Công ty<input name="customer_company"></label></div>
            <label>Địa chỉ<input name="customer_address"></label>
        </div>
    </div>
    <div class="wx-section">
        <h4>B · Thiết bị</h4>
        <div class="wx2-form">
            <div class="wx2-row2"><label>Loại sản phẩm <b style="color:#dc2626">*</b><input name="device_type" placeholder="Inverter, pin lưu trữ, sạc…"></label><label>Thương hiệu<input name="device_brand"></label></div>
            <div class="wx2-row2"><label>Model <b style="color:#dc2626">*</b><input name="device_model"></label><label>Serial (nếu có)<input name="serial_code" id="rpSerial" autocomplete="off" placeholder="Có thể để trống hoặc serial chưa có trong CRM"></label></div>
            <div id="rpRefBox" hidden></div>
            @if($canOverride ?? false)<div class="wx2-warn"><label style="display:flex;gap:8px;align-items:flex-start;flex-direction:row;font-weight:600"><input type="checkbox" name="duplicate_override" value="1" style="width:auto;margin-top:3px"><span>Override: cho phép tạo phiếu KHI serial này đang có phiếu mở (chỉ người có quyền)</span></label><textarea name="duplicate_override_reason" rows="2" placeholder="Lý do bắt buộc khi override…"></textarea></div>@endif
            <div id="rpScopeWrap" hidden><label>Lý do lỗi KHÔNG thuộc phạm vi bảo hành <b style="color:#dc2626">*</b><textarea name="out_of_scope_reason" rows="2"></textarea></label></div>
            <label>Tình trạng khi tiếp nhận<textarea name="received_condition" rows="2" placeholder="Trầy xước, móp, không lên nguồn…"></textarea></label>
            <label>Phụ kiện khách giao kèm<input name="device_accessories" placeholder="Dây nguồn, adapter…"></label>
            <label>Mô tả lỗi khách báo <b style="color:#dc2626">*</b><textarea name="issue_description" rows="3"></textarea></label>
            <label>Ghi chú<textarea name="internal_note" rows="2"></textarea></label>
        </div>
    </div>
    <div class="wx-section">
        <h4>C · Tiếp nhận</h4>
        <div class="wx2-form">
            <div class="wx2-row2"><label>Ngày nhận<input type="date" name="received_at" value="{{ now()->toDateString() }}"></label>
                <label>Mức ưu tiên<select name="priority">@foreach($priorities as $k=>$l)<option value="{{ $k }}" @selected($k==='normal')>{{ $l }}</option>@endforeach</select></label></div>
            <div class="wx2-row2">
                @if($isTechnicianOnly)<label>Kỹ thuật phụ trách<input value="{{ auth()->user()->name }}" disabled><input type="hidden" name="assigned_to" value="{{ auth()->id() }}"></label>
                @else<label>Kỹ thuật phụ trách<select name="assigned_to"><option value="">Chưa phân công</option>@foreach($technicians as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></label>@endif
                <label>Người giao thiết bị<input name="delivered_by" placeholder="Khách / người được ủy quyền"></label>
            </div>
            <label>Người tiếp nhận<input value="{{ auth()->user()->name }}" disabled></label>
            <label>Hình ảnh thiết bị lúc nhận / file liên quan (tối đa {{ config('warranty.evidence_max_files') }} tệp)<input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label>
        </div>
    </div>
</x-wx-modal>
@endif
@endsection

@section('scripts')
<script>window.WX = { lookupUrl: @json(route('ky-thuat.repair.lookup-serial')), customersUrl: @json(route('ky-thuat.repair.customers')), intakeSerial: @json($intakeSerial ?? '') };</script>
<script src="{{ asset('js/warranty-modals-v2.js') }}?v={{ file_exists(public_path('js/warranty-modals-v2.js')) ? filemtime(public_path('js/warranty-modals-v2.js')) : time() }}"></script>
@endsection
