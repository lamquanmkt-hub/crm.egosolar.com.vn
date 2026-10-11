# Tiến độ dự án KPI tự động

Cập nhật ban đầu: 06/10/2026.

## Đã chuẩn bị

- Skill `.claude/skills/ego-kpi-automation/SKILL.md`.
- Agent `.claude/agents/ego-kpi-implementer.md`.
- PROJECT, PLAN, CHECKLIST, DATA-MAP, DECISIONS và prompt thực thi.
- Claude Code 2.1.289 được tìm thấy trong extension VS Code; đăng nhập Claude.ai đang hoạt động. Không ghi thông tin tài khoản vào hồ sơ này.

## Phiên thực thi

Claude Code đã khởi chạy ngày 06/10/2026 và bắt đầu đọc source KPI kỹ thuật.

- Mã phiên: `e8be89e4-ccbd-4d9a-88b2-2c46953fc3ef`.
- Nhiệm vụ: P0, phần nền tảng P1 cần thiết và một luồng kỹ thuật P2 theo CLAUDE-PROMPT.
- Chạy nền không tương tác; giữ kiểm soát quyền công cụ, không bật bỏ qua quyền. Thao tác cần quyền chưa được phép sẽ bị từ chối và agent phải báo rõ hạn chế.
- Log và metadata phiên nằm trong `outputs/claude-kpi-agent/`, đã gitignore; không đưa log thô vào tài liệu được commit.
- Xem tiến độ: `./scripts/start-kpi-claude.ps1 -Action Status`. Mở lại phiên sau khi nó kết thúc: `./scripts/start-kpi-claude.ps1 -Action Attach`.

Đã thấy Claude đọc `KpiStandardEvaluator.php`, `TechnicalKpiConfig.php`, `technical_kpi_standard.php` và `ProjectKpiLinkService.php`. Điều này xác nhận agent nhận việc; chưa xác nhận triển khai đã hoàn tất.

## Cập nhật yêu cầu ngày 06/10/2026

- Người dùng xác nhận bộ kỹ thuật mới áp dụng từ 10/2026. Chi tiết tại `TECHNICAL-REVISION-20261006.md`.
- Skill và hồ sơ dự án đã cập nhật 6 trọng số 25/15/15/10/5/30; bảo hành phản hồi 5 phút, hoàn thành 24 giờ và không khiếu nại.
- Phiên Claude ban đầu được ngắt tại điểm không có thao tác công cụ còn chờ, để tiếp tục cùng lịch sử với yêu cầu mới. Giữ nguyên các thay đổi đã thực hiện; chưa nghiệm thu phần triển khai.
- Đã tiếp tục cùng mã phiên bằng `CLAUDE-REVISION-PROMPT.md`; trạng thái chạy đã xác minh. Claude đã đọc tài liệu cập nhật kỹ thuật, skill mới và DECISIONS. Đây là xác nhận nhận yêu cầu, chưa phải xác nhận web đã triển khai xong.
- D03 và D12 vẫn là quyết định còn mở; không tự duyệt quy đổi thưởng hoặc cách loại thời gian chờ SLA.

## Phiên 06/10/2026 — kết quả triển khai kỹ thuật (Claude)

Phạm vi: P0 kỹ thuật, KS01 Tiến độ, KS06 Bảo hành, chọn cấu hình theo kỳ. HCNS/kho chưa động tới.

**File sửa/thêm** (không reset thay đổi có sẵn; file dirty từ trước chỉ thêm đoạn nhỏ):
- `app/Models/TechnicalKpiConfig.php` — `getActiveForDate` chọn bản đã duyệt (applied/archived) có hiệu lực ≤ kỳ; bỏ fallback lấy bản hiện hành cho tháng cũ.
- `config/technical_kpi_standard.php` — bản sửa đổi 01/10/2026 (25/15/15/10/5/30), cấu hình đo KS01/KS06.
- `app/Services/TechnicalKpi/KpiStandardEvaluator.php` — bộ tiêu chí theo kỳ, phát hiện cấu hình cũ hơn bộ tiêu chí (`config_stale`), chống trùng toàn kỳ, KS01 trạng thái quá hạn còn mở, KS06 chấm SLA; kết quả kèm `rule`.
- `app/Services/TechnicalKpi/ProjectKpiLinkService.php` — hạn KPI không lấy `recommitted_due_at`; bàn giao phải có người duyệt; giữ việc quá hạn còn mở; minh chứng workflow bắt buộc `approved_by`.
- `app/Http/Controllers/Projects/ProjectWorkflowV2Controller.php` — sự kiện giao việc ghi hạn cũ/mới.
- `app/Http/Controllers/Synced/TechnicalKpi/TechnicalPayrollController.php`, `TechnicalKpiInputController.php`, `app/Services/Hr/Kpi/HrKpiEvaluator.php` — truyền kỳ; không tính tiền khi cấu hình chưa duyệt cho bộ mới; quản lý không mở KPI ngoài phạm vi bằng URL.
- Mới: migration `2026_10_06_120000_add_warranty_sla_fields_to_solar_maintenance_schedules.php`, `app/Http/Controllers/Technical/MaintenanceWarrantySlaController.php`, route `POST du-an/bao-tri-bao-hanh/{schedule}/sla-bao-hanh` (`routes/project_unified.php`), partial `resources/views/technical/maintenance/partials/warranty-sla-kpi.blade.php` (gắn 1 dòng vào trang phiếu), `tests/Feature/Technical/TechnicalKpiSourceFlowTest.php`.
- View `resources/views/synced/kythuat/kpis.blade.php` — dòng bộ quy tắc, cảnh báo chưa tính tiền, kết quả thực tế từ nguồn, danh sách nguồn từng tiêu chí trong hộp "Xem cách tính".
- Test cũ chỉnh theo hành vi mới: `TechnicalKpiMonthlyInputTest` (fixture nghiệm thu có người duyệt), `TechnicalKpiStandardPageTest` (trọng số theo kỳ).

**Dữ liệu/migration**
- Migration mới (chỉ thêm 8 cột nullable, không backfill) đã chạy trên `egosolar_test` và `crm_egosolar_test` sau khi xác nhận target; 133 phiếu giữ nguyên, 0 dòng có mốc mới.
- Tạo cấu hình kỹ thuật **v2 nháp** hiệu lực 01/10/2026 trên DB local (chưa duyệt; v1 giữ `applied`). Cách trả tiền sao từ v1.
- Bảng lương 08/2026: 12 dòng, chưa duyệt, không bị đụng; hash lưu `storage/app/kpi-automation/payroll-2026-08-hash.txt` (chỉ có hash sau phiên, không có hash trước).

**Kiểm thử (PHP 8.3, DB `egosolar_test`)**
- `TechnicalKpiSourceFlowTest`: 18/18 đạt (92 assertion) — cấu hình theo kỳ 08/09/10, trọng số, cấu hình cũ không bỏ 30% bảo hành, đúng/trễ/thiếu hạn/đổi hạn, bàn giao chưa duyệt, quá hạn còn mở, đúng kỳ, chống trùng quý, biên 5 phút/24 giờ, khiếu nại, thiếu mốc, phiếu hủy, form SLA và quyền, quyền xem KPI theo phạm vi, trang có danh sách nguồn.
- `TechnicalKpiMonthlyInputTest` + `TechnicalKpiStandardPageTest`: 32/40 đạt. 8 lỗi còn lại là test markup trang KPI (`kpxCriteriaTable`, `data-kpx-total`, `<dialog>`) — view đã được viết lại trước phiên (baseline cũng lỗi). Chưa sửa vì là thay đổi chưa commit của phiên khác; các test này cũng còn kỳ vọng tổng 85% theo bộ cũ cho 2031.
- `tests/Feature/Hr`: 64/66 đạt; 2 lỗi ở trang Cài đặt lương (redirect `?tab=rules`) thuộc `PayrollController` chưa commit, không do phiên này.
- Không chạy: ảnh chụp UI trên `127.0.0.1:8001` (cần đăng nhập), toàn bộ test suite.

**Còn thiếu/chờ**
- D12 (mốc SLA bảo hành), D07 (việc quá hạn còn mở, hạn hợp đồng hay hạn bước), D03 (110–120%), D05 (tháng không có phiếu bảo hành). Cách đọc mặc định ghi ở DECISIONS.
- Ban lãnh đạo duyệt cấu hình v2 tại Cài đặt KPI kỹ thuật để tháng 10 tính được tiền.
- Dữ liệu thật: local chưa có phân công workflow/kỹ sư phụ trách và chưa có mốc SLA → tháng 09/10 kỹ sư vẫn "chưa đủ dữ liệu" (đúng quy tắc).

## Review và sửa theo 4 mục người dùng — 06/10/2026

