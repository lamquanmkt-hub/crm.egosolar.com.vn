(function () {
    'use strict';

    var root = document.getElementById('egoAiSettings');
    if (!root) return;

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var modal = document.getElementById('egoAiProviderModal');
    var toast = document.getElementById('egoAiTestToast');

    function openModal() {
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-open-add-provider]').forEach(function (button) {
        button.addEventListener('click', openModal);
    });

    document.querySelectorAll('[data-close-provider]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.querySelectorAll('[data-toggle-provider]').forEach(function (button) {
        button.addEventListener('click', function () {
            button.closest('[data-provider-card]').classList.toggle('is-open');
        });
    });

    document.querySelectorAll('[data-toggle-secret]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = button.parentElement.querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
            button.innerHTML = '<i class="bi ' + (input.type === 'password' ? 'bi-eye' : 'bi-eye-slash') + '"></i>';
        });
    });

    function showToast(state, title, detail) {
        toast.className = 'ego-ai-test-toast is-show' + (state ? ' is-' + state : '');
        toast.querySelector('i').className = 'bi ' + (state === 'success' ? 'bi-check-circle' : state === 'error' ? 'bi-x-circle' : 'bi-arrow-repeat');
        toast.querySelector('strong').textContent = title;
        toast.querySelector('span').textContent = detail || '';
        if (state) window.setTimeout(function () { toast.classList.remove('is-show'); }, 6000);
    }

    document.querySelectorAll('[data-test-url]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (button.disabled) return;
            button.disabled = true;
            showToast('', 'Đang kiểm tra kết nối...', 'Server đang gọi API đã lưu.');

            fetch(button.getAttribute('data-test-url'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf ? csrf.content : ''
                }
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (!response.ok || data.ok === false) throw new Error(data.message || 'Kiểm tra thất bại.');
                    return data;
                });
            }).then(function (data) {
                showToast('success', 'Kết nối thành công', (data.model || '') + ' · ' + (data.latency_ms || 0) + ' ms · ' + (data.message || 'OK'));
            }).catch(function (error) {
                showToast('error', 'Kết nối thất bại', error.message);
            }).finally(function () {
                button.disabled = false;
            });
        });
    });

    var basePresets = {
        openai: '',
        gemini: '',
        anthropic: '',
        openrouter: '',
        deepseek: '',
        groq: '',
        openai_compatible: 'https://api.example.com/v1',
        custom: 'https://api.example.com/v1'
    };

    document.querySelectorAll('[data-provider-type]').forEach(function (select) {
        select.addEventListener('change', function () {
            var form = select.closest('form');
            var baseInput = form ? form.querySelector('[data-base-url]') : null;
            if (!baseInput) return;
            var current = baseInput.value.trim();
            if (!current || current === 'https://api.example.com/v1') {
                baseInput.value = basePresets[select.value] || '';
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
    });

    if (document.querySelector('.ego-ai-settings-alert--danger')) openModal();
})();
