@php $item = $item ?? null; @endphp

<div class="hs-form-grid">
    <div class="hs-field full">
        <label>Tên hồ sơ *</label>
        <input class="hs-input" name="title" required value="{{ old('title', $item->title ?? '') }}" placeholder="VD: Hồ sơ hợp đồng khách hàng ABC">
    </div>

    <div class="hs-field">
        <label>Loại hồ sơ</label>
        <input class="hs-input" name="document_type" value="{{ old('document_type', $item->document_type ?? '') }}" placeholder="Hợp đồng, nghiệm thu, thanh toán...">
    </div>

    <div class="hs-field">
        <label>Khách hàng / Đối tượng</label>
        <input class="hs-input" name="customer_name" value="{{ old('customer_name', $item->customer_name ?? '') }}" placeholder="Tên khách hàng / dự án">
    </div>

    <div class="hs-field">
        <label>Phòng ban liên quan</label>
        <input class="hs-input" name="department_name" value="{{ old('department_name', $item->department_name ?? '') }}" placeholder="Sales, Kỹ thuật, Kế toán...">
    </div>

    <div class="hs-field">
        <label>Mức ưu tiên</label>
        <select class="hs-select" name="priority">
            @foreach($priorities as $key => $label)
                <option value="{{ $key }}" @selected(old('priority', $item->priority ?? 'normal') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="hs-field">
        <label>Người phụ trách / nhận hồ sơ</label>
        <select class="hs-select" name="assigned_to">
            <option value="">Chưa chọn</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((string) old('assigned_to', $item->assigned_to ?? '') === (string) $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="hs-field">
        <label>Người đang giữ hồ sơ</label>
        <input class="hs-input" name="current_holder" value="{{ old('current_holder', $item->current_holder ?? '') }}" placeholder="Tên người đang giữ hồ sơ">
    </div>

    <div class="hs-field">
        <label>Hạn xử lý</label>
        <input class="hs-input" type="date" name="due_date" value="{{ old('due_date', $item->due_date ?? '') }}">
    </div>

    <div class="hs-field full">
        <label>Mô tả hồ sơ</label>
        <textarea class="hs-textarea" name="description" placeholder="Mô tả nội dung hồ sơ, số lượng bản, yêu cầu xử lý...">{{ old('description', $item->description ?? '') }}</textarea>
    </div>

    <div class="hs-field full">
        <label>Ghi chú</label>
        <textarea class="hs-textarea" name="note" placeholder="Ghi chú nội bộ khi giao nhận hồ sơ...">{{ old('note', $item->note ?? '') }}</textarea>
    </div>

    <div class="hs-field full">
        <label>Vị trí lưu trữ</label>
        <input class="hs-input" name="storage_location" value="{{ old('storage_location', $item->storage_location ?? '') }}" placeholder="Tủ hồ sơ, kệ số, folder lưu trữ...">
    </div>

    @if(\App\Services\Hr\Kpi\Sources\DocumentAccuracySource::ready())
        @php
            $kpiOn = (string) old('kpi_tracked', ! empty($item->kpi_tracked) ? '1' : '') === '1';
            $kpiProcessor = (string) old('processor_id', $item->processor_id ?? auth()->id());
            $kpiReviewer = (string) old('reviewer_id', $item->reviewer_id ?? '');
            $kpiClass = (string) old('classification', $item->classification ?? '');
            $kpiRouted = (string) old('routed_to_user_id', $item->routed_to_user_id ?? '');
        @endphp
        <div class="hs-field full hs-kpi-box" data-hs-kpi-box>
            <label class="hs-kpi-toggle">
                <input type="hidden" name="kpi_tracked" value="0">
                <input type="checkbox" name="kpi_tracked" value="1" @checked($kpiOn) data-hs-kpi-toggle>
                <span><strong>Công văn/giấy tờ HCNS — theo dõi KPI HC07 (phân loại, luân chuyển chính xác)</strong>
                    <small>Chỉ đánh dấu công văn/giấy tờ hành chính cần kiểm tra. Hồ sơ khách hàng thông thường không chọn.</small></span>
            </label>
            <div class="hs-form-grid" data-hs-kpi-fields @if(! $kpiOn) hidden @endif style="margin-top:10px">
                <div class="hs-field">
                    <label>Người xử lý HCNS (chịu KPI) *</label>
                    <select class="hs-select" name="processor_id" data-hs-kpi-required>
                        <option value="">Chọn người xử lý</option>
                        @foreach($users as $user)<option value="{{ $user->id }}" @selected($kpiProcessor === (string) $user->id)>{{ $user->name }}</option>@endforeach
                    </select>
                </div>
                <div class="hs-field">
                    <label>Người kiểm tra độc lập *</label>
                    <select class="hs-select" name="reviewer_id" data-hs-kpi-required>
                        <option value="">Chọn người kiểm (khác người xử lý)</option>
                        @foreach($users as $user)<option value="{{ $user->id }}" @selected($kpiReviewer === (string) $user->id)>{{ $user->name }}</option>@endforeach
                    </select>
                </div>
                <div class="hs-field">
                    <label>Phân loại</label>
                    <select class="hs-select" name="classification">
                        <option value="">Chưa phân loại</option>
                        @foreach((array) config('hcns_kpi.document_classes', []) as $code => $label)<option value="{{ $code }}" @selected($kpiClass === (string) $code)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="hs-field">
                    <label>Luân chuyển đến (người nhận)</label>
                    <select class="hs-select" name="routed_to_user_id">
                        <option value="">Chưa chọn / nơi nhận ngoài công ty</option>
                        @foreach($users as $user)<option value="{{ $user->id }}" @selected($kpiRouted === (string) $user->id)>{{ $user->name }}</option>@endforeach
                    </select>
                </div>
                <div class="hs-field full">
                    <label>Nơi nhận khác / ghi chú luân chuyển</label>
                    <input class="hs-input" name="routed_to_note" maxlength="255" value="{{ old('routed_to_note', $item->routed_to_note ?? '') }}" placeholder="VD: Sở KH&ĐT, Phòng Kế toán — bản gốc">
                </div>
                <div class="hs-field full hs-muted">Người xử lý <b>Gửi kiểm tra</b> ở trang chi tiết → người kiểm chấm 3 mục Đạt/Không đạt. Sửa nội dung hoặc tệp sau khi gửi sẽ phải gửi và kiểm lại.</div>
            </div>
        </div>
    @endif

    <div class="hs-field full">
        <label>File hồ sơ đính kèm</label>
        <input class="hs-input" type="file" name="attachments[]" multiple>
        <div class="hs-muted" style="margin-top:6px">Có thể chọn nhiều file. Hỗ trợ ảnh, PDF, Word, Excel, file nén...</div>
    </div>
</div>

@once
<style>
    .hs-kpi-box{border:1px solid #cfe0f3;border-radius:16px;background:#f7fbff;padding:12px}
    .hs-kpi-toggle{display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin:0}
    .hs-kpi-toggle input[type=checkbox]{width:18px;height:18px;margin-top:3px}
    .hs-kpi-toggle strong{display:block;color:#123a64;font-size:13.5px}
    .hs-kpi-toggle small{display:block;color:#5b6f86;font-size:12px;font-weight:600}
</style>
<script>
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-hs-kpi-toggle]')) { return; }
        var box = e.target.closest('[data-hs-kpi-box]');
        var fields = box && box.querySelector('[data-hs-kpi-fields]');
        if (!fields) { return; }
        fields.hidden = !e.target.checked;
        fields.querySelectorAll('[data-hs-kpi-required]').forEach(function (el) { el.required = e.target.checked; });
    });
    document.querySelectorAll('[data-hs-kpi-toggle]').forEach(function (t) {
        var f = t.closest('[data-hs-kpi-box]').querySelector('[data-hs-kpi-fields]');
        if (f) { f.querySelectorAll('[data-hs-kpi-required]').forEach(function (el) { el.required = t.checked; }); }
    });
</script>
@endonce

<div class="hs-form-actions">
    <button type="button" class="hs-btn soft" onclick="this.closest('.hs-modal').classList.remove('show'); document.body.style.overflow=''">Đóng</button>
    <button class="hs-btn dark" type="submit">Lưu hồ sơ</button>
</div>
