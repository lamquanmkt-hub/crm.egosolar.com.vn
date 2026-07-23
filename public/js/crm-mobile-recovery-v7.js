(() => {
    'use strict';

    const mobile = window.matchMedia(
        '(max-width: 991.98px)'
    );

    const resetStaleState = () => {
        if (!mobile.matches) {
            return;
        }

        const root =
            document.getElementById(
                'crmTopbar'
            );

        root?.querySelectorAll(
            '[data-crm-panel]'
        ).forEach((panel) => {
            panel.classList.remove(
                'is-open'
            );

            panel.setAttribute(
                'aria-hidden',
                'true'
            );
        });

        root?.querySelectorAll(
            '[data-crm-panel-toggle]'
        ).forEach((button) => {
            button.setAttribute(
                'aria-expanded',
                'false'
            );
        });

        const backdrop =
            document.getElementById(
                'crmTopbarBackdrop'
            );

        backdrop?.classList.remove(
            'is-open'
        );

        backdrop?.setAttribute(
            'aria-hidden',
            'true'
        );

        document
            .getElementById(
                'egoTaskFloat'
            )
            ?.classList
            .remove(
                'is-open'
            );

        document
            .getElementById(
                'egoLeaveFloat'
            )
            ?.classList
            .remove(
                'is-open'
            );

        document.body.classList.remove(
            'crm-topbar-panel-open',
            'ego-mobile-notice-open',
            'ego-transition-locked'
        );

        const sidebar =
            document.getElementById(
                'sidebar'
            );

        if (
            !sidebar
            ?.classList
            .contains(
                'show'
            )
        ) {
            document.body.classList.remove(
                'ego-noscroll'
            );
        }

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

    if (
        document.readyState
        === 'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            resetStaleState,
            {
                once: true,
            }
        );
    } else {
        resetStaleState();
    }

    window.addEventListener(
        'pageshow',
        resetStaleState
    );
})();
