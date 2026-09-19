(function () {
    'use strict';

    var MOBILE_QUERY = '(max-width: 991.98px)';
    var suppressOpenUntil = 0;
    var touchStartX = null;
    var touchStartY = null;

    function isMobile() {
        return window.matchMedia(MOBILE_QUERY).matches;
    }

    function sidebar() {
        return document.getElementById('sidebar');
    }

    function allOverlays() {
        return Array.prototype.slice.call(
            document.querySelectorAll(
                '#sidebarOverlay, .ego-sidebar-overlay, [data-sidebar-overlay]'
            )
        );
    }

    function overlay() {
        var item = document.getElementById('sidebarOverlay');

        if (!item) {
            item = document.createElement('div');
            item.id = 'sidebarOverlay';
            item.className = 'ego-sidebar-overlay';
            item.setAttribute('aria-hidden', 'true');
            document.body.appendChild(item);
        }

        return item;
    }

    function toggleButton() {
        return document.getElementById('toggleSidebar');
    }

    function isOpen() {
        var item = sidebar();

        return Boolean(
            item
            && item.classList.contains('show')
            && item.getAttribute('aria-hidden') !== 'true'
        );
    }

    function setToggleAppearance(opened) {
        var button = toggleButton();

        if (!button || !isMobile()) {
            return;
        }

        var icon = button.querySelector('i');

        if (icon) {
            icon.className = opened
                ? 'bi bi-x-lg'
                : 'bi bi-chevron-right';
        }

        button.setAttribute(
            'aria-label',
            opened ? 'Đóng menu' : 'Mở menu'
        );

        button.setAttribute(
            'title',
            opened ? 'Đóng menu' : 'Mở menu'
        );

        button.setAttribute(
            'aria-expanded',
            opened ? 'true' : 'false'
        );
    }

    function clearPageLock() {
        [
            'ego-noscroll',
            'sidebar-open',
            'menu-open',
            'offcanvas-open'
        ].forEach(function (name) {
            document.body.classList.remove(name);
            document.documentElement.classList.remove(name);
        });

        document.documentElement.classList.remove(
            'ego-mobile-sidebar-open'
        );

        [
            document.documentElement,
            document.body
        ].forEach(function (element) {
            element.style.removeProperty('overflow');
            element.style.removeProperty('position');
            element.style.removeProperty('width');
            element.style.removeProperty('height');
            element.style.removeProperty('touch-action');
        });
    }

    function forceClosedStyles(item) {
        if (!item || !isMobile()) {
            return;
        }

        item.style.setProperty(
            'left',
            '0',
            'important'
        );

        item.style.setProperty(
            'transform',
            'translate3d(-105%, 0, 0)',
            'important'
        );

        item.style.setProperty(
            'visibility',
            'hidden',
            'important'
        );

        item.style.setProperty(
            'pointer-events',
            'none',
            'important'
        );
    }

    function forceOpenStyles(item) {
        if (!item || !isMobile()) {
            return;
        }

        item.style.setProperty(
            'left',
            '0',
            'important'
        );

        item.style.setProperty(
            'transform',
            'translate3d(0, 0, 0)',
            'important'
        );

        item.style.setProperty(
            'visibility',
            'visible',
            'important'
        );

        item.style.setProperty(
            'pointer-events',
            'auto',
            'important'
        );
    }

    function closeSidebar(options) {
        var config = options || {};
        var item = sidebar();

        suppressOpenUntil =
            Date.now()
            + (config.longSuppress ? 850 : 500);

        if (item) {
            item.classList.remove(
                'show',
                'open',
                'is-open',
                'mobile-open'
            );

            item.setAttribute(
                'aria-hidden',
                isMobile() ? 'true' : 'false'
            );

            if (isMobile()) {
                forceClosedStyles(item);
            } else {
                item.style.removeProperty('left');
                item.style.removeProperty('transform');
                item.style.removeProperty('visibility');
                item.style.removeProperty('pointer-events');
            }
        }

        allOverlays().forEach(function (itemOverlay) {
            itemOverlay.classList.remove(
                'show',
                'open',
                'is-open',
                'active'
            );

            itemOverlay.setAttribute(
                'aria-hidden',
                'true'
            );

            itemOverlay.style.setProperty(
                'opacity',
                '0',
                'important'
            );

            itemOverlay.style.setProperty(
                'visibility',
                'hidden',
                'important'
            );

            itemOverlay.style.setProperty(
                'pointer-events',
                'none',
                'important'
            );
        });

        clearPageLock();
        setToggleAppearance(false);
    }

    function openSidebar() {
        if (
            !isMobile()
            || Date.now() < suppressOpenUntil
        ) {
            return;
        }

        var item = sidebar();
        var itemOverlay = overlay();

        if (!item || !itemOverlay) {
            return;
        }

        document
            .querySelectorAll(
                '#egoMobileSidebarClose'
            )
            .forEach(function (legacyButton) {
                legacyButton.remove();
            });

        item.classList.remove(
            'open',
            'is-open',
            'mobile-open'
        );

        item.classList.add('show');
        item.setAttribute('aria-hidden', 'false');
        forceOpenStyles(item);

        itemOverlay.classList.remove(
            'open',
            'is-open',
            'active'
        );

        itemOverlay.classList.add('show');
        itemOverlay.setAttribute('aria-hidden', 'false');

        itemOverlay.style.setProperty(
            'opacity',
            '1',
            'important'
        );

        itemOverlay.style.setProperty(
            'visibility',
            'visible',
            'important'
        );

        itemOverlay.style.setProperty(
            'pointer-events',
            'auto',
            'important'
        );

        document.body.classList.add('ego-noscroll');
        document.documentElement.classList.add(
            'ego-mobile-sidebar-open'
        );

        setToggleAppearance(true);
    }

    function toggleSidebar() {
        if (isOpen()) {
            closeSidebar({
                longSuppress: true
            });
        } else {
            openSidebar();
        }
    }

    function isRealNavigation(link) {
        if (!link) {
            return false;
        }

        var href = (
            link.getAttribute('href')
            || ''
        ).trim();

        return Boolean(
            href
            && href !== '#'
            && href.charAt(0) !== '#'
            && link.getAttribute(
                'data-bs-toggle'
            ) !== 'collapse'
            && link.getAttribute(
                'data-ego-type'
            ) !== 'toggle'
            && link.getAttribute(
                'data-ego-type'
            ) !== 'action'
        );
    }

    function consume(event) {
        event.preventDefault();
        event.stopPropagation();

        if (
            typeof event.stopImmediatePropagation
            === 'function'
        ) {
            event.stopImmediatePropagation();
        }
    }

    function handleActivation(event) {
        if (!isMobile()) {
            return;
        }

        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        var closeControl = target.closest(
            '#toggleSidebar, #egoMobileSidebarClose'
        );

        if (closeControl && sidebar()?.contains(closeControl)) {
            consume(event);
            closeSidebar({
                longSuppress: true
            });
            return;
        }

        var openControl = target.closest(
            '#openSidebarBtn, #crmSidebarToggle'
        );

        if (openControl) {
            consume(event);

            if (Date.now() >= suppressOpenUntil) {
                openSidebar();
            }

            return;
        }

        var clickedOverlay = target.closest(
            '#sidebarOverlay, .ego-sidebar-overlay, [data-sidebar-overlay]'
        );

        if (
            clickedOverlay
            && !target.closest('#sidebar')
        ) {
            consume(event);
            closeSidebar({
                longSuppress: true
            });
            return;
        }

        var link = target.closest(
            '#sidebar a[href]'
        );

        if (isRealNavigation(link)) {
            window.setTimeout(function () {
                closeSidebar({
                    longSuppress: true
                });
            }, 0);
        }
    }

    function bindTouchGuards() {
        var item = sidebar();
        var closeControl = toggleButton();
        var openControls = document.querySelectorAll(
            '#openSidebarBtn, #crmSidebarToggle'
        );

        if (
            closeControl
            && !closeControl.dataset.egoMobileV4Touch
        ) {
            closeControl.dataset.egoMobileV4Touch = '1';

            closeControl.addEventListener(
                'touchend',
                function (event) {
                    if (!isMobile()) {
                        return;
                    }

                    consume(event);
                    closeSidebar({
                        longSuppress: true
                    });
                },
                {
                    capture: true,
                    passive: false
                }
            );
        }

        openControls.forEach(function (control) {
            if (control.dataset.egoMobileV4Touch) {
                return;
            }

            control.dataset.egoMobileV4Touch = '1';

            control.addEventListener(
                'touchend',
                function (event) {
                    if (!isMobile()) {
                        return;
                    }

                    consume(event);

                    if (
                        Date.now()
                        >= suppressOpenUntil
                    ) {
                        openSidebar();
                    }
                },
                {
                    capture: true,
                    passive: false
                }
            );
        });

        if (
            item
            && !item.dataset.egoMobileV4Swipe
        ) {
            item.dataset.egoMobileV4Swipe = '1';

            item.addEventListener(
                'touchstart',
                function (event) {
                    if (
                        !isMobile()
                        || !isOpen()
                        || !event.touches
                        || !event.touches[0]
                    ) {
                        return;
                    }

                    touchStartX =
                        event.touches[0].clientX;

                    touchStartY =
                        event.touches[0].clientY;
                },
                {
                    passive: true
                }
            );

            item.addEventListener(
                'touchend',
                function (event) {
                    if (
                        touchStartX === null
                        || touchStartY === null
                        || !event.changedTouches
                        || !event.changedTouches[0]
                    ) {
                        return;
                    }

                    var dx =
                        event.changedTouches[0].clientX
                        - touchStartX;

                    var dy =
                        event.changedTouches[0].clientY
                        - touchStartY;

                    touchStartX = null;
                    touchStartY = null;

                    if (
                        Math.abs(dx) > Math.abs(dy)
                        && dx < -55
                    ) {
                        closeSidebar({
                            longSuppress: true
                        });
                    }
                },
                {
                    passive: true
                }
            );
        }
    }

    function syncViewport() {
        document
            .querySelectorAll(
                '#egoMobileSidebarClose'
            )
            .forEach(function (legacyButton) {
                legacyButton.remove();
            });

        if (isMobile()) {
            closeSidebar();
            bindTouchGuards();
        } else {
            closeSidebar();

            var item = sidebar();

            if (item) {
                item.setAttribute(
                    'aria-hidden',
                    'false'
                );

                item.style.removeProperty('left');
                item.style.removeProperty('transform');
                item.style.removeProperty('visibility');
                item.style.removeProperty('pointer-events');
            }
        }
    }

    function boot() {
        overlay();
        bindTouchGuards();

        window.EgoSidebar = {
            open: openSidebar,
            close: closeSidebar,
            toggle: toggleSidebar,
            isOpen: isOpen
        };

        syncViewport();
    }

    document.addEventListener(
        'click',
        handleActivation,
        true
    );

    document.addEventListener(
        'keydown',
        function (event) {
            if (
                event.key === 'Escape'
                && isMobile()
                && isOpen()
            ) {
                closeSidebar({
                    longSuppress: true
                });
            }
        }
    );

    window.addEventListener(
        'resize',
        function () {
            window.requestAnimationFrame(
                syncViewport
            );
        },
        {
            passive: true
        }
    );

    window.addEventListener(
        'pageshow',
        function () {
            if (isMobile()) {
                closeSidebar();
            }
        }
    );

    if (
        document.readyState === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            boot,
            {
                once: true
            }
        );
    } else {
        boot();
    }

    /*
     * Các bản cũ thường chạy lại sau 250 ms và 1.000 ms.
     * Ghi đè lại API sau những mốc đó.
     */
    window.setTimeout(boot, 1200);
    window.setTimeout(boot, 1800);
})();
