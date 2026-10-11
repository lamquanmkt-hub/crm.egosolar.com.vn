# Đồng bộ dữ liệu và KPI HCNS

Ngày lập: 08/10/2026. **Trạng thái (09/10 lần 5, Claude): HC04 đã có nguồn (đối soát bảng lương HR) nhưng chỉ số đo/xem trước (chờ H-D05); HC05, HC08 chưa có nguồn. Lần 4 (áp local) ghi bên dưới.** **Trạng thái (09/10 lần 4, Claude): HC09 và HC07 có nguồn tự động đầu-cuối; HC06 và HC01–HC03 có nguồn nhưng chỉ số đo/xem trước (chờ H-D04, H-D02/H-D08); HC04, HC05, HC08 chưa có nguồn. Bộ tính báo “chưa đủ” khi phiên bản đã chọn nguồn mà thiếu dữ liệu — không quay về điểm tay. Đã áp 4 migration lên local 8001 (được người dùng cho phép 09/10, có sao lưu) và để bộ mẫu `HCNS-LOCAL-DEMO-20261009`; local chưa có phiên bản quy tắc được duyệt nên mọi nguồn HCNS trên 8001 đều chỉ xem trước. Chưa commit/deploy. HCNS chưa hoàn tất.** Xem mục “Kết quả 09/10/2026 (lần 4) — áp local 8001”.

## Mục tiêu

### Ủy quyền cập nhật local — 09/10/2026

Người dùng đã yêu cầu: “Đẩy lên 8001 xong test dữ liệu đi đưa cho claude làm”. Claude được sao lưu, áp các migration HCNS liên quan và tạo/kiểm tra hồ sơ mẫu tách biệt trên DB local của 8001. Đây là thay đổi phạm vi so với yêu cầu cũ chưa áp local. Không duyệt cấu hình áp dụng chung/lương thật, không chạy test suite/fixture phá dữ liệu ở local, không deploy hosting. Nội dung giao việc cụ thể: [PROMPT-AP-LOCAL-8001-20261009.txt](PROMPT-AP-LOCAL-8001-20261009.txt). **Đã thực hiện 09/10/2026** — kết quả ở mục “Kết quả 09/10/2026 (lần 4) — áp local 8001”.

Nhân viên nhập công việc tại trang nghiệp vụ → người có quyền kiểm tra/xác nhận → hệ thống tổng hợp chín KPI HCNS theo từng người và kỳ → checklist mở được hồ sơ nguồn → đối chiếu/duyệt KPI và lương khi đủ điều kiện.

Người dùng yêu cầu dữ liệu nguồn trước; giao diện đồng nhất với kỹ thuật, ít menu/ít trang, mỗi phòng ban cài đặt riêng. Việc có trang, có hồ sơ hoặc có tệp không đồng nghĩa đã nhận điểm.

## Phạm vi và môi trường

- Repo: `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`.
- Local: `http://127.0.0.1:8001`. DB này là `crm_egosolar_test`, chứa dữ liệu vận hành local; không chạy fixture/test phá dữ liệu ở đây.
- Feature test: `egosolar_test`, theo guard `tests/TestCase.php`. PHP `C:/php83/php.exe`.
- Đầu vào chính: `E:/KPIs/BẢNG TIÊU CHÍ ĐÁNH GIÁ KPI - HCNS.docx`.
- Đã đọc source và Word trong lần chuẩn bị này. Audit 08/10 tại `outputs/hcns-source-audit-20261008/RA-SOAT-KPI-HCNS.md` là điểm bắt đầu, không thay thế việc kiểm tra source hiện tại.
- Không sửa KPI kỹ thuật/kho/Sales/Marketing, không cải tổ toàn bộ bảng lương hay UI CRM.
- Không commit/deploy, không duyệt thay người dùng. Không chạy Claude từ nhiệm vụ viết tài liệu này; prompt để người dùng gửi.

## Baseline cần giữ

1. `HrKpiEvaluator` HCNS đọc `hr_kpi_scores`; hiện chưa đọc đủ chín nghiệp vụ. `% đạt` nhập tay đang được ưu tiên hơn `actual_value`.
2. Chín trọng số đang tạm chia đều: 11,12 + 8 × 11,11. Word không ghi trọng số. Không coi chúng là đã duyệt.
3. Bậc HCNS trong Word: từ 95 điểm hưởng 110% quỹ KPI; từ 80 hưởng 100%; từ 65 hưởng 85%; dưới 65 hưởng 0. Không thay sang % thực đạt chỉ vì kỹ thuật đã có yêu cầu riêng. Khoản thưởng thêm vượt cam kết tuyển dụng chưa có công thức.
4. HCNS/Kế toán quản lý lương qua `PayrollAccess`; Ban Giám đốc/Admin được chấm/cài KPI; không tự chấm chính mình. Xác nhận nguồn HCNS cần đối chiếu đúng quyền của từng module.
5. `HrKpiController::autoData()` mới đưa SLA sự cố vào `assets` dưới dạng tham khảo. Không dùng toàn bộ thống kê sự cố để thay KPI cấp phát/kiểm kê.
6. `monthLocked()` hiện kiểm kỳ HR đã duyệt. Cấu hình HCNS đang sửa trực tiếp, chưa có đầy đủ version/snapshot theo kỳ và khóa nguồn nghiệp vụ.
7. Đã tối ưu bảy bước tuyển dụng. Đã có upload nhiều tệp, preview PDF/ảnh/DOCX/Excel/CSV/TXT và kiểm quyền ở `RecruitmentAttachmentService`/`RecruitmentAttachmentController`; test `RecruitmentAttachmentTest` đạt 7/7, 122 assertions trong lần trước. Claude phải chạy lại khi sửa liên quan.
8. Bảng lương nhập Excel 08/2026 là lịch sử, không tính lại/đổi chế độ/chỉnh tiền. Giữ các công trình, bảo hành, giao việc và cấu hình kỹ thuật mẫu hiện có. Không ghi mật khẩu tài khoản mẫu vào tài liệu.

## Giao diện đích

Trang KPI HCNS hiện có: bộ lọc tháng/người → danh sách nhân viên → mở chi tiết → chín tiêu chí → checklist hồ sơ từng tiêu chí. Dùng cấu trúc/màu/khoảng cách của trang kỹ thuật, tận dụng CSS/component hiện có; không copy toàn bộ engine kỹ thuật.

