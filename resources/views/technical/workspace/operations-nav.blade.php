<section class="tw-subnav">
    <a href="{{ route('technical-workspace.operations.daily-report') }}" class="{{ request()->routeIs('technical-workspace.operations.daily-report') ? 'active' : '' }}"><i class="bi bi-clipboard-data"></i>Báo cáo ngày</a>
    <a href="{{ route('technical-workspace.operations.weekly-plan') }}" class="{{ request()->routeIs('technical-workspace.operations.weekly-plan') ? 'active' : '' }}"><i class="bi bi-calendar-week"></i>Kế hoạch tuần</a>
    <a href="{{ route('technical-workspace.operations.installation-calendar') }}" class="{{ request()->routeIs('technical-workspace.operations.installation-calendar') ? 'active' : '' }}"><i class="bi bi-tools"></i>Lịch thi công</a>
    <a href="{{ route('technical-workspace.operations.maintenance-calendar') }}" class="{{ request()->routeIs('technical-workspace.operations.maintenance-calendar') ? 'active' : '' }}"><i class="bi bi-shield-check"></i>Lịch bảo trì</a>
</section>
