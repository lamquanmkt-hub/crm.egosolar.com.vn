# CRM egosolar.com.vn — Audit toàn bộ Source (Laravel 12 / PHP 8.2)

**Ngày audit:** 2026-09-19
**Phạm vi:** routes/, app/Http/Controllers, app/Models, app/Policies, app/Http/Middleware, resources/views, resources/js|css + public/js|css, database/migrations, database/seeders.
**Phương pháp:** đọc source tĩnh (static read-only), không sửa code, không chạy migrate/seed/artisan, không đọc/trích nội dung `.env`.
**Quy mô hệ thống:** ~950 route definitions, 193 controllers, 166 models, 9 policies, 298 migrations tạo ra 280 bảng, 379 view Blade, ~72 file JS + ~109 file CSS thủ công trong `public/`.

> Báo cáo này tổng hợp từ 4 lượt khảo sát độc lập (routing/controllers/middleware/policy; views/frontend; database/models; security/code-quality). Số dòng/số file nêu dưới đây là kết quả grep tĩnh, có thể lệch nhẹ nhưng đã được đối chiếu chéo giữa các lượt khảo sát.

---

## A. Sơ đồ module hiện tại

```
CRM Egosolar
├── Auth (login only — đăng ký bị khoá cứng)
├── Dashboard (tổng quan theo role)
├── CRM / Khách hàng
│   ├── Customers (crm_customers)
│   ├── Customer Profiles (module riêng, song song)
│   ├── Customer Consignments (ký gửi hàng hoá)
│   └── Leads / Marketing ratings
├── Đơn hàng (Orders)
│   ├── OrderController (root, legacy) + CRM\OrderController (mới) — SONG SONG, cùng sống
│   ├── Order Returns / Refunds
│   └── Sales Quotations (báo giá) — cũng có 2 controller song song
├── Kho & Vật tư (Inventory/Warehouse)
│   ├── Products, Categories, Brands, Price Tiers
│   ├── Serial tracking (units/identifiers/states)
│   ├── Stock lots (FIFO), Stock movements, Goods receipts
│   └── Material Requests (2 hệ thống song song: Projects\MaterialRequest và ProjectTest\MaterialRequest)
├── Công trình / Dự án (Projects-Construction)
│   ├── project-test.* (legacy, ~98 route, đang bị middleware RetireLegacyProjectModule "khai tử" nhưng vẫn là hệ thống chính đang chạy)
│   └── projects-unified.* (/du-an, kiến trúc mới, bảng `sites` là canonical từ 2026-08-01)
│   → Hai Eloquent model `Site` khác nhau cùng trỏ về bảng `sites`
├── Kỹ thuật (Technical)
│   ├── Technical\* (cũ) và Synced\Technical\* (mới) — SONG SONG, trùng route name
│   ├── Bảo trì/Bảo hành: solar_maintenance_* (11 bảng, đã nâng cấp tới v13.1)
│   └── Technical Payroll/KPI — 2 controller trùng route name (web.php vs routes/technical.php)
├── Chấm công (Attendance) — attendance_records/settings/correction, leave_requests
├── KPI & Lương (KPI/Payroll)
│   ├── payrolls, sales_daily_kpis, sales_commissions
│   ├── crm_compensation_v2 (6 bảng, KHÔNG có model — raw query)
│   └── 2 hệ thống payroll-slip song song (generic vs technical)
├── Đề nghị thanh toán (Payment Requests) — có workflow duyệt nhiều cấp
│   → chứa "cửa hậu" hardcode email (xem mục E/Security)
├── Tạm ứng / Hoàn ứng (Advances) — có bảng DB nhưng KHÔNG có Eloquent model, KHÔNG có FK
├── Công nợ (Debt) — crm_customer_debts, đầy đủ
├── Hoá đơn (Invoice) — KHÔNG có bảng riêng, chỉ là cột gắn vào crm_orders
├── Phân quyền (Roles/Permissions) — Spatie laravel-permission + role_permission_audits
├── AI / Workspace — ai_providers/conversations/messages có model; action_drafts/tool_logs/usage_logs KHÔNG có model
├── Marketing — plans, leads, content calendar, KPI-payroll, bộ "mkt_*" SEO/ads riêng
├── HR — tuyển dụng, nhân viên, phòng ban, quà tặng, công tác, bàn giao tài liệu (phần lớn raw-query, không model)
└── Hệ thống — Chat, Notification, Smart Search, Company Documents, Media, Booking phòng họp
```

**Đặc điểm cấu trúc nổi bật:** codebase phát triển hữu cơ theo từng đợt nghiệp vụ, để lại nhiều cặp module **song song không được hợp nhất** (Orders, Sales Quotation, Sales Commission, Payment Requests, Chat, Technical Payroll, Project Test vs Unified, Site model, Payment model). Đây là rủi ro bảo trì lớn nhất của toàn hệ thống, không phải lỗi vụn vặt.

