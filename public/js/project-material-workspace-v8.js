/*
 * EGO MATERIAL WORKFLOW V4.1 / UI V8
 * 4 mục: Vật tư đề xuất -> Xuất kho -> Thu hồi -> Lịch sử.
 * Kỹ thuật được nhập tay; Kho đối chiếu sản phẩm/SKU thật trước khi kiểm tra tồn.
 */
(() => {
    'use strict';

    const root = document.querySelector('[data-emw8-root]');
    if (!root) return;

    const page = root.closest('.pt-page');
    const grid = root.closest('.pt-grid');
    const materialPanel = root.closest('[data-pt-panel="materials"]');
    const optionTemplate = document.querySelector('template[data-emw8-product-options]');
    const productDatalist = document.querySelector('[data-emw8-product-datalist]');
    const rowTemplate = document.querySelector('template[data-emw8-tech-template]');
    const techBody = root.querySelector('[data-emw8-tech-body]');
    const techForm = root.querySelector('[data-emw8-tech-form]');
    const rowCount = root.querySelector('[data-emw8-row-count]');
    const allowedViews = new Set(['proposal', 'issue', 'return']);

    const numberText = (value) => {
        const number = Number(value || 0);
        if (!Number.isFinite(number)) return '0';
        return number.toLocaleString('vi-VN', { maximumFractionDigits: 3 });
    };

    const escapeText = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[char]));

    const normalizeText = (value) => String(value || '').trim().replace(/\s+/g, ' ').toLocaleLowerCase('vi-VN');

    const optionSelect = document.createElement('select');
    optionSelect.innerHTML = optionTemplate ? optionTemplate.innerHTML : '';
    const productCatalog = [...optionSelect.options]
        .filter((option) => option.value)
        .map((option) => ({
            id: String(option.value),
            label: option.textContent.trim(),
            name: option.dataset.name || option.textContent.trim(),
            unit: option.dataset.unit || 'cái',
            brand: option.dataset.brand || '',
            sku: option.dataset.sku || '',
            description: option.dataset.description || '',
            stock: Number(option.dataset.stock || 0),
        }));

    const catalogById = new Map(productCatalog.map((product) => [product.id, product]));
    const catalogByLabel = new Map();
    productCatalog.forEach((product) => {
        [product.label, product.name, product.sku].filter(Boolean).forEach((key) => {
            const normalized = normalizeText(key);
            if (normalized && !catalogByLabel.has(normalized)) catalogByLabel.set(normalized, product);
        });
    });

    if (productDatalist && productDatalist.children.length === 0) {
        const fragment = document.createDocumentFragment();
        productCatalog.forEach((product) => {
            const option = document.createElement('option');
            option.value = product.label;
            option.label = product.sku ? `${product.name} · SKU ${product.sku}` : product.name;
            fragment.appendChild(option);
        });
        productDatalist.appendChild(fragment);
    }

    const syncMaterialLayout = () => {
        grid?.classList.toggle('is-material-workspace', Boolean(materialPanel?.classList.contains('active')));
    };

    const activateProjectPanel = (name) => {
        document.querySelectorAll('[data-pt-panel]').forEach((panel) => {
            panel.classList.toggle('active', panel.dataset.ptPanel === name);
        });
        document.querySelectorAll('[data-pt-tab]').forEach((tab) => {
            if (!tab.dataset.materialView) {
                tab.classList.toggle('active', tab.dataset.ptTab === name);
            }
        });
        syncMaterialLayout();
    };

    const normalizeView = (requestedView) => {
        if (requestedView === 'needs' || requestedView === 'stock') return 'proposal';
        return allowedViews.has(requestedView) ? requestedView : 'proposal';
    };

    const showView = (requestedView, updateUrl = false) => {
        const view = normalizeView(requestedView);
        activateProjectPanel('materials');
        root.dataset.materialView = view;

        root.querySelectorAll('[data-emw8-view]').forEach((panel) => {
            panel.hidden = panel.dataset.emw8View !== view;
        });

        document.querySelectorAll('[data-material-view]').forEach((button) => {
            const isActive = normalizeView(button.dataset.materialView) === view;
            button.classList.toggle('active', isActive);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });

        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'materials');
            url.searchParams.set('material_view', view);
            window.history.replaceState({}, '', url);
        }
    };

    document.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-material-view]');
        if (!viewButton) return;
        event.preventDefault();
        showView(viewButton.dataset.materialView, true);
    });

    document.querySelectorAll('[data-pt-tab]').forEach((tab) => {
        if (tab.dataset.materialView) return;
        tab.addEventListener('click', () => window.requestAnimationFrame(syncMaterialLayout));
    });

    const productOptionHtml = () => optionTemplate ? optionTemplate.innerHTML : '';

    const matchTechnicalProduct = (input) => {
        const row = input.closest('[data-emw8-tech-row]');
        if (!row) return;

        const hiddenId = row.querySelector('[data-emw8-tech-product-id]');
        const unitInput = row.querySelector('[data-emw8-unit]');
        const help = row.querySelector('[data-emw8-product-help]');
        const normalizedValue = normalizeText(input.value);
        const existingProduct = hiddenId?.value ? catalogById.get(String(hiddenId.value)) : null;
        const existingMatches = existingProduct && [existingProduct.label, existingProduct.name, existingProduct.sku]
            .filter(Boolean)
            .some((value) => normalizeText(value) === normalizedValue);
        const product = existingMatches ? existingProduct : catalogByLabel.get(normalizedValue);

        if (!product) {
            if (hiddenId) hiddenId.value = '';
            if (help) help.textContent = 'Nhập tay được. Hãy ghi rõ thương hiệu, model/chủng loại, thông số; Kho sẽ đối chiếu SKU.';
            return;
        }

        if (hiddenId) hiddenId.value = product.id;
        if (unitInput && (!unitInput.value.trim() || unitInput.value.trim() === 'cái')) unitInput.value = product.unit;
        if (help) {
            const brand = product.brand || 'Chưa gắn thương hiệu';
            const sku = product.sku || '—';
            help.textContent = `Nhận diện danh mục: ${brand} · SKU ${sku} · Tồn tham khảo ${numberText(product.stock)}. Kho vẫn đối chiếu lại.`;
        }
    };

    const renumberRows = () => {
        if (!techBody) return;
        const rows = [...techBody.querySelectorAll('[data-emw8-tech-row]')];
        rows.forEach((row, index) => {
            const indexCell = row.querySelector('[data-emw8-tech-index]');
            if (indexCell) indexCell.textContent = String(index + 1);
            const removeButton = row.querySelector('[data-emw8-remove-row]');
            if (removeButton) removeButton.disabled = rows.length === 1;
        });
        if (rowCount) rowCount.textContent = `${rows.length} dòng`;
    };

    const addTechnicalRow = () => {
        if (!techBody || !rowTemplate) return;
        const fragment = rowTemplate.content.cloneNode(true);
        techBody.appendChild(fragment);
        renumberRows();
        techBody.lastElementChild?.querySelector('[data-emw8-tech-product-input]')?.focus();
    };

    const loadProductOptions = (select) => {
        if (!select || select.dataset.optionsLoaded === '1') return;
        const selected = String(select.dataset.selected || select.value || '');
        select.innerHTML = '<option value="">-- Chọn sản phẩm / SKU --</option>' + productOptionHtml();
        if (selected) select.value = selected;
        select.dataset.optionsLoaded = '1';
        updateStockProduct(select, false);
    };

    const warehouseCache = new Map();

    const loadWarehouses = async (select) => {
        if (!select || select.dataset.loading === '1') return;
        const productId = select.dataset.productId;
        const url = select.dataset.url;
        if (!productId || !url) return;

        const cacheKey = `${url}|${productId}`;
        select.dataset.loading = '1';
        const selected = String(select.dataset.selected || select.value || '');

        try {
            let rows = warehouseCache.get(cacheKey);
            if (!rows) {
                const requestUrl = new URL(url, window.location.origin);
                requestUrl.searchParams.set('product_id', productId);
                const response = await fetch(requestUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const payload = await response.json();
                rows = Array.isArray(payload.data) ? payload.data : [];
                warehouseCache.set(cacheKey, rows);
            }

            select.innerHTML = '<option value="">-- Chọn kho --</option>' + rows.map((warehouse) => {
                const location = warehouse.location ? ` · ${escapeText(warehouse.location)}` : '';
                return `<option value="${warehouse.id}" data-available="${warehouse.available_qty}" data-stock="${warehouse.stock_qty}" data-cost="${warehouse.unit_cost ?? ''}">${escapeText(warehouse.name)}${location} · Khả dụng ${numberText(warehouse.available_qty)}</option>`;
            }).join('');
            if (selected) select.value = selected;
            select.dataset.optionsLoaded = '1';
            updateWarehouseRow(select);
        } catch (error) {
            console.error('Không tải được tồn kho:', error);
            window.alert('Không tải được danh sách tồn kho. Vui lòng thử lại.');
        } finally {
            select.dataset.loading = '0';
        }
    };

    const updateStockProduct = (select, resetWarehouse = true) => {
        const row = select.closest('[data-emw8-stock-row]');
        if (!row) return;
        const option = select.options[select.selectedIndex];
        const warehouseSelect = row.querySelector('[data-emw8-warehouse-select]');
        const help = row.querySelector('[data-emw8-stock-product-help]');
        const availableNode = row.querySelector('[data-emw8-available]');

        if (help) {
            help.textContent = option?.value
                ? `Đã chọn ${option.dataset.brand || 'sản phẩm'} · SKU ${option.dataset.sku || '—'}`
                : 'Bắt buộc chọn sản phẩm thật trong danh mục Kho.';
        }

        if (!warehouseSelect) return;
        warehouseSelect.dataset.productId = option?.value || '';

        if (resetWarehouse) {
            warehouseSelect.dataset.selected = '';
            warehouseSelect.dataset.optionsLoaded = '0';
            warehouseSelect.innerHTML = '<option value="">-- Chọn kho --</option>';
            if (availableNode) availableNode.textContent = '—';
        }

        if (option?.value) loadWarehouses(warehouseSelect);
    };

    const updateWarehouseRow = (select) => {
        const row = select.closest('[data-emw8-stock-row]');
        if (!row) return;
        const option = select.options[select.selectedIndex];
        const available = Number(option?.dataset.available || 0);
        const required = Number(row.dataset.required || 0);
        const availableNode = row.querySelector('[data-emw8-available]');
        const statusSelect = row.querySelector('[data-emw8-status-select]');
        const currentStatus = row.dataset.currentStatus || '';

        if (availableNode) availableNode.textContent = select.value ? numberText(available) : '—';
        if (!statusSelect || !select.value) return;

        if (!['transfer', 'waiting_purchase'].includes(currentStatus)) {
            statusSelect.value = available >= required ? 'ready' : 'shortage';
        }
    };

    const prepareInteractiveField = (event) => {
        const stockProduct = event.target.closest?.('[data-emw8-stock-product-select]');
        if (stockProduct) loadProductOptions(stockProduct);

        const warehouseSelect = event.target.closest?.('[data-emw8-warehouse-select]');
        if (warehouseSelect) loadWarehouses(warehouseSelect);
    };

    root.addEventListener('pointerdown', prepareInteractiveField, true);
    root.addEventListener('focusin', prepareInteractiveField);
    root.addEventListener('keydown', prepareInteractiveField, true);

    root.addEventListener('input', (event) => {
        const technicalInput = event.target.closest('[data-emw8-tech-product-input]');
        if (technicalInput) matchTechnicalProduct(technicalInput);
    });

    root.addEventListener('change', (event) => {
        const technicalInput = event.target.closest('[data-emw8-tech-product-input]');
        if (technicalInput) matchTechnicalProduct(technicalInput);

        const stockProduct = event.target.closest('[data-emw8-stock-product-select]');
        if (stockProduct) updateStockProduct(stockProduct, true);

        const warehouseSelect = event.target.closest('[data-emw8-warehouse-select]');
        if (warehouseSelect) updateWarehouseRow(warehouseSelect);
    });

    root.addEventListener('click', (event) => {
        const addButton = event.target.closest('[data-emw8-add-row]');
        if (addButton) {
            event.preventDefault();
            addTechnicalRow();
            return;
        }

        const removeButton = event.target.closest('[data-emw8-remove-row]');
        if (removeButton) {
            event.preventDefault();
            const rows = techBody ? [...techBody.querySelectorAll('[data-emw8-tech-row]')] : [];
            if (rows.length <= 1) return;
            removeButton.closest('[data-emw8-tech-row]')?.remove();
            renumberRows();
        }
    });

    if (techForm) {
        techForm.addEventListener('submit', (event) => {
            const itemNames = [...techForm.querySelectorAll('input[name="item_name[]"]')]
                .map((input) => input.value.trim());
            if (itemNames.length === 0 || itemNames.some((name) => name === '')) {
                event.preventDefault();
                window.alert('Mỗi dòng phải có tên vật tư đề xuất.');
                return;
            }

            const invalidQuantity = [...techForm.querySelectorAll('input[name="quantity[]"]')]
                .some((input) => Number(input.value) <= 0);
            if (invalidQuantity) {
                event.preventDefault();
                window.alert('Số lượng vật tư phải lớn hơn 0.');
                return;
            }

            const invalidUnit = [...techForm.querySelectorAll('input[name="unit[]"]')]
                .some((input) => input.value.trim() === '');
            if (invalidUnit) {
                event.preventDefault();
                window.alert('Mỗi dòng phải có đơn vị tính.');
                return;
            }

            const action = techForm.dataset.existingRequest === '1' ? 'cập nhật' : 'gửi';
            if (!window.confirm(`Xác nhận ${action} đề nghị cấp vật tư và chuyển Kho đối chiếu sản phẩm/SKU?`)) {
                event.preventDefault();
            }
        });
    }

    root.querySelectorAll('[data-emw8-manager-submit]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (form.dataset.allReady !== '1') {
                event.preventDefault();
                window.alert('Phải đối chiếu đủ sản phẩm/SKU và tất cả dòng đều đủ hàng trước khi gửi Quản lý.');
                return;
            }
            if (!window.confirm('Xác nhận chuyển đề nghị đủ hàng sang Quản lý phê duyệt?')) {
                event.preventDefault();
            }
        });
    });

    root.querySelectorAll('[data-emw8-review-form]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const decision = form.querySelector('input[name="decision"]:checked')?.value || '';
            const note = form.querySelector('[name="review_note"]')?.value.trim() || '';
            if (decision !== 'approve' && !note) {
                event.preventDefault();
                window.alert('Bắt buộc nhập lý do khi trả lại.');
                return;
            }
            const message = decision === 'approve'
                ? 'Phê duyệt chuyển sang bước Xuất kho?'
                : 'Xác nhận trả đề nghị về bước trước?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });

    techBody?.querySelectorAll('[data-emw8-tech-product-input]').forEach(matchTechnicalProduct);
    root.querySelectorAll('[data-emw8-stock-product-select]').forEach((select) => {
        if (select.value || select.dataset.selected) loadProductOptions(select);
    });
    renumberRows();

    const initialUrl = new URL(window.location.href);
    const initialTab = initialUrl.searchParams.get('tab') || page?.dataset.projectDefaultTab || 'overview';
    const initialView = normalizeView(initialUrl.searchParams.get('material_view') || root.dataset.materialView || 'proposal');
    if (initialTab === 'materials') {
        showView(initialView, false);
    } else {
        root.querySelectorAll('[data-emw8-view]').forEach((panel) => {
            panel.hidden = panel.dataset.emw8View !== initialView;
        });
        syncMaterialLayout();
    }
})();
