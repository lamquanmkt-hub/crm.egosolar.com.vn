(function () {
    'use strict';

    const root = document.querySelector('[data-emw6-root]');
    if (!root) return;

    const page = root.closest('.pt-page');
    const grid = root.closest('.pt-grid');
    const materialPanel = root.closest('[data-pt-panel="materials"]');
    const rows = Array.from(root.querySelectorAll('[data-emw6-row]'));
    const searchInput = root.querySelector('[data-emw6-search]');
    const warehouseFilter = root.querySelector('[data-emw6-warehouse-filter]');
    const statusFilter = root.querySelector('[data-emw6-status-filter]');
    const pageSizeSelect = root.querySelector('[data-emw6-page-size]');
    const pagination = root.querySelector('[data-emw6-pagination]');
    const resultSummary = root.querySelector('[data-emw6-result-summary]');
    const stockPanel = root.querySelector('[data-emw6-stock-panel]');
    const stagePanels = Array.from(root.querySelectorAll('[data-emw6-stage]'));
    const canEdit = root.dataset.canEdit === '1';
    let currentPage = 1;
    let pageSize = Number(pageSizeSelect?.value || root.dataset.pageSize || 10);
    let dirty = false;
    const warehouseCache = new Map();

    page?.classList.add('is-emw6-page');

    function normalize(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    }

    function formatQty(value) {
        const number = Number(value || 0);
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 3 }).format(number);
    }

    function formatMoney(value) {
        const number = Number(value || 0);
        return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(number);
    }

    function escapeCsv(value) {
        const text = String(value ?? '').replace(/\s+/g, ' ').trim();
        return `"${text.replace(/"/g, '""')}"`;
    }

    function activatePanel(panelName) {
        document.querySelectorAll('[data-pt-panel]').forEach(panel => {
            panel.classList.toggle('active', panel.dataset.ptPanel === panelName);
        });
        document.querySelectorAll('[data-pt-tab]').forEach(button => {
            button.classList.toggle('active', button.dataset.ptTab === panelName && !button.dataset.materialView);
        });
        syncPageLayout();
    }

    function syncPageLayout() {
        const active = materialPanel?.classList.contains('active');
        grid?.classList.toggle('is-material-workspace', Boolean(active));
    }

    function setMaterialView(view, updateUrl) {
        const allowed = ['needs', 'stock', 'issue', 'return'];
        const next = allowed.includes(view) ? view : 'stock';
        activatePanel('materials');
        root.dataset.materialView = next;

        document.querySelectorAll('[data-material-view]').forEach(button => {
            button.classList.toggle('active', button.dataset.materialView === next);
        });

        if (stockPanel) {
            stockPanel.hidden = next === 'issue' || next === 'return';
        }
        stagePanels.forEach(panel => {
            panel.hidden = panel.dataset.emw6Stage !== next;
        });

        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'materials');
            url.searchParams.set('material_view', next);
            window.history.replaceState({}, '', url);
        }

        syncPageLayout();
        renderRows();
    }

    document.querySelectorAll('[data-material-view]').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            setMaterialView(button.dataset.materialView, true);
        });
    });

    document.querySelectorAll('[data-workflow-stage]').forEach(button => {
        button.addEventListener('click', () => {
            const target = button.dataset.targetTab;
            if (target) activatePanel(target);
        });
    });

    document.querySelectorAll('[data-pt-tab]').forEach(button => {
        button.addEventListener('click', () => {
            window.requestAnimationFrame(syncPageLayout);
        });
    });

    const pageDefaultTab = page?.dataset.projectDefaultTab;
    const url = new URL(window.location.href);
    const requestedTab = url.searchParams.get('tab') || pageDefaultTab;
    const requestedView = url.searchParams.get('material_view') || root.dataset.materialView || 'stock';
    if (requestedTab === 'materials' || !requestedTab) {
        setMaterialView(requestedView, false);
    } else {
        syncPageLayout();
    }

    function rowStatus(row) {
        const select = row.querySelector('[data-emw6-row-status]');
        if (select) return select.value || 'unchecked';
        return row.dataset.status || 'unchecked';
    }

    function selectedWarehouseId(row) {
        return row.querySelector('[data-emw6-warehouse-select]')?.value || row.dataset.warehouse || '';
    }

    function filteredRows() {
        const term = normalize(searchInput?.value);
        const warehouse = String(warehouseFilter?.value || '');
        const status = String(statusFilter?.value || '');

        return rows.filter(row => {
            const matchesSearch = !term || normalize(row.dataset.search).includes(term);
            const matchesWarehouse = !warehouse || String(selectedWarehouseId(row)) === warehouse;
            const currentStatus = rowStatus(row);
            const matchesStatus = !status || currentStatus === status;
            return matchesSearch && matchesWarehouse && matchesStatus;
        });
    }

    function warningRow(row) {
        return root.querySelector(`[data-emw6-warning-row][data-parent-index="${row.dataset.rowIndex}"]`);
    }

    function renderPagination(totalPages) {
        if (!pagination) return;
        pagination.innerHTML = '';

        const addButton = (label, pageNumber, options) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'emw6-page-button';
            button.textContent = label;
            if (options?.active) button.classList.add('is-active');
            if (options?.disabled) button.disabled = true;
            button.addEventListener('click', () => {
                currentPage = pageNumber;
                renderRows();
            });
            pagination.appendChild(button);
        };

        addButton('‹', Math.max(1, currentPage - 1), { disabled: currentPage <= 1 });

        const visible = [];
        for (let pageNumber = 1; pageNumber <= totalPages; pageNumber += 1) {
            if (pageNumber === 1 || pageNumber === totalPages || Math.abs(pageNumber - currentPage) <= 2) {
                visible.push(pageNumber);
            }
        }

        let previous = 0;
        visible.forEach(pageNumber => {
            if (pageNumber - previous > 1) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '…';
                ellipsis.style.padding = '0 3px';
                pagination.appendChild(ellipsis);
            }
            addButton(String(pageNumber), pageNumber, { active: pageNumber === currentPage });
            previous = pageNumber;
        });

        addButton('›', Math.min(totalPages, currentPage + 1), { disabled: currentPage >= totalPages });
    }

    function renderRows() {
        const visibleRows = filteredRows();
        const totalPages = Math.max(1, Math.ceil(visibleRows.length / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;
        const visibleSet = new Set(visibleRows.slice(start, end));

        rows.forEach(row => {
            const show = visibleSet.has(row);
            row.hidden = !show;
            const warning = warningRow(row);
            if (warning) {
                const warned = ['shortage', 'transfer', 'waiting_purchase'].includes(rowStatus(row));
                warning.hidden = !(show && warned);
            }
        });

        if (resultSummary) {
            const first = visibleRows.length ? start + 1 : 0;
            const last = Math.min(end, visibleRows.length);
            resultSummary.textContent = `Hiển thị ${first}–${last} trong tổng số ${visibleRows.length} dòng`;
        }
        renderPagination(totalPages);
        window.setTimeout(() => ensureVisibleWarehouses(false), 0);
    }

    [searchInput, warehouseFilter, statusFilter].forEach(control => {
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

    root.querySelector('[data-emw6-filter-button]')?.addEventListener('click', () => {
        currentPage = 1;
        renderRows();
    });

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Không thể tải dữ liệu kho.');
        return payload;
    }

    function updateWarning(row) {
        const status = rowStatus(row);
        row.dataset.status = status;
        const warning = warningRow(row);
        if (!warning) return;

        const prepared = Number(row.querySelector('[data-emw6-quantity]')?.value || 0);
        const available = Number(row.dataset.available || 0);
        const required = Number(row.dataset.required || 0);
        const unit = row.dataset.unit || '';
        const shortage = Math.max(0, required - Math.min(prepared, available));
        const title = warning.querySelector('[data-emw6-warning-title]');
        const detail = warning.querySelector('[data-emw6-warning-detail]');
        const note = row.querySelector('[data-emw6-row-note]')?.value.trim();

        if (status === 'transfer') {
            title.textContent = 'Cần điều chuyển từ kho khác';
            detail.textContent = row.dataset.otherWarehouseText || note || 'Kiểm tra kho khác có tồn và cập nhật phương án điều chuyển.';
        } else if (status === 'waiting_purchase') {
            title.textContent = 'Chờ mua bổ sung';
            detail.textContent = note || 'Kho ghi rõ số lượng cần mua và thời gian dự kiến có hàng.';
        } else {
            title.textContent = `Thiếu ${formatQty(shortage)} ${unit}`;
            detail.textContent = row.dataset.otherWarehouseText || note || 'Chưa đủ tồn tại kho đã chọn.';
        }
        warning.hidden = !['shortage', 'transfer', 'waiting_purchase'].includes(status) || row.hidden;
    }

    function recommendStatus(row) {
        const select = row.querySelector('[data-emw6-row-status]');
        if (!select) return;
        const prepared = Number(row.querySelector('[data-emw6-quantity]')?.value || 0);
        const available = Number(row.dataset.available || 0);
        const required = Number(row.dataset.required || 0);

        if (select.value === 'transfer' || select.value === 'waiting_purchase') {
            updateWarning(row);
            return;
        }

        select.value = available >= prepared && prepared >= required ? 'ready' : 'shortage';
        updateWarning(row);
    }

    function updateRowCost(row, warehouse) {
        const unitCost = Number(warehouse?.unit_cost || 0);
        row.dataset.unitCost = String(unitCost);
        const costCell = row.querySelector('[data-emw6-unit-cost]');
        if (costCell) costCell.textContent = unitCost > 0 ? formatMoney(unitCost) : '—';
        updateSummary();
    }

    function applyWarehouse(row, warehouse) {
        const available = Number(warehouse?.available_qty || 0);
        row.dataset.available = String(available);
        row.dataset.warehouse = String(warehouse?.id || '');
        row.dataset.otherWarehouseText = '';

        const availableCell = row.querySelector('[data-emw6-available]');
        if (availableCell) {
            availableCell.textContent = `${formatQty(available)} ${row.dataset.unit || ''}`;
            availableCell.classList.toggle('is-positive', available > 0);
            availableCell.classList.toggle('is-zero', available <= 0);
        }
        const location = row.querySelector('[data-emw6-location]');
        if (location) location.textContent = warehouse?.location || 'Chưa cập nhật vị trí kho';

        const otherWarehouses = (row._warehouses || []).filter(item => Number(item.id) !== Number(warehouse?.id) && Number(item.available_qty) > 0);
        if (otherWarehouses.length) {
            row.dataset.otherWarehouseText = `Kho khác còn hàng: ${otherWarehouses.slice(0, 2).map(item => `${item.name} ${formatQty(item.available_qty)}`).join(' · ')}`;
        }
        updateRowCost(row, warehouse);
        recommendStatus(row);
    }

    async function loadWarehouses(row, autoPick) {
        const select = row.querySelector('[data-emw6-warehouse-select]');
        if (!select || !select.dataset.productId || !select.dataset.url) return;

        const selected = select.value || row.dataset.selectedWarehouseId || '';
        select.disabled = true;
        select.innerHTML = '<option value="">Đang kiểm tra tồn...</option>';

        try {
            const cacheKey = `${select.dataset.url}|${select.dataset.productId}`;
            let warehouses = warehouseCache.get(cacheKey);
            if (!warehouses) {
                const query = new URLSearchParams({ product_id: select.dataset.productId });
                const payload = await fetchJson(`${select.dataset.url}?${query.toString()}`);
                warehouses = payload.data || [];
                warehouseCache.set(cacheKey, warehouses);
            }
            row._warehouses = warehouses;
            row.dataset.warehousesLoaded = '1';
            select.innerHTML = '<option value="">-- Chọn kho cấp hàng --</option>' + warehouses.map(warehouse => {
                const location = warehouse.location ? ` · ${warehouse.location}` : '';
                return `<option value="${warehouse.id}">${warehouse.name}${location} · Có thể cấp ${formatQty(warehouse.available_qty)}</option>`;
            }).join('');
            select.disabled = false;

            let picked = warehouses.find(warehouse => String(warehouse.id) === String(selected));
            if (!picked && autoPick) {
                picked = warehouses.slice().sort((a, b) => Number(b.available_qty) - Number(a.available_qty))[0];
            }
            if (picked) {
                select.value = String(picked.id);
                applyWarehouse(row, picked);
            } else {
                row.dataset.available = '0';
                updateRowCost(row, null);
                recommendStatus(row);
            }
        } catch (error) {
            select.innerHTML = '<option value="">Lỗi tải dữ liệu kho</option>';
            select.disabled = false;
            const help = row.querySelector('[data-emw6-stock-help]');
            if (help) help.textContent = error.message;
        }
    }

    rows.forEach(row => {
        const warehouseSelect = row.querySelector('[data-emw6-warehouse-select]');
        const quantity = row.querySelector('[data-emw6-quantity]');
        const status = row.querySelector('[data-emw6-row-status]');
        const note = row.querySelector('[data-emw6-row-note]');

        if (warehouseSelect) {
            warehouseSelect.addEventListener('focus', () => {
                if (row.dataset.warehousesLoaded !== '1') loadWarehouses(row, false);
            }, { once: true });
            warehouseSelect.addEventListener('change', () => {
                dirty = true;
                const selected = (row._warehouses || []).find(item => String(item.id) === String(warehouseSelect.value));
                applyWarehouse(row, selected || null);
                updateSummary();
                renderRows();
            });
        }

        quantity?.addEventListener('input', () => {
            dirty = true;
            const required = Number(row.dataset.required || 0);
            const available = Number(row.dataset.available || 0);
            const value = Number(quantity.value || 0);
            const error = row.querySelector('[data-emw6-qty-error]');
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
            updateWarning(row);
            updateSummary();
            renderRows();
        });

        note?.addEventListener('input', () => {
            dirty = true;
            updateWarning(row);
        });

        updateWarning(row);
    });

    root.querySelectorAll('[data-emw6-row-detail]').forEach(button => {
        button.addEventListener('click', () => {
            const row = button.closest('[data-emw6-row]');
            const firstControl = row?.querySelector('[data-emw6-warehouse-select], [data-emw6-quantity], [data-emw6-row-note]');
            row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(() => firstControl?.focus(), 250);
        });
    });

    function ensureVisibleWarehouses(autoPick) {
        rows.filter(row => !row.hidden).forEach(row => {
            if (row.querySelector('[data-emw6-warehouse-select]') && row.dataset.warehousesLoaded !== '1') {
                loadWarehouses(row, autoPick);
            }
        });
    }

    root.querySelector('[data-emw6-auto-warehouse]')?.addEventListener('click', async button => {
        button.disabled = true;
        const original = button.innerHTML;
        button.innerHTML = '<i class="bi bi-arrow-repeat"></i> Đang đề xuất...';
        for (const row of rows) {
            if (row.querySelector('[data-emw6-warehouse-select]')) {
                await loadWarehouses(row, true);
                dirty = true;
            }
        }
        button.disabled = false;
        button.innerHTML = original;
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

        rows.forEach(row => {
            const warehouseId = selectedWarehouseId(row);
            const status = rowStatus(row);
            if (warehouseId) checked += 1;
            if (['ready', 'reserved', 'issued'].includes(status)) ready += 1;
            if (status === 'shortage') shortage += 1;
            if (status === 'transfer') transfer += 1;
            if (status === 'waiting_purchase') waiting += 1;
            const cost = Number(row.dataset.unitCost || 0);
            const qty = Number(row.querySelector('[data-emw6-quantity]')?.value || row.dataset.required || 0);
            totalCost += cost * qty;
        });

        const total = rows.length;
        const percent = total ? Math.round((checked / total) * 100) : 0;
        const setText = (selector, value) => {
            const element = root.querySelector(selector);
            if (element) element.textContent = value;
        };
        setText('[data-emw6-kpi-checked]', `${checked}/${total} dòng`);
        setText('[data-emw6-kpi-percent]', `${percent}%`);
        setText('[data-emw6-side-checked]', `${checked} dòng`);
        setText('[data-emw6-side-ready]', `${ready} dòng`);
        setText('[data-emw6-side-shortage]', `${shortage} dòng`);
        setText('[data-emw6-side-transfer]', `${transfer} dòng`);
        setText('[data-emw6-side-waiting]', `${waiting} dòng`);
        setText('[data-emw6-total-cost]', `${formatMoney(totalCost)} đ`);
        const progress = root.querySelector('[data-emw6-progress]');
        if (progress) progress.style.width = `${percent}%`;
    }

    const generalNote = root.querySelector('[data-emw6-general-note]');
    generalNote?.addEventListener('input', () => {
        dirty = true;
        const counter = root.querySelector('[data-emw6-note-count]');
        if (counter) counter.textContent = String(generalNote.value.length);
    });

    root.querySelector('[data-emw6-submit-manager]')?.closest('form')?.addEventListener('submit', event => {
        if (dirty) {
            event.preventDefault();
            window.alert('Bạn vừa thay đổi dữ liệu. Hãy bấm “Lưu tạm” trước khi gửi Quản lý phê duyệt.');
        }
    });

    const stockForm = root.querySelector('#emw6-stock-form');
    stockForm?.addEventListener('submit', () => {
        dirty = false;
    });

    const approvalForm = root.querySelector('[data-emw6-approval-form]');
    if (approvalForm) {
        const decisions = Array.from(approvalForm.querySelectorAll('input[name="decision"]'));
        const note = approvalForm.querySelector('[data-emw6-review-note]');
        const approve = approvalForm.querySelector('[data-emw6-approve-button]');
        const returnButton = approvalForm.querySelector('[data-emw6-return-button]');

        const renderDecision = () => {
            const selected = decisions.find(input => input.checked)?.value || 'approve';
            const isReturn = selected !== 'approve';
            if (note) {
                note.required = isReturn;
                note.placeholder = isReturn ? 'Bắt buộc nhập lý do trả lại...' : 'Ghi chú phê duyệt (không bắt buộc)...';
            }
            if (approve) approve.hidden = isReturn;
            if (returnButton) returnButton.hidden = !isReturn;
        };

        decisions.forEach(input => input.addEventListener('change', renderDecision));
        renderDecision();
    }

    function exportCsv() {
        const headers = Array.from(root.querySelectorAll('[data-emw6-table] thead th')).map(th => th.textContent.trim());
        const data = filteredRows().map(row => Array.from(row.children).map(cell => cell.innerText));
        const csv = [headers, ...data].map(line => line.map(escapeCsv).join(',')).join('\r\n');
        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `kiem-tra-ton-kho-${Date.now()}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(link.href);
    }

    root.querySelectorAll('[data-emw6-export]').forEach(button => button.addEventListener('click', exportCsv));

    window.addEventListener('beforeunload', event => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    updateSummary();
    renderRows();
    syncPageLayout();
})();
