(() => {
    'use strict';

    const tabs = Array.from(document.querySelectorAll('[data-profile-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-profile-panel]'));

    const activate = (key) => {
        tabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.profileTab === key));
        panels.forEach((panel) => panel.classList.toggle('is-active', panel.dataset.profilePanel === key));
        window.history.replaceState({}, '', `#${encodeURIComponent(key)}`);
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.profileTab)));

    const hashKey = decodeURIComponent(window.location.hash.replace(/^#/, ''));
    if (hashKey && tabs.some((tab) => tab.dataset.profileTab === hashKey)) {
        activate(hashKey);
    }

    document.querySelectorAll('[data-select-group]').forEach((button) => {
        button.addEventListener('click', () => {
            const key = button.dataset.selectGroup;
            const checkboxes = Array.from(document.querySelectorAll(`[data-app-checkbox="${CSS.escape(key)}"]`));
            const shouldCheck = checkboxes.some((checkbox) => !checkbox.checked);
            checkboxes.forEach((checkbox) => { checkbox.checked = shouldCheck; });
            button.textContent = shouldCheck ? 'Bỏ chọn tất cả' : 'Chọn tất cả';
        });
    });

    document.querySelectorAll('[data-featured-checkbox]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            const profile = checkbox.dataset.featuredCheckbox;
            const featured = Array.from(document.querySelectorAll(`[data-featured-checkbox="${CSS.escape(profile)}"]`));
            const checked = featured.filter((item) => item.checked);

            if (checked.length > 3) {
                checkbox.checked = false;
                window.alert('Mỗi vai trò chỉ được chọn tối đa 3 ứng dụng truy cập nhanh.');
                return;
            }

            if (checkbox.checked) {
                const card = checkbox.closest('.ws-app-choice');
                const appCheckbox = card?.querySelector('[data-app-checkbox]');
                if (appCheckbox) appCheckbox.checked = true;
            }
        });
    });

    document.querySelectorAll('[data-app-checkbox]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
            if (checkbox.checked) return;
            const card = checkbox.closest('.ws-app-choice');
            const featured = card?.querySelector('[data-featured-checkbox]');
            if (featured) featured.checked = false;
        });
    });
})();
