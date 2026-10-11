# Kế hoạch triển khai

Thứ tự: nền tảng dữ liệu chung → kỹ thuật → HCNS → kế toán kho → điểm đã duyệt sang lương. Cấu hình chính sách làm song song; chưa chốt chính sách thì vẫn hoàn thiện nguồn, không tự tính tiền theo giả định.

## P0 — Kiểm tra nguồn và chốt phần việc đầu tiên

1. Chụp danh sách file dirty/untracked; xác minh source, web, DB local và DB test.
2. Đối chiếu 22 tiêu chí trong DATA-MAP với bảng/field/route đang dùng thật; đánh dấu đã có, thiếu trường, thiếu quy trình, cần xác nhận.
3. Kiểm tra đúng bảng `technical_kpi_monthly_scores`, không dùng tên suy đoán `technical_kpi_manual_scores`.
4. Tách dữ liệu lịch sử chưa đủ khỏi bản ghi mới; tạo danh sách cần bổ sung, không backfill mốc thời gian bằng `updated_at`.
5. Cập nhật DECISIONS với phần thật sự cần quản lý chốt. Mọi mục mở chỉ chặn phần phụ thuộc nó.

Nghiệm thu: mỗi tiêu chí có nguồn hiện tại, khóa người chịu trách nhiệm, mốc xác định kỳ và khoảng trống được chỉ ra bằng file/schema thật. DB local nhập Excel giữ nguyên.

## P1 — Hoàn thiện ghi nhận dữ liệu và kết quả chung

- Chuẩn hóa liên kết nhân viên bằng ID, phòng ban/vị trí có hiệu lực; màn hình quản lý xác nhận mapping tên lịch sử.
- Module thiếu trường cần bổ sung người phụ trách, ngày nhận, hạn, hoàn tất, kết quả, xác nhận và minh chứng theo đúng nghiệp vụ. Không ép trường không liên quan vào mọi bảng.
- Lưu vòng đời trạng thái và lịch sử đổi hạn/trả lại; ghi mốc tại hành động nghiệp vụ thực tế.
- Kết quả tổng hợp phân biệt: đủ dữ liệu, thiếu dữ liệu, không phát sinh đã xác nhận, chờ quy tắc. Mọi kết quả có nguồn, mẫu số, lý do loại và phiên bản cấu hình.
- Thiết kế phạm vi tháng theo từng tiêu chí: việc đến hạn khác việc hoàn tất, nhóm tuyển dụng khác nghiệm thu công trình. Phân biệt giờ làm việc và giờ liên tục.

Nghiệm thu: tạo/cập nhật qua form hiện có lưu đúng nguồn; việc quá hạn còn mở không biến mất; mỗi điểm có thể xem danh sách nguồn; thiếu dữ liệu không trả 100%.

## P2 — Kỹ thuật trước

Đợt tiếp theo người dùng giao: **Công trình → Nghiệm thu**, ưu tiên P2-02 và P2-04. Thực hiện [PLAN A1–A5](technical-acceptance/PLAN.md); không chạy lại nhiệm vụ phiên đầu ở cuối tài liệu này. Sau khi kiểm tra form nhập → xác nhận → điểm, mới làm đối chiếu kho/vật tư P2-03 theo D08.

- Hoàn thiện phân công, hạn và ngày bàn giao; ghi kết quả nghiệm thu lần đầu, phản ánh khách hàng đã xác nhận.
- Bổ sung hồ sơ khảo sát và xuất/thu hồi vật tư theo mã + đơn vị; kiểm tra điều kiện HSE, vệ sinh, EVN và App riêng biệt.
- Nối nguồn cũ, workflow và `/du-an`, xác định khóa chống đếm trùng; thiếu trường vẫn báo thiếu.
- Áp dụng yêu cầu kỹ thuật mới từ 01/10/2026: 6 trọng số 25/15/15/10/5/30. Hoàn thiện bảo hành phản hồi 5 phút, hoàn thành 24 giờ và kiểm tra không khiếu nại; D12 còn cần định nghĩa rõ mốc/phạm vi. Thiếu nguồn phải báo thiếu, không loại 30% khỏi tổng.
- Sửa chọn cấu hình theo ngày hiệu lực để không lấy phiên bản tương lai cho tháng cũ. Kiểm tra đường tổng hợp nào trả 0 mặc định khi không có minh chứng.
- Kết quả nhận xét của người có trách nhiệm được lưu từ nghiệp vụ, không suy từ trạng thái chung hoặc trường trống.

