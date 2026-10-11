# Hoàn thiện KPI kỹ thuật trước khi dùng tính lương

**Cập nhật 08/10/2026 — D16:** đã triển khai manager nhập tay vật tư có bảng đối chiếu, ghi chú, xác nhận và khóa kỳ. Xem [kết quả đợt tạm thời](../MANUAL-MATERIAL-20261008.md). Đây là phần bổ sung; S1/S2 và đối chiếu Kho tự động S3 chưa hoàn tất. Các mô tả hiện trạng 07/10 bên dưới là baseline trước D16; không khôi phục việc số nhập tay tự vào KPI.

Baseline ngày giao 07/10/2026: người dùng yêu cầu bốn phần: đồng bộ phiếu lương cũ; giữ nguyên tiền, không làm tròn (D15) và thử HR trên trình duyệt; đối chiếu vật tư D08/khóa kỳ; sau kiểm tra giao người có quyền duyệt v3. Khi giao hồ sơ chưa sửa source. Cập nhật thực hiện D16 xem phía trên; các phần còn lại vẫn mở.

**Cập nhật mới nhất (D15): người dùng nói “ko cần làm tròn”.** Phần S2 thay bằng giữ nguyên tiền tính được trên kỹ thuật và HR. Đây là yêu cầu triển khai/chuẩn bị cấu hình nháp, không phải phê duyệt áp dụng. Các con số làm tròn trong báo cáo đợt cũ chỉ là lịch sử.

## Căn cứ hiện có

- `kpi-salary-closeout/PROJECT.md`, review độc lập `outputs/claude-kpi-agent/salary-closeout-20261007/CODEX-REVIEW.md`: tỷ lệ trả tiền dùng chung đã có, nhưng đường phiếu lương cũ và làm tròn HR chưa đồng bộ.
- `app/Http/Controllers/Synced/TechnicalKpi/TechnicalPayrollController.php`: `store()/update()` gọi `calculate()` theo `settingsMap()`/template cũ. Route cùng tên bị khai báo ở nhiều file; phải kiểm route thực chạy và xử lý mọi entry point còn truy cập được.
- Trang KPI gọi `TechnicalKpiConfig::computePayout`; HR `HrKpiEvaluator::technical()` gọi `payoutRate`; `PayrollCalculator` tự nhân quỹ × tỷ lệ công × hệ số và làm tròn 2 chữ số, không dùng `round_rule` của cấu hình kỹ thuật.
- `ProjectKpiLinkService::workflowMaterialDeviationPercent`: cộng `requested_qty` ban đầu/bổ sung, không phân biệt mã/đơn vị, dùng đề xuất thay xuất thực tế. Nhánh cũ `materialDeviationPercent` cũng cộng lượng và suy tiêu hao từ đề xuất. Ô nhập tay nghiệm thu hiện được ưu tiên; không thể tuyên bố D08 chạy tự động bằng dữ liệu kho.
- Nguồn cần kiểm tiếp: `project_material_proposals/items`, `material_requests/items`, `site_planned_materials`, sổ xuất/nhập/thu hồi đã xác nhận và nguồn legacy. Tên bảng/trường không đủ để khẳng định liên kết đã đúng; đối chiếu service ghi và schema thật.
- Khóa nghiệm thu R2 đã có ở `AcceptanceKpiService::assertSourceUnlocked`; đường vật tư chưa nằm trong phạm vi đã kiểm. Phiếu/kỳ duyệt giữ snapshot, không mở khóa để sửa nguồn.

## S1 — Phiếu lương kỹ thuật dùng bộ tính có phiên bản

Đường tạo/sửa phiếu phải lấy đúng người, kỳ, nguồn đã xác nhận và cấu hình **đã áp dụng** có hiệu lực; điểm và tiền khớp trang KPI với cùng phạm vi. Không lấy số tự nhập để thay điểm tự động, không tính riêng từ template cũ khi bộ mới đã áp dụng. Thiếu một tiêu chí cần thiết thì báo thiếu, không lưu phiếu như đủ tiền/0 điểm và không duyệt.

