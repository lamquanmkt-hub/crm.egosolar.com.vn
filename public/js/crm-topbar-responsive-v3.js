(() => {
    'use strict';

    const init = () => {
        const root =
            document.getElementById(
                'crmTopbar'
            );

        const backdrop =
            document.getElementById(
                'crmTopbarBackdrop'
            );

        if (!root || !backdrop) {
            return;
        }

        const media =
            window.matchMedia(
                '(max-width: 991.98px)'
            );

        const panels = Array.from(
            root.querySelectorAll(
                '[data-crm-panel]'
            )
        );

        const floats = [
            document.getElementById(
                'egoTaskFloat'
            ),

            document.getElementById(
                'egoLeaveFloat'
            ),
        ].filter(Boolean);

        let frame = 0;

        const setVisualHeight = () => {
            const height = Math.round(
                window.visualViewport?.height
                || window.innerHeight
                || document.documentElement
                    .clientHeight
            );

            document.documentElement
                .style
                .setProperty(
                    '--ego-topbar-visual-height',
                    `${height}px`
                );
        };

        const closeFloatingNotices = () => {
            floats.forEach((drawer) => {
                drawer.classList.remove(
                    'is-open'
                );

                drawer
                    .querySelector(
                        '[aria-expanded]'
                    )
                    ?.setAttribute(
                        'aria-expanded',
                        'false'
                    );
            });
        };

        const hasOpenPanel = () =>
            panels.some(
                (panel) =>
                    panel.classList.contains(
                        'is-open'
                    )
                    || panel.getAttribute(
                        'aria-hidden'
                    ) === 'false'
            );

        const sync = () => {
            window.cancelAnimationFrame(
                frame
            );

            frame =
                window.requestAnimationFrame(
                    () => {
                        const mobileOpen =
                            media.matches
                            && hasOpenPanel();

                        document.body
                            .classList
                            .toggle(
                                'crm-topbar-panel-open',
                                mobileOpen
                            );

                        backdrop
                            .classList
                            .toggle(
                                'is-open',
                                mobileOpen
                            );

                        backdrop.setAttribute(
                            'aria-hidden',
                            mobileOpen
                                ? 'false'
                                : 'true'
                        );

                        if (mobileOpen) {
                            closeFloatingNotices();
                        }
                    }
                );
        };

        const panelObserver =
            new MutationObserver(sync);

        panels.forEach((panel) => {
            panelObserver.observe(
                panel,
                {
                    attributes: true,

                    attributeFilter: [
                        'class',
                        'aria-hidden',
                    ],
                }
            );
        });

        root.addEventListener(
            'click',
            (event) => {
                if (
                    event.target.closest(
                        '[data-crm-panel-toggle]'
                    )
                    || event.target.closest(
                        '[data-crm-open-panel]'
                    )
                    || event.target.closest(
                        '[data-crm-panel-close]'
                    )
                ) {
                    window.setTimeout(
                        sync,
                        0
                    );
                }
            }
        );

        backdrop.addEventListener(
            'click',
            () => {
                window.setTimeout(
                    sync,
                    0
                );
            }
        );

        document.addEventListener(
            'keydown',
            (event) => {
                if (event.key === 'Escape') {
                    window.setTimeout(
                        sync,
                        0
                    );
                }
            }
        );

        const floatObserver =
            new MutationObserver(
                () => {
                    if (
                        document.body
                            .classList
                            .contains(
                                'crm-topbar-panel-open'
                            )
                    ) {
                        closeFloatingNotices();
                    }
                }
            );

        floats.forEach((drawer) => {
            floatObserver.observe(
                drawer,
                {
                    attributes: true,

                    attributeFilter: [
                        'class',
                    ],
                }
            );
        });

        const onViewportChange = () => {
            setVisualHeight();

            if (!media.matches) {
                document.body
                    .classList
                    .remove(
                        'crm-topbar-panel-open'
                    );
            }

            sync();
        };

        window.addEventListener(
            'resize',
            onViewportChange,
            {
                passive: true,
            }
        );

        window.visualViewport
            ?.addEventListener(
                'resize',
                onViewportChange,
                {
                    passive: true,
                }
            );

        window.visualViewport
            ?.addEventListener(
                'scroll',
                setVisualHeight,
                {
                    passive: true,
                }
            );

        const logo =
            root.querySelector(
                '[data-crm-brand-logo]'
            );

        const fallback =
            root.querySelector(
                '[data-crm-brand-fallback]'
            );

        logo?.addEventListener(
            'error',
            () => {
                logo.hidden = true;

                fallback
                    ?.classList
                    .add(
                        'is-visible'
                    );
            },
            {
                once: true,
            }
        );

        setVisualHeight();
        sync();
    };

    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            init,
            {
                once: true,
            }
        );
    } else {
        init();
    }
})();
