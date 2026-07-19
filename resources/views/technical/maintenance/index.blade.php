@extends('layouts.app')

@section('title', 'Bảo trì & Bảo hành Solar')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v3.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v3.css')) ? filemtime(public_path('css/technical-maintenance-v3.css')) : time() }}">
@endsection

@section('content')
@php
    $statusTone = [
        'draft'=>'muted','scheduled'=>'info','unassigned'=>'violet','assigned'=>'indigo',
        'customer_confirmed'=>'cyan','travelling'=>'cyan','in_progress'=>'warning',
        'waiting_material'=>'orange','waiting_submission'=>'slate','pending_approval'=>'pending',
        'revision_requested'=>'orange','approved'=>'success','waiting_customer'=>'violet',
        'completed'=>'success','postponed'=>'orange','cancelled'=>'danger',
    ];
    $priorityTone = ['low'=>'muted','normal'=>'info','high'=>'orange','urgent'=>'danger'];
@endphp

<div class="ego-container tm3-page">
    <nav class="tm3-breadcrumb" aria-label="breadcrumb">
        <a href="{{ route('dashboard') }}">Trang chủ</a>
        <i class="bi bi-chevron-right"></i>
        <span>Bảo trì &amp; Bảo hành</span>
    </nav>

    @if(session('success'))
        <div class="tm3-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
    @endif
    @if(session('error'))
        <div class="tm3-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>
    @endif
    @if($errors->any())
        <div class="tm3-alert danger align-start">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div><strong>Chưa thể lưu dữ liệu</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        </div>
    @endif

    <header class="tm3-page-header">
        <div>
            <span class="tm3-kicker">Solar O&amp;M</span>
            <h1>Bảo trì &amp; Bảo hành</h1>
            <p>Điều phối lịch, phân công kỹ thuật, phê duyệt và quản lý hồ sơ theo từng công trình.</p>
        </div>
        <div class="tm3-header-actions">
            <a class="btn btn-light" href="{{ url('/products/serials') }}"><i class="bi bi-upc-scan"></i> Tra cứu serial</a>
            @if($permissions['create'])
                <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#tm3CreateDrawer"><i class="bi bi-plus-lg"></i> Tạo lịch</button>
            @endif
        </div>
    </header>

    <section class="tm3-stat-grid">
        <a class="tm3-stat-card" href="{{ route('ky-thuat.maintenance.index', ['month'=>'']) }}">
            <span class="tm3-stat-icon info"><i class="bi bi-calendar2-week"></i></span>
            <div><small>Tổng lịch</small><strong>{{ number_format($summary['total']) }}</strong></div>
        </a>
        <a class="tm3-stat-card" href="{{ route('ky-thuat.maintenance.index', ['month'=>'','date_from'=>now()->toDateString(),'date_to'=>now()->toDateString()]) }}">
            <span class="tm3-stat-icon cyan"><i class="bi bi-calendar-check"></i></span>
            <div><small>Hôm nay</small><strong>{{ number_format($summary['today']) }}</strong></div>
        </a>
        <a class="tm3-stat-card" href="{{ route('ky-thuat.maintenance.index', ['month'=>'','date_from'=>now()->toDateString(),'date_to'=>now()->addDays(7)->toDateString()]) }}">
            <span class="tm3-stat-icon warning"><i class="bi bi-alarm"></i></span>
            <div><small>Sắp đến hạn</small><strong>{{ number_format($summary['upcoming']) }}</strong></div>
        </a>
        <a class="tm3-stat-card" href="{{ route('ky-thuat.maintenance.index', ['month'=>'','overdue'=>1]) }}">
            <span class="tm3-stat-icon danger"><i class="bi bi-exclamation-octagon"></i></span>
            <div><small>Quá hạn</small><strong>{{ number_format($summary['overdue']) }}</strong></div>
        </a>
        <a class="tm3-stat-card" href="{{ route('ky-thuat.maintenance.index', ['month'=>'','status'=>'unassigned']) }}">
            <span class="tm3-stat-icon violet"><i class="bi bi-person-exclamation"></i></span>
            <div><small>Chờ phân công</small><strong>{{ number_format($summary['unassigned']) }}</strong></div>
        </a>
        <a class="tm3-stat-card" href="{{ route('ky-thuat.maintenance.index', ['month'=>'','status'=>'pending_approval']) }}">
            <span class="tm3-stat-icon pending"><i class="bi bi-patch-question"></i></span>
            <div><small>Chờ phê duyệt</small><strong>{{ number_format($summary['pending_approval'] ?? 0) }}</strong></div>
        </a>
    </section>

    <section class="tm3-card tm3-filter-card">
        <form method="GET" action="{{ route('ky-thuat.maintenance.index') }}" class="tm3-filter-form">
            <div class="tm3-search-field">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mã lịch, công trình, khách hàng, số điện thoại...">
            </div>
            <input class="form-control" type="month" name="month" value="{{ $filters['month'] ?? '' }}">
            <select class="form-select" name="status">
                <option value="">Tất cả trạng thái</option>
                @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
            <select class="form-select" name="type">
                <option value="">Tất cả loại lịch</option>
                @foreach($types as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach
            </select>
            <select class="form-select" name="assignee_id">
                <option value="">Mọi kỹ thuật viên</option>
                @foreach($users as $user)<option value="{{ $user->id }}" @selected((string)($filters['assignee_id'] ?? '') === (string)$user->id)>{{ $user->name }}</option>@endforeach
            </select>
            <label class="tm3-check"><input type="checkbox" name="overdue" value="1" @checked(!empty($filters['overdue']))><span>Chỉ quá hạn</span></label>
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Lọc</button>
            <a class="btn btn-light tm3-icon-only" href="{{ route('ky-thuat.maintenance.index') }}" title="Xóa bộ lọc"><i class="bi bi-arrow-counterclockwise"></i></a>
        </form>
    </section>

    <section class="tm3-card">
        <div class="tm3-section-head">
            <div><h2>Danh sách lịch O&amp;M</h2><p>{{ number_format($schedules->total()) }} kết quả phù hợp</p></div>
        </div>
        <div class="tm3-table-wrap">
            <table class="tm3-table">
                <thead>
                    <tr>
                        <th>Mã &amp; công trình</th>
                        <th>Chu kỳ</th>
                        <th>Ngày dự kiến</th>
                        <th>Phụ trách</th>
                        <th>Trạng thái</th>
                        <th class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($schedules as $schedule)
                    @php
                        $hasValidSite = (bool) ($schedule->site_id && $schedule->site);
                        $scheduleUrl = route('ky-thuat.maintenance.show', ['schedule'=>$schedule->id]);
                        $siteUrl = $hasValidSite ? route('ky-thuat.maintenance.site', ['site'=>$schedule->site_id]) : null;
                        $overdue = $schedule->isOverdue();
                        $roundNo = max(1, (int)($schedule->round_no ?: 1));
                        $totalRounds = max(1, (int)($schedule->total_rounds ?: 1));
                        $progress = min(100, (int)round(($roundNo / $totalRounds) * 100));
                    @endphp
                    <tr class="{{ $overdue ? 'is-overdue' : '' }}">
                        <td>
                            <div class="tm3-code-line">
                                <a class="tm3-code" href="{{ $scheduleUrl }}">{{ $schedule->schedule_code ?: '#'.$schedule->id }}</a>
                                <span class="tm3-pill {{ $priorityTone[$schedule->priority] ?? 'muted' }}">{{ $priorities[$schedule->priority] ?? $schedule->priority }}</span>
                            </div>
                            @if($siteUrl)
                                <a class="tm3-site-name" href="{{ $siteUrl }}">{{ $schedule->site?->name ?: $schedule->site_name ?: 'Công trình chưa đặt tên' }}</a>
                            @else
                                <div class="tm3-site-name static">{{ $schedule->site_name ?: 'Công trình chưa đặt tên' }}</div>
                                <span class="tm3-inline-warning"><i class="bi bi-link-45deg"></i> Chưa liên kết công trình hợp lệ</span>
                            @endif
                            <div class="tm3-meta"><span><i class="bi bi-person"></i>{{ $schedule->site?->contact_name ?: $schedule->customer_name ?: 'Chưa có khách hàng' }}</span><span><i class="bi bi-geo-alt"></i>{{ \Illuminate\Support\Str::limit($schedule->site?->address ?: $schedule->address ?: 'Chưa có địa chỉ', 70) }}</span></div>
                        </td>
                        <td>
                            <div class="tm3-round-label">Đợt {{ $roundNo }}/{{ $totalRounds }}</div>
                            <div class="tm3-progress"><span style="width:{{ $progress }}%"></span></div>
                            <small>{{ $totalRounds > 1 ? 'Chu kỳ '.$totalRounds.' đợt' : 'Lịch đơn lẻ' }}</small>
                        </td>
                        <td>
                            <strong class="{{ $overdue ? 'text-danger' : '' }}">{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: '—' }}</strong>
                            <small class="tm3-block">{{ $overdue ? 'Quá hạn '.optional($schedule->scheduled_date)->diffInDays(today()).' ngày' : optional($schedule->scheduled_date)->translatedFormat('l') }}</small>
                        </td>
                        <td>
                            <div class="tm3-person-cell"><span class="tm3-avatar"><i class="bi bi-people"></i></span><div><strong>{{ $schedule->leader?->user?->name ?: $schedule->assignee_names }}</strong><small>{{ $schedule->assignees->count() }} người</small></div></div>
                        </td>
                        <td><span class="tm3-status {{ $statusTone[$schedule->status] ?? 'muted' }}">{{ $statuses[$schedule->status] ?? $schedule->status }}</span></td>
                        <td>
                            <div class="tm3-row-actions">
                                @if($siteUrl)<a class="btn btn-sm btn-light" href="{{ $siteUrl }}"><i class="bi bi-building"></i> Hồ sơ</a>@endif
                                <a class="tm3-icon-btn" href="{{ $scheduleUrl }}" title="Xem chi tiết đợt"><i class="bi bi-eye"></i></a>
                                @can('update', $schedule)<button class="tm3-icon-btn" type="button" title="Sửa nhanh" data-tm3-edit-url="{{ route('ky-thuat.maintenance.json', ['schedule'=>$schedule->id]) }}" data-tm3-update-url="{{ route('ky-thuat.maintenance.update', ['schedule'=>$schedule->id]) }}"><i class="bi bi-pencil-square"></i></button>@endcan
                                @can('changeStatus', $schedule)<button class="tm3-icon-btn" type="button" title="Đổi trạng thái" data-tm3-status-url="{{ route('ky-thuat.maintenance.status', ['schedule'=>$schedule->id]) }}" data-tm3-current-status="{{ $schedule->status }}" data-tm3-code="{{ $schedule->schedule_code }}"><i class="bi bi-arrow-repeat"></i></button>@endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="tm3-empty"><i class="bi bi-calendar2-x"></i><h3>Chưa có lịch phù hợp</h3><p>Thử đổi bộ lọc hoặc tạo lịch bảo trì mới.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($schedules->hasPages())<div class="tm3-pagination">{{ $schedules->links() }}</div>@endif
    </section>
