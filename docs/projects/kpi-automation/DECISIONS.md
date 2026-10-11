# Các quy tắc chưa được xác nhận

**Cập nhật 08/10/2026:** D16 ở cuối tài liệu cho phép tạm thời manager nhập tay vật tư có minh chứng/xác nhận. Cách tổng hợp tự động D08 vẫn chưa chốt.

Các mục ban đầu chưa xác nhận. Cập nhật ngày 06/10: người dùng gửi bản kỹ thuật mới và xác nhận áp dụng từ **10/2026**; D04 về trọng số/nội dung đã được giải quyết như ghi dưới đây. Các mức trả tiền và ngoại lệ còn mở không tự coi được duyệt. Agent tiếp tục phần cấu hình/nguồn độc lập.

| Mã | Điểm cần xác nhận | Hiện trạng/khác biệt | Phần có thể làm ngay |
|---|---|---|---|
| D01 | Trọng số 9 KPI HCNS | Word không có; source đang chia đều tạm | Thu thập và tổng hợp từng tiêu chí; biểu thị trọng số tạm/chưa xác nhận |
| D02 | Kho trả theo bậc hay tỷ lệ; biên 70%, >100% và trần | Word: 90–100% hưởng đủ nhưng ví dụ 93% nhân 93%; có khoảng hở 70% và tỷ lệ lẻ | Xây cấu hình bậc/tỷ lệ và test riêng; không duyệt chính sách mặc định |
| D03 | Kỹ thuật: mức ở đúng 100%, điều kiện 110–120% và trần | Word yêu cầu thưởng; DB hiện hưởng 100%, không cho vượt | Hoàn thiện 6 tiêu chí của bộ mới từ 10/2026; tách cấu hình payout có hiệu lực và trạng thái xác nhận |
| D04 | **Đã xác nhận bộ mới từ 10/2026:** bảo hành và tổng trọng số | Trọng số 25/15/15/10/5/30; bảo hành phản hồi 5 phút, hoàn tất 24 giờ, không khiếu nại; bản mới thay nội dung vật tư cũ | Tạo phiên bản hiệu lực 01/10/2026, giữ bộ cũ trước đó; nối nguồn bảo hành; không loại 30% khi thiếu dữ liệu |
| D05 | Không phát sinh, chưa có dữ liệu, người xác nhận N/A và tái phân bổ | Các module hiện xử lý khác nhau | Tách trạng thái, lý do, xác nhận; không auto-N/A khi không tìm thấy dữ liệu |
| D06 | Lịch làm việc, giờ liên tục, ngày lễ và thời gian chờ của SLA | HCNS mức 2 là giờ làm việc; service sự cố hiện cộng giờ liên tục; phân loại 30 phút cần đo riêng | Lưu mốc và loại SLA; chuẩn bị bộ tính theo lịch; không tự loại thời gian chờ |
| D07 | Kỳ và người chịu trách nhiệm theo tiêu chí | Việc đến hạn/hoàn tất khác nhau, liên bộ phận và qua tháng | Lưu phân công/mốc; nguồn mở quá hạn không mất; cấu hình phạm vi theo tiêu chí |
| D08 | Sai lệch vật tư, cách gộp từng mã/đơn vị và ngưỡng | Word ghi `<3%`; có nguồn đề xuất/thu hồi và cách cộng lượng khác nhau | Đối chiếu từng mã/đơn vị; báo thiếu baseline; không cộng đơn vị khác nhau |
| D09 | Gộp tài sản, VPP và sự cố vào HC06 | Source chỉ có thống kê sự cố tham khảo; Word còn yêu cầu cấp phát và kiểm kê | Tạo ba nhóm nguồn rõ ràng; không lấy thống kê sự cố thay toàn tiêu chí |
| D10 | Cảnh báo trước 15 ngày so với sự kiện nào | Word có tồn >30 ngày nhưng chưa xác định mốc tham chiếu 15 ngày | Lưu lô, tuổi tồn, nhật ký cảnh báo và người nhận; chưa tính đạt tự động |
| D11 | Thang hài lòng và tỷ lệ phản hồi tối thiểu | Word chỉ đặt ≥80%; chưa có cách đổi thang/số người phản hồi | Xây khảo sát và lưu phản hồi, giữ công thức pending khi chưa chốt |
| D12 | Mốc bắt đầu 5 phút/24 giờ, cách xác nhận hoàn tất/khiếu nại và phạm vi SLA bảo hành | Bản mới chốt thời lượng nhưng chưa nói mốc khách báo/nhận phiếu/giao kỹ sư, giờ làm việc hay liên tục, thời gian chờ, khiếu nại nào và cách tính khi không phát sinh | Lưu đủ mốc và kết quả xác nhận; test theo hợp đồng được xác nhận; không tự trừ thời gian chờ, suy không khiếu nại hoặc tự N/A |

