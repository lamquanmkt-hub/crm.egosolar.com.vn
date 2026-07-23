(() => {
    'use strict';

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    document.querySelectorAll('[data-permission-form]').forEach((form) => {
        const items = [...form.querySelectorAll('[data-permission-item]')];
        const search = form.closest('.ego-settings-card')?.querySelector('[data-permission-search]');
        const checkAll = form.closest('.ego-settings-card')?.querySelector('[data-check-all]');
        const uncheckAll = form.closest('.ego-settings-card')?.querySelector('[data-uncheck-all]');

        const visibleCheckboxes = () => items
            .filter((item) => item.style.display !== 'none')
            .flatMap((item) => [...item.querySelectorAll('input[type="checkbox"]')])
            .filter((input) => !input.disabled);

        search?.addEventListener('input', () => {
            const query = normalize(search.value);

            items.forEach((item) => {
                const haystack = normalize(item.dataset.search || item.textContent);
                item.style.display = !query || haystack.includes(query) ? '' : 'none';
            });
        });

        checkAll?.addEventListener('click', () => {
            visibleCheckboxes().forEach((input) => { input.checked = true; });
        });

        uncheckAll?.addEventListener('click', () => {
            visibleCheckboxes().forEach((input) => { input.checked = false; });
        });

        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"], button:not([type])');
            if (!button) return;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang lưu...';
        });
    });

    const userSearch = document.querySelector('[data-user-search]');
    const userCards = [...document.querySelectorAll('[data-user-name]')];

    userSearch?.addEventListener('input', () => {
        const query = normalize(userSearch.value);
        userCards.forEach((card) => {
            card.style.display = !query || normalize(card.dataset.userName).includes(query) ? '' : 'none';
        });
    });
})();
