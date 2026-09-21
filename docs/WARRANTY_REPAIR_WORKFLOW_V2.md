# Bảo hành & Sửa chữa — Quy trình v2

Khu vực: **Kỹ thuật → Bảo hành & Sửa chữa** (`/ky-thuat/de-xuat-doi-hang-bao-hanh`, `/ky-thuat/sua-chua-tinh-phi`, `/ky-thuat/de-xuat-doi-hang-bao-hanh/viec-kho`).
Route cũ giữ nguyên để không gãy link.

## A. Đổi hàng bảo hành (11 bước)

| # | Bước | Ai làm | Trạng thái / dữ liệu |
|---|---|---|---|
| 1 | Tiếp nhận lỗi | Kỹ thuật | `received` (audit) |
| 2 | Kiểm tra serial & bảo hành | Hệ thống (server-side) | `eligibility_check` (audit); tồn tại, đơn/công trình, công ty, hạn BH, phiếu mở khác |
| 3 | Tạo đề xuất | Kỹ thuật | `pending_approval` |
| 4 | Duyệt | Trưởng phòng KT / Giám đốc / Admin | `approved` · `needs_more_information` · `rejected` (lý do bắt buộc) |
| 5 | Chuyển Kho | Hệ thống | `waiting_stock` + việc/thông báo cho Kho |
| 6 | Kho chọn serial & **giữ hàng** | Kho | `reserved` (serial `reserved`, reservation active) |
| 7 | Kho xuất | Kho | `issued` (serial `sold`, inventory event, tồn tổng −1) |
| 8 | Kỹ thuật nhận & thay | Kỹ thuật phụ trách | `technician_received` → `replacing` |
| 9 | Thu hồi thiết bị lỗi | Kho | `waiting_faulty_return` → `faulty_returned` (hoặc **hoãn** có lý do: đổi trước – thu sau) |
| 10 | Lưu cặp serial cũ ↔ mới | Hệ thống | bảng `warranty_serial_replacements` |
| 11 | Hoàn tất | Trưởng phòng / Admin | `completed` (checklist bắt buộc) |

Ngoại lệ bảo hành: `warranty_exception`, `exception_reason`, `exception_requested_by/at`, `exception_approved_by/at`.
Người đề nghị ngoại lệ (hoặc người tạo/phụ trách) **không tự duyệt**; chỉ emergency override (quyền `Admin` hoặc permission `warranty.override`) + lý do bắt buộc + audit `approve_override`.

## B. Sửa chữa tính phí (10 bước)

`received → diagnosing → quotation_draft → waiting_customer_confirmation → (quotation_rejected | approved_for_repair) → waiting_parts → repairing → qa_testing → (qa_failed → repairing | ready_handover) → handed_over → completed`

**CHI PHÍ = LINH KIỆN + CÔNG SỬA + PHÍ ONSITE + VẬN CHUYỂN + PHÁT SINH − GIẢM GIÁ** — tính ở server (`RepairService::computeQuotation`, làm tròn theo đồng/xu, không tin số tiền frontend).
Sửa báo giá đã gửi/duyệt ⇒ **version mới**, bản cũ `superseded`, khách phải xác nhận lại. Hoàn tất khóa snapshot báo giá cuối (`completion_snapshot`, `final_cost`).

## Phân quyền (đọc từ `SolarMaintenanceAccess`)

| Hành động | KT viên | Trưởng phòng KT / GĐ | Kho | Admin |
|---|---|---|---|---|
| Tạo đề xuất / tiếp nhận | ✔ (tự phụ trách) | ✔ | ✘ | ✔ |
| Duyệt / bổ sung / từ chối | ✘ | ✔ (không tự duyệt phiếu của mình) | ✘ | ✔ (tự duyệt = override + lý do) |
| Chọn/giữ/xuất serial, thu hồi, linh kiện | ✘ | ✘ | ✔ | ✔ |
| Nhận hàng, xác nhận thay, chẩn đoán, báo giá, sửa, QA, bàn giao | ✔ (phiếu mình phụ trách) | ✔ | ✘ | ✔ |
| Hoãn thu hồi, hoàn tất, hủy, mở lại phiếu từ chối | ✘ | ✔ | ✘ | ✔ |
| Xem ghi chú nội bộ / file minh chứng | ✔ | ✔ | file | ✔ |
| Xem chi phí | ✘ | ✘ | ✘ | ✔ (+ Kế toán) |

