<nav class="tw-nav" aria-label="Điều hướng Workspace Kỹ thuật">
    <a href="{{ route('technical-workspace.overview') }}" class="{{ request()->routeIs('technical-workspace.overview') ? 'active' : '' }}">
        <i class="bi bi-speedometer2"></i><span>Tổng quan</span>
    </a>
    <a href="{{ route('technical-workspace.operations.daily-report') }}" class="{{ request()->routeIs('technical-workspace.operations.daily-report') ? 'active' : '' }}">
        <i class="bi bi-clipboard-data"></i><span>Báo cáo ngày</span>
    </a>
    <a href="{{ route('technical-workspace.operations.weekly-plan') }}" class="{{ request()->routeIs('technical-workspace.operations.weekly-plan') ? 'active' : '' }}">
        <i class="bi bi-calendar2-week"></i><span>Kế hoạch tuần</span>
    </a>
    <a href="{{ route('technical-workspace.operations.installation-calendar') }}" class="{{ request()->routeIs('technical-workspace.operations.installation-calendar') ? 'active' : '' }}">
        <i class="bi bi-tools"></i><span>Lịch thi công</span>
    </a>
    <a href="{{ route('technical-workspace.operations.maintenance-calendar') }}" class="{{ request()->routeIs('technical-workspace.operations.maintenance-calendar') ? 'active' : '' }}">
        <i class="bi bi-shield-check"></i><span>Lịch bảo trì</span>
    </a>
    <a href="{{ route('ky-thuat.warranty-exchange.index') }}" class="{{ request()->routeIs('ky-thuat.warranty-exchange.*') ? 'active' : '' }}">
        <i class="bi bi-arrow-repeat"></i><span>Đổi hàng BH</span>
    </a>    <a href="{{ route('technical-workspace.materials.index') }}" class="{{ request()->routeIs('technical-workspace.materials.*') ? 'active' : '' }}">
        <i class="bi bi-box-seam"></i><span>Vật tư</span>
    </a>
</nav>