---

## B. Danh sách toàn bộ trang và route (tóm tắt theo module)

Routes chỉ đăng ký qua `routes/web.php` (không có `routes/api.php` — toàn bộ app chạy qua `web` middleware group, có CSRF mặc định). `bootstrap/app.php` require thêm 11 file con: `material_requests_synced.php`, `hr.php`, `booking_room.php`, `ai.php`, `role_permissions.php`, `project_test.php`, `technical_workspace.php`, `project_unified.php`, `project_workflow_document_settings.php`, `technical.php`, `payment_advances.php`, `business_trips.php`.

| File route | Số route (~) | Module |
|---|---|---|
| web.php | 482 | Auth, Customers, Orders, Products, Warehouses, Finance, Payment Requests, Marketing, Chat, Tasks, System |
| hr.php | 144 | Nhân sự, chấm công, tuyển dụng, quà tặng, công tác |
| project_test.php | 98 | Công trình (legacy) |
| project_unified.php | 72 | Công trình (unified) + Bảo trì/bảo hành (maintenance) |
| technical_workspace.php | 37 | Workspace kỹ thuật |
| technical.php | 32 | Kỹ thuật, lương kỹ thuật (Synced) |
| role_permissions.php | 21 | Phân quyền, cài đặt appearance/AI |
| material_requests_synced.php | 20 | Đơn vật tư |
| payment_advances.php | 15 | Tạm ứng/hoàn ứng |
| ai.php | 14 | AI copilot |
| business_trips.php | 6 | Công tác |
| booking_room.php | 4 | Đặt phòng họp |
| project_workflow_document_settings.php | 2 | Cài đặt tài liệu workflow |

**File route KHÔNG được require ở đâu cả (route chết, ~210 dòng):**
- `routes/maintenance_v9.php`
- `routes/maintenance_v10.php` (còn gọi `MaintenanceProjectHandoffService->bootObservers()` ở top-level — nguy hiểm nếu vô tình include lại)
- `routes/technical_workspace_v151.php`, `_v152.php`, `_v154.php`, `_v155.php`

Danh sách route đầy đủ theo từng module nằm trong Mục C (ma trận) — vì số lượng ~950 route quá lớn để liệt kê từng dòng, báo cáo nhóm theo module + nêu route đặc biệt/có vấn đề.

---

## C. Ma trận Module → Controller → View → Model → Database

