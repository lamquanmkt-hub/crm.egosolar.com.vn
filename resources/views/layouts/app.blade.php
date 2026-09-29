<!DOCTYPE html>
<html lang="vi" class="crm-navigation-ready">
<head>
    <link href="https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CRM System')</title>

    {{-- Google Font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Bootstrap 5 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Bootstrap Icons --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    @vite([
        'resources/css/core/theme.css',
        'resources/css/main.css',
        'resources/css/crm-topbar.css',
        'resources/css/crm-navigation-pro.css',
        'resources/css/crm-layout.css'
    ])
    <link rel="stylesheet" href="{{ asset('css/crm-sidebar-misa.css') }}?v={{ file_exists(public_path('css/crm-sidebar-misa.css')) ? filemtime(public_path('css/crm-sidebar-misa.css')) : '2.0.0' }}">
    <script src="{{ asset('js/crm-sidebar-misa.js') }}?v={{ file_exists(public_path('js/crm-sidebar-misa.js')) ? filemtime(public_path('js/crm-sidebar-misa.js')) : '2.0.0' }}" defer></script>
    @yield('styles')
    @stack('styles')
{{-- EGO_SYSTEM_BRANDING_RUNTIME_V2 --}}
    @include('partials.system-branding-runtime')
    {{-- EGO_LEAVE_DASHBOARD_ALERTS_CSS_V110_START --}}
    <link rel="stylesheet" href="{{ asset('css/ego-leave-dashboard-alerts.css') }}?v={{ file_exists(public_path('css/ego-leave-dashboard-alerts.css')) ? filemtime(public_path('css/ego-leave-dashboard-alerts.css')) : '1.1.0' }}">
    {{-- EGO_LEAVE_DASHBOARD_ALERTS_CSS_V110_END --}}
    {{-- EGO_SMART_SEARCH_CSS_START --}}
    <link rel="stylesheet" href="{{ asset('css/ego-smart-search.css') }}?v={{ file_exists(public_path('css/ego-smart-search.css')) ? filemtime(public_path('css/ego-smart-search.css')) : '1.0.0' }}">
    {{-- EGO_SMART_SEARCH_CSS_END --}}
</head>

<body>

<div class="ego-shell">
    {{-- Sidebar (full top) --}}
    @include('partials.sidebar')

    <div class="ego-page">
        {{-- Navbar (chỉ nằm bên phải, không đẩy sidebar xuống nữa) --}}
        @include('partials.navbar')

        <div class="ego-page__body">
            @yield('content')
        </div>
    </div>
</div>

{{-- EGO_SMART_SEARCH_WIDGET_START --}}
@include('smart-search.widget')
{{-- EGO_SMART_SEARCH_WIDGET_END --}}
{{-- EGO_TASK_FLOAT_ALL_ROLES_V150_START --}}
@if(
    auth()->check()
    && (
        request()->routeIs('dashboard')
        || request()->is('/')
    )
)
    @include('dashboard.partials.task-float')

    <script
        src="{{ asset('js/ego-task-dashboard-drawer.js') }}?v={{ file_exists(public_path('js/ego-task-dashboard-drawer.js')) ? filemtime(public_path('js/ego-task-dashboard-drawer.js')) : '1.5.0' }}"
        defer
    ></script>
@endif
{{-- EGO_TASK_FLOAT_ALL_ROLES_V150_END --}}

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/main.js') }}?v={{ filemtime(public_path('js/main.js')) }}"></script>
<script src="{{ asset('js/crm-topbar.js') }}?v={{ filemtime(public_path('js/crm-topbar.js')) }}"></script>
{{-- CRM_NAVIGATION_PRO_V2_JS --}}
<script src="{{ asset('js/crm-navigation-pro.js') }}?v={{ filemtime(public_path('js/crm-navigation-pro.js')) }}"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

@stack('scripts')
@yield('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js"></script>

<!-- �
 Global Toast container -->
<div class="position-fixed top-0 end-0 p-3" style="z-index: 999999;">
  <div id="egoToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="d-flex">
      <div class="toast-body" id="egoToastMsg">...</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
  </div>
</div>

{{-- EGO_SMART_SEARCH_JS_START --}}
<script src="{{ asset('js/ego-smart-search.js') }}?v={{ file_exists(public_path('js/ego-smart-search.js')) ? filemtime(public_path('js/ego-smart-search.js')) : '1.0.0' }}" defer></script>
{{-- EGO_SMART_SEARCH_JS_END --}}
@include('partials.mobile-ui-v5')

</body>
</html>
