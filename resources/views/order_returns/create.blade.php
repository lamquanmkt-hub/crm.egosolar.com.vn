@extends('layouts.app')
@section('title','Tạo yêu cầu đổi trả')
@section('content')
<link rel="stylesheet" href="{{ asset('css/order-after-sales.css') }}">
<style>
    .oas-return-pick{display:flex;align-items:center;gap:8px;font-weight:800;color:#0f766e;cursor:pointer}
    .oas-return-pick input{width:18px;height:18px}
    .oas-item[data-selected="0"] .oas-item-fields{opacity:.48}
    .oas-item[data-selected="0"]{border-style:dashed}
    .oas-qty-wrap{display:flex;align-items:center;gap:8px}
    .oas-qty-wrap .oas-input{width:110px}
    .oas-helper{margin:12px 0;padding:10px 12px;border-radius:10px;background:#ecfeff;color:#155e75;font-size:13px;font-weight:700}
</style>
<div class="oas-page">
    <div class="oas-head">
        <div>
            <div class="oas-sub">Đơn {{ $order->order_code }}</div>
            <h1 class="oas-title">Tạo yêu cầu hậu mãi</h1>
            <div class="oas-sub">Có thể hoàn một phần: đơn có 2 sản phẩm vẫn được chọn đúng 1 sản phẩm để hoàn.</div>
        </div>
        <a href="{{ route('orders.returns.index',$order) }}" class="oas-btn">← Quay lại</a>
    </div>

    @if($errors->any())
        <div class="oas-alert oas-alert-warning">
            <strong>Vui lòng kiểm tra:</strong>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="post" action="{{ route('orders.returns.store',$order) }}" enctype="multipart/form-data" id="oas-return-form">
        @csrf
        <div class="oas-grid">
            <div class="oas-col-8">
                <div class="oas-card">
                    <div class="oas-card-head">
                        <h2 class="oas-card-title">1. Loại xử lý & sản phẩm</h2>
                        <span class="oas-badge {{ $order->inventory_issued?'oas-badge-green':'oas-badge-amber' }}">{{ $order->inventory_issued?'Đã xuất kho':'Chưa xuất kho' }}</span>
                    </div>
                    <div class="oas-card-body">
                        <div class="oas-form-grid">
                            <div>
                                <label class="oas-label">Loại xử lý *</label>
                                <select name="type" class="oas-select" required id="oas-return-type">
                                    <option value="return" @selected(old('type','return')==='return')>Hoàn trả hàng</option>
                                    <option value="exchange" @selected(old('type')==='exchange')>Đổi hàng</option>
                                    <option value="recall" @selected(old('type')==='recall')>Thu hồi hàng đã xuất</option>
                                    @unless($order->inventory_issued)<option value="cancel" @selected(old('type')==='cancel')>Hủy trước xuất kho</option>@endunless
                                </select>
                            </div>
                            <div>
                                <label class="oas-label">Kho dự kiến nhận</label>
                                <select name="receiving_warehouse_id" class="oas-select">
                                    <option value="">Chọn sau</option>
                                    @foreach($warehouses as $w)<option value="{{ $w->id }}" @selected((string)old('receiving_warehouse_id')===(string)$w->id)>{{ $w->name }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="oas-label">Lý do *</label>
                                <select name="reason_code" class="oas-select" required>
                                    @foreach(['customer_change'=>'Khách thay đổi nhu cầu','wrong_item'=>'Giao sai hàng','technical_fault'=>'Lỗi kỹ thuật','shipping_damage'=>'Hư hỏng vận chuyển','warranty'=>'Thu hồi bảo hành','other'=>'Khác'] as $value=>$label)
                                        <option value="{{ $value }}" @selected(old('reason_code','customer_change')===$value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="oas-label">Phương án tài chính</label>
                                <select name="refund_method" class="oas-select">
                                    @foreach(['none'=>'Chờ kiểm tra','bank'=>'Hoàn chuyển khoản','cash'=>'Hoàn tiền mặt','debt_credit'=>'Cấn trừ công nợ','exchange_credit'=>'Cấn sang đơn đổi'] as $value=>$label)
                                        <option value="{{ $value }}" @selected(old('refund_method','none')===$value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="oas-field-full">
                                <label class="oas-label">Mô tả chi tiết *</label>
                                <textarea name="reason_detail" class="oas-textarea" required>{{ old('reason_detail') }}</textarea>
                            </div>
                        </div>

                        <div class="oas-helper">Nếu khách chỉ trả 1 trong 2 sản phẩm: bật “Hoàn sản phẩm này” ở đúng dòng cần trả, nhập SL = 1 và chọn đúng serial của sản phẩm đó. Dòng còn lại để tắt.</div>

                        <div style="margin-top:16px" id="oas-return-items">
                            @foreach($order->items as $item)
                                @php
                                    $maxQty = max(0, (int)($available[$item->id] ?? 0));
                                    $oldQty = min($maxQty, max(0, (int)old("items.{$item->id}.quantity", 0)));
                                    $selected = $oldQty > 0;
                                    $itemSerials = collect($serialsByItem[$item->id] ?? []);
                                    $oldSerialIds = array_map('intval', (array)old("items.{$item->id}.serial_ids", []));
                                @endphp
                                <div class="oas-item" data-return-item data-selected="{{ $selected ? '1' : '0' }}" data-max="{{ $maxQty }}" data-serialized="{{ $itemSerials->isNotEmpty() ? '1' : '0' }}">
                                    <div class="oas-item-head">
                                        <div>
                                            <div class="oas-product">{{ $item->product_name ?: $item->product->name ?? ('Sản phẩm #'.$item->product_id) }}</div>
                                            <div class="oas-muted">Đã giao: {{ $item->quantity }} · Còn có thể hoàn: {{ $maxQty }}</div>
                                            <label class="oas-return-pick" style="margin-top:8px">
                                                <input type="checkbox" data-return-toggle @checked($selected) @disabled($maxQty <= 0)>
                                                <span>{{ $maxQty > 0 ? 'Hoàn sản phẩm này' : 'Đã hết số lượng có thể hoàn' }}</span>
                                            </label>
                                        </div>
                                        <div class="oas-qty-wrap">
                                            <label class="oas-label" style="margin:0">SL hoàn</label>
                                            <input class="oas-input" data-return-qty type="number" min="0" max="{{ $maxQty }}" name="items[{{ $item->id }}][quantity]" value="{{ $oldQty }}" @disabled(!$selected)>
                                        </div>
                                    </div>

                                    <div class="oas-item-fields">
                                        <div class="oas-form-grid">
                                            <div>
                                                <label class="oas-label">Tình trạng dự kiến</label>
                                                <select class="oas-select" data-return-field name="items[{{ $item->id }}][condition]" @disabled(!$selected)>
                                                    @foreach(['sellable'=>'Còn tốt','opened_box'=>'Đã mở hộp','defective'=>'Lỗi','warranty_pending'=>'Chờ bảo hành','damaged'=>'Hư hỏng'] as $value=>$label)
                                                        <option value="{{ $value }}" @selected(old("items.{$item->id}.condition",'sellable')===$value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="oas-label">Phương án</label>
                                                <select class="oas-select" data-return-field name="items[{{ $item->id }}][resolution]" @disabled(!$selected)>
                                                    @foreach(['inspect'=>'Chờ kiểm tra','restock'=>'Nhập lại kho','exchange'=>'Đổi sản phẩm','warranty'=>'Chuyển bảo hành','refund'=>'Hoàn tiền'] as $value=>$label)
                                                        <option value="{{ $value }}" @selected(old("items.{$item->id}.resolution",'inspect')===$value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        @if($itemSerials->isNotEmpty())
                                            <div style="margin-top:10px">
                                                <label class="oas-label">Serial khách hoàn — chọn đúng bằng SL hoàn</label>
                                                <div style="display:flex;gap:10px;flex-wrap:wrap" data-return-serials>
                                                    @foreach($itemSerials as $s)
                                                        <label class="oas-badge">
                                                            <input type="checkbox" data-return-serial name="items[{{ $item->id }}][serial_ids][]" value="{{ $s->id }}" @checked(in_array((int)$s->id,$oldSerialIds,true)) @disabled(!$selected)>
                                                            {{ $s->code }} ({{ $s->state }})
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="oas-col-4">
                <div class="oas-card">
                    <div class="oas-card-head"><h2 class="oas-card-title">2. Chi phí & hồ sơ</h2></div>
                    <div class="oas-card-body">
                        <div style="margin-bottom:12px"><label class="oas-label">Phí xử lý</label><input class="oas-input" type="number" min="0" name="restocking_fee" value="{{ old('restocking_fee',0) }}"></div>
                        <div style="margin-bottom:12px"><label class="oas-label">Phí vận chuyển trừ vào hoàn</label><input class="oas-input" type="number" min="0" name="shipping_fee" value="{{ old('shipping_fee',0) }}"></div>
                        <div style="margin-bottom:12px"><label class="oas-label">Ảnh, video, biên bản</label><input class="oas-input" type="file" name="attachments[]" multiple></div>
                        <div><label class="oas-label">Ghi chú nội bộ</label><textarea class="oas-textarea" name="note">{{ old('note') }}</textarea></div>
                    </div>
                </div>
                <div class="oas-alert oas-alert-info" style="margin-top:14px">Sau khi tạo, yêu cầu phải được phê duyệt. Hệ thống chỉ cộng tồn khi kho đã nhận, kiểm tra và bấm “Nhập hoàn kho”.</div>
                <button class="oas-btn oas-btn-primary" style="width:100%;margin-top:10px" type="submit">Tạo yêu cầu</button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const form = document.getElementById('oas-return-form');
    const type = document.getElementById('oas-return-type');
    if (!form) return;

    const rows = Array.from(form.querySelectorAll('[data-return-item]'));

    function setRow(row, selected) {
        const toggle = row.querySelector('[data-return-toggle]');
        const qty = row.querySelector('[data-return-qty]');
        const fields = row.querySelectorAll('[data-return-field], [data-return-serial]');
        const max = Number(row.dataset.max || 0);

        selected = Boolean(selected && max > 0);
        row.dataset.selected = selected ? '1' : '0';
        if (toggle) toggle.checked = selected;
        if (qty) {
            qty.disabled = !selected;
            if (selected && Number(qty.value || 0) < 1) qty.value = '1';
            if (!selected) qty.value = '0';
        }
        fields.forEach(el => { el.disabled = !selected; });

        if (!selected) {
            row.querySelectorAll('[data-return-serial]').forEach(el => { el.checked = false; });
        } else {
            const serials = Array.from(row.querySelectorAll('[data-return-serial]'));
            if (serials.length === 1 && Number(qty?.value || 0) === 1) serials[0].checked = true;
        }
    }

    rows.forEach(row => {
        const toggle = row.querySelector('[data-return-toggle]');
        const qty = row.querySelector('[data-return-qty]');
        if (toggle) toggle.addEventListener('change', () => setRow(row, toggle.checked));
        if (qty) qty.addEventListener('input', () => {
            const max = Number(row.dataset.max || 0);
            let value = Number(qty.value || 0);
            if (value < 0) value = 0;
            if (value > max) value = max;
            qty.value = String(value);
            if (value === 0) setRow(row, false);
        });
        row.querySelectorAll('[data-return-serial]').forEach(serial => {
            serial.addEventListener('change', () => {
                if (!serial.checked) return;
                const wanted = Number(qty?.value || 0);
                const checked = row.querySelectorAll('[data-return-serial]:checked').length;
                if (checked > wanted) {
                    serial.checked = false;
                    alert('Số serial chọn không được vượt quá số lượng hoàn.');
                }
            });
        });
    });

    form.addEventListener('submit', function (e) {
        if (type && type.value === 'cancel') return;
        const selectedRows = rows.filter(row => row.dataset.selected === '1' && Number(row.querySelector('[data-return-qty]')?.value || 0) > 0);
        if (selectedRows.length === 0) {
            e.preventDefault();
            alert('Hãy chọn ít nhất một sản phẩm cần hoàn.');
            return;
        }

        for (const row of selectedRows) {
            if (row.dataset.serialized !== '1') continue;
            const qty = Number(row.querySelector('[data-return-qty]')?.value || 0);
            const serialCount = row.querySelectorAll('[data-return-serial]:checked').length;
            if (serialCount !== qty) {
                e.preventDefault();
                alert('Sản phẩm quản lý serial: số serial phải đúng bằng số lượng hoàn.');
                return;
            }
        }
    });
})();
</script>
@endsection
