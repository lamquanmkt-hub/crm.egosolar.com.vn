@if($restoreInput && $errors->has($field))
    @php
        $messages = [
            'department' => 'Nhập bộ phận cần tuyển, tối đa 255 ký tự.',
            'position' => 'Nhập vị trí cần tuyển, tối đa 255 ký tự.',
            'quantity' => 'Số lượng phải là số nguyên từ 1 trở lên.',
            'needed_date' => 'Chọn một ngày hợp lệ.',
            'expected_salary' => 'Mức lương dự kiến không được vượt quá 255 ký tự.',
            'reason' => 'Lý do tuyển không được vượt quá 255 ký tự.',
            'approval_status' => 'Kiểm tra lại trạng thái phê duyệt.',
            'requirements' => 'Kiểm tra lại nội dung yêu cầu ứng viên.',
            'job_description' => 'Kiểm tra lại nội dung mô tả công việc.',
            'note' => 'Kiểm tra lại nội dung ghi chú.',
        ];
    @endphp
    <span class="rec-field-error" id="{{ $fieldPrefix }}-{{ $field }}-error">{{ $messages[$field] ?? 'Kiểm tra lại thông tin đã nhập.' }}</span>
@endif
