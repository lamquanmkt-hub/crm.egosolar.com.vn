@extends('layouts.app')

@section('title', 'Sửa chữa sản phẩm tính phí')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/warranty-workflow-v2.css') }}?v={{ file_exists(public_path('css/warranty-workflow-v2.css')) ? filemtime(public_path('css/warranty-workflow-v2.css')) : time() }}">
@endsection

@section('content')
@php
    $tone = ['diagnosing'=>'amber','quotation_draft'=>'cyan','waiting_customer_confirmation'=>'violet','quotation_rejected'=>'red','approved_for_repair'=>'green','waiting_parts'=>'orange',
             'repairing'=>'amber','qa_testing'=>'blue','qa_failed'=>'red','ready_handover'=>'green','handed_over'=>'cyan','completed'=>'green','cancelled'=>'gray'];
@endphp
<div class="wx-page"><div class="wx-shell">
    @if(session('success'))<div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="wx-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể tiếp nhận</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>@endif

    @include('technical.warranty._tabs')

    <header class="wx-hero" style="padding:14px 18px">
        <div><div class="wx-kicker">KỸ THUẬT · BẢO HÀNH & SỬA CHỮA</div><h1 style="font-size:22px">Sửa chữa sản phẩm tính phí</h1>
            <p>Áp dụng khi hết bảo hành hoặc lỗi ngoài phạm vi bảo hành: tiếp nhận → chẩn đoán → báo giá → khách xác nhận → linh kiện → sửa → kiểm tra → bàn giao → hoàn tất.</p></div>
        <div class="wx-hero-actions">@if($canCreate)<button class="wx-btn primary" type="button" onclick="document.getElementById('rpCreate').open=true;document.getElementById('rpCreate').scrollIntoView({behavior:'smooth'})"><i class="bi bi-plus-lg"></i>Tiếp nhận sửa chữa</button>@endif</div>
    </header>

    @if($canCreate)
    <details id="rpCreate" class="wx2-card" @if($errors->any()) open @endif>
        <summary style="cursor:pointer;font-weight:700;font-size:14px">Tiếp nhận phiếu sửa chữa mới</summary>
        <form class="wx2-form" style="margin-top:10px" method="POST" enctype="multipart/form-data" action="{{ route('ky-thuat.repair.store') }}">@csrf
            <div class="wx2-grid">
                <label>Công trình (nếu có)<select name="site_id"><option value="">—</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected((string)old('site_id')===(string)$s->id)>{{ $s->project_code ? $s->project_code.' · ' : '' }}{{ \Illuminate\Support\Str::limit($s->name, 50) }}</option>@endforeach</select></label>
                <label>Đơn hàng (nếu có)<select name="order_id"><option value="">—</option>@foreach($orders as $o)<option value="{{ $o->id }}" @selected((string)old('order_id')===(string)$o->id)>{{ $o->order_code }} · {{ $o->customer_name }}</option>@endforeach</select></label>
                <label>Serial thiết bị <b style="color:#dc2626">*</b><input name="serial_code" value="{{ old('serial_code') }}" required placeholder="Nhập serial…"></label>
                <label>Mức ưu tiên<select name="priority">@foreach($priorities as $k=>$l)<option value="{{ $k }}" @selected(old('priority','normal')===$k)>{{ $l }}</option>@endforeach</select></label>
                @unless($isTechnicianOnly)<label>Kỹ thuật phụ trách<select name="assigned_to"><option value="">Chưa phân công</option>@foreach($technicians as $t)<option value="{{ $t->id }}" @selected((string)old('assigned_to')===(string)$t->id)>{{ $t->name }}</option>@endforeach</select></label>@endunless
            </div>
            <label>Mô tả lỗi / hiện tượng <b style="color:#dc2626">*</b><textarea name="issue_description" rows="3" required>{{ old('issue_description') }}</textarea></label>
            <label>Lý do lỗi KHÔNG thuộc phạm vi bảo hành <small style="font-weight:400">(bắt buộc nếu thiết bị còn bảo hành)</small><textarea name="out_of_scope_reason" rows="2">{{ old('out_of_scope_reason') }}</textarea></label>
            <div class="wx2-row2"><label>Ghi chú nội bộ<textarea name="internal_note" rows="2">{{ old('internal_note') }}</textarea></label>
                <label>Ảnh/file minh chứng (tối đa {{ config('warranty.evidence_max_files') }})<input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label></div>
            <div><button class="wx-btn primary" type="submit"><i class="bi bi-clipboard-plus"></i>Tiếp nhận & kiểm tra serial</button></div>
        </form>
    </details>
    @endif

    <section class="wx-panel" style="padding:12px 14px">
        <form method="GET" action="{{ route('ky-thuat.repair.index') }}" class="wx2-filter">
            <label>Tìm kiếm<input type="search" name="q" value="{{ request('q') }}" placeholder="Mã phiếu, serial…"></label>
            <label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach($statuses as $k=>$l)<option value="{{ $k }}" @selected(request('status')===$k)>{{ $l }}</option>@endforeach</select></label>
            <label>Kỹ thuật<select name="assigned_to"><option value="">Tất cả</option>@foreach($technicians as $t)<option value="{{ $t->id }}" @selected((string)request('assigned_to')===(string)$t->id)>{{ $t->name }}</option>@endforeach</select></label>
            <label>Khách hàng<input name="customer" value="{{ request('customer') }}"></label>
            <label>Công trình<select name="site_id"><option value="">Tất cả</option>@foreach($sites as $s)<option value="{{ $s->id }}" @selected((string)request('site_id')===(string)$s->id)>{{ \Illuminate\Support\Str::limit($s->name, 40) }}</option>@endforeach</select></label>
            <label>Sản phẩm<select name="product_id"><option value="">Tất cả</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected((string)request('product_id')===(string)$p->id)>{{ \Illuminate\Support\Str::limit($p->name, 40) }}</option>@endforeach</select></label>
            <label>Từ ngày<input type="date" name="from" value="{{ request('from') }}"></label>
            <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
            <div class="wx2-actions"><button class="wx-btn secondary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>@if(request()->query())<a class="wx-btn ghost" href="{{ route('ky-thuat.repair.index') }}">Xóa lọc</a>@endif</div>
        </form>
    </section>

    <section class="wx-panel" style="padding:0;overflow:hidden">
        <div style="padding:12px 16px"><strong>{{ number_format($claims->total()) }} phiếu</strong></div>
        <div class="wx-table-wrap"><table class="wx2-table">
            <thead><tr><th>Mã phiếu</th><th>Khách hàng</th><th>Thiết bị</th><th>Serial</th><th>Loại xử lý</th><th>Trạng thái</th><th>Người phụ trách</th><th>Ngày tạo</th><th>Cập nhật cuối</th><th></th></tr></thead>
            <tbody>
            @forelse($claims as $c)
                @php($d = $c->device)
                <tr><td><a class="wx-claim-code" href="{{ route('ky-thuat.repair.show', $c->id) }}">{{ $c->claim_code }}</a></td>
                    <td>{{ $d?->customer_name ?: ($c->site?->name ?: '—') }}</td><td>{{ $d?->product_name ?: '—' }}<small>{{ $d?->sku }}</small></td>
                    <td><code>{{ $c->serial_code }}</code></td><td><span class="wx2-type repair">Sửa chữa tính phí</span></td>
                    <td><span class="wx-pill {{ $tone[$c->status] ?? 'gray' }}">{{ $statuses[$c->status] ?? $c->status }}</span></td>
                    <td>{{ $c->assignee?->name ?: '—' }}</td><td>{{ optional($c->created_at)->format('d/m/Y') }}</td>
                    <td>{{ ($c->status_changed_at ? \Illuminate\Support\Carbon::parse($c->status_changed_at) : $c->updated_at)?->format('d/m/Y H:i') }}</td>
                    <td><a class="wx-btn tiny secondary" href="{{ route('ky-thuat.repair.show', $c->id) }}">Mở</a></td></tr>
            @empty
                <tr><td colspan="10"><div class="wx-empty"><i class="bi bi-tools"></i><strong>Chưa có phiếu sửa chữa</strong><span>Tiếp nhận phiếu mới hoặc đổi bộ lọc.</span></div></td></tr>
            @endforelse
            </tbody></table></div>
        @if($claims->hasPages())<div class="wx-pagination">{{ $claims->links() }}</div>@endif
    </section>
</div></div>
@endsection
