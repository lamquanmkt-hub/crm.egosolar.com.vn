@extends('layouts.app')

@section('title', 'Công trình · EGO Solar')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/projects-unified-v1.css') }}?v={{ file_exists(public_path('css/projects-unified-v1.css')) ? filemtime(public_path('css/projects-unified-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/projects-workflow-v2.css') }}?v={{ file_exists(public_path('css/projects-workflow-v2.css')) ? filemtime(public_path('css/projects-workflow-v2.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/ego-project-list-clean.css') }}?v={{ file_exists(public_path('css/ego-project-list-clean.css')) ? filemtime(public_path('css/ego-project-list-clean.css')) : '1' }}">
<link rel="stylesheet" href="{{ asset('css/ego-project-dashboard-compact.css') }}?v={{ file_exists(public_path('css/ego-project-dashboard-compact.css')) ? filemtime(public_path('css/ego-project-dashboard-compact.css')) : '1' }}">
<link rel="stylesheet" href="{{ asset('css/project-unification-20260906.css') }}?v=1">
@endpush

@section('content')
@php
    $typeLabels = [
        'solar_farm' => 'Solar Farm',
        'factory' => 'Nhà xưởng',
        'industrial' => 'Công nghiệp',
        'large_residential' => 'Dân dụng lớn',
        'residential' => 'Dân dụng',
        'other' => 'Khác',
    ];
    $priorityLabels = ['low'=>'Thấp','normal'=>'Bình thường','high'=>'Cao','urgent'=>'Khẩn cấp'];
    $workflowToneHex = [
        'sky' => '#0ea5e9', 'violet' => '#7c3aed', 'blue' => '#2563eb',
        'amber' => '#d97706', 'teal' => '#0f9f87', 'indigo' => '#4f46e5',
        'green' => '#16a36a',
    ];
    $workflowToneClass = [
        'sky' => 'blue', 'violet' => 'purple', 'blue' => 'blue',
        'amber' => 'orange', 'teal' => 'teal', 'indigo' => 'purple',
        'green' => 'green',
    ];
    $money = fn ($value) => number_format((float) ($value ?? 0), 0, ',', '.').' đ';
    /*
     * Khung hiển thị theo đề xuất nghiệp vụ 5 bước. Các mã quy trình 7 bước
     * hiện hữu vẫn được giữ nguyên ở tầng dữ liệu để bảo toàn lịch sử.
     */
    $proposalWorkflow = [
        'survey_proposal' => [
            'sequence' => 1, 'label' => 'Khảo sát & Phương án', 'short' => 'Khảo sát & PA',
            'codes' => ['survey', 'proposal'], 'tone' => 'sky', 'icon' => 'bi-rulers',
        ],
        'contract_legal' => [
            'sequence' => 2, 'label' => 'Hợp đồng & Pháp lý', 'short' => 'HĐ & Pháp lý',
            'codes' => ['contract', 'legal'], 'tone' => 'blue', 'icon' => 'bi-file-earmark-lock',
        ],
        'materials' => [
            'sequence' => 3, 'label' => 'Đề xuất vật tư', 'short' => 'Đề xuất vật tư',
            'codes' => [], 'tone' => 'amber', 'icon' => 'bi-box-seam',
        ],
        'construction' => [
            'sequence' => 4, 'label' => 'Thi công', 'short' => 'Thi công',
            'codes' => ['construction'], 'tone' => 'teal', 'icon' => 'bi-tools',
        ],
        'acceptance' => [
            'sequence' => 5, 'label' => 'Nghiệm thu', 'short' => 'Nghiệm thu',
            'codes' => ['acceptance'], 'tone' => 'indigo', 'icon' => 'bi-clipboard2-check',
        ],
    ];
    foreach ($proposalWorkflow as $groupCode => &$group) {
        $group['count'] = collect($group['codes'])->sum(fn ($code) => (int) ($workflowCounts[$code] ?? 0));
        if ($groupCode === 'materials') {
            $group['count'] = (int) ($workflowKpis['material_pending'] ?? $workflowKpis['materials'] ?? 0);
        }
    }
    unset($group);
@endphp

