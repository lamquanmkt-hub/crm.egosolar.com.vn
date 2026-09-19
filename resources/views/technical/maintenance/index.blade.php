@extends('layouts.app')

@section('title', 'Bảo hành & O&M')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v10.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v10.css')) ? filemtime(public_path('css/technical-maintenance-v10.css')) : time() }}">
@endsection

@section('content')
@php
    $groups = collect($schedules->items())
        ->groupBy(function ($item) {
            if ($item->maintenance_profile_id) return 'profile-'.$item->maintenance_profile_id;
            if ($item->site_id) return 'site-'.$item->site_id.'-'.$item->type;
            if ($item->round_group) return 'group-'.$item->round_group;
            return 'single-'.$item->id;
        })
        ->map(function ($items) {
            $items = $items->sortBy(fn($s) => [$s->round_no ?: 1, optional($s->scheduled_date)->format('Ymd') ?: '99999999'])->values();
            $validCompletion = fn($s) => $s->status === 'completed'
                || ($s->status === 'approved' && $s->approval_status === 'approved');
            $current = $items->first(fn($s) => !$validCompletion($s) && $s->status !== 'cancelled') ?: $items->last();
            $completed = $items->filter($validCompletion)->count();
            return [
                'items' => $items,
                'current' => $current,
                'completed' => $completed,
                'total' => max((int)($items->first()?->total_rounds ?: 1), $items->count()),
            ];
        })->values();

    $statusLabel = function ($s) {
        return match($s->status) {
            'unassigned','scheduled','draft' => ['Kế hoạch','plan'],
            'assigned','customer_confirmed','travelling' => ['Đã phân công','assigned'],
            'in_progress','waiting_material' => ['Đang thực hiện','doing'],
            'waiting_submission' => ['Sẵn sàng hoàn tất','report'],
            'pending_approval' => ['Hồ sơ cũ chờ hoàn tất','approval'],
            'revision_requested' => ['Cần bổ sung','revision'],
            'completed','approved' => ['Hoàn thành','done'],
            'cancelled' => ['Đã hủy','cancel'],
            default => [$statuses[$s->status] ?? $s->status,'plan'],
        };
    };

    $activeType = request('type');
    $archiveMode = request('view') === 'completed';
    $showWaitingProfiles = !$archiveMode
        && in_array(request('view','all'), ['all','needs_action'], true)
        && !request('q') && !$activeType && !request('assignee_id');
    $lanes = [
        'planned' => collect(),
        'doing' => collect(),
        'approval' => collect(),
        'attention' => collect(),
    ];
    foreach ($groups as $group) {
        $current = $group['current'];
        if ($current->status === 'revision_requested') {
            $lanes['attention']->push($group);
        } elseif ($current->status === 'pending_approval' || $current->approval_status === 'pending') {
            $lanes['approval']->push($group);
        } elseif (in_array($current->status, ['assigned','customer_confirmed','travelling','in_progress','waiting_material','waiting_submission'], true)) {
            $lanes['doing']->push($group);
        } else {
            $lanes['planned']->push($group);
        }
    }
@endphp

