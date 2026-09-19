(function () {
    'use strict';

    const DESKTOP_QUERY = '(min-width: 992px)';
    const boundItems = new WeakSet();

    let sidebar = null;
    let flyout = null;
    let activeItem = null;
    let closeTimer = null;

    function isDesktop() {
        return window.matchMedia(DESKTOP_QUERY).matches;
    }

    function useFlyoutMode() {
        return isDesktop() && sidebar && sidebar.classList.contains('ego-collapsed');
    }

    function directChild(element, selector) {
        if (!element) return null;

        return Array.from(element.children || []).find(function (child) {
            return child.matches && child.matches(selector);
        }) || null;
    }

    function getLabel(link) {
        if (!link) return '';

        const node = link.querySelector(
            '.ego-txt, .ego-group-label, .ego-tech-group-label'
        );

        return (node ? node.textContent : link.textContent || '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function getSubmenu(item) {
        return directChild(item, '.ego-sub')
            || item.querySelector(':scope > .ego-sub')
            || item.querySelector('.ego-sub');
    }

    function getSubmenuEntries(item) {
        const submenu = getSubmenu(item);

        if (!submenu) return [];

        function directListItems(container) {
            return Array.from(container.children || []).filter(function (child) {
                return child && child.tagName === 'LI';
            });
        }

        function firstDirect(element, selectors) {
            if (!element) return null;

            for (const selector of selectors) {
                const found = directChild(element, selector);
                if (found) return found;
            }

            return null;
        }

        function entryFromLi(li) {
            const nestedMenu = firstDirect(li, [
                '.ego-sub--nested',
                '.ego-sub',
                'ul',
            ]);
            const details = firstDirect(li, ['details']);

            if (details) {
                const summary = firstDirect(details, ['summary']);
                const childList = firstDirect(details, ['ul']);
                const children = childList
                    ? directListItems(childList).map(entryFromLi).filter(Boolean)
                    : [];

                return {
                    type: 'group',
                    label: getLabel(summary),
                    active: !!(
                        (summary && summary.classList.contains('active'))
                        || details.hasAttribute('open')
                        || children.some(function (child) { return child.active; })
                    ),
                    children: children,
                };
            }

            const toggle = firstDirect(li, [
                'a.ego-sublink--toggle',
                'a[data-ego-type="toggle"]',
                'a[data-bs-toggle="collapse"]',
            ]);

            if (nestedMenu && toggle) {
                const children = directListItems(nestedMenu)
                    .map(entryFromLi)
                    .filter(Boolean);

                return {
                    type: 'group',
                    label: getLabel(toggle),
                    active: !!(
                        toggle.classList.contains('active')
                        || children.some(function (child) { return child.active; })
                    ),
                    children: children,
                };
            }

            const link = firstDirect(li, [
                'a.ego-sublink',
                'a.ego-sales-child-link',
                'a[href]',
            ]);

            if (!link) return null;

            const href = (link.getAttribute('href') || '').trim();
            if (!href || href === '#') return null;

            return {
                type: 'link',
                label: getLabel(link),
                href: href,
                target: link.target || '',
                rel: link.rel || '',
                active: link.classList.contains('active')
                    || link.getAttribute('aria-current') === 'page',
            };
        }

        return directListItems(submenu).map(entryFromLi).filter(Boolean);
    }

    function hasEntries(item) {
        return getSubmenuEntries(item).length > 0;
    }

    function ensureFlyout() {
        if (flyout) return flyout;

        flyout = document.createElement('section');
        flyout.className = 'crm-misa-flyout';
        flyout.setAttribute('role', 'menu');
        flyout.setAttribute('aria-hidden', 'true');
        flyout.innerHTML = [
            '<div class="crm-misa-flyout__header">',
            '  <div class="crm-misa-flyout__title"></div>',
            '  <button type="button" class="crm-misa-flyout__close" aria-label="Đóng menu">×</button>',
            '</div>',
            '<div class="crm-misa-flyout__list"></div>',
        ].join('');

        document.body.appendChild(flyout);

        flyout
            .querySelector('.crm-misa-flyout__close')
            .addEventListener('click', closeFlyout);

        flyout.addEventListener('mouseenter', cancelClose);
        flyout.addEventListener('mouseleave', scheduleClose);

        return flyout;
    }

    function positionFlyout(trigger) {
        if (!sidebar || !flyout || !trigger) return;

        const sidebarRect = sidebar.getBoundingClientRect();
        const triggerRect = trigger.getBoundingClientRect();
        const width = flyout.offsetWidth || 238;
        const height = flyout.offsetHeight || 340;
        const padding = 10;

        const left = Math.min(
            sidebarRect.right + 6,
            Math.max(padding, window.innerWidth - width - padding)
        );

        const top = Math.min(
            Math.max(padding, triggerRect.top - 4),
            Math.max(padding, window.innerHeight - height - padding)
        );

        flyout.style.left = left + 'px';
        flyout.style.top = top + 'px';
    }

    function renderFlyout(item, trigger) {
        if (!isDesktop()) return;

        const entries = getSubmenuEntries(item);

        if (!entries.length) return;

        ensureFlyout();
        cancelClose();

        if (activeItem && activeItem !== item) {
            activeItem.classList.remove('is-flyout-open');
        }

        activeItem = item;
        activeItem.classList.add('is-flyout-open');

        flyout.querySelector(
            '.crm-misa-flyout__title'
        ).textContent = getLabel(trigger) || 'Chức năng';

        const list = flyout.querySelector(
            '.crm-misa-flyout__list'
        );

        list.replaceChildren();

        function makeLink(entry, depth) {
            const link = document.createElement('a');
            const dot = document.createElement('span');
            const text = document.createElement('span');
            const arrow = document.createElement('span');

            link.className = 'crm-misa-flyout__link';
            link.dataset.depth = String(depth || 0);
            link.href = entry.href || '#';

            if (entry.target) link.target = entry.target;
            if (entry.rel) link.rel = entry.rel;
            if (entry.active) link.classList.add('is-active');

            dot.className = 'crm-misa-flyout__dot';
            text.className = 'crm-misa-flyout__text';
            text.textContent = entry.label || 'Chức năng';
            arrow.className = 'crm-misa-flyout__arrow';
            arrow.textContent = '›';

            link.append(dot, text, arrow);
            link.addEventListener('click', closeFlyout);

            return link;
        }

        const rootTitle = getLabel(trigger) || 'Chức năng';
        const stack = [];

        function renderLevel(levelEntries, title, depth) {
            list.replaceChildren();

            if (stack.length) {
                const back = document.createElement('button');
                const backIcon = document.createElement('span');
                const backText = document.createElement('span');

                back.type = 'button';
                back.className = 'crm-misa-flyout__back';
                backIcon.className = 'crm-misa-flyout__back-icon';
                backIcon.textContent = '‹';
                backText.className = 'crm-misa-flyout__back-text';
                backText.textContent = 'Quay lại';
                back.append(backIcon, backText);

                back.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();

                    const previous = stack.pop();
                    renderLevel(previous.entries, previous.title, previous.depth);
                });

                list.appendChild(back);
            }

            flyout.querySelector('.crm-misa-flyout__title').textContent = title;

            (levelEntries || []).forEach(function (entry) {
                if (entry.type === 'group') {
                    const button = document.createElement('button');
                    const dot = document.createElement('span');
                    const text = document.createElement('span');
                    const arrow = document.createElement('span');

                    button.type = 'button';
                    button.className = 'crm-misa-flyout__group-summary crm-misa-flyout__drill';
                    if (entry.active) button.classList.add('is-active');

                    dot.className = 'crm-misa-flyout__dot';
                    text.className = 'crm-misa-flyout__text';
                    text.textContent = entry.label || 'Nhóm chức năng';
                    arrow.className = 'crm-misa-flyout__group-arrow';
                    arrow.textContent = '›';

                    button.append(dot, text, arrow);
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();

                        stack.push({ entries: levelEntries, title: title, depth: depth });
                        renderLevel(
                            entry.children || [],
                            title + ' › ' + (entry.label || 'Nhóm chức năng'),
                            (depth || 0) + 1
                        );
                    });

                    list.appendChild(button);
                } else {
                    list.appendChild(makeLink(entry, depth || 0));
                }
            });

            if (!list.children.length) {
                const empty = document.createElement('div');
                empty.className = 'crm-misa-empty';
                empty.textContent = 'Chưa có chức năng con.';
                list.appendChild(empty);
            }

            requestAnimationFrame(function () {
                positionFlyout(trigger);
            });
        }

        renderLevel(entries, rootTitle, 0);

        flyout.classList.add('is-open');
        flyout.setAttribute('aria-hidden', 'false');

        requestAnimationFrame(function () {
            positionFlyout(trigger);
        });
    }

    function closeFlyout() {
        cancelClose();

        if (activeItem) {
            activeItem.classList.remove('is-flyout-open');
        }

        activeItem = null;

        if (flyout) {
            flyout.classList.remove('is-open');
            flyout.setAttribute('aria-hidden', 'true');
        }
    }

    function scheduleClose() {
        cancelClose();

        closeTimer = window.setTimeout(closeFlyout, 180);
    }

    function cancelClose() {
        if (!closeTimer) return;

        window.clearTimeout(closeTimer);
        closeTimer = null;
    }

    function bindItem(item) {
        if (!item || boundItems.has(item)) return;

        const trigger = directChild(item, '.ego-link')
            || item.querySelector('.ego-link');

        const hasSubmenuEntries = hasEntries(item);

        if (!trigger) return;

        const label = getLabel(trigger);

        if (label) {
            trigger.setAttribute('title', label);
        }

        if (hasSubmenuEntries) {
            item.classList.add('crm-misa-has-sub');
            trigger.setAttribute('aria-haspopup', 'menu');

            trigger.addEventListener('mouseenter', function () {
                if (useFlyoutMode()) {
                    renderFlyout(item, trigger);
                }
            });

            item.addEventListener('mouseleave', function (event) {
                if (!useFlyoutMode()) return;

                const next = event.relatedTarget;

                if (flyout && next && flyout.contains(next)) {
                    return;
                }

                scheduleClose();
            });
        }

        boundItems.add(item);
    }

    function handleDesktopToggle(event) {
        if (!useFlyoutMode()) {
            closeFlyout();
            return;
        }

        const trigger = event.target.closest(
            '#sidebar .ego-link[data-ego-type="toggle"], '
            + '#sidebar .ego-link[data-bs-toggle="collapse"]'
        );

        if (!trigger) return;

        const item = trigger.closest('.ego-item');
        if (!item || !hasEntries(item)) return;

        event.preventDefault();
        event.stopPropagation();

        if (
            typeof event.stopImmediatePropagation === 'function'
        ) {
            event.stopImmediatePropagation();
        }

        if (
            activeItem === item
            && flyout
            && flyout.classList.contains('is-open')
        ) {
            closeFlyout();
            return;
        }

        renderFlyout(item, trigger);
    }

    function removeOldInjectedUi() {
        document
            .querySelectorAll('#sidebar .crm-misa-search')
            .forEach(function (node) {
                node.remove();
            });
    }

    function initSidebar() {
        sidebar = document.querySelector('#sidebar.ego-sidebar');

        if (!sidebar) return;

        document.documentElement.classList.add(
            'crm-misa-sidebar-ready'
        );

        removeOldInjectedUi();

        sidebar.querySelectorAll('.ego-item').forEach(bindItem);
    }

    function init() {
        initSidebar();

        document.addEventListener(
            'click',
            handleDesktopToggle,
            true
        );

        document.addEventListener('click', function (event) {
            if (
                !flyout
                || !flyout.classList.contains('is-open')
            ) {
                return;
            }

            if (flyout.contains(event.target)) return;
            if (sidebar && sidebar.contains(event.target)) return;

            closeFlyout();
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeFlyout();
            }
        });

        window.addEventListener(
            'resize',
            closeFlyout,
            { passive: true }
        );

        if (sidebar) {
            new MutationObserver(function () {
                if (!useFlyoutMode()) closeFlyout();
            }).observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        }

        window.addEventListener(
            'scroll',
            closeFlyout,
            { passive: true }
        );

        if (sidebar) {
            const observer = new MutationObserver(function () {
                removeOldInjectedUi();
                sidebar.querySelectorAll('.ego-item').forEach(
                    bindItem
                );
            });

            observer.observe(sidebar, {
                childList: true,
                subtree: true,
            });
        }
    }

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
