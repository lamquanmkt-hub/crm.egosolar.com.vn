@php
    $egoThemeDefaults = [
        'brand_name' => 'EGO Solar CRM',
        'brand_short_name' => 'EGO Solar',
        'logo_light' => 'images/ego-logo.png',
        'logo_sidebar' => 'logo/ego-solar-white.png',
        'favicon' => '',
        'primary_color' => '#12ABC6',
        'secondary_color' => '#0D988C',
        'sidebar_color' => '#06182A',
        'topbar_color' => '#FFFFFF',
        'page_background' => '#F4F8FB',
        'card_radius' => '16',
        'ui_density' => 'comfortable',
    ];

    try {
        $egoTheme = \Illuminate\Support\Facades\Cache::remember(
            'ego.system.branding.v2',
            600,
            function () use ($egoThemeDefaults): array {
                if (! \Illuminate\Support\Facades\Schema::hasTable('ego_system_settings')) {
                    return $egoThemeDefaults;
                }

                $stored = \Illuminate\Support\Facades\DB::table('ego_system_settings')
                    ->whereIn('key', array_keys($egoThemeDefaults))
                    ->pluck('value', 'key')
                    ->map(fn ($value): string => (string) $value)
                    ->all();

                return array_merge($egoThemeDefaults, $stored);
            }
        );
    } catch (\Throwable $egoThemeException) {
        $egoTheme = $egoThemeDefaults;
    }

    $egoSafeHex = static function ($value, string $fallback): string {
        $value = strtoupper(trim((string) $value));
        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : $fallback;
    };

    $egoTheme['primary_color'] = $egoSafeHex($egoTheme['primary_color'], '#12ABC6');
    $egoTheme['secondary_color'] = $egoSafeHex($egoTheme['secondary_color'], '#0D988C');
    $egoTheme['sidebar_color'] = $egoSafeHex($egoTheme['sidebar_color'], '#06182A');
    $egoTheme['topbar_color'] = $egoSafeHex($egoTheme['topbar_color'], '#FFFFFF');
    $egoTheme['page_background'] = $egoSafeHex($egoTheme['page_background'], '#F4F8FB');
    $egoTheme['card_radius'] = (string) max(8, min(30, (int) $egoTheme['card_radius']));
    $egoTheme['ui_density'] = in_array($egoTheme['ui_density'], ['comfortable', 'compact'], true)
        ? $egoTheme['ui_density']
        : 'comfortable';

    $egoThemeRuntime = json_encode([
        'brandName' => $egoTheme['brand_name'],
        'brandShortName' => $egoTheme['brand_short_name'],
        'logoLight' => asset($egoTheme['logo_light'] ?: 'images/ego-logo.png'),
        'logoSidebar' => asset($egoTheme['logo_sidebar'] ?: 'logo/ego-solar-white.png'),
        'density' => $egoTheme['ui_density'],
        'sidebarColor' => $egoTheme['sidebar_color'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
@endphp

@if(! empty($egoTheme['favicon']))
    <link rel="icon" href="{{ asset($egoTheme['favicon']) }}">
@endif

<style id="ego-system-branding-v2">
    :root {
        --ego-brand-primary: {{ $egoTheme['primary_color'] }};
        --ego-brand-secondary: {{ $egoTheme['secondary_color'] }};
        --ego-theme-sidebar: {{ $egoTheme['sidebar_color'] }};
        --ego-theme-topbar: {{ $egoTheme['topbar_color'] }};
        --ego-theme-page: {{ $egoTheme['page_background'] }};
        --ego-theme-radius: {{ (int) $egoTheme['card_radius'] }}px;
        --bg: var(--ego-theme-page);
        --radius: var(--ego-theme-radius);
    }

    body:not(.ego-cinematic-page),
    body:not(.ego-cinematic-page) .ego-page,
    body:not(.ego-cinematic-page) .ego-page__body,
    body:not(.ego-cinematic-page) .main-content {
        background-color: var(--ego-theme-page) !important;
    }

    html body #sidebar.ego-sidebar {
        background:
            radial-gradient(720px 430px at -20% 0%, color-mix(in srgb, var(--ego-brand-primary) 24%, transparent), transparent 58%),
            radial-gradient(620px 420px at 112% 18%, color-mix(in srgb, var(--ego-brand-secondary) 18%, transparent), transparent 55%),
            linear-gradient(180deg, color-mix(in srgb, var(--ego-theme-sidebar) 88%, #ffffff 12%), var(--ego-theme-sidebar)) !important;
    }

    html body .ego-topbar,
    html body .crm-topbar,
    html body .app-topbar,
    html body nav.navbar {
        background-color: var(--ego-theme-topbar) !important;
    }

    html body #sidebar .ego-link.active,
    html body #sidebar .ego-link[aria-expanded="true"] {
        border-color: color-mix(in srgb, var(--ego-brand-primary) 78%, #ffffff 22%) !important;
        box-shadow:
            inset 3px 0 0 var(--ego-brand-primary),
            0 8px 22px color-mix(in srgb, var(--ego-brand-primary) 18%, transparent) !important;
    }

    html body #sidebar .ego-sublink.active {
        color: #ffffff !important;
        background: linear-gradient(135deg,
            color-mix(in srgb, var(--ego-brand-primary) 30%, transparent),
            color-mix(in srgb, var(--ego-brand-secondary) 18%, transparent)
        ) !important;
    }

    html body .btn-primary,
    html body .ego-btn-primary,
    html body .cx-btn--primary,
    html body .eas-btn--primary {
        color: #ffffff !important;
        border-color: transparent !important;
        background: linear-gradient(135deg, var(--ego-brand-primary), var(--ego-brand-secondary)) !important;
    }

    html body .card,
    html body .cx-panel,
    html body .dashboard-card,
    html body .content-card,
    html body .modal-content {
        border-radius: var(--ego-theme-radius) !important;
    }

    html[data-ego-density="compact"] .card-body,
    html[data-ego-density="compact"] .cx-card-body {
        padding-top: .72rem !important;
        padding-bottom: .72rem !important;
    }

    html[data-ego-density="compact"] .table > :not(caption) > * > *,
    html[data-ego-density="compact"] table td,
    html[data-ego-density="compact"] table th {
        padding-top: .46rem !important;
        padding-bottom: .46rem !important;
    }
</style>

<script id="ego-system-branding-data" type="application/json">{!! $egoThemeRuntime ?: '{}' !!}</script>
<script>
    (() => {
        const applyEgoBranding = () => {
            const node = document.getElementById('ego-system-branding-data');
            if (!node) return;

            let theme = {};
            try { theme = JSON.parse(node.textContent || '{}'); } catch (_) { return; }

            document.documentElement.setAttribute(
                'data-ego-density',
                theme.density || 'comfortable'
            );

            document.querySelectorAll('#sidebar .ego-brand__logo-big').forEach((image) => {
                if (theme.logoSidebar) image.src = theme.logoSidebar;
            });

            document.querySelectorAll('.ego-login-brand__logo, [data-ego-logo-light]').forEach((image) => {
                if (theme.logoLight) image.src = theme.logoLight;
            });

            document.querySelectorAll('[data-ego-brand-name]').forEach((element) => {
                element.textContent = theme.brandName || 'EGO Solar CRM';
            });

            const themeMeta = document.querySelector('meta[name="theme-color"]');
            if (themeMeta && theme.sidebarColor) themeMeta.content = theme.sidebarColor;
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', applyEgoBranding, { once: true });
        } else {
            applyEgoBranding();
        }
    })();
</script>
