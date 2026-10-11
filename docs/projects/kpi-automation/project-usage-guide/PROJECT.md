# Hướng dẫn sử dụng Công trình → Nghiệm thu → KPI

Ngày giao: 07/10/2026. Trạng thái: **Đã triển khai nút + trang hướng dẫn trên source local (G0–G4, xem mục Kết quả); chưa commit/deploy.**

## Yêu cầu người dùng

Trên trang chi tiết công trình, thêm nút **Hướng dẫn sử dụng** ở góc phải phía đầu nội dung (vùng trống trên khối tên công trình trong ảnh). Bấm sang trang riêng có hình, hướng dẫn dễ hiểu nhất cho người mới. Không thêm mục sidebar. Trang riêng mở cùng tab; có nút quay lại công trình đang xem.

Ảnh tham chiếu bố cục: `C:/Users/PC/AppData/Local/Temp/codex-clipboard-f4470c98-656b-464d-b256-289965f12c66.png`. Đây là ảnh tham chiếu giao diện, không phải dữ liệu công trình thật được phép công khai. Nếu file tạm không còn, căn cứ vị trí được mô tả và view hiện hành, không trì hoãn việc thiết kế.

## Mục tiêu người đọc

Sau khi đọc, nhân viên biết cần nhập gì và bấm gì để gửi kết quả; trưởng phòng biết xác nhận/trả bổ sung; Admin hiểu duyệt nghiệm thu cuối. Mọi người phân biệt được hồ sơ đã đủ, dữ liệu đã xác nhận, nguồn đã vào KPI và lương đã duyệt.

## Nội dung trang

Đề xuất tiêu đề **Hướng dẫn công trình và nghiệm thu tính KPI**. Giới thiệu tối đa vài dòng; trình bày một sơ đồ ngắn và các bước có ảnh. Dùng tên nút đúng giao diện, không đưa tên DB/service/JSON vào hướng dẫn người dùng.

1. **Bạn cần làm gì?** Ba thẻ hoặc ba mục Kỹ thuật / Trưởng phòng kỹ thuật / Admin. Cho chọn để nhảy tới phần tương ứng; vẫn có tổng quan quy trình chung. Quyền thật do backend quyết định, đọc bài không cấp thêm quyền.
2. **Công trình đi qua những bước nào?** Giải thích ngắn Tổng quan, Khảo sát & PA, HĐ & Pháp lý, Đề xuất vật tư, Thi công, Nghiệm thu, Tài chính. Chỉ mô tả hành vi đã đối chiếu source; không tạo hướng dẫn thao tác chi tiết cho cả ERP.
3. **Kỹ thuật nhập nghiệm thu:** mở đúng công trình/bước; xem người phụ trách; tải hồ sơ bắt buộc theo cấu hình; nhập kết quả chất lượng/khách hàng, 5 mục HSE, EVN/App cùng minh chứng; Lưu nháp hoặc Gửi kiểm tra. Phân biệt các nút lưu hồ sơ, gửi kết quả KPI và gửi duyệt bước nghiệm thu nếu màn hình có cả ba.
4. **Trưởng phòng kiểm tra:** xem đúng bản đã gửi và minh chứng; xác nhận nhóm đủ dữ liệu hoặc ghi lý do trả bổ sung. Người thực hiện không tự xác nhận; Admin ngoại lệ chỉ mô tả đúng chức năng hiện có. Kỹ thuật sửa theo lý do, gửi lại; nhóm đã sửa phải xác nhận lại.
5. **Admin duyệt nghiệm thu:** thao tác duyệt bước cuối theo quyền hiện có, sau điều kiện thực tế. Xác nhận KPI của trưởng phòng không thay thao tác này. Nếu thiếu điều kiện, mô tả thông báo thật và cách bổ sung, không khuyên bỏ qua kiểm tra.
6. **Xem điểm:** dùng link nguồn trên trang và checklist KPI đúng người/kỳ. Nêu ai chịu KPI theo phân công thật, kỳ của các tiêu chí công trình theo bộ chấm (hiện gắn kỳ duyệt nghiệm thu), không suy từ ngày chụp ảnh.

### Những nhầm lẫn phải giải thích

