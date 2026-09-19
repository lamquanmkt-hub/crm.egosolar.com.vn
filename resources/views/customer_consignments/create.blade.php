@extends('layouts.app')
@php
    $editing = $editing ?? null;
    $isEdit = (bool) $editing;
    $initialItems = old('items');
    if ($initialItems === null && $editing) {
        $initialItems = $editing->items->map(fn($i) => [
            'product_id' => $i->product_id,
            'source_warehouse_id' => $i->source_warehouse_id,
            'quantity' => $i->quantity,
            'unit_price' => $i->unit_price,
            'item_note' => $i->item_note,
        ])->values()->all();
    }
    $initialItems = $initialItems ?: [['product_id'=>'','source_warehouse_id'=>'','quantity'=>1,'unit_price'=>0,'item_note'=>'']];
    $selectedCustomer = old('customer_id', $editing->customer_id ?? '');
@endphp
@section('title', $isEdit ? 'Sửa đơn ký gửi' : 'Tạo đơn ký gửi')
@section('styles')<link rel="stylesheet" href="{{ asset('css/customer-consignments.css') }}?v={{ @filemtime(public_path('css/customer-consignments.css')) ?: time() }}">@endsection
@section('content')
<div class="cc-page"><div class="cc-wrap">
    <div class="cc-head">
        <div><div class="cc-eyebrow">Ký gửi hàng hóa</div><h1 class="cc-title">{{ $isEdit ? 'Chỉnh sửa đơn ký gửi' : 'Tạo đơn ký gửi mới' }}</h1><div class="cc-sub">Sales chọn khách hàng, sản phẩm, kho nguồn và số lượng. Admin/Giám đốc duyệt xong thì Kho mới được xuất hàng.</div></div>
        <div class="cc-actions"><a class="cc-btn" href="{{ route('customer-consignments.index') }}"><i class="bi bi-arrow-left"></i> Danh sách</a></div>
    </div>

    @if(session('error'))<div class="cc-alert cc-alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="cc-alert cc-alert-danger"><strong>Chưa thể lưu:</strong> {{ $errors->first() }}</div>@endif
    @if($editing?->revision_reason)<div class="cc-alert cc-alert-danger"><strong>Admin/Giám đốc yêu cầu chỉnh sửa:</strong> {{ $editing->revision_reason }}</div>@endif

    <div class="cc-card"><div class="cc-card-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;align-items:center">
            <div><div class="cc-badge blue">1</div> <strong>Sales tạo & chọn số lượng</strong></div>
            <div><div class="cc-badge amber">2</div> <strong>Admin/Giám đốc duyệt</strong></div>
            <div><div class="cc-badge green">3</div> <strong>Kho chọn serial & xuất</strong></div>
        </div>
    </div></div>

    <form method="post" action="{{ $isEdit ? route('customer-consignments.update',$editing) : route('customer-consignments.store') }}" id="consignmentForm">
        @csrf @if($isEdit) @method('put') @endif

        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">1. Khách hàng nhận ký gửi</h2><div class="cc-card-sub">Đây là đơn ký gửi độc lập, không liên quan đơn bán hàng.</div></div><a class="cc-btn cc-btn-sm" href="{{ route('customers.create') }}" target="_blank"><i class="bi bi-person-plus"></i> Tạo khách hàng</a></div><div class="cc-card-body">
            <div class="cc-form-grid">
                <div class="cc-span-2"><label class="cc-label">Khách hàng <span style="color:#dc3545">*</span></label><select class="cc-select" name="customer_id" id="customerSelect" required><option value="">-- Chọn khách hàng --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" data-phone="{{ $customer->phone }}" data-address="{{ $customer->address }}" @selected((string)$selectedCustomer===(string)$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' — '.$customer->phone : '' }}</option>@endforeach</select></div>
                <div><label class="cc-label">Hạn ký gửi dự kiến</label><input class="cc-input" type="date" name="expires_at" value="{{ old('expires_at',$editing?->expires_at?->toDateString() ?? now()->addDays(30)->toDateString()) }}"></div>
                <div><label class="cc-label">Người nhận</label><input class="cc-input" name="receiver_name" value="{{ old('receiver_name',$editing->receiver_name ?? '') }}"></div>
                <div><label class="cc-label">Số điện thoại</label><input class="cc-input" id="receiverPhone" name="receiver_phone" value="{{ old('receiver_phone',$editing->receiver_phone ?? '') }}"></div>
                <div class="cc-span-2"><label class="cc-label">Địa chỉ xuất hàng ký gửi <span style="color:#dc3545">*</span></label><textarea class="cc-textarea" id="shippingAddress" name="shipping_address" required>{{ old('shipping_address',$editing->shipping_address ?? '') }}</textarea></div>
            </div>
        </div></div>

        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">2. Sales chọn hàng và số lượng ký gửi</h2><div class="cc-card-sub">Chỉ chọn tối đa bằng tồn khả dụng. Kho sẽ kiểm tra lại tồn và chọn serial sau khi được duyệt.</div></div><button type="button" class="cc-btn cc-btn-primary" id="addItem"><i class="bi bi-plus-lg"></i> Thêm sản phẩm</button></div>
            <div class="cc-table-wrap"><table class="cc-table"><thead><tr><th style="min-width:260px">Sản phẩm</th><th style="min-width:190px">Kho nguồn</th><th>Tồn</th><th style="width:110px">SL ký gửi</th><th style="width:160px">Giá tham khảo</th><th>Ghi chú</th><th></th></tr></thead><tbody id="itemRows">
                @foreach($initialItems as $idx=>$row)
                <tr class="item-row">
                    <td><select class="cc-select product-select" name="items[{{ $idx }}][product_id]" required><option value="">-- Chọn sản phẩm --</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ (float)($product->price_agent ?: $product->price_retail ?: $product->price) }}" data-serialized="{{ $product->is_serialized ? 1 : 0 }}" @selected((string)($row['product_id']??'')===(string)$product->id)>{{ $product->name }}{{ $product->sku ? ' — '.$product->sku : '' }}</option>@endforeach</select><div class="cc-secondary serialized-note"></div></td>
                    <td><select class="cc-select warehouse-select" name="items[{{ $idx }}][source_warehouse_id]" required><option value="">-- Chọn kho --</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)($row['source_warehouse_id']??'')===(string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></td>
                    <td><strong class="stock-value">0</strong></td>
                    <td><input class="cc-input quantity-input" type="number" min="1" name="items[{{ $idx }}][quantity]" value="{{ $row['quantity']??1 }}" required></td>
                    <td><input class="cc-input price-input" type="number" min="0" step="1000" name="items[{{ $idx }}][unit_price]" value="{{ $row['unit_price']??0 }}"></td>
                    <td><input class="cc-input" name="items[{{ $idx }}][item_note]" value="{{ $row['item_note']??'' }}" placeholder="Tùy chọn"></td>
                    <td><button type="button" class="cc-btn cc-btn-sm remove-item"><i class="bi bi-trash"></i></button></td>
                </tr>
                @endforeach
            </tbody></table></div>
        </div>

        <div class="cc-card"><div class="cc-card-head"><div><h2 class="cc-card-title">3. Điều kiện ký gửi</h2><div class="cc-card-sub">Chưa ghi nhận doanh thu và chưa kích hoạt bảo hành ở bước này.</div></div></div><div class="cc-card-body">
            <label class="cc-label">Điều khoản / ghi chú cho Admin và Kho</label><textarea class="cc-textarea" name="terms_note" rows="4" placeholder="Mục đích ký gửi, thời hạn, điều kiện thu hồi, người phụ trách...">{{ old('terms_note',$editing->terms_note ?? '') }}</textarea>
        </div></div>

        <div class="cc-actions" style="justify-content:flex-end;position:sticky;bottom:10px;background:rgba(245,249,253,.94);padding:12px;border-radius:14px;z-index:3">
            <a class="cc-btn" href="{{ route('customer-consignments.index') }}">Hủy</a>
            <button class="cc-btn" name="action" value="draft"><i class="bi bi-save"></i> Lưu nháp</button>
            <button class="cc-btn cc-btn-primary" name="action" value="submit"><i class="bi bi-send"></i> Gửi Admin/Giám đốc duyệt</button>
        </div>
    </form>
</div></div>

<script id="stockData" type="application/json">@json($stockMap)</script>
<template id="itemTemplate"><tr class="item-row"><td><select class="cc-select product-select" required><option value="">-- Chọn sản phẩm --</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ (float)($product->price_agent ?: $product->price_retail ?: $product->price) }}" data-serialized="{{ $product->is_serialized ? 1 : 0 }}">{{ $product->name }}{{ $product->sku ? ' — '.$product->sku : '' }}</option>@endforeach</select><div class="cc-secondary serialized-note"></div></td><td><select class="cc-select warehouse-select" required><option value="">-- Chọn kho --</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></td><td><strong class="stock-value">0</strong></td><td><input class="cc-input quantity-input" type="number" min="1" value="1" required></td><td><input class="cc-input price-input" type="number" min="0" step="1000" value="0"></td><td><input class="cc-input note-input" placeholder="Tùy chọn"></td><td><button type="button" class="cc-btn cc-btn-sm remove-item"><i class="bi bi-trash"></i></button></td></tr></template>
@endsection
@section('scripts')
<script>
(() => {
    const stock = JSON.parse(document.getElementById('stockData').textContent || '{}');
    const tbody = document.getElementById('itemRows');
    const template = document.getElementById('itemTemplate');
    const customer = document.getElementById('customerSelect');
    const phone = document.getElementById('receiverPhone');
    const address = document.getElementById('shippingAddress');

    function reindex() {
        [...tbody.querySelectorAll('.item-row')].forEach((row, i) => {
            row.querySelector('.product-select').name = `items[${i}][product_id]`;
            row.querySelector('.warehouse-select').name = `items[${i}][source_warehouse_id]`;
            row.querySelector('.quantity-input').name = `items[${i}][quantity]`;
            row.querySelector('.price-input').name = `items[${i}][unit_price]`;
            const note = row.querySelector('.note-input') || row.querySelector('input[name*="item_note"]');
            if (note) note.name = `items[${i}][item_note]`;
        });
    }
    function refresh(row, setPrice=false) {
        const p = row.querySelector('.product-select');
        const w = row.querySelector('.warehouse-select');
        const qty = row.querySelector('.quantity-input');
        const available = Number(stock[p.value]?.[w.value] || 0);
        row.querySelector('.stock-value').textContent = available.toLocaleString('vi-VN');
        qty.max = available > 0 ? available : 0;
        const opt = p.selectedOptions[0];
        row.querySelector('.serialized-note').textContent = opt?.dataset.serialized === '1' ? 'Kho sẽ chọn đúng serial sau khi được duyệt.' : '';
        if (setPrice && opt?.dataset.price) row.querySelector('.price-input').value = Math.round(Number(opt.dataset.price));
    }
    function bind(row) {
        row.querySelector('.product-select').addEventListener('change', () => refresh(row, true));
        row.querySelector('.warehouse-select').addEventListener('change', () => refresh(row));
        row.querySelector('.remove-item').addEventListener('click', () => {
            if (tbody.querySelectorAll('.item-row').length > 1) row.remove();
            else { row.querySelectorAll('select').forEach(x=>x.value=''); row.querySelector('.quantity-input').value=1; }
            reindex();
        });
        refresh(row);
    }
    tbody.querySelectorAll('.item-row').forEach(bind);
    document.getElementById('addItem').addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        tbody.appendChild(row); bind(row); reindex();
    });
    customer.addEventListener('change', () => {
        const o = customer.selectedOptions[0];
        if (!phone.value) phone.value = o?.dataset.phone || '';
        if (!address.value) address.value = o?.dataset.address || '';
    });
    document.getElementById('consignmentForm').addEventListener('submit', e => {
        let ok = true;
        tbody.querySelectorAll('.item-row').forEach(row => {
            const p=row.querySelector('.product-select').value, w=row.querySelector('.warehouse-select').value;
            const q=Number(row.querySelector('.quantity-input').value||0), a=Number(stock[p]?.[w]||0);
            if (!p || !w || q < 1 || q > a) ok=false;
        });
        if (!ok) { e.preventDefault(); alert('Kiểm tra lại sản phẩm, kho nguồn và số lượng. Số lượng ký gửi không được vượt tồn kho.'); }
    });
    reindex();
})();
</script>
@endsection
