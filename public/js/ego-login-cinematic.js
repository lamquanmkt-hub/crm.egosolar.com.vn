(function () {
    'use strict';

    function onReady(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    }

    onReady(function () {
        var root = document.documentElement;
        var body = document.body;
        var spotlight = document.querySelector('[data-ego-spotlight]');
        var form = document.querySelector('[data-ego-login-form]');
        var submit = document.querySelector('[data-ego-submit]');
        var password = document.querySelector('[data-ego-password]');
        var passwordToggle = document.querySelector('[data-ego-password-toggle]');
        var capsWarning = document.querySelector('[data-ego-caps-warning]');
        var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var finePointer = window.matchMedia('(pointer: fine)').matches;
        var baseViewportHeight = window.innerHeight;

        function updateAppHeight() {
            var viewportHeight = window.visualViewport
                ? window.visualViewport.height
                : window.innerHeight;

            root.style.setProperty('--ego-app-height', Math.round(viewportHeight) + 'px');

            var keyboardOpen = window.innerWidth <= 720
                && viewportHeight < baseViewportHeight * 0.76;

            body.classList.toggle('is-keyboard-open', keyboardOpen);
        }

        updateAppHeight();

        window.addEventListener('resize', function () {
            if (!window.visualViewport) {
                baseViewportHeight = window.innerHeight;
            }
            updateAppHeight();
        }, { passive: true });

        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', updateAppHeight, { passive: true });
            window.visualViewport.addEventListener('scroll', updateAppHeight, { passive: true });
        }

        window.requestAnimationFrame(function () {
            body.classList.add('is-ready');
        });

        if (spotlight && !reduceMotion && finePointer) {
            window.addEventListener('pointermove', function (event) {
                spotlight.style.setProperty('--spot-x', event.clientX + 'px');
                spotlight.style.setProperty('--spot-y', event.clientY + 'px');
            }, { passive: true });
        }

        if (password && passwordToggle) {
            passwordToggle.addEventListener('click', function () {
                var shouldShow = password.type === 'password';
                var icon = passwordToggle.querySelector('i');

                password.type = shouldShow ? 'text' : 'password';
                passwordToggle.setAttribute('aria-pressed', shouldShow ? 'true' : 'false');
                passwordToggle.setAttribute('aria-label', shouldShow ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');

                if (icon) {
                    icon.className = shouldShow ? 'bi bi-eye-slash' : 'bi bi-eye';
                }

                try {
                    password.focus({ preventScroll: true });
                } catch (error) {
                    password.focus();
                }
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

                if (submit.disabled) {
                    event.preventDefault();
                    return;
                }

                submit.disabled = true;
                submit.classList.add('is-loading');
                submit.setAttribute('aria-busy', 'true');
            });
        }
    });
})();
