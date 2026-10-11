# Kế hoạch triển khai KPI HCNS

Ngày lập: 08/10/2026. Đọc PROJECT, SOURCE-MAP và skill trước khi triển khai. Chỉ đánh dấu hoàn tất khi có bằng chứng; có form nhưng chưa có nguồn/xác nhận không được tính là xong.

Cập nhật 08/10/2026 (Claude): đã làm P0, P1 (phần nền), **P2 HC09**, **P3 HC07**, P7/P8 cho hai nguồn này. P4–P6 chưa làm. Chưa commit/deploy, chưa áp migration lên DB local `crm_egosolar_test`. Bằng chứng riêng (gitignore): `outputs/claude-kpi-agent/hcns-kpi-20261008/`.

Cập nhật 09/10/2026 lần 5 (Claude): **HC04 có nguồn** — đợt đối soát độ chính xác ngay trong trang Bảng lương HR (nhập từng dòng, lỗi + chứng cứ, gửi → người kiểm độc lập xác nhận/trả), nối checklist/bộ chấm chung, khóa theo kỳ đã chốt, chỉ số đo/xem trước (H-D05 chưa chốt). Test trên `egosolar_test`, rồi sao lưu và áp **riêng** migration HC04 lên 8001, thêm phần HC04 vào bộ mẫu, thử trình duyệt 32/32. HC05, HC08 để bước sau.

Cập nhật 09/10/2026 lần 4 (Claude): người dùng cho phép cập nhật DB local 8001 → **đã sao lưu, áp đúng 4 migration HCNS, tạo bộ mẫu `HCNS-LOCAL-DEMO-20261009` và thử trên trình duyệt 80/80** (mục “P9 — Áp local 8001”). Chưa duyệt quy tắc/lương thật; trên local mọi nguồn HCNS đều chỉ xem trước. Bằng chứng: `outputs/claude-kpi-agent/hcns-local-20261009/`.

Cập nhật 09/10/2026 lần 3 (Claude): **bộ tính không còn âm thầm quay về điểm nhập tay** khi phiên bản đã chọn nguồn mà nguồn chưa sẵn sàng; “Không phát sinh” trên bảng KPI HCNS bắt buộc lý do; **P5 HC01–HC03 có nguồn** (chỉ số đo/xem trước, chưa điểm chính thức). Đối chiếu: `outputs/.../reconcile-20261009-p5.md`.

Cập nhật 09/10/2026 (Claude): đối chiếu tính tay HC09/HC07 (có ca gia hạn) khớp từng dòng; **P8 nền tính lương** (phiên bản trọng số/target/bậc theo kỳ, ảnh chụp khi chốt, minh chứng HC09 riêng tư, thử UI lương HR đầy đủ); **P4 HC06** có nguồn cho cả ba nhóm nhưng **chưa có điểm** vì cách gộp H-D04 chưa chốt. Vẫn chưa commit/deploy, chưa áp migration lên local. Đối chiếu: `outputs/.../reconcile-20261009.md`.

## P0 — Xác nhận baseline

- [x] Đọc quy định repo, thay đổi đang có và kiểm tra có phiên khác đang ghi cùng tệp. Không reset source hoặc ghi đè công việc chưa rõ chủ sở hữu.
  - Phiên ngang hàng duy nhất đang idle. Tệp tuyển dụng/`routes/hr.php` sửa lần cuối 14:00–14:15 bởi phiên trước. Sao lưu bản gốc mọi tệp sẽ sửa vào `baseline/`; diff sau sửa chỉ thêm, ngoại trừ phần chuyển logic quyền Công việc sang service (giữ nguyên hành vi).
- [x] Đọc lại Word HCNS; đối chiếu chín mã/target với SOURCE-MAP, route thực được nạp, schema và các controller/service đang dùng.
  - `route:list` cho `/chat/tasks*`, `/nhan-su/quy-trinh-giao-nhan-ho-so*`, `/nhan-su/kpi*`. Schema thật `tasks`, `task_activity_logs`, `hr_document_handover*`, `hr_kpi_*` trên cả `egosolar_test` và local (chỉ đọc). Chín tiêu chí `hr_kpi_criteria` khớp SOURCE-MAP; trọng số 11,12 + 8 × 11,11.
- [x] Kiểm tra nguồn HCNS, quyền thực tế và toàn bộ đường ghi của từng module; phân biệt hai luồng tuyển dụng hiện có bằng route/model đang chạy.
  - HC09/HC07 đã kiểm hết đường ghi (xem SOURCE-MAP mục “Phát hiện khi triển khai”). Hai luồng tuyển dụng chưa đụng (P5).
- [x] Ghi baseline điểm/cấu hình và chạy các test liên quan trên DB test. Không gọi lỗi “có từ trước” nếu chưa đối chứng.
  - Trước khi sửa: `tests/Feature/Hr` 73 test, 2 lỗi `PayrollFlowTest` (redirect) — `baseline-tests-hr.log`. Một lần chạy lại có thêm lỗi trùng email Faker ngẫu nhiên ở `AttendanceCorrectionAccessTest`. Local: 0 điểm KPI HCNS, 0 hồ sơ lương HR, 130 việc, 1 hồ sơ giao nhận.
- [x] Xác nhận DB test, server, cache, session, storage/upload đều cô lập; không sửa `.env` hoặc server 8001.
  - Test: `egosolar_test`, cache/session array, `Storage::fake`. Server UI 8012 chạy `php -S` + `ui-test/router.php` ép DB test, cache array, session cookie, mail log và `LARAVEL_STORAGE_PATH` riêng; đối chiếu sau chạy: không ghi view/cache/storage dùng chung (mục ghi mới đều do web 8001/phpunit).

## P1 — Nền dữ liệu, quy tắc và xác nhận

- [x] Xác định owner/reviewer bằng ID, công ty, phòng ban và liên kết hồ sơ nguồn. Thiếu liên kết phải hiện thiếu; không nối bằng tên.
  - HC09: `assignee_id`/`approver_id`/`requester_id`, `company_id` = công ty khóa. HC07: `processor_id`, `reviewer_id`, `routed_to_user_id` (+ ghi chú nơi nhận ngoài), `company_id`. Thiếu trường → “Chưa gửi kiểm — thiếu …”.
