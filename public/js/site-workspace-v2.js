(() => {
    'use strict';

    const root = document.querySelector('.site-v2-page');
    if (!root) return;

    const storageKey = 'ego_site_workspace_view_v2';
    const viewButtons = Array.from(root.querySelectorAll('[data-site-view]'));
    const panels = Array.from(root.querySelectorAll('[data-site-panel]'));

    const setView = (view) => {
        const safeView = view === 'board' ? 'board' : 'list';
        panels.forEach((panel) => {
            panel.hidden = panel.dataset.sitePanel !== safeView;
        });
        viewButtons.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.siteView === safeView);
        });
        try { localStorage.setItem(storageKey, safeView); } catch (_) {}
    };

    viewButtons.forEach((button) => {
        button.addEventListener('click', () => setView(button.dataset.siteView));
    });

    let initialView = 'list';
    try { initialView = localStorage.getItem(storageKey) || 'list'; } catch (_) {}
    setView(initialView);

    requestAnimationFrame(() => {
        root.querySelectorAll('.site-v2-progress').forEach((bar, index) => {
            window.setTimeout(() => bar.classList.add('is-ready'), Math.min(index * 28, 360));
        });
    });

    const searchInput = root.querySelector('input[name="q"]');
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        const typing = target instanceof HTMLInputElement
            || target instanceof HTMLTextAreaElement
            || target instanceof HTMLSelectElement
            || target?.isContentEditable;

        if (event.key === '/' && !typing && searchInput) {
            event.preventDefault();
            searchInput.focus();
            searchInput.select();
        }
    });

    const drawerElement = document.getElementById('siteV2Drawer');
    const drawer = drawerElement && window.bootstrap
        ? bootstrap.Offcanvas.getOrCreateInstance(drawerElement)
        : null;

    const fields = {
        code: document.getElementById('siteV2DrawerCode'),
        name: document.getElementById('siteV2DrawerName'),
        address: document.getElementById('siteV2DrawerAddress'),
        stage: document.getElementById('siteV2DrawerStage'),
        owner: document.getElementById('siteV2DrawerOwner'),
        date: document.getElementById('siteV2DrawerDate'),
        material: document.getElementById('siteV2DrawerMaterial'),
        system: document.getElementById('siteV2DrawerSystem'),
        contact: document.getElementById('siteV2DrawerContact'),
        next: document.getElementById('siteV2DrawerNext'),
        open: document.getElementById('siteV2DrawerOpen'),
    };

    const fillDrawer = (card) => {
        const data = card.dataset;
        const setText = (field, value, fallback = '—') => {
            if (fields[field]) fields[field].textContent = value?.trim() || fallback;
        };

        setText('code', data.siteCode);
        setText('name', data.siteName);
        setText('address', data.siteAddress, 'Chưa cập nhật địa chỉ');
        setText('stage', data.siteStage);
        setText('owner', data.siteOwner);
        setText('date', data.siteDate);
        setText('material', data.siteMaterial);
        setText('system', data.siteSystem);
        setText('contact', data.siteContact, 'Chưa cập nhật người liên hệ');
        setText('next', data.siteNext);

        if (fields.open) {
            const hasUrl = Boolean(data.siteUrl);
            fields.open.href = hasUrl ? data.siteUrl : '#';
            fields.open.hidden = !hasUrl;
        }
        drawer?.show();
    };

    root.addEventListener('click', (event) => {
        const trigger = event.target.closest('.js-site-detail');
        if (!trigger) return;
        const card = trigger.closest('.js-site-card');
        if (!card) return;
        event.preventDefault();
        fillDrawer(card);
    });

    const counters = root.querySelectorAll('[data-count]');
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    counters.forEach((element) => {
        const target = Number(element.dataset.count || 0);
        if (reducedMotion || !Number.isFinite(target) || target <= 0) {
            element.textContent = String(target || 0);
            return;
        }

        const duration = 420;
        const startedAt = performance.now();
        const step = (now) => {
            const progress = Math.min(1, (now - startedAt) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = String(Math.round(target * eased));
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    });
})();
