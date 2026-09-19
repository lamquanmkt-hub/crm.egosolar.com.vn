@extends('layouts.app')

@section('title', 'Công việc O&M')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v10.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v10.css')) ? filemtime(public_path('css/technical-maintenance-v10.css')) : time() }}">
@endsection

@section('content')
@php
    $tab = request('tab','work');
    if (!in_array($tab,['work','files','history'],true)) $tab='work';

    // V3: status=completed là hoàn thành hợp lệ, không còn bắt buộc phê duyệt cuối.
    // Trạng thái approved cũ vẫn được coi là hoàn thành nếu dữ liệu duyệt cũ hợp lệ.
    $isCompleted = $schedule->status === 'completed'
        || ($schedule->status === 'approved' && $schedule->approval_status === 'approved');
    $isIntegrityIssue = false;
    $isPending = $schedule->status === 'pending_approval' || $schedule->approval_status === 'pending';
    // Không còn bước nhập báo cáo riêng. revision_requested quay thẳng về checklist để bổ sung.
    $isReport = $schedule->status === 'waiting_submission';
    $isDoing = in_array($schedule->status,['in_progress','waiting_material','revision_requested'],true);
    $isAssigned = in_array($schedule->status,['assigned','customer_confirmed','travelling'],true);
    $isUnassigned = in_array($schedule->status,['draft','scheduled','unassigned','postponed'],true);

    // V13: duyệt phân công là cổng nằm trong Bước 2, không tạo thêm bước mới.
    $assignmentApprovalStatus = (string)($schedule->assignment_approval_status ?: 'not_required');
    $isAssignmentLegacy = $assignmentApprovalStatus === 'not_required';
    $isAssignmentApproved = in_array($assignmentApprovalStatus,['approved','not_required'],true);
    $isAssignmentPending = $assignmentApprovalStatus === 'pending';
    $isAssignmentRevision = in_array($assignmentApprovalStatus,['revision_requested','rejected'],true);
    $isAssignmentDraft = $assignmentApprovalStatus === 'draft';
    $isReadyToStart = $isAssigned && $isAssignmentApproved;
    $isAdminWorkflowEditor = (bool)($permissions['admin'] ?? false);
    $canEditAssignment = $isAdminWorkflowEditor
        ? ((string)$schedule->status !== 'cancelled')
        : (
            ($permissions['assign'] ?? false)
            && !$isCompleted && !$isPending && !$isIntegrityIssue && !$isDoing && !$isReport
            && !$isAssignmentPending
            && !(int)($schedule->external_labor_payment_request_id ?? 0)
        );

    $externalFinanceLocked = $isAdminWorkflowEditor
        && (int)($schedule->external_labor_payment_request_id ?? 0) > 0;

    if ($isCompleted) $displayStatus = 'Hoàn thành';
    elseif ($isPending) $displayStatus = 'Hồ sơ cũ chờ hoàn tất';
    elseif ($isReport) $displayStatus = 'Sẵn sàng hoàn tất';
    elseif ($isDoing) $displayStatus = 'Đang thực hiện';
    elseif ($isAssignmentPending) $displayStatus = 'Chờ duyệt phân công';
    elseif ($isAssignmentRevision) $displayStatus = 'Sửa phân công';
    elseif ($isReadyToStart) $displayStatus = 'Sẵn sàng thực hiện';
    elseif ($isAssigned) $displayStatus = 'Chưa gửi duyệt phân công';
    else $displayStatus = 'Chờ phân công';

    // V13.2: giao diện gom còn 3 bước chính.
    // Backend vẫn giữ các trạng thái báo cáo/duyệt/hoàn thành hiện có để không phá dữ liệu,
    // nhưng Báo cáo + Duyệt + Hoàn thành đều được hiển thị như trạng thái con của Bước 3.
    $workflowStep = ($isReadyToStart || $isDoing || $isReport || $isPending || $isCompleted || $isIntegrityIssue) ? 3 : 2;
    $workflowFinished = $isCompleted;
    $steps = ['Kế hoạch','Phân công','Thực hiện & Hoàn tất'];

    // V13.3: Admin có thể mở lại giao diện của bước đã qua để chỉnh dữ liệu,
    // nhưng không lùi status/approval của workflow hiện tại.
    $adminRequestedStep = $isAdminWorkflowEditor ? (int)request('admin_step', 0) : 0;
    $adminViewStep = (
        $adminRequestedStep >= 1
        && $adminRequestedStep <= 3
        && $adminRequestedStep <= $workflowStep
    ) ? $adminRequestedStep : 0;
    $isAdminReviewingPastStep = $isAdminWorkflowEditor
        && $adminViewStep > 0
        && $adminViewStep < $workflowStep;

    $step2Text = 'Chưa phân công';
    if ($workflowStep > 2) {
        $step2Text = $isAssignmentLegacy ? 'Đã xong' : 'Sếp đã phê duyệt';
    } elseif ($isAssignmentPending) {
        $step2Text = 'Phê duyệt từ sếp · Đang chờ';
    } elseif ($isAssignmentRevision) {
        $step2Text = 'Phê duyệt từ sếp · Cần chỉnh sửa';
    } elseif ($isAssigned || $isAssignmentDraft) {
        $step2Text = 'Phê duyệt từ sếp · Chưa gửi';
    }

    $step3Text = 'Chưa mở';
    if ($isCompleted) {
        $step3Text = 'Hoàn thành';
    } elseif ($isPending) {
        $step3Text = 'Hồ sơ cũ chờ hoàn tất';
    } elseif ($isReport) {
        $step3Text = 'Sẵn sàng hoàn tất';
    } elseif ($isDoing) {
        $step3Text = $schedule->status === 'revision_requested' ? 'Cần bổ sung hồ sơ' : 'Đang thực hiện';
    } elseif ($isReadyToStart) {
        $step3Text = 'Sẵn sàng bắt đầu';
    }
    $leader = $schedule->leader?->user;
    $team = $schedule->assignees->pluck('user')->filter()->values();
    $checkTotal = $schedule->checklistItems->count();
    $checkDone = $schedule->checklistItems->where('is_done',true)->count();
    $requiredItems = $schedule->checklistItems->where('is_required',true);
    $requiredTotal = $requiredItems->count();
    $requiredDone = $requiredItems->where('is_done',true)->count();
    $missingEvidenceItems = $requiredItems->filter(function ($item) {
        return $item->requires_evidence && $item->attachments->count() < max(1, (int)$item->min_evidence);
    });
    $checkReady = $requiredTotal > 0 && $requiredDone === $requiredTotal && $missingEvidenceItems->isEmpty();
    $checkPercent = $requiredTotal ? round($requiredDone/$requiredTotal*100) : 0;
    $evidenceCount = $schedule->attachments->whereIn('category',['before','during','after','report','fault','serial','checklist'])->count();
    $cycleOptions = $siblings;
    $approvedRoundCount = $cycleOptions->filter(function ($item) {
        return $item->status === 'completed'
            || ($item->status === 'approved' && $item->approval_status === 'approved');
    })->count();
    $activeChecklistId = optional($schedule->checklistItems->firstWhere('is_done',false))->id
        ?: optional($schedule->checklistItems->first())->id;
    $canDeleteFiles = $permissions['upload'] && !$isCompleted && !$isPending && !$isIntegrityIssue;
    $filePreviewKind = function ($file) {
        $mime = strtolower((string) ($file->mime_type ?? ''));
        $extension = strtolower((string) pathinfo((string) $file->original_name, PATHINFO_EXTENSION));
        if (str_starts_with($mime, 'image/') || in_array($extension, ['jpg','jpeg','png','webp'], true)) return 'image';
        if ($mime === 'application/pdf' || $extension === 'pdf') return 'pdf';
        if (str_starts_with($mime, 'video/') || $extension === 'mp4') return 'video';
        if (in_array($extension, ['xls','xlsx'], true)) return 'spreadsheet';
        return 'file';
    };
@endphp

