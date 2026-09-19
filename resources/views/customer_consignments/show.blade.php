@extends('layouts.app')
@section('title','Chi tiết đơn ký gửi')
@section('styles')<link rel="stylesheet" href="{{ asset('css/customer-consignments.css') }}?v={{ @filemtime(public_path('css/customer-consignments.css')) ?: time() }}">@endsection
@section('content')
@php
    $c=$customerConsignment; $u=auth()->user();
    $isAdmin=$u && method_exists($u,'hasRole') && $u->hasRole('admin');
    $canCreate=$isAdmin || $u?->can('consignments.create');
    $canSubmit=$isAdmin || $u?->can('consignments.submit');
    $canApprove=$isAdmin || $u?->can('consignments.approve');
    $canReview=$isAdmin || $u?->can('consignments.review');
    $canWarehouse=$isAdmin || $u?->can('consignments.warehouse_issue');
    $canCancel=$isAdmin || $u?->can('consignments.cancel');
    $tone=match($c->status){'active','completed'=>'green','pending_approval','approved'=>'amber','revision_requested'=>'blue','rejected','cancelled'=>'red',default=>'teal'};
    $activityLabels=['created'=>'Sales tạo đơn','updated'=>'Sales cập nhật bản nháp','submitted'=>'Sales gửi duyệt','resubmitted'=>'Sales chỉnh sửa và gửi lại','approved'=>'Admin/Giám đốc phê duyệt','revision_requested'=>'Yêu cầu Sales chỉnh sửa','rejected'=>'Từ chối đơn','warehouse_issued'=>'Kho xuất hàng ký gửi','cancelled'=>'Hủy đơn'];