Một dòng checklist có tên hồ sơ + link, người chịu trách nhiệm, hạn, mốc thực tế, trạng thái công việc, người/giờ xác nhận, trạng thái KPI và lý do. Phân biệt “hoàn thành” với “đã được tính”. Nguồn chưa đủ hoặc chính sách chưa chốt vẫn hiện trong checklist; không biến mất khỏi danh sách để nâng tỷ lệ.

Mỗi form nguồn có ghi chú ngắn: ai nhập, ai xác nhận, bấm gì tiếp, khi nào được tính KPI. Đính kèm minh chứng tận dụng bộ upload/preview sẵn có; thiếu ở module nào thì bổ sung ngay module đó. Không làm thêm một kho file hoặc menu KPI nguồn riêng.

## Những quyết định còn mở

Các lựa chọn dưới đây **chưa được người dùng phê duyệt**. Claude có thể thiết kế cấu hình và đề xuất mặc định ở bản nháp; không áp vào kỳ thật.

| Mã | Cần chốt | Trong lúc chưa chốt |
|---|---|---|
| H-D01 | Trọng số chín tiêu chí; ngày hiệu lực HCNS | Giữ baseline lịch sử, kết quả mới ở chế độ xem trước; không tự nhận tháng 10 là ngày hiệu lực |
| H-D02 | Công thức quy đổi từng chỉ số sang % đạt, trần/vượt và mục tiêu trong khoảng HC01/HC02 | Hiện số đo thực tế + target Word; quy đổi chưa chốt thì chưa có điểm chính thức |
| H-D03 | Kỳ/mẫu số từng tiêu chí; mốc đo HC09/HC07; hủy, hồ sơ qua tháng, không phát sinh | Lưu đủ mốc/lịch sử và tạo lựa chọn có xem trước; không tự loại việc quá hạn chưa xong |
| H-D04 | HC06 gộp cấp phát, kiểm kê, sự cố thế nào; chọn đầu nào của khoảng SLA; lịch làm việc/thời gian chờ | Ba nhóm nguồn riêng; sự cố chỉ tham khảo đến khi chốt cách gộp |
| H-D05 | HC04 dùng bảng lương HR hay tài chính; mẫu số lỗi là dòng/người/khoản; ai xác nhận | Đã xây đối soát trên bảng lương HR (09/10 lần 5). Nháp: kỳ KPI = tháng sau kỳ lương, đơn vị dòng, kiểm đủ, mọi lỗi tính; chỉ xem trước. Đề xuất có ví dụ ở mục kết quả lần 5 |
| H-D06 | HC08 thang khảo sát, mức thế nào là hài lòng, số người phải phản hồi, bảo mật/ẩn danh | Lưu kỳ/số mời/số phản hồi; chưa đủ quy tắc hoặc phản hồi thì chưa đủ dữ liệu |
| H-D07 | Người chịu trách nhiệm và người xác nhận mỗi nhóm nghiệp vụ; quyền trưởng phòng HCNS thực tế | Dùng quyền hiện có; thiết kế phân công reviewer bằng ID nếu phù hợp, không cấp quyền mới tự động |
| H-D08 | Cohort tuyển dụng/thử việc, chi phí hợp lệ, nghỉ thử việc, hoàn ứng và chia chi phí cho nhiều vị trí | Bổ sung liên kết và dữ kiện; không dùng tổng chi VP hoặc trạng thái không hoạt động để suy KPI |

**Đề xuất kỹ thuật để người duyệt xem, không phải quyết định:** HC09 chọn kỳ theo deadline đã được chốt; lưu cả giờ gửi đầu, các lần gửi lại và giờ người kiểm xác nhận; so hạn theo mốc phù hợp được duyệt. Lưu timestamp chuẩn, hiển thị Asia/Ho_Chi_Minh; biên đúng giờ hạn cần thể hiện rõ bằng test. Chưa chốt không được tự coi người duyệt chậm là nhân viên nộp trễ.

## Điều kiện hoàn tất dự án

- Cả chín tiêu chí có đường nhập/xác nhận và adapter nguồn, hoặc nêu rõ phần chưa có nghiệp vụ/quy tắc; phần thiếu không bị gọi là đã tự động.
- Cấu hình theo kỳ đã kiểm thử; total/tiền bị chặn khi thiếu điều kiện, không dùng điểm nháp trả lương.
- Checklist mỗi người mở đúng nguồn trong phạm vi quyền; phép đo và điểm khớp tính tay.
- Nguồn và kết quả đã chốt không bị sửa ngầm; tháng cũ và Excel lịch sử giữ nguyên.
- Test và kiểm thử UI các vai trò đạt cho phần triển khai; lỗi baseline còn lại được tách và có bằng chứng, không gán là “có từ trước” nếu chưa đối chứng.

## Kết quả 08/10/2026 (Claude)

**Đã có nguồn tự động: 2/9 tiêu chí.**

- **HC09** — Công việc → giao việc, đánh dấu “Thủ tục hành chính HCNS”.
  - Luồng: giao → lưu nháp → gửi → người kiểm duyệt hoặc trả bổ sung → gửi lại; hủy có lý do.
  - Ghi mốc gửi đầu/gửi gần nhất/số lần gửi và người/giờ xác nhận.
- **HC07** — Nhân sự → Giao nhận hồ sơ, đánh dấu “theo dõi KPI HC07”.
  - Người xử lý gửi kiểm; người kiểm độc lập chấm 3 mục (phân loại, nơi nhận, đủ giấy tờ).
  - Sửa nội dung hoặc tệp sau khi gửi/xác nhận thì phải gửi và kiểm lại.
  - Tệp mới lưu riêng tư.

Checklist 9 tiêu chí hiện dưới bảng KPI từng người (trang KPI phòng ban và KPI của tôi). 7 tiêu chí ghi rõ đang chấm tay.

**Quy tắc đo nguồn có phiên bản.**

