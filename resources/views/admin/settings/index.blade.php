@extends('layouts.app')

@section('title', 'Cài đặt hệ thống')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/ego-settings-permissions.css') }}?v={{ filemtime(public_path('css/ego-settings-permissions.css')) }}">
@endpush

@section('content')
@php
    $selectedRoleId = optional($selectedRole)->id;
    $sectionRoutes = [
        'overview' => 'admin.settings.index',
        'appearance' => 'admin.settings.appearance',
        'roles' => 'admin.settings.roles',
        'pages' => 'admin.settings.pages',
        'menus' => 'admin.settings.menus',
        'actions' => 'admin.settings.actions',
        'audit' => 'admin.settings.audit',
    ];
    $sectionMeta = [
        'overview' => ['Tổng quan', 'bi-grid-1x2'],
        'appearance' => ['Giao diện & thương hiệu', 'bi-palette'],
        'roles' => ['Vai trò & nhân sự', 'bi-people'],
        'pages' => ['Phân quyền trang', 'bi-window-stack'],
        'menus' => ['Phân quyền menu', 'bi-layout-sidebar-inset'],
        'actions' => ['Quyền thao tác', 'bi-shield-check'],
        'audit' => ['Nhật ký thay đổi', 'bi-clock-history'],
    ];
@endphp

