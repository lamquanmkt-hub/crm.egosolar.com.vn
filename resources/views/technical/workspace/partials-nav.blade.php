<nav class="tw-nav" aria-label="Điều hướng Workspace Kỹ thuật">
    <div class="tw-nav-group">
        <div class="tw-nav-title">Chung</div>
        <a href="{{ route('technical-workspace.overview') }}" class="{{ request()->routeIs('technical-workspace.overview') || request()->routeIs('technical-workspace.overview.legacy') ? 'active' : '' }}">
            <i class="bi bi-speedometer2"></i><span>Tổng quan Kỹ thuật</span>
        </a>
    </div>

    <div class="tw-nav-group">
        <div class="tw-nav-title">Điều hành</div>
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
    </div>

    <div class="tw-nav-group">
        <div class="tw-nav-title">Công trình</div>
        <a href="{{ route('technical-workspace.projects.all') }}" class="{{ request()->routeIs('technical-workspace.projects.all') ? 'active' : '' }}">
            <i class="bi bi-building"></i><span>Tất cả công trình</span>
        </a>
        <a href="{{ route('technical-workspace.projects.waiting-survey') }}" class="{{ request()->routeIs('technical-workspace.projects.waiting-survey') ? 'active' : '' }}">
            <i class="bi bi-search"></i><span>Chờ khảo sát</span>
        </a>
        <a href="{{ route('technical-workspace.projects.installing') }}" class="{{ request()->routeIs('technical-workspace.projects.installing') ? 'active' : '' }}">
            <i class="bi bi-hammer"></i><span>Đang thi công</span>
        </a>
        <a href="{{ route('technical-workspace.projects.waiting-acceptance') }}" class="{{ request()->routeIs('technical-workspace.projects.waiting-acceptance') ? 'active' : '' }}">
            <i class="bi bi-check2-circle"></i><span>Chờ nghiệm thu</span>
        </a>
    </div>

    <div class="tw-nav-group">
        <div class="tw-nav-title">Điều phối</div>
        <a href="{{ route('technical-workspace.coordination.assignments') }}" class="{{ request()->routeIs('technical-workspace.coordination.assignments') ? 'active' : '' }}">
            <i class="bi bi-person-lines-fill"></i><span>Phân công nhân sự</span>
        </a>
        <a href="{{ route('technical-workspace.coordination.my-tasks') }}" class="{{ request()->routeIs('technical-workspace.coordination.my-tasks') ? 'active' : '' }}">
            <i class="bi bi-person-workspace"></i><span>Việc của tôi</span>
        </a>
    </div>

    <div class="tw-nav-group">
        <div class="tw-nav-title">Vật tư thi công</div>
        <a href="{{ route('technical-workspace.materials.index') }}" class="{{ request()->routeIs('technical-workspace.materials.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i><span>Quản lý vật tư</span>
        </a>
    </div>

    <div class="tw-nav-group">
        <div class="tw-nav-title">Hồ sơ Kỹ thuật</div>
        <a href="{{ route('technical-workspace.documents.survey') }}" class="{{ request()->routeIs('technical-workspace.documents.survey') ? 'active' : '' }}">
            <i class="bi bi-images"></i><span>Khảo sát hình ảnh</span>
        </a>
        <a href="{{ route('technical-workspace.documents.acceptance') }}" class="{{ request()->routeIs('technical-workspace.documents.acceptance') ? 'active' : '' }}">
            <i class="bi bi-clipboard-check"></i><span>Nghiệm thu</span>
        </a>
    </div>

    <div class="tw-nav-group">
        <div class="tw-nav-title">Bảo trì & Bảo hành</div>
        <a href="{{ route('technical-workspace.warranty.maintenance') }}" class="{{ request()->routeIs('technical-workspace.warranty.maintenance') ? 'active' : '' }}">
            <i class="bi bi-calendar-event"></i><span>Lịch O&M</span>
        </a>
        <a href="{{ route('technical-workspace.warranty.history') }}" class="{{ request()->routeIs('technical-workspace.warranty.history') ? 'active' : '' }}">
            <i class="bi bi-clock-history"></i><span>Lịch sử xử lý</span>
        </a>
        <a href="{{ route('ky-thuat.warranty-exchange.index') }}" class="{{ request()->routeIs('ky-thuat.warranty-exchange.*') ? 'active' : '' }}">
            <i class="bi bi-arrow-repeat"></i><span>Đổi hàng BH</span>
        </a>
    </div>

    <div class="tw-nav-group">
        <div class="tw-nav-title">Báo cáo</div>
        <a href="{{ route('technical-workspace.reports.progress') }}" class="{{ request()->routeIs('technical-workspace.reports.progress') ? 'active' : '' }}">
            <i class="bi bi-graph-up"></i><span>Tiến độ công trình</span>
        </a>
        <a href="{{ route('technical-workspace.reports.incidents') }}" class="{{ request()->routeIs('technical-workspace.reports.incidents') ? 'active' : '' }}">
            <i class="bi bi-exclamation-triangle"></i><span>Phát sinh</span>
        </a>
    </div>
</nav>
