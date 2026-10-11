# KPI kỹ thuật → tiền lương — đợt 07/10/2026

## Mục tiêu được giao

Người dùng yêu cầu viết skill, plan, project và prompt cho Claude làm tiếp bốn phần: đổi tiền KPI sang nhân trực tiếp tỷ lệ thực đạt; sửa R1/R2; thử công trình mới theo luồng nhập–gửi–xác nhận–nghiệm thu; đối chiếu nguồn KPI với bảng lương. Đây là nhiệm vụ chuẩn bị bàn giao, chưa phải bằng chứng các phần đó đã triển khai.

Workspace: `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`. Web: `http://127.0.0.1:8001`. PHP: `C:/php83/php.exe`. DB local: `crm_egosolar_test`; DB test có guard: `egosolar_test`, host 127.0.0.1. Không dùng source `.vn` hoặc bản sao bên trong repo.

## Công thức cần triển khai

Với KPI đầy đủ dữ liệu từ 0% đến 100%:

```text
Lương cố định = Lương thỏa thuận × Tỷ lệ cố định
Quỹ lương KPI = Lương thỏa thuận × Tỷ lệ quỹ KPI
Tiền KPI = Quỹ lương KPI × (KPI thực đạt / 100)
Tổng dự kiến trước các khoản lương khác = Lương cố định + Tiền KPI
```

Ví dụ giả: lương thỏa thuận 15.000.000đ; cố định 70%, quỹ KPI 30%; KPI 87,5%:

| Thành phần | Số tiền |
|---|---:|
| Cố định | 10.500.000đ |
| Quỹ KPI | 4.500.000đ |
| Tiền KPI trước làm tròn | 3.937.500đ |
| Tổng dự kiến trước làm tròn | 14.437.500đ |
| Nếu cấu hình làm tròn tiền KPI gần nhất 1.000đ | KPI 3.938.000đ; tổng 14.438.000đ |

Số 3.600.000đ trong ảnh cũ là 4.500.000 × 80%, do bậc payout. Không dùng số đó cho chế độ tỷ lệ mới. Không đổi quy tắc làm tròn để ép giống ví dụ trước làm tròn. Tỷ lệ 70/30 là cấu hình mẫu đang thấy, không phải yêu cầu gán cứng cho mọi nhân viên hoặc phòng ban.

Đây là thay đổi cách quy đổi tiền, không đổi cách tính điểm: trọng số kỹ thuật từ 10/2026 vẫn 25/15/15/10/5/30; KS01 vẫn hạn 24 giờ và mẫu số việc đã đến hạn. Giữ chính sách trước 10/2026, Sales, Marketing, HCNS và Kho. Thưởng/trần vượt 100% chưa được người dùng chốt lại; kế thừa cờ/trần hiện có, không tự tạo thưởng 110–120%. Khi thiếu quyết định vượt, giữ phần độc lập 0–100% và ghi rõ giới hạn.

## Trạng thái đã biết — phải đối chiếu lại trước khi sửa

