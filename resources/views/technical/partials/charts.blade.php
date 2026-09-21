{{--
    4 biểu đồ số THẬT (Chart.js đã được layouts/app.blade.php nạp sẵn qua CDN —
    không thêm dependency mới). Không biểu đồ trang trí, không emoji.

    Dữ liệu được truyền qua thẻ <script type="application/json"> rồi đọc bằng
    JSON.parse để không phải nhúng biến PHP vào giữa mã JavaScript.
--}}
@php
    $topRows = $rows->sortByDesc('planned_items')->take(12)->values();

    $chartData = [
        'trend' => $trend,
        'onTime' => [
            'on_time' => (int) $summary['on_time_items'],
            'late' => (int) ($summary['late_items'] + $summary['overdue_items'] + $summary['not_done_items']),
        ],
        'staff' => [
            'labels' => $topRows->pluck('user_name')->map(fn ($name) => \App\Services\Technical\TechnicalWeekPlanService::displayLabel((string) $name))->all(),
            'report_rate' => $topRows->map(fn (array $r): float => (float) ($r['report_rate'] ?? 0))->all(),
            'planned' => $topRows->pluck('planned_items')->map(fn ($v): int => (int) $v)->all(),
            'done' => $topRows->pluck('done_items')->map(fn ($v): int => (int) $v)->all(),
        ],
    ];
@endphp

<div class="tp-charts">
    <div class="tp-chart">
        <p class="tp-chart__title">Kế hoạch so với hoàn thành theo tuần</p>
        <div class="tp-chart__canvas"><canvas id="tpChartTrend"></canvas></div>
    </div>

    <div class="tp-chart">
        <p class="tp-chart__title">Tỷ lệ đúng hạn / chậm hạn</p>
        <div class="tp-chart__canvas"><canvas id="tpChartOnTime"></canvas></div>
    </div>

    <div class="tp-chart">
        <p class="tp-chart__title">Tỷ lệ nộp báo cáo của từng nhân viên (%)</p>
        <div class="tp-chart__canvas"><canvas id="tpChartReport"></canvas></div>
    </div>

    <div class="tp-chart">
        <p class="tp-chart__title">Khối lượng công việc theo nhân viên</p>
        <div class="tp-chart__canvas"><canvas id="tpChartWorkload"></canvas></div>
    </div>
</div>

<script type="application/json" id="tpChartData">@json($chartData)</script>

@push('scripts')
<script>
(function () {
    var holder = document.getElementById('tpChartData');
    if (!holder || typeof Chart === 'undefined') { return; }

    var data;
    try { data = JSON.parse(holder.textContent); } catch (e) { return; }

    /* Bảng màu CRM: navy / aqua / cam cảnh báo / đỏ. */
    var NAVY = '#1f3c88';
    var AQUA = '#12a5a0';
    var AMBER = '#f0b429';
    var RED = '#dc2626';
    var GREY = '#9aa3b2';

    var base = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
    };

    function draw(id, config) {
        var el = document.getElementById(id);
        if (!el) { return; }
        new Chart(el.getContext('2d'), config);
    }

    draw('tpChartTrend', {
        type: 'bar',
        data: {
            labels: data.trend.labels,
            datasets: [
                { label: 'Kế hoạch', data: data.trend.planned, backgroundColor: NAVY },
                { label: 'Hoàn thành', data: data.trend.done, backgroundColor: AQUA }
            ]
        },
        options: base
    });

    draw('tpChartOnTime', {
        type: 'doughnut',
        data: {
            labels: ['Đúng hạn', 'Chậm / chưa xong'],
            datasets: [{
                data: [data.onTime.on_time, data.onTime.late],
                backgroundColor: [AQUA, RED],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } }
        }
    });

    draw('tpChartReport', {
        type: 'bar',
        data: {
            labels: data.staff.labels,
            datasets: [{ label: 'Tỷ lệ nộp báo cáo (%)', data: data.staff.report_rate, backgroundColor: AMBER }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, max: 100 } }
        }
    });

    draw('tpChartWorkload', {
        type: 'bar',
        data: {
            labels: data.staff.labels,
            datasets: [
                { label: 'Đã lên kế hoạch', data: data.staff.planned, backgroundColor: NAVY },
                { label: 'Đã hoàn thành', data: data.staff.done, backgroundColor: GREY }
            ]
        },
        options: base
    });
})();
</script>
@endpush
