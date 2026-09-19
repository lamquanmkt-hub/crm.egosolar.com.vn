/* EGO PROJECT TEST CORE V4 - Đã bỏ toàn bộ handler vật tư ghi tay cũ. */
(() => {
    'use strict';
    const q = (selector, context = document) => context.querySelector(selector);
    const qa = (selector, context = document) => Array.from(context.querySelectorAll(selector));

    qa('[data-pt-tab]').forEach((button) => button.addEventListener('click', () => {
        qa('[data-pt-tab]').forEach((item) => item.classList.toggle('active', item === button));
        qa('[data-pt-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.ptPanel === button.dataset.ptTab));
        try { localStorage.setItem('pt-active-tab', button.dataset.ptTab); } catch (error) { /* ignore */ }
    }));

    const savedTab = (() => {
        try { return localStorage.getItem('pt-active-tab'); } catch (error) { return null; }
    })();
    if (savedTab && q(`[data-pt-tab="${savedTab}"]`)) q(`[data-pt-tab="${savedTab}"]`).click();

    qa('[data-pt-counter]').forEach((element) => {
        const end = Number(element.textContent.replace(/\D/g, '')) || 0;
        const startedAt = performance.now();
        const tick = (now) => {
            const progress = Math.min(1, (now - startedAt) / 650);
            const value = Math.round(end * (1 - Math.pow(1 - progress, 3)));
            element.textContent = value.toLocaleString('vi-VN');
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });

    const wizard = q('[data-pt-wizard]');
    if (wizard) {
        let step = 1;
        const max = 3;
        const updateReview = () => {
            qa('[data-review-field]', wizard).forEach((element) => {
                const input = q(`[name="${element.dataset.reviewField}"]`, wizard);
                if (!input) return;
                let value = input.value || '—';
                if (input.tagName === 'SELECT') value = input.options[input.selectedIndex]?.text || '—';
                element.textContent = value;
            });
        };
        const render = () => {
            qa('[data-wizard-step]', wizard).forEach((element) => element.classList.toggle('active', Number(element.dataset.wizardStep) === step));
            qa('[data-wizard-panel]', wizard).forEach((element) => element.classList.toggle('active', Number(element.dataset.wizardPanel) === step));
            const previous = q('[data-wizard-prev]', wizard);
            const next = q('[data-wizard-next]', wizard);
            const submit = q('[data-wizard-submit]', wizard);
            if (previous) previous.style.display = step === 1 ? 'none' : 'inline-flex';
            if (next) next.style.display = step === max ? 'none' : 'inline-flex';
            if (submit) submit.style.display = step === max ? 'inline-flex' : 'none';
            if (step === max) updateReview();
        };
        q('[data-wizard-next]', wizard)?.addEventListener('click', () => {
            const panel = q(`[data-wizard-panel="${step}"]`, wizard);
            const required = qa('[required]', panel);
            if (required.some((input) => !input.reportValidity())) return;
            step = Math.min(max, step + 1);
            render();
        });
        q('[data-wizard-prev]', wizard)?.addEventListener('click', () => {
            step = Math.max(1, step - 1);
            render();
        });
        render();
    }

    const customer = q('[data-customer-select]');
    if (customer) {
        customer.addEventListener('change', () => {
            const option = customer.options[customer.selectedIndex];
            if (!option) return;
            const name = q('[name="contact_name"]');
            const phone = q('[name="contact_phone"]');
            const address = q('[name="address"]');
            if (name && !name.value) name.value = option.dataset.name || '';
            if (phone && !phone.value) phone.value = option.dataset.phone || '';
            if (address && !address.value) address.value = option.dataset.address || '';
        });
    }

    document.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-line]');
        if (removeButton) removeButton.closest('tr')?.remove();
    });

    qa('[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm || 'Xác nhận thực hiện thao tác này?')) event.preventDefault();
    }));

    const search = q('[data-live-filter]');
    if (search) {
        search.addEventListener('input', () => {
            const term = search.value.trim().toLowerCase();
            qa('[data-filter-row]').forEach((row) => {
                row.hidden = !String(row.dataset.filterRow || '').toLowerCase().includes(term);
            });
        });
    }
})();
