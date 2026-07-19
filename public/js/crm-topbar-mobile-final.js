(() => {
    'use strict';

    const MOBILE_QUERY = '(max-width: 991.98px)';

    const init = () => {
        const root = document.getElementById('crmTopbar');
        const backdrop = document.getElementById('crmTopbarBackdrop');

        if (!root || !backdrop) {
            return;
        }

        const isMobile = () =>
            window.matchMedia(MOBILE_QUERY).matches;

        const getPanel = (name) =>
            root.querySelector(`[data-crm-panel="${name}"]`);

        const getToggle = (name) =>
            root.querySelector(`[data-crm-panel-toggle="${name}"]`);

        const allPanels = () =>
            Array.from(root.querySelectorAll('[data-crm-panel]'));

        const allToggles = () =>
            Array.from(root.querySelectorAll('[data-crm-panel-toggle]'));

        const closePanel = (panel) => {
            if (!panel) {
                return;
            }

            const name = panel.dataset.crmPanel || '';
            const toggle = getToggle(name);

            panel.classList.remove('is-open', 'is-active');
            panel.setAttribute('aria-hidden', 'true');

            if (toggle) {
                toggle.classList.remove('is-open', 'is-active');
                toggle.setAttribute('aria-expanded', 'false');
            }
        };

        const closeAll = (exceptName = '') => {
            allPanels().forEach((panel) => {
                if (panel.dataset.crmPanel !== exceptName) {
                    closePanel(panel);
                }
            });
        };

        const hideBackdrop = () => {
            backdrop.classList.remove('is-open');
            backdrop.setAttribute('aria-hidden', 'true');
        };

        const showBackdrop = () => {
            backdrop.classList.add('is-open');
            backdrop.setAttribute('aria-hidden', 'false');
        };

        const forceOpen = (name) => {
            const panel = getPanel(name);
            const toggle = getToggle(name);

            if (!panel) {
                return;
            }

            closeAll(name);

            panel.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');

            if (toggle) {
                toggle.classList.add('is-open');
                toggle.setAttribute('aria-expanded', 'true');
            }

            showBackdrop();
        };

        const forceCloseAll = () => {
            closeAll();
            hideBackdrop();
        };

        /*
         * CRM gốc vẫn xử lý tải tin nhắn/thông báo.
         * Đoạn này chỉ đồng bộ lại trạng thái hiển thị sau khi CRM gốc chạy.
         */
        root.addEventListener('click', (event) => {
            const toggle = event.target.closest(
                '[data-crm-panel-toggle]'
            );

            if (!toggle || !isMobile()) {
                return;
            }

            const name = toggle.dataset.crmPanelToggle || '';

            window.setTimeout(() => {
                const panel = getPanel(name);

                if (!panel) {
                    return;
                }

                const nativeOpened =
                    toggle.getAttribute('aria-expanded') === 'true'
                    || panel.classList.contains('is-open')
                    || (
                        name === 'more'
                        && backdrop.classList.contains('is-open')
                    );

                if (nativeOpened) {
                    forceOpen(name);
                } else {
                    closePanel(panel);

                    const hasOpenedPanel = allPanels().some(
                        (item) =>
                            item.classList.contains('is-open')
                            || item.getAttribute('aria-hidden') === 'false'
                    );

                    if (!hasOpenedPanel) {
                        hideBackdrop();
                    }
                }
            }, 0);
        });

        /*
         * Từ menu ba chấm chuyển sang panel Tin nhắn/Thông báo.
         * Dùng chính nút gốc để giữ nguyên API, badge và dữ liệu.
         */
        root.addEventListener('click', (event) => {
            const opener = event.target.closest(
                '[data-ego-open-panel]'
            );

            if (!opener || !isMobile()) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const targetName =
                opener.dataset.egoOpenPanel || '';

            const targetToggle =
                getToggle(targetName);

            forceCloseAll();

            window.setTimeout(() => {
                if (targetToggle) {
                    targetToggle.click();

                    window.setTimeout(() => {
                        forceOpen(targetName);
                    }, 20);
                }
            }, 20);
        });

        root.addEventListener('click', (event) => {
            const closeButton = event.target.closest(
                '[data-crm-panel-close]'
            );

            if (!closeButton || !isMobile()) {
                return;
            }

            window.setTimeout(forceCloseAll, 0);
        });

        backdrop.addEventListener('click', () => {
            if (!isMobile()) {
                return;
            }

            window.setTimeout(forceCloseAll, 0);
        });

        document.addEventListener('keydown', (event) => {
            if (
                event.key === 'Escape'
                && isMobile()
            ) {
                forceCloseAll();
            }
        });

        window.addEventListener('resize', () => {
            if (!isMobile()) {
                forceCloseAll();
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            init,
            { once: true }
        );
    } else {
        init();
    }
})();
