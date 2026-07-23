(() => {
    'use strict';

    const ready = (callback) => {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }
        callback();
    };

    ready(() => {
        const page = document.querySelector('.ego-pr-page');
        const detail = document.querySelector('.ego-pr-detail-page');

        if (!page && !detail) {
            return;
        }

        document.body.classList.add('ego-pr-ui-ready');

        // Reveal once without long-running observers.
        requestAnimationFrame(() => {
            document.querySelectorAll('.ego-pr-reveal').forEach((element) => {
                element.classList.add('is-visible');
            });
        });

        // Mobile filter bottom sheet.
        const filterPanel = document.getElementById('egoPrFilterPanel');
        const filterBackdrop = document.getElementById('egoPrFilterBackdrop');
        const filterOpen = document.getElementById('egoPrFilterOpen');
        const filterClose = document.getElementById('egoPrFilterClose');

        const setFilterOpen = (open) => {
            if (!filterPanel || !filterBackdrop || !filterOpen) {
                return;
            }

            filterPanel.classList.toggle('is-open', open);
            filterBackdrop.classList.toggle('is-open', open);
            filterOpen.setAttribute('aria-expanded', String(open));
            filterBackdrop.setAttribute('aria-hidden', String(!open));
            document.body.classList.toggle('ego-pr-filter-lock', open);
        };

        filterOpen?.addEventListener('click', () => setFilterOpen(true));
        filterClose?.addEventListener('click', () => setFilterOpen(false));
        filterBackdrop?.addEventListener('click', () => setFilterOpen(false));

        // Floating row action menu. It is moved to body to avoid table overflow clipping.
        let activeMenu = null;
        let activeButton = null;

        const closeActionMenu = () => {
            if (!activeMenu) {
                return;
            }

            activeMenu.classList.remove('is-open');
            activeMenu.setAttribute('aria-hidden', 'true');
            activeButton?.setAttribute('aria-expanded', 'false');
            activeMenu = null;
            activeButton = null;
        };

        const positionActionMenu = (button, menu) => {
            const rect = button.getBoundingClientRect();
            const menuWidth = 205;
            const viewportPadding = 8;
            const estimatedHeight = Math.min(menu.scrollHeight || 320, 440);

            let left = rect.right - menuWidth;
            if (left < viewportPadding) {
                left = viewportPadding;
            }
            if (left + menuWidth > window.innerWidth - viewportPadding) {
                left = window.innerWidth - menuWidth - viewportPadding;
            }

            let top = rect.bottom + 6;
            if (top + estimatedHeight > window.innerHeight - viewportPadding) {
                top = Math.max(viewportPadding, rect.top - estimatedHeight - 6);
            }

            menu.style.left = `${Math.round(left)}px`;
            menu.style.top = `${Math.round(top)}px`;
            menu.style.maxHeight = `${Math.max(150, window.innerHeight - top - viewportPadding)}px`;
            menu.style.overflowY = 'auto';
        };

        const openActionMenu = (button) => {
            const id = button.dataset.menuId;
            const menu = id ? document.getElementById(id) : null;

            if (!menu) {
                return;
            }

            if (activeMenu === menu) {
                closeActionMenu();
                return;
            }

            closeActionMenu();
            document.body.appendChild(menu);
            menu.classList.add('is-open');
            menu.setAttribute('aria-hidden', 'false');
            button.setAttribute('aria-expanded', 'true');
            activeMenu = menu;
            activeButton = button;
            positionActionMenu(button, menu);
        };

        document.addEventListener('click', (event) => {
            const directToggle = event.target.closest('.ego-pr-menu-toggle');
            const processToggle = event.target.closest('.ego-pr-mobile-process');
            const toggle = directToggle || processToggle;

            if (toggle) {
                event.preventDefault();
                event.stopPropagation();
                openActionMenu(toggle);
                return;
            }

            if (activeMenu && !event.target.closest('.ego-pr-action-menu')) {
                closeActionMenu();
            }
        });

        window.addEventListener('resize', closeActionMenu, { passive: true });
        window.addEventListener('scroll', closeActionMenu, { passive: true, capture: true });

        // Confirm only forms explicitly marked by this module.
        document.querySelectorAll('.ego-pr-confirm-form').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (form.dataset.egoConfirmed === '1') {
                    return;
                }

                const message = form.dataset.confirm || 'Xác nhận thực hiện thao tác này?';
                if (!window.confirm(message)) {
                    event.preventDefault();
                    return;
                }

                form.dataset.egoConfirmed = '1';
                const button = form.querySelector('button[type="submit"]');
                if (button) {
                    button.disabled = true;
                    button.dataset.originalHtml = button.innerHTML;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Đang xử lý...</span>';
                }
            });
        });

        // Single segmented approval/rejection center on detail page.
        document.querySelectorAll('[data-action-center]').forEach((center) => {
            const tabs = Array.from(center.querySelectorAll('[data-action-tab]'));
            const panels = Array.from(center.querySelectorAll('[data-action-panel]'));

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => {
                    const target = tab.dataset.actionTab;

                    tabs.forEach((item) => {
                        item.classList.toggle('is-active', item === tab);
                    });

                    panels.forEach((panel) => {
                        const active = panel.dataset.actionPanel === target;
                        panel.classList.toggle('is-active', active);
                        panel.hidden = !active;
                    });
                });
            });
        });

        // Keep bulk bar visual state synced with legacy bulk logic.
        const bulkBar = document.getElementById('prBulkApprovalBar');
        const bulkCount = document.getElementById('prBulkSelectedCount');

        const syncBulkVisual = () => {
            if (!bulkBar || !bulkCount) {
                return;
            }
            const count = Number.parseInt(bulkCount.textContent || '0', 10) || 0;
            bulkBar.classList.toggle('is-active', count > 0);

            document.querySelectorAll('.js-pr-bulk-checkbox').forEach((checkbox) => {
                checkbox.closest('tr, .ego-pr-mobile-card')?.classList.toggle('ego-pr-row-selected', checkbox.checked);
            });
        };

        if (bulkCount && 'MutationObserver' in window) {
            new MutationObserver(syncBulkVisual).observe(bulkCount, {
                childList: true,
                characterData: true,
                subtree: true,
            });
        }

        document.addEventListener('change', (event) => {
            if (event.target.matches('.js-pr-bulk-checkbox, .pr-bulk-select-all')) {
                window.setTimeout(syncBulkVisual, 0);
            }
        });

        syncBulkVisual();

        // Restore buttons when browser returns from bfcache.
        window.addEventListener('pageshow', () => {
            document.querySelectorAll('.ego-pr-confirm-form button[type="submit"][data-original-html]').forEach((button) => {
                button.disabled = false;
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
                delete button.closest('form')?.dataset.egoConfirmed;
            });
            closeActionMenu();
            setFilterOpen(false);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            closeActionMenu();
            setFilterOpen(false);
        });
    });
})();
