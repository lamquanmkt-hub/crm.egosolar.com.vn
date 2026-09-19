(function () {
    'use strict';

    var STAGE_SELECTOR = '[data-workflow-stage]';
    var PANEL_SELECTOR = '[data-pt-panel]';
    var TAB_SELECTOR = '[data-pt-tab]';

    function all(selector) {
        return Array.prototype.slice.call(
            document.querySelectorAll(selector)
        );
    }

    function currentUrl() {
        return new URL(window.location.href);
    }

    function normalizeTab(tab) {
        var allowed = [
            'overview',
            'action',
            'survey',
            'materials',
            'installation',
            'acceptance',
            'finance',
            'edit',
            'history'
        ];

        return allowed.indexOf(tab) >= 0 ? tab : 'overview';
    }

    function buildTabUrl(tab) {
        var url = currentUrl();

        url.searchParams.set('tab', tab);

        if (tab === 'materials') {
            if (!url.searchParams.get('material_view')) {
                url.searchParams.set('material_view', 'proposal');
            }
        } else {
            url.searchParams.delete('material_view');
        }

        return url;
    }

    function findPanel(tab) {
        return document.querySelector(
            '[data-pt-panel="' + tab + '"]'
        );
    }

    function setActivePanel(tab) {
        var panel = findPanel(tab);

        if (!panel) {
            return false;
        }

        all(PANEL_SELECTOR).forEach(function (item) {
            item.classList.remove('active');
            item.setAttribute('aria-hidden', 'true');
        });

        panel.classList.add('active');
        panel.setAttribute('aria-hidden', 'false');

        return true;
    }

    function setActiveTab(tab) {
        var url = currentUrl();
        var materialView = url.searchParams.get('material_view')
            || 'proposal';

        all(TAB_SELECTOR).forEach(function (button) {
            button.classList.remove('active');
            button.removeAttribute('aria-current');
        });

        var selector = '[data-pt-tab="' + tab + '"]';

        if (tab === 'materials') {
            var materialButton = document.querySelector(
                selector
                + '[data-material-view="'
                + materialView
                + '"]'
            );

            if (!materialButton) {
                materialButton = document.querySelector(selector);
            }

            if (materialButton) {
                materialButton.classList.add('active');
                materialButton.setAttribute('aria-current', 'page');
            }

            return;
        }

        var tabButton = document.querySelector(selector);

        if (tabButton) {
            tabButton.classList.add('active');
            tabButton.setAttribute('aria-current', 'page');
        }
    }

    function setMaterialNavigationVisibility(tab) {
        var materialNav = document.querySelector(
            '.pt-material-context-nav'
        );

        if (!materialNav) {
            return;
        }

        materialNav.style.display = [
            'materials',
            'finance',
            'history'
        ].includes(tab)
            ? ''
            : 'none';
    }

    function markViewingStage(stage) {
        all(STAGE_SELECTOR).forEach(function (item) {
            item.classList.remove('is-viewing');
            item.removeAttribute('aria-current');
        });

        if (!stage) {
            return;
        }

        stage.classList.add('is-viewing');
        stage.setAttribute('aria-current', 'step');
    }

    function scrollToContent() {
        var target =
            document.querySelector('.pt-tabs:not(.pt-material-context-nav)')
            || document.querySelector('.pt-material-context-nav')
            || document.querySelector('.pt-main');

        if (!target) {
            return;
        }

        window.setTimeout(function () {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 50);
    }

    function activateTab(tab, stage, options) {
        options = options || {};
        tab = normalizeTab(tab);

        /*
         * Nếu panel chưa có trong HTML thì chuyển URL và tải lại.
         * Trường hợp bình thường panel đã có sẵn nên không reload.
         */
        if (!setActivePanel(tab)) {
            window.location.href = buildTabUrl(tab).toString();
            return;
        }

        var root = document.querySelector('.pt-page');

        if (root) {
            root.setAttribute('data-project-default-tab', tab);
        }

        if (options.updateUrl !== false) {
            window.history.replaceState(
                {
                    projectTab: tab
                },
                '',
                buildTabUrl(tab).toString()
            );
        }

        setActiveTab(tab);
        setMaterialNavigationVisibility(tab);
        markViewingStage(stage);

        window.dispatchEvent(
            new CustomEvent('project:tab-changed', {
                detail: {
                    tab: tab,
                    source: options.source || 'workflow'
                }
            })
        );

        if (options.scroll !== false) {
            scrollToContent();
        }
    }

    /*
     * Dùng capture=true để chạy trước listener cũ.
     * Ngăn listener cũ tiếp tục tìm một tab không tồn tại.
     */
    document.addEventListener(
        'click',
        function (event) {
            var stage = event.target.closest(STAGE_SELECTOR);

            if (!stage) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            activateTab(
                stage.getAttribute('data-target-tab') || 'overview',
                stage,
                {
                    source: 'workflow-stage',
                    scroll: true,
                    updateUrl: true
                }
            );
        },
        true
    );

    function initialize() {
        var root = document.querySelector('.pt-page');

        if (!root) {
            return;
        }

        var url = currentUrl();
        var requestedTab =
            url.searchParams.get('tab')
            || root.getAttribute('data-project-default-tab')
            || 'overview';

        activateTab(requestedTab, null, {
            source: 'page-load',
            scroll: false,
            updateUrl: false
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener(
            'DOMContentLoaded',
            initialize,
            {
                once: true
            }
        );
    } else {
        initialize();
    }
})();
