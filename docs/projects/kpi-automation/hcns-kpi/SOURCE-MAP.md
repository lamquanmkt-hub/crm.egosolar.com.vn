# Bản đồ chín tiêu chí HCNS

Đối chiếu Word và source ngày 08/10/2026. `HC01…HC09` là mã tài liệu; cột code là mã criterion thật hiện có. Tên trường bổ sung ở đây mô tả **ý nghĩa**, không khẳng định đã có cột cùng tên. Claude xác minh lại bằng route/schema/service trước khi thiết kế.

| Mã / code | Word của sếp | Trang nguồn đang có | Còn thiếu để chấm tự động |
|---|---|---|---|
| HC01 `recruit_time` | Ngày trung bình từ tiếp nhận yêu cầu đến ứng viên nhận việc; 23–25 ngày | `/nhan-su/tuyen-dung/yeu-cau` → “KPI tuyển dụng” `/yeu-cau/{id}/kpi`, lượt tuyển `/luot-tuyen/{id}` | **09/10: có nguồn** (`RecruitmentTimeSource`): giờ HCNS tiếp nhận yêu cầu đã duyệt → ngày nhận việc thực tế đã kiểm, theo tháng nhận việc. Còn thiếu: ngưỡng/cohort/quy đổi (H-D02/H-D08) → chỉ xem trước |
| HC02 `recruit_cost` | Tổng chi phí / số vị trí tuyển thành công; 500.000–1.000.000đ | “KPI tuyển dụng” → gắn chứng từ `payment_requests` (ĐNTT/phiếu chi/tạm ứng/hoàn tiền), trang khoản chi `/tuyen-dung/chi-phi/{id}` | **09/10: có nguồn** (`RecruitmentCostSource`): chỉ phần đã thực chi, tạm ứng theo quyết toán, hoàn tiền trừ, không phân bổ vượt. Còn thiếu: quy đổi, cách chia chi phí chung, có tính chi phí VP không (H-D02/H-D08) |
| HC03 `probation_quit` | Nhân sự mới nghỉ ngay trong thử việc; ≤10% | Lượt tuyển → “Kết quả thử việc”; hồ sơ nhân viên hiện lượt tuyển | **09/10: có nguồn** (`ProbationQuitSource`): kết quả thử việc đã kiểm, cohort tháng kết thúc thử việc/ngày nghỉ thực; không suy từ tài khoản/chấm công. Còn thiếu: cohort, loại nghỉ được tính, quy đổi (H-D08) |
| HC04 `payroll_accuracy` | Tỷ lệ tính lương/thưởng chính xác, không sai sót; ≥99% | `/nhan-su/bang-luong?month=…` → khối “Đối soát độ chính xác (KPI HC04)” → đợt `/nhan-su/bang-luong/doi-soat/{id}` | **09/10 lần 5: có nguồn** (`PayrollAccuracySource`): đợt đối soát trên bảng lương HR, từng dòng đúng/có lỗi/chưa xác định, lỗi có khoản/loại/nguồn/chứng cứ/cách xử lý, người kiểm độc lập xác nhận. Còn thiếu: đơn vị (dòng/khoản), kỳ, độ phủ, lỗi đã sửa, quy đổi (H-D05/H-D02) → chỉ xem trước |
| HC05 `insurance` | Hồ sơ/chốt sổ/chế độ BHXH đúng hạn; 100% | Hồ sơ nhân viên, `/nhan-su/ho-so-nhan-su`, giao nhận hồ sơ nếu phù hợp | Nghiệp vụ hồ sơ BHXH: người lao động, loại thủ tục, người phụ trách, hạn nhập/xác nhận nghiệp vụ, mốc nộp/chốt/giải quyết, chứng cứ, người kiểm; chưa thấy workflow riêng trong audit. Chỉ lưu mốc hạn nghiệp vụ, không tự tư vấn/chốt hạn pháp luật |
| HC06 `assets` | Đáp ứng cấp phát và kiểm kê tài sản mỗi tháng; ≥95% đúng SLA | `/nhan-su/quy-trinh-phan-bo-vpp`, `/nhan-su/hc-van-hanh?tab=assets`, `/finance/assets`, `/nhan-su/su-co-van-phong` | **09/10: đã có nguồn ba nhóm** (mục “Phát hiện và nguồn HC06”). Còn thiếu: quy tắc gộp, mốc/lịch làm việc sự cố (H-D04) → chưa có điểm |
| HC07 `documents` | Tiếp nhận, phân loại, luân chuyển công văn chính xác; 100% | `/nhan-su/quy-trinh-giao-nhan-ho-so`, `/company-documents` | Giao nhận có ID/hạn/mốc nhưng cần xác nhận đúng đủ/đúng nơi nhận, kết quả chất lượng, người kiểm độc lập, mốc SLA được giao/chốt. File trong kho không phải công văn hoàn tất |
| HC08 `satisfaction` | Khảo sát môi trường làm việc mỗi tháng; ≥80% | Chưa có nguồn khảo sát môi trường đầy đủ trong audit | Đợt khảo sát, phạm vi mời, số phản hồi hợp lệ, thang/câu hỏi/ngưỡng hài lòng, người xác nhận kỳ, chống trùng; bảo mật câu trả lời. Sao của một sự cố không thay khảo sát môi trường |
| HC09 `admin_deadline` | Thủ tục hành chính, pháp lý, giấy phép theo deadline được giao; ≥80% | `/chat/tasks`, `/chat/tasks/my`; `/nhan-su/hc-van-hanh?tab=tasks` là nguồn khác | Đánh dấu loại việc HCNS, ID chịu trách nhiệm, hạn gốc/đổi hạn có audit, gửi kết quả/lịch sử, người kiểm xác nhận hoàn tất. Không áp hạn 24h của kỹ thuật |