- Bản nháp chỉ xem trước. Chưa có bản nào được duyệt, nên điểm chính thức vẫn là nhập tay như trước.
- Khi Ban Giám đốc/Admin (khác người lập) duyệt áp dụng từ một kỳ: tiêu chí có nguồn lấy điểm từ hồ sơ, bỏ qua số nhập tay. Nguồn chưa đủ thì tổng KPI “chưa đủ”, không sinh hệ số lương.
- Kỳ đã chốt lương HR khóa nguồn HC09/HC07 của kỳ đó. Việc thường và việc kỹ thuật không bị khóa.

**Mặc định nháp đề xuất (chưa phải chính sách):**

- HC09:
  - Kỳ theo hạn.
  - So lần gửi được chấp nhận với hạn; không dùng giờ duyệt.
  - Gia hạn chỉ nhận khi đổi trước hạn đang hiệu lực.
  - Hạn chỉ có ngày tính hết 23:59:59.
  - Hủy trước hạn thì loại khỏi mẫu số; hủy sau hạn khi chưa nộp thì tính trễ.
- HC07: kỳ theo lần gửi kiểm đầu; đạt khi cả 3 mục đạt.
- Quy đổi xem trước dùng công thức baseline của tiêu chí: Thực tế ÷ Chỉ tiêu, trần 120.

**Tệp chính:**

| Nhóm | Tệp |
|---|---|
| Migration | `2026_10_08_160000_add_hcns_kpi_source_tracking.php` |
| Cấu hình | `config/hcns_kpi.php` |
| Service KPI | `app/Services/Hr/Kpi/{HcnsAdminTasks,HcnsKpiRules,HcnsSourceReviews,HcnsKpiChecklist}.php`, `Sources/{AdminDeadlineSource,DocumentAccuracySource}.php` |
| Service Công việc | `app/Services/Tasks/TaskPermissions.php` (logic quyền chuyển nguyên từ controller) |
| Controller | `TaskController`, `DocumentHandoverController`, `HrKpiController`; `HrKpiEvaluator` thêm `sourceOverrides()` |
| View | `tasks/partials/hcns-*`, `hr/document-handovers/_kpi-review`, `hr/kpi/_hcns-checklist`, `hr/kpi/_hcns-rules` |
| Route mới | `tasks.cancel`, `hr.document-handovers.kpi.{draft,submit,confirm,return}`, `hr.kpi.rules.{draft,approve}` |
| Test | `tests/Feature/Hr/Hcns*Test.php` (28 ca) |

**Sửa lỗi sẵn có:** trang chi tiết giao nhận hồ sơ gọi modal “+ Thêm file”/“Xem trước” nhưng không có markup, nên hai nút không hoạt động. Đã bổ sung.

**Giới hạn còn lại:**

- HC01–HC06, HC08 chưa có nguồn (P4–P6).
- Trọng số/target/bậc vẫn sửa trực tiếp, chưa theo phiên bản.
- Chưa snapshot từng dòng checklist khi chốt kỳ.
- Chưa thử đầy đủ UI bảng lương HR với quy tắc đã áp dụng.
- Minh chứng HC09 dùng tệp kết quả của module Công việc (disk public cũ, đường dẫn ngẫu nhiên). Chưa chuyển sang riêng tư vì module dùng chung với Kỹ thuật.
- Trang HR trên local chưa thử bằng tài khoản HCNS thật (không có mật khẩu). Chỉ thử trang Công việc local bằng tài khoản mẫu kỹ thuật: mở bình thường khi chưa migrate.

### Đề xuất áp lên DB local (chờ người dùng quyết định)

Cập nhật 09/10: nay gồm **3 migration**, áp theo thứ tự. Tất cả chỉ thêm cột nullable/bảng mới; chưa chạy trên local.

1. **Sao lưu trước.** `mysqldump crm_egosolar_test tasks task_attachments hr_document_handovers hr_document_handover_files hr_kpi_criteria hr_kpi_templates hr_vpp_requests hr_office_incidents > backup-hcns-<giờ>.sql`.
2. **Chạy từng tệp.**
   - `C:/php83/php.exe artisan migrate --path=database/migrations/2026_10_08_160000_add_hcns_kpi_source_tracking.php`
   - `... --path=database/migrations/2026_10_08_190000_hcns_kpi_payroll_foundation.php`
   - `... --path=database/migrations/2026_10_08_200000_hcns_kpi_hc06_sources.php`
3. **Tác động.**
   - `160000`: 11 cột nullable vào `tasks`, 7 cột vào `hr_document_handovers`, cột `disk` vào `hr_document_handover_files`; bảng `hr_kpi_source_reviews`, `hr_kpi_source_review_events`, `hr_kpi_rule_versions`; 1 index.
   - `190000`: cột `config` (phiên bản trọng số/target/bậc), bảng `hr_kpi_period_snapshots`, cột `disk` vào `task_attachments` (tệp cũ giữ disk public cho đến khi chạy lệnh chuyển).
   - `200000`: 10 cột nullable vào `hr_vpp_requests`, 7 cột vào `hr_office_incidents`; bảng `hr_asset_inventories`, `hr_asset_inventory_items`, `hr_kpi_source_files`, `hr_kpi_source_logs`.
   - Không sửa dòng dữ liệu cũ: việc/hồ sơ/phiếu/sự cố cũ không tự vào KPI; không tạo phiên bản quy tắc hay ảnh chụp. Lương Excel 08/2026 không bị đọc lại.
   - Tùy chọn sau đó: `php artisan hcns:secure-task-attachments` (chạy thử, chỉ liệt kê) rồi mới `--apply` nếu muốn chuyển tệp kết quả thủ tục HCNS cũ sang riêng tư.
4. **Khôi phục.**
   - `migrate:rollback --path=<tệp>` theo thứ tự ngược: xóa các bảng mới, giữ các cột đã thêm (nullable, không ảnh hưởng code cũ).
   - Muốn bỏ hẳn cột thì phục hồi từ bản sao lưu. Không có bước xóa dữ liệu nghiệp vụ.

### Quyết định còn mở

- **H-D01:** trọng số và ngày hiệu lực.
- **H-D02:** quy đổi số đo → % đạt (baseline đang là Thực tế ÷ Chỉ tiêu).
- **H-D03:** các phương án nháp ở trên (mốc so hạn, gia hạn, hủy, hạn chỉ có ngày, kỳ HC07).
- **H-D04 – H-D06, H-D08:** chặn P4–P6.
- **H-D07:** ai là người kiểm mặc định cho HC09/HC07. Hiện chọn theo từng hồ sơ; Ban Giám đốc/Admin kiểm được HC07.