- [x] Thiết kế lịch sử giao/hạn/gửi đầu/gửi lại/trả bổ sung/xác nhận; không suy mốc thật từ `updated_at` hoặc backfill mọi mốc bằng cùng một giờ.
  - `tasks`: `original_due_at`, `draft_saved_at`, `first_submitted_at` (không ghi đè), `last_submitted_at`, `submission_count`, `approved_by/at`, `cancelled_*`; nhật ký `due_changed`/`draft_saved`/`cancelled`. HC07: `hr_kpi_source_reviews` + `hr_kpi_source_review_events`. Không backfill dữ liệu cũ.
- [x] Thiết kế một kết quả nguồn chung: giá trị thực, tử/mẫu số, hồ sơ tính/loại/thiếu, phiên bản quy tắc, trạng thái, người/giờ xác nhận và lý do. Điểm có thể null.
  - Adapter `AdminDeadlineSource`, `DocumentAccuracySource`: `rows()` (kpi_state earned/lost/pending/waiting/excluded/missing + lý do + link) và `summarize()` (tử/mẫu, value nullable, data_status).
- [x] Tách trạng thái thiếu dữ liệu, chờ xác nhận, chưa chốt quy tắc, còn hạn, quá hạn và không phát sinh đã xác nhận. Định nghĩa cách xử lý N/A bằng cấu hình, không mặc định 100% khi mẫu số 0.
  - Mẫu số 0 → “không có hồ sơ nguồn”, không 100%. Khi đã áp dụng quy tắc: N/A chỉ khi người chấm đã đánh “Không phát sinh”.
- [x] Thêm cơ chế phiên bản cấu hình HCNS và xem trước; bảo toàn cấu hình/điểm lịch sử. Trọng số, ngày hiệu lực và quy đổi chưa chốt chỉ nằm ở bản nháp.
  - `hr_kpi_rule_versions` (draft/applied + kỳ hiệu lực) cho **quy tắc đo nguồn** HC09/HC07; tab Cài đặt có xem trước từng người. Chưa version hóa trọng số/target/bậc (vẫn sửa trực tiếp như cũ) — còn mở.
  - 09/10: đã version hóa trọng số/target/bậc HCNS (cột `config` cùng phiên bản); xem P8.
- [x] Quy định ưu tiên nguồn tự động và nhập tay có audit/minh chứng; không để `achievement` cũ đè nguồn âm thầm, không cộng cùng hồ sơ hai lần.
  - Chưa duyệt quy tắc: điểm chính thức = nhập tay (như cũ), checklist ghi rõ. Đã duyệt: tiêu chí có nguồn bỏ qua Thực tế/% đạt nhập tay (không lưu khi gửi form), chỉ giữ “Không phát sinh” + ghi chú. Test `test_applied_rules_take_source_score_and_ignore_manual_entry`.
  - 09/10 lần 3: một hàm chung `HcnsKpiChecklist::sourceOfficial()` quyết định tiêu chí nào phiên bản đã chọn nguồn (HC09/HC07: phiên bản áp dụng; HC06: đã chọn cách gộp; HC01–HC03: `adoption = source`). Đã chọn nguồn → mọi trường hợp thiếu/chờ xác nhận/nguồn chưa sẵn sàng đều “chưa đủ”, không dùng số nhập tay. “Không phát sinh” chỉ nhận khi nguồn không có hồ sơ nào (HC02: không có cả chi phí lẫn vị trí) và bắt buộc lý do. Test `test_evaluator_reports_incomplete_instead_of_falling_back_and_na_needs_reason`.
- [x] Kiểm tra quyền bằng cơ chế hiện có. Không tự cấp thêm quyền trưởng phòng; lưu ngoại lệ theo quy tắc có thật. Người làm không tự xác nhận phần KPI của mình.
  - Không thêm role/permission. Người kiểm HC09 = `approver_id` chỉ định (được trả bổ sung việc HCNS của chính mình được giao kiểm); HC07 = `reviewer_id` hoặc Ban Giám đốc/Admin (`PayrollAccess::canApprove`), không bao giờ là người xử lý. Duyệt quy tắc: người khác người lập nháp.

Migration `2026_10_08_160000_add_hcns_kpi_source_tracking.php` chỉ thêm cột nullable/bảng mới, đã chạy trên `egosolar_test`. **Chưa áp lên local** — đề xuất trong PROJECT.

## P2 — HC09: Thủ tục hành chính đúng hạn, làm trước

- [x] Bổ sung tại công việc HCNS hiện có: loại nghiệp vụ, owner, hạn, liên kết hồ sơ/minh chứng và reviewer. Không đưa tất cả việc CRM vào KPI HCNS.
  - Form giao/sửa việc có khối “Thủ tục hành chính / pháp lý HCNS — tính KPI HC09”: loại thủ tục, giờ hạn, người kiểm (≠ người làm). Chỉ `task_type=hcns_admin` vào KPI.
- [x] Hoàn thiện luồng giao → nhập/lưu nháp → gửi → xác nhận hoặc trả bổ sung. Lưu riêng lần gửi đầu và mọi lần gửi lại; giữ tương thích công việc kỹ thuật dùng chung.
  - Thêm “Lưu nháp”, “Hủy thủ tục” có lý do (không xóa), ghi người/giờ duyệt; chặn tự duyệt, chặn sửa form đổi trạng thái/đổi loại, chặn cập nhật nhanh mở lại việc đã nộp. Việc thường/kỹ thuật giữ hành vi cũ (test `test_non_hcns_tasks_keep_old_behaviour`; bộ Technical không phát sinh lỗi mới).
- [x] Đưa việc còn hạn, quá hạn chưa nộp và chờ kiểm tra vào checklist. Không loại việc chưa xong để tăng tỷ lệ đúng hạn.
- [x] Hiện phép đo đúng hạn/tổng việc theo bản nháp có giải thích kỳ và mốc; chưa chốt mốc/cohort thì hiện “Quy tắc chưa chốt”, không có điểm chính thức.
  - Ví dụ UI: 2/4 = 50% → xem trước 50/80 = 62,5% · 6,94 điểm (tạm). Khối “Quy tắc đang xem” liệt kê phương án.
