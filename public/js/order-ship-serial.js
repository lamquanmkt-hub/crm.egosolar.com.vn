(function () {
    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.from((root || document).querySelectorAll(sel));
    }

    function esc(value) {
        return String(value || '').replace(/[&<>"']/g, function (m) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
        });
    }

    function renderEmpty(message) {
        const wrapper = qs('#ship-serials-wrapper');
        const container = qs('#ship-serials-container');

        if (!wrapper || !container) return;

        wrapper.style.display = 'block';
        container.innerHTML = '<div class="alert alert-info mb-0" style="border-radius:14px;">' + esc(message || 'Đơn này không có sản phẩm cần chọn Serial/IMEI.') + '</div>';
    }

    function renderSerials(items) {
        const wrapper = qs('#ship-serials-wrapper');
        const container = qs('#ship-serials-container');

        if (!wrapper || !container) return;

        if (!items || !items.length) {
            renderEmpty('Đơn này không có sản phẩm cần chọn Serial/IMEI.');
            return;
        }

        wrapper.style.display = 'block';

        container.innerHTML = items.map(function (item) {
            const list = item.available_serials || [];
            const need = Number(item.quantity || 0);

            const serialHtml = list.length
                ? list.map(function (serial) {
                    return `
                        <label class="ego-serial-choice" data-search="${esc((serial.code || '') + ' ' + (serial.warehouse_name || '') + ' ' + (item.product_name || ''))}">
                            <input type="checkbox"
                                   class="ego-serial-check"
                                   data-item-id="${esc(item.order_item_id)}"
                                   data-need="${need}"
                                   name="serials[${esc(item.order_item_id)}][]"
                                   value="${esc(serial.id)}">
                            <span>
                                <b>${esc(serial.code)}</b>
                                <small>${esc(serial.warehouse_name || 'Trong kho')}</small>
                            </span>
                        </label>
                    `;
                }).join('')
                : '<div class="alert alert-danger mb-0" style="border-radius:12px;">Không có serial trong kho cho sản phẩm này.</div>';

            return `
                <div class="ego-serial-card" data-item-id="${esc(item.order_item_id)}">
                    <div class="ego-serial-head">
                        <div>
                            <div class="ego-serial-title">${esc(item.product_name)}</div>
                            <div class="ego-serial-sub">SKU: ${esc(item.sku || '-')} · Cần chọn: <b>${need}</b> serial</div>
                        </div>
                        <span class="ego-serial-count" id="serial-count-${esc(item.order_item_id)}">0/${need}</span>
                    </div>
                    <div class="ego-serial-list">${serialHtml}</div>
                </div>
            `;
        }).join('');

        injectStyle();
        bindSerialChecks();
    }

    function injectStyle() {
        if (document.getElementById('ego-ship-serial-style')) return;

        const style = document.createElement('style');
        style.id = 'ego-ship-serial-style';
        style.innerHTML = `
            .ego-serial-card{border:1px solid #dbe7ef;border-radius:16px;padding:12px;margin-bottom:12px;background:#fff}
            .ego-serial-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;margin-bottom:10px}
            .ego-serial-title{font-size:13px;font-weight:900;color:#0f172a}
            .ego-serial-sub{font-size:11px;color:#64748b;font-weight:700;margin-top:2px}
            .ego-serial-count{font-size:11px;font-weight:900;color:#078c8c;background:#eef7f7;border-radius:999px;padding:5px 9px;white-space:nowrap}
            .ego-serial-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;max-height:220px;overflow:auto}
            .ego-serial-choice{display:flex;align-items:center;gap:8px;border:1px solid #dbe7ef;border-radius:12px;padding:8px 10px;cursor:pointer;background:#f8fafc}
            .ego-serial-choice:hover{border-color:#078c8c;background:#f0fdfa}
            .ego-serial-choice input{width:16px;height:16px}
            .ego-serial-choice b{display:block;font-size:12px;color:#0f172a}
            .ego-serial-choice small{display:block;font-size:10.5px;color:#64748b}
            @media(max-width:768px){.ego-serial-list{grid-template-columns:1fr}}
        `;
        document.head.appendChild(style);
    }

    function bindSerialChecks() {
        qsa('.ego-serial-check').forEach(function (input) {
            input.addEventListener('change', function () {
                const itemId = input.getAttribute('data-item-id');
                const need = Number(input.getAttribute('data-need') || 0);
                const checked = qsa('.ego-serial-check[data-item-id="' + itemId + '"]:checked');

                if (checked.length > need) {
                    input.checked = false;
                    alert('Sản phẩm này chỉ cần chọn ' + need + ' serial.');
                    return;
                }

                const count = qs('#serial-count-' + itemId);
                if (count) count.textContent = qsa('.ego-serial-check[data-item-id="' + itemId + '"]:checked').length + '/' + need;
            });
        });
    }

    function loadSerials() {
        if (!window.SHIP_SERIALS_URL) return;

        fetch(window.SHIP_SERIALS_URL, {
            headers: {'Accept': 'application/json'}
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                const data = json.serials || {};
                const items = Array.isArray(data) ? data : (data.items || []);
                renderSerials(items);
            })
            .catch(function () {
                renderEmpty('Không tải được danh sách serial. Vui lòng tải lại trang.');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const modal = qs('#stockModal');

        if (modal) {
            modal.addEventListener('shown.bs.modal', loadSerials);
        }

        const search = qs('#serialSearch');
        if (search) {
            search.addEventListener('input', function () {
                const term = search.value.trim().toLowerCase();

                qsa('.ego-serial-choice').forEach(function (el) {
                    const text = (el.getAttribute('data-search') || '').toLowerCase();
                    el.style.display = !term || text.includes(term) ? '' : 'none';
                });
            });
        }

        const form = qs('#shipOrderForm');
        if (form) {
            form.addEventListener('submit', function (event) {
                let ok = true;
                let message = '';

                qsa('.ego-serial-card').forEach(function (card) {
                    const itemId = card.getAttribute('data-item-id');
                    const first = qs('.ego-serial-check[data-item-id="' + itemId + '"]');
                    if (!first) {
                        ok = false;
                        message = 'Có sản phẩm cần serial nhưng không có serial trong kho.';
                        return;
                    }

                    const need = Number(first.getAttribute('data-need') || 0);
                    const selected = qsa('.ego-serial-check[data-item-id="' + itemId + '"]:checked').length;

                    if (selected !== need) {
                        ok = false;
                        const title = qs('.ego-serial-title', card)?.textContent || 'Sản phẩm';
                        message = title + ' cần chọn đúng ' + need + ' serial. Hiện đang chọn ' + selected + '.';
                    }
                });

                if (!ok) {
                    event.preventDefault();
                    const box = qs('#ship-serials-error');
                    if (box) {
                        box.style.display = 'block';
                        box.textContent = message;
                    } else {
                        alert(message);
                    }
                }
            });
        }
    });
})();
