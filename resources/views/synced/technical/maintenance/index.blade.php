@extends('layouts.app')

@section('title', 'Bảo trì & Bảo hành công trình')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-synced-v4.css') }}?v={{ file_exists(public_path('css/technical-maintenance-synced-v4.css')) ? filemtime(public_path('css/technical-maintenance-synced-v4.css')) : time() }}">
@endsection

@section('content')
@php
    $statusTone = [
        'draft'=>'muted','scheduled'=>'info','unassigned'=>'violet','assigned'=>'indigo','customer_confirmed'=>'cyan',
        'travelling'=>'cyan','in_progress'=>'warning','waiting_material'=>'orange','waiting_submission'=>'slate',
        'pending_approval'=>'pending','revision_requested'=>'orange','approved'=>'success','waiting_customer'=>'violet',
        'completed'=>'success','postponed'=>'orange','cancelled'=>'danger',
    ];
    $claimTone = [
        'received'=>'info','eligibility_check'=>'violet','diagnosing'=>'warning','solution_proposed'=>'cyan',
        'pending_approval'=>'pending','approved'=>'success','waiting_stock'=>'orange','replacing'=>'warning',
        'waiting_customer'=>'violet','completed'=>'success','rejected'=>'danger','cancelled'=>'muted',
    ];
    $priorityTone = ['low'=>'muted','normal'=>'info','high'=>'orange','urgent'=>'danger'];
    $stockTone = ['pending'=>'pending','approved'=>'info','completed'=>'success','cancelled'=>'danger'];
    $manualStatuses = array_diff_key($statuses, array_flip(['pending_approval','approved','revision_requested','completed']));
    $tabs = [
        'overview' => ['Tổng quan O&M','bi-grid-1x2'],
        'maintenance' => ['Lịch bảo trì','bi-calendar2-week'],
        'claims' => ['Phiếu sự cố & bảo hành','bi-shield-exclamation'],
        'stock' => ['Kho & đổi thiết bị','bi-box-seam'],
        'files' => ['Hồ sơ công trình','bi-folder2-open'],
    ];
    $operationTotal = max(1, (int)($summary['total'] ?? 0));
    $scheduledPct = round((($summary['scheduled_bucket'] ?? 0) / $operationTotal) * 100);
    $processingPct = round((($summary['processing_bucket'] ?? 0) / $operationTotal) * 100);
    $approvalPct = round((($summary['approval_bucket'] ?? 0) / $operationTotal) * 100);
    $completedPct = round((($summary['completed'] ?? 0) / $operationTotal) * 100);
    $scheduledDeg = round($scheduledPct * 3.6, 2);
    $processingEndDeg = round(min(100, $scheduledPct + $processingPct) * 3.6, 2);
    $approvalEndDeg = round(min(100, $scheduledPct + $processingPct + $approvalPct) * 3.6, 2);
    $completedEndDeg = round(min(100, $scheduledPct + $processingPct + $approvalPct + $completedPct) * 3.6, 2);
    $pendingApprovalTotal = (int)($summary['pending_approval'] ?? 0) + (int)($warrantySummary['pending_approval'] ?? 0);
    $completedThisMonth = (int)($summary['completed_this_month'] ?? 0) + (int)($warrantySummary['completed_this_month'] ?? 0);
    $maintenanceFlow = [
        ['calendar-plus','Tạo kế hoạch'],
        ['people','Phân công'],
        ['person-check','Khách xác nhận'],
        ['tools','Thực hiện'],
        ['file-earmark-text','Báo cáo'],
        ['shield-check','Trưởng phòng duyệt'],
        ['check2-square','Hoàn thành'],
        ['arrow-repeat','Sinh đợt tiếp'],
    ];
    $warrantyFlow = [
        ['exclamation-triangle','Tiếp nhận'],
        ['shield-search','Kiểm tra BH'],
        ['search','Chẩn đoán'],
        ['lightbulb','Đề xuất'],
        ['clipboard-check','Duyệt'],
        ['box-arrow-up','Kho xuất'],
        ['box-arrow-in-down','Thu hồi lỗi'],
        ['wrench-adjustable','Thay thế'],
        ['person-check','Khách xác nhận'],
        ['shield-check','Đóng phiếu'],
    ];
    $maintenanceKpis = [
        ['calendar2-week','Tổng lịch',$summary['total'],'blue',''],
        ['calendar-check','Hôm nay',$summary['today'],'green',''],
        ['alarm','Sắp đến hạn',$summary['upcoming'],'amber',''],
        ['exclamation-octagon','Quá hạn',$summary['overdue'],'red','overdue'],
        ['person-exclamation','Chờ phân công',$summary['unassigned'],'violet','unassigned'],
        ['patch-question','Chờ duyệt',$summary['pending_approval'],'teal','pending_approval'],
    ];
    $claimKpis = [
        ['shield-exclamation','Tổng phiếu',$warrantySummary['total'] ?? 0,'blue'],
        ['activity','Đang mở',$warrantySummary['open'] ?? 0,'amber'],
        ['patch-question','Chờ duyệt',$warrantySummary['pending_approval'] ?? 0,'violet'],
        ['box-seam','Chờ kho',$warrantySummary['waiting_stock'] ?? 0,'red'],
        ['check2-circle','Hoàn thành',$warrantySummary['completed'] ?? 0,'green'],
    ];
    $allFormErrors = collect();
    foreach ($errors->getBags() as $errorBag) {
        foreach ($errorBag->all() as $errorMessage) {
            $allFormErrors->push($errorMessage);
        }
    }
    $allFormErrors = $allFormErrors->unique()->values();
    $claimTransitionsForJs = \App\Models\SolarWarrantyClaim::TRANSITIONS;
    $stockTransitionsForJs = [
        'pending' => ['approved','completed','cancelled'],
        'approved' => ['completed','cancelled'],
        'completed' => [],
        'cancelled' => ['pending'],
    ];
    $tmConfigForJs = [
        'scheduleHasErrors' => $errors->getBag('default')->any(),
        'claimHasErrors' => $errors->getBag('warrantyClaim')->any(),
        'stockHasErrors' => $errors->getBag('warrantyStock')->any(),
        'canCreate' => (bool) $permissions['create'],
        'canCreateClaim' => (bool) $permissions['claim_create'],
        'canManageStock' => (bool) $permissions['stock_manage'],
        'canApprove' => (bool) $permissions['approve'],
        'isManager' => (bool) $permissions['manager'],
        'claimTransitions' => $claimTransitionsForJs,
        'stockTransitions' => $stockTransitionsForJs,
    ];
@endphp

