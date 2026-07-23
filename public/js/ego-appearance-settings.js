(() => {
    'use strict';
    const root = document.getElementById('egoAppearanceSettings');
    if (!root) return;
    const preview = root.querySelector('[data-theme-preview]');
    const map = {
        primary_color: '--preview-primary',
        secondary_color: '--preview-secondary',
        sidebar_color: '--preview-sidebar',
        topbar_color: '--preview-topbar',
        page_background: '--preview-page',
    };
    root.querySelectorAll('[data-theme-color]').forEach((input) => {
        const update = () => {
            const key = input.dataset.themeColor;
            if (preview && map[key]) preview.style.setProperty(map[key], input.value);
            root.querySelector(`[data-color-code="${key}"]`)?.replaceChildren(document.createTextNode(input.value.toUpperCase()));
            if (key === 'sidebar_color') root.querySelectorAll('[data-preview-sidebar-bg]').forEach((el) => { el.style.background = input.value; });
            if (key === 'topbar_color') root.querySelectorAll('[data-preview-topbar]').forEach((el) => { el.style.background = input.value; });
            if (key === 'page_background') root.querySelectorAll('[data-preview-page]').forEach((el) => { el.style.background = input.value; });
        };
        input.addEventListener('input', update);
        update();
    });
    const radius = root.querySelector('[data-radius-input]');
    const updateRadius = () => {
        if (!radius) return;
        const value = `${radius.value}px`;
        preview?.style.setProperty('--preview-radius', value);
        const output = root.querySelector('[data-radius-value]');
        if (output) output.textContent = value;
    };
    radius?.addEventListener('input', updateRadius);
    updateRadius();
    const bindPreview = (inputSelector, imageSelector, placeholderSelector = null) => {
        const input = root.querySelector(inputSelector);
        const images = [...root.querySelectorAll(imageSelector)];
        const placeholder = placeholderSelector ? root.querySelector(placeholderSelector) : null;
        input?.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            const url = URL.createObjectURL(file);
            images.forEach((image) => { image.src = url; image.hidden = false; });
            if (placeholder) placeholder.hidden = true;
        });
    };
    bindPreview('[data-input-logo-light]', '[data-preview-logo-light]');
    bindPreview('[data-input-logo-sidebar]', '[data-preview-logo-sidebar]');
    bindPreview('[data-input-favicon]', '[data-preview-favicon]', '[data-favicon-placeholder]');
    const form = document.getElementById('egoAppearanceForm');
    form?.addEventListener('submit', () => {
        const button = form.querySelector('[type="submit"]');
        if (!button) return;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Đang áp dụng...</span>';
    });
})();
