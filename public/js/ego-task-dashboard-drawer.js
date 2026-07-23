(() => {
    'use strict';

    const drawer =
        document.getElementById(
            'egoTaskFloat'
        );

    const toggle =
        document.getElementById(
            'egoTaskFloatToggle'
        );

    const closeButton =
        document.getElementById(
            'egoTaskFloatClose'
        );

    if (!drawer || !toggle) {
        return;
    }

    /*
     * Chống khởi tạo hai lần khi browser
     * còn giữ JavaScript cache cũ.
     */
    if (
        drawer.dataset.taskDrawerReady
        === '1'
    ) {
        return;
    }

    drawer.dataset.taskDrawerReady = '1';

    const leaveDrawer =
        document.getElementById(
            'egoLeaveFloat'
        );

    const leaveToggle =
        document.getElementById(
            'egoLeaveFloatToggle'
        );

    const reduceMotion =
        window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

    let autoCloseTimer = null;
    let cycleTimer = null;
    let userInteracted = false;

    const closeLeaveDrawer = () => {
        if (!leaveDrawer) {
            return;
        }

        leaveDrawer.classList.remove(
            'is-open'
        );

        leaveToggle?.setAttribute(
            'aria-expanded',
            'false'
        );
    };

    const setOpen = (
        open,
        automatic = false
    ) => {
        if (open) {
            closeLeaveDrawer();
        }

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
                    5600
                );
        }
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

    closeButton?.addEventListener(
        'click',
        () => {
            userInteracted = true;
            setOpen(false);
        }
    );

    drawer
        .querySelectorAll('a')
        .forEach((link) => {
            link.addEventListener(
                'click',
                () => {
                    userInteracted = true;
                }
            );
        });

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

    if (
        leaveDrawer
        && 'MutationObserver' in window
    ) {
        const observer =
            new MutationObserver(() => {
                if (
                    leaveDrawer.classList.contains(
                        'is-open'
                    )
                    && drawer.classList.contains(
                        'is-open'
                    )
                ) {
                    setOpen(false);
                }
            });

        observer.observe(
            leaveDrawer,
            {
                attributes: true,
                attributeFilter: ['class'],
            }
        );
    }

    window.addEventListener(
        'load',
        () => {
            if (reduceMotion) {
                return;
            }

            /*
             * Admin có popup nghỉ phép:
             * Task xuất hiện sau popup nghỉ phép.
             *
             * Nhân viên không có popup nghỉ phép:
             * Task mở sau 1,2 giây.
             */
            const initialDelay =
                leaveDrawer
                    ? 7200
                    : 1200;

            window.setTimeout(
                () => {
                    if (!userInteracted) {
                        setOpen(true, true);
                    }
                },
                initialDelay
            );

            cycleTimer =
                window.setInterval(
                    () => {
                        if (
                            userInteracted
                            || document.hidden
                        ) {
                            return;
                        }

                        setOpen(true, true);
                    },
                    42000
                );
        },
        {
            once: true,
        }
    );

    document.addEventListener(
        'visibilitychange',
        () => {
            if (document.hidden) {
                window.clearTimeout(
                    autoCloseTimer
                );
            }
        }
    );

    window.addEventListener(
        'beforeunload',
        () => {
            window.clearTimeout(
                autoCloseTimer
            );

            window.clearInterval(
                cycleTimer
            );
        }
    );
})();

/* EGO_FLOAT_DOCK_COORDINATOR_V170_START */
(() => {
    'use strict';

    const boot = () => {
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

        const closeDrawer = (
            drawer,
            toggle
        ) => {
            if (!drawer) {
                return;
            }

            drawer.classList.remove(
                'is-open'
            );

            toggle?.setAttribute(
                'aria-expanded',
                'false'
            );
        };

        /*
         * Luôn bắt đầu ở trạng thái thu gọn.
         * Timer cũ sẽ tự mở đúng thứ tự sau đó.
         */
        closeDrawer(task, taskToggle);
        closeDrawer(leave, leaveToggle);

        if (
            task
            && leave
            && 'MutationObserver' in window
        ) {
            const taskObserver =
                new MutationObserver(() => {
                    if (
                        task.classList.contains(
                            'is-open'
                        )
                    ) {
                        closeDrawer(
                            leave,
                            leaveToggle
                        );
                    }
                });

            const leaveObserver =
                new MutationObserver(() => {
                    if (
                        leave.classList.contains(
                            'is-open'
                        )
                    ) {
                        closeDrawer(
                            task,
                            taskToggle
                        );
                    }
                });

            taskObserver.observe(
                task,
                {
                    attributes: true,
                    attributeFilter: [
                        'class',
                    ],
                }
            );

            leaveObserver.observe(
                leave,
                {
                    attributes: true,
                    attributeFilter: [
                        'class',
                    ],
                }
            );
        }

        taskToggle?.addEventListener(
            'click',
            () => {
                window.setTimeout(
                    () => {
                        if (
                            task?.classList.contains(
                                'is-open'
                            )
                        ) {
                            closeDrawer(
                                leave,
                                leaveToggle
                            );
                        }
                    },
                    0
                );
            }
        );

        leaveToggle?.addEventListener(
            'click',
            () => {
                window.setTimeout(
                    () => {
                        if (
                            leave?.classList.contains(
                                'is-open'
                            )
                        ) {
                            closeDrawer(
                                task,
                                taskToggle
                            );
                        }
                    },
                    0
                );
            }
        );
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
/* EGO_FLOAT_DOCK_COORDINATOR_V170_END */

