# Dự án đồng bộ dữ liệu và KPI tự động

Ngày bàn giao: 06/10/2026. Trạng thái thực thi cập nhật tại [STATUS.md](STATUS.md); hồ sơ này mô tả baseline trước khi agent triển khai.

**Đợt đang được giao tiếp theo:** [Nhập liệu Nghiệm thu kỹ thuật](technical-acceptance/PROJECT.md), theo [PLAN A1–A5](technical-acceptance/PLAN.md), [CHECKLIST](technical-acceptance/CHECKLIST.md) và [prompt Claude Code](technical-acceptance/CLAUDE-PROMPT.md). Hồ sơ đã chuẩn bị; chưa triển khai source đợt mới. Mục tiêu là kỹ thuật nhập kết quả/minh chứng, trưởng phòng xác nhận, KPI đọc nguồn; giữ track duyệt nghiệm thu cuối hiện có. Bộ bảo hành mẫu đã test đạt, không quay lại triển khai từ P0.

## Mục tiêu được người dùng xác nhận

Hoàn thiện các mục nghiệp vụ còn thiếu để KPI tự lấy dữ liệu, theo 3 bộ HCNS, kế toán kho và kỹ sư dân dụng. Luồng đích:

`Công việc/chứng từ → ghi nhận đầy đủ → xác nhận nguồn → tổng hợp KPI → duyệt/khóa KPI → tính lương`.

Ưu tiên dữ liệu nghiệp vụ trước việc nhập điểm KPI. Nhập tay chỉ cho đánh giá cần con người hoặc dữ liệu lịch sử, kèm người đánh giá, lý do và minh chứng.

## Vị trí và môi trường

- Source: `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`.
- Local: `http://127.0.0.1:8001`.
- Laravel 12; PHP chạy được: `C:/php83/php.exe` (8.3.35).
- DB local đang sử dụng: MySQL/MariaDB `crm_egosolar_test`, host `127.0.0.1`.
- DB feature test: `egosolar_test`, theo `phpunit.xml` và guard `tests/TestCase.php`. Đây là hai DB khác nhau; kiểm tra riêng schema/migration trước khi test.
- Không sửa source `crm.egosolar.vn`, thư mục `crm.egosolar.vn-local`, hoặc site production.

## Đầu vào

1. `E:/KPIs/BẢNG TIÊU CHÍ ĐÁNH GIÁ KPI - HCNS.docx`: 9 tiêu chí; thiếu trọng số; quy trình sự cố 4 bước.
2. `E:/KPIs/BẢNG TIÊU CHÍ ĐÁNH GIÁ KPI - KẾ TOÁN KHO.docx`: 7 tiêu chí; trọng số 100%; cách trả tiền có mâu thuẫn với ví dụ.
3. **Bản kỹ thuật mới áp dụng từ 10/2026:** `C:/Users/PC/Downloads/BẢNG TIÊU CHÍ ĐÁNH GIÁ KPI - KỸ SƯ DÂN DỤNG.docx`: 6 tiêu chí, trọng số 25/15/15/10/5/30; bảo hành 5 phút phản hồi, 24 giờ hoàn thành, không khiếu nại. Bản `E:/KPIs` là nguồn lịch sử cho các kỳ trước; xem TECHNICAL-REVISION-20261006.md.
4. `E:/KPIs/THANG_8_2026_QT_EGO (7).xlsx`: bảng lương lịch sử để đối chiếu, không phải bộ kết quả KPI chi tiết.
5. `C:/Users/PC/Downloads/KE-HOACH-KPI-TINH-LUONG-v2.xlsx`: phương án tham khảo, không chứng minh tính năng đã triển khai.
6. Báo cáo đối chiếu trước: `outputs/kpi-erp-review-20261006/DE-XUAT-NANG-CAP-ERP-KPI-20261006.xlsx`.

Không chép dữ liệu lương hay thông tin định danh từ các file này vào tài liệu được commit.

## Baseline đã kiểm tra trên local

