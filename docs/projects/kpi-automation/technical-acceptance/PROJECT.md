# Đợt triển khai: nhập liệu Nghiệm thu kỹ thuật

Ngày giao: 06/10/2026. Trạng thái: **đã triển khai A1–A5 trên source local ngày 06/10/2026** (chưa commit/deploy). Kết quả ở cuối file.

**Cập nhật review:** chưa nghiệm thu hoàn tất A3-02/A3-03. Chạy lại 103 ca đạt nhưng ba ca bổ sung tái hiện hai lỗi: xác nhận cũ tự có hiệu lực lại khi revert nháp; Admin sửa khai báo/minh chứng của kỳ KPI đã duyệt. Đọc [REVIEW-20261006.md](REVIEW-20261006.md) và sửa theo [prompt sau review](CLAUDE-REVIEW-FIXES.md) trước khi chốt đợt này.

## Mục tiêu

Trong màn hình Công trình → Nghiệm thu, kỹ thuật được giao nhập kết quả và minh chứng; trưởng phòng kiểm tra, xác nhận hoặc trả bổ sung. Bộ KPI đọc đúng dữ liệu đã xác nhận. Người dùng xem được mục nào chưa nhập, chờ kiểm tra, đã nhận điểm hoặc chưa đạt và vì sao.

Đây là đợt đầu của phần nhập liệu còn thiếu, tập trung KS02 chất lượng, KS04 HSE/vệ sinh và KS05 EVN/App. Tiến độ bàn giao tiếp tục theo workflow hiện tại. Kho/vật tư theo mã + đơn vị và chính sách SLA bảo hành thực hiện sau.

## Source đã kiểm tra

Workspace: `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`; local `http://127.0.0.1:8001`; PHP `C:/php83/php.exe`. DB local `crm_egosolar_test`, DB feature test **chỉ `egosolar_test`**.

| File/nguồn hiện có | Hiện trạng và việc cần tiếp nối |
|---|---|
| `resources/views/projects-unified/partials/workflow-word.blade.php` | Form dữ liệu của bước acceptance đã gắn khối KPI; truyền `can_assign` để cho quản lý đánh giá |
| `resources/views/projects-unified/partials/workflow-kpi-review.blade.php` | Kỹ thuật chỉ xem khối disabled; quản lý nhập phàn nàn, 5 mục HSE, EVN/App và tỷ lệ vật tư. Chưa tách khai báo/gửi/kiểm tra |
| `app/Http/Controllers/Projects/ProjectWorkflowV2Controller.php` | `saveData()` lưu JSON và đóng `kpi_reviewed_by/at` khi quản lý gửi khối review. `submitAssignment()`, `approve()`, `revise()`, upload hồ sơ đã có |
| `app/Services/Projects/ProjectWorkflowV2Service.php` | Phân công, trạng thái, hồ sơ bắt buộc, lịch sử và quyền của workflow |
| `project_workflow_steps.data` | Các khóa đang có: `kpi_customer_complaint`, `kpi_customer_complaint_note`, `kpi_reviewed_by/at`, `kpi_evn_grid_tested`, `kpi_app_installed` và 5 HSE. Không giả định tên field mới đã tồn tại |
| `project_workflow_documents`, `project_workflow_events`, `project_workflow_assignments`, `project_workflow_approvals` | Tái sử dụng hồ sơ, lịch sử, phân công và duyệt đã có; kiểm tra schema thật trước sửa |
| `app/Services/TechnicalKpi/ProjectKpiLinkService.php` | `workflowEvidenceRows()` đọc nghiệm thu approved có người/ngày duyệt, chất lượng dựa lịch sử trả lại và xác nhận phản ánh. HSE hiện chưa ràng xác nhận riêng; EVN/App còn suy từ link/tài khoản giám sát |
| `app/Services/TechnicalKpi/KpiStandardEvaluator.php` | Bộ chấm điểm và lý do thiếu; cần nối trạng thái nguồn của luồng mới |
| `routes/project_unified.php` | POST `/du-an/{site}/quy-trinh/{step}/du-lieu`, `/nop-ket-qua`, `/duyet`, `/tra-lai`, `/ho-so`; route name `projects-unified.workflow.*` |
| `config/project_workflow_v2.php` | Bước acceptance có người giao technical_manager/Admin nhưng **track duyệt cuối là Admin**. Trưởng phòng xác nhận KPI là bước riêng, không đổi quyền này |

Tên/đường dẫn trên đã đọc từ source ngày giao; Claude xác minh lại vì workspace có nhiều thay đổi chưa commit. Kiểm tra middleware/policy và phạm vi công ty ở mọi đường ghi, không chỉ ở trang GET.

## Luồng được giao