<div class="ego-settings-shell">
    <header class="ego-settings-hero">
        <div>
            <div class="ego-settings-eyebrow"><i class="bi bi-sliders"></i> SYSTEM CONTROL CENTER</div>
            <h1>Cài đặt hệ thống</h1>
            <p>Quản lý vai trò, trang được truy cập, menu được nhìn thấy và quyền thao tác độc lập.</p>
        </div>
        <div class="ego-settings-live"><span></span> Permission Engine hoạt động</div>
    </header>

    @if(session('success'))
        <div class="ego-settings-alert ego-settings-alert--success">
            <i class="bi bi-check-circle-fill"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="ego-settings-alert ego-settings-alert--danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="ego-settings-layout">
        <aside class="ego-settings-nav-card">
            <div class="ego-settings-nav-title">CẤU HÌNH HỆ THỐNG</div>
            <nav class="ego-settings-nav">
                @foreach($sectionMeta as $key => [$label, $icon])
                    <a href="{{ route($sectionRoutes[$key], $selectedRoleId ? ['role' => $selectedRoleId] : []) }}"
                       class="{{ $section === $key ? 'active' : '' }}">
                        <i class="bi {{ $icon }}"></i>
                        <span>{{ $label }}</span>
                        <i class="bi bi-chevron-right ego-settings-nav-arrow"></i>
                    </a>
                @endforeach
            </nav>

            <div class="ego-settings-principle">
                <i class="bi bi-info-circle"></i>
                <strong>Nguyên tắc phân quyền</strong>
                <span><b>Menu</b> quyết định nhìn thấy. <b>Trang</b> chặn URL. <b>Thao tác</b> kiểm soát tạo, sửa, xóa, duyệt.</span>
            </div>
        </aside>

        <main class="ego-settings-main">
            @if($section === 'overview')
                <section class="ego-settings-section-head">
                    <div>
                        <span>OVERVIEW</span>
                        <h2>Trung tâm kiểm soát quyền</h2>
                        <p>Tổng quan trạng thái phân quyền hiện tại của CRM.</p>
                    </div>
                </section>

                <div class="ego-settings-stats">
                    <article><i class="bi bi-person-badge"></i><b>{{ $stats['roles'] }}</b><span>Vai trò</span></article>
                    <article><i class="bi bi-window-stack"></i><b>{{ $stats['page_permissions'] }}</b><span>Quyền trang</span></article>
                    <article><i class="bi bi-layout-sidebar-inset"></i><b>{{ $stats['menu_permissions'] }}</b><span>Quyền menu</span></article>
                    <article><i class="bi bi-people"></i><b>{{ $stats['users'] }}</b><span>Tài khoản</span></article>
                </div>

                <div class="ego-settings-overview-grid">
                    <a href="{{ route('admin.settings.roles', $selectedRoleId ? ['role' => $selectedRoleId] : []) }}" class="ego-settings-overview-card">
                        <div class="ego-settings-overview-icon"><i class="bi bi-people"></i></div>
                        <div><h3>Vai trò & nhân sự</h3><p>Tạo role, sao chép role và gán đúng vai trò cho từng tài khoản.</p></div>
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                    <a href="{{ route('admin.settings.pages', $selectedRoleId ? ['role' => $selectedRoleId] : []) }}" class="ego-settings-overview-card">
                        <div class="ego-settings-overview-icon"><i class="bi bi-window-stack"></i></div>
                        <div><h3>Phân quyền trang</h3><p>Không có quyền thì truy cập URL trực tiếp sẽ bị chặn 403.</p></div>
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                    <a href="{{ route('admin.settings.menus', $selectedRoleId ? ['role' => $selectedRoleId] : []) }}" class="ego-settings-overview-card">
                        <div class="ego-settings-overview-icon"><i class="bi bi-layout-sidebar-inset"></i></div>
                        <div><h3>Phân quyền menu</h3><p>Chọn chính xác mục nào xuất hiện trên sidebar của từng role.</p></div>
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                    <a href="{{ route('admin.settings.actions', $selectedRoleId ? ['role' => $selectedRoleId] : []) }}" class="ego-settings-overview-card">
                        <div class="ego-settings-overview-icon"><i class="bi bi-shield-check"></i></div>
                        <div><h3>Quyền thao tác</h3><p>Phân quyền tạo, sửa, xóa, duyệt và xuất dữ liệu theo nghiệp vụ.</p></div>
                        <i class="bi bi-arrow-up-right"></i>
                    </a>
                </div>

                <div class="ego-settings-health-card">
                    <div>
                        <span class="ego-settings-health-dot"></span>
                        <div><strong>{{ $stats['managed_roles'] }}/{{ $stats['roles'] }} role đã bật kiểm soát trang</strong><small>Role mới hoặc role đã lưu quyền trang sẽ được kiểm soát ngay.</small></div>
                    </div>
                    <div class="ego-settings-health-bar"><span style="width: {{ $stats['roles'] ? round(($stats['managed_roles'] / $stats['roles']) * 100) : 0 }}%"></span></div>
                </div>

            @elseif($section === 'roles')
                <section class="ego-settings-section-head ego-settings-section-head--actions">
                    <div>
                        <span>ROLE MANAGEMENT</span>
                        <h2>Vai trò & nhân sự</h2>
                        <p>Quản lý role hệ thống và gán vai trò cho từng tài khoản.</p>
                    </div>
                    <button type="button" class="ego-settings-btn ego-settings-btn--primary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                        <i class="bi bi-plus-lg"></i> Tạo vai trò
                    </button>
                </section>

                <div class="ego-settings-role-layout">
                    <aside class="ego-settings-role-list-card">
                        <div class="ego-settings-card-title">Danh sách vai trò <span>{{ $roles->count() }}</span></div>
                        <div class="ego-settings-role-list">
                            @foreach($roles as $role)
                                <a href="{{ route('admin.settings.roles', ['role' => $role->id]) }}" class="{{ optional($selectedRole)->id === $role->id ? 'active' : '' }}">
                                    <div><strong>{{ $role->ui_name }}</strong><small>{{ $role->name }}</small></div>
                                    <span>{{ $role->users_count }}</span>
                                </a>
                            @endforeach
                        </div>
                    </aside>

                    <div class="ego-settings-stack">
                        @if($selectedRole)
                            <section class="ego-settings-card">
                                <div class="ego-settings-card-head">
                                    <div><span>ROLE PROFILE</span><h3>{{ $selectedRole->ui_name }}</h3></div>
                                    <div class="ego-settings-inline-actions">
                                        <form method="POST" action="{{ route('admin.settings.roles.clone', $selectedRole) }}">
                                            @csrf
                                            <button class="ego-settings-icon-btn" type="submit" title="Sao chép role"><i class="bi bi-copy"></i></button>
                                        </form>
                                        @if(!($selectedRole->is_system ?? false) && ($selectedRole->users_count ?? 0) === 0)
                                            <form method="POST" action="{{ route('admin.settings.roles.destroy', $selectedRole) }}" onsubmit="return confirm('Xóa vai trò này?')">
                                                @csrf @method('DELETE')
                                                <button class="ego-settings-icon-btn ego-settings-icon-btn--danger" type="submit"><i class="bi bi-trash3"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('admin.settings.roles.update', $selectedRole) }}" class="ego-settings-form-grid">
                                    @csrf @method('PUT')
                                    <label><span>Tên hiển thị</span><input name="display_name" value="{{ old('display_name', $selectedRole->display_name ?: $selectedRole->ui_name) }}" required></label>
                                    <label><span>Mã role</span><input name="name" value="{{ old('name', $selectedRole->name) }}" {{ ($selectedRole->is_system ?? false) ? 'readonly' : '' }} required></label>
                                    <label class="ego-settings-form-full"><span>Mô tả</span><textarea name="description" rows="3">{{ old('description', $selectedRole->description) }}</textarea></label>
                                    <div class="ego-settings-form-full ego-settings-form-footer">
                                        <div class="ego-settings-state {{ ($selectedRole->page_access_enabled ?? false) ? 'is-on' : '' }}"><i class="bi bi-shield-check"></i>{{ ($selectedRole->page_access_enabled ?? false) ? 'Đang kiểm soát trang' : 'Chưa bật kiểm soát trang' }}</div>
                                        <button class="ego-settings-btn ego-settings-btn--dark"><i class="bi bi-check2"></i> Lưu thông tin</button>
                                    </div>
                                </form>
                            </section>
                        @endif

                        <section class="ego-settings-card">
                            <div class="ego-settings-card-head">
                                <div><span>USER ASSIGNMENT</span><h3>Gán role nhân viên</h3></div>
                                <div class="ego-settings-search"><i class="bi bi-search"></i><input type="search" placeholder="Tìm nhân viên..." data-user-search></div>
                            </div>
                            <div class="ego-settings-users" data-user-list>
                                @foreach($users as $user)
                                    <form method="POST" action="{{ route('admin.settings.users.roles', $user) }}" class="ego-settings-user-card" data-user-name="{{ strtolower($user->name.' '.$user->email) }}">
                                        @csrf @method('PUT')
                                        <div class="ego-settings-user-head">
                                            <div class="ego-settings-avatar">{{ strtoupper(mb_substr($user->name ?: $user->email, 0, 1)) }}</div>
                                            <div><strong>{{ $user->name ?: 'Chưa đặt tên' }}</strong><small>{{ $user->email }}</small></div>
                                            <button class="ego-settings-btn ego-settings-btn--soft" type="submit">Lưu</button>
                                        </div>
                                        <div class="ego-settings-role-checks">
                                            @foreach($roles as $role)
                                                <label><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains('id', $role->id))><span>{{ $role->ui_name }}</span></label>
                                            @endforeach
                                        </div>
                                    </form>
                                @endforeach
                            </div>
                        </section>
                    </div>
                </div>

            @elseif(in_array($section, ['pages', 'menus', 'actions'], true))
                @php
                    $permissionSection = $section;
                    $isPageSection = $section === 'pages';
                    $isMenuSection = $section === 'menus';
                    $sectionTitle = $isPageSection ? 'Phân quyền trang' : ($isMenuSection ? 'Phân quyền menu' : 'Quyền thao tác nghiệp vụ');
                    $sectionDescription = $isPageSection
                        ? 'Bỏ quyền tại đây sẽ chặn truy cập URL trực tiếp bằng middleware backend.'
                        : ($isMenuSection
                            ? 'Chỉ quyết định mục nào xuất hiện trên sidebar; không thay thế quyền trang.'
                            : 'Kiểm soát tạo, sửa, xóa, duyệt, xuất dữ liệu và các thao tác chuyên môn.');
                    $saveRoute = $isPageSection
                        ? 'admin.settings.roles.pages'
                        : ($isMenuSection ? 'admin.settings.roles.menus' : 'admin.settings.roles.actions');
                    $selectedNames = $isPageSection ? $selectedPageNames : ($isMenuSection ? $selectedMenuNames : $selectedBusinessNames);
                @endphp

                <section class="ego-settings-section-head">
                    <div>
                        <span>{{ $isPageSection ? 'PAGE ACCESS' : ($isMenuSection ? 'SIDEBAR VISIBILITY' : 'BUSINESS ACTIONS') }}</span>
                        <h2>{{ $sectionTitle }}</h2>
                        <p>{{ $sectionDescription }}</p>
                    </div>
                </section>

                <div class="ego-settings-permission-layout">
                    <aside class="ego-settings-role-list-card">
                        <div class="ego-settings-card-title">Chọn vai trò</div>
                        <div class="ego-settings-role-list">
                            @foreach($roles as $role)
                                <a href="{{ route($sectionRoutes[$section], ['role' => $role->id]) }}" class="{{ optional($selectedRole)->id === $role->id ? 'active' : '' }}">
                                    <div><strong>{{ $role->ui_name }}</strong><small>{{ $role->name }}</small></div>
                                    <span>{{ $role->users_count }}</span>
                                </a>
                            @endforeach
                        </div>
                    </aside>

                    <section class="ego-settings-card">
                        <div class="ego-settings-card-head ego-settings-card-head--sticky">
                            <div>
                                <span>ĐANG CẤU HÌNH</span>
                                <h3>{{ optional($selectedRole)->ui_name }}</h3>
                            </div>
                            <div class="ego-settings-toolbar">
                                <div class="ego-settings-search"><i class="bi bi-search"></i><input type="search" placeholder="Tìm quyền..." data-permission-search></div>
                                <button type="button" class="ego-settings-btn ego-settings-btn--soft" data-check-all>Chọn tất cả</button>
                                <button type="button" class="ego-settings-btn ego-settings-btn--ghost" data-uncheck-all>Bỏ tất cả</button>
                            </div>
                        </div>

                        @if($selectedRole)
                            <form method="POST" action="{{ route($saveRoute, $selectedRole) }}" data-permission-form>
                                @csrf @method('PUT')

                                @if($isPageSection)
                                    <div class="ego-settings-explain ego-settings-explain--page"><i class="bi bi-shield-lock"></i><div><strong>Lớp bảo mật backend</strong><span>Khi lưu, role này tự bật kiểm soát trang. Người dùng nhập URL không được cấp sẽ nhận lỗi 403.</span></div></div>
                                    <div class="ego-settings-permission-grid">
                                        @foreach($pageDefinitions as $permissionName => $definition)
                                            <label class="ego-settings-permission-card" data-permission-item data-search="{{ strtolower(($definition['label'] ?? '').' '.$permissionName) }}">
                                                <input type="checkbox" name="permissions[]" value="{{ $permissionName }}" @checked(in_array($permissionName, $selectedNames, true)) @disabled($selectedRole->name === 'admin' || $permissionName === 'page.dashboard')>
                                                @if($selectedRole->name === 'admin' || $permissionName === 'page.dashboard')
                                                    <input type="hidden" name="permissions[]" value="{{ $permissionName }}">
                                                @endif
                                                <span class="ego-settings-permission-switch"></span>
                                                <i class="bi {{ $definition['icon'] ?? 'bi-window' }}"></i>
                                                <div><strong>{{ $definition['label'] ?? $permissionName }}</strong><small>{{ $definition['description'] ?? $permissionName }}</small><code>{{ $permissionName }}</code></div>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif($isMenuSection)
                                    <div class="ego-settings-explain ego-settings-explain--menu"><i class="bi bi-layout-sidebar-inset"></i><div><strong>Hiển thị sidebar độc lập</strong><span>Một mục chỉ hiện khi role có quyền menu và đồng thời có quyền trang tương ứng.</span></div></div>
                                    <div class="ego-settings-permission-grid">
                                        @foreach($menuDefinitions as $permissionName => $definition)
                                            <label class="ego-settings-permission-card" data-permission-item data-search="{{ strtolower(($definition['label'] ?? '').' '.$permissionName) }}">
                                                <input type="checkbox" name="permissions[]" value="{{ $permissionName }}" @checked(in_array($permissionName, $selectedNames, true)) @disabled($selectedRole->name === 'admin' || $permissionName === 'menu.dashboard')>
                                                @if($selectedRole->name === 'admin' || $permissionName === 'menu.dashboard')
                                                    <input type="hidden" name="permissions[]" value="{{ $permissionName }}">
                                                @endif
                                                <span class="ego-settings-permission-switch"></span>
                                                <i class="bi {{ $definition['icon'] ?? 'bi-list' }}"></i>
                                                <div><strong>{{ $definition['label'] ?? $permissionName }}</strong><small>{{ $definition['description'] ?? $permissionName }}</small><code>{{ $permissionName }} · {{ $definition['page_permission'] ?? 'không gắn trang' }}</code></div>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="ego-settings-explain"><i class="bi bi-lightning-charge"></i><div><strong>Quyền thao tác</strong><span>Quyền này không tự hiển thị menu và không tự mở trang. Nó chỉ cho phép hành động bên trong module.</span></div></div>
                                    <div class="ego-settings-action-groups">
                                        @foreach($businessGroups as $group)
                                            <details class="ego-settings-action-group" open data-permission-item data-search="{{ strtolower($group['label']) }}">
                                                <summary><span><i class="bi {{ $group['icon'] }}"></i>{{ $group['label'] }}</span><b>{{ count($group['permissions']) }}</b></summary>
                                                <div class="ego-settings-action-checks">
                                                    @foreach($group['permissions'] as $item)
                                                        <label data-permission-item data-search="{{ strtolower($item['label'].' '.$item['permission']->name) }}">
                                                            <input type="checkbox" name="permissions[]" value="{{ $item['permission']->name }}" @checked(in_array($item['permission']->name, $selectedNames, true)) @disabled($selectedRole->name === 'admin')>
                                                            @if($selectedRole->name === 'admin')
                                                                <input type="hidden" name="permissions[]" value="{{ $item['permission']->name }}">
                                                            @endif
                                                            <span><strong>{{ $item['label'] }}</strong><small>{{ $item['permission']->name }}</small></span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="ego-settings-savebar">
                                    <div><i class="bi bi-info-circle"></i> Thay đổi áp dụng ngay sau khi lưu và tải lại trang.</div>
                                    <button class="ego-settings-btn ego-settings-btn--primary"><i class="bi bi-floppy"></i> Lưu {{ strtolower($sectionTitle) }}</button>
                                </div>
                            </form>
                        @endif
                    </section>
                </div>

            @elseif($section === 'audit')
                <section class="ego-settings-section-head">
                    <div>
                        <span>SECURITY AUDIT</span>
                        <h2>Nhật ký thay đổi</h2>
                        <p>Theo dõi ai đã thay đổi role và quyền, thời điểm và đối tượng bị tác động.</p>
                    </div>
                </section>

                <section class="ego-settings-card">
                    <div class="ego-settings-audit-table-wrap">
                        <table class="ego-settings-audit-table">
                            <thead><tr><th>Thời gian</th><th>Người thao tác</th><th>Hành động</th><th>Đối tượng</th><th>IP</th></tr></thead>
                            <tbody>
                                @forelse($audits as $audit)
                                    <tr>
                                        <td>{{ optional($audit->created_at)->format('d/m/Y H:i:s') }}</td>
                                        <td>{{ optional($audit->actor)->name ?? 'Hệ thống' }}</td>
                                        <td><span class="ego-settings-audit-action">{{ $audit->action }}</span></td>
                                        <td>{{ $audit->subject_name ?: ($audit->subject_type.' #'.$audit->subject_id) }}</td>
                                        <td><code>{{ $audit->ip_address }}</code></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="ego-settings-empty">Chưa có nhật ký thay đổi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </main>
    </div>
</div>

<div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content ego-settings-modal" method="POST" action="{{ route('admin.settings.roles.store') }}">
            @csrf
            <div class="modal-header"><div><span>NEW ROLE</span><h5>Tạo vai trò mới</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label><span>Tên hiển thị</span><input name="display_name" required placeholder="Ví dụ: Chăm sóc khách hàng"></label>
                <label><span>Mã role</span><input name="name" placeholder="Tự tạo nếu để trống"></label>
                <label><span>Mô tả</span><textarea name="description" rows="3"></textarea></label>
                <label><span>Sao chép quyền từ</span><select name="clone_from"><option value="">Khởi tạo quyền cơ bản</option>@foreach($roles as $role)<option value="{{ $role->id }}">{{ $role->ui_name }}</option>@endforeach</select></label>
            </div>
            <div class="modal-footer"><button type="button" class="ego-settings-btn ego-settings-btn--ghost" data-bs-dismiss="modal">Hủy</button><button class="ego-settings-btn ego-settings-btn--primary">Tạo vai trò</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/ego-settings-permissions.js') }}?v={{ filemtime(public_path('js/ego-settings-permissions.js')) }}" defer></script>
@endpush
