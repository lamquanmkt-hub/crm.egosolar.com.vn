(() => {
    'use strict';

    const root = document.getElementById('executiveDashboard');
    const data = window.EGO_EXECUTIVE_DASHBOARD || null;

    if (!root || !data) {
        return;
    }

    const charts = new Map();
    const darkKey = 'ego_executive_dashboard_dark';
    const filterForm = document.getElementById('executiveFilterForm');
    const darkButton = document.getElementById('execDarkMode');
    const refreshButton = document.getElementById('execRefresh');

    const money = (value) => new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 0,
    }).format(Number(value || 0)) + ' đ';

    const compactMoney = (value) => {
        const amount = Number(value || 0);
        if (Math.abs(amount) >= 1_000_000_000) {
            return (amount / 1_000_000_000).toFixed(1).replace('.', ',') + ' tỷ';
        }
        if (Math.abs(amount) >= 1_000_000) {
            return (amount / 1_000_000).toFixed(0) + ' tr';
        }
        return new Intl.NumberFormat('vi-VN').format(amount);
    };

    const isDark = () => document.body.classList.contains('ego-executive-dark');
    const textColor = () => isDark() ? 'rgba(225,238,247,.76)' : 'rgba(38,59,80,.72)';
    const gridColor = () => isDark() ? 'rgba(225,238,247,.09)' : 'rgba(16,42,67,.08)';

    const destroyChart = (key) => {
        const chart = charts.get(key);
        if (chart) {
            chart.destroy();
            charts.delete(key);
        }
    };

    const baseTooltip = {
        backgroundColor: isDark() ? '#102536' : '#102a43',
        titleColor: '#ffffff',
        bodyColor: '#ffffff',
        borderWidth: 0,
        padding: 10,
        cornerRadius: 8,
        displayColors: true,
    };

    const renderTrend = () => {
        const canvas = document.getElementById('executiveTrendChart');
        if (!canvas || typeof window.Chart === 'undefined') return;
        destroyChart('trend');

        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: data.chart.labels || [],
                datasets: [
                    {
                        type: 'bar',
                        label: 'Đơn hàng thương mại',
                        data: data.chart.commercial || [],
                        backgroundColor: 'rgba(7,137,189,.72)',
                        borderColor: '#0789bd',
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 28,
                    },
                    {
                        type: 'bar',
                        label: 'Công trình',
                        data: data.chart.project || [],
                        backgroundColor: 'rgba(124,92,196,.62)',
                        borderColor: '#7c5cc4',
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 28,
                    },
                    {
                        type: 'line',
                        label: 'Tiền đã thu',
                        data: data.chart.collected || [],
                        borderColor: '#148a56',
                        backgroundColor: 'rgba(20,138,86,.10)',
                        borderWidth: 2.5,
                        pointRadius: 2.5,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#ffffff',
                        pointBorderWidth: 2,
                        tension: .32,
                        fill: false,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 420 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...baseTooltip,
                        callbacks: {
                            label: (context) => `${context.dataset.label}: ${money(context.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        stacked: false,
                        grid: { display: false },
                        ticks: { color: textColor(), maxRotation: 0, autoSkip: true },
                        border: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor() },
                        ticks: { color: textColor(), callback: compactMoney },
                        border: { display: false },
                    },
                },
            },
        });

        charts.set('trend', chart);
    };

    const renderComposition = () => {
        const canvas = document.getElementById('executiveCompositionChart');
        if (!canvas || typeof window.Chart === 'undefined') return;
        destroyChart('composition');

        const values = data.composition.values || [0, 0];
        const chart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: data.composition.labels || [],
                datasets: [{
                    data: values,
                    backgroundColor: ['#0789bd', '#7c5cc4'],
                    borderColor: isDark() ? '#0d1d2b' : '#ffffff',
                    borderWidth: 4,
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                animation: { duration: 420 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...baseTooltip,
                        callbacks: {
                            label: (context) => `${context.label}: ${money(context.parsed)}`,
                        },
                    },
                },
            },
        });

        charts.set('composition', chart);
    };

    const renderDebt = () => {
        const canvas = document.getElementById('executiveDebtChart');
        if (!canvas || typeof window.Chart === 'undefined') return;
        destroyChart('debt');

        const aging = data.debtAging || {};
        const chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: ['Chưa đến hạn', '1–7 ngày', '8–30 ngày', 'Trên 30 ngày'],
                datasets: [{
                    data: [aging.current || 0, aging['1_7'] || 0, aging['8_30'] || 0, aging.over_30 || 0],
                    backgroundColor: ['#7aaec3', '#e5ad4d', '#e27b48', '#d9485f'],
                    borderRadius: 5,
                    borderSkipped: false,
                    maxBarThickness: 34,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 420 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        ...baseTooltip,
                        callbacks: { label: (context) => money(context.parsed.x) },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: gridColor() },
                        ticks: { color: textColor(), callback: compactMoney },
                        border: { display: false },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { color: textColor() },
                        border: { display: false },
                    },
                },
            },
        });

        charts.set('debt', chart);
    };

    const renderCharts = () => {
        if (typeof window.Chart === 'undefined') return;
        Chart.defaults.font.family = '"Be Vietnam Pro", system-ui, sans-serif';
        Chart.defaults.color = textColor();
        renderTrend();
        renderComposition();
        renderDebt();
    };

    const applyDark = (enabled) => {
        document.body.classList.toggle('ego-executive-dark', enabled);
        if (darkButton) {
            darkButton.innerHTML = enabled
                ? '<i class="bi bi-sun"></i><span>Light mode</span>'
                : '<i class="bi bi-moon-stars"></i><span>Dark mode</span>';
            darkButton.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        }
        renderCharts();
    };

    const storedDark = localStorage.getItem(darkKey) === '1';
    applyDark(storedDark);

    darkButton?.addEventListener('click', () => {
        const enabled = !isDark();
        localStorage.setItem(darkKey, enabled ? '1' : '0');
        applyDark(enabled);
    });

    refreshButton?.addEventListener('click', () => {
        refreshButton.classList.add('is-loading');
        refreshButton.setAttribute('disabled', 'disabled');
        window.location.reload();
    });

    root.querySelectorAll('.exec-period-button').forEach((button) => {
        button.addEventListener('click', () => {
            if (!filterForm) return;
            const periodInput = filterForm.querySelector('input[name="period"]');
            if (periodInput) periodInput.value = button.dataset.period || 'month';
            const fromInput = filterForm.querySelector('input[name="from"]');
            const toInput = filterForm.querySelector('input[name="to"]');
            if (fromInput) fromInput.removeAttribute('name');
            if (toInput) toInput.removeAttribute('name');
            filterForm.requestSubmit();
        });
    });

    filterForm?.addEventListener('submit', () => {
        filterForm.classList.add('is-loading');
        filterForm.querySelectorAll('button').forEach((control) => {
            control.setAttribute('disabled', 'disabled');
        });
    });

    window.addEventListener('beforeunload', () => {
        charts.forEach((chart) => chart.destroy());
        charts.clear();
    }, { once: true });
})();
