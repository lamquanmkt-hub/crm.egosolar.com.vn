(() => {
    'use strict';

    const state = {
        wheelLockUntil: 0,
        touchStartX: null,
        touchStartY: null,
        pointerStartX: null,
        pointerStartY: null,
        pointerId: null,
        suppressClickUntil: 0,
    };

    const qsa = (selector, root = document) =>
        Array.from(root.querySelectorAll(selector));

    const getViewport = () =>
        document.querySelector(
            '#workspaceAppViewport, .ws5-app-viewport'
        );

    const getPages = () =>
        qsa('[data-app-page], .ws5-app-page');

    const getPagination = () =>
        document.getElementById('workspacePagination');

    const getDots = () =>
        qsa(
            '#workspacePagination [data-page-dot], '
            + '#workspacePagination .ws5-page-dot'
        );

    const visiblePages = () =>
        getPages().filter((page) => {
            if (page.hidden) return false;

            const cards = qsa('[data-app-card]', page);

            return cards.length === 0
                || cards.some((card) => !card.hidden);
        });

    const activeIndex = () => {
        const pages = visiblePages();
        const found = pages.findIndex((page) =>
            page.classList.contains('is-active')
        );

        return found >= 0 ? found : 0;
    };

    const ensurePagination = () => {
        const pagination = getPagination();
        const pages = visiblePages();

        if (!pagination || pages.length <= 1) {
            return;
        }

        /*
         * Workspace V5 tạo dot bằng JavaScript sau khi trang load.
         * Nếu dot chưa xuất hiện, V12.1 tự bổ sung; không còn yêu cầu
         * ws5-page-dot phải có sẵn trong Blade.
         */
        if (getDots().length === pages.length) {
            return;
        }

        pagination.innerHTML = pages.map((_, index) => {
            const active = index === activeIndex()
                ? ' is-active'
                : '';

            return (
                '<button type="button" '
                + 'class="ws5-page-dot' + active + '" '
                + 'data-page-dot="' + index + '" '
                + 'aria-label="Trang ứng dụng ' + (index + 1) + '">'
                + '</button>'
            );
        }).join('');
    };

    const fallbackActivate = (requestedIndex) => {
        const pages = visiblePages();

        if (pages.length <= 1) return false;

        const index = Math.max(
            0,
            Math.min(requestedIndex, pages.length - 1)
        );

        getPages().forEach((page) => {
            page.classList.remove('is-active');
            page.setAttribute('aria-hidden', 'true');
        });

        pages[index].classList.add('is-active');
        pages[index].setAttribute('aria-hidden', 'false');

        ensurePagination();

        getDots().forEach((dot, dotIndex) => {
            const selected = dotIndex === index;

            dot.classList.toggle('is-active', selected);
            dot.setAttribute(
                'aria-current',
                selected ? 'page' : 'false'
            );
        });

        return true;
    };

    const canMove = (direction) => {
        const pages = visiblePages();
        const index = activeIndex();

        if (pages.length <= 1) return false;

        return direction > 0
            ? index < pages.length - 1
            : index > 0;
    };

    const movePage = (direction) => {
        if (!canMove(direction)) {
            return false;
        }

        const button = document.getElementById(
            direction > 0
                ? 'workspacePageNext'
                : 'workspacePagePrev'
        );

        /*
         * Ưu tiên controller Workspace V5 để đồng bộ:
         * page active, nút mũi tên và pagination.
         */
        if (button && !button.disabled) {
            button.click();
            window.setTimeout(ensurePagination, 0);
            return true;
        }

        return fallbackActivate(activeIndex() + direction);
    };

    const applyAvatar = () => {
        const meta = document.querySelector(
            'meta[name="ego-workspace-avatar"]'
        );

        const avatarUrl = (meta?.content || '').trim();

        if (!avatarUrl) return;

        qsa('.ws5-avatar').forEach((avatar) => {
            const currentImage = avatar.querySelector('img');

            if (
                currentImage
                && currentImage.src
                && currentImage.complete
                && currentImage.naturalWidth > 0
            ) {
                return;
            }

            const fallback = avatar.textContent.trim();
            avatar.dataset.egoAvatarFallback = fallback;

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
                    if (currentImage) {
                        currentImage.remove();
                    }

                    avatar.textContent =
                        avatar.dataset.egoAvatarFallback || '';
                },
                { once: true }
            );

            image.src = avatarUrl;
        });
    };

    const bindDots = () => {
        ensurePagination();

        const pagination = getPagination();

        if (
            !pagination
            || pagination.dataset.egoWorkspaceV121Dots === '1'
        ) {
            return;
        }

        pagination.dataset.egoWorkspaceV121Dots = '1';

        pagination.addEventListener('click', (event) => {
            const dot = event.target.closest(
                '[data-page-dot], .ws5-page-dot'
            );

            if (!dot) return;

            /*
             * Controller V5 đã xử lý click của dot được tạo sẵn.
             * Chỉ fallback khi click không làm đổi trang.
             */
            const before = activeIndex();

            window.setTimeout(() => {
                if (activeIndex() !== before) return;

                const dots = getDots();
                const index = Math.max(0, dots.indexOf(dot));

                fallbackActivate(index);
            }, 0);
        });
    };

    const bindTouchSwipe = () => {
        const viewport = getViewport();

        if (
            !viewport
            || viewport.dataset.egoSwipeV121 === '1'
        ) {
            return;
        }

        viewport.dataset.egoSwipeV121 = '1';

        viewport.addEventListener(
            'touchstart',
            (event) => {
                const touch = event.touches?.[0];

                if (!touch) return;

                state.touchStartX = touch.clientX;
                state.touchStartY = touch.clientY;
            },
            { passive: true }
        );

        viewport.addEventListener(
            'touchend',
            (event) => {
                const touch = event.changedTouches?.[0];

                if (
                    !touch
                    || state.touchStartX === null
                    || state.touchStartY === null
                ) {
                    return;
                }

                const dx = touch.clientX - state.touchStartX;
                const dy = touch.clientY - state.touchStartY;

                state.touchStartX = null;
                state.touchStartY = null;

                if (
                    Math.abs(dx) < 42
                    || Math.abs(dx) <= Math.abs(dy) * 1.15
                ) {
                    return;
                }

                const direction = dx < 0 ? 1 : -1;

                if (movePage(direction)) {
                    state.suppressClickUntil = Date.now() + 420;
                }
            },
            { passive: true }
        );

        viewport.addEventListener(
            'click',
            (event) => {
                if (Date.now() < state.suppressClickUntil) {
                    event.preventDefault();
                    event.stopPropagation();
                }
            },
            true
        );
    };

    const bindPointerDrag = () => {
        const viewport = getViewport();

        if (
            !viewport
            || viewport.dataset.egoPointerV121 === '1'
        ) {
            return;
        }

        viewport.dataset.egoPointerV121 = '1';

        viewport.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'touch') return;

            state.pointerId = event.pointerId;
            state.pointerStartX = event.clientX;
            state.pointerStartY = event.clientY;

            viewport.setPointerCapture?.(event.pointerId);
        });

        viewport.addEventListener('pointerup', (event) => {
            if (
                state.pointerId !== event.pointerId
                || state.pointerStartX === null
                || state.pointerStartY === null
            ) {
                return;
            }

            const dx = event.clientX - state.pointerStartX;
            const dy = event.clientY - state.pointerStartY;

            state.pointerId = null;
            state.pointerStartX = null;
            state.pointerStartY = null;

            if (
                Math.abs(dx) < 55
                || Math.abs(dx) <= Math.abs(dy)
            ) {
                return;
            }

            if (movePage(dx < 0 ? 1 : -1)) {
                state.suppressClickUntil = Date.now() + 350;
            }
        });
    };

    const bindDesktopWheel = () => {
        const viewport = getViewport();

        if (
            !viewport
            || viewport.dataset.egoWheelV121 === '1'
        ) {
            return;
        }

        viewport.dataset.egoWheelV121 = '1';

        viewport.addEventListener(
            'wheel',
            (event) => {
                if (
                    window.matchMedia('(max-width: 700px)').matches
                    || visiblePages().length <= 1
                ) {
                    return;
                }

                const delta =
                    Math.abs(event.deltaX) > Math.abs(event.deltaY)
                        ? event.deltaX
                        : event.deltaY;

                if (
                    Math.abs(delta) < 16
                    || Date.now() < state.wheelLockUntil
                ) {
                    return;
                }

                const direction = delta > 0 ? 1 : -1;

                /*
                 * Chỉ chặn cuộn trang khi còn trang ứng dụng để chuyển.
                 * Ở trang đầu/cuối, con lăn vẫn cuộn trang bình thường.
                 */
                if (!canMove(direction)) {
                    return;
                }

                event.preventDefault();

                state.wheelLockUntil = Date.now() + 460;
                movePage(direction);
            },
            { passive: false }
        );
    };

    const bindKeyboard = () => {
        if (
            document.documentElement
                .dataset.egoWorkspaceKeyboardV121 === '1'
        ) {
            return;
        }

        document.documentElement
            .dataset.egoWorkspaceKeyboardV121 = '1';

        document.addEventListener('keydown', (event) => {
            const viewport = getViewport();

            if (
                !viewport
                || !viewport.matches(':hover, :focus-within')
                || visiblePages().length <= 1
            ) {
                return;
            }

            if (event.key === 'ArrowRight' && canMove(1)) {
                event.preventDefault();
                movePage(1);
            }

            if (event.key === 'ArrowLeft' && canMove(-1)) {
                event.preventDefault();
                movePage(-1);
            }
        });
    };

    const boot = () => {
        applyAvatar();
        ensurePagination();
        bindDots();
        bindTouchSwipe();
        bindPointerDrag();
        bindDesktopWheel();
        bindKeyboard();

        window.EgoWorkspacePager = {
            next: () => movePage(1),
            previous: () => movePage(-1),
            goTo: (index) =>
                fallbackActivate(Number(index) || 0),
            current: activeIndex,
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

    window.setTimeout(boot, 300);
    window.setTimeout(boot, 900);
    window.setTimeout(boot, 1600);
})();