- [x] Gắn ghi chú ai nhập/ai xác nhận/khi nào tính và link từ checklist về đúng việc. Upload/preview tại nguồn khi cần minh chứng.
  - Thẻ HC09 trên trang việc + checklist có link (ẩn link nếu người xem không mở được). Minh chứng dùng tệp kết quả sẵn có của module Công việc (disk public cũ — xem giới hạn).
- [x] Test đúng hạn, đúng biên hạn, muộn, nháp, gửi lại, người duyệt chậm, hủy, đổi người/hạn và qua tháng. Không áp hạn 24 giờ của kỹ thuật.
  - `HcnsAdminDeadlineKpiTest` **11/11, 88 assertions**: biên đúng giờ hạn = đúng hạn, trễ 1 phút, quá hạn chưa nộp ở mẫu số, duyệt chậm 10 ngày vẫn đúng hạn, hủy trước/sau hạn, gia hạn trước/sau hạn gốc, đổi người, kỳ tháng 11 tách, công ty khác, việc thường, tự xác nhận, 8/10 = 80%.
- [x] Thử UI trên DB test với người nhập/người kiểm/người không có quyền, desktop và điện thoại.
  - `ui-test/ui-flow.cjs` **27/27**: Giám đốc giao → HCNS lưu nháp/gửi → người kiểm duyệt/trả → HCNS gửi lại → Giám đốc hủy; Sales 403; 390 px không tràn ngang. Ảnh `ui-test/shots/01–05, 08, 10–11`.

## P3 — HC07: Phân loại và giao nhận hồ sơ

- [x] Tại giao nhận hồ sơ hiện có, lưu người xử lý, hồ sơ/loại, nơi nhận đúng, mốc giao/nhận và kết quả kiểm tra độc lập.
  - Form có khối theo dõi KPI HC07; trang chi tiết có khối “Kiểm tra phân loại & luân chuyển”: người xử lý Lưu nháp/Gửi kiểm tra; người kiểm chấm 3 mục Đạt/Không đạt (không đạt bắt buộc ghi lỗi) hoặc Trả bổ sung.
- [x] Upload/preview đúng quyền hồ sơ; rà soát nơi đang dùng public storage trước khi tái sử dụng. Có tệp hoặc trạng thái “đã nhận” chưa đủ chứng minh phân loại đúng.
  - Tệp mới lưu disk `local` riêng tư, chỉ mở qua route xem/tải (cột `disk`; tệp cũ public giữ cách cũ). Bổ sung modal “+ Thêm file” và “Xem trước” vốn thiếu trên trang chi tiết.
- [x] Phân biệt độ chính xác HC07 với đúng hạn HC09; một hồ sơ phục vụ hai tiêu chí chỉ khi quy tắc cho phép và từng tiêu chí có lý do riêng.
  - HC07 chỉ đo độ chính xác qua kiểm tra; không dùng hạn. Không nối hồ sơ giao nhận vào HC09.
- [x] Checklist mở đúng hồ sơ, hiện đạt/không đạt/chưa xác nhận cùng người/giờ kiểm. Test sai nơi nhận, thiếu kiểm tra, đổi tệp, trả bổ sung, URL trực tiếp và khác công ty.
  - `HcnsDocumentAccuracyKpiTest` **7/7, 76 assertions**: người kiểm phải khác người xử lý, tệp riêng tư, nháp/gửi/xác nhận, không đạt cần ghi lỗi, sửa sau xác nhận phải gửi lại kể cả đổi về như cũ, đổi tệp làm bản gửi hết hiệu lực, trả bổ sung giữ lần gửi đầu, không xóa/bỏ theo dõi sau khi gửi, phạm vi người/kỳ/công ty, 1 đạt + 1 không đạt + 1 chờ = 1/2.

## P4 — HC06: VPP, tài sản và sự cố văn phòng

Đã có nguồn ba nhóm (09/10). **Chưa có điểm HC06**: cách gộp H-D04 chưa chốt nên checklist chỉ hiện số đo từng nhóm, điểm chính thức vẫn chấm tay. Migration `2026_10_08_200000_hcns_kpi_hc06_sources.php` (chỉ thêm cột nullable/bảng mới) đã chạy trên `egosolar_test`.

- [x] Giữ ba nhóm nguồn riêng: cấp phát, kiểm kê và xử lý sự cố; xác định owner và tránh trùng giữa tài sản HC và tài sản tài chính.
  - Owner bằng ID: phiếu VPP `handler_id`, đợt kiểm kê `handler_id`, sự cố `handler_id` (chọn khi phân loại, mặc định người phân loại). Đợt kiểm kê chụp danh sách từ **một** danh mục (HC & Vận hành hoặc Tài sản Tài chính) — không nối theo tên, không cộng hai danh mục.
- [x] Bổ sung mốc thực và xác nhận cấp phát/kiểm kê còn thiếu tại trang hiện có. Không dùng thời gian tạo dòng để giả thành toàn bộ SLA.
  - VPP (modal Cấp phát → ô “Theo dõi KPI HC06”): người yêu cầu, người kiểm ≠ người xử lý, giờ HCNS nhận yêu cầu (≤ hiện tại), hạn, giờ giao thực tế. Phiếu cũ/không đánh dấu không vào KPI; không backfill. Trang chi tiết: sửa mốc/người, biên bản ký nhận riêng tư + xem trước, Lưu nháp/Gửi kiểm/Gửi lại, người kiểm chấm 3 mục hoặc trả bổ sung, lịch sử. Người yêu cầu có quyền trang Nhân sự tự bấm “Tôi đã nhận” (không thay biên bản).
  - Kiểm kê (tab Tài sản công ty → “Đợt kiểm kê tài sản · KPI HC06”): lập đợt theo kỳ, ghi kết quả từng tài sản (đổi vị trí/hỏng/mất bắt buộc ghi chú), biên bản riêng tư, gửi kiểm = báo hoàn tất, xác nhận 2 mục/trả bổ sung, hủy có lý do (chỉ trước khi gửi), lịch sử `hr_kpi_source_logs`.
- [x] Với sự cố, lưu phân loại/mốc và thời gian chờ; chỉ đo SLA khi đã xác định mức, lịch làm việc và ngưỡng. Khoảng SLA trong Word không tự thành một ngưỡng mới.
  - Lưu `handler_id`, `resolved_by`, `first_resolved_at` (giữ lần báo xong đầu), `waiting_since` + `approval_wait_minutes` (cộng dồn thời gian chờ Giám đốc duyệt chi), `cancelled_at`, `company_id`. Hạn dùng cài đặt mức sẵn có của trang sự cố; **giờ liên tục, chưa có lịch làm việc** (ghi rõ trên trang). Mốc đo/thời gian chờ là phương án nháp trong quy tắc.