</div>

@if($permissions['create'])
<div class="offcanvas offcanvas-end tm3-drawer" tabindex="-1" id="tm3CreateDrawer">
    <div class="offcanvas-header tm3-drawer-head"><div><span>TẠO KẾ HOẠCH O&amp;M</span><h2>Lịch bảo trì mới</h2></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <form method="POST" action="{{ route('ky-thuat.maintenance.store') }}" class="offcanvas-body tm3-drawer-body" id="tm3CreateForm">
        @csrf
        <section class="tm3-form-section">
            <div class="tm3-form-title"><b>1</b><div><strong>Chọn công trình</strong><small>Dữ liệu khách hàng và hệ thống được tự động điền.</small></div></div>
            <label class="form-label">Công trình</label>
            <select class="form-select" name="site_id" id="tm3SiteSelect" data-search-url="{{ route('ky-thuat.maintenance.sites-search') }}">
                <option value="">Tìm theo công trình, khách hàng hoặc số điện thoại...</option>
                @foreach($sites as $site)
                    <option value="{{ $site->id }}" data-name="{{ $site->name }}" data-customer="{{ $site->contact_name }}" data-phone="{{ $site->contact_phone }}" data-address="{{ $site->address }}" data-kwp="{{ $site->system_kwp }}">{{ $site->name }}{{ $site->contact_name ? ' — '.$site->contact_name : '' }}</option>
                @endforeach
            </select>
            <div class="tm3-readonly-grid mt-3">
                <label>Tên khách hàng<input class="form-control" name="customer_name" id="tm3CustomerName" value="{{ old('customer_name') }}"></label>
                <label>Tên công trình<input class="form-control" name="site_name" id="tm3SiteName" value="{{ old('site_name') }}"></label>
                <label class="full">Địa chỉ<input class="form-control" name="address" id="tm3Address" value="{{ old('address') }}"></label>
            </div>
        </section>
        <section class="tm3-form-section">
            <div class="tm3-form-title"><b>2</b><div><strong>Thiết lập chu kỳ</strong><small>Chọn ngày bắt đầu, số đợt và khoảng cách.</small></div></div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Loại lịch</label><select class="form-select" name="type" required>@foreach($types as $value=>$label)<option value="{{ $value }}" @selected(old('type','periodic')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Ưu tiên</label><select class="form-select" name="priority" required>@foreach($priorities as $value=>$label)<option value="{{ $value }}" @selected(old('priority','normal')===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">Ngày bắt đầu</label><input class="form-control" type="date" name="scheduled_date" id="tm3BaseDate" value="{{ old('scheduled_date', now()->toDateString()) }}" required></div>
                <div class="col-md-3"><label class="form-label">Số đợt</label><input class="form-control" type="number" name="rounds_count" id="tm3RoundsCount" value="{{ old('rounds_count',1) }}" min="1" max="24"></div>
                <div class="col-md-3"><label class="form-label">Cách nhau</label><select class="form-select" name="round_interval_months" id="tm3RoundInterval"><option value="1">1 tháng</option><option value="3" selected>3 tháng</option><option value="6">6 tháng</option><option value="12">12 tháng</option></select></div>
            </div>
            <div id="tm3RoundsPreview" class="tm3-round-preview"></div>
        </section>
        <section class="tm3-form-section">
            <div class="tm3-form-title"><b>3</b><div><strong>Phân công &amp; ghi chú</strong><small>Người đầu tiên được chọn là trưởng nhóm.</small></div></div>
            <label class="form-label">Kỹ thuật viên</label>
            <select class="form-select" name="assigned_user_ids[]" id="tm3CreateAssignees" multiple>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}{{ $user->position?->name ? ' — '.$user->position->name : '' }}</option>@endforeach</select>
            <div class="row g-3 mt-1">
                <div class="col-md-5"><label class="form-label">Công suất kWp</label><input class="form-control" type="number" step="0.01" name="system_kwp" id="tm3SystemKwp" value="{{ old('system_kwp') }}"></div>
                <div class="col-md-7"><label class="form-label">Inverter / thiết bị</label><input class="form-control" name="inverter_info" value="{{ old('inverter_info') }}"></div>
                <div class="col-12"><label class="form-label">Hiện trạng / yêu cầu</label><textarea class="form-control" name="issue_note" rows="3">{{ old('issue_note') }}</textarea></div>
                <div class="col-12"><label class="form-label">Ghi chú nội bộ</label><textarea class="form-control" name="technical_note" rows="2">{{ old('technical_note') }}</textarea></div>
            </div>
        </section>
        <div class="tm3-drawer-footer"><button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Đóng</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Tạo lịch</button></div>
    </form>
</div>
@endif

<div class="offcanvas offcanvas-end tm3-drawer" tabindex="-1" id="tm3EditDrawer">
    <div class="offcanvas-header tm3-drawer-head"><div><span>SỬA NHANH</span><h2 id="tm3EditTitle">Đang tải...</h2></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button></div>
    <form method="POST" action="#" class="offcanvas-body tm3-drawer-body" id="tm3EditForm">@csrf @method('PUT')
        <div class="tm3-loading" id="tm3EditLoading"><span class="spinner-border spinner-border-sm"></span> Đang tải dữ liệu...</div>
        <div id="tm3EditContent" hidden>
            <div class="tm3-reference-box"><div><small>Công trình</small><strong id="tm3EditSite">—</strong></div><div><small>Chu kỳ</small><strong id="tm3EditRound">—</strong></div><div><small>Khách hàng</small><strong id="tm3EditCustomer">—</strong></div></div>
            <section class="tm3-form-section compact">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Ngày dự kiến</label><input class="form-control" type="date" name="scheduled_date" data-edit-field="scheduled_date"></div>
                    <div class="col-md-6"><label class="form-label">Ưu tiên</label><select class="form-select" name="priority" data-edit-field="priority">@foreach($priorities as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Loại lịch</label><select class="form-select" name="type" data-edit-field="type">@foreach($types as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Trạng thái</label><select class="form-select" name="status" data-edit-field="status">@foreach($statuses as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">Người phụ trách</label><select class="form-select" name="assigned_user_ids[]" data-edit-field="assigned_user_ids" multiple>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">Ghi chú kỹ thuật</label><textarea class="form-control" name="technical_note" rows="3" data-edit-field="technical_note"></textarea></div>
                    <div class="col-12"><label class="form-label">Lý do đổi trạng thái / mở lại</label><input class="form-control" name="reason" placeholder="Bắt buộc khi hoãn, hủy hoặc mở lại"></div>
                </div>
            </section>
        </div>
        <div class="tm3-drawer-footer"><button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Đóng</button><a class="btn btn-outline-primary" id="tm3EditFullLink" href="#"><i class="bi bi-box-arrow-up-right"></i> Mở chi tiết</a><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Lưu</button></div>
    </form>
</div>

<div class="modal fade" id="tm3StatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered"><form class="modal-content tm3-modal" method="POST" action="#" id="tm3StatusForm">@csrf
        <div class="modal-header"><div><small>CẬP NHẬT QUY TRÌNH</small><h2>Đổi trạng thái</h2><span id="tm3StatusCode"></span></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><label class="form-label">Trạng thái mới</label><select class="form-select" name="status" id="tm3StatusSelect" required>@foreach($statuses as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><label class="form-label mt-3">Lý do</label><input class="form-control" name="reason" placeholder="Bắt buộc khi hoãn, hủy hoặc mở lại"><label class="form-label mt-3">Kết quả / ghi chú</label><textarea class="form-control" name="result_note" rows="4"></textarea></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button><button class="btn btn-primary" type="submit"><i class="bi bi-arrow-repeat"></i> Cập nhật</button></div>
    </form></div>
</div>
@endsection

@section('scripts')
<script>window.TM3={oldHasErrors:@json($errors->any()),canCreate:@json($permissions['create'])};</script>
<script src="{{ asset('js/technical-maintenance-v3.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v3.js')) ? filemtime(public_path('js/technical-maintenance-v3.js')) : time() }}"></script>
@endsection
