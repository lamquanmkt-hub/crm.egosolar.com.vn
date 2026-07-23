(() => {
    'use strict';

    const boot = () => {
        const media = window.matchMedia(
            '(max-width: 991.98px)'
        );

        if (!media.matches) {
            return;
        }

        const drawers = [
            {
                element:
                    document.getElementById(
                        'egoTaskFloat'
                    ),

                toggle:
                    document.getElementById(
                        'egoTaskFloatToggle'
                    ),

                close:
                    document.getElementById(
                        'egoTaskFloatClose'
                    ),
            },

            {
                element:
                    document.getElementById(
                        'egoLeaveFloat'
                    ),

                toggle:
                    document.getElementById(
                        'egoLeaveFloatToggle'
                    ),

                close:
                    document.getElementById(
                        'egoLeaveFloatClose'
                    ),
            },
        ].filter(
            (item) =>
                item.element
                && item.toggle
        );

        if (drawers.length < 1) {
            return;
        }

        let manuallyOpened = null;

        const unlockBody = () => {
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

        const closeItem = (item) => {
            if (!item) {
                return;
            }

            item.element.classList.remove(
                'is-open'
            );

            item.toggle.setAttribute(
                'aria-expanded',
                'false'
            );

            if (
                manuallyOpened
                === item.element
            ) {
                manuallyOpened = null;
            }
        };

        const closeAll = (
            except = null
        ) => {
            drawers.forEach((item) => {
                if (
                    item.element
                    !== except
                ) {
                    closeItem(item);
                }
            });

            unlockBody();
        };

        /*
         * Chặn listener cũ ở capture phase.
         */
        drawers.forEach((item) => {
            item.toggle.addEventListener(
                'click',
                (event) => {
                    if (!media.matches) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();

                    const willOpen =
                        !item.element
                            .classList
                            .contains(
                                'is-open'
                            );

                    closeAll(
                        willOpen
                            ? item.element
                            : null
                    );

                    if (willOpen) {
                        manuallyOpened =
                            item.element;

                        item.element
                            .classList
                            .add(
                                'is-open'
                            );

                        item.toggle
                            .setAttribute(
                                'aria-expanded',
                                'true'
                            );
                    } else {
                        closeItem(item);
                    }

                    unlockBody();
                },
                true
            );

            item.close?.addEventListener(
                'click',
                (event) => {
                    if (!media.matches) {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();

                    closeItem(item);
                    unlockBody();
                },
                true
            );
        });

        /*
         * Timer cũ thêm is-open sẽ bị đóng lại,
         * chỉ cho phép mở bằng thao tác người dùng.
         */
        const observer =
            new MutationObserver(
                (mutations) => {
                    mutations.forEach(
                        (mutation) => {
                            const element =
                                mutation.target;

                            if (
                                !element
                                    .classList
                                    .contains(
                                        'is-open'
                                    )
                            ) {
                                return;
                            }

                            if (
                                element
                                !== manuallyOpened
                            ) {
                                element
                                    .classList
                                    .remove(
                                        'is-open'
                                    );

                                element
                                    .querySelector(
                                        '[aria-expanded]'
                                    )
                                    ?.setAttribute(
                                        'aria-expanded',
                                        'false'
                                    );
                            }
                        }
                    );

                    unlockBody();
                }
            );

        drawers.forEach((item) => {
            observer.observe(
                item.element,
                {
                    attributes: true,
                    attributeFilter: [
                        'class',
                    ],
                }
            );
        });

        /*
         * Bấm ngoài panel thì đóng.
         */
        document.addEventListener(
            'click',
            (event) => {
                const active = drawers.find(
                    (item) =>
                        item.element
                            .classList
                            .contains(
                                'is-open'
                            )
                );

                if (!active) {
                    return;
                }

                if (
                    active.element.contains(
                        event.target
                    )
                ) {
                    return;
                }

                closeItem(active);
                unlockBody();
            },
            true
        );

        document.addEventListener(
            'keydown',
            (event) => {
                if (event.key !== 'Escape') {
                    return;
                }

                closeAll();
            }
        );

        /*
         * Luôn khôi phục trạng thái an toàn
         * sau khi các script cũ chạy timer.
         */
        closeAll();

        window.setTimeout(
            closeAll,
            300
        );

        window.setTimeout(
            closeAll,
            1400
        );

        window.setTimeout(
            closeAll,
            7600
        );

        window.setInterval(
            unlockBody,
            1500
        );
    };

    if (
        document.readyState
        === 'loading'
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
