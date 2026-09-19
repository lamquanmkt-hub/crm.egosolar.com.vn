class OrderFormManager {
  constructor() {
    this.form = document.getElementById('orderForm');
    this.builder = document.getElementById('orderProductBuilder');
    this.productList = document.getElementById('productTableBody');
    this.customerSelect = document.getElementById('customerSelect');
    this.customerStatus = document.getElementById('customerSearchStatus');
    this.customerInfo = document.getElementById('customerInfo');
    this.leadIdInput = document.getElementById('leadIdInput');
    this.companyInput = document.getElementById('orderCompanyId');
    this.companyLock = document.getElementById('orderCompanyLock');
    this.companyLockName = document.getElementById('orderCompanyLockName');
    this.resetCompanyButton = document.getElementById('resetOrderCompany');
    this.addProductButton = document.getElementById('addProductBtn');
    this.primarySubmit = document.querySelector('[data-primary-submit]');
    this.submitButtons = Array.from(document.querySelectorAll('[data-submit-button]'));

    this.invoiceDetails = document.getElementById('invoiceDetails');
    this.invoiceStateText = document.getElementById('invoiceStateText');
    this.billingStatus = document.getElementById('billingInfoStatus');
    this.saveBillingButton = document.getElementById('btnSaveCustomerBilling');
    this.invoiceFields = {
      company: document.getElementById('invoice_company_name'),
      taxCode: document.getElementById('invoice_tax_code'),
      email: document.getElementById('invoice_email'),
      address: document.getElementById('invoice_address'),
    };

    this.productsUrl = this.builder?.dataset.productsUrl || '/orders/product-catalog';
    this.warehousesUrl = this.builder?.dataset.warehousesUrl || '/orders/product-warehouses';
    this.mode = this.builder?.dataset.mode || 'create';

    // EGO_EDIT_PRESERVE_HISTORY_START
    this.editInitialRows = this.mode === 'edit'
      ? this.rows().map(row => ({
          row,
          priceTierId: row.querySelector('.price-tier-select')?.value || '',
          unitPrice: row.querySelector('.unit-price')?.value || '',
          vatPercent: row.querySelector('.item-vat-percent')?.value || '',
          lineTotal: row.querySelector('.line-total')?.value || '',
        }))
      : [];
    // EGO_EDIT_PRESERVE_HISTORY_END

    this.customerTypeId = window.customerTypeId || null;
    this.customerPriceTierId = null;
    this.allPriceTiers = Array.isArray(window.allPriceTiers) ? window.allPriceTiers : [];
    this.productCache = new Map();
    this.warehouseCache = new Map();
    this.isSubmitting = false;

    const indexes = this.rows().map(row => Number(row.dataset.index || 0));
    this.rowIndex = indexes.length ? Math.max(...indexes) + 1 : 0;

    this.summary = {
      totalItems: document.getElementById('totalItems'),
      totalQuantity: document.getElementById('totalQuantity'),
      totalBeforeVat: document.getElementById('totalBeforeVat'),
      totalVat: document.getElementById('totalVatAmount'),
      totalDiscount: document.getElementById('totalDiscount'),
      discountRow: document.getElementById('discountSummaryRow'),
      totalAmount: document.getElementById('totalAmount'),
      invoiceItems: document.getElementById('invoiceItems'),
    };

    this.bindGlobalEvents();
    this.bootstrap()
      .then(() => {
        // EGO_EDIT_RESTORE_HISTORY_START
        if (this.mode !== 'edit' || !this.editInitialRows.length) return;

        this.editInitialRows.forEach(snapshot => {
          const row = snapshot.row;
          if (!row?.isConnected) return;

          const tierSelect = row.querySelector('.price-tier-select');
          const priceInput = row.querySelector('.unit-price');
          const vatInput = row.querySelector('.item-vat-percent');
          const totalInput = row.querySelector('.line-total');

          if (
            tierSelect
            && Array.from(tierSelect.options).some(
              option => option.value === String(snapshot.priceTierId)
            )
          ) {
            tierSelect.value = String(snapshot.priceTierId);
          }

          if (priceInput && snapshot.unitPrice !== '') {
            priceInput.value = snapshot.unitPrice;
          }

          if (vatInput && snapshot.vatPercent !== '') {
            vatInput.value = snapshot.vatPercent;
          }

          if (totalInput && snapshot.lineTotal !== '') {
            totalInput.value = snapshot.lineTotal;
          }

          const price = this.parseNumber(priceInput?.value);
          const quantity = Math.max(
            0,
            Number(row.querySelector('.quantity')?.value || 0)
          );

          const subtotal = price * quantity;

          const percent = this.clampPercent(
            row.querySelector('.discount-percent')?.value
          );

          const amount = Math.max(
            0,
            this.parseNumber(
              row.querySelector('.discount-per-unit')?.value
            )
          );

          const discount = amount > 0
            ? Math.min(subtotal, amount * quantity)
            : Math.min(subtotal, subtotal * percent / 100);

          row.dataset.discountTotal = String(discount);
        });

        this.updateTotals();
        // EGO_EDIT_RESTORE_HISTORY_END
      })
      .catch(error => {
        console.error(error);
        this.toast('Không thể khởi tạo form đơn hàng.', 'danger');
      });
  }

  rows() {
    return Array.from(this.productList?.querySelectorAll('.product-row') || []);
  }

  bindGlobalEvents() {
    this.addProductButton?.addEventListener('click', () => this.addProductRow());

    this.resetCompanyButton?.addEventListener('click', () => {
      const hasSelectedWarehouse = this.rows().some(row => row.querySelector('.warehouse-select')?.value);

      if (
        hasSelectedWarehouse
        && !window.confirm('Đổi công ty nguồn sẽ xóa toàn bộ kho đã chọn. Tiếp tục?')
      ) {
        return;
      }

      this.resetCompanyLock();
    });

    this.saveBillingButton?.addEventListener('click', () => {
      this.saveCustomerBillingInfo().catch(console.error);
    });

    Object.values(this.invoiceFields).forEach(input => {
      input?.addEventListener('input', () => this.updateInvoiceState());
    });

    this.form?.addEventListener('submit', event => this.handleSubmit(event));

    document.addEventListener('click', event => {
      if (!event.target.closest('.ts-wrapper') && !event.target.closest('.ts-dropdown')) {
        document.querySelectorAll('.product-select, #customerSelect').forEach(select => {
          select.tomselect?.blur();
        });
      }
    });
  }

  async bootstrap() {
    this.initCustomerSelect();

    for (const row of this.rows()) {
      this.attachRowEvents(row);
      await this.bootstrapRow(row);
    }

    if (this.companyInput?.value) {
      const selectedWarehouse = this.rows().map(row => row._selectedWarehouse).find(Boolean);
      this.showCompanyLock(
        selectedWarehouse?.company_name || `Công ty #${this.companyInput.value}`
      );
    }

    if (this.customerSelect?.value) {
      await this.handleCustomerChange(this.customerSelect.value);
    }

    this.refreshRowNumbers();
    this.recalculateAllAvailability();
    this.updateTotals();
    this.updateInvoiceState();
    this.updateCreateButtonState();
  }

  initCustomerSelect() {
    const select = this.customerSelect;
    if (!select || typeof TomSelect === 'undefined' || select.tomselect) return;

    const manager = this;
    const searchUrl = select.dataset.searchUrl;

    new TomSelect(select, {
      valueField: 'id',
      labelField: 'text',
      searchField: ['text'],
      create: false,
      closeAfterSelect: true,
      maxOptions: 50,
      plugins: ['clear_button'],
      dropdownParent: 'body',
      placeholder: 'Tìm theo tên hoặc số điện thoại',
      shouldLoad(query) {
        return String(query || '').trim().length >= 1;
      },
      load(query, callback) {
        manager.setCustomerSearchStatus('Đang tìm khách hàng...', 'loading');

        const url = new URL(searchUrl, window.location.origin);
        url.searchParams.set('q', query || '');
        url.searchParams.set('limit', '30');

        fetch(url.toString(), {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        })
          .then(response => {
            if (!response.ok) throw new Error('Customer search failed');
            return response.json();
          })
          .then(payload => {
            const options = Array.isArray(payload.options) ? payload.options : [];
            manager.setCustomerSearchStatus(
              options.length ? `${options.length} khách hàng phù hợp.` : 'Không tìm thấy khách hàng.',
              options.length ? 'success' : 'danger'
            );
            callback(options);
          })
          .catch(() => {
            manager.setCustomerSearchStatus('Không tải được danh sách khách hàng.', 'danger');
            callback();
          });
      },
      render: {
        option(data, escape) {
          const phone = data.phone || data.mobile || '';
          return `
            <div class="oc-customer-option">
              <strong>${escape(data.name || data.text || '')}</strong>
              ${phone ? `<span>${escape(phone)}</span>` : ''}
            </div>`;
        },
        item(data, escape) {
          return `<div>${escape(data.text || data.name || '')}</div>`;
        },
        no_results() {
          return '<div class="no-results">Không tìm thấy khách hàng.</div>';
        },
      },
      onItemAdd(value) {
        const option = this.options[value] || {};
        if (manager.leadIdInput) manager.leadIdInput.value = option.lead_id || '';
        manager.handleCustomerChange(value).catch(console.error);
      },
      onClear() {
        manager.clearCustomerData();
      },
    });
  }

  setCustomerSearchStatus(message, state = 'muted') {
    if (!this.customerStatus) return;

    this.customerStatus.textContent = message;
    this.customerStatus.className = 'oc-field-feedback';

    if (state === 'danger') this.customerStatus.style.color = 'var(--oc-danger)';
    else if (state === 'success') this.customerStatus.style.color = 'var(--oc-success)';
    else if (state === 'loading') this.customerStatus.style.color = 'var(--oc-primary-dark)';
    else this.customerStatus.style.color = '';
  }

  clearCustomerData() {
    this.customerTypeId = null;
    this.customerPriceTierId = null;
    if (this.leadIdInput) this.leadIdInput.value = '';
    if (this.customerInfo) this.customerInfo.hidden = true;
    this.fillBillingInfo({});
    this.setBillingStatus('Chưa chọn khách hàng.', 'muted');
    this.setCustomerSearchStatus('Gõ tên hoặc số điện thoại để tìm khách hàng.');
    this.rows().forEach(row => {
      this.applyCustomerPriceTier(row);
      this.updateProductPrice(row);
      this.calculateLineTotal(row);
    });
    this.updateCreateButtonState();
  }

  async handleCustomerChange(customerId) {
    if (!customerId) {
      this.clearCustomerData();
      return;
    }

    this.setCustomerSearchStatus('Đang tải thông tin khách hàng...', 'loading');

    try {
      const base = this.customerSelect?.dataset.customerInfoUrl || '/orders/customer-info';
      const response = await fetch(`${base}/${customerId}`, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) throw new Error('Không tải được thông tin khách hàng');

      const payload = await response.json();
      const customer = payload.customer || {};

      this.customerTypeId = customer.customer_type_id
        || customer.customerType?.id
        || customer.customer_type?.id
        || null;

      this.customerPriceTierId = customer.price_tier_id || customer.priceTier?.id || null;

      if (this.leadIdInput && customer.lead_id) {
        this.leadIdInput.value = customer.lead_id;
      }

      this.renderCustomerMeta(customer, payload);
      this.setCustomerSearchStatus('Đã chọn khách hàng.', 'success');

      this.rows().forEach(row => {
        this.applyCustomerPriceTier(row);
        this.updateProductPrice(row);
        this.calculateLineTotal(row);
      });
    } catch (error) {
      console.error(error);
      this.setCustomerSearchStatus('Không tải được thông tin khách hàng.', 'danger');
    }

    await this.loadCustomerBillingInfo(customerId);
    this.updateCreateButtonState();
  }

  renderCustomerMeta(customer, payload) {
    if (!this.customerInfo) return;

    const typeName = customer.customerType?.name
      || customer.customer_type?.name
      || customer.customer_type_name
      || '—';

    const regionName = typeof customer.region === 'object'
      ? (customer.region?.name || '—')
      : (customer.region_name || customer.region || '—');

    const latestOrders = Array.isArray(payload.latest_orders) ? payload.latest_orders.length : 0;
    const totalDebt = Number(payload.total_debt || 0);

    const typeEl = document.getElementById('customerType');
    const regionEl = document.getElementById('customerRegion');
    const ordersEl = document.getElementById('customerOrders');
    const debtEl = document.getElementById('customerDebt');

    if (typeEl) typeEl.textContent = typeName;
    if (regionEl) regionEl.textContent = regionName;
    if (ordersEl) ordersEl.textContent = String(latestOrders);
    if (debtEl) debtEl.textContent = this.formatCurrency(totalDebt);

    this.customerInfo.hidden = false;
  }

  invoiceHasData() {
    return Object.values(this.invoiceFields).some(input => String(input?.value || '').trim() !== '');
  }

  updateInvoiceState() {
    const hasData = this.invoiceHasData();
    if (!this.invoiceStateText) return;

    this.invoiceStateText.textContent = hasData
      ? 'Đã có thông tin hóa đơn'
      : 'Không xuất hóa đơn';
    this.invoiceStateText.classList.toggle('has-data', hasData);
  }

  setBillingStatus(message, state = 'muted') {
    if (!this.billingStatus) return;

    this.billingStatus.textContent = message;
    this.billingStatus.className = '';

    if (state === 'success') this.billingStatus.classList.add('is-success');
    else if (state === 'danger') this.billingStatus.classList.add('is-danger');
    else if (state === 'loading') this.billingStatus.classList.add('is-loading');
  }

  fillBillingInfo(data = {}) {
    if (this.invoiceFields.company) this.invoiceFields.company.value = data.billing_company_name || '';
    if (this.invoiceFields.taxCode) this.invoiceFields.taxCode.value = data.billing_tax_code || '';
    if (this.invoiceFields.address) this.invoiceFields.address.value = data.billing_address || '';
    if (this.invoiceFields.email) this.invoiceFields.email.value = data.billing_email || '';
    this.updateInvoiceState();
  }

  async loadCustomerBillingInfo(customerId) {
    if (!customerId) {
      this.fillBillingInfo({});
      this.setBillingStatus('Chưa chọn khách hàng.', 'muted');
      return;
    }

    const template = this.customerSelect?.dataset.invoiceInfoUrlTemplate || '/customers/__ID__/invoice-info';
    const url = template.replace('__ID__', customerId);
    this.setBillingStatus('Đang tải thông tin hóa đơn...', 'loading');

    try {
      const response = await fetch(url, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) throw new Error('Không lấy được thông tin hóa đơn');

      const data = await response.json();
      this.fillBillingInfo(data);

      if (this.invoiceHasData()) {
        this.setBillingStatus('Đã lấy thông tin hóa đơn từ khách hàng.', 'success');
      } else {
        this.setBillingStatus('Khách hàng chưa có thông tin hóa đơn.', 'muted');
      }
    } catch (error) {
      console.error(error);
      this.fillBillingInfo({});
      this.setBillingStatus('Không tải được thông tin hóa đơn.', 'danger');
    }
  }

  async saveCustomerBillingInfo() {
    const customerId = this.customerSelect?.value || '';

    if (!customerId) {
      this.setBillingStatus('Vui lòng chọn khách hàng trước.', 'danger');
      return;
    }

    const template = this.saveBillingButton?.dataset.updateUrlTemplate || '/customers/__ID__/billing-info';
    const url = template.replace('__ID__', customerId);
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    this.saveBillingButton.disabled = true;
    this.setBillingStatus('Đang lưu thông tin hóa đơn...', 'loading');

    try {
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          billing_company_name: this.invoiceFields.company?.value || '',
          billing_tax_code: this.invoiceFields.taxCode?.value || '',
          billing_address: this.invoiceFields.address?.value || '',
          billing_email: this.invoiceFields.email?.value || '',
        }),
      });

      const payload = await response.json().catch(() => ({}));

      if (!response.ok) {
        const firstError = payload.errors
          ? Object.values(payload.errors).flat()[0]
          : null;
        throw new Error(firstError || payload.message || 'Lưu thông tin hóa đơn thất bại.');
      }

      this.setBillingStatus(payload.message || 'Đã lưu thông tin hóa đơn.', 'success');
    } catch (error) {
      this.setBillingStatus(error.message || 'Lưu thông tin hóa đơn thất bại.', 'danger');
    } finally {
      this.saveBillingButton.disabled = false;
    }
  }

  async bootstrapRow(row) {
    const productId = row.dataset.productId || '';
    const warehouseId = row.dataset.warehouseId || '';

    this.initProductSelect(row);
    this.toggleUnitPriceEditable(row);

    if (!productId) {
      this.clearWarehouseArea(row);
      this.calculateLineTotal(row);
      return;
    }

    try {
      const product = await this.fetchProductById(productId);

      if (!product) {
        this.setRowError(row, 'Sản phẩm cũ không còn tồn tại hoặc đã bị khóa.');
        return;
      }

      this.setProductOnRow(row, product, true);
      await this.loadWarehouses(row, warehouseId);
      this.updatePriceTierOptions(row);
      this.applyCustomerPriceTier(row);
      this.updateProductPrice(row);
      this.calculateLineTotal(row);
    } catch (error) {
      console.error(error);
      this.setRowError(row, 'Không tải được thông tin sản phẩm hoặc tồn kho.');
    }
  }

  attachRowEvents(row) {
    const warehouseSelect = row.querySelector('.warehouse-select');
    const tierSelect = row.querySelector('.price-tier-select');
    const quantity = row.querySelector('.quantity');
    const unitPrice = row.querySelector('.unit-price');
    const discountPercent = row.querySelector('.discount-percent');
    const discountAmount = row.querySelector('.discount-per-unit');
    const removeButton = row.querySelector('.remove-product-btn');

    this.initProductSelect(row);

    warehouseSelect?.addEventListener('change', () => this.handleWarehouseChange(row));

    tierSelect?.addEventListener('change', () => {
      this.updateProductPrice(row);
      this.calculateLineTotal(row);
    });

    quantity?.addEventListener('input', () => {
      this.calculateLineTotal(row);
      this.recalculateAllAvailability();
    });

    unitPrice?.addEventListener('input', () => this.calculateLineTotal(row));
    unitPrice?.addEventListener('change', () => this.calculateLineTotal(row));

    discountPercent?.addEventListener('input', () => {
      if (discountAmount) discountAmount.value = '0';
      this.calculateLineTotal(row);
    });

    discountAmount?.addEventListener('input', () => {
      if (discountPercent) discountPercent.value = '0';
      this.calculateLineTotal(row);
    });

    row.querySelectorAll('[data-qty-action]').forEach(button => {
      button.addEventListener('click', () => {
        if (!quantity) return;

        const direction = button.dataset.qtyAction === 'increase' ? 1 : -1;
        const current = Math.max(1, Number(quantity.value || 1));
        const maximum = Number(quantity.max || 0);
        let next = Math.max(1, current + direction);

        if (maximum > 0) next = Math.min(next, maximum);

        quantity.value = String(next);
        quantity.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });

    removeButton?.addEventListener('click', () => this.removeProductRow(row));
  }

  initProductSelect(row) {
    const select = row.querySelector('.product-select');
    if (!select || typeof TomSelect === 'undefined' || select.tomselect) return;

    const manager = this;

    new TomSelect(select, {
      valueField: 'id',
      labelField: 'text',
      searchField: ['name', 'sku', 'brand', 'text'],
      create: false,
      closeAfterSelect: true,
      maxOptions: 60,
      preload: 'focus',
      dropdownParent: 'body',
      placeholder: select.dataset.placeholder || 'Tìm sản phẩm...',
      load(query, callback) {
        manager.fetchProducts(query)
          .then(products => callback(products))
          .catch(() => callback());
      },
      render: {
        option(data, escape) {
          const hasStock = Number(data.stock_total || 0) > 0;
          const stockText = hasStock
            ? `Khả dụng: ${manager.formatNumber(data.stock_total)}`
            : 'Chưa có tồn khả dụng';

          return `
            <div class="ego-product-option ${hasStock ? 'has-stock' : 'out-stock'}">
              <div class="ego-product-option__main">
                <strong>${escape(data.name || data.text || '')}</strong>
                <span>${escape(data.sku ? `SKU: ${data.sku}` : 'Không có SKU')}</span>
              </div>
              <div class="ego-product-option__stock">
                <span>${escape(stockText)}</span>
                <small>${data.is_serialized ? 'Quản lý serial' : 'Hàng số lượng'}</small>
              </div>
            </div>`;
        },
        item(data, escape) {
          return `
            <div class="ego-product-selected-item">
              <strong>${escape(data.name || data.text || '')}</strong>
              ${data.sku ? `<small>${escape(data.sku)}</small>` : ''}
            </div>`;
        },
        no_results() {
          return '<div class="no-results">Không tìm thấy sản phẩm.</div>';
        },
      },
      onInitialize() {
        this.wrapper.style.width = '100%';
      },
      onChange(value) {
        manager.handleProductChange(row, value).catch(error => {
          console.error(error);
          manager.setRowError(row, 'Không tải được tồn kho của sản phẩm.');
        });
      },
    });
  }

  async fetchProducts(query = '') {
    const key = String(query || '').trim().toLowerCase();
    if (this.productCache.has(key)) return this.productCache.get(key);

    const url = new URL(this.productsUrl, window.location.origin);
    url.searchParams.set('q', query || '');
    url.searchParams.set('limit', '60');

    const response = await fetch(url.toString(), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) throw new Error('Không tải được danh sách sản phẩm');

    const payload = await response.json();
    const products = Array.isArray(payload.products) ? payload.products : [];

    products.forEach(product => this.productCache.set(`id:${product.id}`, product));
    this.productCache.set(key, products);

    return products;
  }

  async fetchProductById(productId) {
    const key = `id:${productId}`;
    if (this.productCache.has(key)) return this.productCache.get(key);

    const url = new URL(this.productsUrl, window.location.origin);
    url.searchParams.set('id', String(productId));
    url.searchParams.set('limit', '1');

    const response = await fetch(url.toString(), {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) return null;

    const payload = await response.json();
    const product = Array.isArray(payload.products) ? payload.products[0] : null;

    if (product) this.productCache.set(key, product);
    return product;
  }

  async handleProductChange(row, productId) {
    this.clearRowError(row);
    this.clearWarehouseSelection(row);

    if (!productId) {
      row._product = null;
      row.dataset.productId = '';
      this.clearWarehouseArea(row);
      this.updateProductSummary(row);
      this.updateProductPrice(row);
      this.calculateLineTotal(row);
      this.recalculateCompanyLock();
      this.updateCreateButtonState();
      return;
    }

    const select = row.querySelector('.product-select');
    let product = select?.tomselect?.options?.[productId] || null;

    if (!product || !product.name) {
      product = await this.fetchProductById(productId);
    }

    if (!product) {
      this.setRowError(row, 'Không tìm thấy sản phẩm đã chọn.');
      return;
    }

    this.setProductOnRow(row, product, true);
    await this.loadWarehouses(row, '');
    this.updatePriceTierOptions(row);
    this.applyCustomerPriceTier(row);
    this.updateProductPrice(row);
    this.calculateLineTotal(row);
    this.updateCreateButtonState();
  }

  setProductOnRow(row, product, silent = false) {
    const normalized = this.normalizeProduct(product);
    row._product = normalized;
    row.dataset.productId = String(normalized.id);

    const select = row.querySelector('.product-select');
    if (select?.tomselect) {
      select.tomselect.addOption(normalized);
      select.tomselect.setValue(String(normalized.id), silent);
    }

    const vatInput = row.querySelector('.item-vat-percent');
    if (vatInput) vatInput.value = String(normalized.vat_percent || 0);

    this.updateProductSummary(row);
  }

  normalizeProduct(product) {
    return {
      ...product,
      id: String(product.id || product.product_id || ''),
      name: product.name || product.text || '',
      sku: product.sku || '',
      brand: product.brand || product.brand_name || '',
      text: product.text || product.name || '',
      price_retail: Number(product.price_retail || product.price || 0),
      price_agent: Number(product.price_agent || 0),
      vat_percent: Number(product.vat_percent || 0),
      tier_prices: product.tier_prices || {},
      tier_vats: product.tier_vats || {},
      stock_total: Number(product.stock_total || product.available_stock || 0),
      reserved_total: Number(product.reserved_total || 0),
      is_serialized: Boolean(product.is_serialized),
    };
  }

  updateProductSummary(row) {
    const panel = row.querySelector('[data-product-summary]');
    const product = row._product;
    if (!panel) return;

    if (!product) {
      panel.hidden = true;
      panel.innerHTML = '';
      return;
    }

    panel.hidden = false;
    panel.innerHTML = `
      <span><i class="bi bi-upc-scan"></i>${this.escapeHtml(product.sku || 'Không có SKU')}</span>
      <span>${product.is_serialized ? 'Quản lý serial' : 'Quản lý số lượng'}</span>`;
  }

  async loadWarehouses(row, preserveWarehouseId = '') {
    const product = row._product;
    const select = row.querySelector('.warehouse-select');
    if (!product || !select) return;

    this.setStockStatus(row, 'Đang kiểm tra tồn kho...', 'loading');
    select.disabled = true;
    select.innerHTML = '<option value="">Đang kiểm tra tồn kho...</option>';

    const companyId = this.companyInput?.value || '';
    const cacheKey = `${product.id}|${companyId}`;
    let warehouses = this.warehouseCache.get(cacheKey);

    if (!warehouses) {
      const url = new URL(`${this.warehousesUrl}/${product.id}`, window.location.origin);
      if (companyId) url.searchParams.set('company_id', companyId);

      const response = await fetch(url.toString(), {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) throw new Error('Không tải được tồn kho theo kho');

      const payload = await response.json();
      warehouses = Array.isArray(payload.warehouses) ? payload.warehouses : [];
      this.warehouseCache.set(cacheKey, warehouses);
    }

    row._warehouses = warehouses;
    this.populateWarehouseSelect(row, preserveWarehouseId);

    if (preserveWarehouseId) {
      select.value = String(preserveWarehouseId);
      this.handleWarehouseChange(row, true);
    } else {
      this.updateRowAvailability(row);
    }
  }

  populateWarehouseSelect(row, preserveWarehouseId = '') {
    const select = row.querySelector('.warehouse-select');
    if (!select) return;

    const allWarehouses = Array.isArray(row._warehouses)
      ? row._warehouses
      : [];

    const inStockCount = allWarehouses.filter(
      warehouse => Number(warehouse.available_qty || 0) > 0
    ).length;

    select.innerHTML = '<option value="">Chọn kho xuất</option>';

    allWarehouses.forEach(warehouse => {
      const option = document.createElement('option');
      const available = Number(warehouse.available_qty || 0);

      option.value = String(warehouse.id);

      option.textContent = available > 0
        ? `${warehouse.name} — khả dụng ${this.formatNumber(available)}`
        : `${warehouse.name} — tồn 0 (vẫn lên đơn)`;

      option.dataset.companyId =
        String(warehouse.company_id || '');

      option.dataset.companyName =
        warehouse.company_name || '';

      select.appendChild(option);
    });

    select.disabled = allWarehouses.length === 0;

    if (allWarehouses.length === 0) {

      this.setStockStatus(
        row,
        'Sản phẩm chưa có kho xuất phù hợp.',
        'danger'
      );

      this.setRowError(
        row,
        'Sản phẩm chưa được gán vào kho xuất nào.'
      );

    } else if (inStockCount === 0) {

      this.setStockStatus(
        row,
        'Kho đang tồn 0 — vẫn có thể tạo đơn.',
        'warning'
      );

      this.clearRowError(row);

    } else if (inStockCount < allWarehouses.length) {

      this.setStockStatus(
        row,
        `${inStockCount}/${allWarehouses.length} kho còn hàng; kho tồn 0 vẫn chọn được.`,
        'warning'
      );

      this.clearRowError(row);

    } else {

      this.setStockStatus(
        row,
        `${inStockCount} kho còn hàng.`,
        'success'
      );

      this.clearRowError(row);
    }
  }

  setStockStatus(row, message, state = 'neutral') {
    const status = row.querySelector('[data-stock-status]');
    if (!status) return;

    status.textContent = message;
    status.className = 'oc-stock-status';
    status.style.removeProperty('color');

    if (state === 'loading') {
      status.classList.add('is-loading');
    } else if (state === 'danger') {
      status.classList.add('is-danger');
    } else if (state === 'success') {
      status.classList.add('is-success');
    } else if (state === 'warning') {
      status.style.color = '#d97706';
    }
  }

  clearWarehouseArea(row) {
    const select = row.querySelector('.warehouse-select');
    const stockValues = row.querySelector('[data-stock-values]');
    const help = row.querySelector('[data-qty-help]');

    row._warehouses = [];
    row._selectedWarehouse = null;
    row.dataset.availableForRow = '0';

    if (select) {
      select.innerHTML = '<option value="">Chọn sản phẩm trước</option>';
      select.value = '';
      select.disabled = true;
    }

    this.setStockStatus(row, 'Chưa chọn sản phẩm');
    if (stockValues) stockValues.hidden = true;
    if (help) help.textContent = 'Chọn kho để kiểm tra tồn.';
  }

  clearWarehouseSelection(row) {
    const select = row.querySelector('.warehouse-select');
    row._selectedWarehouse = null;
    row.dataset.warehouseId = '';
    row.dataset.availableForRow = '0';
    if (select) select.value = '';
  }

  handleWarehouseChange(row, silent = false) {
    const select = row.querySelector('.warehouse-select');
    const warehouseId = select?.value || '';
    const warehouses = Array.isArray(row._warehouses) ? row._warehouses : [];
    const warehouse = warehouses.find(item => String(item.id) === String(warehouseId)) || null;

    this.clearRowError(row);
    row._selectedWarehouse = warehouse;
    row.dataset.warehouseId = warehouseId;

    const stockValues = row.querySelector('[data-stock-values]');
    const stockQty = row.querySelector('[data-stock-qty]');
    const reservedQty = row.querySelector('[data-reserved-qty]');

    if (!warehouse) {
      if (stockValues) stockValues.hidden = true;
      this.recalculateCompanyLock();
      this.updateRowAvailability(row);
      this.updateTotals();
      this.updateCreateButtonState();
      return;
    }

    const lockedCompanyId = String(this.companyInput?.value || '');
    const warehouseCompanyId = String(warehouse.company_id || '');

    if (lockedCompanyId && warehouseCompanyId && lockedCompanyId !== warehouseCompanyId) {
      select.value = '';
      row._selectedWarehouse = null;
      if (stockValues) stockValues.hidden = true;
      this.setRowError(row, 'Một đơn hàng không được lấy hàng từ nhiều công ty khác nhau.');
      this.updateCreateButtonState();
      return;
    }

    if (!lockedCompanyId && warehouseCompanyId) {
      this.companyInput.value = warehouseCompanyId;
      this.showCompanyLock(warehouse.company_name || `Công ty #${warehouseCompanyId}`);
      this.reloadRowsForCompany(row).catch(console.error);
    }

    if (stockValues) stockValues.hidden = false;
    if (stockQty) stockQty.textContent = this.formatNumber(warehouse.stock_qty || 0);
    if (reservedQty) reservedQty.textContent = this.formatNumber(warehouse.reserved_qty || 0);

    this.setStockStatus(row, 'Kho đã sẵn sàng.', 'success');
    this.recalculateAllAvailability();
    this.updateTotals();
    this.updateCreateButtonState();

    if (!silent) row.querySelector('.quantity')?.focus();
  }

  async reloadRowsForCompany(exceptRow = null) {
    this.warehouseCache.clear();

    const jobs = this.rows()
      .filter(row => row !== exceptRow && row._product)
      .map(row => {
        this.clearWarehouseSelection(row);
        return this.loadWarehouses(row, '');
      });

    await Promise.allSettled(jobs);
  }

  recalculateCompanyLock() {
    const selected = this.rows().map(row => row._selectedWarehouse).filter(Boolean);
    const companyIds = [...new Set(selected.map(item => String(item.company_id || '')).filter(Boolean))];

    if (!companyIds.length) {
      if (this.companyInput) this.companyInput.value = '';
      this.hideCompanyLock();
      return;
    }

    if (companyIds.length === 1) {
      const warehouse = selected.find(item => String(item.company_id || '') === companyIds[0]);
      if (this.companyInput) this.companyInput.value = companyIds[0];
      this.showCompanyLock(warehouse?.company_name || `Công ty #${companyIds[0]}`);
    }
  }

  resetCompanyLock() {
    if (this.companyInput) this.companyInput.value = '';
    this.hideCompanyLock();
    this.warehouseCache.clear();

    this.rows().forEach(row => {
      this.clearWarehouseSelection(row);
      if (row._product) this.loadWarehouses(row, '').catch(console.error);
    });

    this.recalculateAllAvailability();
    this.updateCreateButtonState();
  }

  showCompanyLock(name) {
    if (this.companyLockName) this.companyLockName.textContent = name || 'Đã xác định';
    if (this.companyLock) this.companyLock.hidden = false;
  }

  hideCompanyLock() {
    if (this.companyLock) this.companyLock.hidden = true;
  }

  getOtherRowsQuantity(row, productId, warehouseId) {
    return this.rows().reduce((total, candidate) => {
      if (candidate === row) return total;

      const candidateProductId = candidate._product?.id || candidate.dataset.productId || '';
      const candidateWarehouseId = candidate.querySelector('.warehouse-select')?.value || '';

      if (
        String(candidateProductId) !== String(productId)
        || String(candidateWarehouseId) !== String(warehouseId)
      ) {
        return total;
      }

      return total + Math.max(0, Number(candidate.querySelector('.quantity')?.value || 0));
    }, 0);
  }

  updateRowAvailability(row) {
    const product = row._product;
    const warehouse = row._selectedWarehouse;
    const quantityInput = row.querySelector('.quantity');
    const help = row.querySelector('[data-qty-help]');
    const availableValue =
      row.querySelector('[data-available-value]');

    if (!quantityInput) return;

    if (!product || !warehouse) {
      quantityInput.removeAttribute('max');
      quantityInput.classList.remove('is-invalid');

      row.dataset.availableForRow = '0';

      if (help) {
        help.textContent = 'Chọn kho để xem tồn hiện tại.';
      }

      if (availableValue) {
        availableValue.textContent = '—';
      }

      return;
    }

    const otherQuantity = this.getOtherRowsQuantity(
      row,
      product.id,
      warehouse.id
    );

    const availableForRow = Math.max(
      0,
      Number(warehouse.available_qty || 0) - otherQuantity
    );

    const currentQuantity = Math.max(
      0,
      Number(quantityInput.value || 0)
    );

    row.dataset.availableForRow =
      String(availableForRow);

    // Không giới hạn số lượng theo tồn khi tạo đơn
    quantityInput.removeAttribute('max');
    quantityInput.classList.remove('is-invalid');

    if (availableValue) {
      availableValue.textContent =
        this.formatNumber(availableForRow);
    }

    if (availableForRow <= 0) {

      if (help) {
        help.innerHTML =
          '<span style="color:#d97706">Tồn 0 — vẫn được tạo đơn; kho bổ sung hàng trước khi xuất.</span>';
      }

      this.setStockStatus(
        row,
        `Kho ${warehouse.name} đang tồn 0 — vẫn có thể tạo đơn.`,
        'warning'
      );

      this.clearRowError(row);

    } else if (currentQuantity > availableForRow) {

      if (help) {
        help.innerHTML =
          `<span style="color:#d97706">Khả dụng ${this.formatNumber(availableForRow)} — vẫn được tạo đơn.</span>`;
      }

      this.setStockStatus(
        row,
        `Kho ${warehouse.name} đang thiếu ${this.formatNumber(currentQuantity - availableForRow)} — vẫn có thể tạo đơn.`,
        'warning'
      );

      this.clearRowError(row);

    } else {

      if (help) {
        help.textContent =
          `Khả dụng ${this.formatNumber(availableForRow)}`;
      }

      this.setStockStatus(
        row,
        'Kho đã sẵn sàng.',
        'success'
      );

      this.clearRowError(row, true);
    }

    this.updateCreateButtonState();
  }

  recalculateAllAvailability() {
    this.rows().forEach(row => this.updateRowAvailability(row));
  }

  updatePriceTierOptions(row) {
    const select = row.querySelector('.price-tier-select');
    const product = row._product;
    if (!select || !product) return;

    const current = select.value || '';
    const prices = product.tier_prices || {};

    select.innerHTML = '<option value="">Theo loại khách hàng</option>';

    this.allPriceTiers.forEach(tier => {
      const isInternal = String(tier.code || '').toLowerCase() === 'internal'
        || String(tier.name || '').toLowerCase().includes('nội bộ');
      const hasPrice = Number(prices[tier.id] || 0) > 0;

      if (!hasPrice && !isInternal) return;

      const option = document.createElement('option');
      option.value = String(tier.id);
      option.textContent = tier.name;
      option.dataset.tierCode = tier.code || '';
      select.appendChild(option);
    });

    if (current && Array.from(select.options).some(option => option.value === String(current))) {
      select.value = String(current);
    }
  }

  applyCustomerPriceTier(row) {
    const select = row.querySelector('.price-tier-select');
    if (!select || !this.customerPriceTierId) return;

    const exists = Array.from(select.options).some(
      option => option.value === String(this.customerPriceTierId)
    );

    if (exists) select.value = String(this.customerPriceTierId);
  }

  isInternalPriceTier(row) {
    const option = row.querySelector('.price-tier-select')?.selectedOptions?.[0];
    const code = String(option?.dataset?.tierCode || '').toLowerCase();
    const text = String(option?.textContent || '').toLowerCase();
    return code === 'internal' || text.includes('nội bộ');
  }

  toggleUnitPriceEditable(row) {
    const input = row.querySelector('.unit-price');
    if (!input) return;

    if (this.isInternalPriceTier(row)) {
      input.removeAttribute('readonly');
      input.classList.add('is-editable');
    } else {
      input.setAttribute('readonly', 'readonly');
      input.classList.remove('is-editable');
    }
  }

  updateProductPrice(row) {
    const product = row._product;
    const input = row.querySelector('.unit-price');
    const vatInput = row.querySelector('.item-vat-percent');
    if (!input) return;

    this.toggleUnitPriceEditable(row);

    if (!product) {
      if (!this.isInternalPriceTier(row)) input.value = '';
      if (vatInput) vatInput.value = '0';
      return;
    }

    const tierSelect = row.querySelector('.price-tier-select');
    const tierId = tierSelect?.value || '';
    let vat = Number(product.vat_percent || 0);

    if (tierId && product.tier_vats?.[tierId] !== undefined) {
      vat = Number(product.tier_vats[tierId] || vat);
    }

    if (vatInput) vatInput.value = String(vat);
    if (this.isInternalPriceTier(row)) return;

    let price = 0;

    if (tierId && Number(product.tier_prices?.[tierId] || 0) > 0) {
      price = Number(product.tier_prices[tierId]);
    } else if (Number(this.customerTypeId) === 1) {
      price = Number(product.price_agent || product.price_retail || 0);
    } else {
      price = Number(product.price_retail || product.price_agent || 0);
    }

    input.value = price > 0 ? String(Math.round(price)) : '';
  }

  calculateLineTotal(row) {
    const price = this.parseNumber(row.querySelector('.unit-price')?.value);
    const quantity = Math.max(0, Number(row.querySelector('.quantity')?.value || 0));
    const percentInput = row.querySelector('.discount-percent');
    const amountInput = row.querySelector('.discount-per-unit');
    const percent = this.clampPercent(percentInput?.value);
    const amount = Math.max(0, this.parseNumber(amountInput?.value));

    if (percentInput) percentInput.value = String(percent);
    if (amountInput) amountInput.value = String(amount);

    const subtotal = price * quantity;
    const discount = amount > 0
      ? Math.min(subtotal, amount * quantity)
      : Math.min(subtotal, subtotal * percent / 100);
    const total = Math.max(0, subtotal - discount);

    const totalInput = row.querySelector('.line-total');
    if (totalInput) totalInput.value = total > 0 ? this.formatNumber(total) : '';

    row.dataset.discountTotal = String(discount);
    this.updateTotals();
  }

  updateTotals() {
    let itemCount = 0;
    let quantityTotal = 0;
    let beforeVatTotal = 0;
    let vatTotal = 0;
    let discountTotal = 0;
    let afterVatTotal = 0;

    this.rows().forEach(row => {
      const product = row._product;
      const warehouseId = row.querySelector('.warehouse-select')?.value || '';
      if (!product || !warehouseId) return;

      const quantity = Math.max(0, Number(row.querySelector('.quantity')?.value || 0));
      const lineAfterVat = Math.max(0, this.parseNumber(row.querySelector('.line-total')?.value));
      const vatPercent = Math.max(0, Number(row.querySelector('.item-vat-percent')?.value || 0));
      const lineBeforeVat = vatPercent > 0 ? lineAfterVat / (1 + vatPercent / 100) : lineAfterVat;
      const lineVat = Math.max(0, lineAfterVat - lineBeforeVat);

      itemCount += 1;
      quantityTotal += quantity;
      beforeVatTotal += lineBeforeVat;
      vatTotal += lineVat;
      discountTotal += Math.max(0, Number(row.dataset.discountTotal || 0));
      afterVatTotal += lineAfterVat;
    });

    if (this.summary.totalItems) this.summary.totalItems.textContent = String(itemCount);
    if (this.summary.totalQuantity) this.summary.totalQuantity.textContent = String(quantityTotal);
    if (this.summary.totalBeforeVat) this.summary.totalBeforeVat.textContent = this.formatCurrency(beforeVatTotal);
    if (this.summary.totalVat) this.summary.totalVat.textContent = this.formatCurrency(vatTotal);
    if (this.summary.totalDiscount) this.summary.totalDiscount.textContent = this.formatCurrency(discountTotal);
    if (this.summary.discountRow) this.summary.discountRow.hidden = discountTotal <= 0;
    if (this.summary.totalAmount) this.summary.totalAmount.textContent = this.formatCurrency(afterVatTotal);

    this.updateCreateButtonState();
  }

  addProductRow() {
    const first = this.rows()[0];
    if (!first || !this.productList) return;

    const clone = first.cloneNode(true);
    const index = this.rowIndex++;

    clone.dataset.index = String(index);
    clone.dataset.productId = '';
    clone.dataset.warehouseId = '';
    clone.dataset.availableForRow = '0';
    clone.dataset.discountTotal = '0';
    clone._product = null;
    clone._warehouses = [];
    clone._selectedWarehouse = null;

    clone.querySelectorAll('.ts-wrapper').forEach(wrapper => wrapper.remove());

    const productSelect = clone.querySelector('.product-select');
    if (productSelect) {
      productSelect.classList.remove('tomselected', 'ts-hidden-accessible', 'is-invalid');
      productSelect.removeAttribute('tabindex');
      productSelect.removeAttribute('hidden');
      productSelect.style.removeProperty('display');
      productSelect.innerHTML = '<option value="">Tìm theo tên hoặc SKU</option>';
    }

    clone.querySelectorAll('input, select, textarea').forEach(element => {
      const name = element.getAttribute('name');
      if (name) element.setAttribute('name', name.replace(/items\[\d+\]/g, `items[${index}]`));

      element.classList.remove('is-invalid');

      if (element.classList.contains('warehouse-select')) {
        element.innerHTML = '<option value="">Chọn sản phẩm trước</option>';
        element.value = '';
        element.disabled = true;
      } else if (element.classList.contains('price-tier-select')) {
        element.value = '';
      } else if (element.classList.contains('unit-price') || element.classList.contains('line-total')) {
        element.value = '';
      } else if (element.classList.contains('discount-percent') || element.classList.contains('discount-per-unit')) {
        element.value = '0';
      } else if (element.classList.contains('quantity')) {
        element.value = '1';
        element.removeAttribute('max');
      } else if (element.classList.contains('item-vat-percent')) {
        element.value = '0';
      }
    });

    clone.querySelectorAll('input[type="hidden"]').forEach(input => {
      if (/\[id\]$/.test(input.name || '')) input.remove();
    });

    const productSummary = clone.querySelector('[data-product-summary]');
    if (productSummary) {
      productSummary.hidden = true;
      productSummary.innerHTML = '';
    }

    const stockValues = clone.querySelector('[data-stock-values]');
    if (stockValues) stockValues.hidden = true;

    const validation = clone.querySelector('[data-row-validation]');
    if (validation) {
      validation.hidden = true;
      validation.innerHTML = '';
    }

    clone.querySelectorAll('.oc-invalid-feedback').forEach(error => error.remove());

    this.productList.appendChild(clone);
    this.attachRowEvents(clone);
    this.clearWarehouseArea(clone);
    this.refreshRowNumbers();
    this.updateTotals();

    clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => clone.querySelector('.product-select')?.tomselect?.focus(), 180);
  }

  removeProductRow(row) {
    if (this.rows().length <= 1) {
      this.resetRow(row);
      return;
    }

    row.querySelector('.product-select')?.tomselect?.destroy();
    row.remove();
    this.refreshRowIndexes();
    this.recalculateCompanyLock();
    this.recalculateAllAvailability();
    this.updateTotals();
  }

  resetRow(row) {
    const select = row.querySelector('.product-select');
    select?.tomselect?.clear(true);

    row._product = null;
    row.dataset.productId = '';
    row.dataset.discountTotal = '0';

    this.clearWarehouseArea(row);
    row.querySelector('.price-tier-select').value = '';
    row.querySelector('.unit-price').value = '';
    row.querySelector('.quantity').value = '1';
    row.querySelector('.discount-percent').value = '0';
    row.querySelector('.discount-per-unit').value = '0';
    row.querySelector('.line-total').value = '';
    row.querySelector('.item-vat-percent').value = '0';

    this.updateProductSummary(row);
    this.clearRowError(row);
    this.recalculateCompanyLock();
    this.updateTotals();
  }

  refreshRowNumbers() {
    this.rows().forEach((row, index) => {
      const badge = row.querySelector('.ego-product-row__number > span');
      const title = row.querySelector('.ego-product-row__number strong');
      if (badge) badge.textContent = String(index + 1);
      if (title) title.textContent = `Sản phẩm ${index + 1}`;
    });
  }

  refreshRowIndexes() {
    this.rows().forEach((row, index) => {
      row.dataset.index = String(index);
      row.querySelectorAll('input, select, textarea').forEach(element => {
        const name = element.getAttribute('name');
        if (name) element.setAttribute('name', name.replace(/items\[\d+\]/g, `items[${index}]`));
      });
    });

    this.rowIndex = this.rows().length;
    this.refreshRowNumbers();
  }

  isFormReady() {
    if (!this.customerSelect?.value) return false;

    const selectedRows = this.rows().filter(
      row =>
        row._product ||
        row.querySelector('.product-select')?.value
    );

    if (!selectedRows.length) return false;

    return selectedRows.every(row => {
      const product = row._product;
      const warehouse = row._selectedWarehouse;

      const quantity = Number(
        row.querySelector('.quantity')?.value || 0
      );

      const price = this.parseNumber(
        row.querySelector('.unit-price')?.value
      );

      return Boolean(product)
        && Boolean(warehouse)
        && quantity > 0
        && price >= 0;
    });
  }

  updateCreateButtonState() {
    if (!this.primarySubmit || this.isSubmitting) return;
    this.primarySubmit.disabled = !this.isFormReady();
  }

  validateBeforeSubmit() {
    let valid = true;
    let firstInvalid = null;

    if (!this.customerSelect?.value) {
      this.setCustomerSearchStatus(
        'Vui lòng chọn khách hàng.',
        'danger'
      );

      this.customerSelect?.tomselect?.focus();
      valid = false;
    }

    const orderDate =
      document.getElementById('orderDate');

    if (!orderDate?.value) {
      orderDate?.classList.add('is-invalid');

      if (!firstInvalid) {
        firstInvalid = orderDate;
      }

      valid = false;
    }

    const selectedRows = this.rows().filter(
      row =>
        row._product ||
        row.querySelector('.product-select')?.value
    );

    if (!selectedRows.length) {
      this.toast(
        'Vui lòng chọn ít nhất một sản phẩm.',
        'danger'
      );

      this.rows()[0]
        ?.querySelector('.product-select')
        ?.tomselect
        ?.focus();

      return false;
    }

    const seen = new Set();

    selectedRows.forEach(row => {
      const product = row._product;
      const warehouse = row._selectedWarehouse;

      const quantity = Number(
        row.querySelector('.quantity')?.value || 0
      );

      const duplicateKey =
        product && warehouse
          ? `${product.id}|${warehouse.id}`
          : '';

      let rowValid = true;

      if (!product) {

        this.setRowError(
          row,
          'Vui lòng chọn sản phẩm.'
        );

        rowValid = false;

      } else if (!warehouse) {

        this.setRowError(
          row,
          'Vui lòng chọn kho xuất.'
        );

        rowValid = false;

      } else if (quantity <= 0) {

        this.setRowError(
          row,
          'Số lượng phải lớn hơn 0.'
        );

        rowValid = false;

      } else if (
        duplicateKey &&
        seen.has(duplicateKey)
      ) {

        this.setRowError(
          row,
          'Sản phẩm bị trùng trong cùng kho. Hãy gộp số lượng.'
        );

        rowValid = false;
      }

      // Không chặn quantity > available

      if (duplicateKey) {
        seen.add(duplicateKey);
      }

      if (!rowValid) {
        valid = false;

        if (!firstInvalid) {
          firstInvalid = row;
        }
      }
    });

    if (!valid) {
      firstInvalid?.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });

      this.toast(
        'Vui lòng xử lý các trường đang báo lỗi.',
        'danger'
      );
    }

    return valid;
  }

  handleSubmit(event) {
    if (this.isSubmitting) {
      event.preventDefault();
      return;
    }

    if (!this.validateBeforeSubmit()) {
      event.preventDefault();
      return;
    }

    this.isSubmitting = true;
    const activeButton = event.submitter || this.primarySubmit;

    this.submitButtons.forEach(button => {
      button.disabled = true;
      button.classList.add('is-loading');
      button.dataset.originalHtml = button.innerHTML;
    });

    if (activeButton) {
      activeButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Đang xử lý...</span>';
    }
  }

  setRowError(row, message) {
    const box = row.querySelector('[data-row-validation]');
    if (!box) return;

    box.hidden = false;
    box.innerHTML = `<i class="bi bi-exclamation-circle"></i> ${this.escapeHtml(message)}`;
    row.classList.add('has-error');
  }

  clearRowError(row, keepStockError = false) {
    const quantity = row.querySelector('.quantity');
    if (keepStockError && quantity?.classList.contains('is-invalid')) return;

    const box = row.querySelector('[data-row-validation]');
    if (box) {
      box.hidden = true;
      box.innerHTML = '';
    }

    row.classList.remove('has-error');
  }

  parseNumber(value) {
    if (value === null || value === undefined) return 0;

    let text = String(value).trim().replace(/\s/g, '').replace(/đ/gi, '');
    if (!text) return 0;

    if (/^\d{1,3}(\.\d{3})+$/.test(text)) return Number(text.replace(/\./g, '')) || 0;
    if (/^\d{1,3}(,\d{3})+$/.test(text)) return Number(text.replace(/,/g, '')) || 0;

    if (text.includes('.') && text.includes(',')) text = text.replace(/\./g, '').replace(',', '.');
    else if (text.includes(',') && !text.includes('.')) text = text.replace(',', '.');

    return Number(text) || 0;
  }

  clampPercent(value) {
    const number = Number(value || 0);
    if (!Number.isFinite(number)) return 0;
    return Math.max(0, Math.min(100, number));
  }

  formatNumber(value) {
    return new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 })
      .format(Number(value || 0));
  }

  formatCurrency(value) {
    return `${this.formatNumber(Math.round(Number(value || 0)))} đ`;
  }

  escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  toast(message, type = 'info') {
    let container = document.getElementById('egoOrderToastContainer');

    if (!container) {
      container = document.createElement('div');
      container.id = 'egoOrderToastContainer';
      container.className = 'ego-order-toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `ego-order-toast ${type}`;
    toast.innerHTML = `
      <i class="bi bi-${type === 'danger' ? 'exclamation-triangle' : 'check-circle'}"></i>
      <span>${this.escapeHtml(message)}</span>`;

    container.appendChild(toast);
    setTimeout(() => toast.classList.add('is-visible'), 20);
    setTimeout(() => {
      toast.classList.remove('is-visible');
      setTimeout(() => toast.remove(), 250);
    }, 3500);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  try {
    window.orderFormManager = new OrderFormManager();
  } catch (error) {
    console.error('OrderFormManager init failed:', error);
  }
});
