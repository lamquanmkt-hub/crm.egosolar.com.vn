(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    }

    ready(function () {
        var stage = document.querySelector('[data-ego-auth-stage]');
        var spotlight = document.querySelector('[data-ego-spotlight]');
        var form = document.querySelector('[data-ego-login-form]');
        var submit = document.querySelector('[data-ego-submit]');
        var password = document.querySelector('[data-ego-password]');
        var passwordToggle = document.querySelector('[data-ego-password-toggle]');
        var capsWarning = document.querySelector('[data-ego-caps-warning]');
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (stage) {
            window.requestAnimationFrame(function () {
                stage.classList.add('is-ready');
            });
        }

        if (spotlight && !reduceMotion && window.matchMedia('(pointer: fine)').matches) {
            window.addEventListener('pointermove', function (event) {
                spotlight.style.setProperty('--spot-x', event.clientX + 'px');
                spotlight.style.setProperty('--spot-y', event.clientY + 'px');
            }, { passive: true });
        }

        if (password && passwordToggle) {
            passwordToggle.addEventListener('click', function () {
                var shouldShow = password.type === 'password';

                password.type = shouldShow ? 'text' : 'password';
                passwordToggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                passwordToggle.setAttribute('aria-label', shouldShow ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');

                var icon = passwordToggle.querySelector('i');
                if (icon) {
                    icon.className = shouldShow ? 'bi bi-eye-slash' : 'bi bi-eye';
                }

                password.focus({ preventScroll: true });
            });

            ['keydown', 'keyup'].forEach(function (eventName) {
                password.addEventListener(eventName, function (event) {
                    if (!capsWarning || typeof event.getModifierState !== 'function') {
                        return;
                    }

                    capsWarning.hidden = !event.getModifierState('CapsLock');
                });
            });

            password.addEventListener('blur', function () {
                if (capsWarning) {
                    capsWarning.hidden = true;
                }
            });
        }

        if (form && submit) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    form.reportValidity();
                    return;
                }

                submit.disabled = true;
                submit.classList.add('is-loading');
                submit.setAttribute('aria-busy', 'true');
            });
        }
    });
})();
