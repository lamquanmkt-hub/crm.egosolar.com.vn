(function () {
    'use strict';

    var root = document.getElementById('egoSmartSearch');
    if (!root) return;

    var launcher = document.getElementById('egoSmartSearchLauncher');
    var topbarButton = document.getElementById('egoSmartSearchTopbarButton');
    var panel = document.getElementById('egoSmartSearchPanel');
    var closeButton = document.getElementById('egoSmartSearchClose');
    var backdrop = document.getElementById('egoSmartSearchBackdrop');
    var form = document.getElementById('egoSmartSearchForm');
    var input = document.getElementById('egoSmartSearchInput');
    var conversation = document.getElementById('egoSmartSearchConversation');
    var welcome = document.getElementById('egoSmartSearchWelcome');
    var suggestions = document.getElementById('egoSmartSearchSuggestions');
    var scope = document.getElementById('egoSmartSearchScope');
    var submitButton = form ? form.querySelector('button[type="submit"]') : null;
    var isLoading = false;
    var bootstrapped = false;

    function endpoint(name) {
        return root.getAttribute('data-' + name + '-url') || '';
    }

    function currentPath() {
        return window.location.pathname + window.location.search;
    }

    function openPanel() {
        root.classList.add('is-open');
        launcher.setAttribute('aria-expanded', 'true');
        if (topbarButton) topbarButton.setAttribute('aria-expanded', 'true');
        document.body.classList.add('ego-smart-search-open');
        ensureBootstrap();
        window.setTimeout(function () { input.focus(); }, 180);
    }

    function closePanel() {
        root.classList.remove('is-open');
        launcher.setAttribute('aria-expanded', 'false');
        if (topbarButton) topbarButton.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('ego-smart-search-open');
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeUrl(value) {
        var url = String(value || '');
        if (!url) return '#';
        try {
            var parsed = new URL(url, window.location.origin);
            if (parsed.origin !== window.location.origin) return '#';
            return parsed.href;
        } catch (error) {
            return '#';
        }
    }

    function scrollToEnd() {
        window.requestAnimationFrame(function () {
            conversation.scrollTop = conversation.scrollHeight;
        });
    }

    function removeWelcome() {
        if (welcome && welcome.parentNode) {
            welcome.parentNode.removeChild(welcome);
            welcome = null;
        }
    }

    function addUserMessage(text) {
        removeWelcome();
        var wrapper = document.createElement('div');
        wrapper.className = 'ego-smart-search__message ego-smart-search__message--user';
        wrapper.innerHTML = '<div class="ego-smart-search__user-bubble">' + escapeHtml(text) + '</div>';
        conversation.appendChild(wrapper);
        scrollToEnd();
    }

    function addLoading() {
        var wrapper = document.createElement('div');
        wrapper.className = 'ego-smart-search__message';
        wrapper.setAttribute('data-search-loading', '1');
        wrapper.innerHTML = '<div class="ego-smart-search__loading"><i class="bi bi-arrow-repeat"></i><span>Đang tra cứu dữ liệu được cấp quyền...</span></div>';
        conversation.appendChild(wrapper);
        scrollToEnd();
    }

    function removeLoading() {
        var node = conversation.querySelector('[data-search-loading="1"]');
        if (node) node.remove();
    }

    function addError(message) {
        removeLoading();
        var wrapper = document.createElement('div');
        wrapper.className = 'ego-smart-search__message';
        wrapper.innerHTML = '<div class="ego-smart-search__error"><i class="bi bi-exclamation-circle"></i><span>' + escapeHtml(message) + '</span></div>';
        conversation.appendChild(wrapper);
        scrollToEnd();
    }

    function renderMetrics(metrics) {
        if (!Array.isArray(metrics) || !metrics.length) return '';
        return '<div class="ego-smart-search__metrics">' + metrics.map(function (metric) {
            var tone = escapeHtml(metric.tone || 'primary');
            return '<div class="ego-smart-search__metric ego-smart-search__metric--' + tone + '">' +
                '<span class="ego-smart-search__metric-top"><i class="bi ' + escapeHtml(metric.icon || 'bi-circle') + '"></i>' + escapeHtml(metric.label || '') + '</span>' +
                '<strong title="' + escapeHtml(metric.value || '') + '">' + escapeHtml(metric.value || '') + '</strong>' +
            '</div>';
        }).join('') + '</div>';
    }

    function renderItems(items) {
        if (!Array.isArray(items) || !items.length) return '';
        return '<div class="ego-smart-search__results">' + items.map(function (item) {
            var tone = escapeHtml(item.tone || 'info');
            return '<a class="ego-smart-search__result" href="' + escapeHtml(safeUrl(item.url)) + '">' +
                '<span class="ego-smart-search__result-icon"><i class="bi ' + escapeHtml(item.icon || 'bi-search') + '"></i></span>' +
                '<span class="ego-smart-search__result-copy">' +
                    '<strong class="ego-smart-search__result-title">' + escapeHtml(item.title || '') + '</strong>' +
                    '<span class="ego-smart-search__result-subtitle">' + escapeHtml(item.subtitle || '') + '</span>' +
                    '<span class="ego-smart-search__result-meta">' + escapeHtml(item.meta || '') + '</span>' +
                '</span>' +
                '<span class="ego-smart-search__badge ego-smart-search__badge--' + tone + '">' + escapeHtml(item.badge || 'Xem') + '</span>' +
            '</a>';
        }).join('') + '</div>';
    }

    function addAnswer(data) {
        removeLoading();
        var wrapper = document.createElement('div');
        wrapper.className = 'ego-smart-search__message';
        wrapper.innerHTML = '<article class="ego-smart-search__answer">' +
            '<div class="ego-smart-search__answer-head">' +
                '<strong>' + escapeHtml(data.title || 'Kết quả tìm kiếm') + '</strong>' +
                '<p>' + escapeHtml(data.summary || '') + '</p>' +
            '</div>' +
            renderMetrics(data.metrics) +
            renderItems(data.items) +
            '<footer class="ego-smart-search__answer-foot">' +
                '<span title="' + escapeHtml(data.source || '') + '"><i class="bi bi-database-check"></i> ' + escapeHtml(data.source || 'CRM') + '</span>' +
                '<span title="' + escapeHtml(data.scope || '') + '">' + escapeHtml(data.updated_at || '') + '</span>' +
            '</footer>' +
        '</article>';
        conversation.appendChild(wrapper);
        scrollToEnd();
    }

    function renderSuggestions(list) {
        suggestions.innerHTML = '';
        (Array.isArray(list) ? list : []).forEach(function (text) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'ego-smart-search__suggestion';
            button.innerHTML = '<i class="bi bi-arrow-up-right-circle"></i><span>' + escapeHtml(text) + '</span>';
            button.addEventListener('click', function () {
                input.value = text;
                submitQuery(text);
            });
            suggestions.appendChild(button);
        });
    }

    function ensureBootstrap() {
        if (bootstrapped) return;
        bootstrapped = true;

        var url = endpoint('bootstrap');
        if (!url) return;
        url += (url.indexOf('?') === -1 ? '?' : '&') + 'path=' + encodeURIComponent(currentPath());

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Không tải được gợi ý tìm kiếm.');
                return response.json();
            })
            .then(function (data) {
                if (scope) {
                    scope.innerHTML = '<i class="bi bi-shield-check"></i><span>' + escapeHtml(data.scope || 'Theo quyền tài khoản') + '</span>';
                }
                renderSuggestions(data.suggestions || []);
                if (data.available === false) {
                    input.disabled = true;
                    if (submitButton) submitButton.disabled = true;
                    addError('Tài khoản chưa được cấp quyền tra cứu dữ liệu.');
                }
            })
            .catch(function (error) {
                if (scope) scope.innerHTML = '<i class="bi bi-shield-exclamation"></i><span>Không tải được phạm vi quyền</span>';
            });
    }

    function setLoading(value) {
        isLoading = value;
        if (submitButton) submitButton.disabled = value;
        input.disabled = value;
    }

    function submitQuery(rawText) {
        var text = String(rawText || input.value || '').trim();
        if (isLoading || text.length < 2) return;

        addUserMessage(text);
        addLoading();
        setLoading(true);
        input.value = '';
        input.style.height = 'auto';

        var url = endpoint('query');
        url += (url.indexOf('?') === -1 ? '?' : '&') +
            'q=' + encodeURIComponent(text) +
            '&path=' + encodeURIComponent(currentPath());

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok) throw new Error(data.message || 'Không thể tra cứu dữ liệu.');
                    return data;
                });
            })
            .then(function (data) {
                if (data.ok === false) throw new Error(data.message || 'Không thể tra cứu dữ liệu.');
                addAnswer(data);
            })
            .catch(function (error) {
                addError(error.message || 'Không thể tra cứu dữ liệu.');
            })
            .finally(function () {
                setLoading(false);
                input.focus();
            });
    }

    launcher.addEventListener('click', openPanel);
    if (topbarButton) topbarButton.addEventListener('click', openPanel);
    closeButton.addEventListener('click', closePanel);
    backdrop.addEventListener('click', closePanel);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        submitQuery();
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submitQuery();
        }
    });

    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 98) + 'px';
    });

    document.addEventListener('keydown', function (event) {
        var target = event.target;
        var isTyping = target && (/INPUT|TEXTAREA|SELECT/.test(target.tagName) || target.isContentEditable);

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            root.classList.contains('is-open') ? closePanel() : openPanel();
            return;
        }

        if (event.key === '/' && !isTyping && !root.classList.contains('is-open')) {
            event.preventDefault();
            openPanel();
            return;
        }

        if (event.key === 'Escape' && root.classList.contains('is-open')) {
            closePanel();
        }
    });
})();

