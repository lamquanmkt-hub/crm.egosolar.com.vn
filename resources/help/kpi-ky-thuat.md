---
title: KPI kỹ thuật và lương hiệu suất
url: /ky-thuat/kpis
routes: ky-thuat.kpis.*, technical.guides.*
keywords: kpi, kpis, kpi kỹ thuật, lương hiệu suất, lương kpi, tiền kpi, kh, th, kế hoạch, thực hiện, thực tế, nhập số liệu, nhập số liệu tháng, lương thoả thuận, lương thỏa thuận, lương cơ bản, mức lương kpi, chưa đủ dữ liệu, thiếu, cộng điểm, trừ điểm, điểm thưởng, vi phạm, cài đặt kpi, trọng số, 70/30, vượt 100%, trần, bậc thưởng, tiến độ, chất lượng, khảo sát, vật tư, hse, an toàn, vệ sinh, evn, app, đánh giá kpi, nghiệm thu, phàn nàn
---
Trang [Bảng KPI và lương hiệu suất](/ky-thuat/kpis) (menu **Kỹ thuật → KPIs**) tính **% KPI theo tháng** cho từng kỹ sư, rồi ra **tiền KPI** và **tổng lương dự kiến**. Nút **"Cách sử dụng"** ở đầu trang mở bài hướng dẫn chi tiết có hình minh hoạ theo đúng vai trò của người xem:
- Admin / Ban giám đốc: [KPI Kỹ thuật: cài đặt, lương thoả thuận và đọc bảng KPI](/ky-thuat/huong-dan/admin-kpi-ky-thuat)
- Trưởng phòng Kỹ thuật: [KPI tháng: nhập số liệu và đánh giá khi nghiệm thu](/ky-thuat/huong-dan/truong-phong-kpi-thang)
- Kỹ thuật viên: [Xem KPI và lương hiệu suất của tôi](/ky-thuat/huong-dan/nhan-vien-kpi-cua-toi)

Ai làm gì:
- **Admin / Ban giám đốc**: cài đặt công thức KPI và phê duyệt; xem bảng KPI của cả phòng.
- **Admin / HR / Kế toán**: nhập lương thoả thuận.
- **Trưởng phòng Kỹ thuật / Ban giám đốc / Admin**: nhập số liệu tháng, cộng/trừ điểm, đánh giá KPI ở bước Nghiệm thu của dự án.
- **Kỹ thuật viên**: chỉ xem KPI của chính mình, không vào được trang Cài đặt KPI và Nhập số liệu tháng, không tự chấm KPI.

## KH và TH là gì
Trên trang Nhập số liệu tháng, mỗi kỹ sư × tiêu chí có hai ô:
- **KH = Kế hoạch**: chỉ tiêu phải đạt, thường là số hệ thống kỹ sư phụ trách trong tháng.
- **TH = Thực hiện** (số thực tế): số hệ thống đạt chuẩn của tiêu chí đó.

Tỷ lệ đạt = **TH ÷ KH**. TH lớn hơn KH thì tiêu chí được vượt 100%.

Ví dụ một kỹ sư phụ trách 4 hệ thống trong tháng:
- Tiến độ (30%): KH 4, TH 3 (3 hệ thống bàn giao đúng hạn) → 75%.
- Chất lượng (25%): KH 4, TH 4 → 100%.
- Khảo sát & vật tư (15%): KH 4, TH 4 → 100%.
- An toàn HSE (15%): KH 4, TH 4 → 100%.
- EVN & App (15%): KH 4, TH 4 → 100%.

→ % KPI = 75%×30 + 100%×25 + 100%×15 + 100%×15 + 100%×15 = **92,5%**, cộng thêm điểm thưởng / trừ điểm nếu có.

## 5 tiêu chí và cách tính % KPI
Trọng số mặc định (sửa được ở Cài đặt KPI):
1. **Tiến độ hoàn thành lắp đặt — 30%**: số hệ thống bàn giao đúng ngày cam kết hoàn thành ÷ số hệ thống bàn giao.
2. **Chất lượng thi công & thẩm mỹ — 25%**: số hệ thống nghiệm thu đạt ngay lần đầu (không bị "Trả lại") và chủ nhà không phàn nàn ÷ số hệ thống nghiệm thu.
3. **Khảo sát kỹ thuật & khối lượng — 15%**: số hệ thống đủ hồ sơ khảo sát, có bản vẽ sơ bộ và bảng khối lượng vật tư, lệch vật tư dưới 3% ÷ số hệ thống nghiệm thu.
4. **An toàn lao động (HSE) & vệ sinh — 15%**: số hệ thống đạt đủ checklist HSE ÷ số hệ thống nghiệm thu.
5. **Hỗ trợ thủ tục EVN & cài đặt App — 15%**: số hệ thống đã đóng điện thử lưới EVN (khi cần đấu nối) và cài App giám sát ÷ số hệ thống nghiệm thu.

