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
@endphp

<div class="wx-page">
    <div class="wx-shell">
        @if(session('success'))<div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
        @if(session('error'))<div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
        @if($errors->any())
            <div class="wx-alert danger"><i class="bi bi-exclamation-triangle-fill"></i>
                <div><strong>Chưa thể tạo đề xuất</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
        @endif

        @include('technical.warranty._tabs')

        <header class="wx-hero" style="padding:14px 18px">
            <div>
                <div class="wx-kicker">KỸ THUẬT · BẢO HÀNH & SỬA CHỮA</div>
                <h1 style="font-size:22px">Đổi hàng bảo hành</h1>
                <p>Tiếp nhận lỗi → kiểm tra serial → đề xuất → duyệt → Kho giữ & xuất hàng → Kỹ thuật nhận & thay → thu hồi hàng lỗi → hoàn tất.</p>
            </div>
            <div class="wx-hero-actions">
                @if($canCreate)<button class="wx-btn primary" type="button" data-bs-toggle="modal" data-bs-target="#wxCreateModal"><i class="bi bi-plus-lg"></i>Tạo đề xuất đổi hàng</button>@endif
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
                <label>Công trình<select name="site_id"><option value="">Tất cả</option>@foreach($sites as $st)<option value="{{ $st->id }}" @selected((string)request('site_id')===(string)$st->id)>{{ $st->project_code ? $st->project_code.' · ' : '' }}{{ \Illuminate\Support\Str::limit($st->name, 40) }}</option>@endforeach</select></label>
                <label>Sản phẩm<select name="product_id"><option value="">Tất cả</option>@foreach($products as $pr)<option value="{{ $pr->id }}" @selected((string)request('product_id')===(string)$pr->id)>{{ \Illuminate\Support\Str::limit($pr->name, 40) }}</option>@endforeach</select></label>
                <label>Từ ngày<input type="date" name="from" value="{{ request('from') }}"></label>
                <label>Đến ngày<input type="date" name="to" value="{{ request('to') }}"></label>
                <div class="wx2-actions"><button class="wx-btn secondary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>
                    @if(request()->hasAny(['q','status','priority','bucket','assigned_to','warehouse_id','customer','site_id','product_id','from','to']))<a class="wx-btn ghost" href="{{ route('ky-thuat.warranty-exchange.index') }}">Xóa lọc</a>@endif</div>
            </form>
        </section>

        <section class="wx-panel" style="padding:0;overflow:hidden">
            <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center"><strong>{{ number_format($claims->total()) }} phiếu</strong>
                @if($summary['urgent'] > 0)<span class="wx-urgent"><i class="bi bi-exclamation-triangle-fill"></i>{{ $summary['urgent'] }} phiếu khẩn đang mở</span>@endif</div>
            <div class="wx-table-wrap">
                <table class="wx2-table">
                    <thead><tr><th>Mã phiếu</th><th>Khách hàng</th><th>Thiết bị</th><th>Serial</th><th>Loại xử lý</th><th>Trạng thái</th><th>Người phụ trách</th><th>Ngày tạo</th><th>Cập nhật cuối</th><th></th></tr></thead>
                    <tbody>
                    @forelse($claims as $claim)
                        @php($device = $claim->device)
                        <tr>
                            <td><a class="wx-claim-code" href="{{ route('ky-thuat.warranty-exchange.show', ['claim'=>$claim->id]) }}">{{ $claim->claim_code ?: '#'.$claim->id }}</a>
                                <small>{{ $claim->site?->project_code ?: ($claim->order?->order_code ?: '') }}</small></td>
                            <td>{{ $device?->customer_name ?: ($claim->site?->contact_name ?: '—') }}<small>{{ $claim->site?->name }}</small></td>
                            <td>{{ $device?->product_name ?: '—' }}<small>{{ $device?->sku }}</small></td>
                            <td><code>{{ $claim->serial_code }}</code>@if($claim->replacement_serial_code)<small>→ <b>{{ $claim->replacement_serial_code }}</b></small>@endif</td>
                            <td><span class="wx2-type">Đổi hàng BH</span>@if($claim->warranty_exception)<small>Ngoại lệ</small>@endif</td>
                            <td><span class="wx-pill {{ $statusTone[$claim->status] ?? 'gray' }}">{{ $statuses[$claim->status] ?? $claim->status }}</span>
                                <small>{{ $nextStep($claim) }}</small></td>
                            <td>{{ $claim->assignee?->name ?: $claim->assigned_name ?: '—' }}</td>
                            <td>{{ optional($claim->created_at)->format('d/m/Y') }}</td>
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
<div class="modal fade" id="wxCreateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <form class="modal-content wx-modal" method="POST" enctype="multipart/form-data" action="{{ route('ky-thuat.warranty-exchange.store') }}">
            @csrf
            <div class="modal-header">
                <div><span class="wx-kicker">TẠO ĐỀ XUẤT MỚI</span><h2>Đổi hàng bảo hành</h2><p>Lấy thiết bị từ Công trình hoặc Đơn hàng có serial. Kỹ thuật KHÔNG chọn serial thay thế: bước đó do Kho thực hiện sau khi phiếu được duyệt.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="wx-form-grid">
                    <section class="wx-form-card">
                        <h3><span>1</span> Nguồn thiết bị &amp; thiết bị lỗi</h3>
                        @php($oldSource = old('source_type', 'site'))
                        <div class="wx-source-switch wide" id="wxSourceSwitch">
                            <label class="wx-source-option">
                                <input type="radio" name="source_type" value="site" @checked($oldSource === 'site')>
                                <span><i class="bi bi-buildings"></i><b>Công trình</b><small>Chọn công trình rồi nhập serial lỗi.</small></span>
                            </label>
                            <label class="wx-source-option">
                                <input type="radio" name="source_type" value="order" @checked($oldSource === 'order')>
                                <span><i class="bi bi-receipt"></i><b>Đơn hàng</b><small>Lấy serial đã xuất trực tiếp từ đơn.</small></span>
                            </label>
                        </div>

                        <div class="wx-source-pane wide" id="wxSourceSitePane">
                            <label class="wx-field wide"><span>Công trình <b>*</b></span><select name="site_id" id="wxSiteSelect"><option value="">Chọn công trình...</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string)old('site_id')===(string)$site->id)>{{ $site->project_code ? $site->project_code.' · ' : '' }}{{ $site->name }}{{ $site->contact_name ? ' — '.$site->contact_name : '' }}</option>@endforeach</select></label>
                        </div>

                        <div class="wx-source-pane wide" id="wxSourceOrderPane">
                            <label class="wx-field wide"><span>Đơn hàng có serial <b>*</b></span><select name="order_id" id="wxOrderSelect"><option value="">Chọn đơn hàng...</option>@foreach($orders as $order)<option value="{{ $order->id }}" @selected((string)old('order_id')===(string)$order->id)>{{ $order->order_code }}{{ $order->customer_name ? ' · '.$order->customer_name : '' }} · {{ (int)$order->serial_count }} serial</option>@endforeach</select></label>
                            <label class="wx-field wide"><span>Serial trong đơn <b>*</b></span><select id="wxOrderSerialSelect"><option value="">Chọn đơn hàng trước...</option></select></label>
                            <div class="wx-order-preview wide" id="wxOrderPreview"><i class="bi bi-receipt-cutoff"></i><div><strong>Chọn đơn hàng</strong><span>Hệ thống sẽ lấy đúng các serial đã gắn với đơn.</span></div></div>
                        </div>

                        <label class="wx-field wide"><span>Serial thiết bị lỗi <b>*</b></span><div class="wx-input-action"><input name="serial_code" id="wxSerialInput" value="{{ old('serial_code') }}" required autocomplete="off" placeholder="Nhập serial hoặc chọn từ đơn hàng..."><button type="button" id="wxSerialLookup"><i class="bi bi-search"></i></button></div></label>
                        <label class="wx-field wide"><span>Mức ưu tiên <b>*</b></span><select name="priority" required>@foreach($priorities as $key=>$label)<option value="{{ $key }}" @selected(old('priority','normal')===$key)>{{ $label }}</option>@endforeach</select></label>
                        <div class="wx-serial-preview wide" id="wxSerialPreview"><i class="bi bi-upc-scan"></i><div><strong>Nhập/chọn serial để kiểm tra bảo hành</strong><span>Hệ thống sẽ hiện sản phẩm, khách hàng, đơn hàng và hạn bảo hành.</span></div></div>
                    </section>

                    <section class="wx-form-card">
                        <h3><span>2</span> Chẩn đoán &amp; đề xuất</h3>
                        <label class="wx-field wide"><span>Mô tả lỗi / hiện tượng thực tế <b>*</b></span><textarea name="issue_description" rows="3" required placeholder="Thiết bị báo lỗi gì, thời điểm phát sinh, tình trạng tại công trình...">{{ old('issue_description') }}</textarea></label>
                        <label class="wx-field wide"><span>Chẩn đoán của kỹ thuật <b>*</b></span><textarea name="diagnosis" rows="3" required placeholder="Kết quả đo kiểm, nguyên nhân xác định/sơ bộ...">{{ old('diagnosis') }}</textarea></label>
                        <label class="wx-field wide"><span>Lý do &amp; phương án đề xuất đổi <b>*</b></span><textarea name="proposed_solution" rows="3" required placeholder="Vì sao cần đổi, đề xuất đổi thiết bị tương đương/cùng model, yêu cầu phối hợp Kho...">{{ old('proposed_solution') }}</textarea></label>
                    </section>

                    <section class="wx-form-card">
                        <h3><span>3</span> Phụ trách &amp; minh chứng</h3>
                        @if($isTechnicianOnly)
                            <input type="hidden" name="assigned_to" value="{{ auth()->id() }}">
                            <div class="wx-assignee wide"><i class="bi bi-person-check-fill"></i><div><small>Kỹ thuật phụ trách</small><strong>{{ auth()->user()->name }}</strong></div><span>Tự tiếp nhận</span></div>
                        @else
                            <label class="wx-field wide"><span>Kỹ thuật phụ trách</span><select name="assigned_to"><option value="">Chưa phân công</option>@foreach($technicians as $tech)<option value="{{ $tech->id }}" @selected((string)old('assigned_to')===(string)$tech->id)>{{ $tech->name }}</option>@endforeach</select></label>
                        @endif
                        <label class="wx-field"><span>Chi phí dự kiến</span><input type="number" min="0" step="1000" name="estimated_cost" value="{{ old('estimated_cost',0) }}"></label>
                        <label class="wx-field"><span>Minh chứng (tối đa 8 tệp)</span><input type="file" name="evidence[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label>
                        <label class="wx-field wide"><span>Ghi chú nội bộ</span><textarea name="internal_note" rows="2">{{ old('internal_note') }}</textarea></label>
                        <label class="wx-check wide"><input type="checkbox" name="warranty_exception" value="1" id="wxExceptionChk" @checked(old('warranty_exception'))><span><b>Đề nghị ngoại lệ bảo hành</b><small>Chỉ dùng khi serial hết hạn / thiếu hồ sơ bảo hành / chưa liên kết công trình. Bắt buộc nhập lý do; phiếu phải do người KHÁC (không phải người đề nghị) duyệt.</small></span></label>
                        <label class="wx-field wide" id="wxExceptionReasonWrap" style="display:none"><span>Lý do ngoại lệ <b>*</b></span><textarea name="exception_reason" rows="2" placeholder="Vì sao vẫn cần xử lý đổi hàng dù ngoài điều kiện bảo hành...">{{ old('exception_reason') }}</textarea></label>
                    </section>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="wx-btn ghost" data-bs-dismiss="modal">Đóng</button><button class="wx-btn primary" type="submit"><i class="bi bi-send-check"></i>Gửi đề xuất duyệt</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>document.addEventListener('DOMContentLoaded',function(){var c=document.getElementById('wxExceptionChk'),w=document.getElementById('wxExceptionReasonWrap');if(c&&w){var f=function(){w.style.display=c.checked?'':'none'};c.addEventListener('change',f);f();}});</script>
<script src="{{ asset('js/technical-warranty-exchange-v1.js') }}?v={{ file_exists(public_path('js/technical-warranty-exchange-v1.js')) ? filemtime(public_path('js/technical-warranty-exchange-v1.js')) : time() }}"></script>
<script>window.EgoWarrantyExchange={serialInfoUrl:@json(route('ky-thuat.warranty-exchange.serial-info')),orderSerialsUrl:@json(route('ky-thuat.warranty-exchange.order-serials')),oldSerial:@json(old('serial_code')),hasErrors:@json($errors->any())};</script>
@endsection