| Module | Route/URL prefix | Controller chính | View | Model | Bảng DB | Quyền truy cập | CRUD |
|---|---|---|---|---|---|---|---|
| **Dashboard** | `/dashboard` | `DashboardController` | `dashboard/index`, `role-home` | — (tổng hợp từ nhiều model) | nhiều | auth | Read-only |
| **Khách hàng** | `/customers` | `CRM\CustomerController` (+ `CustomerController` legacy chết) | `customers/*` | `CRM\Customers\Customer` | `crm_customers` | auth, permission tùy action | Full CRUD |
| **Hồ sơ khách hàng** | `/customer-profiles` | `CRM\CustomerProfileController` | `customer-profiles/*` | `CustomerProfile`, `CustomerProfileDocument` | `customer_profiles` | auth | Full CRUD |
| **Ký gửi hàng hoá** | `/ky-gui-hang-hoa` | `CustomerConsignmentController` | `customer_consignments/*` | tương ứng | 7 bảng consignment | auth | Full CRUD |
| **Đơn hàng** | `/orders` | `OrderController` (root, legacy) **và** `CRM\OrderController` (mới) — cả 2 cùng có route sống | `orders/*` (có `show-legacy.blade.php` mồ côi) | `CRM\Orders\Order` | `crm_orders`, `crm_order_items`, `crm_order_approvals` | auth, role tuỳ action | Full CRUD + workflow duyệt/giao/huỷ |
| **Trả hàng/Refund** | `/order-returns` | `OrderReturnController` | `order_returns/*` | `OrderReturn` (+Approval/Item/Serial) | `order_returns`, `order_refunds` | auth | Full CRUD |
| **Sản phẩm/Kho** | `/products`, `/warehouses` | `ProductController`, `WarehouseController` (Inventory\*) | `products/*`, `warehouses/*` | `Inventory\Catalog\Product`, `Core\Warehouse` | `crm_product_catalog`, `crm_warehouses`, `crm_product_stock` | `can:warehouse.manage` + `can:warehouse.stock_check` (AND) | Full CRUD |
| **Serial/Bảo hành serial** | `/products/serials`, `/serial-warranty` | `ProductSerialManagementController`, `SerialWarrantyController` | `products/*`, `serial-warranty/*` | `Inventory\Serial\*`, `SolarWarrantyClaim` | `crm_serial_units` + 5 bảng liên quan | **Gate::before hardcode bypass cho role admin\|warehouse\|kho** (xem mục E) | Full CRUD |
| **Vật tư công trình** | `/don-vat-tu` | `MaterialRequestController` (synced, canonical) — còn ~150 dòng code chết dạng `if(false)` trong web.php | `material_requests/*` | `Projects\MaterialRequest` **và** `ProjectTest\MaterialRequest` (2 hệ song song) | `material_requests`, `project_test_material_requests` | role tuỳ action | Full CRUD |
| **Công trình (legacy)** | `/cong-trinh`, `project-test.*` | `ProjectTestController`, `ProjectPaymentController`, `ProjectExpenseController` | `project-test/*` (nhiều partial version hoá v3–v7, v41) | `ProjectTest\Project` (+15 model con, `$guarded=[]` toàn bộ) | 16 bảng `project_test_*` | middleware `RetireLegacyProjectModule` | Full CRUD |
| **Công trình (unified)** | `/du-an` | `UnifiedProjectController`, `ProjectWorkflowV2Controller` | `projects-unified/*` (versioned partials v2–v5) | `Projects\Site` (khác `App\Models\Site`) | `sites`, `project_unified_histories` | middleware `EnsureUnifiedProjectAccess` (sales chỉ xem site của mình) | Full CRUD |
| **Kỹ thuật** | `/technical`, workspace | `Technical\*`, `Synced\Technical\*` (song song) | `technical/*`, `synced/*` | `Technical\TechnicalScheduleEvent` | `technical_schedule_events` + nhiều bảng không model | role kỹ thuật | Phần lớn CRUD, một số raw-query |
| **Bảo trì/Bảo hành** | `/ky-thuat/bao-tri-bao-hanh` → redirect `projects-unified.maintenance.*` | `Synced\Technical\SolarMaintenance*` (chủ đạo) + `Technical\SolarMaintenance*` (cũ, vẫn sống song song) | `technical/maintenance/*`, `synced/technical/maintenance/*` | `SolarMaintenanceSchedule` (+10 model con) | `solar_maintenance_*` (11 bảng) | role kỹ thuật/admin | Full CRUD + workflow duyệt |
| **Chấm công** | `/nhan-su/cham-cong` | `Hr\AttendanceController` | `hr/attendance/*` | `AttendanceRecord`, `AttendanceSetting`, `AttendanceCorrectionRequest` | `attendance_records` (unique user+date), `attendance_settings`, `attendance_correction_*` | role HR | Full CRUD. **Model `AttendanceHoliday` KHÔNG có bảng — lỗi runtime nếu dùng** |
| **KPI & Lương** | `/ky-thuat/luong`, `/sales/kpi` | `TechnicalKpi\TechnicalPayrollController` **và** `Synced\TechnicalKpi\TechnicalPayrollController` — **trùng route name, khác controller** (xem mục E) | `kythuat/*`, `synced/kythuat/*` | `Payroll`, `SalesDailyKpi` | `payrolls`, `sales_daily_kpis`, `crm_compensation_*` (KHÔNG có model), `payroll_slip_*` (2 hệ song song, KHÔNG có model) | role kỹ thuật/admin | CRUD trộn raw-query |
| **Đề nghị thanh toán** | `/payment-requests` | `PaymentRequestController` (root) **và** `Finance\PaymentRequestController` — chia tách 2 controller cho các action khác nhau | `payment_requests/*` (có 2 file backup `.save`) | `Payments\PaymentRequest` | `payment_requests`, `payment_request_approvals`, `payment_attachments` | **Có route chỉ cần `auth`, không role/permission** (`payment_requests.copy`) + **route bị EnforcePageAccess exempt toàn bộ** | Full CRUD + workflow duyệt nhiều cấp |
| **Tạm ứng/Hoàn ứng** | `/payment-requests/tam-ung-*` | `Finance\PaymentAdvanceController` | tương ứng | **Không có model** — raw `DB::table()` | `salary_advance_requests`, `advance_settlements(_items)` — **không có FK constraint nào** | role kế toán | CRUD qua raw query, thiếu ràng buộc toàn vẹn dữ liệu |
| **Công nợ** | `/finance/*-debts` | `Finance\SupplierDebtController`, `Finance\CustomerDebtController` | `finance/debt-*` | `CustomerDebt`, `DebtPaymentHistory` | `crm_customer_debts`, `crm_debt_payment_history` | `role:admin\|accounting` | CustomerDebtController chỉ đọc (read-only), không có store/update/destroy |
| **Hoá đơn** | cột trong `/orders` | không có controller riêng | không có view riêng | **Không có model Invoice** | **Không có bảng `invoices`** — chỉ là cột `invoice_*` trên `crm_orders` | theo quyền Orders | Không phải module độc lập — cần làm rõ với business |
| **Phân quyền** | `/cai-dat/*` | `RolePermissionController` | `admin/role-permissions/*` (1 view mồ côi — controller render `admin.settings.index` thay vì view này) | Spatie Role/Permission + `RolePermissionAudit` | bảng chuẩn Spatie + `role_permission_audits` | `role:admin` cho role CRUD, nhưng **`role:admin\|management\|manager\|director\|ceo`** cho workspace app matrix cùng prefix — không nhất quán | Full CRUD |
| **AI/Workspace** | `/ai`, `/cai-dat/ai-api` | `AiChatController`, `AiGovernanceController` | `ai/index` | `AI\AiConversation/AiMessage/AiProvider` | `ai_providers/conversations/messages` (có model) + `ai_action_drafts/tool_logs/usage_logs` (KHÔNG model) | `permission:ai.use`, throttle 30/phút | Full CRUD (phần có model) |
| **Marketing** | `/marketing/*` | `MarketingPlanController`, `MarketingReportController`, v.v. | `marketing/*` (nhiều view mồ côi: `plans/ads`, `plans/seo`, `report/*` singular cũ) | `Marketing\*` | ~35 bảng (`marketing_*`, `mkt_*`) | `role:marketing\|marketing_manager\|admin` | Full CRUD, nhiều view/route trùng lặp cũ-mới |
| **HR** | `/nhan-su/*` | `Hr\EmployeeController`, `Hr\RecruitmentController`, `Hr\GiftController`, v.v. | `hr/*` | Một phần có model (`Department`, `Position`, `HrBusinessTrip`, `Hr\Gift*`) | Phần lớn bảng `hr_*` **không có model** (tuyển dụng, bàn giao tài liệu, vận hành) | role HR | CRUD trộn model + raw query |
| **Chat/Tasks** | `/chat/*` (bao gồm cả Task CRUD — smell về tổ chức module) | `ChatController` (root) **và** `System\ChatController` — gần như trùng nhau | `chat/*` | `System\Conversation/Message`, `Tasks\Task` | `conversations`, `messages`, `tasks` | auth | Full CRUD |
| **Hệ thống khác** | company-documents, media, smart-search, booking phòng họp, notifications | tương ứng | tương ứng | tương ứng | tương ứng | auth, một số role riêng | CRUD cơ bản |

