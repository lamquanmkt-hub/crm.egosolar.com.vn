# AUDIT QUY TRÌNH BẢO TRÌ / BẢO HÀNH HIỆN TẠI

> Phạm vi: trang `http://127.0.0.1:8010/du-an/bao-tri-bao-hanh` (route `projects-unified.maintenance.index`) và toàn bộ route/controller/service/model mà trang này gọi tới. Tài liệu mô tả THỰC TẾ source đang chạy tại thời điểm audit, không đề xuất sửa, không suy diễn nghiệp vụ chưa có trong code.

## 1. Kết luận nhanh

- Trang `/du-an/bao-tri-bao-hanh` là **một trang tổng hợp (dashboard điều hành) duy nhất**, gộp 5 tab: Tổng quan O&M, Lịch bảo trì, Phiếu sự cố & bảo hành, Kho & đổi thiết bị, Hồ sơ công trình. Không phải 5 trang riêng — cùng 1 route `index`, chuyển tab bằng query string `?view=...`.
- Module này quản lý **đồng thời 2 nghiệp vụ khác nhau trên 2 bảng khác nhau**, dùng chung 1 khung giao diện:
  1. **Lịch bảo trì định kỳ** (bảng `solar_maintenance_schedules`) — có phân công, phê duyệt kết quả kỹ thuật, checklist/work-items.
  2. **Phiếu sự cố / bảo hành cũ** (bảng `crm_serial_warranty_claims`, `claim_type` = `warranty`, `incident`, `inspection`) — có chẩn đoán, duyệt, và một luồng Kho xuất/thu hồi RIÊNG (bảng `solar_warranty_stock_movements`), khác hoàn toàn với module Kho mới (`crm_product_stock`, `StockLedger`).
- **Đây là bản CŨ (legacy) của module bảo hành**, tồn tại song song với module MỚI đã xây ở `/ky-thuat/de-xuat-doi-hang-bao-hanh` (đổi hàng) và `/ky-thuat/sua-chua-tinh-phi` (sửa chữa tính phí) cộng `/kho/xuat-hang-bh-sc`. Cả 2 module dùng **chung một bảng** `crm_serial_warranty_claims` và **chung một Eloquent model** `App\Models\SolarWarrantyClaim`, nhưng **2 bộ trạng thái (status) khác nhau, không tương thích với nhau** (xem mục 8). Source đã tự nhận biết việc này và **chặn cứng** để 2 luồng không giẫm lên nhau (xem mục 17-18).
- Không phát hiện notification thật (email/app-notify) cho module này. Không phát hiện cron/job/scheduler riêng.

## 2. Module này hiện dùng để làm gì

Trả lời theo đúng dữ liệu và code:

1. Module đang quản lý **nhiều nghiệp vụ cùng lúc**, không phải 1 nghiệp vụ đơn:
   - Lịch bảo trì định kỳ / bảo hành inverter / kiểm tra tấm pin / vệ sinh hệ thống / xử lý sự cố / kiểm tra monitoring / nghiệm thu-bàn giao (7 loại — hằng số `SolarMaintenanceSchedule::TYPES`).
   - Phiếu sự cố/bảo hành thiết bị theo `claim_type` = `warranty` (bảo hành thiết bị), `incident` (sự cố hệ thống), `inspection` (kiểm tra kỹ thuật). **Không bao gồm** `replacement` (đổi hàng bảo hành) và `paid_repair` (sửa chữa tính phí) — 2 loại này bị chặn tạo tại đây, phải tạo ở module Kỹ thuật mới.
   - Phiếu kho gắn với phiếu sự cố/bảo hành nói trên (`solar_warranty_stock_movements`): xuất đổi bảo hành, thu hồi hàng lỗi, gửi nhà cung cấp, nhận hàng thay thế.
   - Hồ sơ công trình (tài liệu công trình `solar_site_documents`, tệp đính kèm lịch bảo trì).
2. Module **bắt đầu từ Công trình** (`sites`). Mọi lịch bảo trì (`solar_maintenance_schedules.site_id`) và mọi phiếu sự cố (`crm_serial_warranty_claims.site_id`) đều bắt buộc gắn với 1 công trình đã có sẵn trong hệ thống (`Site` model, bảng `sites`) — không tạo được lịch nếu chưa chọn công trình hợp lệ (`SolarMaintenanceService::createSeries` ném lỗi validate nếu `site_id` rỗng hoặc không tồn tại).
3. Điều kiện để 1 record xuất hiện trên trang này:
   - **Lịch bảo trì**: mọi kỹ thuật viên (`SolarMaintenanceAccess::isTechnician`) nhìn thấy TẤT CẢ lịch của mọi công ty (global scope bị bỏ qua riêng cho kỹ thuật — xem `SolarMaintenanceSchedule::booted()`); role khác chỉ thấy lịch thuộc công ty đang làm việc (`EgoCompanyScope::currentId()`); kỹ thuật viên "chỉ thực thi" (`isTechnicianOnly`) trên trang chi tiết công trình chỉ thấy các đợt mình được phân công.
   - **Phiếu sự cố/bảo hành**: hiển thị theo `scopeClaims()` (công ty hiện tại), không lọc theo `claim_type` — nghĩa là phiếu `replacement`/`paid_repair` tạo từ module mới **vẫn xuất hiện** trong danh sách này (xem mục 17).

## 3. Route / Controller / View

### 3.1. Điểm vào

| Thuộc tính | Giá trị thật trong source |
|---|---|
| URL | `/du-an/bao-tri-bao-hanh` |
| Route name | `projects-unified.maintenance.index` |
| File route | `routes/project_unified.php` (khối `EGO_PROJECT_MAINTENANCE_CANONICAL_ROUTES_START`) |
| Middleware nhóm cha | `auth`, `App\Http\Middleware\EnsureUnifiedProjectAccess` (áp cho toàn `du-an/*`) |
| Middleware nhóm con | `role:ky_thuat|technical|technician|technical_staff|technical_leader|technical_manager|accounting|admin|manager|warehouse|kho|sales|sales_manager|cskh` |
| Controller | `App\Http\Controllers\Synced\Technical\SolarMaintenanceController@index` |
| View | `resources/views/synced/technical/maintenance/index.blade.php` |
| JS | Không có file JS riêng ngoài Bootstrap offcanvas/collapse có sẵn của layout; không thấy `<script src=...>` riêng cho trang này trong `index.blade.php`. |

### 3.2. Toàn bộ route trong cùng nhóm `bao-tri-bao-hanh` (đọc từ `routes/project_unified.php`)

