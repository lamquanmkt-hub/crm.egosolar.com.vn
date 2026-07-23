(function () {
    'use strict';

    const root = document.querySelector('[data-warehouse-v2-root]');
    if (!root) return;

    const rows = Array.from(root.querySelectorAll('[data-allocation-row]'));
    const productsUrl = root.dataset.productsUrl;
    const warehousesUrl = root.dataset.warehousesUrl;
    const serialsUrl = root.dataset.serialsUrl;
    const timers = new WeakMap();

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const formatQty = (value) => Number(value || 0).toLocaleString('vi-VN', { maximumFractionDigits: 3 });

    const fetchJson = async (url) => {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'Không tải được dữ liệu kho.');
        }
        return response.json();
    };

    function rowElements(row) {
        return {
            search: row.querySelector('[data-product-search]'),
            results: row.querySelector('[data-product-results]'),
            productId: row.querySelector('[data-product-id]'),
            selected: row.querySelector('[data-selected-product]'),
            warehouseSelect: row.querySelector('[data-row-warehouse]'),
            warehouseId: row.querySelector('[data-row-warehouse-id]'),
            warehouseStock: row.querySelector('[data-warehouse-stock]'),
            warehouseStep: row.querySelector('[data-warehouse-step]'),
            quantityStep: row.querySelector('[data-quantity-step]'),
            quantity: row.querySelector('[data-allocated-qty]'),
            serialBox: row.querySelector('[data-serial-box]'),
            serialList: row.querySelector('[data-serial-list]'),
            serialCount: row.querySelector('[data-serial-count]'),
            result: row.querySelector('[data-row-result]')
        };
    }

    function setRowStatus(row, state, label) {
        const { result } = rowElements(row);
        row.dataset.rowState = state;
        if (result) {
            result.innerHTML = `<span class="pt-wh-v2-state pt-wh-v2-state--${escapeHtml(state)}">${escapeHtml(label)}</span>`;
        }
        updateSummary();
    }

    function selectedSerialCount(row) {
        return row.querySelectorAll('[data-serial-checkbox]:checked').length;
    }

    function selectedWarehouse(row) {
        const { warehouseId } = rowElements(row);
        const id = Number(warehouseId?.value || 0);
        return (row._warehouses || []).find(item => Number(item.id) === id) || null;
    }

    function evaluateRow(row) {
        const { productId, warehouseId, quantity, serialBox } = rowElements(row);
        const product = row._selectedProduct;
        const warehouse = selectedWarehouse(row);
        const qty = Number(quantity?.value || 0);

        if (!productId?.value) {
            setRowStatus(row, 'waiting_match', 'Chưa chọn sản phẩm');
            return;
        }
        if (!warehouseId?.value || !warehouse) {
            setRowStatus(row, 'matching', 'Chưa chọn kho');
            return;
        }
        if (qty > Number(warehouse.available_qty || 0)) {
            setRowStatus(row, 'shortage', `Thiếu ${formatQty(qty - Number(warehouse.available_qty || 0))}`);
            return;
        }
        if (product?.is_serialized && serialBox && !serialBox.hidden) {
            const required = Math.ceil(qty);
            const selected = selectedSerialCount(row);
            setRowStatus(row, selected >= required ? 'ready' : 'matching', selected >= required ? 'Đủ hàng & serial' : 'Chưa đủ serial');
            return;
        }
        setRowStatus(row, 'ready', 'Đủ hàng');
    }

    function updateSerialCounter(row) {
        const { quantity, serialCount } = rowElements(row);
        if (serialCount) {
            serialCount.textContent = `${selectedSerialCount(row)}/${Math.ceil(Number(quantity?.value || 0))}`;
        }
        evaluateRow(row);
    }

    function clearSerials(row) {
        const { serialBox, serialList, serialCount } = rowElements(row);
        if (serialBox) serialBox.hidden = true;
        if (serialList) serialList.innerHTML = '';
        if (serialCount) serialCount.textContent = '0/0';
    }

    async function loadSerials(row, preselected) {
        const { serialBox, serialList, productId, warehouseId } = rowElements(row);
        const product = row._selectedProduct;
        if (!serialBox || !serialList || !product) return;

        if (!product.is_serialized) {
            clearSerials(row);
            evaluateRow(row);
            return;
        }

        if (!productId?.value || !warehouseId?.value) {
            serialBox.hidden = false;
            serialList.innerHTML = '<div class="pt-help">Chọn kho để tải serial khả dụng.</div>';
            evaluateRow(row);
            return;
        }

        serialBox.hidden = false;
        serialList.innerHTML = '<div class="pt-help">Đang tải serial trong kho...</div>';

        try {
            const params = new URLSearchParams({
                warehouse_id: warehouseId.value,
                product_id: productId.value
            });
            const payload = await fetchJson(`${serialsUrl}?${params.toString()}`);
            const selected = new Set((preselected || []).map(Number));
            if (!(payload.data || []).length) {
                serialList.innerHTML = '<div class="pt-alert">Kho này chưa có serial khả dụng cho SKU đã chọn.</div>';
                setRowStatus(row, 'shortage', 'Thiếu serial');
                return;
            }
            const itemId = row.dataset.itemId;
            serialList.innerHTML = payload.data.map(serial => `
                <label class="pt-wh-v2-serial-option">
                    <input type="checkbox"
                           name="items[${escapeHtml(itemId)}][serial_unit_ids][]"
                           value="${serial.id}"
                           data-serial-checkbox
                           ${selected.has(Number(serial.id)) ? 'checked' : ''}>
                    <span>${escapeHtml(serial.code)}</span>
                </label>
            `).join('');
            serialList.querySelectorAll('[data-serial-checkbox]').forEach(input => {
                input.addEventListener('change', () => updateSerialCounter(row));
            });
            updateSerialCounter(row);
        } catch (error) {
            serialList.innerHTML = `<div class="pt-alert">${escapeHtml(error.message)}</div>`;
            setRowStatus(row, 'shortage', 'Lỗi tải serial');
        }
    }

    function renderSelectedProduct(row, product) {
        const { selected, productId, search, warehouseSelect, warehouseId, warehouseStock, warehouseStep, quantityStep } = rowElements(row);
        row._selectedProduct = product;
        row._warehouses = [];
        productId.value = product.id;
        search.value = product.name;

        selected.classList.remove('is-empty');
        selected.innerHTML = `
            <div>
                <strong>${escapeHtml(product.name)}</strong>
                <small>${escapeHtml(product.sku || 'Không có SKU')} · ${escapeHtml(product.unit || 'cái')}</small>
            </div>
            <div class="pt-wh-v2-stock-metrics">
                <span>Tổng tồn <b>${formatQty(product.total_stock_qty)}</b></span>
                <span>${formatQty(product.warehouse_count)} kho có hàng</span>
                <span>${product.is_serialized ? 'Quản lý serial' : 'Theo số lượng'}</span>
            </div>`;

        warehouseId.value = '';
        warehouseSelect.innerHTML = '<option value="">Đang tải kho có tồn...</option>';
        warehouseSelect.disabled = true;
        warehouseStock.classList.add('is-empty');
        warehouseStock.innerHTML = '<span>Đang kiểm tra tồn theo từng kho...</span>';
        warehouseStep.classList.remove('is-disabled');
        quantityStep.classList.add('is-disabled');
        clearSerials(row);
        setRowStatus(row, 'matching', 'Đang tìm kho có hàng');
        loadWarehouses(row, null);
    }

    function renderProductResults(row, products) {
        const { results } = rowElements(row);
        results.hidden = false;
        if (!products.length) {
            results.innerHTML = '<div class="pt-wh-v2-no-result">Không tìm thấy SKU phù hợp.</div>';
            return;
        }
        results.innerHTML = products.map(product => `
            <button type="button" class="pt-wh-v2-product-option" data-product-option data-product='${escapeHtml(JSON.stringify(product))}'>
                <span><strong>${escapeHtml(product.name)}</strong><small>${escapeHtml(product.sku || 'Không có SKU')} · ${escapeHtml(product.unit || 'cái')}</small></span>
                <span class="pt-wh-v2-product-stock ${Number(product.total_stock_qty) > 0 ? 'has-stock' : 'no-stock'}">
                    Tổng tồn <b>${formatQty(product.total_stock_qty)}</b> · ${formatQty(product.warehouse_count)} kho
                </span>
            </button>
        `).join('');
        results.querySelectorAll('[data-product-option]').forEach(button => {
            button.addEventListener('click', () => {
                renderSelectedProduct(row, JSON.parse(button.dataset.product));
                results.hidden = true;
            });
        });
    }

    async function searchProducts(row) {
        const { search, results } = rowElements(row);
        const keyword = search.value.trim();
        results.hidden = false;
        results.innerHTML = '<div class="pt-wh-v2-no-result">Đang tìm sản phẩm thật...</div>';
        try {
            const params = new URLSearchParams({ q: keyword });
            const payload = await fetchJson(`${productsUrl}?${params.toString()}`);
            renderProductResults(row, payload.data || []);
        } catch (error) {
            results.innerHTML = `<div class="pt-wh-v2-no-result">${escapeHtml(error.message)}</div>`;
        }
    }

    function renderWarehouseStock(row, warehouse) {
        const { warehouseStock, quantityStep } = rowElements(row);
        warehouseStock.classList.remove('is-empty');
        warehouseStock.innerHTML = `
            <span>Kho đã chọn</span>
            <strong>${escapeHtml(warehouse.name)}</strong>
            <div class="pt-wh-v2-stock-metrics">
                <span>Tồn thực tế <b>${formatQty(warehouse.stock_qty)}</b></span>
                <span>Đang giữ Test <b>${formatQty(warehouse.reserved_test_qty)}</b></span>
                <span>Có thể cấp <b>${formatQty(warehouse.available_qty)}</b></span>
            </div>`;
        quantityStep.classList.remove('is-disabled');
    }

    async function loadWarehouses(row, selectedWarehouseId) {
        const { productId, warehouseSelect, warehouseId, warehouseStock } = rowElements(row);
        if (!productId?.value) return;

        try {
            const params = new URLSearchParams({ product_id: productId.value });
            const payload = await fetchJson(`${warehousesUrl}?${params.toString()}`);
            const warehouses = payload.data || [];
            row._warehouses = warehouses;

            if (!warehouses.length) {
                warehouseSelect.innerHTML = '<option value="">Không có kho phù hợp</option>';
                warehouseSelect.disabled = true;
                warehouseId.value = '';
                warehouseStock.classList.remove('is-empty');
                warehouseStock.innerHTML = '<span>Không tìm thấy kho có sản phẩm này.</span>';
                setRowStatus(row, 'shortage', 'Không có kho chứa SKU');
                return;
            }

            warehouseSelect.innerHTML = '<option value="">-- Chọn kho xuất --</option>' + warehouses.map(warehouse => `
                <option value="${warehouse.id}" ${Number(warehouse.available_qty) <= 0 ? 'disabled' : ''}>
                    ${escapeHtml(warehouse.name)} · Có thể cấp ${formatQty(warehouse.available_qty)}
                </option>
            `).join('');
            warehouseSelect.disabled = row.dataset.rowLocked === '1';

            const preferred = warehouses.find(item => Number(item.id) === Number(selectedWarehouseId));
            const autoPick = preferred || (warehouses.filter(item => Number(item.available_qty) > 0).length === 1
                ? warehouses.find(item => Number(item.available_qty) > 0)
                : null);

            if (autoPick) {
                warehouseSelect.value = String(autoPick.id);
                warehouseId.value = String(autoPick.id);
                renderWarehouseStock(row, autoPick);
                loadSerials(row, JSON.parse(row.dataset.selectedSerials || '[]'));
            } else {
                warehouseId.value = '';
                warehouseStock.classList.add('is-empty');
                warehouseStock.innerHTML = '<span>Chọn một kho trong danh sách để xem tồn khả dụng.</span>';
                setRowStatus(row, 'matching', 'Chưa chọn kho');
            }
        } catch (error) {
            warehouseSelect.innerHTML = '<option value="">Lỗi tải kho</option>';
            warehouseSelect.disabled = true;
            warehouseStock.classList.remove('is-empty');
            warehouseStock.innerHTML = `<span>${escapeHtml(error.message)}</span>`;
            setRowStatus(row, 'shortage', 'Lỗi tải kho');
        }
    }

    function updateSummary() {
        const total = rows.length;
        let mapped = 0;
        let shortage = 0;
        let serialRequired = 0;
        let serialSelected = 0;

        rows.forEach(row => {
            const { productId, warehouseId, quantity, serialBox } = rowElements(row);
            if (productId?.value && warehouseId?.value) mapped++;
            if (row.dataset.rowState === 'shortage') shortage++;
            if (serialBox && !serialBox.hidden) {
                serialRequired += Math.ceil(Number(quantity?.value || 0));
                serialSelected += selectedSerialCount(row);
            }
        });

        const mappedEl = root.querySelector('[data-summary-mapped]');
        const shortageEl = root.querySelector('[data-summary-shortage]');
        const serialEl = root.querySelector('[data-summary-serial]');
        if (mappedEl) mappedEl.textContent = `${mapped}/${total}`;
        if (shortageEl) shortageEl.textContent = String(shortage);
        if (serialEl) serialEl.textContent = `${serialSelected}/${serialRequired}`;

        const readyRows = rows.filter(row => row.dataset.rowState === 'ready' || row.dataset.rowState === 'reserved').length;
        const ready = total > 0 && mapped === total && shortage === 0 && readyRows === total && serialSelected >= serialRequired;
        const banner = root.querySelector('[data-ready-banner]');
        if (banner) {
            banner.classList.toggle('is-ready', ready);
            banner.innerHTML = ready
                ? '<i class="bi bi-check-circle"></i><div><strong>Có thể lưu và giữ hàng</strong><small>Tất cả dòng đã chọn đủ SKU, kho, tồn và serial</small></div>'
                : '<i class="bi bi-exclamation-circle"></i><div><strong>Chưa đủ điều kiện</strong><small>Kiểm tra dòng chưa chọn sản phẩm, chưa chọn kho, thiếu tồn hoặc thiếu serial</small></div>';
        }
    }

    rows.forEach(row => {
        const { search, results, quantity, productId, warehouseSelect, warehouseId } = rowElements(row);
        if (!search) return;

        if (!search.disabled) {
            search.addEventListener('focus', () => searchProducts(row));
            search.addEventListener('input', () => {
                clearTimeout(timers.get(search));
                timers.set(search, setTimeout(() => searchProducts(row), 260));
            });
        }

        warehouseSelect?.addEventListener('change', () => {
            const warehouse = (row._warehouses || []).find(item => String(item.id) === String(warehouseSelect.value));
            warehouseId.value = warehouse ? String(warehouse.id) : '';
            clearSerials(row);
            if (!warehouse) {
                rowElements(row).quantityStep.classList.add('is-disabled');
                rowElements(row).warehouseStock.classList.add('is-empty');
                rowElements(row).warehouseStock.innerHTML = '<span>Chưa chọn kho.</span>';
                evaluateRow(row);
                return;
            }
            renderWarehouseStock(row, warehouse);
            loadSerials(row, []);
            evaluateRow(row);
        });

        quantity?.addEventListener('input', () => {
            updateSerialCounter(row);
            evaluateRow(row);
        });

        document.addEventListener('click', event => {
            if (!row.contains(event.target) && results) results.hidden = true;
        });

        if (productId?.value) {
            const savedWarehouseId = row.dataset.selectedWarehouseId || warehouseId?.value || '';
            fetchJson(`${productsUrl}?${new URLSearchParams({ q: search.value }).toString()}`)
                .then(payload => {
                    const product = (payload.data || []).find(item => String(item.id) === String(productId.value));
                    if (!product) return;
                    row._selectedProduct = product;
                    return loadWarehouses(row, savedWarehouseId);
                })
                .catch(() => {});
        }
    });

    updateSummary();
})();
