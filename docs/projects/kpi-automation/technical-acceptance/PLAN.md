# Kế hoạch triển khai luồng Nghiệm thu

## A1 — Xác minh và thiết kế dữ liệu

1. Đọc PROJECT/skill/DECISIONS và chụp baseline các file cần sửa. Kiểm tra schema, route đang nạp, quyền/phạm vi công ty và track duyệt thật.
2. Lập bảng ngắn “đã có / thiếu / sẽ sửa” theo chất lượng, HSE, EVN/App, chứng cứ, trạng thái gửi và xác nhận. Tái sử dụng JSON/hồ sơ/lịch sử đang có; thêm bảng/cột chỉ khi thiết kế cần.
3. Chọn hợp đồng dữ liệu bảo toàn lịch sử: khai báo nháp, lần gửi, phiên bản nội dung, kết quả kiểm tra theo nhóm/mục, ID/ngày xác nhận và tham chiếu minh chứng. Tên field mới do Claude đề xuất sau khi đọc schema; không xem chúng là field đã có.

Yêu cầu bắt buộc: cập nhật một nhóm không xóa hoặc tự đặt false nhóm khác; xóa lựa chọn thật phải lưu được null (không bị `array_filter` bỏ đi); sửa nháp không tạo `kpi_reviewed_at`; cờ/ký xác nhận client gửi không được tin. Hiện `saveData()` đặt nhiều boolean qua `request->boolean()` dù form không gửi — tránh làm thay đổi `requires_grid_connection` hoặc các cờ ngoài bước nghiệm thu.

Đầu ra: bảng dữ liệu và thiết kế ngắn ghi trong PROJECT; tiếp tục triển khai sau khi các lựa chọn kỹ thuật thường lệ đã được giải quyết. Chỉ ghi quyết định nghiệp vụ mới nếu thật sự phát sinh.

## A2 — Kỹ thuật nhập và gửi ngay tại Nghiệm thu

- Dùng người được giao còn hiệu lực/phạm vi công trình. Lưu nháp, sửa nháp, gửi kiểm tra; hiển thị phần thiếu và người/ngày gửi.
- Chất lượng: có/không phản ánh khách hàng, nội dung nếu có và hồ sơ liên quan. “Đạt lần đầu” lấy từ lịch sử trả lại, không do người nhập tự chấm.
- HSE: đủ 5 mục ở `ACCEPTANCE_HSE_CHECKS`, mỗi mục chưa ghi nhận/đạt/không đạt; ghi chú/minh chứng được liên kết với hồ sơ công trình.
- EVN/App: trạng thái cần đấu nối lấy từ hồ sơ pháp lý đã ghi nhận, kết quả đóng điện thử nếu cần, cài/bàn giao App và hồ sơ liên quan. Vắng yêu cầu đấu nối không được tự hiểu là không cần. Link monitoring không tự thay xác nhận.
- Tái sử dụng loại hồ sơ và số file bắt buộc trong cấu hình hiện tại. Không tự đặt thêm mức điểm, số ảnh hoặc thời hạn xác nhận nghiệp vụ.

Đầu ra: kỹ thuật thao tác được toàn bộ nhập/gửi trên màn hình existing; dữ liệu mới chưa xác nhận chưa được tính đạt.

## A3 — Trưởng phòng kiểm tra, xác nhận hoặc trả bổ sung

- Xem khai báo và mở minh chứng. Xác nhận kết quả theo nhóm/mục đủ dữ liệu; ghi người/ngày kiểm tra ở server. Thiếu còn hiện “Chưa đủ dữ liệu”, không đóng dấu toàn bộ từ một nút Lưu.
- Trả bổ sung có lý do, giữ bản gửi/lịch sử và thông tin đã kiểm tra. Sau sửa hoặc thay/xóa file, bản mới phải được kiểm tra lại; kiểm tra cạnh tranh cập nhật để không xác nhận nhầm phiên bản.
- Kỹ thuật không tự duyệt; người xác nhận không thuộc nhóm thực hiện kết quả đó. Kiểm tra cả POST giả mạo, user_id khác, ngoài công ty và bước khóa.
- Giữ track duyệt nghiệm thu cuối của Admin. Bước `approved`, mốc bàn giao và kỳ tính không do nút “Xác nhận kết quả KPI” tự tạo.

Đầu ra: phân biệt được “Đã nhập”, “Chờ kiểm tra”, “Cần bổ sung”, “Đã xác nhận” với trạng thái workflow nghiệm thu.

## A4 — Nối nguồn vào KPI và ghi chú cho người dùng

