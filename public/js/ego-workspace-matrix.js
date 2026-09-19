(() => {
    'use strict';

    const form = document.getElementById('workspaceMatrixForm');
    if (!form) return;

    const appRows = [...form.querySelectorAll('[data-app-row]')];
    const searchInput = form.querySelector('[data-matrix-search]');
    const categoryFilter = form.querySelector('[data-category-filter]');

    const normalize = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();

    const appCheckboxes = (profileKey = null) => {
        const selector = profileKey
            ? `input[type="checkbox"][data-profile-key="${CSS.escape(profileKey)}"]`
            : 'input[type="checkbox"][data-profile-key]';
        return [...form.querySelectorAll(selector)];
    };

    const enabledAppCheckboxes = (profileKey = null) => appCheckboxes(profileKey)
        .filter((checkbox) => !checkbox.disabled);

    const refreshRowVisibility = () => {
        const query = normalize(searchInput?.value);
        const category = categoryFilter?.value || 'all';

        appRows.forEach((row) => {
            const matchesQuery = query === '' || normalize(row.dataset.search).includes(query);
            const matchesCategory = category === 'all' || row.dataset.category === category;
            row.hidden = !(matchesQuery && matchesCategory);
        });
    };

    const selectedIdsForProfile = (profileKey) => new Set(
        appCheckboxes(profileKey)
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.dataset.appId)
    );

    const refreshFeaturedForProfile = (profileKey) => {
        const selectedIds = selectedIdsForProfile(profileKey);
        const selects = [...form.querySelectorAll(`[data-featured-select="${CSS.escape(profileKey)}"]`)];

        selects.forEach((select) => {
            [...select.options].forEach((option) => {
                if (!option.value) return;
                option.disabled = !selectedIds.has(option.value) && option.value !== select.value;
            });

            if (select.value && !selectedIds.has(select.value)) {
                select.value = '';
            }
        });
    };

    const refreshCounts = () => {
        const profileKeys = new Set(appCheckboxes().map((checkbox) => checkbox.dataset.profileKey));
        let total = 0;

        profileKeys.forEach((profileKey) => {
            const count = appCheckboxes(profileKey).filter((checkbox) => checkbox.checked).length;
            total += count;
            form.querySelectorAll(`[data-profile-count="${CSS.escape(profileKey)}"]`).forEach((node) => {
                node.textContent = `${count} ứng dụng đang bật`;
            });
            refreshFeaturedForProfile(profileKey);
        });

        const totalNode = form.querySelector('[data-total-selection]');
        if (totalNode) totalNode.textContent = `${total} lượt hiển thị đang được cấu hình`;
    };

    searchInput?.addEventListener('input', refreshRowVisibility);
    categoryFilter?.addEventListener('change', refreshRowVisibility);

    form.querySelectorAll('[data-column-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const profileKey = button.dataset.columnToggle;
            const checkboxes = enabledAppCheckboxes(profileKey);
            const shouldCheck = checkboxes.some((checkbox) => !checkbox.checked);
            checkboxes.forEach((checkbox) => { checkbox.checked = shouldCheck; });
            refreshCounts();
        });
    });

    form.querySelectorAll('[data-row-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const appId = button.dataset.rowToggle;
            const checkboxes = [...form.querySelectorAll(`input[type="checkbox"][data-app-id="${CSS.escape(appId)}"]`)]
                .filter((checkbox) => !checkbox.disabled);
            const shouldCheck = checkboxes.some((checkbox) => !checkbox.checked);
            checkboxes.forEach((checkbox) => { checkbox.checked = shouldCheck; });
            refreshCounts();
        });
    });

    appCheckboxes().forEach((checkbox) => {
        checkbox.addEventListener('change', () => refreshCounts());
    });

    form.querySelectorAll('[data-featured-select]').forEach((select) => {
        select.addEventListener('change', () => {
            const profileKey = select.dataset.featuredSelect;
            const selectedValue = select.value;

            if (selectedValue) {
                const duplicates = [...form.querySelectorAll(`[data-featured-select="${CSS.escape(profileKey)}"]`)]
                    .filter((item) => item !== select && item.value === selectedValue);

                if (duplicates.length) {
                    select.value = '';
                    window.alert('Ứng dụng này đã có trong Truy cập nhanh của vai trò.');
                    return;
                }

                const appCheckbox = form.querySelector(
                    `input[type="checkbox"][data-profile-key="${CSS.escape(profileKey)}"][data-app-id="${CSS.escape(selectedValue)}"]`
                );
                if (appCheckbox && !appCheckbox.disabled) appCheckbox.checked = true;
            }

            refreshCounts();
        });
    });

    refreshRowVisibility();
    refreshCounts();
})();
