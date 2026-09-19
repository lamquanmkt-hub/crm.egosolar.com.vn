/* EGO Material Workflow V7 */
(function () {
    'use strict';

    const root = document.querySelector('[data-emw7-root]');
    if (!root) return;

    const page = root.closest('.pt-page');
    const grid = root.closest('.pt-grid');
    const materialPanel = root.closest('[data-pt-panel="materials"]');
    const views = Array.from(root.querySelectorAll('[data-emw7-view]'));
    const rows = Array.from(root.querySelectorAll('[data-emw7-row]'));
    const searchInput = root.querySelector('[data-emw7-search]');
    const warehouseFilter = root.querySelector('[data-emw7-warehouse-filter]');
    const statusFilter = root.querySelector('[data-emw7-status-filter]');
    const pageSizeSelect = root.querySelector('[data-emw7-page-size]');
    const pagination = root.querySelector('[data-emw7-pagination]');
    const resultSummary = root.querySelector('[data-emw7-result-summary]');
    const warehouseCache = new Map();
    let currentPage = 1;
    let pageSize = Number(pageSizeSelect?.value || root.dataset.pageSize || 10);
    let dirty = false;

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function formatQty(value) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(Number(value || 0));
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(Number(value || 0));
    }

    function syncMaterialLayout() {
        const active = materialPanel?.classList.contains('active');
        grid?.classList.toggle('is-material-workspace', Boolean(active));
    }

    function activateProjectPanel(name) {
        document.querySelectorAll('[data-pt-panel]').forEach((panel) => {
            panel.classList.toggle('active', panel.dataset.ptPanel === name);
        });
        document.querySelectorAll('[data-pt-tab]').forEach((tab) => {
            if (!tab.dataset.materialView) {
                tab.classList.toggle('active', tab.dataset.ptTab === name);
            }
        });
        syncMaterialLayout();
    }

    function setMaterialView(name, updateUrl) {
        const allowed = ['needs', 'stock', 'issue', 'return'];
        const next = allowed.includes(name) ? name : 'needs';
        activateProjectPanel('materials');
        root.dataset.materialView = next;

        views.forEach((view) => {
            view.hidden = view.dataset.emw7View !== next;
        });
        document.querySelectorAll('[data-material-view]').forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.materialView === next);
        });

        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'materials');
            url.searchParams.set('material_view', next);
            window.history.replaceState({}, '', url);
        }

        if (next === 'stock') {
            renderRows();
            window.setTimeout(() => ensureVisibleWarehouses(false), 0);
        }
        syncMaterialLayout();
    }

    document.querySelectorAll('[data-material-view]').forEach((tab) => {
        tab.addEventListener('click', (event) => {
            event.preventDefault();
            setMaterialView(tab.dataset.materialView, true);
        });
    });

    document.querySelectorAll('[data-pt-tab]').forEach((tab) => {
        if (tab.dataset.materialView) return;
        tab.addEventListener('click', () => window.requestAnimationFrame(syncMaterialLayout));
    });

    const initialUrl = new URL(window.location.href);
    const initialTab = initialUrl.searchParams.get('tab') || page?.dataset.projectDefaultTab;
    const initialView = initialUrl.searchParams.get('material_view') || root.dataset.materialView || 'needs';
    if (initialTab === 'materials' || !initialTab) {
        setMaterialView(initialView, false);
    } else {
        syncMaterialLayout();
    }

    /* Technical product rows */
    const techRowsContainer = root.querySelector('[data-emw7-tech-rows]');
    const techTemplate = document.querySelector('[data-emw7-tech-template]');
    const productOptionsTemplate = document.querySelector('[data-emw7-product-options]');

    function refreshTechRows() {
        if (!techRowsContainer) return;
        const techRows = Array.from(techRowsContainer.querySelectorAll('[data-emw7-tech-row]'));
        techRows.forEach((row, index) => {
            const indexCell = row.querySelector('[data-emw7-tech-index]');
            if (indexCell) indexCell.textContent = String(index + 1);
            const remove = row.querySelector('[data-emw7-remove-tech-row]');
            if (remove) remove.disabled = techRows.length <= 1;
        });

        const counter = root.querySelector('[data-emw7-tech-count]');
        if (counter) counter.textContent = `${techRows.length} dòng`;
    }

    function hydrateProductSelect(select) {
        if (!select || select.dataset.emw7Hydrated === '1') return;

        const currentValue = String(select.value || '');
        const currentOption = currentValue
            ? select.querySelector(`option[value="${window.CSS?.escape ? window.CSS.escape(currentValue) : currentValue}"]`)?.cloneNode(true)
            : null;
        const placeholder = select.querySelector('option[value=""]')?.cloneNode(true)
            || new Option('-- Tìm và chọn đúng sản phẩm / SKU --', '');

        select.replaceChildren(placeholder);

        if (productOptionsTemplate?.content) {
            select.appendChild(productOptionsTemplate.content.cloneNode(true));
        }

        if (currentValue) {
            select.value = currentValue;
            if (select.value !== currentValue && currentOption) {
                select.appendChild(currentOption);
                select.value = currentValue;
            }
        }

        select.dataset.emw7Hydrated = '1';
    }

    function bindProductSelect(select) {
        if (!select || select.dataset.emw7Bound === '1') return;
        select.dataset.emw7Bound = '1';

        // Không dựng 1.500 option và không khởi tạo plugin cho mọi dòng ngay khi tải trang.
        // Chỉ nạp danh mục vào đúng select khi người dùng bắt đầu tương tác.
        const prepare = () => hydrateProductSelect(select);
        select.addEventListener('pointerdown', prepare, { once: true });
        select.addEventListener('focus', prepare, { once: true });
        select.addEventListener('keydown', prepare, { once: true });

        select.addEventListener('change', () => {
            const row = select.closest('[data-emw7-tech-row]');
            const option = select.options[select.selectedIndex];
            const unit = option?.dataset.unit || 'cái';
            const stock = Number(option?.dataset.stock || 0);
            const description = option?.dataset.description || '';
            const unitInput = row?.querySelector('[data-emw7-unit]');
            const help = row?.querySelector('[data-emw7-product-help]');
            if (unitInput) unitInput.value = unit;
            if (help) {
                help.textContent = select.value
                    ? `Tồn tham khảo toàn hệ thống: ${formatQty(stock)} ${unit}${description ? ' · ' + description : ''}`
                    : 'Chọn theo đúng hợp đồng/phương án kỹ thuật.';
            }
            dirty = true;
        });
    }

    function bindTechRow(row) {
        if (!row) return;
        bindProductSelect(row.querySelector('[data-emw7-product-select]'));
        row.querySelector('[data-emw7-remove-tech-row]')?.addEventListener('click', () => {
            const count = techRowsContainer?.querySelectorAll('[data-emw7-tech-row]').length || 0;
            if (count <= 1) return;
            row.remove();
            dirty = true;
            refreshTechRows();
        });
        row.querySelectorAll('input, select, textarea').forEach((control) => {
            control.addEventListener('input', () => { dirty = true; });
            control.addEventListener('change', () => { dirty = true; });
        });
    }

    techRowsContainer?.querySelectorAll('[data-emw7-tech-row]').forEach(bindTechRow);
    refreshTechRows();

    root.querySelector('[data-emw7-add-tech-row]')?.addEventListener('click', () => {
        if (!techRowsContainer || !techTemplate) return;
        const fragment = techTemplate.content.cloneNode(true);
        const row = fragment.querySelector('[data-emw7-tech-row]');
        techRowsContainer.appendChild(fragment);
        bindTechRow(row || techRowsContainer.lastElementChild);
        dirty = true;
        refreshTechRows();
        techRowsContainer.lastElementChild?.querySelector('[data-emw7-product-select]')?.focus();
    });

    const technicalForm = root.querySelector('[data-emw7-technical-form]');
    technicalForm?.addEventListener('submit', (event) => {
        const existingCount = Number(technicalForm.dataset.existingItemCount || 0);
        const shouldReplace = technicalForm.dataset.replaceExisting === '1' && existingCount > 0;

        if (shouldReplace && technicalForm.dataset.replaceConfirmed !== '1') {
            const rowCount = techRowsContainer?.querySelectorAll('[data-emw7-tech-row]').length || 1;
            const accepted = window.confirm(
                `Danh sách mới có ${rowCount} dòng và sẽ thay thế ${existingCount} dòng vật tư hiện tại. Bạn chắc chắn tiếp tục?`
            );

            if (!accepted) {
                event.preventDefault();
                return;
            }

            technicalForm.dataset.replaceConfirmed = '1';
        }

        dirty = false;
    });

    /* Warehouse stock table */
    function rowStatus(row) {
        return row.querySelector('[data-emw7-row-status]')?.value || row.dataset.status || 'unchecked';
    }

    function selectedWarehouseId(row) {
        return row.querySelector('[data-emw7-warehouse-select]')?.value || row.dataset.warehouse || '';
    }

    function warningRow(row) {
        return root.querySelector(`[data-emw7-warning-row][data-parent-index="${row.dataset.rowIndex}"]`);
    }

    function filteredRows() {
        const term = normalize(searchInput?.value);
        const warehouse = String(warehouseFilter?.value || '');
        const status = String(statusFilter?.value || '');
        return rows.filter((row) => {
            const searchMatch = !term || normalize(row.dataset.search).includes(term);
            const warehouseMatch = !warehouse || String(selectedWarehouseId(row)) === warehouse;
            const statusMatch = !status || rowStatus(row) === status;
            return searchMatch && warehouseMatch && statusMatch;
        });
    }

    function renderPagination(totalPages) {
        if (!pagination) return;
        pagination.innerHTML = '';

        function add(label, target, active, disabled) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'emw7-page-button';
            button.textContent = label;
            if (active) button.classList.add('is-active');
            button.disabled = Boolean(disabled);
            button.addEventListener('click', () => {
                currentPage = target;
                renderRows();
            });
            pagination.appendChild(button);
        }

        add('‹', Math.max(1, currentPage - 1), false, currentPage <= 1);
        const shown = [];
        for (let pageNumber = 1; pageNumber <= totalPages; pageNumber += 1) {
            if (pageNumber === 1 || pageNumber === totalPages || Math.abs(pageNumber - currentPage) <= 2) {
                shown.push(pageNumber);
            }
        }
        let previous = 0;
        shown.forEach((pageNumber) => {
            if (pageNumber - previous > 1) {
                const dots = document.createElement('span');
                dots.textContent = '…';
                pagination.appendChild(dots);
            }
            add(String(pageNumber), pageNumber, pageNumber === currentPage, false);
            previous = pageNumber;
        });
        add('›', Math.min(totalPages, currentPage + 1), false, currentPage >= totalPages);
    }

    function renderRows() {
        if (!rows.length) return;
        const filtered = filteredRows();
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        const visibleSet = new Set(filtered.slice(start, start + pageSize));

        rows.forEach((row) => {
            const visible = visibleSet.has(row);
            row.hidden = !visible;
            const warning = warningRow(row);
            if (warning) {
                warning.hidden = !(visible && ['shortage', 'transfer', 'waiting_purchase'].includes(rowStatus(row)));
            }
        });

        if (resultSummary) {
            const first = filtered.length ? start + 1 : 0;
            const last = Math.min(start + pageSize, filtered.length);
            resultSummary.textContent = `Hiển thị ${first}–${last} trong tổng số ${filtered.length} dòng`;
        }
        renderPagination(totalPages);
    }

    [searchInput, warehouseFilter, statusFilter].forEach((control) => {
        control?.addEventListener(control === searchInput ? 'input' : 'change', () => {
            currentPage = 1;
            renderRows();
        });
    });
    pageSizeSelect?.addEventListener('change', () => {
        pageSize = Number(pageSizeSelect.value || 10);
        currentPage = 1;
        renderRows();
    });
    root.querySelector('[data-emw7-filter]')?.addEventListener('click', () => {
        currentPage = 1;
        renderRows();
    });

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Không thể tải dữ liệu tồn kho.');
        return payload;
    }

    function rebuildWarehouseFilter() {
        if (!warehouseFilter) return;
        const current = warehouseFilter.value;
        const map = new Map();
        rows.forEach((row) => {
            (row._warehouses || []).forEach((warehouse) => map.set(String(warehouse.id), warehouse.name));
        });
        warehouseFilter.innerHTML = '<option value="">Tất cả kho</option>' + Array.from(map.entries())
            .sort((a, b) => a[1].localeCompare(b[1], 'vi'))
            .map(([id, name]) => `<option value="${id}">${name}</option>`)
            .join('');
        warehouseFilter.value = current;
    }

    function updateRowWarning(row) {
        const status = rowStatus(row);
        row.dataset.status = status;
        const warning = warningRow(row);
        if (!warning) return;

        const required = Number(row.dataset.required || 0);
        const prepared = Number(row.querySelector('[data-emw7-quantity]')?.value || 0);
        const available = Number(row.dataset.available || 0);
        const unit = row.dataset.unit || '';
        const shortage = Math.max(0, required - Math.min(prepared, available));
        const note = row.querySelector('[data-emw7-row-note]')?.value.trim();
        const title = warning.querySelector('[data-emw7-warning-title]');
        const detail = warning.querySelector('[data-emw7-warning-detail]');

        if (title) {
            if (status === 'transfer') title.textContent = 'Cần điều chuyển từ kho khác';
            else if (status === 'waiting_purchase') title.textContent = 'Chờ nhập / mua bổ sung';
            else title.textContent = `Thiếu ${formatQty(shortage)} ${unit}`;
        }
        if (detail) detail.textContent = row.dataset.otherWarehouseText || note || 'Cập nhật phương án xử lý trước khi gửi Quản lý.';
        warning.hidden = !['shortage', 'transfer', 'waiting_purchase'].includes(status) || row.hidden;
    }

    function recommendStatus(row) {
        const select = row.querySelector('[data-emw7-row-status]');
        if (!select) return;
        if (['transfer', 'waiting_purchase'].includes(select.value)) {
            updateRowWarning(row);
            return;
        }
        const required = Number(row.dataset.required || 0);
        const prepared = Number(row.querySelector('[data-emw7-quantity]')?.value || 0);
        const available = Number(row.dataset.available || 0);
        select.value = prepared >= required && available >= required ? 'ready' : 'shortage';
        updateRowWarning(row);
    }

    function applyWarehouse(row, warehouse) {
        const available = Number(warehouse?.available_qty || 0);
        row.dataset.available = String(available);
        row.dataset.warehouse = String(warehouse?.id || '');
        row.dataset.unitCost = String(Number(warehouse?.unit_cost || 0));

        const availableCell = row.querySelector('[data-emw7-available]');
        if (availableCell) {
            availableCell.textContent = `${formatQty(available)} ${row.dataset.unit || ''}`;
            availableCell.classList.toggle('is-positive', available > 0);
            availableCell.classList.toggle('is-zero', available <= 0);
        }
        const stockHelp = row.querySelector('[data-emw7-stock-help]');
        if (stockHelp) stockHelp.textContent = available > 0 ? 'Có thể cấp tại kho chọn' : 'Chưa có tồn khả dụng';
        const location = row.querySelector('[data-emw7-location]');
        if (location) location.textContent = warehouse?.location || 'Chưa cập nhật vị trí kho';
        const cost = row.querySelector('[data-emw7-unit-cost]');
        if (cost) cost.textContent = Number(warehouse?.unit_cost || 0) > 0 ? formatMoney(warehouse.unit_cost) : '—';

        const alternatives = (row._warehouses || [])
            .filter((item) => Number(item.id) !== Number(warehouse?.id) && Number(item.available_qty) > 0)
            .sort((a, b) => Number(b.available_qty) - Number(a.available_qty));
        row.dataset.otherWarehouseText = alternatives.length
            ? `Kho khác có tồn: ${alternatives.slice(0, 2).map((item) => `${item.name} ${formatQty(item.available_qty)}`).join(' · ')}`
            : '';

        recommendStatus(row);
        updateSummary();
    }

    async function loadWarehouses(row, autoPick) {
        const select = row.querySelector('[data-emw7-warehouse-select]');
        if (!select || !select.dataset.url || !select.dataset.productId || row.dataset.warehousesLoaded === 'loading') return;

        const selectedId = select.value || row.dataset.selectedWarehouseId || '';
        row.dataset.warehousesLoaded = 'loading';
        select.disabled = true;
        select.innerHTML = '<option value="">Đang kiểm tra tồn...</option>';

        try {
            const key = `${select.dataset.url}|${select.dataset.productId}`;
            let warehouses = warehouseCache.get(key);
            if (!warehouses) {
                const query = new URLSearchParams({ product_id: select.dataset.productId });
                const payload = await fetchJson(`${select.dataset.url}?${query.toString()}`);
                warehouses = payload.data || [];
                warehouseCache.set(key, warehouses);
            }
            row._warehouses = warehouses;
            row.dataset.warehousesLoaded = '1';
            select.innerHTML = '<option value="">-- Chọn kho cấp hàng --</option>' + warehouses.map((warehouse) => {
                const location = warehouse.location ? ` · ${warehouse.location}` : '';
                return `<option value="${warehouse.id}">${warehouse.name}${location} · Cấp được ${formatQty(warehouse.available_qty)}</option>`;
            }).join('');
            select.disabled = false;
            rebuildWarehouseFilter();

            let picked = warehouses.find((warehouse) => String(warehouse.id) === String(selectedId));
            if (!picked && autoPick) {
                picked = warehouses.slice().sort((a, b) => Number(b.available_qty) - Number(a.available_qty))[0];
            }
            if (picked) {
                select.value = String(picked.id);
                applyWarehouse(row, picked);
            } else {
                applyWarehouse(row, null);
            }
        } catch (error) {
            row.dataset.warehousesLoaded = 'error';
            select.disabled = false;
            select.innerHTML = '<option value="">Lỗi tải tồn kho</option>';
            const help = row.querySelector('[data-emw7-stock-help]');
            if (help) help.textContent = error.message;
        }
    }

    rows.forEach((row) => {
        const warehouseSelect = row.querySelector('[data-emw7-warehouse-select]');
        const quantity = row.querySelector('[data-emw7-quantity]');
        const status = row.querySelector('[data-emw7-row-status]');
        const note = row.querySelector('[data-emw7-row-note]');

        warehouseSelect?.addEventListener('focus', () => {
            if (row.dataset.warehousesLoaded !== '1') loadWarehouses(row, false);
        });
        warehouseSelect?.addEventListener('change', () => {
            dirty = true;
            const selected = (row._warehouses || []).find((warehouse) => String(warehouse.id) === String(warehouseSelect.value));
            applyWarehouse(row, selected || null);
            renderRows();
        });
        quantity?.addEventListener('input', () => {
            dirty = true;
            const required = Number(row.dataset.required || 0);
            const available = Number(row.dataset.available || 0);
            const value = Number(quantity.value || 0);
            const error = row.querySelector('[data-emw7-qty-error]');
            let message = '';
            if (value > required) message = 'Không được vượt SL yêu cầu.';
            else if (value > available) message = 'Vượt tồn khả dụng.';
            if (error) {
                error.textContent = message;
                error.classList.toggle('is-error', Boolean(message));
            }
            recommendStatus(row);
            updateSummary();
        });
        status?.addEventListener('change', () => {
            dirty = true;
            updateRowWarning(row);
            updateSummary();
            renderRows();
        });
        note?.addEventListener('input', () => {
            dirty = true;
            updateRowWarning(row);
        });
        updateRowWarning(row);
    });

    function ensureVisibleWarehouses(autoPick) {
        rows.filter((row) => !row.hidden).forEach((row) => {
            if (row.querySelector('[data-emw7-warehouse-select]') && row.dataset.warehousesLoaded !== '1') {
                loadWarehouses(row, autoPick);
            }
        });
    }

    root.querySelector('[data-emw7-auto-warehouse]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;
        const original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="bi bi-arrow-repeat"></i> Đang đề xuất...';
        for (const row of rows) {
            if (row.querySelector('[data-emw7-warehouse-select]')) {
                await loadWarehouses(row, true);
            }
        }
        button.disabled = false;
        button.innerHTML = original;
        dirty = true;
        updateSummary();
        renderRows();
    });

    function updateSummary() {
        let checked = 0;
        let ready = 0;
        let shortage = 0;
        let transfer = 0;
        let waiting = 0;
        let totalCost = 0;

        rows.forEach((row) => {
            if (selectedWarehouseId(row)) checked += 1;
            const status = rowStatus(row);
            if (['ready', 'reserved', 'issued'].includes(status)) ready += 1;
            if (status === 'shortage') shortage += 1;
            if (status === 'transfer') transfer += 1;
            if (status === 'waiting_purchase') waiting += 1;
            const cost = Number(row.dataset.unitCost || 0);
            const quantity = Number(row.querySelector('[data-emw7-quantity]')?.value || row.dataset.required || 0);
            totalCost += cost * quantity;
        });

        const total = rows.length;
        const percent = total ? Math.round((checked / total) * 100) : 0;
        const set = (selector, value) => {
            const element = root.querySelector(selector);
            if (element) element.textContent = value;
        };
        set('[data-emw7-kpi-checked]', `${checked}/${total} dòng`);
        set('[data-emw7-kpi-percent]', `${percent}%`);
        set('[data-emw7-side-checked]', `${checked} dòng`);
        set('[data-emw7-side-ready]', `${ready} dòng`);
        set('[data-emw7-side-shortage]', `${shortage} dòng`);
        set('[data-emw7-side-transfer]', `${transfer} dòng`);
        set('[data-emw7-side-waiting]', `${waiting} dòng`);
        set('[data-emw7-total-cost]', `${formatMoney(totalCost)} đ`);
        const progress = root.querySelector('[data-emw7-progress]');
        if (progress) progress.style.width = `${percent}%`;
    }

    const generalNote = root.querySelector('[data-emw7-general-note]');
    generalNote?.addEventListener('input', () => {
        dirty = true;
        const counter = root.querySelector('[data-emw7-note-count]');
        if (counter) counter.textContent = String(generalNote.value.length);
    });

    root.querySelector('[data-emw7-stock-form]')?.addEventListener('submit', () => {
        dirty = false;
    });
    root.querySelector('[data-emw7-manager-submit-form]')?.addEventListener('submit', (event) => {
        if (!dirty) return;
        event.preventDefault();
        window.alert('Bạn vừa thay đổi dữ liệu. Hãy bấm “Lưu kiểm tra” trước khi gửi Quản lý phê duyệt.');
    });

    const approvalForm = root.querySelector('[data-emw7-approval-form]');
    if (approvalForm) {
        const radios = Array.from(approvalForm.querySelectorAll('input[name="decision"]'));
        const note = approvalForm.querySelector('[data-emw7-review-note]');
        const button = approvalForm.querySelector('[data-emw7-review-submit]');
        const render = () => {
            const decision = radios.find((radio) => radio.checked)?.value || 'approve';
            const isReturn = decision !== 'approve';
            if (note) {
                note.required = isReturn;
                note.placeholder = isReturn ? 'Bắt buộc nhập lý do trả lại...' : 'Ghi chú phê duyệt (không bắt buộc)...';
            }
            if (button) {
                button.classList.toggle('pt-btn--danger', isReturn);
                button.classList.toggle('pt-btn--brand', !isReturn);
                button.innerHTML = isReturn
                    ? '<i class="bi bi-arrow-return-left"></i> Xác nhận trả lại'
                    : '<i class="bi bi-shield-check"></i> Phê duyệt & chuyển xuất kho';
            }
        };
        radios.forEach((radio) => radio.addEventListener('change', render));
        render();
    }

    function csvEscape(value) {
        const text = String(value ?? '').replace(/\s+/g, ' ').trim();
        return `"${text.replace(/"/g, '""')}"`;
    }

    function exportCsv() {
        const table = root.querySelector('[data-emw7-table]');
        if (!table) return;
        const headers = Array.from(table.querySelectorAll('thead th')).map((cell) => cell.textContent.trim());
        const body = filteredRows().map((row) => Array.from(row.children).map((cell) => cell.innerText));
        const csv = [headers, ...body].map((line) => line.map(csvEscape).join(',')).join('\r\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `kiem-tra-ton-kho-${Date.now()}.csv`;
        document.body.appendChild(link);
        link.click();
        URL.revokeObjectURL(link.href);
        link.remove();
    }
    root.querySelectorAll('[data-emw7-export]').forEach((button) => button.addEventListener('click', exportCsv));

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    updateSummary();
    renderRows();
    syncMaterialLayout();
})();
