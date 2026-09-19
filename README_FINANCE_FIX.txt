Bản sửa sạch cụm Công nợ nhà cung cấp + Đề nghị thanh toán.

Mục tiêu:
1. Không tự quét/tự nhận link ĐNTT hàng loạt nữa.
2. ĐNTT chỉ link với công nợ khi tạo từ đúng dòng công nợ hoặc chạy repair thủ công có điều kiện rõ ràng.
3. Nếu ĐNTT đã chi ít hơn số tiền đợt, UI hiển thị Đợt cần TT / Đã chi ĐNTT / Còn thiếu và đề xuất tạo 1 đợt còn lại.
4. Nút tạo phần còn lại chỉ tạo thêm dòng công nợ Dự kiến, không tự tạo/link ĐNTT khác.
5. User buibichthao@egosolar.vn được sửa/xóa phiếu ĐNTT và công nợ/đợt đã hoàn thành; user khác bị khóa.
6. Tệp công nợ có Chọn tệp / Thêm tệp / Tải / Xóa.
7. Thêm dòng theo %: bấm 1 lần thêm 1 dòng, các dòng có ô %.
8. repair_finance_supplier_debts.php sẽ backup DB JSON trước khi sửa dữ liệu.

Sau khi unzip, chạy:
php artisan optimize:clear
php repair_finance_supplier_debts.php
php artisan optimize:clear