Giữ phiếu đã duyệt và kỳ lịch sử. Không tính lại snapshot lịch sử để “đồng bộ”. Chặn cập nhật/xóa/thay người hoặc tháng để vượt khóa. Cần lưu thông tin cấu hình/quy tắc làm tròn/nguồn lúc lập phiếu nếu schema hiện có chưa đủ truy vết; migration bổ sung an toàn khi thực sự cần, không migrate:fresh. Với phiếu nháp cũ cần tính lại, làm rõ thay đổi/xem trước thay vì tự ghi hàng loạt vào local.

## S2 — Giữ nguyên tiền kỹ thuật và HR, không làm tròn

Theo D15, chuẩn bị `round_rule=none` ở bản nháp trả theo tỷ lệ (v3 nếu còn draft). Nếu bản đó đã áp dụng thì tạo bản nháp kế thừa, không sửa cấu hình đã duyệt. Chung bộ quy đổi hệ số và giữ nguyên tiền cho phần kỹ thuật. Các template HCNS/kho/Sales, thuế/BH và kỳ nhập Excel giữ cách tính hiện có.

Thứ tự tính: khoản tiền × hệ số KPI × tỷ lệ công khi áp dụng → giữ nguyên kết quả. Không nhân KPI hai lần, làm tròn tới nghìn/chục nghìn, làm tròn lên hoặc ép số nguyên ở các bước tính/ghi/hiển thị để ép hai trang khớp. `TechnicalKpiConfig::applyRounding` hiện nhãn `none` vẫn rơi vào default `round($amount)`; cần sửa đúng ý nghĩa không làm tròn, cùng precision lưu trữ tiền và định dạng hiển thị rõ. Quỹ lương HR và mức lương kỹ thuật phải thực sự tương ứng mới đối chiếu được; không tự sửa hồ sơ lương thật.

Ca đối chiếu bản mới: lương thỏa thuận 15.000.000, cố định 70%, quỹ KPI 30%, đạt 87,5%, đủ công → tiền KPI **3.937.500**, tổng dự kiến **14.437.500**. HR cùng đầu vào không phụ cấp/thuế/BH phải khớp. Nửa công: KPI **1.968.750**. Test thêm quỹ lẻ, kết quả có phần thập phân và công khác 100%; giữ test rounding của cấu hình lịch sử, không đổi kỳ vọng lịch sử thành chế độ mới.

Thử UI HR đầy đủ trên server/DB test cô lập: hồ sơ có hiệu lực → đủ/thiếu công → thiếu KPI → tạo/tính lại → kiểm chi tiết/phiếu → duyệt bằng tài khoản đúng quyền → sửa nguồn bị chặn. Thử desktop/mobile, kiểm khoản khấu trừ/thực lĩnh và Excel snapshot bất biến. Duyệt fixture test không phải quyền duyệt local.

## S3 — Vật tư D08 và khóa nguồn

Lập bảng đối chiếu **từng mã hàng + đơn vị chuẩn**: dự toán/bóc tách đã duyệt, xuất kho thực nhận, thu hồi nguyên vẹn đã nhập kho, tiêu hao = xuất − thu hồi, lệch tuyệt đối và tỷ lệ. Hiện bảng vật tư chưa là baseline đáng tin chỉ vì đã có một đề xuất; cần liên kết và xác nhận baseline.

Không cộng m/cái/bộ hay tổng lượng các mã không thể so. Không lấy request đã gửi/đã duyệt là đã xuất; không trừ yêu cầu thu hồi chưa nhập kho. Mã thay thế hoặc đổi đơn vị phải có mapping được xác nhận và dấu nguồn; chưa có thì báo thiếu. Mã ngoài dự toán, dự toán bằng 0, tiêu hao âm, thu hồi vượt xuất, thiếu chứng từ và nhiều phiếu trùng phải có trạng thái/kiểm tra rõ ràng.

