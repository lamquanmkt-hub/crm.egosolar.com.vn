(() => {
    'use strict';

    const root = document.querySelector('[data-project-workspace]');
    if (!root) return;

    const workflow = root.querySelector('[data-wf2-workspace]');
    if (!workflow) return;

    const beforeVat = workflow.querySelector('[name="contract_amount_before_vat"]');
    const vatRate = workflow.querySelector('[name="vat_rate"]');
    const afterVat = workflow.querySelector('[name="contract_amount_after_vat"]');

    const syncPrimaryAssignee = () => {
        const primary = workflow.querySelector('[data-wf2-primary-assignee]');
        if (!primary) return;

        const selectedId = String(primary.value || '');
        workflow.querySelectorAll('[data-wf2-collaborator-assignee]').forEach((checkbox) => {
            const isPrimary = selectedId !== '' && String(checkbox.value) === selectedId;
            if (isPrimary) checkbox.checked = false;
            checkbox.disabled = isPrimary;
            checkbox.closest('.wf2-assignee-option')?.classList.toggle('is-disabled', isPrimary);
        });
    };

    workflow.querySelector('[data-wf2-primary-assignee]')?.addEventListener('change', syncPrimaryAssignee);
    syncPrimaryAssignee();

    const assignmentModal = workflow.querySelector('[data-wf2-assignment-modal]');
    let assignmentModalLastFocus = null;
    let assignmentModalHideTimer = null;

    const assignmentModalFocusables = () => {
        if (!assignmentModal) return [];

        return Array.from(assignmentModal.querySelectorAll(
            'button:not([disabled]), select:not([disabled]), input:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'
        )).filter((element) => !element.hidden && element.offsetParent !== null);
    };

    const openAssignmentModal = (trigger = null) => {
        if (!assignmentModal) return;

        if (assignmentModalHideTimer) {
            window.clearTimeout(assignmentModalHideTimer);
            assignmentModalHideTimer = null;
        }

        assignmentModalLastFocus = trigger || document.activeElement;
        assignmentModal.hidden = false;
        document.body.classList.add('wf2-modal-open');

        window.requestAnimationFrame(() => {
            assignmentModal.classList.add('is-open');
            syncPrimaryAssignee();

            const firstField = assignmentModal.querySelector('[data-wf2-primary-assignee]')
                || assignmentModalFocusables()[0];

            window.setTimeout(() => firstField?.focus(), 40);
        });
    };

    const closeAssignmentModal = () => {
        if (!assignmentModal || assignmentModal.hidden) return;

        assignmentModal.classList.remove('is-open');
        document.body.classList.remove('wf2-modal-open');

        assignmentModalHideTimer = window.setTimeout(() => {
            assignmentModal.hidden = true;
            assignmentModalHideTimer = null;
            assignmentModalLastFocus?.focus?.();
        }, 170);
    };

    workflow.querySelectorAll('[data-wf2-open-assignment-modal]').forEach((button) => {
        button.addEventListener('click', () => openAssignmentModal(button));
    });

    assignmentModal?.querySelectorAll('[data-wf2-close-assignment-modal]').forEach((button) => {
        button.addEventListener('click', closeAssignmentModal);
    });

    document.addEventListener('keydown', (event) => {
        if (!assignmentModal || assignmentModal.hidden) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeAssignmentModal();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusables = assignmentModalFocusables();
        if (!focusables.length) return;

        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    if (assignmentModal?.dataset.autoOpen === '1') {
        window.setTimeout(() => openAssignmentModal(), 80);
    }

    const chooseDocumentRequirement = (item) => {
        const form = workflow.querySelector('[data-wf2-upload-form]');
        if (!form || !item) return;

        const select = form.querySelector('[data-wf2-document-select]');
        const title = form.querySelector('[data-wf2-document-title]');
        const file = form.querySelector('[data-wf2-document-file]');
        const code = item.dataset.documentCode || '';
        const label = item.dataset.documentLabel || '';

        if (!select || !file || !code) return;

        select.value = code;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        if (title && title.value.trim() === '') title.placeholder = label || 'Để trống sẽ lấy tên hồ sơ chuẩn';
        file.accept = item.dataset.documentAccept || '';

        workflow.querySelectorAll('[data-wf2-document-requirement]').forEach((row) => {
            row.classList.toggle('is-selected', row === item);
        });

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        file.click();
    };

    workflow.querySelectorAll('[data-wf2-document-requirement]').forEach((item) => {
        item.addEventListener('click', () => chooseDocumentRequirement(item));
        item.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            chooseDocumentRequirement(item);
        });
    });

    const calculateContractTotal = () => {
        if (!beforeVat || !vatRate || !afterVat) return;
        const base = Number.parseFloat(beforeVat.value || '0');
        const vat = Number.parseFloat(vatRate.value || '0');
        if (!Number.isFinite(base) || !Number.isFinite(vat) || base < 0 || vat < 0) return;
        if (document.activeElement === afterVat && afterVat.value !== '') return;
        afterVat.value = Math.round(base * (1 + vat / 100));
    };

    beforeVat?.addEventListener('input', calculateContractTotal);
    vatRate?.addEventListener('input', calculateContractTotal);

    workflow.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.classList.contains('wf2-assignment-form')) {
                const primary = form.querySelector('[name="primary_assignee_id"]');
                if (!primary?.value) {
                    event.preventDefault();
                    primary?.focus();
                    window.alert('Hãy chọn người thực hiện chính cho bước này.');
                    return;
                }
            }

            const button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled || event.defaultPrevented) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            window.setTimeout(() => {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }, 8000);
        });
    });

    if (location.hash === '#workflow') {
        window.setTimeout(() => {
            root.querySelector('[data-tabs]')?.scrollIntoView({ block: 'start' });
        }, 60);
    }
})();