## Kết quả 09/10/2026 (Claude)

**1. Đối chiếu HC09/HC07.** Tính tay độc lập từ dòng DB và so từng hồ sơ với bộ đo (`outputs/.../reconcile-20261009.md`). Ví dụ 2/4 hôm trước gồm 2 việc đạt: Gia hạn PCCC (gửi 10:00 03/10 ≤ hạn 17:00 03/10) và Gia hạn HĐ thuê VP (gửi qua UI ≤ hạn 17:00 20/10). Demo nay thêm hai ca đổi hạn:

- Gia hạn **trước** hạn gốc (đổi 15:00 07/10, hạn gốc 17:00 08/10 → 17:00 12/10, gửi 08:30 09/10): dùng hạn mới → đạt.
- Gia hạn **sau khi đã quá** hạn gốc (đổi 09:00 03/10, hạn gốc 17:00 02/10 → 17:00 06/10, gửi 10:00 05/10): giữ hạn gốc → không đạt.

Kết quả kỳ 10: HC09 **3/6 = 50%** (1 chờ xác nhận sau khi gửi lại — giữ lần gửi đầu 08:00 08/10; 2 việc hủy trước hạn bị loại), HC07 **2/3 = 66,67%** (1 chờ). Khớp từng dòng. Checklist tách ba lớp: **Số đo** (tử/mẫu) → **Xem trước** (số đo ÷ chỉ tiêu, trần 120, × trọng số — công thức tạm) → **Chính thức** (đúng kết quả bộ chấm: từ nguồn khi phiên bản đã duyệt và đủ dữ liệu, ngược lại nhập tay). Trang việc và checklist ghi “Hạn đo” và lý do khi hạn hiện tại không được dùng.

**2. Nền tính lương.**

- Trọng số/target/bậc HCNS lưu theo phiên bản: lưu ở tab Cài đặt = bản nháp (cài đặt gốc giữ nguyên), người khác duyệt áp dụng từ một kỳ; người lập không thấy nút duyệt, server cũng chặn. Không áp vào kỳ đã chốt.
- Duyệt lương → ảnh chụp từng người (`hr_kpi_period_snapshots`: dòng tiêu chí, checklist, phiên bản, tổng, hệ số); kỳ đã chốt hiển thị và trả lương theo ảnh chụp; mở khóa → ảnh chụp chuyển lịch sử.
- Khóa kỳ: HC09 (giao/sửa/đổi hạn/nộp/duyệt/trả/hủy/tệp), HC07, VPP theo dõi KPI, đợt kiểm kê, phân loại/hủy sự cố; KPI tay và tính lại lương. Việc thường/kỹ thuật, cấp phát không theo dõi, xử lý/nghiệm thu sự cố không bị khóa.
- Minh chứng HC09 mới lưu riêng tư, xem/tải qua route kiểm quyền; lệnh `hcns:secure-task-attachments` chuyển tệp cũ (chạy thử mặc định, giữ ID).
- Bảng lương HR dùng cùng bộ chấm: 09/2026 tính tay 84,03 ÷ 97,78 = 85,94% → bậc 100% → 2.000.000 × 22/24 × 1,00 = 1.833.333 đ, khớp bảng lương; chỉ làm tròn đồng.

**3. HC06** (chi tiết PLAN P4): VPP theo dõi KPI ngay trong modal Cấp phát + trang chi tiết; đợt kiểm kê trong tab Tài sản công ty (trang chi tiết mới cho từng đợt, không thêm menu); sự cố có người xử lý, mốc xử lý đầu, thời gian chờ duyệt chi, giờ hủy và khối “Số đo KPI HC06” (chỉ HCNS/Ban Giám đốc thấy). Checklist ba nhóm riêng; **chưa có điểm HC06** vì cách gộp chưa chốt — điểm chính thức vẫn chấm tay. Demo: cấp phát 2/3, kiểm kê 1/1, sự cố 2/4.

**Kiểm thử:** HCNS 44 test (6 lớp) đạt; `tests/Feature/Hr` 117: 2 lỗi baseline `PayrollFlowTest`; `tests/Feature/Technical` 408: 10 lỗi trùng danh sách 08/10. UI server cô lập 8012: HC09/HC07 29/29, HC06 35/35, lương 21/21 (desktop + 390 px). Local 8001 không đụng.

**Tệp thêm trong đợt này:**

| Nhóm | Tệp |
|---|---|
| Migration | `2026_10_08_190000_hcns_kpi_payroll_foundation.php`, `2026_10_08_200000_hcns_kpi_hc06_sources.php` |
| Service | `HcnsKpiSnapshots`, `HcnsSourceFiles`, `VppAllocationKpi`, `AssetInventories`, `Sources/AssetsServiceSource`; sửa `HcnsKpiRules`, `HrKpiEvaluator`, `HcnsKpiChecklist`, `OfficeIncidents` |
| Controller | mới `AssetInventoryController`; sửa `OfficeSupplyProcessController`, `OfficeIncidentController`, `HcOperationController`, `HrKpiController`, `PayrollController`, `TaskController` |
| Lệnh | `hcns:secure-task-attachments` |
| View | `hr/kpi/_source-review-panel`, `hr/operations/inventory-show`; sửa VPP index/show, HC & Vận hành, sự cố, checklist/quy tắc/điểm KPI, thẻ HC09 |
| Route | `hr.office-supply-process.kpi.*`, `hr.asset-inventories.*`, `tasks.attachments.file` |
| Test | `HcnsKpiPayrollFoundationTest` (7), `HcnsAssetsServiceKpiTest` (9) |

**Giới hạn còn lại:**

- HC01–HC05, HC08 chưa có nguồn (P5–P6). HC06 chưa cho điểm (H-D04). Tổng KPI HCNS vẫn phụ thuộc chấm tay 6–7 tiêu chí — **HCNS chưa hoàn tất**.
- Sự cố đo giờ liên tục, chưa có lịch làm việc; hạn theo cài đặt mức hiện có của trang sự cố, chưa chọn đầu khoảng SLA của Word.
- Người yêu cầu VPP không có quyền trang Nhân sự thì không tự bấm “đã nhận” được; ký nhận chứng minh bằng biên bản/ảnh do người kiểm chấm.
- Trang HR trên local chưa thử bằng tài khoản HCNS thật.