`Kỹ thuật nhập nháp + hồ sơ → gửi kiểm tra → trưởng phòng xác nhận/trả bổ sung → duyệt nghiệm thu theo track hiện có → bộ KPI đọc nguồn đã xác nhận`.

Xác nhận kỹ thuật không tự biến bước thành approved. Thiếu xác nhận một mục không được làm các mục khác thành đạt. Có thể cho dữ liệu được lưu/gửi với phần thiếu được chỉ rõ; nút xác nhận chỉ nhận các mục có đủ kết quả/minh chứng, không mặc định đánh dấu cả bộ. Hành vi chặn duyệt nghiệm thu hành chính vì thiếu KPI không được tự thêm nếu workflow chưa quy định.

Giao diện gọn: một khối “Kết quả nghiệm thu”, trạng thái từng nhóm, nút Lưu nháp/Gửi kiểm tra và thao tác quản lý phù hợp. Tiền lương và cách chấm không xuất hiện như trường cho kỹ thuật nhập.

## Baseline và giới hạn

- Ba bộ test KPI/bảo hành gần nhất: 83/83 đạt, 407 assertions. Mẫu bảo hành local đã hoàn tất và xác nhận; tổng mẫu 100% không phải mức hoàn thiện hệ thống.
- Phê duyệt công trình mẫu được tạo sẵn, chưa chứng minh nhập/duyệt công trình qua UI từ đầu. Mẫu vật tư dùng 1% nhập tay.
- Giữ trọng số 25/15/15/10/5/30 từ 10/2026; tháng cũ theo bộ cũ. Cấu hình tháng 10 chưa duyệt, tiền KPI chưa được tính/duyệt.
- D07/D08/D12 còn mở. Không đổi mẫu số tiến độ, công thức vật tư, SLA hoặc cơ cấu trả tiền trong đợt này.
- Bảng lương 08/2026 là snapshot 12 dòng; giữ nguyên dữ liệu và trạng thái. Không seed/fixture vào DB local, không tạo role/user mới, không thay `.env` web sang DB test để thử UI.
- Sửa source và test theo phạm vi; migration chỉ khi cần, có target kiểm chứng và không backfill đánh giá/mốc lịch sử. Không reset/clean/commit/push/deploy hoặc gửi thông báo thật.

## Hồ sơ giao việc

[PLAN](PLAN.md) · [CHECKLIST](CHECKLIST.md) · [Prompt cho Claude](CLAUDE-PROMPT.md) · [Skill](../../../../.claude/skills/ego-technical-acceptance/SKILL.md).

## Kết quả sau triển khai — 06/10/2026

### Thiết kế dữ liệu (A1)

Không thêm bảng/cột. Lưu trong JSON có sẵn `project_workflow_steps.data.kpi_acceptance` của bước acceptance; mọi thao tác ghi thêm một dòng `project_workflow_events` (`acceptance_kpi_draft_saved` / `_submitted` / `_confirmed` / `_returned`, payload có bản khai báo và cờ ngoại lệ Admin).

| Khóa | Nội dung |
|---|---|
| `values` | Khai báo hiện tại. `complaint`: null / `none` / `has` + nội dung; `hse`: 5 mục `ACCEPTANCE_HSE_CHECKS`, mỗi mục null / `pass` / `fail`; `grid_tested`, `app_handover`: null / `pass` / `fail`; ghi chú từng nhóm |
| `version`, `updated_by/at` | Tăng khi khai báo đổi; server ghi |
| `status` | `draft` / `submitted` / `returned` |
| `submitted` | Phiên bản, người/giờ gửi, vân tay từng nhóm lúc gửi |
| `returned` | Người/giờ, lý do, các nhóm bị trả |
| `confirmations.{quality,hse,evn}` | Vân tay, kết quả `pass`/`fail`, phiên bản, người/giờ (server ghi), cờ ngoại lệ Admin |

Vân tay của nhóm = nội dung khai báo của nhóm + id/phiên bản file minh chứng + (KS02) số lần "Trả lại" ở Thi công/Nghiệm thu + (KS05) quyết định cần đấu nối. Xác nhận chỉ còn hiệu lực khi vân tay hiện tại trùng lúc xác nhận; bản cũ giữ trong dữ liệu và lịch sử. Minh chứng dùng loại hồ sơ đã có: KS02 = biên bản nghiệm thu khách hàng/ảnh hoàn thiện; KS04 = ảnh hoàn thiện; KS05 = kết quả đo kiểm/chứng chỉ thiết bị, biên bản bàn giao. Không thêm loại hồ sơ hay số file bắt buộc.

