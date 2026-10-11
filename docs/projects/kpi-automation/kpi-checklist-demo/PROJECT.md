# Checklist KPI từng người — 07/10/2026

## Yêu cầu đã giao

Người dùng muốn khi mở chi tiết từng nhân viên thấy ngay checklist cái nào hoàn thành, cái nào chưa hoàn thành, và thêm dữ liệu mẫu để các trạng thái xuất hiện. Triển khai trên source `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`, web local `http://127.0.0.1:8001`. Không thêm menu hoặc trang mới.

Trang nghiệm thu chính: `/ky-thuat/kpis?month=2026-10&user_id=98#kpi-detail-view`. ID 98 là gợi ý mẫu, phải xác minh lại trước khi ghi dữ liệu.

## Giao diện mong muốn

Ngay dưới mỗi tiêu chí trong chi tiết người đang chọn, hiện tóm tắt số mục và checklist nguồn. Nhiều dòng có thể thu gọn ngay tại chỗ, nhưng mặc định phải thấy trạng thái và nguồn đầu tiên; hộp “Xem cách tính” chỉ bổ sung công thức.

| Tiêu chí | Checklist cần đọc từ nguồn thật |
| --- | --- |
| Tiến độ 25% | Từng việc được giao; giờ giao, hạn 24 giờ, lần gửi đầu, trạng thái báo cáo và phần điểm |
| Chất lượng 15% | Từng công trình; kết quả nghiệm thu/phản ánh và xác nhận còn hiệu lực theo bộ chấm |
| Khảo sát 15% | Từng công trình; khảo sát/vật tư đạt, không đạt hoặc thiếu dữ liệu theo nguồn đang dùng |
| HSE 10% | Từng công trình và các mục HSE đã có; kết quả và người/giờ xác nhận |
| EVN/App 5% | Từng công trình; quyết định cần đấu nối, mục áp dụng, kết quả và xác nhận |
| Bảo hành 30% | Từng phiếu; các điều kiện phản hồi, hoàn tất, khiếu nại và điểm theo bộ chấm |

Đọc đúng adapter đang chạy cho nguồn cũ và nguồn mới; không bịa các mục con mà hồ sơ không có. Nếu không có nguồn, hiện “Chưa có dữ liệu” kèm lý do/cách mở nơi nhập đang có. Xác nhận không còn hiệu lực hoặc chờ kiểm tra phải thể hiện rõ.

Mỗi dòng có tên/link nguồn, trạng thái thực hiện, trạng thái KPI, nhận/mất phần điểm hoặc “Chưa tính” và lý do. Người/giờ xác nhận hiện khi nguồn có. Không chia điểm đều cho các mục HSE/EVN nếu bộ chấm không có quy tắc đó; điểm được chấm ở cấp nào thì trình bày ở cấp đó.

Ví dụ: “Đã nộp · Muộn 2 giờ · Không nhận 6,25 điểm”; “Chưa nộp · Còn hạn · Chưa tính”. Chưa đến hạn không có số điểm mất. Không có checkbox duyệt mới tại trang này. Link dẫn đúng công trình/bước, báo cáo hoặc phiếu, giữ kiểm tra quyền ở đích. Bố cục điện thoại phải hiển thị checklist dù bảng desktop bị ẩn.

## Mẫu local được phép thêm

DB local: `crm_egosolar_test` trên 127.0.0.1; DB test tự động: `egosolar_test`. Xác minh environment và config thực tế trước khi ghi.

Mẫu dự kiến có sẵn, phải kiểm tra bằng định danh ổn định cùng công ty/role, không chỉ ID:

- Trưởng phòng: `techmanager.kpidemo@example.test`, dự kiến ID 97.
- Kỹ thuật: `kythuat.kpidemo@example.test`, dự kiến ID 98.
- Công trình: mã `TEST-KPI-20261006`, dự kiến ID 63.
- Bảo hành: mã `TEST-KPI-20261006-BH`, dự kiến ID 148.
- Công ty mẫu dự kiến ID 2. Dừng ghi nếu mẫu không khớp; không tự tạo người thật hoặc cấp quyền để vượt điều kiện.

