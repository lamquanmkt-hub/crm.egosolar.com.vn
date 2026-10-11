# Checklist triển khai

Chỉ đổi `[ ]` thành `[x]` sau khi có bằng chứng nghiệm thu. Ghi file/test/case ở cột cuối; việc chờ nghiệp vụ ghi mã DECISIONS. Trạng thái ban đầu là chưa triển khai.

| Mã | Hoàn tất | Công việc | Bằng chứng cần có |
|---|---|---|---|
| P0-01 | [x] | Xác minh workspace, DB local/test, file đang thay đổi | Baseline và tên môi trường, không dump dữ liệu cá nhân — 06/10: HEAD `6dad37c`, 238 mục dirty/untracked lưu ở `storage/app/kpi-automation/` (gitignore); PHP 8.3.35; local `crm_egosolar_test`, test `egosolar_test` (guard giữ nguyên) |
| P0-02 | [ ] | Rà bảng, field, route thật cho 22 tiêu chí | DATA-MAP được bổ sung đường ghi/đọc và gap — **mới xong 6 tiêu chí kỹ thuật** (DATA-MAP “Kết quả P0 kỹ thuật”); HCNS/kho chưa rà |
| P0-03 | [ ] | Kiểm tra bộ quy tắc thực tế theo tháng | Trọng số, hiệu lực và phần pending được ghi nhận — kỹ thuật xong (v1 duyệt hiệu lực 01/09/2026; v2 nháp 01/10/2026); HCNS/kho chưa |
| P0-04 | [x] | Chọn phần kỹ thuật có thể triển khai đầu tiên | Danh sách file và tiêu chí nghiệm thu phần đó — KS01 Tiến độ + KS06 Bảo hành + chọn cấu hình theo kỳ; file trong STATUS |
| P1-01 | [ ] | Liên kết nhân viên bằng ID và xác nhận mapping | Tên mơ hồ không tự gán; không tạo quyền/account |
| P1-02 | [ ] | Chuẩn hóa người phụ trách và mốc vòng đời cần thiết | Form ghi đúng ngày nhận/hạn/hoàn tất/xác nhận |
| P1-03 | [ ] | Phân biệt đủ, thiếu, N/A, chưa chốt quy tắc | Case thiếu/0/mẫu số 0 và lý do hiển thị đúng |
| P1-04 | [ ] | Kết quả có nguồn, phạm vi kỳ và cấu hình | Điểm truy ngược được; công việc mở quá hạn còn xuất hiện |
| P2-01 | [x] | Tiến độ kỹ thuật: phân công, hạn, bàn giao | Đúng hạn, trễ, thiếu hạn và đổi hạn — `TechnicalKpiSourceFlowTest` (đúng/trễ, tự cam kết lại không cứu trễ, đổi hạn truy được, thiếu hạn/bàn giao chưa duyệt, quá hạn còn mở, chỉ khảo sát không tính, đúng kỳ). Chờ D07 cho việc quá hạn vào mẫu số |
| P2-02 | [ ] | Chất lượng: nghiệm thu lần đầu và phản ánh | Review 06/10: workflow cần kết quả có/không phàn nàn và người/ngày đánh giá; thiếu/một nguồn thiếu không thành đạt. Test đạt. Nguồn nghiệm thu/minh chứng cũ thiếu xác nhận riêng vẫn báo thiếu; còn cần nối form/nguồn cho chúng |
| P2-03 | [ ] | Khảo sát và vật tư theo mã/đơn vị | Hồ sơ đủ; xuất trừ thu hồi; không cộng khác đơn vị |
| P2-04 | [ ] | HSE, vệ sinh, EVN và App | Các điều kiện riêng, người xác nhận và nguồn |
| P2-05 | [x] | Chống trùng nguồn dự án | Một công trình ở nhiều nguồn chỉ tính một lần — chống trùng toàn kỳ theo `sites.id`; test quý workflow + module cũ. Giới hạn: 10 dự án module cũ không có `legacy_site_id` không đối chiếu được |
| P2-06 | [x] | Phiên bản kỹ thuật mới từ 01/10/2026 | 6 trọng số 25/15/15/10/5/30; kỳ 09 và trước giữ bộ cũ; không hồi tố — test 08/09/10. Review 06/10 sửa form/controller còn ép bảo hành về 0; test lưu bộ 6 mục, từ chối bộ sai, quyền duyệt và không hồi tố đạt. DB local v2 vẫn **nháp chờ Ban lãnh đạo duyệt**, không tự duyệt/tính tiền |
| P2-07 | [ ] | Bảo hành 30%: phản hồi 5 phút, hoàn tất 24 giờ, không khiếu nại | Review 06/10: route/page đúng, nút hoàn tất thật ghi mốc; kiểm tra quyền/công ty, thứ tự mốc và khóa sau xác nhận; test 5 phút/24 giờ, thiếu một trong nhiều phiếu, tính tay đạt. **Chờ D12 và nguồn thật** nên chưa đánh dấu |
| P3-01 | [ ] | HCNS: công văn và thủ tục đúng hạn/chính xác | Có người phụ trách, ngày hoàn tất và xác nhận chất lượng |
| P3-02 | [ ] | HCNS: tài sản, VPP và SLA sự cố | Cấp phát/kiểm kê/sự cố có phạm vi và nguồn riêng |
| P3-03 | [ ] | HCNS: tuyển dụng và chi phí | Đợt tuyển, ngày thực nhận việc, chi phí và nhóm mẫu số |
| P3-04 | [ ] | HCNS: thử việc và ngày nghỉ nghiệp vụ | Tài khoản khóa không được coi là ngày nghỉ việc |
| P3-05 | [ ] | HCNS: BHXH và lỗi bảng lương | Hồ sơ đến hạn; lỗi được xác nhận, không đếm chỉnh hợp lệ |
| P3-06 | [ ] | HCNS: khảo sát và tổng hợp 9 tiêu chí | Có kỳ/phản hồi; không thay bằng đánh giá sao sự cố |
| P4-01 | [ ] | Kho: tiếp nhận và nhập chứng từ | Tính 8 giờ làm việc bằng mốc thật, không ngày hóa đơn |
| P4-02 | [ ] | Kho: phiếu kiểm kê và đối chiếu | Tồn sổ chốt, thực tế, số mã lệch và người ký |
| P4-03 | [ ] | Kho: nhật ký lỗi chứng từ | Lỗi, trách nhiệm và kiểm tra xác nhận |
| P4-04 | [ ] | Kho: báo cáo NXT | Lịch, hạn nộp, ngày gửi và người nhận |
| P4-05 | [ ] | Kho: cảnh báo tồn lâu/lỗi | Mốc tham chiếu 15 ngày, người nhận, bằng chứng gửi |
| P4-06 | [ ] | Kho: hồ sơ gốc và tổng hợp 7 tiêu chí | Checklist đủ/thiếu, kiểm tra độc lập, nguồn kết quả |
| P5-01 | [ ] | Xác nhận trọng số và cách trả tiền KPI | Quyết định có người xác nhận/ngày hiệu lực; không dùng giả định |
| P5-02 | [ ] | Duyệt và khóa KPI theo kỳ | Thử sửa UI/API/nguồn; mở lại có quyền và lý do |
| P5-03 | [ ] | Hồ sơ lương và bộ KPI đúng nhân viên | Có hiệu lực; thiếu bộ không tự hưởng đầy đủ |
| P5-04 | [ ] | Nối KPI hợp lệ sang tính lương | Điểm, hệ số và tiền KPI truy cùng snapshot/cấu hình |
| P5-05 | [ ] | Đối chiếu mẫu mỗi bộ và một kỳ vận hành | Đúng số liệu mẫu; thiếu dữ liệu báo rõ; không bịa mẫu HCNS/kho |
| QA-01 | [ ] | Bảo toàn bảng Excel 08/2026 | 12 dòng/số tiền/hash dữ liệu trước-sau giữ nguyên |
| QA-02 | [ ] | Giữ menu gọn và bộ cài đặt riêng | 3 mục Bảng lương; HCNS/kho/kỹ thuật chọn đúng bộ; Sales/Marketing còn dùng được |
| QA-03 | [ ] | Kiểm thử quyền và phạm vi dữ liệu | Không xem/sửa ngoài phạm vi; không tự sửa/duyệt theo quyền hiện có |
| QA-04 | [ ] | Kiểm tra code/UI sau từng phần | Lint, test phù hợp, route, ảnh UI khi chạy được; nêu check không chạy được |

