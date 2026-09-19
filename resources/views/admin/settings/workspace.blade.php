@extends('layouts.app')

@section('title', 'Workspace theo phòng ban & cấp bậc')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-workspace-matrix.css') }}?v={{ file_exists(public_path('css/ego-workspace-matrix.css')) ? filemtime(public_path('css/ego-workspace-matrix.css')) : '3.0.0' }}">
    <link rel="stylesheet" href="{{ asset('css/ego-workspace-navigation-v1.css') }}?v={{ file_exists(public_path('css/ego-workspace-navigation-v1.css')) ? filemtime(public_path('css/ego-workspace-navigation-v1.css')) : '1.0.0' }}">
@endpush

@section('content')
@php
    $allAppIds = $apps->pluck('id')->map(fn ($id) => (string) $id)->all();
    $selectedByProfile = [];
    foreach ($profiles as $profileKey => $profile) {
        $selectedByProfile[$profileKey] = ($profile['apps'] ?? []) === ['*']
            ? $allAppIds
            : array_values(array_unique(array_merge($profile['apps'] ?? [], $requiredAppIds)));
    }
    $isSystemAdmin = auth()->user()?->hasRole('admin') ?? false;
    $settingsHome = $isSystemAdmin && \Illuminate\Support\Facades\Route::has('admin.settings.index')
        ? route('admin.settings.index')
        : route('workspace.index');
@endphp