Tiêu chí 6 (thời gian xử lý bảo hành) chưa tính điểm.

**% KPI** = Σ (tỷ lệ đạt × trọng số) + điểm cộng/trừ (1 điểm = 1%).

## Tiền KPI và tổng lương dự kiến
- Lương thoả thuận chia thành **lương cố định** và **quỹ KPI**, mặc định **70% / 30%**. Ví dụ lương 15.000.000 đ → cố định 10.500.000 đ, quỹ KPI 4.500.000 đ.
- Tiền KPI theo % KPI đạt:
  - Dưới 75%: không nhận.
  - 75% đến dưới 90%: nhận 80% quỹ KPI.
  - 90% đến dưới 100%: nhận 100% quỹ KPI.
  - Từ 100% trở lên: nhận đúng bằng % đạt (120% nhận 120%). Không giới hạn nếu Cài đặt KPI để trống ô "Trần tối đa khi vượt (%)".
- **Tổng lương dự kiến** = lương cố định + tiền KPI.
- Phiếu lương đã duyệt giữ nguyên số đã chốt.

## "Chưa đủ dữ liệu" và dòng "Thiếu: …" nghĩa là gì
Hệ thống **không tính % KPI** khi còn tiêu chí có trọng số chưa có số liệu, để không trả lương sai. Đây **không phải KPI 0%**. Dòng "Thiếu" ghi rõ còn thiếu gì:
- **"Thiếu: Lương thoả thuận"** → Admin / HR / Kế toán nhập ở [Cài đặt KPI](/ky-thuat/kpis/cai-dat), mục "Lương thoả thuận của từng kỹ sư".
- **"Thiếu: Tiến độ…, Chất lượng…, …"** → tháng đó chưa có số liệu. Hai cách xử lý:
  1. Nhanh nhất: Trưởng phòng Kỹ thuật vào [Nhập số liệu KPI tháng](/ky-thuat/kpis/nhap-lieu), nhập KH và TH.
  2. Lâu dài: phân công kỹ sư vào các bước của dự án trên [Dự án](/du-an), điền "Đánh giá KPI kỹ thuật" ở bước Nghiệm thu, rồi Admin duyệt nghiệm thu. Dự án được duyệt nghiệm thu trong tháng nào thì tính vào KPI tháng đó.

## Nhập số liệu KPI tháng
Dành cho Trưởng phòng Kỹ thuật / Ban giám đốc / Admin.
1. Mở [Bảng KPI](/ky-thuat/kpis) → bấm nút xanh **"Nhập số liệu tháng"** ở góc phải trên (hoặc vào [/ky-thuat/kpis/nhap-lieu](/ky-thuat/kpis/nhap-lieu)).
2. Chọn tháng ở ô tháng góc phải.
3. Với mỗi kỹ sư × tiêu chí, nhập **KH** và **TH**. Phải nhập cả hai, KH lớn hơn 0. Dòng "Tự động" gợi ý số lấy từ dự án /du-an để đối chiếu.
4. Bấm **"Lưu số liệu tháng …"** cuối trang.
5. Muốn xoá số đã nhập: để trống cả KH và TH rồi Lưu. Tiêu chí quay về dùng số tự động.

Số nhập tay luôn được ưu tiên hơn số tự động.

## Cộng / trừ điểm KPI
Trên trang Nhập số liệu tháng, mục **Cộng / trừ điểm KPI**: chọn kỹ sư, nhập số điểm (dương = cộng, âm = trừ, trong khoảng ±100) và **lý do** (bắt buộc, ít nhất 5 ký tự), rồi bấm **"Ghi nhận"**. 1 điểm = 1% KPI. Vi phạm nghiêm trọng (không đeo dây an toàn, bạo lực, lời lẽ thiếu chuẩn mực với chủ nhà) có thể trừ 10–20 điểm. Nhập nhầm thì bấm **"Xoá"** ở dòng điểm đó.

## Lương thoả thuận
Admin / HR / Kế toán nhập ở [Cài đặt KPI](/ky-thuat/kpis/cai-dat) → mục **"Lương thoả thuận của từng kỹ sư"**: chọn **"Áp dụng từ tháng"**, nhập lương cho người cần thay đổi, bấm **"Lưu lương thoả thuận"**. Mức lương giữ nguyên cho các tháng sau đến khi nhập mức mới. Nếu chưa nhập ở đây, hệ thống dùng lương đã có trong phiếu lương KPI trước đó hoặc lương chính thức trong hồ sơ nhân viên.