<div class="ego-container tm4-page">
    <nav class="tm4-breadcrumb"><a href="{{ route('dashboard') }}">Trang chủ</a><i class="bi bi-chevron-right"></i><span>Dự án</span><i class="bi bi-chevron-right"></i><strong>Bảo trì &amp; Bảo hành</strong></nav>

    @if(session('success'))<div class="tm4-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="tm4-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($allFormErrors->isNotEmpty())
        <div class="tm4-alert danger align-start"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể lưu dữ liệu</strong><ul>@foreach($allFormErrors as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <header class="tm4-hero">
        <div class="tm4-hero-main">
            <span class="tm4-kicker">TRUNG TÂM ĐIỀU HÀNH O&amp;M</span>
            <h1>Bảo trì &amp; Bảo hành công trình</h1>
            <p>Trang tổng quan điều hành dành cho Ban Giám đốc — theo dõi lịch bảo trì, sự cố, serial, kho đổi bảo hành và tiến độ xử lý theo từng công trình.</p>
        </div>
        <div class="tm4-hero-actions">
            <x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[41px] tw:rounded-[10px] tw:text-[13px]/[19.5px] tw:font-[750] tw:whitespace-nowrap tw:max-[720px]:flex-auto" href="{{ route('products.serials.index') }}"><i class="bi bi-upc-scan"></i> Tra cứu serial</x-ui.button>
            @if($permissions['claim_create'])<x-ui.button variant="outline-light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[41px] tw:rounded-[10px] tw:text-[13px]/[19.5px] tw:font-[750] tw:whitespace-nowrap tw:max-[720px]:flex-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#tm4ClaimDrawer"><i class="bi bi-shield-plus"></i> Tạo phiếu sự cố</x-ui.button>@endif
            @if($permissions['create'])<x-ui.button variant="warning" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[41px] tw:rounded-[10px] tw:text-[13px]/[19.5px] tw:font-[750] tw:whitespace-nowrap tw:max-[720px]:flex-auto" type="button" data-bs-toggle="offcanvas" data-bs-target="#tm4CreateDrawer"><i class="bi bi-calendar-plus"></i> Tạo kế hoạch</x-ui.button>@endif
        </div>
    </header>

    <nav class="tm4-tabs tm4-primary-tabs" aria-label="Điều hướng O&M">
        @foreach($tabs as $key => $tab)
            <a class="{{ $activeView === $key ? 'active' : '' }}" href="{{ route('projects-unified.maintenance.index', ['view'=>$key]) }}">
                <i class="bi {{ $tab[1] }}"></i><span>{{ $tab[0] }}</span>
                @if($key === 'claims' && ($warrantySummary['open'] ?? 0) > 0)<b>{{ $warrantySummary['open'] }}</b>@endif
                @if($key === 'stock' && ($warrantySummary['stock_pending'] ?? 0) > 0)<b>{{ $warrantySummary['stock_pending'] }}</b>@endif
            </a>
        @endforeach
    </nav>

    @if($activeView === 'overview')
        <section class="tm4-kpi-grid tm4-executive-kpis">
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}" class="tm4-kpi"><span class="blue"><i class="bi bi-calendar2-week"></i></span><div><small>Tổng lịch bảo trì</small><strong>{{ number_format($summary['total']) }}</strong><em>{{ number_format($summary['in_progress']) }} đang thực hiện</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','date_from'=>now()->toDateString(),'date_to'=>now()->addDays(7)->toDateString()]) }}" class="tm4-kpi"><span class="amber"><i class="bi bi-clock-history"></i></span><div><small>Sắp đến hạn</small><strong>{{ number_format($summary['upcoming']) }}</strong><em>Còn trong 7 ngày</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}" class="tm4-kpi is-danger"><span class="red"><i class="bi bi-exclamation-triangle"></i></span><div><small>Quá hạn</small><strong>{{ number_format($summary['overdue']) }}</strong><em>Cần xử lý ngay</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'claims']) }}" class="tm4-kpi"><span class="violet"><i class="bi bi-tools"></i></span><div><small>Đang xử lý sự cố</small><strong>{{ number_format($warrantySummary['open'] ?? 0) }}</strong><em>{{ number_format($warrantySummary['urgent_open'] ?? 0) }} phiếu khẩn cấp</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'pending_approval']) }}" class="tm4-kpi"><span class="violet"><i class="bi bi-file-earmark-check"></i></span><div><small>Phiếu chờ duyệt</small><strong>{{ number_format($pendingApprovalTotal) }}</strong><em>Chờ Ban quản lý duyệt</em></div></a>
            <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'completed']) }}" class="tm4-kpi"><span class="green"><i class="bi bi-check2-circle"></i></span><div><small>Hoàn thành tháng này</small><strong>{{ number_format($completedThisMonth) }}</strong><em>Tỷ lệ {{ number_format($summary['completion_rate'] ?? 0) }}% tổng lịch</em></div></a>
        </section>

        <div class="tm4-dashboard-grid tm4-executive-grid">
            <section class="tm4-card tm4-span-8 tm4-watch-card">
                <div class="tm4-card-head tm4-toolbar-head">
                    <div><span>ĐIỀU HÀNH TRỰC TIẾP</span><h2>Công trình cần theo dõi</h2></div>
                    <div class="tm4-head-tools">
                        <a class="tm4-inline-search" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance']) }}"><i class="bi bi-search"></i><span>Tìm kiếm công trình...</span></a>
                        <a class="tm4-tool-button" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">Tất cả trạng thái <i class="bi bi-chevron-down"></i></a>
                        <a class="tm4-tool-button" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}">Cần ưu tiên <i class="bi bi-sliders"></i></a>
                    </div>
                </div>
                <div class="tm4-table-wrap">
                    <table class="tm4-table tm4-exec-table">
                        <thead><tr><th>Công trình</th><th>Mã lịch</th><th>Hạng mục</th><th>Đợt bảo trì</th><th>Ngày dự kiến</th><th>Tiến độ thời gian</th><th>Kỹ thuật phụ trách</th><th>Trạng thái</th><th>Ưu tiên</th></tr></thead>
                        <tbody>
                        @forelse($overviewSchedules as $schedule)
                            @php
                                $overdue = $schedule->isOverdue();
                                $roundNo = max(1, (int)($schedule->round_no ?: 1));
                                $totalRounds = max(1, (int)($schedule->total_rounds ?: 1));
                                $leaderName = $schedule->leader?->user?->name ?: $schedule->assignee_names;
                                $isUnassigned = trim((string)$leaderName) === '' || $leaderName === 'Chưa phân công';
                                $avatarInitial = $isUnassigned ? '!' : mb_strtoupper(mb_substr(trim((string)$leaderName), 0, 1));
                                $scheduledDate = $schedule->scheduled_date;
                                $daysDelta = $scheduledDate ? (int) today()->diffInDays($scheduledDate, false) : null;
                                if ($schedule->status === 'completed') {
                                    $timeLabel = 'Đã hoàn thành';
                                    $timeTone = 'done';
                                } elseif ($overdue) {
                                    $timeLabel = 'Quá hạn '.abs((int)$daysDelta).' ngày';
                                    $timeTone = 'overdue';
                                } elseif ($daysDelta === 0) {
                                    $timeLabel = 'Hôm nay';
                                    $timeTone = 'today';
                                } elseif ($daysDelta !== null && $daysDelta > 0) {
                                    $timeLabel = 'Còn '.$daysDelta.' ngày';
                                    $timeTone = $daysDelta <= 3 ? 'soon' : 'safe';
                                } else {
                                    $timeLabel = 'Chưa xác định';
                                    $timeTone = 'muted';
                                }
                                $capacity = $schedule->site?->system_kwp ?: $schedule->system_kwp;
                            @endphp
                            <tr class="{{ $overdue ? 'is-overdue' : '' }}">
                                <td><a class="tm4-main-link" href="{{ route('projects-unified.maintenance.show',$schedule) }}">{{ $schedule->site?->name ?: $schedule->site_name ?: 'Công trình chưa đặt tên' }}</a><small>{{ \Illuminate\Support\Str::limit($schedule->site?->address ?: $schedule->address, 40) }}</small></td>
                                <td><a class="tm4-code" href="{{ route('projects-unified.maintenance.show',$schedule) }}">{{ $schedule->schedule_code ?: '#'.$schedule->id }}</a></td>
                                <td><strong>{{ $types[$schedule->type] ?? $schedule->type }}</strong><small>{{ $capacity ? number_format((float)$capacity,0).' kWp' : 'Chưa cập nhật công suất' }}</small></td>
                                <td><strong>Đợt {{ $roundNo }}</strong><small>{{ $roundNo }}/{{ $totalRounds }} chu kỳ</small></td>
                                <td><strong class="{{ $overdue ? 'text-danger' : '' }}">{{ optional($scheduledDate)->format('d/m/Y') ?: '—' }}</strong></td>
                                <td><span class="tm4-time-pill {{ $timeTone }}">{{ $timeLabel }}</span></td>
                                <td>
                                    <div class="tm4-person {{ $isUnassigned ? 'unassigned' : '' }}">
                                        <span class="tm4-avatar">{{ $avatarInitial }}</span>
                                        <div><strong>{{ $leaderName ?: 'Chưa phân công' }}</strong><small>{{ $isUnassigned ? 'Cần điều phối kỹ thuật' : ($schedule->assignees->count().' người trong nhóm') }}</small></div>
                                    </div>
                                </td>
                                <td><span class="tm4-badge {{ $statusTone[$schedule->status] ?? 'muted' }}">{{ $statuses[$schedule->status] ?? $schedule->status }}</span></td>
                                <td><span class="tm4-badge {{ $priorityTone[$schedule->priority] ?? 'muted' }}">{{ $priorities[$schedule->priority] ?? $schedule->priority }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="9"><div class="tm4-empty"><i class="bi bi-calendar2-check"></i><h3>Chưa có lịch cần theo dõi</h3><p>Các lịch mới, quá hạn và sắp đến hạn sẽ xuất hiện tại đây.</p></div></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="tm4-table-footer"><span>Hiển thị {{ $overviewSchedules->count() }} lịch ưu tiên</span><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">Mở toàn bộ danh sách <i class="bi bi-arrow-right"></i></a></div>
            </section>

            <section class="tm4-card tm4-span-4 tm4-command-card">
                <div class="tm4-card-head"><div><span>THEO DÕI THÁNG {{ now()->format('m/Y') }}</span><h2>Tổng quan điều hành</h2></div><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance']) }}">Chi tiết</a></div>
                <div class="tm4-command-summary">
                    <div class="tm4-operation-donut" style="--scheduled-deg:{{ $scheduledDeg }}deg;--processing-end-deg:{{ $processingEndDeg }}deg;--approval-end-deg:{{ $approvalEndDeg }}deg;--completed-end-deg:{{ $completedEndDeg }}deg"><div><strong>{{ number_format($summary['total']) }}</strong><small>Tổng lịch</small></div></div>
                    <div class="tm4-operation-legend">
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}"><i class="scheduled"></i><span>Đã lên lịch</span><strong>{{ $summary['scheduled_bucket'] ?? 0 }} ({{ $scheduledPct }}%)</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'in_progress']) }}"><i class="processing"></i><span>Đang xử lý</span><strong>{{ $summary['processing_bucket'] ?? 0 }} ({{ $processingPct }}%)</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}"><i class="overdue"></i><span>Quá hạn</span><strong>{{ $summary['overdue'] ?? 0 }}</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'pending_approval']) }}"><i class="approval"></i><span>Chờ duyệt</span><strong>{{ $summary['approval_bucket'] ?? 0 }} ({{ $approvalPct }}%)</strong></a>
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'completed']) }}"><i class="completed"></i><span>Hoàn thành</span><strong>{{ $summary['completed'] ?? 0 }} ({{ $completedPct }}%)</strong></a>
                    </div>
                </div>
                <div class="tm4-command-alerts">
                    <h3>Cảnh báo &amp; cần chú ý</h3>
                    <a class="danger" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','overdue'=>1]) }}"><i class="bi bi-exclamation-triangle"></i><span><strong>{{ number_format($summary['overdue']) }}</strong> lịch bảo trì quá hạn</span></a>
                    <a class="warning" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','status'=>'unassigned']) }}"><i class="bi bi-person-x"></i><span><strong>{{ number_format($summary['unassigned']) }}</strong> lịch chưa phân công kỹ thuật</span></a>
                    <a class="violet" href="{{ route('projects-unified.maintenance.index',['view'=>'claims','claim_status'=>'pending_approval']) }}"><i class="bi bi-file-earmark-check"></i><span><strong>{{ number_format($warrantySummary['pending_approval'] ?? 0) }}</strong> phiếu bảo hành chờ duyệt</span></a>
                    <a class="blue" href="{{ route('projects-unified.maintenance.index',['view'=>'stock','stock_status'=>'pending']) }}"><i class="bi bi-box-seam"></i><span><strong>{{ number_format($warrantySummary['stock_pending'] ?? 0) }}</strong> phiếu chờ kho xuất đổi</span></a>
                </div>
                <div class="tm4-activity-list">
                    <h3>Hoạt động nổi bật</h3>
                    @forelse($overviewSchedules->take(3) as $activitySchedule)
                        <a href="{{ route('projects-unified.maintenance.show',$activitySchedule) }}"><i class="{{ $activitySchedule->isOverdue() ? 'danger' : 'info' }}"></i><span>{{ $activitySchedule->site?->name ?: $activitySchedule->site_name }} — Đợt {{ max(1,(int)($activitySchedule->round_no ?: 1)) }} {{ $activitySchedule->isOverdue() ? 'đang quá hạn' : 'cần theo dõi' }}</span><small>{{ optional($activitySchedule->updated_at)->diffForHumans() }}</small></a>
                    @empty<p>Chưa có hoạt động mới.</p>@endforelse
                </div>
            </section>

            <section class="tm4-card tm4-span-4 tm4-workload-card">
                <div class="tm4-card-head"><div><span>NGUỒN LỰC KỸ THUẬT</span><h2>Kỹ thuật đang phụ trách</h2></div><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">Điều phối</a></div>
                <div class="tm4-workload-table">
                    <div class="tm4-workload-head"><span>Kỹ thuật viên</span><span>Công việc</span><span>Quá hạn</span><span>Hôm nay</span><span>Hoàn thành</span></div>
                    @forelse($technicianWorkload as $workload)
                        @php
                            $technician = $workload['user'];
                            $techInitial = mb_strtoupper(mb_substr(trim((string)$technician->name), 0, 1));
                        @endphp
                        <a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'','assignee_id'=>$technician->id]) }}" class="tm4-workload-row">
                            <span class="tech"><b>{{ $techInitial }}</b><span><strong>{{ $technician->name }}</strong><small>{{ $workload['active'] > 0 ? $workload['active_sites'].' công trình / '.$workload['active'].' việc' : 'Chưa có lịch đang xử lý' }}</small></span></span>
                            <span>{{ $workload['active'] }}</span>
                            <span class="{{ $workload['overdue'] > 0 ? 'text-danger fw-bold' : '' }}">{{ $workload['overdue'] }}</span>
                            <span class="text-primary fw-bold">{{ $workload['today'] }}</span>
                            <span class="rate"><i><u style="width:{{ $workload['completion_rate'] }}%"></u></i><strong>{{ $workload['completion_rate'] }}%</strong></span>
                        </a>
                    @empty<div class="tm4-empty small"><i class="bi bi-people"></i><p>Chưa tìm thấy nhân sự kỹ thuật khả dụng.</p></div>@endforelse
                </div>
            </section>

            <section class="tm4-card tm4-span-8 tm4-round-card">
                <div class="tm4-card-head"><div><span>TIẾN ĐỘ THEO CHU KỲ</span><h2>Lịch bảo trì sắp tới theo đợt</h2></div><a href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}"><i class="bi bi-chevron-right"></i></a></div>
                <div class="tm4-round-grid">
                    @forelse($maintenanceRounds as $round)
                        @php
                            $roundTone = ['green','amber','blue','violet'][($round['round'] - 1) % 4];
                            if ($round['start_date'] && $round['end_date']) {
                                $roundDateLabel = $round['start_date']->format('d/m').' - '.$round['end_date']->format('d/m/Y');
                            } else {
                                $roundDateLabel = 'Chưa thiết lập thời gian';
                            }
                            if ($round['total'] === 0) {
                                $roundTimeLabel = 'Chưa có kế hoạch';
                            } elseif ($round['days_to_start'] === null) {
                                $roundTimeLabel = 'Chưa xác định ngày';
                            } elseif ($round['days_to_start'] > 0) {
                                $roundTimeLabel = 'Còn '.$round['days_to_start'].' ngày';
                            } elseif ($round['days_to_start'] === 0) {
                                $roundTimeLabel = 'Bắt đầu hôm nay';
                            } else {
                                $roundTimeLabel = 'Đã bắt đầu '.abs((int)$round['days_to_start']).' ngày';
                            }
                        @endphp
                        <a class="tm4-round-item {{ $roundTone }}" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance','month'=>'']) }}">
                            <h3>Đợt {{ $round['round'] }}</h3>
                            <strong>{{ number_format($round['total']) }} công trình</strong>
                            <span>{{ $roundDateLabel }}</span>
                            <small>{{ $roundTimeLabel }}</small>
                            <i><u style="width:{{ $round['progress'] }}%"></u></i>
                            <em>{{ $round['progress'] }}% đã lên lịch</em>
                        </a>
                    @empty<div class="tm4-empty small"><i class="bi bi-calendar-range"></i><p>Chưa có dữ liệu chu kỳ bảo trì.</p></div>@endforelse
                </div>
            </section>

            <section class="tm4-card tm4-span-6 tm4-flow-card">
                <div class="tm4-card-head"><div><span>QUY TRÌNH 8 BƯỚC</span><h2>Luồng bảo trì định kỳ</h2></div></div>
                <div class="tm4-flow green-flow compact-flow">
                    @foreach($maintenanceFlow as $i => $step)
                        <div><span>{{ $i+1 }}</span><i class="bi bi-{{ $step[0] }}"></i><small>{{ $step[1] }}</small></div>@if($i<7)<b><i class="bi bi-arrow-right"></i></b>@endif
                    @endforeach
                </div>
            </section>

            <section class="tm4-card tm4-span-6 tm4-flow-card">
                <div class="tm4-card-head"><div><span>QUY TRÌNH 10 BƯỚC</span><h2>Luồng bảo hành / xử lý sự cố</h2></div></div>
                <div class="tm4-flow violet-flow compact-flow">
                    @foreach($warrantyFlow as $i => $step)
                        <div><span>{{ $i+1 }}</span><i class="bi bi-{{ $step[0] }}"></i><small>{{ $step[1] }}</small></div>@if($i<9)<b><i class="bi bi-arrow-right"></i></b>@endif
                    @endforeach
                </div>
            </section>
        </div>
    @endif

    @if($activeView === 'maintenance')
        <section class="tm4-kpi-grid maintenance-kpi">
            @foreach($maintenanceKpis as $kpi)
                @php
                    $kpiIcon = $kpi[0];
                    $kpiLabel = $kpi[1];
                    $kpiValue = $kpi[2];
                    $kpiTone = $kpi[3];
                    $kpiFilter = $kpi[4];
                    $kpiParams = ['view' => 'maintenance', 'month' => ''];
                    if ($kpiFilter === 'overdue') {
                        $kpiParams['overdue'] = 1;
                    } elseif ($kpiFilter !== '') {
                        $kpiParams['status'] = $kpiFilter;
                    }
                @endphp
                <a href="{{ route('projects-unified.maintenance.index', $kpiParams) }}" class="tm4-kpi"><span class="{{ $kpiTone }}"><i class="bi bi-{{ $kpiIcon }}"></i></span><div><small>{{ $kpiLabel }}</small><strong>{{ number_format($kpiValue) }}</strong></div></a>
            @endforeach
        </section>

        <section class="tm4-card tm4-filter-card"><form method="GET" action="{{ route('projects-unified.maintenance.index') }}" class="tm4-filter"><input type="hidden" name="view" value="maintenance"><label class="search"><i class="bi bi-search"></i><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Mã lịch, công trình, khách hàng, SĐT..."></label><input class="form-control" type="month" name="month" value="{{ $filters['month'] ?? '' }}"><select class="form-select" name="status"><option value="">Tất cả trạng thái</option>@foreach($statuses as $v=>$l)<option value="{{ $v }}" @selected(($filters['status']??'')===$v)>{{ $l }}</option>@endforeach</select><select class="form-select" name="type"><option value="">Tất cả hạng mục</option>@foreach($types as $v=>$l)<option value="{{ $v }}" @selected(($filters['type']??'')===$v)>{{ $l }}</option>@endforeach</select><select class="form-select" name="assignee_id"><option value="">Mọi kỹ thuật viên</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)($filters['assignee_id']??'')===(string)$user->id)>{{ $user->name }}</option>@endforeach</select><label class="tm4-check"><input type="checkbox" name="overdue" value="1" @checked(!empty($filters['overdue']))><span>Quá hạn</span></label><x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" type="submit"><i class="bi bi-funnel"></i> Lọc</x-ui.button><x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" href="{{ route('projects-unified.maintenance.index',['view'=>'maintenance']) }}"><i class="bi bi-arrow-counterclockwise"></i></x-ui.button></form></section>

        <section class="tm4-card">
            <div class="tm4-card-head"><div><span>ĐIỀU PHỐI KỸ THUẬT</span><h2>Danh sách lịch O&amp;M</h2><p>{{ number_format($schedules->total()) }} kết quả</p></div>@if($permissions['create'])<x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-[750]" data-bs-toggle="offcanvas" data-bs-target="#tm4CreateDrawer"><i class="bi bi-plus-lg"></i> Tạo kế hoạch</x-ui.button>@endif</div>
            <div class="tm4-table-wrap"><table class="tm4-table"><thead><tr><th>Mã &amp; công trình</th><th>Hạng mục / chu kỳ</th><th>Ngày dự kiến</th><th>Phụ trách</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead><tbody>
                @forelse($schedules as $schedule)
                    @php $overdue=$schedule->isOverdue();$roundNo=max(1,(int)($schedule->round_no?:1));$totalRounds=max(1,(int)($schedule->total_rounds?:1)); @endphp
                    <tr class="{{ $overdue?'is-overdue':'' }}"><td><div class="tm4-code-line"><a class="tm4-code" href="{{ route('projects-unified.maintenance.show',$schedule) }}">{{ $schedule->schedule_code ?: '#'.$schedule->id }}</a><span class="tm4-badge {{ $priorityTone[$schedule->priority]??'muted' }}">{{ $priorities[$schedule->priority]??$schedule->priority }}</span></div><a class="tm4-main-link" href="{{ $schedule->site_id ? route('projects-unified.maintenance.site',$schedule->site_id) : route('projects-unified.maintenance.show',$schedule) }}">{{ $schedule->site?->name ?: $schedule->site_name ?: 'Công trình chưa đặt tên' }}</a><small><i class="bi bi-geo-alt"></i> {{ \Illuminate\Support\Str::limit($schedule->site?->address ?: $schedule->address,75) }}</small></td><td><strong>{{ $types[$schedule->type]??$schedule->type }}</strong><small>Đợt {{ $roundNo }}/{{ $totalRounds }}</small><div class="tm4-progress"><span style="width:{{ min(100,round($roundNo/$totalRounds*100)) }}%"></span></div></td><td><strong class="{{ $overdue?'text-danger':'' }}">{{ optional($schedule->scheduled_date)->format('d/m/Y') }}</strong><small>{{ $overdue?'Quá hạn '.optional($schedule->scheduled_date)->diffInDays(today()).' ngày':optional($schedule->scheduled_date)->translatedFormat('l') }}</small></td><td><div class="tm4-person"><i class="bi bi-people"></i><div><strong>{{ $schedule->leader?->user?->name ?: $schedule->assignee_names }}</strong><small>{{ $schedule->assignees->count() }} người</small></div></div></td><td><span class="tm4-badge {{ $statusTone[$schedule->status]??'muted' }}">{{ $statuses[$schedule->status]??$schedule->status }}</span></td><td><div class="tm4-actions"><a href="{{ route('projects-unified.maintenance.show',$schedule) }}" title="Chi tiết"><i class="bi bi-eye"></i></a>@can('update',$schedule)@if(!in_array($schedule->status,['pending_approval','approved','completed'],true))<button type="button" title="Sửa nhanh" data-tm3-edit-url="{{ route('projects-unified.maintenance.json',$schedule) }}" data-tm3-update-url="{{ route('projects-unified.maintenance.update',$schedule) }}"><i class="bi bi-pencil-square"></i></button>@endif @endcan @can('changeStatus',$schedule)@if(!in_array($schedule->status,['pending_approval','approved','completed'],true))<button type="button" title="Đổi trạng thái" data-tm3-status-url="{{ route('projects-unified.maintenance.status',$schedule) }}" data-tm3-current-status="{{ $schedule->status }}" data-tm3-code="{{ $schedule->schedule_code }}"><i class="bi bi-arrow-repeat"></i></button>@endif @endcan</div></td></tr>
                @empty<tr><td colspan="6"><div class="tm4-empty"><i class="bi bi-calendar2-x"></i><h3>Chưa có lịch phù hợp</h3><p>Thử đổi bộ lọc hoặc tạo kế hoạch mới.</p></div></td></tr>@endforelse
            </tbody></table></div>@if(method_exists($schedules,'hasPages') && $schedules->hasPages())<div class="tm4-pagination">{{ $schedules->links() }}</div>@endif
        </section>
    @endif

    @if($activeView === 'claims')
        <section class="tm4-kpi-grid maintenance-kpi">
            @foreach($claimKpis as $claimKpi)
                <div class="tm4-kpi"><span class="{{ $claimKpi[3] }}"><i class="bi bi-{{ $claimKpi[0] }}"></i></span><div><small>{{ $claimKpi[1] }}</small><strong>{{ number_format($claimKpi[2]) }}</strong></div></div>
            @endforeach
        </section>
        <section class="tm4-card tm4-filter-card"><form class="tm4-filter claim-filter" method="GET"><input type="hidden" name="view" value="claims"><label class="search"><i class="bi bi-search"></i><input name="claim_q" value="{{ request('claim_q') }}" placeholder="Mã phiếu, công trình, serial, hiện tượng..."></label><select class="form-select" name="claim_status"><option value="">Tất cả trạng thái</option>@foreach($claimStatuses as $v=>$l)<option value="{{ $v }}" @selected(request('claim_status')===$v)>{{ $l }}</option>@endforeach</select><select class="form-select" name="claim_type"><option value="">Tất cả loại phiếu</option>@foreach($claimTypes as $v=>$l)<option value="{{ $v }}" @selected(request('claim_type')===$v)>{{ $l }}</option>@endforeach</select><x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" type="submit"><i class="bi bi-funnel"></i> Lọc</x-ui.button><x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" href="{{ route('projects-unified.maintenance.index',['view'=>'claims']) }}"><i class="bi bi-arrow-counterclockwise"></i></x-ui.button></form></section>
        <section class="tm4-card"><div class="tm4-card-head"><div><span>TIẾP NHẬN &amp; XỬ LÝ</span><h2>Phiếu sự cố &amp; bảo hành</h2><p>{{ method_exists($claims,'total') ? number_format($claims->total()) : 0 }} phiếu</p></div>@if($permissions['claim_create'])<x-ui.button variant="danger" size="none" class="tw:px-3 tw:py-[6px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-[750]" data-bs-toggle="offcanvas" data-bs-target="#tm4ClaimDrawer"><i class="bi bi-plus-lg"></i> Tạo phiếu</x-ui.button>@endif</div>
            <div class="tm4-table-wrap"><table class="tm4-table"><thead><tr><th>Mã phiếu / công trình</th><th>Thiết bị &amp; hiện tượng</th><th>Ngày tiếp nhận</th><th>Phụ trách</th><th>Trạng thái</th><th class="text-end">Xử lý</th></tr></thead><tbody>
            @forelse($claims as $claim)
                <tr><td><div class="tm4-code-line"><strong class="tm4-code">{{ $claim->claim_code ?: '#'.$claim->id }}</strong><span class="tm4-badge {{ $priorityTone[$claim->priority]??'muted' }}">{{ $claimPriorities[$claim->priority]??$claim->priority }}</span></div><a class="tm4-main-link" href="{{ $claim->site_id ? route('projects-unified.maintenance.site',$claim->site_id) : '#' }}">{{ $claim->site?->name ?: 'Chưa liên kết công trình' }}</a><small>{{ $claimTypes[$claim->claim_type]??$claim->claim_type }}</small></td><td><strong>{{ $claim->serial_code ?: 'Sự cố không gắn serial' }}</strong><small>{{ \Illuminate\Support\Str::limit($claim->issue_description,100) }}</small>@if($claim->replacement_serial_code)<small class="text-success"><i class="bi bi-arrow-repeat"></i> Serial thay: {{ $claim->replacement_serial_code }}</small>@endif</td><td><strong>{{ optional($claim->received_at)->format('d/m/Y') }}</strong><small>{{ optional($claim->created_at)->format('H:i') }}</small></td><td><div class="tm4-person"><i class="bi bi-person-gear"></i><div><strong>{{ $claim->assignee?->name ?: $claim->assigned_name ?: 'Chưa phân công' }}</strong><small>{{ $claim->stockMovements->count() }} phiếu kho</small></div></div></td><td><span class="tm4-badge {{ $claimTone[$claim->status]??'muted' }}">{{ $claimStatuses[$claim->status]??$claim->status }}</span></td><td><div class="tm4-actions">@if($permissions['manager'] || (int)$claim->assigned_to===(int)auth()->id())<button type="button" data-tm4-claim-status-url="{{ route('projects-unified.maintenance.claims.status',$claim) }}" data-tm4-claim-code="{{ $claim->claim_code }}" data-tm4-claim-status="{{ $claim->status }}" data-tm4-claim-assigned="{{ $claim->assigned_to }}" data-tm4-claim-diagnosis="{{ $claim->diagnosis }}" data-tm4-claim-solution="{{ $claim->proposed_solution }}" data-tm4-claim-resolution="{{ $claim->resolution }}" title="Cập nhật phiếu"><i class="bi bi-pencil-square"></i></button>@endif @if($permissions['stock_manage'] && in_array($claim->status,['approved','waiting_stock','replacing','waiting_customer']))<button type="button" data-tm4-stock-claim="{{ $claim->id }}" data-bs-toggle="offcanvas" data-bs-target="#tm4StockDrawer" title="Tạo phiếu kho"><i class="bi bi-box-seam"></i></button>@endif</div></td></tr>
            @empty<tr><td colspan="6"><div class="tm4-empty"><i class="bi bi-shield-check"></i><h3>Chưa có phiếu sự cố</h3><p>Mọi phiếu mới sẽ được liên kết theo công trình và serial.</p></div></td></tr>@endforelse
            </tbody></table></div>@if(method_exists($claims,'hasPages') && $claims->hasPages())<div class="tm4-pagination">{{ $claims->links() }}</div>@endif
        </section>
    @endif

    @if($activeView === 'stock')
        <section class="tm4-card tm4-filter-card"><form class="tm4-filter stock-filter" method="GET"><input type="hidden" name="view" value="stock"><select class="form-select" name="stock_status"><option value="">Tất cả trạng thái</option>@foreach($stockStatuses as $v=>$l)<option value="{{ $v }}" @selected(request('stock_status')===$v)>{{ $l }}</option>@endforeach</select><select class="form-select" name="stock_type"><option value="">Tất cả nghiệp vụ</option>@foreach($stockTypes as $v=>$l)<option value="{{ $v }}" @selected(request('stock_type')===$v)>{{ $l }}</option>@endforeach</select><x-ui.button variant="primary" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" type="submit"><i class="bi bi-funnel"></i> Lọc</x-ui.button><x-ui.button variant="light" size="none" class="tw:px-3 tw:py-[6px] tw:min-h-[40px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-normal" href="{{ route('projects-unified.maintenance.index',['view'=>'stock']) }}"><i class="bi bi-arrow-counterclockwise"></i></x-ui.button></form></section>
        <section class="tm4-card"><div class="tm4-card-head"><div><span>KHO &amp; SERIAL</span><h2>Xuất đổi và thu hồi thiết bị</h2><p>{{ method_exists($stockMovements,'total') ? number_format($stockMovements->total()) : 0 }} phiếu kho</p></div>@if($permissions['stock_manage'])<x-ui.button variant="success" size="none" class="tw:px-3 tw:py-[6px] tw:rounded-[9px] tw:text-[12px]/[18px] tw:font-[750]" data-bs-toggle="offcanvas" data-bs-target="#tm4StockDrawer"><i class="bi bi-box-arrow-up-right"></i> Tạo phiếu kho</x-ui.button>@endif</div>
        <div class="tm4-table-wrap"><table class="tm4-table"><thead><tr><th>Mã phiếu kho</th><th>Nghiệp vụ</th><th>Phiếu bảo hành / công trình</th><th>Serial / kho</th><th>Ngày tạo</th><th>Trạng thái</th><th class="text-end">Xử lý</th></tr></thead><tbody>
        @forelse($stockMovements as $movement)
            <tr><td><strong class="tm4-code">{{ $movement->movement_code ?: '#'.$movement->id }}</strong><small>{{ $movement->requester?->name ?: 'Hệ thống' }}</small></td><td><strong>{{ $stockTypes[$movement->movement_type]??$movement->movement_type }}</strong><small>{{ \Illuminate\Support\Str::limit($movement->note,70) }}</small></td><td><strong>{{ $movement->claim?->claim_code }}</strong><small>{{ $movement->site?->name ?: 'Chưa liên kết công trình' }}</small></td><td><strong>{{ $movement->serial_code ?: '—' }}</strong><small>{{ $movement->warehouse?->name ?: 'Chưa chọn kho' }}</small>@if($movement->related_serial_code)<small>Liên quan: {{ $movement->related_serial_code }}</small>@endif</td><td><strong>{{ optional($movement->requested_at)->format('d/m/Y') }}</strong><small>{{ optional($movement->requested_at)->format('H:i') }}</small></td><td><span class="tm4-badge {{ $stockTone[$movement->status]??'muted' }}">{{ $stockStatuses[$movement->status]??$movement->status }}</span></td><td><div class="tm4-actions">@if($permissions['stock_manage'])<button type="button" data-tm4-stock-status-url="{{ route('projects-unified.maintenance.stock.status',$movement) }}" data-tm4-stock-code="{{ $movement->movement_code }}" data-tm4-stock-status="{{ $movement->status }}" title="Cập nhật"><i class="bi bi-arrow-repeat"></i></button>@endif</div></td></tr>
        @empty<tr><td colspan="7"><div class="tm4-empty"><i class="bi bi-box-seam"></i><h3>Chưa có phiếu kho bảo hành</h3><p>Tạo phiếu để theo dõi xuất đổi, thu hồi và gửi nhà cung cấp.</p></div></td></tr>@endforelse
        </tbody></table></div>@if(method_exists($stockMovements,'hasPages') && $stockMovements->hasPages())<div class="tm4-pagination">{{ $stockMovements->links() }}</div>@endif</section>
    @endif

    @if($activeView === 'files')
        <section class="tm4-card"><div class="tm4-card-head"><div><span>LƯU TRỮ THEO MÃ DỰ ÁN</span><h2>Hồ sơ công trình O&amp;M</h2><p>Hợp đồng, bản vẽ, nghiệm thu, bảo hành và báo cáo được gom theo từng công trình.</p></div></div>
            <div class="tm4-project-grid">
            @forelse($documentSites as $site)
                <a href="{{ route('projects-unified.maintenance.site',$site->id) }}"><span><i class="bi bi-folder2-open"></i></span><div><h3>{{ $site->name }}</h3><p>{{ $site->contact_name ?: 'Chưa có khách hàng' }}</p><small><b>{{ number_format($site->documents_count) }}</b> hồ sơ · <b>{{ number_format($site->maintenance_count) }}</b> lịch O&amp;M</small></div><i class="bi bi-chevron-right"></i></a>
            @empty<div class="tm4-empty"><i class="bi bi-folder-x"></i><h3>Chưa có hồ sơ công trình</h3><p>Hồ sơ tải lên từ trang chi tiết dự án sẽ xuất hiện tại đây.</p></div>@endforelse
            </div>
        </section>
    @endif
</div>

@if($permissions['create'])
<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm4CreateDrawer"><div class="offcanvas-header tm4-drawer-head"><div><span>TẠO KẾ HOẠCH O&amp;M</span><h2>Lịch bảo trì mới</h2></div><button class="btn-close" data-bs-dismiss="offcanvas"></button></div><form method="POST" action="{{ route('projects-unified.maintenance.store') }}" class="offcanvas-body tm4-drawer-body" id="tm3CreateForm">@csrf
    <section class="tm4-form-section"><div class="tm4-form-title"><b>1</b><div><strong>Chọn công trình</strong><small>Bắt buộc liên kết đúng mã dự án.</small></div></div><label class="form-label">Công trình <span class="text-danger">*</span></label><select class="form-select" name="site_id" id="tm3SiteSelect" data-search-url="{{ route('projects-unified.maintenance.sites-search') }}" required><option value="">Tìm theo công trình, khách hàng hoặc số điện thoại...</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string)old('site_id',request('site_id'))===(string)$site->id) data-name="{{ $site->name }}" data-customer="{{ $site->contact_name }}" data-address="{{ $site->address }}" data-kwp="{{ $site->system_kwp }}">{{ $site->name }}{{ $site->contact_name?' — '.$site->contact_name:'' }}</option>@endforeach</select><div class="tm4-readonly-grid"><label>Khách hàng<input class="form-control" name="customer_name" id="tm3CustomerName" value="{{ old('customer_name') }}" readonly></label><label>Tên công trình<input class="form-control" name="site_name" id="tm3SiteName" value="{{ old('site_name') }}" readonly></label><label class="full">Địa chỉ<input class="form-control" name="address" id="tm3Address" value="{{ old('address') }}" readonly></label></div></section>
    <section class="tm4-form-section"><div class="tm4-form-title"><b>2</b><div><strong>Thiết lập chu kỳ</strong><small>Ngày bắt đầu, số đợt và khoảng cách.</small></div></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Hạng mục</label><select class="form-select" name="type" required>@foreach($types as $v=>$l)<option value="{{ $v }}" @selected(old('type','periodic')===$v)>{{ $l }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Ưu tiên</label><select class="form-select" name="priority" required>@foreach($priorities as $v=>$l)<option value="{{ $v }}" @selected(old('priority','normal')===$v)>{{ $l }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Ngày bắt đầu</label><input class="form-control" type="date" name="scheduled_date" id="tm3BaseDate" value="{{ old('scheduled_date',now()->toDateString()) }}" required></div><div class="col-md-3"><label class="form-label">Số đợt</label><input class="form-control" type="number" name="rounds_count" id="tm3RoundsCount" value="{{ old('rounds_count',1) }}" min="1" max="24"></div><div class="col-md-3"><label class="form-label">Cách nhau</label><select class="form-select" name="round_interval_months" id="tm3RoundInterval"><option value="1">1 tháng</option><option value="3" selected>3 tháng</option><option value="6">6 tháng</option><option value="12">12 tháng</option></select></div></div><div id="tm3RoundsPreview" class="tm4-round-preview"></div></section>
    <section class="tm4-form-section"><div class="tm4-form-title"><b>3</b><div><strong>Phân công &amp; kỹ thuật</strong><small>Người đầu tiên là trưởng nhóm.</small></div></div><label class="form-label">Kỹ thuật viên</label><select class="form-select" name="assigned_user_ids[]" id="tm3CreateAssignees" multiple>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select><div class="row g-3 mt-1"><div class="col-md-5"><label class="form-label">Công suất kWp</label><input class="form-control" type="number" step="0.01" name="system_kwp" id="tm3SystemKwp"></div><div class="col-md-7"><label class="form-label">Inverter / thiết bị</label><input class="form-control" name="inverter_info"></div><div class="col-12"><label class="form-label">Hiện trạng / yêu cầu</label><textarea class="form-control" name="issue_note" rows="3"></textarea></div><div class="col-12"><label class="form-label">Ghi chú nội bộ</label><textarea class="form-control" name="technical_note" rows="2"></textarea></div></div></section>
    <div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="primary" type="submit"><i class="bi bi-check2-circle"></i> Tạo kế hoạch</x-ui.button></div></form></div>
@endif

@if($permissions['claim_create'])
<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm4ClaimDrawer"><div class="offcanvas-header tm4-drawer-head red"><div><span>TIẾP NHẬN SỰ CỐ</span><h2>Tạo phiếu bảo hành</h2></div><button class="btn-close" data-bs-dismiss="offcanvas"></button></div><form method="POST" action="{{ route('projects-unified.maintenance.claims.store') }}" class="offcanvas-body tm4-drawer-body">@csrf
    <section class="tm4-form-section"><div class="tm4-form-title red"><b>1</b><div><strong>Công trình &amp; thiết bị</strong><small>Phiếu luôn gắn vào một dự án cụ thể.</small></div></div><label class="form-label">Công trình <span class="text-danger">*</span></label><select class="form-select" name="site_id" required><option value="">Chọn công trình...</option>@foreach($sites as $site)<option value="{{ $site->id }}" @selected((string)old('site_id')===(string)$site->id)>{{ $site->name }}{{ $site->contact_name?' — '.$site->contact_name:'' }}</option>@endforeach</select><label class="form-label mt-3">Serial thiết bị lỗi</label><input class="form-control" name="serial_code" value="{{ old('serial_code') }}" placeholder="Để trống nếu sự cố toàn hệ thống"><small class="tm4-help">Serial nhập vào sẽ được đối chiếu với kho và hồ sơ bảo hành.</small></section>
    <section class="tm4-form-section"><div class="tm4-form-title red"><b>2</b><div><strong>Thông tin tiếp nhận</strong><small>Phân loại, ưu tiên và người xử lý.</small></div></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Loại phiếu</label><select class="form-select" name="claim_type" required>@foreach($claimTypes as $v=>$l)<option value="{{ $v }}" @selected(old('claim_type','warranty')===$v)>{{ $l }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Ưu tiên</label><select class="form-select" name="priority" required>@foreach($claimPriorities as $v=>$l)<option value="{{ $v }}" @selected(old('priority','normal')===$v)>{{ $l }}</option>@endforeach</select></div><div class="col-12"><label class="form-label">Kỹ thuật viên phụ trách</label>@if($permissions['technician_only'])<input type="hidden" name="assigned_to" value="{{ auth()->id() }}"><div class="tm4-assignee-fixed"><i class="bi bi-person-check"></i><strong>{{ auth()->user()->name }}</strong><small>Tự tiếp nhận</small></div>@else<select class="form-select" name="assigned_to"><option value="">Chưa phân công</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)old('assigned_to')===(string)$user->id)>{{ $user->name }}</option>@endforeach</select>@endif</div><div class="col-12"><label class="form-label">Mô tả hiện tượng <span class="text-danger">*</span></label><textarea class="form-control" name="issue_description" rows="5" required placeholder="Khách hàng phản ánh gì, thiết bị báo lỗi gì, thời điểm phát sinh...">{{ old('issue_description') }}</textarea></div><div class="col-12"><label class="form-label">Ghi chú nội bộ</label><textarea class="form-control" name="internal_note" rows="2">{{ old('internal_note') }}</textarea></div><div class="col-md-6"><label class="tm4-switch"><input type="checkbox" name="is_chargeable" value="1" @checked(old('is_chargeable'))><span></span> Sửa chữa tính phí</label></div><div class="col-md-6"><label class="form-label">Chi phí dự kiến</label><input class="form-control" type="number" name="estimated_cost" min="0" value="{{ old('estimated_cost',0) }}"></div></div></section>
    <div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="danger" type="submit"><i class="bi bi-shield-plus"></i> Tạo phiếu</x-ui.button></div></form></div>
@endif

@if($permissions['stock_manage'])
<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm4StockDrawer"><div class="offcanvas-header tm4-drawer-head green"><div><span>KHO BẢO HÀNH</span><h2>Tạo phiếu xuất / thu hồi</h2></div><button class="btn-close" data-bs-dismiss="offcanvas"></button></div><form method="POST" action="{{ route('projects-unified.maintenance.stock.store') }}" class="offcanvas-body tm4-drawer-body">@csrf
    <section class="tm4-form-section"><div class="tm4-form-title green"><b>1</b><div><strong>Chọn phiếu bảo hành</strong><small>Kho chỉ xử lý theo yêu cầu đã ghi nhận.</small></div></div><label class="form-label">Phiếu sự cố / bảo hành</label><select class="form-select" name="warranty_claim_id" id="tm4StockClaim" required><option value="">Chọn phiếu...</option>@foreach($openClaims as $claim)<option value="{{ $claim->id }}" @selected((string)old('warranty_claim_id')===(string)$claim->id)>{{ $claim->claim_code }} — {{ $claim->site?->name }}{{ $claim->serial_code?' — '.$claim->serial_code:'' }}</option>@endforeach</select></section>
    <section class="tm4-form-section"><div class="tm4-form-title green"><b>2</b><div><strong>Nghiệp vụ &amp; serial</strong><small>Ghi nhận chính xác thiết bị xuất/thu hồi.</small></div></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Nghiệp vụ</label><select class="form-select" name="movement_type" required>@foreach($stockTypes as $v=>$l)<option value="{{ $v }}" @selected(old('movement_type','warranty_out')===$v)>{{ $l }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Kho</label><select class="form-select" name="warehouse_id" required><option value="">Chọn kho...</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)old('warehouse_id')===(string)$warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Serial xử lý <span class="text-danger">*</span></label><input class="form-control" name="serial_code" value="{{ old('serial_code') }}" required placeholder="Serial xuất hoặc thu hồi"></div><div class="col-md-6"><label class="form-label">Serial liên quan</label><input class="form-control" name="related_serial_code" value="{{ old('related_serial_code') }}" placeholder="Ví dụ serial cũ/mới đối ứng"></div><div class="col-md-4"><label class="form-label">Số lượng</label><input class="form-control" type="number" name="quantity" step="1" min="1" max="1" value="1" readonly></div><div class="col-md-8"><label class="form-label">Ghi chú</label><input class="form-control" name="note" value="{{ old('note') }}" placeholder="Tình trạng hàng, phụ kiện đi kèm..."></div></div></section>
    <div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="success" type="submit"><i class="bi bi-box-arrow-up-right"></i> Tạo phiếu kho</x-ui.button></div></form></div>
@endif

<div class="offcanvas offcanvas-end tm4-drawer" tabindex="-1" id="tm3EditDrawer"><div class="offcanvas-header tm4-drawer-head"><div><span>SỬA THÔNG TIN</span><h2 id="tm3EditTitle">Đang tải...</h2></div><button class="btn-close" data-bs-dismiss="offcanvas"></button></div><form method="POST" action="#" class="offcanvas-body tm4-drawer-body" id="tm3EditForm">@csrf @method('PUT')<div class="tm4-loading" id="tm3EditLoading">Đang tải dữ liệu...</div><div id="tm3EditContent" hidden><div class="tm4-reference"><div><small>Công trình</small><strong id="tm3EditSite">—</strong></div><div><small>Chu kỳ</small><strong id="tm3EditRound">—</strong></div><div><small>Khách hàng</small><strong id="tm3EditCustomer">—</strong></div></div><section class="tm4-form-section"><div class="row g-3"><div class="col-md-6"><label class="form-label">Ngày dự kiến</label><input class="form-control" type="date" name="scheduled_date" data-edit-field="scheduled_date"></div><div class="col-md-6"><label class="form-label">Ưu tiên</label><select class="form-select" name="priority" data-edit-field="priority">@foreach($priorities as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Hạng mục</label><select class="form-select" name="type" data-edit-field="type">@foreach($types as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div><div class="col-12"><label class="form-label">Người phụ trách</label><select class="form-select" name="assigned_user_ids[]" data-edit-field="assigned_user_ids" multiple>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div><div class="col-12"><label class="form-label">Ghi chú kỹ thuật</label><textarea class="form-control" name="technical_note" rows="4" data-edit-field="technical_note"></textarea></div></div><div class="tm4-notice"><i class="bi bi-shield-lock"></i><span>Trạng thái phê duyệt không thể đổi tại form sửa nhanh. Hãy dùng đúng nút trong quy trình.</span></div></section></div><div class="tm4-drawer-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="offcanvas">Đóng</x-ui.button><x-ui.button variant="outline-primary" href="#" id="tm3EditFullLink">Mở chi tiết</x-ui.button><x-ui.button variant="primary" type="submit"><i class="bi bi-save"></i> Lưu</x-ui.button></div></form></div>

<div class="modal fade" id="tm3StatusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content tm4-modal" method="POST" action="#" id="tm3StatusForm">@csrf<div class="modal-header"><div><small>CẬP NHẬT QUY TRÌNH</small><h2>Đổi trạng thái lịch</h2><span id="tm3StatusCode"></span></div><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Trạng thái mới</label><select class="form-select" name="status" id="tm3StatusSelect" required>@foreach($manualStatuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select><label class="form-label mt-3">Lý do</label><input class="form-control" name="reason" placeholder="Bắt buộc khi hoãn, hủy hoặc mở lại"><label class="form-label mt-3">Kết quả / ghi chú</label><textarea class="form-control" name="result_note" rows="4"></textarea></div><div class="modal-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="modal">Đóng</x-ui.button><x-ui.button variant="primary" type="submit"><i class="bi bi-arrow-repeat"></i> Cập nhật</x-ui.button></div></form></div></div>

<div class="modal fade" id="tm4ClaimStatusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content tm4-modal" method="POST" action="#" id="tm4ClaimStatusForm">@csrf<div class="modal-header"><div><small>XỬ LÝ PHIẾU BẢO HÀNH</small><h2 id="tm4ClaimStatusCode">Cập nhật phiếu</h2></div><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-6"><label class="form-label">Trạng thái mới</label><select class="form-select" name="status" id="tm4ClaimStatusSelect">@foreach($claimStatuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div><div class="col-md-6"><label class="form-label">Chi phí thực tế</label><input class="form-control" type="number" name="actual_cost" min="0"></div>@if($permissions['manager'])<div class="col-md-6"><label class="form-label">Người phụ trách</label><select class="form-select" name="assigned_to" id="tm4ClaimAssignee"><option value="">Chưa phân công</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>@endif<div class="col-12"><label class="form-label">Chẩn đoán</label><textarea class="form-control" name="diagnosis" id="tm4ClaimDiagnosis" rows="3"></textarea></div><div class="col-12"><label class="form-label">Phương án đề xuất</label><textarea class="form-control" name="proposed_solution" id="tm4ClaimSolution" rows="3"></textarea></div><div class="col-12"><label class="form-label">Kết quả xử lý</label><textarea class="form-control" name="resolution" id="tm4ClaimResolution" rows="3"></textarea></div><div class="col-12"><label class="form-label">Ghi chú phê duyệt</label><textarea class="form-control" name="approval_note" rows="2"></textarea></div></div></div><div class="modal-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="modal">Đóng</x-ui.button><x-ui.button variant="danger" type="submit"><i class="bi bi-save"></i> Cập nhật phiếu</x-ui.button></div></form></div></div>

<div class="modal fade" id="tm4StockStatusModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content tm4-modal" method="POST" action="#" id="tm4StockStatusForm">@csrf<div class="modal-header"><div><small>XỬ LÝ PHIẾU KHO</small><h2 id="tm4StockStatusCode">Cập nhật phiếu</h2></div><button class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label class="form-label">Trạng thái</label><select class="form-select" name="status" id="tm4StockStatusSelect">@foreach($stockStatuses as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select><label class="form-label mt-3">Ghi chú</label><textarea class="form-control" name="note" rows="4"></textarea></div><div class="modal-footer"><x-ui.button variant="light" type="button" data-bs-dismiss="modal">Đóng</x-ui.button><x-ui.button variant="success" type="submit"><i class="bi bi-check2-circle"></i> Cập nhật</x-ui.button></div></form></div></div>
@endsection

@section('scripts')
<script>window.TM3 = @json($tmConfigForJs);</script>
<script src="{{ asset('js/technical-maintenance-v4.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v4.js')) ? filemtime(public_path('js/technical-maintenance-v4.js')) : time() }}"></script>
@endsection