<div class="ego-container om10-page om13-operations-page">
    <header class="om13-ops-header">
        <div><div class="om10-eyebrow">BẢO HÀNH & O&M</div><h1>{{ $archiveMode ? 'Hồ sơ đã hoàn thành' : 'Điều hành đợt bảo hành' }}</h1><p>{{ $archiveMode ? 'Tra cứu các đợt bảo trì đã hoàn thành.' : 'Tổ chức công việc theo trạng thái để biết việc nào cần hành động tiếp theo.' }}</p></div>
        <div class="om10-head-actions">
            @if($permissions['admin'])<a class="om10-btn light" href="{{ route('ky-thuat.maintenance.checklist-settings.index') }}"><i class="bi bi-list-tree"></i> Checklist builder</a>@endif
            @if($permissions['approve'])<a class="om10-btn light" href="{{ route('ky-thuat.maintenance.approval.index') }}"><i class="bi bi-shield-check"></i> Xử lý hồ sơ cũ @if(($summary['pending_approval'] ?? 0)>0)<b>{{ number_format($summary['pending_approval']) }}</b>@endif</a>@endif
            @if($permissions['create'])<button class="om10-btn light" type="button" data-om10-open-incident><i class="bi bi-exclamation-diamond"></i> Tạo sự cố</button><button class="om10-btn primary" type="button" data-om10-open-plan><i class="bi bi-plus-lg"></i> Tạo kế hoạch</button>@endif
        </div>
    </header>

    <nav class="om13-ops-nav" aria-label="Khu vực bảo hành và O&M">
        <a class="{{ !$archiveMode && request('view')!=='overdue' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.index') }}"><i class="bi bi-columns-gap"></i> Bảng điều hành</a>
        <a class="{{ request('view')==='overdue' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.index',['view'=>'overdue']) }}"><i class="bi bi-exclamation-circle"></i> Quá hạn <b>{{ number_format($summary['overdue'] ?? 0) }}</b></a>
        @if($permissions['approve'])<a href="{{ route('ky-thuat.maintenance.approval.index') }}"><i class="bi bi-inbox"></i> Hồ sơ cũ <b>{{ number_format($summary['pending_approval'] ?? 0) }}</b></a>@endif
        <a class="{{ $archiveMode ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.index',['view'=>'completed']) }}"><i class="bi bi-archive"></i> Kho hồ sơ</a>
    </nav>

    @if(session('success'))<div class="om10-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="om10-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())
        <div class="om10-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể lưu</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <section class="om13-metrics" aria-label="Số liệu điều hành">
        <a href="{{ route('ky-thuat.maintenance.index') }}"><span>Việc cần xử lý</span><strong>{{ number_format($summary['needs_action'] ?? 0) }}</strong></a>
        <a href="{{ route('ky-thuat.maintenance.index') }}"><span>Chưa phân công</span><strong>{{ number_format($summary['unassigned'] ?? 0) }}</strong></a>
        <a href="{{ $permissions['approve'] ? route('ky-thuat.maintenance.approval.index') : route('ky-thuat.maintenance.index',['status'=>'pending_approval']) }}"><span>Hồ sơ cũ chờ xử lý</span><strong>{{ number_format($summary['pending_approval'] ?? 0) }}</strong></a>
        <a href="{{ route('ky-thuat.maintenance.index',['view'=>'overdue']) }}"><span>Quá hạn</span><strong>{{ number_format($summary['overdue'] ?? 0) }}</strong></a>
    </section>

    <section class="om13-board-toolbar">
        <div class="om10-tabs"><a class="{{ !$activeType ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.index',['view'=>$archiveMode?'completed':'all']) }}">Tất cả</a><a class="{{ $activeType==='periodic' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.index',['view'=>$archiveMode?'completed':'all','type'=>'periodic']) }}">Định kỳ</a><a class="{{ $activeType==='incident' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.index',['view'=>$archiveMode?'completed':'all','type'=>'incident']) }}">Sự cố</a></div>
        <form method="GET" action="{{ route('ky-thuat.maintenance.index') }}" class="om10-filter"><input type="hidden" name="view" value="{{ $archiveMode ? 'completed' : ($filters['view'] ?? 'all') }}">@if($activeType)<input type="hidden" name="type" value="{{ $activeType }}">@endif<label><i class="bi bi-search"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Tìm công trình hoặc mã hồ sơ..."></label><select name="assignee_id"><option value="">Mọi kỹ thuật viên</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)request('assignee_id')===(string)$user->id)>{{ $user->name }}</option>@endforeach</select><button class="om10-icon-btn" type="submit"><i class="bi bi-funnel"></i></button></form>
    </section>

    @if(!$archiveMode && $showWaitingProfiles && $waitingProfiles->isNotEmpty())
        <details class="om14-plan-intake">
            <summary><span><i class="bi bi-inbox"></i><strong>{{ number_format($waitingProfiles->count()) }} công trình chờ lập kế hoạch</strong><small>Đã bàn giao từ Sales, chưa tạo lịch O&M</small></span><b>Mở danh sách <i class="bi bi-chevron-down"></i></b></summary>
            <div class="om14-intake-grid">
                @foreach($waitingProfiles->take(12) as $profile)
                    <article><div><strong>{{ $profile->site_name ?: 'Công trình #'.$profile->source_id }}</strong><span>{{ $profile->customer_name ?: 'Chưa có khách hàng' }}</span><small><i class="bi bi-geo-alt"></i> {{ \Illuminate\Support\Str::limit($profile->address ?: 'Chưa có địa chỉ',65) }}</small></div>@if($permissions['create'])<button class="om10-btn primary small" type="button" data-om10-plan-profile data-profile-id="{{ $profile->id }}" data-site-id="{{ $profile->site_id }}" data-site-name="{{ e($profile->site_name) }}" data-customer="{{ e($profile->customer_name) }}" data-address="{{ e($profile->address) }}" data-kwp="{{ $profile->system_kwp }}">Lập kế hoạch <i class="bi bi-arrow-right"></i></button>@endif</article>
                @endforeach
                @if($waitingProfiles->count()>12)<p class="om14-intake-more">Còn {{ $waitingProfiles->count()-12 }} công trình. Dùng ô tìm kiếm để mở nhanh.</p>@endif
            </div>
        </details>
    @endif

    @if($archiveMode)
        <section class="om13-archive-grid">
            @forelse($groups as $group)
                @php $current=$group['current']; @endphp
                <article class="om13-kanban-card done"><div class="om13-card-top"><span class="om10-status done">Hoàn thành</span><b>Đợt {{ $current->round_no ?: 1 }}/{{ $group['total'] }}</b></div><h3>{{ $current->site?->name ?: $current->site_name ?: 'Công trình' }}</h3><p>{{ $current->customer_name ?: $current->site?->contact_name ?: 'Chưa có khách hàng' }}</p><dl><div><dt>Hoàn thành</dt><dd>{{ optional($current->completed_date)->format('d/m/Y') ?: optional($current->completed_at)->format('d/m/Y') ?: optional($current->approved_at)->format('d/m/Y') ?: '—' }}</dd></div><div><dt>Phụ trách</dt><dd>{{ $current->leader?->user?->name ?: $current->assigned_name ?: 'Nhóm kỹ thuật' }}</dd></div></dl><a href="{{ route('ky-thuat.maintenance.show',$current) }}">Mở hồ sơ <i class="bi bi-arrow-right"></i></a></article>
            @empty<div class="om10-empty"><i class="bi bi-archive"></i><strong>Chưa có hồ sơ hoàn thành</strong></div>@endforelse
        </section>
    @else
        <section class="om13-kanban" aria-label="Bảng điều hành công việc">
            @php
                $laneMeta = [
                    'planned'=>['Đã lên kế hoạch','Lịch đã tạo, chờ phân công','bi-calendar2-check'],
                    'doing'=>['Đang thực hiện','Nhóm kỹ thuật đang xử lý','bi-play-circle'],
                    'approval'=>['Hồ sơ cũ chờ xử lý','Dữ liệu từ quy trình duyệt cũ','bi-shield-check'],
                    'attention'=>['Cần bổ sung','Hồ sơ bị trả lại hoặc chưa hợp lệ','bi-exclamation-triangle'],
                ];
            @endphp
            @foreach($laneMeta as $laneKey=>$meta)
                <div class="om13-lane {{ $laneKey }}">
                    <header><div><i class="bi {{ $meta[2] }}"></i><span><strong>{{ $meta[0] }}</strong><small>{{ $meta[1] }}</small></span></div><b>{{ $lanes[$laneKey]->count() }}</b></header>
                    <div class="om13-lane-stack">
                        @foreach($lanes[$laneKey] as $group)
                            @php
                                $current=$group['current']; [$simpleLabel,$simpleTone]=$statusLabel($current);
                                $people=$current->assignees->pluck('user.name')->filter()->values(); $isOverdue=$current->isOverdue();
                            @endphp
                            <article class="om13-kanban-card {{ $isOverdue ? 'overdue' : '' }}"><div class="om13-card-top"><span class="om10-status {{ $simpleTone }}">{{ $simpleLabel }}</span><b>Đợt {{ $current->round_no ?: 1 }}/{{ $group['total'] }}</b></div><h3>{{ $current->site?->name ?: $current->site_name ?: 'Công trình chưa đặt tên' }}</h3><p>{{ $current->customer_name ?: $current->site?->contact_name ?: 'Chưa có khách hàng' }}</p><small><i class="bi bi-calendar3"></i> {{ optional($current->scheduled_date)->format('d/m/Y') ?: 'Chưa có ngày' }} @if($isOverdue) · <span class="danger">quá hạn</span>@endif</small><div class="om13-card-team"><span><i class="bi bi-people"></i> {{ $people->isNotEmpty() ? $people->take(2)->implode(', ') : 'Chưa phân công' }}</span><span>{{ $current->schedule_code ?: '#'.$current->id }}</span></div><div class="om13-card-actions">@if($permissions['approve'] && $current->status==='pending_approval' && $current->approval_status==='pending')<a class="om10-btn success small" href="{{ route('ky-thuat.maintenance.approval.index',['selected'=>$current->id]) }}">Duyệt đợt</a>@else<a
    class="om10-btn light small"
    href="{{ route('ky-thuat.maintenance.show', $current) }}"
