---
title: Kỹ thuật — Kế hoạch tuần & Báo cáo ngày
url: /ky-thuat
routes: ky-thuat.tong-quan, technical.*, ky-thuat.kpis.*
keywords: kỹ thuật, kế hoạch tuần, lập kế hoạch, hoàn tất tuần, lưu nháp, thêm việc, chuyển ngày, sao chép tuần, báo cáo ngày, việc phát sinh, gửi duyệt báo cáo, yêu cầu sửa, trưởng phòng, giao việc, tổng kết tuần, kpi
---
Phòng Kỹ thuật dùng 3 màn hình chính: [Tổng quan](/ky-thuat), [Kế hoạch tuần](/ky-thuat/ke-hoach-tuan), [Báo cáo ngày](/ky-thuat/bao-cao-ngay). Công việc lấy từ 3 nguồn thật: **quy trình Công trình**, **Công việc (task)** và **Bảo trì/Bảo hành**.

## Nhân viên: lập kế hoạch tuần
1. Mở [Kế hoạch tuần](/ky-thuat/ke-hoach-tuan) (tab Tuần này / Tuần trước / Lịch sử). Tuần tính từ thứ Hai đến Chủ nhật.
2. **Thêm việc** cho từng ngày: chọn buổi (Sáng / Chiều / Cả ngày / Giờ cụ thể), nội dung (bắt buộc), mức ưu tiên, **nguồn công việc** (việc công trình, task, bảo trì được giao cho bạn; hoặc việc cá nhân), thời lượng dự kiến (15 phút – 16 giờ).
3. Tối đa 12 việc/ngày; không trùng cùng một việc nguồn trong một ngày.
4. Ngày không có việc thì **đánh dấu**: Nghỉ theo lịch / Nghỉ phép / Chờ phân công / Không có kế hoạch.
5. Bấm **Hoàn tất** tuần. Không hoàn tất được khi: tuần chưa có việc nào; có ngày làm việc (thứ Hai – thứ Bảy) vừa trống vừa chưa đánh dấu; hoặc một ngày vượt 16 giờ. Chủ nhật được miễn.

Tiện ích: **Sao chép việc**, **Chuyển ngày**, **Đề xuất chuyển sang ngày sau** (bắt buộc lý do), **Sao chép tuần trước** (chỉ chép việc chưa xong). Cảnh báo (vẫn cho hoàn tất): trùng giờ, quá tải, việc có hạn trước ngày làm, việc nguồn không còn giao cho bạn.

## Nhân viên: báo cáo ngày
Vào [Báo cáo ngày](/ky-thuat/bao-cao-ngay) → tạo báo cáo theo 1 trong 3 cách: từ **dòng kế hoạch**, từ **đầu việc được giao**, hoặc **việc phát sinh** (không có kế hoạch trước, bắt buộc lý do phát sinh).
- Bắt buộc: ngày thực hiện (không phải ngày tương lai, không lùi quá 60 ngày), nội dung đã làm, tiến độ %.
- Tùy chọn: giờ làm thực tế, kết quả, lý do chưa xong, vật tư, vấn đề phát sinh, kế hoạch tiếp theo, tối đa 10 file minh chứng (≤ 10 MB/file).
- **Lưu nháp** hoặc **Gửi duyệt**. Một ngày được gửi nhiều báo cáo (mỗi đầu việc một báo cáo).
- Bị **Yêu cầu sửa**: mở báo cáo, sửa, rồi gửi duyệt lại.

## Trưởng phòng Kỹ thuật
- **Tổng quan phòng** và **ma trận kế hoạch** nhân viên × 7 ngày (thấy ai chưa lập kế hoạch, việc quá hạn, quá tải).
- **Tạo kế hoạch / Giao việc** cho nhân viên từ ma trận (bấm ô nhân viên × ngày). **Lưu nháp** hoặc **Lưu và giao**; mọi điều chỉnh bắt buộc lý do.
- **Yêu cầu nhân viên cập nhật kế hoạch** (kèm lý do).
- **Duyệt / Yêu cầu sửa** báo cáo ngày của nhân viên (yêu cầu sửa bắt buộc ý kiến). Không tự duyệt báo cáo của chính mình.
- **Tổng kết tuần**: kế hoạch so với thực tế (tỷ lệ hoàn thành, tỷ lệ báo cáo); mẫu số bằng 0 hiển thị N/A.
- Trưởng phòng không nhập báo cáo thay nhân viên.

## KPI kỹ thuật và lương hiệu suất
[Bảng KPI](/ky-thuat/kpis) tính % KPI và lương hiệu suất theo tháng (hoặc quý) cho từng kỹ sư. 5 tiêu chí có trọng số (mặc định): Tiến độ lắp đặt 30%, Chất lượng thi công 25%, Khảo sát & khối lượng 15%, An toàn HSE 15%, EVN & cài App 15%. Tiêu chí 6 (bảo hành) chờ Ban lãnh đạo xác nhận, chưa tính điểm.

