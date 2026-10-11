# Prompt sửa sau review Nghiệm thu

Tiếp tục source `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`, không làm lại A1–A5 từ đầu. Đọc `.claude/skills/ego-technical-acceptance/SKILL.md`, hồ sơ đợt này và `REVIEW-20261006.md`.

Review độc lập đã chạy lại 103 ca/659 assertions đạt nhưng phát hiện **2 lỗi qua 3 ca thử đều thất bại**. Sửa source và bổ sung regression trước khi đánh dấu hoàn tất:

1. **R1: xác nhận cũ sống lại sau khi đổi dữ liệu về giá trị cũ.** Gửi HSE đạt → trưởng phòng xác nhận → kỹ thuật lưu nháp một mục fail → lưu nháp pass lại: xác nhận version 1 tự có hiệu lực dù bản mới chưa gửi/kiểm tra. Sửa cơ chế xác nhận để vô hiệu bền vững theo nhóm/lần thay đổi; fingerprint giống lại không phục hồi xác nhận. Giữ lịch sử, giữ nhóm không sửa và buộc gửi/kiểm tra lại nhóm đã sửa. Kiểm tra thêm đường thay/xóa minh chứng hoặc đổi quyết định đấu nối; không chỉ thêm một điều kiện status toàn bộ khiến các nhóm khác bị mất điểm.

2. **R2: Admin sửa được nguồn của kỳ đã duyệt.** `periodLocked()` trả true nhưng Admin vẫn POST lưu khai báo khác hoặc upload file chứng chỉ làm nguồn thay đổi. Đặt guard khóa kỳ trước mọi ghi vào khai báo/minh chứng/nguồn liên quan, gồm cờ đấu nối và hành động workflow ảnh hưởng điểm; không chỉ ở confirm hoặc view. Khóa kỳ phải chặn cả Admin khi chưa mở kỳ theo quy trình được phép. Giữ ngoại lệ Admin sửa bước approved khi kỳ CHƯA khóa; không tự mở kỳ, không tự đổi quyền, không sửa snapshot/tiền lương. Kiểm tra concurrency guard với bước cập nhật nguồn.

Ba ca tái hiện ở `outputs/claude-kpi-agent/acceptance-review-20261006/TechnicalAcceptanceKpiReviewRegressionTest.php` (private): lấy các phương thức `test_review_*` vào test nguồn phù hợp, không nhân đôi toàn bộ fixture/20 ca. Log `regression-results.txt` là bằng chứng trước sửa. Chạy lại ca mới, 103 ca hồi quy và kiểm tra quyền/nhóm không thay đổi. Chỉ feature test DB `egosolar_test`, giữ guard và rollback.

Thử UI ở server/DB test cô lập khi có công cụ: sửa rồi đổi về bản cũ vẫn chờ kiểm tra; kỳ đã duyệt chặn chỉnh nguồn; kỳ chưa chốt giữ thao tác Admin hiện có. Cô lập cache/session/mailer khỏi web local; không đổi `.env`, cổng 8001 hoặc seed DB local. Không mở lại mẫu 63 để test. Không commit/push/deploy, không cấp thêm quyền đấu nối, không thay trọng số/D07/D08/D12 hoặc duyệt lương.

Cập nhật REVIEW, CHECKLIST A3-02/A3-03 và PROJECT/STATUS bằng số test mới, ảnh và phạm vi đã kiểm tra. Không đánh dấu xong nếu ca mới còn thất bại. Báo ngắn: lỗi trước/sau, file sửa, test/UI và phần còn lại.
