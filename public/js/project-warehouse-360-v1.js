/* EGO_PROJECT_WAREHOUSE_360_UI_V1 */
(function () {
    'use strict';

    function actionTarget(action, text) {
        const value = ((action || '') + ' ' + (text || '')).toLowerCase();
        if (/vat-tu|material/.test(value)) return 'materials';
        if (/nhat-ky|phan-cong|bat-dau-thi-cong|thi-cong|installation/.test(value)) return 'installation';
        if (/nghiem-thu|bao-hanh|acceptance|warranty/.test(value)) return 'acceptance';
        if (/khao-sat|phuong-an|survey|proposal/.test(value)) return 'survey';
        if (/nhan-su-phu-trach|chinh-sua-thong-tin|update-basic|people/.test(value)) return 'overview';
        return 'overview';
    }

    function getDock(panel) {
        let dock = panel.querySelector(':scope > .ego-tab-action-dock');
        if (dock) return dock;

        dock = document.createElement('section');
        dock.className = 'ego-tab-action-dock';
        dock.innerHTML = [
            '<div class="ego-tab-action-dock__head">',
                '<strong><i class="bi bi-shield-check"></i> Phê duyệt & chỉnh sửa tại tab này</strong>',
                '<span>Đúng quyền hiện tại</span>',
            '</div>',
            '<div class="ego-tab-action-dock__body"></div>'
        ].join('');
        panel.insertBefore(dock, panel.firstChild);
        return dock;
    }

    function moveNodeToTab(node) {
        if (!node || node.dataset.pw360Moved === '1') return false;
        const form = node.matches('form') ? node : node.querySelector('form[action]');
        const action = form ? form.getAttribute('action') : '';
        const targetName = actionTarget(action, node.textContent);
        const targetPanel = document.querySelector('[data-pt-panel="' + targetName + '"]');
        if (!targetPanel) return false;

        const dock = getDock(targetPanel);
        const body = dock.querySelector('.ego-tab-action-dock__body');
        node.dataset.pw360Moved = '1';
        body.appendChild(node);
        return true;
    }

    function localizeTabActions() {
        const actionPanel = document.querySelector('[data-pt-panel="action"]');
        const editPanel = document.querySelector('[data-pt-panel="edit"]');
        let moved = 0;

        if (actionPanel) {
            Array.from(actionPanel.children).forEach(function (node) {
                if (moveNodeToTab(node)) moved++;
            });
        }

        if (editPanel) {
            const candidates = editPanel.querySelectorAll(':scope > article, :scope > .pt-card, :scope > form');
            Array.from(candidates).forEach(function (node) {
                if (moveNodeToTab(node)) moved++;
            });
        }

        ['action', 'edit'].forEach(function (name) {
            const tab = document.querySelector('[data-pt-tab="' + name + '"]');
            const panel = document.querySelector('[data-pt-panel="' + name + '"]');
            if (tab) tab.hidden = true;
            if (panel) panel.hidden = true;
        });

        return moved;
    }

    function enhanceTabMotion() {
        document.addEventListener('click', function (event) {
            const tab = event.target.closest('[data-pt-tab]');
            if (!tab) return;
            const name = tab.getAttribute('data-pt-tab');
            window.setTimeout(function () {
                const panel = document.querySelector('[data-pt-panel="' + name + '"]');
                if (!panel) return;
                panel.classList.remove('is-entering');
                void panel.offsetWidth;
                panel.classList.add('is-entering');
            }, 20);
        });
    }

    function addRipple() {
        document.addEventListener('pointerdown', function (event) {
            const target = event.target.closest('.pt-btn, .pt-tab, .pt-flow__step');
            if (!target || target.disabled) return;
            const rect = target.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const ripple = document.createElement('span');
            ripple.className = 'pw360-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (event.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (event.clientY - rect.top - size / 2) + 'px';
            target.style.position = target.style.position || 'relative';
            target.appendChild(ripple);
            window.setTimeout(function () { ripple.remove(); }, 520);
        }, { passive: true });
    }

    function focusCurrentWarehouseRow() {
        const active = document.querySelector('.pt-wh-v2-tab.active');
        if (active && active.scrollIntoView) {
            active.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        }
    }

    function init() {
        localizeTabActions();
        enhanceTabMotion();
        addRipple();
        focusCurrentWarehouseRow();
        document.documentElement.classList.add('pw360-ready');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