---

## D. Những chức năng đang hoạt động (đánh giá tổng quan)

- **CRM lõi (Khách hàng, Đơn hàng, Kho/Vật tư, Serial, Công nợ):** trưởng thành, đầy đủ CRUD, model/migration khớp nhau, N+1 được kiểm soát tốt ở các controller lớn (`CRM\OrderController`, `Hr\EmployeeController`, `Marketing\ContentCalendarController`, `Hr\Gifts\GiftRequestController` đều có eager-load đúng).
- **Đề nghị thanh toán, Bảo trì/Bảo hành, Chấm công, Phân quyền (Spatie):** có workflow duyệt nhiều cấp, migration lịch sử cho thấy được đầu tư nâng cấp liên tục (bảo trì đã qua tới v13.1).
- **CSRF, SQL Injection, XSS:** kiểm tra diện rộng không phát hiện lỗ hổng khai thác được — các điểm dùng `DB::raw`/`whereRaw` đều bind tham số đúng cách; các điểm dùng `{!! !!}` trong Blade đều đã qua `e()`/`nl2br(e())` hoặc `json_encode` với cờ `JSON_HEX_*`.
- **Dependencies:** `laravel/framework ^12.0`, PHP `^8.2`, không có gói lỗi thời/rủi ro; `require-dev` được tách đúng.

---

## E. Những phần lỗi hoặc chưa hoàn thiện (ưu tiên đọc kỹ)

### E.1 — Nghiêm trọng nhất: "cửa hậu" hardcode email trong route (Critical)
`routes/web.php` dòng 792, 807-1029, và lặp lại độc lập ở dòng 2377-2479: có closure kiểm tra
`strtolower(auth()->user()->email) === 'buibichthao@egosolar.vn'`, nếu đúng thì cho phép:
- Update **bất kỳ cột nào** của `payment_requests` qua `$request->except($blocked)` ghi thẳng bằng `DB::table(...)->update()`, không qua Model/FormRequest, không giới hạn mass-assignment.
- Xoá mềm/xoá cứng payment request **bất kể trạng thái đã duyệt/đã thanh toán hay chưa**, cascade xoá cả file đính kèm, lịch sử duyệt.

Pattern hardcode-email này còn lặp lại độc lập ở **7 vị trí khác** (không dùng chung 1 hàm):
`LockCompletedFinanceRecords.php:13`, `Finance\PaymentRequestController.php:1333,1342`, `PaymentRequestController.php:1120,1129`, `Finance\SupplierDebtController.php:1332`, `EgoPaymentRequestAttachmentController.php:78`, `Finance\EgoPaymentRequestAttachmentController.php:98`.

→ Rủi ro: nếu tài khoản này bị lộ mật khẩu/chiếm phiên, hoặc email được gán lại cho người khác, kẻ tấn công có toàn quyền ghi/xoá dữ liệu tài chính, hoàn toàn nằm ngoài hệ thống phân quyền Spatie đang có sẵn.

