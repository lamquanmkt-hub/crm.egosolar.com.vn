(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    };

    document.documentElement.classList.add('crm-navigation-ready');

    ready(() => {
        const root = document.documentElement;
        const topbar = document.getElementById('crmTopbar');
        const sidebar = document.getElementById('sidebar');
        const sidebarScroll = sidebar?.querySelector('.ego-sidebar__scroll');

        const updateMotionState = () => {
            root.classList.toggle('crm-navigation-paused', document.hidden);
        };

        const updateTopbarScroll = () => {
            topbar?.classList.toggle('is-page-scrolled', window.scrollY > 8);
        };

        const updateSidebarScroll = () => {
            sidebar?.classList.toggle('is-menu-scrolled', (sidebarScroll?.scrollTop || 0) > 5);
        };

        updateMotionState();
        updateTopbarScroll();
        updateSidebarScroll();

        document.addEventListener('visibilitychange', updateMotionState, { passive: true });
        window.addEventListener('scroll', updateTopbarScroll, { passive: true });
        sidebarScroll?.addEventListener('scroll', updateSidebarScroll, { passive: true });

        /*
         * Mobile usability: close drawer after choosing a real navigation link.
         * Submenu toggles and buttons remain untouched.
         */
        sidebar?.addEventListener('click', (event) => {
            if (window.innerWidth > 991.98) return;

            const link = event.target.closest('a[href]');
            if (!link || link.getAttribute('href') === '#') return;
            if (link.hasAttribute('data-bs-toggle')) return;
            if (link.getAttribute('aria-expanded') !== null) return;

            window.setTimeout(() => window.EgoSidebar?.close?.(), 60);
        });

        /* Keep sidebar state classes synchronized for smoother layout changes. */
        const stateObserver = sidebar
            ? new MutationObserver(() => {
                document.body.classList.toggle(
                    'crm-sidebar-is-collapsed',
                    sidebar.classList.contains('ego-collapsed')
                );
                document.body.classList.toggle(
                    'crm-sidebar-is-open',
                    sidebar.classList.contains('show')
                );
            })
            : null;

        if (sidebar && stateObserver) {
            stateObserver.observe(sidebar, {
                attributes: true,
                attributeFilter: ['class'],
            });
        }

        window.addEventListener('beforeunload', () => stateObserver?.disconnect(), { once: true });
    });
})();


/* CRM_NAVIGATION_EFFECTS_V21_JS */
(() => {
    'use strict';

    document.documentElement.classList.add(
        'crm-navigation-ready'
    );

    const activateNavigationEffects = () => {
        document.documentElement.classList.add(
            'crm-navigation-effects-active'
        );

        document.body?.classList.add(
            'crm-navigation-effects-active'
        );
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            activateNavigationEffects,
            { once: true }
        );
    } else {
        activateNavigationEffects();
    }
})();