| Route name | Method | Controller@method |
|---|---|---|
| `.index` | GET | `Synced\Technical\SolarMaintenanceController@index` |
| `.sites-search` | GET | `Synced\Technical\SolarMaintenanceController@sitesSearch` |
| `.store` | POST | `Synced\Technical\SolarMaintenanceController@store` |
| `.site` | GET | `Synced\Technical\SolarMaintenanceDetailController@site` |
| `.site-files.store` | POST | `Synced\Technical\SolarMaintenanceAttachmentController@storeSite` |
| `.site-files.preview` / `.download` / `.destroy` | GET/GET/DELETE | `Synced\Technical\SolarMaintenanceAttachmentController` |
| `.schedule-files.store` / `.preview` / `.download` / `.destroy` | POST/GET/GET/DELETE | `Synced\Technical\SolarMaintenanceAttachmentController` |
| `.work-items.store` / `.update` / `.destroy` | POST/PUT/DELETE | **`Technical\SolarMaintenanceWorkItemController`** (namespace khác, không có `Synced\`) |
| `.comments.store` / `.destroy` | POST/DELETE | **`Technical\SolarMaintenanceCommentController`** (namespace khác) |
| `.approval.submit` / `.approve` / `.complete` / `.revision` / `.reject` / `.reopen` | POST | `Synced\Technical\SolarMaintenanceApprovalController` |
| `.assignment.approve` / `.assignment.accept` | POST | `Synced\Technical\SolarMaintenanceController` |
| `.claims.store` / `.claims.status` | POST | **`Technical\SolarWarrantyClaimController`** (namespace khác) |
| `.stock.store` / `.stock.status` | POST | **`Technical\SolarWarrantyStockController`** (namespace khác) |
| `.json` | GET | `Synced\Technical\SolarMaintenanceController@showJson` |
| `.update` | PUT | `Synced\Technical\SolarMaintenanceController@update` |
| `.status` | POST | `Synced\Technical\SolarMaintenanceController@updateStatus` |
| `.destroy` | DELETE | `Synced\Technical\SolarMaintenanceController@destroy` |
| `.show` | GET | `Synced\Technical\SolarMaintenanceDetailController@show` |

**Ghi nhận thực tế (không phải đề xuất sửa):** route trong CÙNG một nhóm nghiệp vụ được chia làm 2 namespace controller khác nhau — `App\Http\Controllers\Synced\Technical\*` (đa số) và `App\Http\Controllers\Technical\*` (work-items, comments, claims, stock). Cả 2 namespace đều có các class trùng tên (`SolarMaintenanceController`, `SolarMaintenanceApprovalController`, `SolarMaintenanceAttachmentController`, `SolarWarrantyClaimController` không trùng nhưng cùng họ) tồn tại song song trong source — namespace `Synced\` là bản đang được route trỏ tới cho phần lịch bảo trì/phê duyệt/tệp; namespace không có `Synced\` được dùng cho work-items/comments/claims/stock.

### 3.3. Service / Model / View liên quan

| Loại | Tên | Vai trò |
|---|---|---|
| Service truy vấn | `App\Services\Synced\Technical\SolarMaintenanceQueryService` | Lấy danh sách lịch, thống kê, workload kỹ thuật, tìm công trình |
| Service nghiệp vụ | `App\Services\Synced\Technical\SolarMaintenanceService` | `createSeries`, `update`, `changeStatus`, `softDelete`, đồng bộ người phụ trách |
| Service phê duyệt | `App\Services\Synced\Technical\SolarMaintenanceApprovalService` | `submit`, `approve`, `complete`, `requestRevision`, `reject`, `reopen` |
| Service truy vấn bảo hành (cũ) | `App\Services\Technical\SolarWarrantyQueryService` | Thống kê + danh sách phiếu/kho cho tab "claims"/"stock" |
| Model chính | `App\Models\SolarMaintenanceSchedule` (bảng `solar_maintenance_schedules`) | Lịch bảo trì |
| Model phiếu sự cố | `App\Models\SolarWarrantyClaim` (bảng `crm_serial_warranty_claims`) | Phiếu sự cố/bảo hành — **dùng chung với module Kỹ thuật mới** |
| Model kho (cũ) | `App\Models\SolarWarrantyStockMovement` (bảng `solar_warranty_stock_movements`) | Phiếu kho legacy, độc lập với `StockLedger`/`crm_product_stock` |
| View | `resources/views/synced/technical/maintenance/index.blade.php` (423 dòng), `show.blade.php` (1428 dòng), `site-show.blade.php` (101 dòng) | 3 view chính |
| Policy | `App\Policies\SolarMaintenanceSchedulePolicy` | Phân quyền theo action trên `SolarMaintenanceSchedule` |
| Repository | **Hiện tại chưa có** lớp Repository riêng — controller gọi thẳng Service, Service gọi thẳng Eloquent/Query Builder. |

## 4. Giao diện hiện tại

### 4.1. Cấu trúc trang `index` (5 tab qua query `?view=`)

- **Tổng quan O&M** (`overview`, mặc định): 6 thẻ KPI (tổng lịch, hôm nay, sắp đến hạn, quá hạn, chưa phân công, chờ duyệt), bảng "Công trình cần theo dõi" (8 dòng ưu tiên), biểu đồ donut theo trạng thái, danh sách cảnh báo, top 3 hoạt động nổi bật, bảng khối lượng việc theo kỹ thuật viên (6 người), lưới "tiến độ theo chu kỳ" (4 round), 2 sơ đồ quy trình tĩnh (8 bước bảo trì, 10 bước bảo hành — chỉ là hình minh họa, không phải state machine thật).
- **Lịch bảo trì** (`maintenance`): 6 thẻ KPI, filter (tìm kiếm theo mã/công trình/khách/SĐT, tháng, trạng thái, hạng mục, kỹ thuật viên, checkbox "Quá hạn"), bảng danh sách có phân trang (cột: Mã & công trình, Hạng mục/chu kỳ, Ngày dự kiến, Phụ trách, Trạng thái, Thao tác).
- **Phiếu sự cố & bảo hành** (`claims`): bảng (Mã phiếu/công trình, Thiết bị & hiện tượng, Ngày tiếp nhận, Phụ trách, Trạng thái, Xử lý), filter theo `claim_status`, `claim_type`, tìm kiếm `claim_q`.
- **Kho & đổi thiết bị** (`stock`): bảng (Mã phiếu kho, Nghiệp vụ, Phiếu bảo hành/công trình, Serial/kho, Ngày tạo, Trạng thái, Xử lý).
- **Hồ sơ công trình** (`files`): danh sách công trình dạng thẻ, mỗi thẻ có số hồ sơ + số lịch O&M, link sang trang chi tiết công trình.

### 4.2. Popup/offcanvas (không phải modal Bootstrap `.modal`, dùng `offcanvas` — khác hẳn kiểu popup ở module Kỹ thuật mới)

| Offcanvas | Mở bằng | Action form | Điều kiện hiện nút |
|---|---|---|---|
| `#tm4CreateDrawer` — "Tạo kế hoạch O&M" | Nút "Tạo kế hoạch" trên header/tab lịch | `POST projects-unified.maintenance.store` | `permissions['create']` (canManage) |
| `#tm4ClaimDrawer` — "Tiếp nhận sự cố / Tạo phiếu bảo hành" | Nút "Tạo phiếu sự cố" trên header | `POST projects-unified.maintenance.claims.store` | `permissions['claim_create']` |
| `#tm4StockDrawer` — "Kho bảo hành / Tạo phiếu xuất-thu hồi" | (không thấy nút trigger trực tiếp trên `index.blade.php` ngoài phần drawer tự thân; có trong `show.blade.php` dòng ~1030) | `POST projects-unified.maintenance.stock.store` | `permissions['stock_manage']` |

### 4.3. Hành động trên trang chi tiết đợt bảo trì (`show.blade.php`) — theo action route thực tế tìm thấy

| Tên nút / khối chức năng | Route gọi | Controller | Làm gì | Đổi dữ liệu | Trạng thái trước → sau |
|---|---|---|---|---|---|
| Cập nhật thông tin/phân công | `projects-unified.maintenance.update` (PUT) | `SolarMaintenanceController@update` | Sửa field kỹ thuật, đổi người phụ trách | `solar_maintenance_schedules`, `solar_maintenance_assignees`, có thể tạo `solar_maintenance_approvals` (assignment) | tuỳ theo có đổi `status` không; nếu đổi phân công → có thể chuyển sang chờ duyệt phân công |
| Đổi trạng thái thủ công | `projects-unified.maintenance.status` (POST) | `SolarMaintenanceController@updateStatus` | Đổi trạng thái theo `TRANSITIONS`, chặn các trạng thái thuộc luồng duyệt | `solar_maintenance_schedules`, `solar_maintenance_status_histories`, `solar_maintenance_audit_logs` | theo bảng TRANSITIONS (mục 9) |
| Duyệt phân công | `projects-unified.maintenance.assignment.approve` (POST) | `SolarMaintenanceController@approveAssignment` | Admin duyệt nhóm kỹ thuật đã chọn | `solar_maintenance_schedules.status` (nếu đang draft/scheduled/unassigned → `assigned`), `solar_maintenance_approvals` | `draft/scheduled/unassigned` → `assigned` |
| Nhận việc | `projects-unified.maintenance.assignment.accept` (POST) | `SolarMaintenanceController@acceptAssignment` | Người được phân công xác nhận đã nhận | `solar_maintenance_assignees.accepted_at/started_at` | `assigned/customer_confirmed/travelling` → `in_progress` |
| Thêm/sửa/xoá công việc con | `projects-unified.maintenance.work-items.*` | `Technical\SolarMaintenanceWorkItemController` | CRUD `solar_maintenance_work_items` | bảng `solar_maintenance_work_items` | không đổi status lịch cha |
| Bình luận | `projects-unified.maintenance.comments.*` | `Technical\SolarMaintenanceCommentController` | CRUD `solar_maintenance_comments` | bảng `solar_maintenance_comments` | — |
| Gửi duyệt | `projects-unified.maintenance.approval.submit` (POST) | `SolarMaintenanceApprovalController@submit` → `SolarMaintenanceApprovalService::submit` | Bắt buộc đã nhập `result_note` | `status=pending_approval`, `approval_status=pending`, tạo `solar_maintenance_approvals` + `solar_maintenance_status_histories` + `solar_maintenance_audit_logs` | `in_progress/waiting_material/waiting_submission/revision_requested` → `pending_approval` |
| Phê duyệt | `.approval.approve` (POST) | `SolarMaintenanceApprovalService::approve` | Người duyệt ≠ người thực hiện | `status=approved`, `approval_status=approved` + 3 bảng log | `pending_approval` → `approved` |
| Hoàn thành/đóng hồ sơ | `.approval.complete` (POST) | `SolarMaintenanceApprovalService::complete` | Chỉ khi đã `approved` | `status=completed`, `completed_at/completed_date` + 3 bảng log | `approved` → `completed` |
| Yêu cầu chỉnh sửa | `.approval.revision` (POST) | `SolarMaintenanceApprovalService::requestRevision` | Bắt buộc có nội dung | `status=revision_requested` + log | `pending_approval` → `revision_requested` |
| Từ chối | `.approval.reject` (POST) | `SolarMaintenanceApprovalService::reject` | Bắt buộc có lý do | `status=revision_requested`, `approval_status=rejected` + log | `pending_approval` → `revision_requested` (không có trạng thái "rejected" riêng cho lịch bảo trì — khác với phiếu sự cố có `rejected` riêng) |
| Mở lại | `.approval.reopen` (POST) | `SolarMaintenanceApprovalService::reopen` | Bắt buộc có lý do, chỉ Manager | reset toàn bộ field duyệt, `status=in_progress` + log | `approved/completed/pending_approval/revision_requested` → `in_progress` |
| Cập nhật trạng thái phiếu sự cố | `projects-unified.maintenance.claims.status` (POST) | `SolarWarrantyClaimController@updateStatus` | Đổi trạng thái phiếu sự cố (không áp dụng cho `replacement`/`paid_repair`) | `crm_serial_warranty_claims` + `crm_serial_warranty_events` (nếu có serial) | theo `SolarWarrantyClaim::TRANSITIONS` |
| Tạo/đổi trạng thái phiếu kho | `projects-unified.maintenance.stock.store` / `.stock.status` (POST) | `SolarWarrantyStockController` | Tạo phiếu xuất/thu hồi, hoàn tất phiếu sẽ cập nhật tồn kho serial thật | `solar_warranty_stock_movements`, khi `completed`: `crm_serial_unit_states`, `crm_inventory_events`, `crm_serial_event_lines`, `crm_serial_units.warehouse_id`, `crm_serial_warranty_events`, và field `replacement_serial_*`/`returned_serial_*` trên `crm_serial_warranty_claims` | `pending → approved → completed` (hoặc `cancelled`) |
| Upload/tải/xoá tệp công trình hoặc lịch | `.site-files.*`, `.schedule-files.*` | `SolarMaintenanceAttachmentController` | Upload private disk `local`, tải theo policy `view`, xoá theo policy `uploadAttachment` | `solar_maintenance_attachments`, `solar_site_documents` | — |

## 5. Quy trình Bảo trì

Trả lời đúng theo code (`SolarMaintenanceService`, `SolarMaintenanceApprovalService`, `SolarMaintenanceSchedule::TRANSITIONS`):

1. **Lịch bảo trì được tạo bằng cách nào?** Tạo thủ công qua form offcanvas "Tạo kế hoạch O&M", gọi `SolarMaintenanceController::store` → `SolarMaintenanceService::createSeries`.
2. **Ai tạo?** Người có quyền `SolarMaintenanceAccess::canManage()` (Policy `create`).
3. **Thủ công hay tự động?** Hoàn toàn thủ công — không có job/command tự sinh lịch định kỳ (xem mục 19).
4. **Có lấy từ Công trình không?** Có, bắt buộc — `site_id` bắt buộc tồn tại trong bảng `sites`; `customer_name`, `site_name`, `address`, `system_kwp` được lấy mặc định từ `Site` nếu form không nhập.
5. **Có chu kỳ tháng/quý/năm không?** Có dạng "số đợt (rounds_count, 1-24) × khoảng cách tháng (round_interval_months, 1-24)" — người dùng tự nhập số tháng giữa các đợt (không có preset cố định "quý"/"năm", chỉ nhập số tháng). Mỗi đợt tạo thành 1 dòng riêng trong `solar_maintenance_schedules`, cùng `round_group` (mã nhóm sinh ngẫu nhiên `SMG-...`).
6. **Giao kỹ thuật bằng cách nào?** Chọn danh sách `assigned_user_ids` (hoặc theo từng đợt `round_assignees.{round}`) ngay lúc tạo, hoặc sau này qua `update()`. Người đầu tiên trong danh sách luôn là `leader`, còn lại là `member` (`syncAssignees`). Chỉ nhân sự vượt qua `SolarMaintenanceAccess::isSelectableTechnician()` mới được chọn; nếu lịch có `company_id`, không được chọn nhân sự khác công ty.
7. **Kỹ thuật xem lịch ở đâu?** Trực tiếp tại tab "Lịch bảo trì" của trang này (lọc theo `assignee_id`), và tại trang chi tiết công trình (`.site`). Đồng thời được đồng bộ sang `technical_schedule_events`/`technical_schedule_event_users` (xem mục 16) để hiển thị ở Technical Workspace — nhưng **trên môi trường audit hiện tại 2 bảng này KHÔNG tồn tại** nên đồng bộ đang không chạy (silent no-op, xem `TechnicalScheduleSyncService::ready()`).
8. **Có xác nhận đến công trình không?** Có bước "Nhận việc" (`acceptAssignment`) ghi `accepted_at`/`started_at` trên `solar_maintenance_assignees`, chuyển `status → in_progress` nếu đang ở `assigned/customer_confirmed/travelling`. Không thấy cơ chế xác nhận riêng của **khách hàng** (trạng thái `customer_confirmed` tồn tại trong danh sách STATUSES/TRANSITIONS nhưng không tìm thấy action/controller nào đặt trạng thái này — xem GAP).
9. **Có checklist không?** Có 2 cơ chế cùng tồn tại: bảng mới `solar_maintenance_work_items` (model `SolarMaintenanceWorkItem`, có `progress_percent`, `status`) và bảng cũ `solar_maintenance_checklist_items` (model `SolarMaintenanceChecklistItem`) — code dùng `work_items` nếu có, fallback về `checklist_items` cũ nếu `work_items` rỗng (`SolarMaintenanceDetailController::show`, biến `$legacyChecklistFallback`). Trên DB đang chạy, bảng `solar_maintenance_checklist_items` **không tồn tại**.
10. **Có nhập kết quả bảo trì không?** Có — field `result_note` bắt buộc phải có nội dung trước khi `submit()` gửi duyệt (`SolarMaintenanceApprovalService::submit`).
11. **Có hình ảnh/file không?** Có — `solar_maintenance_attachments`, upload qua `.schedule-files.store`/`.site-files.store`, lưu disk `local` (private).
12. **Có phát hiện sự cố sau bảo trì không?** Có field liên quan trên `solar_maintenance_schedules` (`incident_kind`, `incident_material_note`, `incident_replacement_reason`, `incident_estimated_cost`, `execution_fault_note`) nhưng đây là các cột dữ liệu, **không thấy action nào tự động tạo phiếu `crm_serial_warranty_claims` từ các field này** — việc tạo phiếu sự cố vẫn phải làm thủ công qua offcanvas riêng (`claims.store`), có thể gắn `maintenance_schedule_id` để nối lại, nhưng không tự động.
13. **Có tạo công việc tiếp theo không?** Không thấy cơ chế tự tạo lịch/việc kế tiếp khi hoàn tất — mỗi round đã được tạo sẵn hết ngay từ lúc `createSeries()` (đặt `scheduled_date` tương lai cho từng round), không phải "hoàn tất round N thì tự sinh round N+1".
14. **Có duyệt kết quả không?** Có — `submit → approve/reject/requestRevision` bởi người **khác** người thực hiện (`assertNotExecutor`), thuộc cùng công ty.
15. **Hoàn tất bảo trì bằng điều kiện gì?** `complete()` chỉ chạy được khi `status === 'approved' && approval_status === 'approved'`; ngoại lệ: `applyStatusTimestamps()` cho phép Admin đặt `status = completed` trực tiếp qua `update()`/`updateStatus()` kể cả khi chưa được duyệt (điều kiện: `SolarMaintenanceAccess::isAdmin($actor)`).
16. **Có tạo lịch bảo trì kế tiếp không?** Không — không tìm thấy code tự sinh round mới sau khi hoàn tất; toàn bộ round của 1 chu kỳ được tạo 1 lần duy nhất lúc khởi tạo.

### Flow thực tế (Bảo trì)

```
CÔNG TRÌNH (sites)
   ↓
TẠO KẾ HOẠCH O&M (offcanvas "Tạo kế hoạch")
   → sinh N đợt (round 1..N) cùng lúc, cách nhau X tháng
   ↓
[có assigned_user_ids khi tạo?]
   ├─ Có → status = assigned
   └─ Không → status = unassigned
   ↓
(unassigned) → cập nhật phân công (update) → tạo bản ghi approvals(assignment, pending)
   ↓
Admin DUYỆT PHÂN CÔNG (assignment.approve) → status = assigned
   ↓
Người được phân công NHẬN VIỆC (assignment.accept) → status = in_progress
   ↓
KỸ THUẬT THỰC HIỆN (cập nhật work-items / checklist, technical_note, result_note...)
   ↓
[cần vật tư / chờ gửi duyệt?] → waiting_material / waiting_submission (đổi thủ công qua status)
   ↓
GỬI DUYỆT (approval.submit, bắt buộc có result_note) → status = pending_approval
   ↓
TRƯỞNG PHÒNG KỸ THUẬT XEM XÉT (người khác người thực hiện)
   ├─ Yêu cầu chỉnh sửa (approval.revision) → revision_requested → quay lại "KỸ THUẬT THỰC HIỆN"
   ├─ Từ chối (approval.reject) → revision_requested (kèm approval_status = rejected)
   └─ Phê duyệt (approval.approve) → status = approved
        ↓
      HOÀN THÀNH (approval.complete) → status = completed
```

Nhánh "phát hiện sự cố sau bảo trì" **không được nối tự động** trong code — nếu kỹ thuật phát hiện sự cố, phải tự tay mở offcanvas "Tạo phiếu sự cố" ở cùng trang (dữ liệu độc lập, chỉ liên kết bằng cách chọn `maintenance_schedule_id` thủ công nếu form cho chọn).

## 6. Quy trình Bảo hành (phiếu sự cố/bảo hành cũ trong module này)

- **Tạo phiếu từ đâu?** Offcanvas "Tiếp nhận sự cố" trên trang này (`claims.store`), hoặc `assigned_to` tự chọn nếu là kỹ thuật viên "chỉ thực thi".
- **Bắt buộc Công trình?** Có — `site_id` bắt buộc (`exists:sites,id`).
- **Bắt buộc serial?** Không bắt buộc — `serial_code` là `nullable`. Nếu có nhập, hệ thống tra `crm_serial_units`/`crm_serial_unit_identifiers`/`crm_serial_identifiers` để lấy `serial_unit_id`, `customer_id`, `order_id` (qua bảng `crm_serial_warranties`).
- **Serial được kiểm tra thế nào?** Chỉ kiểm tra tồn tại + đúng công ty (`assertSerialCompany` so `crm_serial_unit_states.company_id`). **Không kiểm tra thời hạn bảo hành** tại bước tạo phiếu (khác với module mới `/ky-thuat/de-xuat-doi-hang-bao-hanh` có kiểm tra `warranty_end_at` và chặn nếu hết hạn).
- **Kiểm tra thời hạn bảo hành ở đâu?** Không có trong controller này. Field `eligibility_check` tồn tại trong `STATUSES` nhưng không có logic kiểm tra tự động gắn với nó — chuyển sang trạng thái này hoàn toàn thủ công qua `updateStatus`.
- **Ai chẩn đoán?** Người được `assigned_to` (kỹ thuật) hoặc Manager, nhập `diagnosis`/`proposed_solution` khi gọi `updateStatus`.
- **Ai duyệt?** `SolarMaintenanceAccess::canApprove()`, và người duyệt không được là chính người xử lý (`assigned_to`), trừ Admin.
- **Có yêu cầu bổ sung không?** Không có trạng thái/action "yêu cầu bổ sung" riêng cho phiếu sự cố (khác quy trình lịch bảo trì có `revision_requested`, và khác hẳn module mới có `needs_more_information`). Chuyển ngược `pending_approval → diagnosing` là cách duy nhất để "trả lại".
- **Có từ chối không?** Có — trạng thái `rejected`, chỉ chuyển được từ `pending_approval`; từ `rejected` chỉ có thể quay lại `diagnosing`.
- **Có đổi thiết bị không?** Có, qua bảng kho riêng: hoàn tất phiếu kho loại `warranty_out` sẽ set `claim.replacement_serial_unit_id`/`replacement_serial_code` và chuyển `claim.status = replacing`.
- **Có sửa chữa không?** Không có field/khái niệm "sửa chữa" riêng trong luồng này — chỉ có `resolution`, `actual_cost`, `is_chargeable` (đánh dấu có tính phí hay không) trên chính phiếu, không tách thành báo giá/QA/bàn giao như module mới.
- **Có chuyển Kho không?** Có, nhưng **là một luồng Kho khác** (mục 17) — tạo `solar_warranty_stock_movements`, chỉ chạy được khi `claim.status` thuộc `['approved','waiting_stock','replacing','waiting_customer']`, và **claim phải KHÔNG thuộc loại flow mới** (`WarrantyFlow::isFlowType()` chặn).
- **Có thu hồi hàng lỗi không?** Có — phiếu kho loại `faulty_return`, hoàn tất sẽ set `claim.returned_serial_unit_id/returned_serial_code/returned_at`.
- **Có kết thúc phiếu không?** Có — `status = completed`, bắt buộc `resolution` không rỗng, chỉ Manager được đóng; đồng thời set `resolved_at`, `customer_confirmed_at` (nếu chưa có), `closed_at`, `closed_by`, `cost`.

### Liên kết với "Đổi hàng bảo hành" / "Sửa chữa tính phí"

Ghi đúng thực tế: **module này KHÔNG tự tạo claim cho 2 luồng đó**. Cụ thể:
- `SolarWarrantyClaimController::store` **từ chối thẳng** nếu `claim_type` gửi lên là `replacement` hoặc `paid_repair`, với thông báo: *"Đổi hàng bảo hành và Sửa chữa tính phí phải tạo tại Kỹ thuật → Bảo hành & Sửa chữa (quy trình riêng)."*
- `SolarWarrantyClaimController::updateStatus` và `SolarWarrantyStockController::store/updateStatus` đều chặn thao tác nếu `claim.claim_type` thuộc `WarrantyFlow::isFlowType()` (tức `replacement`/`paid_repair`), yêu cầu xử lý ở trang chi tiết phiếu của module mới.
- Tuy nhiên **danh sách hiển thị (tab "claims")** trên trang này **không lọc claim_type** — nếu 1 phiếu `replacement`/`paid_repair` tồn tại, nó vẫn hiện trong bảng, nhưng nhãn trạng thái sẽ hiển thị sai (xem mục 8, mục 20-P1).
- `SolarWarrantyStockController::store` có tham số `return_to = warranty_exchange` để sau khi tạo phiếu kho thì redirect sang `ky-thuat.warranty-exchange.show` — nhưng vì bước tạo phiếu bị chặn với claim thuộc flow mới ngay từ đầu, nhánh redirect này trên thực tế **không thể được kích hoạt** với dữ liệu hợp lệ (xem GAP P2).

## 7. Công trình ↔ Thiết bị ↔ Serial

| Quan hệ | Cột / bảng dùng thật |
|---|---|
| Lịch bảo trì ↔ Công trình | `solar_maintenance_schedules.site_id` → `sites.id` (không ràng buộc FK cứng trong code kiểm tra ngoài validate `exists:sites,id`; quan hệ Eloquent `SolarMaintenanceSchedule::site()` dùng `withoutGlobalScopes()`) |
| Phiếu sự cố ↔ Công trình | `crm_serial_warranty_claims.site_id` |
| Phiếu sự cố ↔ Lịch bảo trì | `crm_serial_warranty_claims.maintenance_schedule_id` → `solar_maintenance_schedules.id` (nullable, chỉ gắn khi người tạo chọn) |
| 1 Công trình có bao nhiêu lịch bảo trì | Không giới hạn — `SolarMaintenanceDetailController::site()` gom toàn bộ `solar_maintenance_schedules.site_id = X`, nhóm theo `round_group` thành các "chu kỳ" |
| 1 Công trình có bao nhiêu phiếu bảo hành | Không giới hạn — truy vấn trực tiếp theo `site_id` (ở trang site) hoặc theo `maintenance_schedule_id` (ở trang chi tiết 1 đợt, lấy **phiếu mới nhất** — `->latest('id')->first()`, tức 1 đợt bảo trì chỉ hiển thị **1** phiếu bảo hành liên kết dù có thể có nhiều) |
| Thiết bị công trình | **Không có bảng "thiết bị đã lắp" riêng cho công trình.** Trang `site-show.blade.php` lấy "serials" của công trình bằng cách join `crm_serial_warranties.site_id` (không phải qua 1 bảng thiết bị lắp đặt) |
| Serial nằm ở đâu | `crm_serial_units` (đơn vị serial) + `crm_serial_unit_identifiers`/`crm_serial_identifiers` (mã serial text) + `crm_serial_unit_states` (trạng thái/kho hiện tại) + `crm_serial_warranties` (thời hạn bảo hành, `customer_id`, `order_id`, `site_id`) |
| Khách hàng lấy từ đâu | Không có bảng khách hàng riêng cho module này — `customer_name` là cột text tự do trên `solar_maintenance_schedules` (copy từ `site.contact_name` lúc tạo, không phải FK); với phiếu sự cố, `customer_id` lấy gián tiếp qua `crm_serial_warranties.customer_id` của serial đã chọn (chỉ có khi có nhập serial) |

**Kết luận về tính "liên kết thật":** Công trình → Lịch bảo trì là liên kết FK thật (site_id). Công trình → Serial là liên kết thật nhưng gián tiếp qua bảng `crm_serial_warranties.site_id` (không qua một bảng "thiết bị của công trình"). Khách hàng ở lịch bảo trì **chỉ là text hiển thị**, không phải liên kết thật tới bảng khách hàng.

## 8. Serial

| Câu hỏi | Trả lời từ source |
|---|---|
| Serial lấy từ bảng nào | `crm_serial_units` (bảng gốc) + `crm_serial_unit_identifiers` + `crm_serial_identifiers` (mã code) |
| Có `product_id` không | Có — `crm_serial_units.product_id` |
| Có `customer_id` không | Không trực tiếp trên `crm_serial_units`; có trên `crm_serial_warranties.customer_id` (qua join `serial_unit_id`) |
| Có `order_id` không | Tương tự — trên `crm_serial_warranties.order_id`, không trực tiếp trên `crm_serial_units` |
| Có `site_id` không | Có trên `crm_serial_warranties.site_id` |
| Có warranty record không | Có — bảng `crm_serial_warranties` (`warranty_start_at`, `warranty_end_at`, `warranty_months`, `status`) |
| Trạng thái hiện tại nằm bảng nào | `crm_serial_unit_states` (`state`, `warehouse_id`, `company_id`) |
| Thao tác trên module này có cập nhật serial không | **Có**, nhưng chỉ khi hoàn tất phiếu kho (`SolarWarrantyStockController::updateStatus`, khi `new === 'completed'`): gọi `applyInventoryMovement()` ghi `crm_inventory_events` + `crm_serial_event_lines`, rồi `UPDATE/INSERT crm_serial_unit_states` (đổi `state`, `warehouse_id`), và nếu bảng `crm_serial_units` có cột `warehouse_id` thì cập nhật luôn cột đó. Đồng thời gọi `syncReplacementWarranty()` để cập nhật `crm_serial_warranties` (đánh dấu serial cũ `status = replaced`, tạo/`updateOrInsert` bản ghi bảo hành cho serial mới). Việc *tạo* phiếu sự cố (`SolarWarrantyClaimController::store`) chỉ ĐỌC serial, không ghi. |

## 9. Trạng thái

### 9.1. Trạng thái Lịch bảo trì (`SolarMaintenanceSchedule::STATUSES`, cột `status`)

| Status | Tên hiển thị | Ý nghĩa (theo code) | Ai được chuyển | Chuyển từ (theo `TRANSITIONS`) | Chuyển sang |
|---|---|---|---|---|---|
| `draft` | Nháp | Mới tạo, chưa gán ai | Người tạo/Manager | (khởi tạo) | scheduled, unassigned, assigned, cancelled |
| `scheduled` | Đã lên lịch | — | update/changeStatus | draft | unassigned, assigned, customer_confirmed, travelling, in_progress, postponed, cancelled |
| `unassigned` | Chờ phân công | Tạo nhưng chưa chọn ai | update/changeStatus | draft, scheduled, postponed, cancelled | scheduled, assigned, postponed, cancelled |
| `assigned` | Đã phân công | Có `assigned_user_ids` | tạo có sẵn assignee, hoặc Admin duyệt phân công | draft, scheduled, unassigned, postponed, cancelled | scheduled, customer_confirmed, travelling, in_progress, postponed, cancelled |
| `customer_confirmed` | Khách đã xác nhận | **Không thấy action nào đặt trạng thái này** ngoài đổi thủ công qua `status` form | update/changeStatus (thủ công) | scheduled, assigned | travelling, in_progress, postponed, cancelled |
| `travelling` | Đang di chuyển | Thủ công | update/changeStatus | scheduled, assigned, customer_confirmed | in_progress, postponed, cancelled |
| `in_progress` | Đang thực hiện | Người được phân công đã "Nhận việc", hoặc đổi thủ công | acceptAssignment / update / reopen | assigned, customer_confirmed, travelling, waiting_material, waiting_submission, revision_requested (qua reopen), approved (qua reopen), completed (qua reopen) | waiting_material, waiting_submission, pending_approval, postponed, cancelled |
| `waiting_material` | Chờ vật tư | Thủ công | update/changeStatus | in_progress | in_progress, waiting_submission, pending_approval, postponed, cancelled |
| `waiting_submission` | Chờ gửi duyệt | Thủ công | update/changeStatus | in_progress, waiting_material, revision_requested | in_progress, pending_approval, postponed, cancelled |
| `pending_approval` | Chờ phê duyệt | Đã "Gửi duyệt", bắt buộc có `result_note` | `approval.submit` | in_progress, waiting_material, waiting_submission, revision_requested | revision_requested, approved, cancelled (chỉ qua approval action) |
| `revision_requested` | Yêu cầu chỉnh sửa | Bị trả lại hoặc từ chối | `approval.revision` / `approval.reject` | pending_approval | in_progress, waiting_submission, pending_approval, cancelled |
| `approved` | Đã phê duyệt | Trưởng phòng đã duyệt | `approval.approve` | pending_approval | waiting_customer, completed, in_progress (chỉ qua reopen) |
| `waiting_customer` | Chờ khách xác nhận | **Không thấy action đặt trạng thái này** | thủ công | approved | completed, in_progress, postponed, cancelled |
| `completed` | Hoàn thành | Đã đóng hồ sơ | `approval.complete` (hoặc Admin qua update/status) | approved | in_progress (chỉ qua reopen) |
| `postponed` | Hoãn | Thủ công | update/changeStatus | scheduled, unassigned, assigned, customer_confirmed, travelling, in_progress, waiting_material, waiting_submission, waiting_customer | scheduled, unassigned, assigned, cancelled |
| `cancelled` | Đã hủy | Thủ công, ghi `cancellation_reason` | update/changeStatus | hầu hết trạng thái | scheduled, unassigned |

Ghi chú xác nhận từ code: `pending_approval`, `approved`, `revision_requested`, `completed` **không thể** đặt qua `update()`/`updateStatus()` thông thường — bị chặn cứng, bắt buộc dùng đúng action `approval.*` (trừ ngoại lệ Admin có thể ép `completed` qua `update`/`updateStatus`).

### 9.2. Trạng thái phê duyệt riêng (`SolarMaintenanceSchedule::APPROVAL_STATUSES`, cột `approval_status`)
`not_required`, `not_submitted`, `pending`, `approved`, `revision_requested`, `rejected` — chạy song song với `status`, không phải state machine riêng, chỉ là nhãn phụ được set cùng lúc trong `SolarMaintenanceApprovalService`.

### 9.3. Trạng thái duyệt phân công (`ASSIGNMENT_APPROVAL_STATUSES`)
`not_required`, `draft`, `pending`, `approved`, `revision_requested`, `rejected` — dùng cho bản ghi `solar_maintenance_approvals` với `approval_level = assignment`.

### 9.4. Trạng thái Phiếu sự cố/bảo hành (`SolarWarrantyClaim::STATUSES`, cột `status` trên `crm_serial_warranty_claims`)

| Status | Tên hiển thị | Ai được chuyển | Chuyển từ | Chuyển sang |
|---|---|---|---|---|
| `received` | Mới tiếp nhận | (khởi tạo) | cancelled (mở lại) | eligibility_check, diagnosing, cancelled |
| `eligibility_check` | Kiểm tra điều kiện BH | Người xử lý/Manager | received | diagnosing, rejected, cancelled |
| `diagnosing` | Đang chẩn đoán | Người xử lý/Manager | received, eligibility_check, solution_proposed, rejected | solution_proposed, rejected, cancelled |
| `solution_proposed` | Đã đề xuất phương án | Người xử lý/Manager | diagnosing | pending_approval, diagnosing, cancelled |
| `pending_approval` | Chờ duyệt | Người xử lý/Manager | solution_proposed | approved, diagnosing, rejected |
| `approved` | Đã duyệt | Người duyệt ≠ người xử lý | pending_approval | waiting_stock, replacing, cancelled |
| `waiting_stock` | Chờ kho chuẩn bị | tự set khi tạo phiếu kho ở trạng thái approved/diagnosing/solution_proposed | approved, replacing (khi `replacement_receive` hoàn tất) | replacing, cancelled |
| `replacing` | Đang thay thế/xử lý | tự set khi phiếu kho `warranty_out` hoàn tất | approved, waiting_stock, completed (mở lại) | waiting_customer, waiting_stock |
| `waiting_customer` | Chờ khách xác nhận | thủ công | replacing | completed, replacing |
| `completed` | Hoàn thành | Chỉ Manager, bắt buộc có `resolution` | waiting_customer, replacing | replacing (mở lại) |
| `rejected` | Từ chối bảo hành | Người duyệt ≠ người xử lý | eligibility_check, diagnosing, pending_approval | diagnosing |
| `cancelled` | Đã hủy | Chỉ Manager | received, eligibility_check, diagnosing, solution_proposed | received (mở lại) |

**Cảnh báo quan trọng (dữ kiện, không phải đề xuất):** Đây là bộ trạng thái **KHÁC** với `App\Support\Warranty\WarrantyFlow::EXCHANGE_STATUSES` (17 trạng thái, có `needs_more_information`, `reserved`, `issued`, `technician_received`, `waiting_faulty_return`, `faulty_returned`...) và **KHÁC HẲN** `WarrantyFlow::REPAIR_STATUSES` (17 trạng thái hoàn toàn khác: `quotation_draft`, `waiting_parts`, `repairing`, `qa_testing`, `handed_over`...) — dù CÙNG là cột `status` trên CÙNG bảng `crm_serial_warranty_claims`. Do `claim_type` bị phân luồng cứng ở tầng controller (mục 6), 2 bộ vocabulary này **không ghi đè lẫn nhau trên cùng 1 dòng** trong điều kiện vận hành đúng, nhưng khi **hiển thị chung 1 danh sách** (tab "claims" ở trang này) thì nhãn trạng thái của claim thuộc flow mới sẽ không tra được trong `SolarWarrantyClaim::STATUSES` (xem GAP P1).

### 9.5. Trạng thái Phiếu kho legacy (`SolarWarrantyStockMovement::STATUSES`)
`pending → approved/completed/cancelled`, `approved → completed/cancelled`, `completed` (kết thúc), `cancelled → pending`. Người chuyển: `SolarMaintenanceAccess::canHandleWarrantyStock()`.

## 10. Phân quyền

Đọc từ `App\Support\SolarMaintenanceAccess`, `SolarMaintenanceSchedulePolicy`, và các `abort_unless`/kiểm tra trong controller — **kiểm server-side, không chỉ nút UI**.

| Action | Kỹ thuật viên (isTechnicianOnly) | Trưởng phòng KT (isManager/isTechnicalLead) | Kho (isWarehouse/canHandleWarrantyStock) | Admin/GĐ |
|---|---|---|---|---|
| Xem danh sách lịch | ✅ (thấy toàn bộ mọi công ty — global scope bỏ qua cho kỹ thuật) | ✅ (theo công ty đang làm việc) | ✅ nếu `canViewAny` đúng (Kho nằm trong danh sách role được `canViewAny`) | ✅ toàn bộ |
| Xem chi tiết 1 lịch | ✅ chỉ nếu được phân công (`isAssigned`) | ✅ | ✅ (nếu qua `canViewAny`, không phân biệt phân công) | ✅ |
| Tạo lịch | ❌ (cần `canManage`) | ✅ | ❌ | ✅ |
| Sửa lịch | ✅ chỉ nếu được phân công | ✅ | ❌ (trừ khi có permission `maintenance.update`) | ✅ |
| Đổi trạng thái thường | ✅ chỉ nếu được phân công (giống `update`) | ✅ | ❌ | ✅ |
| Gửi duyệt | ✅ chỉ người được phân công (`isAssigned`) | ✅ | ❌ | ✅ |
| Duyệt/Từ chối/Yêu cầu sửa | ❌ (và không được tự duyệt việc của mình) | ✅ (canApprove, khác người thực hiện) | ❌ | ✅ |
| Hoàn tất (approval.complete) | ❌ | ✅ | ❌ | ✅ |
| Mở lại (reopen) | ❌ | ✅ (isManager) | ❌ | ✅ |
| Hủy lịch | tuỳ (qua `changeStatus`, không có rule riêng ngoài update-permission) | ✅ | — | ✅ |
| Duyệt phân công (assignment.approve) | ❌ | ❌ (chỉ Admin theo code — `abort_unless(SolarMaintenanceAccess::isAdmin(...))`, **không phải Manager**) | ❌ | ✅ |
| Nhận việc (assignment.accept) | ✅ nếu là người được phân công | ✅ nếu được phân công | — | ✅ |
| Upload file | ✅ (mọi kỹ thuật, không cần được phân công) | ✅ | — | ✅ |
| Xoá file | Theo policy `uploadAttachment` (tương tự upload — không có kiểm tra bổ sung theo trạng thái hoàn tất, xem GAP) | ✅ | — | ✅ |
| Tạo phiếu sự cố (claim) | ✅ (nếu `canCreateWarrantyClaim`; tự động gán `assigned_to = chính mình`) | ✅ | ❌ | ✅ |
| Đổi trạng thái phiếu sự cố | ✅ chỉ nếu `assigned_to = mình` | ✅ | ❌ | ✅ |
| Duyệt/Từ chối phiếu sự cố | ❌ (và không tự duyệt phiếu của mình) | ✅ (canApprove) | ❌ | ✅ |
| Đóng/Hủy phiếu sự cố | ❌ | ✅ (isManager) | ❌ | ✅ |
| Tạo phiếu kho (xuất/thu hồi) | ❌ | ❌ (trừ khi cũng có quyền Kho) | ✅ (`canHandleWarrantyStock`) | ✅ |
| Duyệt/Hoàn tất phiếu kho | ❌ | ❌ | ✅ | ✅ |
| Xem chi phí (`canViewCosts` / `canViewMaintenanceCosts`) | Theo permission cụ thể trong `SolarMaintenanceAccess` (không mặc định) | ✅ | Không liên quan | ✅ |

## 11. Database

| Bảng | Model | Chức năng | Khóa chính | FK/ID quan trọng | Cột trạng thái | Liên kết |
|---|---|---|---|---|---|---|
| `solar_maintenance_schedules` | `SolarMaintenanceSchedule` | 1 đợt bảo trì | `id` | `site_id`, `project_id`, `maintenance_profile_id`, `assigned_to`, `created_by`, `approved_by`, `submitted_by` | `status`, `approval_status`, `assignment_approval_status` | 1-n với assignees/approvals/attachments/statusHistories/auditLogs/workItems/comments/warrantyClaims |
| `solar_maintenance_assignees` | `SolarMaintenanceAssignee` | Người phụ trách 1 đợt | `id` | `maintenance_schedule_id`, `user_id`, `assigned_by` | `role` (`leader`/`member`), `is_leader` | n-1 với schedule |
| `solar_maintenance_approvals` | `SolarMaintenanceApproval` | Lịch sử phê duyệt (kể cả assignment) | `id` | `maintenance_schedule_id`, `submitted_by`, `approver_id` | `status`, `approval_level` | n-1 với schedule |
| `solar_maintenance_status_histories` | `SolarMaintenanceStatusHistory` | Lịch sử đổi `status` | `id` | `maintenance_schedule_id`, `changed_by` | `from_status`, `to_status` | n-1 với schedule |
| `solar_maintenance_audit_logs` | `SolarMaintenanceAuditLog` | Nhật ký audit (before/after) | `id` | `maintenance_schedule_id`, `user_id` | `action` | n-1 với schedule |
| `solar_maintenance_attachments` | `SolarMaintenanceAttachment` | Tệp đính kèm | `id` | `maintenance_schedule_id`, `maintenance_work_item_id`, `checklist_item_id`, `site_id`, `uploaded_by` | — | n-1 với schedule/work-item |
| `solar_maintenance_work_items` | `SolarMaintenanceWorkItem` | Công việc con (checklist mới) | `id` | `maintenance_schedule_id`, `assigned_to`, `created_by` | `status` (`SolarMaintenanceWorkItem::STATUSES`) | n-1 với schedule |
| `solar_maintenance_checklist_items` | `SolarMaintenanceChecklistItem` | Checklist cũ (legacy, không có trên DB đang chạy) | `id` | `maintenance_schedule_id` | `is_done` | n-1 với schedule |
| `solar_maintenance_comments` | `SolarMaintenanceComment` | Bình luận | `id` | `maintenance_schedule_id`, `user_id` | — | n-1 với schedule |
| `crm_serial_warranty_claims` | `SolarWarrantyClaim` | Phiếu sự cố/bảo hành — **DÙNG CHUNG với module Kỹ thuật mới** | `id` | `site_id`, `maintenance_schedule_id`, `serial_unit_id`, `customer_id`, `order_id`, `assigned_to`, `created_by`, `approved_by` | `status`, `approval_status`, `claim_type` | 1-n `stockMovements`; n-1 `site`, `schedule` |
| `solar_warranty_stock_movements` | `SolarWarrantyStockMovement` | Phiếu kho legacy của claim cũ | `id` | `warranty_claim_id`, `site_id`, `warehouse_id`, `product_id`, `serial_unit_id`, `related_serial_unit_id`, `requested_by`, `approved_by`, `completed_by` | `status`, `movement_type` | n-1 với `claim` |
| `sites` | `Site` | Công trình | `id` | `company_id` | — | 1-n với schedules, claims |
| `crm_serial_units` | (không có model riêng thấy dùng ở đây, truy vấn thẳng qua `DB::table`) | Đơn vị serial vật lý | `id` | `product_id`, `warehouse_id` | — | 1-1 `crm_serial_unit_identifiers` |
| `crm_serial_unit_states` | — | Trạng thái/kho hiện tại của serial | `serial_unit_id` (khoá theo `updateOrInsert`) | `serial_unit_id`, `warehouse_id`, `company_id` | `state` | — |
| `crm_serial_warranties` | — | Bảo hành theo serial | `id` | `serial_unit_id`, `customer_id`, `order_id`, `site_id` | `status` | — |
| `crm_serial_warranty_events` | — | Log sự kiện serial (ghi thêm, không có model) | `id` | `serial_unit_id`, `customer_id`, `order_id`, `created_by` | `event_type` | — |
| `technical_schedule_events` / `technical_schedule_event_users` | `TechnicalScheduleEvent` | Đồng bộ sự kiện sang Technical Workspace | `id` | `source_type`, `source_id` | `status` | 1-n user (event_users); **không tồn tại trên DB đang audit** |

## 12. Dữ liệu được ghi khi thao tác

| Action | INSERT | UPDATE | Event/Log | Notification | Transaction |
|---|---|---|---|---|---|
| Tạo kế hoạch (`createSeries`) | N dòng `solar_maintenance_schedules`, N dòng `solar_maintenance_assignees`, N dòng `solar_maintenance_status_histories`, N dòng `solar_maintenance_audit_logs` | — | status history + audit log mỗi round | Không | Có — toàn bộ N round trong 1 `DB::transaction` |
| Cập nhật lịch (`update`) | có thể thêm `solar_maintenance_approvals` (nếu đổi phân công) | `solar_maintenance_schedules`, xoá & tạo lại `solar_maintenance_assignees` (nếu đổi phân công) | `solar_maintenance_status_histories` (nếu đổi status), `solar_maintenance_audit_logs` | Không | Có |
| Đổi trạng thái (`changeStatus`) | — | `solar_maintenance_schedules` | `solar_maintenance_status_histories`, `solar_maintenance_audit_logs` | Không | Có |
| Duyệt phân công | `solar_maintenance_approvals` | `solar_maintenance_schedules.status` (nếu cần) | — | Không | Có |
| Nhận việc | — | `solar_maintenance_assignees` (`accepted_at`, `started_at`), có thể `schedules.status` | — | Không | Có |
| Gửi duyệt/Duyệt/Hoàn thành/Từ chối/Yêu cầu sửa/Mở lại | `solar_maintenance_approvals` | `solar_maintenance_schedules` | `solar_maintenance_status_histories`, `solar_maintenance_audit_logs` | Không | Có (mỗi action riêng 1 `DB::transaction`) |
| Xoá mềm (`softDelete`) | — | `solar_maintenance_schedules.deleted_at` | `solar_maintenance_audit_logs` (action `soft_deleted`) | Không | Có |
| Tạo phiếu sự cố (`claims.store`) | `crm_serial_warranty_claims`, `crm_serial_warranty_events` (nếu có serial) | — | event `maintenance_claim_received` | Không | Có |
| Đổi trạng thái phiếu sự cố (`claims.status`) | `crm_serial_warranty_events` (nếu có serial) | `crm_serial_warranty_claims` | event `maintenance_claim_status` | Không | Có |
| Tạo phiếu kho (`stock.store`) | `solar_warranty_stock_movements` | có thể `crm_serial_warranty_claims.status = waiting_stock` | Không có log riêng cho bước "pending" | Không | Có |
| Hoàn tất phiếu kho (`stock.status` → completed) | `crm_inventory_events`, `crm_serial_event_lines`, `crm_serial_warranty_events` | `solar_warranty_stock_movements`, `crm_serial_unit_states` (upsert), `crm_serial_units.warehouse_id` (nếu có cột), `crm_serial_warranties` (upsert cho serial mới + update serial cũ), `crm_serial_warranty_claims` (field liên quan thay thế/thu hồi) | event `maintenance_<movement_type>` | Không | Có |
| Chuyển Kho khi hoàn tất mà thiếu bảng cần thiết | — | — | Ném `ValidationException`, không ghi gì (transaction rollback) | Không | Có (an toàn — không ghi 1 phần) |

## 13. Audit log

- **Có audit log thật cho lịch bảo trì**: bảng `solar_maintenance_audit_logs`, ghi `action`, `old_values`/`new_values` (JSON toàn bộ record), `user_id`, `ip_address`, `user_agent`, `created_at`. Ghi tại mọi thao tác tạo/sửa/đổi trạng thái/xoá/duyệt (liệt kê ở mục 12).
- **Có lịch sử chuyển trạng thái riêng**: `solar_maintenance_status_histories` (`from_status`, `to_status`, `reason`, `note`, `changed_by`, `changed_at`, `metadata` JSON).
- **Phiếu sự cố/bảo hành (cũ) và phiếu kho legacy KHÔNG có audit log dạng before/after riêng** — chỉ có `crm_serial_warranty_events` (chỉ ghi khi có `serial_unit_id`, chỉ có `event_type` + `note` dạng text, **không có from_status/to_status/before-after** dữ liệu có cấu trúc, chỉ có `created_by`, không có `ip_address`).
- Không phát hiện audit log cho: tạo/xoá `solar_maintenance_work_items`, `solar_maintenance_comments`, `solar_maintenance_attachments` (không có bảng log riêng cho các thao tác này ngoài chính bảng dữ liệu đó).

## 14. Notification

**Hiện tại chưa có notification thật** (không có email, không có push, không có bản ghi bảng "notifications"/"thông báo" nào được tạo bởi module này — không tìm thấy `Notification::`, `->notify(`, hay `Mail::` trong toàn bộ `app/Services/Synced/Technical`, `app/Http/Controllers/Synced/Technical`, và các controller `SolarWarranty*`/`SolarMaintenance*` liên quan). Toàn bộ "cảnh báo" trên UI (badge số trên tab, KPI quá hạn, khối "Cảnh báo & cần chú ý") là **query trực tiếp lúc render trang**, không phải cơ chế đẩy thông báo. Người dùng chỉ biết có việc mới nếu tự mở trang.

## 15. File / Minh chứng

- **Có upload file**: `solar_maintenance_attachments` (theo lịch/site/work-item/checklist-item) và `solar_site_documents` (theo site).
- **Lưu ở đâu**: `disk = 'local'` — **private**, không phải disk `public`.
- **Bảng metadata**: `solar_maintenance_attachments` (cột `disk`, `file_name`, `original_name`, `file_path`, `mime_type`, `file_size`, `category`, `is_customer_visible`, `uploaded_by`).
- **Ai được tải**: `previewSchedule`/`downloadSchedule` kiểm `$this->authorize('view', $attachment->schedule)` (Policy `view` — theo công ty + phân công nếu là kỹ thuật viên chỉ-thực-thi). File công trình (`site-files`) kiểm quyền `canViewAny` + cùng công ty.
- **Có authorization**: Có, ở tầng Policy/`abort_unless`, không phải chỉ ẩn nút.
- **Có thể xoá sau khi hoàn tất không**: **Có** — `destroySchedule`/`destroySite` chỉ kiểm `$request->user()->can('uploadAttachment', ...)`, **không kiểm tra `schedule.status` đã `completed` hay chưa** — tức tệp minh chứng vẫn xoá được sau khi lịch đã hoàn tất và đã được duyệt (xem GAP P0).

## 16. Liên kết Kỹ thuật

- **Có**, qua cơ chế đồng bộ sự kiện: `SolarMaintenanceSchedule::booted()` đăng ký `static::saved()` và `static::deleted()` gọi `App\Services\Technical\TechnicalScheduleSyncService::syncMaintenance()` / `removeMaintenance()`.
- Cách hoạt động chính xác: mỗi lần lưu 1 `solar_maintenance_schedules`, service tạo/cập nhật 1 bản ghi trong `technical_schedule_events` (loại `source_type = 'maintenance'`, `source_id = schedule.id`) kèm danh sách người liên quan trong `technical_schedule_event_users` (gộp từ `assignees`, `assigned_to`, `assigned_user_ids`). Bảng `technical_schedule_events` là nguồn dữ liệu chung cho lịch/kế hoạch của Technical Workspace (dùng chung với sự kiện từ `project_test_projects` qua `syncProject()`).
- **Trạng thái thực tế trên môi trường audit**: bảng `technical_schedule_events`/`technical_schedule_event_users` **KHÔNG tồn tại** trên DB đang chạy (`Schema::hasTable()` trả `false`) → `TechnicalScheduleSyncService::ready()` trả `false` → toàn bộ đồng bộ **hiện đang no-op (không chạy)**, dù code gọi đúng và không lỗi (có kiểm tra `ready()` trước khi làm gì).
- Không tìm thấy liên kết trực tiếp nào khác tới "kế hoạch tuần", "báo cáo ngày", hay "KPI" của module Kỹ thuật (không có code nào trong các bảng KPI/báo cáo ngày đọc trực tiếp từ `solar_maintenance_schedules` hay `crm_serial_warranty_claims`).

## 17. Liên kết Kho

Trả lời đúng 5 câu hỏi bắt buộc:

1. **Xuất hàng có trừ tồn thật không?** Có, nhưng chỉ khi **hoàn tất** phiếu kho (`status = completed`): với `movement_type = warranty_out`, code đổi `crm_serial_unit_states.state` từ `in_stock` (đúng kho đã chọn) sang `sold`, và cập nhật `crm_serial_units.warehouse_id = null` nếu cột tồn tại. Đây là trừ theo **serial cụ thể** (từng đơn vị), không phải trừ số lượng (`quantity`) trong bảng tồn kho số lượng (`crm_product_stock`) — module này **không đụng tới** `crm_product_stock`/`StockLedger` mà module Kỹ thuật mới đang dùng.
2. **Reserve (`pending`/`approved`) có trừ tồn không?** Không — chỉ tạo bản ghi `solar_warranty_stock_movements.status = pending/approved`; `crm_serial_unit_states` chỉ bị đổi khi `status = completed`. Tuy vậy có ràng buộc mềm: khi tạo phiếu mới (`store`), nếu serial đó đã có 1 phiếu khác đang `pending`/`approved` thì bị chặn tạo trùng (`$duplicated` check).
3. **Thu hồi hàng lỗi nhập vào đâu?** `movement_type = faulty_return`, hoàn tất sẽ set `crm_serial_unit_states.state = 'damaged'`, `warehouse_id` = kho đã chọn khi tạo phiếu.
4. **Hàng lỗi có bị cộng vào tồn bán được không?** **Không** — trạng thái sau thu hồi là `damaged`, khác với `in_stock` (trạng thái coi là sẵn sàng bán/dùng cho `warranty_out`). Muốn xử lý tiếp phải qua nghiệp vụ riêng `supplier_send` (gửi nhà cung cấp, chuyển sang `supplier_warranty`) — không có đường tự động đưa `damaged` trở lại `in_stock` trong controller này.
5. **Linh kiện dư có hoàn kho không?** Khái niệm "linh kiện dư theo số lượng" **không tồn tại trong module này** — toàn bộ nghiệp vụ ở đây làm việc theo **serial đơn chiếc** (đổi nguyên thiết bị), không có khái niệm số lượng/linh kiện như module sửa chữa tính phí mới (`warranty_repair_parts`, `qty_issued/qty_used/qty_returned`). Bảng `solar_warranty_stock_movements.quantity` tồn tại nhưng validate luôn ép `quantity = 1` (`'quantity' => ['nullable','numeric','min:1','max:1']`).

## 18. Liên kết Đổi hàng bảo hành / Sửa chữa tính phí / Kho → Xuất hàng BH/SC

Ghi đúng thực tế, không suy diễn:

- **Dùng chung bảng?** Có — cả 2 hệ thống (cũ ở đây, mới ở `/ky-thuat/...`) đều đọc/ghi cùng bảng `crm_serial_warranty_claims` qua cùng 1 Eloquent model `App\Models\SolarWarrantyClaim`. Tab "claims" ở trang này sẽ **liệt kê cả** phiếu `replacement`/`paid_repair` tạo từ module mới (vì câu query `SolarWarrantyQueryService::claims()` không lọc `claim_type`).
- **Dùng chung serial?** Có — cùng bảng gốc `crm_serial_units`/`crm_serial_unit_states`/`crm_serial_warranties`, nhưng **cơ chế trừ/cộng tồn khác nhau hoàn toàn**: module này thao tác trực tiếp `crm_serial_unit_states` qua `SolarWarrantyStockController`; module mới thao tác qua `App\Services\Warranty\StockLedger`/`WarrantyExchangeService`/`RepairService` với cơ chế khoá dòng (`lockForUpdate`), bảng `warranty_serial_reservations`, và validate riêng — 2 codepath độc lập, không gọi lẫn nhau.
- **Link qua route?** Có 1 điểm: tham số `return_to=warranty_exchange` trong `SolarWarrantyStockController::store` sẽ redirect về `ky-thuat.warranty-exchange.show`. Nhưng vì bước tạo phiếu kho ở controller này **luôn từ chối** claim thuộc `WarrantyFlow::isFlowType()` (dòng 39-43), nhánh redirect này **không có đường hợp lệ nào để chạy tới** trong điều kiện dữ liệu đúng — chỉ còn tồn tại như code chết (dead branch).
- **Tự tạo claim?** Không — module này **bị chặn cứng** không cho tạo `claim_type = replacement/paid_repair` (mục 6).
- **Chỉ hiển thị hay đã kết nối thật?** Kết luận: **hiển thị lẫn (list chung), nhưng nghiệp vụ (tạo/đổi trạng thái/tạo phiếu kho) đã được TÁCH BIỆT HOÀN TOÀN và có kiểm tra chặn chéo ở cả 2 phía** (mục 6 và các đoạn code `WarrantyFlow::isFlowType()` chặn ở `SolarWarrantyClaimController`/`SolarWarrantyStockController`). Không có bất kỳ chỗ nào trong module này gọi tới `App\Services\Warranty\WarrantyExchangeService`, `RepairService`, hay route `/kho/xuat-hang-bh-sc`.

## 19. Cron / Job / Scheduler

**Hiện tại chưa có.** Không tìm thấy entry nào liên quan `SolarMaintenance`/`solar_maintenance`/`SolarWarranty`/`warranty` trong `app/Console/Kernel.php`, `routes/console.php`, hay trong thư mục `app/Console/Commands`. Không có job tự tạo lịch bảo trì, không có job nhắc lịch/cảnh báo quá hạn, không có job cập nhật trạng thái tự động. Mọi số liệu "quá hạn" (`isOverdue()`) đều tính **on-the-fly** lúc render trang (so `scheduled_date` với `today()`), không lưu lại trạng thái "overdue" trong DB.

## 20. Các GAP phát hiện

### P0 — nguy cơ sai dữ liệu

1. **2 bộ vocabulary trạng thái khác nhau dùng chung 1 cột `status` trên `crm_serial_warranty_claims`.** `SolarWarrantyClaim::STATUSES`/`TRANSITIONS` (module này) và `WarrantyFlow::EXCHANGE_STATUSES`/`REPAIR_STATUSES` (module mới) không tương thích. Hiện được ngăn ghi đè bằng kiểm tra `claim_type` ở tầng controller, nhưng **không có ràng buộc ở tầng database** (không có CHECK constraint hay cột enum) — nếu trong tương lai có bất kỳ đoạn code nào khác (báo cáo, export, job) đọc/ghi `crm_serial_warranty_claims.status` mà không qua đúng 2 lớp chặn này, dữ liệu 2 luồng có thể lẫn/sai nhãn.
2. **Danh sách "Phiếu sự cố & bảo hành" (tab `claims`) không lọc `claim_type`, hiển thị lẫn phiếu của module mới với nhãn trạng thái sai/rỗng** — vì trang này dùng `SolarWarrantyClaim::STATUSES[$status] ?? $status` để hiển thị, còn `$status` của phiếu `replacement`/`paid_repair` không nằm trong mảng đó (ví dụ `reserved`, `repairing`, `qa_testing` sẽ hiện nguyên văn tiếng Anh, không có nhãn tiếng Việt, không đúng tone màu).
3. **Xoá tệp minh chứng không kiểm tra trạng thái đợt bảo trì đã hoàn tất/đã duyệt hay chưa** (`SolarMaintenanceAttachmentController::destroySchedule` / `destroySite`) — chỉ kiểm quyền `uploadAttachment`, không có điều kiện chặn xoá khi `schedule.status === 'completed'` hoặc `approval_status === 'approved'`. Có thể xoá bằng chứng đã được duyệt.
4. **`assignment.approve` yêu cầu đúng `isAdmin()`, không chấp nhận `isManager()` (Trưởng phòng)** — khác với hầu hết action khác trong cùng file đều dùng `isManager()`. Nếu Trưởng phòng Kỹ thuật không đồng thời có cờ Admin, họ **không duyệt được phân công**, dù các tài liệu/luồng khác trong hệ thống mô tả Trưởng phòng là người duyệt.

### P1 — thiếu nghiệp vụ

1. **`customer_confirmed` và `waiting_customer` (trạng thái Lịch bảo trì) tồn tại trong `STATUSES`/`TRANSITIONS` nhưng không có action/nút riêng nào đặt các trạng thái này** — chỉ có thể đạt được bằng cách chọn tay ở form đổi trạng thái tự do (nếu UI có cho chọn); không có cơ chế khách hàng tự xác nhận (không có link/portal khách hàng nhìn thấy trong source được audit).
2. **Không có cơ chế tự động tạo phiếu sự cố (`crm_serial_warranty_claims`) từ các field "phát hiện sự cố" trên `solar_maintenance_schedules`** (`incident_kind`, `incident_material_note`, `execution_fault_note`...) dù các cột này tồn tại — kỹ thuật phải tự tạo phiếu riêng và tự chọn `maintenance_schedule_id` để nối lại (nếu form cho chọn).
3. **Phiếu sự cố (bảo hành cũ) không có bước "yêu cầu bổ sung thông tin" riêng** như module mới (`needs_more_information`) — chỉ có thể lùi thủ công `pending_approval → diagnosing`, không có trường lý do bắt buộc kèm theo cho bước lùi này (khác hẳn `SolarMaintenanceApprovalService::requestRevision` của lịch bảo trì, bước đó bắt buộc `comment`).
4. **Không kiểm tra thời hạn bảo hành khi tạo phiếu sự cố** (mục 6) — khác với module mới có bước kiểm tra & chặn nếu hết hạn bảo hành.
5. **Nhánh redirect `return_to=warranty_exchange` trong `SolarWarrantyStockController::store` là code chết** (mục 18) — không có đường dữ liệu hợp lệ nào kích hoạt được nó.
6. **Đồng bộ sang Technical Workspace (`technical_schedule_events`) đang không hoạt động trên môi trường audit** vì thiếu bảng — cần xác nhận trên production bảng này có tồn tại hay không (không nằm trong phạm vi audit này để kiểm tra production).
7. **`solar_maintenance_checklist_items` (checklist cũ) không tồn tại trên DB đang chạy** — code có fallback an toàn (`SchemaCache::hasTable` check) nên không lỗi, nhưng nghĩa là "checklist" trên môi trường này hiện chỉ chạy được qua `solar_maintenance_work_items`.
8. **Route cùng 1 nhóm nghiệp vụ nhưng chia 2 namespace controller** (`Synced\Technical\*` và `Technical\*` — mục 3.2) — không phải lỗi chạy, nhưng là điểm dễ nhầm khi tra cứu/bảo trì code do có nhiều class trùng tên khác namespace.

### P2 — UI/UX/cải tiến

1. Trang dùng `offcanvas` (Bootstrap) cho popup nhập liệu, khác hẳn kiểu `x-wx-modal`/AJAX đã chuẩn hoá ở module Kỹ thuật mới (`/ky-thuat/...`, `/kho/xuat-hang-bh-sc`) — không đồng nhất UI giữa 2 thế hệ module trong cùng hệ thống.
2. 2 sơ đồ "quy trình 8 bước"/"quy trình 10 bước" ở tab Tổng quan là hình minh hoạ tĩnh, không phản ánh chính xác state machine thật (ví dụ sơ đồ bảo hành 10 bước liệt kê "Kho xuất" và "Thu hồi lỗi" là 2 bước tách biệt và tuần tự, nhưng trong `TRANSITIONS` thật thì `waiting_stock → replacing` không phân biệt thứ tự xuất/thu hồi, và không có bước "Thu hồi lỗi" là 1 status riêng).
3. Không có Repository layer — controller/service gọi thẳng Eloquent/Query Builder, một số logic nghiệp vụ (vd. `applyInventoryMovement`) nằm trong Controller thay vì Service.

## 21. Danh sách file source chính đã đọc

**Routes:** `routes/project_unified.php`

**Controllers:**
`app/Http/Controllers/Synced/Technical/SolarMaintenanceController.php`,
`app/Http/Controllers/Synced/Technical/SolarMaintenanceDetailController.php`,
`app/Http/Controllers/Synced/Technical/SolarMaintenanceApprovalController.php`,
`app/Http/Controllers/Synced/Technical/SolarMaintenanceAttachmentController.php`,
`app/Http/Controllers/Technical/SolarMaintenanceWorkItemController.php`,
`app/Http/Controllers/Technical/SolarMaintenanceCommentController.php`,
`app/Http/Controllers/Technical/SolarWarrantyClaimController.php`,
`app/Http/Controllers/Technical/SolarWarrantyStockController.php`

**Services:**
`app/Services/Synced/Technical/SolarMaintenanceService.php`,
`app/Services/Synced/Technical/SolarMaintenanceApprovalService.php`,
`app/Services/Synced/Technical/SolarMaintenanceQueryService.php` (tham chiếu),
`app/Services/Technical/SolarWarrantyQueryService.php`,
`app/Services/Technical/TechnicalScheduleSyncService.php`

**Models:**
`app/Models/SolarMaintenanceSchedule.php`,
`app/Models/SolarWarrantyClaim.php`,
`app/Models/SolarWarrantyStockMovement.php`,
`app/Models/SolarMaintenanceAttachment.php` (tham chiếu)

**Policy:** `app/Policies/SolarMaintenanceSchedulePolicy.php`

**Views:**
`resources/views/synced/technical/maintenance/index.blade.php`,
`resources/views/synced/technical/maintenance/show.blade.php` (đọc theo route/action, không đọc trọn 1428 dòng),
`resources/views/synced/technical/maintenance/site-show.blade.php` (tham chiếu)

**Hỗ trợ:** `app/Support/Warranty/WarrantyFlow.php` (đối chiếu), `app/Support/SolarMaintenanceAccess.php` (đối chiếu)

**Database:** truy vấn `SHOW TABLES`/đếm dòng trực tiếp trên DB `egosolar_test` cho 16 bảng liên quan; đối chiếu tên migration qua `grep` trong `database/migrations/`.

## 22. Kết luận bàn giao

Module tại `/du-an/bao-tri-bao-hanh` là **hệ thống O&M (bảo trì + sự cố/bảo hành cũ) đầy đủ, đang hoạt động**, có state machine thật, có audit log/lịch sử trạng thái thật cho phần lịch bảo trì, có phân quyền server-side rõ ràng. Phần "phiếu sự cố/bảo hành" trong module này là **phiên bản trước** của nghiệp vụ bảo hành, **đã được khoanh vùng và chặn không cho chồng lấn** với 2 luồng mới (Đổi hàng bảo hành, Sửa chữa tính phí) ở tầng tạo phiếu và tầng đổi trạng thái/kho — nhưng **chưa được khoanh vùng ở tầng hiển thị danh sách chung**, dẫn đến rủi ro hiển thị sai nhãn trạng thái khi 2 loại phiếu nằm chung 1 bảng dữ liệu (GAP P0-2). Việc đồng bộ sang Technical Workspace có code đầy đủ nhưng phụ thuộc bảng `technical_schedule_events` hiện không có trên môi trường audit. Không phát hiện thay đổi code, database hay dữ liệu nào được thực hiện trong quá trình audit này.