Đã kiểm tra phần phiên Claude hoàn thành và sửa tiếp trực tiếp sau khi xác nhận các phiên khác đang idle. Chi tiết tại [REVIEW-TECHNICAL-20261006.md](REVIEW-TECHNICAL-20261006.md).

- Route bảo hành đã có; bổ sung ghi `execution_finished_at` tại nút hoàn tất thật của Synced, khóa mốc đã xác nhận và chặn mở lại trong cả hai dịch vụ.
- Chất lượng cần phản ánh được xác nhận, người/ngày đánh giá; thiếu hoặc chỉ có cờ chưa xác nhận không được thành đạt. Dữ liệu cũ thiếu xác nhận vẫn báo thiếu.
- Form SLA kiểm tra công ty, phân công, đúng thứ tự mốc, hoàn tất trước khi xác nhận; quản lý được ghi giờ phản hồi thực tế có lý do/audit. Không tự xác nhận kết quả của nhóm thực hiện, giữ ngoại lệ Admin sẵn có.
- Sửa lỗi controller/form cài đặt vẫn ép bảo hành về 0: bộ mới lưu đủ 6 tiêu chí, kiểm tra trọng số khi lưu/duyệt. Cấu hình DB local v2 vẫn draft, không được tự duyệt.
- Test 3 bộ liên quan đạt 80/80 (397 assertion), lần chạy cuối lưu riêng trong `outputs/claude-kpi-agent/review-fix-20261006/tests-final.txt`. Một công trình/một phiếu bảo hành giả lập đi qua route thật trên DB test: 100 điểm khi đủ, 70 khi phản hồi trễ 1 giây, chưa có tổng khi thiếu chất lượng. Ba tiêu chí còn lại trong ca này nhập giả lập rõ ràng.
- PHP lint đạt; route/view feature test đạt; local login HTTP 200. Không thêm migration hoặc dữ liệu giả vào local. Snapshot lương 08/2026 có SHA trùng mốc trước review, giữ 12 dòng draft/chưa duyệt.

File sửa trong review: `MaintenanceWarrantySlaController.php`, `TechnicalKpiConfigController.php`, các dịch vụ `Synced/Technical/SolarMaintenanceApprovalService.php`, `Technical/SolarMaintenanceApprovalService.php`, `Technical/SolarMaintenanceWorkflowService.php`, `TechnicalKpi/ProjectKpiLinkService.php`, `TechnicalKpi/KpiStandardEvaluator.php`, partial `warranty-sla-kpi.blade.php`, view `kpi_config.blade.php`, hai test `TechnicalKpiSourceFlowTest.php` / `TechnicalKpiMonthlyInputTest.php`.

## Kiểm thử giao diện KPI kỹ thuật trên trình duyệt — 06/10/2026

Thao tác thật trên Chrome (Playwright, headless) tại `http://127.0.0.1:8001`, web chạy tạm trỏ vào DB `egosolar_test`. Dữ liệu giả lập gắn nhãn `KPI-UITEST` (6 tài khoản `@example.test`, 2 công trình, 6 phiếu sự cố) chỉ trong `egosolar_test`; vai trò `ky_thuat`/`technical_manager` được tạo tạm trong DB test với đúng cờ/quyền của DB local; toàn bộ đã xóa sau kiểm thử. Không ghi vào `crm_egosolar_test`, không đụng bảng lương.

- Phiếu bảo hành: nhập giờ khách báo → ghi phản hồi (kỹ sư "ghi giờ hiện tại"; quản lý "giờ thực tế + lý do") → Hoàn tất đợt → quản lý xác nhận khiếu nại. Giờ hoàn tất được lưu đúng lúc bấm. Xác nhận trước hoàn tất: form ẩn, request trực tiếp bị 422. Kỹ sư tự xác nhận: 403. Phiếu khác công ty: không có form, request 403, không vào KPI.
- KPI tháng 10: trọng số 25/15/15/10/5/30; "Xem cách tính" liệt kê đúng công trình/phiếu. Phản hồi đúng 5 phút + hoàn tất trong 24 giờ + không khiếu nại = 30 điểm; phản hồi trễ 11 phút, hoàn tất sau 25 giờ = 0/1 chưa đạt; thiếu xác nhận/chưa hoàn tất = thiếu dữ liệu, không ra tổng. Chất lượng chưa xác nhận phản ánh = thiếu dữ liệu, tổng "Chưa đủ dữ liệu". Tháng 09: 30/25/15/15/15, bảo hành chờ xác nhận.
- Quyền xem KPI: kỹ sư mở `?user_id=` người khác vẫn chỉ thấy mình; quản lý và Admin thấy danh sách.
- Sửa: `KpiStandardEvaluator::scoreEvidence()` — khi có nghiệm thu nhưng chưa xác nhận phản ánh, lý do thiếu không còn ghi sai "Chưa có dự án được nghiệm thu trong kỳ". Kiểm tra lại trên giao diện; 3 bộ test liên quan 80/80 đạt.
- Cần quyết định: khảo sát thiếu hồ sơ trên hệ thống đang chấm "Chưa đạt" (0 điểm), không phải "thiếu dữ liệu" — ca kiểm thử ra tổng 85% vì vậy. Biên 24 giờ đúng từng giây không thao tác được trên giao diện (giờ hoàn tất = lúc bấm); dựa vào feature test.
- Ảnh và kết quả: `outputs/claude-kpi-agent/ui-test-20261006/` (gitignore).

## Công việc tiếp theo sau review

1. Chốt D12/D07; nhập nguồn thật và review cấu hình v2 theo quy trình. Không duyệt tiền khi quy tắc còn mở.
2. P2-02/03/04: chất lượng (phản ánh có xác nhận), vật tư theo mã + đơn vị (D08), HSE/EVN có người kiểm.
3. Quyết định với chủ view KPI mới: cập nhật 8 test markup cũ hoặc khôi phục hook `data-kpx-*`.
4. Sau đó P3 HCNS, P4 kho.

## Mẫu cập nhật sau mỗi phần

- Thời điểm và phần việc/mã checklist.
- File thay đổi và hành vi trước/sau.
- Kiểm tra đã chạy, kết quả và phần không kiểm tra được.
- Tác động đến dữ liệu cũ, trạng thái snapshot và phạm vi quyền.
- Quyết định/dữ liệu còn thiếu, mã DECISIONS liên quan.
- Phần tiếp theo có thể thực hiện.

Chưa có mục triển khai nào được đánh dấu xong trong lần tạo hồ sơ.

## Mẫu thao tác local theo yêu cầu người dùng — 06/10/2026

- Đã tạo đúng 2 tài khoản TEST với các vai trò có sẵn `technical_manager` và `ky_thuat`, 1 công trình và 1 phiếu sự cố/bảo hành trên `crm_egosolar_test`, công ty 2. Manifest, tài khoản và hướng dẫn nằm trong thư mục private được gitignore `outputs/kpi-demo-20261006/`.
- Tất cả 7 bước workflow và các mục hồ sơ bảo hành có ghi chú trạng thái lúc tạo. Hai view công trình và bảo hành thêm ô trạng thái KPI đọc từ bộ chấm hiện tại; chỉ hiển thị cho mã công trình TEST của mẫu, không thay bộ quy tắc hay điểm nhân viên thật.
- Điểm thành phần mẫu: 25/15/15/10/5 = 70; KS06 thiếu hoàn tất/xác nhận khiếu nại nên tổng KPI vẫn null. Hồ sơ/ảnh, mốc và xác nhận công trình là giả lập. Sai lệch vật tư 1% là nhập tại nghiệm thu, chưa kiểm thử đối chiếu kho theo mã/đơn vị.
- Đã đăng nhập thực tế cả hai vai trò trên trình duyệt local; xem KPI của kỹ thuật, các bước công trình và phiếu bảo hành không lỗi 500. Kiểm tra riêng bước khảo sát sau khi snapshot trong vòng lặp chưa bắt kịp trang mới: ghi chú hiện đúng. PHP kiểm tra mật khẩu/vai trò/quyền xem và nguồn chỉ có 1 công trình; Blade đã biên dịch và render trên web.
- Hash dòng bảng lương Excel 08/2026 giữ nguyên. Không tạo hồ sơ lương, không duyệt cấu hình tháng 10, không duyệt trả lương; D12 vẫn chờ quản lý chốt. Để phiếu chưa hoàn tất/xác nhận cho người dùng thử các bước tiếp theo; chưa coi đây là nghiệm thu toàn bộ nghiệp vụ.

## Hoàn tất thử luồng bảo hành mẫu trên UI — 06/10/2026