@endphp
<div class="cc-page"><div class="cc-wrap">
    <div class="cc-head"><div><div class="cc-eyebrow">Đơn ký gửi độc lập</div><h1 class="cc-title">{{ $c->code }}</h1><div class="cc-sub">{{ $c->customer->name ?? 'Khách hàng #'.$c->customer_id }} · Sales: {{ $c->salesUser->name ?? $c->creator->name ?? '—' }}</div></div><div class="cc-actions">@if($canCreate && in_array($c->status,['draft','revision_requested']))<a class="cc-btn cc-btn-primary" href="{{ route('customer-consignments.edit',$c) }}"><i class="bi bi-pencil"></i> Chỉnh sửa</a>@endif<a class="cc-btn" href="{{ route('customer-consignments.index') }}"><i class="bi bi-arrow-left"></i> Danh sách</a></div></div>
    @if(session('success'))<div class="cc-alert cc-alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="cc-alert cc-alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="cc-alert cc-alert-danger">{{ $errors->first() }}</div>@endif

    <div class="cc-card"><div class="cc-card-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
            <div style="padding:12px;border-radius:12px;background:{{ in_array($c->status,['draft','revision_requested'])?'#eef6ff':'#eaf8f4' }}"><strong>1. Sales chọn hàng</strong><div class="cc-secondary">{{ $c->submitted_at ? 'Đã gửi duyệt '.optional($c->submitted_at)->format('d/m/Y H:i') : 'Đang chuẩn bị' }}</div></div>
            <div style="padding:12px;border-radius:12px;background:{{ in_array($c->status,['approved','active','completed'])?'#eaf8f4':'#fff8e8' }}"><strong>2. Admin/Giám đốc duyệt</strong><div class="cc-secondary">{{ $c->approved_at ? 'Đã duyệt '.optional($c->approved_at)->format('d/m/Y H:i') : 'Chưa duyệt' }}</div></div>
            <div style="padding:12px;border-radius:12px;background:{{ in_array($c->status,['active','completed'])?'#eaf8f4':'#f3f5f8' }}"><strong>3. Kho xuất ký gửi</strong><div class="cc-secondary">{{ $c->warehouse_confirmed_at ? 'Đã xuất '.optional($c->warehouse_confirmed_at)->format('d/m/Y H:i') : 'Chưa được phép xuất' }}</div></div>
        </div>
    </div></div>

    @if($c->revision_reason)<div class="cc-alert cc-alert-danger"><strong>Yêu cầu chỉnh sửa:</strong> {{ $c->revision_reason }}</div>@endif
    @if($c->reject_reason)<div class="cc-alert cc-alert-danger"><strong>Lý do từ chối:</strong> {{ $c->reject_reason }}</div>@endif

    <div class="cc-detail-grid"><div>
        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">Thông tin đơn ký gửi</h2><div class="cc-card-sub">Không ghi nhận doanh thu bán hàng</div></div><span class="cc-badge {{ $tone }}">{{ $c->status_label }}</span></div><div class="cc-card-body"><div class="cc-form-grid">
            <div><div class="cc-label">Khách hàng</div><div class="cc-main">{{ $c->customer->name ?? '—' }}</div><div class="cc-secondary">{{ $c->customer->phone ?? '' }}</div></div>
            <div><div class="cc-label">Sales phụ trách</div><div class="cc-main">{{ $c->salesUser->name ?? $c->creator->name ?? '—' }}</div></div>
            <div><div class="cc-label">Người nhận</div><div class="cc-main">{{ $c->receiver_name ?: '—' }}</div><div class="cc-secondary">{{ $c->receiver_phone }}</div></div>
            <div><div class="cc-label">Hạn ký gửi</div><div class="cc-main">{{ $c->expires_at?->format('d/m/Y') ?? 'Không đặt hạn' }}</div></div>
            <div class="cc-span-2"><div class="cc-label">Địa chỉ xuất hàng</div><div>{{ $c->shipping_address ?: '—' }}</div></div>
            <div class="cc-span-2"><div class="cc-label">Điều khoản / ghi chú</div><div>{{ $c->terms_note ?: '—' }}</div></div>
            @if($c->approval_note)<div class="cc-span-2"><div class="cc-label">Ghi chú phê duyệt</div><div>{{ $c->approval_note }}</div></div>@endif
            @if($c->warehouse_issue_note)<div class="cc-span-2"><div class="cc-label">Ghi chú xuất Kho</div><div>{{ $c->warehouse_issue_note }}</div></div>@endif
        </div></div></div>

        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">Hàng hóa Sales đề xuất ký gửi</h2><div class="cc-card-sub">Số lượng do Sales chọn; Kho chỉ xuất sau khi được duyệt.</div></div></div><div class="cc-table-wrap"><table class="cc-table"><thead><tr><th>Sản phẩm</th><th>Kho nguồn</th><th>SL Sales chọn</th><th>Đã xuất</th><th>Giá tham khảo</th><th>Serial đã xuất</th></tr></thead><tbody>
            @foreach($c->items as $item)<tr><td><div class="cc-main">{{ $item->product->name ?? 'SP #'.$item->product_id }}</div><div class="cc-secondary">{{ $item->product->sku ?? '' }} {{ $item->item_note ? '· '.$item->item_note : '' }}</div></td><td>{{ $item->sourceWarehouse->name ?? 'Kho #'.$item->source_warehouse_id }}</td><td><strong>{{ number_format($item->quantity) }}</strong></td><td>{{ number_format($item->issued_quantity) }}</td><td class="cc-money">{{ number_format((float)$item->unit_price,0,',','.') }} đ</td><td>@forelse($item->serials as $serial)<span class="cc-badge teal" style="margin:2px">{{ $serial->serialUnit->primaryIdentifier->serialIdentifier->code ?? ('SN#'.$serial->serial_unit_id) }}</span>@empty<span class="cc-secondary">{{ ($item->product->is_serialized??false) ? 'Kho chưa chọn' : 'Không quản lý serial' }}</span>@endforelse</td></tr>@endforeach
        </tbody><tfoot><tr><th colspan="2">Tổng</th><th>{{ number_format($c->items->sum('quantity')) }}</th><th>{{ number_format($c->items->sum('issued_quantity')) }}</th><th class="cc-money">{{ number_format($c->total_value,0,',','.') }} đ</th><th></th></tr></tfoot></table></div></div>
    </div>

    <div>
        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">Xử lý theo trạng thái</h2><div class="cc-card-sub">Hệ thống khóa đúng người xử lý từng bước</div></div></div><div class="cc-card-body">
            @if(in_array($c->status,['draft','revision_requested']))
                <div class="cc-note">Sales kiểm tra khách hàng, sản phẩm, kho và số lượng trước khi gửi duyệt.</div>
                @if($canSubmit)<form method="post" action="{{ route('customer-consignments.submit',$c) }}" data-confirm="Gửi đơn này cho Admin/Giám đốc duyệt?" style="margin-top:10px">@csrf<button class="cc-btn cc-btn-primary" style="width:100%"><i class="bi bi-send"></i> Gửi Admin/Giám đốc duyệt</button></form>@endif
            @elseif($c->status==='pending_approval')
                <div class="cc-note">Kho chưa được phép xuất. Đơn đang chờ Admin/Giám đốc quyết định.</div>
                @if($canApprove)<form method="post" action="{{ route('customer-consignments.approve',$c) }}" style="margin-top:10px">@csrf<label class="cc-label">Ghi chú phê duyệt</label><textarea class="cc-textarea" name="note"></textarea><button class="cc-btn cc-btn-primary" style="width:100%;margin-top:8px"><i class="bi bi-check2-circle"></i> Phê duyệt cho Kho xuất</button></form>@endif
                @if($canReview)<div class="cc-actions" style="margin-top:8px"><button class="cc-btn" data-bs-toggle="modal" data-bs-target="#revisionModal">Yêu cầu sửa</button><button class="cc-btn cc-btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Từ chối</button></div>@endif
            @elseif($c->status==='approved')
                <div class="cc-alert cc-alert-success" style="margin-top:0">Admin/Giám đốc đã duyệt. Kho được phép xuất đúng số lượng Sales đã đề xuất.</div>
                @if($canWarehouse)<form method="post" action="{{ route('customer-consignments.warehouse-issue',$c) }}" data-confirm="Xác nhận xuất toàn bộ hàng ký gửi cho khách?" style="margin-top:10px">@csrf
                    <div><label class="cc-label">Ngày xuất ký gửi</label><input class="cc-input" type="date" name="issue_date" value="{{ now()->toDateString() }}" required></div>
                    @foreach($c->items as $item) @if($item->product->is_serialized??false)<div style="margin-top:10px"><label class="cc-label">{{ $item->product->name }} — chọn đúng {{ $item->quantity }} serial</label><select class="cc-select" name="serials[{{ $item->id }}][]" multiple size="{{ min(8,max(3,$item->quantity)) }}" required>@foreach($availableSerials[$item->id]??[] as $serial)<option value="{{ $serial['id'] }}">{{ $serial['code'] }}</option>@endforeach</select><div class="cc-secondary">Giữ Ctrl/Cmd để chọn nhiều serial.</div></div>@endif @endforeach
                    <div style="margin-top:10px"><label class="cc-label">Ghi chú xuất Kho</label><textarea class="cc-textarea" name="issue_note" placeholder="Số phiếu xuất, đơn vị vận chuyển, người bàn giao..."></textarea></div>
                    <button class="cc-btn cc-btn-primary" style="width:100%;margin-top:10px"><i class="bi bi-box-arrow-right"></i> Kho xác nhận xuất hàng</button>
                </form>@else<div class="cc-note">Đang chờ nhân sự Kho xử lý.</div>@endif
            @elseif($c->status==='active')
                <div class="cc-alert cc-alert-success" style="margin:0"><strong>Đã xuất hàng ký gửi.</strong><br>Ngày xuất: {{ $c->warehouse_issue_date?->format('d/m/Y') }}<br>Kho xử lý: {{ $c->warehouseConfirmer->name ?? '—' }}<br>Hàng vẫn thuộc sở hữu công ty và chưa ghi nhận doanh thu bán hàng.</div>
            @elseif($c->status==='rejected')<div class="cc-alert cc-alert-danger" style="margin:0">Đơn đã bị từ chối. {{ $c->reject_reason }}</div>
            @elseif($c->status==='cancelled')<div class="cc-alert cc-alert-danger" style="margin:0">Đơn đã hủy. {{ $c->cancel_reason }}</div>
            @endif

            @if($canCancel && !in_array($c->status,['active','completed','cancelled']))<button class="cc-btn cc-btn-danger" style="width:100%;margin-top:10px" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="bi bi-x-circle"></i> Hủy đơn ký gửi</button>@endif
        </div></div>

        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">Nhật ký</h2><div class="cc-card-sub">Ai làm gì, lúc nào</div></div></div><div class="cc-card-body"><div class="cc-timeline">@forelse($c->activities as $a)<div class="cc-timeline-item"><div class="cc-timeline-dot"><i class="bi bi-check2"></i></div><div><div class="cc-timeline-title">{{ $activityLabels[$a->action] ?? $a->action }}</div><div class="cc-timeline-meta">{{ $a->user->name ?? 'Hệ thống' }} · {{ optional($a->created_at)->format('d/m/Y H:i') }}</div>@if(!empty($a->payload['reason']))<div class="cc-secondary">{{ $a->payload['reason'] }}</div>@endif</div></div>@empty<div class="cc-empty">Chưa có nhật ký.</div>@endforelse</div></div></div>
    </div></div>