Thêm 5 việc trưởng phòng giao cho kỹ thuật mẫu, liên kết đúng công trình mẫu, tiền tố `TEST-KPI-CHECKLIST-20261007-`. Nội dung và ghi chú ghi rõ dữ liệu giả:

| Việc | Mốc/trạng thái minh họa tại lúc tạo | Kết quả mong đợi |
| --- | --- | --- |
| 01 | Đã đến hạn, gửi lần đầu trước hạn | Nhận phần điểm |
| 02 | Đã đến hạn, gửi đúng biên 24 giờ | Nhận phần điểm |
| 03 | Đã đến hạn, gửi muộn 2 giờ | Đã nộp nhưng mất phần điểm |
| 04 | Đã quá hạn, không có báo cáo đã gửi | Chưa nộp, mất phần điểm |
| 05 | Còn hạn, chỉ có báo cáo nháp | Chờ nộp, chưa tính/chưa mất điểm |

Theo bộ chấm hiện tại, 4 việc đến hạn chia 25 điểm: mỗi việc 6,25; 2 đúng hạn nhận 12,5/25. Nếu năm tiêu chí khác vẫn đạt 75 điểm và không có nguồn/điều chỉnh khác, tổng là 87,5. Kiểm tra thực tế, không ép tổng bằng số nhập tay hoặc xóa dữ liệu khác. Việc còn hạn sẽ đến hạn sau này: ghi rõ thời điểm đánh giá và thời điểm hết hạn trong manifest, không quảng bá mẫu là số cố định mãi mãi.

Giờ mẫu nằm trong tháng 10/2026, timezone theo app đã xác minh (dự kiến Asia/Bangkok). Nhật ký gửi và lần gửi đầu khớp dữ liệu giả; không dùng ngày báo cáo thay giờ nộp. Khi chạy lại giữ mốc đã tạo, không làm mới giờ để cứu điểm. Một việc một mẫu, không tạo trùng báo cáo/lịch sử. Script/command có guard, chỉ chạy chủ động; không seed mặc định. Nếu có dữ liệu mẫu cùng tiền tố từ lần trước thì xác minh và tái sử dụng, không ghi đè nội dung nhân viên đã thao tác sau đó.

Ghi baseline và manifest ID tạo/tái sử dụng trong thư mục private `outputs/kpi-checklist-demo-20261007/` được gitignore. Giữ công trình 63, bảo hành 148 và các xác nhận đã có; không mở lại bước đã duyệt hoặc sửa dữ liệu để làm minh họa. Các tình huống thiếu/fail khác có thể kiểm bằng fixture DB test; không cần phá mẫu đang đạt.

## Giới hạn và điều kiện hoàn tất

Giữ kỳ lương 08/2026 (12 dòng snapshot, nháp), cấu hình tháng 10 hiện có và tiền/duyệt nguyên trạng. Không gửi thông báo thật, không lưu thông tin đăng nhập vào file theo dõi Git. Repo đang có nhiều sửa đổi của các phiên khác: đọc baseline và chỉ sửa phạm vi cần thiết.

Lỗi review R1/R2 của đợt Nghiệm thu còn theo `../technical-acceptance/REVIEW-20261006.md`; không tự đóng hoặc tuyên bố đã sửa trong đợt giao diện này. Không đổi quy tắc vật tư D08, bảo hành D12 hoặc tiền lương.

Hoàn tất khi người dùng mở URL thấy checklist thực tế, nhấp được nguồn, nhận diện đúng hạn/muộn/chưa nộp/còn hạn, và điểm khớp cách tính tay. Kiểm tra quyền và mobile; cập nhật [CHECKLIST](CHECKLIST.md) bằng bằng chứng.

## Kết quả — 07/10/2026

Đã triển khai trên source local, chưa commit/deploy. Bằng chứng private: `outputs/kpi-checklist-demo-20261007/` (thêm vào `.gitignore`).

