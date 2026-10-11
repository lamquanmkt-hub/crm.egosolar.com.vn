# Kế hoạch và checklist thực hiện

Trạng thái ban đầu: tất cả chưa thực hiện. Cập nhật bằng chứng ngay dưới từng mã, phân biệt code đã làm, test đã chạy và phần chưa thử.

Cập nhật 07/10/2026 (Claude): G0–G4 đã làm trên source local, **chưa commit/deploy**. Bằng chứng riêng: `outputs/claude-kpi-agent/project-usage-guide-20261007/` (gitignore).

## G0 — Đối chiếu trước khi làm

- [x] G0-01 Đọc skill/PROJECT, tìm view/route/quyền thực của công trình và hướng dẫn sẵn; xác minh các tên nút trong ảnh còn đúng.
  - Trang `projects-unified.show` → `UnifiedProjectController@show`, quyền xem = `abortIfCannotView` (Sales theo phạm vi; quản lý/tài chính/kho; kỹ sư chính, người tạo, người được giao việc hoặc bước). `/du-an` cần `page.sites`. Hướng dẫn kỹ thuật `technical.guides.*` giới hạn vai trò Kỹ thuật. Tên nút đối chiếu từ `workflow-word` và `workflow-kpi-review`: Tải lên, Lưu, Lưu nháp, Gửi kiểm tra, Xác nhận nhóm đã chọn, Trả bổ sung, Gửi duyệt, Duyệt/Duyệt ngoại lệ, Trả hồ sơ cần sửa.
- [x] G0-02 Chụp baseline local chỉ đọc (KPI mẫu, trạng thái demo 63/64, cấu hình áp dụng/nháp, lương tháng 08) trong vùng private; nhận diện file đang có thay đổi để không ghi đè việc khác.
  - `before.json` (state-snapshot: cấu hình, KPI/lương 98, phiếu KPI, hash site 63/64, phiếu 148, việc mẫu, kỳ HR). `show.blade.php`, `routes/project_unified.php`, `UnifiedProjectController.php` đã có thay đổi chưa commit của phiên khác → sao lưu ở `baseline/`; so sau: chỉ thêm dòng (+29, +2, +44), không xóa dòng nào.
- [x] G0-03 Chọn route và cách kiểm tra quyền: ai được xem công trình cần đọc được bài; không đặt link dẫn 403, không mở rộng quyền nghiệp vụ. Ghi lựa chọn trong PROJECT.
  - `GET /du-an/{site}/huong-dan` (`projects-unified.guide`) trong nhóm route Công trình, cùng middleware và cùng `abortIfCannotView` với trang công trình. Không thêm quyền/role/menu. Ghi tại PROJECT mục Kết quả.

## G1 — Nút và trang hướng dẫn

- [x] G1-01 Thêm nút **Hướng dẫn sử dụng**, icon sách/dấu hỏi theo bộ icon sẵn, góc phải đầu nội dung phía trên tên công trình; kích thước và màu theo UI hiện có.
  - `pword-btn light` + `bi-book`, thanh riêng phía trên `.pword-hero`. Trình duyệt đo tọa độ: nút nằm trên khối tên công trình, căn phải, chữ hiện đủ ở 1366 px và 390 px (3 vai trò 8012 + 2 tài khoản local).
- [x] G1-02 Bấm mở trang riêng cùng tab; không modal, không thêm sidebar. Có quay lại đúng site/bước; kiểm tra trường hợp URL mở trực tiếp, tham số return không hợp lệ và site không được xem.
  - Link mang `step` hiện tại; khi bấm, JS thêm `anchor` từ `#` đang xem (regex). Server tự dựng URL quay lại từ danh sách bước cho phép (Tài chính chỉ khi được xem tài chính) + anchor hợp lệ; không nhận URL. Mở trực tiếp/tham số lạ → về tổng quan công trình. Người không xem được công trình: trang công trình và bài bị chặn như nhau (test 403/403; trình duyệt Sales ngoài phạm vi 404/404).
- [x] G1-03 Trang dùng layout/sidebar/thương hiệu hiện có; mục lục theo vai trò, sơ đồ ngắn, bước có ảnh. Không dựng header lớn che nội dung hoặc thêm nhiều khoảng trống.
  - `resources/views/projects-unified/guide.blade.php`: `layouts.app` + `ego-technical-guide.css` + partials step/lightbox/note/flow-diagram. Thẻ vai trò đánh dấu “Vai trò của bạn” và nhảy tới mục; mục lục dính bên phải trên desktop, ẩn dưới 992 px.