<div class="ewm-shell">
    <header class="ewm-hero">
        <div class="ewm-hero__icon"><i class="bi bi-grid-3x3-gap-fill"></i></div>
        <div class="ewm-hero__copy">
            <span>CÀI ĐẶT WORKSPACE</span>
            <h1>Workspace theo phòng ban &amp; cấp bậc</h1>
            <p>Chọn ứng dụng cho từng phòng ban; menu trái và quyền trang sẽ tự đồng bộ theo Workspace đang sử dụng.</p>
        </div>
        <div class="ewm-hero__actions">
            <a href="{{ route('workspace.index', ['preview' => 'management']) }}" class="ewm-btn ewm-btn--soft" target="_blank">
                <i class="bi bi-eye"></i> Xem thử
            </a>
            <a href="{{ $settingsHome }}" class="ewm-btn ewm-btn--soft">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </header>

    <nav class="ewm-settings-nav">
        @if($isSystemAdmin && \Illuminate\Support\Facades\Route::has('admin.settings.index'))
            <a href="{{ route('admin.settings.index') }}"><i class="bi bi-grid-1x2"></i>Tổng quan</a>
            <a href="{{ route('admin.settings.appearance') }}"><i class="bi bi-palette"></i>Giao diện</a>
            <a href="{{ route('admin.settings.roles') }}"><i class="bi bi-people"></i>Vai trò</a>
            <a href="{{ route('admin.settings.menus') }}"><i class="bi bi-layout-sidebar"></i>Phân quyền menu</a>
        @endif
        <a class="active" href="{{ route('admin.settings.workspace') }}"><i class="bi bi-grid-3x3-gap"></i>Ứng dụng theo vai trò</a>
    </nav>

    @if(session('success'))
        <div class="ewm-alert ewm-alert--success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
    @endif
    @if(session('error'))
        <div class="ewm-alert ewm-alert--danger"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ session('error') }}</span></div>
    @endif
    @if($errors->any())
        <div class="ewm-alert ewm-alert--danger"><i class="bi bi-exclamation-triangle-fill"></i><span>{{ $errors->first() }}</span></div>
    @endif

    <section class="ewm-info-strip">
        <div><i class="bi bi-check2-square"></i><strong>Tích ô = hiện ứng dụng trong Workspace</strong></div>
        <div><i class="bi bi-shield-lock"></i><strong>Quyền <code>page.*</code> vẫn bảo vệ URL thật</strong></div>
        <div><i class="bi bi-clock-history"></i><strong>Chấm công luôn bắt buộc cho mọi tài khoản</strong></div>
    </section>

    @include('admin.settings.partials.workspace-navigation-summary')

    <form method="POST" action="{{ route('admin.settings.workspace.update') }}" id="workspaceMatrixForm">
        @csrf
        @method('PUT')

        <section class="ewm-card ewm-card--matrix">
            <div class="ewm-card__head">
                <div>
                    <span>MA TRẬN HIỂN THỊ</span>
                    <h2>Chọn ứng dụng cho từng vai trò</h2>
                    <p>Có thể chọn theo từng ô, chọn cả hàng ứng dụng hoặc chọn cả cột vai trò.</p>
                </div>
                <div class="ewm-toolbar">
                    <label class="ewm-search">
                        <i class="bi bi-search"></i>
                        <input type="search" placeholder="Tìm ứng dụng..." data-matrix-search>
                    </label>
                    <select data-category-filter aria-label="Lọc nhóm ứng dụng">
                        <option value="all">Tất cả nhóm</option>
                        @foreach($categories as $categoryKey => $categoryLabel)
                            @if($categoryKey !== 'all')
                                <option value="{{ $categoryKey }}">{{ $categoryLabel }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="ewm-matrix-scroll">
                <table class="ewm-matrix" aria-label="Ma trận ứng dụng theo vai trò">
                    <thead>
                    <tr>
                        <th class="ewm-matrix__app-head">
                            <div><strong>Ứng dụng / Chức năng</strong><small>{{ $apps->count() }} ứng dụng</small></div>
                        </th>
                        @foreach($profiles as $profileKey => $profile)
                            <th data-profile-column="{{ $profileKey }}">
                                <div class="ewm-role-head">
                                    <span class="ewm-role-icon ewm-role-icon--{{ $profileKey }}"><i class="bi {{ $profile['icon'] ?? 'bi-person' }}"></i></span>
                                    <strong>{{ $profile['label'] }}</strong>
                                    <small>
                                        @if(($profileUserCounts[$profileKey] ?? null) !== null)
                                            {{ $profileUserCounts[$profileKey] }} tài khoản
                                        @else
                                            {{ $profileKey === 'general' ? 'Chưa xác định' : implode(', ', $profile['role_names'] ?? []) }}
                                        @endif
                                    </small>
                                    <button type="button" class="ewm-column-toggle" data-column-toggle="{{ $profileKey }}" title="Chọn hoặc bỏ toàn bộ ứng dụng của vai trò này">
                                        <i class="bi bi-check2-all"></i><span>Chọn tất cả</span>
                                    </button>
                                    <a href="{{ route('workspace.index', ['preview' => $profileKey]) }}" target="_blank" class="ewm-preview-link"><i class="bi bi-eye"></i>Xem thử</a>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($apps as $app)
                        @php($isRequired = in_array((string) $app['id'], $requiredAppIds, true))
                        <tr data-app-row data-category="{{ $app['category'] }}" data-search="{{ mb_strtolower($app['name'].' '.$app['description'].' '.$app['category_label']) }}">
                            <th scope="row">
                                <div class="ewm-app-cell">
                                    <span class="ewm-app-icon"><i class="bi {{ $app['icon'] }}"></i></span>
                                    <div>
                                        <strong>{{ $app['name'] }}</strong>
                                        <small>{{ $app['category_label'] }} · {{ $app['description'] }}</small>
                                        @if($isRequired)
                                            <em><i class="bi bi-lock-fill"></i>Bắt buộc</em>
                                        @endif
                                    </div>
                                    @unless($isRequired)
                                        <button type="button" data-row-toggle="{{ $app['id'] }}" title="Chọn ứng dụng này cho tất cả vai trò"><i class="bi bi-check2-all"></i></button>
                                    @endunless
                                </div>
                            </th>
                            @foreach($profiles as $profileKey => $profile)
                                <td>
                                    @if($isRequired)
                                        <input type="hidden" name="profiles[{{ $profileKey }}][apps][]" value="{{ $app['id'] }}">
                                    @endif
                                    <label class="ewm-check {{ $isRequired ? 'is-required' : '' }}" title="{{ $profile['label'] }}: {{ $app['name'] }}">
                                        @if($isRequired)
                                            <input
                                                type="checkbox"
                                                value="{{ $app['id'] }}"
                                                data-app-id="{{ $app['id'] }}"
                                                data-profile-key="{{ $profileKey }}"
                                                checked
                                                disabled
                                            >
                                        @else
                                            <input
                                                type="checkbox"
                                                name="profiles[{{ $profileKey }}][apps][]"
                                                value="{{ $app['id'] }}"
                                                data-app-id="{{ $app['id'] }}"
                                                data-profile-key="{{ $profileKey }}"
                                                @checked(in_array((string) $app['id'], $selectedByProfile[$profileKey], true))
                                            >
                                        @endif
                                        <span><i class="bi bi-check-lg"></i></span>
                                    </label>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="ewm-matrix-legend">
                <span><i class="bi bi-check-square-fill"></i> Được hiển thị</span>
                <span><i class="bi bi-square"></i> Không hiển thị</span>
                <span><i class="bi bi-lock-fill"></i> Bắt buộc</span>
            </div>
        </section>

        <section class="ewm-card">
            <div class="ewm-card__head">
                <div>
                    <span>HIỂN THỊ KHI MỚI VÀO</span>
                    <h2>Tab mặc định và truy cập nhanh</h2>
                    <p>Mỗi vai trò được chọn một tab mở mặc định và tối đa 3 ứng dụng truy cập nhanh.</p>
                </div>
            </div>

            <div class="ewm-profile-options-grid">
                @foreach($profiles as $profileKey => $profile)
                    <article class="ewm-profile-option" data-profile-option="{{ $profileKey }}">
                        <header>
                            <span class="ewm-role-icon ewm-role-icon--{{ $profileKey }}"><i class="bi {{ $profile['icon'] ?? 'bi-person' }}"></i></span>
                            <div><strong>{{ $profile['label'] }}</strong><small data-profile-count="{{ $profileKey }}">0 ứng dụng đang bật</small></div>
                        </header>

                        <label>
                            <span>Tab mở mặc định</span>
                            <select name="profiles[{{ $profileKey }}][default_category]">
                                @foreach($categories as $categoryKey => $categoryLabel)
                                    <option value="{{ $categoryKey }}" @selected(($profile['default_category'] ?? 'all') === $categoryKey)>{{ $categoryLabel }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="ewm-featured-fields">
                            <span>Truy cập nhanh</span>
                            @for($slot = 0; $slot < 3; $slot++)
                                <select name="profiles[{{ $profileKey }}][featured][]" data-featured-select="{{ $profileKey }}">
                                    <option value="">— Chọn ứng dụng {{ $slot + 1 }} —</option>
                                    @foreach($apps as $app)
                                        <option value="{{ $app['id'] }}" @selected(($profile['featured'][$slot] ?? null) === $app['id'])>{{ $app['name'] }}</option>
                                    @endforeach
                                </select>
                            @endfor
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="ewm-savebar">
            <div><strong data-total-selection>Đang kiểm tra cấu hình...</strong><small>Thay đổi chỉ ảnh hưởng màn hình Workspace; quyền trang thật vẫn giữ nguyên.</small></div>
            <div class="ewm-savebar__actions">
                <button type="submit" form="workspaceMatrixResetForm" class="ewm-btn ewm-btn--danger" onclick="return confirm('Khôi phục cấu hình Workspace mặc định?')">
                    <i class="bi bi-arrow-counterclockwise"></i> Khôi phục
                </button>
                <button type="submit" class="ewm-btn ewm-btn--primary"><i class="bi bi-check2-circle"></i>Lưu ma trận</button>
            </div>
        </div>
    </form>

    <form id="workspaceMatrixResetForm" method="POST" action="{{ route('admin.settings.workspace.reset') }}" hidden>
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ego-workspace-matrix.js') }}?v={{ file_exists(public_path('js/ego-workspace-matrix.js')) ? filemtime(public_path('js/ego-workspace-matrix.js')) : '3.0.0' }}" defer></script>
@endpush