### E.2 — Route trùng tên, khác controller thực thi (High — bug thật, không chỉ là dead code)
`ky-thuat.luong.*` được định nghĩa **2 lần**: `routes/web.php:1640-1673` (controller `TechnicalKpi\TechnicalPayrollController`) và `routes/technical.php:100-151` (controller `Synced\TechnicalKpi\TechnicalPayrollController`, được require sau). Do cơ chế Laravel: `route('ky-thuat.luong.index')` (dùng trong Blade/redirect) sẽ trỏ tới bản đăng ký **sau** (Synced), nhưng **khớp URL trực tiếp** lại dùng bản đăng ký **đầu tiên** (non-Synced) — nghĩa là link hiển thị và hành vi thực tế xử lý bởi 2 controller khác nhau. Cùng pattern lặp lại cho toàn bộ Solar Maintenance (`Technical\SolarMaintenance*` vs `Synced\Technical\SolarMaintenance*`).

### E.3 — Route không có phân quyền tương xứng (High)
- `payment_requests.copy` (`web.php:1185`) chỉ có `middleware('auth')` — bất kỳ ai đăng nhập cũng copy được payment request của người khác.
- `EnforcePageAccess` (middleware toàn cục) **loại trừ hoàn toàn** mọi route `payment_requests.*`, `de-xuat.*`, `company-documents.*` khỏi kiểm tra quyền trang — kết hợp với E.1/trên, nghĩa là module thanh toán chỉ được bảo vệ bởi phân quyền cấp route lẻ tẻ, không có lớp bảo vệ toàn cục.
- `Gate::before()` trong `AppServiceProvider.php` bypass toàn bộ ability check cho role `admin|warehouse|kho` trên URL `serial-warranty*` — **trùng lặp y hệt logic** đã có trong `EnforcePageAccess` middleware (viết 2 lần ở 2 tầng khác nhau).
- ~70/193 controller có hàm `destroy()`/`store()`/`update()` không gọi `authorize()`/`Gate`/`can()` trực tiếp — phần lớn được che bởi `role:` middleware ở route-group, nhưng vài route chỉ dựa vào `auth` (`BrandController@destroy`, `PriceTierController@destroy`, `MediaController@destroy`).
- Debug routes (`/debug/*`, dòng 247-256) chỉ chặn bằng `role:admin`, không chặn theo môi trường (`app()->environment('local')`) — nếu có tài khoản admin ở production, các route lộ schema DB vẫn truy cập được.

### E.4 — File công khai trên public disk (High)
60+ điểm upload dùng `->store()/->storeAs()` lên **disk `public`**, phục vụ trực tiếp qua symlink `public/storage` **không qua kiểm tra quyền ở URL**. Một số controller có kiểm tra quyền khi tải xuống (`CompanyDocumentController`, `CRM\CustomerProfileController`) nhưng file vẫn truy cập được trực tiếp nếu đoán được đường dẫn (`storage/payment_requests/{id}/{filename}`) — bảo mật kiểu "obscurity", không phải access-control thật.

### E.5 — Thiếu validate khi upload (Medium-High)
Nhiều điểm gọi `$request->file(...)->store()` không có `validate(...mimes...)` gần đó: `ChatController.php:614`, `ContentFeedbackController.php:26`, `CRM\CustomerProfileController.php:564`, `CRM\EgoOrderDocumentController.php:84`, `Marketing\ContentCalendarController.php:149,451,481`, `Projects\ProjectTestController.php` (nhiều điểm). Rủi ro upload file loại nguy hiểm tuỳ cấu hình hosting.

### E.6 — Kiến trúc triển khai: toàn bộ app nằm ở document root (Medium-High)
Theo tài liệu cài đặt (`HUONG_DAN_CAI_DAT.txt`, `README_INSTALL.txt`), toàn bộ mã nguồn Laravel (không chỉ `public/`) được deploy thẳng vào webroot chia sẻ hosting; `.htaccess` rewrite mọi request về `public/` — đây là **điểm lỗi đơn (single point of failure)**: nếu Apache config sai (`AllowOverride None`) hoặc migrate sang Nginx không có rule tương đương, `.env`, `app/`, `vendor/` và các script vá lỗi thô (`RESET_CT_OLD_000036_CHUA_AI_PHE_DUYET.php`, `force_*.php`) sẽ lộ trực tiếp ra Internet.

### E.7 — Mass assignment (High)
`$guarded = []` (mở toàn bộ) trên 19 model, gồm toàn bộ namespace `ProjectTest\*` (Project, PaymentMilestone, PaymentTransaction, PaymentAdjustment, MaterialRequest, ProjectExpense...) và `Technical\TechnicalScheduleEvent(User)` — đây là các model tài chính/workflow. `Marketing\MarketingMetric` dùng `$guarded=['id']` tương đương mở toàn bộ. `Order` model có `$fillable` rộng gồm cả `approved_by`, `approved_at`, `total_amount`.

