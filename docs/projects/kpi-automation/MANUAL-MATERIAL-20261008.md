# Vật tư KPI: manager nhập tay tạm thời

Ngày thực hiện: 08/10/2026. Người dùng xác nhận “Ok đúng chuẩn làm theo cách đó nhé” sau đề xuất manager nhập tỷ lệ, ghi cách tính, đính kèm bảng đối chiếu rồi xác nhận. Đây là D16, không phải chốt công thức đối chiếu tự động D08.

## Cách sử dụng

1. Trưởng phòng Kỹ thuật mở Công trình → Nghiệm thu → **Đối chiếu vật tư tính KPI**. Admin cũng có quyền này; kỹ thuật chỉ xem.
2. Tải bảng đối chiếu/quyết toán vật tư dạng Excel, CSV hoặc PDF. Hồ sơ đã có trong mục Bảng quyết toán vật tư được dùng làm căn cứ, không bắt buộc tải trùng.
3. Nhập **% sai lệch vật tư** và **Cách tính / căn cứ đối chiếu** theo bảng đính kèm.
4. **Lưu nháp vật tư** chỉ lưu, chưa tính KPI. **Xác nhận kết quả vật tư** yêu cầu đủ tỷ lệ, ghi chú và bảng đối chiếu; người và thời gian xác nhận do server ghi.
5. KPI Khảo sát vẫn cần hồ sơ khảo sát đầy đủ và Admin duyệt nghiệm thu. Tỷ lệ dưới 3% đạt ngưỡng vật tư; đúng 3% trở lên không đạt. Một công trình cần tính nhưng thiếu xác nhận/hồ sơ khiến tiêu chí báo thiếu, không bỏ dòng thiếu để tính phần còn lại.

Link xem form trên công trình TEST: http://127.0.0.1:8001/du-an/64?step=acceptance#material-kpi. Công trình này còn chờ Admin duyệt và thiếu hồ sơ khảo sát; nhập/xác nhận vật tư riêng không tự làm công trình đủ điểm.

## Quy tắc và phạm vi

- Không thêm menu/trang mới, không cần migration; lưu JSON trong bước Nghiệm thu. Không thay trọng số hoặc cách tính các nhóm Chất lượng/HSE/EVN, việc được giao hay bảo hành.
- Nguồn trên KPI ghi **Manager nhập tay**, người/giờ xác nhận; checklist liên kết tới đúng khối vật tư ở Nghiệm thu.
- Sửa tỷ lệ/ghi chú hoặc tải thêm/thay bảng quyết toán làm xác nhận cũ hết hiệu lực. Đổi về nội dung cũ không khôi phục xác nhận; lịch sử còn lưu. Form đã cũ không ghi đè bản người khác vừa sửa.
- Kỳ kỹ thuật hoặc kỳ HR live dùng KPI kỹ thuật đã duyệt thì chặn ghi trước DB/file, kể cả Admin. Kỳ Excel snapshot giữ quy tắc cũ, không khóa nguồn live.
- Nghiệm thu được duyệt từ 08/10/2026 dùng luồng có xác nhận. Hồ sơ được duyệt trước ngày đó giữ nguồn cũ cho tới khi được sửa bằng luồng mới; không tự chuyển hàng loạt hay tính lại kỳ đã chốt. POST ô phần trăm từ form cũ chỉ tạo nháp, không bypass xác nhận.
- Không tự gộp mã/đơn vị, lấy đề xuất thay xuất kho thực hay chọn công thức tổng hợp. Manager chịu trách nhiệm ghi cách tính và căn cứ. Đối chiếu Kho tự động D08 vẫn mở; đây không phải hoàn tất toàn bộ S3.

## Thực hiện và kiểm chứng

- Service mới: `app/Services/Projects/ManualMaterialKpiService.php`. Route POST `projects-unified.workflow.material-kpi.save`; partial `workflow-material-kpi.blade.php`.
- Bộ nguồn `ProjectKpiLinkService`, bộ chấm `KpiStandardEvaluator` và checklist dùng cùng kết quả xác nhận. So ngưỡng bằng số gốc, không làm tròn 2,999999 thành 3 trước chấm.
- 10 test mới kiểm quyền, nháp/blank/0, trường bắt buộc, dấu xác nhận server, biên 3%, sửa/đổi về/upload, xung đột phiên bản, thiếu hồ sơ, nguồn cũ và khóa kỳ.
- Chạy cùng Nghiệm thu, SalaryCloseout, checklist và hướng dẫn: **60/60 test đạt, 660 assertions** trên `egosolar_test`; dùng transaction và storage giả riêng.
- Sau thu gọn ghi chú trên form, chạy lại bộ nhập tay: **10/10 đạt, 75 assertions**; kiểm tra diff không có lỗi whitespace trong các file phạm vi.
- Web local: đăng nhập tài khoản mẫu Trưởng phòng, mở công trình 64 và kiểm form/nút/trạng thái. Không gửi form, tải file hoặc xác nhận/duyệt dữ liệu local. Phần ghi dữ liệu và chấm điểm được thử trên DB test, chưa thử thao tác ghi qua trình duyệt local.
- Không commit/deploy, không duyệt cấu hình/lương/nghiệm thu local. Không đổi dữ liệu nghiệp vụ local; đăng nhập/xem có thể cập nhật phiên và thời điểm truy cập của tài khoản mẫu.

## Còn lại

D08 tự động theo Kho và khóa các đường thay đổi chứng từ Kho chưa thực hiện trong đợt này. Đồng bộ phiếu lương cũ, bỏ làm tròn theo D15, kiểm giao diện HR và phê duyệt cấu hình v3 thuộc đợt khác; không đánh dấu hoàn tất bằng thay đổi nhập tay vật tư.
