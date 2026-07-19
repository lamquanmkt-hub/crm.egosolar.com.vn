@if(strtolower((string) optional(auth()->user())->email) === 'buibichthao@egosolar.vn')
<style>
    .ego-pay-admin-actions {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
        margin-left: 6px;
        margin-top: 6px;
    }

    .ego-pay-admin-actions a,
    .ego-pay-admin-actions button,
    .ego-pay-show-actions a,
    .ego-pay-show-actions button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 32px;
        border-radius: 9px;
        border: 1px solid #dbeafe;
        background: #fff;
        color: #1d4ed8;
        padding: 0 10px;
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        cursor: pointer;
    }

    .ego-pay-admin-actions button.danger,
    .ego-pay-show-actions button.danger {
        border-color: #fecdd3;
        background: #fff1f2;
        color: #be123c;
    }

    .ego-pay-show-actions {
        position: fixed;
        right: 24px;
        bottom: 24px;
        z-index: 9999;
        display: flex;
        gap: 8px;
        padding: 8px;
        border: 1px solid #dbeafe;
        border-radius: 14px;
        background: rgba(255, 255, 255, .96);
        box-shadow: 0 16px 36px rgba(15, 23, 42, .14);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

    function idFromPaymentUrl(href) {
        try {
            const url = new URL(href, window.location.origin);
            const match = url.pathname.match(/^\/payment-requests\/([0-9]+)\/?$/);
            return match ? match[1] : null;
        } catch (e) {
            return null;
        }
    }

    function makeActions(id) {
        const wrap = document.createElement('span');
        wrap.className = 'ego-pay-admin-actions';
        wrap.dataset.egoPayActions = id;

        const edit = document.createElement('a');
        edit.href = '/payment-requests/' + id + '/edit';
        edit.textContent = 'Sửa';
        edit.title = 'Sửa phiếu';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/payment-requests/' + id + '/force-delete-by-thao';
        form.style.display = 'inline';
        form.style.margin = '0';
        form.onsubmit = function () {
            return confirm('Xóa phiếu ĐNTT #' + id + '? Liên kết công nợ sẽ quay lại trạng thái chưa lập ĐNTT.');
        };

        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<button class="danger" type="submit">Xóa</button>';

        wrap.appendChild(edit);
        wrap.appendChild(form);

        return wrap;
    }

    document.querySelectorAll('a[href*="/payment-requests/"]').forEach(function (link) {
        const id = idFromPaymentUrl(link.href);
        if (!id) return;

        const row = link.closest('tr');
        if (!row) return;

        const targetCell = row.querySelector('td:last-child') || link.closest('td');
        if (!targetCell || targetCell.querySelector('[data-ego-pay-actions="' + id + '"]')) return;

        targetCell.appendChild(makeActions(id));
    });

    const showMatch = window.location.pathname.match(/^\/payment-requests\/([0-9]+)\/?$/);
    if (showMatch && !document.querySelector('[data-ego-pay-show-actions]')) {
        const id = showMatch[1];
        const box = document.createElement('div');
        box.className = 'ego-pay-show-actions';
        box.dataset.egoPayShowActions = id;

        const edit = document.createElement('a');
        edit.href = '/payment-requests/' + id + '/edit';
        edit.textContent = 'Sửa phiếu';

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/payment-requests/' + id + '/force-delete-by-thao';
        form.style.margin = '0';
        form.onsubmit = function () {
            return confirm('Xóa phiếu ĐNTT #' + id + '? Liên kết công nợ sẽ quay lại trạng thái chưa lập ĐNTT.');
        };
        form.innerHTML =
            '<input type="hidden" name="_token" value="' + csrf + '">' +
            '<button class="danger" type="submit">Xóa phiếu</button>';

        box.appendChild(edit);
        box.appendChild(form);
        document.body.appendChild(box);
    }
});
</script>
@endif
