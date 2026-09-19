{{-- EGO_MOBILE_UI_UNIFIED_V51_START --}}
<style>
@media (max-width: 991.98px) {
    :root {
        --ego-mobile-sidebar-w: min(88vw, 320px);
    }

    html,
    body {
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: hidden !important;
    }

    html body #sidebar.ego-sidebar {
        position: fixed !important;
        top: 0 !important;
        right: auto !important;
        bottom: 0 !important;
        left: 0 !important;
        z-index: 2147483646 !important;
        width: var(--ego-mobile-sidebar-w) !important;
        min-width: 0 !important;
        max-width: 320px !important;
        height: 100dvh !important;
        min-height: 100dvh !important;
        max-height: 100dvh !important;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        visibility: hidden !important;
        pointer-events: none !important;
        opacity: 1 !important;
        filter: none !important;
        transform: translate3d(-105%, 0, 0) !important;
        transition:
            transform .22s cubic-bezier(.22,.61,.36,1),
            visibility 0s linear .22s !important;
        will-change: transform !important;
        isolation: isolate !important;
        border-radius: 0 16px 16px 0 !important;
        box-shadow: 18px 0 46px rgba(15,23,42,.24) !important;
    }

    html body #sidebar.ego-sidebar.show {
        visibility: visible !important;
        pointer-events: auto !important;
        opacity: 1 !important;
        filter: none !important;
        transform: translate3d(0, 0, 0) !important;
        transition:
            transform .22s cubic-bezier(.22,.61,.36,1),
            visibility 0s linear 0s !important;
    }

    html body #sidebar.ego-sidebar .ego-sidebar__header {
        position: relative !important;
        flex: 0 0 auto !important;
        min-height: 70px !important;
        padding: 12px 62px 12px 14px !important;
        overflow: visible !important;
    }

    html body #sidebar.ego-sidebar .ego-sidebar__scroll {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow-x: hidden !important;
        overflow-y: auto !important;
        overscroll-behavior: contain !important;
        -webkit-overflow-scrolling: touch !important;
    }

    html body #sidebar.ego-sidebar #toggleSidebar {
        position: absolute !important;
        top: 12px !important;
        right: 12px !important;
        left: auto !important;
        z-index: 2147483647 !important;
        width: 42px !important;
        min-width: 42px !important;
        height: 42px !important;
        min-height: 42px !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 1px solid #d8e3ec !important;
        border-radius: 13px !important;
        background: #ffffff !important;
        color: #0f172a !important;
        box-shadow: 0 10px 28px rgba(15,23,42,.16) !important;
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        touch-action: manipulation !important;
        transform: none !important;
        -webkit-tap-highlight-color: transparent !important;
    }

    html body #sidebar.ego-sidebar #toggleSidebar i {
        font-size: 20px !important;
        line-height: 1 !important;
        pointer-events: none !important;
    }

    html body #egoMobileSidebarClose {
        display: none !important;
    }

    /*
     * Quan trọng: overlay bắt đầu SAU chiều rộng Sidebar,
     * nên không bao giờ phủ tối hoặc chặn thao tác trên menu.
     */
    html body #sidebarOverlay.ego-sidebar-overlay {
        position: fixed !important;
        top: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        left: var(--ego-mobile-sidebar-w) !important;
        z-index: 2147483645 !important;
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        border: 0 !important;
        background: rgba(15,23,42,.48) !important;
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        transition:
            opacity .18s ease,
            visibility 0s linear .18s !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        touch-action: manipulation !important;
    }

    html body #sidebarOverlay.ego-sidebar-overlay.show {
        opacity: 1 !important;
        visibility: visible !important;
        pointer-events: auto !important;
        transition:
            opacity .18s ease,
            visibility 0s linear 0s !important;
    }

    /*
     * Khi Sidebar mở, toàn bộ backdrop khác phải biến mất.
     */
    html.ego-mobile-sidebar-open body #crmTopbarBackdrop,
    html.ego-mobile-sidebar-open body .modal-backdrop,
    html.ego-mobile-sidebar-open body .offcanvas-backdrop {
        display: none !important;
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
    }

    html.ego-mobile-sidebar-open,
    html.ego-mobile-sidebar-open body,
    body.ego-noscroll {
        overflow: hidden !important;
        overscroll-behavior: none !important;
    }

    html.ego-mobile-sidebar-open body #sidebar,
    html.ego-mobile-sidebar-open body #sidebar .ego-sidebar__scroll {
        touch-action: pan-y !important;
    }


