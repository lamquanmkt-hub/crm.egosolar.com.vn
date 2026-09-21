@extends('layouts.app')

@section('title', 'Đổi hàng bảo hành')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/warranty-workflow-v2.css') }}?v={{ file_exists(public_path('css/warranty-workflow-v2.css')) ? filemtime(public_path('css/warranty-workflow-v2.css')) : time() }}">
@endsection

@section('content')
@php
    $statusTone = [
        'pending_approval'=>'violet','needs_more_information'=>'amber','rejected'=>'red','approved'=>'green','waiting_stock'=>'orange','reserved'=>'cyan','issued'=>'blue',
        'technician_received'=>'blue','replacing'=>'amber','waiting_faulty_return'=>'orange','faulty_returned'=>'cyan','completed'=>'green','cancelled'=>'gray',
    ];
    $nextStep = fn ($claim) => match ((string) $claim->status) {
        'pending_approval' => 'Trưởng phòng duyệt',
        'needs_more_information' => 'Kỹ thuật bổ sung',
        'waiting_stock' => 'Kho chọn serial & giữ hàng',
        'reserved' => 'Kho xuất thiết bị',
        'issued' => 'Kỹ thuật nhận hàng',
        'technician_received', 'replacing' => 'Kỹ thuật thay thiết bị',
        'waiting_faulty_return' => 'Kho thu hồi thiết bị lỗi',
        'faulty_returned' => 'Trưởng phòng hoàn tất',
        'completed' => 'Đã hoàn tất', 'rejected' => 'Đã từ chối', 'cancelled' => 'Đã hủy',
        default => '—',
    };
    $warrantyLabel = function ($d) {
        if (! $d || ! $d->warranty_status) { return ['Chưa có hồ sơ', 'gray']; }
        $active = strtolower((string) $d->warranty_status) === 'active' && (! $d->warranty_end_at || $d->warranty_end_at >= now()->toDateString());
        if (strtolower((string) $d->warranty_status) === 'replaced') { return ['Đã thay thế', 'gray']; }
        return $active ? ['Còn BH đến '.\Illuminate\Support\Carbon::parse($d->warranty_end_at)->format('d/m/Y'), 'green'] : ['Hết bảo hành', 'red'];
    };
@endphp

