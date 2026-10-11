# Cập nhật KPI kỹ thuật — áp dụng từ 10/2026

Nguồn mới do người dùng gửi ngày 06/10/2026:
`C:/Users/PC/Downloads/BẢNG TIÊU CHÍ ĐÁNH GIÁ KPI - KỸ SƯ DÂN DỤNG.docx`.

SHA-256: `c1ea3850e4b1cfb5b7167287d3d756ec9612d94a4f727eec4dc57d3e7f7087e7`.

Người dùng xác nhận trong chat: **áp dụng từ tháng 10/2026**. Ngày hiệu lực của bộ mới là 01/10/2026. Bản cùng tên trong `E:/KPIs` là nguồn cũ để đối chiếu; không còn dùng làm chuẩn kỹ thuật cho kỳ từ 10/2026.

## Thay đổi đã xác định

| Mã/code | Tiêu chí | Trọng số cũ | Trọng số mới |
|---|---|---:|---:|
| KS01 / timeline | Tiến độ | 30% | 25% |
| KS02 / quality | Chất lượng/thẩm mỹ | 25% | 15% |
| KS03 / survey | Khảo sát/khối lượng | 15% | 15% |
| KS04 / hse | An toàn/vệ sinh | 15% | 10% |
| KS05 / evn_app | EVN/App | 15% | 5% |
| KS06 / warranty | Thời gian xử lý bảo hành | Chưa có | 30% |
| | Tổng | 100% trên 5 mục | 100% trên 6 mục |

KS06 đã sửa nội dung: 100% khách hàng được hỗ trợ bảo hành đúng thời gian và không có khiếu nại; phản hồi thông tin trong **5 phút**, hoàn thành bảo hành trong **24 giờ**. Không còn dùng nội dung vật tư tiêu hao cho mục này.

Điều kiện tiến độ/chất lượng/khảo sát/HSE/EVN-App và đoạn quy đổi tiền thưởng không thay đổi. D03 (cách chọn 110–120% ở mức từ 100%) vẫn chưa được xác nhận; tài liệu mới không tự giải quyết điểm này.

## Yêu cầu triển khai cho Claude

1. Đọc lại skill, PLAN, DATA-MAP và DECISIONS. Đây là bổ sung từ người dùng sau khi phiên đầu đang chạy; ưu tiên nội dung cập nhật này khi khác với hướng dẫn kỹ thuật cũ.
2. Tạo phiên bản cấu hình kỹ thuật mới có hiệu lực 01/10/2026, đủ 6 trọng số ở trên. Giữ phiên bản cũ và kỳ đã duyệt; không ghi đè cấu hình tháng 09 hoặc dùng bộ mới cho tháng 08.
3. Không tiếp tục loại bảo hành khỏi tổng KPI hoặc tính đủ 100% từ 5 mục cũ. Nếu chưa có nguồn bảo hành/thiếu mốc/xác nhận, tổng kỳ mới phải báo chưa đủ theo quy tắc thiếu dữ liệu; không đổi 30% thành 0 điểm hoặc tự N/A.
4. Hoàn thiện nguồn bảo hành từ quy trình đang có: ID kỹ sư chịu trách nhiệm, tiếp nhận thông tin khách hàng, phản hồi đầu tiên, hạn hoàn tất, hoàn tất/đóng có xác nhận, kiểm tra khiếu nại và người xác nhận. Xác minh field thật và bổ sung trường thiếu; không suy mốc phản hồi từ trạng thái hoặc `updated_at`.
5. Điều kiện đạt cần đủ cả phản hồi đúng 5 phút, hoàn thành đúng 24 giờ và không có khiếu nại đã được kiểm tra. Các mốc SLA và phạm vi khiếu nại còn phải định nghĩa rõ trong D12; triển khai nguồn và test độc lập trước, không tự đặt thời gian chờ được loại trừ.
6. Kiểm thử chọn phiên bản tháng 09/tháng 10, tổng trọng số 100%, thiếu dữ liệu bảo hành, trễ phản hồi, trễ hoàn tất, có khiếu nại và đúng biên 5 phút/24 giờ theo định nghĩa được xác nhận.
7. Bảng lương Excel 08/2026 tiếp tục giữ nguyên. Không tự phê duyệt kỳ KPI/lương hoặc chính sách trả tiền.

## Hạng mục cần bổ sung khi nguồn được xác nhận

Ứng viên hợp đồng dữ liệu bảo hành: `responsible_user_id`, `customer_received_at`, `first_responded_at`, `resolution_due_at`, `resolved_at`, `closed_at`, kết quả kiểm tra khiếu nại nullable, `verified_by`, `verified_at` và minh chứng. Đây là ý nghĩa cần có, không phải khẳng định các tên field đã tồn tại.

Không tự gán “không khiếu nại” chỉ vì bảng khiếu nại không có dòng. Không tự coi ngày đóng phiếu là thời điểm sửa xong nếu hai hành động có ý nghĩa khác nhau.
