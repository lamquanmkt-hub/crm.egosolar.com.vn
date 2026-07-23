@php
    $topbarUser = auth()->user();
    $topbarRouteName = (string) optional(request()->route())->getName();

    $topbarModuleTitle = match (true) {
        request()->routeIs('orders.*', 'order-returns.*', 'orders.returns.*') => 'Đơn hàng',
        request()->routeIs('customers.*', 'customer-profiles.*') => 'Khách hàng',
        request()->routeIs('products.*', 'product-categories.*', 'serial-warranty.*') => 'Sản phẩm',
        request()->routeIs('sites.*', 'ky-thuat.*', 'technical.*') => 'Công trình & Kỹ thuật',
        request()->routeIs('finance.*', 'payment_requests.*', 'payment-requests.*', 'payment_methods.*') => 'Tài chính',
        request()->routeIs('hr.*') => 'Nhân sự',
        request()->routeIs('sales.*', 'sales-quotations.*') => 'Kinh doanh',
        request()->routeIs('marketing.*') => 'Marketing',
        request()->routeIs('chat.*') => 'Tin nhắn',
        request()->routeIs('tasks.*') => 'Công việc',
        request()->routeIs('notifications.*') => 'Thông báo',
        request()->routeIs('solar.*') => 'Công cụ Solar',
        default => 'CRM EGO Solar',
    };

    $topbarAvatarPath = null;
    if ($topbarUser && !empty($topbarUser->avatar)) {
        if (is_string($topbarUser->avatar)) {
            $topbarAvatarPath = $topbarUser->avatar;
        } elseif (is_object($topbarUser->avatar) && isset($topbarUser->avatar->file_path)) {
            $topbarAvatarPath = $topbarUser->avatar->file_path;
        } elseif (is_array($topbarUser->avatar) && isset($topbarUser->avatar['file_path'])) {
            $topbarAvatarPath = $topbarUser->avatar['file_path'];
        }
    }

    $topbarAvatarUrl = null;
    if ($topbarAvatarPath) {
        if (
            str_starts_with($topbarAvatarPath, 'http://')
            || str_starts_with($topbarAvatarPath, 'https://')
            || str_starts_with($topbarAvatarPath, '/storage/')
        ) {
            $topbarAvatarUrl = $topbarAvatarPath;
        } else {
            $topbarAvatarUrl = \Illuminate\Support\Facades\Storage::url($topbarAvatarPath);
        }
    }

    $topbarUserName = (string) ($topbarUser->name ?? 'Tài khoản');
    $topbarUserInitial = mb_strtoupper(mb_substr(trim($topbarUserName), 0, 1));
    $topbarUserEmail = (string) ($topbarUser->email ?? '');
    $topbarChatInboxUrl = \Illuminate\Support\Facades\Route::has('chat.inbox')
        ? route('chat.inbox')
        : url('/chat');
    $topbarQuoteUrl = \Illuminate\Support\Facades\Route::has('sales-quotations.index')
        ? route('sales-quotations.index')
        : url('/bao-gia');
@endphp

<nav
    id="crmTopbar"
    class="crm-topbar ego-topbar"
    aria-label="Thanh điều hướng chính"
    data-csrf="{{ csrf_token() }}"
    data-vapid-key="{{ config('webpush.vapid.public_key') }}"
    data-push-subscribe-url="{{ route('push.subscribe') }}"
    data-notification-unread-url="{{ route('notifications.unread-count') }}"
    data-notification-list-url="{{ route('notifications.json') }}"
    data-notification-mark-all-url="{{ route('notifications.mark-all-read') }}"
    data-notification-read-template="{{ url('/notifications/__ID__/read') }}"
    data-notification-index-url="{{ route('notifications.index') }}"
    data-chat-list-url="{{ $topbarChatInboxUrl === url('/chat') && !\Illuminate\Support\Facades\Route::has('chat.conversations.json') ? url('/chat/conversations/json') : route('chat.conversations.json') }}"
    data-chat-base-url="{{ url('/chat') }}"
>
    <div class="crm-topbar__inner">
        <div class="crm-topbar__left">
            <button
                type="button"
                class="crm-topbar__icon-button crm-topbar__sidebar-toggle"
                id="crmSidebarToggle"
                aria-label="Thu gọn hoặc mở rộng thanh bên"
                title="Thu gọn hoặc mở rộng menu"
            >
                <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
            </button>

            <button
                type="button"
                class="crm-topbar__icon-button crm-topbar__mobile-menu"
                id="openSidebarBtn"
                aria-label="Mở menu"
                title="Mở menu"
            >
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            {{-- EGO_MOBILE_CORE_V9_MORE --}}
            <button
                type="button"
                class="crm-topbar__icon-button crm-topbar__mobile-more"
                data-crm-panel-toggle="more"
                aria-controls="crmMorePanel"
                aria-expanded="false"
                aria-label="Mở tiện ích"
                title="Tiện ích"
            >
                <i
                    class="bi bi-three-dots"
                    aria-hidden="true"
                ></i>
            </button>