### E.8 — Model không có bảng / bảng không có model (Medium)
- `App\Models\AttendanceHoliday` — **không có migration tương ứng**, gọi model này sẽ lỗi SQL runtime.
- Không có model cho: `salary_advance_requests`/`advance_settlements(_items)` (không FK), `crm_compensation_*` (6 bảng), `payroll_slip_*` (2 hệ song song), phần lớn bảng `technical_*` và nhiều bảng `hr_*` (tuyển dụng, bàn giao tài liệu, vận hành) — các module này vận hành hoàn toàn bằng raw query, không có validation ở tầng Eloquent.

### E.9 — Xung đột model trùng bảng/trùng tên (Medium)
- `App\Models\Site` và `App\Models\Projects\Site` **cùng trỏ bảng `sites`** nhưng `$fillable` khác nhau — hệ quả của migration hợp nhất `2026_08_01_create_unified_project_module` chưa hợp nhất tầng model.
- `App\Models\Payment` (→`payments`, sổ quỹ chung) và `App\Models\Payments\Payment` (→`crm_payments`, gắn với đơn hàng) — trùng tên class, dễ `use` nhầm.
- Hai `MaterialRequest` khác bảng (`Projects\` vs `ProjectTest\`) — dễ nhầm khi audit/onboard.

### E.10 — View mồ côi / không còn dùng (đã xác nhận qua đối chiếu controller)
`admin/role-permissions/index.blade.php`, `hr/employees/org-chart.blade.php`, `hr/online-work/create.blade.php`, `kythuat/kpis.blade.php`, `synced/kythuat/kpi_project.blade.php`, `finance/accounts.blade.php`, `finance/receipts.blade.php`, `finance/debt-suppliers.blade.php`, phần lớn `marketing/plans/{ads,email,seo,overview,show_enterprise}.blade.php`, `marketing/report/{ads,overview,seo}.blade.php` (thư mục số ít cũ), `marketing/{index,tasks,weekly_tasks}.blade.php`, `orders/show-legacy.blade.php`, `project-test/warehouse.blade.php` (bản không -v2), `welcome.blade.php` (scaffold Laravel mặc định, không route tới).

### E.11 — Vấn đề dữ liệu đã biết nhưng chưa xử lý
- `2026_07_19_000002_add_foreign_keys_zero_orphan_safe.php` — chính đội dev tự ghi nhận **~51 dòng mồ côi** ở `crm_order_edit_histories.order_id` và cố tình bỏ qua không thêm FK.
- `crm_customers` không có ràng buộc unique trên `phone`, không có cột `email` — có thể phát sinh khách hàng trùng lặp.
- `crm_order_items`, `crm_order_approvals` không có `timestamps()` trong khi hầu hết bảng khác đều có — giảm khả năng audit trail cho bảng tài chính quan trọng.
- Migration `2026_05_29_..._add_task_group_id_to_tasks_table.php` có thân hàm **rỗng** (`up()/down()` chỉ có `//`) — cột `task_group_id` có thể chưa từng được thêm dù tên file khẳng định đã thêm; bản `.save` cạnh nó mới chứa code thật.

---

## F. Các file thừa cần xử lý (đã xác nhận là file được **track trong git**, không phải rác cục bộ)

**Backup code (`.save`, `_bk`, `.before-*`, `.jsbk`) — cần xoá khỏi git:**
- `app/Http/Controllers/CRM/SalesCommissionController.php.before-groupby-fix-20260806_113309`
- `app/Http/Controllers/PaymentRequestController.php.save`, `.save.1`
- `app/Services/OrderService.php_bk`, `PricingService.php_bk`, `ProductService.php_bk`
- `database/seeders/RolePermissionSeeder.php_bk`
- `database/migrations/2026_01_19_035502_add_discount_amount_to_order_items_table.php.save` (nội dung sai/copy-paste)
- `database/migrations/2026_05_29_165701_add_task_group_id_to_tasks_table.php.save` (chứa code thật, bản `.php` đang chạy lại rỗng — **cần kiểm tra ngay, không chỉ dọn rác**)
- `config/ego_menu_v4.php.before-*` (4 file)
- `public/css/ego-workspace-menu-v4.css.before-*` (2 file)
- `public/js/order-form.jsbk`, `.jsbk2`; `public/js/baogia-no-vat.js.disabled_20260505_171208`
- 13 bản backup của `resources/views/partials/sidebar.blade.php` và `workspace-menu-excel-v3.blade.php` (dán ngày tháng vào tên file)
- `resources/views/orders/{approval-form,create}.blade.php_bk`
- `resources/views/hr/business-trips/index.blade.php.before-*`
- `resources/views/payment_requests/edit.blade.php.before_ego_final_*`, `.save`
- `resources/views/project-test/partials/material-unified-workflow-v1.blade.php.before-*`
- `resources/views/site-assemblies/index_before_compact_ui_*.blade.php`
- `resources/views/marketing/report_old_file` (rỗng)
- 2 file cache IDE `.ea-php-cli.cache`

**Script/ghi chú rời ở root (nguy hiểm vì cả app nằm ở webroot — xem E.6):**
`RESET_CT_OLD_000036_CHUA_AI_PHE_DUYET.php`, `force_show_company_management_menu.php`, `force_company_menu_orders.php`, `force_patch_orders_filter.php`, `install_customer_profiles_module.py`, `install_finance_assets_module.py`, `fix_dashboard_xinxo.py`, cùng ~15 file `.txt/.md` hướng dẫn cài đặt trùng lặp (`README.txt`, `README_INSTALL.txt`, `HUONG_DAN.txt`, `HUONG_DAN_CAI_DAT.txt`, `INSTALL_*.txt`, `DANH-SACH-FILE.txt`, `PATCH-MANIFEST.txt`, v.v.) và `error_log` (không nên commit).

**Route file chết:** `routes/maintenance_v9.php`, `maintenance_v10.php`, `technical_workspace_v151/152/154/155.php`.

**Controller "phẳng" mồ côi (đã có bản mới trong subfolder nhưng bản cũ chưa xoá) — 50 file**, ví dụ: `CustomerController.php`, `OrderController.php` (⚠️ vẫn còn route sống — xem E), `WarehouseController.php`, `ProductController.php`, `TaskController.php`, `SalesQuotationController.php`, `SalesCommissionController.php`, `ChatController.php`, `PaymentRequestController.php`, `Auth/RegisterController.php` (đăng ký đã bị khoá — chết hẳn), và ~40 file khác (danh sách đầy đủ có thể cung cấp riêng nếu cần).

**Thư mục view "nghĩa địa version"** cần dọn theo lộ trình (không xoá ngay vì một số vẫn được include): `project-test/partials/material-*-v3/v4/v41/v5/v6/v7`, `projects-unified/partials/*-v2/v3/v4/v5`, cùng các file JS/CSS phiên bản tương ứng trong `public/`.

---

## G. Đề xuất nâng cấp theo mức ưu tiên

### P0 — Phải xử lý ngay (rủi ro bảo mật/dữ liệu nghiêm trọng)
1. Gỡ bỏ toàn bộ cơ chế hardcode email `buibichthao@egosolar.vn` (routes/web.php + 7 vị trí khác) và thay bằng permission Spatie chuẩn (ví dụ `payment_requests.override_locked`), gán qua hệ thống phân quyền đã có sẵn.
2. Xác nhận cột `task_group_id` đã thực sự được thêm vào bảng `tasks` chưa (migration đang rỗng) — nếu chưa, viết migration bổ sung đúng cách thay vì dựa vào file `.save`.
3. Rà soát `payment_requests.copy` và toàn bộ route bị `EnforcePageAccess` loại trừ (`payment_requests.*`, `de-xuat.*`, `company-documents.*`) để thêm role/permission tương xứng.

### P1 — Ưu tiên cao (ảnh hưởng vận hành/bảo trì lâu dài)
4. Hợp nhất từng cặp module song song, chọn 1 bản canonical, xoá bản còn lại: Orders (`OrderController` vs `CRM\OrderController`), Sales Quotation, Sales Commission, Chat, Technical Payroll/KPI (đặc biệt vì đang trùng route name — E.2), Solar Maintenance (`Technical\*` vs `Synced\Technical\*`).
5. Hợp nhất 2 model `Site` (`App\Models\Site` vs `App\Models\Projects\Site`) thành 1, đồng bộ `$fillable`.
6. Thêm FK constraint cho `salary_advance_requests`/`advance_settlements(_items)`; viết Eloquent model cho các bảng đang raw-query thuộc module tài chính (advances, compensation_v2, payroll_slip).
7. Chuyển file upload nhạy cảm (thanh toán, HR, hồ sơ khách hàng) từ disk `public` sang disk `private` + controller kiểm tra quyền khi tải, không phục vụ qua symlink trực tiếp.
8. Thêm `mimes:`/`max:` validate cho các điểm upload còn thiếu (liệt kê ở E.5).
9. Đổi `$guarded = []` thành `$fillable` tường minh cho toàn bộ namespace `ProjectTest\*` và `Technical\TechnicalScheduleEvent*`.
10. Xác nhận lại cấu hình hosting để đảm bảo document root trỏ đúng vào `public/` (không phụ thuộc một mình `.htaccess`).
11. Xoá toàn bộ file backup/rác đã liệt kê ở Mục F khỏi git (`git rm --cached`), cập nhật `.gitignore` đã có sẵn rule nhưng chưa dọn hồi tố.

### P2 — Có thể làm dần, không khẩn cấp
12. Xoá 6 route file chết (`maintenance_v9/v10`, `technical_workspace_v151/152/154/155`) và 50 controller phẳng mồ côi.
13. Xoá các view mồ côi đã xác nhận ở E.10; dọn "nghĩa địa version" partials/JS/CSS sau khi xác nhận không còn tham chiếu.
14. Viết Policy cho các model còn thiếu (hiện chỉ 9/193 controller có Policy riêng), giảm phụ thuộc hoàn toàn vào `role:` middleware ở route.
15. Xem xét đưa `payroll_slip_*` về 1 hệ thống thay vì 2 (generic + technical).
16. Cân nhắc chuyển ~180 file JS/CSS thủ công trong `public/` sang pipeline Vite đã cấu hình sẵn nhưng chưa dùng, để có minify/cache-busting tự động thay vì `filemtime()` thủ công.
17. Làm rõ với nghiệp vụ: "Hoá đơn" có cần tách thành module/bảng riêng hay giữ nguyên là cột trên `crm_orders`.

---

## H. Lộ trình nâng cấp an toàn (chia giai đoạn nhỏ, không đụng production đột ngột)

**Giai đoạn 0 — Chuẩn bị (0.5–1 ngày, không đổi hành vi):**
- Tạo nhánh git riêng cho audit fix; xoá các file backup/rác ở Mục F khỏi git (không ảnh hưởng runtime vì các file này không được PHP autoload/route tới).
- Viết test/checklist thủ công cho các luồng nghiệp vụ chính (đơn hàng, thanh toán, bảo trì) để có baseline trước khi sửa.

**Giai đoạn 1 — Vá bảo mật P0 (1–2 ngày, cần review kỹ + deploy riêng lẻ):**
- Thay hardcode email bằng permission Spatie (E.1). Test kỹ luồng duyệt/xoá payment request với tài khoản có/không có permission mới.
- Kiểm tra và vá migration `task_group_id` (E.11).
- Thêm role/permission cho `payment_requests.copy` và rà lại danh sách exempt trong `EnforcePageAccess`.

**Giai đoạn 2 — Củng cố dữ liệu & upload (3–5 ngày):**
- Thêm FK cho bảng advance/settlement; viết model cho các bảng raw-query quan trọng nhất (advances trước, vì liên quan tiền).
- Chuyển file upload nhạy cảm sang disk private + endpoint tải có kiểm tra quyền.
- Bổ sung validate cho các điểm upload thiếu.
- Đổi `$guarded=[]` → `$fillable` cho `ProjectTest\*` (làm theo từng model, test lại form liên quan sau mỗi lần đổi để không vỡ mass-assignment hợp lệ hiện có).

**Giai đoạn 3 — Hợp nhất module song song (2–3 tuần, rủi ro cao nhất, cần lên kế hoạch riêng với business):**
- Từng cặp một: xác định bản canonical (đã có gợi ý ở Mục G.4), viết route redirect từ bản cũ sang bản mới, theo dõi log truy cập bản cũ trong 2-4 tuần trước khi xoá hẳn.
- Bắt đầu với cặp rủi ro rõ nhất trước: Technical Payroll/KPI (đang lỗi route-name thật — E.2), sau đó đến Site model, rồi Orders/Sales Quotation/Chat.

**Giai đoạn 4 — Dọn dẹp & chuẩn hoá (song song, không gấp):**
- Xoá route file chết, controller mồ côi, view mồ côi, nghĩa địa version partials/JS/CSS.
- Bổ sung Policy cho các model còn thiếu, giảm phụ thuộc route-level `role:`.
- Đánh giá lại kiến trúc deploy (document root) với đội hạ tầng/hosting.

**Giai đoạn 5 — Tối ưu hoá dài hạn:**
- Chuyển JS/CSS sang Vite pipeline.
- Làm rõ và chính thức hoá module Hoá đơn nếu nghiệp vụ cần tách riêng.
- Bổ sung test tự động (hiện chưa thấy test suite bao phủ các luồng nghiệp vụ chính trong phạm vi audit).

---

## Ghi chú giới hạn của audit
- Đây là audit **tĩnh** (đọc source), không chạy `php artisan route:list` nên chưa xác nhận 100% từng route trỏ đúng tới method controller đang tồn tại — khuyến nghị chạy lệnh này trên môi trường dev/staging (không phải production) để đối chiếu chéo lần cuối.
- N+1 query mới được kiểm tra mẫu (sample) trên các module CRM/HR/Marketing/Gifts, chưa rà hết 252 file dùng `@foreach` — khuyến nghị dùng Laravel Debugbar/Telescope trên staging cho các module Projects/Technical nếu có báo cáo chậm.
- Không đọc/hiển thị nội dung `.env`; không chạy migrate/seed/lệnh xoá dữ liệu; không thao tác lên production trong suốt quá trình audit.