- [x] Hiện từng nhóm/checklist riêng. Chưa chốt cách gộp thì không dùng thống kê sự cố thay cho cả tiêu chí tài sản/VPP.
  - Checklist HC06 chia 3 nhóm, mỗi nhóm số đo riêng, mỗi hồ sơ có link + trạng thái “được tính/chưa tính” + lý do. Quy tắc `assets.aggregation` = `unset` (mặc định) → không có tỷ lệ chung, không điểm xem trước, `sourceOverrides` bỏ qua HC06. Chỉ khi phiên bản **đã duyệt** chọn `pooled`/`average_groups` mới lấy điểm từ nguồn (test).
- [x] Test lịch làm việc, biên SLA, chờ, thiếu mốc, nhiều người, kiểm kê không phát sinh và chống đếm trùng.
  - `HcnsAssetsServiceKpiTest` **9/9, 207 assertions**: người kiểm ≠ người xử lý, hạn < giờ nhận, tệp riêng tư/tải đúng tên, người xử lý không tự xác nhận, biên giao = hạn là đúng hạn, trễ/kiểm không đạt vào mẫu số, đổi hạn/người/tệp sau xác nhận phải gửi lại (đổi về như cũ không sống lại), phiếu đã gửi không xóa, kỳ chốt khóa mọi đường sửa VPP/kiểm kê/phân loại sự cố (xử lý/nghiệm thu vẫn chạy), kiểm kê danh mục trống không lập được, hủy trước/sau hạn, thời gian chờ duyệt 120 phút, lần xử lý đầu vs cuối, phiếu không theo dõi/khác công ty/người khác không vào, gộp chung/trung bình nhóm = 66,67%. **Chưa có lịch làm việc** (H-D04) nên không test giờ làm việc.

## P5 — HC01/HC02/HC03: Tuyển dụng và thử việc

Đã có nguồn 09/10 (lần 3). **Chưa có điểm chính thức**: ngưỡng/cohort/quy đổi (H-D02, H-D08) chưa chốt → quy tắc `adoption = preview_only`, checklist chỉ hiện số đo + xem trước. Migration `2026_10_09_100000_hcns_kpi_recruitment_sources.php` (chỉ thêm cột nullable/bảng mới) đã chạy trên `egosolar_test`. Nguồn tuyển dụng chính: `RecruitmentController` (7 bước, route `/nhan-su/tuyen-dung/*`); `CandidateProcessController` cũ không dùng.

- [x] HC01: nối yêu cầu tuyển đã duyệt → ứng viên → ngày thực nhận việc đã xác nhận → nhân viên; lưu người phụ trách và nhiều vị trí/số lượng tuyển.
  - Yêu cầu tuyển: server ghi người/giờ duyệt (`approved_by/at`), trang “KPI tuyển dụng” (`/tuyen-dung/yeu-cau/{id}/kpi`) bật theo dõi, HCNS phụ trách/người kiểm bằng ID, **giờ thật HCNS tiếp nhận yêu cầu** (không dùng ngày tạo). Lượt tuyển (`hr_recruitment_hires`) = một suất trong số lượng: ứng viên phải gắn đúng yêu cầu bằng ID, thư mời chỉ cho **ngày dự kiến** (không tính), HCNS ghi **ngày nhận việc thực tế** + tài khoản nhân viên + chứng cứ → gửi kiểm → người kiểm (khác người phụ trách, khác chính nhân viên) chấm 3 mục. Không vượt số lượng; “không nhận việc”/“hủy” trả lại suất; hủy đợt tuyển hủy các lượt chưa nhận việc.
  - Số đo: bình quân số ngày lịch (tiếp nhận → nhận việc thực tế) của lượt đã xác nhận, theo tháng nhận việc. Lượt chưa nhận việc: “còn chờ” trong tháng, sau tháng thì loại (tính vào tháng nhận việc thật).
- [x] HC02: nối chi phí được xác nhận với đợt/vị trí; xử lý phân bổ, hoàn ứng/hoàn tiền và chống đếm tạm ứng cùng quyết toán. Không lấy tổng chi văn phòng làm chi phí tuyển dụng.
  - Gắn chứng từ module Đề nghị thanh toán (`payment_requests`) cho đợt tuyển với số phân bổ (`hr_recruitment_costs`). Hợp lệ: ĐNTT/phiếu chi **kế toán đã duyệt chi**; tạm ứng chỉ tính **số đã quyết toán** (`advance_settlements.actual_spent_amount`, không tính tiền tạm ứng); hoàn tiền trừ ra; chứng từ chưa duyệt/bị từ chối không tính. Một chứng từ không phân bổ vượt số thực chi cho mọi đợt (chặn khi lưu và báo thiếu nếu quyết toán thấp hơn số đã gắn); không gắn lại cùng chứng từ cho cùng đợt. Điều chỉnh/gỡ có lý do và lịch sử; sửa sau xác nhận phải gửi lại. Không dùng chi phí VP (`hr_office_expenses`, không có duyệt chi).
  - Số đo: chi phí thực chi đã kiểm (theo tháng thực chi) ÷ số lượt nhận việc đã xác nhận HC01 (theo tháng nhận việc) của cùng người phụ trách. 0 vị trí → chưa đủ; có chi phí mà 0 vị trí thì không được đánh “Không phát sinh”.
- [x] HC03: lưu ngày thử việc, kết thúc/nghỉ thực và xác nhận lý do; tính cohort có thể kiểm tra. Không dùng cờ nhân viên không hoạt động để suy nghỉ thử việc.
  - Ở lượt tuyển: ngày bắt đầu/kết thúc thử việc, kết quả (đang thử việc / đạt / nghỉ trong thử việc), ngày nghỉ thực (trong thời gian thử việc, không sau hôm nay), hình thức (tự nghỉ / công ty chấm dứt / khác), lý do; gửi kiểm riêng (`probation_quit`). Không ghi “đạt” trước khi hết thử việc. Hết thử việc mà chưa xác định → thiếu dữ liệu (test đặt tài khoản ngừng hoạt động vẫn “thiếu”, không thành “nghỉ”). Hồ sơ nhân viên hiện ngày thử việc của hồ sơ chi tiết và cảnh báo nếu khác.
  - Cohort nháp: người có ngày kết thúc thử việc (hoặc ngày nghỉ thực nếu nghỉ sớm) trong tháng. Phương án nháp “mọi trường hợp nghỉ” / “chỉ tự nghỉ”. Cohort 0 → chưa đủ.
