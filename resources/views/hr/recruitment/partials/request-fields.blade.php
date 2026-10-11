@php
    $requestRow = $requestRow ?? null;
    $fieldPrefix = 'recruitment-'.($requestRow?->id ?? 'new');
    $restoreInput = $errors->any() && (string) old('_editing_request_id', '') === (string) ($requestRow?->id ?? '');
    $fieldValue = fn ($key, $default = '') => $restoreInput ? old($key, $requestRow?->$key ?? $default) : ($requestRow?->$key ?? $default);
@endphp
<input type="hidden" name="_editing_request_id" value="{{ $requestRow?->id ?? '' }}">
<div class="rec-grid rec-request-fields">
    <div class="rec-field">
        <label class="rec-label" for="{{ $fieldPrefix }}-department">Bộ phận cần tuyển *</label>
        <input id="{{ $fieldPrefix }}-department" class="rec-input" name="department" maxlength="255" value="{{ $fieldValue('department') }}" placeholder="VD: Kỹ thuật" required @if($restoreInput && $errors->has('department')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-department-error" @endif>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'department'])
    </div>
    <div class="rec-field">
        <label class="rec-label" for="{{ $fieldPrefix }}-position">Vị trí *</label>
        <input id="{{ $fieldPrefix }}-position" class="rec-input" name="position" maxlength="255" value="{{ $fieldValue('position') }}" placeholder="VD: Kỹ sư dân dụng" required @if($restoreInput && $errors->has('position')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-position-error" @endif>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'position'])
    </div>
    <div class="rec-field">
        <label class="rec-label" for="{{ $fieldPrefix }}-quantity">Số lượng *</label>
        <input id="{{ $fieldPrefix }}-quantity" class="rec-input" type="number" min="1" name="quantity" value="{{ $fieldValue('quantity', 1) }}" required @if($restoreInput && $errors->has('quantity')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-quantity-error" @endif>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'quantity'])
    </div>
    <div class="rec-field">
        <label class="rec-label" for="{{ $fieldPrefix }}-date">Ngày cần nhân sự</label>
        <input id="{{ $fieldPrefix }}-date" class="rec-input" type="date" name="needed_date" value="{{ $fieldValue('needed_date') }}" @if($restoreInput && $errors->has('needed_date')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-needed_date-error" @endif>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'needed_date'])
    </div>
    <div class="rec-field">
        <label class="rec-label" for="{{ $fieldPrefix }}-salary">Mức lương dự kiến</label>
        <input id="{{ $fieldPrefix }}-salary" class="rec-input" name="expected_salary" maxlength="255" value="{{ $fieldValue('expected_salary') }}" placeholder="VD: 8–12 triệu" @if($restoreInput && $errors->has('expected_salary')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-expected_salary-error" @endif>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'expected_salary'])
    </div>
    <div class="rec-field">
        <label class="rec-label" for="{{ $fieldPrefix }}-reason">Lý do tuyển</label>
        <input id="{{ $fieldPrefix }}-reason" class="rec-input" name="reason" maxlength="255" list="{{ $fieldPrefix }}-reasons" value="{{ $fieldValue('reason', 'Tuyển mới') }}" @if($restoreInput && $errors->has('reason')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-reason-error" @endif>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'reason'])
        <datalist id="{{ $fieldPrefix }}-reasons"><option value="Tuyển mới"><option value="Thay thế"><option value="Bổ sung nhân sự"></datalist>
    </div>
    <div class="rec-field">
        <label class="rec-label" for="{{ $fieldPrefix }}-status">Trạng thái phê duyệt</label>
        <select id="{{ $fieldPrefix }}-status" class="rec-select" name="approval_status" @if($restoreInput && $errors->has('approval_status')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-approval_status-error" @endif>
            @foreach($approvalStatuses as $key => $label)
                <option value="{{ $key }}" @selected($fieldValue('approval_status', 'pending') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'approval_status'])
    </div>
    <div class="rec-field full">
        <label class="rec-label" for="{{ $fieldPrefix }}-requirements">Yêu cầu ứng viên</label>
        <textarea id="{{ $fieldPrefix }}-requirements" class="rec-textarea" name="requirements" rows="3" placeholder="Kinh nghiệm, chuyên môn, kỹ năng cần có…" @if($restoreInput && $errors->has('requirements')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-requirements-error" @endif>{{ $fieldValue('requirements') }}</textarea>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'requirements'])
    </div>
    <div class="rec-field full">
        <label class="rec-label" for="{{ $fieldPrefix }}-description">Mô tả công việc (JD)</label>
        <textarea id="{{ $fieldPrefix }}-description" class="rec-textarea" name="job_description" rows="4" placeholder="Các công việc chính của vị trí…" @if($restoreInput && $errors->has('job_description')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-job_description-error" @endif>{{ $fieldValue('job_description') }}</textarea>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'job_description'])
    </div>
    <div class="rec-field full">
        <label class="rec-label" for="{{ $fieldPrefix }}-note">Ghi chú</label>
        <textarea id="{{ $fieldPrefix }}-note" class="rec-textarea" name="note" rows="2" @if($restoreInput && $errors->has('note')) aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-note-error" @endif>{{ $fieldValue('note') }}</textarea>
        @include('hr.recruitment.partials.request-field-error', ['field' => 'note'])
    </div>
</div>
