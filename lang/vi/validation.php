<?php

return [
    'max' => [
        'numeric' => ':attribute không được lớn hơn :max.',
        'file' => ':attribute không được vượt quá :max KB.',
        'string' => ':attribute không được vượt quá :max ký tự.',
        'array' => ':attribute không được vượt quá :max mục.',
    ],

    'mimes' => ':attribute phải đúng định dạng: :values.',
    'mimetypes' => ':attribute không đúng định dạng cho phép.',
    'uploaded' => ':attribute tải lên không thành công.',
    'file' => ':attribute phải là một file hợp lệ.',
    'required' => ':attribute là bắt buộc.',

    'attributes' => [
        'attachments' => 'File đính kèm',
        'attachments.*' => 'File đính kèm',
        'files' => 'File đính kèm',
        'files.*' => 'File đính kèm',
        'attachment' => 'File đính kèm',
    ],

    'custom' => [
        'attachments.*.max' => [
            'file' => 'File đính kèm vượt quá giới hạn upload của server.',
        ],
        'files.*.max' => [
            'file' => 'File đính kèm vượt quá giới hạn upload của server.',
        ],
        'attachment.max' => [
            'file' => 'File đính kèm vượt quá giới hạn upload của server.',
        ],
    ],
];
