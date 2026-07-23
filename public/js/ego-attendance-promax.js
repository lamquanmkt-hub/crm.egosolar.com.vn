(() => {
    'use strict';

    const root = document.getElementById('egoAttendancePromax');
    if (!root) return;

    const clock = root.querySelector('[data-live-clock]');
    const day = root.querySelector('[data-live-day]');
    const forms = root.querySelectorAll('[data-attendance-form]');
    let activeRequest = false;
    let lastForm = null;

    const updateClock = () => {
        const now = new Date();
        if (clock) clock.textContent = now.toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        if (day) day.textContent = now.toLocaleDateString('vi-VN', {weekday:'long', day:'2-digit', month:'2-digit', year:'numeric'});
    };

    updateClock();
    window.setInterval(updateClock, 1000);

    const removeHelp = () => document.getElementById('egoLocationHelp')?.remove();

    const showHelp = (message, denied = false) => {
        removeHelp();
        const box = document.createElement('div');
        box.id = 'egoLocationHelp';
        box.className = 'ego-location-help';
        box.innerHTML = `
            <div class="ego-location-head">
                <div class="ego-location-icon"><i class="bi ${denied ? 'bi-shield-lock' : 'bi-geo-alt-fill'}"></i></div>
                <div><div class="ego-location-title">Cần quyền vị trí để chấm công</div><div class="ego-location-sub">GPS chỉ được ghi nhận khi check-in hoặc check-out.</div></div>
            </div>
            <div class="ego-location-message">${message}</div>
            <div class="ego-location-actions">
                <button type="button" class="at-btn at-btn--primary" data-retry-location><i class="bi bi-arrow-clockwise"></i> Thử lại</button>
                <button type="button" class="at-btn at-btn--light" data-close-location>Đóng</button>
            </div>
            <div class="ego-location-tip"><b>Cách bật lại:</b> bấm biểu tượng ổ khóa cạnh địa chỉ website → Vị trí → Cho phép.</div>
            <div class="ego-location-permission" data-permission-status>Đang kiểm tra quyền vị trí...</div>`;
        document.body.appendChild(box);
        box.querySelector('[data-close-location]')?.addEventListener('click', removeHelp);
        box.querySelector('[data-retry-location]')?.addEventListener('click', () => {
            removeHelp();
            if (lastForm) submitWithLocation(lastForm, true);
        });
        updatePermission(box.querySelector('[data-permission-status]'));
    };

    const updatePermission = async (element) => {
        if (!element) return;
        if (!navigator.permissions?.query) {
            element.textContent = 'Trình duyệt không hỗ trợ kiểm tra quyền tự động.';
            return;
        }
        try {
            const result = await navigator.permissions.query({name:'geolocation'});
            const labels = {granted:'Đã cho phép vị trí.', prompt:'Chưa chọn quyền vị trí.', denied:'Quyền vị trí đang bị chặn.'};
            element.textContent = labels[result.state] || 'Không xác định trạng thái quyền.';
        } catch (_) {
            element.textContent = 'Hãy kiểm tra quyền vị trí trong trình duyệt.';
        }
    };

    const resetButton = (button) => {
        if (!button) return;
        button.disabled = false;
        if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    };

    const setLoading = (button) => {
        if (!button) return;
        button.dataset.originalHtml ||= button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang lấy vị trí...';
    };

    const geoErrorMessage = (error, label) => {
        if (error?.code === 1) return [`Bạn đang chặn quyền vị trí. Hãy bật lại quyền rồi thử ${label}.`, true];
        if (error?.code === 2) return ['Thiết bị chưa xác định được vị trí. Hãy bật GPS và thử lại.', false];
        if (error?.code === 3 || error?.code === 'TIMEOUT') return ['Quá thời gian lấy vị trí. Hãy thử lại hoặc kiểm tra GPS.', false];
        return ['Không thể lấy vị trí hiện tại.', false];
    };

    const submitWithLocation = (form, retry = false) => {
        if (activeRequest && !retry) return;
        const button = form.querySelector('[type="submit"]');
        const label = form.dataset.actionLabel || 'chấm công';
        activeRequest = true;
        lastForm = form;
        removeHelp();
        setLoading(button);

        if (!navigator.geolocation) {
            activeRequest = false;
            resetButton(button);
            showHelp('Trình duyệt không hỗ trợ GPS.', false);
            return;
        }

        let completed = false;
        const timer = window.setTimeout(() => {
            if (completed) return;
            completed = true;
            activeRequest = false;
            resetButton(button);
            const [message, denied] = geoErrorMessage({code:'TIMEOUT'}, label);
            showHelp(message, denied);
        }, 7000);

        navigator.geolocation.getCurrentPosition((position) => {
            if (completed) return;
            completed = true;
            window.clearTimeout(timer);
            activeRequest = false;
            const lat = form.querySelector('[name="lat"]');
            const lng = form.querySelector('[name="lng"]');
            if (lat) lat.value = position.coords.latitude;
            if (lng) lng.value = position.coords.longitude;
            form.submit();
        }, (error) => {
            if (completed) return;
            completed = true;
            window.clearTimeout(timer);
            activeRequest = false;
            resetButton(button);
            const [message, denied] = geoErrorMessage(error, label);
            showHelp(message, denied);
        }, {enableHighAccuracy:true, timeout:6500, maximumAge:0});
    };

    forms.forEach((form) => form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitWithLocation(form);
    }));
})();