Nghiệm thu: 6 tiêu chí của bộ mới có trường hợp đủ, thiếu và không phát sinh được kiểm tra; bảo hành có các biên 5 phút/24 giờ và khiếu nại; tháng 09 giữ bộ cũ, tháng 10 dùng bộ mới; cùng công trình nhiều nguồn chỉ tính một lần; tổng KPI truy được nguồn; chưa có cấu hình phù hợp thì báo rõ. Không tự nâng thưởng vượt chỉ tiêu.

## P3 — HCNS

Triển khai từng nhóm nguồn để không tạo một module quá lớn:

1. Công văn, thủ tục hành chính và yêu cầu tài sản/VPP: phân công, hạn, hoàn tất, chất lượng và xác nhận; sự cố là một nguồn có phạm vi riêng.
2. Tuyển dụng: nhận yêu cầu, ngày nhận việc thực tế, chi phí gắn đợt, nhóm thử việc và ngày nghỉ việc nghiệp vụ.
3. BHXH, lỗi bảng lương và khảo sát: phiếu theo dõi đúng hạn, lỗi được xác nhận và khảo sát có kỳ/phạm vi phản hồi.
4. Adapter tổng hợp từng tiêu chí vào màn KPI HCNS; trọng số chia đều hiện tại phải được nhận diện là tạm, không báo đã được duyệt.

Nghiệm thu: 9 tiêu chí có nguồn và quy tắc thiếu dữ liệu; khảo sát hài lòng không bị thay bằng sao đánh giá sự cố; kiểm kê tài sản không bị thay bằng tỷ lệ xử lý sự cố.

## P4 — Kế toán kho

1. Giờ tiếp nhận chứng từ, người xử lý, giờ nhập xong, lịch làm việc; không lấy ngày hóa đơn thay thời điểm nhận.
2. Phiếu kiểm kê với tồn sổ chốt, tồn thực tế, đối chiếu và ký xác nhận; nhật ký lỗi chứng từ đã xác nhận.
3. Lịch và mốc nộp báo cáo NXT; nhật ký cảnh báo tồn lâu/lỗi có người nhận; checklist hồ sơ gốc.
4. Adapter tổng hợp 7 tiêu chí, lấy số mã hàng/chứng từ/báo cáo đúng mẫu số; không lấy lượng tồn tổng thay số mã lệch.

Nghiệm thu: 7 tiêu chí truy được nguồn; số mã lệch và giờ làm việc được kiểm thử; cảnh báo trước 15 ngày chỉ tính khi mốc tham chiếu được chốt. Chưa chốt thì báo chờ quy tắc.

## P5 — Duyệt KPI và tích hợp lương

- Xác nhận trọng số, ngưỡng và bậc trả tiền theo phòng ban; lưu phiên bản có ngày hiệu lực và người duyệt.
- Duyệt/khóa KPI theo nhân viên, kỳ, bộ quy tắc; khi sửa nguồn phải giữ snapshot đã duyệt và có đường mở lại theo quyền/lý do.
- Hồ sơ lương hiệu lực tháng và đúng bộ KPI. Không gán thiếu bộ KPI rồi mặc định hưởng đủ hiệu suất.
- Chỉ dùng kết quả KPI hợp lệ cho bảng lương; ghi rõ chưa đủ thay vì suy đoán. Bảng lương Excel 08/2026 tiếp tục giữ snapshot.
- Đối chiếu mẫu có số liệu và chính sách đã xác nhận cho mỗi bộ. Mẫu tháng 8 hiện chưa đủ kết quả HCNS/kho để chứng minh hai bộ đó.

Nghiệm thu: nguồn đã xác nhận → điểm → hệ số hưởng → tiền KPI → tổng lương khớp bộ mẫu; khóa được kiểm tra cả API; tính lại không đổi kỳ đã khóa và không đụng snapshot Excel.

## Cách chạy phiên Claude đầu

Hoàn thành P0, rồi triển khai phần nền tảng tối thiểu của P1 và một luồng kỹ thuật P2 có đủ điều kiện. Ghi đúng phần đã làm, chưa làm và phần bị chặn. Không tự chạy migration toàn repo hoặc tiếp tục duyệt lương để chứng minh kết quả.

Ưu tiên điểm nhỏ có đủ kiểm thử, chạy được từ form đến kết quả. Kế hoạch đầy đủ tiếp tục dùng cho các phiên sau, không đánh dấu toàn dự án xong vì phiên đầu thành công.
