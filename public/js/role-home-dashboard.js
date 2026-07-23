(() => {
    'use strict';

    const root = document.getElementById('roleHomeDashboard');
    if (!root) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const formatters = {
        number: new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }),
        money: new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }),
        percent: new Intl.NumberFormat('vi-VN', { minimumFractionDigits: 1, maximumFractionDigits: 1 }),
    };

    const formatValue = (value, format) => {
        if (format === 'boolean') return Number(value) === 1 ? 'Đã check-in' : 'Chưa check-in';
        if (format === 'money') return `${formatters.money.format(value)} đ`;
        if (format === 'percent') return `${formatters.percent.format(value)}%`;
        return formatters.number.format(value);
    };

    // Đồng hồ trực tiếp.
    const timeNode = document.getElementById('roleLiveTime');
    const dateNode = document.getElementById('roleLiveDate');
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
    const filterForm = document.getElementById('roleFilterForm');
    const periodInput = document.getElementById('rolePeriodInput');
    document.querySelectorAll('.role-period[data-period]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.role-period').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            if (periodInput) periodInput.value = button.dataset.period || 'month';
            if (filterForm) filterForm.submit();
        });
    });

    document.querySelectorAll('.role-field input[type="date"]').forEach((input) => {
        input.addEventListener('change', () => {
            if (periodInput) periodInput.value = 'custom';
            document.querySelectorAll('.role-period').forEach((item) => item.classList.remove('is-active'));
        });
    });

    // Refresh có hiệu ứng xoay.
    const refreshButton = document.getElementById('roleRefresh');
    refreshButton?.addEventListener('click', () => {
        refreshButton.classList.add('is-loading');
        const icon = refreshButton.querySelector('i');
        if (icon) {
            icon.style.transition = 'transform .55s ease';
            icon.style.transform = 'rotate(360deg)';
        }
        window.setTimeout(() => window.location.reload(), 260);
    });

    // Dark mode chỉ áp dụng cho dashboard này.
    const themeKey = 'ego-role-home-dark';
    const themeButton = document.getElementById('roleThemeToggle');
    const applyTheme = (dark) => {
        root.classList.toggle('is-dark', dark);
        const icon = themeButton?.querySelector('i');
        if (icon) icon.className = dark ? 'bi bi-sun' : 'bi bi-moon-stars';
    };

    applyTheme(window.localStorage.getItem(themeKey) === '1');
    themeButton?.addEventListener('click', () => {
        const dark = !root.classList.contains('is-dark');
        applyTheme(dark);
        window.localStorage.setItem(themeKey, dark ? '1' : '0');
    });

    // Spotlight theo chuột.
    const spotlight = document.getElementById('roleHomeSpotlight');
    if (spotlight && !reduceMotion) {
        root.addEventListener('pointermove', (event) => {
            spotlight.style.left = `${event.clientX}px`;
            spotlight.style.top = `${event.clientY}px`;
        }, { passive: true });
    }

    // Reveal khi vào viewport.
    const revealNodes = document.querySelectorAll('.reveal-card');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.08 });
        revealNodes.forEach((node) => revealObserver.observe(node));
    } else {
        revealNodes.forEach((node) => node.classList.add('is-visible'));
    }

    // Bar chart animation.
    const bars = document.querySelectorAll('.js-bar');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const barObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                window.setTimeout(() => entry.target.classList.add('is-visible'), 90);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.25 });
        bars.forEach((bar) => barObserver.observe(bar));
    } else {
        bars.forEach((bar) => bar.classList.add('is-visible'));
    }

    // Counter animation.
    const counters = document.querySelectorAll('.js-counter');
    const animateCounter = (node) => {
        const target = Number(node.dataset.value || 0);
        const format = node.dataset.format || 'number';
        if (format === 'boolean' || reduceMotion || !Number.isFinite(target)) {
            node.textContent = formatValue(target, format);
            return;
        }

        const duration = 850;
        const start = performance.now();
        const from = 0;
        const tick = (time) => {
            const progress = Math.min(1, (time - start) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            node.textContent = formatValue(from + (target - from) * eased, format);
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };

    if ('IntersectionObserver' in window && !reduceMotion) {
        const counterObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                animateCounter(entry.target);
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.35 });
        counters.forEach((counter) => counterObserver.observe(counter));
    } else {
        counters.forEach(animateCounter);
    }

    // Tilt 3D nhẹ, tự tắt trên thiết bị cảm ứng.
    const canTilt = !reduceMotion && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (canTilt) {
        document.querySelectorAll('.js-tilt, .js-tilt-soft').forEach((card) => {
            const isSoft = card.classList.contains('js-tilt-soft');
            const strength = isSoft ? 2.4 : 5;

            card.addEventListener('pointermove', (event) => {
                const rect = card.getBoundingClientRect();
                const x = (event.clientX - rect.left) / rect.width;
                const y = (event.clientY - rect.top) / rect.height;
                const rotateY = (x - 0.5) * strength * 2;
                const rotateX = (0.5 - y) * strength * 2;
                card.style.transform = `perspective(900px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-3px)`;
            });

            card.addEventListener('pointerleave', () => {
                card.style.transform = '';
            });
        });
    }
})();
