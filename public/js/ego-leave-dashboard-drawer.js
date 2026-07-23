(() => {
    'use strict';

    const drawer =
        document.getElementById(
            'egoLeaveFloat'
        );

    const toggle =
        document.getElementById(
            'egoLeaveFloatToggle'
        );

    const close =
        document.getElementById(
            'egoLeaveFloatClose'
        );

    if (!drawer || !toggle) {
        return;
    }

    const reduceMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    let autoCloseTimer = null;
    let cycleTimer = null;
    let userInteracted = false;

    const resizeCharts = () => {
        window.dispatchEvent(
            new Event('resize')
        );

        if (
            typeof window.Chart === 'undefined'
            || !window.Chart.instances
        ) {
            return;
        }

        const instances =
            window.Chart.instances;

        const charts = Array.isArray(instances)
            ? instances
            : Object.values(instances);

        charts.forEach((chart) => {
            try {
                chart.resize();
            } catch (error) {
                console.debug(
                    'Chart resize skipped',
                    error
                );
            }
        });
    };

    const setOpen = (
        open,
        automatic = false
    ) => {
        drawer.classList.toggle(
            'is-open',
            open
        );

        toggle.setAttribute(
            'aria-expanded',
            open ? 'true' : 'false'
        );

        window.clearTimeout(
            autoCloseTimer
        );

        if (
            open
            && automatic
            && !reduceMotion
        ) {
            autoCloseTimer =
                window.setTimeout(
                    () => {
                        if (!userInteracted) {
                            setOpen(false);
                        }
                    },
                    5200
                );
        }

        window.setTimeout(
            resizeCharts,
            560
        );
    };

    toggle.addEventListener(
        'click',
        () => {
            userInteracted = true;

            setOpen(
                !drawer.classList.contains(
                    'is-open'
                )
            );
        }
    );

    close?.addEventListener(
        'click',
        () => {
            userInteracted = true;
            setOpen(false);
        }
    );

    document.addEventListener(
        'keydown',
        (event) => {
            if (
                event.key === 'Escape'
                && drawer.classList.contains(
                    'is-open'
                )
            ) {
                userInteracted = true;
                setOpen(false);
            }
        }
    );

    window.addEventListener(
        'load',
        () => {
            window.setTimeout(
                resizeCharts,
                240
            );

            window.setTimeout(
                resizeCharts,
                900
            );

            if (reduceMotion) {
                return;
            }

            window.setTimeout(
                () => {
                    if (!userInteracted) {
                        setOpen(true, true);
                    }
                },
                950
            );

            cycleTimer = window.setInterval(
                () => {
                    if (
                        userInteracted
                        || document.hidden
                    ) {
                        return;
                    }

                    setOpen(true, true);
                },
                26000
            );
        },
        {
            once: true,
        }
    );

    document.addEventListener(
        'visibilitychange',
        () => {
            if (
                document.hidden
                && cycleTimer
            ) {
                window.clearTimeout(
                    autoCloseTimer
                );
            }
        }
    );
})();
