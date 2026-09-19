@extends('layouts.app')

@section('title', ($pageMode === 'sales' ? 'Công trình Sales' : 'Danh sách công trình'))

@push('styles')
<link rel="stylesheet" href="{{ asset('css/orders-pro-v2.css') }}?v={{ file_exists(public_path('css/orders-pro-v2.css')) ? filemtime(public_path('css/orders-pro-v2.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/project-list-orders-ui-v1.css') }}?v={{ file_exists(public_path('css/project-list-orders-ui-v1.css')) ? filemtime(public_path('css/project-list-orders-ui-v1.css')) : time() }}">
@endpush

@push('scripts')
<script defer src="{{ asset('js/orders-pro-v2.js') }}?v={{ file_exists(public_path('js/orders-pro-v2.js')) ? filemtime(public_path('js/orders-pro-v2.js')) : time() }}"></script>
@endpush

@section('content')
@php
    $user = auth()->user();
    $activeWorkspace = auth()->check()
        ? app(\App\Services\Workspace\WorkspaceContextService::class)->current($user)
        : 'general';
    $isTechnicalWorkspace = $activeWorkspace === 'technical'
        || str_starts_with((string) $pageMode, 'technical')
        || in_array($pageMode, ['deployment-plan', 'acceptance-handover'], true);

    $pageConfig = match ($pageMode) {
        'sales' => [
            'title' => 'Công trình Sales',
            'description' => 'Theo dõi hồ sơ Sales bàn giao, tiến độ triển khai và tình trạng xử lý công trình.',
            'route' => 'sales-projects.index',
        ],
        'technical-from-sales' => [
            'title' => 'Công trình từ Sales',
            'description' => 'Danh sách hồ sơ Sales đã bàn giao cho Phòng Kỹ thuật tiếp nhận và triển khai.',
            'route' => 'technical-projects.from-sales',
        ],
        'technical-created' => [
            'title' => 'Công trình Kỹ thuật',
            'description' => 'Công trình do Phòng Kỹ thuật trực tiếp tạo và vận hành.',
            'route' => 'technical-projects.created',
        ],
        'deployment-plan' => [
            'title' => 'Kế hoạch triển khai',
            'description' => 'Theo dõi chuẩn bị vật tư, lịch thi công và phân công đội thực hiện.',
            'route' => 'technical-projects.deployment',
        ],
        'acceptance-handover' => [
            'title' => 'Nghiệm thu & bàn giao',
            'description' => 'Theo dõi hồ sơ nghiệm thu, hoàn công, bàn giao và bảo hành.',
            'route' => 'technical-projects.handover',
        ],
        'technical-all' => [
            'title' => 'Danh sách công trình tổng',
            'description' => 'Toàn bộ công trình từ Sales và công trình Kỹ thuật tự tạo.',
            'route' => 'technical-projects.all',
        ],
        default => [
            'title' => $phaseInfo['label'] ?? 'Danh sách công trình',
            'description' => $phaseInfo['description'] ?? 'Theo dõi tiến độ, người phụ trách và mốc xử lý của toàn bộ hồ sơ công trình.',
            'route' => 'project-test.index',
        ],
    };

    $baseRoute = $pageConfig['route'];
    $canCreateSales = $user && $user->hasAnyRole(['admin', 'sales', 'sales_staff', 'sales_manager']);
    $canCreateTechnical = $user && $user->hasAnyRole(['admin', 'technical_manager', 'technical_leader']);
    $canWarehouse = $user && $user->can('project-test.warehouse');
    $canDeleteProjects = $user && (
        $user->hasAnyRole(['admin', 'management', 'manager', 'technical_manager', 'technical_leader'])
        || $user->can('project-test.admin')
    );

    $pipeline = [
        'flow-intake' => ['label' => 'Tiếp nhận', 'icon' => 'bi-inbox'],
        'flow-survey' => ['label' => 'Khảo sát', 'icon' => 'bi-geo-alt'],
        'flow-proposal' => ['label' => 'Phương án', 'icon' => 'bi-badge-3d'],
        'flow-materials' => ['label' => 'Chuẩn bị vật tư', 'icon' => 'bi-box-seam'],
        'flow-installation' => ['label' => 'Thi công', 'icon' => 'bi-tools'],
        'flow-acceptance' => ['label' => 'Nghiệm thu', 'icon' => 'bi-clipboard-check'],
        'flow-warranty' => ['label' => 'Bảo hành', 'icon' => 'bi-shield-check'],
    ];

    $sourceOptions = [
        'sales' => 'Từ Sales',
        'non-sales' => 'Kỹ thuật / nguồn khác',
    ];
    $isSourceLocked = in_array($pageMode, ['sales', 'technical-from-sales', 'technical-created'], true);
    $hasFilters = request()->hasAny(['q', 'status', 'workspace_stage', 'source_scope']);
