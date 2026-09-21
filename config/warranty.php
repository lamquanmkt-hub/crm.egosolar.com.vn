<?php

return [
    /*
     | Chính sách nghiệp vụ Bảo hành / Sửa chữa (có thể ghi đè bằng .env).
     */

    // Bắt buộc có ít nhất 1 file minh chứng trước khi hoàn tất phiếu.
    'require_completion_evidence' => (bool) env('WARRANTY_REQUIRE_COMPLETION_EVIDENCE', false),

    // Số ký tự tối thiểu cho lý do bắt buộc (từ chối, yêu cầu bổ sung, ngoại lệ, override, hủy...).
    'min_reason_length' => 5,

    // File minh chứng: private disk, giới hạn.
    'evidence_disk' => env('WARRANTY_EVIDENCE_DISK', 'local'),
    'evidence_max_files' => 8,
    'evidence_max_kb' => 20480,
    'evidence_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
];