**Giao diện.** Trang `/ky-thuat/kpis?month=2026-10&user_id=98#kpx-checklist`: ngay dưới bảng 6 tiêu chí có khối "Checklist nguồn theo tiêu chí", mỗi tiêu chí một khối gồm điểm (vd. 12,5/25), số dòng nhận/mất/chưa tính/thiếu và tối đa 5 dòng hiện sẵn (còn lại thu gọn). Mỗi dòng: tên nguồn có link, mốc, cột **Thực hiện** (đã nộp đúng hạn, đã nộp · muộn 2 giờ, chưa nộp · quá hạn, chưa nộp (có bản nháp) · còn hạn, chờ trưởng phòng xác nhận, đã đánh giá (dạng cũ)…) và cột **KPI** (nhận/mất X điểm, chưa tính kèm lý do, loại trừ). Mỗi dòng bảng tiêu chí có link "Checklist ↓". Trên điện thoại mỗi dòng xếp một cột.

**Nguồn duy nhất.** `KpiChecklistPresenter` chỉ đọc output bộ chấm; bộ chấm gắn `points_possible/earned` cho dòng đạt/không đạt khi tiêu chí đã có điểm (không gắn khi tiêu chí thiếu dữ liệu hoặc nhập tay theo tháng). `AssignedWorkKpiService` thêm `phase`, `due_at`, `late_seconds`, `draft_report_id`; adapter Nghiệm thu thêm trạng thái nhập/xác nhận từng nhóm. Không đổi công thức hay trọng số. Link nguồn chuyển từ Blade sang presenter, dùng chung cho hộp "Xem cách tính". Nháp chỉ chủ báo cáo mở trực tiếp; trưởng phòng mở dòng việc ở màn điều phối.

**Mẫu TEST trên local.** `php artisan kpi:demo-checklist` (xem trước) / `--apply` (ghi). Guard: môi trường local + DB `crm_egosolar_test` @127.0.0.1 (testing: `egosolar_test`), công ty 2, 4 định danh mẫu; ghi trực tiếp trong transaction, không phát thông báo. Đã tạo plan item 1–5 (nguồn = phân công #7 của kỹ thuật 98 ở công trình 63), báo cáo 1–4, nhật ký giao/gửi đúng giờ mẫu. Chạy lại giữ nguyên.

| Việc | Giao | Hạn 24 giờ | Gửi lần đầu | Trạng thái tại 07/10 10:19 |
|---|---|---|---|---|
| 01 | 02/10 08:00 | 03/10 08:00 | 02/10 17:00 | Đúng hạn — nhận 6,25 |
| 02 | 03/10 08:00 | 04/10 08:00 | 04/10 08:00 (đúng biên) | Đúng hạn — nhận 6,25 |
| 03 | 04/10 08:00 | 05/10 08:00 | 05/10 10:00 | Muộn 2 giờ — mất 6,25 |
| 04 | 05/10 08:00 | 06/10 08:00 | — | Quá hạn chưa nộp — mất 6,25 |
| 05 | 07/10 09:00 | 08/10 09:00 | — (chỉ nháp) | Còn hạn — chưa tính |

KS01 = 2/4 × 25 = **12,5/25**; năm tiêu chí còn lại 15 + 15 + 10 + 5 + 30 = 75 (nguồn mẫu cũ, KS02/04/05 là đánh giá dạng cũ); tổng **87,5%**, khớp trang. Sau 08/10/2026 09:00 nếu việc 05 vẫn chưa nộp: 2/5 × 25 = 10, tổng 85 — mẫu không phải con số cố định.

**Kiểm thử.** `TechnicalKpiChecklistTest` 8/8 (96 assertions); cùng KS01, nguồn KPI, nhập tháng, Nghiệm thu, bảo hành: 123/123 (801 assertions) trên `egosolar_test`. Nhóm mở rộng 335 ca: 9 lỗi có từ trước (8 markup `TechnicalKpiStandardPageTest`, 1 `TechnicalAdminPagesTest`), không có 500; chạy gộp cần `memory_limit=1G`. Trình duyệt thật trên 8001 với 2 tài khoản mẫu: 34/34 bước (checklist, link nguồn HTTP 200, quyền, điện thoại 390 px).

**Giữ nguyên.** Công trình 63, bước/hồ sơ/sự kiện, phiếu 148, lương 08/2026 (12 dòng nháp) và cấu hình KPI không đổi hash. R1/R2 của đợt Nghiệm thu vẫn mở theo `../technical-acceptance/REVIEW-20261006.md`.