- `workflowEvidenceRows()` đọc bản nội dung/minh chứng đã xác nhận của nghiệm thu hợp lệ, không lấy bản nháp hoặc link App làm đạt. Ưu tiên nguồn workflow theo cơ chế chống trùng hiện tại.
- KS02 phải có kiểm tra phản ánh và lấy lịch sử trả lại thật. KS04 chỉ đạt khi 5 điều kiện có kết quả đã xác nhận đầy đủ. KS05 cần đủ điều kiện EVN/App đã xác nhận theo trường cần đấu nối.
- Đủ bằng chứng không đạt → điểm 0 theo quy tắc; thiếu kết quả/xác nhận → báo thiếu. Không tự N/A hoặc tái phân bổ. Tháng 09/10 dùng cấu hình tương ứng, không đổi kỳ khóa hoặc snapshot.
- Hiển thị ghi chú tại Nghiệm thu: tiêu chí đang chờ gì / đã nhận bao nhiêu điểm / chưa đạt vì sao. Dùng cùng kết quả bộ chấm, không tính công thức riêng trong view; không giới hạn ghi chú chỉ ở mã TEST.
- Trang KPI xem được công trình, kết quả, người/ngày xác nhận, trạng thái và nguồn; các nguồn cũ thiếu xác nhận vẫn báo thiếu. Không tự hợp thức hóa dữ liệu cũ.

Vật tư tiếp tục hiển thị nguồn hiện tại và cảnh báo nhập tay/chưa đối chiếu kho; chưa đổi thuật toán. Bảo hành tiếp tục dùng luồng đã kiểm tra; tổng có thể vẫn thiếu nếu hai phần này chưa đủ.

## A5 — Kiểm tra và bàn giao

Feature test chỉ trên `egosolar_test`, có rollback/cleanup fixture. Các ca bắt buộc:

1. Nháp/gửi nhưng chưa kiểm tra không nhận điểm; dữ liệu thiếu khác không đạt.
2. Người được giao lưu/gửi; trưởng phòng hợp lệ xác nhận; kỹ thuật/ngoài công trình/ngoài công ty không giả mạo được.
3. Lưu một nhóm không ghi đè nhóm khác/cờ pháp lý; bỏ lựa chọn không giữ nhầm kết quả cũ. Client không tự đặt người/ngày xác nhận.
4. Chất lượng chưa kiểm tra phản ánh → thiếu; có phản ánh hoặc lịch sử trả lại → không đạt theo quy tắc; chưa có phàn nàn trong DB không suy không phàn nàn.
5. HSE thiếu một mục/xác nhận → thiếu; một mục không đạt đã xác nhận → không đạt; đủ 5 đạt đã xác nhận → 10 điểm tháng 10.
6. Link/App account đơn lẻ → thiếu; cần đấu nối nhưng thiếu EVN → thiếu; đủ điều kiện đã xác nhận → 5 điểm tháng 10. Thiếu yêu cầu đấu nối → thiếu.
7. Sửa sau xác nhận/thay-xóa file/trả bổ sung phải vô hiệu xác nhận của bản mới, giữ lịch sử; bản đã khóa không sửa được qua UI/API. Người xác nhận không xác nhận bản bị thay trong lúc kiểm tra.
8. Track Admin vẫn giữ; xác nhận của trưởng phòng không tự duyệt nghiệm thu. Đúng kỳ, không đếm trùng nguồn, không thay kết quả tháng cũ/đã khóa.

Chạy lint, các test mới và 3 bộ hồi quy `TechnicalKpiSourceFlowTest`, `TechnicalKpiMonthlyInputTest`, `MaintenanceCompleteWithoutFullEvidenceTest`; sửa lỗi liên quan trước bàn giao. Nếu test cũ phụ thuộc hành vi link App mặc định, cập nhật kỳ vọng theo yêu cầu và nêu lý do, không sửa test để che lỗi.

Thử UI với tài khoản kỹ thuật/trưởng phòng và tài khoản duyệt hiện có trong **môi trường DB test cô lập**. Có thể dựng server riêng cổng khác và cấu hình process riêng; không đổi `.env`, không chiếm cổng local 8001, không chạy fixture trong `crm_egosolar_test`. Không mở lại mẫu đã đóng. Ghi ảnh trước/sau và cách nhập để người dùng xem trên local sau triển khai; nếu không có công cụ trình duyệt, ghi rõ chưa test UI, không dùng request HTTP thay bằng chứng thao tác UI.

Cuối đợt cập nhật CHECKLIST/PROJECT và STATUS dự án chính. Không tự đổi estimate 70% thành 100% từ một ca mẫu. Đợt kế tiếp: khảo sát → mã vật tư/đơn vị → xuất/thu hồi kho (D08).