<div class="ego-container om10-page om10-detail om13-work-page">
    <nav class="om10-breadcrumb"><a href="{{ route('ky-thuat.maintenance.index') }}">Bảo hành & O&M</a><i class="bi bi-chevron-right"></i><span>{{ $schedule->schedule_code ?: '#'.$schedule->id }}</span></nav>

    @if(session('success'))<div class="om10-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="om10-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="om10-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể thực hiện</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <section class="om10-detail-head om13-work-header">
        <div class="om10-detail-title">
            <div class="om10-badges"><span>{{ $types[$schedule->type] ?? $schedule->type }}</span><span>Đợt {{ $schedule->round_no ?: 1 }}/{{ $schedule->total_rounds ?: 1 }}</span></div>
            <h1>{{ $schedule->site?->name ?: $schedule->site_name ?: 'Công trình' }}</h1>
            <p><strong>{{ $schedule->schedule_code ?: '#'.$schedule->id }}</strong> · {{ $schedule->address ?: $schedule->site?->address ?: 'Chưa có địa chỉ' }}</p>
        </div>
        <div class="om10-detail-facts">
            <div><small>Ngày dự kiến</small><strong class="{{ $schedule->isOverdue() ? 'danger' : '' }}">{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: '—' }}</strong></div>
            <div><small>Nhóm thực hiện</small><strong>{{ $team->count() ? $team->count().' người' : 'Chưa phân công' }}</strong></div>
            <div><small>Phụ trách chính</small><strong>{{ $leader?->name ?: 'Chưa có' }}</strong></div>
            <div><small>Trạng thái</small><strong class="{{ $isIntegrityIssue ? 'danger' : '' }}">{{ $displayStatus }}</strong></div>
        </div>
        <div class="om10-detail-actions">
            @if($permissions['admin'])<a class="om10-btn light" href="{{ route('ky-thuat.maintenance.checklist-settings.index') }}"><i class="bi bi-sliders"></i> Cài đặt checklist</a>@endif
            @if($schedule->site_id)<a class="om10-btn light" href="{{ route('ky-thuat.maintenance.site',['site'=>$schedule->site_id]) }}"><i class="bi bi-folder2-open"></i> Hồ sơ công trình</a>@endif
@if(($permissions['assign'] ?? false) || ($permissions['admin'] ?? false))
<button
    type="button"
    class="om10-btn light"
    data-bs-toggle="modal"
    data-bs-target="#omRoundsModal"
>
    <i class="bi bi-calendar2-range"></i>
    Sửa số đợt
