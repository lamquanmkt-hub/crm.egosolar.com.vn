(function () {
    'use strict';

    function syncMaterialLayout() {
        const grid = document.querySelector('.pt-grid');
        if (!grid) return;

        const activePanel = document.querySelector('[data-pt-panel].active');
        const fullLayout = activePanel
            && activePanel.dataset.ptPanel === 'materials'
            && activePanel.dataset.materialFullLayout === '1';

        grid.classList.toggle('is-material-full-layout', Boolean(fullLayout));
    }

    function initReviewForm(form) {
        const decision = form.querySelector('[data-material-review-decision-v4]');
        const note = form.querySelector('[data-material-review-note-v4]');
        const submit = form.querySelector('[data-material-review-submit-v4]');

        if (!decision || !submit) return;

        function render() {
            const rejecting = decision.value === 'reject';

            if (note) {
                note.required = rejecting;
                note.placeholder = rejecting
                    ? 'Nhập rõ vật tư, số lượng hoặc thông số Kỹ thuật cần điều chỉnh.'
                    : 'Có thể nhập lưu ý cho Kho; không bắt buộc.';
            }

            submit.innerHTML = rejecting
                ? '<i class="bi bi-arrow-counterclockwise"></i> Xác nhận trả Kỹ thuật'
                : '<i class="bi bi-shield-check"></i> Xác nhận phê duyệt';

            form.dataset.confirm = rejecting
                ? 'Xác nhận trả phiếu vật tư về Kỹ thuật để điều chỉnh?'
                : 'Xác nhận phê duyệt phiếu và chuyển sang Kho xử lý?';
        }

        decision.addEventListener('change', render);
        render();
    }

    function init() {
        document.querySelectorAll('[data-pt-tab]').forEach(function (button) {
            button.addEventListener('click', function () {
                window.requestAnimationFrame(syncMaterialLayout);
            });
        });

        document.querySelectorAll('[data-material-review-form-v4]').forEach(initReviewForm);

        syncMaterialLayout();
        window.setTimeout(syncMaterialLayout, 50);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
