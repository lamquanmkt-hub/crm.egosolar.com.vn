(function () {
    'use strict';

    const config = window.TM3 || {};
    const byId = (id) => document.getElementById(id);

    function populateSite(item) {
        if (!item) return;
        const values = {
            tm3CustomerName: item.contact_name || item.dataset?.customer || '',
            tm3SiteName: item.name || item.dataset?.name || '',
            tm3Address: item.address || item.dataset?.address || '',
            tm3SystemKwp: item.system_kwp || item.dataset?.kwp || '',
        };
        Object.entries(values).forEach(([id, value]) => {
            const el = byId(id);
            if (el) el.value = value == null ? '' : value;
        });
    }

    function initSiteSearch() {
        const select = byId('tm3SiteSelect');
        if (!select) return;
        if (typeof TomSelect === 'undefined') {
            select.addEventListener('change', () => populateSite(select.options[select.selectedIndex]));
            if (select.value) populateSite(select.options[select.selectedIndex]);
            return;
        }
        const searchUrl = select.dataset.searchUrl;
        const instance = new TomSelect(select, {
            valueField: 'value', labelField: 'text', searchField: ['text', 'name', 'contact_name', 'contact_phone', 'address'],
            maxOptions: 30, allowEmptyOption: true, loadThrottle: 300,
            load(query, callback) {
                if (!query || query.length < 2) return callback();
                fetch(searchUrl + '?q=' + encodeURIComponent(query), {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
                    .then((response) => { if (!response.ok) throw new Error(); return response.json(); })
                    .then((json) => callback(json.items || [])).catch(() => callback());
            },
            onItemAdd(value) { populateSite(this.options[value] || null); },
        });
        instance.on('change', (value) => { if (value) populateSite(instance.options[value] || null); });
        if (instance.getValue()) populateSite(instance.options[instance.getValue()] || null);
    }

    let editAssignees = null;
    function initMultiSelects() {
        if (typeof TomSelect === 'undefined') return;
        const create = byId('tm3CreateAssignees');
        if (create) new TomSelect(create, {plugins: ['remove_button'], maxItems: 12, placeholder: 'Chọn kỹ thuật viên...'});
        const edit = document.querySelector('[data-edit-field="assigned_user_ids"]');
        if (edit) editAssignees = new TomSelect(edit, {plugins: ['remove_button'], maxItems: 12, placeholder: 'Chọn kỹ thuật viên...'});
    }

    function renderRoundsPreview() {
        const countEl = byId('tm3RoundsCount'), intervalEl = byId('tm3RoundInterval'), dateEl = byId('tm3BaseDate'), wrap = byId('tm3RoundsPreview');
        if (!countEl || !intervalEl || !dateEl || !wrap) return;
        const count = Math.max(1, Math.min(24, parseInt(countEl.value || '1', 10)));
        const interval = Math.max(1, parseInt(intervalEl.value || '3', 10));
        const base = dateEl.value ? new Date(dateEl.value + 'T12:00:00') : new Date();
        let html = '';
        for (let i = 0; i < count; i += 1) {
            const date = new Date(base.getTime()); date.setMonth(date.getMonth() + (i * interval));
            html += '<div class="tm4-round-preview-row"><strong>Đợt ' + (i + 1) + '/' + count + '</strong><span>' + date.toLocaleDateString('vi-VN') + '</span></div>';
        }
        wrap.innerHTML = html;
    }

    function fillEdit(data, updateUrl) {
        const form = byId('tm3EditForm'), loading = byId('tm3EditLoading'), content = byId('tm3EditContent');
        if (!form || !content || !loading) return;
        form.action = updateUrl;
        byId('tm3EditTitle').textContent = data.schedule_code || ('Lịch #' + data.id);
        byId('tm3EditSite').textContent = data.site_name || 'Công trình chưa đặt tên';
        byId('tm3EditRound').textContent = data.round_label || 'Lịch đơn lẻ';
        byId('tm3EditCustomer').textContent = data.customer_name || 'Chưa cập nhật';
        const fullLink = byId('tm3EditFullLink'); if (fullLink) fullLink.href = data.schedule_detail_url || '#';
        document.querySelectorAll('[data-edit-field]').forEach((field) => {
            const key = field.dataset.editField; if (key === 'assigned_user_ids') return;
            field.value = data[key] == null ? '' : data[key];
        });
        if (editAssignees) { editAssignees.clear(true); editAssignees.setValue((data.assigned_user_ids || []).map(String), true); }
        loading.hidden = true; content.hidden = false;
    }

    function openEdit(button) {
        const drawerEl = byId('tm3EditDrawer'); if (!drawerEl || typeof bootstrap === 'undefined') return;
        const loading = byId('tm3EditLoading'), content = byId('tm3EditContent');
        if (loading) { loading.hidden = false; loading.textContent = 'Đang tải dữ liệu...'; }
        if (content) content.hidden = true;
        bootstrap.Offcanvas.getOrCreateInstance(drawerEl).show();
        fetch(button.dataset.tm3EditUrl, {headers: {Accept: 'application/json'}, credentials: 'same-origin'})
            .then((response) => { if (!response.ok) return response.json().catch(() => ({})).then((json) => { throw new Error(json.message || 'Không tải được dữ liệu'); }); return response.json(); })
            .then((data) => fillEdit(data, button.dataset.tm3UpdateUrl))
            .catch((error) => { if (loading) loading.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + error.message + '</span>'; });
    }

    function openScheduleStatus(button) {
        const modal = byId('tm3StatusModal'), form = byId('tm3StatusForm'), select = byId('tm3StatusSelect');
        if (!modal || !form || !select || typeof bootstrap === 'undefined') return;
        form.action = button.dataset.tm3StatusUrl;
        if ([...select.options].some((o) => o.value === button.dataset.tm3CurrentStatus)) select.value = button.dataset.tm3CurrentStatus;
        byId('tm3StatusCode').textContent = button.dataset.tm3Code || '';
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function openClaimStatus(button) {
        const modal = byId('tm4ClaimStatusModal'), form = byId('tm4ClaimStatusForm');
        if (!modal || !form || typeof bootstrap === 'undefined') return;
        form.action = button.dataset.tm4ClaimStatusUrl;
        byId('tm4ClaimStatusCode').textContent = button.dataset.tm4ClaimCode || 'Cập nhật phiếu';
        const currentStatus = button.dataset.tm4ClaimStatus || 'received';
        const select = byId('tm4ClaimStatusSelect');
        const allowed = new Set([currentStatus, ...((config.claimTransitions || {})[currentStatus] || [])]);
        [...select.options].forEach((option) => {
            let enabled = allowed.has(option.value);
            if (['approved', 'rejected'].includes(option.value) && !config.canApprove) enabled = false;
            if (['completed', 'cancelled'].includes(option.value) && !config.isManager) enabled = false;
            if (['completed', 'rejected', 'cancelled'].includes(currentStatus) && !config.isManager && option.value !== currentStatus) enabled = false;
            option.disabled = !enabled;
            option.hidden = !enabled;
        });
        select.value = currentStatus;
        const assignee = byId('tm4ClaimAssignee');
        if (assignee) assignee.value = button.dataset.tm4ClaimAssigned || '';
        byId('tm4ClaimDiagnosis').value = button.dataset.tm4ClaimDiagnosis || '';
        byId('tm4ClaimSolution').value = button.dataset.tm4ClaimSolution || '';
        byId('tm4ClaimResolution').value = button.dataset.tm4ClaimResolution || '';
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function openStockStatus(button) {
        const modal = byId('tm4StockStatusModal'), form = byId('tm4StockStatusForm');
        if (!modal || !form || typeof bootstrap === 'undefined') return;
        form.action = button.dataset.tm4StockStatusUrl;
        byId('tm4StockStatusCode').textContent = button.dataset.tm4StockCode || 'Cập nhật phiếu';
        const currentStatus = button.dataset.tm4StockStatus || 'pending';
        const select = byId('tm4StockStatusSelect');
        const allowed = new Set([currentStatus, ...((config.stockTransitions || {})[currentStatus] || [])]);
        [...select.options].forEach((option) => {
            option.disabled = !allowed.has(option.value);
            option.hidden = !allowed.has(option.value);
        });
        select.value = currentStatus;
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function initActions() {
        document.addEventListener('click', (event) => {
            const edit = event.target.closest('[data-tm3-edit-url]'); if (edit) { event.preventDefault(); openEdit(edit); return; }
            const status = event.target.closest('[data-tm3-status-url]'); if (status) { event.preventDefault(); openScheduleStatus(status); return; }
            const claim = event.target.closest('[data-tm4-claim-status-url]'); if (claim) { event.preventDefault(); openClaimStatus(claim); return; }
            const stock = event.target.closest('[data-tm4-stock-status-url]'); if (stock) { event.preventDefault(); openStockStatus(stock); return; }
            const stockClaim = event.target.closest('[data-tm4-stock-claim]');
            if (stockClaim) {
                const select = byId('tm4StockClaim'); if (select) select.value = stockClaim.dataset.tm4StockClaim;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSiteSearch(); initMultiSelects(); initActions(); renderRoundsPreview();
        ['tm3RoundsCount', 'tm3RoundInterval', 'tm3BaseDate'].forEach((id) => { const el = byId(id); if (el) el.addEventListener('change', renderRoundsPreview); });
        if (config.scheduleHasErrors && config.canCreate && typeof bootstrap !== 'undefined') {
            const drawer = byId('tm4CreateDrawer'); if (drawer) bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
        } else if (config.claimHasErrors && config.canCreateClaim && typeof bootstrap !== 'undefined') {
            const drawer = byId('tm4ClaimDrawer'); if (drawer) bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
        } else if (config.stockHasErrors && config.canManageStock && typeof bootstrap !== 'undefined') {
            const drawer = byId('tm4StockDrawer'); if (drawer) bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
        }
    });
})();