- Khối checklist từng người đã có nguồn/link, trạng thái thực hiện và điểm.
- Kỹ thuật mẫu `kythuat.kpidemo@example.test` dự kiến ID 98; trưởng phòng mẫu `techmanager.kpidemo@example.test` dự kiến ID 97. Công trình cũ mã `TEST-KPI-20261006` dự kiến 63; bảo hành `TEST-KPI-20261006-BH` dự kiến 148. Phải xác minh, không coi ID là bằng chứng duy nhất. Tài khoản 98 có `company_id = null` ở lần kiểm tra gần nhất; không sửa/cấp quyền vì tài liệu cũ dự kiến công ty 2.
- Lương thỏa thuận mẫu 98 đã lưu 15.000.000đ từ 2026-10 trong `technical_kpi_salaries`. Giữ nguyên dữ liệu đó.
- Kết quả tại 07/10/2026: 2/4 việc đến hạn đúng hạn → 12,5/25; các tiêu chí khác 75 → 87,5%. Việc TEST số 05 hết hạn 08/10 09:00; số điểm có thể thay đổi theo thời gian. Không đổi mốc hoặc ép tổng 87,5% khi chạy muộn hơn.
- Snapshot local gần nhất có cấu hình id 1 archived, id 2 applied từ 01/10/2026; ảnh sau khi người dùng thao tác đã có tiền theo bậc. Không quay lại tuyên bố v2 vẫn nháp. Đọc DB hiện tại; tạo phiên bản tiếp theo bằng số phiên bản thật, không gán cứng v3.
- Hiển thị nghiệm thu vừa được sửa: hồ sơ cũ có người/giờ và kết quả đang dùng ở đầu; Admin có khối thu gọn nhập lại; lưu bản mới chuyển sang nguồn mới, nhóm chưa xác nhận báo thiếu. Đợt hiển thị có 32 test đạt/372 assertions, không chứng minh R1/R2 đã sửa.
- R1/R2 còn theo [review gốc](../technical-acceptance/REVIEW-20261006.md), trừ khi đọc source và tái hiện chứng minh đã có phiên khác sửa.
- Kỳ HR 08/2026 là snapshot Excel, nháp, 12 dòng; không sửa tiền/liên kết/ngày công/trạng thái. Chụp baseline trước/sau để kiểm chứng.

## Điểm nối source đã kiểm tra

| Nguồn | Vai trò trong đợt này |
|---|---|
| `app/Models/TechnicalKpiConfig.php` | `computePayout`, `payoutRate`, `applyRounding`, chọn phiên bản có hiệu lực |
| `app/Http/Controllers/Synced/TechnicalKpi/TechnicalKpiConfigController.php` | Tạo nháp, validate, duyệt và preview; hiện chưa có chế độ tỷ lệ |
| `app/Http/Controllers/Synced/TechnicalKpi/TechnicalPayrollController.php` | Lấy lương thỏa thuận theo tháng; chi tiết tiền KPI, các đường snapshot/duyệt |
| `app/Services/Hr/Kpi/HrKpiEvaluator.php` | Nguồn kỹ thuật gọi `payoutRate` chung, trả percent/rate cho bảng lương HR |
| `app/Services/Hr/Payroll/PayrollGenerator.php`, `PayrollCalculator.php` | Gắn hệ số KPI/ngày công, lập snapshot; cần phân biệt tổng dự kiến với thực lĩnh |
| `app/Services/Projects/AcceptanceKpiService.php` | Lưu/đổi bản, xác nhận theo nhóm, đọc hiệu lực và khóa kỳ |
| `app/Http/Controllers/Projects/ProjectWorkflowV2Controller.php` | Upload/thay/xóa hồ sơ, nhập dữ liệu bước và các hành động có thể sửa nguồn |
| `app/Services/TechnicalKpi/ProjectKpiLinkService.php` | Adapter nguồn cũ/mới, nhóm đã xác nhận; không tự tạo nguồn mới |
| `resources/views/synced/kythuat/kpi_config.blade.php`, `kpis.blade.php`, partials liên quan | Chọn cách trả tiền, công thức, hệ số thực tế và trạng thái thiếu/chờ duyệt |
| `resources/views/projects-unified/partials/workflow-kpi-review.blade.php`, `workflow-kpi-legacy.blade.php` | Giữ hiển thị nguồn cũ đã xác nhận và bản mới tách riêng |

Đường dẫn trong bảng là chỉ dẫn tìm source trong repo, không cam kết chỉ có các đường sửa này. Tìm tất cả callsite và entry point trước khi sửa khóa kỳ. Chế độ trả tiền có thể lưu trong JSON `salary_structure` nếu phù hợp; không bắt buộc thêm bảng/migration.

## Phạm vi và quyền

Claude được sửa source/test liên quan, tạo bản nháp cấu hình local và dữ liệu TEST có guard để người dùng xem. Không tự duyệt cấu hình trên DB local, không chốt bảng lương, không mở kỳ hay công trình cũ để thử. Việc người dùng đồng ý xây công thức mới không phải dấu `approved_by/approved_at` để tự ghi vào cấu hình.