Role `manager` chung của phòng ban khác **không** còn quyền duyệt hoặc làm việc của Kho trong module này.

## Reservation (giữ hàng thật)

Bảng `warranty_serial_reservations` (`active_key` UNIQUE = `serial_unit_id` khi đang giữ, NULL khi kết thúc) ⇒ DB không cho giữ 1 serial cho 2 phiếu.
Kho chọn serial: kiểm tra tồn kho sẵn sàng đúng kho, **cùng `product_id`**, khác serial lỗi, cùng công ty, chưa giữ/không có phiếu kho mở. Serial chuyển `reserved`.
Hủy phiếu / nhả hàng / đổi serial ⇒ release đúng: trả state cũ, movement `cancelled`, reservation `released`. Xuất kho ⇒ reservation `consumed`.
Linh kiện sửa chữa: `warranty_repair_parts` (tồn khả dụng = `crm_product_stock.qty` − đang giữ).

## Serial cũ ↔ mới

`warranty_serial_replacements` (claim_id UNIQUE, old/new serial unit + code, product, order, site, customer, replaced_at, technician). Truy vấn hai chiều theo `old_serial_unit_id` / `new_serial_unit_id`; `crm_serial_warranties` của serial mới có `replaced_serial_unit_id`, `replacement_claim_id`, gắn đúng công trình/khách/đơn (kế thừa thời hạn BH còn lại).

## Audit

`warranty_claim_events` — chỉ INSERT: action, from/to status, before/after (JSON), reason, user, IP, đối tượng liên quan. Ghi ở tạo, duyệt/bổ sung/từ chối, ngoại lệ, reserve/swap/release, xuất kho, nhận hàng, thay, thu hồi, hoãn, báo giá (version), khách duyệt, linh kiện, sửa, QA, bàn giao, hoàn tất, upload/xóa minh chứng.

## File minh chứng

Disk `local` (private, `storage/app/private/warranty-claims/{id}/{random}.ext`), tải qua controller có phân quyền, MIME thật (finfo), tối đa 8 tệp × 20MB, JPG/PNG/WEBP/PDF, khóa thêm/xóa khi phiếu đóng/hủy, log upload/xóa. Bản ghi cũ (disk `public`) vẫn tải được qua controller.
Nếu bảng minh chứng chưa migrate: **báo lỗi rõ**, không im lặng bỏ qua file.

## Chống trùng / race / double action

- `crm_serial_warranty_claims.open_serial_key` UNIQUE (chỉ giữ khi phiếu mở) + `lockForUpdate` trong transaction.
- Mọi action khóa dòng phiếu, kiểm tra trạng thái theo whitelist `WarrantyFlow`, không nhận status từ request; endpoint cũ `claims.status` bị khóa với phiếu đổi hàng/sửa chữa.
- `completed → completed`, xuất kho lần 2, hoàn kho lần 2 đều bị chặn; `approved_by` / `closed_by` không bị ghi đè.

## Migration (additive)

`2026_09_22_000100_warranty_workflow_v2_core.php`, `2026_09_22_000200_warranty_repair_v2_tables.php`. Deploy production: `php artisan migrate --force` (backfill `open_serial_key` chỉ cho serial có đúng 1 phiếu mở). **Chưa deploy.**

## Còn tồn tại

- Báo giá đang sửa chữa (`repairing`) không cho lập version mới — phát sinh giữa chừng phải qua QA/hủy.
- Linh kiện sửa chữa chỉ theo số lượng (không theo serial linh kiện).
- Việc/thông báo cho Kho là bảng `warranty_claim_notifications` + màn “Việc của Kho” (chưa đẩy email/Zalo).
- Mở lại phiếu `completed` chưa hỗ trợ (chỉ mở lại phiếu bị từ chối).
- Phiếu cũ (trước v2) ở trạng thái `waiting_customer`/`solution_proposed`… không có đường chuyển trong quy trình mới; phiếu cũ `pending_approval`, `approved`, `waiting_stock`, `replacing` tiếp tục xử lý được bằng quy trình mới (checklist tự bỏ qua `issued_at`/`tech_received_at` cho phiếu đã xuất bằng luồng cũ).
- Trên DB test `egosolar_test` (bản chụp cũ) 31 test cũ ngoài module (Orders/Finance/Sales/Products/Smoke) đã lỗi từ trước — xem báo cáo.