**Quyết định cần chốt thêm:** H-D04 (gộp ba nhóm: gộp chung hay trung bình nhóm; mốc đo sự cố lần đầu/lần nghiệm thu; trừ hay không thời gian chờ duyệt chi; lịch làm việc); H-D01 trọng số/ngày hiệu lực (demo dùng HC09 = 20 chỉ để thử); ai được làm người kiểm VPP/kiểm kê (H-D07).

## Kết quả 09/10/2026 (lần 3, Claude)

**1. Bộ tính hiện tại.** Đối chiếu `outputs/.../reconcile-20261009-p5.md` (demo dựng lại, khớp từng dòng).

- HC09 kỳ 10 = **3/6 = 50%**: tử số là 3 việc được kiểm xác nhận và lần gửi được chấp nhận ≤ hạn đo (Gia hạn PCCC 10:00 03/10 ≤ 17:00 03/10; HĐ thuê VP gửi 09/10 ≤ 17:00 20/10; Hồ sơ ATLĐ gửi 08:30 09/10 ≤ hạn mới 17:00 12/10 vì gia hạn lúc 15:00 07/10, trước hạn gốc 17:00 08/10). Mẫu số thêm 3 việc không đạt (ĐKKD gửi 09:00 06/10 > 17:00 05/10; Báo cáo LĐ quá 17:00 07/10 chưa nộp; Nội quy LĐ gia hạn khi đã quá hạn gốc nên giữ 17:00 02/10, gửi 05/10). **Việc chờ xác nhận** (Chữ ký số: gửi đầu 08:00 08/10, trả bổ sung, gửi lại) **không vào tử/mẫu** và làm nguồn “chưa đủ”; 2 việc hủy trước hạn bị loại.
- Ba lớp: **Số đo** 3/6 = 50% → **Xem trước** 50 ÷ 80 = 62,5% × trọng số → **Chính thức**: kỳ 10 thuộc phiên bản v1 (áp dụng từ 09/2026, duyệt trong demo) nên HC09/HC07 lấy nguồn; nguồn còn chờ xác nhận → “**chưa đủ**”, tổng kỳ 10 chưa đủ, không dùng số nhập tay.
- Đã vá: nếu phiên bản chọn nguồn mà nguồn chưa sẵn sàng (thiếu bảng/cột), trước đây bộ chấm bỏ qua và dùng số nhập tay; nay báo “chưa đủ”. “Không phát sinh” trên bảng KPI HCNS bắt buộc lý do; chỉ nhận khi nguồn thật sự không có hồ sơ.
- Mẫu số trọng số kỳ 09 = **97,78** = 108,89 (9 tiêu chí: 11,12 + 7 × 11,11 + HC09 20 của bản thử) − 11,11 của HC07, vì HC07 tháng 9 không có hồ sơ nguồn và Giám đốc (TEST) đã đánh “Không phát sinh” có lý do. Bộ tính sẵn có loại tiêu chí không phát sinh khỏi mẫu số: 84,03 ÷ 97,78 = 85,94% → bậc 100%. Để so sánh: tính HC07 = 0 → 77,17% (bậc 85%); tính HC07 = 100% → 87,37%. **Cách xử lý “không phát sinh” là quyết định H-D03 cần chốt** — hệ thống không tự đổi.

**2–4. HC01–HC03** (chi tiết PLAN P5): nguồn ngay tại Tuyển dụng → “KPI tuyển dụng” của từng yêu cầu, trang lượt tuyển, trang khoản chi; hồ sơ nhân viên hiện lượt tuyển & thử việc. Không thêm menu. Demo kỳ 10: HC01 Σ 42 ngày ÷ 2 lượt = **21 ngày/lượt**; HC02 2.800.000 đ ÷ 2 vị trí = **1.400.000 đ/vị trí** (còn tạm ứng chưa quyết toán và ĐNTT chưa duyệt → chưa đủ); HC03 cohort 09: 1 nghỉ ÷ 2 = **50%** (1 người chưa xác định → chưa đủ). Cả ba **chỉ xem trước**; điểm chính thức vẫn chấm tay đến khi phiên bản chọn `adoption = source`.

**Kiểm thử:** `HcnsRecruitmentKpiTest` 8/8 (297 assertions); HCNS + tuyển dụng 80 test, `tests/Feature/Hr` 125 test — chỉ 2 lỗi baseline `PayrollFlowTest`; `tests/Feature/Technical` 408 — 10 lỗi trùng baseline. UI 8012 (desktop + 390 px): tuyển dụng 31/31 + 4/4, HC09/HC07 29/29, HC06 35/35, lương 21/21.

**Tệp thêm:** migration `2026_10_09_100000_hcns_kpi_recruitment_sources.php`; `RecruitmentKpi`, `Sources/{RecruitmentTimeSource,RecruitmentCostSource,ProbationQuitSource}`, `RecruitmentKpiController`, view `hr/recruitment/kpi/{request,hire,cost,_style}`; sửa `RecruitmentController` (dấu duyệt, chặn xóa/đổi yêu cầu khi đã gắn KPI, không giảm số lượng dưới suất đang dùng), `RecruitmentAttachmentService/Controller` (thực thể `hires`), `HcnsKpiChecklist`, `HrKpiEvaluator`, `HrKpiController`, checklist/quy tắc/panel, hồ sơ nhân viên; test `HcnsRecruitmentKpiTest`.

**Áp local:** nay **4 migration** theo thứ tự `160000`, `190000`, `200000`, `2026_10_09_100000` (thêm 10 cột nullable vào `hr_recruitment_requests`; bảng `hr_recruitment_hires`, `hr_recruitment_costs`). Sao lưu thêm `hr_recruitment_requests`, `hr_recruitment_candidates`, `hr_recruitment_offers`, `payment_requests`, `advance_settlements`. Không sửa dữ liệu cũ: yêu cầu/ứng viên/thư mời cũ (local có 1 yêu cầu, 17 ứng viên, 10 thư mời) không tự vào KPI, không tạo lượt tuyển từ ngày dự kiến. Rollback `2026_10_09_100000` xóa 2 bảng mới, giữ cột nullable.

