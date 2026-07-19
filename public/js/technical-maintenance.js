(function () {
    'use strict';

    const config = window.SMX || {};
    const editDrawerEl = document.getElementById('smxEditDrawer');
    const detailDrawerEl = document.getElementById('smxDetailDrawer');
    const editDrawer = editDrawerEl ? bootstrap.Offcanvas.getOrCreateInstance(editDrawerEl) : null;
    const detailDrawer = detailDrawerEl ? bootstrap.Offcanvas.getOrCreateInstance(detailDrawerEl) : null;
    const statusModalEl = document.getElementById('smxStatusModal');
    const statusModal = statusModalEl ? bootstrap.Modal.getOrCreateInstance(statusModalEl) : null;
    let createAssigneeSelect = null;
    let editAssigneeSelect = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(value) {
        if (!value) return 'Chưa cập nhật';
        const parts = String(value).substring(0, 10).split('-');
        return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
    }

    function setView(name) {
        document.querySelectorAll('[data-smx-view-panel]').forEach(function (panel) {
            panel.classList.toggle('active', panel.dataset.smxViewPanel === name);
        });

        document.querySelectorAll('[data-smx-view]').forEach(function (button) {
            button.classList.toggle('active', button.dataset.smxView === name);
        });

        localStorage.setItem('smx-maintenance-view', name);
        const target = document.querySelector('[data-smx-view-panel="' + name + '"]');
        if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function initViews() {
        document.querySelectorAll('[data-smx-view]').forEach(function (button) {
            button.addEventListener('click', function () {
                setView(button.dataset.smxView || 'list');
            });
        });

        const saved = localStorage.getItem('smx-maintenance-view');
        if (saved && document.querySelector('[data-smx-view-panel="' + saved + '"]')) {
            setView(saved);
        }
    }

    function populateSiteFields(item) {
        if (!item) return;
        const map = {
            smxCustomerName: item.contact_name || item.dataset?.customer || '',
            smxSiteName: item.name || item.dataset?.name || '',
            smxAddress: item.address || item.dataset?.address || '',
            smxSystemKwp: item.system_kwp || item.dataset?.kwp || '',
        };

        Object.keys(map).forEach(function (id) {
            const input = document.getElementById(id);
            if (input) input.value = map[id] == null ? '' : map[id];
        });
    }

    function initSiteSearch() {
        const select = document.getElementById('smxSiteSelect');
        if (!select || typeof TomSelect === 'undefined') return;

        const searchUrl = select.dataset.searchUrl;
        const instance = new TomSelect(select, {
            valueField: 'value',
            labelField: 'text',
            searchField: ['text', 'name', 'contact_name', 'contact_phone', 'address'],
            maxOptions: 30,
            allowEmptyOption: true,
            loadThrottle: 300,
            load: function (query, callback) {
                if (!query || query.length < 2) return callback();
                fetch(searchUrl + '?q=' + encodeURIComponent(query), {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (response) {
                        if (!response.ok) throw new Error('Không tải được công trình');
                        return response.json();
                    })
                    .then(function (json) { callback(json.items || []); })
                    .catch(function () { callback(); });
            },
            onItemAdd: function (value) {
                const item = this.options[value] || null;
                populateSiteFields(item);
            },
        });

        instance.on('change', function (value) {
            if (!value) return;
            populateSiteFields(instance.options[value] || null);
        });
    }

    function initMultiSelects() {
        if (typeof TomSelect === 'undefined') return;

        const create = document.getElementById('smxCreateAssignees');
        if (create) {
            createAssigneeSelect = new TomSelect(create, {
                plugins: ['remove_button'],
                maxItems: 12,
                placeholder: 'Chọn kỹ thuật viên...',
            });
        }

        const edit = document.querySelector('[data-edit-field="assigned_user_ids"]');
        if (edit) {
            editAssigneeSelect = new TomSelect(edit, {
                plugins: ['remove_button'],
                maxItems: 12,
                placeholder: 'Chọn kỹ thuật viên...',
            });
        }
    }

    function renderRoundsPreview() {
        const countEl = document.getElementById('smxRoundsCount');
        const intervalEl = document.getElementById('smxRoundInterval');
        const dateEl = document.getElementById('smxBaseDate');
        const wrap = document.getElementById('smxRoundsPreview');
        if (!countEl || !intervalEl || !dateEl || !wrap) return;

        const count = Math.max(1, Math.min(24, parseInt(countEl.value || '1', 10)));
        const interval = Math.max(1, parseInt(intervalEl.value || '3', 10));
        const base = dateEl.value ? new Date(dateEl.value + 'T12:00:00') : new Date();
        let html = '';

        for (let i = 0; i < count; i += 1) {
            const date = new Date(base.getTime());
            date.setMonth(date.getMonth() + (i * interval));
            html += '<div class="smx-round-preview-row"><strong>Đợt ' + (i + 1) + '/' + count + '</strong><span>'
                + date.toLocaleDateString('vi-VN') + '</span></div>';
        }

        wrap.innerHTML = html;
    }

    function fillEditForm(data, updateUrl) {
        const form = document.getElementById('smxEditForm');
        const loading = document.getElementById('smxEditLoading');
        const content = document.getElementById('smxEditContent');
        const title = document.getElementById('smxEditTitle');
        if (!form || !content || !loading) return;

        form.action = updateUrl;
        title.textContent = data.schedule_code || ('Lịch #' + data.id);

        document.querySelectorAll('[data-edit-field]').forEach(function (field) {
            const key = field.dataset.editField;
            if (key === 'assigned_user_ids') return;
            field.value = data[key] == null ? '' : data[key];
        });

        if (editAssigneeSelect) {
            editAssigneeSelect.clear(true);
            editAssigneeSelect.setValue((data.assigned_user_ids || []).map(String), true);
        }

        loading.hidden = true;
        content.hidden = false;
    }

    function openEdit(button) {
        const url = button.dataset.smxEditUrl;
        const updateUrl = button.dataset.smxUpdateUrl;
        const loading = document.getElementById('smxEditLoading');
        const content = document.getElementById('smxEditContent');
        if (loading) loading.hidden = false;
        if (content) content.hidden = true;
        editDrawer.show();

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('Không tải được dữ liệu');
                return response.json();
            })
            .then(function (data) { fillEditForm(data, updateUrl); })
            .catch(function (error) {
                if (loading) loading.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> ' + escapeHtml(error.message) + '</span>';
            });
    }

    function detailHtml(data) {
        const histories = Array.isArray(data.history) ? data.history : [];
        const historyHtml = histories.length
            ? histories.map(function (item) {
                const from = item.from_status ? (config.statusLabels[item.from_status] || item.from_status) : 'Khởi tạo';
                const to = config.statusLabels[item.to_status] || item.to_status;
                return '<div class="smx-history-item"><strong>' + escapeHtml(from) + ' → ' + escapeHtml(to) + '</strong>'
                    + '<small>' + escapeHtml(item.changed_at || '') + ' · ' + escapeHtml(item.changed_by || 'Hệ thống') + '</small>'
                    + ((item.reason || item.note) ? '<p>' + escapeHtml(item.reason || item.note) + '</p>' : '') + '</div>';
            }).join('')
            : '<div class="text-muted small">Chưa có lịch sử.</div>';

        return '<div class="smx-detail-grid">'
            + '<div class="smx-detail-card"><small>Mã lịch</small><strong>' + escapeHtml(data.schedule_code || ('#' + data.id)) + '</strong></div>'
            + '<div class="smx-detail-card"><small>Ngày dự kiến</small><strong>' + escapeHtml(formatDate(data.scheduled_date)) + '</strong></div>'
            + '<div class="smx-detail-card full"><small>Công trình</small><strong>' + escapeHtml(data.site_name || 'Chưa cập nhật') + '</strong><p>' + escapeHtml(data.address || '') + '</p></div>'
            + '<div class="smx-detail-card"><small>Khách hàng</small><strong>' + escapeHtml(data.customer_name || 'Chưa cập nhật') + '</strong></div>'
            + '<div class="smx-detail-card"><small>Người phụ trách</small><strong>' + escapeHtml(data.assignee_names || 'Chưa phân công') + '</strong></div>'
            + '<div class="smx-detail-card"><small>Loại lịch</small><strong>' + escapeHtml(config.typeLabels[data.type] || data.type) + '</strong></div>'
            + '<div class="smx-detail-card"><small>Trạng thái</small><strong>' + escapeHtml(config.statusLabels[data.status] || data.status) + '</strong></div>'
            + '<div class="smx-detail-card"><small>Công suất</small><strong>' + escapeHtml(data.system_kwp ? data.system_kwp + ' kWp' : 'Chưa cập nhật') + '</strong></div>'
            + '<div class="smx-detail-card"><small>Inverter / thiết bị</small><strong>' + escapeHtml(data.inverter_info || 'Chưa cập nhật') + '</strong></div>'
            + '<div class="smx-detail-card full"><small>Hiện trạng / yêu cầu</small><p>' + escapeHtml(data.issue_note || 'Chưa có ghi chú') + '</p></div>'
            + '<div class="smx-detail-card full"><small>Kết quả xử lý</small><p>' + escapeHtml(data.result_note || 'Chưa có kết quả') + '</p></div>'
            + '</div><div class="smx-history"><h3>Lịch sử trạng thái</h3>' + historyHtml + '</div>';
    }

    function openDetail(url) {
        const body = document.getElementById('smxDetailBody');
        const title = document.getElementById('smxDetailTitle');
        body.innerHTML = '<div class="smx-loading"><span class="spinner-border"></span> Đang tải hồ sơ...</div>';
        detailDrawer.show();

        fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) throw new Error('Không tải được hồ sơ');
                return response.json();
            })
            .then(function (data) {
                title.textContent = data.schedule_code || ('Lịch #' + data.id);
                body.innerHTML = detailHtml(data);
            })
            .catch(function (error) {
                body.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.message) + '</div>';
            });
    }

    function openStatus(button) {
        const form = document.getElementById('smxStatusForm');
        const select = document.getElementById('smxStatusSelect');
        const code = document.getElementById('smxStatusCode');
        form.action = button.dataset.smxStatusUrl;
        select.value = button.dataset.smxCurrentStatus || 'scheduled';
        code.textContent = button.dataset.smxCode || '';
        statusModal.show();
    }

    function initActions() {
        document.addEventListener('click', function (event) {
            const edit = event.target.closest('[data-smx-edit-url]');
            if (edit) {
                event.preventDefault();
                openEdit(edit);
                return;
            }

            const detail = event.target.closest('[data-smx-detail-url]');
            if (detail) {
                event.preventDefault();
                openDetail(detail.dataset.smxDetailUrl);
                return;
            }

            const status = event.target.closest('[data-smx-status-url]');
            if (status) {
                event.preventDefault();
                openStatus(status);
            }
        });

        document.querySelectorAll('[data-smx-delete-form]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!confirm('Chuyển lịch này vào thùng rác? Dữ liệu lịch sử vẫn được giữ lại.')) {
                    event.preventDefault();
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initViews();
        initSiteSearch();
        initMultiSelects();
        initActions();
        renderRoundsPreview();

        ['smxRoundsCount', 'smxRoundInterval', 'smxBaseDate'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', renderRoundsPreview);
        });

        if (config.oldHasErrors && config.canCreate) {
            const drawer = document.getElementById('smxCreateDrawer');
            if (drawer) bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
        }
    });
})();
