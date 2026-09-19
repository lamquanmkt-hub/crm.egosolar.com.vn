(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function config() {
        return window.EgoWarrantyExchange || {};
    }

    function sourceType() {
        var checked = document.querySelector('input[name="source_type"]:checked');
        return checked ? checked.value : 'site';
    }

    function setPreview(preview, state, html) {
        if (!preview) return;
        preview.classList.remove('ok', 'bad', 'loading');
        if (state) preview.classList.add(state);
        preview.innerHTML = html;
    }

    function setOrderPreview(state, html) {
        var preview = document.getElementById('wxOrderPreview');
        if (!preview) return;
        preview.classList.remove('ok', 'bad', 'loading');
        if (state) preview.classList.add(state);
        preview.innerHTML = html;
    }

    function resetOrderSerials(message) {
        var select = document.getElementById('wxOrderSerialSelect');
        if (!select) return;
        select.innerHTML = '<option value="">' + escapeHtml(message || '\u0110\u01a1n h\u00e0ng ch\u01b0a c\u00f3 serial') + '</option>';
    }

    function updateSourceMode() {
        var mode = sourceType();
        var sitePane = document.getElementById('wxSourceSitePane');
        var orderPane = document.getElementById('wxSourceOrderPane');
        var siteSelect = document.getElementById('wxSiteSelect');
        var orderSelect = document.getElementById('wxOrderSelect');
        var orderSerialSelect = document.getElementById('wxOrderSerialSelect');
        var serialInput = document.getElementById('wxSerialInput');
        var lookupButton = document.getElementById('wxSerialLookup');

        if (sitePane) sitePane.hidden = mode !== 'site';
        if (orderPane) orderPane.hidden = mode !== 'order';
        if (siteSelect) siteSelect.required = mode === 'site';
        if (orderSelect) orderSelect.required = mode === 'order';
        if (orderSerialSelect) orderSerialSelect.required = mode === 'order';
        if (serialInput) serialInput.readOnly = mode === 'order';
        if (lookupButton) lookupButton.disabled = false;

        document.querySelectorAll('.wx-source-option').forEach(function (label) {
            var radio = label.querySelector('input[type="radio"]');
            label.classList.toggle('active', !!radio && radio.checked);
        });

        if (mode === 'order') {
            if (orderSelect && orderSelect.value) {
                loadOrderSerials();
            } else {
                resetOrderSerials('\u0110\u1ea7u ti\u00ean h\u00e3y ch\u1ecdn \u0111\u01a1n h\u00e0ng...');
                setOrderPreview('', '<i class="bi bi-receipt-cutoff"></i><div><strong>Ch\u1ecdn \u0111\u01a1n h\u00e0ng</strong><span>H\u1ec7 th\u1ed1ng s\u1ebd l\u1ea5y \u0111\u00fang c\u00e1c serial \u0111\u00e3 g\u1eafn v\u1edbi \u0111\u01a1n.</span></div>');
            }
        }
    }

    async function lookupSerial() {
        var cfg = config();
        var input = document.getElementById('wxSerialInput');
        var preview = document.getElementById('wxSerialPreview');
        if (!input || !preview || !cfg.serialInfoUrl) return;

        var code = input.value.trim();
        if (!code) {
            setPreview(preview, '', '<i class="bi bi-upc-scan"></i><div><strong>Nh\u1eadp/ch\u1ecdn serial \u0111\u1ec3 ki\u1ec3m tra b\u1ea3o h\u00e0nh</strong><span>H\u1ec7 th\u1ed1ng s\u1ebd hi\u1ec7n s\u1ea3n ph\u1ea9m, kh\u00e1ch h\u00e0ng, \u0111\u01a1n h\u00e0ng v\u00e0 h\u1ea1n b\u1ea3o h\u00e0nh.</span></div>');
            return;
        }

        setPreview(preview, 'loading', '<i class="bi bi-arrow-repeat"></i><div><strong>\u0110ang ki\u1ec3m tra serial...</strong><span>\u0110\u1ed1i chi\u1ebfu s\u1ea3n ph\u1ea9m v\u00e0 h\u1ed3 s\u01a1 b\u1ea3o h\u00e0nh.</span></div>');
        try {
            var response = await fetch(cfg.serialInfoUrl + '?code=' + encodeURIComponent(code), {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            var payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Kh\u00f4ng th\u1ec3 tra c\u1ee9u serial.');

            var serial = payload.serial || {};
            var warranty = serial.warranty_active ? 'B\u1ea3o h\u00e0nh c\u00f2n hi\u1ec7u l\u1ef1c' : 'B\u1ea3o h\u00e0nh kh\u00f4ng c\u00f2n hi\u1ec7u l\u1ef1c / ch\u01b0a \u0111\u1ee7 d\u1eef li\u1ec7u';
            var detail = [serial.customer_name, serial.order_code, serial.warranty_end_at ? 'BH \u0111\u1ebfn ' + serial.warranty_end_at : ''].filter(Boolean).join(' \u00b7 ');
            setPreview(preview, serial.warranty_active ? 'ok' : 'bad',
                '<i class="bi ' + (serial.warranty_active ? 'bi-shield-check' : 'bi-shield-exclamation') + '"></i>' +
                '<div><strong>' + escapeHtml(serial.product_name || serial.serial_code) + '</strong>' +
                '<span>' + escapeHtml(warranty) + (detail ? ' \u00b7 ' + escapeHtml(detail) : '') + '</span></div>'
            );

            var siteSelect = document.getElementById('wxSiteSelect');
            if (siteSelect && serial.warranty_site_id && !siteSelect.value) {
                var option = siteSelect.querySelector('option[value="' + String(serial.warranty_site_id).replace(/"/g, '') + '"]');
                if (option) siteSelect.value = String(serial.warranty_site_id);
            }
        } catch (error) {
            setPreview(preview, 'bad', '<i class="bi bi-exclamation-triangle-fill"></i><div><strong>Kh\u00f4ng x\u00e1c nh\u1eadn \u0111\u01b0\u1ee3c serial</strong><span>' + escapeHtml(error.message || 'Vui l\u00f2ng ki\u1ec3m tra l\u1ea1i serial.') + '</span></div>');
        }
    }

    async function loadOrderSerials() {
        var cfg = config();
        var orderSelect = document.getElementById('wxOrderSelect');
        var serialSelect = document.getElementById('wxOrderSerialSelect');
        var serialInput = document.getElementById('wxSerialInput');
        if (!orderSelect || !serialSelect || !cfg.orderSerialsUrl) return;

        var orderId = orderSelect.value;
        if (!orderId) {
            resetOrderSerials('Ch\u1ecdn \u0111\u01a1n h\u00e0ng tr\u01b0\u1edbc...');
            if (sourceType() === 'order' && serialInput) serialInput.value = '';
            return;
        }

        serialSelect.disabled = true;
        serialSelect.innerHTML = '<option value="">\u0110ang l\u1ea5y serial t\u1eeb \u0111\u01a1n h\u00e0ng...</option>';
        setOrderPreview('loading', '<i class="bi bi-arrow-repeat"></i><div><strong>\u0110ang \u0111\u1ecdc \u0111\u01a1n h\u00e0ng...</strong><span>\u0110ang l\u1ea5y danh s\u00e1ch serial \u0111\u00e3 xu\u1ea5t.</span></div>');

        try {
            var response = await fetch(cfg.orderSerialsUrl + '?order_id=' + encodeURIComponent(orderId), {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            });
            var payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'Kh\u00f4ng th\u1ec3 l\u1ea5y serial c\u1ee7a \u0111\u01a1n h\u00e0ng.');

            var serials = Array.isArray(payload.serials) ? payload.serials : [];
            serialSelect.innerHTML = '<option value="">Ch\u1ecdn serial l\u1ed7i trong \u0111\u01a1n...</option>';
            serials.forEach(function (serial) {
                var option = document.createElement('option');
                option.value = serial.serial_code || '';
                option.textContent = (serial.serial_code || '') + ' \u00b7 ' + (serial.product_name || 'S\u1ea3n ph\u1ea9m') + (serial.warranty_active ? ' \u00b7 C\u00f2n BH' : ' \u00b7 Ki\u1ec3m tra BH');
                serialSelect.appendChild(option);
            });

            var order = payload.order || {};
            setOrderPreview(serials.length ? 'ok' : 'bad',
                '<i class="bi ' + (serials.length ? 'bi-receipt-check' : 'bi-exclamation-triangle') + '"></i>' +
                '<div><strong>' + escapeHtml(order.order_code || '\u0110\u01a1n h\u00e0ng') + (order.customer_name ? ' \u00b7 ' + escapeHtml(order.customer_name) : '') + '</strong>' +
                '<span>' + (serials.length ? ('C\u00f3 ' + serials.length + ' serial \u0111\u1ec3 ch\u1ecdn.') : 'Kh\u00f4ng t\u00ecm th\u1ea5y serial \u0111\u00e3 g\u1eafn v\u1edbi \u0111\u01a1n n\u00e0y.') + '</span></div>'
            );

            var preferred = (cfg.oldSerial || (serialInput ? serialInput.value : '') || '').trim();
            if (preferred && serials.some(function (serial) { return serial.serial_code === preferred; })) {
                serialSelect.value = preferred;
            } else if (serials.length === 1) {
                serialSelect.value = serials[0].serial_code;
            }

            if (serialSelect.value && serialInput) {
                serialInput.value = serialSelect.value;
                lookupSerial();
            } else if (sourceType() === 'order' && serialInput) {
                serialInput.value = '';
            }
        } catch (error) {
            resetOrderSerials('Kh\u00f4ng l\u1ea5y \u0111\u01b0\u1ee3c serial');
            setOrderPreview('bad', '<i class="bi bi-exclamation-triangle-fill"></i><div><strong>Kh\u00f4ng l\u1ea5y \u0111\u01b0\u1ee3c serial t\u1eeb \u0111\u01a1n</strong><span>' + escapeHtml(error.message || 'Vui l\u00f2ng th\u1eed l\u1ea1i.') + '</span></div>');
        } finally {
            serialSelect.disabled = false;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var button = document.getElementById('wxSerialLookup');
        var input = document.getElementById('wxSerialInput');
        var orderSelect = document.getElementById('wxOrderSelect');
        var orderSerialSelect = document.getElementById('wxOrderSerialSelect');

        document.querySelectorAll('input[name="source_type"]').forEach(function (radio) {
            radio.addEventListener('change', updateSourceMode);
        });

        if (orderSelect) orderSelect.addEventListener('change', loadOrderSerials);
        if (orderSerialSelect) {
            orderSerialSelect.addEventListener('change', function () {
                if (!input) return;
                input.value = orderSerialSelect.value || '';
                if (input.value) lookupSerial();
            });
        }

        if (button) button.addEventListener('click', lookupSerial);
        if (input) {
            input.addEventListener('blur', function () {
                if (sourceType() === 'site') lookupSerial();
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    lookupSerial();
                }
            });
        }

        updateSourceMode();
        if (input && input.value.trim() && sourceType() === 'site') lookupSerial();

        var cfg = config();
        if (cfg.hasErrors) {
            var modal = document.getElementById('wxCreateModal');
            if (modal && window.bootstrap && window.bootstrap.Modal) {
                window.bootstrap.Modal.getOrCreateInstance(modal).show();
            }
        }
    });
})();
