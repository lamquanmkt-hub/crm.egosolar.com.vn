# Kế hoạch thực hiện

Đọc [PROJECT](PROJECT.md) và [CHECKLIST](CHECKLIST.md); hoàn thành công việc độc lập trước khi cần thao tác duyệt cuối của người dùng.

## F0 — Đối chiếu hiện trạng và baseline

- Kiểm tra phiên đang làm cùng file, Git diff, guard DB và route/callsite thật. Đọc trạng thái R1/R2 hiện tại; tái hiện để tránh sửa trùng nếu đã có phiên khác xử lý.
- Ghi baseline riêng tư: cấu hình/phiên bản/hiệu lực, nguồn mẫu 63/148, lương mẫu 98 theo tháng, mốc 5 việc và lương HR 08/2026. Không xuất DB hoặc PII vào docs theo dõi Git.
- Xác định luồng tiền trên KPI kỹ thuật, preview và HR; phân biệt tiền dự kiến trước ngày công/khấu trừ với thực lĩnh. Dự kiến mới từ 01/10/2026, không thay phiên bản cũ đã duyệt.

Đạt khi đã biết entry point tính tiền và sửa nguồn/khóa kỳ, cùng baseline để đối chiếu. Chưa có baseline thì không seed local.

## F1 — Tiền KPI theo tỷ lệ và cấu hình có hiệu lực

1. Thêm chế độ tiền có tên rõ trên cấu hình hiện có: “Theo % KPI thực đạt” và “Theo bậc quy đổi”. Có thể dùng trường JSON như `payout_mode`; tên/schema do triển khai quyết định sau khi kiểm tra.
2. Với bản không có chế độ, giữ bậc lịch sử. Bản tỷ lệ dùng hệ số KPI thực tế trong phạm vi 0–100%, kể cả dưới 75%; bậc phân loại thành tích có thể giữ nhưng không quyết định tiền trong chế độ này.
3. Đưa quy đổi qua một entry point dùng chung để `computePayout`, `payoutRate`, preview và HR không lệch nhau. Kiểm tra đơn vị 0,875/87,5, null so với 0, cờ/trần vượt và thứ tự làm tròn.
4. Lưu/validate/đọc lại đúng chế độ. Khi chọn tỷ lệ, ẩn hoặc vô hiệu phần chỉnh payout theo bậc và giải thích công thức. Không để trường required của bậc chặn lưu tỷ lệ hoặc gửi cấu hình vô nghĩa. Bảng lương hiển thị hệ số trả tiền thực tế, không chỉ nhãn thành tích.
5. Chuẩn bị một bản nháp kế thừa cấu hình hiện áp dụng, hiệu lực 01/10/2026, chỉ đổi chế độ cần thiết. Giữ tỷ lệ lương, trọng số, làm tròn/cờ/trần của bản gốc. Preview dùng chính bản nháp này. Không sửa/duyệt tại chỗ bản applied/archived.

Ca đối chiếu tối thiểu: null, 0%, dưới 75%, đúng 75%, 87,5%, đúng 90%, 100%; kiểm tra >100% theo cờ/trần đã cấu hình, không tạo thưởng mới. Giữ kỳ trước 10/2026 và cấu hình không có mode trả theo bậc. Mẫu 15 triệu, quỹ 4,5 triệu: 87,5% ra 3.937.500 trước làm tròn; nếu quy tắc 1.000 thì 3.938.000 sau làm tròn. Không thay độ làm tròn để test pass.

## F2 — R1: xác nhận không sống lại sau khi sửa

- Tái hiện đạt → gửi → xác nhận HSE → sửa một mục thành fail/lưu nháp → đổi về pass/lưu nháp. Kết quả vẫn chưa xác nhận đến khi gửi và xác nhận lại.
- Vô hiệu bền vững theo nhóm; giữ lịch sử người/giờ/lần xác nhận. Không dùng version toàn bộ để làm mất nhóm khác không sửa, cũng không chỉ so fingerprint hiện tại.
- Bao phủ khai báo, thay/thêm/xóa minh chứng liên quan, sửa quyết định đấu nối và lịch sử trả lại có ảnh hưởng. Tình huống quay về nội dung cũ không phục hồi hiệu lực.
- Đưa test review gốc vào test nguồn phù hợp, không sao chép toàn bộ bộ test private hoặc sửa kỳ vọng để chấp nhận lỗi.

Đạt khi ba bước “sửa rồi đổi về”, “minh chứng đổi rồi khôi phục” và “nhóm khác không đổi” được kiểm bằng quyền/API thực tế. Bản nháp không được tính đã xác nhận.

## F3 — R2: khóa kỳ bảo vệ mọi đường sửa nguồn