- Tiếp tục mẫu TEST local: đăng nhập kỹ thuật, nhập kết quả và bấm Hoàn tất đợt; xác minh cả `execution_finished_at` và `completed_at` được lưu lúc thao tác. Khi chưa có xác nhận khiếu nại, tổng vẫn thiếu; kỹ thuật không có nút tự xác nhận. Đăng nhập trưởng phòng và xác nhận không khiếu nại trên UI: KS06 tăng lên 30/30, tổng đạt 100%, khớp 25 + 15 + 15 + 10 + 5 + 30. Phiếu mẫu hiện đã hoàn tất/xác nhận, thay trạng thái chờ thử ở mục trên.
- Sửa `Synced/Technical/SolarMaintenanceDetailController.php`: phiếu dùng checklist cũ đã hoàn tất và đủ file hiển thị tiến độ/mục hoàn thành đúng thay vì 0%. Chỉ thay cách đọc hiển thị; không ghi lại cờ checklist lịch sử. Phiếu đang mở hoặc thiếu minh chứng không bị coi là hoàn thành. UI mẫu xác minh 2/2 và 100%.
- Thêm 3 ca hồi quy trong `MaintenanceCompleteWithoutFullEvidenceTest.php`. Chạy `TechnicalKpiSourceFlowTest`, `TechnicalKpiMonthlyInputTest`, `MaintenanceCompleteWithoutFullEvidenceTest`: **83/83 đạt, 407 assertions**, DB riêng `egosolar_test`. Lint file sửa đạt. Lượt thử bị mất kết nối DB đã dừng và chạy lại sau khi khôi phục dịch vụ local; kết quả trên là lượt chạy lại đầy đủ.
- Đối chiếu trang KPI thật: 6 tiêu chí đạt, tổng 100,0%; tiền vẫn chưa tính vì thiếu lương thỏa thuận/cấu hình tháng 10 chưa duyệt. Hash bảng lương 08/2026 giữ nguyên. Không bổ sung migration, không duyệt tiền hoặc chính sách D12.
- Bằng chứng và hướng dẫn cập nhật trong `outputs/kpi-demo-20261006/` (gitignore), ảnh `bao-hanh-100.png`. Nghiệm thu công trình trước đó là dữ liệu giả lập được tạo sẵn; lần này chỉ thao tác UI luồng hoàn tất bảo hành → xác nhận → cộng điểm. Sai lệch vật tư 1% vẫn là nhập tay, chưa nghiệm thu luồng kho theo mã/đơn vị; dự án tự động hóa đầy đủ còn mở.

## Chuẩn bị giao Claude Code luồng Nghiệm thu — 06/10/2026

- Người dùng giao viết skill/project/plan/prompt cho phần nhập liệu còn thiếu. Đã tạo `.claude/skills/ego-technical-acceptance/SKILL.md` và bộ hồ sơ `docs/projects/kpi-automation/technical-acceptance/` (PROJECT, PLAN A1–A5, CHECKLIST, CLAUDE-PROMPT).
- Đã đọc form Nghiệm thu, saveData/submit/approve, cấu hình workflow và adapter thật: hiện kỹ thuật chỉ xem khối KPI; chưa tách nháp/gửi/xác nhận; HSE và EVN/App cần xác nhận rõ. Track duyệt nghiệm thu cuối đang thuộc Admin; đợt mới giữ track đó, trưởng phòng xác nhận nguồn KPI riêng.
- Phạm vi giao: KS02/KS04/KS05 tại Nghiệm thu, nguồn/minh chứng/phiên bản xác nhận, quyền, ghi chú và kiểm thử UI. Vật tư theo mã/đơn vị, D07/D08/D12 và tiền lương nằm ngoài đợt này. Các file dự án/skill nền đã liên kết đợt mới để không chạy lại prompt phiên đầu.
- Đây là chuẩn bị hồ sơ thực thi, **chưa sửa source hoặc chạy Claude Code đợt mới**, chưa đánh dấu checklist triển khai. Claude cập nhật kết quả thực tế sau khi chạy.

## Triển khai luồng Nghiệm thu A1–A5 — 06/10/2026 (Claude)

- Đã làm trên source local, chưa commit/deploy. Chi tiết thiết kế, file, quyền và cách dùng: `technical-acceptance/PROJECT.md` mục Kết quả; checklist A1-01 → A5-04 đã đánh dấu kèm bằng chứng.
- Luồng chạy: kỹ thuật được giao nhập kết quả 3 nhóm (phản ánh khách hàng, 5 mục HSE, EVN/App) tại bước Nghiệm thu → Lưu nháp / Gửi kiểm tra → Trưởng phòng KT xác nhận từng nhóm hoặc trả bổ sung có lý do → Admin duyệt nghiệm thu theo track hiện có → KS02/KS04/KS05 chỉ đọc kết quả đã xác nhận còn hiệu lực. Sửa nội dung, thêm/thay file minh chứng hoặc bị "Trả lại" làm xác nhận cũ hết hiệu lực; lịch sử giữ trong `project_workflow_events`.
- Sửa lỗi cũ: lưu dữ liệu bất kỳ bước nào từng ép `requires_grid_connection`/`technical_feasibility_confirmed`… thành false. Quyết định cần đấu nối nay có ba trạng thái, ghi ở tab HĐ & Pháp lý; giá trị false cũ coi là chưa xác định.
- Đổi hành vi theo PLAN: link/tài khoản App không còn tính là đạt; KS04/KS05 có công trình thiếu kết quả đã xác nhận → thiếu dữ liệu (không chấm phần còn lại). Đã đổi 5 ca test cũ và ghi lý do.
- Kiểm thử: bộ mới + 3 bộ hồi quy **103/103, 659 assertions**; riêng 3 bộ hồi quy **83/83, 409 assertions** (số mới, không phải baseline 407). Nhóm mở rộng 276 ca: 8 ca markup `TechnicalKpiStandardPageTest` vẫn hỏng như mục "Công việc tiếp theo" (3). UI thật trên server cô lập 127.0.0.1:8012 + `egosolar_test`, 4 vai trò: **26/26 bước đạt**; ảnh trong `outputs/claude-kpi-agent/acceptance-20261006/ui/` (gitignore). Fixture đã dọn.
- Sự cố khi thử UI: lượt đầu server thử dùng chung cache file với web local, đọc cache quyền của local (403) và một lần chạy CLI đã ghi cache nhận diện thương hiệu từ DB test. Đã xóa đúng mục cache đó (local tự dựng lại từ DB của nó) và chạy lại với `CACHE_STORE=array SESSION_DRIVER=cookie MAIL_MAILER=log`; lượt sau không ghi file cache dùng chung nào. Khi dựng server thử sau này cần dùng các biến này.
- Dữ liệu được giữ: lương 08/2026 vẫn 12 dòng `excel_snapshot`, nháp; cấu hình tháng 10 vẫn nháp; mẫu 63 không bị sửa, KPI vẫn đạt 3 tiêu chí theo đánh giá dạng cũ. Không duyệt lương, không deploy, không gửi thông báo thật.
- Mục "Công việc tiếp theo" (2): phần chất lượng/HSE/EVN có người kiểm tại Nghiệm thu đã làm; vật tư theo mã + đơn vị (D08) vẫn mở. Cần quyết định: có cấp quyền ghi quyết định đấu nối cho Trưởng phòng KT không (hiện giữ quyền Admin/người được giao Hồ sơ pháp lý).

## Review độc lập sau A1–A5 — 06/10/2026

- Đã đọc source quyền/trạng thái/adapter và chạy lại 4 bộ: **103 passed, 659 assertions, 36.63s**, khớp báo cáo Claude. Không chạy lại UI hoặc bộ rộng 276 ca.
- Bổ sung 3 ca review với fixture giả, transaction rollback trên `egosolar_test`: **3 failures, 19 assertions**. R1: lưu nháp HSE fail rồi pass lại làm xác nhận version 1 tự có hiệu lực dù không gửi/kiểm tra lại. R2: kỳ KPI đã approved vẫn bị Admin sửa khai báo và thêm minh chứng, làm nguồn thay đổi; guard khóa kỳ chỉ có ở xác nhận.
- Đã mở lại A3-02/A3-03, tạo `technical-acceptance/REVIEW-20261006.md` và `CLAUDE-REVIEW-FIXES.md`. Chưa sửa source ứng dụng trong review này; ưu tiên sửa hai lỗi trước đợt vật tư D08.
- Test/log tái hiện nằm trong `outputs/claude-kpi-agent/acceptance-review-20261006/` được gitignore. Không sửa công trình 63, kỳ/cấu hình lương hoặc cấp thêm quyền đấu nối. Kết quả chứng minh nguồn sống bị đổi trong fixture test, không khẳng định tiền snapshot thật đã bị tính lại.

## Link hồ sơ nguồn tại trang KPI — 07/10/2026