</button>
@endif
            <a class="om10-btn light" href="{{ route('ky-thuat.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Quay lại</a>
        </div>
    </section>

    @if($isIntegrityIssue)
        <div class="om11-integrity-warning">
            <i class="bi bi-shield-exclamation"></i>
            <div><strong>Hồ sơ hoàn thành chưa hợp lệ</strong><span>Bản ghi này được đánh dấu hoàn thành nhưng không có đủ thông tin người duyệt và thời gian duyệt. Hệ thống không tính đợt này vào số liệu hoàn thành.</span></div>
            @if($permissions['reopen'])
                <form method="POST" action="{{ route('ky-thuat.maintenance.approval.reopen',$schedule) }}">@csrf<input name="comment" required value="Mở lại hồ sơ hoàn thành thiếu phê duyệt" aria-label="Lý do mở lại"><button class="om10-btn danger-outline" type="submit">Mở lại để hoàn thiện</button></form>
            @endif
        </div>
    @endif

    <section class="om14-rounds-board">
        <header>
            <div><span>CHU KỲ BẢO TRÌ</span><h2>Toàn bộ {{ $cycleOptions->count() }} đợt công việc</h2><p>Mỗi đợt là một hồ sơ độc lập. Bấm trực tiếp vào thẻ để mở.</p></div>
            <div><span class="om10-status {{ $isCompleted?'done':($isPending?'approval':($isReport?'report':($isDoing?'doing':'assigned'))) }}">{{ $statuses[$schedule->status] ?? $schedule->status }}</span><small>Đang mở đợt {{ $schedule->round_no ?: 1 }}/{{ $schedule->total_rounds ?: 1 }}</small></div>
        </header>
        <div class="om14-round-grid" role="tablist" aria-label="Danh sách đợt bảo trì">
            @foreach($cycleOptions as $item)
                @php
                    $roundIsActive = (int)$item->id === (int)$schedule->id;
                    $roundIsDone = $item->status === 'completed'
                        || ($item->status === 'approved' && $item->approval_status === 'approved');
                    $roundIsPending = $item->status === 'pending_approval' || $item->approval_status === 'pending';
                    $roundIsRevision = $item->status === 'revision_requested';
                    $roundIsOverdue = !$roundIsDone && $item->isOverdue();
                    $roundIcon = $roundIsDone ? 'bi-check-lg' : ($roundIsPending ? 'bi-shield-check' : (($roundIsRevision || $roundIsOverdue) ? 'bi-exclamation-lg' : 'bi-calendar3'));
                    $roundTone = $roundIsDone ? 'done' : ($roundIsPending ? 'approval' : ($roundIsRevision ? 'revision' : (in_array($item->status,['in_progress','waiting_material','waiting_submission'],true) ? 'doing' : 'plan')));
                @endphp
                <a role="tab" aria-selected="{{ $roundIsActive ? 'true' : 'false' }}" @if($roundIsActive) aria-current="page" @endif class="om14-round-card {{ $roundIsActive?'active':'' }} {{ $roundIsDone?'is-done':'' }} {{ $roundIsOverdue?'is-overdue':'' }}" href="{{ route('ky-thuat.maintenance.show',$item) }}">
                    <span class="om14-round-top"><b>Đợt {{ $item->round_no ?: 1 }}/{{ $item->total_rounds ?: $cycleOptions->count() }}</b><i class="bi {{ $roundIcon }}"></i></span>
                    <strong>{{ optional($item->scheduled_date)->format('d/m/Y') ?: 'Chưa có ngày' }}</strong>
                    <span class="om10-status {{ $roundTone }}">{{ $roundIsRevision ? 'Cần bổ sung' : ($statuses[$item->status] ?? $item->status) }}</span>
                    @if($roundIsOverdue)<small><i class="bi bi-clock-history"></i> Quá hạn</small>@elseif($roundIsActive)<small><i class="bi bi-eye"></i> Đang mở</small>@else<small>Mở hồ sơ <i class="bi bi-arrow-right"></i></small>@endif
                </a>
            @endforeach
        </div>
        <footer><span><i class="bi bi-check2-circle"></i> Đã hoàn thành <strong>{{ $approvedRoundCount }}/{{ $cycleOptions->count() }}</strong> đợt</span><span>Đợt đang mở: <strong>{{ $requiredDone }}/{{ $requiredTotal }}</strong> mục bắt buộc · <strong>{{ $evidenceCount }}</strong> file</span></footer>
    </section>

    <div class="om13-workspace-shell">
    <nav class="om13-step-rail om19-step-rail" aria-label="Tiến trình công việc">
        @foreach($steps as $i=>$label)
            @php
                $n = $i + 1;
                $isStepDone = $n < $workflowStep || ($n === 3 && $workflowFinished);
                $isStepCurrent = $n === $workflowStep && !$isStepDone;
                $stepText = $n === 1 ? 'Đã xong' : ($n === 2 ? $step2Text : $step3Text);
                $adminCanOpenStep = $isAdminWorkflowEditor && $n <= $workflowStep;
                $adminIsViewing = $adminViewStep === $n;
                $stepClass = trim(
                    ($isStepDone ? 'done ' : ($isStepCurrent ? 'current ' : ''))
                    .($adminCanOpenStep ? 'admin-editable ' : '')
                    .($adminIsViewing ? 'admin-viewing' : '')
                );
            @endphp

            @if($adminCanOpenStep)
                <a
                    class="{{ $stepClass }}"
                    href="{{ route('ky-thuat.maintenance.show',$schedule) }}?tab=work&admin_step={{ $n }}"
                    title="{{ $n < $workflowStep ? 'Admin: mở lại bước đã qua để chỉnh sửa' : 'Xem bước hiện tại' }}"
                >
                    <span>@if($isStepDone)<i class="bi bi-check-lg"></i>@else{{ $n }}@endif</span>
                    <div>
                        <strong>{{ $label }}</strong>
                        <small>{{ $stepText }}</small>
                        @if($n < $workflowStep)<em><i class="bi bi-pencil-square"></i> Admin có thể sửa</em>@endif
                    </div>
                </a>
            @else
                <div class="{{ $stepClass }}">
                    <span>@if($isStepDone)<i class="bi bi-check-lg"></i>@else{{ $n }}@endif</span>
                    <div><strong>{{ $label }}</strong><small>{{ $stepText }}</small></div>
                </div>
            @endif
        @endforeach
    </nav>
    <div class="om13-workspace-main">
    <nav class="om10-detail-tabs om13-work-tabs">
        <a class="{{ $tab==='work'?'active':'' }}" href="{{ route('ky-thuat.maintenance.show',$schedule) }}?tab=work"><i class="bi bi-tools"></i> Công việc</a>
        <a class="{{ $tab==='files'?'active':'' }}" href="{{ route('ky-thuat.maintenance.show',$schedule) }}?tab=files"><i class="bi bi-folder2-open"></i> Hồ sơ <b>{{ $schedule->attachments->count() }}</b></a>
        <a class="{{ $tab==='history'?'active':'' }}" href="{{ route('ky-thuat.maintenance.show',$schedule) }}?tab=history"><i class="bi bi-clock-history"></i> Lịch sử</a>
    </nav>

    @if($tab==='work')
        <div class="om10-work-layout">
            <main>
                @if($isAdminReviewingPastStep && $adminViewStep === 1)
                    <section class="om10-card om19-admin-past-step">
                        <div class="om19-admin-review-banner">
                            <div>
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>
                                    <strong>ADMIN · ĐANG CHỈNH BƯỚC ĐÃ QUA</strong>
                                    <small>Chỉnh Kế hoạch không làm lùi tiến trình hiện tại: {{ $displayStatus }}.</small>
                                </span>
                            </div>
                            <a class="om10-btn light" href="{{ route('ky-thuat.maintenance.show',$schedule) }}?tab=work">
                                <i class="bi bi-arrow-return-right"></i> Về bước hiện tại
                            </a>
                        </div>

                        <div class="om10-section-head">
                            <div>
                                <span>BƯỚC 1/3 · KẾ HOẠCH</span>
                                <h2>Chỉnh lại kế hoạch</h2>
                                <p>Admin được sửa thông tin kế hoạch đã qua. Hệ thống giữ nguyên phân công, checklist, báo cáo và trạng thái hiện tại.</p>
                            </div>
                            <span class="om10-status plan">Chỉnh sửa có nhật ký</span>
                        </div>

                        <form
                            method="POST"
                            action="{{ route('ky-thuat.maintenance.admin-plan.update',$schedule) }}"
                            class="om19-admin-plan-form"
                        >
                            @csrf
                            <div class="om19-admin-plan-grid">
                                <label>
                                    <span>Ngày thực hiện *</span>
                                    <input
                                        type="date"
                                        name="scheduled_date"
                                        value="{{ old('scheduled_date', optional($schedule->scheduled_date)->format('Y-m-d')) }}"
                                        required
                                    >
                                </label>

                                <label>
                                    <span>Loại công việc *</span>
                                    <select name="type" required>
                                        @foreach($types as $key=>$label)
                                            <option value="{{ $key }}" @selected(old('type',$schedule->type)===$key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label>
                                    <span>Ưu tiên *</span>
                                    <select name="priority" required>
                                        @foreach($priorities as $key=>$label)
                                            <option value="{{ $key }}" @selected(old('priority',$schedule->priority)===$key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                <label>
                                    <span>Công suất hệ thống (kWp)</span>
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="system_kwp"
                                        value="{{ old('system_kwp',$schedule->system_kwp) }}"
                                    >
                                </label>

                                <label class="wide">
                                    <span>Thông tin inverter / thiết bị</span>
                                    <input
                                        name="inverter_info"
                                        value="{{ old('inverter_info',$schedule->inverter_info) }}"
                                        placeholder="Model, công suất, serial nếu cần..."
                                    >
                                </label>

                                <label class="wide">
                                    <span>Nội dung / yêu cầu công việc</span>
                                    <textarea
                                        name="issue_note"
                                        rows="4"
                                        placeholder="Nội dung bảo hành, bảo trì, sự cố cần xử lý..."
                                    >{{ old('issue_note',$schedule->issue_note) }}</textarea>
                                </label>

                                <label class="wide">
                                    <span>Ghi chú kỹ thuật</span>
                                    <textarea
                                        name="technical_note"
                                        rows="3"
                                        placeholder="Ghi chú bổ sung cho kế hoạch..."
                                    >{{ old('technical_note',$schedule->technical_note) }}</textarea>
                                </label>
                            </div>

                            <div class="om19-admin-form-footer">
                                <div>
                                    <i class="bi bi-clock-history"></i>
                                    Mọi lần sửa của Admin được lưu vào Lịch sử. Không reset bước 2 hoặc bước 3.
                                </div>
                                <button class="om10-btn primary" type="submit">
                                    <i class="bi bi-save2"></i> Lưu lại Kế hoạch
                                </button>
                            </div>
                        </form>
                    </section>

                @elseif($isAdminReviewingPastStep && $adminViewStep === 2)
                    <section class="om10-card om19-admin-past-step">
                        <div class="om19-admin-review-banner">
                            <div>
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>
                                    <strong>ADMIN · ĐANG CHỈNH BƯỚC ĐÃ QUA</strong>
                                    <small>Chỉnh Phân công không làm lùi tiến trình hiện tại: {{ $displayStatus }}.</small>
                                </span>
                            </div>
                            <a class="om10-btn light" href="{{ route('ky-thuat.maintenance.show',$schedule) }}?tab=work">
                                <i class="bi bi-arrow-return-right"></i> Về bước hiện tại
                            </a>
                        </div>

                        <div class="om10-section-head">
                            <div>
                                <span>BƯỚC 2/3 · PHÂN CÔNG</span>
                                <h2>Xem lại & chỉnh phân công</h2>
                                <p>Admin có thể thay đổi người phụ trách và thành viên ngay cả khi hồ sơ đã sang Bước 3. Lần sửa được ghi lịch sử và không xóa dữ liệu thực hiện.</p>
                            </div>
                            <button class="om10-btn primary" type="button" data-bs-toggle="modal" data-bs-target="#om10TeamModal">
                                <i class="bi bi-pencil-square"></i> Chỉnh sửa phân công
                            </button>
                        </div>

                        <div class="om18-assignment-summary">
                            <div><small>Phụ trách chính</small><strong>{{ $leader?->name ?: 'Chưa chọn' }}</strong></div>
                            <div><small>Nhân sự nội bộ</small><strong>{{ $team->count() }} người</strong></div>
                            <div><small>Nhân công ngoài</small><strong>{{ $schedule->external_labor_enabled ? ((int)$schedule->external_labor_headcount).' người' : 'Không' }}</strong></div>
                            <div><small>Trạng thái công việc</small><strong>{{ $displayStatus }}</strong></div>
                        </div>

                        @if($schedule->external_labor_enabled)
                            <div class="om18-external-detail">
                                <div><span>Đơn vị/người nhận</span><b>{{ $schedule->external_labor_name ?: 'Chưa cập nhật' }}</b></div>
                                <div><span>SĐT</span><b>{{ $schedule->external_labor_phone ?: '—' }}</b></div>
                                <div><span>Tổng tiền công</span><b>{{ number_format((float)$schedule->external_labor_total_cost,0,',','.') }} đ</b></div>
                                <div><span>Tạm ứng / ĐNTT</span><b>{{ number_format((float)$schedule->external_labor_advance_amount,0,',','.') }} đ</b></div>
                            </div>
                        @endif

                        @if((int)($schedule->external_labor_payment_request_id ?? 0) > 0)
                            <div class="om19-admin-finance-lock">
                                <i class="bi bi-lock-fill"></i>
                                <div>
                                    <strong>Chi phí nhân công ngoài đã phát sinh ĐNTT #{{ $schedule->external_labor_payment_request_id }}</strong>
                                    <span>Admin vẫn sửa được nhân sự nội bộ. Các thông tin tiền công / tạm ứng / ngân hàng được khóa để không lệch Tài chính.</span>
                                </div>
                                @if(\Illuminate\Support\Facades\Route::has('payment_requests.show'))
                                    <a class="om10-btn light" href="{{ route('payment_requests.show',$schedule->external_labor_payment_request_id) }}">Xem ĐNTT</a>
                                @endif
                            </div>
                        @else
                            <div class="om19-admin-edit-note">
                                <i class="bi bi-info-circle"></i>
                                Nếu Admin sửa Phân công sau khi bước này đã qua, hệ thống ghi nhận lần sửa như đã được Admin xác nhận và giữ nguyên Bước 3.
                            </div>
                        @endif
                    </section>

                @else
                    @if($isIntegrityIssue)
                    <section class="om10-card">
                        <div class="om10-section-head"><div><span>KIỂM SOÁT DỮ LIỆU</span><h2>Đợt này chưa được công nhận hoàn thành</h2><p>Admin mở lại hồ sơ để bổ sung checklist và minh chứng, sau đó gửi duyệt lại đúng quy trình.</p></div><span class="om10-status revision">Thiếu phê duyệt</span></div>
                        @include('technical.maintenance.partials.report-review', ['schedule'=>$schedule,'checkDone'=>$checkDone,'checkTotal'=>$checkTotal,'reportConclusions'=>$reportConclusions])
                    </section>
                @elseif($isUnassigned)
                    <section class="om10-focus-card">
                        <div class="om10-focus-number">2</div>
                        <div class="om10-focus-copy"><span>BƯỚC 2/3 · PHÂN CÔNG</span><h2>Phân công nhóm kỹ thuật</h2><p>Một chuyến có thể chọn 1, 2, 3 hoặc nhiều kỹ thuật viên. Chỉ định một người phụ trách chính để tổng hợp báo cáo.</p></div>
                        @if($permissions['assign'])<button class="om10-btn primary" type="button" data-bs-toggle="modal" data-bs-target="#om10TeamModal"><i class="bi bi-people"></i> Chọn nhóm thực hiện</button>@endif
                    </section>
                @elseif($isAssigned && !$isAssignmentApproved)
                    <section class="om10-card om18-assignment-gate">
                        <div class="om10-section-head">
                            <div>
                                <span>BƯỚC 2/3 · PHÂN CÔNG · PHÊ DUYỆT TỪ SẾP</span>
                                <h2>{{ $isAssignmentPending ? 'Phân công đang chờ sếp duyệt' : ($isAssignmentRevision ? 'Cần chỉnh sửa phân công' : 'Phân công đã lưu - chưa gửi duyệt') }}</h2>
                                <p>Bước Thực hiện & Hoàn tất chỉ mở sau khi sếp phê duyệt phân công.</p>
                            </div>
                            <span class="om10-status {{ $isAssignmentPending ? 'approval' : ($isAssignmentRevision ? 'revision' : 'plan') }}">
                                {{ $assignmentApprovalStatuses[$assignmentApprovalStatus] ?? $assignmentApprovalStatus }}
                            </span>
                        </div>

                        @if($isAssignmentRevision && $schedule->assignment_approval_note)
                            <div class="om18-assignment-note"><i class="bi bi-exclamation-triangle"></i><div><strong>Ý kiến của người duyệt</strong><span>{{ $schedule->assignment_approval_note }}</span></div></div>
                        @endif

                        <div class="om18-assignment-summary">
                            <div><small>Phụ trách chính</small><strong>{{ $leader?->name ?: 'Chưa chọn' }}</strong></div>
                            <div><small>Nhân sự nội bộ</small><strong>{{ $team->count() }} người</strong></div>
                            <div><small>Nhân công ngoài</small><strong>{{ $schedule->external_labor_enabled ? ((int)$schedule->external_labor_headcount).' người' : 'Không' }}</strong></div>
                            <div><small>Tiền công ngoài</small><strong>{{ $schedule->external_labor_enabled ? number_format((float)$schedule->external_labor_total_cost,0,',','.').' đ' : '0 đ' }}</strong></div>
                        </div>

                        @if($schedule->external_labor_enabled)
                            <div class="om18-external-detail">
                                <div><span>Đơn vị/người nhận</span><b>{{ $schedule->external_labor_name ?: 'Chưa cập nhật' }}</b></div>
                                <div><span>SĐT</span><b>{{ $schedule->external_labor_phone ?: '—' }}</b></div>
                                <div><span>Đề nghị/tạm ứng</span><b>{{ number_format((float)$schedule->external_labor_advance_amount,0,',','.') }} đ</b></div>
                                <div><span>Ngân hàng</span><b>{{ $schedule->external_labor_bank_info ?: '—' }}</b></div>
                            </div>
                        @endif

                        @if($isAssignmentPending)
                            <div class="om18-waiting-banner"><i class="bi bi-lock-fill"></i><span>Nút <b>Bắt đầu công việc</b> đang khóa cho tới khi phân công được duyệt.</span></div>
                            @if($permissions['assignment_approve'] ?? false)
                                <div class="om10-approval-actions om18-assignment-actions">
                                    <form method="POST" action="{{ route('ky-thuat.maintenance.assignment-approval.revision',$schedule) }}">@csrf<textarea name="comment" rows="3" required placeholder="Nội dung cần chỉnh sửa trong phân công..."></textarea><button class="om10-btn danger-outline" type="submit">Trả lại chỉnh sửa</button></form>
                                    <form method="POST" action="{{ route('ky-thuat.maintenance.assignment-approval.approve',$schedule) }}" data-om11-confirm="Duyệt phân công và mở bước Thực hiện? Nếu có nhân công ngoài, hệ thống sẽ tự tạo ĐNTT.">@csrf<textarea name="comment" rows="3" placeholder="Nhận xét duyệt (không bắt buộc)"></textarea><button class="om10-btn success" type="submit"><i class="bi bi-patch-check"></i> Duyệt phân công</button></form>
                                </div>
                                <form class="om18-reject-assignment" method="POST" action="{{ route('ky-thuat.maintenance.assignment-approval.reject',$schedule) }}">@csrf<input name="comment" required placeholder="Lý do từ chối phân công"><button class="om10-btn danger-outline" type="submit">Từ chối</button></form>
                            @endif
                        @else
                            <div class="om18-assignment-actions-row">
                                @if($canEditAssignment)
                                    <button class="om10-btn light" type="button" data-bs-toggle="modal" data-bs-target="#om10TeamModal"><i class="bi bi-pencil-square"></i> Chỉnh sửa phân công</button>
                                @endif
                                @if($permissions['assign'])
                                    <form method="POST" action="{{ route('ky-thuat.maintenance.assignment-approval.submit',$schedule) }}">@csrf<input type="text" name="comment" placeholder="Ghi chú gửi sếp duyệt (không bắt buộc)"><button class="om10-btn primary" type="submit"><i class="bi bi-send-check"></i> Gửi sếp duyệt</button></form>
                                @endif
                            </div>
                        @endif
                    </section>
                @elseif($isReadyToStart)
                    <section class="om10-focus-card">
                        <div class="om10-focus-number">3</div>
                        <div class="om10-focus-copy"><span>BƯỚC 3/3 · THỰC HIỆN & HOÀN TẤT</span><h2>Sẵn sàng thực hiện</h2><p>{{ $isAssignmentLegacy ? 'Hồ sơ cũ được giữ nguyên luồng để không ảnh hưởng công việc đang có.' : 'Phân công đã được sếp duyệt.' }} Kỹ thuật có thể bắt đầu để mở checklist hiện trường.</p></div>
                        @if($permissions['update'])
                            <form method="POST" action="{{ route('ky-thuat.maintenance.work.start',$schedule) }}">@csrf<button class="om10-btn primary" type="submit"><i class="bi bi-play-fill"></i> Bắt đầu công việc</button></form>
                        @endif
                    </section>
                    @if($schedule->external_labor_enabled)
                        <section class="om10-card om18-approved-external"><div><i class="bi bi-people-fill"></i><span><strong>Nhân công ngoài đã được duyệt</strong><small>{{ (int)$schedule->external_labor_headcount }} người · {{ number_format((float)$schedule->external_labor_total_cost,0,',','.') }} đ</small></span></div>@if($schedule->external_labor_payment_request_id && ($permissions['assignment_approve'] ?? false))<a class="om10-btn light" href="{{ route('payment_requests.show',$schedule->external_labor_payment_request_id) }}">Xem ĐNTT #{{ $schedule->external_labor_payment_request_id }}</a>@endif</section>
                    @endif
                @elseif($isDoing)
                    <section class="om10-card om10-checklist-card om13-checklist-workspace">
                        <div class="om13-checklist-heading"><div><span>BƯỚC 3/3 · THỰC HIỆN & HOÀN TẤT</span><h2>Checklist hiện trường</h2><p>Mở từng bước, ghi kết quả và thêm minh chứng ngay tại bước đó.</p>@if($schedule->status==='revision_requested' && $schedule->approval_note)<div class="om10-revision-note">Cần bổ sung: {{ $schedule->approval_note }}</div>@endif</div><div><strong>{{ $requiredDone }}/{{ $requiredTotal }}</strong><span>{{ $missingEvidenceItems->isEmpty() ? $checkPercent.'%' : $missingEvidenceItems->count().' bước thiếu file' }}</span></div></div>
                        <div class="om10-progress"><span style="width:{{ $checkPercent }}%"></span></div>
                        <form id="om12ChecklistForm" method="POST" action="{{ route('ky-thuat.maintenance.checklist.save',$schedule) }}">@csrf</form>
                        <div class="om13-check-queue">
                            @foreach($schedule->checklistItems as $index => $item)
                                @php
                                    $itemFiles = $item->attachments;
                                    $minimumFiles = $item->requires_evidence ? max(1, (int)$item->min_evidence) : 0;
                                    $hasEnoughFiles = !$item->requires_evidence || $itemFiles->count() >= $minimumFiles;
                                @endphp
                                <article class="om13-check-task {{ (int)$item->id===(int)$activeChecklistId?'active':'' }} {{ $item->is_done?'is-done':'' }} {{ !$hasEnoughFiles?'needs-file':'' }}" data-om13-check-task>
                                    <button class="om13-check-summary" type="button" data-om13-check-open aria-expanded="{{ (int)$item->id===(int)$activeChecklistId?'true':'false' }}"><span class="om13-check-number">{{ str_pad($index+1,2,'0',STR_PAD_LEFT) }}</span><span class="om13-check-name"><strong>{{ $item->label }}</strong><small>{{ $item->is_required?'Bắt buộc':'Tùy chọn' }} · {{ $item->requires_evidence ? $itemFiles->count().' file · tối thiểu '.$minimumFiles : 'Không yêu cầu file' }}</small></span><span class="om13-check-result {{ $item->is_done&&$hasEnoughFiles?'ready':'waiting' }}"><i class="bi {{ $item->is_done&&$hasEnoughFiles?'bi-check-circle-fill':'bi-circle' }}"></i>{{ $item->is_done?'Đã xong':'Chưa xong' }}</span><i class="bi bi-chevron-down"></i></button>
                                    <div class="om13-check-detail" @if((int)$item->id!==(int)$activeChecklistId) hidden @endif>
                                        <div class="om13-check-instruction"><span class="om10-status {{ $item->is_required?'revision':'plan' }}">{{ $item->is_required?'Bắt buộc':'Tùy chọn' }}</span><p>{{ $item->template?->description ?: 'Ghi nhận kết quả kiểm tra hoặc thông số đo được tại hiện trường.' }}</p></div>
                                        <div class="om13-check-completion">
                                            <label class="om12-complete-toggle">
                                                <input form="om12ChecklistForm" type="checkbox" name="checked[]" value="{{ $item->id }}" @checked($item->is_done) data-om12-check data-evidence-ready="{{ $hasEnoughFiles ? 1 : 0 }}">
                                                <span><i class="bi bi-check-lg"></i></span>
                                                <b>{{ $item->is_done ? 'Đã hoàn thành' : 'Đánh dấu xong' }}</b>
                                            </label>
                                        </div>
                                        <label class="om13-note-field"><span>Kết quả / ghi chú kỹ thuật</span><textarea form="om12ChecklistForm" name="notes[{{ $item->id }}]" rows="3" placeholder="Nhập tình trạng, thông số hoặc nội dung đã xử lý...">{{ $item->note }}</textarea></label>

                                        <div class="om13-evidence-zone {{ $hasEnoughFiles?'ready':'missing' }}">
                                            <div class="om12-evidence-status {{ $hasEnoughFiles ? 'ready' : 'missing' }}">
                                                <i class="bi {{ $hasEnoughFiles ? 'bi-shield-check' : 'bi-cloud-arrow-up' }}"></i>
                                                <div><strong>Minh chứng: {{ $itemFiles->count() }} file</strong><span>{{ $item->requires_evidence ? ($hasEnoughFiles ? 'Đã đủ tối thiểu '.$minimumFiles.' file · vẫn có thể thêm' : 'Cần thêm '.($minimumFiles-$itemFiles->count()).' file') : 'Không yêu cầu file' }}</span></div>
                                            </div>
                                            @if($permissions['upload'] && $item->requires_evidence)
                                                <form class="om13-item-upload" method="POST" action="{{ route('ky-thuat.maintenance.schedule-files.store',$schedule) }}" enctype="multipart/form-data" data-om15-auto-upload>
                                                    @csrf
                                                    <input type="hidden" name="category" value="checklist">
                                                    <input type="hidden" name="checklist_item_id" value="{{ $item->id }}">
                                                    <label><input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4"><i class="bi bi-plus-circle"></i><span>Thêm ảnh hoặc file</span></label>
                                                </form>
                                            @endif
                                        </div>

                                        @if($itemFiles->isNotEmpty())
                                            <div class="om13-proof-list">
                                                @foreach($itemFiles as $file)
                                                    @php $previewKind = $filePreviewKind($file); @endphp
                                                    <span><i class="bi bi-file-earmark-check"></i><button class="om16-file-name" type="button" data-om16-preview data-kind="{{ $previewKind }}" data-name="{{ e($file->original_name) }}" data-preview-url="{{ route('ky-thuat.maintenance.schedule-files.preview',$file) }}" data-download-url="{{ route('ky-thuat.maintenance.schedule-files.download',$file) }}" @if($canDeleteFiles) data-delete-url="{{ route('ky-thuat.maintenance.schedule-files.destroy',$file) }}" @endif>{{ $file->original_name }}</button><small>{{ number_format(($file->file_size??0)/1024,1) }} KB</small><button class="om16-preview-action" type="button" title="Xem trước file" data-om16-preview data-kind="{{ $previewKind }}" data-name="{{ e($file->original_name) }}" data-preview-url="{{ route('ky-thuat.maintenance.schedule-files.preview',$file) }}" data-download-url="{{ route('ky-thuat.maintenance.schedule-files.download',$file) }}" @if($canDeleteFiles) data-delete-url="{{ route('ky-thuat.maintenance.schedule-files.destroy',$file) }}" @endif><i class="bi bi-eye"></i></button>@if($canDeleteFiles)<form method="POST" action="{{ route('ky-thuat.maintenance.schedule-files.destroy',$file) }}" data-om11-confirm="Xóa file minh chứng này?">@csrf @method('DELETE')<button class="om17-delete-file" type="submit" title="Xóa file"><i class="bi bi-trash3"></i></button></form>@endif</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <div class="om13-check-actions">
                            <span><i class="bi bi-info-circle"></i> Mục bắt buộc chỉ được hoàn thành khi đủ số file quy định.</span>
                            <button form="om12ChecklistForm" class="om10-btn light" type="submit"><i class="bi bi-save"></i> Lưu checklist</button>
                        </div>
                        @if(($permissions['submit'] ?? false) || ($permissions['update'] ?? false))
                            <form class="om10-finish-form om12-finish-form" method="POST" action="{{ route('ky-thuat.maintenance.work.finish',$schedule) }}" data-om11-confirm="Hoàn tất đợt bảo trì này? Sau khi hoàn tất, hồ sơ được lưu ngay và không cần gửi duyệt.">
                                @csrf
                                <div><strong>{{ $checkReady ? 'Hồ sơ đã đủ để hoàn tất' : 'Chưa thể hoàn tất' }}</strong><span>{{ $checkReady ? 'Checklist và minh chứng bắt buộc đã đầy đủ. Bấm Hoàn tất là xong, không có bước gửi duyệt phía sau.' : 'Còn '.$missingEvidenceItems->count().' mục thiếu minh chứng hoặc '.($requiredTotal-$requiredDone).' mục chưa hoàn thành.' }}</span></div>
                                <button class="om10-btn primary" type="submit" @disabled(!$checkReady)><i class="bi bi-check2-circle"></i> Hoàn tất đợt bảo trì</button>
                            </form>
                        @endif
                    </section>
                @elseif($isReport)
                    <section class="om10-card">
                        <div class="om10-section-head"><div><span>BƯỚC 3/3 · THỰC HIỆN & HOÀN TẤT</span><h2>Hồ sơ đã sẵn sàng hoàn tất</h2><p>Đây là trạng thái cũ. Checklist và minh chứng đã được nộp; bấm Hoàn tất là kết thúc đợt, không cần gửi duyệt.</p></div><span class="om10-status report">Sẵn sàng hoàn tất</span></div>
                        @include('technical.maintenance.partials.report-review', ['schedule'=>$schedule,'checkDone'=>$checkDone,'checkTotal'=>$checkTotal,'reportConclusions'=>$reportConclusions])
                        @if(($permissions['submit'] ?? false) || ($permissions['update'] ?? false))
                            <form method="POST" action="{{ route('ky-thuat.maintenance.work.finish',$schedule) }}" class="om10-submit-approval" data-om11-confirm="Hoàn tất đợt bảo trì này? Hồ sơ sẽ được lưu ngay, không cần gửi duyệt.">@csrf<button class="om10-btn primary" type="submit"><i class="bi bi-check2-circle"></i> Hoàn tất đợt bảo trì</button></form>
                        @endif
                    </section>
                @elseif($isPending)
                    <section class="om10-card">
                        <div class="om10-section-head"><div><span>BƯỚC 3/3 · THỰC HIỆN & HOÀN TẤT</span><h2>Hồ sơ cũ đang ở trạng thái chờ duyệt</h2><p>Quy trình mới không cần duyệt cuối. Nếu checklist và minh chứng đã đủ, bấm Hoàn tất để đóng đợt ngay.</p></div><span class="om10-status report">Chờ hoàn tất</span></div>
                        @include('technical.maintenance.partials.report-review', ['schedule'=>$schedule,'checkDone'=>$checkDone,'checkTotal'=>$checkTotal,'reportConclusions'=>$reportConclusions])
                        @if(($permissions['submit'] ?? false) || ($permissions['update'] ?? false) || ($permissions['approve'] ?? false))
                            <form method="POST" action="{{ route('ky-thuat.maintenance.work.finish',$schedule) }}" class="om10-submit-approval" data-om11-confirm="Hoàn tất hồ sơ cũ này mà không qua bước duyệt?">@csrf<button class="om10-btn primary" type="submit" @disabled(!$checkReady)><i class="bi bi-check2-circle"></i> Hoàn tất đợt bảo trì</button></form>
                        @endif
                    </section>
                @elseif($isCompleted)
                    <section class="om10-card">
                        <div class="om10-section-head"><div><span>BƯỚC 3/3 · THỰC HIỆN & HOÀN TẤT</span><h2>Đợt đã hoàn thành</h2><p>Checklist và minh chứng đã được lưu vào hồ sơ công trình. Không có bước phê duyệt cuối.</p></div><span class="om10-status done">Hoàn thành</span></div>
                        @include('technical.maintenance.partials.report-review', ['schedule'=>$schedule,'checkDone'=>$checkDone,'checkTotal'=>$checkTotal,'reportConclusions'=>$reportConclusions])
                        @if($permissions['reopen'])<form method="POST" action="{{ route('ky-thuat.maintenance.approval.reopen',$schedule) }}" class="om10-reopen">@csrf<input name="comment" required placeholder="Lý do mở lại"><button class="om10-btn light" type="submit">Mở lại công việc</button></form>@endif
                    </section>
                    @endif
                @endif
            </main>

            <aside>
                <section class="om10-card om13-readiness-card"><div class="om10-side-head"><strong>Hồ sơ tại chỗ</strong><span class="om10-status {{ $checkReady?'done':'revision' }}">{{ $checkReady?'Sẵn sàng':'Đang bổ sung' }}</span></div><div class="om13-readiness-ring" style="--om13-progress:{{ $checkPercent }}%"><strong>{{ $checkPercent }}%</strong><span>checklist bắt buộc</span></div><dl><div><dt>Hoàn thành</dt><dd>{{ $requiredDone }}/{{ $requiredTotal }}</dd></div><div><dt>Thiếu minh chứng</dt><dd>{{ $missingEvidenceItems->count() }} bước</dd></div><div><dt>Tổng file</dt><dd>{{ $evidenceCount }}</dd></div></dl>@if($schedule->attachments->isNotEmpty())<div class="om13-recent-proofs">@foreach($schedule->attachments->take(4) as $file)@php $previewKind = $filePreviewKind($file); @endphp<div class="om17-recent-file"><button type="button" data-om16-preview data-kind="{{ $previewKind }}" data-name="{{ e($file->original_name) }}" data-preview-url="{{ route('ky-thuat.maintenance.schedule-files.preview',$file) }}" data-download-url="{{ route('ky-thuat.maintenance.schedule-files.download',$file) }}" @if($canDeleteFiles) data-delete-url="{{ route('ky-thuat.maintenance.schedule-files.destroy',$file) }}" @endif><i class="bi bi-eye"></i><span>{{ \Illuminate\Support\Str::limit($file->original_name,28) }}</span></button>@if($canDeleteFiles)<form method="POST" action="{{ route('ky-thuat.maintenance.schedule-files.destroy',$file) }}" data-om11-confirm="Xóa file minh chứng này?">@csrf @method('DELETE')<button type="submit" title="Xóa file"><i class="bi bi-trash3"></i></button></form>@endif</div>@endforeach</div>@endif</section>
                <section class="om10-card om10-team-card">
                    <div class="om10-side-head"><strong>Nhóm thực hiện</strong>@if($canEditAssignment)<button type="button" data-bs-toggle="modal" data-bs-target="#om10TeamModal">Chỉnh sửa</button>@endif</div>
                    @forelse($schedule->assignees as $assignee)
                        <div class="om10-person"><span>{{ mb_strtoupper(mb_substr($assignee->user?->name ?: '?',0,1)) }}</span><div><strong>{{ $assignee->user?->name ?: 'Người dùng' }}</strong><small>{{ $assignee->is_leader || $assignee->role==='leader' ? 'Phụ trách chính' : 'Thành viên' }}</small></div></div>
                    @empty<div class="om10-empty mini">Chưa phân công.</div>@endforelse
                    @if($schedule->external_labor_enabled)
                        <div class="om18-side-external"><i class="bi bi-person-workspace"></i><div><strong>Nhân công ngoài</strong><small>{{ (int)$schedule->external_labor_headcount }} người · {{ number_format((float)$schedule->external_labor_total_cost,0,',','.') }} đ</small></div></div>
                    @endif
                    @if($isAssigned)
                        <div class="om18-side-approval"><span>Duyệt phân công</span><b>{{ $assignmentApprovalStatuses[$assignmentApprovalStatus] ?? $assignmentApprovalStatus }}</b></div>
                    @endif
                </section>
                <section class="om10-card om10-info-card"><strong>Thông tin công việc</strong><dl><div><dt>Khách hàng</dt><dd>{{ $schedule->customer_name ?: $schedule->site?->contact_name ?: '—' }}</dd></div><div><dt>Ưu tiên</dt><dd>{{ $priorities[$schedule->priority] ?? $schedule->priority }}</dd></div><div><dt>Checklist</dt><dd>{{ $checkDone }}/{{ $checkTotal }}</dd></div><div><dt>Minh chứng</dt><dd>{{ $evidenceCount }} file</dd></div><div><dt>Hoàn tất</dt><dd>{{ optional($schedule->completed_at ?: $schedule->execution_finished_at)->format('d/m/Y H:i') ?: 'Chưa hoàn tất' }}</dd></div></dl></section>
            </aside>
        </div>
    @elseif($tab==='files')
        <section class="om10-card">
            <div class="om10-section-head"><div><span>HỒ SƠ CÔNG VIỆC</span><h2>Ảnh, biên bản & tài liệu</h2><p>Toàn bộ minh chứng của đợt được lưu tại đây.</p></div></div>
            @if($permissions['upload'] && !$isCompleted && !$isPending && !$isIntegrityIssue)
            <form class="om10-file-upload" method="POST" action="{{ route('ky-thuat.maintenance.schedule-files.store',$schedule) }}" enctype="multipart/form-data">@csrf<select name="category" required><option value="before">Ảnh trước</option><option value="during">Trong quá trình</option><option value="after">Ảnh sau</option><option value="fault">Thiết bị lỗi</option><option value="serial">Serial</option><option value="report">Biên bản / báo cáo</option><option value="other">Khác</option></select><input type="file" name="files[]" multiple required><input name="description" placeholder="Ghi chú file"><button class="om10-btn primary" type="submit">Tải lên</button></form>
            @endif
            <div class="om10-files-grid">@forelse($schedule->attachments as $file)@php $previewKind = $filePreviewKind($file); @endphp<article><div class="om10-file-icon"><i class="bi bi-file-earmark"></i></div><div><strong>{{ $file->original_name }}</strong><span>{{ strtoupper($file->category) }} · {{ number_format(($file->file_size ?? 0)/1024,1) }} KB</span><small>{{ $file->uploader?->name ?: 'Hệ thống' }} · {{ optional($file->created_at)->format('d/m/Y H:i') }}</small></div><div><button type="button" title="Xem trước" data-om16-preview data-kind="{{ $previewKind }}" data-name="{{ e($file->original_name) }}" data-preview-url="{{ route('ky-thuat.maintenance.schedule-files.preview',$file) }}" data-download-url="{{ route('ky-thuat.maintenance.schedule-files.download',$file) }}" @if($canDeleteFiles) data-delete-url="{{ route('ky-thuat.maintenance.schedule-files.destroy',$file) }}" @endif><i class="bi bi-eye"></i></button><a title="Tải xuống" href="{{ route('ky-thuat.maintenance.schedule-files.download',$file) }}"><i class="bi bi-download"></i></a>@if($canDeleteFiles)<form method="POST" action="{{ route('ky-thuat.maintenance.schedule-files.destroy',$file) }}" data-om11-confirm="Xóa file minh chứng này?">@csrf @method('DELETE')<button class="om17-delete-file" type="submit" title="Xóa file"><i class="bi bi-trash3"></i></button></form>@endif</div></article>@empty<div class="om10-empty">Chưa có file.</div>@endforelse</div>
        </section>
    @else
        <section class="om10-card">
            <div class="om10-section-head"><div><span>NHẬT KÝ</span><h2>Lịch sử công việc</h2><p>Mọi thay đổi trạng thái, bổ sung hồ sơ và lịch sử cũ đều được lưu.</p></div></div>
            <div class="om10-timeline">
                @foreach($schedule->approvals as $a)<article><span></span><div><strong>{{ strtoupper(str_replace('_',' ',$a->action)) }}</strong><p>{{ $a->comment ?: ($approvalStatuses[$a->status] ?? $a->status) }}</p><small>{{ $a->approver?->name ?: $a->submitter?->name ?: 'Hệ thống' }} · {{ optional($a->reviewed_at ?: $a->submitted_at ?: $a->created_at)->format('d/m/Y H:i') }}</small></div></article>@endforeach
                @foreach($schedule->statusHistories as $h)<article><span></span><div><strong>{{ $statuses[$h->to_status] ?? $h->to_status }}</strong><p>{{ $h->reason ?: $h->note ?: 'Cập nhật trạng thái' }}</p><small>{{ $h->user?->name ?: 'Hệ thống' }} · {{ optional($h->changed_at)->format('d/m/Y H:i') }}</small></div></article>@endforeach
            </div>
        </section>
    @endif
    </div>
    </div>
</div>

<div class="modal fade om16-preview-modal" id="om16PreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content om10-modal">
            <div class="modal-header"><div><small>XEM TRƯỚC MINH CHỨNG</small><h2 data-om16-preview-title>File minh chứng</h2><p data-om16-preview-note>Đang chuẩn bị nội dung xem trước...</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body om16-preview-body">
                <div class="om16-preview-loading" data-om16-preview-loading><span></span><b>Đang mở file...</b></div>
                <img data-om16-preview-image alt="Xem trước ảnh" hidden>
                <iframe data-om16-preview-frame title="Xem trước tài liệu" hidden></iframe>
                <video data-om16-preview-video controls playsinline hidden></video>
                <div class="om16-preview-fallback" data-om16-preview-fallback hidden><i class="bi bi-file-earmark-arrow-down"></i><strong>Định dạng này chưa xem trực tiếp trong trình duyệt</strong><span>Hãy tải file xuống để mở bằng ứng dụng phù hợp.</span></div>
            </div>
            <div class="modal-footer">@if($canDeleteFiles)<form method="POST" action="#" data-om17-preview-delete data-om11-confirm="Xóa file minh chứng này?" hidden>@csrf @method('DELETE')<button class="om10-btn danger-outline" type="submit"><i class="bi bi-trash3"></i> Xóa file</button></form>@endif<a class="om10-btn light" target="_blank" rel="noopener" data-om16-preview-newtab><i class="bi bi-box-arrow-up-right"></i> Mở tab mới</a><a class="om10-btn primary" data-om16-preview-download><i class="bi bi-download"></i> Tải xuống</a></div>
        </div>
    </div>
</div>

@if($canEditAssignment)
<div class="modal fade" id="om10TeamModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg"><form class="modal-content om10-modal" method="POST" action="{{ route('ky-thuat.maintenance.team.assign',$schedule) }}">@csrf
        <div class="modal-header"><div><small>PHÂN CÔNG NHÓM</small><h2>Chọn nhân sự thực hiện</h2><p>{{ $isAdminReviewingPastStep && $adminViewStep===2 ? 'Admin đang chỉnh lại bước đã qua. Lưu xong vẫn giữ nguyên tiến trình hiện tại.' : 'Chọn nhân sự nội bộ và, nếu cần, khai báo nhân công ngoài. Sau khi lưu phải gửi sếp duyệt.' }}</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <label class="om10-team-search"><i class="bi bi-search"></i><input type="search" placeholder="Tìm kỹ thuật viên..." data-om10-team-search></label>
            <div class="om10-team-picker" data-om10-team-picker>
                @foreach($technicalUsers as $user)
                    @php $selected=in_array((int)$user->id,array_map('intval',$memberIds),true) || (int)$leaderId===(int)$user->id; @endphp
                    <div class="om10-team-option" data-name="{{ mb_strtolower($user->name) }}">
                        <label class="member"><input type="checkbox" name="member_user_ids[]" value="{{ $user->id }}" @checked($selected)><span></span><div><strong>{{ $user->name }}</strong><small>{{ $user->position?->name ?: $user->department?->name ?: 'Kỹ thuật' }}</small></div></label>
                        <label class="leader"><input type="radio" name="leader_user_id" value="{{ $user->id }}" @checked((int)$leaderId===(int)$user->id)><span>Phụ trách chính</span></label>
                    </div>
                @endforeach
            </div>
            <div class="om10-team-summary">Đã chọn <strong data-om10-team-count>0</strong> người</div>

            @if($externalFinanceLocked)
                <div class="om19-modal-finance-lock">
                    <i class="bi bi-lock-fill"></i>
                    <span><strong>Đã có ĐNTT #{{ $schedule->external_labor_payment_request_id }}</strong><small>Chỉ sửa nhân sự nội bộ. Thông tin thuê ngoài được giữ nguyên để không lệch Tài chính.</small></span>
                </div>
            @endif

            <section class="om18-external-form" data-om18-external-wrap>
                <label class="om18-external-toggle">
                    <input type="checkbox" name="external_labor_enabled" value="1" data-om18-external-toggle @checked($schedule->external_labor_enabled) @disabled($externalFinanceLocked)>
                    <span><i class="bi bi-person-workspace"></i></span>
                    <div><strong>Thuê nhân công ngoài</strong><small>Bật khi cần thuê thêm đội/người ngoài công ty cho đợt này.</small></div>
                </label>
                <div class="om18-external-fields" data-om18-external-fields @if(!$schedule->external_labor_enabled) hidden @endif>
                    <label><span>Đơn vị / người nhận việc</span><input name="external_labor_name" value="{{ old('external_labor_name',$schedule->external_labor_name) }}" placeholder="VD: Đội thi công ABC" @disabled($externalFinanceLocked)></label>
                    <label><span>Số điện thoại</span><input name="external_labor_phone" value="{{ old('external_labor_phone',$schedule->external_labor_phone) }}" placeholder="SĐT liên hệ" @disabled($externalFinanceLocked)></label>
                    <label><span>Số lượng nhân công *</span><input type="number" min="1" max="999" name="external_labor_headcount" value="{{ old('external_labor_headcount',$schedule->external_labor_headcount) }}" placeholder="VD: 4" @disabled($externalFinanceLocked)></label>
                    <label><span>Tổng tiền công *</span><input type="number" min="0" step="1000" name="external_labor_total_cost" value="{{ old('external_labor_total_cost',(int)$schedule->external_labor_total_cost) }}" placeholder="VD: 8000000" @disabled($externalFinanceLocked)></label>
                    <label><span>Số tiền đề nghị / tạm ứng</span><input type="number" min="0" step="1000" name="external_labor_advance_amount" value="{{ old('external_labor_advance_amount',(int)$schedule->external_labor_advance_amount) }}" placeholder="Để 0 = đề nghị toàn bộ tiền công" @disabled($externalFinanceLocked)></label>
                    <label><span>Thông tin ngân hàng</span><input name="external_labor_bank_info" value="{{ old('external_labor_bank_info',$schedule->external_labor_bank_info) }}" placeholder="Ngân hàng - STK - Chủ TK" @disabled($externalFinanceLocked)></label>
                    <label class="wide"><span>Nội dung / phạm vi công việc</span><textarea name="external_labor_note" rows="3" placeholder="Nội dung thuê ngoài, điều kiện thanh toán..." @disabled($externalFinanceLocked)>{{ old('external_labor_note',$schedule->external_labor_note) }}</textarea></label>
                    <p class="om18-external-hint"><i class="bi bi-info-circle"></i> Khi sếp duyệt phân công, hệ thống tự tạo ĐNTT. Nếu số tiền tạm ứng = 0, ĐNTT lấy toàn bộ tổng tiền công.</p>
                </div>
            </section>
        </div>
        <div class="modal-footer"><button class="om10-btn light" type="button" data-bs-dismiss="modal">Hủy</button><button class="om10-btn primary" type="submit">{{ $isAdminReviewingPastStep && $adminViewStep===2 ? 'Lưu chỉnh sửa của Admin' : 'Lưu phân công' }}</button></div>
    </form></div>
</div>
@endif
@endsection

@section('scripts')
<script src="{{ asset('js/technical-maintenance-v10.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v10.js')) ? filemtime(public_path('js/technical-maintenance-v10.js')) : time() }}"></script>
@endsection

@if(($permissions['assign'] ?? false) || ($permissions['admin'] ?? false))
<div
    class="modal fade"
    id="omRoundsModal"
    tabindex="-1"
>
    <div
        class="modal-dialog modal-dialog-centered"
    >
        <form
            method="POST"
            class="modal-content"
            action="{{ route('ky-thuat.maintenance.rounds.update', $schedule) }}"
        >
            @csrf

            <div class="modal-header">
                <div>
                    <div class="small text-muted">
                        CHU KỲ BẢO TRÌ / BẢO HÀNH
                    </div>

                    <h5 class="modal-title">
                        Sửa số đợt
                    </h5>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body">

                <label class="form-label">
                    Tổng số đợt
                </label>

                <input
                    class="form-control"
                    type="number"
                    name="total_rounds" data-om-total-rounds
                    min="1"
                    max="36"
                    value="{{ $schedule->total_rounds ?: $cycleOptions->count() }}"
                    required
                >

                <label class="form-label mt-3">
                    Khoảng cách đợt mới
                </label>

                <select
                    class="form-select"
                    name="interval_months" data-om-interval-months
                >
                    @for($month = 1; $month <= 12; $month++)
                        <option value="{{ $month }}" @selected($month === 3)>
                            {{ $month }} tháng
                        </option>
                    @endfor
                </select>

                
                {{-- EGO_ROUND_DATE_EDITOR_FINAL --}}
                <div class="mt-4">
                    <div class="mb-2">
                        <label class="form-label fw-semibold mb-1">
                            Ngày từng đợt
                        </label>

                        <div class="small text-muted">
                            Chỉnh trực tiếp ngày của từng đợt bảo hành/bảo trì.
                        </div>
                    </div>

                    <div
                        data-om-round-dates
                        style="
                            max-height:280px;
                            overflow-y:auto;
                            border:1px solid #e5e7eb;
                            border-radius:10px;
                            padding:8px 12px;
                            background:#f8fafc;
                        "
                    >
                        @foreach($cycleOptions as $roundOption)
                            @php
                                $roundDateValue = $roundOption->scheduled_date
                                    ? \Illuminate\Support\Carbon::parse(
                                        $roundOption->scheduled_date
                                    )->format('Y-m-d')
                                    : '';

                                $roundDateLocked = false;
                            @endphp

                            <div
                                class="d-flex align-items-center gap-3 py-2"
                                data-om-round-row
                                data-round-no="{{ $roundOption->round_no ?: $loop->iteration }}"
                                data-round-id="{{ $roundOption->id }}"
                                data-locked="0"
                                @if(!$loop->last)
                                    style="border-bottom:1px solid #e5e7eb;"
                                @endif
                            >
                                <div style="width:75px;flex:0 0 75px;">
                                    <strong>
                                        Đợt {{ $roundOption->round_no ?: $loop->iteration }}
                                    </strong>

                                    
                                </div>

                                <div class="flex-grow-1">
                                    <input
                                        type="date"
                                        class="form-control form-control-sm"
                                        name="round_dates[{{ $roundOption->id }}]"
                                        value="{{ $roundDateValue }}"
                                        
                                    >
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
<div
                    class="alert alert-light border mt-3 mb-0"
                >
                    Giảm số đợt chỉ xóa các đợt
                    chưa phát sinh thực hiện.
                    Các đợt đã làm sẽ được giữ lại.
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-light"
                    data-bs-dismiss="modal"
                >
                    Hủy
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Cập nhật
                </button>

            </div>
        </form>
    </div>
</div>
@endif

{{-- EGO_DYNAMIC_ROUNDS_UI_V1 --}}
<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal = document.getElementById('omRoundsModal');
    if (!modal) return;

    const totalInput =
        modal.querySelector('[data-om-total-rounds]');

    const intervalSelect =
        modal.querySelector('[data-om-interval-months]');

    const list =
        modal.querySelector('[data-om-round-dates]');

    if (!totalInput || !intervalSelect || !list) {
        return;
    }

    function parseDate(value) {
        if (!value) return null;

        const d = new Date(value + 'T00:00:00');

        return Number.isNaN(d.getTime())
            ? null
            : d;
    }

    function formatDate(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');

        return `${y}-${m}-${d}`;
    }

    function addMonthsSafe(date, months) {
        const originalDay = date.getDate();

        const result = new Date(date);
        result.setDate(1);
        result.setMonth(result.getMonth() + months);

        const lastDay = new Date(
            result.getFullYear(),
            result.getMonth() + 1,
            0
        ).getDate();

        result.setDate(
            Math.min(originalDay, lastDay)
        );

        return result;
    }

    function existingRows() {
        return Array.from(
            list.querySelectorAll('[data-om-round-row]')
        );
    }

    function makeRow(roundNo, dateValue) {

        const row = document.createElement('div');

        row.className =
            'd-flex align-items-center gap-3 py-2';

        row.setAttribute('data-om-round-row', '');
        row.dataset.roundNo = String(roundNo);
        row.dataset.roundId = '';
        row.dataset.locked = '0';

        row.style.borderBottom = '1px solid #e5e7eb';

        row.innerHTML = `
            <div style="width:75px;flex:0 0 75px;">
                <strong>Đợt ${roundNo}</strong>
                <div class="small text-muted">
                    Mới
                </div>
            </div>

            <div class="flex-grow-1">
                <input
                    type="date"
                    class="form-control form-control-sm"
                    name="new_round_dates[${roundNo}]"
                    value="${dateValue || ''}"
                >
            </div>
        `;

        return row;
    }

    function getLastKnownDate(rows) {

        for (let i = rows.length - 1; i >= 0; i--) {

            const input = rows[i].querySelector(
                'input[type="date"]'
            );

            if (input && input.value) {
                return parseDate(input.value);
            }
        }

        return null;
    }

    function syncRows() {

        const target =
            Math.max(
                1,
                parseInt(totalInput.value || '1', 10)
            );

        const interval =
            Math.max(
                1,
                parseInt(intervalSelect.value || '3', 10)
            );

        let rows = existingRows();

        /*
         * Thu bớt dòng mới nếu giảm tổng số đợt.
         * Dòng DB cũ đã khóa thì không xóa UI.
         */
        while (rows.length > target) {

            const last = rows[rows.length - 1];

            /*
             * Tổng số đợt trên form là nguồn chuẩn.
             * Nhập 5 thì danh sách chỉ hiển thị đúng 5 dòng.
             */
            last.remove();

            rows = existingRows();
        }

        /*
         * Sinh thêm dòng mới nếu tăng tổng số đợt.
         */
        rows = existingRows();

        if (rows.length < target) {

            let lastDate =
                getLastKnownDate(rows);

            for (
                let roundNo = rows.length + 1;
                roundNo <= target;
                roundNo++
            ) {

                if (lastDate) {
                    lastDate =
                        addMonthsSafe(
                            lastDate,
                            interval
                        );
                }

                const row =
                    makeRow(
                        roundNo,
                        lastDate
                            ? formatDate(lastDate)
                            : ''
                    );

                list.appendChild(row);
            }
        }

        /*
         * Bỏ border dòng cuối
         */
        rows = existingRows();

        rows.forEach(function (row, index) {
            row.style.borderBottom =
                index === rows.length - 1
                    ? '0'
                    : '1px solid #e5e7eb';
        });
    }

    totalInput.addEventListener(
        'input',
        syncRows
    );

    intervalSelect.addEventListener(
        'change',
        function () {

            /*
             * Khi đổi khoảng cách, chỉ tính lại các dòng MỚI.
             * Không đè ngày các đợt DB hiện có.
             */
            const rows = existingRows();

            const interval =
                Math.max(
                    1,
                    parseInt(
                        intervalSelect.value || '3',
                        10
                    )
                );

            let lastDate = null;

            rows.forEach(function (row) {

                const input =
                    row.querySelector('input[type="date"]');

                if (!input) return;

                if (row.dataset.roundId) {

                    if (input.value) {
                        lastDate =
                            parseDate(input.value);
                    }

                    return;
                }

                if (lastDate) {

                    lastDate =
                        addMonthsSafe(
                            lastDate,
                            interval
                        );

                    input.value =
                        formatDate(lastDate);
                }
            });
        }
    );

    modal.addEventListener(
        'shown.bs.modal',
        syncRows
    );
});
</script>
