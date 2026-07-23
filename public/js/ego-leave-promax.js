(() => {
    'use strict';

    const root = document.getElementById('egoLeavePromax') || document.getElementById('egoLeaveCreatePromax');
    if (!root) return;

    const approvalModalElement = document.getElementById('leaveApprovalModal');
    const transferModalElement = document.getElementById('leaveTransferModal');
    const approvalModal = approvalModalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(approvalModalElement) : null;
    const transferModal = transferModalElement && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(transferModalElement) : null;

    document.addEventListener('click', (event) => {
        const approvalButton = event.target.closest('[data-leave-approval]');
        if (approvalButton && approvalModal) {
            const form = approvalModalElement.querySelector('form');
            const mode = approvalButton.dataset.mode;
            form.action = mode === 'approve' ? approvalButton.dataset.approveUrl : approvalButton.dataset.rejectUrl;
            approvalModalElement.querySelector('[data-approval-title]').textContent = mode === 'approve' ? 'Duyệt đơn nhân sự' : 'Từ chối đơn nhân sự';
            approvalModalElement.querySelector('[data-approval-person]').textContent = approvalButton.dataset.employee || '';
            const note = approvalModalElement.querySelector('[name="approval_note"]');
            note.required = mode === 'reject';
            note.placeholder = mode === 'reject' ? 'Nhập lý do từ chối (bắt buộc)' : 'Ghi chú duyệt (không bắt buộc)';
            const submit = approvalModalElement.querySelector('[type="submit"]');
            submit.className = `lv-btn ${mode === 'approve' ? 'lv-btn--success' : 'lv-btn--danger'}`;
            submit.innerHTML = mode === 'approve' ? '<i class="bi bi-check2-circle"></i> Xác nhận duyệt' : '<i class="bi bi-x-circle"></i> Xác nhận từ chối';
            approvalModal.show();
        }

        const transferButton = event.target.closest('[data-leave-transfer]');
        if (transferButton && transferModal) {
            const form = transferModalElement.querySelector('form');
            form.action = transferButton.dataset.transferUrl;
            transferModalElement.querySelector('[data-transfer-person]').textContent = transferButton.dataset.employee || '';
            transferModalElement.querySelector('[data-current-approver]').textContent = transferButton.dataset.approver || 'Chưa xác định';
            transferModal.show();
        }

        const confirmButton = event.target.closest('[data-confirm]');
        if (confirmButton && !window.confirm(confirmButton.dataset.confirm)) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"]');
            if (!button) return;
            button.disabled = true;
            button.dataset.oldHtml = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang xử lý...';
        });
    });

    const requestType = root.querySelector('[name="request_type"]');
    const leaveTypeWrap = root.querySelector('[data-leave-type-wrap]');
    const leaveType = root.querySelector('[name="leave_type"]');
    const startDate = root.querySelector('[name="start_date"]');
    const endDate = root.querySelector('[name="end_date"]');
    const daysPreview = root.querySelector('[data-days-preview]');
    const summaryType = root.querySelector('[data-summary-type]');
    const summaryPeriod = root.querySelector('[data-summary-period]');
    const summaryApprover = root.querySelector('[data-summary-approver]');
    const approver = root.querySelector('[name="approver_id"]');

    const typeLabels = {leave:'Nghỉ phép',wfh:'Làm online',business_trip:'Công tác',late:'Xin đi trễ',early_leave:'Xin về sớm'};

    const updateCreateSummary = () => {
        if (!requestType) return;
        const isLeave = requestType.value === 'leave';
        if (leaveTypeWrap) leaveTypeWrap.hidden = !isLeave;
        if (leaveType && !isLeave) leaveType.value = requestType.value;
        if (summaryType) summaryType.textContent = typeLabels[requestType.value] || '—';

        let days = 0;
        if (startDate?.value && endDate?.value) {
            const start = new Date(`${startDate.value}T00:00:00`);
            const end = new Date(`${endDate.value}T00:00:00`);
            if (!Number.isNaN(start.getTime()) && !Number.isNaN(end.getTime()) && end >= start) {
                days = Math.floor((end - start) / 86400000) + 1;
            }
        }
        if (daysPreview) daysPreview.textContent = `${days || 0} ngày`;
        if (summaryPeriod) summaryPeriod.textContent = startDate?.value && endDate?.value ? `${startDate.value.split('-').reverse().join('/')} → ${endDate.value.split('-').reverse().join('/')}` : 'Chưa chọn';
        if (summaryApprover) summaryApprover.textContent = approver?.selectedOptions?.[0]?.textContent?.trim() || 'Chưa chọn';
    };

    [requestType,startDate,endDate,approver].filter(Boolean).forEach((element) => element.addEventListener('change', updateCreateSummary));
    updateCreateSummary();

    const fileInput = root.querySelector('[data-proof-input]');
    const dropzone = root.querySelector('[data-proof-dropzone]');
    const preview = root.querySelector('[data-proof-preview]');
    let selectedFiles = [];

    const formatBytes = (bytes) => {
        if (!bytes) return '0 KB';
        if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    };

    const syncFiles = () => {
        if (!fileInput || !preview) return;
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        fileInput.files = transfer.files;
        preview.innerHTML = '';
        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'lv-file-item';
            item.innerHTML = `<i class="bi bi-paperclip"></i><span></span><small>${formatBytes(file.size)}</small><button type="button" aria-label="Xóa"><i class="bi bi-x"></i></button>`;
            item.querySelector('span').textContent = file.name;
            item.querySelector('button').addEventListener('click', () => {
                selectedFiles.splice(index, 1);
                syncFiles();
            });
            preview.appendChild(item);
        });
    };

    const addFiles = (files) => {
        [...files].forEach((file) => {
            if (selectedFiles.length >= 8) return;
            const duplicate = selectedFiles.some((item) => item.name === file.name && item.size === file.size);
            if (!duplicate) selectedFiles.push(file);
        });
        syncFiles();
    };

    fileInput?.addEventListener('change', () => addFiles(fileInput.files));
    ['dragenter','dragover'].forEach((name) => dropzone?.addEventListener(name, (event) => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    }));
    ['dragleave','drop'].forEach((name) => dropzone?.addEventListener(name, (event) => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
    }));
    dropzone?.addEventListener('drop', (event) => addFiles(event.dataTransfer.files));
})();
