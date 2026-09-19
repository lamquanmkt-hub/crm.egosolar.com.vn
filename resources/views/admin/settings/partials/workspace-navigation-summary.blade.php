@php
    $egoNavigationService = app(\App\Services\Workspace\WorkspaceNavigationService::class);
    $egoNavigationLevels = config('ego_navigation.levels', []);
    $egoNavigationWorkspaces = collect(['technical', 'sales', 'accounting', 'warehouse', 'hr', 'marketing'])
        ->mapWithKeys(fn (string $key) => [$key => $egoNavigationService->summary($key)]);
    $egoMenuDefinitions = config('role_permissions.menu_permissions', []);
    $egoAppDefinitions = collect(config('ego_workspace.apps', []))->keyBy('id');
@endphp

<section class="ewn-card">
    <div class="ewn-head">
        <div>
            <span>MENU THEO PHÒNG BAN &amp; CẤP BẬC</span>
            <h2>Nguồn điều hướng dùng chung</h2>
            <p>Workspace, menu trái và quyền trang được đồng bộ từ cùng một cấu hình. Cấp bậc quyết định phạm vi dữ liệu và thao tác quản lý.</p>
        </div>
        <span class="ewn-version">Navigation {{ config('ego_navigation.version', '1.0') }}</span>
    </div>

    <div class="ewn-grid">
        @foreach($egoNavigationWorkspaces as $workspaceKey => $workspace)
            <article class="ewn-workspace">
                <header>
                    <span class="ewn-workspace__icon"><i class="bi {{ config('ego_workspace.profiles.'.$workspaceKey.'.icon', 'bi-grid') }}"></i></span>
                    <div>
                        <strong>{{ $workspace['label'] }}</strong>
                        <small>{{ count($workspace['apps']) }} ứng dụng · {{ count($workspace['menu_permissions']) }} nhóm menu</small>
                    </div>
                </header>

                <div class="ewn-tags">
                    @foreach($workspace['apps'] as $appId)
                        @php($app = $egoAppDefinitions->get($appId))
                        @if($app)
                            <span>{{ $app['name'] }}</span>
                        @endif
                    @endforeach
                </div>

                <details>
                    <summary>Xem cấu trúc menu</summary>
                    <div class="ewn-menu-list">
                        @foreach($workspace['menu_permissions'] as $permission)
                            <span><i class="bi bi-check2"></i>{{ $egoMenuDefinitions[$permission]['label'] ?? $permission }}</span>
                        @endforeach
                    </div>
                </details>
            </article>
        @endforeach
    </div>

    <div class="ewn-levels">
        @foreach($egoNavigationLevels as $levelKey => $level)
            <div>
                <i class="bi {{ $level['icon'] ?? 'bi-person' }}"></i>
                <strong>{{ $level['label'] }}</strong>
                <small>Phạm vi: {{ match($level['scope'] ?? 'own') { 'company' => 'Toàn công ty', 'department' => 'Phòng ban', 'team' => 'Nhóm', default => 'Cá nhân' } }}</small>
            </div>
        @endforeach
    </div>
</section>
