---
name: ego-kpi-salary-closeout
description: Hoàn thiện tiền KPI kỹ thuật theo tỷ lệ thực đạt, sửa lỗi hiệu lực xác nhận và khóa kỳ, rồi kiểm thử nguồn nghiệm thu đến bảng lương trong crm.egosolar.com.vn. Dùng cho đợt tiếp theo sau checklist KPI và đồng bộ nguồn nghiệm thu cũ.
---

# KPI kỹ thuật đến tiền lương

Làm trong `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`, web local 8001. Đọc hồ sơ đợt này:

- [PROJECT](../../../docs/projects/kpi-automation/kpi-salary-closeout/PROJECT.md): công thức người dùng yêu cầu, nguồn đã kiểm tra và phạm vi.
- [PLAN](../../../docs/projects/kpi-automation/kpi-salary-closeout/PLAN.md): thứ tự F0–F5, ca đối chiếu và bằng chứng.
- [CHECKLIST](../../../docs/projects/kpi-automation/kpi-salary-closeout/CHECKLIST.md): cập nhật bằng kết quả thực tế.
- [Review R1/R2](../../../docs/projects/kpi-automation/technical-acceptance/REVIEW-20261006.md): tái hiện trước khi sửa; không suy đã hết lỗi từ các bộ test đang pass.
- [STATUS chung](../../../docs/projects/kpi-automation/STATUS.md): đọc mục mới nhất để giữ công việc các đợt trước.

## Các điều kiện phải giữ

- Công thức mới: **quỹ lương KPI × tỷ lệ KPI thực đạt**. Tỷ lệ 87,5% là hệ số 0,875. Không dùng mức hưởng 80% chỉ vì kết quả nằm trong bậc 75–90%.
- Cấu hình có chế độ trả theo tỷ lệ hoặc theo bậc và ngày hiệu lực. Cấu hình lịch sử không có trường chế độ vẫn dùng bậc cũ. Không sửa tại chỗ cấu hình đã duyệt, không tính lại snapshot hoặc đổi kỳ 08/2026. Bản mới dự kiến từ 01/10/2026; giữ tỷ lệ cố định/quỹ KPI, trọng số và cờ/trần vượt hiện có khi kế thừa. Cập nhật D15 ngày 07/10/2026 thay yêu cầu giữ làm tròn: phần tiền mới không làm tròn, thực hiện tiếp theo [skill technical-payroll-material](../ego-technical-payroll-material/SKILL.md); quy tắc làm tròn của phiên bản lịch sử giữ nguyên.
- Chỉ chuẩn bị bản nháp cấu hình mới và xem trước. Hoàn tất code, hai lỗi xác nhận và các kiểm thử trước khi giao bản nháp cho người dùng duyệt qua quyền hiện có. Không tự duyệt cấu hình, nghiệm thu công trình thật hoặc bảng lương local.
- Một bộ quy đổi dùng chung cho KPI kỹ thuật, preview cấu hình và nguồn kỹ thuật trong bảng lương HR. Không nhân 87,5% lần thứ hai; không lấy lương thỏa thuận 15 triệu làm toàn bộ quỹ KPI. Thiếu nguồn/xác nhận/cấu hình vẫn thiếu, không thành 0 hoặc đạt.
- R1: sửa một nhóm rồi đổi về giá trị cũ không được làm xác nhận cũ sống lại. Vô hiệu bền vững theo nhóm, giữ lịch sử và xác nhận của nhóm không bị ảnh hưởng. Phải xét cả thay đổi minh chứng và quyết định đấu nối có liên quan.
- R2: khóa kỳ khác khóa bước. Admin không được vượt khóa kỳ để sửa nguồn KPI; kiểm tra trước ghi dữ liệu/file. Đối chiếu cả phiếu KPI kỹ thuật và kỳ lương HR đã duyệt có dùng nguồn kỹ thuật này. Không tự mở kỳ để demo.
- Giữ phần hiển thị nguồn nghiệm thu cũ vừa làm: người/giờ xác nhận, kết quả thực và khối nhập lại riêng. Không chuyển dữ liệu cũ thành xác nhận mới hoặc đổ điểm mẫu vào form.
- DB ứng dụng local là `crm_egosolar_test`; test tự động chỉ chạy trên `egosolar_test`, giữ guard `Tests/TestCase.php`. Cache/session/mail của CLI/test phải cô lập; không lặp sự cố đổi thương hiệu web local do cache test dùng chung.
- Repo đang có thay đổi của nhiều phiên. Không reset/clean, ghi đè việc khác, commit/push/deploy hay gửi thông báo thật. Nếu có phiên cùng sửa đúng file, đọc trạng thái và tiếp tục việc độc lập trước khi điều phối.
- Mẫu local phải xác minh bằng ID + email/mã + role/phạm vi công ty thực tế. `company_id` của tài khoản mẫu có thể null; không tự gán công ty hoặc mở rộng quyền để khớp giả định trong tài liệu cũ. Không ghi mật khẩu/PII vào file theo dõi Git.

## Kết quả giao

Hoàn thành F0–F5, giữ bằng chứng riêng tư trong vùng đã gitignore, cập nhật PROJECT/CHECKLIST/STATUS. Giao link cấu hình nháp, preview công thức, công trình mới và KPI từng người; bảng đối chiếu tiền trước/sau làm tròn; test thực chạy và giới hạn còn lại. Nếu chưa được người dùng duyệt cấu hình mới, nói rõ trang chính đang dùng bản nào, không tuyên bố đã áp dụng trả lương.
