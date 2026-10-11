---
name: ego-project-usage-guide
description: Thêm nút Hướng dẫn sử dụng và trang hướng dẫn Công trình–Nghiệm thu–KPI có ảnh màn hình thật trong crm.egosolar.com.vn. Dùng cho đợt hướng dẫn công trình; không thay công thức KPI hay quy trình duyệt.
---

# Hướng dẫn sử dụng công trình

Đọc [PROJECT](../../../docs/projects/kpi-automation/project-usage-guide/PROJECT.md) và [PLAN](../../../docs/projects/kpi-automation/project-usage-guide/PLAN.md), làm trong `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`, web local 8001.

## Kết quả phải có

- Nút đúng chữ **Hướng dẫn sử dụng** ở góc phải đầu phần nội dung trang công trình, phía trên khối tên công trình như vùng người dùng chỉ trong ảnh. Bấm mở một trang hướng dẫn riêng trong cùng tab; có nút quay lại đúng công trình/bước đang xem. Mobile vẫn nhìn thấy chữ, không chỉ có icon.
- Hướng dẫn ngắn, theo vai trò, dùng ảnh chụp giao diện thật với dữ liệu TEST. Mỗi bước nói: ai làm → bấm đâu → nhập gì → thấy kết quả gì → đã tính KPI chưa. Một mục tổng quan công trình, trọng tâm là hồ sơ và kết quả Nghiệm thu.
- Nêu rõ % tiến độ công trình không phải % KPI; lưu hồ sơ khác lưu khai báo KPI; lưu nháp/gửi kiểm tra chưa phải xác nhận; trưởng phòng xác nhận khác Admin duyệt nghiệm thu; kỳ lương đã chốt khác bước nghiệm thu đã duyệt.

## Tận dụng source hiện có

Xem `resources/views/projects-unified/show.blade.php`, partials `workflow-word`, `workflow-kpi-review`, `workflow-kpi-legacy`; kiểm chứng tên nút/quyền với `AcceptanceKpiService` và controller workflow. Hệ thống hướng dẫn hiện có ở `technical.guides.*`, `TechnicalGuideRegistry`, `config/technical_guides.php`, `resources/views/technical/guides/` (layout, step, lightbox-image) và CSS `public/css/ego-technical-guide.css`. Tái sử dụng phần trình bày phù hợp; route kỹ thuật có giới hạn vai trò, nên không đặt nút dẫn 403 cho người có quyền xem Công trình. Chọn route hướng dẫn theo quyền Công trình nếu cần; không mở rộng quyền nghiệp vụ hoặc sửa các bài khác hàng loạt.

Các bài KPI cũ còn nội dung 5 tiêu chí/trọng số cũ. Không sao chép chúng làm nguồn quy tắc mới. Nếu bài mới dẫn sang bài KPI/báo cáo cũ, sửa đúng đoạn liên quan bị lỗi thời hoặc dẫn tới màn hình thật thay vì để hướng dẫn mâu thuẫn.

## Quy tắc nội dung và ảnh

- Source và trạng thái thực tế là căn cứ. Hiện v2 áp dụng, v3 nháp: không viết công thức theo tỷ lệ đã áp dụng cho tất cả kỳ. Các tiêu chí tháng 10 là 25/15/15/10/5/30; tiến độ 25% lấy việc được giao và lần gửi báo cáo đầu trong 24 giờ, không lấy vòng tròn tiến độ công trình.
- Ảnh phải chụp đúng vai trò trên giao diện thực, khoanh vùng/số thứ tự bằng công cụ phù hợp khi có sẵn; không dựng ảnh màn hình giả, không dùng ảnh người dùng có dữ liệu/tab trình duyệt cá nhân để đưa vào public. Cắt khung nội dung ứng dụng khi chụp. Mỗi ảnh có chú thích, alt, bấm phóng to; không giao placeholder hoặc ảnh lỗi.
- Ảnh đưa vào website là dữ liệu giả đã kiểm tra, lưu dưới `public/images/guides/projects/` hoặc vị trí assets phù hợp repo; bằng chứng/log thô giữ trong `outputs/claude-kpi-agent/project-usage-guide-20261007/` đã được gitignore theo thư mục cha. Không đưa mật khẩu/token/lương/khách hàng thật vào assets.
- Nếu nguồn cũ đang được dùng nhưng form mới chưa nhập, hướng dẫn cách đọc nguồn cũ và việc khai lại sẽ cần gửi/xác nhận mới; không nói form trống luôn đồng nghĩa điểm đang có sai.

## Giới hạn thực thi

Chỉ thêm hướng dẫn và nút, không thêm menu nghiệp vụ, sửa công thức/DB/schema, tự duyệt v3/nghiệm thu/lương local, sửa công trình 63/64, việc mẫu hoặc tài khoản. Ảnh các trạng thái cần thao tác dùng fixture DB test `egosolar_test` trong môi trường cô lập; local `crm_egosolar_test` chỉ xem. Giữ guard Tests/TestCase, dùng PHP `C:/php83/php.exe`; cô lập cache/session/mail/storage khi chạy server test. Không reset/clean, commit/push/deploy hoặc gửi thông báo thật.

Kiểm tra link, quyền, quay lại đúng bước, ảnh/phóng to, desktop/mobile và trạng thái DB không đổi. Báo phần nào thực sự thử, không ghi đạt chỉ vì có source. Cập nhật kết quả trong PROJECT/PLAN và STATUS; giao link web hoạt động và ảnh trang hướng dẫn cho người dùng xem.
