(() => {
    'use strict';

    const boot = () => {
        const root =
            document.getElementById(
                'crmTopbar'
            );

        const backdrop =
            document.getElementById(
                'crmTopbarBackdrop'
            );

        const mobile =
            window.matchMedia(
                '(max-width: 991.98px)'
            );

        if (!root || !backdrop) {
            return;
        }

        const panels = Array.from(
            root.querySelectorAll(
                '[data-crm-panel]'
            )
        );

        const unlockPage = () => {
            document.body.classList.remove(
                'ego-mobile-notice-open'
            );

            document.documentElement
                .style
                .removeProperty(
                    'overflow'
                );

            document.body
                .style
                .removeProperty(
                    'overflow'
                );

            document.body
                .style
                .removeProperty(
                    'touch-action'
                );
        };

        const isOpen = (panel) => {
            return (
                panel.classList.contains(
                    'is-open'
                )
                || panel.getAttribute(
                    'aria-hidden'
                ) === 'false'
            );
        };

        const sync = () => {
            const opened =
                mobile.matches
                && panels.some(isOpen);

            document.body.classList.toggle(
                'crm-topbar-panel-open',
                opened
            );

            backdrop.classList.toggle(
                'is-open',
                opened
            );

            backdrop.setAttribute(
                'aria-hidden',
                opened ? 'false' : 'true'
            );

            if (!opened) {
                unlockPage();
            }
        };

        const closeAll = () => {
            panels.forEach((panel) => {
                panel.classList.remove(
                    'is-open'
                );

                panel.setAttribute(
                    'aria-hidden',
                    'true'
                );
            });

            root.querySelectorAll(
                '[data-crm-panel-toggle]'
            ).forEach((button) => {
                button.setAttribute(
                    'aria-expanded',
                    'false'
                );
            });

            sync();
        };

        /*
         * Các nút Tin nhắn/Thông báo
         * bên trong bảng Tiện ích.
         */
        root.addEventListener(
            'click',
            (event) => {
                const bridge =
                    event.target.closest(
                        '[data-ego-open-panel]'
                    );

                if (bridge) {
                    event.preventDefault();
                    event.stopPropagation();

                    const name = (
                        bridge.getAttribute(
                            'data-ego-open-panel'
                        )
                        || ''
                    ).replace(
                        /[^a-z-]/gi,
                        ''
                    );

                    const target =
                        root.querySelector(
                            '[data-crm-panel-toggle="'
                            + name
                            + '"]'
                        );

                    target?.click();

                    window.setTimeout(
                        sync,
                        0
                    );

                    return;
                }

                if (
                    event.target.closest(
                        [
                            '[data-crm-panel-toggle]',
                            '[data-crm-open-panel]',
                            '[data-crm-panel-close]',
                        ].join(',')
                    )
                ) {
                    window.setTimeout(
                        sync,
                        0
                    );
                }
            }
        );

        const observer =
            new MutationObserver(sync);

        panels.forEach((panel) => {
            observer.observe(
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

        backdrop.addEventListener(
            'click',
            () => {
                window.setTimeout(
                    sync,
                    0
                );
            }
        );

        window.addEventListener(
            'resize',
            () => {
                if (!mobile.matches) {
                    closeAll();
                } else {
                    sync();
                }
            },
            {
                passive: true,
            }
        );

        /*
         * Safari có thể giữ DOM cũ khi quay lại trang.
         */
        window.addEventListener(
            'pageshow',
            () => {
                closeAll();

                window.scrollTo(
                    0,
                    0
                );
            }
        );

        closeAll();
        unlockPage();
    };

    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            boot,
            {
                once: true,
            }
        );
    } else {
        boot();
    }
})();
