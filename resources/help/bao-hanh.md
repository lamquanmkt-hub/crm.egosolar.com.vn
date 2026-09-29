---
title: Bảo hành — Đổi hàng & Sửa chữa tính phí
url: /ky-thuat/de-xuat-doi-hang-bao-hanh
routes: ky-thuat.warranty-exchange.*, ky-thuat.repair.*, warehouse.warranty-fulfillment.*, serial-warranty.*
keywords: bảo hành, đổi hàng, phiếu đổi, dxbh, serial lỗi, thiết bị lỗi, trưởng phòng duyệt, phân công, người phụ trách, kho giữ hàng, xuất kho, thu hồi, hoàn tất, ngoại lệ bảo hành, sửa chữa tính phí, báo giá, qa, bàn giao
---
Khu vực **Kỹ thuật → Bảo trì/Bảo hành** gồm hai quy trình: **Đổi hàng bảo hành** ([/ky-thuat/de-xuat-doi-hang-bao-hanh](/ky-thuat/de-xuat-doi-hang-bao-hanh)) và **Sửa chữa tính phí** ([/ky-thuat/sua-chua-tinh-phi](/ky-thuat/sua-chua-tinh-phi)). Việc của Kho nằm ở trang riêng của Kho (Xuất hàng bảo hành/sửa chữa).

## Tạo phiếu đổi hàng bảo hành
Ai tạo được: kỹ thuật viên, Trưởng phòng Kỹ thuật, Admin, CSKH.
1. Vào [Đổi hàng bảo hành](/ky-thuat/de-xuat-doi-hang-bao-hanh) → tạo đề xuất.
2. **Nhập serial thiết bị lỗi**: hệ thống tự tìm sản phẩm, khách hàng, đơn hàng, công trình và hạn bảo hành (không cần nhập tay).
3. Nhập hiện tượng lỗi, chẩn đoán, phương án đề xuất, mức ưu tiên; đính kèm minh chứng (ảnh/video/biên bản).
4. Gửi → phiếu **Chờ duyệt**.

Chặn tự động: serial không tồn tại, serial thuộc công ty khác, serial đang có phiếu mở khác, hoặc **hết bảo hành**. Thiết bị hết bảo hành có thể chuyển sang Sửa chữa tính phí, hoặc đề nghị **ngoại lệ bảo hành** (bắt buộc lý do, người khác duyệt).

## Người phụ trách (phân công)
- Kỹ thuật viên tự tạo phiếu thì **tự là người phụ trách**.
- Trưởng phòng/Admin tạo phiếu có thể để "Chưa phân công".
- Chỉ **Trưởng phòng Kỹ thuật, Giám đốc, Admin** được **Phân công / Đổi người phụ trách** (nút ở cột Thao tác hoặc cạnh ô "Người phụ trách"), ở bất kỳ bước nào khi phiếu còn mở. Đổi người bắt buộc nhập lý do. Kỹ thuật viên được giao nhận thông báo.
- Phiếu chưa có người phụ trách thì khi **Duyệt** bắt buộc chọn kỹ thuật viên phụ trách.
- Kỹ thuật viên chỉ thấy phiếu mình phụ trách hoặc mình tạo.

## Các bước xử lý (11 bước)
1. Tiếp nhận lỗi → 2. Hệ thống kiểm tra serial & bảo hành → 3. Tạo đề xuất (Chờ duyệt)
4. **Trưởng phòng KT / Giám đốc / Admin duyệt**: Duyệt, **Yêu cầu bổ sung** hoặc **Từ chối** (bắt buộc lý do). Người tạo/phụ trách không được tự duyệt phiếu của mình.
5. Duyệt xong → chuyển **Kho**, Kho nhận việc.
6. **Kho chọn serial thay thế và giữ hàng** (cùng sản phẩm/model, còn tồn, khác serial lỗi).
7. **Kho xuất** thiết bị thay thế.
8. **Kỹ thuật viên phụ trách** bấm **Xác nhận đã nhận hàng**, rồi **Xác nhận đã thay thiết bị** cho khách.
9. **Kho thu hồi** thiết bị lỗi (Trưởng phòng có thể cho **hoãn thu hồi** kèm lý do: đổi trước – thu sau).
10. Hệ thống lưu cặp serial cũ ↔ mới.
11. **Trưởng phòng/Admin hoàn tất** phiếu (theo checklist).

Phiếu *Yêu cầu bổ sung*: người tạo/phụ trách bổ sung rồi **Gửi duyệt lại**. Phiếu *Từ chối* có thể được Trưởng phòng **mở lại** kèm lý do. **Hủy phiếu** bắt buộc lý do. Mọi thao tác được ghi lịch sử trên phiếu.

## Sửa chữa tính phí
Trình tự: Tiếp nhận → **Chẩn đoán** → **Lập báo giá** → gửi khách → **khách xác nhận** đồng ý (hoặc từ chối) → Kho cấp linh kiện → **Sửa chữa** → **Kiểm tra QA** (không đạt thì sửa lại) → **Bàn giao** → Hoàn tất.
- Chi phí = linh kiện + công sửa + phí onsite + vận chuyển + phát sinh − giảm giá; hệ thống tự tính.
- Sửa báo giá đã gửi/đã duyệt sẽ tạo **phiên bản mới**, khách phải xác nhận lại.
- Chi phí chỉ Admin và Kế toán xem được.
