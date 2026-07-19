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

    <div class="hs-field full">
        <label>File hồ sơ đính kèm</label>
        <input class="hs-input" type="file" name="attachments[]" multiple>
        <div class="hs-muted" style="margin-top:6px">Có thể chọn nhiều file. Hỗ trợ ảnh, PDF, Word, Excel, file nén...</div>
    </div>
</div>

<div class="hs-form-actions">
    <button type="button" class="hs-btn soft" onclick="this.closest('.hs-modal').classList.remove('show'); document.body.style.overflow=''">Đóng</button>
    <button class="hs-btn dark" type="submit">Lưu hồ sơ</button>
</div>
