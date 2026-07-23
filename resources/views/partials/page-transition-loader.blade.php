@php
    $transitionLogo = 'images/ego-logo.png';

    foreach ([
        'images/ego-logo.png',
        'assets/img/ego-logo.png',
        'assets/images/ego-logo.png',
        'img/ego-logo.png',
        'images/logo.png',
    ] as $candidate) {
        if (file_exists(public_path($candidate))) {
            $transitionLogo = $candidate;
            break;
        }
    }
@endphp

<link
    rel="stylesheet"
    href="{{ asset('css/ego-page-transition.css') }}?v={{ filemtime(public_path('css/ego-page-transition.css')) }}"
>

<div
    id="egoPageTransition"
    class="ego-transition is-visible is-page-boot"
    aria-hidden="false"
>
    <div class="ego-transition__scene" aria-hidden="true">
        <span class="ego-transition__grid"></span>
        <span class="ego-transition__glow ego-transition__glow--one"></span>
        <span class="ego-transition__glow ego-transition__glow--two"></span>
    </div>

    <div class="ego-transition__content">
        <div class="ego-transition__spinner" aria-hidden="true">
            <span class="ego-transition__ring ego-transition__ring--outer"></span>
            <span class="ego-transition__ring ego-transition__ring--middle"></span>
            <span class="ego-transition__ring ego-transition__ring--inner"></span>

            <div class="ego-transition__logo-box">
                <img
                    src="{{ asset($transitionLogo) }}"
                    alt="EGO Solar"
                    class="ego-transition__logo"
                >
            </div>
        </div>

        <span class="ego-transition__brand">
            EGO SOLAR CRM
        </span>

        <strong
            class="ego-transition__title"
            data-ego-transition-title
        >
            Đang chuẩn bị không gian làm việc
        </strong>

        <span
            class="ego-transition__message"
            data-ego-transition-message
        >
            Đang đồng bộ dữ liệu...
        </span>

        <div class="ego-transition__progress" aria-hidden="true">
            <span></span>
        </div>
    </div>
</div>

<script>
    window.EGO_TRANSITION_OPTIONS = {
        bootDuration: 650,
        navigationDuration: 2000,
        maximumDuration: 3500
    };

    /* Failsafe: JS ngoài lỗi thì loader vẫn tự biến mất. */
    window.setTimeout(function () {
        var element = document.getElementById('egoPageTransition');

        if (!element) {
            return;
        }

        element.classList.remove('is-visible');
        element.setAttribute('aria-hidden', 'true');

        if (document.body) {
            document.body.classList.remove('ego-transition-locked');
        }
    }, 4000);
</script>

<script
    src="{{ asset('js/ego-page-transition.js') }}?v={{ filemtime(public_path('js/ego-page-transition.js')) }}"
    defer
></script>
