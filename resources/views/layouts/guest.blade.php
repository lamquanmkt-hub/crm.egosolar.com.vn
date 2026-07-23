<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#06182a">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Đăng nhập • EGO SOLAR CRM')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('css/ego-login-cinematic.css') }}?v=2.1.1" rel="stylesheet">

    @stack('styles')

    {{-- EGO_SYSTEM_BRANDING_RUNTIME_V2 --}}
    @include('partials.system-branding-runtime')
</head>
<body class="ego-cinematic-page">
    <div class="ego-cinematic-bg" aria-hidden="true">
        <div class="ego-cinematic-bg__image"></div>
        <div class="ego-cinematic-bg__veil"></div>
        <div class="ego-cinematic-bg__grid"></div>
        <div class="ego-cinematic-bg__noise"></div>
        <div class="ego-cinematic-bg__glow ego-cinematic-bg__glow--cyan"></div>
        <div class="ego-cinematic-bg__glow ego-cinematic-bg__glow--teal"></div>
        <div class="ego-cinematic-bg__spotlight" data-ego-spotlight></div>
        <div class="ego-cinematic-particles">
            @for ($particle = 1; $particle <= 8; $particle++)
                <span></span>
            @endfor
        </div>
    </div>

    <main class="ego-login-shell" data-ego-login-shell>
        <header class="ego-login-brand" data-ego-reveal>
            <img src="{{ asset('images/ego-logo.png') }}" alt="EGO Solar" class="ego-login-brand__logo">
            <span class="ego-login-brand__copy">
                <strong>EGO SOLAR</strong>
                <small>ENTERPRISE CRM</small>
            </span>
        </header>

        <section class="ego-login-story" aria-label="Giới thiệu EGO Solar CRM" data-ego-reveal>
            <span class="ego-login-story__eyebrow">Nền tảng điều hành hợp nhất</span>
            <h1>Điều hành thông minh.<br>Tăng trưởng bền vững.</h1>
            <p>
                Một nền tảng thống nhất cho Sales, Marketing, Kỹ thuật,
                Kho, Nhân sự và Ban điều hành.
            </p>

            <div class="ego-login-story__points" aria-label="Năng lực hệ thống">
                <span><b>01</b> Quy trình liên phòng ban</span>
                <span><b>02</b> Dữ liệu theo thời gian thực</span>
                <span><b>03</b> Phân quyền theo vai trò</span>
            </div>
        </section>

        <section class="ego-login-mobile-intro" aria-hidden="true" data-ego-reveal>
            <span class="ego-login-mobile-status">
                <i></i> CRM Enterprise đang hoạt động
            </span>
            <strong>Điều hành thông minh</strong>
        </section>

        <section class="ego-login-panel-wrap" data-ego-reveal>
            <div class="ego-login-panel">
                <div class="ego-login-panel__status">
                    <span class="ego-system-indicator">
                        <i aria-hidden="true"></i>
                        Kết nối bảo mật
                    </span>
                    <span class="ego-system-live">
                        <i aria-hidden="true"></i>
                        Hệ thống đang hoạt động
                    </span>
                </div>

                <div class="ego-login-panel__body">
                    @yield('content')
                </div>

                <footer class="ego-login-panel__footer">
                    <span>© {{ date('Y') }} EGO SOLAR</span>
                    <span>CRM Enterprise · Secure Connection</span>
                </footer>
            </div>
        </section>

        <footer class="ego-login-global-footer" data-ego-reveal>
            <span><i class="bi bi-shield-check" aria-hidden="true"></i> Bảo mật phiên đăng nhập</span>
            <span><i class="bi bi-headset" aria-hidden="true"></i> Hỗ trợ nội bộ</span>
        </footer>
    </main>

    <script src="{{ asset('js/ego-login-cinematic.js') }}?v=2.1.0" defer></script>
    @stack('scripts')
</body>
</html>
