(function () {
    'use strict';

    function activateMainMaterialsTab() {
        const page = document.querySelector('[data-project-default-tab]');
        if (!page) return;
        const requested = page.dataset.projectDefaultTab;
        if (requested !== 'materials' && !page.classList.contains('is-warehouse-only')) return;
        const button = document.querySelector('[data-pt-tab="materials"]');
        if (button) button.click();
    }

    function setSubtab(root, name, updateUrl) {
        const valid = Array.from(root.querySelectorAll('[data-warehouse-subtab]')).map(el => el.dataset.warehouseSubtab);
        if (!valid.includes(name)) name = valid[0] || 'need';
        root.querySelectorAll('[data-warehouse-subtab]').forEach(button => button.classList.toggle('is-active', button.dataset.warehouseSubtab === name));
        root.querySelectorAll('[data-warehouse-subpanel]').forEach(panel => panel.classList.toggle('is-active', panel.dataset.warehouseSubpanel === name));
        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'materials');
            url.searchParams.set('material_tab', name);
            history.replaceState({}, '', url.toString());
        }
    }

    function initEmbeddedWarehouse() {
        activateMainMaterialsTab();
        document.querySelectorAll('[data-warehouse-embedded-root]').forEach(root => {
            setSubtab(root, root.dataset.defaultSubtab || 'need', false);
            root.querySelectorAll('[data-warehouse-subtab]').forEach(button => button.addEventListener('click', () => setSubtab(root, button.dataset.warehouseSubtab, true)));
            root.querySelectorAll('[data-open-warehouse-subtab]').forEach(button => button.addEventListener('click', () => setSubtab(root, button.dataset.openWarehouseSubtab, true)));
        });
        document.querySelectorAll('[data-warehouse-request-switcher]').forEach(select => select.addEventListener('change', () => { if (select.value) window.location.href = select.value; }));
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initEmbeddedWarehouse);
    else initEmbeddedWarehouse();
})();
