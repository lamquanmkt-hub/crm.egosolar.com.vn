@extends('layouts.app')
@section('title', 'Tạo yêu cầu tặng quà')
@push('styles')<link rel="stylesheet" href="{{ asset('css/ego-gifts.css') }}?v={{ file_exists(public_path('css/ego-gifts.css')) ? filemtime(public_path('css/ego-gifts.css')) : '1.0.0' }}">@endpush
@section('content')
<div class="gift-page">
    <div class="gift-page-head"><div><div class="gift-eyebrow">YÊU CẦU TẶNG QUÀ</div><h1>Tạo yêu cầu mới</h1><p>Chọn khách hàng trực tiếp từ CRM và khai báo quà cần tặng.</p></div><a class="gift-btn gift-btn--light" href="{{ route('hr.gifts.requests.index') }}"><i class="bi bi-arrow-left"></i>Quay lại</a></div>
    @include('hr.gifts.partials.nav')
    @include('hr.gifts.partials.alerts')
    <form method="post" action="{{ route('hr.gifts.requests.store') }}">@csrf
        <div class="gift-detail-grid">
            <section class="gift-card gift-form-card">
                <div class="gift-card-head"><div><h2>1. Khách hàng & giao nhận</h2><p>Khách hàng được lấy theo quyền xem trong CRM.</p></div></div>
                <div class="gift-form-grid">
                    <label class="gift-col-2">Khách hàng CRM<select id="giftCustomerSelect" class="gift-select" name="customer_id" required><option value="">Tìm theo tên, số điện thoại...</option></select></label>
                    <label>Số điện thoại<input id="giftCustomerPhone" class="gift-input" readonly></label>
                    <label>Ngày dự kiến giao<input class="gift-input" type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date') }}"></label>
                    <label class="gift-col-2">Địa chỉ giao<textarea id="giftCustomerAddress" class="gift-textarea" name="delivery_address">{{ old('delivery_address') }}</textarea></label>
                    <label class="gift-col-2">Lý do / chương trình tặng<input class="gift-input" name="reason" value="{{ old('reason') }}" placeholder="Sinh nhật, tri ân, ký hợp đồng..."></label>
                    <label class="gift-col-2">Ghi chú<textarea class="gift-textarea" name="note">{{ old('note') }}</textarea></label>
                </div>
            </section>
            <aside class="gift-card gift-guide"><h2>Luồng xử lý</h2><ol><li><span>1</span>Tạo yêu cầu nháp</li><li><span>2</span>Gửi người có thẩm quyền duyệt</li><li><span>3</span>Duyệt và tự động trừ tồn</li><li><span>4</span>Cập nhật chuẩn bị, đang giao, đã giao</li></ol><div class="gift-note"><i class="bi bi-shield-check"></i>Hệ thống không cho duyệt nếu số lượng tồn không đủ.</div></aside>
        </div>
        <section class="gift-card gift-form-card mt-3"><div class="gift-card-head"><div><h2>2. Danh sách quà</h2><p>Có thể thêm nhiều loại quà trên cùng yêu cầu.</p></div></div><div class="gift-lines" id="requestLines"></div><div class="gift-line-actions"><button class="gift-btn gift-btn--light" type="button" id="addRequestLine"><i class="bi bi-plus-circle"></i>Thêm dòng quà</button><button class="gift-btn gift-btn--primary"><i class="bi bi-save"></i>Lưu yêu cầu nháp</button></div></section>
    </form>
</div>
<template id="requestLineTemplate"><div class="gift-line gift-line--request"><label>Quà tặng<select class="gift-select" name="gift_id[]" required><option value="">Chọn quà</option>@foreach($gifts as $gift)<option value="{{ $gift->id }}">{{ $gift->sku }} · {{ $gift->name }} · Tồn {{ number_format((float)$gift->current_stock,3,',','.') }} {{ $gift->unit }}</option>@endforeach</select></label><label>Số lượng<input class="gift-input" type="number" min="0.001" step="0.001" name="quantity[]" value="1" required></label><label>Ghi chú dòng<input class="gift-input" name="item_note[]"></label><button type="button" class="gift-remove-line"><i class="bi bi-x-lg"></i></button></div></template>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const customerEl=document.getElementById('giftCustomerSelect');
    const customerSelect=new TomSelect(customerEl,{valueField:'id',labelField:'text',searchField:['text'],loadThrottle:350,load:function(query,callback){fetch(`{{ route('hr.gifts.requests.customers.search') }}?q=${encodeURIComponent(query)}`,{headers:{'Accept':'application/json'}}).then(r=>r.json()).then(data=>callback(data.results||[])).catch(()=>callback())},onChange:function(value){const row=this.options[value]||{};document.getElementById('giftCustomerPhone').value=row.phone||'';const address=document.getElementById('giftCustomerAddress');if(!address.value)address.value=row.address||''}});
    const box=document.getElementById('requestLines'),tpl=document.getElementById('requestLineTemplate');
    function add(){const node=tpl.content.cloneNode(true);const line=node.querySelector('.gift-line');line.querySelector('.gift-remove-line').addEventListener('click',()=>line.remove());box.appendChild(node)}
    document.getElementById('addRequestLine').addEventListener('click',add);add();
});
</script>
@endpush
