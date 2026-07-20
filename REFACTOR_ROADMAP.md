# Refactor Roadmap — crm.egosolar.vn → chuẩn crm-shop (SOLID/KISS/DRY/Clean Code)

Tài liệu này ghi lại (1) những gì đã làm trong đợt refactor 2026-07-19 và (2) phần còn lại theo thứ tự ưu tiên, để tiếp tục an toàn từng bước có kiểm chứng.

## Nguyên tắc xuyên suốt
- **KHÔNG mất dữ liệu production.** Mọi migration là additive; FK chỉ thêm sau khi đếm orphan = 0 lúc chạy (nếu còn orphan thì tự bỏ qua, không làm gãy deploy).
- Chuẩn đích: Controller (mỏng) → Service (interface) → Repository (interface) → Model. DTO tải dữ liệu; Enum + config mã hóa workflow; trait `HandleException` gom xử lý lỗi.
- `declare(strict_types=1)` + PHPDoc tiếng Việt cho **file mới hoặc file được refactor** — KHÔNG sweep mù toàn repo (rủi ro TypeError runtime với code cũ chưa kiểm thử).
- Mỗi bước phải verify: `php -l`, container resolve, `php artisan route:cache`, `php artisan test`, Pint.

## ĐÃ HOÀN THÀNH (đợt 2026-07-19)

### Bảo mật
- Xóa 49 file backup/chết (`*.phpbk*`, `*.broken_*`, `*.current_500_*`) khỏi app/config/routes/views/seeders — khôi phục được từ git.
- Gỡ **đăng ký công khai** (`RegisterController` + route + view) — CRM nội bộ, tài khoản do admin cấp.
- Gate `/debug/*` về `role:admin` (trước đây chỉ `auth`, lộ thông tin schema); gỡ route `/hr-test`, `/debug/serial-columns` rác.
- Sửa **2 lỗ XSS stored thật** trong `orders/create.blade.php` và `orders/edit.blade.php` (`json_encode` mất cờ hex → `@json`). Các `{!! !!}` còn lại đã xác minh an toàn (đã `nl2br(e())` hoặc là markup do dev sinh).
- Sửa **mass-assignment** 10 model `$guarded=[]` → `$fillable` tường minh (Site, Finance/Asset, MaterialRequestItem, 7 model Solar*).
- Gỡ mật khẩu hardcode trong seeder → lấy từ env `SEED_ADMIN_PASSWORD`/`SEED_USER_PASSWORD`, thiếu thì random + in ra 1 lần; `UsersTableSeeder` chặn chạy trên production.

### Kiến trúc (DIP / interface binding)
- Tạo tầng `app/Contracts/Services/` — **11 interface** mirror đầy đủ public API các service lõi.
- Tạo `app/Providers/ContractServiceProvider.php` (declarative `public array $bindings`), đăng ký trong `bootstrap/providers.php`.
- Chuyển sang phụ thuộc interface: 5 controller CRUD mỏng (Brand, ProductCategory, PriceTier, Warehouse, PaymentMethod) + **OrderController, CustomerController, ProductController** + wiring chéo trong `OrderService` (ProductStock/Pricing) và `ProductService` (Warehouse/Brand).

### DB (additive, đã test trên bản sao MariaDB)
- `2026_07_19_000001_add_performance_indexes_to_crm_orders.php` — thêm index `order_date`, `shipping_status`, `current_department`, `invoice_status`. Safe-online, idempotent.
- `2026_07_19_000002_add_foreign_keys_zero_orphan_safe.php` — thêm 7 FK (customer_profiles×3, crm_product_stock_lots×3, crm_order_edit_histories.user_id) đã xác minh 0 orphan trên production. Tự đếm orphan lúc chạy, bỏ qua nếu có; idempotent; `down()` sạch.
- Đã test: tạo/idempotent/orphan-skip/rollback trên MariaDB replica → tất cả PASS.

## ĐÃ HOÀN THÀNH (đợt 2026-07-20)