| Người dùng thấy | Ý nghĩa cần nói |
|---|---|
| Vòng tròn 91% trên công trình | Tiến độ quy trình công trình; không phải 91% KPI hay 91% tiền lương |
| Hồ sơ đủ / Đã nhập kết quả | Chưa tự đồng nghĩa dữ liệu KPI đã được xác nhận |
| Lưu nháp / Đã gửi kiểm tra | Nháp chưa tính; gửi còn cần kiểm tra/xác nhận và điều kiện nghiệm thu |
| Trưởng phòng xác nhận nhóm | Xác nhận dữ liệu, không thay Admin duyệt nghiệm thu |
| Nghiệm thu đã duyệt | Nguồn đủ điều kiện được bộ chấm xét; không đảm bảo mọi tiêu chí đạt hoặc toàn bộ KPI 100% |
| Nguồn cũ đã xác nhận, bản mới chưa nhập | Đọc khối nguồn đang được dùng; không suy bản trống là toàn bộ điểm đã bị bỏ |
| Kỳ KPI/lương đã duyệt | Nguồn liên quan bị khóa theo phạm vi đang triển khai; không hướng dẫn Admin sửa vượt khóa |
| Trang KPI và lương dự kiến | Điểm khác tiền thực lĩnh; tiền dùng cấu hình đã áp dụng, bản nháp chỉ xem trước |

Nguồn tháng 10/2026: Tiến độ **25%** từ việc giao → Báo cáo ngày, hạn 24 giờ tính từ giao và lần gửi đầu; công trình cung cấp Khảo sát **15%**, Chất lượng **15%**, HSE **10%**, EVN/App **5%**; bảo hành **30%** từ phiếu bảo hành. Không cộng nguyên 45 điểm cho mỗi công trình, không nói các bước quy trình cứ hoàn thành là tự nhận điểm. Mức đạt phải theo bộ chấm và tổng nguồn trong kỳ.

### Ảnh minh họa cần có

Tối thiểu sáu ảnh rõ, mỗi ảnh gắn đúng một thao tác hoặc trạng thái:

- Mở công trình, vị trí bước Nghiệm thu và vòng tròn tiến độ.
- Tải hồ sơ, nhận biết hồ sơ còn thiếu/đã đủ.
- Kỹ thuật khai kết quả và thấy nút Lưu nháp/Gửi kiểm tra.
- Trưởng phòng xác nhận hoặc trả bổ sung, ghi rõ hai kết quả khác nhau.
- Admin duyệt bước nghiệm thu cuối, không dùng ảnh xác nhận nhóm thay thế.
- Checklist KPI: mở đúng nguồn công trình, nhận biết đã tính/chờ/thiếu dữ liệu.

Có thể thêm ảnh nguồn cũ và kỳ khóa để trả lời thắc mắc. Chụp thực trên fixture TEST, không bịa trạng thái. Ảnh không có nội dung nhạy cảm; chữ đọc được trên điện thoại khi phóng to. Mỗi ảnh có chú thích “Bấm … → Kết quả …” và ghi dữ liệu minh họa. Không biến hướng dẫn thành một loạt ảnh toàn màn hình khó đọc.

## Source và cách tích hợp

- Trang công trình: `resources/views/projects-unified/show.blade.php`; route `projects-unified.show`.
- Hồ sơ, dữ liệu, nguồn cũ: partials `workflow-word.blade.php`, `workflow-kpi-review.blade.php`, `workflow-kpi-legacy.blade.php`.
- Quyền/trạng thái: `AcceptanceKpiService`, `ProjectWorkflowV2Controller`, controller Công trình và policy hiện hành. Phải tìm entry point thật trước khi sửa.
- Bộ hướng dẫn sẵn: `config/technical_guides.php`, `TechnicalGuideRegistry`, `TechnicalGuideController`, `technical.guides.*`, `resources/views/technical/guides/layout.blade.php` và partials step/lightbox/flow; CSS `public/css/ego-technical-guide.css`.
- Các route hướng dẫn kỹ thuật giới hạn vai trò module. Ưu tiên tái sử dụng phần trình bày; khi nhóm được xem Công trình rộng hơn kỹ thuật, thêm trang hướng dẫn trong phạm vi route Công trình với cùng kiểm tra quyền xem công trình. Ví dụ **route dự kiến, chưa tồn tại** `/du-an/{site}/huong-dan`; không mở toàn module kỹ thuật để ai cũng xem được bài này.
- Link quay lại chỉ nội bộ, giữ site, bước/query và anchor phù hợp; không nhận URL bên ngoài, không hardcode 64 vào source. Nếu mở trực tiếp không có return thì quay lại công trình tương ứng sau kiểm tra quyền. Không tải hồ sơ của site khác để minh họa.
- Các bài KPI cũ có nội dung 5 tiêu chí và trọng số lỗi thời. Không dùng làm nguồn; nếu liên kết sang bài cũ, sửa đoạn liên quan để đúng hoặc dùng link màn hình nghiệp vụ thật. Không mở rộng thành viết lại toàn bộ thư viện hướng dẫn.

