@extends('layouts.app')
@section('title','Tạo yêu cầu giao hàng ký gửi')
@section('styles')<link rel="stylesheet" href="{{ asset('css/customer-consignments.css') }}?v={{ @filemtime(public_path('css/customer-consignments.css')) ?: time() }}">@endsection
@section('content')
@php $c=$customerConsignment; @endphp
<div class="cc-page"><div class="cc-wrap">
    <div class="cc-head"><div><div class="cc-eyebrow">{{ $c->code }}</div><h1 class="cc-title">Yêu cầu giao hàng ký gửi</h1><div class="cc-sub">Chọn số lượng và đúng serial cần bàn giao trong đợt này.</div></div><div class="cc-actions"><a class="cc-btn" href="{{ route('customer-consignments.show',$c) }}"><i class="bi bi-arrow-left"></i> Quay lại hồ sơ</a></div></div>
    @if(session('error'))<div class="cc-alert cc-alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="cc-alert cc-alert-danger">{{ $errors->first() }}</div>@endif

    <form method="post" action="{{ route('customer-consignments.releases.store',$c) }}">@csrf
        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">Thông tin giao nhận</h2><div class="cc-card-sub">Đơn {{ $c->order->order_code ?? '#'.$c->order_id }} · {{ $c->order->lead->customer->name ?? 'Khách hàng' }}</div></div></div><div class="cc-card-body"><div class="cc-form-grid-3">
            <div><label class="cc-label">Ngày giao thực tế</label><input class="cc-input" type="date" name="delivery_date" value="{{ old('delivery_date',now()->toDateString()) }}" required></div>
            <div><label class="cc-label">Thời hạn bảo hành serial</label><input class="cc-input" type="number" min="1" max="240" name="warranty_months" value="{{ old('warranty_months',60) }}" required></div>
            <div><label class="cc-label">Đơn vị vận chuyển</label><input class="cc-input" name="shipping_carrier" value="{{ old('shipping_carrier') }}" placeholder="Ahamove, chành xe..."></div>
            <div><label class="cc-label">Người nhận</label><input class="cc-input" name="receiver_name" value="{{ old('receiver_name',$c->receiver_name) }}"></div>
            <div><label class="cc-label">Điện thoại</label><input class="cc-input" name="receiver_phone" value="{{ old('receiver_phone',$c->receiver_phone) }}"></div>
            <div><label class="cc-label">Mã vận đơn</label><input class="cc-input" name="tracking_number" value="{{ old('tracking_number') }}"></div>
            <div class="cc-span-2"><label class="cc-label">Địa chỉ giao</label><textarea class="cc-textarea" name="shipping_address">{{ old('shipping_address',$c->shipping_address) }}</textarea></div>
            <div><label class="cc-label">Ghi chú</label><textarea class="cc-textarea" name="note">{{ old('note') }}</textarea></div>
        </div></div></div>

        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">Hàng giao trong đợt này</h2><div class="cc-card-sub">Không được vượt số lượng còn lại; sản phẩm serial phải chọn đúng bằng số lượng giao.</div></div></div><div class="cc-card-body" style="display:grid;gap:12px">
            @foreach($c->items as $item)
                @if($item->remaining_quantity>0)
                <div class="cc-info" style="padding:14px"><div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start"><div><div class="cc-main">{{ $item->product->name ?? 'SP#'.$item->product_id }}</div><div class="cc-secondary">{{ $item->product->sku ?? '' }} · Còn ký gửi {{ number_format($item->remaining_quantity) }}</div></div><div style="width:150px"><label class="cc-label">Số lượng giao</label><input class="cc-input" type="number" min="0" max="{{ $item->remaining_quantity }}" name="quantities[{{ $item->id }}]" value="{{ old('quantities.'.$item->id,0) }}"></div></div>
                    @if(($item->product->is_serialized??false))
                    <div style="margin-top:10px"><label class="cc-label">Chọn serial giao</label><div class="cc-serial-box">@forelse($item->serials as $serial)<label class="cc-check"><input type="checkbox" name="serials[{{ $item->id }}][]" value="{{ $serial->id }}" @checked(in_array($serial->id,(array)old('serials.'.$item->id,[])))><span>{{ $serial->code }}</span></label>@empty<div class="cc-secondary">Không còn serial đang giữ.</div>@endforelse</div></div>
                    @endif
                </div>
                @endif
            @endforeach
        </div></div>
        <div class="cc-actions" style="justify-content:flex-end"><a class="cc-btn" href="{{ route('customer-consignments.show',$c) }}">Hủy</a><button class="cc-btn cc-btn-primary"><i class="bi bi-send"></i> Tạo yêu cầu giao</button></div>
    </form>
</div></div>
@endsection
@section('scripts')<script src="{{ asset('js/customer-consignments.js') }}?v={{ @filemtime(public_path('js/customer-consignments.js')) ?: time() }}"></script>@endsection