Quyết định cần đấu nối ghi ở bước `legal` (hiển thị trong tab **HĐ & Pháp lý**): ba trạng thái Chưa xác định / Có / Không, server ghi `requires_grid_connection_decided_by/at`. Giá trị `false` cũ không có dấu này được coi là **chưa xác định** (form cũ tự ép false khi lưu bất kỳ bước nào). `true` cũ được nhận.

### File đã sửa / thêm

- Thêm `app/Services/Projects/AcceptanceKpiService.php`: trạng thái, vân tay, quyền, lưu/gửi, xác nhận, trả bổ sung, khóa kỳ.
- `app/Http/Controllers/Projects/ProjectWorkflowV2Controller.php`: 3 action mới; `saveData()` chỉ ghi cờ của đúng bước khi form gửi trường (hết ghi đè `requires_grid_connection`/`technical_feasibility_confirmed`…), lưu được null, bỏ đường `kpi_review_submitted`; ô % vật tư giữ cho Trưởng phòng/Admin; khóa upload minh chứng KPI sau duyệt; Hồ sơ pháp lý quay về tab HĐ & Pháp lý.
- `routes/project_unified.php`: POST `/du-an/{site}/quy-trinh/acceptance/ket-qua-nghiem-thu` (`workflow.kpi.save`), `/xac-nhan` (`workflow.kpi.confirm`), `/tra-bo-sung` (`workflow.kpi.return`); bước khác trả 404.
- `resources/views/projects-unified/partials/workflow-kpi-review.blade.php` (viết lại, form riêng ngoài form dữ liệu bước) và `workflow-word.blade.php` (gắn khối, ô quyết định đấu nối ở tab HĐ & Pháp lý, cảnh báo cho Admin khi KPI chưa xác nhận đủ, hidden `0` cho checkbox sự cố).
- `app/Services/TechnicalKpi/ProjectKpiLinkService.php`: `acceptanceKpiResult()` dùng chung cho bộ chấm và màn hình; link/tài khoản App không còn là "đạt" (cả module cũ).
- `app/Services/TechnicalKpi/KpiStandardEvaluator.php`: KS04/KS05 có công trình nghiệm thu thiếu kết quả đã xác nhận → **thiếu dữ liệu** (giống KS02), ghi lý do và người/giờ xác nhận vào nguồn.
- Test: thêm `tests/Feature/Technical/TechnicalAcceptanceKpiFlowTest.php` (20 ca); đổi 5 ca trong `TechnicalKpiMonthlyInputTest.php`.

### Quyền đã áp dụng

- Nhập/gửi: người đang được giao bước Nghiệm thu. Admin làm thay được, payload ghi `admin_exception`.
- Xác nhận/trả bổ sung: Trưởng phòng KT hoặc Admin (nhóm giao việc hiện có của bước), không phải người đang được giao, người gửi hay người sửa bản đang kiểm tra. Admin tham gia vẫn được (ngoại lệ, có ghi dấu).
- Track duyệt nghiệm thu cuối vẫn là Admin; xác nhận KPI không đổi trạng thái bước, không tạo bản duyệt.
- Sau khi Admin duyệt nghiệm thu: người không phải Admin không sửa khai báo, không trả bổ sung, không tải thêm minh chứng KPI đã xác nhận, không đổi quyết định đấu nối hay % vật tư. Xác nhận vẫn được nếu kỳ lương KPI của người liên quan chưa duyệt; kỳ đã duyệt thì bị từ chối.
- Quyết định đấu nối: giữ quyền hiện có của bước Hồ sơ pháp lý (Admin hoặc người được giao bước đó). Không cấp quyền mới cho Trưởng phòng KT.

### Thay đổi hành vi với dữ liệu cũ (đã đổi kỳ vọng test)

1. `test_evn_app_is_auto_from_monitoring_link` → `test_monitoring_link_alone_does_not_pass_evn_app`: link App chỉ là minh chứng hỗ trợ.
2. `test_workflow_hse_uses_acceptance_checklist…` → `test_legacy_workflow_hse_counts_only_reviewed_full_checklist`: HSE dạng cũ chỉ tính khi có dấu đánh giá và đủ 5 mục; ô chưa tick không phân biệt được "không đạt" với "chưa ghi nhận" nên báo thiếu.
3. `test_workflow_evn_requires_grid_test…` → `test_legacy_workflow_evn_needs_review_app_result_and_grid_decision`: cần dấu đánh giá, kết quả App và quyết định đấu nối.
4. `test_acceptance_step_form_saves_kpi_review` → `test_acceptance_data_form_no_longer_stamps_kpi_review_but_keeps_material_input`.
5. `test_assigned_engineer_cannot_review_own_kpi_at_acceptance` → `test_assigned_engineer_cannot_stamp_or_confirm_own_kpi_at_acceptance`: trường cũ bị bỏ qua thay vì 403; route xác nhận mới trả 403.