### Hạ tầng test đặc tả (characterization)
- DB test `egosolar_test` = **schema production dump** + migration local pending; cấu hình trong `phpunit.xml`. KHÔNG dùng sqlite (raw SQL MariaDB).
- ⚠️ File dump `database/schema/mysql-schema.sql` **KHÔNG commit** (đã gitignore): repo công khai, và Laravel tự nạp file này khi `migrate` trên DB rỗng. Tạo lại khi cần:
  ```bash
  # 1) lấy schema từ production (chỉ cấu trúc, không dữ liệu)
  ssh crmegoso@103.200.23.139 'cd ~/www && /usr/local/bin/php artisan schema:dump \
    && gzip -c database/schema/mysql-schema.sql | base64 && rm -f database/schema/mysql-schema.sql' > /tmp/s.b64
  # 2) decode, và BẮT BUỘC strip DEFINER= (nếu không MySQL local báo lỗi 1446)
  # 3) tạo DB egosolar_test, nạp dump, rồi: php artisan migrate
  ```
- `tests/TestCase.php`: guard từ chối chạy test nếu DB ≠ `egosolar_test` (bảo vệ dữ liệu); helper `userWithRole()`.
- `tests/Concerns/SeedsOrderFixture.php`: seed company/kho/sản phẩm/khách/lead/đơn/tồn/serial dùng chung.
- 17 characterization test Orders: flow duyệt xuất kho (serial, bảo hành, FIFO, công nợ), export Excel, 5 API picker. Fix migration `drop_media_tables` replay-safe (disable FK checks).

### P1a. OrderController: 3.418 → 1.607 dòng, test chốt hành vi trước khi tách
- `Services/Order/OrderStockGuard` — assertOrderStockAvailable/currentWarehouseStock/stockByProductForWarehouse.
- `Services/Order/OrderSerialWarrantyService` — payload serial theo unit-id, validate chọn serial, kích hoạt bảo hành (GIỮ tách biệt với `OrderInventoryHandler::getShipSerialsPayload` theo code — 2 ngữ nghĩa khác nhau, không gộp).
- `Services/Order/OrderExcelExporter` — toàn bộ exportExcel (658 dòng) chuyển nguyên trạng.
- `Services/Order/OrderProductPickerService` — productCatalog/warehousesForProduct/productPrice/productsForWarehouseSelect/warehousesByCompany; controller chỉ authorize + đọc request + bọc JSON.
- `PricingService::resolveVatPercentForOrderItem` (thêm vào interface) — dùng chung product-price API + sync dòng hàng.
- Xóa dead code `resolveBasePrice`; controller còn lại: CRUD/approval/payment/invoice/shipping + helpers (`egoSyncOrderItemsAndTotalFromRequest` ~290 dòng có thể tách tiếp vào OrderService sau).

### P1b. ProductController: 3.793 → 3.036 dòng
- `Services/Inventory/ProductStockLotQueryService` — calcTotalsAllPages, paginateStockLotIndexRows, stockLotActualCostExpr, attachLotAverageCostsToProducts.
- `Services/Inventory/ProductCatalogOptionsService` — loadPriceTiers, attachTierPricesToProducts, loadTierPricesForProduct, loadCompanyWarehouses, buildCategoryOptions, tableExists.
- `Services/Inventory/ProductStockLotExcelExporter` — export Excel trang nhập kho.
- Route: `Route::resource('products')->except(['show'])` — controller không có `show`, trước đây GET `/products/{id}` gây 500, nay 405.

### P1c. SalesCommissionController: 2.891 → 1.445 dòng
- `Services/Sales/SalesCommissionExcelExporter` — exportExcel (1.289 dòng) chuyển nguyên trạng.
- `Services/Sales/SalesKpiSettingsService` — cấu hình KPI (default/targets/extra metrics/enable flags) + 4 hằng chỉ tiêu.
- `Services/Sales/SalesCommissionScope` — hằng `EXCLUDED_SALES_NAME` dùng chung controller + exporter (trước bị nhân bản).
- Gỡ guard `method_exists($this, 'decodeExtraMetrics')` vô nghĩa sau khi tách.

### P1d. Finance/SupplierDebtController: 1.759 → 1.369 dòng
- `Services/Finance/SupplierDebtService` — 17 method: phân loại trạng thái đợt (paid/pending), đối chiếu ĐNTT, `syncSupplierDebtTotals`, chuẩn hoá tiền, khoá đợt đã chi.
- ⚠️ Quy ước nghiệp vụ đã chốt bằng test: đợt `planned` KHÔNG tính vào `paid_amount`; công nợ chuyển `partial` khi có tiền chờ chi, `paid` khi trả đủ.