**Còn thiếu / quyết định cần chốt:**

- **H-D02/H-D08 (HC01–HC03):** ngưỡng 23–25 ngày/500.000–1.000.000 đ/≤10% quy đổi thế nào; cohort (tháng nhận việc / tháng thực chi / tháng kết thúc thử việc là phương án nháp); mốc bắt đầu HC01 (giờ HCNS tiếp nhận hay giờ duyệt yêu cầu); chi phí chung nhiều vị trí chia thế nào (hiện nhập tay số phân bổ); nghỉ do công ty chấm dứt có tính “nghỉ trong thử việc” không; có tính chi phí VP không có duyệt chi không.
- **H-D03:** “Không phát sinh” loại khỏi mẫu số (như hiện tại) hay tính 0/100%.
- **H-D04 (HC06), H-D01 (trọng số/ngày hiệu lực), H-D07 (người kiểm mặc định).**
- HC04, HC05, HC08 chưa có nguồn (P6, chờ H-D05/H-D06).

## Kết quả 09/10/2026 (lần 4) — áp local 8001 (Claude)

Phạm vi được cho phép: cập nhật DB local `crm_egosolar_test` của web 8001 (sao lưu, 4 migration HCNS, bộ mẫu riêng). **Chưa được** duyệt cấu hình áp dụng chung hay lương thật — không làm. Bằng chứng/nhật ký riêng tư: `outputs/claude-kpi-agent/hcns-local-20261009/` (gitignore).

