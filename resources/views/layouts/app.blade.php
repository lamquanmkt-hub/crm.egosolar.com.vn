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

    <link rel="stylesheet" href="{{ asset('css/main.css') }}?v={{ filemtime(public_path('css/main.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/crm-topbar.css') }}?v={{ filemtime(public_path('css/crm-topbar.css')) }}">
    {{-- CRM_NAVIGATION_PRO_V2_CSS --}}
    <link rel="stylesheet" href="{{ asset('css/crm-navigation-pro.css') }}?v={{ filemtime(public_path('css/crm-navigation-pro.css')) }}">

    <style>
        :root{
            --app-font: "Be Vietnam Pro", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            --bg:#f6f8fc;
            --card:#ffffff;
            --border: rgba(15,23,42,.08);
            --radius: 16px;
        }

        html, body{
            font-family: var(--app-font) !important;
            background: var(--bg);
            margin: 0;
            padding: 0;
            height: 100%;
        }
        body, button, input, select, textarea, .btn, .form-control, .form-select, table{
            font-family: var(--app-font) !important;
        }

        /* �
 SHELL: sidebar full top + page bên phải */
        .ego-shell{
            min-height: 100dvh;
            display: flex;
            width: 100%;
        }

        /* �
 PAGE: navbar + content theo cột */
        .ego-page{
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* �
 Navbar “dính” trên cùng của khu vực page */
        .ego-topbar{
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        /* �
 Content body */
        .ego-page__body{
            flex: 1 1 auto;
            min-width: 0;
            width: 100%;
        }

        .card{
            border: 1px solid var(--border) !important;
            border-radius: var(--radius) !important;
            background: var(--card);
        }

        /* helper padding nếu trang nào cần */
        .ego-container{ padding: 18px 18px 22px; }
        @media (max-width: 991.98px){
            .ego-container{ padding: 12px 12px 18px; }
        }

        .table-responsive{ overflow-x:auto !important; -webkit-overflow-scrolling: touch; }
        table{ max-width:100%; }

        /* Mobile: sidebar là offcanvas nên shell vẫn ok */
        @media (max-width: 991.98px){
            .ego-shell{ display:block; }
            .ego-page{ display:block; }
            .ego-page__body{ display:block; }
        }

        /* Only Ads report page: full width */
        .mr-ads-page{ max-width:none !important; width:100%; }
        :root{
  --ego-sb: 292px;
  --ego-sb-collapsed: 86px;
}

/* ===== Desktop layout: sidebar dính + content full ===== */
@media (min-width: 992px){
  main.ego-main{
    display: flex !important;
    align-items: stretch;
    min-height: 100vh;
  }

 /* Sidebar là 1 cột cố định + đứng yên khi cuộn */
#sidebar.ego-sidebar{
  flex: 0 0 var(--ego-sb, 292px);
  position: sticky !important;
  top: 0;
  height: 100dvh;
  max-height: 100dvh;
  overflow-y: auto;
  overflow-x: hidden;
  align-self: flex-start;
  z-index: 1040;
  scrollbar-width: thin;
}
  /* Content là cột còn lại */
  .main-content{
    flex: 1 1 auto;
    width: auto !important;
    max-width: 100% !important;
    margin-left: 0 !important; /* �
 bỏ margin-left kiểu cũ */
    min-width: 0; /* �
 tránh table đẩy bung layout */
  }

  /* Khi collapsed */
  body.ego-sidebar-collapsed #sidebar.ego-sidebar{
    flex-basis: var(--ego-sb-collapsed, 86px);
  }
}
    </style>

    @yield('styles')
    @stack('styles')
    {{-- EGO_TOPBAR_MOBILE_FINAL_CSS --}}
    <link rel="stylesheet" href="{{ asset('css/crm-topbar-mobile-final.css') }}?v={{ filemtime(public_path('css/crm-topbar-mobile-final.css')) }}">
</head>

<body>
@if(auth()->check())
    <script>window.CHAT_ME_ID = {{ auth()->id() }};</script>
    <script src="{{ asset('js/chat-widget.js') }}?v={{ time() }}"></script>
@endif

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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/main.js') }}?v={{ filemtime(public_path('js/main.js')) }}"></script>
<script src="{{ asset('js/crm-topbar.js') }}?v={{ filemtime(public_path('js/crm-topbar.js')) }}"></script>
{{-- CRM_NAVIGATION_PRO_V2_JS --}}
<script src="{{ asset('js/crm-navigation-pro.js') }}?v={{ filemtime(public_path('js/crm-navigation-pro.js')) }}"></script>
@include('chat.widget')
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

@include('company_context.switcher')


{{-- EGO_TOPBAR_MOBILE_FINAL_JS --}}
<script src="{{ asset('js/crm-topbar-mobile-final.js') }}?v={{ filemtime(public_path('js/crm-topbar-mobile-final.js')) }}"></script>
</body>
</html>