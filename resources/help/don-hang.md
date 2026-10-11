---
title: Đơn hàng
url: /orders
routes: orders.*, order-returns.*
keywords: đơn hàng, tạo đơn, gửi duyệt, duyệt đơn, từ chối đơn, hủy đơn, xóa đơn, xuất kho, serial, thanh toán, công nợ, sales manager, kế toán, ban giám đốc, kho, đổi trả
---
Module Đơn hàng quản lý đơn bán hàng thương mại từ lúc Sales tạo đến khi Kho xuất hàng. Mỗi đơn đi qua 5 cấp xử lý.

## Quy trình duyệt đơn hàng (5 cấp)
1. **Sales** tạo đơn (trạng thái ở bộ phận Sales).
2. Sales bấm **Gửi duyệt** → đơn chuyển sang **Sales Manager** duyệt.
3. Sales Manager duyệt → chuyển **Kế toán** duyệt.
4. Kế toán duyệt → chuyển **Ban Giám đốc** duyệt.
5. Ban Giám đốc duyệt → đơn **Sẵn sàng xuất kho**, chuyển **Kho**.
6. Kho xác nhận **xuất kho** (chọn serial nếu là hàng có serial) → đơn **Hoàn thành**.

Ở bất kỳ cấp duyệt nào, người duyệt có thể **Từ chối** kèm lý do: đơn quay về Sales với trạng thái *Bị từ chối*, Sales nhận thông báo kèm lý do, sửa lại rồi gửi duyệt lại. Mỗi bước được ghi lịch sử (ai, lúc nào, ghi chú).

## Tạo đơn hàng mới
1. Vào [Đơn hàng](/orders) → bấm **Tạo đơn**.
2. Chọn khách hàng (hệ thống tự tạo lead nếu cần), nhập ngày đơn, thêm sản phẩm, số lượng, đơn giá.
3. Lưu đơn. Đơn ở trạng thái Sales, bạn vẫn sửa được.
4. Khi đã đủ thông tin, bấm **Gửi duyệt** để chuyển Sales Manager.

## Hủy và xóa đơn
- **Hủy đơn**: chỉ khi đơn **còn ở bộ phận Sales** (chưa gửi duyệt hoặc đã bị trả về). Sales Manager nhận thông báo.
- **Xóa đơn**: không xóa được đơn đã **Hoàn thành**. Khi xóa, các dữ liệu kèm theo (dòng hàng, thanh toán, công nợ, lịch sử) cũng bị xóa.

## Xuất kho
- Chỉ thực hiện khi đơn đang ở bước **Kho** (đã qua Ban Giám đốc duyệt). Không xuất được đơn đã xuất trước đó.
- Hàng có serial phải chọn đúng serial còn tồn trong kho.
- Nếu đơn đang xử lý qua module **Ký gửi hàng hóa**, phải xuất giao tại hồ sơ ký gửi (tránh trừ tồn hai lần).
- Khi xuất kho, hệ thống trừ tồn và tạo **công nợ khách hàng** cho đơn.

## Ghi nhận thanh toán của khách
Ghi nhận khoản khách trả trên đơn: số tiền được cộng vào phần đã thu, công nợ tự chuyển trạng thái **Trả một phần** hoặc **Đã trả đủ**.

## Đổi trả hàng
Yêu cầu đổi trả được theo dõi ở trang Đổi trả hàng (từ menu Đơn hàng): thu hồi, kiểm tra hàng, nhập hoàn và hoàn tiền (nếu có) do Kế toán xử lý.