## Điểm cần đọc đúng trong source

- `app/Services/Hr/Kpi/HrKpiEvaluator.php`, `app/Http/Controllers/Hr/HrKpiController.php`, `resources/views/hr/kpi/`, `app/Services/Hr/Payroll/PayrollAccess.php`, `PayrollGenerator.php`, `PayrollCalculator.php`.
- Criterion/template/score: `hr_kpi_templates`, `hr_kpi_criteria`, `hr_kpi_scores`; seed tại `database/migrations/2026_10_01_090000_create_hr_kpi_payroll_tables.php`. Dữ liệu seed không thay quyết định phê duyệt.
- HC09: `app/Http/Controllers/Tasks/TaskController.php`, model Task theo import thật của controller; `tasks`, `task_activity_logs`. `completed_at` được ghi lúc nộp, nộp lại có thể ghi đè, trả bổ sung xóa. Không dùng `updated_at` làm lần gửi đầu; không sửa cơ chế dùng chung để phá KPI kỹ thuật. Các việc HC có người nhận dạng chữ không tự map thành users ID.
- HC07: `DocumentHandoverController`, bảng `hr_document_handovers`, tệp/lịch sử theo schema thật; có `assigned_to`, `assigned_at`, `received_at`, `sent_at`, `completed_at` trong audit. Route đã đổi trạng thái chưa đồng nghĩa kiểm tra chất lượng độc lập. Module này còn dùng storage public; rà quyền đọc/minh chứng trong phạm vi luồng liên quan, không coi mọi link public là đủ an toàn để tái dùng.
- HC06: `OfficeSupplyProcessController::allocateStock()` hiện ghi các mốc submitted/received/completed cùng lúc. Không backfill mốc giả để khẳng định đúng SLA. `OfficeIncidents::slaStats()` hiện chỉ tham khảo, cần lọc người/công ty và xác nhận nghiệm thu. Hai kho tài sản cần chọn nguồn/khóa liên kết, không nối theo tên và cộng hai lần.
- Tuyển dụng có hai luồng cũ/mới (`CandidateProcessController` và `RecruitmentController`). Kiểm route runtime trùng và chọn nguồn chính có mapping được xác nhận; không đếm hai lần cùng ứng viên.
- Tệp tuyển dụng mới: `app/Services/Hr/RecruitmentAttachmentService.php`, `RecruitmentAttachmentController.php`, `resources/views/hr/recruitment/partials/attachments.blade.php`, `public/js/hr-recruitment-pdf.js`, `tests/Feature/Hr/RecruitmentAttachmentTest.php`; migration `2026_10_08_120000_create_hr_recruitment_attachments_table.php`. Không tạo thêm bảng tệp thứ hai cho cùng hồ sơ.
- UI kỹ thuật tham khảo: `resources/views/synced/kythuat/kpis.blade.php`; HR CSS `public/css/hcns-technical-workspace.css`. Giữ bố cục người dùng đã yêu cầu.

