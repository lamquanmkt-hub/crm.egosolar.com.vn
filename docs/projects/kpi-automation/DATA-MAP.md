# Bản đồ dữ liệu KPI

Các trường dưới đây mô tả dữ liệu cần có, không khẳng định mọi field đã tồn tại. Agent phải ghi tên bảng/field và đường cập nhật thật sau P0. Không tạo cột trùng nếu module đã có trường cùng nghĩa.

## Nguồn code cần mở theo phần việc

| Phần | Điểm bắt đầu trong source |
|---|---|
| Tổng hợp KPI HCNS/kho | `app/Services/Hr/Kpi/HrKpiEvaluator.php`, `app/Http/Controllers/Hr/HrKpiController.php` |
| Bộ tiêu chí/trọng số | `database/migrations/2026_10_01_090000_create_hr_kpi_payroll_tables.php`, `2026_10_05_100000_hr_payroll_kpi_followup.php` |
| KPI kỹ thuật | `app/Services/TechnicalKpi/KpiStandardEvaluator.php`, `ProjectKpiLinkService.php`, `app/Models/TechnicalKpiConfig.php`, `config/technical_kpi_standard.php` |
| Nhập/điều chỉnh kỹ thuật | `app/Http/Controllers/Synced/TechnicalKpi/TechnicalKpiInputController.php`, `app/Http/Controllers/TechnicalKpi/TechnicalProjectKpiController.php` |
| Tuyển dụng/hồ sơ | `app/Http/Controllers/Hr/RecruitmentController.php`, các migration `hr_employee_profiles` và `hr_recruitment` |
| Hồ sơ/công việc HC | `app/Http/Controllers/Hr/DocumentHandoverController.php`, `HcOperationController.php` |
| VPP/chi phí/sự cố | `app/Http/Controllers/Hr/OfficeSupplyProcessController.php`, `OfficeExpenseController.php`, `OfficeIncidentController.php`, `app/Services/Hr/OfficeIncidents.php` |
| Kho | `app/Http/Controllers/Inventory/ProductGoodsReceiptController.php`, `app/Services/Inventory/ProductStockLotQueryService.php`; mở route liên quan để xác minh phiếu xuất và kiểm kê |
| Tính lương | `app/Services/Hr/Payroll/PayrollGenerator.php`, `PayrollAttendanceSummary.php`, `PayrollCalculator.php`, `PayrollSnapshotImporter.php`, `app/Http/Controllers/Hr/PayrollController.php` |
| Giao diện gọn | `config/hr_payroll_menu.php`, `config/hr_payroll_departments.php`, `resources/views/hr/kpi/`, `resources/views/hr/payroll/` |

`KpiStandardEvaluator::manualScores()` đọc `technical_kpi_monthly_scores`. Nguồn kỹ thuật gồm dự án cũ, workflow, `/du-an`, minh chứng đã duyệt và điều chỉnh; phải kiểm tra khóa liên kết trước khi nối thêm nguồn.

## Dữ liệu chung tối thiểu

- ID bản ghi nguồn và loại nguồn; ID nhân viên chịu trách nhiệm; công ty/kho/dự án/phòng ban nếu nghiệp vụ có phạm vi đó.
- Mốc tiếp nhận, hạn, hoàn tất, xác nhận theo nghiệp vụ; lịch sử thay đổi hạn và trả lại. Trường chỉ có ngày không đủ để đo SLA theo giờ.
- Kết quả, người xác nhận, thời điểm xác nhận, minh chứng. Không gán `approved_at` hoặc `completed_at` từ `updated_at` để lấp dữ liệu lịch sử.
- Quy tắc chọn kỳ, mẫu số và loại trừ phải cấu hình/được xác nhận. Hủy/không phát sinh/thiếu dữ liệu không là cùng một trạng thái.
- Kết quả tổng hợp có actual/target/achievement nullable, trạng thái dữ liệu, nguồn được tính/bị loại, lý do thiếu/N/A, rule version, thời điểm tổng hợp và trạng thái duyệt/khóa.
- Nên tính từ nguồn chuẩn và có snapshot lúc duyệt; không giữ nhiều bản điểm tự thay đổi ở các module mà không biết nguồn ưu tiên.

## Kỹ thuật — 6 tiêu chí

Từ 10/2026 dùng bản mới trong Downloads; ngày hiệu lực 01/10/2026 đã được người dùng xác nhận. Các trọng số trong bảng dưới đây là bộ mới; xem TECHNICAL-REVISION-20261006.md để đối chiếu kỳ cũ.