<div class="pu-page">
    <div class="pu-shell">
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
        <header class="pu-panel pu-compact-hero">
            <div class="pu-compact-title">
                <div class="pu-eyebrow">Hồ sơ chung Sales · Kỹ thuật</div>
                <h1 class="pu-title">Công trình</h1>
                <p class="pu-subtitle"><strong>{{ number_format($workflowKpis['total'] ?? $kpis['total']) }}</strong> công trình đang quản lý · {{ $scopeLabel }}</p>
            </div>
            @if($canSeeRevenue && $financeSummary)
                <div class="pu-compact-finance">
                    <div><small>Giá trị hợp đồng</small><strong>{{ $money($financeSummary['contract']) }}</strong></div>
                    <div class="received"><small>Đã thu</small><strong>{{ $money($financeSummary['received']) }}</strong></div>
                    <div class="debt"><small>Còn phải thu</small><strong>{{ $money($financeSummary['debt']) }}</strong></div>
                </div>
            @endif
            <div class="pu-header-actions">
                <a class="pu-btn pu-btn-soft" href="{{ route('projects-unified.maintenance.index') }}"><i class="bi bi-shield-check"></i>Bảo trì &amp; Bảo hành</a>
                @if($canCreate)
                    <a class="pu-btn pu-btn-primary" href="{{ route('projects-unified.create') }}"><i class="bi bi-plus-lg"></i>Tạo công trình</a>
                @endif
            </div>
        </header>

        <nav class="pu-panel pu-status-strip" aria-label="Lọc nhanh theo trạng thái">
            <a href="{{ route('projects-unified.index', request()->except(['page', 'status_group', 'workflow_step', 'overdue'])) }}" class="{{ !request()->filled('status_group') && !request()->filled('workflow_step') && !request()->boolean('overdue') ? 'active all' : '' }}"><span><i class="bi bi-grid"></i>Tất cả</span><b>{{ number_format($workflowKpis['total'] ?? $kpis['total']) }}</b></a>
            @foreach($proposalWorkflow as $groupCode => $workflowDefinition)
                <a href="{{ route('projects-unified.index', array_merge(request()->except(['page', 'status_group', 'workflow_step', 'overdue']), ['status_group' => $groupCode])) }}" class="{{ request('status_group') === $groupCode ? 'active' : '' }}" style="--status-tone:{{ $workflowToneHex[$workflowDefinition['tone'] ?? 'blue'] ?? '#2385d9' }}"><span><i class="bi {{ $workflowDefinition['icon'] ?? 'bi-diagram-3' }}"></i>{{ $workflowDefinition['short'] }}</span><b>{{ number_format((int) $workflowDefinition['count']) }}</b></a>
            @endforeach
            <a href="{{ route('projects-unified.index', array_merge(request()->except(['page', 'status_group', 'workflow_step', 'overdue']), ['status_group' => 'warranty'])) }}" class="{{ request('status_group') === 'warranty' ? 'active' : '' }}" style="--status-tone:#16a36a"><span><i class="bi bi-shield-check"></i>Bảo hành</span><b>{{ number_format((int) ($workflowCounts['warranty'] ?? 0)) }}</b></a>
            <a href="{{ route('projects-unified.index', array_merge(request()->except(['page', 'status_group', 'workflow_step']), ['overdue' => 1])) }}" class="danger {{ request()->boolean('overdue') ? 'active' : '' }}" style="--status-tone:#dc3e55"><span><i class="bi bi-exclamation-triangle"></i>Quá hạn</span><b>{{ number_format((int) ($workflowKpis['overdue'] ?? 0)) }}</b></a>
        </nav>

        <section class="pu-panel pu-toolbar">
            <form method="GET" class="pu-filter" id="projectListFilterForm">
                <div class="pu-search"><i class="bi bi-search"></i><input class="pu-control" name="q" value="{{ request('q') }}" placeholder="Tìm mã, tên dự án, địa chỉ, người liên hệ..."></div>
                <select class="pu-control" name="type">
                    <option value="">Tất cả loại dự án</option>
                    @foreach($typeLabels as $key => $label)
                        <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="pu-control" name="engineer_id">
                    <option value="">Tất cả kỹ sư</option>
                    @foreach($engineers as $engineer)
                        <option value="{{ $engineer->id }}" @selected((int) request('engineer_id') === (int) $engineer->id)>{{ $engineer->name }}</option>
                    @endforeach
                </select>
                <select class="pu-control" name="company_id">
                    <option value="">Công ty đang chọn</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" @selected((int) request('company_id') === (int) $company->id)>{{ $company->code ?: $company->name }}</option>
                    @endforeach
                </select>
                <div class="pu-filter-actions">
                    <button class="pu-icon-btn is-primary" title="Lọc" type="submit"><i class="bi bi-funnel"></i></button>
                    <a class="pu-icon-btn" title="Đặt lại" href="{{ route('projects-unified.index') }}"><i class="bi bi-arrow-counterclockwise"></i></a>
                </div>
            </form>
        </section>

        <section class="pu-panel pu-table-wrap pu-simple-project-list">
            <div class="pu-table-head pu-simple-head">
                <div>Mã</div><div>Tên công trình</div><div>Khách hàng</div><div>Công suất</div>
                <div class="pu-status-heading"><select name="workflow_step" form="projectListFilterForm" onchange="document.getElementById('projectListFilterForm').requestSubmit()" aria-label="Lọc trạng thái"><option value="">Trạng thái</option>@foreach($workflowDefinitions as $key => $workflowDefinition)<option value="{{ $key }}" @selected(request('workflow_step') === $key)>{{ $workflowDefinition['short'] ?? $workflowDefinition['label'] }}</option>@endforeach</select></div>
                <div>Phụ trách</div><div>{{ $canSeeRevenue ? 'Giá trị / Công nợ' : 'Ghi chú' }}</div>
            </div>

            @forelse($projects as $site)
                @php
                    $project = $site->unified;
                    $workflowRow = $site->workflow_v2 ?? [];
                    $workflowTone = $workflowRow['tone'] ?? 'blue';
                    $rowTone = !empty($workflowRow['is_overdue']) ? '#dc3e55' : ($workflowToneHex[$workflowTone] ?? '#2385d9');
                    $statusCode = (string) ($workflowRow['code'] ?? '');
                    $statusLabel = (string) ($workflowRow['short'] ?? $workflowRow['label'] ?? $project['phase_info']['short']);
                    $capacity = (float) ($project['capacity_kwp'] ?? 0);
                    $capacityLabel = $capacity > 0 ? rtrim(rtrim(number_format($capacity, 2, ',', '.'), '0'), ',').' kWp' : '—';
                    $projectNote = $project['note'] ?: ($workflowRow['next_action'] ?? $project['next_action'] ?? '—');
                @endphp
                <article class="pu-row pu-simple-row" style="--row-tone:{{ $rowTone }}">
                    <div class="pu-cell" data-label="Mã"><a class="pu-simple-code" href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => $statusCode ?: null]) }}">{{ $project['code'] }}</a></div>
                    <div class="pu-cell" data-label="Tên công trình"><a class="pu-simple-name" href="{{ route('projects-unified.show', ['site' => $site->id, 'step' => $statusCode ?: null]) }}" title="{{ $project['name'] }}">{{ $project['name'] }}</a></div>
                    <div class="pu-cell pu-simple-customer" data-label="Khách hàng" title="{{ $project['customer'] ?: 'Chưa cập nhật khách hàng' }}">{{ $project['customer'] ?: '—' }}</div>
                    <div class="pu-cell pu-simple-capacity" data-label="Công suất">{{ $capacityLabel }}</div>
                    <div class="pu-cell" data-label="Trạng thái"><a class="pu-badge pu-status-filter {{ $workflowToneClass[$workflowTone] ?? 'blue' }}" href="{{ route('projects-unified.index', array_merge(request()->except(['page', 'workflow_step']), $statusCode !== '' ? ['workflow_step' => $statusCode] : [])) }}" title="Bấm để lọc trạng thái {{ $statusLabel }}"><i class="bi {{ $workflowRow['icon'] ?? 'bi-diagram-3' }}"></i>{{ $statusLabel }}</a><small class="pu-canonical-progress">Tiến độ {{ (int) ($workflowRow['progress'] ?? $project['progress'] ?? 0) }}%</small></div>
                    <div class="pu-cell pu-simple-engineer" data-label="Phụ trách">{{ $project['lead_engineer'] }}</div>
                    @if($canSeeRevenue)
                        @php($revenue = $revenueMap[(int) $site->id] ?? ['contract'=>0,'received'=>0,'debt'=>0])
                        <div class="pu-cell pu-canonical-revenue" data-label="Giá trị / Công nợ">
                            <strong>{{ $money($revenue['contract']) }}</strong>
                            <small>Đã thu <b>{{ $money($revenue['received']) }}</b></small>
                            <small class="debt">Còn phải thu <b>{{ $money($revenue['debt']) }}</b></small>
                        </div>
                    @else
                    <div class="pu-cell pu-simple-note {{ !empty($workflowRow['is_overdue']) ? 'is-overdue' : '' }}" data-label="Ghi chú" title="{{ $projectNote }}">{{ \Illuminate\Support\Str::limit($projectNote, 78) }}</div>
                    @endif
                </article>
            @empty
                <div class="pu-empty"><i class="bi bi-folder2-open"></i><strong>Chưa có dự án phù hợp</strong><div>Hãy thay đổi bộ lọc hoặc tạo dự án mới.</div></div>
            @endforelse

            @if($projects->hasPages())<div class="pu-pagination">{{ $projects->links() }}</div>@endif
        </section>
    </div>
</div>
@endsection