## Cách đọc mặc định đang dùng trong source (chưa phải quyết định)

Cấu hình tại `config/technical_kpi_standard.php`; đổi được khi có xác nhận, không cần sửa code chấm.

| Mã | Cách đọc mặc định | Câu hỏi cần chốt |
|---|---|---|
| D07 | KS01: công trình quá hạn chưa bàn giao được liệt kê ("Quá hạn còn mở") nhưng chưa vào mẫu số (`open_overdue_policy = list_only`, đúng chữ "/ Tổng số hệ thống bàn giao"); khi bàn giao trễ thì tính trễ ở tháng bàn giao. Kỹ sư chịu KS01 khi được giao bước Thi công/Nghiệm thu hoặc là kỹ sư phụ trách chính | Có tính việc quá hạn còn mở là trễ trong tháng đến hạn (`count_as_late`)? Hạn KPI ưu tiên hạn bước Nghiệm thu hay hạn hợp đồng? |
| D12 | KS06: loại phiếu `incident`, `warranty_inverter`; mốc bắt đầu 5 phút/24 giờ = giờ khách báo; tính liên tục, không trừ thời gian chờ; biên đúng 5:00 / 24:00 là đạt; kỳ theo tháng khách báo; người chịu trách nhiệm = trưởng nhóm phân công; hoàn tất = "Hoàn tất thực hiện"; khiếu nại do Trưởng phòng không thuộc nhóm thực hiện xác nhận | Mốc bắt đầu (khách báo / tạo phiếu / giao kỹ sư)? Giờ làm việc hay liên tục, ngày lễ? Loại phiếu nào là bảo hành? Hoàn tất là xong hiện trường hay đóng phiếu? Khiếu nại trong bao lâu sau hoàn tất? Tháng không có phiếu xử lý thế nào (D05)? |
| D03 | Bản nháp v2 giữ cách trả tiền của v1 (không vượt 100%) | Mức 110–120% |

## Cách ghi nhận quyết định

### D13 — Tiến độ kỹ thuật từ công việc được giao (người dùng xác nhận 07/10/2026)

- Người dùng xác nhận: hạn nộp báo cáo 24 giờ; chọn toàn bộ 25 điểm Tiến độ chia đều cho các việc trưởng phòng giao, không chia thêm phần bàn giao công trình. Câu hỏi đã nêu rõ mốc 24 giờ kể từ lúc giao và được người dùng chọn.
- KS01 từ kỳ 10/2026 đọc việc `is_manager_assigned`, giờ giao `assigned_at` và lần nộp đầu của báo cáo gắn đúng `plan_item_id`. Đúng biên 24 giờ nhận điểm; nháp, chưa nộp quá hạn hoặc nộp muộn mất phần điểm của việc. Không trừ thêm một khoản riêng; số nhập tay tháng không thay nguồn KS01 mới.
- Cách xử lý kỳ đang triển khai: kỳ theo tháng hạn 24 giờ, tỷ lệ = việc nộp đúng hạn / việc đã đến hạn tại thời điểm đánh giá (cuối kỳ hoặc hiện tại). Việc còn hạn hiện chờ; việc hủy loại trừ, thiếu giờ giao/nộp sai thứ tự báo thiếu dữ liệu, không tự đạt. Ngày thực hiện và % tự khai không thay mốc giao/nộp. Kỳ không có việc đủ điều kiện chưa tự nhận 25 điểm.
- Các kỳ trước 10/2026 giữ nguồn bàn giao công trình; bảng lương đã duyệt giữ snapshot. Không duyệt cấu hình tiền lương, không đổi KS06 bảo hành hoặc các quyết định D03/D12.

Mỗi quyết định được cập nhật khi có xác nhận thật: mã, lựa chọn, người/vai trò xác nhận, ngày hiệu lực, phạm vi phòng ban, nguồn xác nhận và phiên bản cấu hình. Không tự điền người duyệt hoặc trạng thái “đã chốt”.

Khi thiếu quyết định, dòng KPI/tiền liên quan phải hiển thị chờ xác nhận. Việc thiếu quyết định không ngăn agent sửa các form/nguồn/test không phụ thuộc vào lựa chọn đó.

### D14 — Yêu cầu triển khai tiền KPI theo tỷ lệ thực đạt (07/10/2026)