</div></div>

@if($canReview)
<div class="modal fade" id="revisionModal" tabindex="-1"><div class="modal-dialog"><form method="post" action="{{ route('customer-consignments.request-revision',$c) }}" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">Yêu cầu Sales chỉnh sửa</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="cc-label">Nội dung cần sửa</label><textarea class="cc-textarea" name="reason" required></textarea></div><div class="modal-footer"><button type="button" class="cc-btn" data-bs-dismiss="modal">Đóng</button><button class="cc-btn cc-btn-primary">Gửi yêu cầu sửa</button></div></form></div></div>
<div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog"><form method="post" action="{{ route('customer-consignments.reject',$c) }}" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">Từ chối đơn ký gửi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="cc-label">Lý do từ chối</label><textarea class="cc-textarea" name="reason" required></textarea></div><div class="modal-footer"><button type="button" class="cc-btn" data-bs-dismiss="modal">Đóng</button><button class="cc-btn cc-btn-danger">Xác nhận từ chối</button></div></form></div></div>
@endif
@if($canCancel && !in_array($c->status,['active','completed','cancelled']))<div class="modal fade" id="cancelModal" tabindex="-1"><div class="modal-dialog"><form method="post" action="{{ route('customer-consignments.cancel',$c) }}" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">Hủy đơn {{ $c->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="cc-label">Lý do hủy</label><textarea class="cc-textarea" name="reason" required></textarea></div><div class="modal-footer"><button type="button" class="cc-btn" data-bs-dismiss="modal">Đóng</button><button class="cc-btn cc-btn-danger">Xác nhận hủy</button></div></form></div></div>@endif
@endsection
