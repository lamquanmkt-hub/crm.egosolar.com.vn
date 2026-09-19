PATCH: HR xem toàn bộ Đề nghị thanh toán

File thay đổi:
app/Http/Controllers/Finance/PaymentRequestController.php

Thay đổi chính:
- Role hr được xem toàn bộ danh sách ĐNTT.
- HR xem được chi tiết phiếu của người khác.
- HR xuất Excel/PDF theo toàn bộ phạm vi dữ liệu.
- HR tải PDF phiếu đã chi nếu liên kết hiển thị trên danh sách.
- KHÔNG cấp HR quyền bulk approve/admin approve/accounting approve.
- KHÔNG mở HR quyền sửa/xóa phiếu của người khác.

Sau khi chép file vào source Laravel:
php artisan optimize:clear
php artisan permission:cache-reset || true