/* Tìm sản phẩm Kho bằng AJAX: không nạp hàng trăm option khi mở dự án. */
(() => {
    'use strict';

    const cache = new Map();
    const timers = new WeakMap();
    const formatNumber = (value) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(Number(value || 0));
    const formatMoney = (value) => new Intl.NumberFormat('vi-VN').format(Number(value || 0)) + ' đ';
    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

    async function search(picker, keyword) {
        const host = picker.querySelector('[data-wf2-product-results]');
        const url = picker.dataset.searchUrl;
        if (!host || !url || keyword.length < 2) {
            if (host) host.innerHTML = '';
            picker.classList.remove('is-open');
            return;
        }

        host.innerHTML = '<div class="pw22-product-message">Đang tìm sản phẩm...</div>';
        picker.classList.add('is-open');

        try {
            const key = keyword.toLocaleLowerCase('vi-VN');
            let rows = cache.get(key);
            if (!rows) {
                const endpoint = new URL(url, window.location.origin);
                endpoint.searchParams.set('q', keyword);
                const response = await fetch(endpoint.toString(), {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) throw new Error('Không tải được danh mục sản phẩm.');
                const payload = await response.json();
                rows = Array.isArray(payload.data) ? payload.data : [];
                cache.set(key, rows);
            }

            if (!rows.length) {
                host.innerHTML = '<div class="pw22-product-message">Không tìm thấy sản phẩm phù hợp.</div>';
                return;
            }

            host.innerHTML = rows.map(row => `
                <button type="button" class="pw22-product-result"
                        data-product-id="${escapeHtml(row.id)}"
                        data-product-label="${escapeHtml(row.name)}${row.sku ? ' · ' + escapeHtml(row.sku) : ''}">
                    <span><strong>${escapeHtml(row.name)}</strong><small>SKU ${escapeHtml(row.sku || '—')} · ĐVT ${escapeHtml(row.unit || 'cái')}</small></span>
                    <em>Tồn ${formatNumber(row.stock_qty)}${Number(row.unit_cost || 0) > 0 ? '<small>Giá vốn ' + formatMoney(row.unit_cost) + '</small>' : ''}</em>
                </button>
            `).join('');
        } catch (error) {
            console.error('[Project product search]', error);
            host.innerHTML = `<div class="pw22-product-message">${escapeHtml(error.message || 'Không thể tìm sản phẩm.')}</div>`;
        }
    }

    document.addEventListener('input', event => {
        const input = event.target.closest('[data-wf2-product-search]');
        if (!input) return;
        const picker = input.closest('[data-wf2-product-picker]');
        const oldTimer = timers.get(picker);
        if (oldTimer) clearTimeout(oldTimer);
        const timer = setTimeout(() => search(picker, input.value.trim()), 280);
        timers.set(picker, timer);
    });

    document.addEventListener('focusin', event => {
        const input = event.target.closest('[data-wf2-product-search]');
        if (input && input.value.trim().length >= 2 && !input.closest('[data-wf2-product-picker]').querySelector('[data-wf2-product-id]').value) {
            search(input.closest('[data-wf2-product-picker]'), input.value.trim());
        }
    });

    document.addEventListener('click', event => {
        const result = event.target.closest('.pw22-product-result');
        if (result) {
            const picker = result.closest('[data-wf2-product-picker]');
            picker.querySelector('[data-wf2-product-id]').value = result.dataset.productId || '';
            picker.querySelector('[data-wf2-product-search]').value = result.dataset.productLabel || '';
            picker.querySelector('[data-wf2-product-results]').innerHTML = '';
            picker.classList.remove('is-open');
            return;
        }

        const clear = event.target.closest('[data-wf2-product-clear]');
        if (clear) {
            const picker = clear.closest('[data-wf2-product-picker]');
            picker.querySelector('[data-wf2-product-id]').value = '';
            const input = picker.querySelector('[data-wf2-product-search]');
            input.value = '';
            picker.querySelector('[data-wf2-product-results]').innerHTML = '';
            picker.classList.remove('is-open');
            input.focus();
            return;
        }

        document.querySelectorAll('[data-wf2-product-picker].is-open').forEach(picker => {
            if (!picker.contains(event.target)) picker.classList.remove('is-open');
        });
    });

    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('form[action*="/chon-hang"]')) return;
        const missing = [...form.querySelectorAll('[data-wf2-product-id]')].find(input => !input.value);
        if (missing) {
            event.preventDefault();
            const picker = missing.closest('[data-wf2-product-picker]');
            const searchInput = picker.querySelector('[data-wf2-product-search]');
            searchInput.focus();
            searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.alert('Còn dòng chưa chọn sản phẩm/SKU thực tế.');
        }
    });
})();


