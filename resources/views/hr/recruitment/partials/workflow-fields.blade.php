@php
    $formRow = $formRow ?? null;
    $formKey = $active.'-'.($formRow?->id ?? 'new');
    $restoreWorkflowInput = $errors->any() && old('_workflow_form') === $formKey;
    $fieldLimits = ['full_name'=>255,'phone'=>50,'email'=>255,'zalo'=>100,'position'=>255,'cv_link'=>1000,'location'=>255,'interviewer'=>255,'result'=>255,'salary_offer'=>255];
@endphp
<input type="hidden" name="_workflow_form" value="{{ $formKey }}">
<div class="rec-grid rec-request-fields">
@foreach($workflow['fields'] as $field)
    @if(!empty($field['editOnly']) && !$formRow) @continue @endif
    @php
        $fieldName = $field['name'];
        $fieldId = 'rec-'.$formKey.'-'.$fieldName;
        $fieldValue = array_key_exists('fixed',$field) ? $field['fixed'] : ($formRow?->$fieldName ?? $field['default'] ?? '');
        if ($restoreWorkflowInput && !array_key_exists('fixed',$field)) $fieldValue = old($fieldName,$fieldValue);
        if ($fieldValue && in_array($field['type'],['date','datetime-local'],true)) {
            try { $fieldValue = \Illuminate\Support\Carbon::parse($fieldValue)->format($field['type'] === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i'); }
            catch (\Exception $invalidDate) { $fieldValue = ''; }
        }
        $fieldRequired = in_array($fieldName,['full_name','candidate_id'],true);
        $fieldError = $restoreWorkflowInput && $errors->has($fieldName);
    @endphp
    @if($field['type'] === 'hidden')
        <input type="hidden" name="{{ $fieldName }}" value="{{ $fieldValue }}">
        @continue
    @endif
    <div class="rec-field{{ $field['type'] === 'textarea' ? ' full' : '' }}">
        <label class="rec-label" for="{{ $fieldId }}">{{ $field['label'] }}{{ $fieldRequired ? ' *' : '' }}</label>
        @if($field['type'] === 'select')
            <select class="rec-select" id="{{ $fieldId }}" name="{{ $fieldName }}" @required($fieldRequired) @if($fieldError) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>
                @if($fieldValue !== '' && $fieldValue !== null && !array_key_exists($fieldValue,$field['options']))<option value="{{ $fieldValue }}" selected>{{ $fieldValue }} (giá trị đã lưu)</option>@endif
                @foreach($field['options'] as $key=>$label)<option value="{{ $key }}" @selected((string)$fieldValue === (string)$key)>{{ $label }}</option>@endforeach
            </select>
        @elseif($field['type'] === 'textarea')
            <textarea class="rec-textarea" id="{{ $fieldId }}" name="{{ $fieldName }}" rows="3" @if($fieldError) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>{{ $fieldValue }}</textarea>
        @else
            <input class="rec-input" id="{{ $fieldId }}" name="{{ $fieldName }}" type="{{ $field['type'] }}" value="{{ $fieldValue }}" @required($fieldRequired) @if(isset($fieldLimits[$fieldName])) maxlength="{{ $fieldLimits[$fieldName] }}" @endif @if($fieldError) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @endif>
        @endif
        @if($fieldName === 'cv_link')<span class="rec-card-note">Dán link hồ sơ trực tuyến. Đường dẫn trên máy tính chỉ đọc được tại máy lưu tệp.</span>@endif
        @if($fieldError)<span class="rec-field-error" id="{{ $fieldId }}-error">{{ $fieldRequired ? 'Vui lòng nhập hoặc chọn '.$field['label'].'.' : 'Kiểm tra lại '.$field['label'].'.' }}@if(isset($fieldLimits[$fieldName])) Tối đa {{ $fieldLimits[$fieldName] }} ký tự.@endif</span>@endif
    </div>
@endforeach
</div>