- [x] Dùng upload/preview tuyển dụng hiện có ở bảy bước; không tạo hệ tệp thứ hai. Giữ hồ sơ cũ, không tự tải CV từ đường dẫn `file:///` trên máy người dùng.
  - Chứng cứ lượt tuyển dùng `RecruitmentAttachmentService` (thêm thực thể `hires`, disk `local` riêng tư) và trang xem trước/tải có kiểm quyền sẵn có. Chi phí dùng chứng từ gốc ở module ĐNTT (không tải lại hóa đơn).
- [x] Checklist mở yêu cầu/ứng viên/hồ sơ/chi phí đúng quyền. Test nhiều vị trí, qua tháng, rút ứng viên, ngày chưa xác nhận, cohort 0, chi phí trùng/hoàn và phạm vi công ty.
  - Link lượt tuyển/khoản chi theo quyền trang Nhân sự; link chứng từ theo quyền module ĐNTT (Admin/Kế toán/HCNS hoặc người lập). `HcnsRecruitmentKpiTest` **8/8, 297 assertions**.
- [x] Chạy lại `RecruitmentAttachmentTest` nếu có thay đổi liên quan. — Không sửa tuyển dụng; vẫn chạy trong bộ HR: 7/7 đạt. 09/10: có sửa dịch vụ tệp (thêm `hires`, `attach`, `deleteOne`) → chạy lại trong bộ HR, đạt.

## P6 — HC04/HC05/HC08: Đối soát lương, BHXH, hài lòng

HC04 làm ngày 09/10 (lần 5); HC05/HC08 chưa làm (H-D06; chưa có module BHXH và khảo sát).

- [x] HC04: thêm sổ đối soát tại quy trình lương phù hợp, có phạm vi kiểm, lỗi xác nhận, người kiểm và liên kết kỳ/dòng. Chưa chốt đơn vị mẫu số/nguồn lương thì chưa chấm chính thức. Không có ghi nhận lỗi không tự thành 100% chính xác.
  - Nguồn: chỉ Bảng lương Nhân sự (`hr_payroll_periods/lines`); bảng cũ Tài chính (`payrolls`, `?legacy=1`) và `payroll_slip_*` không dùng, không cộng (SOURCE-MAP mục HC04).
  - Trang: Bảng lương tháng → khối “Đối soát độ chính xác (KPI HC04)” (danh sách đợt + “Lập đợt”: kỳ, phạm vi toàn bộ/phòng ban/chọn dòng, HCNS chịu KPI, người nhập, người kiểm độc lập) → trang đợt `/nhan-su/bang-luong/doi-soat/{id}`: từng dòng Đúng/Có lỗi/Chưa xác định + khoản đã kiểm; lỗi (khoản, loại, nội dung, số đúng/đã ghi, liên kết nguồn, chứng cứ riêng tư có xem trước, cách xử lý/đã sửa); khối kiểm tra dùng chung (lưu nháp → gửi → xác nhận 3 mục/trả bổ sung, lịch sử). Không thêm menu.
  - Số đo `PayrollAccuracySource`: phần đúng ÷ phần đã kiểm của đợt đã xác nhận; chưa có đợt/dòng chưa kiểm/chưa xác định/dòng đổi sau khi kiểm/đợt chờ → chưa đủ; “không phát sinh” chỉ khi không có bảng lương kỳ được đo. Lỗi đã sửa giữ lịch sử; tính hay không theo quy tắc. Không đếm số lần sửa dòng lương.
  - Quy tắc nháp H-D05: kỳ KPI = tháng sau kỳ lương, đơn vị dòng, kiểm đủ phạm vi, mọi lỗi đã xác nhận đều tính; `adoption = preview_only`.
- [x] HC04 khóa và bảo toàn: kỳ KPI đã chốt lương → chặn ghi kết quả, thêm/sửa/gỡ lỗi, tệp, người/phạm vi, gửi/trả/xác nhận, hủy; ảnh chụp KPI lúc chốt có khối HC04 và không đổi theo nguồn sau đó; đợt không xóa được (chỉ hủy có lý do trước khi gửi). Không đổi số tiền lương, không tính lại kỳ Excel 08/2026.
- [x] HC04 test `HcnsPayrollAccuracyKpiTest` **13/13** (154 assertions): chưa kiểm ≠ 100% và không N/A, kiểm không lỗi, lỗi chưa xác nhận, trả bổ sung/gửi lại (giữ lần gửi đầu), sửa sau xác nhận làm hết hiệu lực, nhiều lỗi cùng dòng/cùng khoản, lỗi đã sửa theo hai phương án + gỡ lỗi giữ lịch sử, mẫu số 0 khi dòng lương đổi sau kiểm, khác công ty, tự xác nhận (HCNS chịu KPI/người nhập/người gửi), quyền đọc tệp, sửa sau chốt, ảnh chụp khi chốt, lớp điểm chính thức chỉ khi áp dụng `source`, trang lương/checklist hiển thị và link đúng.
- [ ] HC05: bổ sung hồ sơ nghiệp vụ tại nhân viên/hồ sơ/giao nhận đang có, có loại thủ tục, người chịu trách nhiệm, hạn được giao, kết quả và xác nhận. Không tự đặt hạn pháp lý từ suy đoán.
- [ ] HC08: bổ sung kỳ khảo sát, người được mời, phản hồi, thang/mức hài lòng và bảo mật. Chưa chốt thang/ngưỡng/số phản hồi thì chưa có điểm; không công khai phản hồi nhạy cảm cho người bị đánh giá.
- [ ] Test 99/100 = 99% là phép đo, lỗi chưa kiểm, không phát sinh BHXH, phản hồi thiếu/trùng, bảo mật và quyền đọc/ghi.

## P7 — Tổng hợp và giao diện chín KPI

