{{-- Ô nhập một lỗi đối soát lương (HC04). Biến: $e (lỗi đang sửa hoặc null), $allowed (khoản đã kiểm của dòng), $components, $errorTypes, $resolutions. --}}
<div class="pa-field">
    <label>Khoản có lỗi *</label>
    <select class="pr-input" name="component" required>
        @foreach($components as $ck => $cdef)
            @continue(! in_array($ck, $allowed, true))
            <option value="{{ $ck }}" @selected(($e->component ?? '') === $ck)>{{ $cdef['label'] }}</option>
        @endforeach
    </select>
</div>
<div class="pa-field">
    <label>Loại lỗi *</label>
    <select class="pr-input" name="error_type" required>
        @foreach($errorTypes as $tk => $tl)<option value="{{ $tk }}" @selected(($e->error_type ?? '') === $tk)>{{ $tl }}</option>@endforeach
    </select>
</div>
<div class="pa-field pa-field--wide">
    <label>Nội dung lỗi *</label>
    <input class="pr-input" name="description" required minlength="5" maxlength="4000" value="{{ $e->description ?? '' }}" placeholder="VD: tính 22 công, chấm công thực tế 21 công">
</div>
<div class="pa-field">
    <label>Số đúng (đ / ngày)</label>
    <input class="pr-input" type="number" step="any" name="expected_amount" value="{{ $e->expected_amount ?? '' }}">
</div>
<div class="pa-field">
    <label>Số đã ghi trên bảng</label>
    <input class="pr-input" type="number" step="any" name="recorded_amount" value="{{ $e->recorded_amount ?? '' }}">
</div>
<div class="pa-field pa-field--wide">
    <label>Liên kết nguồn (chấm công, hồ sơ lương, KPI…)</label>
    <input class="pr-input" name="source_url" maxlength="500" value="{{ $e->source_url ?? '' }}" placeholder="/nhan-su/cham-cong?… hoặc https://…">
</div>
<div class="pa-field">
    <label>Xử lý *</label>
    <select class="pr-input" name="resolution_status" required>
        @foreach($resolutions as $rk => $rl)<option value="{{ $rk }}" @selected(($e->resolution_status ?? 'open') === $rk)>{{ $rl }}</option>@endforeach
    </select>
</div>
<div class="pa-field pa-field--wide" style="grid-column:span 3">
    <label>Cách xử lý (bắt buộc khi đã sửa / kỳ sau / không sửa)</label>
    <input class="pr-input" name="resolution" maxlength="4000" value="{{ $e->resolution ?? '' }}" placeholder="VD: sửa ngày công 21, tính lại dòng lúc 10:00">
</div>