## Word: quy trình sự cố và quy đổi tiền

Word có quy trình bốn bước: tiếp nhận → phân loại/phê duyệt → xử lý → nghiệm thu/đóng. Người báo hoặc trưởng phòng bộ phận kiểm thực tế; có ảnh kết quả và thời gian hoàn tất.

- Phân loại tối đa 30 phút.
- Mức 1: 1–2 giờ; mức 2: 4–8 **giờ làm việc**; mức 3: 24–48 giờ.
- Khoảng SLA chưa chỉ ra chọn đầu nào, mốc bắt đầu, lịch làm việc hoặc cách trừ thời gian chờ. Không tự thay mọi mức bằng giờ liên tục.
- Chi dưới 500.000đ HC chủ động, trên 500.000đ cần Giám đốc duyệt; **đúng 500.000đ chưa được nêu**. Đây là nội dung tham khảo từ tài liệu, không tự mở quyền chi tiền hoặc đổi luồng tài chính.
- Word mô tả ≥95 →110% quỹ KPI, 80–94 →100%, 65–79 →85%, dưới65 →0. Với điểm có phần thập phân cần thể hiện biên liên tục rõ ràng theo cấu hình được duyệt; thưởng thêm vượt tuyển dụng chưa có công thức.

## Phát hiện khi triển khai 08/10/2026

**Công việc dùng chung (HC09).**

- Quyền:
  - Role `hr` không nằm trong nhóm được giao việc (`canAssign`). HCNS nhận việc; Ban Giám đốc/Kế toán/quản lý giao việc.
  - `returnRevision` trước đây chỉ cho người giao/quản lý. Với thủ tục HCNS, người kiểm chỉ định (`approver_id`) được trả bổ sung.
- Lỗ hổng làm mất mốc nộp/duyệt (với thủ tục HCNS đã chặn hoặc đổi thành ghi mốc riêng):
  - `submitResult` ghi đè `completed_at`; `returnRevision` xóa nó.
  - `approve` không lưu người/giờ duyệt.
  - Form sửa cho chọn thẳng trạng thái "Đã duyệt".
  - “Cập nhật nhanh” mở lại được việc đã nộp/duyệt.
  - Xóa việc thì mất khỏi mẫu số.
- `tasks.company_id` không được ghi khi giao việc. Thủ tục HCNS nay ghi công ty khóa.
- Tác động lên Kỹ thuật: workspace Kỹ thuật lọc `task_type in (technical, null)`, nên `hcns_admin` không lẫn vào feed Kỹ thuật.
- Form giao việc chỉ có ô ngày (00:00). Thủ tục HCNS thêm ô giờ hạn.

**Giao nhận hồ sơ (HC07).**

- Controller không tự kiểm quyền; mọi người có `page.hr` thao tác được mọi hồ sơ (local: admin, hr, management).
- Tệp cũ lưu disk public.
- Trang chi tiết thiếu modal “+ Thêm file” và “Xem trước”, nên hai nút không hoạt động. Đã bổ sung.

**KPI HR.**

- `HrKpiEvaluator` chỉ đọc `hr_kpi_scores`.
- `PayrollGenerator` lấy hệ số qua `forEmployee()`. Vì vậy nguồn tự động khi được duyệt đi chung một đường cho trang KPI, KPI của tôi và bảng lương.

## Phát hiện và nguồn HC06 — 09/10/2026

**Đã nối nguồn (chỉ số đo, chưa điểm):** `App\Services\Hr\Kpi\Sources\AssetsServiceSource` (code `assets`), ba nhóm `allocation`/`inventory`/`incident`, kỳ theo tháng của hạn (kiểm kê: theo kỳ của đợt), công ty khóa hoặc null.