- [x] Nối adapter của chín tiêu chí vào một bộ tổng hợp HCNS phục vụ trang KPI/KPI của tôi/HR payroll; checklist dùng chính kết quả bộ tổng hợp, không có công thức riêng.
  - **Mới 2/9 adapter (HC09, HC07)**; 7 tiêu chí còn lại hiện “Chưa có nguồn tự động — đang chấm tay”. 09/10: thêm adapter HC06 (`AssetsServiceSource`) — chỉ số đo ba nhóm, chưa cho điểm đến khi chốt H-D04; còn 6 tiêu chí chấm tay. 09/10 lần 3: thêm HC01 `RecruitmentTimeSource`, HC02 `RecruitmentCostSource`, HC03 `ProbationQuitSource` (số đo không phải % hiển thị bằng `measure_text`: ngày/lượt, đ/vị trí); còn HC04, HC05, HC08 chưa có nguồn. 09/10 lần 5: thêm HC04 `PayrollAccuracySource` (chỉ xem trước); còn **HC05, HC08** chưa có nguồn. `HrKpiEvaluator::sourceOverrides()` dùng chung cho trang KPI, KPI của tôi và `PayrollGenerator` (qua `forEmployee`).
- [x] Dùng giao diện kỹ thuật: bộ lọc, danh sách người, chi tiết chín tiêu chí và checklist nguồn. Tận dụng trang/menu hiện có, không thêm chín trang KPI nhập tay.
  - Checklist nằm ngay dưới bảng 9 tiêu chí của từng người ở `/nhan-su/kpi?template=hcns` và trong `/nhan-su/kpi-cua-toi`; không thêm menu.
  - 09/10 lần 4: thêm bộ lọc **Nhân viên** (`employee=<id>`) cạnh Kỳ đánh giá; người ngoài bộ KPI kỳ đó → hiện tất cả. Test `HcnsKpiChecklistTest` 5/5.
- [x] Mỗi dòng hiện công việc đã hoàn thành chưa, đã được tính chưa, lý do, phần điểm nếu hợp lệ và link nguồn. Người không có quyền không mở được hồ sơ qua URL trực tiếp.
  - `HcnsKpiChecklistTest` 4/4: link ẩn với người không mở được việc, URL trực tiếp 403.
- [x] Bản xem trước cấu hình hiện tác động từng người, ghi rõ chưa áp dụng. Thiếu quy tắc/trọng số/xác nhận phải chặn total chính thức và tiền KPI mới.
  - Tab Cài đặt: bảng xem trước HC09/HC07 từng người theo bản nháp; nguồn chưa đủ khi đã áp dụng → tổng “chưa đủ”, hệ số lương null (test).
- [x] Kiểm tra desktop/390px: bộ lọc, đọc checklist, mở nguồn, upload/preview, lỗi nhập và quay lại đúng người/kỳ; không tràn ngang toàn trang.
  - UI 8012: KPI phòng ban, KPI của tôi, trang việc, trang giao nhận, tab quy tắc ở 1366/390 px không tràn ngang (`results-ui*.json`).

## P8 — Đối chiếu lương, snapshot và khóa kỳ

- [x] Phân biệt xác nhận nguồn, duyệt KPI, duyệt cấu hình và duyệt lương; dùng đúng quyền hiện có. Không tự duyệt ở DB vận hành local.
  - Nguồn: người kiểm; quy tắc: Ban Giám đốc/Admin khác người lập; lương: luồng HR có sẵn. Không duyệt gì trên local.
- [x] Lưu kết quả và phiên bản cấu hình khi chốt; kết quả kỳ cũ không đổi vì nguồn hiện tại thay đổi. Nguồn dùng nhiều kỳ cần snapshot/version, không khóa mọi hoạt động tương lai.
  - Trọng số/target/bậc HCNS theo phiên bản (`hr_kpi_rule_versions.config`): tab Cài đặt lưu thành **bản nháp**, cài đặt gốc không đổi; người khác duyệt áp dụng từ một kỳ (không vào kỳ đã chốt). Duyệt lương → `hr_kpi_period_snapshots` (dòng tiêu chí, checklist, phiên bản, tổng, hệ số); kỳ chốt hiển thị/tính từ ảnh chụp; mở khóa → ảnh chụp chuyển lịch sử. Test `HcnsKpiPayrollFoundationTest` 7/7.
- [x] Chặn hoặc version mọi đường làm thay đổi nguồn đã chốt: sửa/xóa, file, hạn/người, trạng thái, hủy, phân công, gửi lại, trả bổ sung và xác nhận. Khóa đúng nguồn HCNS liên quan, giữ công việc kỹ thuật dùng chung hoạt động.
  - HC09: giao mới vào kỳ chốt, sửa/đổi hạn/người, nộp/nháp, duyệt, trả, hủy, cập nhật nhanh, thay/xóa minh chứng. HC07 (theo tháng gửi kiểm lần đầu): sửa, thêm/xóa tệp, nháp/gửi/xác nhận/trả, xóa; bước giao nhận vẫn chạy. Việc thường không bị khóa. Test `HcnsKpiRuleVersionLockTest` 6/6.
- [x] HR payroll đọc cùng kết quả và đúng bậc HCNS được duyệt. Không nhân % hai lần, không dùng bậc/kho/kỹ thuật thay HCNS, không thêm làm tròn theo nghìn. Xác minh độ chính xác tiền ở hệ thống hiện có và báo riêng nếu cần quyết định về phần lẻ.
  - `PayrollGenerator` → `forEmployee` (cùng bộ chấm, phiên bản của kỳ, ảnh chụp khi đã chốt). Lương KPI = quỹ KPI × (công/công chuẩn) × hệ số bậc; chỉ làm tròn đồng. Tính tay 09/2026: Σ điểm 84,03 ÷ Σ trọng số 97,78 = 85,94% → bậc 100% → 2.000.000 × 22/24 × 1,00 = **1.833.333 đ**, khớp dòng lương (phần lẻ đồng giữ nguyên, không làm tròn nghìn).
