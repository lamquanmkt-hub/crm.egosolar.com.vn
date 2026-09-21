/* Bảo hành & Sửa chữa v2 — mọi thao tác nhập liệu bằng POPUP (AJAX). Không phụ thuộc thư viện ngoài ngoài Bootstrap. */
(function () {
  'use strict';
  var WX = window.WX || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
  var money = function (n) { return (Math.round(n) || 0).toLocaleString('vi-VN') + ' đ'; };

  function modalOf(id) { return document.getElementById(id); }
  function openModal(id) {
    var el = modalOf(id);
    if (!el || !window.bootstrap) return;
    window.bootstrap.Modal.getOrCreateInstance(el).show();
  }

  function showErrors(form, json, status) {
    var box = $('.wx-form-errors', form);
    var msgs = [];
    if (json && json.errors) {
      Object.keys(json.errors).forEach(function (k) {
        [].concat(json.errors[k]).forEach(function (m) { msgs.push(m); });
        var f = form.querySelector('[name="' + k + '"], [name="' + k + '[]"]');
        if (f) f.classList.add('is-invalid');
      });
    } else if (json && json.message) {
      msgs.push(json.message);
    }
    if (!msgs.length) msgs.push(status === 419 ? 'Phiên làm việc đã hết hạn, vui lòng tải lại trang.' : status === 403 ? 'Bạn không có quyền thực hiện thao tác này.' : 'Không thực hiện được (mã lỗi ' + status + ').');
    if (!box) { window.alert(msgs.join(String.fromCharCode(10))); return; }
    if (box) {
      box.innerHTML = '<strong>Không thực hiện được</strong><ul>' + msgs.map(function (m) { return '<li>' + esc(m) + '</li>'; }).join('') + '</ul>';
      box.hidden = false;
      var body = form.closest('.modal-body') || $('.modal-body', form);
      if (body) body.scrollTop = 0;
    }
  }

  function submitForm(form) {
    var btns = $$('[type=submit]', form);
    var box = $('.wx-form-errors', form);
    if (box) { box.hidden = true; box.innerHTML = ''; }
    $$('.is-invalid', form).forEach(function (e) { e.classList.remove('is-invalid'); });
    btns.forEach(function (b) { b.disabled = true; b.dataset.label = b.innerHTML; b.innerHTML = 'Đang lưu…'; });
    var chain = form.dataset.wxChain;
    var fd = new FormData(form);
    return fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { r: r, j: j }; }); })
      .then(function (x) {
        if (x.r.ok && x.j.ok !== false) {
          if (chain) {
            var f2 = new FormData(); f2.append('_token', fd.get('_token'));
            return fetch(chain, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: f2, credentials: 'same-origin' })
              .then(function (r2) { return r2.json().catch(function () { return {}; }).then(function (j2) { return { r: r2, j: j2 }; }); })
              .then(function (y) { if (y.r.ok && y.j.ok !== false) { window.location.reload(); } else { showErrors(form, y.j, y.r.status); } });
          }
          if (x.j.redirect) { window.location.href = x.j.redirect; } else { window.location.reload(); }
          return null;
        }
        showErrors(form, x.j, x.r.status);
        return null;
      })
      .catch(function () { showErrors(form, { message: 'Mất kết nối máy chủ. Vui lòng thử lại.' }, 0); })
      .then(function () { btns.forEach(function (b) { b.disabled = false; if (b.dataset.label) b.innerHTML = b.dataset.label; }); });
  }

  /* ------------------------------------------------------------ Đổi hàng: tra SERIAL */
  function initExchangeCreate() {
    var input = $('#wxSerialInput'); var btn = $('#wxSerialCheck'); var out = $('#wxSerialResult'); var details = $('#wxCreateDetails');
    if (!input || !btn) return;
    var submit = $('#wxCreateSubmit');
    function reset() { if (out) out.innerHTML = ''; if (details) details.hidden = true; if (submit) submit.disabled = true; var ex = $('#wxExceptionBox'); if (ex) ex.hidden = true; }
    function row(k, v) { return '<div><dt>' + k + '</dt><dd>' + (v ? esc(v) : '—') + '</dd></div>'; }
    function render(i) {
      var h = '<div class="wx2-card wx-readonly"><h2>THÔNG TIN THIẾT BỊ (tự động tra cứu)</h2><dl class="wx2-grid">' +
        row('Sản phẩm', i.product_name) + row('Model / Mã SP', i.sku) + row('Serial', i.serial_code) +
        row('Khách hàng', i.customer_name) + row('SĐT', i.customer_phone) + row('Đơn hàng gốc', i.order_code ? i.order_code + (i.order_date ? ' (' + String(i.order_date).substr(0, 10) + ')' : '') : '') +
        row('Ngày bán', i.sold_at) + row('Công trình', i.site_name ? (i.site_code ? i.site_code + ' · ' : '') + i.site_name : '') + row('Địa chỉ công trình', i.site_address) +
        row('Bảo hành từ', i.warranty_start_at) + row('Bảo hành đến', i.warranty_end_at) + row('Tình trạng bảo hành', i.warranty_label) +
        row('Trạng thái serial', i.state) + row('Kho', i.warehouse_name) + '</dl>';
      if (i.replaced_by) h += '<div class="wx2-warn">Serial này đã từng được thay bằng <b>' + esc(i.replaced_by.serial) + '</b> (' + esc(i.replaced_by.date) + ').</div>';
      if (i.replaces) h += '<div class="wx2-info">Serial này là hàng thay thế cho <b>' + esc(i.replaces.serial) + '</b> (' + esc(i.replaces.date) + ').</div>';
      if (i.history && i.history.length) {
        h += '<h3>Lịch sử đổi / bảo hành / sửa chữa</h3><ul class="wx2-hist">' + i.history.map(function (x) { return '<li>' + esc(x.code || '#' + x.id) + ' · ' + esc(x.type_label) + ' · ' + esc(x.status) + ' · ' + esc(x.date) + '</li>'; }).join('') + '</ul>';
      }
      h += '</div>';
      if (i.open_claim) {
        h += '<div class="wx2-warn"><b>⚠ Serial này đang có phiếu xử lý ' + esc(i.open_claim.code) + '</b> (' + esc(i.open_claim.status) + '). Không thể tạo phiếu trùng.</div>';
      } else if (!i.warranty_active) {
        h += '<div class="wx2-warn"><b>⚠ THIẾT BỊ KHÔNG ĐỦ ĐIỀU KIỆN ĐỔI BẢO HÀNH</b> — ' + esc(i.warranty_label) + '.<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">' +
          '<a class="wx-btn secondary small" href="' + esc(WX.repairIntakeUrl) + '?intake_serial=' + encodeURIComponent(i.serial_code) + '">CHUYỂN SANG SỬA CHỮA TÍNH PHÍ</a>' +
          (WX.canException ? '<span style="align-self:center;font-size:12px">hoặc đề nghị ngoại lệ bảo hành ở bên dưới</span>' : '') + '</div></div>';
      }
      out.innerHTML = h;
      if (!i.open_claim) {
        details.hidden = false;
        var ex = $('#wxExceptionBox');
        var exChk = $('#wxExceptionChk');
        if (ex) ex.hidden = i.warranty_active || !WX.canException;
        if (exChk) { exChk.checked = !i.warranty_active && WX.canException; exChk.dispatchEvent(new Event('change')); }
        if (submit) submit.disabled = !i.warranty_active && !WX.canException;
      }
    }
    function check() {
      var code = input.value.trim(); reset();
      if (!code) { out.innerHTML = '<div class="wx2-warn">Nhập hoặc quét serial thiết bị.</div>'; return; }
      out.innerHTML = '<div class="wx2-info">Đang tra cứu…</div>';
      fetch(WX.serialInfoUrl + '?code=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json().then(function (j) { return { r: r, j: j }; }); })
        .then(function (x) { if (x.r.ok && x.j.ok) { render(x.j.info); } else { out.innerHTML = '<div class="wx2-warn">' + esc(x.j.message || 'Không tra cứu được serial.') + '</div>'; } })
        .catch(function () { out.innerHTML = '<div class="wx2-warn">Mất kết nối máy chủ.</div>'; });
    }
    btn.addEventListener('click', check);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); check(); } });
    input.addEventListener('input', reset);
    var chk = $('#wxExceptionChk'); var wrap = $('#wxExceptionReasonWrap');
    if (chk && wrap) chk.addEventListener('change', function () { wrap.hidden = !chk.checked; });
    reset();
  }

  /* ------------------------------------------------------------ Sửa chữa: tiếp nhận độc lập */
  function initRepairIntake() {
    var form = $('#rpIntakeModal form'); if (!form) return;
    var serial = $('#rpSerial'); var ref = $('#rpRefBox'); var scope = $('#rpScopeWrap'); var sug = $('#rpCustSuggest');
    function set(name, val, force) { var el = form.elements[name]; if (el && (force || !el.value)) el.value = val || ''; }
    function useCustomer(c) {
      set('customer_id', c.id, true); set('customer_name', c.name, true); set('customer_phone', c.phone, true);
      set('customer_email', c.email, true); set('customer_address', c.address, true); set('customer_company', c.company, true);
      var tag = $('#rpCustTag'); if (tag) { tag.hidden = false; tag.innerHTML = 'Đang dùng khách CRM: <b>' + esc(c.name) + '</b> <a href="#" id="rpCustClear">bỏ chọn</a>'; }
      if (sug) sug.innerHTML = '';
    }
    form.addEventListener('click', function (e) {
      var a = e.target.closest('[data-cust]'); if (a) { e.preventDefault(); useCustomer(JSON.parse(a.getAttribute('data-cust'))); }
      if (e.target.id === 'rpCustClear') { e.preventDefault(); form.elements.customer_id.value = ''; $('#rpCustTag').hidden = true; }
    });
    function searchCust(q) {
      if (!sug || q.length < 2) { if (sug) sug.innerHTML = ''; return; }
      fetch(WX.customersUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          sug.innerHTML = (j.items || []).length ? '<div class="wx2-info">Khách hàng đã có trong CRM — bấm để chọn:<div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:6px">' + j.items.map(function (c) {
            return '<a href="#" class="wx-btn tiny secondary" data-cust=\'' + esc(JSON.stringify(c)).replace(/&quot;/g, '&quot;') + '\'>' + esc(c.name) + (c.phone ? ' · ' + esc(c.phone) : '') + '</a>';
          }).join('') + '</div></div>' : '';
          // esc() đã mã hóa dấu nháy; giải mã lại khi đọc thuộc tính
          $$('[data-cust]', sug).forEach(function (a) { a.setAttribute('data-cust', a.getAttribute('data-cust').replace(/&quot;/g, '"')); });
        }).catch(function () {});
    }
    var t; [form.elements.customer_name, form.elements.customer_phone].forEach(function (el) {
      if (!el) return;
      el.addEventListener('input', function () { if (form.elements.customer_id.value) return; clearTimeout(t); var v = el.value.trim(); t = setTimeout(function () { searchCust(v); }, 300); });
    });
    function lookup() {
      var code = serial.value.trim(); if (!code) { ref.innerHTML = ''; ref.hidden = true; if (scope) scope.hidden = true; return; }
      fetch(WX.lookupUrl + '?code=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j.found) { ref.hidden = false; ref.innerHTML = '<div class="wx2-info">Serial chưa có trong CRM — <b>vẫn tiếp nhận sửa chữa bình thường</b> (thiết bị ngoài hệ thống).</div>'; if (scope) scope.hidden = true; return; }
          var i = j.info;
          set('device_type', i.product_name); set('device_model', i.sku || i.product_name);
          if (i.customer_id && !form.elements.customer_id.value && !form.elements.customer_name.value) useCustomer({ id: i.customer_id, name: i.customer_name, phone: i.customer_phone, email: '', address: '', company: '' });
          var h = '<div class="wx2-card wx-readonly"><h2>THÔNG TIN THAM CHIẾU TRONG HỆ THỐNG</h2><dl class="wx2-grid">' +
            '<div><dt>Sản phẩm</dt><dd>' + esc(i.product_name) + '</dd></div><div><dt>Bảo hành</dt><dd>' + esc(i.warranty_label) + (i.warranty_end_at ? ' (đến ' + esc(i.warranty_end_at) + ')' : '') + '</dd></div>' +
            (i.order_code ? '<div><dt>Thiết bị từng được bán</dt><dd>Đơn ' + esc(i.order_code) + '</dd></div>' : '') +
            (i.site_name ? '<div><dt>Từng lắp tại</dt><dd>' + esc(i.site_name) + '</dd></div>' : '') + '</dl>';
          if (i.history && i.history.length) h += '<ul class="wx2-hist">' + i.history.slice(0, 5).map(function (x) { return '<li>' + esc(x.code || '#' + x.id) + ' · ' + esc(x.type_label) + ' · ' + esc(x.status) + '</li>'; }).join('') + '</ul>';
          h += '</div>';
          if (i.open_claim) h += '<div class="wx2-warn"><b>⚠ Serial này đang có phiếu xử lý ' + esc(i.open_claim.code) + '</b> — không tạo phiếu trùng.</div>';
          ref.hidden = false; ref.innerHTML = h;
          if (scope) scope.hidden = !i.warranty_active;
          if (i.warranty_active) ref.innerHTML += '<div class="wx2-warn">Thiết bị còn bảo hành: nhập lý do lỗi KHÔNG thuộc phạm vi bảo hành để tính phí.</div>';
        }).catch(function () {});
    }
    serial.addEventListener('blur', lookup);
    serial.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); lookup(); } });
    if (WX.intakeSerial) { serial.value = WX.intakeSerial; openModal('rpIntakeModal'); lookup(); }
  }

  /* ------------------------------------------------------------ Báo giá: dòng linh kiện + tổng tạm tính */
  function initQuote() {
    var tbody = $('#qItems'); if (!tbody) return;
    var form = tbody.closest('form');
    function recalc() {
      var parts = 0;
      $$('tr', tbody).forEach(function (tr) {
        var q = parseFloat((tr.querySelector('.q-qty') || {}).value) || 0; var p = parseFloat((tr.querySelector('.q-price') || {}).value) || 0;
        var line = q * p; parts += line; var c = tr.querySelector('.q-line'); if (c) c.textContent = money(line);
      });
      function v(n) { var el = form.elements[n]; return parseFloat(el && el.value) || 0; }
      var total = parts + v('labor_amount') + v('onsite_amount') + v('shipping_amount') + v('extra_amount') - v('discount_amount');
      var pt = $('#qParts'); if (pt) pt.textContent = money(parts);
      var tt = $('#qTotal'); if (tt) tt.textContent = money(total);
    }
    form.addEventListener('input', recalc);
    var add = $('#qAddRow');
    if (add) add.addEventListener('click', function () {
      var idx = $$('tr', tbody).length; var tpl = $('#qRowTpl').innerHTML.replace(/__IDX__/g, idx);
      tbody.insertAdjacentHTML('beforeend', tpl); recalc();
    });
    tbody.addEventListener('change', function (e) {
      if (e.target.classList.contains('q-prod')) { var tr = e.target.closest('tr'); var o = e.target.options[e.target.selectedIndex]; if (e.target.value) tr.querySelector('.q-name').value = o.text; }
    });
    recalc();
  }

  document.addEventListener('DOMContentLoaded', function () {
    // đưa modal ra <body> để không bị menu/topbar che (stacking context)
    $$('.wx-modal-v2').forEach(function (m) { document.body.appendChild(m); });
    document.addEventListener('click', function (e) {
      var cb = e.target.closest('[data-wx-chain-btn]');
      if (cb) { e.preventDefault(); var cf = cb.closest('form'); cf.dataset.wxChain = cb.getAttribute('data-wx-chain-btn'); submitForm(cf).then(function () { delete cf.dataset.wxChain; }); return; }
      var b = e.target.closest('[data-wx-open]'); if (!b) return; e.preventDefault(); openModal(b.getAttribute('data-wx-open'));
    });
    document.addEventListener('submit', function (e) {
      var f = e.target.closest('form.wx-ajax'); if (!f) return; e.preventDefault(); if (f.dataset.confirm && !window.confirm(f.dataset.confirm)) return; delete f.dataset.wxChain; submitForm(f);
    });
    initExchangeCreate(); initRepairIntake(); initQuote();
    // giữ popup mở nếu server trả về lỗi theo kiểu cũ (non-AJAX)
    var reopen = document.body.getAttribute('data-wx-reopen'); if (reopen) openModal(reopen);
  });
})();