| Nhóm | Trang nhập | Người chịu / người kiểm | Mốc đo | Được tính khi |
|---|---|---|---|---|
| Cấp phát VPP | `/nhan-su/quy-trinh-phan-bo-vpp` → modal Cấp phát, ô “Theo dõi KPI HC06”; chi tiết `/{id}` | `handler_id` / `reviewer_id` (≠ người xử lý) hoặc Ban Giám đốc | `request_received_at`, `due_at`, `delivered_at` | Người kiểm chấm đạt 3 mục (mốc, số lượng, ký nhận) và giao ≤ hạn |
| Kiểm kê | `/nhan-su/hc-van-hanh?tab=assets` → “Đợt kiểm kê”; chi tiết `/nhan-su/hc-van-hanh/kiem-ke/{id}` | `handler_id` / `reviewer_id` hoặc Ban Giám đốc | `due_at`, `completed_at` = lần gửi kiểm gần nhất | Đạt 2 mục (kết quả, biên bản) và lần gửi được xác nhận ≤ hạn; hủy trước hạn loại, quá hạn chưa xong không đạt |
| Sự cố | `/nhan-su/su-co-van-phong/{id}` (phân loại chọn người xử lý) | `handler_id` / người báo nghiệm thu (hoặc Ban Giám đốc) | `due_at` theo mức; `resolved_at` hoặc `first_resolved_at`; `approval_wait_minutes` | Đã đóng (nghiệm thu) và mốc đo ≤ hạn (± thời gian chờ theo quy tắc) |

- `allocateStock()` vẫn ghi submitted/received/completed cùng lúc cho phiếu thường. Phiếu theo dõi KPI lấy giờ nhận yêu cầu/giao thực do người xử lý nhập (≤ hiện tại), không dùng giờ lưu phiếu; phiếu cũ không backfill.
- Hai danh mục tài sản (`hr_operation_assets` không có company_id/mã; `finance_assets` có công ty, xóa mềm): một đợt kiểm kê chụp danh sách của **một** danh mục vào `hr_asset_inventory_items` (khóa nguồn + ID), không nối theo tên, không cộng hai danh mục.
- `/nhan-su/*` cần quyền trang Nhân sự; trang sự cố cho người báo xem sự cố của mình (khối KPI chỉ HCNS/Ban Giám đốc thấy). Người yêu cầu VPP không có quyền trang Nhân sự không vào được trang phiếu → ký nhận chứng minh bằng biên bản.
- `OfficeIncidents::slaStats()` cũ (tổng hợp mọi người, theo `resolved_at`) giữ nguyên làm số tham khảo trên trang sự cố; KPI dùng adapter mới theo người xử lý.
- Quy tắc nháp `assets`: `aggregation` (unset | pooled | average_groups), `incident_measure` (lần nghiệm thu | lần báo xong đầu), `incident_wait` (giờ liên tục | trừ chờ duyệt chi). Phiên bản đã duyệt cũ thiếu khóa `assets` → mặc định `unset`.

## Phát hiện và nguồn tuyển dụng HC01–HC03 — 09/10/2026