<div class="crm-topbar__module-copy">
                <span class="crm-topbar__module-eyebrow">Không gian làm việc</span>
                <strong class="crm-topbar__module-title">{{ $topbarModuleTitle }}</strong>
            </div>

            <span class="crm-topbar__status-chip" title="Chủ động phối hợp, tăng tốc hiệu suất">
                <span class="crm-topbar__status-dot" aria-hidden="true"></span>
                Chủ động phối hợp, tăng tốc hiệu suất
            </span>
        </div>


        {{-- EGO_MOBILE_CORE_V9_BRAND --}}
        <a
            href="{{ url('/') }}"
            class="crm-topbar__mobile-brand"
            aria-label="Trang chủ EGO Solar"
        >
            <img
                src="{{ asset('images/ego-logo.png') }}"
                alt="EGO Solar"
                data-crm-brand-logo
                data-ego-logo-light
            >

            <span
                class="crm-topbar__brand-fallback"
                data-crm-brand-fallback
            >
                EGO SOLAR
            </span>
        </a>

        <div class="crm-topbar__center" aria-label="Điều hướng nhanh">
            <a
                href="{{ route('solar.calculator') }}"
                class="crm-topbar__quick-link {{ request()->routeIs('solar.*') ? 'is-active' : '' }}"
            >
                <i class="bi bi-sun" aria-hidden="true"></i>
                <span>Công cụ Solar</span>
            </a>

            <a
                href="{{ $topbarQuoteUrl }}"
                class="crm-topbar__quick-link {{ request()->routeIs('sales-quotations.*') ? 'is-active' : '' }}"
            >
                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                <span>Báo giá</span>
            </a>

            @auth
                <a
                    href="{{ route('hr.attendance.my') }}"
                    class="crm-topbar__quick-link {{ request()->routeIs('hr.attendance.*') ? 'is-active' : '' }}"
                >
                    <i class="bi bi-calendar-check" aria-hidden="true"></i>
                    <span>Chấm công</span>
                </a>
            @endauth
        </div>

        <div class="crm-topbar__right">
            @auth
                <div class="crm-topbar__panel-wrap crm-topbar__chat-wrap" data-crm-panel-wrap="chat">
                    <button
                        type="button"
                        class="crm-topbar__action-button {{ request()->routeIs('chat.*') ? 'is-active' : '' }}"
                        id="chatNavbarToggle"
                        data-crm-panel-toggle="chat"
                        aria-controls="crmChatPanel"
                        aria-expanded="false"
                        title="Tin nhắn"
                    >
                        <span class="crm-topbar__action-icon">
                            <i class="bi bi-chat-dots" aria-hidden="true"></i>
                            <span class="crm-topbar__badge" id="chatNavbarBadge" hidden>0</span>
                        </span>
                        <span class="crm-topbar__action-label">Tin nhắn</span>
                    </button>

                    <section class="crm-topbar__panel crm-topbar__panel--wide" id="crmChatPanel" data-crm-panel="chat" aria-hidden="true">
                        <header class="crm-topbar__panel-header">
                            <div>
                                <span class="crm-topbar__panel-kicker">Trao đổi nội bộ</span>
                                <h2>Tin nhắn</h2>
                            </div>
                            <div class="crm-topbar__panel-header-actions">
                                <a href="{{ $topbarChatInboxUrl }}" class="crm-topbar__panel-icon" aria-label="Mở tất cả tin nhắn" title="Mở tất cả">
                                    <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                                </a>
                                <button type="button" class="crm-topbar__panel-icon crm-topbar__panel-close" data-crm-panel-close aria-label="Đóng tin nhắn">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                </button>
                            </div>
                        </header>

                        <div class="crm-topbar__search-row">
                            <i class="bi bi-search" aria-hidden="true"></i>
                            <input type="search" id="chatNavbarSearch" placeholder="Tìm người, nhóm hoặc nội dung" autocomplete="off">
                        </div>

                        <div class="crm-topbar__tabs" role="tablist" aria-label="Bộ lọc tin nhắn">
                            <button type="button" class="is-active" data-chat-filter="all">Tất cả</button>
                            <button type="button" data-chat-filter="unread">Chưa đọc</button>
                            <button type="button" data-chat-filter="group">Nhóm</button>
                            <button type="button" data-chat-filter="department">Phòng ban</button>
                        </div>

                        <div class="crm-topbar__panel-list" id="chatNavbarList">
                            <div class="crm-topbar__empty-state">Đang tải tin nhắn…</div>
                        </div>

                        <a class="crm-topbar__panel-footer" href="{{ $topbarChatInboxUrl }}">
                            Xem tất cả tin nhắn
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>
                </div>

                <div class="crm-topbar__panel-wrap crm-topbar__more-wrap" data-crm-panel-wrap="more">
                    <button
                        type="button"
                        class="crm-topbar__icon-button crm-topbar__more-toggle"
                        data-crm-panel-toggle="more"
                        aria-controls="crmMorePanel"
                        aria-expanded="false"
                        aria-label="Mở tiện ích"
                        title="Tiện ích"
                    >
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                    </button>

                    <section class="crm-topbar__panel crm-topbar__panel--compact" id="crmMorePanel" data-crm-panel="more" aria-hidden="true">
                        <header class="crm-topbar__panel-header crm-topbar__panel-header--compact">
                            <div>
                                <span class="crm-topbar__panel-kicker">Truy cập nhanh</span>
                                <h2>Tiện ích</h2>
                            </div>
                            <button type="button" class="crm-topbar__panel-icon crm-topbar__panel-close" data-crm-panel-close aria-label="Đóng tiện ích">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </header>
                        <nav class="crm-topbar__utility-list" aria-label="Tiện ích hệ thống">
    <a href="{{ route('solar.calculator') }}">
        <span class="crm-topbar__utility-icon">
            <i class="bi bi-sun" aria-hidden="true"></i>
        </span>
        <span class="crm-topbar__utility-copy">
            <strong>Công cụ Solar</strong>
            <small>Tính toán hệ thống điện mặt trời</small>
        </span>
    </a>

    <a href="{{ $topbarQuoteUrl }}">
        <span class="crm-topbar__utility-icon">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
        </span>
        <span class="crm-topbar__utility-copy">
            <strong>Báo giá</strong>
            <small>Danh sách và tạo báo giá mới</small>
        </span>
    </a>

    <a href="{{ route('hr.attendance.my') }}">
        <span class="crm-topbar__utility-icon">
            <i class="bi bi-calendar-check" aria-hidden="true"></i>
        </span>
        <span class="crm-topbar__utility-copy">
            <strong>Chấm công</strong>
            <small>Kiểm tra lịch sử chấm công</small>
        </span>
    </a>

    <button type="button" data-ego-open-panel="chat">
        <span class="crm-topbar__utility-icon">
            <i class="bi bi-chat-dots" aria-hidden="true"></i>
        </span>
        <span class="crm-topbar__utility-copy">
            <strong>Tin nhắn</strong>
            <small>Trao đổi nội bộ và công việc</small>
        </span>
    </button>

    <button
        type="button"
        class="crm-topbar__utility-item--wide"
        data-ego-open-panel="notifications"
    >
        <span class="crm-topbar__utility-icon">
            <i class="bi bi-bell" aria-hidden="true"></i>
        </span>
        <span class="crm-topbar__utility-copy">
            <strong>Thông báo</strong>
            <small>Xem cập nhật và các nội dung cần xử lý</small>
        </span>
    </button>
