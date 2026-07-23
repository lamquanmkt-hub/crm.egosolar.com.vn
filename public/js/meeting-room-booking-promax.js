(() => {
    'use strict';

    const page = document.getElementById('meetingRoomBookingPage');

    if (!page) {
        return;
    }

    const createModalElement = document.getElementById('createBookingModal');
    const editModalElement = document.getElementById('editBookingModal');
    const createForm = document.getElementById('createBookingForm');
    const editForm = document.getElementById('editBookingForm');
    const deleteForm = document.getElementById('deleteBookingForm');

    const createModal = createModalElement && window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(createModalElement)
        : null;

    const editModal = editModalElement && window.bootstrap
        ? window.bootstrap.Modal.getOrCreateInstance(editModalElement)
        : null;

    const updateTemplate = page.dataset.updateTemplate || '';
    const deleteTemplate = page.dataset.deleteTemplate || '';

    let editingTitle = 'booking này';

    const setValue = (form, name, value) => {
        const field = form?.elements?.namedItem(name);

        if (!field) {
            return;
        }

        if (field instanceof RadioNodeList) {
            field.value = value ?? '';
            return;
        }

        field.value = value ?? '';
    };

    const localDateTime = (date, hour, minute = 0) => {
        const pad = (value) => String(value).padStart(2, '0');

        return [
            date,
            `${pad(hour)}:${pad(minute)}`,
        ].join('T');
    };

    const prepareCreateModal = (date = '') => {
        if (!createForm) {
            return;
        }

        if (date) {
            setValue(createForm, 'start_at', localDateTime(date, 9));
            setValue(createForm, 'end_at', localDateTime(date, 10));
        }

        createModal?.show();
    };

    const openEditModal = (button) => {
        if (!editForm || !editModal || !button) {
            return;
        }

        const id = button.dataset.id;

        editForm.action = updateTemplate.replace('__BOOKING__', id);

        if (deleteForm) {
            deleteForm.action = deleteTemplate.replace('__BOOKING__', id);
        }

        editingTitle = button.dataset.title || 'booking này';

        setValue(editForm, 'room_name', button.dataset.room);
        setValue(editForm, 'title', button.dataset.title);
        setValue(editForm, 'organizer_name', button.dataset.organizer);
        setValue(editForm, 'department', button.dataset.department);
        setValue(editForm, 'attendees', button.dataset.attendees || '1');
        setValue(editForm, 'start_at', button.dataset.start);
        setValue(editForm, 'end_at', button.dataset.end);
        setValue(editForm, 'usage_status', button.dataset.usage || 'unused');
        setValue(editForm, 'note', button.dataset.note);

        editModal.show();
    };

    document.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-edit-booking]');

        if (editButton) {
            openEditModal(editButton);
            return;
        }

        const createButton = event.target.closest('[data-create-booking]');

        if (createButton) {
            event.preventDefault();
            prepareCreateModal(createButton.dataset.date || '');
            return;
        }

        const deleteButton = event.target.closest('[data-delete-booking]');

        if (!deleteButton || !deleteForm) {
            return;
        }

        if (window.confirm(`Xóa “${editingTitle}”? Thao tác này không thể hoàn tác.`)) {
            deleteButton.disabled = true;
            deleteForm.submit();
        }
    });

    document.querySelectorAll('[data-booking-form]').forEach((form) => {
        const startField = form.elements.namedItem('start_at');
        const endField = form.elements.namedItem('end_at');
        const footer = form.closest('.modal-content')?.querySelector('.mrb3-modal__footer');

        const validateTime = () => {
            if (!startField || !endField || !startField.value || !endField.value) {
                return true;
            }

            const start = new Date(startField.value);
            const end = new Date(endField.value);
            const valid = end > start;

            endField.setCustomValidity(
                valid ? '' : 'Giờ kết thúc phải lớn hơn giờ bắt đầu.'
            );

            return valid;
        };

        startField?.addEventListener('change', validateTime);
        endField?.addEventListener('change', validateTime);

        form.addEventListener('submit', (event) => {
            if (!validateTime() || !form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            form.classList.add('is-submitting');
            footer?.classList.add('is-submitting');

            footer?.querySelectorAll('button').forEach((button) => {
                button.disabled = true;
            });
        });
    });

    if (page.dataset.openCreate === '1') {
        window.setTimeout(() => prepareCreateModal(), 120);
    }

    const editId = page.dataset.openEdit;

    if (editId && editModal) {
        const editButton = document.querySelector(
            `[data-edit-booking][data-id="${CSS.escape(editId)}"]`
        );

        if (editButton) {
            window.setTimeout(() => openEditModal(editButton), 120);
        }
    }
})();