## Cài đặt công thức KPI (trọng số, 70/30, vượt 100%)
Admin vào [Cài đặt KPI](/ky-thuat/kpis/cai-dat):
1. Ở form cấu hình, sửa trọng số 5 tiêu chí (tổng 100%), tỷ lệ lương cố định / quỹ KPI, bậc tiền KPI.
2. Muốn "vượt bao nhiêu tính bấy nhiêu": mục **Hạn mức vượt 100%** tick **"Cho phép KPI vượt 100%"** và **để trống ô "Trần tối đa khi vượt (%)"**.
3. Bấm **"Lưu thành bản nháp mới"** (không ghi đè phiên bản cũ).
4. Ở bản nháp vừa tạo, Admin bấm **"Phê duyệt áp dụng"**. Chỉ phiên bản đã duyệt mới có hiệu lực. Tháng đã có phiếu lương duyệt không bị tính lại.

## Đánh giá KPI ở bước Nghiệm thu của dự án
Để KPI tự lấy số từ dự án: mở dự án trên [Dự án](/du-an) → bước **Nghiệm thu và bàn giao** → bảng **"ĐÁNH GIÁ KPI KỸ THUẬT"**. Chỉ Trưởng phòng Kỹ thuật / Admin điền được; kỹ sư được giao chỉ xem. Điền rồi bấm **Lưu**, trước khi Admin duyệt nghiệm thu:
- **Chất lượng thi công**: chọn "Chủ nhà không phàn nàn" hoặc "Có phàn nàn" (có phàn nàn thì bắt buộc ghi nội dung). Dự án bị bấm **"Trả lại"** ở bước Thi công hoặc Nghiệm thu tự tính là không đạt lần đầu.
- **An toàn HSE & vệ sinh**: tick đủ 5 mục — không tai nạn/sự cố, đeo dây an toàn & bảo hộ, không làm hỏng mái, dọn vệ sinh 100%, thu gom rác & cáp thừa. Thiếu một mục là không đạt. Chưa tick mục nào thì chưa tính.
- **EVN & cài App**: tick "Đã đóng điện thử, hòa lưới EVN" (bắt buộc khi bước Hồ sơ pháp lý có tick "cần hồ sơ đấu nối điện lực") và "Đã cài App giám sát" (hoặc dự án đã có link/tài khoản Monitoring).
- **Khảo sát & vật tư**: hệ thống tự kiểm tra bước Khảo sát đủ file bắt buộc, bước Đề xuất có Bản vẽ sơ bộ và Bảng khối lượng vật tư, và tự tính % lệch vật tư = vật tư đề xuất bổ sung ÷ đề xuất ban đầu. Có bảng quyết toán thì nhập "% chênh lệch vật tư theo bảng quyết toán" — số nhập được ưu tiên. Đạt khi lệch dưới 3%.

Cột **Trạng thái** của bảng hiện ngay Đạt / Chưa đủ / Thiếu hồ sơ / Chưa đánh giá. Link **"Cách đánh giá"** ở đầu bảng mở bài hướng dẫn.

## Câu hỏi thường gặp về KPI
- **Cả bảng đều "Chưa đủ dữ liệu"**: tháng đó chưa có dự án nào được duyệt nghiệm thu với kỹ sư được phân công, và chưa nhập số liệu tháng. Nhập số liệu tháng để có số ngay.
- **Vượt 100% nhưng tiền KPI vẫn chỉ 100%**: phiên bản Cài đặt KPI đang áp dụng chưa tick "Cho phép KPI vượt 100%" hoặc đang đặt "Trần tối đa khi vượt (%)". Sửa ở form cấu hình, tick cho phép vượt, để trống ô trần, "Lưu thành bản nháp mới" rồi "Phê duyệt áp dụng".
- **Đã sửa công thức mà bảng không đổi**: phiên bản mới chưa được "Phê duyệt áp dụng", hoặc tháng đang xem đã có phiếu lương được duyệt.
- **Kỹ thuật viên không thấy nút Nhập số liệu / Cài đặt KPI**: đúng phân quyền — kỹ thuật viên chỉ xem KPI của mình.
- **Không tick được bảng Đánh giá KPI ở bước Nghiệm thu**: chỉ Trưởng phòng Kỹ thuật / Admin được đánh giá; kỹ sư được giao chỉ xem.