| Phần | Hiện trạng ngày bàn giao |
|---|---|
| Menu | Bảng lương nằm ngay dưới Nhân sự; 3 mục: Bảng lương tháng, KPI phòng ban, Cài đặt lương |
| HCNS | 9 tiêu chí, chấm tay/nhập thực tế; trọng số đang chia đều tạm, chưa coi là chính sách được xác nhận |
| Kế toán kho | 7 tiêu chí đúng trọng số; chưa nối đầy đủ quy trình kiểm kê/lỗi chứng từ/báo cáo/cảnh báo |
| Kỹ thuật | Có bộ tổng hợp dự án và nguồn nghiệm thu; phải kiểm tra độ đầy đủ và đường nhập nguồn |
| Dữ liệu KPI | Chưa có kết quả chấm HCNS/kho; lần rà tháng 08 và 10 không có kỹ sư được tính ra tổng KPI |
| Hồ sơ lương | 0 hồ sơ lương hiệu lực; chưa gán đủ nhân viên/bộ KPI để tự tính lương |
| Excel tháng 08 | Đã nhập 12 dòng dạng snapshot; 1 liên kết họ tên đầy đủ, 11 chờ xác nhận liên kết; chưa duyệt |
| Chính sách kỹ thuật | Cấu hình đang giới hạn hưởng 100%; chưa khớp dòng thưởng 110–120% của Word |

Baseline là trạng thái tại thời điểm bàn giao, không phải số liệu production. Agent phải đọc lại trạng thái khi bắt đầu.

## Phạm vi

- Hoàn thiện trường dữ liệu, phân công, mốc thời gian, xác nhận và nhật ký tại module đang có.
- Nối đủ 22 tiêu chí trong DATA-MAP, gồm bảo hành kỹ thuật 30% từ 10/2026; chi tiết SLA/khiếu nại còn thiếu định nghĩa phải báo rõ, không loại mục này để tính tổng.
- Hiển thị nguồn và dữ liệu thiếu trong trang KPI phòng ban; duyệt/khóa kết quả; cấu hình có hiệu lực theo kỳ.
- Bổ sung nghiệp vụ còn thiếu như phiếu kiểm kê, nhật ký lỗi và khảo sát ngay trong khu vực liên quan, ưu tiên tab/form thay vì thêm menu cấp cao.
- Tích hợp kết quả KPI đã duyệt vào cơ chế tính lương đang có sau khi dữ liệu và quy tắc được xác nhận.

Không nằm trong lần giao này: đổi cách tính Sales/Marketing, thay hệ thống ERP, deploy production, chi trả lương, đổi tài khoản/quyền, commit/push hoặc thông báo nhân viên.

## Người xác nhận nghiệp vụ

| Nội dung | Vai trò xác nhận |
|---|---|
| Trọng số, bậc trả tiền và ngoại lệ | Giám đốc và trưởng bộ phận tương ứng |
| Nguồn kỹ thuật | Trưởng phòng kỹ thuật, kỹ sư phụ trách, người nghiệm thu và kho khi có vật tư |
| Nguồn HCNS | HCNS và người tiếp nhận/kiểm tra |
| Nguồn kho | Kế toán kho, thủ kho và người nhận báo cáo |
| Đối chiếu lương | HCNS, kế toán; người có quyền duyệt lương hiện tại |

Đây là vai trò đề xuất cho vận hành; agent không tự cấp thêm quyền hay xác nhận thay người phụ trách.

## Hồ sơ thực thi

- [PLAN.md](PLAN.md): các giai đoạn và đầu ra.
- [CHECKLIST.md](CHECKLIST.md): checklist có mã và bằng chứng nghiệm thu.
- [DATA-MAP.md](DATA-MAP.md): nguồn từng tiêu chí và dữ liệu thiếu.
- [DECISIONS.md](DECISIONS.md): các lựa chọn chưa được xác nhận.
- [CLAUDE-PROMPT.md](CLAUDE-PROMPT.md): lệnh giao nhiệm vụ đầu tiên.
- [STATUS.md](STATUS.md): tiến độ do agent cập nhật.
- [TECHNICAL-REVISION-20261006.md](TECHNICAL-REVISION-20261006.md): thay đổi kỹ thuật và ngày hiệu lực đã xác nhận.
