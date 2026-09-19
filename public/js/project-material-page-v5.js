(function () {
    'use strict';

    const one = (selector, root = document) => root.querySelector(selector);
    const all = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const numberOf = (value) => Number.parseFloat(String(value ?? '').replace(',', '.')) || 0;

    function activateMaterialWorkspace() {
        const root = one('[data-emw5-root]');
        const page = one('.pt-page');
        if (!root || !page) return;

        const materialTab = one('[data-pt-tab="materials"]');
        const materialPanel = one('[data-pt-panel="materials"]');
        const defaultTab = page.dataset.projectDefaultTab || '';
        const queryTab = new URLSearchParams(window.location.search).get('tab') || '';
        const shouldOpen = defaultTab === 'materials' || queryTab === 'materials' || root.dataset.emw5DefaultMaterial === '1';

        if (shouldOpen && materialTab && materialPanel) {
            all('[data-pt-tab]').forEach((tab) => tab.classList.toggle('active', tab === materialTab));
            all('[data-pt-panel]').forEach((panel) => panel.classList.toggle('active', panel === materialPanel));
            try { localStorage.setItem('pt-active-tab', 'materials'); } catch (_) {}
        }

        const sync = () => {
            const active = one('[data-pt-panel="materials"].active');
            page.classList.toggle('is-emw5-material', Boolean(active));
        };

        all('[data-pt-tab]').forEach((tab) => tab.addEventListener('click', () => window.requestAnimationFrame(sync)));
        sync();
        window.setTimeout(sync, 80);
    }

    function initReviewForm(root) {
        const form = one('[data-emw5-review-form]', root);
        if (!form) return;

        const note = one('[data-emw5-review-note]', form);
        const approveButton = one('[data-emw5-approve]', form);
        const returnButton = one('[data-emw5-return]', form);
        const radios = all('input[name="decision"]', form);

        function render() {
            const decision = one('input[name="decision"]:checked', form)?.value || 'approve';
            const returning = decision !== 'approve';

            if (note) {
                note.required = returning;
                note.placeholder = returning
                    ? 'Bắt buộc nhập rõ lý do cần kiểm tra hoặc điều chỉnh lại...'
                    : 'Có thể nhập lưu ý cho Kho; không bắt buộc.';
            }

            if (approveButton) approveButton.hidden = returning;
            if (returnButton) {
                returnButton.hidden = !returning;
                returnButton.innerHTML = decision === 'return_warehouse'
                    ? '<i class="bi bi-arrow-return-left"></i> Trả Kho kiểm tra lại'
                    : '<i class="bi bi-arrow-return-left"></i> Trả Sales/Kỹ thuật';
            }

            form.dataset.confirm = returning
                ? 'Xác nhận trả phiếu để cập nhật lại?'
                : 'Xác nhận phê duyệt phiếu và chuyển sang bước xuất kho?';
        }

        radios.forEach((radio) => radio.addEventListener('change', render));
        render();
    }

    function initWorkspace(root) {
        const rows = all('[data-emw5-row]', root);
        if (!rows.length) return;

        const search = one('[data-emw5-search]', root);
        const warehouseFilter = one('[data-emw5-warehouse-filter]', root);
        const statusFilter = one('[data-emw5-status-filter]', root);
        const resetFilter = one('[data-emw5-reset-filter]', root);
        const pageSizeSelect = one('[data-emw5-page-size]', root);
        const pagination = one('[data-emw5-pagination]', root);
        const resultCount = one('[data-emw5-result-count]', root);
        const mappingForm = one('[data-emw5-form]', root);
        const saveTrigger = one('[data-emw5-save-trigger]', root);
        const autoButton = one('[data-emw5-auto]', root);

        let page = 1;
        let pageSize = numberOf(pageSizeSelect?.value) || 10;
        let filteredRows = rows.slice();
        const warehouseNames = new Map();

        function normalize(value) {
            return String(value || '').trim().toLocaleLowerCase('vi-VN');
        }

        function updateWarehouseFilterOptions() {
            if (!warehouseFilter) return;
            const selectedValue = warehouseFilter.value;
            rows.forEach((row) => {
                const select = one('[data-emw5-warehouse]', row);
                const option = select?.selectedOptions?.[0];
                if (option?.value) {
                    const name = option.textContent.split('·')[0].trim();
                    warehouseNames.set(normalize(name), name);
                    row.dataset.warehouse = normalize(name);
                }
            });

            warehouseFilter.innerHTML = '<option value="">Tất cả kho</option>';
            Array.from(warehouseNames.entries())
                .sort((a, b) => a[1].localeCompare(b[1], 'vi'))
                .forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    warehouseFilter.appendChild(option);
                });
            warehouseFilter.value = selectedValue;
        }

        function applyFilters() {
            const term = normalize(search?.value);
            const warehouse = normalize(warehouseFilter?.value);
            const status = statusFilter?.value || '';

            filteredRows = rows.filter((row) => {
                const matchesTerm = !term || normalize(row.dataset.search).includes(term);
                const matchesWarehouse = !warehouse || normalize(row.dataset.warehouse) === warehouse;
                const matchesStatus = !status || row.dataset.status === status;
                const visible = matchesTerm && matchesWarehouse && matchesStatus;
                row.classList.toggle('is-hidden-filter', !visible);
                return visible;
            });

            page = 1;
            renderPage();
        }

        function renderPage() {
            const total = filteredRows.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            page = Math.min(Math.max(1, page), totalPages);
            const start = (page - 1) * pageSize;
            const end = Math.min(start + pageSize, total);

            rows.forEach((row) => row.classList.add('is-hidden-page'));
            filteredRows.forEach((row, index) => row.classList.toggle('is-hidden-page', index < start || index >= end));

            if (resultCount) {
                resultCount.textContent = total > 0
                    ? `Hiển thị ${start + 1} - ${end} trong ${total} dòng`
                    : 'Không có dòng phù hợp';
            }

            if (!pagination) return;
            pagination.innerHTML = '';

            const addButton = (label, targetPage, disabled, active) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.innerHTML = label;
                button.disabled = disabled;
                button.classList.toggle('is-active', Boolean(active));
                button.addEventListener('click', () => {
                    page = targetPage;
                    renderPage();
                });
                pagination.appendChild(button);
            };

            addButton('<i class="bi bi-chevron-left"></i>', page - 1, page === 1, false);

            const pages = [];
            for (let i = 1; i <= totalPages; i += 1) {
                if (i === 1 || i === totalPages || Math.abs(i - page) <= 2) pages.push(i);
            }

            let previous = 0;
            pages.forEach((pageNumber) => {
                if (previous && pageNumber - previous > 1) {
                    const dots = document.createElement('span');
                    dots.textContent = '…';
                    pagination.appendChild(dots);
                }
                addButton(String(pageNumber), pageNumber, false, pageNumber === page);
                previous = pageNumber;
            });

            addButton('<i class="bi bi-chevron-right"></i>', page + 1, page === totalPages, false);
        }

        async function loadWarehouses(select, force) {
            if (!select || select.dataset.loading === '1') return [];
            if (select.dataset.loaded === '1' && !force) {
                return all('option[data-available]', select).map((option) => ({
                    id: option.value,
                    name: option.dataset.name || option.textContent.split('·')[0].trim(),
                    available_qty: numberOf(option.dataset.available),
                }));
            }

            const endpoint = select.dataset.endpoint;
            const productId = select.dataset.productId;
            if (!endpoint || !productId) return [];

            const currentValue = select.value;
            select.dataset.loading = '1';
            select.disabled = true;

            try {
                const url = new URL(endpoint, window.location.origin);
                url.searchParams.set('product_id', productId);
                const response = await fetch(url.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!response.ok) throw new Error('Không tải được tồn kho');
                const payload = await response.json();
                const warehouses = Array.isArray(payload.data) ? payload.data : [];

                select.innerHTML = '<option value="">-- Chọn kho --</option>';
                warehouses.forEach((warehouse) => {
                    const option = document.createElement('option');
                    option.value = String(warehouse.id);
                    option.dataset.available = String(warehouse.available_qty ?? 0);
                    option.dataset.name = warehouse.name || `Kho #${warehouse.id}`;
                    option.textContent = `${option.dataset.name} · Tồn ${warehouse.available_qty ?? 0}`;
                    if (String(warehouse.id) === String(currentValue)) option.selected = true;
                    select.appendChild(option);
                    warehouseNames.set(normalize(option.dataset.name), option.dataset.name);
                });

                select.dataset.loaded = '1';
                updateWarehouseFilterOptions();
                updateRowFromWarehouse(select.closest('[data-emw5-row]'));
                return warehouses;
            } catch (error) {
                console.error(error);
                return [];
            } finally {
                select.disabled = false;
                select.dataset.loading = '0';
            }
        }

        function updateRowFromWarehouse(row) {
            if (!row) return;
            const select = one('[data-emw5-warehouse]', row);
            const quantityInput = one('[data-emw5-quantity]', row);
            const statusSelect = one('[data-emw5-row-status]', row);
            const stockCell = one('[data-emw5-stock]', row);
            const location = one('[data-emw5-location]', row);
            if (!select) return;

            const option = select.selectedOptions?.[0];
            const available = numberOf(option?.dataset.available);
            const quantity = numberOf(quantityInput?.value);
            const name = option?.dataset.name || option?.textContent?.split('·')[0]?.trim() || '';

            if (stockCell) {
                const unit = stockCell.textContent.trim().split(' ').slice(-1)[0] || '';
                stockCell.textContent = `${available} ${unit}`.trim();
                stockCell.classList.toggle('is-good', available > 0);
                stockCell.classList.toggle('is-bad', available <= 0);
            }
            if (location) location.textContent = select.value ? `Đã chọn ${name}` : 'Chọn kho để xem tồn khả dụng';
            row.dataset.warehouse = normalize(name);

            if (statusSelect && !['transfer', 'waiting_purchase'].includes(statusSelect.value)) {
                statusSelect.value = select.value && available >= quantity ? 'ready' : 'shortage';
            }
            if (statusSelect) row.dataset.status = statusSelect.value;
            updateWarehouseFilterOptions();
        }

        all('[data-emw5-warehouse]', root).forEach((select) => {
            select.addEventListener('focus', () => loadWarehouses(select, false), { once: true });
            select.addEventListener('mousedown', () => loadWarehouses(select, false), { once: true });
            select.addEventListener('change', () => updateRowFromWarehouse(select.closest('[data-emw5-row]')));
        });

        all('[data-emw5-quantity]', root).forEach((input) => {
            input.addEventListener('input', () => updateRowFromWarehouse(input.closest('[data-emw5-row]')));
        });

        all('[data-emw5-row-status]', root).forEach((select) => {
            select.addEventListener('change', () => {
                const row = select.closest('[data-emw5-row]');
                if (row) row.dataset.status = select.value;
                applyFilters();
            });
        });

        if (autoButton) {
            autoButton.addEventListener('click', async () => {
                const selects = all('[data-emw5-warehouse]', root);
                autoButton.disabled = true;
                const oldHtml = autoButton.innerHTML;
                autoButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang kiểm tra...';

                try {
                    for (const select of selects) {
                        const warehouses = await loadWarehouses(select, true);
                        const row = select.closest('[data-emw5-row]');
                        const needed = numberOf(select.dataset.needed);
                        if (!warehouses.length) continue;
                        const enough = warehouses.find((warehouse) => numberOf(warehouse.available_qty) >= needed);
                        const best = enough || warehouses.slice().sort((a, b) => numberOf(b.available_qty) - numberOf(a.available_qty))[0];
                        if (best) {
                            select.value = String(best.id);
                            updateRowFromWarehouse(row);
                        }
                    }
                } finally {
                    autoButton.disabled = false;
                    autoButton.innerHTML = oldHtml;
                }
            });
        }

        search?.addEventListener('input', applyFilters);
        warehouseFilter?.addEventListener('change', applyFilters);
        statusFilter?.addEventListener('change', applyFilters);
        resetFilter?.addEventListener('click', () => {
            if (search) search.value = '';
            if (warehouseFilter) warehouseFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            applyFilters();
        });
        pageSizeSelect?.addEventListener('change', () => {
            pageSize = numberOf(pageSizeSelect.value) || 10;
            page = 1;
            renderPage();
        });
        saveTrigger?.addEventListener('click', () => {
            if (!mappingForm) return;
            if (typeof mappingForm.requestSubmit === 'function') mappingForm.requestSubmit();
            else mappingForm.submit();
        });

        updateWarehouseFilterOptions();
        applyFilters();
    }

    function init() {
        activateMaterialWorkspace();
        const root = one('[data-emw5-root]');
        if (!root) return;
        initWorkspace(root);
        initReviewForm(root);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