Kiểm thử đủ luồng có duyệt dùng fixture trong DB test riêng; local chỉ demo hồ sơ mới, không gửi thông báo. Muốn minh họa đủ điểm công trình mới trên local thì giao thao tác nghiệm thu cuối cho người có quyền; trước đó ghi rõ còn chờ. Không cấp thêm quyền hoặc tạo tài khoản đăng nhập thật. Tái sử dụng mẫu đã xác minh, không in hay thay mật khẩu.

Không thêm menu/trang. D08 đối chiếu vật tư và quyết định bảo hành D12 không triển khai trong đợt này; nếu thiếu nguồn thì báo thiếu, không bù điểm. Repo dirty/shared: giữ baseline, không reset/clean, không commit/push/deploy.

## Điều kiện hoàn tất

F0–F5 có bằng chứng: cách trả tỷ lệ có phiên bản/hiệu lực; R1/R2 pass test tái hiện; luồng mới không dùng legacy; preview và bộ lương đọc cùng %/hệ số/tiền trước–sau làm tròn; kỳ cũ và mẫu cũ giữ nguyên. Giao URL thao tác được, hướng dẫn theo vai trò và bản nháp sẵn để người dùng duyệt ở bước cuối. Nếu chưa duyệt, trang chính tiếp tục dùng bản đang áp dụng và phải nói rõ điều đó.

## Kết quả thực hiện — 07/10/2026

**Đã triển khai, chưa áp dụng cấu hình mới.** Trang chính và bảng lương HR vẫn dùng v2 (trả theo bậc). Chưa commit/deploy.

### F1 — Tiền KPI theo tỷ lệ