@endphp

<div class="erp-page project-list-ui">
    <div class="erp-container">
        <div class="erp-page-head">
            <div>
                <div class="erp-breadcrumb">
                    <i class="bi bi-house-door"></i><span>/</span><span>Công trình</span><span>/</span><strong>Danh sách</strong>
                </div>
                <h1 class="erp-title">{{ $pageConfig['title'] }}</h1>
                <div class="erp-subtitle">{{ $pageConfig['description'] }}</div>
            </div>

            <div class="erp-head-actions erp-no-print">
                @if($canWarehouse && $isTechnicalWorkspace)
                    <a href="{{ route('project-test.warehouse.index') }}" class="erp-btn"><i class="bi bi-box-arrow-up-right"></i> Xuất kho</a>
                @endif

                @if($pageMode === 'sales' && $canCreateSales)
                    <a href="{{ route('sales-projects.create') }}" class="erp-btn erp-btn-primary"><i class="bi bi-plus-lg"></i> Tạo công trình</a>
                @elseif($isTechnicalWorkspace && $canCreateTechnical)
                    <a href="{{ route('technical-projects.create') }}" class="erp-btn erp-btn-primary"><i class="bi bi-plus-lg"></i> Tạo công trình</a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="erp-alert erp-alert-success"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="erp-alert erp-alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>
        @endif

        @if($isTechnicalWorkspace)
            <nav class="project-origin-nav erp-no-print" aria-label="Nguồn công trình">
                <a href="{{ route('technical-projects.from-sales') }}" class="{{ $pageMode === 'technical-from-sales' ? 'active' : '' }}"><i class="bi bi-graph-up-arrow"></i><span>Từ Sales</span><strong>{{ $kpis['from_sales'] }}</strong></a>
                <a href="{{ route('technical-projects.created') }}" class="{{ $pageMode === 'technical-created' ? 'active' : '' }}"><i class="bi bi-tools"></i><span>Kỹ thuật tạo</span><strong>{{ $kpis['technical_created'] }}</strong></a>
                <a href="{{ route('technical-projects.all') }}" class="{{ $pageMode === 'technical-all' ? 'active' : '' }}"><i class="bi bi-grid"></i><span>Danh sách tổng</span></a>
                <a href="{{ route('technical-projects.deployment') }}" class="{{ $pageMode === 'deployment-plan' ? 'active' : '' }}"><i class="bi bi-calendar2-week"></i><span>Kế hoạch triển khai</span></a>
                <a href="{{ route('technical-projects.handover') }}" class="{{ $pageMode === 'acceptance-handover' ? 'active' : '' }}"><i class="bi bi-clipboard-check"></i><span>Nghiệm thu & bàn giao</span></a>
            </nav>
        @endif

        @if(!in_array($pageMode, ['deployment-plan', 'acceptance-handover'], true))
            <nav class="project-stage-strip erp-no-print" aria-label="Quy trình công trình">
                <a href="{{ route($baseRoute) }}" class="{{ ($workspaceStage ?? '') === '' ? 'active' : '' }}">
                    <span>Tất cả</span><strong>{{ $kpis['total'] }}</strong>
                </a>
                @foreach($pipeline as $stageKey => $stage)
                    <a href="{{ route($baseRoute, ['workspace_stage' => $stageKey]) }}" class="{{ ($workspaceStage ?? '') === $stageKey ? 'active' : '' }}">
                        <i class="bi {{ $stage['icon'] }}"></i><span>{{ $stage['label'] }}</span><strong>{{ $stageCounts[$stageKey] ?? 0 }}</strong>
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="erp-stats">
            <div class="erp-stat blue">
                <div class="erp-stat-icon"><i class="bi bi-kanban"></i></div>
                <div><div class="erp-stat-label">Tổng hồ sơ</div><div class="erp-stat-value">{{ number_format($kpis['total']) }}</div><div class="erp-stat-note">Trong phạm vi được phép xem</div></div>
            </div>

            @if($showFinancialsList ?? false)
                <div class="erp-stat">
                    <div class="erp-stat-icon"><i class="bi bi-cash-stack"></i></div>
                    <div><div class="erp-stat-label">Doanh thu công trình</div><div class="erp-stat-value project-money-stat">{{ number_format((float)($financeKpis['revenue'] ?? 0), 0, ',', '.') }} đ</div><div class="erp-stat-note">Theo danh sách hiện tại</div></div>
                </div>
            @elseif($isTechnicalWorkspace)
                <div class="erp-stat">
                    <div class="erp-stat-icon"><i class="bi bi-arrow-left-right"></i></div>
                    <div><div class="erp-stat-label">Nguồn công trình</div><div class="erp-stat-value">{{ $kpis['from_sales'] }} / {{ $kpis['technical_created'] }}</div><div class="erp-stat-note">Sales bàn giao / Kỹ thuật tạo</div></div>
                </div>
            @else
                <div class="erp-stat">
                    <div class="erp-stat-icon"><i class="bi bi-person-check"></i></div>
                    <div><div class="erp-stat-label">Của tôi</div><div class="erp-stat-value">{{ number_format($kpis['mine']) }}</div><div class="erp-stat-note">Tạo, phụ trách hoặc được phân công</div></div>
                </div>
            @endif

            <div class="erp-stat amber">
                <div class="erp-stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div><div class="erp-stat-label">Chờ xử lý</div><div class="erp-stat-value">{{ number_format($kpis['waiting']) }}</div><div class="erp-stat-note">Đang chờ đơn vị phụ trách xử lý</div></div>
            </div>

            <div class="erp-stat red">
                <div class="erp-stat-icon"><i class="bi bi-tools"></i></div>
                <div><div class="erp-stat-label">Thi công / nghiệm thu</div><div class="erp-stat-value">{{ number_format($kpis['installing']) }}</div><div class="erp-stat-note">Đang triển khai hoặc chờ nghiệm thu</div></div>
            </div>
        </div>

        <form method="GET" action="{{ route($baseRoute) }}" class="erp-card erp-filter erp-no-print">
            <div class="erp-card-body">
                <div class="project-filter-grid">
                    <div>
                        <label class="erp-label">Tìm kiếm</label>
                        <input class="erp-input" type="search" name="q" value="{{ request('q') }}" placeholder="Mã, tên, khách hàng, địa chỉ, hợp đồng...">
                    </div>
                    <div>
                        <label class="erp-label">Giai đoạn</label>
                        <select class="erp-select" name="workspace_stage">
                            <option value="">Tất cả giai đoạn</option>
                            @foreach($pipeline as $stageKey => $stage)
                                <option value="{{ $stageKey }}" @selected(($workspaceStage ?? '') === $stageKey)>{{ $stage['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="erp-label">Trạng thái</label>
                        <select class="erp-select" name="status">
                            <option value="">Tất cả trạng thái</option>
                            @foreach($statuses as $key => $statusInfo)
                                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $statusInfo['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="erp-label">Nguồn công trình</label>
                        @if($isSourceLocked)
                            <input type="hidden" name="source_scope" value="{{ $sourceScope }}">
                            <input class="erp-input" value="{{ $sourceScope === 'sales' ? 'Từ Sales' : 'Kỹ thuật / nguồn khác' }}" disabled>
                        @else
                            <select class="erp-select" name="source_scope">
                                <option value="">Tất cả nguồn</option>
                                @foreach($sourceOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($sourceScope === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                    <div class="project-filter-actions">
                        <button class="erp-btn erp-btn-primary" type="submit"><i class="bi bi-search"></i> Lọc</button>
                        @if($hasFilters)
                            <a class="erp-btn" href="{{ route($baseRoute) }}" title="Xóa bộ lọc"><i class="bi bi-x-lg"></i></a>
                        @endif
                    </div>
                </div>
            </div>
        </form>

        <div class="erp-card erp-desktop-table project-desktop-table">
            <div class="erp-card-head">
                <div>
                    <h2 class="erp-card-title">Danh sách công trình</h2>
                    <div class="erp-card-sub">{{ number_format($projects->total()) }} hồ sơ phù hợp · {{ $roleLabel }}</div>
                </div>
            </div>
            <div class="erp-table-wrap">
                <table class="erp-table project-table">
                    <thead>
                        <tr>
                            <th>Mã & công trình</th>
                            <th>Khách hàng / địa điểm</th>
                            <th>Nguồn</th>
                            <th>Giai đoạn</th>
                            <th>Tiến độ</th>
                            @if($showFinancialsList ?? false)<th>Doanh thu</th>@endif
                            <th>Phụ trách</th>
                            <th>Mốc gần nhất</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($projects as $project)
                            @php
                                $statusInfo = $statuses[$project->status] ?? ['label' => $project->status ?: 'Chưa xác định', 'group' => 'Khác'];
                                $group = (string)($statusInfo['group'] ?? 'Khác');
                                $statusTone = match ($group) {
                                    'Tiếp nhận' => 'blue',
                                    'Khảo sát' => 'teal',
                                    'Phương án' => 'purple',
                                    'Vật tư' => 'amber',
                                    'Thi công' => 'blue',
                                    'Nghiệm thu' => 'purple',
                                    'Bảo hành' => 'green',
                                    'Dừng' => 'red',
                                    default => 'gray',
                                };
                                $isSalesSource = ($project->request_source ?? 'sales') === 'sales';
                                $sourceLabel = $requestSources[$project->request_source ?? 'sales'] ?? ($project->request_source ?: 'Chưa xác định');
                                $typeLabel = $projectTypes[$project->project_type ?? 'commercial'] ?? ($project->project_type ?: 'Chưa xác định');
                                $ownerName = $project->leadTechnician?->name ?: $project->technicalManager?->name ?: 'Chưa phân công';
                                $milestoneAt = $project->proposed_installation_at ?: $project->proposed_survey_at;
                                $progress = max(0, min(100, (int)($project->progress ?? ($statusInfo['progress'] ?? 0))));
                                $customerName = $project->contact_name ?: $project->name;
                                $customerPhone = $project->contact_phone ?: 'Chưa có số điện thoại';
                                $revenue = (float)$project->contract_amount + (float)$project->extra_revenue;
                                $remaining = max(0, $revenue - (float)$project->amount_collected);
                            @endphp
                            <tr>
                                <td>
                                    <a class="erp-code" href="{{ route('project-test.show', $project) }}">{{ $project->code }}</a>
                                    <button class="erp-btn erp-icon-btn erp-btn-sm" type="button" data-copy="{{ $project->code }}" title="Sao chép mã"><i class="bi bi-copy"></i></button>
                                    <div class="erp-primary-text project-name">{{ $project->name }}</div>
                                    <div class="erp-secondary-text">ID #{{ $project->id }}@if($project->salesOrder) · Đơn {{ $project->salesOrder->order_code }}@elseif($project->contract_reference) · HĐ {{ $project->contract_reference }}@endif</div>
                                </td>
                                <td>
                                    <div class="erp-primary-text">{{ $customerName }}</div>
                                    <div class="erp-secondary-text">{{ $customerPhone }}</div>
                                    <div class="erp-secondary-text project-address">{{ $project->address ?: 'Chưa có địa chỉ' }}</div>
                                </td>
                                <td>
                                    <span class="erp-badge {{ $isSalesSource ? 'blue' : 'teal' }}">{{ $isSalesSource ? 'Từ Sales' : 'Kỹ thuật tạo' }}</span>
                                    <div class="erp-secondary-text">{{ $sourceLabel }}</div>
                                    <div class="erp-secondary-text">{{ $typeLabel }}</div>
                                </td>
                                <td>
                                    <span class="erp-badge {{ $statusTone }}">{{ $group }}</span>
                                    <div class="erp-secondary-text project-status-text">{{ $statusInfo['label'] }}</div>
                                </td>
                                <td>
                                    <div class="erp-primary-text">{{ $progress }}%</div>
                                    <div class="erp-progress"><div class="erp-progress-bar" style="width:{{ $progress }}%"></div></div>
                                    <div class="erp-progress-label"><span>Tiến độ workflow</span><span>{{ $progress }}/100</span></div>
                                </td>
                                @if($showFinancialsList ?? false)
                                    <td>
                                        <div class="erp-money">{{ number_format($revenue, 0, ',', '.') }} đ</div>
                                        <div class="erp-secondary-text">Đã thu {{ number_format((float)$project->amount_collected, 0, ',', '.') }} đ</div>
                                        <div class="erp-secondary-text {{ $remaining > 0 ? 'project-debt' : 'project-paid' }}">Còn {{ number_format($remaining, 0, ',', '.') }} đ</div>
                                    </td>
                                @endif
                                <td>
                                    <div class="erp-primary-text">{{ $ownerName }}</div>
                                    @if($isSalesSource && $project->salesUser)
                                        <div class="erp-secondary-text">Sales: {{ $project->salesUser->name }}</div>
                                    @else
                                        <div class="erp-secondary-text">{{ $isSalesSource ? 'Chưa xác định Sales' : 'Không qua Sales' }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="erp-primary-text">{{ $milestoneAt ? $milestoneAt->format('d/m/Y H:i') : 'Chưa có lịch' }}</div>
                                    <div class="erp-secondary-text">{{ $project->latestMaterialRequest?->code ? 'VT: '.$project->latestMaterialRequest->code : 'Chưa có phiếu vật tư' }}</div>
                                </td>
                                <td>
                                    <div class="erp-actions-inline">
                                        <a class="erp-btn erp-icon-btn" href="{{ route('project-test.show', $project) }}" title="Xem hồ sơ"><i class="bi bi-eye"></i></a>
                                        <div class="erp-menu">
                                            <button class="erp-btn erp-icon-btn" type="button" data-erp-menu><i class="bi bi-three-dots-vertical"></i></button>
                                            <div class="erp-menu-panel">
                                                <a class="erp-menu-item" href="{{ route('project-test.show', $project) }}"><i class="bi bi-folder2-open"></i> Xem hồ sơ</a>
                                                <a class="erp-menu-item" href="{{ route('project-test.show', $project) }}?tab=materials&material_view=proposal"><i class="bi bi-box-seam"></i> Đề nghị cấp vật tư</a>
                                                <a class="erp-menu-item" href="{{ route('project-test.show', $project) }}?tab=history"><i class="bi bi-clock-history"></i> Lịch sử xử lý</a>
                                                @if($canDeleteProjects)
                                                    <form method="POST" action="{{ route('project-test.destroy', $project) }}" onsubmit="return confirm('Bạn chắc chắn muốn xóa công trình này?\n\nCông trình sẽ bị ẩn khỏi danh sách nhưng dữ liệu vẫn được giữ dạng xóa mềm để đối soát.');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="erp-menu-item danger" type="submit"><i class="bi bi-trash3"></i> Xóa công trình</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ ($showFinancialsList ?? false) ? 9 : 8 }}"><div class="erp-empty"><div class="erp-empty-icon"><i class="bi bi-inboxes"></i></div>Không có công trình phù hợp.</div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($projects->hasPages())
                <div class="erp-pagination">{{ $projects->appends(request()->query())->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>

        <div class="erp-mobile-list project-mobile-list">
            @forelse($projects as $project)
                @php
                    $statusInfo = $statuses[$project->status] ?? ['label' => $project->status ?: 'Chưa xác định', 'group' => 'Khác'];
                    $group = (string)($statusInfo['group'] ?? 'Khác');
                    $statusTone = match ($group) {
                        'Tiếp nhận' => 'blue', 'Khảo sát' => 'teal', 'Phương án' => 'purple',
                        'Vật tư' => 'amber', 'Thi công' => 'blue', 'Nghiệm thu' => 'purple',
                        'Bảo hành' => 'green', 'Dừng' => 'red', default => 'gray',
                    };
                    $progress = max(0, min(100, (int)($project->progress ?? ($statusInfo['progress'] ?? 0))));
                    $ownerName = $project->leadTechnician?->name ?: $project->technicalManager?->name ?: 'Chưa phân công';
                    $milestoneAt = $project->proposed_installation_at ?: $project->proposed_survey_at;
                @endphp
                <div class="erp-mobile-card">
                    <div class="erp-mobile-top">
                        <div>
                            <a class="erp-code" href="{{ route('project-test.show', $project) }}">{{ $project->code }}</a>
                            <div class="erp-primary-text project-mobile-name">{{ $project->name }}</div>
                        </div>
                        <span class="erp-badge {{ $statusTone }}">{{ $group }}</span>
                    </div>
                    <div class="erp-secondary-text project-address">{{ $project->address ?: 'Chưa có địa chỉ' }}</div>
                    <div class="erp-progress"><div class="erp-progress-bar" style="width:{{ $progress }}%"></div></div>
                    <div class="erp-progress-label"><span>{{ $statusInfo['label'] }}</span><strong>{{ $progress }}%</strong></div>
                    <div class="erp-mobile-grid">
                        <div><div class="erp-mobile-k">Nguồn</div><div class="erp-mobile-v">{{ ($project->request_source ?? 'sales') === 'sales' ? 'Từ Sales' : 'Kỹ thuật tạo' }}</div></div>
                        <div><div class="erp-mobile-k">Phụ trách</div><div class="erp-mobile-v">{{ $ownerName }}</div></div>
                        <div><div class="erp-mobile-k">Mốc gần nhất</div><div class="erp-mobile-v">{{ $milestoneAt ? $milestoneAt->format('d/m/Y H:i') : 'Chưa có lịch' }}</div></div>
                        <div><div class="erp-mobile-k">Vật tư</div><div class="erp-mobile-v">{{ $project->latestMaterialRequest?->code ?: 'Chưa có phiếu' }}</div></div>
                    </div>
                    <div class="project-mobile-actions">
                        <a class="erp-btn erp-btn-primary" href="{{ route('project-test.show', $project) }}"><i class="bi bi-eye"></i> Xem hồ sơ</a>
                        <a class="erp-btn" href="{{ route('project-test.show', $project) }}?tab=materials&material_view=proposal"><i class="bi bi-box-seam"></i></a>
                        @if($canDeleteProjects)
                            <form method="POST" action="{{ route('project-test.destroy', $project) }}" onsubmit="return confirm('Bạn chắc chắn muốn xóa công trình này? Công trình sẽ được xóa mềm để bảo toàn lịch sử.');">
                                @csrf
                                @method('DELETE')
                                <button class="erp-btn erp-btn-danger" type="submit" title="Xóa công trình"><i class="bi bi-trash3"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="erp-empty">Không có công trình phù hợp.</div>
            @endforelse

            @if($projects->hasPages())
                <div class="erp-pagination">{{ $projects->appends(request()->query())->links('pagination::bootstrap-5') }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
