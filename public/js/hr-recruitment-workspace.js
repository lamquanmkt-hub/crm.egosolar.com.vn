(() => {
    const requestedRow = document.getElementById(location.hash.slice(1));
    if (requestedRow?.matches('details.rec-request')) requestedRow.open = true;
    document.querySelectorAll('[data-rec-attachments]').forEach(panel => {
        const input = panel.querySelector('[data-rec-files]');
        const selected = panel.querySelector('[data-rec-selected]');
        let urls = [];
        input.addEventListener('change', () => {
            urls.forEach(url => URL.revokeObjectURL(url));
            urls = [];
            selected.replaceChildren();
            const invalid = input.files.length > 8 || [...input.files].some(file => file.size > Number(input.dataset.maxSize)) || [...input.files].reduce((sum,file) => sum + file.size,0) > Number(input.dataset.maxBatch);
            input.setCustomValidity(invalid ? 'Tệp vượt giới hạn dung lượng hoặc số lượng. Chia thành nhiều lần lưu.' : '');
            if (invalid) {
                const error = document.createElement('p'); error.className = 'rec-field-error'; error.textContent = input.validationMessage; selected.append(error);
            }
            [...input.files].forEach(file => {
                const row = document.createElement('div');
                row.className = 'rec-file-row';
                const name = document.createElement('span');
                name.textContent = `${file.name} · Chờ tải lên khi lưu`;
                row.append(name);
                if (['image/jpeg','image/png','image/webp','application/pdf'].includes(file.type)) {
                    const link = document.createElement('a');
                    const url = URL.createObjectURL(file);
                    urls.push(url);
                    link.href = url; link.target = '_blank'; link.rel = 'noopener';
                    link.className = 'rec-btn-outline'; link.textContent = 'Xem tệp đã chọn';
                    row.append(link);
                }
                selected.append(row);
            });
        });
    });
    const list = document.querySelector('[data-rec-requests], [data-rec-workflow]');
    if (!list) return;
    const search = list.querySelector('[data-rec-search]');
    const status = list.querySelector('[data-rec-status]');
    const rows = [...list.querySelectorAll('[data-rec-request], [data-rec-row]')];
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLocaleLowerCase('vi').trim();
    const filter = () => {
        const query = normalize(search?.value || '');
        let count = 0;
        rows.forEach(row => {
            const match = normalize(row.dataset.search || '').includes(query) && (!status?.value || row.dataset.status === status.value);
            row.hidden = !match;
            if (match) count++;
        });
        const counter = list.querySelector('[data-rec-count]');
        if (counter) counter.textContent = String(count);
        const empty = list.querySelector('[data-rec-empty]');
        if (empty) empty.hidden = count !== 0;
    };
    search?.addEventListener('input', filter);
    status?.addEventListener('change', filter);
})();
