<?php

return [
    // CRM chỉ phục vụ một công ty: CÔNG TY TNHH THƯƠNG MẠI KỸ THUẬT QUỐC TẾ EGO (companies.id = 2).
    // Mặc định 2 để site không lỗi khi .env thiếu biến; vẫn ghi đè được bằng SINGLE_COMPANY_ID.
    'single_company_id' => env('SINGLE_COMPANY_ID', 2),
];