Cách tính:
- Mỗi tiêu chí: tỷ lệ đạt = Thực tế ÷ Kế hoạch. **Thực tế vượt Kế hoạch thì tiêu chí được vượt 100%.**
- % KPI = tổng (tỷ lệ đạt × trọng số) + điểm cộng/trừ (1 điểm = 1%). Chỉ tính được khi **đủ số liệu mọi tiêu chí có trọng số**; thiếu thì dòng nhân viên ghi rõ "Thiếu: …".
- Lương thoả thuận chia thành lương cố định và quỹ KPI (mặc định 70% / 30%). Tiền KPI: dưới 75% không nhận; 75% đến dưới 90% nhận 80%; 90% đến dưới 100% nhận 100%; từ 100% trở lên nhận đúng bằng % đạt (không giới hạn nếu cấu hình để trống trần).
- Phiếu lương đã duyệt giữ nguyên số đã chốt.

Việc cần làm mỗi tháng:
1. **Admin / HR / Kế toán**: nhập **lương thoả thuận** từng kỹ sư ở [Cài đặt KPI](/ky-thuat/kpis/cai-dat), mục "Lương thoả thuận của từng kỹ sư" (chọn tháng bắt đầu áp dụng; chỉ nhập người cần thay đổi).
2. **Trưởng phòng Kỹ thuật / Ban Giám đốc / Admin**: vào [Nhập số liệu KPI tháng](/ky-thuat/kpis/nhap-lieu), chọn tháng, nhập **Kế hoạch (KH)** và **Thực tế (TH)** cho từng kỹ sư × tiêu chí rồi bấm **Lưu**. Ô "Tự động" gợi ý số lấy từ dự án (/du-an) để đối chiếu; để trống cả KH và TH là xoá số đã nhập.
3. Cộng điểm thưởng hoặc trừ điểm vi phạm ở mục **Cộng / trừ điểm KPI** cùng trang (bắt buộc lý do). Vi phạm nghiêm trọng (không đeo dây an toàn, bạo lực, lời lẽ thiếu chuẩn mực với chủ nhà) có thể trừ 10–20 điểm.
4. Số liệu **tự động** từ quy trình [Dự án](/du-an) của các công trình mà kỹ sư được phân công (bước Khảo sát / Thi công / Nghiệm thu) hoặc là kỹ sư phụ trách chính:
   - **Tiến độ**: bàn giao đúng **ngày cam kết hoàn thành** (bước Ký hợp đồng) hoặc hạn của bước.
   - Các tiêu chí dưới đây tính cho công trình có bước **Nghiệm thu và bàn giao được duyệt trong tháng**. **Trưởng phòng Kỹ thuật hoặc Admin** điền mục **"Đánh giá KPI kỹ thuật"** ở bước Nghiệm thu (kỹ sư được giao chỉ xem, không tự chấm) (mở dự án → bước Nghiệm thu và bàn giao → điền → **Lưu**) trước khi duyệt:
   - **Chất lượng**: đạt nếu bước Thi công và Nghiệm thu **không bị "Trả lại"** lần nào và chọn **"Chủ nhà không phàn nàn"**. Chọn "Có phàn nàn" thì bắt buộc ghi nội dung.
   - **An toàn HSE**: đạt khi tick đủ 5 mục: không tai nạn/sự cố, đeo dây an toàn và đồ bảo hộ, không làm hỏng mái, dọn vệ sinh 100%, thu gom rác và cáp thừa. Chưa đánh giá thì chưa tính.
   - **EVN & App**: đạt khi đã cài App giám sát (tick "Đã cài App" hoặc có link/tài khoản Monitoring) và, nếu công trình cần đấu nối điện lực (tick ở bước Hồ sơ pháp lý), đã tick **"Đã đóng điện thử, hòa lưới EVN"**.
   - **Khảo sát & vật tư**: đạt khi bước Khảo sát đủ file bắt buộc, bước Đề xuất có **Bản vẽ sơ bộ** và **Bảng khối lượng vật tư**, và lệch vật tư **dưới 3%**. Lệch vật tư lấy số nhập ở ô "% chênh lệch vật tư theo bảng quyết toán"; để trống thì tự tính = vật tư đề xuất bổ sung ÷ đề xuất ban đầu.
   Số Trưởng phòng nhập tay ở trang Nhập số liệu KPI tháng luôn được ưu tiên hơn số tự động.

Đổi trọng số, tỷ lệ 70/30, bậc thưởng hoặc trần vượt: ở [Cài đặt KPI](/ky-thuat/kpis/cai-dat) tạo phiên bản mới, Admin bấm **Phê duyệt áp dụng** thì mới có hiệu lực. Kỹ thuật viên chỉ xem được KPI của chính mình, không vào được trang cài đặt và trang nhập số liệu.
