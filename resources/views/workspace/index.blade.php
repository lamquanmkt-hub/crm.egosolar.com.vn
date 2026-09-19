<!DOCTYPE html>
<html lang="vi" data-theme="cinematic">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $brandShortName }} Workspace</title>

    @if($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/ego-workspace-cinematic-v5.css') }}?v={{ file_exists(public_path('css/ego-workspace-cinematic-v5.css')) ? filemtime(public_path('css/ego-workspace-cinematic-v5.css')) : '5.0.0' }}">
    <link rel="stylesheet" href="{{ asset('css/ego-workspace-mobile-premium-v6.css') }}?v={{ file_exists(public_path('css/ego-workspace-mobile-premium-v6.css')) ? filemtime(public_path('css/ego-workspace-mobile-premium-v6.css')) : '6.0.0' }}">

{{-- EGO_WORKSPACE_AVATAR_V121_START --}}
@php
    $ws12User = auth()->user();
    $ws12AvatarPath = null;

    if ($ws12User && !empty($ws12User->avatar)) {
        if (is_string($ws12User->avatar)) {
            $ws12AvatarPath = $ws12User->avatar;
        } elseif (is_object($ws12User->avatar) && isset($ws12User->avatar->file_path)) {
            $ws12AvatarPath = $ws12User->avatar->file_path;
        } elseif (is_array($ws12User->avatar) && isset($ws12User->avatar['file_path'])) {
            $ws12AvatarPath = $ws12User->avatar['file_path'];
        }
    }

    $ws12AvatarUrl = null;

    if ($ws12AvatarPath) {
        if (
            str_starts_with($ws12AvatarPath, 'http://')
            || str_starts_with($ws12AvatarPath, 'https://')
            || str_starts_with($ws12AvatarPath, '/storage/')
        ) {
            $ws12AvatarUrl = $ws12AvatarPath;
        } else {
            $ws12AvatarUrl = \Illuminate\Support\Facades\Storage::url(
                $ws12AvatarPath
            );
        }
    }
@endphp
<meta name="ego-workspace-avatar" content="{{ $ws12AvatarUrl ?? '' }}">
{{-- EGO_WORKSPACE_AVATAR_V121_END --}}
</head>
<body>
@php
    $userName = trim((string) ($user->name ?? 'Tài khoản')) ?: 'Tài khoản';
    $userInitial = mb_strtoupper(mb_substr($userName, 0, 1));
    $workspaceOptionsCollection = collect($workspaceOptions ?? []);
    $activeWorkspaceOption = $workspaceOptionsCollection->firstWhere('active', true);
    $activeWorkspaceLabel = (string) ($activeWorkspaceOption['label'] ?? $activeProfileLabel ?? 'Workspace');
    $activeWorkspaceIcon = (string) ($activeWorkspaceOption['icon'] ?? 'bi-grid-3x3-gap-fill');
    $canSwitchWorkspaceSafe = (bool) ($canSwitchWorkspace ?? false) && $workspaceOptionsCollection->isNotEmpty();
    $appPages = $apps->chunk(15)->values();
    $appPayload = $apps->map(fn (array $app): array => [
        'id' => (string) ($app['id'] ?? ''),
        'name' => (string) ($app['name'] ?? ''),
        'description' => (string) ($app['description'] ?? ''),
        'url' => (string) ($app['url'] ?? '#'),
        'icon' => (string) ($app['icon'] ?? 'bi-grid'),
        'tone' => (string) ($app['tone'] ?? 'blue'),
        'badge' => (int) ($app['badge'] ?? 0),
        'search' => trim((string) ($app['keywords_text'] ?? '') . ' ' . (string) ($app['name'] ?? '') . ' ' . (string) ($app['description'] ?? '')),
    ])->values();
@endphp

<div
    class="ws5"
    id="egoWorkspace"
    data-active-workspace="{{ $activeProfileKey }}"
    data-app-count="{{ $apps->count() }}"
>
    <div class="ws5-backdrop" aria-hidden="true"></div>
    <div class="ws5-vignette" aria-hidden="true"></div>

    <header class="ws5-topbar">
        <a class="ws5-brand" href="{{ route('workspace.index') }}" aria-label="{{ $brandShortName }} Workspace">
            <span class="ws5-brand__logo">
                <img src="{{ $brandLogoUrl }}" alt="{{ $brandShortName }}">
            </span>
            <span class="ws5-brand__copy">
                <strong>{{ $brandShortName }}</strong>
                <small>WORKSPACE</small>
            </span>
        </a>

        <label class="ws5-search" for="workspaceSearch">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                id="workspaceSearch"
                type="search"
                autocomplete="off"
                placeholder="Tìm ứng dụng, chức năng..."
                aria-label="Tìm ứng dụng"
            >
            <kbd>Ctrl K</kbd>
        </label>

        <div class="ws5-top-actions">
            <a class="ws5-dashboard-button" href="{{ route('dashboard') }}">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            @if($canManageWorkspace)
                <a class="ws5-icon-button" href="{{ route('workspace.settings.index') }}" title="Cấu hình Workspace" aria-label="Cấu hình Workspace">
                    <i class="bi bi-gear"></i>
                </a>
            @endif

            <a class="ws5-icon-button ws5-notification" href="{{ route('notifications.index') }}" title="Thông báo" aria-label="Thông báo">
                <i class="bi bi-bell"></i>
                <span class="ws5-notification__badge" id="workspaceNotificationBadge" hidden>0</span>
            </a>

            <button class="ws5-icon-button" id="workspaceThemeToggle" type="button" title="Đổi sắc độ nền" aria-label="Đổi sắc độ nền">
                <i class="bi bi-brightness-high" data-theme-icon></i>
            </button>

            <div class="ws5-account" id="workspaceAccount">
                <button class="ws5-account__button" type="button" id="workspaceAccountToggle" aria-expanded="false">
                    <span class="ws5-avatar">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="Ảnh đại diện {{ $userName }}">
                        @else
                            <span>{{ $userInitial }}</span>
                        @endif
                    </span>
                    <span class="ws5-account__copy">
                        <strong>{{ $userName }}</strong>
                        <small>{{ $roleLabel }}</small>
                    </span>
                    <i class="bi bi-chevron-down"></i>
                </button>

                <div class="ws5-account__menu" id="workspaceAccountMenu" hidden>
                    <div class="ws5-account__summary">
                        <span class="ws5-avatar ws5-avatar--large">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="Ảnh đại diện {{ $userName }}">
                            @else
                                <span>{{ $userInitial }}</span>
                            @endif
                        </span>
                        <div>
                            <strong>{{ $userName }}</strong>
                            <small>{{ $user->email }}</small>
                        </div>
                    </div>
                    <a href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Vào Dashboard</a>
                    @if($canManageWorkspace)
                        <a href="{{ route('workspace.settings.index') }}"><i class="bi bi-sliders2-vertical"></i> Cấu hình Workspace</a>
                    @endif
                    <a href="{{ route('users.profile') }}"><i class="bi bi-person"></i> Thông tin cá nhân</a>
                    <a href="{{ route('users.profile-edit') }}"><i class="bi bi-pencil-square"></i> Chỉnh sửa hồ sơ</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="ws5-stage">
        @if(session('success'))
            <div class="ws5-flash" role="status">
                <i class="bi bi-check-circle-fill"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        <section class="ws5-workspace-nav" aria-label="Chọn phòng ban làm việc">
            <button class="ws5-rail-arrow" id="workspaceRailPrev" type="button" aria-label="Cuộn phòng ban sang trái">
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="ws5-workspace-rail" id="workspaceDepartmentRail">
                <button class="ws5-filter-icon" id="workspaceRecentFilter" type="button" aria-pressed="false" title="Ứng dụng gần đây">
                    <i class="bi bi-clock-history"></i>
                </button>

                <button class="ws5-filter-icon" id="workspaceFavoriteFilter" type="button" aria-pressed="false" title="Ứng dụng yêu thích">
                    <i class="bi bi-star"></i>
                </button>

                <button class="ws5-filter-pill is-active" id="workspaceAllFilter" type="button" aria-pressed="true">
                    Tất cả
                </button>

                @if($canSwitchWorkspaceSafe)
                    @foreach($workspaceOptionsCollection as $workspaceOption)
                        <form method="POST" action="{{ route('workspace.context.switch') }}" class="ws5-workspace-form">
                            @csrf
                            <input type="hidden" name="workspace" value="{{ $workspaceOption['key'] }}">
                            <input type="hidden" name="redirect_to" value="workspace">
                            <button
                                type="submit"
                                class="ws5-workspace-pill {{ $workspaceOption['active'] ? 'is-active' : '' }}"
                                aria-current="{{ $workspaceOption['active'] ? 'page' : 'false' }}"
                                title="Chuyển sang {{ $workspaceOption['label'] }}"
                            >
                                <i class="bi {{ $workspaceOption['icon'] ?? 'bi-grid' }}"></i>
                                <span>{{ $workspaceOption['label'] }}</span>
                            </button>
                        </form>
                    @endforeach
                @else
                    <span class="ws5-workspace-pill is-active is-static">
                        <i class="bi {{ $activeWorkspaceIcon }}"></i>
                        <span>{{ $activeWorkspaceLabel }}</span>
                    </span>
                @endif
            </div>

            <button class="ws5-rail-arrow" id="workspaceRailNext" type="button" aria-label="Cuộn phòng ban sang phải">
                <i class="bi bi-chevron-right"></i>
            </button>
        </section>

        <section class="ws5-context">
            <div class="ws5-context__left">
                <span class="ws5-context__icon"><i class="bi {{ $activeWorkspaceIcon }}"></i></span>
                <div>
                    <small>KHÔNG GIAN ĐANG DÙNG</small>
                    <strong>{{ $activeWorkspaceLabel }}</strong>
                </div>
            </div>
            <div class="ws5-context__right">
                <span id="workspaceVisibleCount">{{ $apps->count() }}</span> ứng dụng được cấp quyền
                <span class="ws5-context__dot"></span>
                <span>{{ $companyName }}</span>
            </div>
        </section>

        <section class="ws5-app-shell" aria-label="Danh sách ứng dụng">
            <button class="ws5-page-arrow ws5-page-arrow--prev" id="workspacePagePrev" type="button" aria-label="Trang ứng dụng trước">
                <i class="bi bi-arrow-left"></i>
            </button>

            <div class="ws5-app-viewport" id="workspaceAppViewport">
                @forelse($appPages as $pageIndex => $pageApps)
                    <div class="ws5-app-page {{ $pageIndex === 0 ? 'is-active' : '' }}" data-app-page data-page-index="{{ $pageIndex }}">
                        <div class="ws5-app-grid">
                            @foreach($pageApps as $app)
                                <article
                                    class="ws5-app-card"
                                    data-app-card
                                    data-app-id="{{ $app['id'] }}"
                                    data-search="{{ $app['keywords_text'] }} {{ $app['name'] }} {{ $app['description'] }}"
                                >
                                    <a
                                        href="{{ $app['url'] }}"
                                        class="ws5-app-link"
                                        data-app-link
                                        title="{{ $app['description'] }}"
                                    >
                                        <span class="ws5-app-icon tone-{{ $app['tone'] }}">
                                            <i class="bi {{ $app['icon'] }}"></i>
                                            @if(($app['badge'] ?? 0) > 0)
                                                <span class="ws5-badge">{{ ($app['badge'] ?? 0) > 99 ? '99+' : $app['badge'] }}</span>
                                            @endif
                                        </span>
                                        <strong>{{ $app['name'] }}</strong>
                                    </a>

                                    <button
                                        type="button"
                                        class="ws5-favorite-button"
                                        data-favorite-button
                                        aria-label="Thêm {{ $app['name'] }} vào yêu thích"
                                        title="Yêu thích"
                                    >
                                        <i class="bi bi-star"></i>
                                    </button>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="ws5-empty is-visible" id="workspaceEmptyInitial">
                        <span><i class="bi bi-grid"></i></span>
                        <strong>Chưa có ứng dụng trong Workspace này</strong>
                        <p>Ứng dụng sẽ xuất hiện khi tài khoản được cấp quyền phù hợp.</p>
                    </div>
                @endforelse

                <div class="ws5-empty" id="workspaceEmpty" hidden>
                    <span><i class="bi bi-search"></i></span>
                    <strong>Không tìm thấy ứng dụng</strong>
                    <p>Thử đổi từ khóa hoặc tắt bộ lọc hiện tại.</p>
                    <button type="button" id="workspaceReset">Hiển thị tất cả</button>
                </div>
            </div>

            <button class="ws5-page-arrow ws5-page-arrow--next" id="workspacePageNext" type="button" aria-label="Trang ứng dụng tiếp theo">
                <i class="bi bi-arrow-right"></i>
            </button>
        </section>

        <section class="ws5-bottom-bar">
            <div class="ws5-app-hint" id="workspaceAppHint">
                <i class="bi bi-grid-3x3-gap"></i>
                <span>Chọn một ứng dụng để bắt đầu làm việc</span>
            </div>

            <div class="ws5-pagination" id="workspacePagination" aria-label="Phân trang ứng dụng"></div>

            <div class="ws5-filter-state" id="workspaceFilterState">
                Tất cả ứng dụng · {{ $activeWorkspaceLabel }}
            </div>
        </section>
    </main>
</div>

<div class="ws5-switch-overlay" id="workspaceSwitchOverlay" hidden>
    <span class="ws5-spinner"></span>
    <strong>Đang chuyển không gian làm việc...</strong>
</div>

<script>
window.EgoWorkspaceCinematic = {
    apps: {{ Illuminate\Support\Js::from($appPayload) }},
    activeWorkspace: {{ Illuminate\Support\Js::from($activeProfileKey) }},
    activeWorkspaceLabel: {{ Illuminate\Support\Js::from($activeWorkspaceLabel) }},
    unreadNotificationsUrl: {{ Illuminate\Support\Js::from(route('notifications.unread-count')) }},
};
</script>
<script src="{{ asset('js/ego-workspace-cinematic-v5.js') }}?v={{ file_exists(public_path('js/ego-workspace-cinematic-v5.js')) ? filemtime(public_path('js/ego-workspace-cinematic-v5.js')) : '5.0.0' }}" defer></script>


{{-- EGO_WORKSPACE_INTERACTION_V122_START --}}
<link rel="stylesheet" href="{{ asset('css/ego-workspace-carousel-smooth-v12.2.css') }}?v={{ file_exists(public_path('css/ego-workspace-carousel-smooth-v12.2.css')) ? filemtime(public_path('css/ego-workspace-carousel-smooth-v12.2.css')) : '12.2.0' }}">
<script src="{{ asset('js/ego-workspace-carousel-smooth-v12.2.js') }}?v={{ file_exists(public_path('js/ego-workspace-carousel-smooth-v12.2.js')) ? filemtime(public_path('js/ego-workspace-carousel-smooth-v12.2.js')) : '12.2.0' }}"></script>
{{-- EGO_WORKSPACE_INTERACTION_V122_END --}}
</body>
</html>
