(function () {
    'use strict';

    const config = window.TM3 || {};

    function byId(id) { return document.getElementById(id); }

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
        if (!select || typeof TomSelect === 'undefined') return;
        const searchUrl = select.dataset.searchUrl;
        const instance = new TomSelect(select, {
            valueField: 'value',
            labelField: 'text',
            searchField: ['text', 'name', 'contact_name', 'contact_phone', 'address'],
            maxOptions: 30,
            allowEmptyOption: true,
            loadThrottle: 300,
            load(query, callback) {
                if (!query || query.length < 2) return callback();
                fetch(searchUrl + '?q=' + encodeURIComponent(query), {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then((response) => {
                        if (!response.ok) throw new Error('Không tải được công trình');
                        return response.json();
                    })
                    .then((json) => callback(json.items || []))
                    .catch(() => callback());
            },
            onItemAdd(value) { populateSite(this.options[value] || null); },
        });
        instance.on('change', (value) => {
            if (value) populateSite(instance.options[value] || null);
        });
    }

    let createAssignees = null;
    let editAssignees = null;

    function initMultiSelects() {
        if (typeof TomSelect === 'undefined') return;
        const create = byId('tm3CreateAssignees');
        if (create) {
            createAssignees = new TomSelect(create, {
                plugins: ['remove_button'], maxItems: 12, placeholder: 'Chọn kỹ thuật viên...'
            });
        }
        const edit = document.querySelector('[data-edit-field="assigned_user_ids"]');
        if (edit) {
            editAssignees = new TomSelect(edit, {
                plugins: ['remove_button'], maxItems: 12, placeholder: 'Chọn kỹ thuật viên...'
            });
        }
    }

    function renderRoundsPreview() {
        const countEl = byId('tm3RoundsCount');
        const intervalEl = byId('tm3RoundInterval');
        const dateEl = byId('tm3BaseDate');
        const wrap = byId('tm3RoundsPreview');
        if (!countEl || !intervalEl || !dateEl || !wrap) return;

        const count = Math.max(1, Math.min(24, parseInt(countEl.value || '1', 10)));
        const interval = Math.max(1, parseInt(intervalEl.value || '3', 10));
        const base = dateEl.value ? new Date(dateEl.value + 'T12:00:00') : new Date();
        let html = '';
        for (let i = 0; i < count; i += 1) {
            const date = new Date(base.getTime());
            date.setMonth(date.getMonth() + (i * interval));
            html += '<div class="tm3-round-preview-row"><strong>Đợt ' + (i + 1) + '/' + count + '</strong><span>' + date.toLocaleDateString('vi-VN') + '</span></div>';
        }
        wrap.innerHTML = html;
    }

    function initTabs() {
        document.querySelectorAll('[data-tm3-tab]').forEach((button) => {
            button.addEventListener('click', () => {
                const name = button.dataset.tm3Tab;
                document.querySelectorAll('[data-tm3-tab]').forEach((item) => item.classList.toggle('active', item === button));
                document.querySelectorAll('[data-tm3-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.tm3Panel === name));
                if (history.replaceState) history.replaceState(null, '', '#' + name);
            });
        });
        const hash = location.hash.replace('#', '');
        if (hash) {
            const target = document.querySelector('[data-tm3-tab="' + CSS.escape(hash) + '"]');
            if (target) target.click();
        }
    }

    function fillEdit(data, updateUrl) {
        const form = byId('tm3EditForm');
        const loading = byId('tm3EditLoading');
        const content = byId('tm3EditContent');
        if (!form || !content || !loading) return;

        form.action = updateUrl;
        byId('tm3EditTitle').textContent = data.schedule_code || ('Lịch #' + data.id);
        byId('tm3EditSite').textContent = data.site_name || 'Công trình chưa đặt tên';
        byId('tm3EditRound').textContent = data.round_label || 'Lịch đơn lẻ';
        byId('tm3EditCustomer').textContent = data.customer_name || 'Chưa cập nhật';
        const fullLink = byId('tm3EditFullLink');
        if (fullLink) fullLink.href = data.schedule_detail_url || '#';

        document.querySelectorAll('[data-edit-field]').forEach((field) => {
            const key = field.dataset.editField;
            if (key === 'assigned_user_ids') return;
            field.value = data[key] == null ? '' : data[key];
        });

        if (editAssignees) {
            editAssignees.clear(true);
            editAssignees.setValue((data.assigned_user_ids || []).map(String), true);
        }

        loading.hidden = true;
        content.hidden = false;
    }

    function openEdit(button) {
        const drawerEl = byId('tm3EditDrawer');
        if (!drawerEl) return;
        const drawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        const loading = byId('tm3EditLoading');
        const content = byId('tm3EditContent');
        if (loading) loading.hidden = false;
        if (content) content.hidden = true;
        drawer.show();

        fetch(button.dataset.tm3EditUrl, {
            headers: { Accept: 'application/json' }, credentials: 'same-origin'
        })
            .then((response) => {
                if (!response.ok) return response.json().catch(() => ({})).then((json) => { throw new Error(json.message || 'Không tải được dữ liệu'); });
                return response.json();
            })
            .then((data) => fillEdit(data, button.dataset.tm3UpdateUrl))
            .catch((error) => {
                if (loading) loading.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + error.message + '</span>';
            });
    }

    function openStatus(button) {
        const modalEl = byId('tm3StatusModal');
        const form = byId('tm3StatusForm');
        const select = byId('tm3StatusSelect');
        if (!modalEl || !form || !select) return;
        form.action = button.dataset.tm3StatusUrl;
        select.value = button.dataset.tm3CurrentStatus || 'scheduled';
        byId('tm3StatusCode').textContent = button.dataset.tm3Code || '';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function initActions() {
        document.addEventListener('click', (event) => {
            const edit = event.target.closest('[data-tm3-edit-url]');
            if (edit) { event.preventDefault(); openEdit(edit); return; }
            const status = event.target.closest('[data-tm3-status-url]');
            if (status) { event.preventDefault(); openStatus(status); }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSiteSearch();
        initMultiSelects();
        initTabs();
        initActions();
        renderRoundsPreview();
        ['tm3RoundsCount', 'tm3RoundInterval', 'tm3BaseDate'].forEach((id) => {
            const el = byId(id); if (el) el.addEventListener('change', renderRoundsPreview);
        });
        if (config.oldHasErrors && config.canCreate) {
            const drawer = byId('tm3CreateDrawer');
            if (drawer) bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
        }
    });
})();
