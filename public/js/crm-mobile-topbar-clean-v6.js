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

        if (!root || !mobile.matches) {
            return;
        }

        const panels = Array.from(
            root.querySelectorAll(
                '[data-crm-panel]'
            )
        );

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

            backdrop?.classList.remove(
                'is-open'
            );

            backdrop?.setAttribute(
                'aria-hidden',
                'true'
            );

            document.body.classList.remove(
                'crm-topbar-panel-open',
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

        /*
         * Các nút bên trong Tiện ích:
         * Tin nhắn và Thông báo.
         */
        root.querySelectorAll(
            '[data-ego-open-panel]'
        ).forEach((bridge) => {
            bridge.addEventListener(
                'click',
                (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const name =
                        bridge.getAttribute(
                            'data-ego-open-panel'
                        );

                    if (!name) {
                        return;
                    }

                    const target =
                        root.querySelector(
                            '[data-crm-panel-toggle="'
                            + CSS.escape(name)
                            + '"]'
                        );

                    target?.click();
                }
            );
        });

        /*
         * Nút menu sidebar luôn hoạt động.
         */
        const sidebarButton =
            document.getElementById(
                'openSidebarBtn'
            );

        sidebarButton?.addEventListener(
            'click',
            () => {
                closeAll();

                window.setTimeout(
                    () => {
                        window.EgoSidebar
                            ?.open?.();
                    },
                    0
                );
            }
        );

        /*
         * Safari giữ trạng thái cũ khi Back/Forward.
         */
        window.addEventListener(
            'pageshow',
            closeAll
        );

        /*
         * Backdrop không được phép tồn tại
         * trên mobile.
         */
        const observer =
            new MutationObserver(() => {
                backdrop?.classList.remove(
                    'is-open'
                );

                backdrop?.setAttribute(
                    'aria-hidden',
                    'true'
                );

                document.body.classList.remove(
                    'crm-topbar-panel-open',
                    'ego-mobile-notice-open'
                );
            });

        if (backdrop) {
            observer.observe(
                backdrop,
                {
                    attributes: true,

                    attributeFilter: [
                        'class',
                        'aria-hidden',
                    ],
                }
            );
        }

        closeAll();
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
