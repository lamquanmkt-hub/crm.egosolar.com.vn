Patch: HC vận hành - quản lý nhóm và trạng thái đẹp hơn

File đã sửa:
- routes/hr.php
- app/Http/Controllers/Hr/HrDocumentController.php
- resources/views/hr/operations/index.blade.php

Cài đặt nhanh trên hosting:
1) Upload zip này lên public_html
2) Giải nén đè file cũ
3) Chạy các lệnh sau trong Terminal:

cd ~/public_html
php artisan optimize:clear
php artisan route:clear
php artisan view:clear

Ghi chú:
- Không cần chạy migration riêng.
- Bảng hr_operation_statuses sẽ tự tạo khi mở trang /nhan-su/hc-van-hanh.
- Nếu đang cache route/view thì bắt buộc chạy clear cache như trên.
