@php
    $asset = $asset ?? null;
    $val = fn($field, $default = '') => old($field, $asset->{$field} ?? $default);
    $num = function ($field, $default = '') use ($asset) {
        $value = old($field, $asset->{$field} ?? $default);
        if ($value === null || $value === '') return '';
        $n = (float) $value;
        return abs($n - round($n)) < 0.00001 ? (string) (int) round($n) : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    };
@endphp

<div class="ap-field"><label>Mã tài sản</label><input class="ap-input" name="code" value="{{ $val('code') }}" placeholder="Tự sinh nếu bỏ trống"></div>
<div class="ap-field span2"><label>Tên tài sản *</label><input class="ap-input" name="name" value="{{ $val('name') }}" required placeholder="VD: Laptop Dell, Xe nâng, Máy hàn..."></div>
<div class="ap-field"><label>Nhóm</label><select class="ap-select" name="category_id"><option value="">Chọn nhóm</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected((string)$val('category_id') === (string)$c->id)>{{ $c->name }}</option>@endforeach</select></div>

<div class="ap-field"><label>Công ty</label><select class="ap-select" name="company_id"><option value="">Chọn công ty</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected((string)$val('company_id') === (string)$c->id)>{{ $c->name }}</option>@endforeach</select></div>
<div class="ap-field"><label>Người đang giữ</label><select class="ap-select" name="assigned_to"><option value="">Chưa bàn giao</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected((string)$val('assigned_to') === (string)$u->id)>{{ $u->name }}</option>@endforeach</select></div>
<div class="ap-field"><label>Bộ phận</label><input class="ap-input" name="department" value="{{ $val('department') }}" placeholder="Kế toán, kỹ thuật..."></div>
<div class="ap-field"><label>Serial / IMEI</label><input class="ap-input" name="serial_no" value="{{ $val('serial_no') }}" placeholder="Số serial"></div>

<div class="ap-field"><label>Ngày mua</label><input class="ap-input" type="date" name="purchase_date" value="{{ $val('purchase_date') }}"></div>
<div class="ap-field"><label>Ngày bắt đầu dùng</label><input class="ap-input" type="date" name="start_use_date" value="{{ $val('start_use_date') }}"></div>
<div class="ap-field"><label>Bảo hành đến</label><input class="ap-input" type="date" name="warranty_until" value="{{ $val('warranty_until') }}"></div>
<div class="ap-field"><label>Bảo trì tiếp theo</label><input class="ap-input" type="date" name="next_maintenance_date" value="{{ $val('next_maintenance_date') }}"></div>

<div class="ap-field"><label>Nguyên giá</label><input class="ap-input" name="original_cost" value="{{ $num('original_cost', 0) }}" inputmode="decimal" data-money placeholder="VD: 15000000"></div>
<div class="ap-field"><label>Giá trị còn lại tối thiểu</label><input class="ap-input" name="salvage_value" value="{{ $num('salvage_value', 0) }}" inputmode="decimal" data-money></div>
<div class="ap-field"><label>Thời gian KH</label><input class="ap-input" type="number" min="1" max="600" name="useful_life_months" value="{{ $val('useful_life_months', 36) }}"><small>Đơn vị: tháng</small></div>
<div class="ap-field"><label>Phương pháp KH</label><select class="ap-select" name="depreciation_method"><option value="straight_line" @selected($val('depreciation_method', 'straight_line') === 'straight_line')>Đường thẳng</option></select></div>

<div class="ap-field"><label>Trạng thái</label><select class="ap-select" name="status">@foreach($statuses as $k=>$v)<option value="{{ $k }}" @selected($val('status', 'active') === $k)>{{ $v }}</option>@endforeach</select></div>
<div class="ap-field"><label>Tình trạng</label><select class="ap-select" name="condition">@foreach($conditions as $k=>$v)<option value="{{ $k }}" @selected($val('condition', 'good') === $k)>{{ $v }}</option>@endforeach</select></div>
<div class="ap-field"><label>Nhà cung cấp</label><input class="ap-input" name="vendor" value="{{ $val('vendor') }}"></div>
<div class="ap-field"><label>Số hóa đơn/CT</label><input class="ap-input" name="invoice_no" value="{{ $val('invoice_no') }}"></div>

<div class="ap-field span2"><label>Vị trí</label><input class="ap-input" name="location" value="{{ $val('location') }}" placeholder="Văn phòng, kho, công trình..."></div>
<div class="ap-field span2"><label>File chứng từ</label><input class="ap-input" type="file" name="files[]" multiple></div>
<div class="ap-field span4"><label>Ghi chú</label><textarea class="ap-textarea" name="note" placeholder="Thông tin mua, bàn giao, cấu hình, lưu ý...">{{ $val('note') }}</textarea></div>