- Theo yêu cầu người dùng, tên công trình trong cột Nguồn của hộp Xem cách tính đã thành link mở hồ sơ ở bước tương ứng; nguồn bảo hành mở đúng phiếu. Bộ chấm bổ sung `schedule_id` cho minh chứng bảo hành để tạo link theo ID. Dòng nhập tay không có ID vẫn là chữ, không suy đoán hồ sơ.
- File sửa: `resources/views/synced/kythuat/kpis.blade.php`, `app/Services/TechnicalKpi/KpiStandardEvaluator.php`, bổ sung kiểm tra URL trong `tests/Feature/Technical/TechnicalKpiSourceFlowTest.php`. Không thay quy tắc/điểm, dữ liệu hoặc quyền.
- Kiểm thử: `TechnicalKpiSourceFlowTest` **29 passed, 177 assertions** trên DB test riêng; PHP lint đạt. UI local xác minh link công trình 63 mở đúng bước Nghiệm thu, link bảo hành trỏ đúng phiếu 148 và trang đích hiển thị được. Bằng chứng private tại `outputs/kpi-source-links-20261007/`. Chưa commit/deploy; các lỗi review A1–A5 vẫn theo mục review bên trên.

## KS01 — 25 điểm theo việc được giao và hạn nộp 24 giờ — 07/10/2026

- Người dùng chọn toàn bộ 25 điểm Tiến độ chia đều cho việc trưởng phòng giao; hạn 24 giờ kể từ lúc giao. Quyết định D13 ghi trong DECISIONS. Từ kỳ 10/2026, KS01 đọc `technical_plan_items` + báo cáo gắn đúng việc thay nguồn bàn giao; trước 10/2026 giữ bộ cũ.
- Nguồn mới chỉ đọc, không migration/backfill: giờ giao → hạn +24 giờ → lần gửi đầu từ lịch sử server (fallback mốc nộp của báo cáo cũ). Mỗi việc chỉ một lần; lọc công ty/nhân viên/công trình. Nháp không nhận điểm; muộn/chưa nộp quá hạn mất phần điểm; chưa đến hạn không bị mất điểm; hủy loại trừ; thiếu/đảo mốc báo thiếu. Kỳ theo tháng đến hạn, tỷ lệ trên việc đã đến hạn, chia đều trong phạm vi đang xem.
- Đã nối vào KPI cá nhân/toàn phòng, xu hướng theo bộ quy tắc của từng tháng, tổng hợp lương nhân sự, gợi ý nhập liệu và ghi chú công trình TEST. Tiến độ mới không nhận số thay thế nhập tay; cột nhập tay chuyển sang thông tin tự động và link Xem việc và điểm. Nguồn KPI hiện giờ giao/hạn/lần nộp, điểm nhận/mất, link báo cáo hoặc đúng tuần/người được giao.
- Kiểm thử mới `TechnicalAssignedWorkKpiTest`: **12/12 đạt, 44 assertions**. Năm bộ hồi quy nguồn KPI, nhập tháng, liên kết báo cáo, kế hoạch tuần, bảng giao việc: **95 ca đạt**. Lượt gộp 107 ca có một lỗi tên hàm assertion trong ca mới (không phải lỗi ứng dụng); đã sửa và chạy lại toàn bộ 12 ca mới đạt. PHP lint 4 file lõi đạt.
- UI local 8001 mở KPI nhân viên mẫu 98, tiêu chí mới/công thức 24 giờ hiện đúng, không 500. Mẫu chưa có việc trưởng phòng giao nên KS01 báo thiếu, tổng không còn 100%; năm tiêu chí còn lại vẫn như trước. Chưa tạo công việc/báo cáo mẫu mới trên DB local. Ảnh công thức tại `outputs/kpi-source-links-20261007/tien-do-24h.png` (private).
- Không cập nhật/duyệt cấu hình tiền hoặc bảng lương, không đổi SLA bảo hành, không commit/deploy. Các lỗi review A1–A5 chưa nằm trong phạm vi đợt này. Hồ sơ triển khai: `assigned-work/PROJECT.md`.

## Chuẩn bị giao Claude checklist và dữ liệu mẫu — 07/10/2026

- Người dùng yêu cầu chi tiết từng người hiện checklist hoàn thành/chưa hoàn thành và thêm dữ liệu để xem. Đã viết skill `.claude/skills/ego-kpi-checklist-demo/SKILL.md` cùng PROJECT/PLAN/CHECKLIST/CLAUDE-PROMPT tại `kpi-checklist-demo/`.
- Phạm vi giao: checklist nguồn trực tiếp dưới 6 tiêu chí, link và trạng thái/điểm; thêm 5 việc TEST local cho mẫu đã xác minh để minh họa 24 giờ. Không đổi bộ chấm, nguồn công trình/bảo hành đã xác nhận, lương hoặc cấu hình.
- Đây là hồ sơ giao việc; đợt này chưa sửa UI, chưa seed mẫu hoặc chạy Claude. Checklist triển khai còn mở. R1/R2 và các phần nghiệp vụ ngoài phạm vi vẫn theo review/quyết định riêng.

## Checklist KPI từng người + 5 việc TEST — 07/10/2026 (Claude)

- Chi tiết KPI từng người có khối "Checklist nguồn theo tiêu chí" ngay dưới bảng 6 tiêu chí: mỗi dòng có link hồ sơ, mốc, trạng thái thực hiện và phần điểm nhận/mất/chưa tính kèm lý do; điện thoại xếp một cột. Đọc output bộ chấm (`KpiChecklistPresenter`), không đổi công thức/trọng số. Chi tiết, bảng mốc và kết quả: `kpi-checklist-demo/PROJECT.md` mục Kết quả; CHECKLIST C1–C5 đã đánh dấu.
- Dữ liệu TEST local qua lệnh chủ động `php artisan kpi:demo-checklist --apply` (guard local/DB/công ty/định danh mẫu, chạy lại không trùng): 5 việc trưởng phòng 97 giao kỹ thuật 98 tại công trình 63 — 2 đúng hạn, 1 muộn 2 giờ, 1 quá hạn chưa nộp, 1 nháp còn hạn (hết hạn 08/10/2026 09:00).
- Tại 07/10 10:19: KS01 2/4 × 25 = 12,5/25; tổng 87,5% (năm tiêu chí còn lại 75 từ mẫu cũ), khớp tính tay và trang. Sau hạn việc 05 nếu chưa nộp: 10/25, tổng 85%.
- Kiểm thử: `TechnicalKpiChecklistTest` 8/8; cùng 5 bộ KPI/Nghiệm thu/bảo hành 123/123 (801 assertions) trên `egosolar_test`. Nhóm mở rộng 335 ca còn 9 lỗi có từ trước (8 markup trang KPI, 1 trang báo cáo); chạy gộp cần `memory_limit=1G`. Trình duyệt thật 8001, 2 tài khoản mẫu: 34/34 bước, link nguồn HTTP 200. Bằng chứng private `outputs/kpi-checklist-demo-20261007/` (đã thêm `.gitignore`).
- Giữ nguyên hash công trình 63, phiếu 148, lương 08/2026 và cấu hình KPI. Chưa commit/deploy, chưa duyệt lương. R1/R2 đợt Nghiệm thu vẫn mở. Lưu ý: `outputs/kpi-demo-20261006/` và `outputs/kpi-source-links-20261007/` chưa có trong `.gitignore` dù được ghi là private (thư mục đầu có mật khẩu tài khoản mẫu).

## Đối chiếu độc lập checklist — 07/10/2026 (Codex)

- Đọc source checklist, command tạo mẫu và kiểm thử. Chạy lại `TechnicalKpiChecklistTest` + `TechnicalAssignedWorkKpiTest`: **20/20 đạt, 140 assertions** trên DB test riêng. Không chạy lại bộ 335 ca hoặc trình duyệt trong lượt đối chiếu này; đã xem ảnh Tiến độ và log UI do Claude cung cấp.
- Đọc lại DB local bằng script snapshot chỉ đọc, cache/session cô lập: mẫu 98 có 5 việc, 4 báo cáo; KS01 2/4 =12,5/25, tổng 87,5%. So với baseline trước seed, hash công trình/bước/hồ sơ, số sự kiện, phiếu 148, cấu hình KPI và lương 08/2026 đều giữ nguyên. Snapshot tại `outputs/kpi-checklist-demo-20261007/review-codex.json`.
- Đã thêm hai thư mục `outputs/kpi-demo-20261006/` và `outputs/kpi-source-links-20261007/` vào `.gitignore`, kiểm tra Git loại các file bằng chứng/thông tin đăng nhập; không có file trong hai thư mục được theo dõi bởi Git tại thời điểm kiểm tra. Không sửa dữ liệu mẫu/source ứng dụng, không commit/deploy. R1/R2 vẫn cần xử lý trước khi dùng nguồn nghiệm thu để chốt KPI thật.