| Mã | Tiêu chí / trọng số Word | Nguồn và dữ liệu cần có | Điểm thiếu cần xử lý / cách đo |
|---|---|---|---|
| KS01 | Tiến độ — 25% | Phân công kỹ sư; dự án/hệ thống; hạn cam kết; ngày bàn giao; đổi hạn đã duyệt | Số hệ thống bàn giao đúng hạn / tổng bàn giao trong phạm vi; công trình không có hạn phải báo thiếu |
| KS02 | Chất lượng/thẩm mỹ — 15% | Nghiệm thu lần đầu; trả lại; checklist thi công; phản ánh có/không và kết quả xác nhận | Không suy không có khiếu nại từ trường trống hoặc chỉ từ trạng thái nghiệm thu |
| KS03 | Khảo sát/khối lượng — 15% | Hồ sơ khảo sát đủ; dự toán đã chốt từng mã/đơn vị; xuất thực tế; thu hồi nguyên vẹn; xác nhận | Tiêu hao = xuất − thu hồi; đối chiếu với bóc tách theo cùng mã/đơn vị; chốt tổng hợp và ngưỡng `< 3%` (D08) |
| KS04 | HSE/vệ sinh — 10% | Checklist an toàn và vệ sinh; người kiểm; ảnh; biên bản vi phạm và ngày xảy ra | Không có vi phạm ghi nhận chưa chứng minh đã kiểm tra; tránh trừ cùng lỗi hai lần |
| KS05 | EVN/App — 5% | Xác nhận đấu nối thử; cài App; hướng dẫn/bàn giao; người xác nhận; lý do N/A nếu có | Link giám sát không chứng minh đủ tất cả điều kiện; thiếu một phần phải chỉ ra phần thiếu |
| KS06 | Bảo hành — 30% | ID kỹ sư; tiếp nhận thông tin; phản hồi đầu tiên; hạn và hoàn tất bảo hành; kiểm tra khiếu nại; người xác nhận/minh chứng | Phản hồi trong 5 phút, hoàn thành trong 24 giờ và không khiếu nại. D12 định nghĩa mốc/phạm vi; thiếu nguồn không tự thành đạt hoặc N/A |

Tài liệu có thưởng từ 100% hưởng 110–120%, trong khi cấu hình hiện tại hưởng 100%; không thay mức trả tự động (D03).

### Kết quả P0 kỹ thuật — source thật (06/10/2026)

Lưu ý tên gọi trong code: "/du-an" = `sites` + `project_workflow_*` (UnifiedProjectController, ProjectWorkflowV2Controller); hàm `duAn*` trong `ProjectKpiLinkService` thực ra đọc module Công trình cũ `project_test_*`.

| Mã | Ghi nguồn (route/form) | Bảng/field dùng để tính | Trạng thái sau phiên |
|---|---|---|---|
| KS01 | `POST du-an/{site}/quy-trinh/{step}/phan-cong` (hạn `due_at`), `.../xu-ly-tre` (`recommitted_due_at`), `.../duyet` | `project_workflow_assignments.user_id` (bước construction/acceptance) hoặc `sites.lead_engineer_id`; hạn = `acceptance.due_at` → `construction.due_at` → `sites.target_completion_at`; bàn giao = `acceptance.approved_at` khi có `approved_by`; lịch sử đổi hạn = `project_workflow_events` (`step_assigned`, payload `due_at`/`previous_due_at`) | Đã nối. `recommitted_due_at` không thay hạn KPI. Bước tự đánh dấu khi chuyển dữ liệu (approved_by trống) không là bàn giao. Quá hạn còn mở được liệt kê mọi tháng (D07) |
| KS02 | `.../duyet`, `.../tra-lai`, form KPI bước Nghiệm thu (`.../du-lieu`, `kpi_review_submitted`) | `project_workflow_events.step_revision_requested`, `acceptance.data.kpi_customer_complaint` | Chỉ thêm điều kiện nghiệm thu có người duyệt; việc còn lại chưa làm |
| KS03 | Hồ sơ bước Khảo sát/Đề xuất; đề xuất vật tư | `project_workflow_documents`, `project_material_proposal_items.requested_qty` (cộng mọi đơn vị — **gap D08**), `acceptance.data.kpi_material_deviation_percent` | Chưa sửa |
| KS04 | Checklist HSE bước Nghiệm thu | `acceptance.data.kpi_*` (5 mục) | Chưa sửa |
| KS05 | Checklist EVN/App bước Nghiệm thu | `legal.data.requires_grid_connection`, `kpi_evn_grid_tested`, `kpi_app_installed`, `sites.monitoring_link` | Chưa sửa |
| KS06 | Trang phiếu `du-an/bao-tri-bao-hanh/{schedule}` → `POST .../{schedule}/sla-bao-hanh` (mới) | `solar_maintenance_schedules` loại `incident`/`warranty_inverter`; trách nhiệm = `assigned_to`/trưởng nhóm; mốc mới `customer_reported_at`, `first_responded_at(_by)`, `complaint_status`, `complaint_checked_by/_at`; hoàn tất = `execution_finished_at` (có sẵn, ghi khi bấm Hoàn tất thực hiện) | Đã nối nguồn + chấm. Bảng `crm_serial_warranty_claims` (0 dòng local, ngày chỉ có DATE) không dùng được cho SLA 5 phút |