- `TechnicalKpiConfig`: `salary_structure.payout_mode` = `proportional` | `tiered`; thiếu trường = `tiered` như lúc duyệt. `payoutRate()` là điểm quy đổi duy nhất: tỷ lệ → hệ số = % thực đạt (0–100%); > 100% theo cờ/trần đã có, không thêm thưởng. `computePayout()` trả thêm `payout_rate`, `real_kpi_salary_raw`, `total_income_raw`.
- Cài đặt KPI: chọn “Theo % KPI thực đạt” / “Theo bậc quy đổi”; ở tỷ lệ bảng bậc bị khóa, không bắt buộc. Xem trước 10 mốc (thiếu dữ liệu, 0, 68, 75, 85, 87,5, 90, 95, 100, 105%) với hệ số và số trước/sau làm tròn. Trang KPI từng người hiện “× hệ số”, số trước làm tròn và ghi chú “không phải thực lĩnh”.
- HR: `HrKpiEvaluator::technical()` gọi cùng `payoutRate`. HR nhân thêm tỷ lệ công theo quy tắc hiện có và làm tròn tới đồng; trang kỹ thuật làm tròn theo cấu hình (1.000 đ).
- Bản nháp: `php artisan kpi:payout-draft --apply` tạo **v3 (#3, draft)** “Trả tiền KPI theo % thực đạt (từ 10/2026)”, hiệu lực 01/10/2026, kế thừa v2; chỉ khác `payout_mode`.

| Lương thỏa thuận 15.000.000 đ, KPI 87,5% | v2 đang áp dụng (bậc) | v3 nháp (tỷ lệ) |
|---|---:|---:|
| Cố định 70% / Quỹ KPI 30% | 10.500.000 / 4.500.000 | 10.500.000 / 4.500.000 |
| Hệ số trả | 0,8 | 0,875 |
| Tiền KPI trước làm tròn | 3.600.000 | 3.937.500 |
| Tiền KPI (làm tròn 1.000 đ) | 3.600.000 | 3.938.000 |
| Tổng dự kiến trước / sau làm tròn | 14.100.000 / 14.100.000 | 14.437.500 / 14.438.000 |
| HR, đủ công (4.500.000 × 1 × hệ số, làm tròn đồng) | 3.600.000 | 3.937.500 |

### F2 — R1

Khi vân tay nhóm đổi (khai báo, minh chứng, quyết định đấu nối, lịch sử trả lại), `syncConfirmations()` chuyển xác nhận sang `confirmation_history` và hủy lượt gửi của riêng nhóm đó. Đổi về giá trị cũ không làm xác nhận hay trạng thái “chờ kiểm tra” sống lại. Trả bổ sung cũng giữ lịch sử.

### F3 — R2

`kpiSourceLock()` = nghiệm thu đã duyệt tháng M + người chịu KPI có phiếu KPI kỹ thuật tháng M đã duyệt, hoặc kỳ lương HR tháng M đã duyệt có dòng dùng KPI kỹ thuật (kỳ Excel bỏ qua). Khóa áp dụng cho mọi người kể cả Admin, kiểm tra trước khi ghi DB/file ở: lưu/gửi/xác nhận/trả KPI, tải hồ sơ Khảo sát/Đề xuất/Nghiệm thu, dữ liệu Nghiệm thu, đấu nối, trả lại Thi công/Nghiệm thu, phân công, duyệt lại, ngoại lệ, đổi kỹ sư chính. Màn hình Nghiệm thu hiện lý do và ẩn form. Khóa bước đã duyệt giữ ngoại lệ Admin như cũ khi kỳ chưa chốt.

### F4 — Công trình mới

- Local: `php artisan kpi:demo-salary-site --apply` tạo **#64 `TEST-KPI-LUONG-20261007-01`** (kỹ sư 98, trưởng phòng 97 giao, 15 file giả, đấu nối = Có). Trên trình duyệt đã chạy: nháp thiếu 1 mục → gửi → trưởng phòng trả HSE → gửi lại → xác nhận 3 nhóm → kỹ thuật “Gửi duyệt”. **Đang chờ Admin duyệt nghiệm thu** — chưa vào KPI.
- DB test (test + server cô lập 8012): chuỗi đủ quyền tới Admin duyệt; KPI 87,5% → 3.600.000 với bản bậc → Admin duyệt bản tỷ lệ → 3.938.000/14.438.000; khóa kỳ chặn Admin.

### Kiểm thử

- `TechnicalKpiSalaryCloseoutTest` 12/12. Cùng 10 bộ liên quan: 164 ca, 162 đạt; 2 lỗi `PayrollFlowTest` (redirect hồ sơ/cài đặt) đã có trước, không do đợt này.
- Ba ca review R1/R2 gốc: trước 3 fail → sau 3/3 đạt.
- Nhóm mở rộng 370 ca: 11 lỗi, đều thuộc nhóm đã biết (8 markup trang KPI, 1 trang báo cáo, 2 HR redirect).
- Đổi kỳ vọng 1 ca `TechnicalAcceptanceKpiFlowTest`: lịch sử xác nhận nay ở `confirmation_history`.
- Trình duyệt: local 8001 15/15 (2 vai trò mẫu, desktop + 390 px); cô lập 8012 10/10 (cache array, session cookie, mail log; dọn sạch, 0 file cache dùng chung). HR chưa thử trên trình duyệt.
- Baseline trước/sau giữ nguyên: v1/v2, kỳ HR 08/2026 (12 dòng nháp), công trình 63, phiếu 148, 5 việc mẫu, lương 98, KPI 98.

### Giới hạn và việc còn lại

- Người dùng duyệt v3 tại `/ky-thuat/kpis/cai-dat?version_id=3` (Admin), và nếu muốn tính công trình #64 thì Admin duyệt nghiệm thu `/du-an/64?step=acceptance#workflow` (KPI 98 sẽ có thêm 1 công trình).
- KPI 98 đổi sau 08/10 09:00 (việc 05 hết hạn): 87,5% → 85%.
- Vật tư/đề xuất vật tư (KS03) chưa nằm trong khóa kỳ (D08). Module phiếu lương cũ (`calculate()` theo bảng cài đặt riêng) không dùng cấu hình có phiên bản — giữ nguyên.
- Một lần chạy test lệnh công trình mẫu đã ghi 15 file giả ra storage dùng chung (thư mục site ID chỉ có trong DB test); đã xóa đúng thư mục đó và sửa lệnh dùng disk `public`.
