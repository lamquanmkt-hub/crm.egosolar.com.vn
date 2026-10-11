# Thứ tự triển khai

## C1 — Đọc nguồn, chụp baseline

Đọc hướng dẫn repo có hiệu lực, skill đợt này, STATUS, DECISIONS/D13 và `../assigned-work/PROJECT.md`. Kiểm tra thay đổi đang có; nếu phiên khác đang sửa cùng file, phối hợp để tránh ghi đè. Xác minh route, dữ liệu, role, timezone và các mẫu trong PROJECT. Lưu baseline private cho KPI mẫu, công trình/bảo hành, kỳ 08/2026 và cấu hình tháng 10.

Điểm bắt đầu đọc code: `resources/views/synced/kythuat/kpis.blade.php`, `TechnicalPayrollController`, `KpiStandardEvaluator`, `AssignedWorkKpiService` và nguồn/xác nhận Nghiệm thu. Đối chiếu route link đích với routes hiện có, không đoán URL.

## C2 — Checklist trên trang từng người

Tận dụng evidence/output của bộ chấm; nếu cần bổ sung thuộc tính trình bày thì làm ở service/controller, giữ nguồn tính điểm duy nhất. Đưa checklist vào `#kpi-detail-view` dưới sáu tiêu chí. Hiện trạng thái thực hiện, trạng thái điểm, lý do, mốc và link theo PROJECT. Dùng layout gọn và mobile hữu dụng. Giữ bộ lọc kỳ/người/công trình, dữ liệu và quyền cùng phạm vi.

## C3 — Tạo mẫu có kiểm soát

Viết lệnh/script local opt-in, guard DB/host/environment/company/demo identifiers. Thêm đúng 5 việc/báo cáo trong PROJECT, đủ lịch sử lần gửi đầu. Seed transaction cho nhóm ghi liên quan; không tự động gửi thông báo. Chạy lại idempotent, không đổi giờ/nội dung mẫu đã được thao tác. Không sửa tài khoản, công trình/bảo hành đã xác nhận, cấu hình hoặc lương. Lưu manifest private và kết quả tính tại thời điểm seed.

## C4 — Kiểm tra có ý nghĩa

Test tự động trên `egosolar_test`: dòng checklist đúng nguồn/trạng thái/link; muộn khác chưa làm; pending không mất điểm; thiếu xác nhận không đạt; người/công ty/kỳ đúng; không xem dữ liệu ngoài quyền; command guard và chạy lại không trùng/không đổi mốc. Chạy `TechnicalAssignedWorkKpiTest` và bộ hồi quy thực sự bị thay đổi. Không đổi assertion điểm để che lỗi; không vô hiệu DB guard.

Kiểm tra trình duyệt local 8001 với tài khoản mẫu: chọn tháng/người, thấy checklist trực tiếp, mở đúng link báo cáo/việc/công trình/bảo hành, không 500, xem được mobile. Đối chiếu 2/4 ×25=12,5 và tổng thực tế. Kiểm tra trưởng phòng/kỹ thuật chỉ thấy thao tác hợp quyền. Nếu dùng web/DB test riêng thì cô lập `CACHE_STORE=array SESSION_DRIVER=cookie MAIL_MAILER=log`; giữ web local và cache nhận diện thương hiệu nguyên trạng.

## C5 — Bàn giao

Cập nhật CHECKLIST, PROJECT mục kết quả và STATUS chung bằng kết quả thật. Ghi test pass/fail và lỗi chưa sửa, không chỉ số ca đã chạy. Chụp ảnh checklist đủ trạng thái, gửi URL đúng tài khoản/kỳ và hướng dẫn 3 bước: trưởng phòng giao → kỹ thuật nộp → vào KPI xem trạng thái/điểm. Ghi rõ nguồn nào đã xác nhận/còn thiếu và mẫu pending hết hạn lúc nào. Không commit/deploy hoặc duyệt tiền.