- [x] Thử UI lương HR đầy đủ trên DB test: đủ/thiếu điều kiện, xem trước, người được/không được duyệt, chốt và thử sửa sau chốt bằng UI lẫn request trực tiếp.
  - `ui-test/ui-payroll.cjs` **21/21**: HR tạo bảng khi thiếu KPI → cảnh báo, không có nút duyệt; Giám đốc duyệt bị chặn; chấm tay → ba lớp số; lưu nháp trọng số HC09 = 20, người lập không thấy nút duyệt (POST trực tiếp bị từ chối), Phó GĐ duyệt từ 09/2026; HR tính lại → 85,94%/100%; duyệt & khóa → ảnh chụp; sau chốt sửa KPI tay, tính lại, đổi hạn thủ tục tháng 9 đều bị chặn; Sales 403; 390 px không tràn trang. Ảnh `ui-test/shots/p1–p9`.
- [x] Bảo vệ minh chứng HC09: tệp mới của thủ tục HCNS lưu disk `local`, mở qua `tasks.attachments.file` (kiểm `TaskPermissions::canView`); trang việc không còn link `/storage/` hoặc gửi tệp Office ra viewer ngoài. Tệp cũ chuyển bằng `php artisan hcns:secure-task-attachments` (mặc định chạy thử; `--apply` sao chép → kiểm kích thước → đổi `disk` → xóa bản public), giữ nguyên ID nên link không mất. Test 2 ca.
- [x] Đối chiếu trước/sau kỳ Excel 08/2026, cấu hình và dữ liệu kỹ thuật mẫu; không tính lại lịch sử từ Excel. — Không đụng bảng lương/kỹ thuật; local chỉ đọc; bộ Technical không có lỗi mới do đợt này.

## P9 — Áp local 8001 (người dùng cho phép 09/10/2026)

Phạm vi: DB local `crm_egosolar_test` của web 8001. Không duyệt cấu hình áp dụng chung hoặc lương thật; không chạy PHPUnit/fixture test suite trên DB này; không commit/push/deploy. Bằng chứng: `outputs/claude-kpi-agent/hcns-local-20261009/` (gitignore).

- [x] Xác minh 8001 chạy từ repo này với DB `crm_egosolar_test` (không chuyển sang `egosolar_test`); mail `log`, queue `sync`.
- [x] Sao lưu đầy đủ DB + `storage/app` + `public/uploads` ngoài git (`D:/Source Dev/crm.egosolar.com.vn/backups/hcns-local-20261009/`), kiểm sha256, khôi phục thử vào DB tạm (381/381 bảng, 32.696 dòng, 0 lệch), ghi cách khôi phục `KHOI-PHUC.md`.
- [x] Baseline chỉ đọc trước khi áp (`baseline-before.json`): băm từng bảng, lương Excel 08/2026, cấu hình/dữ liệu kỹ thuật, tuyển dụng, Admin cũ (role + dấu mật khẩu), role/quyền.
- [x] Áp **chỉ** 4 migration theo thứ tự bằng `--path` (batch 244–247); không fresh/refresh/wipe/seed toàn DB. Sau áp: 0 bảng dữ liệu cũ đổi, +10 bảng mới (`compare-after-migrate.json`, `migrate-local.log`).
- [x] Minh chứng HC09: `hcns:secure-task-attachments` chạy thử = 0 tệp cần chuyển; không `--apply`, không đụng tệp kỹ thuật.
- [x] Bộ mẫu `HCNS-LOCAL-DEMO-20261009` (`local-demo.php seed|show|cleanup`, manifest `local-demo.json`): tài khoản theo role sẵn có (hr nhập liệu, hr người kiểm, sales không quyền), hồ sơ lương 0 đ từ 09/2026, đủ ca HC09/HC07/HC06/HC01–HC03; chạy lại không trùng/không đổi giờ; không tạo kỳ lương, phiên bản quy tắc hay điểm tay. `cleanup` thử 2 lần → về baseline (thư mục rỗng PHP không xóa được thì in ra để xóa tay).
- [x] Thử trên trình duyệt 8001 (`ui-local.cjs` → `results-ui-local.json`, ảnh `shots/`): **80/80** — đăng nhập/menu, nhập/gửi/xác nhận/trả bổ sung, upload/xem trước, checklist, link nguồn, lọc người/tháng, quyền (sales 403), 390 px, trang lương HR mở bình thường. Không có lỗi 500/route/schema/quyền; `laravel.log` không ghi lỗi mới.
- [x] Đối chiếu tính tay từng dòng (`reconcile-local.md`): HC09 3/6, HC07 2/3, HC06 2/2 · 1/1 · 2/4, HC01 21 ngày/lượt, HC02 1.400.000 đ/vị trí, HC03 (09) 50% — khớp bộ đo; điểm chính thức chưa có, tổng chưa đủ.
- [x] Test tự động chỉ trên `egosolar_test`: `tests/Feature/Hr` 126 test, 2 lỗi baseline `PayrollFlowTest`; UI 8012 chạy lại đạt toàn bộ.
- [x] So baseline cuối (`compare-final.json`): 0 bảng dữ liệu cũ đổi; Admin/role/mật khẩu cũ, lương 08/2026, cấu hình kỹ thuật giữ nguyên.
- [ ] Đăng nhập Admin cũ trên 8001 — **chưa kiểm** (không có mật khẩu, không reset).
- [ ] Giao thủ tục HCNS mới trên 8001 — chưa thử (role `hr` không có quyền giao việc; cần tài khoản Ban Giám đốc/Admin của người dùng).
- [x] HC04 lên 8001 (09/10 lần 5): sao lưu lại DB + tệp (`crm_egosolar_test-before-hc04-20261009-161543.sql`, khôi phục thử 391/391 bảng, 32.911 dòng, 0 lệch); áp **riêng** `2026_10_09_160000_hcns_kpi_payroll_audits` (batch 248), không chạy migration khác; so baseline: 0 bảng dữ liệu cũ đổi, +3 bảng mới.
- [x] Bộ mẫu thêm phần HC04 (không trùng, chạy lại không đổi): kỳ lương **MẪU** 09/2026 chỉ 8 tài khoản mẫu, số tiền giả, bản nháp, không duyệt; 3 đợt (đã xác nhận / chờ kiểm có dòng chưa xác định / chưa ghi). Dọn riêng HC04: `local-demo.php cleanup-hc04` (đã thử, về đúng baseline trước HC04).
- [x] Trình duyệt 8001 (`ui-hc04.cjs`): **32/32** — lập/xem khối trên Bảng lương, ghi kết quả, thêm lỗi + chứng cứ + xem trước, chặn “Đúng” khi còn lỗi, gửi/xác nhận/trả bổ sung/gửi lại, sửa cách xử lý, checklist KPI tháng 10 (5/8 = 62,5%, xem trước 63,13%, chính thức chưa có), link nguồn, sales 403, 390 px không tràn. Kỳ lương 08/2026 mở bình thường, không tạo gì trên kỳ thật. Hai lỗi phát hiện và đã sửa (500 khi trang đợt hiện lại sau lưu bị chặn; mở tệp làm “trang trước”), kiểm lại 1/1.