## G2 — Nội dung dễ hiểu

- [x] G2-01 Viết tổng quan các bước công trình và bảng ba nguồn KPI; nhấn mạnh % tiến độ công trình không phải % KPI.
  - Mục 2 (các bước + ghi chú vòng tròn tiến độ) và mục 3 (bảng 6 tiêu chí: tên/trọng số đọc từ bộ chấm của tháng hiện tại, cột nguồn; tiến độ 25% từ việc giao + báo cáo đầu ≤ 24 giờ; bảo hành 30% nguồn riêng; không cộng điểm cố định theo công trình).
- [x] G2-02 Kỹ thuật: hồ sơ → khai kết quả → nháp/gửi, thiếu dữ liệu báo gì, sửa sau trả bổ sung; mỗi bước có kết quả mong đợi và tình trạng tính KPI.
  - Mục 4, 4 bước (Tải lên → Lưu nháp/Gửi kiểm tra → trả bổ sung → Gửi duyệt), mỗi bước có “Kết quả” và nhãn Chưa tính KPI/Được xét KPI; phân biệt nút Lưu (ghi chú bước), Gửi kiểm tra, Gửi duyệt.
- [x] G2-03 Trưởng phòng: kiểm tra đúng bản, minh chứng, xác nhận nhóm/trả có lý do, không tự xác nhận việc mình làm.
  - Mục 5 + ghi chú “Xác nhận nhóm không phải duyệt nghiệm thu”; ngoại lệ Admin mô tả đúng chức năng hiện có.
- [x] G2-04 Admin: duyệt nghiệm thu cuối theo quyền hiện hành; giải thích KPI chỉ xét khi nguồn đủ điều kiện, không tự bảo đảm 100%.
  - Mục 6: Duyệt/Duyệt ngoại lệ (có hỏi lại), Trả hồ sơ cần sửa, cảnh báo số nhóm đã xác nhận; sau duyệt chỉ Admin sửa (trừ kỳ đã chốt).
- [x] G2-05 Giải thích nguồn cũ/bản mới, thay minh chứng làm mất hiệu lực xác nhận, kỳ khóa và xem checklist người/kỳ. D08 chưa khóa không được mô tả thành toàn hệ thống đã khóa mọi dữ liệu.
  - Mục 7 (checklist, kỳ = tháng duyệt, người được tính) và mục 9 (nguồn cũ, sửa sau xác nhận, kỳ đã chốt; ghi rõ khóa kỳ **chưa** áp dụng cho đề xuất vật tư).
- [x] G2-06 Kiểm mọi hướng dẫn/link liên quan: không còn câu 5 tiêu chí/trọng số cũ/tiến độ bàn giao 25% trong bài này; không nói v3 đang áp dụng nếu vẫn nháp; ví dụ điểm/tiền không gắn cứng vào dữ liệu mẫu thay đổi theo giờ.
  - Bài không dẫn sang bài KPI cũ; chỉ dẫn tới trang KPI thật (khi vai trò vào được). Câu cấu hình lấy từ bộ chấm lúc xem: local hiện “đang dùng v2 … tiền KPI theo bậc quy đổi”, nháp chỉ xem trước. Không có số điểm/tiền mẫu cố định; test chặn cụm “45 điểm”.

## G3 — Ảnh thật

- [x] G3-01 Fixture và server chụp ảnh dùng DB test cô lập, đúng ba vai trò, không thao tác demo local để tạo trạng thái. Kiểm disk/cache/session/mail trước thao tác ghi.
  - `ui-test/fixtures.php` (seed/docs/lock/cleanup, tag HDSD-TEST, site 994101, tài khoản `kphdsd.*@example.test`) trên `egosolar_test`; server 8012 `CACHE_STORE=array SESSION_DRIVER=cookie MAIL_MAILER=log QUEUE_CONNECTION=sync`. `capture.cjs` thao tác thật: thiếu hồ sơ → gửi kiểm tra → trả bổ sung HSE → xác nhận 3 nhóm → gửi duyệt → Admin duyệt → checklist KPI → khóa kỳ: 14/14 bước đạt.
- [x] G3-02 Có ít nhất 6 ảnh theo PROJECT, tên file ổn định; crop giao diện thực, số/khoanh vùng nếu có công cụ phù hợp, chú thích thao tác và kết quả. Không dùng hình dựng giả làm bằng chứng.
  - 8 ảnh `public/images/guides/projects/p01…p08`, cắt theo vùng nội dung, khung đỏ + số vẽ bằng lớp phủ tạm trên trang thật; ẩn tiện ích nổi “Hỏi cách dùng”.
