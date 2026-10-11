# Nhiệm vụ giao Claude Code

**Prompt dưới đây là nhiệm vụ phiên đầu đã thực hiện.** Đợt hiện tại do người dùng giao là [luồng nhập liệu Nghiệm thu kỹ thuật](technical-acceptance/CLAUDE-PROMPT.md). Dùng prompt đợt mới, không khởi động lại P0 hoặc làm lại luồng bảo hành mẫu.

Bạn được giao triển khai local dự án đồng bộ dữ liệu nghiệp vụ để KPI tự động cho source `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`.

Đọc `.claude/skills/ego-kpi-automation/SKILL.md`, rồi đọc PROJECT, PLAN, CHECKLIST, DATA-MAP, DECISIONS và STATUS tại `docs/projects/kpi-automation`. Không coi báo cáo hoặc ví dụ trong Word là chính sách đã được duyệt.

Đọc bổ sung `TECHNICAL-REVISION-20261006.md`: người dùng xác nhận bộ kỹ thuật mới hiệu lực từ 01/10/2026, trọng số timeline/quality/survey/hse/evn_app/warranty là 25/15/15/10/5/30. Nguồn kỹ thuật mới là file trong Downloads; bản E:/KPIs là lịch sử. Bảo hành đã có trọng số 30%, yêu cầu phản hồi 5 phút, hoàn thành 24 giờ và không khiếu nại; D12 chỉ còn định nghĩa vận hành SLA. Giữ nguyên kỳ cũ, không loại bảo hành hoặc coi thiếu dữ liệu là đạt.

Người dùng muốn bổ sung dữ liệu tại các mục công việc/chứng từ đang có trước, sau đó tự tổng hợp sang KPI và cuối cùng sang lương. Menu phải giữ gọn; bộ cài đặt tách theo phòng ban. Phạm vi 3 file: kỹ thuật, HCNS, kế toán kho; không sửa cơ chế Sales/Marketing.

Phiên đầu hãy:

1. Hoàn thành P0: xác minh source/môi trường, baseline file đã thay đổi, bảng/field/route thật, khoảng trống từng tiêu chí và chính sách còn mở. Cập nhật DATA-MAP với kết quả thay vì chỉ đưa lại một kế hoạch chung.
2. Triển khai phần nền tảng tối thiểu P1 và một luồng KPI kỹ thuật P2 từ form ghi nguồn → xác nhận → kết quả và danh sách nguồn, chọn phần có đủ điều kiện. Tiến độ kỹ thuật là lựa chọn ưu tiên nếu kiểm tra cho thấy phù hợp. Sửa vấn đề chọn cấu hình tương lai cho tháng cũ và không đếm trùng nếu phần đó đang nằm trong luồng bạn triển khai.
3. Thêm/chạy test thực chất cho đủ/thiếu nguồn, quá hạn còn mở, đúng kỳ, chống trùng, quyền truy cập và cấu hình có hiệu lực. Không chạy fixture vào DB local chứa dữ liệu nhập. PHP là `C:/php83/php.exe`; test DB chỉ `egosolar_test`, không thay guard trong `tests/TestCase.php`.
4. Cập nhật CHECKLIST và STATUS theo đúng phần đã kiểm tra, ghi file đã sửa, test pass/fail/không chạy được và đầu việc tiếp theo. Dừng phiên đầu sau một phần kỹ thuật hoàn chỉnh để review trước khi tiếp tục các module còn lại.

Bạn được sửa source, thêm migration giữ dữ liệu cũ và chạy kiểm tra phù hợp trên môi trường local/test đã xác minh. Không xóa/reset thay đổi có sẵn; không tạo user/quyền; không deploy, commit/push, gửi email/thông báo hay duyệt lương. Chỉ chạy migration liên quan khi thật sự cần và kiểm tra target trước; không chạy migration toàn repo/migrate:fresh.

Bảng lương 08/2026 là Excel snapshot 12 dòng để đối chiếu. Không sửa tiền, tính lại, chuyển chế độ hoặc duyệt nó; không đọc/dump toàn bộ hồ sơ lương cá nhân nếu phần việc không cần. Không đưa dữ liệu cá nhân vào tài liệu/test có thể commit.

Các quy tắc chưa chốt trong DECISIONS chỉ chặn phần tính điểm/trả tiền liên quan. Tiếp tục hoàn thiện nguồn và khả năng cấu hình ở phần độc lập; không tự chọn trọng số hoặc mức thưởng cho đủ demo. Nếu một thao tác bị chặn quyền hoặc không có môi trường test, báo đúng hạn chế, thực hiện phần độc lập và không đánh dấu đã chạy được.

Kết quả cuối phiên bằng tiếng Việt, ngắn gọn: đã triển khai gì, bằng chứng kiểm tra, dữ liệu/quyết định còn thiếu và checklist tiếp theo.
