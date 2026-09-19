@extends('layouts.app')

@section('title', 'Đề xuất đổi hàng bảo hành')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-warranty-exchange-v1.css') }}?v={{ file_exists(public_path('css/technical-warranty-exchange-v1.css')) ? filemtime(public_path('css/technical-warranty-exchange-v1.css')) : time() }}">
@endsection

@section('content')
@php
    $statusTone = [
        'received'=>'blue','eligibility_check'=>'blue','diagnosing'=>'amber','solution_proposed'=>'cyan',
        'pending_approval'=>'violet','approved'=>'green','waiting_stock'=>'orange','replacing'=>'amber',
        'waiting_customer'=>'cyan','completed'=>'green','rejected'=>'red','cancelled'=>'gray',
    ];
    $priorityTone = ['low'=>'gray','normal'=>'blue','high'=>'orange','urgent'=>'red'];
    $nextStep = function ($claim) {
        return match ((string) $claim->status) {
            'pending_approval' => 'Trưởng phòng/Admin duyệt',
            'approved', 'waiting_stock' => 'Kho chọn và xuất serial thay thế',
            'replacing' => 'Kỹ thuật thực hiện đổi tại khách',
            'waiting_customer' => 'Xác nhận kết quả và đóng phiếu',
            'completed' => 'Đã hoàn tất',
            'rejected' => 'Đã từ chối',
            'cancelled' => 'Đã hủy',
            default => 'Tiếp tục cập nhật xử lý',
        };
    };
@endphp

