(() => {
    'use strict';

    const root = document.getElementById('departmentDashboard');
    if (!root) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hoverFine = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

    const formatters = {
        number: new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }),
        money: new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }),
        percent: new Intl.NumberFormat('vi-VN', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
        days: new Intl.NumberFormat('vi-VN', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    };

    const formatValue = (value, format = 'number') => {
        const numeric = Number(value || 0);
        if (format === 'boolean') return numeric === 1 ? 'Đã check-in' : 'Chưa check-in';
        if (format === 'money') return `${formatters.money.format(numeric)} đ`;
        if (format === 'percent') return `${formatters.percent.format(numeric)}%`;
        if (format === 'days') return `${formatters.days.format(numeric)} ngày`;
        return formatters.number.format(numeric);
    };

    // Đồng hồ trực tiếp.
    const timeNode = document.getElementById('deptLiveTime');
    const dateNode = document.getElementById('deptLiveDate');
    const updateClock = () => {
        const now = new Date();
        if (timeNode) {
            timeNode.textContent = now.toLocaleTimeString('vi-VN', {
                hour: '2-digit',
                minute: '2-digit',
            });
        }
        if (dateNode) {
            dateNode.textContent = now.toLocaleDateString('vi-VN', {
                weekday: 'short',
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        }
    };
    updateClock();
    window.setInterval(updateClock, 30_000);

    // Bộ lọc nhanh.
    const filterForm = document.getElementById('deptFilterForm');
    const periodInput = document.getElementById('deptPeriodInput');
    document.querySelectorAll('.dept-period[data-period]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.dept-period').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            if (periodInput) periodInput.value = button.dataset.period || 'month';
            filterForm?.submit();
        });
    });

    document.querySelectorAll('.dept-date-field input[type="date"]').forEach((input) => {
        input.addEventListener('change', () => {
            if (periodInput) periodInput.value = 'custom';
            document.querySelectorAll('.dept-period').forEach((item) => item.classList.remove('is-active'));
        });
    });

    // Refresh.
    const refreshButton = document.getElementById('deptRefresh');
    refreshButton?.addEventListener('click', () => {
        refreshButton.classList.add('is-spinning');
        window.setTimeout(() => window.location.reload(), 300);
    });

    // Dark mode cục bộ.
    const themeKey = 'ego-department-dashboard-dark';
    const themeButton = document.getElementById('deptThemeToggle');
    const applyTheme = (isDark) => {
        root.classList.toggle('is-dark', isDark);
        const icon = themeButton?.querySelector('i');
        if (icon) icon.className = isDark ? 'bi bi-sun' : 'bi bi-moon-stars';
    };
    applyTheme(window.localStorage.getItem(themeKey) === '1');
    themeButton?.addEventListener('click', () => {
        const isDark = !root.classList.contains('is-dark');
        applyTheme(isDark);
        window.localStorage.setItem(themeKey, isDark ? '1' : '0');
        window.setTimeout(drawChart, 60);
    });

    // Reveal theo viewport.
    const revealNodes = document.querySelectorAll('.dept-reveal');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const observer = new IntersectionObserver((entries, instance) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                instance.unobserve(entry.target);
            });
        }, { threshold: 0.08 });
        revealNodes.forEach((node) => observer.observe(node));
    } else {
        revealNodes.forEach((node) => node.classList.add('is-visible'));
    }

    // Counter animation.
    const animateCounter = (node) => {
        const target = Number(node.dataset.value || 0);
        const format = node.dataset.format || 'number';
        if (!Number.isFinite(target) || reduceMotion || format === 'boolean') {
            node.textContent = formatValue(target, format);
            return;
        }

        const duration = 760;
        const start = performance.now();
        const tick = (time) => {
            const progress = Math.min(1, (time - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            node.textContent = formatValue(target * eased, format);
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    const counters = document.querySelectorAll('.js-dept-counter');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const observer = new IntersectionObserver((entries, instance) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                animateCounter(entry.target);
                instance.unobserve(entry.target);
            });
        }, { threshold: 0.3 });
        counters.forEach((node) => observer.observe(node));
    } else {
        counters.forEach(animateCounter);
    }

    // Spotlight nhẹ.
    const spotlight = document.getElementById('deptSpotlight');
    if (spotlight && hoverFine && !reduceMotion) {
        root.addEventListener('pointermove', (event) => {
            spotlight.style.left = `${event.clientX}px`;
            spotlight.style.top = `${event.clientY}px`;
        }, { passive: true });
    }

    // SVG line chart native.
    const svg = document.getElementById('deptLineChart');
    const chartDataNode = document.getElementById('deptChartData');
    const chartEmpty = document.getElementById('deptChartEmpty');

    const createSvgNode = (name, attrs = {}) => {
        const node = document.createElementNS('http://www.w3.org/2000/svg', name);
        Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));
        return node;
    };

    const parseChartData = () => {
        if (!chartDataNode) return { labels: [], values: [], format: 'number' };
        try {
            const parsed = JSON.parse(chartDataNode.textContent || '{}');
            return {
                labels: Array.isArray(parsed.labels) ? parsed.labels.map(String) : [],
                values: Array.isArray(parsed.values) ? parsed.values.map(Number) : [],
                format: parsed.format || 'number',
            };
        } catch (_) {
            return { labels: [], values: [], format: 'number' };
        }
    };

    let chartData = parseChartData();

    function drawChart() {
        if (!svg) return;

        const values = chartData.values.filter((value) => Number.isFinite(value));
        const labels = chartData.labels.slice(0, values.length);
        svg.innerHTML = '';

        if (!values.length || values.every((value) => value === 0)) {
            svg.style.display = 'none';
            chartEmpty?.classList.add('is-visible');
            return;
        }

        svg.style.display = 'block';
        chartEmpty?.classList.remove('is-visible');

        const width = Math.max(640, svg.parentElement?.clientWidth || 640);
        const height = 260;
        const pad = { top: 24, right: 24, bottom: 42, left: 50 };
        const innerWidth = width - pad.left - pad.right;
        const innerHeight = height - pad.top - pad.bottom;
        const max = Math.max(...values, 1);
        const min = Math.min(...values, 0);
        const range = Math.max(1, max - min);
        const x = (index) => pad.left + (values.length === 1 ? innerWidth / 2 : (index / (values.length - 1)) * innerWidth);
        const y = (value) => pad.top + innerHeight - ((value - min) / range) * innerHeight;

        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('preserveAspectRatio', 'none');

        const defs = createSvgNode('defs');
        const gradient = createSvgNode('linearGradient', {
            id: 'ddChartGradient',
            x1: '0%', y1: '0%', x2: '0%', y2: '100%',
        });
        gradient.appendChild(createSvgNode('stop', { offset: '0%', 'stop-color': 'var(--dd-accent)', 'stop-opacity': '.35' }));
        gradient.appendChild(createSvgNode('stop', { offset: '100%', 'stop-color': 'var(--dd-accent)', 'stop-opacity': '0' }));
        defs.appendChild(gradient);
        svg.appendChild(defs);

        for (let i = 0; i <= 4; i += 1) {
            const lineY = pad.top + (innerHeight / 4) * i;
            svg.appendChild(createSvgNode('line', {
                x1: pad.left,
                y1: lineY,
                x2: width - pad.right,
                y2: lineY,
                class: 'dd-grid-line',
            }));

            const value = max - ((max - min) / 4) * i;
            const label = createSvgNode('text', {
                x: pad.left - 9,
                y: lineY + 3,
                'text-anchor': 'end',
                class: 'dd-axis-label',
            });
            label.textContent = chartData.format === 'money'
                ? `${Math.round(value / 1_000_000).toLocaleString('vi-VN')}tr`
                : Math.round(value).toLocaleString('vi-VN');
            svg.appendChild(label);
        }

        const points = values.map((value, index) => [x(index), y(value)]);
        const linePath = points.map(([px, py], index) => `${index === 0 ? 'M' : 'L'} ${px} ${py}`).join(' ');
        const areaPath = `${linePath} L ${points[points.length - 1][0]} ${pad.top + innerHeight} L ${points[0][0]} ${pad.top + innerHeight} Z`;

        const area = createSvgNode('path', { d: areaPath, class: 'dd-area' });
        const line = createSvgNode('path', { d: linePath, class: 'dd-line' });
        if (!reduceMotion) {
            const length = Math.max(1, line.getTotalLength?.() || 1000);
            line.style.strokeDasharray = String(length);
            line.style.strokeDashoffset = String(length);
            line.style.transition = 'stroke-dashoffset 900ms cubic-bezier(.2,.75,.25,1)';
            requestAnimationFrame(() => requestAnimationFrame(() => { line.style.strokeDashoffset = '0'; }));
        }
        svg.appendChild(area);
        svg.appendChild(line);

        const labelStep = Math.max(1, Math.ceil(labels.length / 8));
        points.forEach(([px, py], index) => {
            const dot = createSvgNode('circle', { cx: px, cy: py, r: 4, class: 'dd-dot' });
            svg.appendChild(dot);

            const hit = createSvgNode('circle', { cx: px, cy: py, r: 14, class: 'dd-hit' });
            const title = createSvgNode('title');
            title.textContent = `${labels[index] || ''}: ${formatValue(values[index], chartData.format)}`;
            hit.appendChild(title);
            svg.appendChild(hit);

            if (index % labelStep === 0 || index === labels.length - 1) {
                const text = createSvgNode('text', {
                    x: px,
                    y: height - 15,
                    'text-anchor': 'middle',
                    class: 'dd-axis-label',
                });
                text.textContent = labels[index] || '';
                svg.appendChild(text);
            }
        });
    }

    drawChart();
    let resizeTimer = null;
    window.addEventListener('resize', () => {
        window.clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(drawChart, 120);
    }, { passive: true });
})();