- [x] G3-03 Soát ảnh public không có dữ liệu thật/token/mật khẩu/tab cá nhân; ảnh hiển thị thật, alt/chú thích, lazy load nếu phù hợp, bấm phóng to và đóng bằng nút/Escape.
  - Đã xem từng ảnh: chỉ tên/địa chỉ TEST. Trình duyệt: 8/8 ảnh tải được, có alt, không placeholder; bấm ảnh mở modal, Escape và nút × đều đóng (mọi vai trò, desktop + mobile).
- [x] G3-04 Ghi manifest ảnh (file, vai trò, trạng thái, bước, môi trường chụp) ở thư mục bằng chứng. Không để file ảnh public phụ thuộc đường dẫn Temp hoặc outputs.
  - `image-manifest.json` (file, vai trò, URL, chú thích, số đánh dấu, kích thước, sha256, kết quả soát).

## G4 — Kiểm tra và bàn giao

- [x] G4-01 Kiểm tra có ý nghĩa cho route/quyền, nút tới đúng bài và quay lại đúng site/bước; guest hoặc người không xem site không được lấy dữ liệu site qua bài. Tái sử dụng/mở rộng bộ guide test phù hợp, không viết hàng loạt test chỉ so nguyên văn câu chữ.
  - `tests/Feature/Technical/ProjectUsageGuideTest.php` 10 ca/55 assertion: nút giữ bước, kỹ thuật được giao mở bài + đủ ảnh + 2 nút quay lại đúng bước/anchor, step/anchor lạ, bước Tài chính không giữ khi không có quyền, vai trò trưởng phòng/Admin, người ngoài 403 như trang công trình, guest → login, công trình công ty khác bị chặn như trang công trình, không có POST.
- [x] G4-02 Thử trình duyệt desktop khoảng 1366px và mobile 390px với kỹ thuật/trưởng phòng/Admin: nút đọc được, mục lục nhảy đúng, ảnh đủ và phóng to, trang không tràn ngang, link nguồn không sai công trình.
  - `verify.cjs`: 8012 (3 vai trò × 1366/390 + người ngoài + guest + tham số lạ) **81/81**; local 8001 công trình 64, tài khoản mẫu kỹ thuật/trưởng phòng × 1366/390 **54/54**. Admin chỉ thử trên 8012 (không dùng tài khoản Admin thật ở local).
- [x] G4-03 Chạy lint file sửa và bộ hướng dẫn liên quan; chỉ mở rộng test khi lỗi/thay đổi mới đòi hỏi. Ghi test/lỗi hiện có trung thực, không sửa kỳ vọng để che sai quyền.
  - `php -l` controller/test đạt; Blade được render trong test. ProjectUsageGuide + TechnicalAcceptanceKpiFlow + TechnicalKpiSalaryCloseout 42/42; TechnicalGuidePagesTest 17/17. Một ca ban đầu đỏ do vai trò tạm `ky_thuat` của fixture (có `warehouse.view`) còn trong DB test → đổi người ngoài sang vai trò không quyền phạm vi rộng, không nới kỳ vọng 403.
- [x] G4-04 Đối chiếu local baseline sau: không đổi nguồn KPI, demo, cấu hình/lương. Dọn server/fixture/cache/file test đúng phạm vi; giữ ảnh public đã chọn và bằng chứng riêng.
  - `after.json` khác `before.json` chỉ ở thời điểm chụp. Đăng nhập xem làm app tự cập nhật `last_seen_at` của tài khoản 98 và tạo 10 file session local (driver file). `egosolar_test` 380 bảng/514 dòng trước = sau dọn = sau test. Server 8012 đã dừng; không file mới trong `storage/app`; 6 mục cache dùng chung mới đều do web 8001 ghi (dữ liệu local).
- [x] G4-05 Cập nhật PROJECT/PLAN/STATUS, giao URL web đã xác minh + ảnh trang hướng dẫn desktop/mobile + phần chưa kiểm chứng. Không commit/deploy; không tự duyệt dữ liệu local.

## Điều kiện nghiệm thu

Người mới mở công trình → bấm Hướng dẫn sử dụng → nhìn ảnh biết thao tác của mình → phân biệt khi nào dữ liệu được tính → quay lại đúng bước để làm. Không chấp nhận bài toàn chữ, ảnh placeholder, link 403, số điểm demo gắn cứng hoặc bài nói sai quy trình đang chạy.