- Tách khóa bước đã nghiệm thu (ngoại lệ Admin hiện có) khỏi khóa kỳ đã duyệt (mọi người bị chặn). Kiểm tra tập nhân viên và đúng tháng sử dụng nguồn, không khóa công trình/tháng không liên quan.
- Xác định cả `technical_kpi_payrolls` approved và kỳ `hr_payroll_periods` approved có dòng dùng nguồn kỹ thuật tương ứng. Tái dùng trạng thái snapshot/nguồn thực tế, tránh coi kỳ Excel nhập là lý do khóa mọi nghiệp vụ.
- Chặn trước khi ghi DB/file: lưu khai báo/gửi/xác nhận/trả bổ sung, upload/thay/xóa file, nhập legacy/đấu nối và các đường đổi phân công/trả lại/mốc có thể tác động KPI đã chốt. Tìm callsite thực tế trước khi quyết định nơi đặt guard; dùng service chung nếu phù hợp.
- Bị chặn phải giữ nguyên nội dung, version, lịch sử, liên kết file và bytes/file count. Không ghi file rồi mới báo lỗi, không tạo sự kiện thay nguồn do request thất bại.
- Giữ Admin được sửa bước đã approved khi kỳ chưa khóa; khóa không làm mất quyền hợp lệ ở tháng/công trình khác. Không mở khóa ngầm hay thêm đường bypass.

Đạt khi test hai đường R2 gốc và các mutation liên quan pass, bao gồm Admin, trưởng phòng, kỹ thuật; snapshot lương đã chốt không bị tính lại.

## F4 — Công trình mới: nhập → gửi → xác nhận → KPI

- DB test: dùng dữ liệu giả mới, đủ hồ sơ tại các bước theo quy trình có sẵn; không gán field legacy để tạo điểm.
- Kỹ thuật được giao nhập chất lượng/HSE/EVN và minh chứng; nháp còn thiếu xác nhận → gửi kiểm tra → trưởng phòng khác người thực hiện xác nhận/hoặc trả bổ sung → Admin duyệt nghiệm thu. Giờ/người do server ghi.
- Kiểm chứng các tiêu chí tương ứng trên KPI/checklist nguồn, đúng tháng duyệt và người chịu trách nhiệm; thiếu Khảo sát/Bảo hành/Tiến độ vẫn báo thiếu, không bù cho tổng 100%.
- Trên local có thể tạo hồ sơ mới tiền tố `TEST-KPI-LUONG-20261007-`, tái sử dụng tài khoản mẫu đã xác minh. Script/command chỉ chạy khi gọi, guard môi trường/DB/email/mã, chạy lại không tạo trùng hoặc làm mới mốc; không thông báo. Dùng tên/file/địa chỉ giả, giữ manifest riêng tư.
- Không sửa công trình 63, phiếu 148 hoặc 5 việc cũ để demo thuận lợi. Không tự thực hiện duyệt nghiệm thu cuối trên local; giao link/nút cho người dùng, hoặc trình diễn chuỗi đủ quyền trong DB test cô lập và nói rõ giới hạn local.

Đạt khi có bằng chứng từng trạng thái trước/sau và link mở đúng hồ sơ. Không coi bộ test pass là thao tác trình duyệt đã làm.

## F5 — Đối chiếu tiền, UI và bàn giao duyệt

- Test cùng bộ dữ liệu có quỹ KPI thống nhất trên kỹ thuật và HR: % thực đạt, hệ số payout, tiền KPI, rounding và tổng dự kiến. Khi so HR, kiểm soát ngày công chuẩn/thực tế, phụ cấp, bảo hiểm/thuế/tạm ứng; không so thực lĩnh với tổng dự kiến như hai số cùng nghĩa.
- Quy tắc HR hiện có thể nhân ngày công vào lương KPI; không tự bỏ quy tắc đó. Dùng fixture đủ ngày công để đối chiếu trực tiếp và một ca ngày công khác để giải thích công thức thực tế. Nếu hai nguồn lương/profile khác nhau, chỉ rõ field, không sửa nhân viên thật để ép khớp.
- Chạy test mới và các bộ liên quan: `TechnicalAcceptanceKpiFlowTest`, `TechnicalAcceptanceLegacyDisplayTest`, `TechnicalKpiChecklistTest`, `TechnicalKpiMonthlyInputTest`, các bộ PayrollCalculator/Flow/Access phù hợp. Các lỗi cũ phải có baseline/repro riêng; không mặc định mọi failure là lỗi cũ.
- Browser: preview chế độ tỷ lệ, form cấu hình, công trình mới, KPI từng người và HR theo quyền; desktop/mobile không tràn và không có 500. Không cấp quyền cho session hiện có để thử.
- So baseline sau làm: kỳ 08/2026, mẫu cũ, lương 98, mốc việc và cấu hình đã duyệt không đổi; chỉ xuất hiện bản nháp mới/hồ sơ TEST mới đúng manifest. Bằng chứng giữ `outputs/claude-kpi-agent/salary-closeout-20261007/` (đã gitignore) với cache/session cô lập.
- Cập nhật CHECKLIST, PROJECT và STATUS. Giao bản nháp đã review/preview và URL duyệt theo quyền cho người dùng ở bước cuối. Báo rõ “đã triển khai nhưng chưa áp dụng” nếu chưa duyệt; không tự ghi approved hoặc chốt tiền local.

Nếu thông tin nghiệp vụ ngoài phạm vi còn thiếu, ghi vào báo cáo và tiếp tục phần độc lập. Không hỏi lại về công thức nhân trực tiếp 0–100% đã được giao; việc duyệt phiên bản local là thao tác cuối có kết quả cụ thể để người dùng xem.