@media (min-width: 992px) {
    html body #sidebar.ego-sidebar {
        visibility: visible !important;
        pointer-events: auto !important;
        transform: none !important;
        opacity: 1 !important;
        filter: none !important;
    }

    html body #sidebarOverlay.ego-sidebar-overlay {
        display: none !important;
    }
}

@media (prefers-reduced-motion: reduce) {
    html body #sidebar.ego-sidebar,
    html body #sidebarOverlay.ego-sidebar-overlay {
        transition: none !important;
    }
}
</style>

<script src="{{ asset('js/crm-mobile-ui-unified-v5.1.js') }}?v={{ file_exists(public_path('js/crm-mobile-ui-unified-v5.1.js')) ? filemtime(public_path('js/crm-mobile-ui-unified-v5.1.js')) : '5.1.0' }}"></script>
{{-- EGO_MOBILE_UI_UNIFIED_V51_END --}}

{{-- EGO_MOBILE_SIDEBAR_EDGE_V52_START --}}
<style>
@media (max-width: 991.98px) {

    /*
     * Bỏ hoàn toàn bóng đổ cũ tạo thành dải sáng ở mép Sidebar.
     */
    html body #sidebar.ego-sidebar {
        background: #ffffff !important;
        border-right: 1px solid #dfe7ef !important;
        box-shadow: none !important;
        filter: none !important;
        overflow: hidden !important;
        background-clip: padding-box !important;
    }

    /*
     * Các khối bên trong phải có nền đặc, không để lộ hiệu ứng
     * hoặc màu nền của trang phía sau.
     */
    html body #sidebar.ego-sidebar .ego-sidebar__header,
    html body #sidebar.ego-sidebar .ego-sidebar__scroll,
    html body #sidebar.ego-sidebar .ego-nav {
        background: #ffffff !important;
        background-image: none !important;
        filter: none !important;
    }

    /*
     * Loại bỏ pseudo-element trang trí hoặc glow cũ.
     */
    html body #sidebar.ego-sidebar::before {
        display: none !important;
        content: none !important;
    }

    /*
     * Tạo đường che kín 2px cuối Sidebar.
     */
    html body #sidebar.ego-sidebar::after {
        content: "" !important;
        position: absolute !important;
        top: 0 !important;
        right: -1px !important;
        bottom: 0 !important;
        width: 3px !important;
        height: 100% !important;
        z-index: 2147483647 !important;
        display: block !important;
        background: #ffffff !important;
        background-image: none !important;
        opacity: 1 !important;
        pointer-events: none !important;
        box-shadow: none !important;
        filter: none !important;
    }

    /*
     * Cho overlay chồng vào Sidebar 2px để không xuất hiện khe hở
     * do làm tròn kích thước viewport trên điện thoại.
     */
    html body #sidebarOverlay.ego-sidebar-overlay {
        left: calc(var(--ego-mobile-sidebar-w) - 2px) !important;
        border: 0 !important;
        border-left: 0 !important;
        box-shadow: none !important;
        filter: none !important;
        mix-blend-mode: normal !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        background: rgba(15, 23, 42, .50) !important;
    }

    /*
     * Khi menu mở, không cho các hiệu ứng glow của trang phía sau
     * xuất hiện sát mép Sidebar.
     */
    html.ego-mobile-sidebar-open body .workspace-hero,
    html.ego-mobile-sidebar-open body .workspace-shell,
    html.ego-mobile-sidebar-open body .hero-glow,
    html.ego-mobile-sidebar-open body .page-glow {
        filter: none !important;
    }
}
</style>
{{-- EGO_MOBILE_SIDEBAR_EDGE_V52_END --}}
