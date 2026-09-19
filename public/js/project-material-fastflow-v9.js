(function () {
    'use strict';

    const productCache = new Map();
    const warehouseCache = new Map();
    const pending = new Map();

    const qs = (selector, root) => (root || document).querySelector(selector);
    const qsa = (selector, root) => Array.from((root || document).querySelectorAll(selector));

    function number(value) {
        const parsed = Number.parseFloat(String(value ?? '').replace(',', '.'));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatQty(value) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(number(value));
    }

    function formatMoney(value) {
        const amount = number(value);
        return amount > 0 ? new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(amount) + ' đ' : '—';
    }

    async function json(url) {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) {
            let message = 'Không tải được dữ liệu.';
            try {
                const payload = await response.json();
                message = payload.message || message;
            } catch (_) {}
            throw new Error(message);
        }
        return response.json();
    }

    function cachedFetch(cache, key, url) {
        if (cache.has(key)) return Promise.resolve(cache.get(key));
        if (pending.has(key)) return pending.get(key);
        const promise = json(url)
            .then((payload) => {
                const rows = Array.isArray(payload.data) ? payload.data : [];
                cache.set(key, rows);
                return rows;
            })
            .finally(() => pending.delete(key));
        pending.set(key, promise);
        return promise;
    }

    function setLoading(select, loading) {
        select.classList.toggle('is-loading', loading);
        select.setAttribute('aria-busy', loading ? 'true' : 'false');
        if (loading) select.dataset.loadingLabel = select.options[0]?.textContent || '';
    }

    function appendOption(select, value, label, selected, data) {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = label;
        option.selected = Boolean(selected);
        Object.entries(data || {}).forEach(([key, val]) => {
            if (val !== null && val !== undefined) option.dataset[key] = String(val);
        });
        select.appendChild(option);
        return option;
    }

    async function loadProducts(select, query) {
        const base = select.dataset.url;
        if (!base) return;
        const keyword = String(query || '').trim();
        const key = 'products|' + base + '|' + keyword.toLowerCase();
        const selectedId = String(select.value || select.dataset.selected || '');
        const selectedOption = selectedId ? select.querySelector('option[value="' + CSS.escape(selectedId) + '"]') : null;
        const selectedLabel = selectedOption?.textContent || '';

        setLoading(select, true);
        try {
            const url = new URL(base, window.location.origin);
            if (keyword) url.searchParams.set('q', keyword);
            const rows = await cachedFetch(productCache, key, url.toString());
            select.innerHTML = '<option value="">Chọn sản phẩm / SKU</option>';
            let selectedFound = false;
            rows.forEach((row) => {
                const id = String(row.id);
                const isSelected = id === selectedId;
                if (isSelected) selectedFound = true;
                const stockText = row.total_stock_qty !== null && row.total_stock_qty !== undefined
                    ? ' · tồn ' + formatQty(row.total_stock_qty)
                    : '';
                appendOption(
                    select,
                    id,
                    (row.name || 'Sản phẩm #' + id) + ' · ' + (row.sku || 'không SKU') + stockText,
                    isSelected,
                    {
                        unit: row.unit || 'cái',
                        serialized: row.is_serialized ? '1' : '0',
                        totalStock: row.total_stock_qty ?? 0
                    }
                );
            });
            if (selectedId && !selectedFound) {
                appendOption(select, selectedId, selectedLabel || ('Sản phẩm #' + selectedId), true, {});
            }
            select.dataset.loaded = '1';
        } catch (error) {
            console.error('[FastFlow V9] products:', error);
            select.dataset.loaded = '0';
        } finally {
            setLoading(select, false);
        }
    }

    async function loadWarehouses(productSelect, warehouseSelect, force) {
        const productId = String(productSelect.value || '');
        if (!productId || !warehouseSelect?.dataset.url) {
            if (warehouseSelect) warehouseSelect.innerHTML = '<option value="">Chọn kho cấp</option>';
            updateStockRow(productSelect.closest('[data-emw9-stock-row]'));
            return;
        }

        const selectedId = String(warehouseSelect.value || warehouseSelect.dataset.selected || '');
        const selectedOption = selectedId ? warehouseSelect.querySelector('option[value="' + CSS.escape(selectedId) + '"]') : null;
        const selectedLabel = selectedOption?.textContent || '';
        const base = warehouseSelect.dataset.url;
        const key = 'warehouses|' + base + '|' + productId;
        if (!force && warehouseSelect.dataset.loadedProduct === productId && warehouseSelect.options.length > 1) {
            updateStockRow(productSelect.closest('[data-emw9-stock-row]'));
            return;
        }

        setLoading(warehouseSelect, true);
        try {
            const url = new URL(base, window.location.origin);
            url.searchParams.set('product_id', productId);
            const rows = await cachedFetch(warehouseCache, key, url.toString());
            warehouseSelect.innerHTML = '<option value="">Chọn kho cấp</option>';
            let selectedFound = false;
            rows.forEach((row) => {
                const id = String(row.id);
                const isSelected = id === selectedId;
                if (isSelected) selectedFound = true;
                const costText = row.unit_cost ? ' · vốn ' + formatMoney(row.unit_cost) : '';
                appendOption(
                    warehouseSelect,
                    id,
                    (row.name || 'Kho #' + id) + ' · khả dụng ' + formatQty(row.available_qty) + costText,
                    isSelected,
                    {
                        stock: row.stock_qty ?? 0,
                        available: row.available_qty ?? 0,
                        reserved: row.reserved_test_qty ?? 0,
                        cost: row.unit_cost ?? 0,
                        location: row.location || ''
                    }
                );
            });
            if (selectedId && !selectedFound) {
                appendOption(warehouseSelect, selectedId, selectedLabel || ('Kho #' + selectedId), true, {});
            }
            warehouseSelect.dataset.loadedProduct = productId;
            updateStockRow(productSelect.closest('[data-emw9-stock-row]'));
        } catch (error) {
            console.error('[FastFlow V9] warehouses:', error);
        } finally {
            setLoading(warehouseSelect, false);
        }
    }

    function updateStockRow(row) {
        if (!row) return;
        const warehouseSelect = qs('[data-emw9-warehouse-select]', row);
        const selected = warehouseSelect?.selectedOptions?.[0];
        const available = selected && selected.value ? number(selected.dataset.available) : null;
        const cost = selected && selected.value ? number(selected.dataset.cost) : 0;
        const required = number(row.dataset.required);

        const stockEl = qs('[data-emw9-live-stock]', row);
        const unitCostEl = qs('[data-emw9-unit-cost]', row);
        const lineCostEl = qs('[data-emw9-line-cost]', row);
        const statusSelect = qs('[data-emw9-status-select]', row);

        if (stockEl) stockEl.textContent = available === null ? '—' : formatQty(available);
        if (unitCostEl) unitCostEl.textContent = formatMoney(cost);
        if (lineCostEl) lineCostEl.textContent = cost > 0 ? 'Thành tiền ' + formatMoney(cost * required) : 'Chưa có giá lô';
        if (statusSelect && available !== null) statusSelect.value = available + 0.000001 >= required ? 'ready' : 'shortage';

        row.classList.remove('emw9-flash');
        void row.offsetWidth;
        row.classList.add('emw9-flash');
    }

    function initWarehouseSelects(root) {
        qsa('[data-emw9-product-select]', root).forEach((productSelect) => {
            if (productSelect.dataset.emw9Bound === '1') return;
            productSelect.dataset.emw9Bound = '1';
            const row = productSelect.closest('[data-emw9-stock-row]');
            const warehouseSelect = qs('[data-emw9-warehouse-select]', row);
            let searchTimer = null;
            let searchBuffer = '';
            let lastKeyAt = 0;

            productSelect.addEventListener('focus', () => {
                if (productSelect.dataset.loaded !== '1') loadProducts(productSelect, '');
            });
            productSelect.addEventListener('mousedown', () => {
                if (productSelect.dataset.loaded !== '1') loadProducts(productSelect, '');
            }, { once: true });
            productSelect.addEventListener('keydown', (event) => {
                if (event.ctrlKey || event.altKey || event.metaKey) return;
                const now = Date.now();
                if (now - lastKeyAt > 1000) searchBuffer = '';
                lastKeyAt = now;
                if (event.key === 'Backspace') searchBuffer = searchBuffer.slice(0, -1);
                else if (event.key.length === 1 && /[\p{L}\p{N}\s._-]/u.test(event.key)) searchBuffer += event.key;
                else return;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => loadProducts(productSelect, searchBuffer), 320);
            });
            productSelect.addEventListener('change', () => {
                if (warehouseSelect) {
                    warehouseSelect.dataset.selected = '';
                    warehouseSelect.innerHTML = '<option value="">Đang tải kho...</option>';
                    loadWarehouses(productSelect, warehouseSelect, true);
                }
            });

            if (warehouseSelect && warehouseSelect.dataset.emw9Bound !== '1') {
                warehouseSelect.dataset.emw9Bound = '1';
                warehouseSelect.addEventListener('focus', () => loadWarehouses(productSelect, warehouseSelect, false));
                warehouseSelect.addEventListener('change', () => updateStockRow(row));
            }
        });
    }

    function setMaterialView(root, view, updateUrl) {
        if (!['proposal', 'issue', 'return'].includes(view)) view = 'proposal';
        root.dataset.materialView = view;
        qsa('[data-emw9-view]', root).forEach((panel) => panel.classList.toggle('is-active', panel.dataset.emw9View === view));
        qsa('button[data-material-view]').forEach((button) => {
            const active = button.dataset.materialView === view;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        if (updateUrl && window.history?.replaceState) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'materials');
            url.searchParams.set('material_view', view);
            window.history.replaceState({}, '', url.toString());
        }
        requestAnimationFrame(() => initWarehouseSelects(root));
    }

    function initTabs(root) {
        setMaterialView(root, root.dataset.materialView || 'proposal', false);
        qsa('button[data-material-view]').forEach((button) => {
            if (button.dataset.emw9Bound === '1') return;
            button.dataset.emw9Bound = '1';
            button.addEventListener('click', () => setMaterialView(root, button.dataset.materialView, true));
        });
    }

    function findSuggestion(value) {
        const normalized = String(value || '').trim().toLocaleLowerCase('vi-VN');
        if (!normalized) return null;
        return qsa('#emw9-product-suggestions option').find((option) =>
            String(option.value || '').trim().toLocaleLowerCase('vi-VN') === normalized
        ) || null;
    }

    function bindTechnicalRow(row) {
        if (!row || row.dataset.emw9Bound === '1') return;
        row.dataset.emw9Bound = '1';
        const nameInput = qs('[data-emw9-tech-name]', row);
        const productInput = qs('[data-emw9-tech-product-id]', row);
        const unitInput = qs('[data-emw9-tech-unit]', row);
        if (nameInput) {
            nameInput.dataset.originalValue = String(nameInput.value || '').trim();
            const sync = () => {
                const option = findSuggestion(nameInput.value);
                if (option) {
                    if (productInput) productInput.value = option.dataset.id || '';
                    if (unitInput && option.dataset.unit) unitInput.value = option.dataset.unit;
                } else if (productInput && String(nameInput.value || '').trim() !== nameInput.dataset.originalValue) {
                    productInput.value = '';
                }
            };
            nameInput.addEventListener('change', sync);
            nameInput.addEventListener('blur', sync);
        }
        const remove = qs('[data-emw9-remove-row]', row);
        if (remove) remove.addEventListener('click', () => {
            const body = row.parentElement;
            const rows = qsa('[data-emw9-tech-row]', body);
            if (rows.length <= 1) {
                qsa('input', row).forEach((input) => {
                    if (input.type === 'number') input.value = '1';
                    else if (input.name === 'unit[]') input.value = 'cái';
                    else input.value = '';
                });
                return;
            }
            row.remove();
        });
    }

    function initTechnicalForm(root) {
        const form = qs('[data-emw9-tech-form]', root);
        if (!form) return;
        const body = qs('[data-emw9-tech-body]', form);
        qsa('[data-emw9-tech-row]', body).forEach(bindTechnicalRow);
        const addButton = qs('[data-emw9-add-row]', form);
        const template = qs('[data-emw9-tech-template]');
        if (addButton && template && addButton.dataset.emw9Bound !== '1') {
            addButton.dataset.emw9Bound = '1';
            addButton.addEventListener('click', () => {
                const fragment = template.content.cloneNode(true);
                const row = qs('[data-emw9-tech-row]', fragment);
                body.appendChild(fragment);
                bindTechnicalRow(row || body.lastElementChild);
                qs('[data-emw9-tech-name]', body.lastElementChild)?.focus();
            });
        }
    }

    function buildTechnicalRow(body, data) {
        const template = qs('[data-emw9-tech-template]');
        if (!template) return null;
        const fragment = template.content.cloneNode(true);
        const row = qs('[data-emw9-tech-row]', fragment);
        const set = (name, value) => { const input = qs('[name="' + name + '"]', row); if (input) input.value = value; };
        set('item_name[]', data?.name || ''); set('quantity[]', data?.quantity || 1); set('unit[]', data?.unit || 'cái'); set('item_note[]', data?.note || ''); set('product_id[]', '');
        body.appendChild(fragment); const appended = body.lastElementChild; bindTechnicalRow(appended); return appended;
    }

    function initExcelImport(root) {
        const form = qs('[data-emw9-tech-form]', root); if (!form) return;
        const input = qs('[data-emw11-excel-input]', form), button = qs('[data-emw11-excel-button]', form), status = qs('[data-emw11-import-status]', form), body = qs('[data-emw9-tech-body]', form);
        if (!input || !button || !body || button.dataset.emw11Bound === '1') return;
        button.dataset.emw11Bound = '1';
        button.addEventListener('click', () => { input.value = ''; input.click(); });
        input.addEventListener('change', async () => {
            const file = input.files?.[0]; if (!file) return;
            const hasData = qsa('[data-emw9-tech-row]', body).some(row => String(qs('[name="item_name[]"]', row)?.value || '').trim() !== '');
            const replace = hasData ? window.confirm('Danh sách đang có dữ liệu. OK = thay thế bằng Excel; Hủy = thêm nối tiếp.') : true;
            const fd = new FormData(); fd.append('file', file);
            const token = qs('input[name="_token"]', form)?.value || qs('meta[name="csrf-token"]')?.content || '';
            button.disabled = true; if (status) status.textContent = 'Đang đọc ' + file.name + '...';
            try {
                const response = await fetch(input.dataset.url, {method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':token},body:fd});
                const payload = await response.json().catch(() => ({})); if (!response.ok) throw new Error(payload.message || 'Không đọc được file Excel.');
                const rows = Array.isArray(payload.data) ? payload.data : []; if (!rows.length) throw new Error('File không có dòng vật tư hợp lệ.');
                if (replace) qsa('[data-emw9-tech-row]', body).forEach(row => row.remove());
                rows.forEach(row => buildTechnicalRow(body, row));
                if (status) status.textContent = 'Đã nhập ' + rows.length + ' dòng' + (payload.meta?.skipped ? ' · bỏ qua ' + payload.meta.skipped + ' dòng trống/tiêu đề' : '') + '. Hãy kiểm tra trước khi gửi Kho.';
            } catch (error) { if (status) status.textContent = error.message; window.alert(error.message); }
            finally { button.disabled = false; }
        });
    }

    function initAftercare(root) {
        const type = qs('[data-emw9-aftercare-type]', root);
        const source = qs('[data-emw9-source-item]', root);
        const unit = qs('[data-emw9-aftercare-unit]', root);
        if (type && source) {
            const desired = type.closest('form')?.querySelector('[name="desired_item_name"]');
            const syncType = () => {
                const additional = type.value === 'additional';
                source.required = !additional;
                if (desired) {
                    desired.required = additional;
                    desired.placeholder = additional ? 'Tên vật tư cần bổ sung' : (type.value === 'exchange' ? 'Tên vật tư muốn đổi sang (để trống nếu cùng model)' : 'Để trống nếu giữ nguyên loại cũ');
                }
            };
            type.addEventListener('change', syncType);
            source.addEventListener('change', () => {
                const option = source.selectedOptions[0];
                if (option?.dataset.unit && unit) unit.value = option.dataset.unit;
            });
            syncType();
        }
    }

    function initManagerForms(root) {
        qsa('[data-emw9-manager-form]', root).forEach((form) => {
            form.addEventListener('submit', (event) => {
                const decision = event.submitter?.value || '';
                const note = qs('[name="review_note"]', form);
                if (decision !== 'approve' && note && !note.value.trim()) {
                    event.preventDefault();
                    note.focus();
                    window.alert('Hãy nhập lý do trả lại Kho hoặc Kỹ thuật.');
                }
            });
        });

        qsa('[data-emw9-return-tech-form]', root).forEach((form) => {
            form.addEventListener('submit', (event) => {
                const note = window.prompt('Nhập rõ vật tư thiếu, sai model hoặc nội dung Kỹ thuật cần điều chỉnh:');
                if (!note || !note.trim()) {
                    event.preventDefault();
                    return;
                }
                const input = qs('[name="feedback_note"]', form);
                if (input) input.value = note.trim();
            });
        });

        qsa('.emw9-decision-form', root).forEach((form) => {
            form.addEventListener('submit', (event) => {
                const decision = event.submitter?.value || '';
                if (['approve', 'send_manager'].includes(decision)) return;
                const note = qs('[name="manager_note"], [name="warehouse_note"]', form);
                if (note && !note.value.trim()) {
                    event.preventDefault();
                    note.focus();
                    window.alert('Hãy nhập lý do trước khi trả lại hoặc từ chối.');
                }
            });
        });
    }

    function initSubmitProtection(root) {
        qsa('form', root).forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (event.defaultPrevented) return;
                form.setAttribute('aria-busy', 'true');
                window.setTimeout(() => {
                    if (event.defaultPrevented) {
                        form.removeAttribute('aria-busy');
                        return;
                    }
                    qsa('button[type="submit"], button:not([type])', form).forEach((button) => button.disabled = true);
                }, 0);
            });
        });
    }

    function init() {
        const root = qs('[data-emw9-root]');
        if (!root) return;
        initTabs(root);
        initTechnicalForm(root);
        initExcelImport(root);
        initWarehouseSelects(root);
        initAftercare(root);
        initManagerForms(root);
        initSubmitProtection(root);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();

/* BEGIN EGO_MATERIAL_SUBMIT_FIX_V11_1 */
(() => {
    'use strict';

    if (window.__egoMaterialSubmitFixV111) return;
    window.__egoMaterialSubmitFixV111 = true;

    document.addEventListener('click', (event) => {
        const button = event.target.closest(
            '[data-emw11-submit-materials]'
        );

        if (!button) return;

        event.preventDefault();

        const form = document.getElementById('emw9-technical-form');

        if (!form) {
            window.alert(
                'Không tìm thấy biểu mẫu đề nghị vật tư. Hãy tải lại trang.'
            );
            return;
        }

        /*
         * Xóa các dòng hoàn toàn trống trước khi kiểm tra.
         * Tránh một dòng trống được tạo dư làm trình duyệt chặn submit.
         */
        const rows = Array.from(
            form.querySelectorAll('[data-emw9-tech-row]')
        );

        rows.forEach((row) => {
            const name = row.querySelector(
                '[name="item_name[]"]'
            );

            const quantity = row.querySelector(
                '[name="quantity[]"]'
            );

            const unit = row.querySelector(
                '[name="unit[]"]'
            );

            const isEmpty =
                !String(name?.value || '').trim() &&
                !String(quantity?.value || '').trim() &&
                !String(unit?.value || '').trim();

            if (isEmpty && rows.length > 1) {
                row.remove();
            }
        });

        if (!form.checkValidity()) {
            const invalid = form.querySelector(':invalid');

            invalid?.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            invalid?.focus();

            form.reportValidity();

            window.setTimeout(() => {
                window.alert(
                    'Có dòng vật tư còn thiếu tên, số lượng hoặc đơn vị. ' +
                    'Hệ thống đã đưa bạn tới ô cần kiểm tra.'
                );
            }, 100);

            return;
        }

        const itemNames = Array.from(
            form.querySelectorAll('[name="item_name[]"]')
        ).filter((input) => String(input.value || '').trim() !== '');

        if (!itemNames.length) {
            window.alert('Danh sách chưa có vật tư để gửi Kho.');
            return;
        }

        button.disabled = true;
        button.setAttribute('aria-busy', 'true');

        const originalHtml = button.innerHTML;

        button.innerHTML =
            '<span class="spinner-border spinner-border-sm" ' +
            'aria-hidden="true"></span> Đang gửi Kho...';

        /*
         * requestSubmit chạy validation chuẩn.
         * Nếu HTML bị form lồng nhau, submit() vẫn gửi trực tiếp form đúng.
         */
        try {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        } catch (error) {
            console.error('[Material submit]', error);

            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.innerHTML = originalHtml;

            window.alert(
                'Không thể gửi biểu mẫu: ' +
                (error?.message || 'Lỗi không xác định')
            );
        }
    }, true);
})();
/* END EGO_MATERIAL_SUBMIT_FIX_V11_1 */

/* BEGIN EGO_WAREHOUSE_MAPPING_V12 */
(() => {
    'use strict';

    if (window.__egoWarehouseMappingV12) return;
    window.__egoWarehouseMappingV12 = true;

    const form = document.querySelector('[data-emw9-stock-form]');
    if (!form) return;

    const syncNoteRequirement = (row) => {
        const status = row.querySelector('[data-emw9-status-select]');
        const note = row.querySelector('[data-emw12-warehouse-note]');
        if (!status || !note) return;

        const needsNote = ['shortage', 'transfer', 'waiting_purchase'].includes(status.value);
        note.required = needsNote;
        note.setAttribute('aria-required', needsNote ? 'true' : 'false');

        if (needsNote) {
            note.placeholder = status.value === 'shortage'
                ? 'Ví dụ: Kho không có hàng / chỉ còn 8 trên 12 tấm'
                : status.value === 'waiting_purchase'
                    ? 'Ví dụ: Chờ nhập, dự kiến hàng về ngày...'
                    : 'Ví dụ: Cần điều chuyển từ kho...';
        } else {
            note.placeholder = 'Ghi chú của Kho (không bắt buộc)';
        }
    };

    form.querySelectorAll('[data-emw9-stock-row]').forEach((row) => {
        const status = row.querySelector('[data-emw9-status-select]');
        if (!status) return;
        syncNoteRequirement(row);
        status.addEventListener('change', () => syncNoteRequirement(row));
    });

    form.addEventListener('submit', (event) => {
        let firstInvalid = null;

        form.querySelectorAll('[data-emw9-stock-row]').forEach((row) => {
            syncNoteRequirement(row);
            const note = row.querySelector('[data-emw12-warehouse-note]');
            if (note?.required && !note.value.trim() && !firstInvalid) {
                firstInvalid = note;
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus();
            window.alert('Dòng thiếu hàng, chờ nhập hoặc điều chuyển phải có ghi chú của Kho.');
        }
    });
})();
/* END EGO_WAREHOUSE_MAPPING_V12 */

/* BEGIN EGO_WAREHOUSE_PRODUCT_SEARCH_V12_1 */
(() => {
    'use strict';

    if (window.__egoWarehouseProductSearchV121) return;
    window.__egoWarehouseProductSearchV121 = true;

    const cache = new Map();
    let activeResults = null;
    let timer = null;
    let requestController = null;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatQty = (value) => {
        const number = Number.parseFloat(value);
        if (!Number.isFinite(number)) return '0';

        return new Intl.NumberFormat('vi-VN', {
            maximumFractionDigits: 3
        }).format(number);
    };

    function positionResults(wrapper) {
        const inputBox = wrapper.querySelector(
            '.emw12-product-search__box'
        );

        const results = wrapper.querySelector(
            '.emw12-product-search__results'
        );

        if (!inputBox || !results) return;

        const rect = inputBox.getBoundingClientRect();
        const margin = 12;
        const gap = 6;
        const width = Math.min(
            Math.max(rect.width, 520),
            window.innerWidth - margin * 2
        );

        let left = rect.left;

        if (left + width > window.innerWidth - margin) {
            left = window.innerWidth - width - margin;
        }

        left = Math.max(margin, left);

        const below = window.innerHeight - rect.bottom - margin - gap;
        const above = rect.top - margin - gap;

        results.style.left = `${left}px`;
        results.style.width = `${width}px`;

        if (below >= 180 || below >= above) {
            results.style.top = `${rect.bottom + gap}px`;
            results.style.bottom = 'auto';
            results.style.maxHeight =
                `${Math.max(160, Math.min(340, below))}px`;
        } else {
            results.style.top = 'auto';
            results.style.bottom =
                `${window.innerHeight - rect.top + gap}px`;
            results.style.maxHeight =
                `${Math.max(160, Math.min(340, above))}px`;
        }
    }

    function closeResults(wrapper) {
        const results = wrapper?.querySelector(
            '.emw12-product-search__results'
        );

        results?.classList.remove('is-open');

        if (activeResults === results) {
            activeResults = null;
        }
    }

    function showMessage(wrapper, message) {
        const results = wrapper.querySelector(
            '.emw12-product-search__results'
        );

        results.innerHTML =
            `<div class="emw12-product-search__message">` +
            `${escapeHtml(message)}</div>`;

        results.classList.add('is-open');
        activeResults = results;
        positionResults(wrapper);
    }

    async function searchProducts(wrapper, query) {
        const select = wrapper.previousElementSibling;

        if (!select?.matches('[data-emw9-product-select]')) return;

        const baseUrl = select.dataset.url;
        const keyword = String(query || '').trim();

        if (!baseUrl) {
            showMessage(wrapper, 'Thiếu đường dẫn tìm sản phẩm.');
            return;
        }

        if (keyword.length < 2) {
            showMessage(wrapper, 'Nhập ít nhất 2 ký tự để tìm.');
            return;
        }

        const cacheKey =
            `${baseUrl}|${keyword.toLocaleLowerCase('vi-VN')}`;

        showMessage(wrapper, 'Đang tìm sản phẩm...');

        try {
            let rows = cache.get(cacheKey);

            if (!rows) {
                requestController?.abort();
                requestController = new AbortController();

                const url = new URL(baseUrl, window.location.origin);
                url.searchParams.set('q', keyword);

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    signal: requestController.signal
                });

                if (!response.ok) {
                    throw new Error('Không tải được sản phẩm.');
                }

                const payload = await response.json();
                rows = Array.isArray(payload.data) ? payload.data : [];
                cache.set(cacheKey, rows);
            }

            const results = wrapper.querySelector(
                '.emw12-product-search__results'
            );

            if (!rows.length) {
                showMessage(
                    wrapper,
                    `Không tìm thấy sản phẩm phù hợp với “${keyword}”.`
                );
                return;
            }

            results.innerHTML = rows.map((row) => {
                const name = row.name || `Sản phẩm #${row.id}`;
                const sku = row.sku || 'Không SKU';
                const unit = row.unit || 'cái';
                const stock = formatQty(row.total_stock_qty ?? 0);

                return `
                    <button type="button"
                            class="emw12-product-search__item"
                            data-emw12-product-id="${escapeHtml(row.id)}"
                            data-emw12-product-name="${escapeHtml(name)}"
                            data-emw12-product-sku="${escapeHtml(sku)}"
                            data-emw12-product-unit="${escapeHtml(unit)}">
                        <span class="emw12-product-search__copy">
                            <strong>${escapeHtml(name)}</strong>
                            <small>SKU ${escapeHtml(sku)} · ĐVT ${escapeHtml(unit)}</small>
                        </span>
                        <span class="emw12-product-search__stock">
                            Tồn tổng ${stock}
                        </span>
                    </button>
                `;
            }).join('');

            results.classList.add('is-open');
            activeResults = results;
            positionResults(wrapper);
        } catch (error) {
            if (error.name === 'AbortError') return;

            console.error('[Warehouse product search]', error);
            showMessage(wrapper, error.message || 'Không thể tìm sản phẩm.');
        }
    }

    function selectProduct(wrapper, item) {
        const select = wrapper.previousElementSibling;
        const input = wrapper.querySelector(
            '.emw12-product-search__input'
        );

        const productId = item.dataset.emw12ProductId;
        const name = item.dataset.emw12ProductName;
        const sku = item.dataset.emw12ProductSku;

        select.innerHTML = '';

        const option = document.createElement('option');
        option.value = productId;
        option.textContent = `${name} · ${sku}`;
        option.selected = true;

        select.appendChild(option);
        select.value = productId;
        select.dataset.selected = productId;

        input.value = `${name} · ${sku}`;
        input.title = `${name} · ${sku}`;

        wrapper.classList.add('is-selected');
        closeResults(wrapper);

        /*
         * Kích hoạt logic hiện có:
         * tự tải kho cấp, tồn khả dụng và giá vốn.
         */
        select.dispatchEvent(new Event('change', {
            bubbles: true
        }));
    }

    function clearProduct(wrapper) {
        const select = wrapper.previousElementSibling;
        const input = wrapper.querySelector(
            '.emw12-product-search__input'
        );

        select.innerHTML =
            '<option value="">Chọn sản phẩm / SKU</option>';

        select.value = '';
        select.dataset.selected = '';

        input.value = '';
        input.title = '';

        wrapper.classList.remove('is-selected');

        select.dispatchEvent(new Event('change', {
            bubbles: true
        }));

        input.focus();
    }

    function enhanceSelect(select) {
        if (
            !select ||
            select.dataset.emw12SearchBound === '1'
        ) {
            return;
        }

        select.dataset.emw12SearchBound = '1';
        select.classList.add('emw12-original-select');

        const selectedOption =
            select.value ? select.selectedOptions?.[0] : null;

        const wrapper = document.createElement('div');
        wrapper.className = 'emw12-product-search';

        if (selectedOption?.value) {
            wrapper.classList.add('is-selected');
        }

        wrapper.innerHTML = `
            <div class="emw12-product-search__box">
                <span class="emw12-product-search__icon">
                    <i class="bi bi-search"></i>
                </span>

                <input type="search"
                       class="emw12-product-search__input"
                       placeholder="Tìm tên sản phẩm, SKU hoặc mã vạch..."
                       autocomplete="off">

                <button type="button"
                        class="emw12-product-search__clear"
                        title="Bỏ sản phẩm đã chọn">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="emw12-product-search__results"></div>
        `;

        select.insertAdjacentElement('afterend', wrapper);

        const input = wrapper.querySelector(
            '.emw12-product-search__input'
        );

        if (selectedOption?.value) {
            input.value = selectedOption.textContent.trim();
            input.title = selectedOption.textContent.trim();
        }

        input.addEventListener('input', () => {
            wrapper.classList.remove('is-selected');

            clearTimeout(timer);

            timer = window.setTimeout(() => {
                searchProducts(wrapper, input.value);
            }, 300);
        });

        input.addEventListener('focus', () => {
            if (
                !wrapper.classList.contains('is-selected') &&
                input.value.trim().length >= 2
            ) {
                searchProducts(wrapper, input.value);
            }
        });

        wrapper.querySelector(
            '.emw12-product-search__clear'
        ).addEventListener('click', () => {
            clearProduct(wrapper);
        });

        wrapper.querySelector(
            '.emw12-product-search__results'
        ).addEventListener('click', (event) => {
            const item = event.target.closest(
                '[data-emw12-product-id]'
            );

            if (item) {
                selectProduct(wrapper, item);
            }
        });
    }

    function enhanceAll() {
        document.querySelectorAll(
            '[data-emw9-product-select]'
        ).forEach(enhanceSelect);
    }

    document.addEventListener('click', (event) => {
        const wrapper = event.target.closest(
            '.emw12-product-search'
        );

        document.querySelectorAll(
            '.emw12-product-search'
        ).forEach((candidate) => {
            if (candidate !== wrapper) {
                closeResults(candidate);
            }
        });
    });

    document.addEventListener('scroll', () => {
        const wrapper = activeResults?.closest(
            '.emw12-product-search'
        );

        if (wrapper) positionResults(wrapper);
    }, true);

    window.addEventListener('resize', () => {
        const wrapper = activeResults?.closest(
            '.emw12-product-search'
        );

        if (wrapper) positionResults(wrapper);
    });

    document.addEventListener('DOMContentLoaded', enhanceAll);

    if (document.readyState !== 'loading') {
        enhanceAll();
    }
})();
/* END EGO_WAREHOUSE_PRODUCT_SEARCH_V12_1 */