**1. Môi trường và sao lưu.** 8001 = `artisan serve` từ đúng repo, `.env` local: DB `crm_egosolar_test`, cache/session file, mail `log`, queue `sync`. Không chuyển 8001 sang `egosolar_test`. Sao lưu ngoài git tại `D:/Source Dev/crm.egosolar.com.vn/backups/hcns-local-20261009/`: dump đầy đủ DB (sha256 trong `SHA256SUMS.txt`), `storage/app` + `public/uploads` (80 tệp), thử khôi phục vào DB tạm: 381/381 bảng, 32.696 dòng, 0 lệch (DB tạm đã xóa); cách khôi phục ở `KHOI-PHUC.md`. Baseline chỉ đọc (`baseline.php`): băm từng bảng + lương Excel 08/2026 (kỳ #1 nháp, 12 dòng, gross 117.552.561, net 106.049.961, KPI 22.864.647,56), cấu hình kỹ thuật (3), lịch bảo trì 134, việc 130, tuyển dụng (1 yêu cầu, 17 ứng viên, 10 thư mời), tài khoản Admin (#1 khóa, #2, #17, #85: role + dấu mật khẩu), role/quyền.

**2. Migration.** Kiểm nội dung/phụ thuộc rồi chạy riêng từng tệp bằng `--path`, đúng thứ tự: `2026_10_08_160000` (batch 244), `2026_10_08_190000` (245), `2026_10_08_200000` (246), `2026_10_09_100000` (247). Không chạy migration khác (sau đó `migrate:status` không còn tệp chờ), không fresh/refresh/wipe/seed. So baseline sau áp: 0 bảng dữ liệu cũ đổi, chỉ thêm 4 dòng `migrations` và 10 bảng mới. `hcns:secure-task-attachments` chạy thử: 0 thủ tục HCNS, 0 tệp cần chuyển — **không** chạy `--apply`, 369 tệp việc cũ không đụng.

**3. Bộ mẫu `HCNS-LOCAL-DEMO-20261009`** (`local-demo.php seed|show|cleanup`; chạy lại không tạo trùng, không đổi giờ/kết quả). Tài khoản `hcns-local-demo.{nhaplieu,kiemtra}` (role `hr` sẵn có), `hcns-local-demo.khongquyen` (role `sales`), 5 “nhân viên mới” bị khóa đăng nhập; mật khẩu ngẫu nhiên chỉ ghi trong hướng dẫn riêng tư ngoài git. Hồ sơ lương 0 đ, bộ `hcns`, hiệu lực từ 09/2026 cho nhaplieu và kiemtra (không lọt vào kỳ 08/2026; không tạo kỳ lương). Nguồn: 9 thủ tục HC09 (đúng hạn, trễ, quá hạn chưa nộp, nháp, 2 chờ xác nhận, gia hạn trước/sau hạn, hủy), 4 hồ sơ HC07 (đạt, sai nơi nhận, chờ kiểm, chưa gửi), HC06 (1 vật phẩm + 3 phiếu VPP, 3 tài sản mẫu + 1 đợt kiểm kê chỉ gồm tài sản mẫu, 4 sự cố), HC01–HC03 (3 yêu cầu, 7 ứng viên, 6 lượt tuyển, 5 chứng từ ĐNTT/tạm ứng/hoàn tiền ghi thẳng trạng thái — không chạy luồng thanh toán — và 5 khoản phân bổ). Không có phiên bản quy tắc, điểm tay hay ảnh chụp KPI nào được tạo. Đã thử `cleanup` hai lần (kể cả với bản ghi/tệp tạo từ giao diện): so baseline về 0 bảng dữ liệu cũ đổi; PHP trên máy không xóa được thư mục rỗng nên lệnh in danh sách để xóa tay.

**4. Kiểm tra thực tế trên 8001** (`ui-local.cjs`, Chrome, desktop 1366 + điện thoại 390 px): **80/80 đạt** — đăng nhập 3 vai trò, menu; HC09 lưu nháp/xác nhận/trả bổ sung/gửi lại (giữ lần gửi đầu)/chặn tự xác nhận; HC07 gửi kiểm/xác nhận 3 mục/chặn POST; HC06 nhập phiếu VPP có KPI, tải + xem trước biên bản riêng tư, xác nhận, trả bổ sung, kiểm kê 3 tài sản, người báo nghiệm thu sự cố; HC01–HC03 ghi nhận việc thực tế + chứng cứ PDF xem trước, chống gắn trùng/vượt thực chi, hoàn tiền âm, tạm ứng theo quyết toán, trả bổ sung khoản chi, xác nhận nghỉ thử việc, người chưa xác định không suy từ tài khoản; trang KPI lọc người/tháng, ba lớp số, link nguồn mở được; sales 403 ở mọi trang/tệp HCNS; trang lương HR 08 và 10/2026 mở bình thường (không chụp ảnh lương thật); 390 px không tràn ngang. `laravel.log` không ghi lỗi mới trong lúc thử. Lỗi tìm thấy: không có lỗi 500/route/schema/quyền; 2 dòng sai ở lần chạy đầu là kỳ vọng sai của kịch bản (đã sửa, chạy lại sau khi dọn + tạo lại bộ mẫu).

**Thêm trong source:** bộ lọc **Nhân viên** trên `/nhan-su/kpi` (tham số `employee`; người không thuộc bộ KPI kỳ đó → hiện tất cả) — `HrKpiController::index`, `hr/kpi/index.blade.php`; test `HcnsKpiChecklistTest::test_department_page_filters_one_employee`.

**5. Đối chiếu tính tay** (`reconcile-local.php` → `reconcile-local.md`, chỉ đọc), nhân viên mẫu, kỳ 10: HC09 **3/6 = 50%** (1 chờ xác nhận, 1 còn hạn, 1 loại) → xem trước 50 ÷ 80 = 62,5% đạt · 6,94 điểm tạm; HC07 **2/3 = 66,67%**; HC06 cấp phát **2/2**, kiểm kê **1/1**, sự cố **2/4**, không có tỷ lệ chung (H-D04); HC01 **Σ 42 ngày ÷ 2 lượt = 21 ngày/lượt**; HC02 **2.800.000 đ ÷ 2 vị trí = 1.400.000 đ/vị trí**, thiếu dữ liệu (tạm ứng chưa quyết toán, ĐNTT chưa duyệt). Kỳ 09: HC03 **1 nghỉ ÷ 2 = 50%**, thiếu dữ liệu (1 người chưa xác định). Khớp bộ đo từng dòng. Điểm chính thức: “Chưa có — chưa nhập”; tổng **chưa đủ** — đúng quy tắc, không nhập điểm tay để che.

**6. Test tự động** chỉ trên `egosolar_test` (dọn demo 8012 trước, dựng lại sau): HCNS/lương/tuyển dụng 94 test, `tests/Feature/Hr` **126 test — chỉ 2 lỗi baseline `PayrollFlowTest`**. Dựng lại demo 8012 và chạy lại UI: 29/29, 35/35, 31/31, 21/21, 4/4. Ca duyệt/chốt lương, khóa kỳ chỉ thử trên DB test này.

**So baseline cuối** (`compare-final.json`): 0 bảng dữ liệu cũ đổi; Admin, role, mật khẩu người dùng cũ, kỳ lương 08/2026, cấu hình kỹ thuật giữ nguyên; chỉ thêm dòng của bộ mẫu.

**Chưa kiểm:** đăng nhập bằng Admin cũ (không có mật khẩu, không reset — chỉ xác minh tài khoản không đổi qua baseline); giao thủ tục HCNS mới trên 8001 (role `hr` không có quyền giao việc; cần Ban Giám đốc/Admin); chấm KPI tay, duyệt quy tắc, duyệt/chốt lương trên local (không được phép — đã thử trên 8012).

**Còn chờ chốt:** H-D01 (trọng số/ngày hiệu lực), H-D02/H-D08 (quy đổi và cohort HC01–HC03), H-D03 (không phát sinh, mốc đo), H-D04 (gộp HC06), H-D05/H-D06 (HC04/HC08), H-D07 (người kiểm mặc định). Vì local chưa có phiên bản được duyệt, HC09/HC07 trên 8001 cũng chỉ xem trước. HC04, HC05, HC08 chưa có nguồn — **HCNS chưa hoàn tất**.

## Kết quả 09/10/2026 (lần 5) — HC04 Độ chính xác tính lương (Claude)

**1. Nguồn lương.** Nguồn kiểm duy nhất là **Bảng lương Nhân sự** (`hr_payroll_periods/lines`, một dòng = một nhân viên/kỳ). Phiếu lương HR là cùng dòng đó. `/finance/salary` đã chuyển hướng sang Bảng lương Nhân sự, bảng cũ `payrolls` (local còn kỳ 04/2026) chỉ mở bằng `?legacy=1` — không cộng, không coi tương đương. Bảng `payroll_slip_*` không có code dùng. Chi tiết SOURCE-MAP mục HC04.

**2. Luồng đối soát (ngay tại Bảng lương, không thêm menu).** Bảng lương tháng → khối “Đối soát độ chính xác (KPI HC04)” → “Lập đợt”: kỳ (trang đang xem), phạm vi (toàn bộ / phòng ban / chọn dòng), HCNS chịu KPI, người nhập, người kiểm độc lập. Trang đợt: mỗi dòng chọn **Đúng / Có lỗi / Chưa xác định**, đánh dấu khoản đã kiểm (10 khoản: công, lương chính, KPI, phụ cấp, công tác/tăng ca, khác, BH, thuế, khấu trừ, thực lĩnh), ghi chú; lỗi có khoản, loại, nội dung, số đúng/số đã ghi, liên kết nguồn, chứng cứ (riêng tư, xem trước), cách xử lý (chưa xử lý / đã sửa trên bảng lương / kỳ sau / không sửa). Lưu nháp → gửi → người kiểm (khác HCNS chịu KPI, khác người nhập/người gửi) chấm 3 mục rồi xác nhận hoặc trả bổ sung. Người, giờ, lịch sử sửa (đợt, dòng, lỗi, tệp) và lịch sử kiểm tra đều lưu; gỡ lỗi chỉ đánh dấu kèm lý do. Số tiền bảng lương không bị đổi.

**3. Checklist và bộ tính.** HC04 dùng chung `HrKpiEvaluator`/`HcnsKpiChecklist` (adapter `PayrollAccuracySource`). Mỗi dòng checklist: kỳ lương, mã đợt, số khoản đã kiểm, giờ kiểm, người nhập, trạng thái kiểm tra, người/giờ xác nhận, lý do được/chưa được tính, link mở đúng dòng trong đợt (chỉ người có quyền bảng lương). Tỷ lệ chỉ hiện khi đơn vị, mẫu số và dữ liệu hợp lệ: chưa có đợt → “chưa kiểm”, không 100%, không được đánh “không phát sinh” khi có bảng lương; dòng chưa kiểm/chưa xác định/dòng lương đổi sau khi kiểm/đợt chưa xác nhận → chưa đủ; mẫu số 0 → không có tỷ lệ. Phiên bản chưa chọn `source` → chỉ xem trước; đã chọn mà thiếu → “chưa đủ”, không quay về điểm tay.

**4. Khóa và lịch sử.** Kỳ KPI chứa đợt đã chốt lương → mọi đường ghi bị chặn (kết quả, lỗi, gỡ lỗi, tệp, người/phạm vi, gửi/trả/xác nhận, hủy); ảnh chụp KPI hiện có lưu khối HC04 lúc chốt. Đợt không xóa được (chỉ hủy trước khi gửi, có lý do).

**5. Ví dụ tính tay (bộ mẫu 8001, KPI tháng 10 ← bảng lương MẪU 09/2026, 8 dòng đã kiểm, 3 dòng có lỗi, 2 lỗi đã sửa):** quy tắc nháp (đơn vị dòng, mọi lỗi tính) **5/8 = 62,5%** → xem trước 62,5 ÷ 99 = 63,13% đạt · 7,01 điểm tạm; chính thức “chưa có”. Cùng dữ liệu theo phương án khác: đơn vị khoản 76/80 = 95%; dòng + bỏ lỗi đã sửa trước khi duyệt 6/8 = 75%; khoản + bỏ lỗi đã sửa 78/80 = 97,5%. Ví dụ Word: 99 dòng đúng/100 dòng đã kiểm = 99% (số đo).

**H-D05 — đề xuất để người dùng chọn (chưa áp thành chính sách):**

| Câu hỏi | Đề xuất | Lý do / ví dụ |
|---|---|---|
| Nguồn | Bảng lương Nhân sự | Nguồn chính thức duy nhất đang chạy; bảng cũ Tài chính chỉ để đối chiếu lịch sử |
| Đơn vị | **Dòng lương (một nhân viên/kỳ)** | Dễ hiểu, khớp “tỷ lệ tính lương chính xác”: 1 nhân viên sai/100 = 99%. Đơn vị khoản làm loãng lỗi (1 lỗi/10 khoản), demo 95% thay vì 62,5% |
| Kỳ KPI | **Tháng sau kỳ lương** | HCNS lập lương tháng M trong tháng M+1; tránh vòng lặp (KPI tháng M của HCNS nằm trong bảng lương M); còn thời gian ghi lỗi phát hiện sau chi trả |
| Độ phủ | Kiểm đủ mọi dòng của kỳ (hoặc kiểm mẫu nếu công ty quyết định) | Kiểm mẫu làm tỷ lệ phụ thuộc phần được chọn |
| Lỗi đã sửa | Chọn một: tính mọi lỗi (đo độ chính xác bản tính HCNS lập) hoặc bỏ lỗi đã sửa trước khi duyệt (đo bảng lương đã chi) | Demo: 62,5% so với 75% |
| Người kiểm (H-D07) | Kế toán hoặc Ban Giám đốc, khác HCNS lập lương | Hệ thống đã chặn tự xác nhận |
| Quy đổi (H-D02) | Baseline Thực tế ÷ 99, trần 120 | Chưa duyệt |

**Kiểm thử:** `HcnsPayrollAccuracyKpiTest` **13/13** (154 assertions) trên `egosolar_test`; `tests/Feature/Hr` xem STATUS (chỉ lỗi baseline `PayrollFlowTest`). Luồng UI cũ 8012 chạy lại đạt. 8001: trình duyệt **32/32** (người nhập, người kiểm, người không có quyền; desktop + 390 px) + kiểm lại 1/1; đối chiếu tính tay `reconcile-local-hc04.md` khớp từng dòng.

**Lỗi phát hiện và đã sửa:** (1) trang đợt lỗi 500 khi hiện lại sau một lần lưu bị chặn (ô ghi chú của hai form trùng tên) → đổi tên ô, thêm test; (2) route xem tệp HCNS dùng chung (VPP, kiểm kê, HC04) bị lưu làm “trang trước” → sau khi bấm Xem tệp, thông báo lỗi/“quay lại” mở tệp thay vì hồ sơ → `HcnsSourceFiles::respond` không lưu tệp làm trang trước, thêm test.

**Áp local 8001:** sao lưu DB + tệp mới (`backups/hcns-local-20261009/crm_egosolar_test-before-hc04-20261009-161543.sql`, khôi phục thử 391/391 bảng, 0 lệch); áp riêng migration `2026_10_09_160000_hcns_kpi_payroll_audits` (batch 248); 0 bảng dữ liệu cũ đổi. Bộ mẫu thêm kỳ lương **MẪU** 09/2026 (chỉ tài khoản mẫu, số giả, nháp — không tính lại, không duyệt) và 3 đợt đối soát. Kỳ 08/2026 (Excel) không đổi.

**Tệp:** mới — migration `2026_10_09_160000_hcns_kpi_payroll_audits.php`, `PayrollAudits`, `Sources/PayrollAccuracySource`, `PayrollAuditController`, view `hr/payroll/{audit-show,_audits,_audit-error-fields}`, test `HcnsPayrollAccuracyKpiTest`; sửa — `config/hcns_kpi.php` (khoản lương, loại lỗi, xử lý, mục kiểm, quy tắc nháp `payroll_accuracy`), `HcnsKpiChecklist`, `HcnsSourceFiles`, `PayrollController` (khối đối soát), `routes/hr.php` (`hr.payroll.audits.*`), view `hr/payroll/index`, `hr/kpi/_hcns-checklist`, `hr/kpi/_hcns-rules`.

**Còn lại:** H-D05 (và H-D02/H-D07 cho HC04) chưa chốt → HC04 chỉ xem trước. **HC05, HC08 chưa có nguồn — HCNS chưa hoàn tất.** Chưa commit/push/deploy.

Theo dõi tiến độ bằng checkbox và bằng chứng tại [PLAN](PLAN.md). Bản đồ chín tiêu chí tại [SOURCE-MAP](SOURCE-MAP.md). Lệnh giao việc tại [CLAUDE-PROMPT](CLAUDE-PROMPT.md).