## Dữ liệu thử và nghiệm thu

- [x] Mỗi tiêu chí có ca đủ/thiếu và đối chiếu số đo tính tay; cấu hình thử dùng nhãn TEST, không gọi là chính sách đã duyệt. — Cho HC09/HC07 (8/10 = 80%; 2/4 = 50% → 62,5%; 1/2; 2/3 = 66,67%; tổng 95,31% khi áp dụng).
  - 09/10: `ui-test/reconcile2.php` → `reconcile-20261009.md` tính tay độc lập từ dòng DB rồi so bộ đo từng dòng. HC09 kỳ 10 (demo dựng lại): **3/6 = 50%** — đạt: Gia hạn PCCC (gửi 10:00 03/10 ≤ 17:00 03/10), HĐ thuê VP (gửi qua UI ≤ 17:00 20/10), Hồ sơ ATLĐ (gia hạn lúc 15:00 07/10 trước hạn gốc 17:00 08/10 → hạn 17:00 12/10, gửi 08:30 09/10); không đạt: ĐKKD (gửi 09:00 06/10 > 17:00 05/10), Báo cáo LĐ (quá 17:00 07/10 chưa nộp), Nội quy LĐ (gia hạn lúc 09:00 03/10 sau hạn gốc 17:00 02/10 → giữ hạn gốc, gửi 10:00 05/10); chờ: Chữ ký số (gửi đầu 08:00 08/10, 2 lần gửi); loại: 2 việc hủy trước hạn. HC07 2/3. HC06: cấp phát 2/3, kiểm kê 1/1, sự cố 2/4, không có tỷ lệ chung. Lương 09/2026 khớp. **Khớp từng dòng.**
- [x] Test sửa nội dung/tệp rồi đổi lại giá trị cũ vẫn phải xác nhận lại; lịch sử cũ giữ nguyên. Khóa kỳ bao phủ cả đường ghi gián tiếp và dữ liệu qua tháng.
- [x] Chạy test phù hợp và hồi quy module dùng chung; chỉ mở rộng khi thay đổi hoặc kết quả cho thấy cần. Ghi số đạt/hỏng cùng baseline đối chứng.
  - HCNS mới 28 test. `tests/Feature/Hr` 101 test: 2 lỗi baseline `PayrollFlowTest`. `tests/Feature/Technical` 408 test: 10 lỗi — 9 đã biết từ các phiên trước, 1 do drawer kỹ thuật bị phiên khác sửa lúc 00:50 (STATUS “Ghi chú KPI tại các thao tác Kỹ thuật”). Khi fixture UI còn trong DB test có thêm 2 lỗi; dọn fixture thì đạt (đối chứng trong `tests-*-final.log`).
  - 09/10 lần 3 (dọn demo trước khi chạy): HCNS + tuyển dụng **80 test** (gồm `HcnsRecruitmentKpiTest` 8), chỉ 2 lỗi baseline `PayrollFlowTest`; `tests/Feature/Hr` **125 test: 2 lỗi** baseline (`tests-hr-p5.log`); `tests/Feature/Technical` 408: **10 lỗi trùng danh sách baseline** (`tests-technical-p5.log`). DB test sau hồi quy trùng số dòng trước khi chạy (thêm 2 bảng rỗng của migration mới). UI demo dựng lại: HC09/HC07 29/29, HC06 35/35, tuyển dụng 31/31 + kiểm lại 4/4, lương 21/21.
  - 09/10 (dọn demo trước khi chạy): HCNS **44 test** (6 lớp). `tests/Feature/Hr` **117 test: 2 lỗi** — cùng 2 lỗi baseline `PayrollFlowTest` (`tests-hr-final-20261009.log`). `tests/Feature/Technical` **408 test: 10 lỗi**, trùng danh sách 08/10 (`tests-technical-20261009-mem.log`). Lần chạy với giới hạn PHP mặc định 128 MB dừng ở ca ~237 vì hết bộ nhớ khi Laravel vẽ trang lỗi; chạy với `-d memory_limit=1024M` hoàn tất. `egosolar_test` sau hồi quy: 389 bảng/517 dòng, trùng trước khi chạy.
- [x] Lưu ảnh/đối chiếu/nhật ký trong thư mục gitignore. Tài liệu có thể commit không chứa dữ liệu thật, mật khẩu hay phản hồi khảo sát nhạy cảm.
- [x] Dọn fixture/server thử đúng phạm vi; báo riêng test đã chạy/chưa chạy và dữ liệu nào thay đổi. — `egosolar_test` về 515 dòng/384 bảng như trước seed; server 8012 dừng.
  - 09/10: dọn → hồi quy → **dựng lại demo** để người dùng kiểm tra (fixture `HCNS-KPI-TEST` mở rộng: 6 tài khoản, 12 thủ tục, 4 công văn, 3 phiếu VPP, 1 đợt kiểm kê, 4 sự cố, chấm công 09/2026). Server 8012 đang chạy với demo; dọn bằng `php ui-test/fixtures.php cleanup`. UI: HC09/HC07 29/29, HC06 35/35, lương 21/21.
- [x] Cập nhật checkbox bằng đường dẫn bằng chứng, PROJECT bằng trạng thái thực và danh sách H-D còn mở. Gửi link màn hình và thao tác từng vai trò để người dùng kiểm tra.

Nếu thiếu quyết định, tiếp tục các phần độc lập và ghi đúng phần bị chặn. Hoàn thiện source/test/xem trước trước khi đề xuất áp migration lên local. Không tự đổi quyết định chưa trả lời thành mặc định được duyệt; không gọi toàn hệ thống hoàn tất khi còn tiêu chí hoặc kiểm thử chưa đủ.
