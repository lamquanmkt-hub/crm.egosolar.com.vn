---
name: ego-kpi-checklist-demo
description: Bổ sung checklist nguồn và điểm ngay trong chi tiết KPI từng kỹ sư, kèm dữ liệu TEST local để minh họa đúng hạn, muộn, chưa nộp và còn hạn. Dùng khi tiếp tục yêu cầu checklist KPI kỹ thuật ngày 07/10/2026 tại crm.egosolar.com.vn.
---

# Checklist KPI từng người và mẫu thao tác

Đọc [PROJECT](../../../docs/projects/kpi-automation/kpi-checklist-demo/PROJECT.md), [PLAN](../../../docs/projects/kpi-automation/kpi-checklist-demo/PLAN.md), [CHECKLIST](../../../docs/projects/kpi-automation/kpi-checklist-demo/CHECKLIST.md) và [prompt giao việc](../../../docs/projects/kpi-automation/kpi-checklist-demo/CLAUDE-PROMPT.md). Áp dụng quy tắc môi trường của [skill nền](../ego-kpi-automation/SKILL.md); phạm vi hiện tại là đợt này.

## Kết quả cần có

Mở KPI một người là thấy checklist nguồn dưới sáu tiêu chí ngay trên trang hiện tại. Mỗi dòng cho biết đã làm/chưa làm, điều kiện tính điểm, nhận/mất bao nhiêu hoặc vì sao chưa tính, và link mở hồ sơ nguồn. Không bắt người dùng tìm trong hộp “Xem cách tính”. Dùng bố cục gọn, xem được cả điện thoại; không thêm menu/trang nhập liệu mới.

## Quy tắc thực hiện

- Checklist đọc kết quả bộ chấm và xác nhận nguồn hiện có. Không tính lại công thức riêng trong Blade/JavaScript, không thêm checkbox để người xem tự đánh dấu đạt.
- Tách trạng thái công việc khỏi trạng thái điểm. Nộp muộn có thể đã hoàn thành nhưng không nhận phần điểm tiến độ; việc còn hạn là chờ, chưa mất điểm; thiếu nguồn/xác nhận không được hiển thị hoàn thành hoặc đạt.
- Giữ phạm vi người, kỳ, công trình, công ty và quyền hiện có ở cả dữ liệu checklist lẫn link đích. Không đưa nguồn của người khác vào để lấp checklist trống.
- Giữ KS01 từ 10/2026: toàn bộ 25 điểm chia đều việc trưởng phòng giao đã đến hạn, lần gửi báo cáo đầu ≤ giờ giao +24 giờ. Kỳ theo tháng đến hạn, việc còn hạn chưa vào mẫu số, hủy loại trừ. Giữ bộ trước tháng 10, năm tiêu chí còn lại và SLA bảo hành.
- Người dùng đã yêu cầu thêm dữ liệu TEST để xem. Được tạo việc/báo cáo giả thuộc đúng tài khoản mẫu local đã xác minh; giới hạn trong PROJECT. Quy tắc “không seed local” của prompt đợt Nghiệm thu cũ không ngăn phạm vi TEST được giao lần này. Không cần hỏi lại để thêm các mẫu đã mô tả.
- Seed chỉ chạy khi chủ động gọi, kiểm tra local/DB/công ty/tài khoản giả trước khi ghi, chạy lại không tạo trùng hoặc đổi mốc giờ. Không gắn vào khởi động hay DatabaseSeeder mặc định. Tắt gửi thông báo thật trong phạm vi thao tác mẫu, không thay cấu hình toàn hệ thống.
- Không sửa công trình/bảo hành mẫu đã xác nhận để ép đủ điểm, không thêm quyền hoặc duyệt cấu hình/lương. Không reset/clean, commit, push hay deploy. Bằng chứng và manifest giữ trong outputs được gitignore; không lưu mật khẩu/dữ liệu nhân viên thật trong tài liệu theo dõi Git.
- Test tự động chỉ dùng DB egosolar_test theo guard. Khi dựng web test riêng dùng cache/session/mail cô lập; không đổi .env của web 8001 sang DB test, không ghi cache nhận diện thương hiệu chung.

## Hoàn tất

Thực hiện C1–C5 theo PLAN, kiểm tra checklist thật trên trình duyệt và đối chiếu điểm bằng tay. Cập nhật CHECKLIST/STATUS với bằng chứng và giới hạn. Gửi người dùng URL chọn đúng người/tháng, ảnh checklist, điểm thực tế và đường đi nhập/nộp/xác nhận. Không kết luận toàn hệ thống KPI đã hoàn thiện chỉ vì dữ liệu TEST đạt.