Nguồn khác: `technical_kpi_project_evidence` (minh chứng duyệt tay, 0 dòng local), `technical_kpi_monthly_scores` (nhập tay Kế hoạch/Thực tế, ưu tiên hơn số tự động — đúng tên bảng; không có `technical_kpi_manual_scores`), `technical_kpi_adjustments`.

Dữ liệu local (chỉ đếm): 40 công trình; 30 bước Nghiệm thu "approved" đều không có người duyệt và không có sự kiện (đánh dấu khi chuyển dữ liệu) → không còn được tính là bàn giao; 0 phân công workflow, 0 `lead_engineer_id`; 25 dự án module cũ, 10 không liên kết `legacy_site_id` (không đối chiếu trùng được bằng khóa); 133 lịch bảo trì, chỉ 1 loại `incident`, chưa có mốc SLA nào → KS06 các kỳ hiện tại báo thiếu dữ liệu.

## HCNS — 9 tiêu chí

| Mã | Tiêu chí | Nguồn và dữ liệu cần có | Cách đo / giới hạn |
|---|---|---|---|
| HC01 | Thời gian tuyển dụng | Yêu cầu tuyển; ngày tiếp nhận; ngày thực nhận việc đã xác nhận; người tuyển; liên kết nhân viên | Trung bình ngày nhận yêu cầu → thực nhận việc; nhóm hồ sơ tính và hủy cần thống nhất |
| HC02 | Chi phí tuyển dụng | Chi phí gắn đợt/yêu cầu; loại chi phí; số vị trí tuyển thành công; người phụ trách | Tổng chi phí thuộc phạm vi / vị trí thành công; không có tuyển thành công không tự chia 0 hay tính đạt |
| HC03 | Nghỉ trong thử việc | Nhóm thử việc; ngày bắt đầu/kết thúc; ngày nghỉ việc thật; kết quả; lý do | Số nghỉ trong thử việc / nhóm được chốt; tài khoản khóa không thay ngày nghỉ |
| HC04 | Chính xác bảng lương | Kỳ lương; dòng bị lỗi; lỗi đã xác nhận; người chịu trách nhiệm; nguyên nhân; lịch sử sửa | Tỷ lệ dòng đúng trên phạm vi chốt; sửa dữ liệu hợp lệ không tự thành lỗi |
| HC05 | BHXH đúng hạn | Phiếu nghiệp vụ BHXH; người xử lý; hạn; ngày nộp/hoàn tất; minh chứng | Đúng hạn / tổng hồ sơ đến hạn; việc quá hạn chưa xong vẫn ở mẫu số |
| HC06 | Tài sản/VPP | Yêu cầu; thời gian nhận; hạn cấp; người xử lý; ngày nhận; lịch và biên bản kiểm kê; nguồn sự cố | Tách cấp phát, kiểm kê và sự cố; gộp theo quy tắc D09. Không dùng sự cố thay kiểm kê |
| HC07 | Công văn/giấy tờ | Hồ sơ giao nhận; phân công; mốc nhận/gửi/hoàn tất; hạn; kiểm tra chính xác | Đúng hạn và chính xác trên phạm vi hồ sơ đến hạn; tính cả quá hạn còn mở |
| HC08 | Hài lòng nội bộ | Kỳ khảo sát; câu hỏi; phản hồi; số người mời/trả lời; thang điểm | Chốt tỷ lệ phản hồi tối thiểu và cách quy đổi; sao sự cố không thay khảo sát môi trường |
| HC09 | Thủ tục hành chính | Công việc đúng loại; ID người phụ trách; hạn; `completed_at`; người xác nhận | Tỷ lệ đúng hạn trên công việc đến hạn; không suy ngày hoàn tất từ ngày cập nhật |