/* EGO Workflow document settings modal V1 */
(() => {
    'use strict';

    const root = document.querySelector('[data-wf2-workspace]');
    const modal = root?.querySelector('[data-wf2-document-settings-modal]');
    if (!root || !modal || modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';

    const form = modal.querySelector('[data-wf2-document-settings-form]');
    const resetForm = modal.querySelector('[data-wf2-document-settings-reset-form]');
    const list = modal.querySelector('[data-wf2-document-settings-list]');
    const template = modal.querySelector('[data-wf2-document-setting-template]');
    const scope = modal.querySelector('[data-wf2-document-settings-scope]');
    const resetScope = modal.querySelector('[data-wf2-reset-scope]');
    const deletedToggle = modal.querySelector('[data-wf2-toggle-deleted-document-settings]');
    const deletedCountLabel = modal.querySelector('[data-wf2-deleted-document-count]');
    let lastFocus = null;
    let hideTimer = null;

    const rows = () => Array.from(list?.querySelectorAll('[data-wf2-document-setting-row]') || []);

    const reindex = () => {
        rows().forEach((row, index) => {
            row.querySelectorAll('[name]').forEach((field) => {
                field.name = field.name.replace(/documents\[[^\]]+\]/, `documents[${index}]`);
            });
            const orderField = row.querySelector('[data-doc-field="sort_order"]');
            if (orderField) orderField.value = String((index + 1) * 10);
        });
    };

    const updateDeletedSummary = () => {
        const deletedRows = rows().filter((row) => String(row.querySelector('[data-doc-field="is_active"]')?.value || '0') !== '1');
        if (deletedToggle) deletedToggle.hidden = deletedRows.length === 0;
        if (deletedCountLabel) {
            deletedCountLabel.textContent = list?.classList.contains('show-inactive')
                ? `Ẩn mục đã xóa (${deletedRows.length})`
                : `Mục đã xóa (${deletedRows.length})`;
        }
    };

    const updateRowState = (row) => {
        const active = row.querySelector('[data-doc-field="is_active"]');
        const button = row.querySelector('[data-wf2-doc-setting-toggle]');
        const isActive = String(active?.value || '0') === '1';
        row.classList.toggle('is-inactive', !isActive);
        if (button) {
            const icon = button.querySelector('i');
            const isNew = row.classList.contains('is-new');
            if (icon) icon.className = `bi ${isNew || isActive ? 'bi-trash' : 'bi-arrow-counterclockwise'}`;
            button.title = isNew
                ? 'Xóa dòng mới'
                : (isActive ? 'Xóa khỏi bước' : 'Khôi phục mục đã xóa');
            button.setAttribute('aria-label', button.title);
        }
        updateDeletedSummary();
    };

    const focusables = () => Array.from(modal.querySelectorAll(
        'button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )).filter((element) => !element.closest('[hidden]'));

    const open = (trigger) => {
        if (hideTimer) window.clearTimeout(hideTimer);
        lastFocus = trigger || document.activeElement;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('wf2-modal-open');
        window.requestAnimationFrame(() => {
            modal.classList.add('is-open');
            modal.querySelector('[data-wf2-document-settings-scope]')?.focus();
        });
    };

    const close = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('wf2-modal-open');
        hideTimer = window.setTimeout(() => {
            modal.hidden = true;
            lastFocus?.focus?.();
        }, 170);
    };

    root.querySelectorAll('[data-wf2-open-document-settings]').forEach((button) => {
        button.addEventListener('click', () => open(button));
    });
    modal.querySelectorAll('[data-wf2-close-document-settings]').forEach((button) => button.addEventListener('click', close));

    modal.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-wf2-add-document-setting]');
        if (addButton && template && list) {
            const index = rows().length;
            const html = template.innerHTML
                .replaceAll('__INDEX__', String(index))
                .replaceAll('__ORDER__', String((index + 1) * 10));
            list.insertAdjacentHTML('beforeend', html);
            const row = rows().at(-1);
            reindex();
            row?.querySelector('[data-doc-field="label"]')?.focus();
            return;
        }

        const toggleButton = event.target.closest('[data-wf2-doc-setting-toggle]');
        if (toggleButton) {
            const row = toggleButton.closest('[data-wf2-document-setting-row]');
            if (!row) return;
            const label = row.querySelector('[data-doc-field="label"]')?.value?.trim() || 'loại hồ sơ này';
            if (row.classList.contains('is-new')) {
                if (!window.confirm(`Xóa dòng “${label}” khỏi danh sách cấu hình?`)) return;
                row.remove();
                reindex();
                updateDeletedSummary();
                return;
            }
            const active = row.querySelector('[data-doc-field="is_active"]');
            const isActive = String(active?.value || '0') === '1';
            if (isActive) {
                const accepted = window.confirm(
                    `Xóa “${label}” khỏi bước này?

` +
                    'Các file đã tải vẫn được giữ trong lịch sử và không bị xóa.'
                );
                if (!accepted) return;
                active.value = '0';
            } else {
                active.value = '1';
            }
            updateRowState(row);
            return;
        }

        const deletedButton = event.target.closest('[data-wf2-toggle-deleted-document-settings]');
        if (deletedButton) {
            list?.classList.toggle('show-inactive');
            updateDeletedSummary();
            return;
        }

        const up = event.target.closest('[data-wf2-doc-setting-up]');
        if (up) {
            const row = up.closest('[data-wf2-document-setting-row]');
            if (row?.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
            reindex();
            return;
        }

        const down = event.target.closest('[data-wf2-doc-setting-down]');
        if (down) {
            const row = down.closest('[data-wf2-document-setting-row]');
            if (row?.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
            reindex();
            return;
        }

        const resetButton = event.target.closest('[data-wf2-reset-document-settings]');
        if (resetButton) {
            const currentScope = scope?.value || 'project';
            const message = currentScope === 'global'
                ? 'Khôi phục mẫu hồ sơ dùng chung về cấu hình gốc? Các cấu hình riêng từng dự án vẫn được giữ.'
                : 'Bỏ cấu hình riêng của dự án này và quay về mẫu hồ sơ dùng chung/mặc định?';
            if (!window.confirm(message)) return;
            if (resetScope) resetScope.value = currentScope;
            resetForm?.submit();
        }
    });

    scope?.addEventListener('change', () => {
        if (resetScope) resetScope.value = scope.value;
    });

    form?.addEventListener('submit', (event) => {
        reindex();
        const activeRows = rows().filter((row) => String(row.querySelector('[data-doc-field="is_active"]')?.value || '0') === '1');
        if (!activeRows.length && !window.confirm('Không còn loại hồ sơ nào đang áp dụng. Bạn vẫn muốn lưu?')) {
            event.preventDefault();
            return;
        }
        const missingLabel = activeRows.find((row) => !row.querySelector('[data-doc-field="label"]')?.value.trim());
        if (missingLabel) {
            event.preventDefault();
            missingLabel.querySelector('[data-doc-field="label"]')?.focus();
            window.alert('Hãy nhập tên cho tất cả loại hồ sơ đang áp dụng.');
        }
    });

    rows().forEach(updateRowState);
    updateDeletedSummary();

    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }
        if (event.key !== 'Tab') return;
        const elements = focusables();
        if (!elements.length) return;
        const first = elements[0];
        const last = elements[elements.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
