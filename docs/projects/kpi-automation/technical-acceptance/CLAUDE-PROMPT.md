# Prompt triển khai cho Claude Code

Làm trong `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`. Triển khai đợt **Công trình → Nghiệm thu: kỹ thuật nhập → trưởng phòng kiểm tra/xác nhận → KPI tự cập nhật**; không dừng ở báo cáo hoặc viết thêm kế hoạch.

Đọc `.claude/skills/ego-technical-acceptance/SKILL.md`, skill nền nó liên kết và `PROJECT.md`, `PLAN.md`, `CHECKLIST.md` trong `docs/projects/kpi-automation/technical-acceptance/`. Đọc STATUS/DECISIONS của dự án chính để giữ kết quả và giới hạn hiện tại. Nếu có AGENTS.md/CLAUDE.md áp dụng, đọc chúng trước sửa. Không chạy lại prompt phiên đầu hoặc triển khai cả HCNS/kho trong đợt này.

Source đã có form KPI trong `workflow-kpi-review.blade.php`, `saveData()` và adapter `ProjectKpiLinkService::workflowEvidenceRows()`; tiếp nối chúng. Kỹ thuật hiện chỉ xem; quản lý Lưu là đóng dấu review, HSE chưa ràng xác nhận và EVN/App còn suy từ link monitoring. Tách khai báo nháp/gửi khỏi kết quả kiểm tra; dùng null/đạt/không đạt, minh chứng, phiên bản nội dung và ID/ngày xác nhận server. Kỹ thuật không tự xác nhận. Lưu từng nhóm không được đặt false nhóm khác hoặc xóa cờ pháp lý. Sửa/thay-xóa minh chứng/trả bổ sung phải kiểm tra lại bản mới, giữ lịch sử và khóa được cả API.

Giữ giao diện trong bước Nghiệm thu, không thêm menu/trang nhập điểm. KS02 lấy lịch sử trả lại và phản ánh đã kiểm tra; KS04 đủ 5 mục HSE có xác nhận; KS05 có kết quả EVN/App đã xác nhận và yêu cầu đấu nối rõ ràng, link App đơn lẻ không là đạt. Hiển thị mục thiếu/chờ/đã nhận điểm ngay tại Nghiệm thu và truy nguồn từ bảng KPI. Không tạo công thức điểm riêng trong view, không chỉ áp dụng cho mã TEST.

Trưởng phòng xác nhận dữ liệu KPI là bước riêng. Track duyệt nghiệm thu cuối hiện ở `config/project_workflow_v2.php` thuộc Admin: giữ nguyên, không cấp role/quyền mới và không dùng nút xác nhận KPI để tự approve workflow. Kiểm tra quyền công ty/công trình và không cho người thực hiện tự xác nhận.

Triển khai lần lượt A1–A5 trong PLAN, hoàn thiện cả form, lưu/gửi, xác nhận/trả bổ sung, adapter, ghi chú và kiểm thử. Giữ trọng số 25/15/15/10/5/30 từ tháng 10/2026 và bộ cũ trước đó. Kho/vật tư tự đối chiếu theo mã/đơn vị, D07/D08/D12 và tiền lương chưa thuộc phần sửa này; không tự chốt để đủ demo. Thiếu nguồn vẫn báo thiếu.

PHP `C:/php83/php.exe`. Feature test chỉ dùng DB `egosolar_test`, giữ guard. Test mới theo PLAN và chạy lại `TechnicalKpiSourceFlowTest`, `TechnicalKpiMonthlyInputTest`, `MaintenanceCompleteWithoutFullEvidenceTest` (baseline trước đợt: 83 pass/407 assertions, không dùng số đó làm kết quả đợt mới). Migration chỉ khi thật cần, target đã kiểm tra, giữ dữ liệu cũ; không migrate:fresh hoặc migrate toàn repo.

Thử UI thật nhập → gửi → xác nhận → duyệt nghiệm thu đúng quyền → xem nguồn/điểm với fixture riêng DB test và server riêng, không đổi `.env`/cổng 8001 sang test, không seed DB local. Mẫu local cũ đã hoàn tất/xác nhận; không mở lại hoặc chỉnh điểm của nó. Lưu ảnh/log private gitignore; không chạy được UI thì báo rõ, không nhận là đã test UI. Hoàn thiện code local và để lại đường dẫn/cách nhập theo hai vai trò cho người dùng xem.

Repo có nhiều thay đổi chưa commit: baseline trước sửa, giữ thay đổi có sẵn; không reset/clean/commit/push/deploy, không gửi thông báo thật. Không tạo user/role mới trong local. Giữ nguyên snapshot lương 08/2026, cấu hình tháng 10 còn nháp, không tự duyệt trả tiền. Một phiên ghi source; nếu phát hiện phiên khác đang sửa cùng tệp, tiếp tục phần độc lập và điều phối trước khi ghi đè.

Khi xong, cập nhật CHECKLIST và PROJECT đợt này, STATUS dự án chính với file sửa, test/UI pass-fail và giới hạn. Báo ngắn bằng tiếng Việt: đã làm, cách kỹ thuật nhập, cách trưởng phòng xác nhận, URL xem, test đã chạy và phần cần tiếp tục. Không coi demo 100 điểm là hệ thống hoàn thiện 100%.
