(() => {
    'use strict';

    const boot = () => {
        const media = window.matchMedia(
            '(max-width: 991.98px)'
        );

        const task =
            document.getElementById(
                'egoTaskFloat'
            );

        const leave =
            document.getElementById(
                'egoLeaveFloat'
            );

        const taskToggle =
            document.getElementById(
                'egoTaskFloatToggle'
            );

        const leaveToggle =
            document.getElementById(
                'egoLeaveFloatToggle'
            );

        const taskClose =
            document.getElementById(
                'egoTaskFloatClose'
            );

        const leaveClose =
            document.getElementById(
                'egoLeaveFloatClose'
            );

        const drawers = [
            {
                element: task,
                toggle: taskToggle,
            },
            {
                element: leave,
                toggle: leaveToggle,
            },
        ].filter(
            (item) =>
                item.element
                && item.toggle
        );

        if (drawers.length < 1) {
            return;
        }

        let touchStartY = null;

        const syncBody = () => {
            const open =
                media.matches
                && drawers.some(
                    (item) =>
                        item.element
                            .classList
                            .contains(
                                'is-open'
                            )
                );

            document.body
                .classList
                .toggle(
                    'ego-mobile-notice-open',
                    open
                );
        };

        const closeDrawer = (item) => {
            if (!item?.element) {
                return;
            }

            item.element
                .classList
                .remove(
                    'is-open'
                );

            item.toggle
                ?.setAttribute(
                    'aria-expanded',
                    'false'
                );
        };

        const closeOthers = (
            activeElement
        ) => {
            drawers.forEach((item) => {
                if (
                    item.element
                    !== activeElement
                ) {
                    closeDrawer(item);
                }
            });
        };

        const observer =
            new MutationObserver(
                (mutations) => {
                    mutations.forEach(
                        (mutation) => {
                            const element =
                                mutation.target;

                            if (
                                element
                                    .classList
                                    .contains(
                                        'is-open'
                                    )
                            ) {
                                closeOthers(
                                    element
                                );
                            }
                        }
                    );

                    syncBody();
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

            item.element
                .addEventListener(
                    'touchstart',
                    (event) => {
                        touchStartY =
                            event.touches[0]
                                ?.clientY
                            ?? null;
                    },
                    {
                        passive: true,
                    }
                );

            item.element
                .addEventListener(
                    'touchend',
                    (event) => {
                        if (
                            touchStartY
                            === null
                        ) {
                            return;
                        }

                        const touchEndY =
                            event.changedTouches[0]
                                ?.clientY
                            ?? touchStartY;

                        const distance =
                            touchEndY
                            - touchStartY;

                        touchStartY = null;

                        if (distance > 70) {
                            closeDrawer(item);
                            syncBody();
                        }
                    },
                    {
                        passive: true,
                    }
                );
        });

        document.addEventListener(
            'click',
            (event) => {
                if (!media.matches) {
                    return;
                }

                const openItem =
                    drawers.find(
                        (item) =>
                            item.element
                                .classList
                                .contains(
                                    'is-open'
                                )
                    );

                if (!openItem) {
                    return;
                }

                if (
                    openItem.element
                        .contains(
                            event.target
                        )
                ) {
                    return;
                }

                if (
                    event.target.closest(
                        '#crmTopbar'
                    )
                ) {
                    return;
                }

                closeDrawer(openItem);
                syncBody();
            }
        );

        taskClose?.addEventListener(
            'click',
            () => {
                closeDrawer(
                    drawers.find(
                        (item) =>
                            item.element
                            === task
                    )
                );

                syncBody();
            }
        );

        leaveClose?.addEventListener(
            'click',
            () => {
                closeDrawer(
                    drawers.find(
                        (item) =>
                            item.element
                            === leave
                    )
                );

                syncBody();
            }
        );

        document.addEventListener(
            'keydown',
            (event) => {
                if (
                    event.key
                    !== 'Escape'
                ) {
                    return;
                }

                drawers.forEach(
                    closeDrawer
                );

                syncBody();
            }
        );

        media.addEventListener?.(
            'change',
            () => {
                if (!media.matches) {
                    document.body
                        .classList
                        .remove(
                            'ego-mobile-notice-open'
                        );
                } else {
                    syncBody();
                }
            }
        );

        syncBody();
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