- Người dùng chỉ ra: “Lương KPIS nhân với % KPIs ra số lương KPIs”, sau đó giao viết skill/plan/project/prompt cho Claude thực hiện đợt tiền lương và sửa R1/R2. Công thức triển khai cho phần 0–100%: quỹ lương KPI × tỷ lệ thực đạt; không lấy hệ số bậc 80% thay cho 87,5%.
- Đây là yêu cầu xây chế độ mới theo phiên bản, dự kiến từ 10/2026, không phải dấu phê duyệt cấu hình trên DB. Giữ phiên bản lịch sử, rounding và cờ/trần hiện có; chuẩn bị bản nháp review được và bàn giao thao tác duyệt cuối cho người dùng.
- Không chốt lại tỷ lệ cố định/quỹ KPI chung cho tất cả nhân viên, thưởng vượt 100% của D03, quy tắc D12 hoặc chính sách của phòng ban khác. Ví dụ 15 triệu × 30% × 87,5% là dữ liệu giả đối chiếu trước làm tròn.
- Hồ sơ thực hiện: [PROJECT đợt tiền lương](kpi-salary-closeout/PROJECT.md), [PLAN](kpi-salary-closeout/PLAN.md), [CHECKLIST](kpi-salary-closeout/CHECKLIST.md).

### D15 — Không làm tròn tiền KPI kỹ thuật (người dùng xác nhận 07/10/2026)

- Người dùng nói “ko cần làm tròn” sau khi được giải thích chênh lệch kỹ thuật/HR 500đ. Yêu cầu mới: giữ nguyên kết quả tính tiền KPI/cấu phần lương kỹ thuật; không làm tròn lên hoặc tới 1.000/10.000đ. Cùng đầu vào tỷ lệ 87,5%, quỹ 4.500.000 → tiền KPI 3.937.500, tổng dự kiến với cố định 10.500.000 → 14.437.500; nửa công tiền KPI 1.968.750.
- Thay yêu cầu giữ rounding 1.000đ trong S2 của đợt technical-payroll-material. Chế độ `none` phải thực sự giữ tiền, không rơi vào `round($amount)` hoặc định dạng/cắt số nguyên trước lưu. Phạm vi triển khai/cấu hình mới dự kiến kỳ 10/2026; không tính lại snapshot đã duyệt, thuế/BH hay các template phòng ban khác.
- Đây là quyết định yêu cầu triển khai và chuẩn bị bản nháp, không phải phê duyệt applied trên DB. V3 còn nháp có thể chuẩn bị `round_rule=none`; nếu đã duyệt thì tạo nháp kế thừa. Người có quyền vẫn duyệt áp dụng sau kiểm tra.
- Hồ sơ: [PROJECT](technical-payroll-material/PROJECT.md), [PLAN](technical-payroll-material/PLAN.md), [Prompt](technical-payroll-material/CLAUDE-PROMPT.md).

### D16 — Manager nhập tay vật tư tạm thời (người dùng xác nhận 08/10/2026)

- Người dùng hỏi “Tạm thời cái này cho manager nhập tính tay được không nhỉ”, sau đó xác nhận “Ok đúng chuẩn làm theo cách đó nhé”. Phương án được xác nhận: Trưởng phòng Kỹ thuật nhập % sai lệch, ghi cách tính, đính kèm bảng đối chiếu; lưu nháp chưa tính, xác nhận có người/giờ server ghi mới là nguồn hợp lệ.
- Ngưỡng theo Word vẫn `<3%`: dưới 3% đạt, đúng 3% trở lên không đạt. Hồ sơ khảo sát và Admin duyệt nghiệm thu vẫn bắt buộc; không tự đạt khi ô trống hoặc chưa xác nhận. Sửa kết quả/minh chứng cần xác nhận lại. Kỳ đã duyệt khóa sửa, kể cả Admin.
- Luồng mới áp dụng cho nghiệm thu được duyệt từ 08/10/2026 và hồ sơ cũ được sửa bằng luồng mới. Giữ nguồn trước đó, không tự đổi hàng loạt hoặc tính lại snapshot đã duyệt. Không thay các tiêu chí khác hay tự áp dụng cấu hình tiền lương.
- Đây là phương án nhập tay tạm thời, không chốt công thức tổng hợp theo SKU/đơn vị/tiền và không hoàn tất đối chiếu Kho tự động D08. Căn cứ tính tay phải ghi trên từng công trình để review.
- Triển khai/kiểm chứng: [Nhập tay vật tư](MANUAL-MATERIAL-20261008.md).