>
    Mở công việc
    <i class="bi bi-arrow-right"></i>
</a>

{{-- EGO_MAINTENANCE_DELETE_EXACT_V3 --}}
@if(
    auth()->check()
    && !empty($current->maintenance_profile_id)
    && (
        auth()->user()->hasAnyRole([
            'admin',
            'management',
            'manager',
            'technical_manager',
            'technical_leader',
            'truong_phong_ky_thuat',
        ])
        || \App\Support\SolarMaintenanceAccess::isManager(auth()->user())
    )
)
<form
    method="POST"
    action="{{ route('ky-thuat.maintenance.cards.destroy', $current->maintenance_profile_id) }}"
    class="d-inline-block"
    style="margin-left:4px;"
    onsubmit="return confirm('Xóa công trình này khỏi Bảo hành & O&M? Công trình CRM gốc vẫn được giữ lại.');"
>
    @csrf
    @method('DELETE')

    <button
        type="submit"
        class="om10-btn danger small"
        style="
            width:30px;
            min-width:30px;
            height:30px;
            padding:0;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        "
        title="Xóa khỏi Bảo hành & O&M"
    >
        <i class="bi bi-trash"></i>
    </button>
</form>
@endif
@endif<details><summary>{{ $group['total'] }} đợt</summary><div>@foreach($group['items'] as $round)<a href="{{ route('ky-thuat.maintenance.show',$round) }}">Đợt {{ $round->round_no ?: 1 }} · {{ optional($round->scheduled_date)->format('d/m/Y') ?: '—' }}</a>@endforeach</div></details></div></article>
                        @endforeach
                        @if($lanes[$laneKey]->isEmpty())<div class="om13-lane-empty"><i class="bi bi-check2-circle"></i><span>Không có công việc</span></div>@endif
                    </div>
                </div>
            @endforeach
        </section>
    @endif