Word không có trọng số 9 tiêu chí. Hiện source chia đều tạm; đánh dấu chờ xác nhận D01. Công thức so target (`higher_better`/`lower_better`) hiện có không tự quyết định cách chấm cho mọi tiêu chí mới.

## Kế toán kho — 7 tiêu chí

| Mã | Tiêu chí / trọng số Word | Nguồn và dữ liệu cần có | Cách đo / giới hạn |
|---|---|---|---|
| KT01 | Chênh lệch kiểm kê — 25% | Kỳ/kho; tồn sổ chốt; tồn thực tế từng mã; người kiểm và duyệt; biên bản | Số mã lệch / số mã kiểm ×100%; mục tiêu 0%, không dùng giá trị hoặc lượng tổng thay số mã |
| KT02 | Lỗi chứng từ — 15% | Chứng từ; loại lỗi; người chịu trách nhiệm; lỗi được kiểm tra xác nhận | Số chứng từ lỗi / tổng chứng từ trong phạm vi; chỉnh sửa hợp lệ không tự thành lỗi |
| KT03 | Nhập phiếu trong 8 giờ — 15% | Giờ nhận chứng từ; người nhận/xử lý; giờ hoàn tất nhập; loại phiếu; lịch làm việc | Giờ làm việc thực tế từ nhận đến nhập xong; `invoice_date`/ngày lô không thay giờ nhận |
| KT04 | Kiểm kê trước ngày 02 — 15% | Kỳ tháng; hạn; mốc kiểm/đối chiếu; ký xác nhận của thủ kho và kế toán | Xác định ngày 01 hết ngày hay cách tính khác; giữ việc đến hạn chưa hoàn tất |
| KT05 | Báo cáo NXT — 10% | Lịch tuần/tháng; hạn; bản chốt báo cáo; thời điểm nộp; người nhận | Báo cáo đúng hạn / báo cáo đến hạn; có dữ liệu tồn không có nghĩa đã nộp báo cáo |
| KT06 | Cảnh báo tồn lâu/lỗi — 10% | Mã/lô; tuổi tồn; phiếu cảnh báo; người gửi/nhận; ngày gửi; phản hồi và bằng chứng | Word ghi trước 15 ngày nhưng chưa rõ mốc; phải chốt D10, không tự gửi email để chứng minh |
| KT07 | Hồ sơ gốc — 10% | Mã bộ hồ sơ; vị trí lưu; checklist; đủ/thiếu/hỏng; người kiểm; biên bản | Điểm theo kiểm tra thực tế xác nhận; có file điện tử không chứng minh chứng từ gốc đầy đủ |

Trọng số tổng 100%. Quy đổi tiền và biên 70%/100% còn cần D02; không lấy ví dụ 93% làm chính sách mặc định.

## Các tình huống cần kiểm thử

1. Một hồ sơ thiếu ngày nhận nhưng có ngày hoàn tất: SLA chưa đủ dữ liệu.
2. Việc quá hạn còn mở: vẫn xuất hiện trong mẫu số đúng phạm vi.
3. Tháng không phát sinh: có trạng thái/lý do xác nhận; khác tháng chưa nhập dữ liệu.
4. Một công trình có ở hai nguồn: chỉ tính một lần theo khóa nghiệp vụ.
5. Vật tư gồm mét và cái: không cộng trực tiếp vào cùng mẫu số.
6. Chứng từ nhận chiều thứ Sáu: SLA giờ làm việc đi qua cuối tuần/ngày lễ theo lịch được chốt.
7. Cấu hình có hiệu lực tháng sau: không áp dụng cho kỳ trước.
8. Kỳ đã khóa và nguồn bị sửa: số đã duyệt giữ nguyên, yêu cầu mở lại theo quyền/lý do.
9. Người không có quyền bộ phận: không đọc nguồn/điểm ngoài phạm vi bằng đổi URL.
10. Bảng lương Excel đã nhập: mọi thao tác đồng bộ không thay số tiền hoặc duyệt kỳ đó.