<div class="wx-page">
    <div class="wx-shell">
        @if(session('success'))
            <div class="wx-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
        @endif
        @if(session('error'))
            <div class="wx-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>
        @endif
        @if($errors->any())
            <div class="wx-alert danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><strong>Chưa thể tạo đề xuất</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            </div>
        @endif

        <header class="wx-hero">
            <div>
                <div class="wx-kicker">KỸ THUẬT · BẢO HÀNH THIẾT BỊ</div>
                <h1>Đề xuất đổi hàng bảo hành</h1>
                <p>Kỹ thuật có thể tạo đề xuất từ Công trình hoặc trực tiếp từ Đơn hàng đã có serial, sau đó Trưởng phòng duyệt và Kho xử lý đổi.</p>
            </div>
            <div class="wx-hero-actions">
                <a class="wx-btn ghost" href="{{ route('projects-unified.maintenance.index', ['view'=>'claims']) }}"><i class="bi bi-shield-check"></i>Bảo trì / Bảo hành</a>
                @if($canCreate)
                    <button class="wx-btn primary" type="button" data-bs-toggle="modal" data-bs-target="#wxCreateModal"><i class="bi bi-plus-lg"></i>Tạo đề xuất đổi hàng</button>
                @endif
            </div>
        </header>

        <section class="wx-kpis">
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'pending_approval']) }}" class="wx-kpi violet {{ request('bucket')==='pending_approval' ? 'active' : '' }}"><span><i class="bi bi-hourglass-split"></i>Chờ duyệt</span><strong>{{ number_format($summary['pending_approval']) }}</strong><small>Cần Trưởng phòng/Admin xử lý</small></a>
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'warehouse']) }}" class="wx-kpi orange {{ request('bucket')==='warehouse' ? 'active' : '' }}"><span><i class="bi bi-box-seam"></i>Chờ Kho</span><strong>{{ number_format($summary['warehouse']) }}</strong><small>Đã duyệt / đang chuẩn bị serial</small></a>
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'processing']) }}" class="wx-kpi amber {{ request('bucket')==='processing' ? 'active' : '' }}"><span><i class="bi bi-arrow-repeat"></i>Đang đổi</span><strong>{{ number_format($summary['processing']) }}</strong><small>Đang thay thế / chờ khách xác nhận</small></a>
            <a href="{{ route('ky-thuat.warranty-exchange.index', ['bucket'=>'completed']) }}" class="wx-kpi green {{ request('bucket')==='completed' ? 'active' : '' }}"><span><i class="bi bi-check2-circle"></i>Hoàn thành</span><strong>{{ number_format($summary['completed']) }}</strong><small>Đã đóng hồ sơ đổi bảo hành</small></a>
        </section>

        <section class="wx-panel wx-toolbar">
            <form method="GET" action="{{ route('ky-thuat.warranty-exchange.index') }}">
                <div class="wx-search"><i class="bi bi-search"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Mã phiếu, serial, công trình, khách hàng, nội dung lỗi..."></div>
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    @foreach($statuses as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach
                </select>
                <select name="priority">
                    <option value="">Tất cả mức ưu tiên</option>
                    @foreach($priorities as $key=>$label)<option value="{{ $key }}" @selected(request('priority')===$key)>{{ $label }}</option>@endforeach
                </select>
                <button class="wx-btn secondary" type="submit"><i class="bi bi-funnel"></i>Lọc</button>
                @if(request()->hasAny(['q','status','priority','bucket']))<a class="wx-btn icon" href="{{ route('ky-thuat.warranty-exchange.index') }}" title="Xóa bộ lọc"><i class="bi bi-arrow-counterclockwise"></i></a>@endif
            </form>
        </section>

        <section class="wx-panel">
            <div class="wx-panel-head">
                <div><span>DANH SÁCH ĐỀ XUẤT</span><h2>{{ number_format($claims->total()) }} phiếu</h2><p>Mỗi đề xuất đi theo đúng chuỗi Kỹ thuật → Duyệt → Kho → Đổi thiết bị → Hoàn tất.</p></div>
                @if($summary['urgent'] > 0)<div class="wx-urgent"><i class="bi bi-exclamation-triangle-fill"></i>{{ $summary['urgent'] }} phiếu khẩn đang mở</div>@endif
            </div>
            <div class="wx-table-wrap">
                <table class="wx-table">
                    <thead><tr><th>Phiếu / Công trình</th><th>Thiết bị lỗi</th><th>Nội dung đề xuất</th><th>Trạng thái</th><th>Bước tiếp theo</th><th></th></tr></thead>
                    <tbody>
                    @forelse($claims as $claim)
                        @php($device = $claim->device)
                        <tr>
                            <td>
                                <a class="wx-claim-code" href="{{ route('ky-thuat.warranty-exchange.show', ['claim'=>$claim->id]) }}">{{ $claim->claim_code ?: '#'.$claim->id }}</a>
                                <strong>{{ $claim->site?->name ?: ($claim->order?->order_code ? 'Đơn hàng '.$claim->order->order_code : 'Chưa có công trình') }}</strong>
                                <small>{{ $claim->site?->project_code ?: $claim->site?->contact_name ?: $device?->customer_name ?: '—' }}</small>
                            </td>
                            <td>
                                <code>{{ $claim->serial_code ?: '—' }}</code>
                                <strong>{{ $device?->product_name ?: 'Chưa xác định sản phẩm' }}</strong>
                                <small>@if($claim->replacement_serial_code)Đổi sang: <b>{{ $claim->replacement_serial_code }}</b>@else{{ $device?->customer_name ?: 'Chưa có khách hàng' }}@endif</small>
                            </td>
                            <td><strong>{{ \Illuminate\Support\Str::limit((string)$claim->diagnosis, 78) }}</strong><small>{{ \Illuminate\Support\Str::limit((string)$claim->proposed_solution, 100) }}</small></td>
                            <td><span class="wx-pill {{ $statusTone[$claim->status] ?? 'gray' }}">{{ $statuses[$claim->status] ?? $claim->status }}</span><span class="wx-pill mini {{ $priorityTone[$claim->priority] ?? 'gray' }}">{{ $priorities[$claim->priority] ?? $claim->priority }}</span></td>
                            <td><div class="wx-next"><i class="bi bi-arrow-right-circle"></i><span>{{ $nextStep($claim) }}</span></div><small>{{ $claim->assignee?->name ?: $claim->assigned_name ?: 'Chưa phân công' }}</small></td>
                            <td><a class="wx-row-go" href="{{ route('ky-thuat.warranty-exchange.show', ['claim'=>$claim->id]) }}"><i class="bi bi-chevron-right"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="wx-empty"><i class="bi bi-shield-check"></i><strong>Chưa có đề xuất phù hợp</strong><span>Tạo đề xuất mới hoặc thay đổi bộ lọc.</span></div></td></tr>
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
                <div><span class="wx-kicker">TẠO ĐỀ XUẤT MỚI</span><h2>Đổi hàng bảo hành</h2><p>Có thể lấy thiết bị từ Công trình hoặc từ Đơn hàng đã xuất có serial. Kỹ thuật không chọn serial thay thế; bước này do Kho xử lý sau khi phiếu được duyệt.</p></div>
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
                        @if($isManager)
                            <label class="wx-check wide"><input type="checkbox" name="warranty_override" value="1" @checked(old('warranty_override'))><span><b>Cho phép ngoại lệ</b><small>Chỉ dùng khi serial hết hạn/thiếu dữ liệu bảo hành nhưng vẫn cần Ban quản lý xem xét đổi.</small></span></label>
                        @endif
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
<script src="{{ asset('js/technical-warranty-exchange-v1.js') }}?v={{ file_exists(public_path('js/technical-warranty-exchange-v1.js')) ? filemtime(public_path('js/technical-warranty-exchange-v1.js')) : time() }}"></script>
<script>window.EgoWarrantyExchange={serialInfoUrl:@json(route('ky-thuat.warranty-exchange.serial-info')),orderSerialsUrl:@json(route('ky-thuat.warranty-exchange.order-serials')),oldSerial:@json(old('serial_code')),hasErrors:@json($errors->any())};</script>
@endsection