<div class="wx-page">
    <div class="wx-shell">
        @if(session('success'))<div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
        @if(session('error'))<div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif

        @include('technical.warranty._tabs')

        <header class="wx-hero" style="padding:14px 18px">
            <div>
                <div class="wx-kicker">KỸ THUẬT · BẢO HÀNH & SỬA CHỮA</div>
                <h1 style="font-size:22px">Đổi hàng bảo hành</h1>
                <p>Nhập serial → hệ thống tự tra khách hàng, đơn hàng, công trình, bảo hành → đề xuất → duyệt → Kho giữ & xuất → thay → thu hồi → hoàn tất.</p>
            </div>
            <div class="wx-hero-actions">
                @if($canCreate)<button class="wx-btn primary" type="button" data-wx-open="wxCreateModal"><i class="bi bi-plus-lg"></i>Tạo đề xuất đổi hàng</button>@endif
            </div>
        </header>

        <section class="wx-kpis">
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'pending_approval']) }}" class="wx-kpi violet {{ request('bucket')==='pending_approval' ? 'active' : '' }}"><span><i class="bi bi-hourglass-split"></i>Chờ duyệt</span><strong>{{ number_format($summary['pending_approval']) }}</strong><small>Cần Trưởng phòng/Giám đốc</small></a>
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'warehouse']) }}" class="wx-kpi orange {{ request('bucket')==='warehouse' ? 'active' : '' }}"><span><i class="bi bi-box-seam"></i>Chờ Kho</span><strong>{{ number_format($summary['warehouse']) }}</strong><small>Giữ hàng / xuất / thu hồi</small></a>
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'processing']) }}" class="wx-kpi amber {{ request('bucket')==='processing' ? 'active' : '' }}"><span><i class="bi bi-arrow-repeat"></i>Kỹ thuật xử lý</span><strong>{{ number_format($summary['processing']) }}</strong><small>Nhận hàng / thay thiết bị</small></a>
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'completed']) }}" class="wx-kpi green {{ request('bucket')==='completed' ? 'active' : '' }}"><span><i class="bi bi-check2-circle"></i>Hoàn tất</span><strong>{{ number_format($summary['completed']) }}</strong><small>Đã đóng hồ sơ</small></a>
        </section>

        <section class="wx-panel" style="padding:12px 14px">
            <form method="GET" action="{{ route('ky-thuat.warranty-exchange.index') }}" class="wx2-filter">
                <label>Tìm kiếm<input type="search" name="q" value="{{ request('q') }}" placeholder="Mã phiếu, serial, nội dung…"></label>
                <label>Trạng thái<select name="status"><option value="">Tất cả</option>@foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label>Ưu tiên<select name="priority"><option value="">Tất cả</option>@foreach($priorities as $key=>$label)<option value="{{ $key }}" @selected(request('priority')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label>Kỹ thuật<select name="assigned_to"><option value="">Tất cả</option>@foreach($technicians as $t)<option value="{{ $t->id }}" @selected((string)request('assigned_to')===(string)$t->id)>{{ $t->name }}</option>@endforeach</select></label>
                <label>Kho<select name="warehouse_id"><option value="">Tất cả</option>@foreach($warehousesList as $w)<option value="{{ $w->id }}" @selected((string)request('warehouse_id')===(string)$w->id)>{{ $w->name }}</option>@endforeach</select></label>
                <label>Khách hàng<input name="customer" value="{{ request('customer') }}" placeholder="Tên khách"></label>
                <label>Sản phẩm<select name="product_id"><option value="">Tất cả</option>@foreach($products as $pr)<option value="{{ $pr->id }}" @selected((string)request('product_id')===(string)$pr->id)>{{ \Illuminate\Support\Str::limit($pr->name, 40) }}</option>@endforeach</select></label>
                <label>Từ ngày<input type="date" name="from" value="{{ request('from') }}"></label>
                <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
                <div class="wx2-actions"><button class="wx-btn secondary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>
                    @if(request()->hasAny(['q','status','priority','bucket','assigned_to','warehouse_id','customer','product_id','from','to']))<a class="wx-btn ghost" href="{{ route('ky-thuat.warranty-exchange.index') }}">Xóa lọc</a>@endif</div>
            </form>
        </section>

        <section class="wx-panel" style="padding:0;overflow:hidden">
            <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center"><strong>{{ number_format($claims->total()) }} phiếu</strong>
                @if($summary['urgent'] > 0)<span class="wx-urgent"><i class="bi bi-exclamation-triangle-fill"></i>{{ $summary['urgent'] }} phiếu khẩn đang mở</span>@endif</div>
            <div class="wx-table-wrap">
                <table class="wx2-table">
                    <thead><tr><th>Mã phiếu</th><th>Serial</th><th>Sản phẩm</th><th>Khách hàng</th><th>Tình trạng bảo hành</th><th>Trạng thái xử lý</th><th>Kỹ thuật</th><th>Ngày tiếp nhận</th><th>Cập nhật</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    @forelse($claims as $claim)
                        @php($device = $claim->device)
                        @php([$wl, $wt] = $warrantyLabel($device))
                        <tr>
                            <td><a class="wx-claim-code" href="{{ route('ky-thuat.warranty-exchange.show', ['claim'=>$claim->id]) }}">{{ $claim->claim_code ?: '#'.$claim->id }}</a>@if($claim->warranty_exception)<small>Ngoại lệ BH</small>@endif</td>
                            <td><code>{{ $claim->serial_code }}</code>@if($claim->replacement_serial_code)<small>→ <b>{{ $claim->replacement_serial_code }}</b></small>@endif</td>
                            <td>{{ $device?->product_name ?: '—' }}<small>{{ $device?->sku }}</small></td>
                            <td>{{ $device?->customer_name ?: '—' }}<small>{{ $device?->customer_phone }}</small></td>
                            <td><span class="wx-pill mini {{ $wt }}">{{ $wl }}</span></td>
                            <td><span class="wx-pill {{ $statusTone[$claim->status] ?? 'gray' }}">{{ $statuses[$claim->status] ?? $claim->status }}</span><small>{{ $nextStep($claim) }}</small></td>
                            <td>{{ $claim->assignee?->name ?: $claim->assigned_name ?: '—' }}</td>
                            <td>{{ optional($claim->received_at)->format('d/m/Y') }}</td>
                            <td>{{ optional($claim->status_changed_at ? \Illuminate\Support\Carbon::parse($claim->status_changed_at) : $claim->updated_at)->format('d/m/Y H:i') }}</td>
                            <td><a class="wx-btn tiny secondary" href="{{ route('ky-thuat.warranty-exchange.show', ['claim'=>$claim->id]) }}">Mở</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><div class="wx-empty"><i class="bi bi-shield-check"></i><strong>Chưa có đề xuất phù hợp</strong><span>Tạo đề xuất mới hoặc thay đổi bộ lọc.</span></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($claims->hasPages())<div class="wx-pagination">{{ $claims->links() }}</div>@endif
        </section>
    </div>
</div>

@if($canCreate)
<x-wx-modal id="wxCreateModal" size="xl" title="Tạo đề xuất đổi hàng bảo hành" :action="route('ky-thuat.warranty-exchange.store')" submit="GỬI ĐỀ XUẤT" submit-id="wxCreateSubmit" :files="true">
    <div class="wx-section">
        <h4>Bước 1 · Serial thiết bị</h4>
        <div style="display:flex;gap:8px">
            <input id="wxSerialInput" name="serial_code" class="form-control wx-big" autocomplete="off" placeholder="Nhập / quét serial…" autofocus>
            <button type="button" id="wxSerialCheck" class="wx-btn primary" style="white-space:nowrap"><i class="bi bi-search"></i> KIỂM TRA SERIAL</button>
        </div>
        <small style="color:#64748b">Bấm Enter để tra cứu. Sản phẩm, khách hàng, đơn hàng, công trình và bảo hành được tự động tra ngược từ serial.</small>
        <div id="wxSerialResult" style="margin-top:10px"></div>
    </div>

    <div id="wxCreateDetails" hidden>
        <div class="wx-section">
            <h4>Bước 2 · Lỗi & đề xuất</h4>
            <div class="wx2-form">
                <label>Mô tả lỗi / hiện tượng thực tế <b style="color:#dc2626">*</b><textarea name="issue_description" rows="2" placeholder="Thiết bị báo lỗi gì, thời điểm phát sinh…"></textarea></label>
                <label>Chẩn đoán ban đầu của kỹ thuật <b style="color:#dc2626">*</b><textarea name="diagnosis" rows="2" placeholder="Kết quả đo kiểm, nguyên nhân xác định/sơ bộ…"></textarea></label>
                <label>Lý do & phương án đề xuất đổi <b style="color:#dc2626">*</b><textarea name="proposed_solution" rows="2" placeholder="Vì sao cần đổi, yêu cầu phối hợp Kho…"></textarea></label>
            </div>
        </div>
        <div class="wx-section">
            <h4>Bước 3 · Phụ trách & minh chứng</h4>
            <div class="wx2-row2 wx2-form">
                <label>Mức ưu tiên<select name="priority">@foreach($priorities as $key=>$label)<option value="{{ $key }}" @selected($key==='normal')>{{ $label }}</option>@endforeach</select></label>
                @if($isTechnicianOnly)
                    <label>Kỹ thuật phụ trách<input value="{{ auth()->user()->name }}" disabled><input type="hidden" name="assigned_to" value="{{ auth()->id() }}"></label>
                @else
                    <label>Kỹ thuật phụ trách<select name="assigned_to"><option value="">Chưa phân công</option>@foreach($technicians as $tech)<option value="{{ $tech->id }}">{{ $tech->name }}</option>@endforeach</select></label>
                @endif
            </div>
            <div class="wx2-form" style="margin-top:8px">
                <label>Minh chứng (tối đa {{ config('warranty.evidence_max_files') }} tệp JPG/PNG/WEBP/PDF)<input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label>
                <label>Ghi chú nội bộ<textarea name="internal_note" rows="2"></textarea></label>
            </div>
            <div id="wxExceptionBox" class="wx2-warn" hidden style="margin-top:10px">
                <label style="display:flex;flex-direction:row;gap:8px;align-items:flex-start;font-weight:600"><input type="checkbox" name="warranty_exception" value="1" id="wxExceptionChk" style="width:auto;margin-top:3px">
                    <span>Đề nghị NGOẠI LỆ bảo hành — bắt buộc lý do; phiếu phải do người KHÁC duyệt.</span></label>
                <div id="wxExceptionReasonWrap" hidden class="wx2-form" style="margin-top:6px"><label>Lý do ngoại lệ <b style="color:#dc2626">*</b><textarea name="exception_reason" rows="2" placeholder="Vì sao vẫn cần xử lý đổi hàng dù ngoài điều kiện bảo hành…"></textarea></label></div>
            </div>
        </div>
    </div>
</x-wx-modal>
@endif
@endsection

@section('scripts')
<script>window.WX = { serialInfoUrl: @json(route('ky-thuat.warranty-exchange.serial-info')), repairIntakeUrl: @json(route('ky-thuat.repair.index')), canException: @json((bool) ($canRequestException ?? false)) };</script>
<script src="{{ asset('js/warranty-modals-v2.js') }}?v={{ file_exists(public_path('js/warranty-modals-v2.js')) ? filemtime(public_path('js/warranty-modals-v2.js')) : time() }}"></script>
@endsection