Trang KPI có thể hiện "thiếu dữ liệu" ở KS04/KS05 cho các tháng có dữ liệu cũ mà trước đây được chấm một phần. Snapshot lương 08/2026 và phiếu lương đã lưu không bị tính lại.

### Kiểm thử

- Lint 5 file PHP; Blade 2 view biên dịch không lỗi.
- `TechnicalAcceptanceKpiFlowTest` + `TechnicalKpiSourceFlowTest` + `TechnicalKpiMonthlyInputTest` + `MaintenanceCompleteWithoutFullEvidenceTest`: **103/103 đạt, 659 assertions** trên `egosolar_test`. Riêng 3 bộ hồi quy: **83/83, 409 assertions** (sau khi đổi 5 ca ở trên; không dùng lại số baseline 407).
- Nhóm mở rộng 276 ca có chạm workflow/KPI: 8 ca hỏng, đều ở `TechnicalKpiStandardPageTest` do markup trang KPI (`kpxCriteriaTable`, `data-kpx-engineer`) — view này không sửa trong đợt; đã ghi ở STATUS mục "Công việc tiếp theo sau review" (3). Một lượt chạy có 1 lỗi trùng email ngẫu nhiên của factory ở `MaintenanceCompleteWithoutFullEvidenceTest`, chạy lại đạt.
- UI thật (Chrome, 4 vai trò, server cô lập 127.0.0.1:8012 với `DB_DATABASE=egosolar_test CACHE_STORE=array SESSION_DRIVER=cookie MAIL_MAILER=log`): **26/26 bước đạt** — nhập nháp thiếu 1 mục HSE → gửi → POST giả mạo bị 403 → trưởng phòng trả bổ sung HSE → gửi lại → xác nhận 3 nhóm → kỹ thuật gửi duyệt → trưởng phòng không duyệt được → Admin duyệt → trang KPI tháng 10: KS02 15/15, KS04 10/10, KS05 5/5, nguồn có người/giờ xác nhận → sau duyệt kỹ thuật không sửa được. KS03 = 0 vì fixture thiếu hồ sơ khảo sát và KS06 thiếu dữ liệu nên tổng chưa đủ; đây là ca thử, không phải mức hoàn thiện hệ thống. Ảnh, `results.json`, script trong `outputs/claude-kpi-agent/acceptance-20261006/ui/` (gitignore). Fixture đã dọn khỏi `egosolar_test`.
- Đọc lại DB local: lương 08/2026 vẫn 12 dòng `excel_snapshot`, nháp; cấu hình tháng 10 vẫn nháp; mẫu 63 không bị sửa, KPI vẫn đạt 3 tiêu chí theo dữ liệu cũ (màn hình ghi rõ "đánh giá dạng cũ").

### Cách dùng trên local (`http://127.0.0.1:8001`)

1. Admin (hoặc người được giao Hồ sơ pháp lý) mở `/du-an/{id}?step=contract#grid-decision`, chọn **Công trình cần đấu nối điện lực**: Có / Không.
2. Kỹ thuật được giao Nghiệm thu mở `/du-an/{id}?step=acceptance#acceptance-kpi`, tải hồ sơ ở bảng phía trên, chọn kết quả từng mục (Chưa ghi nhận / Đạt / Không đạt), **Lưu nháp** hoặc **Gửi kiểm tra**. Nên gửi trước khi Admin duyệt nghiệm thu; sau duyệt chỉ Admin sửa được.
3. Trưởng phòng KT mở cùng URL: tick nhóm đủ dữ liệu → **Xác nhận nhóm đã chọn**, hoặc ghi lý do → **Trả bổ sung**.
4. Admin duyệt nghiệm thu như hiện tại. Nút **Xem nguồn trên trang KPI** mở `/ky-thuat/kpis?month=YYYY-MM&site_id={id}`; "Xem cách tính" cho thấy công trình, kết quả, người/giờ xác nhận.

### Còn lại / cần quyết định

- Công trình cũ có `requires_grid_connection=false` cần Admin chọn lại Có/Không, nếu không KS05 báo thiếu.
- Quyết định đấu nối hiện chỉ Admin (hoặc người được giao Hồ sơ pháp lý) nhập; có cấp cho Trưởng phòng KT không là quyết định nghiệp vụ, chưa tự cấp.
- Đợt kế tiếp theo PLAN: khảo sát → mã vật tư/đơn vị → xuất/thu hồi kho (D08). D07/D12, cấu hình tháng 10 và tiền lương vẫn mở; 8 test markup trang KPI chờ quyết định với chủ view.
