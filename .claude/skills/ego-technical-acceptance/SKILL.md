---
name: ego-technical-acceptance
description: Hoàn thiện luồng kỹ thuật nhập kết quả nghiệm thu, trưởng phòng xác nhận chất lượng/HSE/EVN-App và tự tổng hợp KPI trong module Công trình của crm.egosolar.com.vn. Dùng cho đợt nghiệm thu kỹ thuật; chưa triển khai đối chiếu kho hoặc trả lương.
---

# Nhập liệu nghiệm thu và xác nhận KPI kỹ thuật

Đọc [skill nền](../ego-kpi-automation/SKILL.md), rồi hồ sơ đợt này:

- [PROJECT](../../../docs/projects/kpi-automation/technical-acceptance/PROJECT.md): mục tiêu, source đã kiểm tra và giới hạn quyền.
- [PLAN](../../../docs/projects/kpi-automation/technical-acceptance/PLAN.md): hợp đồng dữ liệu và thứ tự triển khai.
- [CHECKLIST](../../../docs/projects/kpi-automation/technical-acceptance/CHECKLIST.md): điều kiện nghiệm thu, chỉ đánh dấu với bằng chứng.
- [DECISIONS](../../../docs/projects/kpi-automation/DECISIONS.md): D07/D08/D12 chưa tự chốt.

## Các quyết định triển khai của đợt này

- Làm ngay trong `Công trình → Nghiệm thu`, tái sử dụng form/hồ sơ/quy trình đang có. Không thêm menu hoặc trang nhập điểm KPI.
- Tách dữ liệu kỹ thuật khai báo khỏi dữ liệu đã được kiểm tra. Kỹ thuật được giao nhập và gửi; trưởng phòng trong phạm vi công trình kiểm tra, xác nhận hoặc trả bổ sung. ID người xác nhận và giờ xác nhận do server ghi, không nhận từ form.
- Trưởng phòng xác nhận dữ liệu KPI không đồng nghĩa duyệt nghiệm thu cuối cùng. `config/project_workflow_v2.php` hiện giao track duyệt nghiệm thu cho Admin; giữ track đó, không thêm role/quyền global hoặc biến `can_assign` thành quyền duyệt cuối.
- Không cho người thực hiện tự xác nhận kết quả của mình. Admin giữ phạm vi ngoại lệ hiện có; các ngoại lệ phải được lưu dấu vết, không mở rộng quyền để demo chạy.
- Kết quả mỗi mục có ba giá trị: chưa ghi nhận / đạt / không đạt. Trường thiếu, checkbox vắng và chưa xác nhận không được tự thành 0, false hoặc đạt. Lưu nháp không đóng dấu đã kiểm tra.
- Chất lượng lần đầu lấy từ lịch sử trả lại thi công/nghiệm thu; không thêm nút người nhập tự chọn đạt lần đầu. Không phàn nàn phải được xác nhận rõ ràng.
- HSE dùng 5 mục hiện có. EVN/App cần kết quả và người kiểm tra; link/tài khoản giám sát chỉ là minh chứng hỗ trợ. Thiếu quyết định cần đấu nối phải báo thiếu, không tự miễn EVN.
- Xác nhận gắn với phiên bản nội dung và minh chứng được kiểm tra. Khi sửa/trả lại/thay hoặc xóa file, xác nhận cũ không áp dụng cho bản mới; giữ lịch sử. Bước/kỳ đã khóa không được sửa vòng qua API, upload hoặc delete.
- Giữ quy tắc, trọng số và kỳ hiệu lực hiện tại; tổng chỉ đủ khi tất cả tiêu chí đủ theo bộ chấm. Các tiêu chí kho/bảo hành thiếu không được bù để tạo tổng.

## Bằng chứng cần giao

Chạy feature test trên `egosolar_test` và thử UI hai vai trò. Bộ test 83 ca đã đạt là baseline, không phải chứng minh luồng mới đã xong. Thử thực sự nhập → gửi → xác nhận → duyệt nghiệm thu theo quyền hiện có → xem nguồn/điểm; chụp trạng thái trước và sau. Giữ bằng chứng có dữ liệu cá nhân trong vùng gitignore.

Mẫu local hiện có đã đóng; không mở lại, sửa mốc hoặc đổi điểm để tái dùng làm fixture. Dùng fixture cô lập ở DB test; hướng dẫn chạy được trên local sau khi triển khai mà không tự tạo thêm tài khoản/hồ sơ kinh doanh. Cập nhật CHECKLIST, PROJECT phần kết quả và STATUS dự án chính; ghi rõ test không chạy được và phần tiếp theo.