**Cách làm an toàn (đã áp dụng cho cả 4 controller):** viết **feature test đặc tả hành vi hiện tại** cho từng endpoint TRƯỚC, rồi mới tách từng nhóm method, chạy test sau mỗi lần. Không rewrite cả file một lần. Khi test đầu tiên fail, kiểm tra hành vi thật rồi sửa TEST cho khớp production — không sửa code cho khớp giả định.

## CÒN LẠI — theo thứ tự ưu tiên

### P1e. Tách tiếp phần còn lại của 4 controller trên (tuỳ chọn)
- `OrderController::egoSyncOrderItemsAndTotalFromRequest` (~290 dòng) → `OrderService`.
- `ProductController`: `store`/`update` vẫn rất lớn (lô FIFO, serial, tier prices) — cần test cho luồng ghi trước khi tách.
- `SalesCommissionController::buildQuery`/`kpiDashboard` — raw SQL động, nên bọc repository có allowlist cột.

### P2. FormRequest thay cho inline validate
**Đã làm mẫu (2026-07-20):** `Finance/SupplierDebtController` → `app/Http/Requests/Finance/SupplierDebtRequest` (dùng chung store+update, `prepareForValidation` chuẩn hoá tiền) + `SupplierDebtPaymentRoundRequest`. Kiểm chứng bằng `SupplierDebtValidationTest` (4 test).

⚠️ **Bài học:** `storePaymentRound` CỐ Ý giữ inline validate — endpoint nhận 2 dạng payload (1 đợt ở cấp gốc / nhiều đợt qua `bulk_rounds[]`), FormRequest chạy trước controller sẽ bắt buộc `amount` và làm gãy nhánh bulk. Rà cùng kiểu trước khi chuyển các endpoint đa-payload khác.

**Còn lại:** ~185 chỗ `$request->validate()`. Ưu tiên: HcOperation (10), SerialWarranty (9), OrderReturn (8), ContentCalendar (8), Recruitment (8), HrDocument (8). Tạo FormRequest trong `app/Http/Requests/<Domain>/` theo mẫu trên; **viết test validation trước khi chuyển**.

### P3. Authorization cho ~60 controller thiếu (Finance/*, Hr/*, Marketing/*, Admin)
- Dùng `config/permissions.php` + `app/Enums/Permission.php` + Policy sẵn có. Gate bằng `can:`/`$this->authorize()` hoặc middleware route. Rà từng module.

### P4. Tách routes/web.php (2.662 dòng)
- Chia theo domain: `routes/orders.php`, `routes/products.php`, `routes/finance.php`, `routes/marketing.php`... load trong `bootstrap/app.php` hoặc `RouteServiceProvider`.

### P5. DB 3NF sâu (cần đổi code app trước — làm sau, additive)
- **CSV/JSON quan hệ → bảng con** (dual-write, giữ cột cũ): `solar_maintenance_schedules.assigned_user_ids` (CSV!) → pivot `solar_maintenance_assignees` đã có; `weekly_tasks.assignees/links/attachments`; `marketing_metrics.*_breakdown`; `companies.bank_accounts`.
- **department varchar → FK** `departments` (thêm cột `*_id` companion, backfill, FK sau).
- **campaign varchar → campaign_id** (marketing_budgets/metrics) — backfill rồi FK.
- **Cột phái sinh làm cache**: `crm_product_catalog.price*/quantity` (nguồn thật: `crm_product_prices`/`crm_product_stock`) — thêm reconciler, document là cache, KHÔNG drop.
- **FK còn lại** (order_returns, finance_assets, site_assemblies, solar_maintenance_*, product_goods_receipts...) — mỗi cái: dọn orphan → thêm FK guarded như migration mẫu.
- **`crm_order_edit_histories.order_id`**: có ~51 orphan (order xóa cứng). Quyết định nghiệp vụ: chuyển sang soft-delete order, hoặc null-hóa orphan, rồi mới thêm FK.
- **UNIQUE `crm_customers.phone`** (nếu nghiệp vụ yêu cầu): dedupe trước.

### P6. strict_types + PHPDoc sweep phần còn lại
- 299 file thiếu `strict_types`, 281 thiếu PHPDoc class. Làm dần khi refactor từng file (kèm test), KHÔNG sweep mù.