## Đồng nhất giao diện 3 trang Bảng lương — 07/10/2026

- Theo ảnh mẫu trang KPI kỹ thuật: Bảng lương tháng, KPI phòng ban và Cài đặt lương dùng nội dung toàn chiều rộng, breadcrumb/tiêu đề gọn, cards/bảng/filter/nút cùng kiểu; bỏ hero và giới hạn 1480px. CSS scope `.pr-workspace` không đổi kiểu Chấm công/Phiếu lương. Không đổi công thức, form action hoặc dữ liệu local.
- Đã render 5 view/tab bằng controller thật với fixture DB test trong transaction rollback; kiểm tra browser ảnh desktop, màn hình rộng, mobile và mở form/tab. Do session browser local là kỹ thuật (403 trang HR), UI được kiểm trên HTML view thật ở server review chỉ GET, không cấp quyền hoặc sửa account. Server review đã dừng. Bằng chứng private `outputs/payroll-ui-20261007/REVIEW.md` và ảnh trong cùng thư mục, đã gitignore.
- Hai bộ lương: 14/16 đạt; hai failure là kỳ vọng redirect URL hồ sơ/cài đặt không khớp controller hiện có, chưa sửa trong phạm vi giao diện. Chưa commit/deploy.

## Cải thiện UX trang KPI phòng ban — 07/10/2026

- Theo phản hồi “chưa tối ưu UX/UI”: thay dropdown bằng thẻ phòng ban, gom tháng/mode vào một thanh. Trạng thái trống có CTA Gán bộ KPI cho nhân viên, hướng dẫn gán theo tháng hiệu lực, xem nhanh tiêu chí và đường mở cài đặt; có nhân viên hiện số đủ/thiếu dữ liệu. Giữ role và link phù hợp, không đổi công thức/dữ liệu/quyền.
- Render view từ controller với fixture DB test rollback, browser kiểm CTA/cài đặt/trạng thái có người và desktop/mobile; mobile phòng ban cuộn ngang, không tràn toàn trang. Bằng chứng private `outputs/payroll-ui-20261007/kpi-ux-desktop.png`, `kpi-ux-mobile.png`, REVIEW.md. Đã dừng server review GET-only.
- Kiểm tra quyền lương `PayrollAccessTest`: 7/7 đạt, 30 assertions. Hai lỗi kỳ vọng redirect của đợt trước vẫn theo ghi chú cũ, không tự đóng. Chưa commit/deploy.

## Hiển thị nguồn nghiệm thu cũ và bản khai báo mới — 07/10/2026

- Theo phản hồi form “Chưa ghi nhận” nhưng KPI 87,5%: hiển thị kết quả từ hồ sơ cũ có dấu người/giờ đánh giá, trạng thái từng nhóm và minh chứng hiện có bằng cùng nguồn bộ chấm. Nhóm thiếu xác nhận vẫn báo thiếu, không tự thành đạt.
- Với Admin/người được phép nhập, phần nhập lại theo luồng mới nằm trong khối thu gọn riêng; cảnh báo đúng hành vi hiện có: lưu bản mới chuyển nguồn sang bản mới, nhóm chưa xác nhận báo thiếu. Không tự điền kết quả cũ, không tạo xác nhận, không thay công thức hoặc dữ liệu. Người không được sửa chỉ xem nguồn đang dùng.
- Ghi chú KPI công trình mẫu hiển thị số thập phân thay vì làm tròn 87,5% thành 88%, để khớp trang KPI.
- 4 ca kiểm tra mới + Nghiệm thu/KPI checklist: **32/32 đạt, 372 assertions** trên DB test riêng. Kiểm tra trang local công trình mẫu 63 bằng session trưởng phòng: người/giờ, chất lượng/HSE/EVN đúng nguồn; tổng KPI đọc lại vẫn 87,5%. Bằng chứng private trong `outputs/payroll-ui-20261007/`, snapshot `outputs/kpi-checklist-demo-20261007/acceptance-display-after.json`.
- Không commit/deploy/duyệt lương. R1/R2 và thay đổi công thức tiền KPI vẫn ngoài phạm vi đợt hiển thị này.

## Chuẩn bị bàn giao Claude: tiền KPI và khóa xác nhận — 07/10/2026

- Đã tạo `.claude/skills/ego-kpi-salary-closeout/SKILL.md` và `kpi-salary-closeout/{PROJECT,PLAN,CHECKLIST,CLAUDE-PROMPT}.md`; cập nhật skill nền trỏ đợt hiện hành và yêu cầu D14. Phạm vi F0–F5: tiền theo tỷ lệ qua cấu hình có phiên bản, R1/R2, luồng công trình mới và đối chiếu kỹ thuật/HR.
- Tài liệu phân biệt 3.937.500đ trước rounding với 3.938.000đ nếu rounding 1.000; giữ lịch sử và chỉ tạo bản nháp mới, giao thao tác duyệt cuối cho người dùng. Ghi đúng hiện trạng snapshot gần nhất: v2 applied, không còn nháp. Chưa triển khai hay chạy Claude trong lượt tạo tài liệu.
- Kiểm tra thủ công frontmatter, tất cả link trong bộ mới và scaffold: đạt. Validator bundled chưa chạy được do Python thiếu PyYAML; không cài package hoặc tuyên bố validator pass. Không sửa source ứng dụng/DB trong lượt này.

## Tiền KPI theo tỷ lệ, sửa R1/R2, công trình mới — 07/10/2026 (Claude)

