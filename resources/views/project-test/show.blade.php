@extends('layouts.app')

@section('title', $project->code.' · Công trình')

@push('styles')
{{--
    EGO_CONSTRUCTION_DETAIL_STABILITY_V2
    Nạp skin sidebar trắng trong <head> để không chớp sidebar xanh cũ khi trang chi tiết có HTML lớn.
    Không thay đổi sidebar/menu dùng chung; chỉ nạp sớm đúng stylesheet đang được layout sử dụng.
--}}
<link rel="stylesheet" href="{{ asset('css/crm-sidebar-misa.css') }}?v={{ file_exists(public_path('css/crm-sidebar-misa.css')) ? filemtime(public_path('css/crm-sidebar-misa.css')) : '2.0.0' }}">
<script>document.documentElement.classList.add('crm-misa-sidebar-ready');</script>
<link rel="stylesheet" href="{{ asset('css/project-test.css') }}?v={{ file_exists(public_path('css/project-test.css')) ? filemtime(public_path('css/project-test.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/project-warehouse-360-v1.css') }}?v={{ file_exists(public_path('css/project-warehouse-360-v1.css')) ? filemtime(public_path('css/project-warehouse-360-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/project-workflow-360-v2.css') }}?v={{ file_exists(public_path('css/project-workflow-360-v2.css')) ? filemtime(public_path('css/project-workflow-360-v2.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/project-material-workspace-v8.css') }}?v={{ file_exists(public_path('css/project-material-workspace-v8.css')) ? filemtime(public_path('css/project-material-workspace-v8.css')) : time() }}">

@endpush

@section('content')
@php
    $groups = ['Tiếp nhận','Khảo sát','Phương án','Vật tư','Thi công','Nghiệm thu','Bảo hành'];
    $groupIcons = [
        'Tiếp nhận' => 'bi-inbox',
        'Khảo sát' => 'bi-calendar-check',
        'Phương án' => 'bi-badge-3d',
        'Vật tư' => 'bi-box-seam',
        'Thi công' => 'bi-tools',
        'Nghiệm thu' => 'bi-clipboard-check',
        'Bảo hành' => 'bi-shield-check',
    ];
    $groupTargetTabs = [
        'Tiếp nhận' => 'overview',
        'Khảo sát' => 'survey',
        'Phương án' => 'survey',
        'Vật tư' => 'materials',
        'Thi công' => 'installation',
        'Nghiệm thu' => 'acceptance',
        'Bảo hành' => 'acceptance',
    ];
    $currentGroup = $statusInfo['group'] ?? 'Tiếp nhận';
    if (! in_array($currentGroup, $groups, true)) {
        $currentGroup = in_array($project->status, ['completed', 'warranty_active'], true)
            ? 'Bảo hành'
            : 'Tiếp nhận';
    }
    $currentGroupIndex = array_search($currentGroup, $groups, true);
    $currentGroupIndex = $currentGroupIndex === false ? 0 : $currentGroupIndex;
    $workflowCancelled = $project->status === 'cancelled';
    $preliminary = $project->proposal?->preliminary_materials_json ?: [];
    // EGO_PROJECT_WAREHOUSE_EMBEDDED_V2_MODE
    $warehouseOnlyMode = (bool) ($can['warehouse'] ?? false) && ! (bool) ($can['admin'] ?? false);

    // EGO_MATERIAL_LAYOUT_V41: công nhận đề nghị nhập tay; Kho đối chiếu SKU trong cùng mục Vật tư đề xuất.
    $selectedMaterialRequestId = (int) request('material_request', 0);
    $v4MaterialStatuses = ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'revision', 'issued'];
    $officialMaterialRequestsForStep = $project->materialRequests
        ->filter(function ($request) use ($v4MaterialStatuses): bool {
            return in_array((string) $request->status, $v4MaterialStatuses, true)
                && collect($request->items ?? [])->contains(fn ($item): bool => trim((string) ($item->item_name ?? '')) !== '');
        })
        ->sortByDesc('id')
        ->values();
    $materialRequestForStep = $selectedMaterialRequestId > 0
        ? $officialMaterialRequestsForStep->firstWhere('id', $selectedMaterialRequestId)
        : $officialMaterialRequestsForStep->first();
    $hasOfficialMaterialWorkflow = (bool) $materialRequestForStep;

    $materialInlineWorkflow = ! $warehouseOnlyMode
        && $materialRequestForStep
        && in_array($project->status, [
            'materials_admin_review',
            'materials_revision',
            'warehouse_preparing',
        ], true);

    $materialPhaseContext = request('tab') === 'materials'
        || in_array($project->status, [
            'materials_pending',
            'materials_revision',
            'materials_admin_review',
            'warehouse_preparing',
        ], true);

    $displayStatusLabel = ! $hasOfficialMaterialWorkflow
        && in_array($project->status, ['materials_pending', 'materials_revision', 'materials_admin_review', 'warehouse_preparing'], true)
            ? 'Chờ Kỹ thuật gửi đề nghị cấp vật tư'
            : match($project->status) {
                'warehouse_preparing' => 'Kho đang xử lý',
                'materials_admin_review' => 'Chờ Quản lý phê duyệt',
                'materials_revision' => 'Chờ Kỹ thuật điều chỉnh danh sách',
                default => $statusInfo['label'],
            };