Không ghi `[x]` chỉ vì tạo migration/service. Nếu chỉ hoàn thành một phần, giữ `[ ]` và ghi rõ phần đã làm trong STATUS.

06/10/2026: đã có mẫu thao tác local gồm 2 tài khoản TEST, 1 công trình, 1 phiếu bảo hành và ghi chú KPI ở từng bước (xem STATUS). Đã kiểm tra đăng nhập/xem và điểm thành phần 70, tổng còn thiếu KS06; giữ các mục QA/nghiệm thu mở vì người dùng chưa hoàn tất và xác nhận phiếu mẫu, chính sách và luồng dữ liệu thực còn cần đối soát.

06/10/2026 — cập nhật sau thử UI: kỹ thuật hoàn tất phiếu, trưởng phòng xác nhận không khiếu nại; KS06 30/30, tổng mẫu 100% khớp tính tay. Sửa hiển thị checklist cũ từ 0% thành 100% khi đã đóng và đủ minh chứng; 83/83 test liên quan đạt (407 assertions). Bảng Excel 08/2026 giữ nguyên. Giữ P2-07/P5/QA mở cho D12, nguồn thật và phạm vi còn chưa nghiệm thu; không dùng kết quả mẫu để đánh dấu toàn hệ thống sẵn sàng trả lương.