- Nguồn tuyển dụng đang chạy: `RecruitmentController` (route `hr.recruitment.*`, 7 bước). Bảng `hr_recruitment_requests` (vị trí + số lượng, `approval_status` dạng chuỗi, không có người/giờ duyệt, không công ty), `hr_recruitment_candidates.recruitment_request_id`, `hr_recruitment_offers.start_date` (ngày dự kiến), `onboarding_date`. Không có liên kết ứng viên → tài khoản nhân viên. Local: 1 yêu cầu, 17 ứng viên, 10 thư mời — không backfill.
- Đã bổ sung: dấu duyệt `approved_by/at` (server ghi khi chuyển “Đã duyệt”, xóa khi bỏ duyệt); phần KPI của yêu cầu (`kpi_tracked`, `owner_id`, `reviewer_id`, `request_received_at`, hủy đợt); `hr_recruitment_hires` (yêu cầu → ứng viên → thư mời → `employee_user_id`, ngày nhận việc thực tế, kết quả thử việc); `hr_recruitment_costs` (chứng từ ↔ đợt tuyển, số phân bổ, gỡ có lý do).
- Bảo vệ đường sửa/xóa: không xóa yêu cầu/ứng viên/thư mời đã gắn lượt tuyển/chi phí; không đổi yêu cầu của ứng viên đã có lượt; không giảm số lượng dưới số suất đang dùng; không bỏ theo dõi khi đã có lượt; kỳ đã chốt khóa sửa lượt tuyển (theo tháng nhận việc), kết quả thử việc (theo tháng cohort), khoản chi (theo tháng thực chi), mốc tiếp nhận (nếu có lượt ở kỳ chốt), tệp chứng cứ và nháp/gửi/xác nhận/trả. Lượt mới ở kỳ mở vẫn thêm được.
- Hồ sơ nhân viên: `hr_employee_profiles` có `probation_start/end_date`, không có ngày nghỉ/lý do; KPI HC03 ghi ở lượt tuyển và hiển thị đối chiếu với hồ sơ (cảnh báo nếu khác), không tự ghi đè hồ sơ. Trang hồ sơ nhân viên chỉ HCNS/Admin mở được (Giám đốc 403 theo quyền sẵn có).
- Chi phí: `payment_requests.doc_type` = `payment_request` | `payment_voucher` | `advance` | `refund_request`; thực chi khi `status = accounting_approved` (`accounting_approved_at`); tạm ứng có `advance_settlements` (`waiting_payment/waiting_settlement/draft/submitted/returned/completed`, `actual_spent_amount`, `completed_at`). Quyền xem chứng từ: Admin/Kế toán/HCNS xem tất cả, người khác chỉ chứng từ mình lập — checklist ẩn link chứng từ theo đúng quy tắc này. `hr_office_expenses`/`hr_operation_expenses` không có duyệt chi → chưa dùng.

## Phát hiện và nguồn HC04 — 09/10/2026 (lần 5)

**Rà soát nguồn lương (không cộng hai nguồn, không coi tương đương):**

| Nguồn | Thực tế trong source/DB | Dùng cho HC04 |
|---|---|---|
| Bảng lương Nhân sự `/nhan-su/bang-luong` (`hr_payroll_periods`, `hr_payroll_lines`) | Bảng lương chính thức: tự tính từ hồ sơ lương/chấm công/KPI, HR sửa tay ô cho phép, Ban Giám đốc duyệt & khóa; một dòng = một nhân viên/kỳ (unique `period_id + user_id`). Kỳ Excel 08/2026 là ảnh chụp nhập từ file (`settings_snapshot.import.mode = excel_snapshot`, 11/12 dòng chưa nối tài khoản) | **Nguồn kiểm duy nhất.** Đợt đối soát tham chiếu kỳ + dòng; chỉ đọc, không đổi số tiền |
| Phiếu lương HR `/nhan-su/bang-luong/phieu/{line}` | Hiển thị đúng một dòng `hr_payroll_lines` | Không phải nguồn riêng; dùng làm link xem dòng khi kiểm |
| Bảng lương cũ Tài chính `/finance/salary` (`payrolls`) | Đã chuyển hướng sang Bảng lương Nhân sự; bảng cũ chỉ mở bằng `?legacy=1`; local có 11 dòng kỳ 04/2026 | Không dùng, không cộng |
| `payroll_slip_components/values/settings` | Có bảng, không có code dùng (0 giá trị trên local) | Không dùng |

**Đã nối nguồn (số đo, chưa điểm chính thức):** `App\Services\Hr\Kpi\Sources\PayrollAccuracySource` (code `payroll_accuracy`), dịch vụ `PayrollAudits`, controller `PayrollAuditController`, migration `2026_10_09_160000_hcns_kpi_payroll_audits.php` (3 bảng mới: `hr_payroll_audits`, `hr_payroll_audit_items`, `hr_payroll_audit_errors`). Xác nhận dùng `hr_kpi_source_reviews`, chứng cứ `hr_kpi_source_files` (riêng tư), lịch sử `hr_kpi_source_logs`.