@endphp
<div class="pt-page {{ $warehouseOnlyMode ? 'is-warehouse-only' : '' }}" data-project-default-tab="{{ request('tab', $materialPhaseContext ? 'materials' : 'overview') }}">
<div class="pt-shell">
    @if(session('success'))<div class="pt-alert pt-alert--success" style="margin-bottom:14px"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>@endif
    @if(session('error'))<div class="pt-alert" style="margin-bottom:14px"><i class="bi bi-exclamation-triangle"></i> <strong>Không thể xử lý:</strong> {{ session('error') }}</div>@endif
    @if($errors->any())<div class="pt-alert" style="margin-bottom:14px"><i class="bi bi-exclamation-triangle"></i> <strong>Không thể xử lý:</strong> {{ $errors->first() }}</div>@endif

    <section class="pt-card pt-detail-hero">
        <div class="pt-detail-top">
            <div>
                <div class="pt-detail-code"><span class="pt-new">VẬN HÀNH</span><span>{{ $project->code }}</span></div>
                <h1>{{ $project->name }}</h1>
                <div class="pt-meta">
                    <span><i class="bi bi-geo-alt"></i>{{ $project->address ?: 'Chưa có địa chỉ' }}</span>
                    <span><i class="bi bi-person"></i>{{ $project->contact_name ?: 'Chưa có người liên hệ' }}</span>
                    <span><i class="bi bi-telephone"></i>{{ $project->contact_phone ?: '—' }}</span>
                    <span><i class="bi bi-lightning-charge"></i>{{ $project->estimated_kwp ? number_format((float)$project->estimated_kwp,2).' kWp' : 'Chưa chốt công suất' }}</span>
                </div>
            </div>
            <div class="pt-current">
                <small>Đơn vị đang giữ bước</small>
                <strong>{{ $displayStatusLabel }}</strong>
                <div class="pt-progress"><span style="width:{{ $project->progress }}%"></span></div>
                <div class="pt-help">Tiến độ workflow: {{ $project->progress }}%</div>
            </div>
        </div>
    </section>

    {{-- EGO_PROJECT_WORKFLOW_360_V2 --}}
    <div class="pt-flow pt-flow--360" data-workflow-360 aria-label="Quy trình công trình 7 giai đoạn">
        @foreach($groups as $index => $group)
            @php
                $isDone = ! $workflowCancelled && $index < $currentGroupIndex;
                $isCurrent = ! $workflowCancelled && $index === $currentGroupIndex;
                $stageClass = $isDone ? 'is-done' : ($isCurrent ? 'is-current' : '');
                if ($workflowCancelled && $index === $currentGroupIndex) $stageClass = 'is-cancelled';
            @endphp
            <button
                type="button"
                class="pt-flow__step {{ $stageClass }}"
                data-workflow-stage
                data-target-tab="{{ $groupTargetTabs[$group] }}"
                title="Mở {{ $group }}"
            >
                <i class="bi {{ $groupIcons[$group] }}"></i>
                <div>
                    <strong>{{ $group === 'Vật tư' ? 'Chuẩn bị vật tư' : $group }}</strong>
                    <small>
                        @if($isCurrent || ($workflowCancelled && $index === $currentGroupIndex))
                            {{ $workflowCancelled ? 'Đã dừng' : $displayStatusLabel }}
                        @elseif($isDone)
                            Hoàn thành
                        @else
                            Chưa thực hiện
                        @endif
                    </small>
                </div>
                @if($isDone)<span class="pt-flow__check"><i class="bi bi-check"></i></span>@endif
            </button>
        @endforeach
    </div>
    @if($materialPhaseContext)
        @php
            $materialNavCount = collect($project->materialRequests ?? [])
                ->filter(fn ($request) => in_array((string) ($request->status ?? ''), ['warehouse_check', 'pending_manager', 'approved', 'preparing', 'revision', 'issued'], true))
                ->sortByDesc('id')
                ->first()?->items?->count() ?? 0;
        @endphp
        <nav class="pt-tabs pt-tabs--360 pt-material-context-nav" data-emw8-context-nav aria-label="Quy trình vật tư 4 bước">
            <button class="pt-tab active" data-pt-tab="materials" data-material-view="proposal"><i class="bi bi-lightbulb"></i> Vật tư đề xuất ({{ $materialNavCount }})</button>
            <button class="pt-tab" data-pt-tab="materials" data-material-view="issue"><i class="bi bi-box-arrow-up-right"></i> Xuất kho</button>
            <button class="pt-tab" data-pt-tab="materials" data-material-view="return"><i class="bi bi-arrow-return-left"></i> Thu hồi</button>
            {{-- EGO_MATERIAL_FINANCE_TAB_START --}}
            @if($showFinancials ?? false)
                <button
                    type="button"
                    class="pt-tab pt-tab--finance"
                    data-pt-tab="finance"
                    title="Doanh thu, tiền đã thu và công nợ công trình"
                >
                    <i class="bi bi-cash-stack"></i>
                    Doanh thu
                </button>
            @endif
            @if($showProjectExpenseLedger ?? false)
                <button
                    type="button"
                    class="pt-tab pt-tab--expense"
                    data-pt-tab="expenses"
                    title="Chi phí vận chuyển, nhân công ngoài, hoa hồng và phát sinh công trình"
                >
                    <i class="bi bi-receipt-cutoff"></i>
                    Chi phí
                </button>
            @endif
            {{-- EGO_MATERIAL_FINANCE_TAB_END --}}
            <button class="pt-tab" data-pt-tab="history"><i class="bi bi-clock-history"></i> Lịch sử</button>
        </nav>
    @else
        <nav class="pt-tabs pt-tabs--360">
            <button class="pt-tab active" data-pt-tab="overview">Tổng quan</button>
            @if(! $materialInlineWorkflow)
                <button class="pt-tab" data-pt-tab="action">Việc cần xử lý</button>
            @endif
            <button class="pt-tab" data-pt-tab="survey">Khảo sát & phương án</button>
            <button class="pt-tab" data-pt-tab="materials">Chuẩn bị vật tư</button>
            <button class="pt-tab" data-pt-tab="installation">Thi công</button>
            <button class="pt-tab" data-pt-tab="acceptance">Nghiệm thu & bảo hành</button>
            @if($showFinancials ?? false)
                <button class="pt-tab" data-pt-tab="finance"><i class="bi bi-cash-stack"></i> Doanh thu</button>
            @endif
            @if($showProjectExpenseLedger ?? false)
                <button class="pt-tab" data-pt-tab="expenses"><i class="bi bi-receipt-cutoff"></i> Chi phí</button>
            @endif
            @if($can['editBasic'] || $can['editPeople'] || $can['editSurvey'] || $can['editAcceptance'])
                <button class="pt-tab" data-pt-tab="edit"><i class="bi bi-pencil-square"></i> Chỉnh sửa</button>
            @endif
            <button class="pt-tab" data-pt-tab="history">Lịch sử</button>
        </nav>
    @endif

    <div class="pt-grid">
        <main class="pt-main">
            <section class="pt-panel active" data-pt-panel="overview">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Thông tin yêu cầu Kỹ thuật</h2><p>Phòng Kỹ thuật là đơn vị tiếp nhận và sở hữu luồng vận hành. Sales/CSKH chỉ là nguồn chuyển yêu cầu khi có.</p></div><a href="{{ route('project-test.index') }}" class="pt-btn pt-btn--soft pt-btn--sm"><i class="bi bi-arrow-left"></i> Danh sách</a></div>
                    <div class="pt-summary">
                        <div><small>Nguồn công trình</small><strong><span class="pt-source-chip {{ ($project->request_source ?? 'sales') === 'sales' ? 'pt-source-chip--sales' : 'pt-source-chip--technical' }}">{{ ($project->request_source ?? 'sales') === 'sales' ? 'TỪ SALES' : 'KỸ THUẬT TẠO' }}</span></strong></div>
                        <div><small>Loại nghiệp vụ</small><strong>{{ $projectTypes[$project->project_type] ?? $project->project_type ?? 'Chưa xác định' }}</strong></div>
                        <div><small>Đơn hàng Sales</small><strong>{{ $project->salesOrder?->order_code ?: 'Không liên kết' }}</strong></div>
                        <div><small>Mã hợp đồng / báo giá</small><strong>{{ $project->contract_reference ?: 'Không liên kết' }}</strong></div>
                        <div><small>Người tạo yêu cầu</small><strong>{{ $project->creator?->name ?: '—' }}</strong></div>
                        <div><small>Phối hợp khách hàng</small><strong>{{ $project->salesUser?->name ?: 'Không bắt buộc' }}</strong></div>
                        <div><small>Trưởng phòng Kỹ thuật</small><strong>{{ $project->technicalManager?->name ?: 'Chưa tiếp nhận' }}</strong></div>
                        <div><small>Người khảo sát</small><strong>{{ $project->survey?->surveyor?->name ?: 'Chưa phân công' }}</strong></div>
                        <div><small>Đội trưởng thi công</small><strong>{{ $project->leadTechnician?->name ?: 'Chưa phân công' }}</strong></div>
                        <div><small>Mức ưu tiên</small><strong>{{ mb_strtoupper($project->priority) }}</strong></div>
                        <div><small>Lịch khảo sát</small><strong>{{ optional($project->proposed_survey_at)->format('d/m/Y H:i') ?: 'Chưa xếp lịch' }}</strong></div>
                        <div><small>Lịch thi công</small><strong>{{ optional($project->proposed_installation_at)->format('d/m/Y H:i') ?: 'Chưa xếp lịch' }}</strong></div>
                        <div><small>Xác nhận khách hàng</small><strong>{{ $project->customer_confirmation_required ? ($project->customer_confirmation_status === 'confirmed' ? 'Đã xác nhận' : 'Có yêu cầu xác nhận') : 'Không bắt buộc' }}</strong></div>
                        <div><small>Trạng thái tiếp nhận</small><strong>{{ $statusInfo['label'] }}</strong></div>
                        <div class="pt-field--full"><small>Nội dung yêu cầu</small><strong style="line-height:1.6">{{ $project->customer_need ?: '—' }}</strong></div>
                        @if(($project->request_source ?? 'sales') === 'sales')
                            <div class="pt-field--full"><small>Ghi chú bàn giao từ Sales</small><strong style="line-height:1.6">{{ $project->handover_note ?: 'Không có ghi chú bàn giao' }}</strong></div>
                        @endif
                        <div class="pt-field--full"><small>Ghi chú nội bộ</small><strong style="line-height:1.6">{{ $project->note ?: '—' }}</strong></div>
                        @if($project->legacy_site_id)
                            <div><small>Mã dữ liệu cũ</small><strong>#{{ $project->legacy_site_id }}</strong></div>
                            {{-- EGO_PROJECT_CONTRACT_VALUE_PRIVATE_START --}}
                            @if($showFinancials ?? false)

                            <div><small>Giá trị hợp đồng</small><strong>{{ number_format((float) $project->contract_amount, 0, ',', '.') }} đ</strong></div>
                            @endif
                            {{-- EGO_PROJECT_CONTRACT_VALUE_PRIVATE_END --}}
                            <div><small>Ngày lắp đặt cũ</small><strong>{{ optional($project->installed_at)->format('d/m/Y') ?: '—' }}</strong></div>
                            <div><small>Bảo hành đến</small><strong>{{ optional($project->warranty_to)->format('d/m/Y') ?: '—' }}</strong></div>
                            @if($project->monitoring_link)
                                <div class="pt-field--full"><small>Monitoring</small><strong><a href="{{ $project->monitoring_link }}" target="_blank" rel="noopener">Mở hệ thống giám sát</a>{{ $project->monitoring_account ? ' · '.$project->monitoring_account : '' }}</strong></div>
                            @endif
                        @endif
                    </div>
                </article>

                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Đội thi công</h2><p>Trưởng phòng Kỹ thuật chủ động phân công khi hồ sơ đủ điều kiện triển khai.</p></div></div>
                    @forelse($project->assignments as $assignment)
                        <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-person-check"></i></span><div><strong>{{ $assignment->user?->name }} · {{ $assignment->assignment_role === 'leader' ? 'Đội trưởng thi công' : 'Thành viên kỹ thuật' }}</strong><p>Ngày làm: {{ optional($assignment->work_date)->format('d/m/Y') ?: '—' }} · {{ $assignment->note ?: 'Không ghi chú' }}</p></div></div>
                    @empty
                        <div class="pt-alert pt-alert--info">Chưa phân công đội thi công.</div>
                    @endforelse
                </article>
            </section>

            <section class="pt-panel" data-pt-panel="action">
                @if(! $materialPhaseContext)
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Xử lý bước hiện tại</h2><p>Trưởng phòng Kỹ thuật là trung tâm tiếp nhận và điều phối; Sales không phải nút chặn bắt buộc.</p></div><span class="pt-status">{{ $statusInfo['label'] }}</span></div>

                    @if($project->status === 'request_new' && $can['technicalManager'])
                        <div class="pt-alert pt-alert--info" style="margin-bottom:14px"><strong>Tiếp nhận yêu cầu:</strong> kiểm tra đầu vào, chọn người khảo sát và xếp lịch ngay nếu đã đủ thông tin.</div>
                        <form method="POST" action="{{ route('project-test.request.accept', $project) }}" class="pt-form-grid" data-confirm="Xác nhận tiếp nhận yêu cầu Kỹ thuật?">@csrf
                            <div><label class="pt-label">Trưởng phòng Kỹ thuật</label><select class="pt-select" name="technical_manager_id" required><option value="">-- Chọn người tiếp nhận --</option>@foreach($technicalManagers as $user)<option value="{{ $user->id }}" @selected($project->technical_manager_id==$user->id || (!$project->technical_manager_id && auth()->id()==$user->id))>{{ $user->name }}</option>@endforeach</select></div>
                            <div><label class="pt-label">Người khảo sát</label><select class="pt-select" name="surveyor_id"><option value="">-- Có thể phân công sau --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->survey?->surveyed_by==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                            <div><label class="pt-label">Thời gian khảo sát</label><input class="pt-input" type="datetime-local" name="scheduled_at" value="{{ optional($project->proposed_survey_at)->format('Y-m-d\TH:i') }}"><div class="pt-help">Có thể để trống và xếp lịch sau.</div></div>
                            <div><label class="pt-label">Nguồn yêu cầu</label><input class="pt-input" value="{{ $requestSources[$project->request_source] ?? $project->request_source }}" disabled></div>
                            <div class="pt-field--full"><label class="pt-label">Ghi chú tiếp nhận</label><textarea class="pt-textarea" name="note" placeholder="Phạm vi xử lý, thông tin còn thiếu hoặc lưu ý cho người khảo sát"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-inbox-fill"></i> Tiếp nhận yêu cầu</button></div>
                        </form>
                    @elseif(in_array($project->status, ['request_accepted','survey_pending','survey_reschedule']) && $can['technicalManager'])
                        <form method="POST" action="{{ route('project-test.survey.review', $project) }}" class="pt-form-grid" data-confirm="Xác nhận lịch và người khảo sát?">@csrf
                            <div><label class="pt-label">Xử lý lịch khảo sát</label><select class="pt-select" name="decision" required><option value="confirm">Xác nhận lịch & phân công</option><option value="reschedule">Cần cập nhật lại lịch</option></select></div>
                            <div><label class="pt-label">Thời gian khảo sát</label><input class="pt-input" type="datetime-local" name="scheduled_at" value="{{ optional($project->proposed_survey_at)->format('Y-m-d\TH:i') }}" required></div>
                            <div><label class="pt-label">Trưởng phòng Kỹ thuật</label><select class="pt-select" name="technical_manager_id" required><option value="">-- Chọn người điều phối --</option>@foreach($technicalManagers as $user)<option value="{{ $user->id }}" @selected($project->technical_manager_id==$user->id || (!$project->technical_manager_id && auth()->id()==$user->id))>{{ $user->name }}</option>@endforeach</select></div>
                            <div><label class="pt-label">Người khảo sát</label><select class="pt-select" name="surveyor_id"><option value="">-- Chọn kỹ thuật viên --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->survey?->surveyed_by==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                            <div class="pt-field--full"><label class="pt-label">Lý do / ghi chú</label><textarea class="pt-textarea" name="reason" placeholder="Nêu rõ lý do nếu cần đổi lịch"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-calendar-check"></i> Lưu lịch & phân công khảo sát</button></div>
                        </form>
                    @elseif($project->status === 'survey_reschedule' && $can['requestCoordinator'])
                        <form method="POST" action="{{ route('project-test.survey.reschedule', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Lịch khảo sát cập nhật</label><input class="pt-input" type="datetime-local" name="proposed_survey_at" required></div>
                            <div><label class="pt-label">Ghi chú phối hợp</label><input class="pt-input" name="note" placeholder="Thông tin từ khách hàng hoặc nội bộ"></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-calendar-plus"></i> Cập nhật lịch khảo sát</button></div>
                        </form>
                    @elseif(in_array($project->status, ['survey_confirmed','survey_in_progress','proposal_revision']) && $can['technical'])
                        <div class="pt-survey-presence">
                            <div class="pt-survey-presence__info">
                                <span class="pt-status"><i class="bi bi-geo-alt"></i> Bằng chứng hiện trường</span>
                                <strong>Check-in: {{ optional($project->survey?->check_in_at)->format('H:i d/m/Y') ?: 'Chưa thực hiện' }}</strong>
                                <strong>Check-out: {{ optional($project->survey?->check_out_at)->format('H:i d/m/Y') ?: 'Chưa thực hiện' }}</strong>
                                <small>Kỹ thuật bắt buộc check-in trước khi hoàn tất khảo sát.</small>
                            </div>
                            <div class="pt-actions">
                                @if(!$project->survey?->check_in_at)
                                    <form method="POST" action="{{ route('project-test.survey.check-in', $project) }}" data-survey-geo data-geo-action="check-in">@csrf<input type="hidden" name="latitude" data-geo-lat><input type="hidden" name="longitude" data-geo-lng><button class="pt-btn pt-btn--brand" type="submit"><i class="bi bi-geo-alt-fill"></i> Check-in khảo sát</button></form>
                                @elseif(!$project->survey?->check_out_at)
                                    <form method="POST" action="{{ route('project-test.survey.check-out', $project) }}" data-survey-geo data-geo-action="check-out">@csrf<input type="hidden" name="latitude" data-geo-lat><input type="hidden" name="longitude" data-geo-lng><button class="pt-btn pt-btn--soft" type="submit"><i class="bi bi-box-arrow-right"></i> Check-out hiện trường</button></form>
                                @else
                                    <span class="pt-alert pt-alert--success"><i class="bi bi-check-circle"></i> Đã có đủ check-in/out</span>
                                @endif
                            </div>
                        </div>
                        <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.survey.submit', $project) }}" class="pt-form-grid">@csrf
                            <div class="pt-field--full"><label class="pt-label">Hiện trạng khảo sát</label><textarea class="pt-textarea" name="site_condition" required>{{ old('site_condition',$project->survey?->site_condition) }}</textarea></div>
                            <div class="pt-field--full"><label class="pt-label">Số liệu thực tế tại hiện trường</label><textarea class="pt-textarea" name="actual_measurements" required placeholder="Kích thước mái, hướng mái, độ nghiêng, điện áp, vị trí tủ điện, khoảng cách đi dây...">{{ old('actual_measurements',$project->survey?->actual_measurements) }}</textarea></div>
                            <div class="pt-field--full"><label class="pt-label">Rủi ro và yêu cầu xử lý</label><textarea class="pt-textarea" name="site_risks">{{ old('site_risks',$project->survey?->site_risks) }}</textarea></div>
                            <div><label class="pt-label">Công suất đề xuất (kWp)</label><input class="pt-input" type="number" step="0.01" name="proposed_kwp" value="{{ old('proposed_kwp',$project->proposal?->proposed_kwp ?: $project->estimated_kwp) }}" required></div>
                            <div><label class="pt-label">File mô phỏng 3D/CAD</label><input class="pt-input" type="file" name="design_3d_file"></div>
                            <div class="pt-field--full"><label class="pt-label">Phương án kỹ thuật</label><textarea class="pt-textarea" name="solution_summary" required>{{ old('solution_summary',$project->proposal?->solution_summary) }}</textarea></div>
                            <div class="pt-field--full"><label class="pt-label">Danh mục thiết bị sơ bộ · mỗi dòng một vật tư</label><textarea class="pt-textarea" name="preliminary_materials" required>{{ old('preliminary_materials',implode("\n",$preliminary)) }}</textarea></div>
                            <div><label class="pt-label">Ghi chú kỹ thuật</label><textarea class="pt-textarea" name="technical_notes">{{ old('technical_notes',$project->survey?->technical_notes) }}</textarea></div>
                            <div><label class="pt-label">Ảnh/video/tài liệu khảo sát</label><input class="pt-input" type="file" name="attachment_file"></div>
                            <div class="pt-field--full pt-step-note"><i class="bi bi-arrow-right-circle"></i><span>{{ $project->customer_confirmation_required ? 'Sau khi nộp, hồ sơ chuyển sang bước xác nhận phương án/triển khai.' : 'Hồ sơ này không bắt buộc Sales xác nhận; sau khi nộp sẽ chuyển thẳng sang Kỹ thuật xếp lịch thi công.' }}</span></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-badge-3d"></i> Hoàn tất khảo sát & chuyển bước tiếp theo</button></div>
                        </form>
                    @elseif(in_array($project->status, ['customer_confirmation','sales_review']) && $can['customerCoordinator'])
                        <div class="pt-alert pt-alert--info" style="margin-bottom:14px"><strong>Xác nhận có điều kiện:</strong> Sales chỉ phối hợp nếu đây là công trình thương mại. Trưởng phòng Kỹ thuật/Admin/CSKH cũng có thể xác nhận theo hồ sơ thực tế.</div>
                        <form method="POST" action="{{ route('project-test.proposal.confirm', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Kết quả xác nhận</label><select class="pt-select" name="decision"><option value="accept">Đồng ý triển khai</option><option value="revision">Yêu cầu chỉnh phương án</option></select></div>
                            <div><label class="pt-label">Lịch thi công dự kiến</label><input class="pt-input" type="datetime-local" name="proposed_installation_at"></div>
                            <div class="pt-field--full"><label class="pt-label">Nội dung xác nhận / phản hồi</label><textarea class="pt-textarea" name="feedback" placeholder="Khách hàng, nội bộ hoặc người có thẩm quyền đã xác nhận nội dung gì?"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-check2-circle"></i> Cập nhật kết quả xác nhận</button></div>
                        </form>
                    @elseif(in_array($project->status, ['installation_pending','installation_reschedule']) && $can['technicalManager'])
                        <form method="POST" action="{{ route('project-test.installation.review', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Xử lý lịch thi công</label><select class="pt-select" name="decision"><option value="confirm">Xác nhận lịch thi công</option><option value="reschedule">Cần cập nhật lại lịch</option></select></div>
                            <div><label class="pt-label">Ngày giờ thi công</label><input class="pt-input" type="datetime-local" name="installation_at" value="{{ optional($project->proposed_installation_at)->format('Y-m-d\TH:i') }}" required></div>
                            <div class="pt-field--full"><label class="pt-label">Lý do / ghi chú</label><textarea class="pt-textarea" name="reason"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-calendar-check"></i> Lưu lịch thi công</button></div>
                        </form>
                    @elseif($project->status === 'installation_reschedule' && $can['requestCoordinator'])
                        <form method="POST" action="{{ route('project-test.installation.reschedule', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Lịch thi công cập nhật</label><input class="pt-input" type="datetime-local" name="proposed_installation_at" required></div>
                            <div><label class="pt-label">Ghi chú</label><input class="pt-input" name="note"></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand">Cập nhật lịch để Trưởng phòng xác nhận</button></div>
                        </form>
                    @elseif(in_array($project->status, ['warehouse_issued','assignment_pending']) && $can['technicalManager'])
                        <form method="POST" action="{{ route('project-test.assign', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Đội trưởng</label><select class="pt-select" name="lead_technician_id" required>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->lead_technician_id==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                            <div><label class="pt-label">Ngày thi công</label><input class="pt-input" type="date" name="work_date" value="{{ optional($project->proposed_installation_at)->format('Y-m-d') }}" required></div>
                            <div class="pt-field--full"><label class="pt-label">Thành viên</label><select class="pt-select" name="member_ids[]" multiple size="6">@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->assignments->where('assignment_role','member')->pluck('user_id')->contains($user->id))>{{ $user->name }}</option>@endforeach</select></div>
                            <div class="pt-field--full"><label class="pt-label">Ghi chú điều phối</label><textarea class="pt-textarea" name="note"></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-people"></i> Phân công đội thi công</button></div>
                        </form>
                    @elseif($project->status === 'ready_install' && $can['technical'])
                        <form method="POST" action="{{ route('project-test.installation.start', $project) }}" data-confirm="Xác nhận đội kỹ thuật bắt đầu thi công?">@csrf<button class="pt-btn pt-btn--brand"><i class="bi bi-play-circle"></i> Bắt đầu thi công</button></form>
                    @elseif($project->status === 'installing' && $can['technical'])
                        <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.logs.store', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Ngày cập nhật</label><input class="pt-input" type="date" name="log_date" value="{{ now()->format('Y-m-d') }}" required></div>
                            <div><label class="pt-label">Tiến độ thực tế (%)</label><input class="pt-input" type="number" name="progress" min="0" max="100" value="{{ max(0,$project->progress) }}" required></div>
                            <div><label class="pt-label">Tình trạng</label><select class="pt-select" name="status"><option value="working">Đang làm</option><option value="blocked">Bị vướng</option><option value="waiting_customer">Chờ xác nhận</option><option value="waiting_material">Chờ vật tư</option><option value="done">Hoàn tất · Gửi nghiệm thu</option></select></div>
                            <div><label class="pt-label">Ảnh / tài liệu</label><input class="pt-input" type="file" name="attachment_file"></div>
                            <div class="pt-field--full"><label class="pt-label">Nội dung nhật ký</label><textarea class="pt-textarea" name="content" required></textarea></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-journal-check"></i> Lưu nhật ký</button></div>
                        </form>
                    @elseif($project->status === 'acceptance_pending' && $can['acceptance'])
                        <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.accept', $project) }}" class="pt-form-grid">@csrf
                            <div><label class="pt-label">Ngày nghiệm thu</label><input class="pt-input" type="date" name="accepted_at" value="{{ now()->format('Y-m-d') }}" required></div>
                            <div><label class="pt-label">Thời hạn bảo hành (tháng)</label><input class="pt-input" type="number" name="warranty_months" value="60" min="1" max="240" required></div>
                            <div class="pt-field--full"><label class="pt-label">Serial thiết bị thực tế</label><textarea class="pt-textarea" name="device_serials" required></textarea></div>
                            <div><label class="pt-label">Link Monitoring</label><input class="pt-input" type="url" name="monitoring_link"></div>
                            <div><label class="pt-label">Tài khoản Monitoring</label><input class="pt-input" name="monitoring_account"></div>
                            <div><label class="pt-label">Biên bản nghiệm thu</label><input class="pt-input" type="file" name="report_file"></div>
                            <div><label class="pt-label">Ghi chú</label><input class="pt-input" name="note"></div>
                            <div class="pt-field--full"><label class="pt-label">Checklist</label><div class="pt-summary"><label><input type="checkbox" name="checklist[]" value="installation_complete"> Lắp đặt hoàn tất</label><label><input type="checkbox" name="checklist[]" value="system_tested"> Đã kiểm tra vận hành</label><label><input type="checkbox" name="checklist[]" value="customer_handover"> Đã bàn giao</label><label><input type="checkbox" name="checklist[]" value="monitoring_ready"> Monitoring hoạt động</label></div></div>
                            <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-clipboard-check"></i> Duyệt nghiệm thu & tạo bảo hành</button></div>
                        </form>
                    @elseif($project->status === 'warranty_active')
                        <div class="pt-alert pt-alert--success"><strong>Workflow triển khai đã hoàn tất.</strong> Hồ sơ bảo hành đã được mở và tiếp tục do Phòng Kỹ thuật quản lý.</div>
                    @else
                        <div class="pt-alert pt-alert--info">Tài khoản hiện tại không giữ bước này hoặc hồ sơ đang chờ người có quyền phù hợp xử lý.</div>
                    @endif
                </article>
                @endif
            </section>

            <section class="pt-panel" data-pt-panel="survey">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Khảo sát & mô phỏng 3D</h2><p>Kết quả khảo sát, hình ảnh và file 3D/CAD được gắn trực tiếp vào hồ sơ Kỹ thuật.</p></div></div>
                    <div class="pt-summary">
                        <div><small>Trạng thái lịch</small><strong>{{ $project->survey?->schedule_status ?: 'Chưa có' }}</strong></div>
                        <div><small>Ngày khảo sát</small><strong>{{ optional($project->survey?->scheduled_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
                        <div><small>Check-in</small><strong>{{ optional($project->survey?->check_in_at)->format('H:i d/m/Y') ?: '—' }}</strong></div>
                        <div><small>Check-out</small><strong>{{ optional($project->survey?->check_out_at)->format('H:i d/m/Y') ?: '—' }}</strong></div>
                        <div class="pt-field--full"><small>Hiện trạng</small><strong style="white-space:pre-line">{{ $project->survey?->site_condition ?: 'Chưa khảo sát' }}</strong></div>
                        <div class="pt-field--full"><small>Số liệu thực tế</small><strong style="white-space:pre-line">{{ $project->survey?->actual_measurements ?: 'Chưa ghi nhận' }}</strong></div>
                        <div class="pt-field--full"><small>Rủi ro hiện trường</small><strong style="white-space:pre-line">{{ $project->survey?->site_risks ?: 'Không ghi nhận rủi ro' }}</strong></div>
                        @if($project->survey?->check_in_lat && $project->survey?->check_in_lng)
                            <div class="pt-field--full"><small>Tọa độ check-in</small><strong>{{ $project->survey->check_in_lat }}, {{ $project->survey->check_in_lng }}</strong></div>
                        @endif
                        <div class="pt-field--full"><small>Phương án kỹ thuật</small><strong style="white-space:pre-line">{{ $project->proposal?->solution_summary ?: 'Chưa có phương án' }}</strong></div>
                    </div>
                    <div class="pt-actions" style="margin-top:12px">
                        @if($project->survey?->design_3d_file)<a class="pt-btn pt-btn--brand" href="{{ route('project-test.download',[$project,'design-3d']) }}"><i class="bi bi-badge-3d"></i> Tải mô phỏng 3D</a>@endif
                        @if($project->survey?->attachment_file)<a class="pt-btn pt-btn--soft" href="{{ route('project-test.download',[$project,'survey']) }}"><i class="bi bi-paperclip"></i> Tài liệu khảo sát</a>@endif
                    </div>
                    @if($preliminary)<div style="margin-top:15px"><label class="pt-label">Thiết bị sơ bộ</label>@foreach($preliminary as $line)<span class="pt-status" style="margin:3px">{{ $line }}</span>@endforeach</div>@endif
                </article>
            </section>

            <section class="pt-panel" data-pt-panel="materials" data-material-full-layout="1">
                @include('project-test.partials.material-workflow-v41')
            </section>

            <section class="pt-panel" data-pt-panel="installation">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Nhật ký thi công</h2><p>Đội kỹ thuật cập nhật tiến độ, vướng mắc và ảnh thực tế theo ngày.</p></div></div>
                    @forelse($project->dailyLogs as $log)
                        <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-journal-text"></i></span><div style="width:100%"><strong>{{ optional($log->log_date)->format('d/m/Y') }} · {{ $log->author?->name }} · {{ $log->progress }}%</strong><p>{{ $log->content }}</p><time>{{ $log->status }}</time>
                            @if($can['editSurvey'] && (auth()->user()->hasAnyRole(['admin','technical_manager']) || $log->created_by===auth()->id()))
                                <details class="pt-edit-details" style="margin-top:9px"><summary><i class="bi bi-pencil"></i> Sửa nhật ký</summary>
                                    <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.logs.update',[$project,$log]) }}" class="pt-form-grid" style="margin-top:12px">@csrf
                                        <div><label class="pt-label">Ngày</label><input class="pt-input" type="date" name="log_date" value="{{ optional($log->log_date)->format('Y-m-d') }}" required></div>
                                        <div><label class="pt-label">Tiến độ (%)</label><input class="pt-input" type="number" min="0" max="100" name="progress" value="{{ $log->progress }}" required></div>
                                        <div><label class="pt-label">Tình trạng</label><select class="pt-select" name="status">@foreach(['working'=>'Đang làm','blocked'=>'Bị vướng','waiting_customer'=>'Chờ khách','waiting_material'=>'Chờ vật tư','done'=>'Hoàn tất'] as $value=>$label)<option value="{{ $value }}" @selected($log->status===$value)>{{ $label }}</option>@endforeach</select></div>
                                        <div><label class="pt-label">Thay file</label><input class="pt-input" type="file" name="attachment_file"></div>
                                        <div class="pt-field--full"><label class="pt-label">Nội dung</label><textarea class="pt-textarea" name="content" required>{{ $log->content }}</textarea></div>
                                        <div class="pt-field--full"><button class="pt-btn pt-btn--brand pt-btn--sm"><i class="bi bi-save"></i> Lưu nhật ký</button></div>
                                    </form>
                                </details>
                            @endif
                        </div></div>
                    @empty<div class="pt-alert pt-alert--info">Chưa có nhật ký thi công.</div>@endforelse
                </article>
            </section>

            <section class="pt-panel" data-pt-panel="acceptance">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Nghiệm thu & bảo hành</h2><p>Bảo hành tự động tạo sau khi nghiệm thu được duyệt.</p></div></div>
                    @if($project->acceptance)
                        <div class="pt-summary"><div><small>Ngày nghiệm thu</small><strong>{{ optional($project->acceptance->accepted_at)->format('d/m/Y') }}</strong></div><div><small>Monitoring</small><strong>{{ $project->acceptance->monitoring_link ?: '—' }}</strong></div><div class="pt-field--full"><small>Serial thiết bị</small><strong style="white-space:pre-line">{{ $project->acceptance->device_serials }}</strong></div></div>
                        @if($project->acceptance->report_file)<a href="{{ route('project-test.download',[$project,'acceptance']) }}" class="pt-btn pt-btn--soft" style="margin-top:10px"><i class="bi bi-file-earmark-arrow-down"></i> Tải biên bản nghiệm thu</a>@endif
                    @else<div class="pt-alert pt-alert--info">Chưa nghiệm thu.</div>@endif
                    @if($project->warranty)
                        <div class="pt-alert pt-alert--success" style="margin-top:12px"><strong>Bảo hành đang hoạt động:</strong> {{ optional($project->warranty->starts_at)->format('d/m/Y') }} → {{ optional($project->warranty->ends_at)->format('d/m/Y') }} · Bảo trì tiếp theo {{ optional($project->warranty->next_maintenance_at)->format('d/m/Y') }}</div>
                    @endif
                </article>
            </section>

            @if($can['editBasic'] || $can['editPeople'] || $can['editSurvey'] || $can['editAcceptance'])
            <section class="pt-panel" data-pt-panel="edit">
                <div class="pt-edit-stack">
                    @if($can['editBasic'])
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head">
                                <div><h2><i class="bi bi-pencil-square"></i> Chỉnh sửa thông tin yêu cầu</h2><p>Người điều phối được phép cập nhật nguồn, loại nghiệp vụ, đầu vào và lịch dự kiến.</p></div>
                                <span class="pt-role-lock">ĐIỀU PHỐI</span>
                            </div>
                            <form method="POST" action="{{ route('project-test.update-basic', $project) }}" class="pt-form-grid" data-confirm="Lưu thay đổi yêu cầu Kỹ thuật?">
                                @csrf
                                <div><label class="pt-label">Công ty</label><select class="pt-select" name="company_id"><option value="">-- Không chọn --</option>@foreach($companies as $company)<option value="{{ $company->id }}" @selected($project->company_id==$company->id)>{{ $company->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Khách hàng CRM</label><select class="pt-select" name="customer_id"><option value="">-- Không liên kết --</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected($project->customer_id==$customer->id)>{{ $customer->name }}{{ $customer->phone ? ' · '.$customer->phone : '' }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Nguồn yêu cầu</label><select class="pt-select" name="request_source" required>@foreach($requestSources as $value=>$label)<option value="{{ $value }}" @selected($project->request_source===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Loại nghiệp vụ</label><select class="pt-select" name="project_type" required>@foreach($projectTypes as $value=>$label)<option value="{{ $value }}" @selected($project->project_type===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Phối hợp khách hàng (không bắt buộc)</label><select class="pt-select" name="sales_user_id"><option value="">-- Không cần Sales phối hợp --</option>@foreach($salesUsers as $user)<option value="{{ $user->id }}" @selected($project->sales_user_id==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Mức ưu tiên</label><select class="pt-select" name="priority" required>@foreach(['low'=>'Thấp','normal'=>'Bình thường','high'=>'Cao','urgent'=>'Khẩn'] as $value=>$label)<option value="{{ $value }}" @selected($project->priority===$value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="pt-field--full"><label class="pt-label">Tên công trình</label><input class="pt-input" name="name" value="{{ $project->name }}" required></div>
                                <div class="pt-field--full"><label class="pt-label">Địa chỉ</label><input class="pt-input" name="address" value="{{ $project->address }}" required></div>
                                <div><label class="pt-label">Người liên hệ</label><input class="pt-input" name="contact_name" value="{{ $project->contact_name }}"></div>
                                <div><label class="pt-label">Số điện thoại</label><input class="pt-input" name="contact_phone" value="{{ $project->contact_phone }}"></div>
                                <div><label class="pt-label">Loại hệ thống</label><input class="pt-input" name="system_type" value="{{ $project->system_type }}"></div>
                                <div><label class="pt-label">Công suất dự kiến (kWp)</label><input class="pt-input" type="number" step="0.01" name="estimated_kwp" value="{{ $project->estimated_kwp }}"></div>
                                <div><label class="pt-label">Lịch khảo sát dự kiến</label><input class="pt-input" type="datetime-local" name="proposed_survey_at" value="{{ optional($project->proposed_survey_at)->format('Y-m-d\TH:i') }}"></div>
                                <div><label class="pt-label">Lịch thi công dự kiến</label><input class="pt-input" type="datetime-local" name="proposed_installation_at" value="{{ optional($project->proposed_installation_at)->format('Y-m-d\TH:i') }}"></div>
                                <div><label class="pt-label">Hạn hoàn thành</label><input class="pt-input" type="date" name="target_completion_at" value="{{ optional($project->target_completion_at)->format('Y-m-d') }}"></div>
                                <div class="pt-field--full"><label class="pt-label">Nội dung yêu cầu</label><textarea class="pt-textarea" name="customer_need" required>{{ $project->customer_need }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Ghi chú nội bộ</label><textarea class="pt-textarea" name="note">{{ $project->note }}</textarea></div>
                                <div class="pt-field--full pt-toggle-row"><label><input type="checkbox" name="customer_confirmation_required" value="1" @checked($project->customer_confirmation_required)> <strong>Yêu cầu xác nhận khách hàng trước khi triển khai</strong></label><span>Chỉ bật cho công trình thương mại hoặc trường hợp có thay đổi phạm vi/chi phí.</span></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu thông tin yêu cầu</button></div>
                            </form>
                        </article>
                    @endif

                    @if($can['editPeople'])
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head">
                                <div><h2><i class="bi bi-people-fill"></i> Nhân sự phụ trách & phân công</h2><p>Đây là nơi thêm người sau bước khảo sát và sửa lại bất kỳ lúc nào.</p></div>
                                <span class="pt-role-lock">TECHNICAL MANAGER</span>
                            </div>
                            <div class="pt-alert pt-alert--info" style="margin-bottom:14px">
                                Chỉ <strong>role technical_manager</strong> (và Admin dự phòng) nhìn thấy và thao tác. Dropdown nhân sự kỹ thuật chỉ lấy <strong>role ky_thuat</strong>.
                            </div>
                            <form method="POST" action="{{ route('project-test.people.update', $project) }}" class="pt-form-grid" data-confirm="Cập nhật nhân sự phụ trách?">
                                @csrf
                                <div><label class="pt-label">Trưởng phòng Kỹ thuật</label><select class="pt-select" name="technical_manager_id" required><option value="">-- Chọn technical_manager --</option>@foreach($technicalManagers as $user)<option value="{{ $user->id }}" @selected($project->technical_manager_id==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Người khảo sát</label><select class="pt-select" name="surveyor_id"><option value="">-- Chưa phân công --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->survey?->surveyed_by==$user->id)>{{ $user->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Ngày giờ khảo sát</label><input class="pt-input" type="datetime-local" name="survey_at" value="{{ optional($project->survey?->scheduled_at ?: $project->proposed_survey_at)->format('Y-m-d\TH:i') }}"></div>
                                <div class="pt-field--full pt-toggle-row"><label><input type="checkbox" name="update_team" value="1"> <strong>Cập nhật cả đội thi công</strong></label><span>Chỉ tick khi cần tạo/sửa/xóa đội thi công.</span></div>
                                <div><label class="pt-label">Đội trưởng thi công</label><select class="pt-select" name="lead_technician_id"><option value="">-- Chưa phân công / xóa đội --</option>@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->assignments->where('assignment_role','leader')->pluck('user_id')->contains($user->id))>{{ $user->name }}</option>@endforeach</select></div>
                                <div><label class="pt-label">Ngày thi công của đội</label><input class="pt-input" type="date" name="work_date" value="{{ optional($project->assignments->first()?->work_date ?: $project->proposed_installation_at)->format('Y-m-d') }}"></div>
                                <div class="pt-field--full"><label class="pt-label">Thành viên kỹ thuật</label><select class="pt-select" name="member_ids[]" multiple size="7">@foreach($technicians as $user)<option value="{{ $user->id }}" @selected($project->assignments->where('assignment_role','member')->pluck('user_id')->contains($user->id))>{{ $user->name }}</option>@endforeach</select><div class="pt-help">Giữ Ctrl để chọn nhiều người. Danh sách này không hiển thị Sales, HR, Marketing hoặc role khác.</div></div>
                                <div class="pt-field--full"><label class="pt-label">Ghi chú phân công</label><textarea class="pt-textarea" name="note">{{ $project->assignments->first()?->note }}</textarea></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-person-check"></i> Lưu nhân sự & phân công</button></div>
                            </form>
                        </article>
                    @endif

                    @if($can['editSurvey'] && ($project->survey || $project->proposal))
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head">
                                <div><h2><i class="bi bi-badge-3d"></i> Chỉnh sửa khảo sát & phương án</h2><p>Thay nội dung hoặc tải file mới mà không làm nhảy workflow.</p></div>
                                <span class="pt-role-lock">KỸ THUẬT</span>
                            </div>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.survey.update', $project) }}" class="pt-form-grid">
                                @csrf
                                <div><label class="pt-label">Ngày hoàn tất khảo sát</label><input class="pt-input" type="datetime-local" name="completed_at" value="{{ optional($project->survey?->completed_at)->format('Y-m-d\TH:i') }}"></div>
                                <div><label class="pt-label">Công suất đề xuất (kWp)</label><input class="pt-input" type="number" step="0.01" name="proposed_kwp" value="{{ $project->proposal?->proposed_kwp ?: $project->estimated_kwp }}" required></div>
                                <div class="pt-field--full"><label class="pt-label">Hiện trạng khảo sát</label><textarea class="pt-textarea" name="site_condition" required>{{ $project->survey?->site_condition }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Số liệu thực tế tại hiện trường</label><textarea class="pt-textarea" name="actual_measurements" required>{{ $project->survey?->actual_measurements }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Rủi ro và yêu cầu xử lý</label><textarea class="pt-textarea" name="site_risks">{{ $project->survey?->site_risks }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Phương án kỹ thuật</label><textarea class="pt-textarea" name="solution_summary" required>{{ $project->proposal?->solution_summary }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Thiết bị sơ bộ · mỗi dòng một mục</label><textarea class="pt-textarea" name="preliminary_materials" required>{{ implode("\n", $preliminary) }}</textarea></div>
                                <div><label class="pt-label">Ghi chú kỹ thuật</label><textarea class="pt-textarea" name="technical_notes">{{ $project->survey?->technical_notes }}</textarea></div>
                                <div><label class="pt-label">Thay file mô phỏng 3D</label><input class="pt-input" type="file" name="design_3d_file"><div class="pt-help">Bỏ trống để giữ file hiện tại.</div></div>
                                <div><label class="pt-label">Thay tài liệu khảo sát</label><input class="pt-input" type="file" name="attachment_file"><div class="pt-help">Bỏ trống để giữ file hiện tại.</div></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu khảo sát & phương án</button></div>
                            </form>
                        </article>
                    @endif

                    @if($can['editAcceptance'] && $project->acceptance)
                        <article class="pt-card pt-section pt-edit-block">
                            <div class="pt-section__head"><div><h2><i class="bi bi-shield-check"></i> Chỉnh sửa nghiệm thu & bảo hành</h2><p>Giữ nguyên workflow, cập nhật lại hồ sơ sau bàn giao.</p></div><span class="pt-role-lock">TRƯỞNG PHÒNG / ADMIN</span></div>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('project-test.acceptance.update', $project) }}" class="pt-form-grid">
                                @csrf
                                <div><label class="pt-label">Ngày nghiệm thu</label><input class="pt-input" type="date" name="accepted_at" value="{{ optional($project->acceptance->accepted_at)->format('Y-m-d') }}" required></div>
                                <div><label class="pt-label">Thời hạn bảo hành (tháng)</label><input class="pt-input" type="number" name="warranty_months" min="1" max="240" value="{{ $project->warranty ? max(1, $project->warranty->starts_at->diffInMonths($project->warranty->ends_at)) : 60 }}" required></div>
                                <div><label class="pt-label">Bảo trì tiếp theo</label><input class="pt-input" type="date" name="next_maintenance_at" value="{{ optional($project->warranty?->next_maintenance_at)->format('Y-m-d') }}"></div>
                                <div><label class="pt-label">Link Monitoring</label><input class="pt-input" type="url" name="monitoring_link" value="{{ $project->acceptance->monitoring_link }}"></div>
                                <div><label class="pt-label">Tài khoản Monitoring</label><input class="pt-input" name="monitoring_account" value="{{ $project->acceptance->monitoring_account }}"></div>
                                <div><label class="pt-label">Thay biên bản</label><input class="pt-input" type="file" name="report_file"><div class="pt-help">Bỏ trống để giữ file cũ.</div></div>
                                <div class="pt-field--full"><label class="pt-label">Serial thiết bị</label><textarea class="pt-textarea" name="device_serials" required>{{ $project->acceptance->device_serials }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Ghi chú</label><textarea class="pt-textarea" name="note">{{ $project->acceptance->note }}</textarea></div>
                                <div class="pt-field--full"><label class="pt-label">Checklist</label><div class="pt-summary">@foreach(['installation_complete'=>'Lắp đặt hoàn tất','system_tested'=>'Đã kiểm tra vận hành','customer_handover'=>'Đã bàn giao khách','monitoring_ready'=>'Monitoring hoạt động'] as $value=>$label)<label><input type="checkbox" name="checklist[]" value="{{ $value }}" @checked(in_array($value,$project->acceptance->checklist_json ?: []))> {{ $label }}</label>@endforeach</div></div>
                                <div class="pt-field--full"><button class="pt-btn pt-btn--brand"><i class="bi bi-save"></i> Lưu nghiệm thu & bảo hành</button></div>
                            </form>
                        </article>
                    @endif
                </div>
            </section>
            @endif
            @if($showFinancials ?? false)
                @include('project-test.partials.finance-payments')
            @endif
            @if($showProjectExpenseLedger ?? false)
                @include('project-test.partials.project-expenses')
            @endif

            <section class="pt-panel" data-pt-panel="history">
                <article class="pt-card pt-section">
                    <div class="pt-section__head"><div><h2>Lịch sử workflow</h2><p>Mọi chuyển bước đều lưu người thao tác, thời gian và trạng thái trước/sau.</p></div></div>
                    <div class="pt-timeline">
                        @forelse($project->histories as $history)
                            <div class="pt-history"><span class="pt-history__dot"><i class="bi bi-clock-history"></i></span><div><strong>{{ $history->action }}</strong><p>{{ $history->from_status ?: 'Khởi tạo' }} → {{ $history->to_status ?: 'Không đổi trạng thái' }} · {{ $history->user?->name ?: 'Hệ thống' }}</p><time>{{ $history->created_at->format('d/m/Y H:i:s') }}</time></div></div>
                        @empty<div class="pt-alert pt-alert--info">Chưa có lịch sử.</div>@endforelse
                    </div>
                </article>
            </section>
        </main>

        <aside class="pt-side">
            @include('project-test.partials.project-side-panel')
        </aside>
    </div>
</div>
</div>

@endsection

@push('scripts')
{{-- EGO_MATERIAL_WORKFLOW_V41: chỉ nạp một bộ asset vật tư V8; không nạp logic vật tư cũ. --}}
<script src="{{ asset('js/project-test-v4.js') }}?v={{ file_exists(public_path('js/project-test-v4.js')) ? filemtime(public_path('js/project-test-v4.js')) : time() }}"></script>
<script src="{{ asset('js/project-warehouse-360-v1.js') }}?v={{ file_exists(public_path('js/project-warehouse-360-v1.js')) ? filemtime(public_path('js/project-warehouse-360-v1.js')) : time() }}"></script>
<script src="{{ asset('js/project-workflow-360-v2.js') }}?v={{ file_exists(public_path('js/project-workflow-360-v2.js')) ? filemtime(public_path('js/project-workflow-360-v2.js')) : time() }}"></script>
<script src="{{ asset('js/project-workflow-stage-navigation-v1.js') }}?v={{ file_exists(public_path('js/project-workflow-stage-navigation-v1.js')) ? filemtime(public_path('js/project-workflow-stage-navigation-v1.js')) : time() }}"></script>
<script src="{{ asset('js/project-material-workspace-v8.js') }}?v={{ file_exists(public_path('js/project-material-workspace-v8.js')) ? filemtime(public_path('js/project-material-workspace-v8.js')) : time() }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-survey-geo]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.geoReady === '1') {
                return;
            }

            event.preventDefault();
            var actionLabel = form.dataset.geoAction === 'check-out' ? 'check-out' : 'check-in';
            var submitButton = form.querySelector('button[type="submit"]');
            var originalHtml = submitButton ? submitButton.innerHTML : '';

            function submitWithoutLocation(message) {
                if (!window.confirm(message + '\nTiếp tục ' + actionLabel + ' mà không lưu GPS?')) {
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalHtml;
                    }
                    return;
                }
                form.dataset.geoReady = '1';
                form.submit();
            }

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Đang lấy vị trí...';
            }

            if (!navigator.geolocation) {
                submitWithoutLocation('Trình duyệt không hỗ trợ định vị.');
                return;
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                var lat = form.querySelector('[data-geo-lat]');
                var lng = form.querySelector('[data-geo-lng]');
                if (lat) lat.value = position.coords.latitude;
                if (lng) lng.value = position.coords.longitude;
                form.dataset.geoReady = '1';
                form.submit();
            }, function () {
                submitWithoutLocation('Không lấy được vị trí. Hãy bật quyền Location cho trình duyệt để lưu bằng chứng đầy đủ.');
            }, {
                enableHighAccuracy: true,
                timeout: 12000,
                maximumAge: 0
            });
        });
    });
});
</script>
@endpush
