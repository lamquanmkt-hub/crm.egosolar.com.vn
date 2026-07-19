(function () {
  'use strict';

  let currentWarehouseId = null;

  function $(id) { return document.getElementById(id); }

  function recalcTotalQty() {
    let total = 0;
    document.querySelectorAll('.js-warehouse-qty').forEach(el => {
      const v = parseInt(el.value || '0', 10);
      if (!isNaN(v)) total += v;
    });
    const totalEl = $('total_qty_display');
    if (totalEl) totalEl.value = total;
  }

  function safeParseJsonArray(value) {
    try {
      if (!value) return [];
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
      return [];
    }
  }

  function validateSerials(serials) {
    const errors = [];
    const seen = new Set();

    serials.forEach((serial, index) => {
      const s = (serial || '').trim();
      const lineNum = index + 1;

      if (!s) {
        errors.push(`Dòng ${lineNum}: Serial không được rỗng`);
        return;
      }
      if (s.length < 6) errors.push(`Dòng ${lineNum}: "${s}" quá ngắn (tối thiểu 6 ký tự)`);
      if (s.length > 50) errors.push(`Dòng ${lineNum}: "${s}" quá dài (tối đa 50 ký tự)`);

      if (!/^[A-Za-z0-9\-_\/]+$/.test(s)) {
        errors.push(`Dòng ${lineNum}: "${s}" chứa ký tự không hợp lệ (chỉ cho phép A-Z, 0-9, -, _, /)`);
      }

      const upper = s.toUpperCase();
      if (seen.has(upper)) errors.push(`Dòng ${lineNum}: "${s}" bị trùng trong danh sách`);
      seen.add(upper);
    });

    return errors;
  }

  function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      container.style.zIndex = '1100';
      document.body.appendChild(container);
    }

    const bgClass = ({
      success: 'bg-success',
      error: 'bg-danger',
      warning: 'bg-warning',
      info: 'bg-info'
    })[type] || 'bg-info';

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white ${bgClass} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    `;

    container.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
    bsToast.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
  }

  function hideSerialErrors() {
    const errorContainer = $('serial-errors');
    if (!errorContainer) return;
    errorContainer.innerHTML = '';
    errorContainer.style.display = 'none';
  }

  function showSerialErrors(errors) {
    const errorContainer = $('serial-errors');
    if (!errorContainer) return;

    errorContainer.innerHTML = `
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong><i class="bi bi-exclamation-triangle"></i> Có ${errors.length} lỗi:</strong>
        <ul class="mb-0 mt-2">
          ${errors.map(e => `<li>${e}</li>`).join('')}
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    `;
    errorContainer.style.display = 'block';
  }

  function updateEnteredCount() {
    const textarea = $('serial-textarea');
    if (!textarea) return;

    const serials = textarea.value
      .split('\n')
      .map(s => s.trim())
      .filter(s => s.length > 0);

    const countEl = $('serial-entered-count');
    if (countEl) countEl.textContent = serials.length;

    const preview = $('serial-list-preview');
    if (!preview) return;

    if (serials.length === 0) {
      preview.innerHTML = '';
      return;
    }

    const badges = serials.map((s, i) => {
      let badgeClass = 'bg-secondary';
      if (s.length < 6 || s.length > 50) badgeClass = 'bg-warning text-dark';
      if (!/^[A-Za-z0-9\-_\/]+$/.test(s)) badgeClass = 'bg-danger';
      return `<span class="badge ${badgeClass} me-1 mb-1">${i + 1}. ${s.toUpperCase()}</span>`;
    }).join('');

    preview.innerHTML = `
      <label class="form-label small text-muted">Preview (đã chuyển IN HOA):</label>
      <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;">
        ${badges}
      </div>
    `;
  }

  function autoUppercase(textarea) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    textarea.value = textarea.value.toUpperCase();
    textarea.setSelectionRange(start, end);
  }

  function openSerialModal(warehouseId, warehouseName) {
    currentWarehouseId = warehouseId;

    const nameEl = $('serial-modal-warehouse-name');
    if (nameEl) nameEl.textContent = warehouseName || '';

    const hiddenInput = $('serials-' + warehouseId);
    const existingSerials = safeParseJsonArray(hiddenInput ? hiddenInput.value : '[]');

    const textarea = $('serial-textarea');
    if (textarea) textarea.value = existingSerials.join('\n');

    updateEnteredCount();
    hideSerialErrors();

    const modalEl = $('serialModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    setTimeout(() => textarea && textarea.focus(), 200);
  }

  async function pasteFromClipboard() {
    const textarea = $('serial-textarea');
    if (!textarea) return;

    try {
      const text = await navigator.clipboard.readText();
      const cur = textarea.value.trim();
      textarea.value = cur ? (cur + '\n' + text) : text;
      updateEnteredCount();
    } catch (err) {
      const text = prompt('Paste nội dung vào đây:');
      if (text) {
        const cur = textarea.value.trim();
        textarea.value = cur ? (cur + '\n' + text) : text;
        updateEnteredCount();
      }
    }
  }

  function clearSerials() {
    if (!confirm('Bạn có chắc muốn xóa tất cả serial?')) return;
    const textarea = $('serial-textarea');
    if (textarea) textarea.value = '';
    updateEnteredCount();
    hideSerialErrors();
  }

  function saveSerials() {
    const textarea = $('serial-textarea');
    if (!textarea || !currentWarehouseId) return;

    let serials = textarea.value
      .split('\n')
      .map(s => s.trim())
      .filter(s => s.length > 0);

    const errors = validateSerials(serials);
    if (errors.length > 0) {
      showSerialErrors(errors);
      return;
    }

    serials = serials.map(s => s.toUpperCase());

    const hiddenInput = $('serials-' + currentWarehouseId);
    if (hiddenInput) hiddenInput.value = JSON.stringify(serials);

    const qtyInput = document.querySelector(`.js-warehouse-qty[data-warehouse-id="${currentWarehouseId}"]`);
    if (qtyInput) qtyInput.value = serials.length;

    const countBadge = $('serial-count-' + currentWarehouseId);
    if (countBadge) {
      countBadge.textContent = serials.length;
      const btn = countBadge.closest('button');
      if (btn) {
        btn.classList.remove('btn-outline-primary', 'btn-outline-success');
        btn.classList.add(serials.length > 0 ? 'btn-outline-success' : 'btn-outline-primary');
      }
    }

    recalcTotalQty();

    const modal = bootstrap.Modal.getInstance($('serialModal'));
    if (modal) modal.hide();

    showToast(`Đã lưu ${serials.length} serial cho kho này`, 'success');
  }

  function setSerialUIEnabled(enabled) {
    // show/hide serial column
    document.querySelectorAll('th.serial-column, td.serial-column').forEach(el => {
      el.style.display = enabled ? '' : 'none';
    });

    // qty behavior
    document.querySelectorAll('.js-warehouse-qty').forEach(el => {
      if (enabled) {
        el.setAttribute('readonly', 'readonly');
        el.classList.add('bg-light');
        el.title = 'Số lượng tự động tính từ số serial';
      } else {
        el.removeAttribute('readonly');
        el.classList.remove('bg-light');
        el.title = '';
      }
    });
  }

  function handleSerializedToggle() {
    const checkbox = $('is_serialized');
    if (!checkbox) {
      // nếu không có checkbox thì coi như không serialized
      setSerialUIEnabled(false);
      return;
    }

    const enabled = checkbox.checked;
    setSerialUIEnabled(enabled);

    // Nếu tắt serialized: không bắt buộc xóa serials, nhưng thường nên “disable” nút nhập
    document.querySelectorAll('.js-open-serial').forEach(btn => {
      btn.disabled = !enabled;
      btn.classList.toggle('disabled', !enabled);
    });
  }

  // expose for inline onclick (nếu cần)
  window.openSerialModal = openSerialModal;
  window.saveSerials = saveSerials;
  window.clearSerials = clearSerials;
  window.pasteFromClipboard = pasteFromClipboard;

  document.addEventListener('DOMContentLoaded', function () {
    // init UI state
    const initial = (window.__PRODUCT_FORM__ && window.__PRODUCT_FORM__.isSerialized) ? true : false;
    setSerialUIEnabled(initial);
    document.querySelectorAll('.js-open-serial').forEach(btn => {
      btn.disabled = !initial;
      btn.classList.toggle('disabled', !initial);
    });

    // bind events
    document.addEventListener('input', function (e) {
      if (e.target && e.target.classList && e.target.classList.contains('js-warehouse-qty')) {
        recalcTotalQty();
      }
    });

    const checkbox = $('is_serialized');
    if (checkbox) checkbox.addEventListener('change', handleSerializedToggle);

    // open modal buttons
    document.querySelectorAll('.js-open-serial').forEach(btn => {
      btn.addEventListener('click', function () {
        const wid = this.getAttribute('data-warehouse-id');
        const wname = this.getAttribute('data-warehouse-name') || '';
        openSerialModal(parseInt(wid, 10), wname);
      });
    });

    // modal buttons
    const btnPaste = $('btn-serial-paste');
    if (btnPaste) btnPaste.addEventListener('click', pasteFromClipboard);

    const btnClear = $('btn-serial-clear');
    if (btnClear) btnClear.addEventListener('click', clearSerials);

    const btnSave = $('btn-serial-save');
    if (btnSave) btnSave.addEventListener('click', saveSerials);

    const serialTextarea = $('serial-textarea');
    if (serialTextarea) {
      serialTextarea.addEventListener('input', function () {
        updateEnteredCount();
        autoUppercase(this);
      });
      serialTextarea.addEventListener('paste', function () {
        setTimeout(updateEnteredCount, 100);
      });
    }

    // initial total
    recalcTotalQty();
  });

})();
