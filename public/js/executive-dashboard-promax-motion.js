(() => {
    'use strict';

    const root = document.getElementById('executiveDashboard');

    if (!root || root.dataset.promaxMotionReady === '1') {
        return;
    }

    root.dataset.promaxMotionReady = '1';

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    const desktopPointer = window.matchMedia(
        '(hover: hover) and (pointer: fine)'
    ).matches;

    const addOrbs = () => {
        ['one', 'two', 'three'].forEach((name) => {
            const orb = document.createElement('span');

            orb.className =
                `exec-motion-orb exec-motion-orb--${name}`;

            orb.setAttribute('aria-hidden', 'true');

            root.prepend(orb);
        });
    };

    const addLiveBadge = () => {
        const heading = root.querySelector('h1');

        if (!heading || heading.querySelector('.exec-motion-live')) {
            return;
        }

        const badge = document.createElement('span');

        badge.className = 'exec-motion-live';
        badge.textContent = 'LIVE DATA';

        heading.appendChild(badge);
    };

    const setupSpotlight = () => {
        if (!desktopPointer || reduceMotion) {
            return;
        }

        root.addEventListener('pointermove', (event) => {
            const bounds = root.getBoundingClientRect();

            const x =
                ((event.clientX - bounds.left) / bounds.width) * 100;

            const y =
                ((event.clientY - bounds.top) / bounds.height) * 100;

            root.style.setProperty('--motion-x', `${x}%`);
            root.style.setProperty('--motion-y', `${y}%`);
        }, {
            passive: true,
        });
    };

    const revealElements = () => {
        const elements = root.querySelectorAll([
            '.exec-filter',
            '.exec-filter-bar',
            '.exec-toolbar',
            '.exec-kpi',
            '.exec-action-center',
            '.exec-panel',
            '.exec-section',
            '.exec-chart-card',
            '.exec-card',
        ].join(','));

        elements.forEach((element, index) => {
            element.classList.add('exec-motion-reveal');
            element.style.transitionDelay =
                `${Math.min(index * 35, 280)}ms`;
        });

        if (reduceMotion || !('IntersectionObserver' in window)) {
            elements.forEach((element) => {
                element.classList.add('is-visible');
            });

            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.08,
            rootMargin: '0px 0px -28px',
        });

        elements.forEach((element) => observer.observe(element));
    };

    const setupKpiTilt = () => {
        if (!desktopPointer || reduceMotion) {
            return;
        }

        root.querySelectorAll('.exec-kpi').forEach((card) => {
            card.addEventListener('pointermove', (event) => {
                const rect = card.getBoundingClientRect();

                const px =
                    (event.clientX - rect.left) / rect.width;

                const py =
                    (event.clientY - rect.top) / rect.height;

                const rotateY = (px - 0.5) * 4.5;
                const rotateX = (0.5 - py) * 4.5;

                card.style.setProperty(
                    '--tilt-x',
                    `${rotateX.toFixed(2)}deg`
                );

                card.style.setProperty(
                    '--tilt-y',
                    `${rotateY.toFixed(2)}deg`
                );

                card.style.setProperty(
                    '--card-x',
                    `${(px * 100).toFixed(1)}%`
                );

                card.style.setProperty(
                    '--card-y',
                    `${(py * 100).toFixed(1)}%`
                );
            }, {
                passive: true,
            });

            card.addEventListener('pointerleave', () => {
                card.style.setProperty('--tilt-x', '0deg');
                card.style.setProperty('--tilt-y', '0deg');
                card.style.setProperty('--card-x', '50%');
                card.style.setProperty('--card-y', '50%');
            });
        });
    };

    const parseNumber = (text) => {
        const digits = text.replace(/[^\d-]/g, '');

        if (!digits || digits === '-') {
            return null;
        }

        const value = Number(digits);

        return Number.isFinite(value) ? value : null;
    };

    const formatLikeOriginal = (value, original) => {
        const hasCurrency = /đ/i.test(original);
        const hasPercent = /%/.test(original);
        const hasDotGrouping = /\d\.\d{3}/.test(original);

        const formatted = hasDotGrouping || hasCurrency
            ? new Intl.NumberFormat('vi-VN').format(
                Math.round(value)
            )
            : String(Math.round(value));

        if (hasPercent) {
            return `${formatted}%`;
        }

        if (hasCurrency) {
            return `${formatted} đ`;
        }

        return formatted;
    };

    const animateCounters = () => {
        if (reduceMotion) {
            return;
        }

        const elements = root.querySelectorAll([
            '.exec-kpi__value',
            '.exec-stat__value',
            '[data-countup]',
        ].join(','));

        elements.forEach((element) => {
            if (element.dataset.motionCounted === '1') {
                return;
            }

            const original = element.textContent.trim();
            const target = parseNumber(original);

            if (
                target === null
                || Math.abs(target) < 2
                || original.length > 34
            ) {
                return;
            }

            element.dataset.motionCounted = '1';

            const duration = 850;
            const startedAt = performance.now();

            const tick = (now) => {
                const elapsed = Math.min(
                    1,
                    (now - startedAt) / duration
                );

                const eased =
                    1 - Math.pow(1 - elapsed, 3);

                element.textContent = formatLikeOriginal(
                    target * eased,
                    original
                );

                if (elapsed < 1) {
                    requestAnimationFrame(tick);
                    return;
                }

                element.textContent = original;
            };

            requestAnimationFrame(tick);
        });
    };

    const setupRipples = () => {
        root.querySelectorAll([
            'button',
            '.btn',
            'a[class*="button"]',
            'a[class*="btn"]',
        ].join(',')).forEach((button) => {
            button.addEventListener('click', (event) => {
                if (reduceMotion) {
                    return;
                }

                const style = getComputedStyle(button);

                if (style.position === 'static') {
                    button.style.position = 'relative';
                }

                button.style.overflow = 'hidden';

                const rect = button.getBoundingClientRect();
                const ripple = document.createElement('span');

                ripple.className = 'exec-motion-ripple';

                ripple.style.left =
                    `${event.clientX - rect.left}px`;

                ripple.style.top =
                    `${event.clientY - rect.top}px`;

                ripple.style.width =
                    ripple.style.height =
                        `${Math.max(rect.width, rect.height)}px`;

                button.appendChild(ripple);

                window.setTimeout(() => {
                    ripple.remove();
                }, 680);
            });
        });
    };

    const setupChartReveal = () => {
        if (reduceMotion) {
            return;
        }

        root.querySelectorAll('canvas, svg').forEach((chart) => {
            chart.animate([
                {
                    opacity: 0,
                    transform: 'translateY(10px) scale(.985)',
                },
                {
                    opacity: 1,
                    transform: 'translateY(0) scale(1)',
                },
            ], {
                duration: 700,
                easing: 'cubic-bezier(.2,.72,.18,1)',
                fill: 'both',
                delay: 180,
            });
        });
    };

    addOrbs();
    addLiveBadge();
    setupSpotlight();
    revealElements();
    setupKpiTilt();
    setupRipples();

    window.requestAnimationFrame(() => {
        animateCounters();
        setupChartReveal();
    });
})();
