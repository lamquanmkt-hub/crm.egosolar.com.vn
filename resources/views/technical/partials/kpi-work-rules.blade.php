@php
    $kwnCriterion = app(\App\Services\TechnicalKpi\KpiActionHintService::class)->criterion('timeline');
    $kwnPoints = isset($kwnCriterion['weight']) ? rtrim(rtrim(number_format($kwnCriterion['weight'] * 100, 2, ',', ''), '0'), ',') : null;
    $kwnHours = (int) ($kwnCriterion['submission_hours'] ?? 24);
@endphp
<aside class="alert alert-info small mb-3" data-kpi-work-rules>
    <strong>KPI Tiến độ{{ $kwnPoints !== null ? ' · '.$kwnPoints.' điểm tối đa' : '' }}</strong>
    <div>Trưởng phòng <strong>Giao việc</strong> → kỹ thuật chọn đúng việc trong Báo cáo ngày → bấm <strong>Hoàn tất báo cáo / Gửi duyệt</strong> trong {{ $kwnHours }} giờ kể từ giờ giao.</div>
    <div>Lưu nháp, Bắt đầu công việc, cập nhật % tiến độ hoặc đánh dấu Hoàn thành chưa được tính là nộp báo cáo. Nộp đúng hạn nhận phần điểm của việc; nộp muộn hoặc quá hạn chưa nộp mất phần điểm đó. Việc còn hạn đang chờ tính.</div>
    <div>Điểm chia đều cho các việc đã đến hạn trong kỳ. Chỉ lần gửi đầu được dùng; gửi lại sau yêu cầu sửa không đổi mốc đó. Kế hoạch quản lý mới lưu nháp và việc tự lập chưa vào tiêu chí này.</div>
</aside>
