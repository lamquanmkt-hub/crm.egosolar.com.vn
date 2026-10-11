---
name: ego-hcns-kpi-automation
description: Đồng bộ dữ liệu nghiệp vụ và triển khai KPI HCNS tự động trong CRM EGO Solar; dùng cho tuyển dụng, thử việc, lỗi lương, BHXH, tài sản/VPP, công văn, khảo sát nội bộ và thủ tục hành chính. Không dùng để sửa KPI kỹ thuật, kho hoặc tự duyệt lương.
---

# KPI HCNS từ dữ liệu nghiệp vụ

Đọc [PROJECT](../../../docs/projects/kpi-automation/hcns-kpi/PROJECT.md), [PLAN](../../../docs/projects/kpi-automation/hcns-kpi/PLAN.md) và [SOURCE-MAP](../../../docs/projects/kpi-automation/hcns-kpi/SOURCE-MAP.md). Hồ sơ này là nhiệm vụ HCNS mới; các PLAN kỹ thuật cũ chỉ là tham khảo, không phải công việc phải làm lại.

## Điều quyết định cách triển khai

- Source đúng: `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`; local cổng 8001, DB vận hành local `crm_egosolar_test`; feature test chỉ `egosolar_test`, PHP `C:/php83/php.exe`. Không suy DB local an toàn để chạy test chỉ vì tên có chữ test.
- Dữ liệu nguồn trước: nhập/giao/gửi/xác nhận tại trang nghiệp vụ đang có, rồi KPI đọc nguồn. Không tạo chín form nhập % để gọi là tự động.
- Word HCNS có chín tiêu chí và bậc trả tiền, **không có trọng số**. Các trọng số chia đều, target chọn đầu trên của khoảng và phép chia theo target hiện có là baseline source, chưa phải chính sách được người dùng duyệt.
- Không dùng quy tắc kỹ thuật cho HCNS: không tự áp hạn 24 giờ, trọng số 25/15/15/10/5/30, tỷ lệ lương 70/30 hoặc cách trả % thực đạt. Người dùng không cần làm tròn tiền theo bậc nghìn; không thêm quy tắc làm tròn tiền mới.
- Kỳ chạy thử 10/2026 không chứng minh ngày hiệu lực HCNS đã được chốt. Cấu hình mới chỉ nháp/xem trước cho tới khi người có quyền duyệt.
- Đã có tệp riêng tư và xem trước ở bảy bước tuyển dụng. Tận dụng `RecruitmentAttachmentService`, controller và partial hiện có; không quay lại lưu CV bằng đường dẫn ổ đĩa hoặc đưa hồ sơ sang viewer bên ngoài. Tải tệp, lưu nháp, có link CV hay đổi trạng thái **không tự chứng minh đạt KPI**.
- Nhân viên chịu trách nhiệm bằng ID; áp phạm vi công ty/phòng ban theo cơ chế source. Không nối bằng tên gần giống. Hồ sơ thiếu người, hạn, mốc thật hoặc xác nhận giữ nguyên trạng thái thiếu.
- Giữ riêng: chưa nhập, chờ xác nhận, quy tắc chưa chốt, còn hạn, quá hạn, đạt/không đạt và không phát sinh đã xác nhận. Không đổi mẫu số để làm đẹp điểm. N/A phải có người xác nhận, lý do và quy tắc hợp lệ; không mặc định mọi tháng không phát sinh được 100%.
- KPI tự động và nhập tay có nguồn ưu tiên rõ ràng; không để `achievement` nhập cũ âm thầm đè kết quả nguồn. Nhập tay nếu giữ lại phải đánh dấu, lưu người/lý do/minh chứng và không tính hai lần.
- Quyền ghi nghiệp vụ khác quyền xác nhận và quyền duyệt KPI/lương. Tận dụng phân quyền thật, không tự cấp role/quyền mới; không tự xác nhận KPI của chính mình. `PayrollAccess` hiện cho Ban Giám đốc/Admin chấm KPI, HR/Kế toán quản lý lương; không mặc định Trưởng phòng HCNS đã có quyền mới.
- Một bộ tổng hợp phục vụ trang KPI, KPI của tôi và HR payroll, kèm nguồn và phiên bản theo kỳ. Nguồn thiếu/quy tắc chưa chốt phải khiến kết quả chưa đủ; không sinh số lương khẳng định từ điểm nháp.
- Khi chốt kỳ, bảo vệ toàn bộ đường ghi có thể đổi kết quả đã chốt: sửa/xóa, API trực tiếp, đính kèm/xóa minh chứng, đổi người/hạn, hủy, trả bổ sung và duyệt lại. Chỉ khóa nguồn HCNS liên quan, không khóa toàn bộ module dùng chung. Nguồn có thể phục vụ nhiều kỳ phải dùng snapshot/version thay vì cấm mọi cập nhật tương lai.

## Thực hiện và kiểm chứng

Đối chiếu route thực sự được nạp, controller/service, schema thật và các đường ghi trước khi bổ sung. Sửa source có sẵn theo baseline hiện tại, không reset thay đổi của phiên khác. Chọn thiết kế hợp với module; tên service/trường mới trong hồ sơ là gợi ý, không coi là đã tồn tại.

Thực hiện PLAN theo các luồng hoàn chỉnh. Quyết định chưa chốt chỉ chặn phần phụ thuộc nó: vẫn hoàn thiện nhập liệu, lịch sử, xác nhận, phép đo nguồn và checklist. Nếu có đủ đầu vào, tiếp tục sang phần kế tiếp; không kết thúc chỉ với audit.

Kiểm thử hành vi thực: thiếu/đúng/trễ/biên hạn, hủy, gửi lại, thay người/hạn, chống trùng, kỳ/công ty, quyền, N/A, sửa sau chốt và lương. Browser test dùng server, DB, cache, phiên, storage và thư mục upload tạm riêng; không thay `.env`/cổng 8001 hoặc dùng cache/storage chung. Test fixture chỉ ở `egosolar_test`, không gửi thông báo. Migration thêm nullable/schema có mục đích; chỉ chạy migration liên quan trên DB test. Việc áp dụng migration/data nháp lên DB local vận hành phải trình bày cụ thể, chưa thuộc prompt này.

Giữ kỹ thuật mẫu, cấu hình và tiền lương hiện có; kỳ Excel 08/2026 giữ nguyên. Không commit/push/deploy, phê duyệt cấu hình/nguồn/KPI/lương trên local vận hành, gửi thông báo hoặc nhập dữ liệu thật thay người dùng.

Cập nhật PLAN với bằng chứng và PROJECT với trạng thái thực. Báo cáo riêng phần đã chạy, đã test, chưa test, thiếu dữ liệu và quyết định đang chờ; không gọi đủ chín KPI chỉ vì đã có trang hoặc file đính kèm. Nhật ký/ảnh nhạy cảm chỉ ở thư mục gitignore; tài liệu có thể commit chỉ dùng dữ liệu TEST.