/* EGO_HIDE_COMPANY_WORKING_ROW_START */

(function () {
    'use strict';

    if (window.__egoHideCompanyWorkingRowLoaded) {
        return;
    }

    window.__egoHideCompanyWorkingRowLoaded = true;

    function normalize(value) {
        return String(value || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function getSidebarRoots() {
        var selectors = [
            '#sidebar',
            '#crmSidebar',
            '.sidebar',
            '.crm-sidebar',
            '.ego-sidebar',
            'aside[class*="sidebar"]',
            '[class*="sidebar-nav"]',
            '[class*="navigation-sidebar"]'
        ];

        var roots = [];

        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (element) {
                if (!roots.includes(element)) {
                    roots.push(element);
                }
            });
        });

        return roots;
    }

    function isCompanyLabel(element) {
        if (!element) {
            return false;
        }

        var text = normalize(element.textContent);

        return (
            text === 'công ty làm việc' ||
            text === 'công ty đang làm việc'
        );
    }

    function findCompanyRow(label, sidebar) {
        var current = label;
        var fallback = null;

        for (var level = 0; level < 8; level += 1) {
            if (
                !current ||
                current === sidebar ||
                current === document.body
            ) {
                break;
            }

            var text = normalize(current.textContent);
            var rect = current.getBoundingClientRect();

            var containsCompanyLabel =
                text.includes('công ty làm việc') ||
                text.includes('công ty đang làm việc');

            var containsCompanyCode =
                /\bqt\b/.test(text) ||
                text.includes('quốc tế');

            var containsOtherStatusBlock =
                text.includes('đang online') ||
                text.includes('đang làm việc') ||
                text.includes('nhân viên hoạt động') ||
                text.includes('xem chấm công');

            var containsLanguageBar =
                text.includes('vn') &&
                text.includes('en') &&
                text.includes('cn');

            if (
                containsCompanyLabel &&
                rect.height >= 26 &&
                rect.height <= 110
            ) {
                fallback = current;
            }

            if (
                containsCompanyLabel &&
                containsCompanyCode &&
                !containsOtherStatusBlock &&
                !containsLanguageBar &&
                rect.height >= 26 &&
                rect.height <= 90
            ) {
                return current;
            }

            current = current.parentElement;
        }

        return fallback || label.parentElement;
    }

    function removeCompanyWorkingRows() {
        getSidebarRoots().forEach(function (sidebar) {
            var elements = sidebar.querySelectorAll(
                'span, label, p, strong, small, div'
            );

            Array.prototype.forEach.call(elements, function (element) {
                if (!isCompanyLabel(element)) {
                    return;
                }

                var row = findCompanyRow(element, sidebar);

                if (!row || !sidebar.contains(row)) {
                    return;
                }

                row.setAttribute('aria-hidden', 'true');
                row.remove();
            });
        });
    }

    function scheduleRemove() {
        window.requestAnimationFrame(removeCompanyWorkingRows);
    }

    document.addEventListener(
        'DOMContentLoaded',
        scheduleRemove
    );

    window.addEventListener(
        'load',
        scheduleRemove
    );

    /*
     * Sidebar có thể render lại khi chuyển trang hoặc thu gọn menu.
     */
    var observer = new MutationObserver(function (mutations) {
        var shouldRun = mutations.some(function (mutation) {
            return mutation.addedNodes.length > 0;
        });

        if (shouldRun) {
            scheduleRemove();
        }
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true
    });

    window.setTimeout(scheduleRemove, 200);
    window.setTimeout(scheduleRemove, 700);
    window.setTimeout(scheduleRemove, 1500);

    scheduleRemove();
})();

/* EGO_HIDE_COMPANY_WORKING_ROW_END */