## Môi trường và phạm vi

Repo `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`; PHP `C:/php83/php.exe`; local 8001, DB `crm_egosolar_test`. Test chỉ `egosolar_test` theo guard repo, không bypass. Tách cache/session/mail/storage cho test/server chụp ảnh; không lặp sự cố ghi đè thương hiệu hoặc file giả trong storage local.

Local chỉ đọc, không sửa demo 63/64/148, lương, cấu hình, phân công hay tài khoản. Fixture và các thao tác duyệt để chụp ảnh chạy trên môi trường test riêng, rollback/dọn đúng dữ liệu và thư mục fixture. Không migration, thay công thức, tự duyệt local, commit/push/deploy hoặc gửi thông báo.

Ảnh public đã kiểm tra nằm `public/images/guides/projects/` hoặc assets theo repo; ảnh/log kiểm chứng riêng trong `outputs/claude-kpi-agent/project-usage-guide-20261007/`. Nội dung public không chứa tên khách hàng thật, mật khẩu, số tài khoản, token, lương thật hoặc tab trình duyệt cá nhân.

## Bàn giao

Nút bấm được, trang đọc được trên desktop/mobile, có ảnh thật phóng to, quay lại đúng công trình, không ảnh hưởng điểm/quyền. Giao link công trình có nút, link bài hướng dẫn đã xác minh, ảnh trang cuối cùng và checklist đã kiểm. Không giao riêng file Markdown thay cho trang web; không ghi hoàn thành khi chưa có ảnh/đã còn 403 hoặc placeholder.

## Kết quả — 07/10/2026 (Claude)

**Route và quyền (G0-03).** `GET /du-an/{site}/huong-dan`, tên `projects-unified.guide`, đặt trong nhóm route Công trình (cùng `auth`, `EnsureUnifiedProjectAccess`, `page.sites`) và gọi đúng `abortIfCannotView` của trang công trình: ai xem được công trình thì đọc được bài, ai bị chặn ở công trình thì bị chặn y hệt ở bài. Không thêm quyền, vai trò hay mục menu. Link trang KPI trong bài chỉ hiện với vai trò mà route KPI cho vào.

**Quay lại đúng chỗ.** Nút trên trang công trình mang bước đang xem; khi bấm, trình duyệt gửi thêm vị trí `#` hiện tại. Server dựng link quay lại từ danh sách bước cho phép và anchor hợp lệ, không nhận URL ngoài. Mở bài trực tiếp hoặc tham số lạ thì quay về tổng quan công trình.

**Thay đổi source (chỉ thêm, không sửa logic nghiệp vụ):**

- `routes/project_unified.php`: 1 route.
- `UnifiedProjectController::guide()`: kiểm quyền, dựng link quay lại, đọc bộ tiêu chí/cấu hình của tháng hiện tại để hiển thị.
- `projects-unified/show.blade.php`: thanh nút **Hướng dẫn sử dụng** phía trên khối tên công trình + CSS/JS nhỏ.
- `projects-unified/guide.blade.php`: trang hướng dẫn 9 mục, dùng lại CSS và partials của bộ hướng dẫn kỹ thuật.
- `public/images/guides/projects/p01…p08.png`: 8 ảnh thật dữ liệu TEST, có khoanh số.
- `tests/Feature/Technical/ProjectUsageGuideTest.php`: 10 ca.

**Nội dung.** Trọng số và tên tiêu chí lấy từ bộ chấm lúc mở trang; local 10/2026 hiện 25/15/15/10/5/30, câu cấu hình hiện “đang dùng v2 … tiền KPI theo bậc quy đổi”. Ảnh chụp trên fixture TEST cô lập (`egosolar_test`, server 8012), đã dọn.

**Giới hạn còn lại.**

- Admin chỉ thử trình duyệt trên 8012, không dùng tài khoản Admin local.
- Ảnh p01 có vòng tròn 86% của công trình TEST, bài ghi rõ đó là ví dụ.
- Ảnh sẽ cần chụp lại nếu giao diện Nghiệm thu đổi; lệnh chụp nằm trong `outputs/claude-kpi-agent/project-usage-guide-20261007/ui-test/`.
- Chưa thử với Sales/Kế toán đọc bài (cùng quyền xem công trình, không có nút nghiệp vụ mới).