| Phần | Trang nhập | Người | Được tính khi |
|---|---|---|---|
| Đợt đối soát | Bảng lương tháng → “Lập đợt đối soát” (kỳ = trang đang xem, phạm vi toàn bộ/phòng ban/chọn dòng) | HCNS chịu KPI (`owner_id`), người nhập (`checker_id`, mặc định người lập), người kiểm (`reviewer_id` ≠ hai người trên) | — |
| Dòng đã kiểm | Trang đợt: từng dòng chọn Đúng / Có lỗi / Chưa xác định + khoản đã kiểm (10 khoản) + ghi chú; chụp số tiền lúc kiểm | Người nhập hoặc Ban Giám đốc/Admin | Đợt được người kiểm độc lập xác nhận đạt 3 mục (phạm vi, kết quả, lỗi) |
| Lỗi | Trong dòng: khoản, loại (9 loại), nội dung, số đúng/số đã ghi, liên kết nguồn, chứng cứ, xử lý (chưa xử lý / đã sửa / kỳ sau / không sửa + cách xử lý) | Người nhập | Lỗi chưa gỡ của đợt đã xác nhận; gỡ lỗi bắt buộc lý do, vẫn trong lịch sử |

- Phân biệt: chưa có đợt (chưa kiểm, không thành 100%, không được đánh “không phát sinh” khi có bảng lương), dòng chưa kiểm/chưa xác định (thiếu), đã kiểm không lỗi (đúng), đã xác nhận có lỗi (sai). Không đếm số lần sửa dòng lương.
- Dòng “đúng” mà bảng lương tính lại làm đổi số tiền sau khi kiểm → thiếu (cần “kiểm lại số mới”); dòng có lỗi giữ nguyên là lỗi. Dòng bị xóa khi tính lại vẫn hiện làm lịch sử.
- Quyền: dùng `PayrollAccess::canManage` sẵn có (HR/Kế toán/Ban Giám đốc/Admin) cho xem/lập; ghi/sửa: người nhập của đợt hoặc Ban Giám đốc/Admin; xác nhận: người kiểm hoặc Ban Giám đốc/Admin, không phải HCNS chịu KPI, người nhập hay người đã gửi. Không thêm quyền. Đợt khác công ty: không tính, mở 404.
- Khóa: kỳ KPI chứa đợt (theo quy tắc) đã chốt lương → mọi đường ghi (kết quả, lỗi, gỡ lỗi, tệp, người/phạm vi, gửi/trả/xác nhận, hủy) bị chặn; ảnh chụp KPI hiện có (`HcnsKpiSnapshots`) lưu khối HC04 lúc chốt.
- Quy tắc nháp `payroll_accuracy` (H-D05): `adoption` (preview_only | source), `period_by` (next_month | payroll_month), `unit` (line | component), `coverage` (full | sample), `fixed_errors` (count_all | exclude_fixed_before_approval).
- Phát hiện khi thử: route xem tệp HCNS dùng chung (`HcnsSourceFiles::respond`, gồm VPP/kiểm kê/HC04) bị Laravel lưu làm “trang trước” → sau khi bấm Xem tệp, thông báo lỗi/“quay lại” mở tệp thay vì hồ sơ. Đã sửa (tệp không lưu làm trang trước) + test.

## Hợp đồng dữ liệu kết quả gợi ý

Một evaluator HCNS trả actual/target, tử số/mẫu số, danh sách nguồn được tính/bị loại/thiếu, trạng thái dữ liệu, phép đo theo kỳ, quy tắc/version, `% đạt` và điểm nullable, người/giờ xác nhận. Đây là giao diện logic đề xuất; tự chọn class/schema phù hợp source.

Một nguồn có khóa định danh ổn định và revision, nhân viên/phạm vi, mốc/hạn, kết quả xác nhận và minh chứng. Đọc lại nhiều lần không tạo bản ghi/điểm trùng. Khi bản nháp thay đổi sau xác nhận, xác nhận cũ không tự phục hồi khi sửa về giá trị trước; giữ lịch sử và yêu cầu xác nhận lại phần bị đổi.

Các phép tính thử chỉ đối chiếu **số đo nghiệp vụ**, chưa phải quy đổi điểm hay mẫu số đã duyệt: 8/10 hồ sơ đúng hạn =80%; 99/100 dòng đối soát không lỗi =99%; 1 người nghỉ/10 người trong cohort =10%; 800.000đ chi phí/1 vị trí =800.000đ/vị trí. Nếu không có mẫu số, không chia cho 0 và không tự ra 100%.
