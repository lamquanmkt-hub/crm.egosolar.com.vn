(() => {
    'use strict';

    const state = {
        animating: false,
        wheelDelta: 0,
        wheelResetTimer: null,
        wheelLockUntil: 0,
        touchStartX: null,
        touchStartY: null,
        touchLastX: null,
        touchHorizontal: false,
    };

    const qsa = (selector, root = document) =>
        Array.from(root.querySelectorAll(selector));

    const viewport = () =>
        document.querySelector(
            '#workspaceAppViewport, .ws5-app-viewport'
        );

    const allPages = () =>
        qsa('[data-app-page], .ws5-app-page');

    const visiblePages = () =>
        allPages().filter((page) => {
            if (page.hidden) return false;

            const cards = qsa('[data-app-card]', page);

            return cards.length === 0
                || cards.some((card) => !card.hidden);
        });

    const currentIndex = () => {
        const pages = visiblePages();
        const found = pages.findIndex((page) =>
            page.classList.contains('is-active')
        );

        return found >= 0 ? found : 0;
    };

    const pagination = () =>
        document.getElementById('workspacePagination');

    const dots = () =>
        qsa(
            '#workspacePagination [data-page-dot], '
            + '#workspacePagination .ws5-page-dot'
        );

    const syncDots = (index) => {
        const pager = pagination();
        const pages = visiblePages();

        if (!pager) return;

        if (pages.length <= 1) {
            pager.innerHTML = '';
            return;
        }

        if (dots().length !== pages.length) {
            pager.innerHTML = pages.map((_, dotIndex) => {
                const active = dotIndex === index
                    ? ' is-active'
                    : '';

                return (
                    '<button type="button" '
                    + 'class="ws5-page-dot' + active + '" '
                    + 'data-page-dot="' + dotIndex + '" '
                    + 'aria-label="Trang ứng dụng ' + (dotIndex + 1) + '" '
                    + 'aria-current="'
                    + (dotIndex === index ? 'page' : 'false')
                    + '"></button>'
                );
            }).join('');

            return;
        }

        dots().forEach((dot, dotIndex) => {
            const active = dotIndex === index;

            dot.classList.toggle('is-active', active);
            dot.setAttribute(
                'aria-current',
                active ? 'page' : 'false'
            );
        });
    };

    const syncArrows = (index) => {
        const pages = visiblePages();
        const previous = document.getElementById(
            'workspacePagePrev'
        );
        const next = document.getElementById(
            'workspacePageNext'
        );

        if (previous) {
            previous.disabled = index <= 0;
        }

        if (next) {
            next.disabled = index >= pages.length - 1;
        }
    };

    const clearPageInlineStyles = (page) => {
        [
            'position',
            'inset',
            'width',
            'height',
            'z-index',
            'opacity',
            'transform',
            'pointer-events',
            'will-change',
        ].forEach((property) => {
            page.style.removeProperty(property);
        });
    };

    const directActivate = (requestedIndex) => {
        const pages = visiblePages();

        if (!pages.length) return false;

        const index = Math.max(
            0,
            Math.min(requestedIndex, pages.length - 1)
        );

        allPages().forEach((page) => {
            page.classList.remove('is-active');
            page.setAttribute('aria-hidden', 'true');
            clearPageInlineStyles(page);
        });

        pages[index].classList.add('is-active');
        pages[index].setAttribute('aria-hidden', 'false');

        syncDots(index);
        syncArrows(index);

        return true;
    };

    const animateTo = async (
        requestedIndex,
        direction = null
    ) => {
        const pages = visiblePages();
        const oldIndex = currentIndex();

        if (
            state.animating
            || pages.length <= 1
        ) {
            return false;
        }

        const newIndex = Math.max(
            0,
            Math.min(requestedIndex, pages.length - 1)
        );

        if (newIndex === oldIndex) {
            syncDots(oldIndex);
            syncArrows(oldIndex);
            return false;
        }

        const oldPage = pages[oldIndex];
        const newPage = pages[newIndex];
        const host = viewport();

        if (!oldPage || !newPage || !host) {
            return directActivate(newIndex);
        }

        state.animating = true;

        const moveDirection = direction
            ?? (newIndex > oldIndex ? 1 : -1);

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

        if (
            reducedMotion
            || typeof oldPage.animate !== 'function'
            || typeof newPage.animate !== 'function'
        ) {
            directActivate(newIndex);
            state.animating = false;
            return true;
        }

        const oldHeight = Math.max(
            oldPage.getBoundingClientRect().height,
            host.getBoundingClientRect().height
        );

        host.style.setProperty(
            'min-height',
            oldHeight + 'px'
        );

        oldPage.classList.add('is-active');
        oldPage.setAttribute('aria-hidden', 'false');

        newPage.classList.add('is-active');
        newPage.setAttribute('aria-hidden', 'false');

        [oldPage, newPage].forEach((page) => {
            page.style.setProperty(
                'position',
                'absolute',
                'important'
            );
            page.style.setProperty(
                'inset',
                '0',
                'important'
            );
            page.style.setProperty(
                'width',
                '100%',
                'important'
            );
            page.style.setProperty(
                'will-change',
                'transform, opacity',
                'important'
            );
        });

        oldPage.style.setProperty(
            'z-index',
            '2',
            'important'
        );

        newPage.style.setProperty(
            'z-index',
            '3',
            'important'
        );

        newPage.style.setProperty(
            'pointer-events',
            'none',
            'important'
        );

        const distance = Math.min(
            88,
            Math.max(48, host.clientWidth * 0.12)
        );

        const duration = 360;
        const easing = 'cubic-bezier(.22,.75,.24,1)';

        const oldAnimation = oldPage.animate(
            [
                {
                    opacity: 1,
                    transform:
                        'translate3d(0,0,0) scale(1)',
                },
                {
                    opacity: 0,
                    transform:
                        'translate3d('
                        + (-moveDirection * distance)
                        + 'px,0,0) scale(.985)',
                },
            ],
            {
                duration,
                easing,
                fill: 'forwards',
            }
        );

        const newAnimation = newPage.animate(
            [
                {
                    opacity: 0,
                    transform:
                        'translate3d('
                        + (moveDirection * distance)
                        + 'px,0,0) scale(.985)',
                },
                {
                    opacity: 1,
                    transform:
                        'translate3d(0,0,0) scale(1)',
                },
            ],
            {
                duration,
                easing,
                fill: 'forwards',
            }
        );

        syncDots(newIndex);
        syncArrows(newIndex);

        try {
            await Promise.all([
                oldAnimation.finished,
                newAnimation.finished,
            ]);
        } catch (_) {
            // Animation may be cancelled during resize/navigation.
        }

        allPages().forEach((page) => {
            page.classList.remove('is-active');
            page.setAttribute('aria-hidden', 'true');
            clearPageInlineStyles(page);
        });

        newPage.classList.add('is-active');
        newPage.setAttribute('aria-hidden', 'false');

        host.style.removeProperty('min-height');

        syncDots(newIndex);
        syncArrows(newIndex);

        state.animating = false;

        return true;
    };

    const canMove = (direction) => {
        const pages = visiblePages();
        const index = currentIndex();

        if (pages.length <= 1) return false;

        return direction > 0
            ? index < pages.length - 1
            : index > 0;
    };

    const move = (direction) => {
        if (!canMove(direction)) return false;

        animateTo(
            currentIndex() + direction,
            direction
        );

        return true;
    };

    const applyAvatar = () => {
        const meta = document.querySelector(
            'meta[name="ego-workspace-avatar"]'
        );

        const avatarUrl = (meta?.content || '').trim();

        if (!avatarUrl) return;

        qsa('.ws5-avatar').forEach((avatar) => {
            if (
                avatar.querySelector(
                    'img[data-ego-workspace-avatar]'
                )
            ) {
                return;
            }

            const fallback = avatar.textContent.trim();
            const image = new Image();

            image.alt = 'Ảnh đại diện';
            image.decoding = 'async';
            image.loading = 'eager';
            image.dataset.egoWorkspaceAvatar = '1';

            image.addEventListener(
                'load',
                () => avatar.replaceChildren(image),
                { once: true }
            );

            image.addEventListener(
                'error',
                () => {
                    avatar.textContent = fallback;
                },
                { once: true }
            );

            image.src = avatarUrl;
        });
    };

    const bindControls = () => {
        if (
            document.documentElement
                .dataset.egoWorkspaceControlsV122 === '1'
        ) {
            return;
        }

        document.documentElement
            .dataset.egoWorkspaceControlsV122 = '1';

        /*
         * Capture phase để controller V5 cũ không chạy thêm lần nữa.
         * Không chạm vào link ứng dụng.
         */
        document.addEventListener(
            'click',
            (event) => {
                const previous = event.target.closest(
                    '#workspacePagePrev'
                );

                const next = event.target.closest(
                    '#workspacePageNext'
                );

                const dot = event.target.closest(
                    '#workspacePagination '
                    + '[data-page-dot], '
                    + '#workspacePagination '
                    + '.ws5-page-dot'
                );

                if (!previous && !next && !dot) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (
                    typeof event.stopImmediatePropagation
                    === 'function'
                ) {
                    event.stopImmediatePropagation();
                }

                if (previous) {
                    move(-1);
                    return;
                }

                if (next) {
                    move(1);
                    return;
                }

                const dotIndex = Number(
                    dot.dataset.pageDot
                );

                const index = Number.isFinite(dotIndex)
                    ? dotIndex
                    : Math.max(0, dots().indexOf(dot));

                animateTo(
                    index,
                    index > currentIndex() ? 1 : -1
                );
            },
            true
        );
    };

    const bindWheel = () => {
        const host = viewport();

        if (
            !host
            || host.dataset.egoWheelV122 === '1'
        ) {
            return;
        }

        host.dataset.egoWheelV122 = '1';

        host.addEventListener(
            'wheel',
            (event) => {
                if (
                    window.matchMedia(
                        '(max-width: 700px)'
                    ).matches
                    || visiblePages().length <= 1
                    || state.animating
                ) {
                    return;
                }

                const rawDelta =
                    Math.abs(event.deltaX)
                    > Math.abs(event.deltaY)
                        ? event.deltaX
                        : event.deltaY;

                if (!rawDelta) return;

                const direction = rawDelta > 0
                    ? 1
                    : -1;

                if (!canMove(direction)) {
                    state.wheelDelta = 0;
                    return;
                }

                event.preventDefault();

                if (
                    Date.now()
                    < state.wheelLockUntil
                ) {
                    return;
                }

                state.wheelDelta += rawDelta;

                window.clearTimeout(
                    state.wheelResetTimer
                );

                state.wheelResetTimer =
                    window.setTimeout(() => {
                        state.wheelDelta = 0;
                    }, 150);

                if (
                    Math.abs(state.wheelDelta) < 42
                ) {
                    return;
                }

                const finalDirection =
                    state.wheelDelta > 0 ? 1 : -1;

                state.wheelDelta = 0;
                state.wheelLockUntil =
                    Date.now() + 470;

                move(finalDirection);
            },
            { passive: false }
        );
    };

    const bindTouch = () => {
        const host = viewport();

        if (
            !host
            || host.dataset.egoTouchV122 === '1'
        ) {
            return;
        }

        host.dataset.egoTouchV122 = '1';

        host.addEventListener(
            'touchstart',
            (event) => {
                if (state.animating) return;

                const touch = event.touches?.[0];

                if (!touch) return;

                state.touchStartX = touch.clientX;
                state.touchStartY = touch.clientY;
                state.touchLastX = touch.clientX;
                state.touchHorizontal = false;
            },
            { passive: true }
        );

        host.addEventListener(
            'touchmove',
            (event) => {
                const touch = event.touches?.[0];

                if (
                    !touch
                    || state.touchStartX === null
                    || state.touchStartY === null
                    || state.animating
                ) {
                    return;
                }

                const dx =
                    touch.clientX - state.touchStartX;

                const dy =
                    touch.clientY - state.touchStartY;

                state.touchLastX = touch.clientX;

                if (!state.touchHorizontal) {
                    state.touchHorizontal =
                        Math.abs(dx) > 10
                        && Math.abs(dx)
                            > Math.abs(dy) * 1.08;
                }

                if (!state.touchHorizontal) {
                    return;
                }

                event.preventDefault();

                const page =
                    visiblePages()[currentIndex()];

                if (!page) return;

                const resisted =
                    canMove(dx < 0 ? 1 : -1)
                        ? dx * 0.34
                        : dx * 0.12;

                page.style.setProperty(
                    'transform',
                    'translate3d('
                    + resisted
                    + 'px,0,0) scale(.994)',
                    'important'
                );

                page.style.setProperty(
                    'opacity',
                    String(
                        Math.max(
                            .78,
                            1 - Math.abs(resisted) / 420
                        )
                    ),
                    'important'
                );
            },
            { passive: false }
        );

        host.addEventListener(
            'touchend',
            () => {
                const page =
                    visiblePages()[currentIndex()];

                const dx =
                    state.touchLastX !== null
                    && state.touchStartX !== null
                        ? state.touchLastX
                            - state.touchStartX
                        : 0;

                state.touchStartX = null;
                state.touchStartY = null;
                state.touchLastX = null;

                if (page) {
                    page.style.removeProperty(
                        'transform'
                    );

                    page.style.removeProperty(
                        'opacity'
                    );
                }

                const wasHorizontal =
                    state.touchHorizontal;

                state.touchHorizontal = false;

                if (
                    !wasHorizontal
                    || Math.abs(dx) < 44
                ) {
                    return;
                }

                move(dx < 0 ? 1 : -1);
            },
            { passive: true }
        );

        host.addEventListener(
            'touchcancel',
            () => {
                const page =
                    visiblePages()[currentIndex()];

                page?.style.removeProperty(
                    'transform'
                );

                page?.style.removeProperty(
                    'opacity'
                );

                state.touchStartX = null;
                state.touchStartY = null;
                state.touchLastX = null;
                state.touchHorizontal = false;
            },
            { passive: true }
        );
    };

    const protectAppLinks = () => {
        /*
         * Bản 12.1 dùng pointer capture nên click có thể bị chuyển
         * từ thẻ <a> sang viewport. V12.2 không dùng pointer capture.
         * Đảm bảo link ứng dụng luôn nhận click bình thường.
         */
        qsa('.ws5-app-link, [data-app-link]')
            .forEach((link) => {
                link.style.setProperty(
                    'pointer-events',
                    'auto',
                    'important'
                );
            });
    };

    const boot = () => {
        applyAvatar();
        protectAppLinks();
        directActivate(currentIndex());
        bindControls();
        bindWheel();
        bindTouch();

        window.EgoWorkspacePager = {
            next: () => move(1),
            previous: () => move(-1),
            goTo: (index) =>
                animateTo(Number(index) || 0),
            current: currentIndex,
        };
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            boot,
            { once: true }
        );
    } else {
        boot();
    }

    window.addEventListener('pageshow', boot);

    window.setTimeout(boot, 350);
    window.setTimeout(boot, 1000);
})();