- **Đã triển khai, chưa áp dụng cấu hình mới.** Chi tiết: `kpi-salary-closeout/PROJECT.md` mục Kết quả; CHECKLIST F0–F5 có bằng chứng. Chưa commit/deploy, chưa duyệt cấu hình, nghiệm thu hay lương local.
- F1: cấu hình có `payout_mode` (tỷ lệ/bậc; bản không ghi chế độ giữ bậc). `payoutRate()` dùng chung cho trang KPI, xem trước và HR. Mẫu 15 tr, 87,5%: v3 nháp 3.937.500 → 3.938.000 đ, tổng 14.437.500 → 14.438.000 đ; v2 đang áp dụng 3.600.000 đ. Bản nháp **v3 (#3)** tạo bằng `php artisan kpi:payout-draft --apply`, kế thừa v2, chỉ khác chế độ trả.
- R1 sửa: xác nhận của nhóm bị đổi chuyển hẳn sang lịch sử, lượt gửi của nhóm bị hủy; đổi về không sống lại. R2 sửa: khóa kỳ (phiếu KPI kỹ thuật hoặc kỳ HR đã duyệt dùng KPI kỹ thuật) chặn mọi người kể cả Admin trước khi ghi ở 12 đường sửa nguồn. Ba ca review gốc: 3 fail → 3/3 đạt; A3-02/A3-03 đã cập nhật, chờ review độc lập.
- F4: công trình local **#64 `TEST-KPI-LUONG-20261007-01`** (lệnh `kpi:demo-salary-site --apply`) đã chạy nháp → gửi → trả bổ sung → gửi lại → xác nhận → gửi duyệt; **chờ Admin duyệt nghiệm thu**. Chuỗi có Admin duyệt và đổi cấu hình được trình diễn trên server cô lập 8012 + `egosolar_test` (đã dọn).
- Kiểm thử: `TechnicalKpiSalaryCloseoutTest` 12/12; 10 bộ liên quan 164 ca, 2 lỗi `PayrollFlowTest` đã biết. Nhóm mở rộng 370 ca: 11 lỗi đã biết. Trình duyệt: local 15/15, cô lập 10/10, mobile 390 px. HR chưa thử trên trình duyệt.
- Giữ nguyên (so `before.json`/`after.json`): v1/v2, kỳ HR 08/2026 12 dòng nháp, công trình 63, phiếu 148, 5 việc mẫu, lương/KPI 98. Thêm mới: v3 nháp, công trình #64 và 15 file giả của #64. Một lần test đã ghi 15 file giả của site chỉ có trong DB test ra storage dùng chung; đã xóa đúng thư mục và sửa lệnh dùng disk `public`.
- Bằng chứng private: `outputs/claude-kpi-agent/salary-closeout-20261007/`.

## Chuẩn bị bàn giao Claude: hướng dẫn sử dụng Công trình có ảnh — 07/10/2026

- Đã tạo `.claude/skills/ego-project-usage-guide/SKILL.md` và `project-usage-guide/{PROJECT,PLAN,CLAUDE-PROMPT}.md`. Checklist G0–G4 nằm trong PLAN, tất cả chưa thực hiện. Skill nền đã trỏ phạm vi mới, không tự tiếp tục đợt tiền lương.
- Yêu cầu: nút Hướng dẫn sử dụng ở góc phải đầu nội dung trang Công trình, mở trang riêng cùng tab có ảnh thật/phóng to, quy trình theo ba vai trò và quay lại đúng site/bước. Trọng tâm Nghiệm thu, giải thích % tiến độ khác KPI, nháp/gửi/xác nhận/duyệt là các trạng thái khác nhau.
- Đã đọc hệ thống hướng dẫn kỹ thuật hiện có và phát hiện bài KPI còn 5 tiêu chí/trọng số cũ; tài liệu yêu cầu không sao chép, không dẫn người dùng sang nội dung mâu thuẫn. Route kỹ thuật giới hạn vai trò; bài từ Công trình phải dùng quyền phù hợp, không tạo nút dẫn 403 hoặc mở rộng quyền nghiệp vụ.
- Ảnh public phải từ dữ liệu TEST; tạo trạng thái/chụp ảnh trên DB test cô lập. Local chỉ đọc, không tự duyệt công trình/cấu hình/lương hay sửa demo. Đợt tạo hồ sơ này chưa triển khai nút/trang, chưa khởi chạy Claude, không sửa code ứng dụng/DB.
- Đã kiểm tra thủ công frontmatter, scaffold và mọi liên kết của bộ tài liệu mới: đạt. `quick_validate.py` chưa chạy được vì runtime thiếu PyYAML; không cài package hoặc ghi validator đã đạt.

## Hướng dẫn sử dụng Công trình có ảnh — 07/10/2026 (Claude)

- **Đã triển khai G0–G4 trên source local, chưa commit/deploy.** Chi tiết: `project-usage-guide/PROJECT.md` mục Kết quả; PLAN có bằng chứng từng mã.
- Nút **Hướng dẫn sử dụng** phía trên khối tên công trình → `/du-an/{site}/huong-dan` (cùng quyền xem công trình, chỉ đọc), quay lại đúng bước và vị trí. Trang 9 mục theo vai trò Kỹ thuật/Trưởng phòng/Admin, 8 ảnh thật dữ liệu TEST có khoanh số, bấm phóng to.
- Kiểm thử: `ProjectUsageGuideTest` 10/10; kèm Acceptance + SalaryCloseout 42/42; `TechnicalGuidePagesTest` 17/17. Trình duyệt 1366/390 px: server cô lập 8012 ba vai trò 81/81; local công trình 64 hai tài khoản mẫu 54/54.
- Local không đổi so baseline (chỉ `last_seen_at` của tài khoản xem và file session). `egosolar_test` về đúng 514 dòng; fixture/server 8012 đã dọn. Bằng chứng private: `outputs/claude-kpi-agent/project-usage-guide-20261007/`.

## Chuẩn bị phần phiếu lương, HR và vật tư D08 — 07/10/2026

- Người dùng yêu cầu hoàn thiện phiếu lương cũ, rounding kỹ thuật/HR và thử HR trên trình duyệt, đối chiếu D08/khóa kỳ; sau kiểm tra giao người có quyền duyệt v3. Đã tạo `.claude/skills/ego-technical-payroll-material/SKILL.md` và `technical-payroll-material/{PROJECT,PLAN,CLAUDE-PROMPT}.md`, cập nhật skill nền trỏ phạm vi mới. Chưa sửa code/DB/duyệt cấu hình hoặc khởi chạy Claude trong lượt chuẩn bị này.
- Đọc source xác nhận: phiếu lương cũ còn bộ tính riêng; HR chưa dùng round_rule cấu hình kỹ thuật; D08 workflow cộng requested_qty giữa các mã/đơn vị và dùng đề xuất thay xuất thực tế. Hồ sơ thực hiện yêu cầu chung bộ chấm/rounding, snapshot lịch sử, dữ liệu Kho đã xác nhận, bảng từng mã/đơn vị và khóa mọi đường ảnh hưởng nguồn trước ghi.
- Đang hỏi cách tổng hợp D08 (từng mã/đơn vị đều `<3%` hay tổng theo giá trị tiền) và cách giao thực hiện (Claude hoặc sửa trực tiếp). Không có câu trả lời thì không tự coi chính sách D08 đã chốt; phần lương, dữ liệu/khóa vật tư độc lập vẫn có thể làm.
- Frontmatter, link và scaffold của bộ mới đã kiểm thủ công: đạt. Validator bundled chưa dùng được vì thiếu PyYAML như lượt trước; không cài dependency chỉ để tạo tài liệu. Checklist S0–S4 chưa đánh dấu triển khai.

## Cập nhật D15: không làm tròn tiền — 07/10/2026

- Người dùng xác nhận “ko cần làm tròn”. Đã cập nhật DECISIONS, skill, PROJECT, PLAN và prompt của đợt technical-payroll-material: giữ nguyên tiền tính được; chuẩn bị `round_rule=none` ở bản nháp, không tự duyệt áp dụng. Yêu cầu này thay phần giữ làm tròn 1.000đ trong hồ sơ giao trước.
- Ca đối chiếu chế độ tỷ lệ: quỹ 4.500.000 × 87,5% = 3.937.500đ; cộng cố định 10.500.000 = 14.437.500đ; nửa công tiền KPI 1.968.750đ. Nhánh none hiện còn rơi vào `round($amount)` trong source; Claude cần sửa tính, lưu và hiển thị theo D15, bảo toàn cấu hình/snapshot lịch sử và thuế/BH.
- Lượt cập nhật này chỉ sửa tài liệu, không sửa source ứng dụng/DB, không chạy Claude hay duyệt cấu hình. Web vẫn dùng trạng thái đã đối chiếu trước đó. D08 vẫn chờ quyết định tổng hợp; câu trả lời về làm tròn không chốt D08.

## D16: manager nhập tay vật tư tạm thời — 08/10/2026

- Theo xác nhận của người dùng, đã triển khai ngay trong Công trình → Nghiệm thu: bảng đối chiếu, % sai lệch, cách tính, Lưu nháp và Xác nhận kết quả vật tư. Manager/Admin nhập và xác nhận, kỹ thuật chỉ xem. Người/giờ do server ghi; ô trống/nháp không thành đạt. Hồ sơ khảo sát đầy đủ và Admin duyệt nghiệm thu vẫn bắt buộc. `<3%` đạt, đúng 3% fail, dùng số gốc trước hiển thị.
- Bộ chấm và checklist đọc cùng kết quả xác nhận, có nguồn Manager nhập tay và link về `acceptance#material-kpi`. Sửa tỷ lệ/ghi chú hoặc upload bảng quyết toán làm xác nhận hết hiệu lực vĩnh viễn, giữ lịch sử; bản form cũ không ghi đè bản mới. Mọi đường mới và upload/form cũ dùng khóa kỳ kỹ thuật/HR live hiện có, chặn cả Admin trước ghi DB/file. Nghiệm thu trước 08/10 giữ nguồn cũ cho tới khi sửa bằng luồng mới; không tự cập nhật dữ liệu cũ.
- Không cần migration. 10 test mới cùng Nghiệm thu/SalaryCloseout/checklist/hướng dẫn: **60/60 đạt, 660 assertions**, DB test riêng và storage fake. Sau sửa CSS ghi chú chạy lại nhập tay: **10/10 đạt, 75 assertions**. Web local mở form công trình 64 bằng tài khoản mẫu trưởng phòng để kiểm giao diện; không gửi/duyệt/upload dữ liệu local. Browser chưa kiểm thao tác ghi đầy đủ; các thao tác đó được kiểm bằng feature test.
- Không commit/deploy hoặc duyệt cấu hình, lương, nghiệm thu local. Không sửa dữ liệu nghiệp vụ local; đăng nhập có thể ghi session/last_seen. Cập nhật DECISIONS D16, PROJECT/PLAN/prompt/skill đợt vật tư. Hướng dẫn và giới hạn: [MANUAL-MATERIAL-20261008](MANUAL-MATERIAL-20261008.md).
- Đối chiếu tự động Kho D08 và khóa mọi chứng từ Kho vẫn mở, chưa coi S3 hoàn tất. D15 không làm tròn tiền, đồng bộ phiếu lương cũ và thử HR chưa triển khai trong đợt nhập tay này.
- Skill đã kiểm thủ công frontmatter và link cập nhật. Validator bundled chưa chạy được do runtime thiếu PyYAML; không cài package hoặc ghi nhận validator đã đạt.

## Ghi chú KPI tại các thao tác Kỹ thuật — 08/10/2026

- Theo yêu cầu “ghi chú cả hết vào để kỹ thuật biết”, đã thêm hướng dẫn ngay ở Kế hoạch, Giao việc (hai drawer), Việc hôm nay, Báo cáo ngày/tuần, form và chi tiết báo cáo; bốn bài hướng dẫn liên quan cũng hiện cùng quy tắc. Phân biệt lưu nháp kế hoạch với giao thật, giờ giao + 24 giờ, lần gửi báo cáo đầu với lưu nháp/cập nhật %/đánh dấu hoàn thành. Khi chọn việc trên form báo cáo, phần ghi chú của đúng việc hiện theo lựa chọn.
- Từng dòng việc và báo cáo có trạng thái KPI, giờ giao/hạn/lần gửi đầu và link checklist đúng người/tháng. `KpiActionHintService` đọc cùng `AssignedWorkKpiService::collect/evaluate`, tái dùng cấu hình hiệu lực của bộ chấm, cache chỉ trong request; không có bộ tính điểm hoặc mốc thời gian riêng phía trình duyệt. Việc tự lập/nháp chưa gắn giao việc báo chưa tính; nộp muộn/quá hạn báo mất phần điểm; việc còn hạn báo chờ tính. Kỳ còn nguồn thiếu được ghi rõ chưa chốt điểm Tiến độ.
- Bảo hành: ghi chú đủ nhận/phân công → khách báo → phản hồi → xử lý/Hoàn tất đợt → kiểm tra khiếu nại ngay trên phiếu; thêm ghi chú cạnh Lưu giờ khách báo, phản hồi, xác nhận và nút Hoàn tất đợt. Trang Tổng quan và bước Thực hiện hiện trạng thái/lý do của phiếu, link checklist người phụ trách chính; giải thích phiếu đạt chỉ nhận phần điểm theo các phiếu trong kỳ. Không dùng lưu kết quả, tải file, tick hạng mục hoặc Gửi duyệt thay cho mốc hoàn tất. Cách chấm SLA được ghi là cách đọc hiện tại, D12 chi tiết chưa được chốt thêm.
- Trạng thái phiếu tái dùng `KpiStandardEvaluator::warrantyState` (đổi thành public để presenter đọc), không sửa công thức/phân quyền/nguồn hay tạo dữ liệu xác nhận. Chỉ phiếu thuộc loại bảo hành hiện hành hiện khối KPI; ngoài phạm vi công ty hoặc chưa có người phụ trách không hiển thị đủ điều kiện nhận điểm.
- Kiểm thử: 4 ca mới kiểm nộp đúng hạn/muộn/còn hạn dù tự đánh dấu 100%, lần gửi đầu không bị lượt gửi lại thay, hoàn tất bảo hành vẫn thiếu khiếu nại, biên 5 phút/24 giờ, phiếu định kỳ không hứa điểm bảo hành và GET không sửa nguồn. Kèm AssignedWork/SourceFlow/Checklist: **53/53 đạt, 342 assertions**. Sau bổ sung ghi chú theo lựa chọn trên form và các bài hướng dẫn: ActionHint/GuidePages/PlanCta **33/33 đạt, 216 assertions** (4 ca ActionHint chạy lại). Test trên `egosolar_test`, transaction rollback.
- Browser local chỉ đọc bằng tài khoản mẫu trưởng phòng: công việc mẫu 4/5, báo cáo 1 và phiếu 148 hiện đúng ghi chú, thời gian và trạng thái cùng checklist. Không gửi form, đổi phân công/mốc giờ hay duyệt dữ liệu. Tab kiểm tra riêng để không làm mất bản người dùng đang nhập ở công trình 64. Chưa thử thao tác chọn dropdown mới trên browser bằng tài khoản kỹ thuật; form/quyền đã được feature test kiểm.
- Không commit/deploy, không thay tiền lương, cấu hình hay dữ liệu nghiệp vụ local. `git diff --check` còn báo 5 dòng whitespace có sẵn ở file bảo hành dùng chung, nằm ngoài các dòng sửa của đợt này; không sửa việc khác để dọn diff.

## KPI HCNS: HC09 và HC07 từ nguồn — 08/10/2026 (Claude)

- **Đã có nguồn tự động 2/9 tiêu chí HCNS, chưa áp migration lên local, chưa commit/deploy.** Chi tiết: `hcns-kpi/PROJECT.md` mục Kết quả; PLAN có bằng chứng từng mục.
- HC09: thủ tục hành chính HCNS trong Công việc, gồm lưu nháp, gửi, người kiểm duyệt/trả, gửi lại và hủy có lý do. Lưu gửi đầu/gửi gần nhất/số lần gửi; chặn tự duyệt, chặn xóa và đổi trạng thái qua form.
- HC07: giao nhận hồ sơ theo dõi KPI, người kiểm độc lập chấm 3 mục. Sửa sau khi gửi hoặc xác nhận thì phải kiểm lại, kể cả đổi về như cũ. Tệp mới lưu riêng tư.
- Checklist 9 tiêu chí dưới bảng KPI từng người; 7 tiêu chí ghi đang chấm tay. Quy tắc đo nguồn có phiên bản nháp/duyệt (người duyệt khác người lập). Bản nháp không đổi điểm chính thức.
- Kỳ lương HR đã duyệt khóa nguồn HC09/HC07 của kỳ đó; việc thường và việc kỹ thuật không bị khóa.
- Migration `2026_10_08_160000` chỉ thêm cột/bảng, đã chạy trên `egosolar_test`. Đề xuất áp local, tác động và khôi phục ở PROJECT.
- Kiểm thử:
  - HCNS 28 ca mới đạt.
  - `tests/Feature/Hr` 101 ca: 2 lỗi baseline `PayrollFlowTest`.
  - `tests/Feature/Technical` 408 ca: 10 lỗi đã có trước, gồm 1 do phiên ghi chú Kỹ thuật sửa drawer lúc 00:50.
  - UI server cô lập 8012 (DB/cache/session/storage riêng): luồng 4 vai trò 27/27, tab quy tắc 8/8, desktop + 390 px.
  - Local 8001 chỉ xem trang Công việc bằng tài khoản mẫu: không lỗi khi chưa migrate.
- Fixture `HCNS-KPI-TEST` đã dọn, `egosolar_test` về đúng số dòng trước seed. Không duyệt quy tắc/KPI/lương trên local. P4–P6 (HC01–HC06, HC08) chưa làm, chờ H-D04–H-D08.

## KPI HCNS: đối chiếu, nền tính lương, HC06 — 09/10/2026 (Claude)

- **HCNS chưa hoàn tất:** HC09/HC07 từ nguồn; HC06 có nguồn ba nhóm nhưng chưa có điểm (chờ H-D04); HC01–HC05, HC08 chấm tay. Chưa áp migration lên local (nay 3 tệp), chưa commit/deploy. Chi tiết `hcns-kpi/PROJECT.md` mục “Kết quả 09/10/2026”, bằng chứng trong PLAN.
- Đối chiếu tính tay từng hồ sơ (`outputs/claude-kpi-agent/hcns-kpi-20261008/reconcile-20261009.md`): HC09 3/6 = 50% gồm hai ca đổi hạn (trước hạn gốc → dùng hạn mới, sau khi đã quá hạn → giữ hạn gốc), HC07 2/3, HC06 cấp phát 2/3 · kiểm kê 1/1 · sự cố 2/4 (không có tỷ lệ chung). Khớp bộ đo từng dòng; checklist tách Số đo / Xem trước / Chính thức.
- Nền tính lương: trọng số/target/bậc theo phiên bản (nháp → người khác duyệt từ một kỳ), ảnh chụp KPI khi duyệt lương, khóa nguồn theo kỳ (không khóa việc thường/kỹ thuật), minh chứng HC09 riêng tư + lệnh chuyển tệp cũ. Lương 09/2026 demo: 85,94% → bậc 100% → 2.000.000 × 22/24 = 1.833.333 đ, khớp tính tay, không làm tròn nghìn.
- HC06: VPP theo dõi KPI ngay trong modal Cấp phát; đợt kiểm kê trong tab Tài sản công ty; sự cố thêm người xử lý, mốc xử lý đầu, thời gian chờ duyệt chi, giờ hủy. Không thêm menu.
- Kiểm thử: HCNS 44 test đạt; `tests/Feature/Hr` 117 (2 lỗi baseline `PayrollFlowTest`); `tests/Feature/Technical` 408 (10 lỗi trùng 08/10; cần `memory_limit` > 128 MB để chạy hết). UI 8012 cô lập: 29/29, 35/35, 21/21, desktop + 390 px.
- Demo `HCNS-KPI-TEST` **đang để lại** trên `egosolar_test` + server 8012 cho người dùng kiểm tra; dọn bằng `php outputs/claude-kpi-agent/hcns-kpi-20261008/ui-test/fixtures.php cleanup`. Local 8001 không đụng.

## KPI HCNS: bộ tính “chưa đủ”, HC01–HC03 tuyển dụng — 09/10/2026 lần 3 (Claude)

- **HCNS chưa hoàn tất:** HC09/HC07 từ nguồn; HC06 và HC01–HC03 có nguồn nhưng chỉ số đo/xem trước (chờ H-D04, H-D02/H-D08); HC04, HC05, HC08 chưa có nguồn. Chưa áp 4 migration lên local, chưa commit/deploy. Chi tiết `hcns-kpi/PROJECT.md` mục “Kết quả 09/10/2026 (lần 3)”.
- Bộ tính: phiên bản đã chọn nguồn mà nguồn thiếu/chờ xác nhận/chưa sẵn sàng → “chưa đủ”, không âm thầm dùng điểm tay (vá trường hợp nguồn chưa sẵn sàng). “Không phát sinh” bắt buộc lý do, chỉ nhận khi nguồn không có hồ sơ. Giải thích 3/6 của HC09 và mẫu số 97,78 kỳ 09 (HC07 không phát sinh có lý do bị loại khỏi mẫu số — H-D03 cần chốt) trong file đối chiếu.
- HC01–HC03 ngay tại Tuyển dụng (không thêm menu): trang “KPI tuyển dụng” của yêu cầu, lượt tuyển (nhận việc thực tế + thử việc), khoản chi (chứng từ ĐNTT/tạm ứng đã quyết toán/hoàn tiền); hồ sơ nhân viên hiện lượt tuyển. Demo kỳ 10: 21 ngày/lượt; 1.400.000 đ/vị trí (chưa đủ vì còn khoản chưa thực chi); HC03 kỳ 09: 50% (chưa đủ, 1 người chưa xác định).
- Kiểm thử: `HcnsRecruitmentKpiTest` 8/8; `tests/Feature/Hr` 125 (2 lỗi baseline `PayrollFlowTest`); `tests/Feature/Technical` 408 (10 lỗi trùng baseline). UI 8012 cô lập, desktop + 390 px: tuyển dụng 31/31 + 4/4, HC09/HC07 29/29, HC06 35/35, lương 21/21. Đối chiếu tính tay `outputs/claude-kpi-agent/hcns-kpi-20261008/reconcile-20261009-p5.md` khớp từng dòng.
- Demo dựng lại đang để trên `egosolar_test` + server 8012 (6 tài khoản HCNS-KPI-TEST + 6 nhân viên mới TEST); dọn bằng lệnh cleanup ở trên. Local 8001/`crm_egosolar_test` không đụng.

## KPI HCNS: áp lên local 8001 và bộ mẫu — 09/10/2026 lần 4 (Claude)

- **Người dùng cho phép cập nhật DB local 8001** (thay yêu cầu cũ “chưa áp local”); chưa cho phép duyệt cấu hình áp dụng chung hay lương thật — không làm. Chi tiết `hcns-kpi/PROJECT.md` mục “Kết quả 09/10/2026 (lần 4)”, checklist PLAN “P9”.
- Sao lưu đầy đủ DB `crm_egosolar_test` + `storage/app` + `public/uploads` ngoài git tại `D:/Source Dev/crm.egosolar.com.vn/backups/hcns-local-20261009/` (đã khôi phục thử: 381/381 bảng, 0 lệch; cách khôi phục `KHOI-PHUC.md`).
- Áp **đúng 4 migration** HCNS bằng `--path` (batch 244–247): `2026_10_08_160000`, `2026_10_08_190000`, `2026_10_08_200000`, `2026_10_09_100000`. Không chạy migration khác, không fresh/refresh/wipe/seed. Không chuyển minh chứng HC09 (chạy thử: 0 tệp).
- Bộ mẫu `HCNS-LOCAL-DEMO-20261009` để người dùng xem: 3 tài khoản `hcns-local-demo.*@example.test` theo role sẵn có (hr nhập liệu, hr người kiểm, sales không quyền) + 5 nhân viên mới khóa đăng nhập; hồ sơ nguồn HC09/HC07/HC06/HC01–HC03. Mật khẩu chỉ trong `outputs/claude-kpi-agent/hcns-local-20261009/HUONG-DAN-RIENG-TU.md` (gitignore). Dọn riêng: `php outputs/claude-kpi-agent/hcns-local-20261009/local-demo.php cleanup` (đã thử, về đúng baseline).
- Trình duyệt trên 8001: **80/80** (desktop + 390 px). Đối chiếu tính tay khớp từng dòng: HC09 3/6 = 50%, HC07 2/3, HC06 2/2 · 1/1 · 2/4 (chưa gộp), HC01 21 ngày/lượt, HC02 1.400.000 đ/vị trí (thiếu dữ liệu), HC03 kỳ 09 50% (thiếu dữ liệu). Local chưa có phiên bản quy tắc được duyệt → tất cả chỉ là số đo/xem trước; điểm chính thức chưa có, tổng “chưa đủ”.
- Source: thêm bộ lọc **Nhân viên** trên trang KPI phòng ban (`HrKpiController`, `hr/kpi/index.blade.php`) + 1 test. `tests/Feature/Hr` 126 test trên `egosolar_test`: chỉ 2 lỗi baseline `PayrollFlowTest`. Demo 8012 dựng lại, UI chạy lại đạt.
- So baseline cuối: 0 bảng dữ liệu cũ đổi; Admin cũ, role, mật khẩu người dùng cũ, lương Excel 08/2026, cấu hình/dữ liệu kỹ thuật giữ nguyên.
- Chưa kiểm: đăng nhập Admin cũ (không có mật khẩu, không reset); giao thủ tục HCNS mới trên 8001 (role hr không giao việc được). HC04, HC05, HC08 chưa có nguồn — **HCNS chưa hoàn tất**. Không commit/push/deploy.

## KPI HCNS: HC04 đối soát độ chính xác tính lương — 09/10/2026 lần 5 (Claude)

- **HC04 có nguồn, chỉ số đo/xem trước (chờ H-D05). HC05, HC08 chưa có nguồn — HCNS chưa hoàn tất.** Chi tiết và đề xuất H-D05 có ví dụ: `hcns-kpi/PROJECT.md` mục “Kết quả 09/10/2026 (lần 5)”; checklist PLAN P6/P9; nguồn SOURCE-MAP mục HC04.
- Nguồn kiểm duy nhất: Bảng lương Nhân sự (`hr_payroll_periods/lines`). Bảng lương cũ Tài chính (`payrolls`, `?legacy=1`) và `payroll_slip_*` không dùng, không cộng.
- Ngay trong trang Bảng lương (không thêm menu): khối “Đối soát độ chính xác (KPI HC04)” → đợt đối soát (kỳ, phạm vi, HCNS chịu KPI, người nhập, người kiểm độc lập) → từng dòng Đúng/Có lỗi/Chưa xác định + khoản đã kiểm; lỗi có khoản/loại/nội dung/số tiền/nguồn/chứng cứ/cách xử lý; lưu nháp → gửi → xác nhận hoặc trả bổ sung; lịch sử đầy đủ, gỡ lỗi chỉ đánh dấu có lý do. Không đổi số tiền lương.
- Checklist/bộ chấm dùng chung; chưa có đợt ≠ 100%, dòng chưa kiểm/chưa xác định/dòng lương đổi sau khi kiểm → chưa đủ; khóa theo kỳ đã chốt + ảnh chụp KPI có khối HC04.
- Test trên `egosolar_test`: `HcnsPayrollAccuracyKpiTest` 13/13; `tests/Feature/Hr` 139 test — chỉ 2 lỗi baseline `PayrollFlowTest`. Luồng UI cũ 8012 chạy lại sau khi sửa: HC09/HC07 29/29, HC06 35/35, tuyển dụng 31/31 + 4/4, lương 21/21. `tests/Feature/Technical` không chạy lại (đợt này không sửa phần kỹ thuật).
- Local 8001: sao lưu mới (`backups/hcns-local-20261009/crm_egosolar_test-before-hc04-20261009-161543.sql`, khôi phục thử 0 lệch) → áp riêng migration `2026_10_09_160000_hcns_kpi_payroll_audits` (batch 248) → 0 bảng dữ liệu cũ đổi. Bộ mẫu thêm kỳ lương MẪU 09/2026 (chỉ tài khoản mẫu, số giả, nháp) + 3 đợt; dọn riêng: `local-demo.php cleanup-hc04`. Trình duyệt 32/32 (người nhập, người kiểm, người không có quyền; desktop + 390 px). Đối chiếu tay KPI tháng 10: 5/8 dòng = 62,5% (khớp từng dòng).
- Lỗi phát hiện khi thử và đã sửa: trang đợt lỗi 500 khi hiện lại sau lưu bị chặn (trùng tên ô); xem tệp HCNS (VPP/kiểm kê/HC04) bị lưu làm “trang trước” khiến thông báo lỗi/quay lại mở tệp. Có test cho cả hai.
- Không duyệt cấu hình/lương, không commit/push/deploy, không gửi thông báo. Admin cũ không đổi (không đăng nhập Admin).