</div>

@if($permissions['create'])
<div class="modal fade" id="om10PlanModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content om10-modal" method="POST" action="{{ route('ky-thuat.maintenance.store') }}">@csrf
            <div class="modal-header"><div><small>LẬP KẾ HOẠCH O&M</small><h2 id="om10PlanTitle">Kế hoạch bảo trì mới</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" name="maintenance_profile_id" id="om10ProfileId">
                <div class="om10-form-grid">
                    <div class="span-2">
    <div class="d-flex align-items-end gap-2">
        <label class="flex-grow-1 mb-0">
            <span>Công trình</span>

            <select
                name="site_id"
                id="om10SiteSelect"
                class="form-select"
                data-om-plan-site-select
            >
                <option value="">Chọn công trình</option>
                @foreach($sites as $site)
                    <option
                        value="{{ $site->id }}"
                        data-profile-id=""
                        data-name="{{ e($site->name) }}"
                        data-customer="{{ e($site->contact_name) }}"
                        data-address="{{ e($site->address) }}"
                        data-kwp="{{ $site->system_kwp }}"
                    >
                        {{ $site->name }}{{ $site->contact_name ? ' — '.$site->contact_name : '' }}
                    </option>
                @endforeach
                @if(($profileOptions ?? collect())->isNotEmpty())
                    <optgroup label="Công trình Kỹ thuật / hồ sơ O&M">
                        @foreach($profileOptions as $profileOption)
                            <option
                                value=""
                                data-profile-id="{{ $profileOption->id }}"
                                data-name="{{ e($profileOption->site_name) }}"
                                data-customer="{{ e($profileOption->customer_name) }}"
                                data-address="{{ e($profileOption->address) }}"
                                data-kwp="{{ $profileOption->system_kwp }}"
                            >
                                {{ $profileOption->site_name ?: 'Công trình #'.$profileOption->source_id }}{{ $profileOption->customer_name ? ' — '.$profileOption->customer_name : '' }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
        </label>

        <button
            type="button"
            class="om10-btn light"
            data-om-plan-quick-project-open
            style="white-space:nowrap;"
        >
            <i class="bi bi-plus-lg"></i>
            Tạo công trình mới
        </button>
    </div>

    <div
        class="small text-success mt-1"
        id="omPlanQuickProjectResult"
        hidden
    ></div>
</div>
                    <input type="hidden" name="site_name" id="om10SiteName"><input type="hidden" name="customer_name" id="om10Customer"><input type="hidden" name="address" id="om10Address"><input type="hidden" name="system_kwp" id="om10Kwp">
                    <label><span>Loại công việc</span><select class="form-select" name="type" id="om10PlanType">@foreach($types as $k=>$v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></label>
                    <label><span>Ưu tiên</span><select class="form-select" name="priority">@foreach($priorities as $k=>$v)<option value="{{ $k }}" @selected($k==='normal')>{{ $v }}</option>@endforeach</select></label>
                    <label><span>Ngày bắt đầu</span><input class="form-control" type="date" name="scheduled_date" value="{{ now()->toDateString() }}" required></label>
                    <label><span>Số đợt</span><input class="form-control" type="number" name="rounds_count" id="om10Rounds" value="5" min="1" max="24"></label>
                    <label><span>Chu kỳ</span><select class="form-select" name="round_interval_months" id="om10Interval">@for($month = 1; $month <= 12; $month++)<option value="{{ $month }}" @selected($month === 6)>{{ $month }} tháng/lần</option>@endfor</select></label>
                    <label class="span-2"><span>Nội dung cần thực hiện</span><textarea class="form-control" name="issue_note" rows="4" placeholder="Ví dụ: kiểm tra hệ thống, vệ sinh pin, kiểm tra inverter, sản lượng..."></textarea></label>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="om10-btn light" data-bs-dismiss="modal">Hủy</button><button class="om10-btn primary" type="submit">Tạo kế hoạch</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="om10IncidentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content om10-modal" method="POST" action="{{ route('ky-thuat.maintenance.issues.store') }}">@csrf
            <div class="modal-header"><div><small>CÔNG VIỆC O&M</small><h2>Tiếp nhận sự cố</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="om10-form-grid">
                    <div class="span-2">
                        <div class="d-flex align-items-end gap-2">
                            <label class="flex-grow-1 mb-0">
                                <span>Công trình</span>

                                <select
                                    name="site_id"
                                    id="omIncidentSiteSelect"
                                    class="form-select"
                                >
                                    <option value="">
                                        Chọn công trình
                                    </option>

                                    @foreach($sites as $site)
                                        <option
                                            value="{{ $site->id }}"
                                            data-profile-id=""
                                            data-name="{{ e($site->name) }}"
                                            data-customer="{{ e($site->contact_name) }}"
                                            data-address="{{ e($site->address) }}"
                                            data-kwp="{{ $site->system_kwp }}"
                                        >
                                            {{ $site->name }}
                                            {{ $site->contact_name ? ' — '.$site->contact_name : '' }}
                                        </option>
                                    @endforeach
                                    @if(($profileOptions ?? collect())->isNotEmpty())
                                        <optgroup label="Công trình Kỹ thuật / hồ sơ O&M">
                                            @foreach($profileOptions as $profileOption)
                                                <option
                                                    value=""
                                                    data-profile-id="{{ $profileOption->id }}"
                                                    data-name="{{ e($profileOption->site_name) }}"
                                                    data-customer="{{ e($profileOption->customer_name) }}"
                                                    data-address="{{ e($profileOption->address) }}"
                                                    data-kwp="{{ $profileOption->system_kwp }}"
                                                >
                                                    {{ $profileOption->site_name ?: 'Công trình #'.$profileOption->source_id }}
                                                    {{ $profileOption->customer_name ? ' — '.$profileOption->customer_name : '' }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                </select>
                            </label>

                            <button
                                type="button"
                                class="om10-btn light"
                                data-om-quick-project-open
                                style="white-space:nowrap;"
                            >
                                <i class="bi bi-plus-lg"></i>
                                Tạo công trình mới
                            </button>
                        </div>

                        <input
                            type="hidden"
                            name="maintenance_profile_id"
                            id="omIncidentProfileId"
                        >

                        <input
                            type="hidden"
                            name="site_name"
                            id="omIncidentSiteName"
                        >

                        <input
                            type="hidden"
                            name="customer_name"
                            id="omIncidentCustomer"
                        >

                        <input
                            type="hidden"
                            name="address"
                            id="omIncidentAddress"
                        >

                        <input
                            type="hidden"
                            name="system_kwp"
                            id="omIncidentKwp"
                        >

                        <div
                            class="small text-success mt-1"
                            id="omIncidentQuickProjectResult"
                            hidden
                        ></div>
                    </div>
                    <label><span>Mức độ</span><select name="priority" class="form-select"><option value="normal">Bình thường</option><option value="high">Cao</option><option value="urgent">Khẩn cấp</option></select></label>
                    <label><span>Ngày xử lý dự kiến</span><input name="scheduled_date" class="form-control" type="date" value="{{ now()->toDateString() }}" required></label>
                    <label class="span-2"><span>Khách hàng phản ánh / Nội dung sự cố</span><textarea name="issue_note" class="form-control" rows="5" required placeholder="Mô tả hiện tượng, lỗi, thời điểm phát sinh..."></textarea></label>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="om10-btn light" data-bs-dismiss="modal">Hủy</button><button class="om10-btn primary" type="submit">Tạo công việc sự cố</button></div>
        </form>
    </div>

<div
    class="modal fade"
    id="omQuickProjectModal"
    tabindex="-1"
>
    <div
        class="modal-dialog modal-dialog-centered"
    >
        <form
            class="modal-content om10-modal"
            id="omQuickProjectForm"
            data-submit-url="{{ route('ky-thuat.maintenance.quick-project.store') }}"
        >
            @csrf

            <div class="modal-header">
                <div>
                    <small>
                        BẢO HÀNH & O&M
                    </small>

                    <h2>
                        Tạo nhanh công trình
                    </h2>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body">

                <div class="om10-form-grid">

                    <label class="span-2">
                        <span>
                            Tên công trình *
                        </span>

                        <input
                            class="form-control"
                            type="text"
                            name="name"
                            required
                            maxlength="190"
                            placeholder="Ví dụ: Nhà Anh Minh - Bình Dương"
                        >
                    </label>

                    <label>
                        <span>
                            Khách hàng
                        </span>

                        <input
                            class="form-control"
                            type="text"
                            name="customer_name"
                            maxlength="190"
                        >
                    </label>

                    <label>
                        <span>
                            Số điện thoại
                        </span>

                        <input
                            class="form-control"
                            type="text"
                            name="customer_phone"
                            maxlength="50"
                        >
                    </label>

                    <label class="span-2">
                        <span>
                            Địa chỉ
                        </span>

                        <input
                            class="form-control"
                            type="text"
                            name="address"
                            maxlength="255"
                        >
                    </label>

                    <label>
                        <span>
                            Công suất kWp
                        </span>

                        <input
                            class="form-control"
                            type="number"
                            name="system_kwp"
                            min="0"
                            step="0.01"
                        >
                    </label>

                    <label>
                        <span>
                            Ghi chú
                        </span>

                        <input
                            class="form-control"
                            type="text"
                            name="note"
                            maxlength="2000"
                        >
                    </label>

                </div>

                <div
                    class="alert alert-danger mt-3 mb-0"
                    id="omQuickProjectError"
                    hidden
                ></div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="om10-btn light"
                    data-bs-dismiss="modal"
                >
                    Hủy
                </button>

                <button
                    type="submit"
                    class="om10-btn primary"
                    id="omQuickProjectSubmit"
                >
                    <i class="bi bi-check2"></i>
                    Tạo & chọn công trình
                </button>

            </div>
        </form>
    </div>
</div>
</div>
@endif
@endsection

@section('scripts')
<script src="{{ asset('js/technical-maintenance-v10.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v10.js')) ? filemtime(public_path('js/technical-maintenance-v10.js')) : time() }}"></script>
@endsection
