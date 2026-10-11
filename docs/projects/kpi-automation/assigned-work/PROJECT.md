# KS01 — Công việc được giao và nộp báo cáo trong 24 giờ

Người dùng xác nhận ngày 07/10/2026: dùng toàn bộ 25 điểm Tiến độ chia đều các việc trưởng phòng giao. Không có khoản trừ cố định riêng. Hạn tính từ lúc giao +24 giờ, đúng biên được tính đúng hạn. Luồng áp dụng cho kỳ 10/2026 trở đi; lịch sử trước đó giữ nguồn bàn giao cũ. Xem D13 trong DECISIONS.

## Quy tắc đang chạy

- Chỉ việc đã giao (`is_manager_assigned=1`), đúng công ty và nhân viên. Nộp báo cáo đúng `plan_item_id`; không lấy % tự khai hoặc trạng thái Hoàn thành của kế hoạch thay việc nộp.
- Đọc lần gửi đầu tiên từ nhật ký `submit`. Khi báo cáo bị trả hoặc gửi lại, không lấy giờ gửi lại thay lần đầu. Với dữ liệu cũ không có nhật ký, dùng `submitted_at` khi trạng thái đã gửi/đã duyệt/cần sửa. Không dùng ngày báo cáo do nhân viên chọn hoặc giờ duyệt làm giờ nộp.
- Đánh giá tại cuối tháng hoặc hiện tại nếu tháng đang mở. Tháng KPI theo thời điểm hết hạn 24 giờ. Chưa tới hạn: chờ, không vào mẫu số. Đã đến hạn: nộp ≤24 giờ nhận phần điểm; nháp/chưa nộp/nộp muộn mất phần đó. Hủy không tính; thiếu/đảo thứ tự mốc cần xác minh.
- Điểm Tiến độ = số việc nộp đúng hạn / số việc đã đến hạn ×25. Ví dụ 20 việc, 18 nộp đúng hạn: 22,5/25. Không có việc đủ điều kiện: thiếu dữ liệu, chưa tự đạt/N/A.
- KS01 không còn lấy số KH/TH nhập tháng làm thay thế. Các tiêu chí khác giữ cơ chế cũ. Không đổi 30 điểm bảo hành hoặc tiền đã duyệt.

## Triển khai và kiểm tra

- [x] Thêm `AssignedWorkKpiService`: lọc/phân loại nguồn, đọc lần nộp đầu, chống đếm trùng.
- [x] Nối nguồn vào `KpiStandardEvaluator`, trang KPI, gợi ý nhập tháng, phần tổng hợp lương nhân sự, ghi chú TEST.
- [x] Hiện giờ giao/hạn/nộp, phần điểm và link báo cáo/tuần của người được giao.
- [x] Đặt cột Tiến độ mới chỉ đọc và bỏ ghi KH/TH của tiêu chí này từ endpoint nhập tháng.
- [x] 12 ca mới: biên 24 giờ; muộn một giây; nháp/chưa nộp; chưa tới hạn; không có việc; hủy/cá nhân; sai công ty/người; lịch sử gửi lại; nhiều báo cáo; mốc thiếu/đảo; chuyển tháng/quý; tháng cũ; link/phạm vi công trình; chặn nhập tay.
- [x] 5 bộ hồi quy, 95 ca đạt. Bộ nguồn công trình cũ chọn rõ `project_handover` trong fixture để tiếp tục kiểm tra bộ đọc lịch sử; không thay assertion điểm để che hành vi mới. Bộ 12 ca mới dùng chính cấu hình mặc định đã đổi theo người dùng.
- [x] UI local hiển thị quy tắc mới. Mẫu 98 chưa có việc giao nên báo thiếu là đúng; ảnh private trong outputs.

Không migration, không ghi dữ liệu giả vào DB local, không duyệt lương/cấu hình, không commit/deploy. UI có dữ liệu đủ/thiếu/nộp muộn được kiểm qua HTTP render trong DB test rollback; chưa tạo mẫu thao tác trình duyệt mới để minh họa các trạng thái đó.
