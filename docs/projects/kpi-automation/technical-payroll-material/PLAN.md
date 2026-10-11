# Plan và checklist S0–S4

**08/10/2026:** D16 cho phép luồng manager nhập tay tạm thời và đã triển khai/kiểm tra. [Bằng chứng và cách dùng](../MANUAL-MATERIAL-20261008.md). Không đánh dấu S3 tự động hoàn tất; khi tiếp tục phải giữ nháp/xác nhận, minh chứng và khóa kỳ của D16.

- [ ] S0-01 Đọc source/route thực, baseline local private, schema và trạng thái v3; xác định file đang được phiên khác sửa.
- [ ] S0-02 Chốt cách tổng hợp D08 từ câu trả lời người dùng; ghi DECISIONS với nguồn/hiệu lực. Chưa trả lời thì không tự chọn, vẫn làm nguồn/khóa và S1–S2.

## S1 — Đồng bộ phiếu lương

- [ ] S1-01 Tái hiện cùng người/kỳ: trang KPI và tạo/sửa phiếu lương cũ ra khác nguồn/tiền.
- [ ] S1-02 Nối create/update/recalculate/approve còn truy cập được vào bộ chấm+cấu hình hiệu lực dùng chung; xử lý route trùng, giữ link lịch sử.
- [ ] S1-03 Thiếu nguồn/cấu hình không lưu hoặc duyệt số 0 thay thiếu; giới hạn quyền và phạm vi người/kỳ ở backend.
- [ ] S1-04 Lưu snapshot truy vết cấu hình/nguồn/rounding. Phiếu đã duyệt không sửa qua mọi route, không đổi người/tháng để vượt khóa; kỳ lịch sử/Excel bất biến.
- [ ] S1-05 UI preview dùng cùng số server, không để bộ JavaScript cũ hứa một số khác tiền lưu; giải thích phiếu nháp cũ cần xem trước khi tính lại.

## S2 — Không làm tròn tiền KPI và đồng bộ HR (D15)

- [ ] S2-01 Chung hệ số, giữ nguyên tiền kỹ thuật/HR theo D15; chuẩn bị `round_rule=none` chỉ ở bản nháp. Nhánh none không `round()`/ceil/cắt lẻ. HR template khác, thuế/BH và snapshot lịch sử giữ nguyên.
- [ ] S2-02 Đối chiếu v2 lịch sử/bản mới; thiếu/0/87,5/100/>100 theo cờ; đủ công/nửa công; quỹ lẻ và kết quả thập phân. Bản mới 87,5% đủ công: KPI 3.937.500, tổng 14.437.500; nửa công KPI 1.968.750. Kiểm tiền lưu và hiển thị không tự làm tròn, dòng cộng ra tổng đúng.
- [ ] S2-03 HR lấy đúng nguồn kỹ thuật; không override thiếu nguồn bằng hệ số tay ngoài quyền/ngoại lệ rõ ràng. Không tự đổi hồ sơ thật để ép khớp.
- [ ] S2-04 Thử trình duyệt HR desktop/mobile đầy đủ với fixture test: nhập hồ sơ, ngày công, tạo/xem/tính lại/duyệt, quyền và khóa. Không dùng Admin local để thử.

## S3 — D08

- [ ] S3-01 Bản đồ dữ liệu baseline/đề xuất/xuất/thực nhận/thu hồi/mapping mã-đơn vị; rà service ghi, trạng thái thực và liên kết. Nếu thiếu trường thì bổ sung tối thiểu đúng module.
- [ ] S3-02 Bảng đối chiếu từng mã/đơn vị và minh chứng; thiếu dữ liệu/mapping/baseline không tự đạt, không cộng đơn vị khác nhau. Có người xác nhận và lịch sử hiệu lực.
- [ ] S3-03 Chấm theo quyết định đã chốt; `<3%` theo giá trị gốc, 3% fail, hiển thị rounding không thay kết quả. Chưa chốt tổng hợp thì đánh giá pending, không âm thầm lựa chọn.
- [ ] S3-04 KPI survey và checklist đọc cùng kết quả, số nhập tay/legacy không bỏ qua đối chiếu mới; sửa nguồn làm mất xác nhận tương ứng, không sống lại khi đổi về.
- [ ] S3-05 Khóa mọi đường ảnh hưởng vật tư theo phiếu kỹ thuật hoặc HR live đã duyệt: trực tiếp POST, import/bulk, baseline, mapping, xuất/thu hồi, trạng thái, file; trước ghi DB/file/ledger. Chứng từ nhiều site kiểm toàn bộ site ảnh hưởng.
- [ ] S3-06 Test hỗn hợp đơn vị, SKU ngoài dự toán, baseline 0, thiếu mapping, phiếu chưa xuất, thu hồi chưa nhập, hoàn dư/âm, nhiều phiếu, đếm trùng, biên 2,999/3/3,001%, sửa sau xác nhận và sau khóa kể cả Admin.

## S4 — Nghiệm thu và bàn giao duyệt

- [ ] S4-01 Chạy một fixture xuyên suốt khảo sát/vật tư/nghiệm thu/việc/bảo hành → KPI → phiếu kỹ thuật → HR; đối chiếu tay toàn bộ tiêu chí và tiền. Không dùng số nhập tay để bù tiêu chí cần chứng minh tự động.
- [ ] S4-02 Check phù hợp phạm vi, phân loại lỗi bằng baseline; chứng minh local cũ không đổi, dọn server/fixture đúng phạm vi.
- [ ] S4-03 Preview tác động v3 và checklist sẵn sàng. Chỉ ghi sẵn sàng khi S1–S3 đủ bằng chứng và D08 đã chốt. Giao link người dùng duyệt, không tự approve.
- [ ] S4-04 Cập nhật PROJECT/PLAN/STATUS và hướng dẫn bị ảnh hưởng; báo điểm chưa xong và quyết định pending. Chưa áp dụng v3 phải nói rõ đang dùng v2 nếu còn đúng hiện trạng.
