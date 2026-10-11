# Checklist nghiệm thu đợt này

Đã triển khai trên source local ngày 07/10/2026 (chưa commit/deploy). Bằng chứng private trong `outputs/kpi-checklist-demo-20261007/` (đã thêm vào `.gitignore`).

- [x] C1-01 Đọc source/quy tắc D13; xác minh công ty, role, mẫu, timezone và môi trường. — local, DB `crm_egosolar_test` @127.0.0.1, `Asia/Ho_Chi_Minh`, công ty 2; trưởng phòng 97 (`technical_manager`), kỹ thuật 98 (`ky_thuat`), công trình 63, phiếu 148 khớp mã.
- [x] C1-02 Lưu baseline private; xác định file đang có thay đổi/phiên sửa cùng phạm vi. — `baseline/` + `before.json`; phiên còn lại đang rảnh, sửa source dừng 09:46 trước khi bắt đầu.
- [x] C2-01 Mở từng người thấy checklist ngay trên trang của đủ 6 tiêu chí. — khối "Checklist nguồn theo tiêu chí" ngay dưới bảng 6 tiêu chí; UI: 6 khối `timeline…warranty`.
- [x] C2-02 Tách đã làm/chưa làm khỏi đạt/mất điểm/chờ/thiếu xác nhận; không tự đạt từ ô trống. — cột "Thực hiện" và "KPI" riêng; test `test_presenter_separates_work_status_from_kpi_status…`.
- [x] C2-03 Hiện mốc, lý do, điểm đúng bộ chấm; link đúng hồ sơ/bước/báo cáo. — điểm từng dòng do bộ chấm gắn (`points_possible/earned`); UI mở 8 link nguồn HTTP 200.
- [x] C2-04 Đúng người/kỳ/công trình/công ty/quyền; mobile vẫn thấy checklist. — test phạm vi người/công ty; UI kỹ thuật đổi `user_id` vẫn chỉ thấy mình; 390 px: 1 cột, không tràn ngang.
- [x] C3-01 Command/script opt-in có guard, không gửi thông báo thật, manifest private. — `php artisan kpi:demo-checklist` (mặc định xem trước, `--apply` mới ghi); guard môi trường/DB/host/công ty/4 định danh mẫu.
- [x] C3-02 Mẫu 5 việc gồm 2 đúng hạn, 1 muộn, 1 quá hạn chưa nộp, 1 nháp còn hạn. — plan item 1–5, báo cáo 1–4 trên local.
- [x] C3-03 Chạy lại không trùng, không đổi mốc/nội dung đã thao tác; lịch sử lần gửi đầu đúng. — chạy lại local: 5 dòng "đã có — giữ nguyên"; test `test_rerun_does_not_duplicate…`.
- [x] C3-04 Công trình/bảo hành/xác nhận cũ, lương 08/2026 và cấu hình tháng 10 nguyên trạng. — so `before.json`/`after.json`: hash công trình 63, bước, hồ sơ, sự kiện, phiếu 148, lương 08/2026 (12 dòng, nháp) và cấu hình không đổi.
- [x] C4-01 Test trạng thái, điểm, phạm vi/quyền, guard/idempotency đạt trên DB test riêng. — `TechnicalKpiChecklistTest` 8/8, 96 assertions.
- [x] C4-02 Bộ KS01 24 giờ và các hồi quy bị ảnh hưởng đạt; ghi rõ lỗi còn lại. — 6 bộ (checklist, KS01, nguồn KPI, nhập tháng, Nghiệm thu, bảo hành) 123/123, 801 assertions. Nhóm mở rộng 335 ca: 9 lỗi đã có từ trước (8 markup `TechnicalKpiStandardPageTest`, 1 `TechnicalAdminPagesTest`); cần `memory_limit=1G` khi chạy gộp.
- [x] C4-03 Trình duyệt thật thấy checklist, mở được nguồn không 500; kiểm tra mobile và 2 role mẫu. — web local 8001, 34/34 bước đạt (`ui/results.json`).
- [x] C4-04 Đối chiếu 12,5/25 tại mốc đánh giá mẫu và tổng thực tế, không ép bằng nhập tay. — 07/10 10:19: 2/4 × 25 = 12,5; tổng 12,5 + 15 + 15 + 10 + 5 + 30 = 87,5, khớp trang.
- [x] C5-01 Ảnh, manifest, thời điểm pending hết hạn và hướng dẫn nhập/nộp/xem điểm được bàn giao. — `ui/shots/`, `manifest-first-apply.json`, `HUONG-DAN.md`; việc 05 hết hạn 08/10/2026 09:00.
- [x] C5-02 PROJECT/STATUS có kết quả thật; R1/R2 và các phần ngoài phạm vi vẫn theo dõi riêng.
