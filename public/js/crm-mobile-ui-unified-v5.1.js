(function () {
    'use strict';

    var MOBILE_QUERY = '(max-width: 991.98px)';
    var suppressOpenUntil = 0;
    var touchStartX = null;
    var touchStartY = null;

    function isMobile() {
        return window.matchMedia(MOBILE_QUERY).matches;
    }

    function getSidebar() {
        return document.getElementById('sidebar');
    }

    function getToggleButton() {
        return document.getElementById('toggleSidebar');
    }

    function getSidebarOverlay() {
        var overlay = document.getElementById('sidebarOverlay');

        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'sidebarOverlay';
            overlay.className = 'ego-sidebar-overlay';
            overlay.setAttribute('aria-hidden', 'true');
        }

        /*
         * Đưa overlay ra cuối body để tránh stacking-context của ego-shell.
         */
        if (overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
        }

        return overlay;
    }

    function removeDuplicateSidebarOverlays() {
        var keep = getSidebarOverlay();

        document.querySelectorAll('.ego-sidebar-overlay, [data-sidebar-overlay]').forEach(function (item) {
            if (item !== keep) {
                item.remove();
            }
        });
    }

    function hardHideForeignBackdrops() {
        document.querySelectorAll(
            '#crmTopbarBackdrop, .modal-backdrop, .offcanvas-backdrop'
        ).forEach(function (item) {
            item.classList.remove('show', 'is-open', 'active');
            item.setAttribute('aria-hidden', 'true');
            item.style.setProperty('display', 'none', 'important');
            item.style.setProperty('opacity', '0', 'important');
            item.style.setProperty('visibility', 'hidden', 'important');
            item.style.setProperty('pointer-events', 'none', 'important');
        });
    }

    function releaseForeignBackdropStyles() {
        document.querySelectorAll(
            '#crmTopbarBackdrop, .modal-backdrop, .offcanvas-backdrop'
        ).forEach(function (item) {
            item.style.removeProperty('display');
            item.style.removeProperty('opacity');
            item.style.removeProperty('visibility');
            item.style.removeProperty('pointer-events');
        });
    }

    function closeTopbarPanels() {
        document.querySelectorAll('[data-crm-panel]').forEach(function (panel) {
            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
        });

        document.querySelectorAll('[data-crm-panel-toggle]').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });

        hardHideForeignBackdrops();
    }

    function clearLocks() {
        [
            'ego-noscroll',
            'sidebar-open',
            'menu-open',
            'offcanvas-open',
            'crm-sidebar-is-open'
        ].forEach(function (name) {
            document.body.classList.remove(name);
            document.documentElement.classList.remove(name);
        });

        document.documentElement.classList.remove('ego-mobile-sidebar-open');

        [document.documentElement, document.body].forEach(function (element) {
            element.style.removeProperty('overflow');
            element.style.removeProperty('position');
            element.style.removeProperty('width');
            element.style.removeProperty('height');
            element.style.removeProperty('touch-action');
        });
    }

    function setToggleState(opened) {
        var button = getToggleButton();

        if (!button || !isMobile()) {
            return;
        }

        var icon = button.querySelector('i');

        if (icon) {
            icon.className = opened ? 'bi bi-x-lg' : 'bi bi-chevron-right';
        }

        button.setAttribute('aria-expanded', opened ? 'true' : 'false');
        button.setAttribute('aria-label', opened ? 'Đóng menu' : 'Mở menu');
        button.setAttribute('title', opened ? 'Đóng menu' : 'Mở menu');
    }

    function forceSidebarStyles(opened) {
        var sidebar = getSidebar();

        if (!sidebar || !isMobile()) {
            return;
        }

        sidebar.style.setProperty('left', '0', 'important');
        sidebar.style.setProperty(
            'transform',
            opened ? 'translate3d(0, 0, 0)' : 'translate3d(-105%, 0, 0)',
            'important'
        );
        sidebar.style.setProperty(
            'visibility',
            opened ? 'visible' : 'hidden',
            'important'
        );
        sidebar.style.setProperty(
            'pointer-events',
            opened ? 'auto' : 'none',
            'important'
        );
        sidebar.style.setProperty('opacity', '1', 'important');
        sidebar.style.setProperty('filter', 'none', 'important');
    }

    function isSidebarOpen() {
        var sidebar = getSidebar();

        return Boolean(
            sidebar
            && sidebar.classList.contains('show')
            && sidebar.getAttribute('aria-hidden') !== 'true'
        );
    }

    function closeSidebar(options) {
        var config = options || {};
        var sidebar = getSidebar();
        var overlay = getSidebarOverlay();

        suppressOpenUntil = Date.now() + (config.longSuppress ? 1000 : 500);

        if (sidebar) {
            sidebar.classList.remove('show', 'open', 'is-open', 'mobile-open');
            sidebar.setAttribute('aria-hidden', isMobile() ? 'true' : 'false');

            if (isMobile()) {
                forceSidebarStyles(false);
            } else {
                sidebar.style.removeProperty('left');
                sidebar.style.removeProperty('transform');
                sidebar.style.removeProperty('visibility');
                sidebar.style.removeProperty('pointer-events');
                sidebar.style.removeProperty('opacity');
                sidebar.style.removeProperty('filter');
            }
        }

        if (overlay) {
            overlay.classList.remove('show', 'open', 'is-open', 'active');
            overlay.setAttribute('aria-hidden', 'true');
            overlay.style.removeProperty('display');
            overlay.style.removeProperty('opacity');
            overlay.style.removeProperty('visibility');
            overlay.style.removeProperty('pointer-events');
        }

        clearLocks();
        setToggleState(false);

        /*
         * Sau khi đóng Sidebar, trả lại quyền điều khiển backdrop cho Topbar.
         */
        window.setTimeout(releaseForeignBackdropStyles, 80);
    }

    function openSidebar() {
        if (!isMobile() || Date.now() < suppressOpenUntil) {
            return;
        }

        var sidebar = getSidebar();
        var overlay = getSidebarOverlay();

        if (!sidebar || !overlay) {
            return;
        }

        closeTopbarPanels();
        removeDuplicateSidebarOverlays();

        sidebar.classList.remove('open', 'is-open', 'mobile-open', 'ego-collapsed');
        sidebar.classList.add('show');
        sidebar.setAttribute('aria-hidden', 'false');
        forceSidebarStyles(true);

        /*
         * Overlay chỉ phủ phần bên phải Sidebar, tuyệt đối không phủ menu.
         */
        overlay.classList.remove('open', 'is-open', 'active');
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.style.setProperty('display', 'block', 'important');
        overlay.style.setProperty('opacity', '1', 'important');
        overlay.style.setProperty('visibility', 'visible', 'important');
        overlay.style.setProperty('pointer-events', 'auto', 'important');

        document.body.classList.add('ego-noscroll');
        document.documentElement.classList.add('ego-mobile-sidebar-open');

        hardHideForeignBackdrops();
        setToggleState(true);
    }

    function consume(event) {
        event.preventDefault();
        event.stopPropagation();

        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
    }

    function isRealNavigation(link) {
        if (!link) {
            return false;
        }

        var href = (link.getAttribute('href') || '').trim();

        return Boolean(
            href
            && href !== '#'
            && href.charAt(0) !== '#'
            && link.getAttribute('data-bs-toggle') !== 'collapse'
            && link.getAttribute('data-ego-type') !== 'toggle'
            && link.getAttribute('data-ego-type') !== 'action'
        );
    }

    function handleClick(event) {
        if (!isMobile()) {
            return;
        }

        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        var sidebar = getSidebar();

        var closeControl = target.closest('#toggleSidebar, #egoMobileSidebarClose');

        if (closeControl && sidebar && sidebar.contains(closeControl)) {
            consume(event);
            closeSidebar({ longSuppress: true });
            return;
        }

        var openControl = target.closest('#openSidebarBtn, #crmSidebarToggle');

        if (openControl) {
            consume(event);

            if (Date.now() >= suppressOpenUntil) {
                openSidebar();
            }

            return;
        }

        var overlay = target.closest('#sidebarOverlay, .ego-sidebar-overlay, [data-sidebar-overlay]');

        if (overlay && !(sidebar && sidebar.contains(target))) {
            consume(event);
            closeSidebar({ longSuppress: true });
            return;
        }

        var link = target.closest('#sidebar a[href]');

        if (isRealNavigation(link)) {
            window.setTimeout(function () {
                closeSidebar({ longSuppress: true });
            }, 0);
        }
    }

    function bindTouchControls() {
        var sidebar = getSidebar();
        var closeButton = getToggleButton();

        document.querySelectorAll('#egoMobileSidebarClose').forEach(function (legacyButton) {
            legacyButton.remove();
        });

        if (closeButton && !closeButton.dataset.egoUnifiedV51Touch) {
            closeButton.dataset.egoUnifiedV51Touch = '1';

            closeButton.addEventListener(
                'touchend',
                function (event) {
                    if (!isMobile()) {
                        return;
                    }

                    consume(event);
                    closeSidebar({ longSuppress: true });
                },
                { capture: true, passive: false }
            );
        }

        document.querySelectorAll('#openSidebarBtn, #crmSidebarToggle').forEach(function (button) {
            if (button.dataset.egoUnifiedV51Touch) {
                return;
            }

            button.dataset.egoUnifiedV51Touch = '1';

            button.addEventListener(
                'touchend',
                function (event) {
                    if (!isMobile()) {
                        return;
                    }

                    consume(event);

                    if (Date.now() >= suppressOpenUntil) {
                        openSidebar();
                    }
                },
                { capture: true, passive: false }
            );
        });

        if (sidebar && !sidebar.dataset.egoUnifiedV51Swipe) {
            sidebar.dataset.egoUnifiedV51Swipe = '1';

            sidebar.addEventListener(
                'touchstart',
                function (event) {
                    if (!isMobile() || !isSidebarOpen() || !event.touches || !event.touches[0]) {
                        return;
                    }

                    touchStartX = event.touches[0].clientX;
                    touchStartY = event.touches[0].clientY;
                },
                { passive: true }
            );

            sidebar.addEventListener(
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

                    var dx = event.changedTouches[0].clientX - touchStartX;
                    var dy = event.changedTouches[0].clientY - touchStartY;

                    touchStartX = null;
                    touchStartY = null;

                    if (Math.abs(dx) > Math.abs(dy) && dx < -55) {
                        closeSidebar({ longSuppress: true });
                    }
                },
                { passive: true }
            );
        }
    }

    function syncViewport() {
        getSidebarOverlay();
        removeDuplicateSidebarOverlays();
        bindTouchControls();

        if (isMobile()) {
            closeSidebar();
            return;
        }

        closeSidebar();

        var sidebar = getSidebar();

        if (sidebar) {
            sidebar.setAttribute('aria-hidden', 'false');
            sidebar.style.removeProperty('left');
            sidebar.style.removeProperty('transform');
            sidebar.style.removeProperty('visibility');
            sidebar.style.removeProperty('pointer-events');
            sidebar.style.removeProperty('opacity');
            sidebar.style.removeProperty('filter');
        }
    }

    function boot() {
        getSidebarOverlay();
        removeDuplicateSidebarOverlays();
        bindTouchControls();

        window.EgoSidebar = {
            open: openSidebar,
            close: closeSidebar,
            toggle: function () {
                if (isSidebarOpen()) {
                    closeSidebar({ longSuppress: true });
                } else {
                    openSidebar();
                }
            },
            isOpen: isSidebarOpen
        };

        syncViewport();
    }

    document.addEventListener('click', handleClick, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isMobile() && isSidebarOpen()) {
            closeSidebar({ longSuppress: true });
        }
    });

    window.addEventListener(
        'resize',
        function () {
            window.requestAnimationFrame(syncViewport);
        },
        { passive: true }
    );

    window.addEventListener('pageshow', function () {
        if (isMobile()) {
            closeSidebar();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }

    window.setTimeout(boot, 300);
    window.setTimeout(boot, 1200);
})();
