@extends('layouts.app')
@section('title','Tạo yêu cầu đổi trả')
@section('content')
<link rel="stylesheet" href="{{ asset('css/order-after-sales.css') }}">
<div class="oas-page">
    <div class="oas-head"><div><div class="oas-sub">Đơn {{ $order->order_code }}</div><h1 class="oas-title">Tạo yêu cầu hậu mãi</h1><div class="oas-sub">Chọn đúng nghiệp vụ. Đơn đã xuất kho không thể hủy trực tiếp.</div></div><a href="{{ route('orders.returns.index',$order) }}" class="oas-btn">← Quay lại</a></div>
    @if($errors->any())<div class="oas-alert oas-alert-warning"><strong>Vui lòng kiểm tra:</strong><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
    <form method="post" action="{{ route('orders.returns.store',$order) }}" enctype="multipart/form-data">@csrf
        <div class="oas-grid">
            <div class="oas-col-8">
                <div class="oas-card"><div class="oas-card-head"><h2 class="oas-card-title">1. Loại xử lý & sản phẩm</h2><span class="oas-badge {{ $order->inventory_issued?'oas-badge-green':'oas-badge-amber' }}">{{ $order->inventory_issued?'Đã xuất kho':'Chưa xuất kho' }}</span></div><div class="oas-card-body">
                    <div class="oas-form-grid">
                        <div><label class="oas-label">Loại xử lý *</label><select name="type" class="oas-select" required><option value="return">Hoàn trả hàng</option><option value="exchange">Đổi hàng</option><option value="recall">Thu hồi hàng đã xuất</option>@unless($order->inventory_issued)<option value="cancel">Hủy trước xuất kho</option>@endunless</select></div>
                        <div><label class="oas-label">Kho dự kiến nhận</label><select name="receiving_warehouse_id" class="oas-select"><option value="">Chọn sau</option>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                        <div><label class="oas-label">Lý do *</label><select name="reason_code" class="oas-select" required><option value="customer_change">Khách thay đổi nhu cầu</option><option value="wrong_item">Giao sai hàng</option><option value="technical_fault">Lỗi kỹ thuật</option><option value="shipping_damage">Hư hỏng vận chuyển</option><option value="warranty">Thu hồi bảo hành</option><option value="other">Khác</option></select></div>
                        <div><label class="oas-label">Phương án tài chính</label><select name="refund_method" class="oas-select"><option value="none">Chờ kiểm tra</option><option value="bank">Hoàn chuyển khoản</option><option value="cash">Hoàn tiền mặt</option><option value="debt_credit">Cấn trừ công nợ</option><option value="exchange_credit">Cấn sang đơn đổi</option></select></div>
                        <div class="oas-field-full"><label class="oas-label">Mô tả chi tiết *</label><textarea name="reason_detail" class="oas-textarea" required>{{ old('reason_detail') }}</textarea></div>
                    </div>
                    <div style="margin-top:16px">
                        @foreach($order->items as $item)
                            <div class="oas-item"><div class="oas-item-head"><div><div class="oas-product">{{ $item->product_name ?: $item->product->name ?? ('Sản phẩm #'.$item->product_id) }}</div><div class="oas-muted">Đã giao: {{ $item->quantity }} · Có thể yêu cầu: {{ $available[$item->id] ?? 0 }}</div></div><input class="oas-input" style="width:110px" type="number" min="0" max="{{ $available[$item->id] ?? 0 }}" name="items[{{ $item->id }}][quantity]" value="0"></div>
                                <div class="oas-form-grid"><div><label class="oas-label">Tình trạng dự kiến</label><select class="oas-select" name="items[{{ $item->id }}][condition]"><option value="sellable">Còn tốt</option><option value="opened_box">Đã mở hộp</option><option value="defective">Lỗi</option><option value="warranty_pending">Chờ bảo hành</option><option value="damaged">Hư hỏng</option></select></div><div><label class="oas-label">Phương án</label><select class="oas-select" name="items[{{ $item->id }}][resolution]"><option value="inspect">Chờ kiểm tra</option><option value="restock">Nhập lại kho</option><option value="exchange">Đổi sản phẩm</option><option value="warranty">Chuyển bảo hành</option><option value="refund">Hoàn tiền</option></select></div></div>
                                @if(($serialsByItem[$item->id] ?? collect())->count())<div style="margin-top:10px"><label class="oas-label">Serial khách hoàn</label><div style="display:flex;gap:10px;flex-wrap:wrap">@foreach($serialsByItem[$item->id] as $s)<label class="oas-badge"><input type="checkbox" name="items[{{ $item->id }}][serial_ids][]" value="{{ $s->id }}"> {{ $s->code }} ({{ $s->state }})</label>@endforeach</div></div>@endif
                            </div>
                        @endforeach
                    </div>
                </div></div>
            </div>
            <div class="oas-col-4">
                <div class="oas-card"><div class="oas-card-head"><h2 class="oas-card-title">2. Chi phí & hồ sơ</h2></div><div class="oas-card-body"><div style="margin-bottom:12px"><label class="oas-label">Phí xử lý</label><input class="oas-input" type="number" min="0" name="restocking_fee" value="0"></div><div style="margin-bottom:12px"><label class="oas-label">Phí vận chuyển trừ vào hoàn</label><input class="oas-input" type="number" min="0" name="shipping_fee" value="0"></div><div style="margin-bottom:12px"><label class="oas-label">Ảnh, video, biên bản</label><input class="oas-input" type="file" name="attachments[]" multiple></div><div><label class="oas-label">Ghi chú nội bộ</label><textarea class="oas-textarea" name="note"></textarea></div></div></div>
                <div class="oas-alert oas-alert-info" style="margin-top:14px">Sau khi tạo, yêu cầu phải được phê duyệt. Hệ thống chỉ cộng tồn khi kho đã nhận, kiểm tra và bấm “Nhập hoàn kho”.</div>
                <button class="oas-btn oas-btn-primary" style="width:100%;margin-top:10px">Tạo yêu cầu</button>
            </div>
        </div>
    </form>
</div>
@endsection
