# Checklist đợt Nghiệm thu kỹ thuật

Trạng thái: **đã triển khai A1–A5 trên source local ngày 06/10/2026**, chưa commit/deploy. Mỗi mục chỉ đánh dấu kèm test hoặc ảnh/URL và phạm vi đã kiểm tra. Các mục P2 chính của dự án vẫn mở cho phần vật tư (D08) và bảo hành (D12).

Review sau triển khai: mở lại A3-02/A3-03 do ba ca tái hiện lỗi; xem [REVIEW-20261006.md](REVIEW-20261006.md). Ngày 07/10/2026 đã sửa R1/R2 trong đợt [kpi-salary-closeout](../kpi-salary-closeout/PROJECT.md): ba ca review gốc chạy lại đạt; chờ review độc lập xác nhận trước khi chốt đợt.

| Mã | Trạng thái | Công việc | Bằng chứng |
|---|---|---|---|
| A1-01 | [x] | Xác minh file/schema/route và baseline; bản đồ trường | Baseline file trong `outputs/claude-kpi-agent/acceptance-20261006/baseline/`. Không cần migration: lưu `project_workflow_steps.data.kpi_acceptance` (JSON đã có) + `project_workflow_events`. Thiết kế ghi trong PROJECT, mục Kết quả |
| A2-01 | [x] | Kỹ thuật nhập nháp chất lượng/HSE/EVN-App | Khối "Kết quả nghiệm thu tính KPI" tại bước Nghiệm thu; route `projects-unified.workflow.kpi.save`. Test `test_only_assignee_declares_and_only_reviewer_outside_the_work_confirms`; ảnh UI `03-ky-thuat-luu-nhap.png` |
| A2-02 | [x] | Gửi kiểm tra, chỉ rõ phần thiếu, gắn minh chứng | Trạng thái từng nhóm + "Còn thiếu"; minh chứng dùng loại hồ sơ đã có của bước. Test `test_draft_and_submitted_results_do_not_score_until_manager_confirms`, `test_incomplete_declaration_is_missing_not_failed`; ảnh `04-…` |
| A2-03 | [x] | Lưu từng nhóm, tri-state và cờ ngoài bước không bị ghi đè | `saveData()` chỉ ghi cờ của đúng bước khi form gửi trường; null lưu được. Test `test_saving_one_group_keeps_other_groups_legal_flag_and_can_clear_to_null` |
| A3-01 | [x] | Trưởng phòng xác nhận dữ liệu, tách track duyệt cuối | Server ghi `by/at`; track Admin giữ nguyên. Test `test_manager_confirmation_does_not_approve_acceptance_and_admin_track_is_kept`; UI: trưởng phòng không có nút/POST duyệt bị 403 |
| A3-02 | [x] | Trả bổ sung và sửa lại; xác nhận đúng phiên bản | Sửa R1 ngày 07/10 (đợt `kpi-salary-closeout`): nhóm đổi nội dung/minh chứng/đấu nối/lịch sử trả lại thì xác nhận chuyển sang `confirmation_history` và lượt gửi của nhóm bị hủy — đổi về không sống lại. Ca review gốc chạy lại trên source: 3/3 đạt (trước sửa 3 fail); `TechnicalKpiSalaryCloseoutTest` R1 2 ca đạt, nhóm không sửa giữ xác nhận |
| A3-03 | [x] | Chặn tự xác nhận, ngoài phạm vi và bước/kỳ khóa | Sửa R2 ngày 07/10: `kpiSourceLock()` (phiếu KPI kỹ thuật + kỳ lương HR đã duyệt dùng KPI kỹ thuật) chặn mọi người kể cả Admin trước khi ghi: lưu/gửi/xác nhận/trả, upload Khảo sát/Đề xuất/Nghiệm thu, dữ liệu Nghiệm thu, đấu nối, trả lại, phân công, duyệt lại, ngoại lệ, đổi kỹ sư chính. Test R2 4 ca đạt; UI cô lập: Admin bị chặn, nội dung giữ nguyên |
| A4-01 | [x] | KS02 đọc phản ánh đã kiểm tra và lịch sử trả lại | `ProjectKpiLinkService::acceptanceKpiResult()`. Test `test_quality_uses_checked_complaint_and_real_revision_history` |
| A4-02 | [x] | KS04 đọc 5 HSE đã kiểm tra | Test `test_hse_needs_all_five_items_confirmed` (đủ 5 mục = 10 điểm tháng 10), `test_legacy_workflow_hse_counts_only_reviewed_full_checklist` |
| A4-03 | [x] | KS05 dùng xác nhận EVN/App và yêu cầu đấu nối | Test `test_monitoring_link_alone_is_not_evn_app_pass`, `test_evn_needs_grid_decision_and_grid_test_when_required` (5 điểm tháng 10), `test_monitoring_link_alone_does_not_pass_evn_app` |
| A4-04 | [x] | Ghi chú tại Nghiệm thu + nguồn trên trang KPI | Ghi chú dùng kết quả adapter chung + trọng số bộ chấm của kỳ; áp dụng mọi công trình. Test `test_acceptance_screen_shows_role_specific_actions_and_kpi_note`; ảnh `10-…`, `12-nguon-*.png` |
| A5-01 | [x] | Lint/test mới và hồi quy trên egosolar_test | Lint 5 file PHP + Blade biên dịch đạt. `TechnicalAcceptanceKpiFlowTest` + 3 bộ hồi quy: **103/103, 659 assertions**. Riêng 3 bộ hồi quy: **83/83, 409 assertions** sau khi đổi 5 ca cũ (lý do trong PROJECT) |
| A5-02 | [x] | UI nhập → gửi → xác nhận → duyệt → điểm | Chrome thật, 4 vai trò, server cô lập cổng 8012 + `egosolar_test`: **26/26 bước đạt**, ảnh trước/sau trong `outputs/claude-kpi-agent/acceptance-20261006/ui/` |
| A5-03 | [x] | Bảo toàn snapshot/cấu hình/mẫu local đã khóa | Đọc lại DB local: lương 08/2026 vẫn 12 dòng `excel_snapshot`, nháp; cấu hình tháng 10 vẫn nháp; mẫu 63 không đổi dữ liệu, KPI vẫn đạt 3 tiêu chí theo dữ liệu cũ |
| A5-04 | [x] | Bàn giao cách dùng và cập nhật PROJECT/STATUS | PROJECT mục Kết quả, STATUS mục 06/10/2026 |

Chưa nằm trong đợt này: công thức vật tư D08, chính sách bảo hành D12, duyệt cấu hình/payout, hồ sơ lương và chi trả, HCNS/kho/Sales.
