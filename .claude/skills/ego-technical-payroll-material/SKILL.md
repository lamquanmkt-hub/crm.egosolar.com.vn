---
name: ego-technical-payroll-material
description: Đồng bộ phiếu lương kỹ thuật cũ với KPI theo phiên bản, giữ nguyên tiền KPI không làm tròn trên kỹ thuật và HR, hoàn thiện đối chiếu vật tư D08 cùng khóa kỳ trong crm.egosolar.com.vn. Dùng cho đợt chuẩn bị trả lương thật; chỉ bàn giao người có quyền duyệt cấu hình mới.
---

# Kỹ thuật: phiếu lương, HR và đối chiếu vật tư

**Cập nhật 08/10/2026 (D16):** manager nhập tay vật tư tạm thời đã được triển khai, xem [kết quả và hướng dẫn](../../../docs/projects/kpi-automation/MANUAL-MATERIAL-20261008.md). Giữ nháp/xác nhận, minh chứng, truy vết và khóa kỳ. Không coi D08 tự động đã chốt hoặc hoàn tất; các yêu cầu S3 bên dưới là bước Kho tự động tiếp theo.

Đọc [PROJECT](../../../docs/projects/kpi-automation/technical-payroll-material/PROJECT.md), [PLAN](../../../docs/projects/kpi-automation/technical-payroll-material/PLAN.md), [DECISIONS](../../../docs/projects/kpi-automation/DECISIONS.md) và STATUS chung. Làm S0–S4; phạm vi là bốn mục người dùng giao, không thay đợt này bằng sửa giao diện hướng dẫn hay lập lại kế hoạch.

## Các điểm phải giữ

- Phiếu lương cũ cho kỳ mới phải đọc cùng bộ chấm/cấu hình hiệu lực với trang KPI và HR. `Synced/TechnicalKpi/TechnicalPayrollController::calculate()` còn dùng template/settings riêng; kiểm các route trùng tên và đường POST vẫn truy cập được. Chặn sửa/xóa/đổi người hoặc tháng của phiếu đã duyệt; giữ snapshot lịch sử, không tính lại kỳ cũ hàng loạt.
- **D15 đã chốt: không làm tròn tiền KPI/cấu phần lương kỹ thuật trong đợt mới.** Kỹ thuật và HR giữ kết quả tính; không làm tròn tới 1.000/10.000đ, làm tròn lên, ép số nguyên hoặc cắt phần lẻ để hai trang khớp. Chuẩn bị `round_rule=none` trong bản nháp còn chưa duyệt; nếu bản đó đã duyệt thì tạo bản nháp kế thừa, không sửa tại chỗ. Giữ template phòng ban khác, quy tắc thuế/BH và Excel snapshot. Cùng hệ số và tỷ lệ công, không nhân % hai lần. Hồ sơ HR khác mức lương kỹ thuật thì báo/đối chiếu đầu vào, không tự sửa dữ liệu thật để ép tiền khớp.
- D08 không được cộng tổng số lượng các mã/đơn vị khác nhau hoặc coi đề xuất đã duyệt là xuất thực tế. Theo dõi baseline đã xác nhận, xuất kho thực, thu hồi nguyên vẹn đã nhập kho, mapping mã/đơn vị và người xác nhận. Thiếu mapping/baseline/nguồn hợp lệ thì thiếu, không tự 0 hoặc đạt.
- Cách tổng hợp D08 đang chờ lựa chọn người dùng: từng mã/đơn vị đều lệch `<3%` hay tổng theo giá trị tiền. Đọc câu trả lời/DECISIONS mới nhất; nếu chưa chốt thì làm bảng/nguồn/khóa/S1–S2 và giữ chấm tổng pending. Không coi lựa chọn đầu tiên trong câu hỏi hoặc ví dụ tài liệu là đã được phê duyệt. Không hỏi lại khi đã có câu trả lời.
- Nối D08 vào đúng khảo sát/checklist, không để số nhập tay cũ âm thầm bypass. Giữ nguồn/kỳ lịch sử và xác nhận cũ đã truy vết; sửa nguồn mới làm mất hiệu lực đúng nhóm, không sống lại khi đổi về.
- Khóa vật tư ở mọi service/entry point ghi nguồn, trước DB/file/ledger, theo cả phiếu kỹ thuật và HR live đã duyệt; Admin không vượt. Chứng từ nhiều site phải kiểm tất cả site chịu ảnh hưởng. Kỳ Excel không khóa nguồn live; không khóa cả hoạt động Kho không liên quan.
- V3 chỉ bàn giao duyệt sau bằng chứng sẵn sàng. Không tự approve cấu hình/nghiệm thu/lương local hoặc gán người duyệt theo yêu cầu triển khai.

## Môi trường và kiểm chứng

Repo `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`, web 8001, DB local `crm_egosolar_test`, PHP `C:/php83/php.exe`. Test chỉ `egosolar_test`, giữ TestCase guard. Cache/session/mail/storage/server chụp ảnh phải cô lập. Không migrate:fresh, reset/clean, commit/push/deploy hoặc gửi thông báo thật; không tự ngắt phiên khác. Nếu cần migration bổ sung thì giữ dữ liệu cũ, kiểm trên DB test trước, báo rõ schema local đã/chưa cập nhật.

Giữ công trình 63/64, phiếu 148, việc/lương mẫu, cấu hình đã duyệt, kỳ HR 08/2026 và tài khoản. Chỉ được chuẩn bị bản nháp tiền theo %/không làm tròn theo D15, không tự áp dụng. Demo/duyệt xuyên suốt chạy trên fixture test, không bù điểm nhập tay để gọi là tự động. Bằng chứng riêng trong `outputs/claude-kpi-agent/technical-payroll-material-20261007/` (cha đã gitignore), không đưa mật khẩu hay dữ liệu thật vào Git.

Kiểm tiền cùng đầu vào và nguồn: chế độ tỷ lệ/không làm tròn, 15 triệu 70/30, đạt 87,5%, đủ công → KPI **3.937.500**, tổng dự kiến **14.437.500**; nửa công → KPI **1.968.750**. Rà `TechnicalKpiConfig::applyRounding`: nhãn `none` hiện rơi vào nhánh default `round($amount)`; cần thực sự giữ tiền, kiểm cả lưu DB và định dạng hiển thị không cắt/làm tròn lẻ. HR cần thử thật trên trình duyệt với ngày công/khoản lương/khấu trừ/thiếu nguồn/quyền/khóa, desktop/mobile. Test D08 theo mã/đơn vị, biên `<3%`, xuất/thu hồi chưa hoàn tất, đếm trùng và sửa sau khóa. Giao preview và checklist review được; phần pending phải báo đúng, không đánh dấu S4 sẵn sàng khi còn thiếu quyết định/bằng chứng.
