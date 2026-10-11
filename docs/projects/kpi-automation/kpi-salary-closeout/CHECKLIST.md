# Checklist bàn giao và nghiệm thu

Cập nhật 07/10/2026 sau khi triển khai trên source local (chưa commit/deploy). Bằng chứng private: `outputs/claude-kpi-agent/salary-closeout-20261007/` (gitignore). **Đã triển khai, chưa áp dụng cấu hình mới**: bản nháp v3 chờ người dùng duyệt.

| Mã | Việc cần đạt | Trạng thái / bằng chứng |
|---|---|---|
| F0-01 | Đọc Git/phiên cùng file, guard DB, source tiền và mutation; chụp baseline private | Đạt. Phiên còn lại idle, sửa source cuối 11:30. Baseline file `baseline/`, DB `before.json`. Luồng tiền: `computePayout` (KPI kỹ thuật, preview) và `payoutRate` (HR) |
| F0-02 | Tái hiện R1/R2 hoặc chứng minh phiên khác đã sửa | Đạt. Ba ca review gốc trên source hiện tại: 3 fail (`r1r2-repro-before.txt`) |
| F1-01 | Chế độ tỷ lệ/bậc có phiên bản; bản thiếu mode giữ bậc lịch sử | Đạt. `salary_structure.payout_mode`; test `test_config_without_mode_keeps_historical_tier_payout` |
| F1-02 | 87,5% trả hệ số 0,875; null khác 0, đúng biên và cờ/trần vượt | Đạt. Test null/0/68/75/87,5/90/100/105 và trần vượt; 3.937.500 → 3.938.000, 14.437.500 → 14.438.000 |
| F1-03 | Cấu hình lưu/validate/preview; bậc không chi phối tiền ở mode tỷ lệ | Đạt. Form có lựa chọn chế độ, bảng bậc bị khóa ở tỷ lệ; xem trước 10 mốc có hệ số, số trước/sau làm tròn. Test + UI local |
| F1-04 | Kỹ thuật và HR dùng cùng bộ quy đổi; không nhân KPI hai lần | Đạt. HR `technical()` gọi `payoutRate` → 0,875; test đối chiếu trang KPI và `PayrollCalculator` |
| F1-05 | Bản nháp mới hiệu lực 10/2026 kế thừa chính xác, không sửa/duyệt bản applied | Đạt. `php artisan kpi:payout-draft --apply` tạo v3 #3 (draft) kế thừa v2, chỉ khác `payout_mode`; v1/v2 giữ hash |
| F2-01 | Sửa rồi đổi về không phục hồi xác nhận; phải gửi và xác nhận lại | Đạt. Ca review gốc đạt sau sửa (`r1r2-repro-after.txt`); test cũng kiểm lượt gửi cũ không sống lại |
| F2-02 | Đổi/khôi phục minh chứng, đấu nối hoặc trả lại không phục hồi xác nhận cũ | Đạt. Test thêm rồi gỡ minh chứng, đổi đấu nối rồi đổi về, trả lại Thi công |
| F2-03 | Nhóm không ảnh hưởng giữ xác nhận; lịch sử không mất | Đạt. Nhóm khác vẫn `confirmed`; `confirmation_history` giữ người/giờ/nguyên nhân + sự kiện `acceptance_kpi_confirmation_invalidated` |
| F3-01 | Nhận đúng kỳ đã khóa ở KPI kỹ thuật và HR có dùng nguồn kỹ thuật | Đạt. Test: phiếu KPI kỹ thuật duyệt; kỳ HR duyệt có dòng `kpi_template=technical`; Excel/người khác/kỳ nháp/tháng khác không khóa |
| F3-02 | Admin không lưu/gửi/confirm/return hoặc nhập legacy/đấu nối qua khóa kỳ | Đạt. Test 11 đường ghi + đổi kỹ sư chính; UI cô lập: Admin thấy lý do khóa, POST trực tiếp bị từ chối |
| F3-03 | Upload/thay/xóa file và đổi nguồn liên quan bị chặn trước ghi | Đạt. Guard trước khi lưu file; không có route xóa hồ sơ bước (đã kiểm). File count không đổi |
| F3-04 | Request bị chặn không đổi DB/file/events; kỳ khác chưa khóa vẫn hoạt động | Đạt. So snapshot bước/hồ sơ/sự kiện/phân công/kỹ sư chính/file; Admin vẫn sửa công trình chưa khóa |
| F4-01 | Công trình mới dùng luồng mới, phân công đúng và hồ sơ giả đủ | Đạt. Local #64 `TEST-KPI-LUONG-20261007-01` (kỹ sư 98, trưởng phòng 97 giao); test xác nhận không có khóa legacy |
| F4-02 | Nháp → gửi → trả bổ sung/xác nhận → nghiệm thu → KPI đúng nguồn/kỳ/người | Đạt trên DB test (test + UI cô lập có Admin duyệt). Local dừng ở "chờ Admin duyệt" theo phạm vi |
| F4-03 | Demo local mới có guard/idempotency/manifest, không sửa mẫu cũ/thông báo | Đạt. `php artisan kpi:demo-salary-site --apply`; test guard/chạy lại; mẫu 63/148/5 việc giữ hash |
| F5-01 | Tiền trước/sau rounding khớp tính tay; HR đối chiếu đúng ngày công/khoản lương | Đạt. Kỹ thuật 3.937.500/3.938.000; HR đủ công 3.937.500 (làm tròn đồng), 13/26 công 1.968.750 |
| F5-02 | Test nghiệp vụ/khóa/quyền và hồi quy; phân loại failure bằng bằng chứng | Đạt. 164 ca: 162 đạt, 2 lỗi HR redirect đã ghi trong STATUS. Nhóm mở rộng 370 ca: 11 lỗi đều thuộc nhóm đã biết |
| F5-03 | UI desktop/mobile và link nguồn đúng quyền; ghi môi trường thực thử | Đạt: local 8001 15/15, cô lập 8012 10/10, mobile 390 px. HR chưa thử trên trình duyệt (chỉ test) |
| F5-04 | Baseline kỳ 08/2026/mẫu cũ/lương/cấu hình đã duyệt giữ nguyên | Đạt. `before.json` ↔ `after.json` |
| F5-05 | Giao link cấu hình nháp/preview/công trình/KPI, cập nhật PROJECT/STATUS | Đạt — PROJECT mục Kết quả |

## Cổng áp dụng cấu hình

Chưa áp dụng. Trang chính và bảng lương HR vẫn dùng **v2 (trả theo bậc)**. Áp dụng khi Admin/Ban lãnh đạo mở Cài đặt KPI → v3 → bấm “Phê duyệt áp dụng”; ghi phiên bản và thời điểm nếu người dùng thực hiện.
