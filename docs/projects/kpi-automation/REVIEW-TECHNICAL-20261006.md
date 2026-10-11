# Kiểm tra và sửa 4 mục kỹ thuật — 06/10/2026

## Kết quả

1. **Trang bảo hành:** route thật `projects-unified.maintenance.warranty-sla.store` đã được phiên Claude đăng ký. Kiểm tra trang chi tiết sau migration trả HTTP 200. Nút hoàn tất thật ở `/du-an` nay ghi `execution_finished_at` đúng thời điểm thao tác; không điền lại mốc cho phiếu lịch sử. Mở lại phiếu chưa xác nhận xóa mốc thực hiện để chờ hoàn tất mới; phiếu đã xác nhận KPI bị chặn mở lại/sửa mốc ở cả hai dịch vụ.
2. **Chất lượng:** cần kết quả có/không phàn nàn và người/ngày đánh giá tại nghiệm thu. Bỏ trống, null hoặc chỉ có giá trị chưa xác nhận đều báo thiếu; một công trình thiếu cũng làm tiêu chí chưa đủ, không bỏ nó ra rồi tính 100% từ công trình còn lại. Nguồn nghiệm thu cũ và minh chứng cũ chưa có xác nhận phản ánh tiếp tục báo thiếu, không suy đạt từ việc không bị trả lại.
3. **Quyền và cấu hình:** kỹ sư được phân công ghi phản hồi hiện tại; chỉ quản lý ghi giờ phản hồi thực tế kèm lý do lưu audit. Người thực hiện không tự xác nhận, giữ ngoại lệ Admin của hệ thống. Chỉ xác nhận khi phiếu đã hoàn tất và đủ mốc đúng thứ tự. Chặn ghi/chấm KPI ngoài công ty được cấu hình; phiếu thiếu company chỉ dùng khi xác định được từ công trình. Các thao tác SLA khóa dòng trong transaction để không ghi đè mốc khi có thao tác đồng thời.
4. **Bộ tháng 10:** đã sửa controller và form cài đặt còn ép bảo hành về 0/draft. Bộ từ 10/2026 được lưu đủ 25/15/15/10/5/30; từ chối lưu/duyệt bản thiếu hoặc sai trọng số. Kỹ sư không được lưu cấu hình; quản lý kỹ thuật không được duyệt; quyền duyệt lãnh đạo/Admin hiện có được giữ. Kỳ 09 vẫn chọn bản cũ.

## Chạy thử và đối chiếu

Dữ liệu giả trong `egosolar_test`, tự rollback. Ca tháng 10 gồm **một công trình** nghiệm thu đúng hạn, chất lượng được quản lý xác nhận và **một phiếu bảo hành** đi qua các route nhập mốc → phản hồi → hoàn tất → xác nhận. Ba tiêu chí khảo sát/HSE/EVN-App dùng số liệu nhập giả lập 1/1 riêng, không tuyên bố đã tự động lấy nguồn cho chúng.

| Trường hợp | Tính tay | Kết quả kiểm thử |
|---|---|---|
| Phản hồi đúng 5 phút, hoàn tất đúng 24 giờ, không khiếu nại; 6 tiêu chí đạt | 25 + 15 + 15 + 10 + 5 + 30 = 100 | 100 điểm |
| Phản hồi trễ 1 giây, các điều kiện khác giữ nguyên | 100 − 30 = 70 | 70 điểm |
| Chưa xác nhận phản ánh tại nghiệm thu | Chưa có căn cứ cho 15% chất lượng | Không ra tổng KPI |
| Có một phiếu bảo hành đạt và một phiếu thiếu mốc hoàn tất | Chưa đủ nguồn bảo hành | Không chấm 30% từ một phiếu còn lại |

## Bằng chứng kiểm tra

- `TechnicalKpiSourceFlowTest` + `TechnicalKpiMonthlyInputTest` + `MaintenanceCompleteWithoutFullEvidenceTest`: **80/80 test, 397 assertion đạt** trên PHP 8.3 / `egosolar_test`. Log lần chạy cuối: `outputs/claude-kpi-agent/review-fix-20261006/tests-final.txt` (gitignore).
- Kiểm tra cú pháp các PHP thay đổi đạt; trang bảo hành và cài đặt được render qua feature test; local `/login` trả HTTP 200. Chưa chụp UI trong phiên đăng nhập thật.
- Lần chạy mở rộng đầu gặp thiếu bộ nhớ hệ thống; chạy kiểm tra từng lỗi, sửa hai kỳ vọng cũ và chạy lại bộ 80 test hoàn tất thành công. Không thay guard DB test.
- DB local được kiểm tra chỉ đọc: cấu hình v2 ngày 01/10/2026 vẫn **draft, chưa duyệt**, đủ 6 trọng số. Không chạy thêm migration, không tạo dữ liệu giả trên DB local.
- Snapshot lương 08/2026 vẫn draft, chưa duyệt, 12 dòng. SHA-256 dữ liệu dòng bằng mốc phiên Claude trước review: `b5a79e9aabebdff0ddf17c5102ca5d61c93172575cf7fdaa2326ee3bf39c4da8`.

## Còn cần hoàn thiện

- D12 vẫn chưa chốt: ai ghi giờ khách báo, mốc bắt đầu, giờ liên tục/giờ làm việc, thời gian chờ, loại phiếu và phạm vi khiếu nại. Các ca kiểm thử trên theo cách đọc tạm hiện có; không phải quyết định áp dụng tính lương.
- Chưa mở luồng thu hồi xác nhận KPI bảo hành; phiếu đã xác nhận bị chặn mở lại để tránh thay đổi điểm âm thầm.
- Form nghiệm thu cũ và minh chứng cũ chưa có xác nhận phản ánh riêng; tiếp tục báo thiếu. Cần nối nguồn xác nhận trước khi tự động chấm các hồ sơ đó.
- Nguồn tự động khảo sát/vật tư theo mã, HSE và EVN/App vẫn là phần tiếp theo P2-03/P2-04. Nguồn EVN/App cũ còn suy từ link giám sát, chưa sửa trong 4 mục này.
- DB local chưa có mốc SLA thật. Sau khi quản lý chốt quy tắc, nhập nguồn thật, đối chiếu và duyệt cấu hình theo quy trình trước khi nối tính lương.

Danh sách PHP/view/test sửa được ghi trong STATUS. Baseline trước review giữ riêng trong thư mục gitignore `outputs/claude-kpi-agent/review-fix-20261006/baseline`.