</nav>
                    </section>
                </div>

                <div class="crm-topbar__panel-wrap" data-crm-panel-wrap="notifications">
                    <button
                        type="button"
                        class="crm-topbar__action-button crm-topbar__notification-button {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}"
                        id="notifyDropdownBtn"
                        data-crm-panel-toggle="notifications"
                        aria-controls="crmNotificationPanel"
                        aria-expanded="false"
                        title="Thông báo"
                    >
                        <span class="crm-topbar__action-icon">
                            <i class="bi bi-bell" aria-hidden="true"></i>
                            <span class="crm-topbar__badge" id="notifyBadge" hidden>0</span>
                        </span>
                        <span class="crm-topbar__action-label">Thông báo</span>
                    </button>

                    <section class="crm-topbar__panel crm-topbar__panel--wide" id="crmNotificationPanel" data-crm-panel="notifications" aria-hidden="true">
                        <header class="crm-topbar__panel-header">
                            <div>
                                <span class="crm-topbar__panel-kicker">Cập nhật hệ thống</span>
                                <h2>Thông báo</h2>
                            </div>
                            <div class="crm-topbar__panel-header-actions">
                                <button type="button" class="crm-topbar__text-action" id="notifyReadAllBtn">
                                    <i class="bi bi-check2-all" aria-hidden="true"></i>
                                    <span>Đọc tất cả</span>
                                </button>
                                <button type="button" class="crm-topbar__panel-icon crm-topbar__panel-close" data-crm-panel-close aria-label="Đóng thông báo">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                </button>
                            </div>
                        </header>

                        <div class="crm-topbar__notification-tools">
                            <div class="crm-topbar__tabs" role="tablist" aria-label="Bộ lọc thông báo">
                                <button type="button" class="is-active" data-notification-filter="all">Tất cả</button>
                                <button type="button" data-notification-filter="unread">Chưa đọc</button>
                            </div>
                            <button type="button" class="crm-topbar__push-button" id="pushEnableBtn">
                                <i class="bi bi-bell" aria-hidden="true"></i>
                                <span>Bật thông báo</span>
                            </button>
                        </div>

                        <div class="crm-topbar__panel-list" id="notifyList">
                            <div class="crm-topbar__skeleton-list" aria-label="Đang tải thông báo">
                                <span></span><span></span><span></span>
                            </div>
                        </div>

                        <a class="crm-topbar__panel-footer" href="{{ route('notifications.index') }}">
                            Xem tất cả thông báo
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </a>
                    </section>
                </div>

                <div class="crm-topbar__panel-wrap" data-crm-panel-wrap="account">
                    <button
                        type="button"
                        class="crm-topbar__account-button"
                        id="navbarDropdown"
                        data-crm-panel-toggle="account"
                        aria-controls="crmAccountPanel"
                        aria-expanded="false"
                    >
                        <span class="crm-topbar__avatar">
                            @if($topbarAvatarUrl)
                                <img src="{{ $topbarAvatarUrl }}" alt="Ảnh đại diện {{ $topbarUserName }}" data-crm-avatar-image>
                            @endif
                            <span class="crm-topbar__avatar-fallback {{ $topbarAvatarUrl ? 'is-hidden' : '' }}" data-crm-avatar-fallback>{{ $topbarUserInitial }}</span>
                        </span>
                        <span class="crm-topbar__account-copy">
                            <strong>{{ $topbarUserName }}</strong>
                            <small>Tài khoản</small>
                        </span>
                        <i class="bi bi-chevron-down crm-topbar__account-chevron" aria-hidden="true"></i>
                    </button>

                    <section class="crm-topbar__panel crm-topbar__panel--account" id="crmAccountPanel" data-crm-panel="account" aria-hidden="true">
                        <header class="crm-topbar__account-header">
                            <span class="crm-topbar__avatar crm-topbar__avatar--large">
                                @if($topbarAvatarUrl)
                                    <img src="{{ $topbarAvatarUrl }}" alt="Ảnh đại diện {{ $topbarUserName }}" data-crm-avatar-image>
                                @endif
                                <span class="crm-topbar__avatar-fallback {{ $topbarAvatarUrl ? 'is-hidden' : '' }}" data-crm-avatar-fallback>{{ $topbarUserInitial }}</span>
                            </span>
                            <div>
                                <strong>{{ $topbarUserName }}</strong>
                                @if($topbarUserEmail !== '')
                                    <span>{{ $topbarUserEmail }}</span>
                                @endif
                            </div>
                            <button type="button" class="crm-topbar__panel-icon crm-topbar__panel-close" data-crm-panel-close aria-label="Đóng tài khoản">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </header>

                        <nav class="crm-topbar__account-menu" aria-label="Menu tài khoản">
                            <a href="{{ route('users.profile') }}">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <span>Thông tin cá nhân</span>
                            </a>
                            <a href="{{ route('users.profile-edit') }}">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                <span>Chỉnh sửa hồ sơ</span>
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="is-danger">
                                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                    <span>Đăng xuất</span>
                                </button>
                            </form>
                        </nav>
                    </section>
                </div>
            @else
                <a class="crm-topbar__quick-link" href="{{ route('login') }}">
                    <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                    <span>Đăng nhập</span>
                </a>
            @endauth
        </div>
    </div>
</nav>

<div id="crmTopbarBackdrop" class="crm-topbar__backdrop" aria-hidden="true"></div>
<div id="notifyToastContainer" class="crm-topbar__toast-container" aria-live="polite" aria-atomic="true"></div>