**Quyết định tổng hợp đang chờ người dùng:** mỗi mã/đơn vị đều lệch `<3%`, hay tỷ lệ tổng theo giá trị tiền. Chưa có câu trả lời thì làm bảng/nguồn/xác nhận/khóa và test độc lập, không tự gắn lựa chọn vào phiên bản applied hoặc chấm đạt toàn công trình. Nếu quy đổi tiền, cần giá baseline đã chốt và công thức tổng sai lệch được xác nhận; không dùng giá hàng hiện tại hoặc bù trừ mã dư với mã thiếu để che lỗi. Ngưỡng trong Word là `<3%`, đúng 3% không đạt; xét số gốc trước rounding hiển thị.

Tích hợp ngay trong Công trình → Vật tư/Nghiệm thu và checklist nguồn khảo sát, không thêm menu. Khi đủ nguồn và xác nhận thì bộ chấm đọc kết quả đối chiếu; số nhập tay cũ phải được nhận diện là nguồn cũ/ngoại lệ có người duyệt, không ưu tiên vô điều kiện để bỏ qua D08. Giữ cách đọc của kỳ lịch sử và demo cũ, không tự duyệt ngoại lệ để giữ điểm.

Rà tất cả đường có thể đổi baseline, mapping, phiếu xuất/thu hồi/trạng thái, file minh chứng hoặc người chịu KPI. Khóa theo cả phiếu kỹ thuật đã duyệt và kỳ HR đã duyệt có nguồn kỹ thuật, kiểm trước ghi DB/file/ledger; Admin cũng không vượt. Một chứng từ liên kết nhiều công trình phải kiểm tất cả công trình chịu ảnh hưởng. Không khóa toàn Kho nếu thao tác không ảnh hưởng nguồn của kỳ đã chốt; kỳ Excel không được hiểu là dùng KPI live.

## S4 — Cổng duyệt v3

Đây là bước bàn giao cho người có quyền sau khi S1–S3 kiểm tra đạt và các quyết định cần thiết đã có. Không tự bấm duyệt cấu hình/nghiệm thu/lương local. Giao preview tác động trước/sau, bảng tiền không làm tròn, nguồn còn thiếu và link bản nháp đã xác minh (v3 nếu còn draft; bản kế thừa nếu v3 đã được duyệt). Không hạ trạng thái bản đã duyệt hoặc tạo bản nháp trùng.

## Môi trường và giữ dữ liệu

Repo `D:/Source Dev/crm.egosolar.com.vn/crm.egosolar.com.vn`; local 8001, DB `crm_egosolar_test`; test chỉ `egosolar_test`, PHP `C:/php83/php.exe`, giữ guard TestCase. Fixture, cache/session/mail/storage/server test cô lập, không dùng chung cache thương hiệu hoặc storage local.

Không tự sửa mẫu 63/64/148, việc giao, lương người 98, cấu hình đã duyệt, kỳ HR 08/2026 (12 dòng Excel nháp), tài khoản/phân công hoặc gửi thông báo. Bản nháp tiền tỷ lệ được chuẩn bị chế độ không làm tròn theo D15, chưa tự áp dụng. Các demo thao tác/duyệt chỉ trên fixture test có guard và dọn chính xác. Không reset/clean/commit/push/deploy. Repo có các phiên khác: xác định file đang thay đổi, không ngắt tiến trình hoặc ghi đè việc khác.

## Trạng thái thực hiện

Chưa triển khai S1–S4. Đã đọc source xác nhận các gap trên. Đang hỏi người dùng cách tổng hợp D08 và lựa chọn sửa trực tiếp hay giao Claude. Các câu hỏi này không phải yêu cầu phê duyệt lương/cấu hình.
