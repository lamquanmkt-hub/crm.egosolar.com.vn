# Prompt gửi Claude Code

Làm tiếp trong `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`, web local 8001. Đọc `.claude/skills/ego-kpi-checklist-demo/SKILL.md` và bộ PROJECT/PLAN/CHECKLIST cùng thư mục này; thực hiện C1–C5 đến khi người dùng kiểm tra được trên web.

Yêu cầu mới: tại `/ky-thuat/kpis?month=2026-10&user_id=98#kpi-detail-view`, mở từng người phải thấy ngay checklist dưới 6 tiêu chí: mục nào đã hoàn thành, chưa hoàn thành, đã nộp muộn, còn hạn, chờ xác nhận hoặc thiếu dữ liệu. Mỗi dòng có link hồ sơ, mốc liên quan, trạng thái KPI và điểm nhận/mất/chưa tính cùng lý do. Đừng chỉ để nguồn trong modal “Xem cách tính”; không thêm menu/trang nhập mới. Trên điện thoại vẫn xem được.

Giữ cách tính hiện tại. KS01 từ 10/2026 lấy toàn bộ 25 điểm chia đều việc trưởng phòng giao đã đến hạn; lần gửi báo cáo đầu trong 24 giờ từ lúc giao nhận phần điểm, muộn/quá hạn chưa nộp mất phần đó, còn hạn chưa vào mẫu số. Checklist phải dùng output bộ chấm, không tạo công thức song song hoặc checkbox tự đánh dấu đạt. Năm tiêu chí còn lại và bộ trước tháng 10 giữ nguyên.

Tôi đã cho phép thêm dữ liệu TEST local cho tài khoản mẫu để nhìn thấy checklist. Xác minh đúng mẫu manager/engineer, công ty, công trình và bảo hành trong PROJECT, rồi thêm 5 việc: 2 đúng hạn, 1 nộp muộn, 1 quá hạn chưa nộp, 1 nháp còn hạn. Command/script chỉ chạy chủ động, guard local/DB/demo, không thông báo thật, chạy lại không trùng và không đổi giờ. Lịch sử gửi phải khớp thời gian mẫu. Lưu manifest/ảnh private. Không sửa nguồn đã xác nhận của công trình/bảo hành cũ, không tạo/cấp quyền tài khoản thật hoặc thay tiền/cấu hình/duyệt lương.

Kiểm tra trên DB test riêng theo guard repo, sau đó thao tác thật ở local với mẫu: chọn người, thấy checklist, bấm được link nguồn, mobile và quyền đúng. Đối chiếu tại thời điểm mẫu: 4 việc đã đến hạn, 2 đúng hạn =12,5/25; nếu 5 tiêu chí khác vẫn 75 thì tổng 87,5. Nếu có nguồn khác giải thích số thực tế, không xóa dữ liệu hay nhập số ép kết quả. Ghi giờ mẫu pending sẽ hết hạn.

Repo có thay đổi đang dở: không ghi đè công việc phiên khác, không reset/clean/commit/push/deploy. Giữ kỳ lương 08/2026 và cấu hình tháng 10 nguyên trạng. R1/R2 đợt Nghiệm thu vẫn theo review riêng, không tự đánh dấu đã hết lỗi. Cập nhật CHECKLIST/PROJECT/STATUS bằng kết quả thực tế; cuối cùng gửi URL, ảnh checklist, test pass/fail và cách trưởng phòng giao → kỹ thuật nộp → xem điểm.
