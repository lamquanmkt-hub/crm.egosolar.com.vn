@extends('layouts.app')

@section('title', 'Chi tiết Bảo trì & Bảo hành')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-detail-v9.css') }}?v={{ file_exists(public_path('css/technical-maintenance-detail-v9.css')) ? filemtime(public_path('css/technical-maintenance-detail-v9.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-proposal-v1.css') }}?v={{ file_exists(public_path('css/technical-maintenance-proposal-v1.css')) ? filemtime(public_path('css/technical-maintenance-proposal-v1.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-experience-v2.css') }}?v={{ file_exists(public_path('css/technical-maintenance-experience-v2.css')) ? filemtime(public_path('css/technical-maintenance-experience-v2.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-round-v8.css') }}?v={{ file_exists(public_path('css/technical-maintenance-round-v8.css')) ? filemtime(public_path('css/technical-maintenance-round-v8.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-round-v9.css') }}?v={{ file_exists(public_path('css/technical-maintenance-round-v9.css')) ? filemtime(public_path('css/technical-maintenance-round-v9.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-assignment-v10.css') }}?v={{ file_exists(public_path('css/technical-maintenance-assignment-v10.css')) ? filemtime(public_path('css/technical-maintenance-assignment-v10.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-execution-v11.css') }}?v={{ file_exists(public_path('css/technical-maintenance-execution-v11.css')) ? filemtime(public_path('css/technical-maintenance-execution-v11.css')) : time() }}">
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-incident-v12.css') }}?v={{ file_exists(public_path('css/technical-maintenance-incident-v12.css')) ? filemtime(public_path('css/technical-maintenance-incident-v12.css')) : time() }}">
@endsection

@section('content')

@php

    $roundNo = max(1, (int) ($schedule->round_no ?: 1));
    $totalRounds = max(1, (int) ($schedule->total_rounds ?: 1));
    $siteName = $schedule->site?->name ?: $schedule->site_name ?: 'Công trình chưa đặt tên';
    $siteAddress = $schedule->site?->address ?: $schedule->address ?: 'Chưa cập nhật địa chỉ';
    $customerName = $schedule->site?->contact_name ?: $schedule->customer_name ?: 'Chưa cập nhật khách hàng';
    $leaderName = $schedule->leader?->user?->name ?: $schedule->assigned_name ?: 'Chưa phân công';
    $statusLabel = $statuses[$schedule->status] ?? $schedule->status;
    $approvalLabel = $approvalStatuses[$schedule->approval_status] ?? $schedule->approval_status;
    $isOverdue = $schedule->isOverdue();
    $overdueDays = $isOverdue ? max(1, (int) $schedule->scheduled_date->diffInDays(today())) : 0;
    $editMode = request()->boolean('edit') && ($permissions['update'] ?? false);
    $workflowPercent = count($workflowSteps) > 0 ? (int) round(($currentWorkflowStep / count($workflowSteps)) * 100) : 0;
    $displayProgress = $workSummary['total'] > 0 ? $workSummary['progress'] : $workflowPercent;
    $statusTone = match (true) {
        in_array($schedule->status, ['completed','approved'], true) => 'success',
        in_array($schedule->status, ['pending_approval','waiting_submission','waiting_material','revision_requested'], true) => 'warning',
        in_array($schedule->status, ['cancelled'], true) => 'danger',
        in_array($schedule->status, ['in_progress','travelling','assigned','customer_confirmed'], true) => 'primary',
        default => 'muted',
    };
    $roundCompleted = in_array($schedule->status, ['approved', 'completed'], true);
    $assignedCount = $schedule->assignees->count();
    $completedRounds = $siblings->whereIn('status', ['approved', 'completed'])->count();
    $attachmentCount = $schedule->attachments->count();
    $requiredChecklist = max($workSummary['total'], $attachmentCount > 0 ? $attachmentCount : 2);
    $completedChecklist = $workSummary['total'] > 0 ? $workSummary['completed'] : min($requiredChecklist, $attachmentCount);
    $checklistPercent = $requiredChecklist > 0 ? min(100, (int) round(($completedChecklist / $requiredChecklist) * 100)) : 0;
    $assignmentApproved = ($assignmentApprovalStatus ?? 'pending') === 'approved';
    $currentUserAccepted = (bool) ($currentAssignment?->accepted_at ?? null);
    $hasIncident = (bool) (($schedule->incident_kind && $schedule->incident_kind !== 'none') || (isset($maintenanceProposals) && $maintenanceProposals->isNotEmpty()));
    $planDone = (bool) $schedule->scheduled_date;
    $assignmentDone = $assignedCount > 0 && $assignmentApproved;
    $executionStarted = $workSummary['total'] > 0 || in_array($schedule->status, ['in_progress','waiting_material','waiting_submission','pending_approval','approved','completed'], true);
    $executionDone = $roundCompleted || ($workSummary['total'] > 0 && $workSummary['completed'] === $workSummary['total']);
    $roundStages = ['summary', 'plan', 'assignment', 'perform', 'incident', 'complete'];
    $activeStage = request('round_step', 'summary');
    $activeStage = in_array($activeStage, $roundStages, true) ? $activeStage : 'summary';

@endphp

<div class="emd9-page" data-emd9-root data-emx2-initial-stage="{{ $activeStage }}">
    <nav class="emd9-breadcrumb" aria-label="Breadcrumb">
        <a href="{{ route('projects-unified.index') }}">Dự án</a>
        <i class="bi bi-chevron-right"></i>
        <a href="{{ route('projects-unified.maintenance.index') }}">Bảo trì &amp; Bảo hành</a>
        <i class="bi bi-chevron-right"></i>
        
@if($schedule->site_id)

            <a href="{{ route('projects-unified.maintenance.site', ['site' => $schedule->site_id]) }}">Chi tiết công trình</a>
            <i class="bi bi-chevron-right"></i>
        
@endif

        <span>{{ $schedule->schedule_code ?: '#'.$schedule->id }}</span>
    </nav>

@if(session('success'))

        <div class="emd9-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>
    
@endif

@if(session('error'))

        <div class="emd9-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>
    
@endif

@if($errors->any())

        <div class="emd9-alert danger align-start"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể thực hiện</strong><ul>
@foreach($errors->all() as $error)
<li>{{ $error }}</li>
@endforeach
</ul></div></div>
    
@endif

    
@if(!$schedule->site_id || !$schedule->site)

        <div class="emd9-alert warning"><i class="bi bi-link-45deg"></i><span>Phiếu chưa liên kết công trình hợp lệ. Dữ liệu cũ vẫn được giữ nguyên để Ban quản lý xử lý.</span></div>
    
@endif

    <header class="emd9-hero">
        <div class="emd9-hero-title">
            <div class="emd9-kickers">
                <span><i class="bi {{ $isWarrantyFlow ? 'bi-shield-check' : 'bi-calendar2-check' }}"></i> {{ $isWarrantyFlow ? 'Bảo hành / Sự cố' : ($types[$schedule->type] ?? 'Bảo trì định kỳ') }}</span>
                <b>Đợt {{ $roundNo }}/{{ $totalRounds }}</b>
            </div>
            <h1>{{ $siteName }}</h1>
            <p><a class="emx2-schedule-code" href="#">{{ $schedule->schedule_code ?: '#'.$schedule->id }}</a><span> · {{ $siteAddress }}</span></p>
        </div>

        <div class="emd9-hero-meta">
            <div><small>Ngày dự kiến</small><strong>{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: '—' }}</strong>
@if($isOverdue)
<em>Quá hạn {{ $overdueDays }} ngày</em>
@endif
</div>
            <div><small>Nhóm thực hiện</small><strong>{{ $assignedCount > 0 ? $assignedCount.' kỹ thuật viên' : 'Chưa phân công' }}</strong></div>
            <div><small>Phụ trách chính</small><strong>{{ $leaderName }}</strong></div>
            <div><small>Trạng thái</small><strong class="emd9-badge {{ $statusTone }}">{{ $statusLabel }}</strong></div>
        </div>

        <div class="emd9-head-actions">
            @if($schedule->site_id)<a class="emd9-btn light" href="{{ route('projects-unified.maintenance.site', ['site'=>$schedule->site_id]) }}"><i class="bi bi-folder2-open"></i> Hồ sơ công trình</a>@endif
            @if($permissions['update'])<button class="emd9-btn light" type="button" data-emx2-stage="plan"><i class="bi bi-journal-text"></i> Sửa số đợt</button>@endif
            <a class="emd9-btn light" href="{{ route('projects-unified.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Quay lại</a>
            
            <details class="emd9-more">
                <summary class="emd9-btn light"><i class="bi bi-three-dots"></i></summary>
                <div>
                    
@if($schedule->site_id)
<a href="{{ route('projects-unified.maintenance.site', ['site'=>$schedule->site_id]) }}">Hồ sơ công trình</a>
@endif

@if($permissions['reopen'] && in_array($schedule->status, ['approved','completed','pending_approval','revision_requested'], true))
<button type="button" data-emp1-open="complete">Mở lại công việc</button>
@endif

                </div>
            </details>
        </div>
    </header>

    <section class="emx2-cycle" aria-label="Chu kỳ bảo trì">
        <header class="emx2-cycle-head"><div><span>CHU KỲ BẢO TRÌ</span><h2>Toàn bộ {{ max($totalRounds, $siblings->count()) }} đợt công việc</h2><p>Mỗi đợt là một hồ sơ độc lập. Bấm trực tiếp vào thẻ để mở.</p></div><div><span class="emd9-badge {{ $roundCompleted ? 'success' : 'primary' }}">{{ $roundCompleted ? 'Hoàn thành' : 'Đã lên lịch' }}</span><small>Đang mở đợt {{ $roundNo }}/{{ $totalRounds }}</small></div></header>
        <div class="emx2-round-grid">
            @foreach($siblings as $item)
                <?php $itemDone = in_array($item->status, ['approved','completed'], true); ?>
                <?php $itemCurrent = (int) $item->id === (int) $schedule->id; ?>
                <?php $itemOverdue = $item->scheduled_date && $item->scheduled_date->isPast() && !in_array($item->status, ['completed','approved','cancelled'], true); ?>
                <a href="{{ route('projects-unified.maintenance.show', ['schedule'=>$item->id]) }}" class="emx2-round {{ $itemDone ? 'is-done' : '' }} {{ $itemCurrent ? 'is-active' : '' }} {{ $itemOverdue ? 'is-overdue' : '' }}"><div><span>Đợt {{ $item->round_no ?: 1 }}/{{ max(1, (int) ($item->total_rounds ?: $totalRounds)) }}</span><i class="bi {{ $itemDone ? 'bi-check-lg' : 'bi-calendar3' }}"></i></div><strong>{{ optional($item->scheduled_date)->format('d/m/Y') ?: 'Chưa đặt ngày' }}</strong><span class="emd9-badge {{ $itemDone ? 'success' : ($itemOverdue ? 'warning' : 'primary') }}">{{ $statuses[$item->status] ?? $item->status }}</span><footer><span>{{ $itemCurrent ? 'Đang mở' : 'Mở hồ sơ' }}</span><i class="bi {{ $itemCurrent ? 'bi-eye' : 'bi-arrow-right' }}"></i></footer></a>
            @endforeach
        </div>
        <footer class="emx2-cycle-foot"><span><i class="bi bi-check2-circle"></i> Đã hoàn thành <b>{{ $completedRounds }}/{{ max($totalRounds, $siblings->count()) }}</b> đợt</span><span>Đợt đang mở: <b>{{ $completedChecklist }}/{{ $requiredChecklist }}</b> mục bắt buộc · <b>{{ $attachmentCount }}</b> file</span></footer>
    </section>

    <div class="emp1-layout">
        <aside class="emp1-rail" aria-label="Quy trình một đợt bảo trì bảo hành">
            <div class="emx2-process-heading">QUY TRÌNH</div>
            <button class="emp1-step emx2-process-step" type="button" data-emp1-target="summary" data-emp1-tab="summary"><span>1</span><div><b>Tổng quan</b><small>Trạng thái cả đợt</small></div></button>
            <button class="emp1-step emx2-process-step {{ $planDone ? 'is-done' : '' }}" type="button" data-emp1-target="plan" data-emp1-tab="plan"><span>{{ $planDone ? '✓' : '2' }}</span><div><b>Kế hoạch</b><small>{{ $planDone ? 'Đã lập kế hoạch' : 'Chưa lập kế hoạch' }}</small></div></button>
            <button class="emp1-step emx2-process-step {{ $assignmentDone ? 'is-done' : '' }}" type="button" data-emp1-target="assignment" data-emp1-tab="assignment"><span>{{ $assignmentDone ? '✓' : '3' }}</span><div><b>Phân công</b><small>{{ $assignedCount === 0 ? 'Chưa chọn người' : ($assignmentApproved ? ($currentUserAccepted ? 'Đã nhận việc' : 'Chờ nhận việc') : 'Chờ Admin duyệt') }}</small></div></button>
            <button class="emp1-step emx2-process-step {{ $executionDone ? 'is-done' : (!$assignmentDone ? 'is-locked' : '') }}" type="button" data-emp1-target="perform" data-emp1-tab="perform"><span>{{ $executionDone ? '✓' : '4' }}</span><div><b>Thực hiện</b><small>{{ !$assignmentDone ? 'Mở sau phân công' : ($executionStarted ? 'Đang cập nhật' : 'Chưa bắt đầu') }}</small></div></button>
            <button class="emp1-step emx2-process-step {{ $hasIncident ? 'is-done' : '' }}" type="button" data-emp1-target="incident" data-emp1-tab="incident"><span>{{ $hasIncident ? '✓' : '5' }}</span><div><b>Phát sinh</b><small>{{ $hasIncident ? 'Có phát sinh' : 'Chưa ghi nhận' }}</small></div></button>
            <button class="emp1-step emx2-process-step {{ $roundCompleted ? 'is-done' : (!$executionStarted ? 'is-locked' : '') }}" type="button" data-emp1-target="complete" data-emp1-tab="complete"><span>{{ $roundCompleted ? '✓' : '6' }}</span><div><b>Hoàn tất</b><small>{{ $roundCompleted ? 'Đã đóng đợt' : 'Chờ hoàn thành' }}</small></div></button>
        </aside>
        <div class="emp1-content">

    <header class="emr9-workspace-head">
        <div><span>HỒ SƠ TỪNG ĐỢT</span><strong data-emr9-current-title>Tổng quan</strong><small>Đợt {{ $roundNo }}/{{ $totalRounds }} · {{ $schedule->schedule_code ?: '#'.$schedule->id }}</small></div>
        <div class="emr9-head-state"><i></i><span>{{ $statusLabel }}</span></div>
    </header>

    <section class="emd9-workflow" aria-label="Quy trình xử lý">
        
@foreach($workflowSteps as $step)

            <article class="{{ $step['state'] }}" title="{{ $step['actor'] }} · {{ $step['time'] }}">
                <span>{{ $step['state'] === 'done' ? '✓' : $step['number'] }}</span>
                <div><b>{{ $step['label'] }}</b><small>{{ $step['actor'] }}</small><em>{{ $step['time'] }}</em></div>
            </article>
        
@endforeach

    </section>

    <nav class="emd9-tabs emx2-tabs" role="tablist">
        <button class="active" type="button" data-emd9-tab="summary"><i class="bi bi-grid-1x2"></i> Nội dung bước</button>
        <button type="button" data-emd9-tab="documents"><i class="bi bi-folder2-open"></i> Hồ sơ <span>{{ $attachmentCount }}</span></button>
        <button type="button" data-emd9-tab="history"><i class="bi bi-clock-history"></i> Lịch sử</button>
    </nav>

    <section class="emd9-panel active" data-emd9-panel="summary">
        <section class="emd9-card emp1-word-card">
            <div class="emr8-summary-head"><div><span class="emp1-kicker">ĐỢT BẢO TRÌ {{ $roundNo }}/{{ $totalRounds }}</span><h2>Tổng quan hồ sơ từng đợt</h2><p>Một nơi duy nhất để theo dõi kế hoạch, nhân sự, thực hiện, phát sinh và hoàn tất.</p></div><div class="emr8-progress" style="--emr8-progress:{{ $displayProgress }}"><strong>{{ $displayProgress }}%</strong><small>Tiến độ</small></div></div>
            <div class="emr8-state-grid">
                <button type="button" data-emx2-stage="plan" class="{{ $planDone ? 'done' : 'pending' }}"><span>2</span><div><small>Kế hoạch</small><strong>{{ $planDone ? 'Đã xong' : 'Chưa xong' }}</strong></div><i class="bi bi-chevron-right"></i></button>
                <button type="button" data-emx2-stage="assignment" class="{{ $assignmentDone ? 'done' : 'pending' }}"><span>3</span><div><small>Phân công</small><strong>{{ $assignmentDone ? ($currentUserAccepted ? 'Đã nhận việc' : 'Chờ nhận việc') : ($assignedCount ? 'Chờ duyệt' : 'Chưa chọn người') }}</strong></div><i class="bi bi-chevron-right"></i></button>
                <button type="button" data-emx2-stage="perform" class="{{ $executionDone ? 'done' : ($executionStarted ? 'active' : 'pending') }}"><span>4</span><div><small>Thực hiện</small><strong>{{ $executionDone ? 'Đã xong' : ($executionStarted ? 'Đang thực hiện' : 'Chưa bắt đầu') }}</strong></div><i class="bi bi-chevron-right"></i></button>
                <button type="button" data-emx2-stage="incident" class="{{ $hasIncident ? 'warning' : 'neutral' }}"><span>5</span><div><small>Phát sinh</small><strong>{{ $hasIncident ? 'Có phát sinh' : 'Chưa có' }}</strong></div><i class="bi bi-chevron-right"></i></button>
                <button type="button" data-emx2-stage="complete" class="{{ $roundCompleted ? 'done' : 'pending' }}"><span>6</span><div><small>Hoàn tất</small><strong>{{ $roundCompleted ? 'Đã đóng đợt' : 'Chưa hoàn tất' }}</strong></div><i class="bi bi-chevron-right"></i></button>
            </div>
            <dl class="emp1-summary-list emp1-summary-meta emr8-summary-meta">
                <div><dt>Ngày dự kiến</dt><dd>{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: 'Chưa đặt ngày' }}</dd></div>
                <div><dt>Nhóm thực hiện</dt><dd>{{ $schedule->assignee_names ?: 'Chưa phân công' }}</dd></div>
                <div><dt>Phụ trách chính</dt><dd>{{ $leaderName }}</dd></div>
                <div><dt>Trạng thái</dt><dd>{{ $statusLabel }}</dd></div>
            </dl>
        </section>
    </section>

    <section class="emd9-panel" data-emd9-panel="legacy-overview">
        <div class="emd9-main-grid">
            <main>
                <section class="emd9-card">
                    <div class="emd9-card-head"><div><h2>Thông tin công trình &amp; đợt</h2><p>Tổng quan nhanh; cập nhật dữ liệu tại đúng bước ở cột trái.</p></div></div>

                    <div class="emd9-read-view {{ $editMode ? 'hidden' : '' }}" data-emd9-read>
                        <div class="emd9-info-grid">
                            <div class="wide"><small>Công trình</small><strong>{{ $siteName }}</strong><span>{{ $siteAddress }}</span></div>
                            <div><small>Khách hàng</small><strong>{{ $customerName }}</strong></div>
                            <div><small>Loại lịch</small><strong>{{ $types[$schedule->type] ?? $schedule->type }}</strong></div>
                            <div><small>Đợt bảo trì</small><strong>Đợt {{ $roundNo }} / {{ $totalRounds }}</strong></div>
                            <div><small>Ngày dự kiến</small><strong>{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: '—' }}</strong></div>
                            <div><small>Trưởng nhóm</small><strong>{{ $leaderName }}</strong></div>
                            <div><small>Thành viên</small><strong>{{ $schedule->assignee_names }}</strong></div>
                            <div><small>Ưu tiên</small><strong>{{ $priorities[$schedule->priority] ?? $schedule->priority }}</strong></div>
                            <div class="wide"><small>Mô tả công việc</small><p>{{ $schedule->issue_note ?: 'Chưa cập nhật mô tả.' }}</p></div>
                            <div class="wide"><small>Ghi chú kỹ thuật</small><p>{{ $schedule->technical_note ?: 'Chưa có ghi chú.' }}</p></div>
                            <div class="wide"><small>Kết quả xử lý</small><p>{{ $schedule->result_note ?: 'Chưa cập nhật kết quả.' }}</p></div>
                        </div>
                    </div>

@if($permissions['update'])

                    <form class="emd9-edit-form {{ $editMode ? 'active' : '' }}" data-emd9-form method="POST" action="{{ route('projects-unified.maintenance.update', ['schedule'=>$schedule->id]) }}">
                        @csrf @method('PUT')
                        <label><span>Ngày dự kiến</span><input type="date" name="scheduled_date" value="{{ optional($schedule->scheduled_date)->format('Y-m-d') }}" required></label>
                        <label><span>Loại lịch</span><select name="type">
@foreach($types as $key=>$label)
<option value="{{ $key }}" @selected($schedule->type===$key)>{{ $label }}</option>
@endforeach
</select></label>
                        <label><span>Ưu tiên</span><select name="priority">
@foreach($priorities as $key=>$label)
<option value="{{ $key }}" @selected($schedule->priority===$key)>{{ $label }}</option>
@endforeach
</select></label>
                        <label><span>Trưởng nhóm kỹ thuật</span><select name="leader_user_id"><option value="">Chưa chọn</option>
@foreach($technicalUsers as $user)
<option value="{{ $user->id }}" @selected((int)$leaderId===(int)$user->id)>{{ $user->name }}</option>
@endforeach
</select></label>
                        <label class="wide"><span>Thành viên thực hiện</span><select name="member_user_ids[]" multiple size="5">
@foreach($technicalUsers as $user)
<option value="{{ $user->id }}" @selected(in_array((int)$user->id,$memberIds,true))>{{ $user->name }}{{ $user->department?->name ? ' — '.$user->department->name : '' }}</option>
@endforeach
</select><small>Giữ Ctrl để chọn nhiều người.</small></label>
                        <label><span>Công suất kWp</span><input type="number" step="0.01" min="0" name="system_kwp" value="{{ $schedule->system_kwp }}"></label>
                        <label><span>Inverter / thiết bị</span><input name="inverter_info" value="{{ $schedule->inverter_info }}"></label>
                        <label class="wide"><span>Mô tả công việc</span><textarea name="issue_note" rows="3">{{ $schedule->issue_note }}</textarea></label>
                        <label class="wide"><span>Ghi chú kỹ thuật</span><textarea name="technical_note" rows="3">{{ $schedule->technical_note }}</textarea></label>
                        <label class="wide"><span>Kết quả xử lý</span><textarea name="result_note" rows="4">{{ $schedule->result_note }}</textarea></label>
                        <div class="wide emd9-form-actions"><button class="emd9-btn light" type="button" data-emd9-cancel-edit>Hủy</button><button class="emd9-btn primary" type="submit"><i class="bi bi-save"></i> Lưu cập nhật</button></div>
                    </form>
                    
@endif

                </section>

@if($warrantyClaim)

                <section class="emd9-card">
                    <div class="emd9-card-head"><div><h2>Thông tin phiếu bảo hành / sự cố</h2><p>{{ $warrantyClaim->claim_code }} · {{ $warrantyStatuses[$warrantyClaim->status] ?? $warrantyClaim->status }}</p></div></div>
                    <div class="emd9-info-grid compact">
                        <div><small>Serial lỗi</small><strong>{{ $warrantyClaim->serial_code ?: 'Chưa xác định' }}</strong></div>
                        <div><small>Người phụ trách</small><strong>{{ $warrantyClaim->assignee?->name ?: $warrantyClaim->assigned_name ?: 'Chưa phân công' }}</strong></div>
                        <div class="wide"><small>Mô tả sự cố</small><p>{{ $warrantyClaim->issue_description ?: '—' }}</p></div>
                        <div class="wide"><small>Chẩn đoán</small><p>{{ $warrantyClaim->diagnosis ?: 'Chưa cập nhật' }}</p></div>
                        <div class="wide"><small>Phương án</small><p>{{ $warrantyClaim->proposed_solution ?: 'Chưa cập nhật' }}</p></div>
                        <div class="wide"><small>Kết quả</small><p>{{ $warrantyClaim->resolution ?: 'Chưa cập nhật' }}</p></div>
                    </div>
                </section>
                
@endif

            </main>

            <aside>
                <section class="emd9-card emd9-progress-card">
                    <div class="emd9-card-head"><div><h2>Tiến độ xử lý</h2><p>{{ $workSummary['total'] > 0 ? 'Tính theo công việc kỹ thuật' : 'Tính theo quy trình nghiệp vụ' }}</p></div><strong>{{ $displayProgress }}%</strong></div>
                    <div class="emd9-progress"><i style="width:{{ $displayProgress }}%"></i></div>
                    <div class="emd9-kpi-mini">
                        <div><b>{{ $workSummary['total'] }}</b><small>Tổng việc</small></div>
                        <div><b>{{ $workSummary['completed'] }}</b><small>Hoàn thành</small></div>
                        <div><b>{{ $workSummary['in_progress'] }}</b><small>Đang làm</small></div>
                        <div><b>{{ $workSummary['blocked'] }}</b><small>Đang vướng</small></div>
                    </div>
                    <div class="emd9-step-list">
@foreach($workflowSteps as $step)
<div class="{{ $step['state'] }}"><span>{{ $step['state']==='done'?'✓':$step['number'] }}</span><div><b>{{ $step['label'] }}</b><small>{{ $step['actor'] }}</small></div><em>{{ $step['time'] }}</em></div>
@endforeach
</div>
                </section>

                <section class="emd9-card">
                    <div class="emd9-card-head"><h2>SLA &amp; hệ thống</h2></div>
                    <dl class="emd9-meta-list">
                        <div><dt>Mã phiếu</dt><dd>{{ $schedule->schedule_code ?: '#'.$schedule->id }}</dd></div>
                        <div><dt>Người tạo</dt><dd>{{ $schedule->creator?->name ?: 'Hệ thống' }}</dd></div>
                        <div><dt>Ngày tạo</dt><dd>{{ optional($schedule->created_at)->format('d/m/Y H:i') ?: '—' }}</dd></div>
                        <div><dt>Duyệt</dt><dd>{{ $approvalLabel }}</dd></div>
                        <div><dt>Cập nhật cuối</dt><dd>{{ optional($schedule->updated_at)->format('d/m/Y H:i') ?: '—' }}</dd></div>
                        <div><dt>SLA</dt><dd class="{{ $isOverdue ? 'danger' : 'success' }}">{{ $isOverdue ? 'Quá hạn '.$overdueDays.' ngày' : 'Trong hạn' }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </section>

    <section class="emd9-panel" data-emd9-panel="plan">
        <section class="emd9-card emp1-step-card">
            <div class="emd9-card-head"><div><span class="emp1-kicker">KẾ HOẠCH - ĐỢT {{ $roundNo }}/{{ $totalRounds }}</span><h2>Kế hoạch bảo trì / bảo hành</h2><p>Cập nhật trực tiếp tại bước Kế hoạch, không chuyển sang màn hình khác.</p></div></div>
@if($permissions['update'])
            <form class="emp1-form" method="POST" action="{{ route('projects-unified.maintenance.update', ['schedule'=>$schedule->id]) }}">
                @csrf @method('PUT')
                <label><span>Ngày thực hiện <b>*</b></span><input type="date" name="scheduled_date" value="{{ optional($schedule->scheduled_date)->format('Y-m-d') }}" required></label>
                <label><span>Loại công việc</span><select name="type">@foreach($types as $key=>$label)<option value="{{ $key }}" @selected($schedule->type===$key)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Ưu tiên</span><select name="priority">@foreach($priorities as $key=>$label)<option value="{{ $key }}" @selected($schedule->priority===$key)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Công suất kWp</span><input type="number" step="0.01" min="0" name="system_kwp" value="{{ $schedule->system_kwp }}"></label>
                <label class="wide"><span>Thông tin inverter / thiết bị</span><input name="inverter_info" value="{{ $schedule->inverter_info }}" placeholder="Model, công suất, serial nếu cần..."></label>
                <label class="wide"><span>Nội dung / yêu cầu công việc</span><textarea name="issue_note" rows="4">{{ $schedule->issue_note }}</textarea></label>
                <label class="wide"><span>Ghi chú kỹ thuật</span><textarea name="technical_note" rows="3">{{ $schedule->technical_note }}</textarea></label>
                <fieldset class="wide emp1-checklist"><legend>CHECKLIST</legend>
                    <?php $checkedPlan = collect($schedule->plan_checklist ?? []); ?>
                    <label><input type="checkbox" name="plan_checklist[]" value="system" @checked($checkedPlan->contains('system'))> Kiểm tra hệ thống</label>
                    <label><input type="checkbox" name="plan_checklist[]" value="inverter" @checked($checkedPlan->contains('inverter'))> Kiểm tra inverter</label>
                    <label><input type="checkbox" name="plan_checklist[]" value="panels" @checked($checkedPlan->contains('panels'))> Vệ sinh tấm pin</label>
                    <label><input type="checkbox" name="plan_checklist[]" value="electrical" @checked($checkedPlan->contains('electrical'))> Kiểm tra tủ điện</label>
                </fieldset>
                <div class="wide emp1-actions"><button class="emd9-btn primary" type="submit"><i class="bi bi-save"></i> Lưu kế hoạch</button></div>
            </form>
@else
            <div class="emd9-info-grid"><div><small>Ngày thực hiện</small><strong>{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: 'Chưa đặt ngày' }}</strong></div><div><small>Loại công việc</small><strong>{{ $types[$schedule->type] ?? $schedule->type }}</strong></div><div class="wide"><small>Nội dung</small><p>{{ $schedule->issue_note ?: 'Chưa cập nhật.' }}</p></div></div>
@endif
        </section>
    </section>

    <section class="emd9-panel" data-emd9-panel="assignment">
        <section class="emd9-card ema10-card">
            <div class="ema10-heading"><div><span class="emp1-kicker">PHÂN CÔNG · ĐỢT {{ $roundNo }}/{{ $totalRounds }}</span><h2>Nhóm thực hiện</h2><p>Chọn nhân sự, Admin xác nhận, sau đó từng kỹ thuật viên chủ động nhận việc.</p></div>@if($permissions['assignment_manage'])<button class="emd9-btn primary" type="button" data-emx2-open-assignment><i class="bi bi-person-plus"></i> {{ $assignedCount ? 'Chỉnh sửa nhân sự' : 'Thêm nhân sự' }}</button>@endif</div>

            <div class="ema10-flow" aria-label="Quy trình phân công">
                <div class="done"><i class="bi bi-people-fill"></i><span><small>Bước 1</small><strong>Chọn người</strong></span></div>
                <b></b><div class="{{ $assignmentApproved ? 'done' : ($assignedCount ? 'current' : '') }}"><i class="bi bi-shield-check"></i><span><small>Bước 2</small><strong>{{ $assignmentApproved ? 'Admin đã duyệt' : 'Admin duyệt' }}</strong></span></div>
                <b></b><div class="{{ $currentUserAccepted ? 'done' : ($assignmentApproved ? 'current' : '') }}"><i class="bi bi-person-check"></i><span><small>Bước 3</small><strong>Nhận việc</strong></span></div>
            </div>

            <div class="ema10-layout"><div class="ema10-main">
                <section class="ema10-section"><header><div><h3>Nhân sự nội bộ</h3><p>{{ $assignedCount }} người được chọn · 1 người phụ trách chính</p></div></header>
                    <div class="ema10-members">@forelse($schedule->assignees as $assignee)<article><span class="ema10-avatar">{{ mb_strtoupper(mb_substr($assignee->user?->name ?: '?',0,1)) }}</span><div><strong>{{ $assignee->user?->name ?: 'Nhân sự' }}</strong><small>{{ $assignee->user?->department?->name ?: 'Kỹ thuật' }}</small></div><span class="ema10-role {{ (int)$assignee->user_id === (int)$leaderId ? 'lead' : '' }}">{{ (int)$assignee->user_id === (int)$leaderId ? 'Phụ trách chính' : 'Thành viên' }}</span><span class="ema10-accept {{ $assignee->accepted_at ? 'accepted' : '' }}"><i class="bi {{ $assignee->accepted_at ? 'bi-check-circle-fill' : 'bi-clock' }}"></i>{{ $assignee->accepted_at ? 'Đã nhận việc' : ($assignmentApproved ? 'Chờ nhận việc' : 'Chờ duyệt') }}</span></article>@empty<div class="ema10-empty"><i class="bi bi-person-plus"></i><strong>Chưa có nhân sự</strong><span>Chọn nhóm thực hiện để bắt đầu quy trình.</span></div>@endforelse</div>
                </section>

                <details class="ema10-external" @if($schedule->external_labor_enabled) open @endif><summary><span><i class="bi bi-buildings"></i><b>Nhân công ngoài</b><small>{{ $schedule->external_labor_enabled ? ($schedule->external_labor_name ?: 'Đang sử dụng') : 'Không sử dụng' }}</small></span><i class="bi bi-chevron-down"></i></summary><div>
                    @if($permissions['assignment_manage'])<form method="POST" action="{{ route('projects-unified.maintenance.update', ['schedule'=>$schedule->id]) }}" class="ema10-external-form">@csrf @method('PUT')<label class="ema10-switch"><input type="hidden" name="external_labor_enabled" value="0"><input type="checkbox" name="external_labor_enabled" value="1" @checked($schedule->external_labor_enabled)><span></span> Có thuê nhân công ngoài</label><label>Đơn vị / người nhận việc<input name="external_labor_name" value="{{ $schedule->external_labor_name }}" placeholder="Ví dụ: Đội thi công ABC"></label><label>Điện thoại / liên hệ<input name="external_labor_contact" value="{{ $schedule->external_labor_contact }}" placeholder="Số điện thoại"></label>@if($canViewCosts)<label>Chi phí dự kiến<input type="number" min="0" name="external_labor_estimated_cost" value="{{ $schedule->external_labor_estimated_cost }}" placeholder="0"></label>@endif<button class="emd9-btn light" type="submit"><i class="bi bi-save"></i> Lưu thuê ngoài</button></form>@else<div class="ema10-external-read">{{ $schedule->external_labor_enabled ? (($schedule->external_labor_name ?: 'Chưa nhập đơn vị').' · '.($schedule->external_labor_contact ?: 'Chưa có liên hệ')) : 'Đợt này không sử dụng nhân công ngoài.' }}</div>@endif
                </div></details>
            </div><aside class="ema10-approval"><div class="ema10-approval-head"><span class="{{ $assignmentApproved ? 'approved' : 'waiting' }}"><i class="bi {{ $assignmentApproved ? 'bi-patch-check-fill' : 'bi-hourglass-split' }}"></i></span><div><small>TRẠNG THÁI PHÂN CÔNG</small><h3>{{ $assignmentApproved ? 'Đã được Admin duyệt' : ($assignedCount ? 'Chờ Admin duyệt' : 'Chưa chọn nhân sự') }}</h3></div></div>
                <dl><div><dt>Phụ trách chính</dt><dd>{{ $leaderName }}</dd></div><div><dt>Số thành viên</dt><dd>{{ $assignedCount }} người</dd></div><div><dt>Người đã nhận</dt><dd>{{ $schedule->assignees->whereNotNull('accepted_at')->count() }}/{{ $assignedCount }}</dd></div>@if($assignmentApproval)<div><dt>Cập nhật duyệt</dt><dd>{{ optional($assignmentApproval->reviewed_at ?: $assignmentApproval->submitted_at ?: $assignmentApproval->created_at)->format('d/m/Y H:i') }}</dd></div>@endif</dl>
                @if($assignedCount > 0 && !$assignmentApproved && $permissions['assignment_admin'])<form method="POST" action="{{ route('projects-unified.maintenance.assignment.approve', ['schedule'=>$schedule->id]) }}">@csrf<button class="emd9-btn success" type="submit"><i class="bi bi-shield-check"></i> Duyệt phân công</button></form>@endif
                @if($assignmentApproved && $permissions['assignment_accept'] && !$currentUserAccepted && !$roundCompleted)<form method="POST" action="{{ route('projects-unified.maintenance.assignment.accept', ['schedule'=>$schedule->id]) }}">@csrf<button class="emd9-btn primary" type="submit"><i class="bi bi-person-check"></i> Nhận việc</button></form>@endif
                @if($currentUserAccepted)<div class="ema10-confirm"><i class="bi bi-check-circle-fill"></i><span><strong>Bạn đã nhận việc</strong><small>{{ optional($currentAssignment->accepted_at)->format('d/m/Y H:i') }}</small></span></div>@endif
                <p class="ema10-rule"><i class="bi bi-info-circle"></i> Chỉ người đã được duyệt và nhận việc mới được cập nhật bước Thực hiện.</p>
            </aside></div>
        </section>
    </section>

    <section class="emd9-panel" data-emd9-panel="perform">
        <section class="emd9-card eme11-card">
            {{-- LEGACY_CHECKLIST_COMPAT_V1: show migrated checklist rows when V11 work-items do not exist. --}}
            <?php $legacyExecutionItems = ($legacyChecklistItems ?? collect()); ?>
            <?php $useLegacyExecution = $workItems->isEmpty() && $legacyExecutionItems->isNotEmpty(); ?>
            <?php $executionCompleted = $useLegacyExecution ? $legacyExecutionItems->where('is_done', true)->count() : $workItems->where('status','completed')->count(); ?>
            <?php $executionTotal = $useLegacyExecution ? $legacyExecutionItems->count() : $workItems->count(); ?>
            <?php $executionPercent = $executionTotal ? (int) round($executionCompleted / $executionTotal * 100) : 0; ?>
            <header class="eme11-head"><div><span class="emp1-kicker">THỰC HIỆN · ĐỢT {{ $roundNo }}/{{ $totalRounds }}</span><h2>Checklist hiện trường</h2><p>Mở từng hạng mục, ghi kết quả và tải minh chứng ngay tại đúng mục.</p></div><div class="eme11-counter"><strong>{{ $executionCompleted }}/{{ $executionTotal }}</strong><span>hạng mục hoàn thành</span>@if($permissions['assignment_admin'])<button class="emd9-btn light" type="button" data-eme11-settings-open><i class="bi bi-sliders"></i> Cài đặt hồ sơ</button>@endif</div></header>
            <div class="eme11-progress"><i style="width:{{ $executionPercent }}%"></i></div>

            <div class="eme11-list">
            @if($useLegacyExecution)
                @foreach($legacyExecutionItems as $legacyItem)
                    <?php $itemFiles = $legacyItem->attachments ?? collect(); ?>
                    <?php $itemMinimum = max(0, (int)($legacyItem->min_evidence ?? 0)); ?>
                    <?php $itemRequired = (bool)($legacyItem->is_required ?? true); ?>
                    <?php $itemDone = (bool)$legacyItem->is_done; ?>
                    <?php $itemHasFiles = !$legacyItem->requires_evidence || $itemFiles->count() >= $itemMinimum; ?>
                    <details class="eme11-item {{ $itemDone ? 'is-done' : '' }}" @if($loop->first && !$itemDone) open @endif>
                        <summary>
                            <span class="eme11-number">{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</span>
                            <span class="eme11-title"><strong>{{ $legacyItem->label }}</strong><small>{{ $itemRequired ? 'Bắt buộc' : 'Không bắt buộc' }} · {{ $itemFiles->count() }} file · tối thiểu {{ $itemMinimum }} · dữ liệu cũ</small></span>
                            <span class="eme11-state {{ $itemDone ? 'done' : ($itemHasFiles ? 'ready' : 'pending') }}"><i class="bi {{ $itemDone ? 'bi-check-circle-fill' : 'bi-clock' }}"></i>{{ $itemDone ? 'Hoàn thành' : ($itemHasFiles ? 'Đủ minh chứng' : 'Chưa xong') }}</span>
                            <i class="bi bi-chevron-down eme11-chevron"></i>
                        </summary>
                        <div class="eme11-body">
                            <span class="eme11-required">Hạng mục được khôi phục từ checklist cũ #{{ $legacyItem->id }}</span>
                            @if($legacyItem->note)<p class="eme11-read-note">{{ $legacyItem->note }}</p>@endif
                            <div class="eme11-evidence"><div><i class="bi bi-cloud-arrow-up"></i><span><strong>Minh chứng · {{ $itemFiles->count() }} file</strong><small>{{ $legacyItem->requires_evidence ? 'Yêu cầu tối thiểu '.$itemMinimum.' file' : 'Không bắt buộc file' }}</small></span></div>
                                @if($permissions['upload'])
                                <form method="POST" action="{{ route('projects-unified.maintenance.schedule-files.store',['schedule'=>$schedule->id]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="checklist_item_id" value="{{ $legacyItem->id }}"><input type="hidden" name="category" value="during"><label class="emd9-btn light"><i class="bi bi-plus-circle"></i> Thêm ảnh hoặc file<input type="file" name="files[]" multiple required onchange="this.form.submit()"></label></form>
                                @endif
                            </div>
                            @if($itemFiles->isNotEmpty())<div class="eme11-files">@foreach($itemFiles as $attachment)<div><a href="{{ route('projects-unified.maintenance.schedule-files.preview',['attachment'=>$attachment->id]) }}" target="_blank"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($attachment->original_name,48) }}</a>@if($permissions['assignment_admin'] || (int)$attachment->uploaded_by === (int)auth()->id())<form method="POST" action="{{ route('projects-unified.maintenance.schedule-files.destroy',['attachment'=>$attachment->id]) }}" onsubmit="return confirm('Xóa tệp minh chứng này?')">@csrf @method('DELETE')<button type="submit" title="Xóa tệp"><i class="bi bi-trash3"></i></button></form>@endif</div>@endforeach</div>@endif
                        </div>
                    </details>
                @endforeach
            @else
            @forelse($workItems as $workItem)
                <?php $itemConfig = str_starts_with((string)$workItem->description,'__ego_checklist__') ? (json_decode(substr((string)$workItem->description,17),true) ?: []) : []; ?>
                <?php $itemRequired = (bool)($itemConfig['required'] ?? true); ?>
                <?php $itemMinimum = (int)($itemConfig['min'] ?? 1); ?>
                <?php $itemMaximum = (int)($itemConfig['max'] ?? 0); ?>
                <?php $itemFiles = $workItem->attachments; ?>
                <?php $itemHasFiles = !$itemRequired || $itemFiles->count() >= $itemMinimum; ?>
                <?php $itemDone = $workItem->status === 'completed'; ?>
                <details class="eme11-item {{ $itemDone ? 'is-done' : '' }}" @if($loop->first && !$itemDone) open @endif><summary><span class="eme11-number">{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</span><span class="eme11-title"><strong>{{ $workItem->title }}</strong><small>{{ $itemRequired ? 'Bắt buộc' : 'Không bắt buộc' }} · {{ $itemFiles->count() }}{{ $itemMaximum ? '/'.$itemMaximum : '' }} file · tối thiểu {{ $itemMinimum }}</small></span><span class="eme11-state {{ $itemDone ? 'done' : ($itemHasFiles ? 'ready' : 'pending') }}"><i class="bi {{ $itemDone ? 'bi-check-circle-fill' : 'bi-clock' }}"></i>{{ $itemDone ? 'Hoàn thành' : ($itemHasFiles ? 'Đủ minh chứng' : 'Chưa xong') }}</span><i class="bi bi-chevron-down eme11-chevron"></i></summary><div class="eme11-body">
                    @if($itemRequired)<span class="eme11-required">Bắt buộc</span>@endif
                    @if($permissions['update'])<form class="eme11-result" method="POST" action="{{ route('projects-unified.maintenance.work-items.update',['schedule'=>$schedule->id,'workItem'=>$workItem->id]) }}">@csrf @method('PUT')<label>Kết quả / ghi chú kỹ thuật<textarea name="result_note" rows="3" placeholder="Nhập tình trạng, thông số hoặc nội dung đã xử lý...">{{ $workItem->result_note }}</textarea></label><input type="hidden" name="status" value="{{ $itemDone ? 'completed' : 'in_progress' }}"><input type="hidden" name="progress_percent" value="{{ $itemDone ? 100 : 50 }}"><button class="emd9-btn light" type="submit"><i class="bi bi-save"></i> Lưu ghi chú</button></form>@elseif($workItem->result_note)<p class="eme11-read-note">{{ $workItem->result_note }}</p>@endif

                    <div class="eme11-evidence"><div><i class="bi bi-cloud-arrow-up"></i><span><strong>Minh chứng · {{ $itemFiles->count() }} file</strong><small>{{ $itemHasFiles ? 'Đã đủ số file yêu cầu' : 'Cần thêm '.max(0,$itemMinimum-$itemFiles->count()).' file' }}{{ !empty($itemConfig['extensions']) ? ' · '.strtoupper($itemConfig['extensions']) : '' }}</small></span></div>@if($permissions['upload'] && (!$itemMaximum || $itemFiles->count() < $itemMaximum))<form method="POST" action="{{ route('projects-unified.maintenance.schedule-files.store',['schedule'=>$schedule->id]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="work_item_id" value="{{ $workItem->id }}"><input type="hidden" name="category" value="during"><label class="emd9-btn light"><i class="bi bi-plus-circle"></i> Thêm ảnh hoặc file<input type="file" name="files[]" multiple required @if(!empty($itemConfig['extensions'])) accept="{{ collect(explode(',',$itemConfig['extensions']))->map(fn($extension)=>'.'.trim($extension))->implode(',') }}" @endif onchange="this.form.submit()"></label></form>@endif</div>

                    @if($itemFiles->isNotEmpty())<div class="eme11-files">@foreach($itemFiles as $attachment)<div><a href="{{ route('projects-unified.maintenance.schedule-files.preview',['attachment'=>$attachment->id]) }}" target="_blank"><i class="bi bi-paperclip"></i> {{ \Illuminate\Support\Str::limit($attachment->original_name,48) }}</a>@if($permissions['assignment_admin'] || (int)$attachment->uploaded_by === (int)auth()->id())<form method="POST" action="{{ route('projects-unified.maintenance.schedule-files.destroy',['attachment'=>$attachment->id]) }}" onsubmit="return confirm('Xóa tệp minh chứng này?')">@csrf @method('DELETE')<button type="submit" title="Xóa tệp"><i class="bi bi-trash3"></i></button></form>@endif</div>@endforeach</div>@endif

                    @if($permissions['update'] && !$itemDone)<form class="eme11-mark" method="POST" action="{{ route('projects-unified.maintenance.work-items.update',['schedule'=>$schedule->id,'workItem'=>$workItem->id]) }}">@csrf @method('PUT')<input type="hidden" name="status" value="completed"><input type="hidden" name="progress_percent" value="100"><button class="emd9-btn {{ $itemHasFiles ? 'success' : 'light' }}" type="submit" @disabled(!$itemHasFiles)><i class="bi bi-check2-circle"></i> Đánh dấu hoàn thành</button></form>@endif
                </div></details>
            @empty<div class="eme11-empty"><i class="bi bi-card-checklist"></i><strong>Chưa có hạng mục hồ sơ</strong><span>Admin thêm các mục cần thực hiện và quy định file minh chứng.</span>@if($permissions['assignment_admin'])<button class="emd9-btn primary" type="button" data-eme11-settings-open><i class="bi bi-plus-lg"></i> Cài đặt hạng mục</button>@endif</div>@endforelse
            @endif
            </div>

            <footer class="eme11-foot"><div><i class="bi bi-info-circle"></i>{{ $executionTotal && $executionCompleted === $executionTotal ? 'Tất cả hạng mục đã hoàn thành.' : 'Hạng mục bắt buộc chỉ được hoàn thành khi đủ số file quy định.' }}</div><div>@if($permissions['update'])<button class="emd9-btn light" type="button" data-emx2-stage="incident"><i class="bi bi-exclamation-circle"></i> Ghi nhận phát sinh</button><button class="emd9-btn primary" type="button" data-emx2-stage="complete" @disabled(!$executionTotal || $executionCompleted !== $executionTotal)><i class="bi bi-check2-circle"></i> Hoàn tất đợt</button>@endif</div></footer>

            @if($permissions['assignment_admin'])<div class="eme11-modal" data-eme11-settings-modal hidden><div class="eme11-backdrop" data-eme11-settings-close></div><section class="eme11-dialog" role="dialog" aria-modal="true"><header><div><span class="emp1-kicker">CHỈ ADMIN</span><h2>Cài đặt hồ sơ thực hiện</h2><p>Thêm, sửa hoặc xóa hạng mục và quy định số lượng file cần tải.</p></div><button type="button" data-eme11-settings-close><i class="bi bi-x-lg"></i></button></header><div class="eme11-settings-list"><div class="eme11-settings-heading"><span>Tên hồ sơ</span><span>Bắt buộc</span><span>Tối thiểu</span><span>Tối đa</span><span>Định dạng</span><span></span></div>@foreach($workItems as $workItem)<?php $configuration = str_starts_with((string)$workItem->description,'__ego_checklist__') ? (json_decode(substr((string)$workItem->description,17),true) ?: []) : []; ?><div class="eme11-setting-row"><form method="POST" action="{{ route('projects-unified.maintenance.work-items.update',['schedule'=>$schedule->id,'workItem'=>$workItem->id]) }}">@csrf @method('PUT')<input type="hidden" name="checklist_setting" value="1"><input type="text" name="title" value="{{ $workItem->title }}" required><input type="hidden" name="setting_required" value="0"><input type="checkbox" name="setting_required" value="1" @checked((bool)($configuration['required'] ?? true))><input type="number" name="setting_min_files" min="0" max="100" value="{{ (int)($configuration['min'] ?? 1) }}" required><input type="number" name="setting_max_files" min="1" max="100" value="{{ !empty($configuration['max']) ? (int)$configuration['max'] : '' }}" placeholder="∞"><input type="text" name="setting_extensions" value="{{ $configuration['extensions'] ?? 'jpg,jpeg,png,pdf' }}"><button type="submit" title="Lưu"><i class="bi bi-check-lg"></i></button></form><form method="POST" action="{{ route('projects-unified.maintenance.work-items.destroy',['schedule'=>$schedule->id,'workItem'=>$workItem->id]) }}" onsubmit="return confirm('Xóa hạng mục hồ sơ này?')">@csrf @method('DELETE')<button class="eme11-delete" type="submit" title="Xóa"><i class="bi bi-trash3"></i></button></form></div>@endforeach</div><form class="eme11-setting-new" method="POST" action="{{ route('projects-unified.maintenance.work-items.store',['schedule'=>$schedule->id]) }}">@csrf<input type="hidden" name="checklist_setting" value="1"><input type="hidden" name="status" value="pending"><input type="hidden" name="progress_percent" value="0">@if($leaderId)<input type="hidden" name="assignee_id" value="{{ $leaderId }}">@endif<input name="title" placeholder="Ví dụ: Hình ảnh bảo trì, biên bản công trình..." required><input type="hidden" name="setting_required" value="0"><input type="checkbox" name="setting_required" value="1" checked><input type="number" name="setting_min_files" value="1" min="0" max="100" required><input type="number" name="setting_max_files" min="1" max="100" placeholder="∞"><input name="setting_extensions" value="jpg,jpeg,png,pdf"><button class="emd9-btn primary" type="submit"><i class="bi bi-plus-lg"></i> Thêm</button></form><footer><button class="emd9-btn light" type="button" data-eme11-settings-close>Đóng</button></footer></section></div>@endif
        </section>
    </section>

    <section class="emd9-panel" data-emd9-panel="legacy-execution">
        <section class="emd9-card">
            <div class="emd9-card-head"><div><h2>Công việc kỹ thuật</h2><p>Quản lý từng đầu việc, người thực hiện, thời gian, tiến độ và minh chứng.</p></div>
@if($permissions['update'])
<button class="emd9-btn primary" type="button" data-emd9-toggle="#emd9-add-task"><i class="bi bi-plus-lg"></i> Thêm công việc</button>
@endif
</div>

@if($permissions['update'])

            <form id="emd9-add-task" class="emd9-task-form hidden" method="POST" action="{{ route('projects-unified.maintenance.work-items.store', ['schedule'=>$schedule->id]) }}">
                @csrf
                <label class="wide"><span>Tên công việc</span><input name="title" required maxlength="255" placeholder="Ví dụ: Kiểm tra inverter, vệ sinh tấm pin..."></label>
                <label><span>Người thực hiện</span><select name="assignee_id"><option value="">Chưa chọn</option>
@foreach($technicalUsers as $user)
<option value="{{ $user->id }}">{{ $user->name }}</option>
@endforeach
</select></label>
                <label><span>Trạng thái</span><select name="status">
@foreach($workStatuses as $key=>$label)
<option value="{{ $key }}">{{ $label }}</option>
@endforeach
</select></label>
                <label><span>Tiến độ</span><input type="number" name="progress_percent" min="0" max="100" value="0"></label>
                <label><span>Dự kiến (phút)</span><input type="number" name="estimated_minutes" min="0"></label>
                <label class="wide"><span>Mô tả</span><textarea name="description" rows="3"></textarea></label>
                <div class="wide emd9-form-actions"><button class="emd9-btn light" type="button" data-emd9-toggle="#emd9-add-task">Đóng</button><button class="emd9-btn primary" type="submit">Lưu công việc</button></div>
            </form>
            
@endif

            <div class="emd9-task-table-wrap">
                <table class="emd9-task-table">
                    <thead><tr><th>Công việc</th><th>Người thực hiện</th><th>Thời gian</th><th>Tiến độ</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
                    <tbody>
                    
@forelse($workItems as $workItem)

                        <tr>
                            <td data-label="Công việc"><strong>{{ $workItem->title }}</strong><small>{{ $workItem->description ?: 'Không có mô tả' }}</small>
@if($workItem->result_note)
<em>Kết quả: {{ $workItem->result_note }}</em>
@endif
</td>
                            <td data-label="Người thực hiện">{{ $workItem->assignee?->name ?: 'Chưa phân công' }}</td>
                            <td data-label="Thời gian"><span>{{ optional($workItem->started_at)->format('d/m H:i') ?: '—' }} → {{ optional($workItem->completed_at)->format('d/m H:i') ?: '—' }}</span><small>{{ $workItem->actual_minutes ? $workItem->actual_minutes.' phút' : 'Chưa ghi nhận' }}</small></td>
                            <td data-label="Tiến độ"><div class="emd9-inline-progress"><i style="width:{{ $workItem->progress_percent }}%"></i></div><b>{{ $workItem->progress_percent }}%</b></td>
                            <td data-label="Trạng thái"><span class="emd9-status work-{{ $workItem->status }}">{{ $workStatuses[$workItem->status] ?? $workItem->status }}</span></td>
                            <td data-label="Thao tác"><button class="emd9-icon-btn" type="button" data-emd9-toggle="#task-{{ $workItem->id }}"><i class="bi bi-pencil-square"></i></button></td>
                        </tr>
                        <tr id="task-{{ $workItem->id }}" class="emd9-task-editor hidden"><td colspan="6">
                            
@if($permissions['update'])

                            <form class="emd9-task-form" method="POST" action="{{ route('projects-unified.maintenance.work-items.update', ['schedule'=>$schedule->id,'workItem'=>$workItem->id]) }}">
                                @csrf @method('PUT')
                                <label class="wide"><span>Tên công việc</span><input name="title" value="{{ $workItem->title }}" required></label>
                                <label><span>Người thực hiện</span><select name="assignee_id"><option value="">Chưa chọn</option>
@foreach($technicalUsers as $user)
<option value="{{ $user->id }}" @selected((int)$workItem->assignee_id===(int)$user->id)>{{ $user->name }}</option>
@endforeach
</select></label>
                                <label><span>Trạng thái</span><select name="status">
@foreach($workStatuses as $key=>$label)
<option value="{{ $key }}" @selected($workItem->status===$key)>{{ $label }}</option>
@endforeach
</select></label>
                                <label><span>Tiến độ (%)</span><input type="number" name="progress_percent" min="0" max="100" value="{{ $workItem->progress_percent }}"></label>
                                <label><span>Thực tế (phút)</span><input type="number" name="actual_minutes" min="0" value="{{ $workItem->actual_minutes }}"></label>
                                <label><span>Bắt đầu</span><input type="datetime-local" name="started_at" value="{{ optional($workItem->started_at)->format('Y-m-d\TH:i') }}"></label>
                                <label><span>Kết thúc</span><input type="datetime-local" name="completed_at" value="{{ optional($workItem->completed_at)->format('Y-m-d\TH:i') }}"></label>
                                <label class="wide"><span>Mô tả</span><textarea name="description" rows="2">{{ $workItem->description }}</textarea></label>
                                <label class="wide"><span>Kết quả</span><textarea name="result_note" rows="3">{{ $workItem->result_note }}</textarea></label>
                                <div class="wide emd9-form-actions"><button class="emd9-btn light" type="button" data-emd9-toggle="#task-{{ $workItem->id }}">Đóng</button><button class="emd9-btn primary" type="submit">Lưu thay đổi</button></div>
                            </form>
                            <form class="emd9-delete-inline" method="POST" action="{{ route('projects-unified.maintenance.work-items.destroy', ['schedule'=>$schedule->id,'workItem'=>$workItem->id]) }}" onsubmit="return confirm('Xóa công việc này?')">@csrf @method('DELETE')<button type="submit"><i class="bi bi-trash3"></i> Xóa công việc</button></form>
                            
@endif

@if($permissions['upload'])

                            <form class="emd9-quick-upload" method="POST" action="{{ route('projects-unified.maintenance.schedule-files.store', ['schedule'=>$schedule->id]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="work_item_id" value="{{ $workItem->id }}"><input type="hidden" name="category" value="during"><input type="file" name="files[]" multiple required><input name="description" placeholder="Ghi chú minh chứng"><button class="emd9-btn light" type="submit"><i class="bi bi-paperclip"></i> Tải minh chứng</button></form>
                            
@endif

                        </td></tr>
                    
@empty

                        <tr><td colspan="6"><div class="emd9-empty"><i class="bi bi-list-check"></i><b>Chưa có công việc kỹ thuật</b><p>Thêm từng đầu việc để theo dõi đúng người, đúng tiến độ và đúng hồ sơ.</p></div></td></tr>
                    
@endforelse

                    </tbody>
                </table>
            </div>
        </section>
    </section>

    <section class="emd9-panel" data-emd9-panel="incident">
        @php
            $incidentFiles = $schedule->attachments->where('category', 'fault');
            $maintenanceProposalItems = $maintenanceProposalItems ?? collect();
            $maintenanceProposalStatuses = [
                'SUBMITTED' => 'Chờ Admin duyệt',
                'PENDING' => 'Chờ Admin duyệt',
                'NEEDS_REVISION' => 'Cần chỉnh sửa',
                'ADMIN_APPROVED' => 'Đã duyệt · Chờ Kho',
                'APPROVED' => 'Đã duyệt · Chờ Kho',
                'PARTIALLY_ALLOCATED' => 'Kho đang soạn',
                'WAREHOUSE_ALLOCATED' => 'Kho đã soạn',
                'READY_FOR_EXPORT' => 'Đã chuyển Kho',
                'EXPORTED' => 'Đã xuất kho',
                'REJECTED' => 'Đã từ chối',
            ];
            $maintenanceProposalTone = fn ($status) => match (strtoupper((string) $status)) {
                'SUBMITTED', 'PENDING' => 'warning',
                'NEEDS_REVISION', 'REJECTED' => 'danger',
                'EXPORTED' => 'success',
                default => 'success',
            };
            $maintenanceQty = fn ($quantity) => rtrim(rtrim(number_format((float) $quantity, 2, ',', '.'), '0'), ',');
        @endphp
        <section class="emd9-card emp1-step-card emi12-card">
            <div class="emi12-head"><div><span>PHÁT SINH · ĐỢT {{ $roundNo }}/{{ $totalRounds }}</span><h2>Phát sinh &amp; đề xuất vật tư</h2></div><span class="emi12-state" data-emi12-state>{{ $hasIncident ? 'Có phát sinh' : 'Không phát sinh' }}</span></div>
@if($permissions['update'])
            <form class="emi12-form" method="POST" action="{{ route('projects-unified.maintenance.update', ['schedule'=>$schedule->id]) }}">@csrf @method('PUT')
                <div class="emi12-toggle-row"><div><b>Có phát sinh?</b><small>Tắt nếu đợt này không có lỗi hoặc vật tư cần xử lý.</small></div><label class="emi12-switch"><input type="checkbox" value="1" @checked($hasIncident) data-emi12-incident-toggle><span></span></label></div>
                <input type="hidden" name="incident_kind" value="none" data-emi12-incident-none @disabled($hasIncident)>
                <div class="emi12-fields" data-emi12-incident-fields @if(! $hasIncident) hidden @endif>
                    <label><span>Loại xử lý</span><select name="incident_kind" data-emi12-incident-kind @disabled(! $hasIncident)><option value="warranty_free" @selected($schedule->incident_kind==='warranty_free')>Bảo hành miễn phí</option><option value="maintenance_free" @selected($schedule->incident_kind==='maintenance_free')>Bảo trì miễn phí</option><option value="warranty_paid" @selected($schedule->incident_kind==='warranty_paid')>Bảo hành có tính phí</option><option value="maintenance_paid" @selected($schedule->incident_kind==='maintenance_paid')>Bảo trì có tính phí</option></select></label>
                    @if($canViewCosts)<label><span>Chi phí dự kiến</span><div class="emi12-money"><input type="number" min="0" step="1000" name="incident_estimated_cost" value="{{ $schedule->incident_estimated_cost }}"><i>đ</i></div></label>@endif
                    <label class="wide"><span>Mô tả hư hỏng / hướng xử lý</span><textarea name="incident_replacement_reason" rows="3" placeholder="Nêu hiện trạng, nguyên nhân và phương án xử lý...">{{ $schedule->incident_replacement_reason }}</textarea></label>
                    <input type="hidden" name="incident_material_note" value="{{ $schedule->incident_material_note }}">
                    <div class="wide emi12-tools">
                        <div><i class="bi bi-paperclip"></i><b>{{ $incidentFiles->count() }} minh chứng</b><small>Ảnh lỗi, PDF</small></div>
                        @if($permissions['upload'])<label class="emi12-file"><input type="file" name="emi12_unused" data-emi12-upload-picker accept=".jpg,.jpeg,.png,.webp,.pdf">+ Thêm file</label>@endif
                        <div><i class="bi bi-box-seam"></i><b>{{ $maintenanceProposals->count() }} đề xuất vật tư</b><small>Admin duyệt trước khi chuyển Kho</small></div>
                        @if($schedule->site_id)<button type="button" class="emd9-btn light" data-emi12-proposal-open><i class="bi bi-plus-lg"></i> Tạo đề xuất</button>@endif
                    </div>
                    <div class="wide emi12-actions"><button class="emd9-btn primary" type="submit"><i class="bi bi-save"></i> Lưu phát sinh</button></div>
                </div>
            </form>
@endif
@if($permissions['upload'])<form hidden data-emi12-upload-form method="POST" action="{{ route('projects-unified.maintenance.schedule-files.store', ['schedule'=>$schedule->id]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="category" value="fault"><input type="file" name="files[]" data-emi12-upload-target accept=".jpg,.jpeg,.png,.webp,.pdf"><input type="hidden" name="description" value="Hình ảnh lỗi / phát sinh"></form>@endif
@if($permissions['update'] && $schedule->site_id)
<div class="emi12-modal emi13-modal" data-emi12-proposal-modal hidden>
    <div class="emi12-backdrop" data-emi12-proposal-close></div>

    <section>
        <header>
            <div>
                <span>ĐỀ XUẤT VẬT TƯ</span>
                <h3>Tạo đề xuất mới</h3>
            </div>

            <button type="button" data-emi12-proposal-close>×</button>
        </header>

        <form class="emi13-form" method="POST" action="{{ route('projects-unified.materials.proposal.store', ['site' => $schedule->site_id]) }}">
            @csrf

            <input type="hidden" name="proposal_type" value="REPLACEMENT">

            <input
                type="hidden"
                name="warranty_scope"
                value="{{ in_array($schedule->incident_kind, ['warranty_paid', 'maintenance_paid'], true) ? 'out_of_scope' : 'pending_assessment' }}"
                data-emi12-warranty-scope
            >

            <input
                type="hidden"
                name="purpose"
                value="[Bảo trì {{ $schedule->schedule_code }}] Đề xuất vật tư phát sinh"
            >

            <div class="emi13-import">
                <div>
                    <strong>Nhập tay hoặc tải file Excel</strong>
                    <small>Chỉ cần ba cột: Tên vật tư, Số lượng, Ghi chú.</small>
                </div>

                <div class="emi13-import-actions">
                    <button type="button" class="emi13-secondary" data-emi13-template>
                        ↓ File mẫu
                    </button>

                    <label class="emi13-secondary">
                        Nhập Excel
                        <input
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            data-emi13-import
                            hidden
                        >
                    </label>
                </div>
            </div>

            <div class="emi13-table-wrap">
                <table class="emi13-table">
                    <thead>
                        <tr>
                            <th>Tên vật tư</th>
                            <th>Số lượng</th>
                            <th>Ghi chú</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody data-emi13-rows></tbody>
                </table>
            </div>

            <button type="button" class="emi13-add" data-emi13-add>
                + Thêm vật tư
            </button>

            <footer>
                <small>
                    <b data-emi13-count>1</b> vật tư · Admin duyệt xong mới chuyển Kho.
                </small>

                <button class="emd9-btn primary" type="submit">
                    <i class="bi bi-send"></i>
                    Gửi Admin duyệt
                </button>
            </footer>
        </form>
    </section>
</div>
@endif
@if($warrantyClaim)
            <div class="emd9-info-grid">
                <div><small>Mã phiếu</small><strong>{{ $warrantyClaim->claim_code }}</strong></div>
                <div><small>Trạng thái</small><strong>{{ $warrantyStatuses[$warrantyClaim->status] ?? $warrantyClaim->status }}</strong></div>
                <div><small>Serial lỗi</small><strong>{{ $warrantyClaim->serial_code ?: 'Chưa xác định' }}</strong></div>
                <div><small>Hình thức</small><strong>{{ $warrantyClaim->is_chargeable ? 'Có tính phí' : 'Miễn phí / bảo hành' }}</strong></div>
                <div class="wide"><small>Lỗi / hư hỏng phát hiện</small><p>{{ $warrantyClaim->issue_description ?: 'Chưa ghi nhận phát sinh.' }}</p></div>
                <div class="wide"><small>Phương án xử lý</small><p>{{ $warrantyClaim->proposed_solution ?: 'Chưa cập nhật phương án.' }}</p></div>
            </div>
            @if($canViewCosts)<div class="emp1-costs"><span>Dự kiến <b>{{ number_format((float)$warrantyClaim->estimated_cost,0,',','.') }} đ</b></span><span>Thực tế <b>{{ number_format((float)$warrantyClaim->actual_cost,0,',','.') }} đ</b></span><span>Phiếu kho <b>{{ $warrantyClaim->stockMovements->count() }}</b></span></div>@endif
@if($canUpdateWarranty)
            <form class="emp1-form emp1-incident-form" method="POST" action="{{ route('projects-unified.maintenance.claims.status', ['claim'=>$warrantyClaim->id]) }}">@csrf
                <label><span>Trạng thái xử lý</span><select name="status"><?php $allowedClaimStatuses = array_unique(array_merge([$warrantyClaim->status], $warrantyTransitions[$warrantyClaim->status] ?? [])); ?> @foreach($allowedClaimStatuses as $claimStatus)<option value="{{ $claimStatus }}" @selected($claimStatus===$warrantyClaim->status)>{{ $warrantyStatuses[$claimStatus] ?? $claimStatus }}</option>@endforeach</select></label>
                <label class="wide"><span>Chẩn đoán</span><textarea name="diagnosis" rows="3">{{ $warrantyClaim->diagnosis }}</textarea></label>
                <label class="wide"><span>Phương án xử lý</span><textarea name="proposed_solution" rows="3">{{ $warrantyClaim->proposed_solution }}</textarea></label>
                <label class="wide"><span>Kết quả xử lý</span><textarea name="resolution" rows="3">{{ $warrantyClaim->resolution }}</textarea></label>
@if($canViewCosts)<label><span>Chi phí thực tế</span><input type="number" min="0" step="0.01" name="actual_cost" value="{{ $warrantyClaim->actual_cost }}"></label>@endif
                <div class="wide emp1-actions"><button class="emd9-btn primary" type="submit"><i class="bi bi-save"></i> Lưu phát sinh</button></div>
            </form>
@endif
@endif
            <?php if ($maintenanceProposals->isNotEmpty()): ?>
                <section class="emi14-history">
                    <header class="emi14-history-head">
                        <div>
                            <h3>Đề xuất vật tư đã gửi</h3>
                            <small>Bấm Xem / Duyệt để mở chi tiết ngay tại trang này.</small>
                        </div>
                        <span>{{ $maintenanceProposals->count() }} phiếu</span>
                    </header>

                    <div class="emi14-table-wrap">
                        <table class="emi14-table">
                            <thead>
                                <tr>
                                    <th>Phiếu / Người đề xuất</th>
                                    <th>Vật tư</th>
                                    <th>Ghi chú bảo hành / bảo trì</th>
                                    <th>Trạng thái</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($maintenanceProposals as $proposal): ?>
                                    <?php
                                        $proposalStatus = strtoupper((string) $proposal->status);
                                        $proposalItems = $maintenanceProposalItems[$proposal->id] ?? collect();
                                        $proposalNote = $proposal->note ?: $schedule->incident_replacement_reason;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong>DX-{{ str_pad((string) $proposal->id, 5, '0', STR_PAD_LEFT) }}</strong>
                                            <small>{{ $proposal->creator_name ?: 'Người lập' }} · {{ \Illuminate\Support\Carbon::parse($proposal->created_at)->format('d/m/Y H:i') }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $proposalItems->count() }} dòng</strong>
                                            <small>{{ $maintenanceQty($proposalItems->sum('requested_qty')) }} tổng số lượng</small>
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit($proposalNote ?: '—', 65) }}</td>
                                        <td><span class="emi14-status {{ $maintenanceProposalTone($proposalStatus) }}">{{ $maintenanceProposalStatuses[$proposalStatus] ?? $proposalStatus }}</span></td>
                                        <td class="emi14-actions"><button type="button" class="emi14-view" data-emi14-open="proposal-{{ $proposal->id }}">Xem / Duyệt <i class="bi bi-chevron-right"></i></button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php foreach ($maintenanceProposals as $proposal): ?>
                    <?php
                        $proposalStatus = strtoupper((string) $proposal->status);
                        $proposalItems = $maintenanceProposalItems[$proposal->id] ?? collect();
                        $linkedMaterialRequestId = $proposalItems->pluck('material_request_id')->filter()->first();
                        $proposalNote = $proposal->note ?: $schedule->incident_replacement_reason;
                        $incidentKindLabels = [
                            'warranty_free' => 'Bảo hành miễn phí',
                            'maintenance_free' => 'Bảo trì miễn phí',
                            'warranty_paid' => 'Bảo hành có tính phí',
                            'maintenance_paid' => 'Bảo trì có tính phí',
                        ];
                    ?>
                    <div class="emi14-detail-modal" data-emi14-modal="proposal-{{ $proposal->id }}" hidden>
                        <div class="emi14-backdrop" data-emi14-close></div>
                        <section class="emi14-detail-card" role="dialog" aria-modal="true" aria-labelledby="emi14-title-{{ $proposal->id }}">
                            <header>
                                <div><small>CHI TIẾT ĐỀ XUẤT</small><h3 id="emi14-title-{{ $proposal->id }}">DX-{{ str_pad((string) $proposal->id, 5, '0', STR_PAD_LEFT) }}</h3></div>
                                <button type="button" class="emi14-close" data-emi14-close aria-label="Đóng">×</button>
                            </header>

                            <div class="emi14-detail-meta">
                                <div><small>Người đề xuất</small><strong>{{ $proposal->creator_name ?: 'Người lập' }}</strong></div>
                                <div><small>Loại xử lý</small><strong>{{ $incidentKindLabels[$schedule->incident_kind] ?? 'Bảo trì / Bảo hành' }}</strong></div>
                                <div><small>Trạng thái</small><strong>{{ $maintenanceProposalStatuses[$proposalStatus] ?? $proposalStatus }}</strong></div>
                            </div>

                            <div class="emi14-detail-body">
                                <table class="emi14-detail-table">
                                    <thead><tr><th>Tên vật tư đề xuất</th><th>Số lượng</th><th>Ghi chú</th><th>Sản phẩm Kho đã chọn</th></tr></thead>
                                    <tbody>
                                        <?php if ($proposalItems->isNotEmpty()): ?>
                                        <?php foreach ($proposalItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <strong>{{ $item->requested_name }}</strong>
                                                    <?php if ($item->requested_spec): ?>
                                                        <small>{{ $item->requested_spec }}</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>{{ $maintenanceQty($item->requested_qty) }} {{ $item->requested_unit ?: '' }}</td>
                                                <td>{{ $item->request_note ?: '—' }}</td>
                                                <td>
                                                    <?php if (data_get($item, 'selected_product_name')): ?>
                                                        <strong>{{ data_get($item, 'selected_product_name') }}</strong>
                                                        <small>{{ data_get($item, 'selected_warehouse_name') ?: 'Kho đã ghép' }}</small>
                                                    <?php elseif ($linkedMaterialRequestId): ?>
                                                        <span>Kho chưa ghép sản phẩm</span>
                                                    <?php else: ?>
                                                        <span>Chưa chuyển Kho</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4">Phiếu chưa có dòng vật tư.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>

                                <div class="emi14-warranty-note"><b>Ghi chú bảo hành / bảo trì</b><p>{{ $proposalNote ?: 'Chưa có ghi chú.' }}</p></div>
                                <?php if ($proposalStatus === 'NEEDS_REVISION' && $proposal->approval_note): ?>
                                    <div class="emi14-review-note"><i class="bi bi-exclamation-circle"></i> {{ $proposal->approval_note }}</div>
                                <?php endif; ?>
                            </div>

                            <?php if (($permissions['assignment_admin'] ?? false) && in_array($proposalStatus, ['SUBMITTED', 'PENDING', 'NEEDS_REVISION'], true)): ?>
                                <footer class="emi14-review">
                                    <form class="emi14-return-form" method="POST" action="{{ route('projects-unified.materials.proposal.return', ['site' => $schedule->site_id, 'proposal' => $proposal->id]) }}">
                                        @csrf
                                        <input type="hidden" name="return_to" value="maintenance">
                                        <input name="revision_note" required maxlength="2000" placeholder="Lý do trả chỉnh sửa...">
                                        <button class="emi14-return" type="submit">Trả chỉnh sửa</button>
                                    </form>
                                    <form method="POST" action="{{ route('projects-unified.materials.proposal.approve', ['site' => $schedule->site_id, 'proposal' => $proposal->id]) }}">
                                        @csrf
                                        <input type="hidden" name="return_to" value="maintenance">
                                        <input type="hidden" name="approval_note" value="Duyệt từ hồ sơ {{ $schedule->schedule_code }}">
                                        <button class="emi14-approve" type="submit"><i class="bi bi-check2-circle"></i> Duyệt &amp; chuyển Kho</button>
                                    </form>
                                </footer>
                            <?php elseif ($linkedMaterialRequestId && Route::has('material-requests.show')): ?>
                                <footer class="emi14-review simple"><a class="emi14-neutral" href="{{ route('material-requests.show', $linkedMaterialRequestId) }}"><i class="bi bi-box-seam"></i> Xem phiếu Kho #{{ $linkedMaterialRequestId }}</a></footer>
                            <?php else: ?>
                                <footer class="emi14-review simple"><button type="button" class="emi14-neutral" data-emi14-close>Đóng</button></footer>
                            <?php endif; ?>
                        </section>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </section>

    <section class="emd9-panel" data-emd9-panel="complete">
        <section class="emd9-card emp1-step-card">
            <div class="emd9-card-head"><div><span class="emp1-kicker">HOÀN TẤT - ĐỢT {{ $roundNo }}/{{ $totalRounds }}</span><h2>{{ $roundCompleted ? 'Đợt đã hoàn thành' : 'Hoàn tất đợt bảo trì' }}</h2><p>{{ $roundCompleted ? 'Checklist và minh chứng đã được lưu vào hồ sơ công trình.' : 'Chốt kết quả và gửi duyệt ngay tại bước hoàn tất.' }}</p></div><span class="emd9-badge {{ $statusTone }}">{{ $statusLabel }}</span></div>
            <div class="emd9-info-grid">
                <div><small>Trạng thái đợt</small><strong>{{ $statusLabel }}</strong></div>
                <div><small>Trạng thái duyệt</small><strong>{{ $approvalLabel }}</strong></div>
                <div><small>Tiến độ xử lý</small><strong>{{ $displayProgress }}%</strong></div>
                <div><small>Hồ sơ đính kèm</small><strong>{{ $schedule->attachments->count() }} file</strong></div>
                <div class="wide"><small>Kết quả cuối cùng</small><p>{{ $schedule->result_note ?: 'Chưa cập nhật kết quả cuối cùng.' }}</p></div>
                <div class="wide"><small>Phản hồi duyệt</small><p>{{ $schedule->approval_note ?: 'Chưa có phản hồi.' }}</p></div>
            </div>
@if($permissions['update'])
            <form class="emp1-form emp1-result-form" method="POST" action="{{ route('projects-unified.maintenance.update', ['schedule'=>$schedule->id]) }}">@csrf @method('PUT')
                <label class="wide"><span>Kết quả cuối cùng</span><textarea name="result_note" rows="5">{{ $schedule->result_note }}</textarea></label>
                @if($canViewCosts)<label><span>Chi phí thực tế (VNĐ)</span><input type="number" min="0" step="1000" name="completion_actual_cost" value="{{ $schedule->completion_actual_cost }}"></label>@endif
                <fieldset class="wide emp1-radio-group"><legend>XÁC NHẬN</legend><label><input type="radio" name="completion_state" value="completed" @checked($schedule->completion_state==='completed')> Hoàn thành đợt</label><label><input type="radio" name="completion_state" value="needs_followup" @checked($schedule->completion_state==='needs_followup')> Chưa hoàn thành / cần xử lý tiếp</label></fieldset>
                <div class="wide emp1-actions"><button class="emd9-btn light" type="submit"><i class="bi bi-save"></i> Lưu kết quả</button></div>
            </form>
@endif
@if($permissions['upload'])
            <form class="emp1-upload" method="POST" action="{{ route('projects-unified.maintenance.schedule-files.store', ['schedule'=>$schedule->id]) }}" enctype="multipart/form-data">@csrf<input type="hidden" name="category" value="report"><input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4"><input name="description" placeholder="Ghi chú hồ sơ hoàn thành"><button class="emd9-btn light" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải hồ sơ</button></form>
@endif
            <div class="emp1-complete-actions" id="emp1-complete-actions">
@if($permissions['submit'] && in_array($schedule->status,['in_progress','waiting_material','waiting_submission','revision_requested'],true))<form method="POST" action="{{ route('projects-unified.maintenance.approval.submit', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="comment" rows="2" placeholder="Ghi chú khi gửi duyệt"></textarea><button class="emd9-btn primary" type="submit"><i class="bi bi-send-check"></i> Gửi duyệt</button></form>@endif
@if($permissions['approve'] && $schedule->status==='pending_approval')<form method="POST" action="{{ route('projects-unified.maintenance.approval.approve', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="comment" rows="2" placeholder="Nhận xét phê duyệt"></textarea><button class="emd9-btn success" type="submit"><i class="bi bi-patch-check"></i> Phê duyệt</button></form>@endif
@if($permissions['approve'] && $schedule->status==='approved')<form method="POST" action="{{ route('projects-unified.maintenance.approval.complete', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="comment" rows="2" placeholder="Ghi chú đóng hồ sơ"></textarea><button class="emd9-btn success" type="submit"><i class="bi bi-check2-circle"></i> Xác nhận hoàn thành đợt</button></form>@endif
            </div>
        </section>
    </section>

    <section class="emd9-panel" data-emd9-panel="documents">
        <div class="emd9-doc-grid">
            <main>
                <section class="emd9-card">
                    <div class="emd9-card-head"><div><h2>Tài liệu đính kèm</h2><p>Ảnh trước, trong, sau xử lý; serial; biên bản; PDF; Excel và video.</p></div><strong>{{ $schedule->attachments->count() }} file</strong></div>
                    
@if($permissions['upload'])

                    <form class="emd9-upload" method="POST" action="{{ route('projects-unified.maintenance.schedule-files.store', ['schedule'=>$schedule->id]) }}" enctype="multipart/form-data">
                        @csrf
                        <select name="category" required><option value="before">Trước xử lý</option><option value="during">Trong xử lý</option><option value="after">Sau xử lý</option><option value="fault">Thiết bị lỗi</option><option value="serial">Ảnh serial</option><option value="report">Biên bản</option><option value="video">Video</option><option value="other">Khác</option></select>
                        <select name="work_item_id"><option value="">Hồ sơ chung của đợt</option>
@foreach($workItems as $item)
<option value="{{ $item->id }}">{{ $item->title }}</option>
@endforeach
</select>
                        <input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4">
                        <input name="description" placeholder="Ghi chú nhóm file">
                        <button class="emd9-btn primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button>
                    </form>
                    
@endif

                    <div class="emd9-files">
                        
@forelse($schedule->attachments as $attachment)

                        <article>
                            <span class="emd9-file-icon"><i class="bi bi-file-earmark"></i></span>
                            <div><b>{{ mb_strimwidth($attachment->original_name, 0, 60, '…') }}</b><small>{{ strtoupper($attachment->category) }} · {{ number_format($attachment->file_size/1024,1) }} KB · {{ $attachment->uploader?->name ?: 'Hệ thống' }} · {{ optional($attachment->created_at)->format('d/m/Y H:i') }}</small>
@if($attachment->workItem)
<em>Công việc: {{ $attachment->workItem->title }}</em>
@endif
</div>
                            <a href="{{ route('projects-unified.maintenance.schedule-files.preview', ['attachment'=>$attachment->id]) }}" target="_blank" title="Xem trước"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('projects-unified.maintenance.schedule-files.download', ['attachment'=>$attachment->id]) }}" title="Tải xuống"><i class="bi bi-download"></i></a>
                            
@if($permissions['upload'])
<form method="POST" action="{{ route('projects-unified.maintenance.schedule-files.destroy', ['attachment'=>$attachment->id]) }}" onsubmit="return confirm('Xóa file này?')">@csrf @method('DELETE')<button type="submit" title="Xóa"><i class="bi bi-trash3"></i></button></form>
@endif

                        </article>
                        
@empty
<div class="emd9-empty"><i class="bi bi-folder2-open"></i><b>Chưa có tài liệu</b></div>
@endforelse

                    </div>
                </section>

                <section class="emd9-card">
                    <div class="emd9-card-head"><div><h2>Bình luận nội bộ</h2><p>Trao đổi giữa Giám đốc, Trưởng phòng, Kỹ thuật, Kho và Kế toán.</p></div></div>
                    
@if($permissions['comment'])

                    <form class="emd9-comment-form" method="POST" action="{{ route('projects-unified.maintenance.comments.store', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="body" rows="3" required maxlength="5000" placeholder="Nhập bình luận, có thể ghi @Tên người cần lưu ý..."></textarea><button class="emd9-btn primary" type="submit"><i class="bi bi-send"></i> Gửi bình luận</button></form>
                    
@endif

                    <div class="emd9-comments">
                        
@forelse($comments as $comment)

                        <article><span>{{ mb_substr($comment->user?->name ?: '?',0,1) }}</span><div><b>{{ $comment->user?->name ?: 'Người dùng đã xóa' }}</b><small>{{ optional($comment->created_at)->format('d/m/Y H:i') }}</small><p>{{ $comment->body }}</p></div>
@if((int)$comment->user_id===(int)auth()->id() || $permissions['manager'])
<form method="POST" action="{{ route('projects-unified.maintenance.comments.destroy', ['schedule'=>$schedule->id,'comment'=>$comment->id]) }}" onsubmit="return confirm('Xóa bình luận này?')">@csrf @method('DELETE')<button><i class="bi bi-trash3"></i></button></form>
@endif
</article>
                        
@empty
<div class="emd9-empty compact">Chưa có bình luận nội bộ.</div>
@endforelse

                    </div>
                </section>
            </main>

            <aside>
                <section class="emd9-card" id="emd9-approval-actions">
                    <div class="emd9-card-head"><div><h2>Ghi chú &amp; phê duyệt</h2><p>{{ $approvalLabel }}</p></div></div>
                    <div class="emd9-note"><b>Ghi chú kỹ thuật</b><p>{{ $schedule->technical_note ?: 'Chưa có ghi chú.' }}</p></div>
                    <div class="emd9-note"><b>Kết quả xử lý</b><p>{{ $schedule->result_note ?: 'Chưa cập nhật kết quả.' }}</p></div>
                    <div class="emd9-note"><b>Phản hồi duyệt</b><p>{{ $schedule->approval_note ?: 'Chưa có phản hồi.' }}</p></div>
                    <div class="emd9-approval-actions">
                        
@if($permissions['submit'] && in_array($schedule->status,['in_progress','waiting_material','waiting_submission','revision_requested'],true))
<form method="POST" action="{{ route('projects-unified.maintenance.approval.submit', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="comment" rows="2" placeholder="Ghi chú khi gửi duyệt"></textarea><button class="emd9-btn primary" type="submit"><i class="bi bi-send-check"></i> Gửi duyệt</button></form>
@endif

@if($permissions['approve'] && $schedule->status==='pending_approval')
<form method="POST" action="{{ route('projects-unified.maintenance.approval.approve', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="comment" rows="2" placeholder="Nhận xét phê duyệt"></textarea><button class="emd9-btn success" type="submit"><i class="bi bi-patch-check"></i> Phê duyệt</button></form>
@endif

@if($permissions['revision'] && $schedule->status==='pending_approval')
<form method="POST" action="{{ route('projects-unified.maintenance.approval.revision', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="comment" rows="2" required placeholder="Nội dung cần chỉnh sửa"></textarea><button class="emd9-btn warning" type="submit"><i class="bi bi-arrow-return-left"></i> Yêu cầu chỉnh sửa</button></form>
@endif

@if($permissions['reject'] && $schedule->status==='pending_approval')
<form method="POST" action="{{ route('projects-unified.maintenance.approval.reject', ['schedule'=>$schedule->id]) }}" onsubmit="return confirm('Từ chối kết quả này?')">@csrf<textarea name="comment" rows="2" required placeholder="Lý do từ chối"></textarea><button class="emd9-btn danger" type="submit"><i class="bi bi-x-octagon"></i> Từ chối</button></form>
@endif

@if($permissions['approve'] && $schedule->status==='approved')
<form method="POST" action="{{ route('projects-unified.maintenance.approval.complete', ['schedule'=>$schedule->id]) }}" onsubmit="return confirm('Hoàn thành và đóng hồ sơ?')">@csrf<textarea name="comment" rows="2" placeholder="Ghi chú đóng hồ sơ"></textarea><button class="emd9-btn success" type="submit"><i class="bi bi-check2-circle"></i> Hoàn thành &amp; đóng hồ sơ</button></form>
@endif

@if($permissions['reopen'] && in_array($schedule->status,['approved','completed','pending_approval','revision_requested'],true))
<form method="POST" action="{{ route('projects-unified.maintenance.approval.reopen', ['schedule'=>$schedule->id]) }}">@csrf<textarea name="comment" rows="2" required placeholder="Lý do mở lại"></textarea><button class="emd9-btn light" type="submit"><i class="bi bi-arrow-clockwise"></i> Mở lại công việc</button></form>
@endif

                    </div>
                </section>

@if($warrantyClaim && $canUpdateWarranty)

                <section class="emd9-card">
                    <div class="emd9-card-head"><div><h2>Cập nhật phiếu bảo hành</h2><p>{{ $warrantyClaim->claim_code }}</p></div></div>
                    <form class="emd9-stack-form" method="POST" action="{{ route('projects-unified.maintenance.claims.status', ['claim'=>$warrantyClaim->id]) }}">@csrf
                        <label><span>Trạng thái tiếp theo</span><select name="status">
<?php $allowedClaimStatuses = array_unique(array_merge([$warrantyClaim->status], $warrantyTransitions[$warrantyClaim->status] ?? [])); ?>
 
@foreach($allowedClaimStatuses as $claimStatus)
<option value="{{ $claimStatus }}" @selected($claimStatus===$warrantyClaim->status)>{{ $warrantyStatuses[$claimStatus] ?? $claimStatus }}</option>
@endforeach
</select></label>
                        <label><span>Chẩn đoán</span><textarea name="diagnosis" rows="3">{{ $warrantyClaim->diagnosis }}</textarea></label>
                        <label><span>Phương án đề xuất</span><textarea name="proposed_solution" rows="3">{{ $warrantyClaim->proposed_solution }}</textarea></label>
                        <label><span>Kết quả xử lý</span><textarea name="resolution" rows="3">{{ $warrantyClaim->resolution }}</textarea></label>
                        
@if($canViewCosts)
<label><span>Chi phí thực tế</span><input type="number" min="0" step="0.01" name="actual_cost" value="{{ $warrantyClaim->actual_cost }}"></label>
@endif

                        <button class="emd9-btn primary" type="submit">Cập nhật phiếu</button>
                    </form>
                </section>
                
@endif

                
@if($warrantyClaim)

                <section class="emd9-card">
                    <div class="emd9-card-head"><div><h2>Kho &amp; đổi thiết bị</h2><p>Serial lỗi: {{ $warrantyClaim->serial_code ?: '—' }}</p></div></div>
                    <div class="emd9-stock-list">
                        
@forelse($warrantyClaim->stockMovements as $movement)

                        <article><div><b>{{ $movement->movement_code ?: '#'.$movement->id }}</b><small>{{ $stockTypes[$movement->movement_type] ?? $movement->movement_type }} · {{ $movement->warehouse?->name ?: 'Chưa chọn kho' }}</small><em>{{ $movement->serial_code }}</em></div><span class="emd9-status">{{ $stockStatuses[$movement->status] ?? $movement->status }}</span></article>
                        
@empty
<div class="emd9-empty compact">Chưa phát sinh phiếu kho.</div>
@endforelse

                    </div>
                    
@if($permissions['stock'])

                    <details class="emd9-stock-create"><summary>+ Tạo yêu cầu kho</summary><form class="emd9-stack-form" method="POST" action="{{ route('projects-unified.maintenance.stock.store') }}">@csrf<input type="hidden" name="warranty_claim_id" value="{{ $warrantyClaim->id }}"><label><span>Nghiệp vụ</span><select name="movement_type">
@foreach($stockTypes as $key=>$label)
<option value="{{ $key }}">{{ $label }}</option>
@endforeach
</select></label><label><span>Kho</span><select name="warehouse_id" required><option value="">Chọn kho</option>
@foreach($warehouses as $warehouse)
<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
@endforeach
</select></label><label><span>Serial</span><input name="serial_code" required placeholder="Nhập chính xác serial"></label><label><span>Serial liên quan</span><input name="related_serial_code" placeholder="Không bắt buộc"></label><label><span>Ghi chú</span><textarea name="note" rows="2"></textarea></label><button class="emd9-btn primary" type="submit">Tạo yêu cầu</button></form></details>
                    
@endif

@if($canViewCosts)

                    <div class="emd9-cost-box"><div><small>Dự kiến</small><b>{{ number_format((float)$warrantyClaim->estimated_cost,0,',','.') }} đ</b></div><div><small>Thực tế</small><b>{{ number_format((float)$warrantyClaim->actual_cost,0,',','.') }} đ</b></div><div><small>Hình thức</small><b>{{ $warrantyClaim->is_chargeable ? 'Tính phí' : 'Bảo hành miễn phí' }}</b></div></div>
                    
@endif

                </section>
                
@endif

            </aside>
        </div>
    </section>

    <section class="emd9-panel" data-emd9-panel="history">
        <section class="emd9-card">
            <div class="emd9-card-head"><div><h2>Lịch sử xử lý</h2><p>Trạng thái, phê duyệt, công việc và thay đổi quan trọng.</p></div></div>
            <div class="emd9-history">
                
@foreach($schedule->approvals as $approval)
<article><span></span><div><b>{{ strtoupper(str_replace('_',' ',$approval->action)) }}</b><p>{{ $approval->comment ?: ($approvalStatuses[$approval->status] ?? $approval->status) }}</p><small>{{ $approval->approver?->name ?: $approval->submitter?->name ?: 'Hệ thống' }} · {{ optional($approval->reviewed_at ?: $approval->submitted_at ?: $approval->created_at)->format('d/m/Y H:i') }}</small></div></article>
@endforeach

@foreach($schedule->statusHistories as $history)
<article><span></span><div><b>{{ $statuses[$history->to_status] ?? $history->to_status }}</b><p>{{ $history->reason ?: $history->note ?: 'Cập nhật trạng thái' }}</p><small>{{ $history->user?->name ?: 'Hệ thống' }} · {{ optional($history->changed_at ?: $history->created_at)->format('d/m/Y H:i') }}</small></div></article>
@endforeach

@foreach($workItems->sortByDesc('updated_at') as $workItem)
<article><span></span><div><b>Công việc: {{ $workItem->title }}</b><p>{{ $workStatuses[$workItem->status] ?? $workItem->status }} · {{ $workItem->progress_percent }}%</p><small>{{ $workItem->assignee?->name ?: $workItem->creator?->name ?: 'Hệ thống' }} · {{ optional($workItem->updated_at)->format('d/m/Y H:i') }}</small></div></article>
@endforeach

@if($schedule->approvals->isEmpty() && $schedule->statusHistories->isEmpty() && $workItems->isEmpty())
<div class="emd9-empty">Chưa có lịch sử.</div>
@endif

            </div>
        </section>
    </section>

        </div>

        <aside class="emx2-side" aria-label="Hồ sơ tại chỗ và nhóm thực hiện">
            <section class="emx2-side-card"><header><h3>Hồ sơ tại chỗ</h3><span class="emd9-badge {{ $checklistPercent >= 100 ? 'success' : 'warning' }}">{{ $checklistPercent >= 100 ? 'Sẵn sàng' : 'Đang bổ sung' }}</span></header><div class="emx2-donut" style="--emx2-progress:{{ $checklistPercent }}"><div><strong>{{ $checklistPercent }}%</strong><small>checklist bắt buộc</small></div></div><dl><div><dt>Hoàn thành</dt><dd>{{ $completedChecklist }}/{{ $requiredChecklist }}</dd></div><div><dt>Thiếu minh chứng</dt><dd>{{ max(0, $requiredChecklist - $completedChecklist) }} bước</dd></div><div><dt>Tổng file</dt><dd>{{ $attachmentCount }}</dd></div></dl>@foreach($schedule->attachments->take(3) as $attachment)<a class="emx2-side-file" href="{{ route('projects-unified.maintenance.schedule-files.preview', ['attachment'=>$attachment->id]) }}" target="_blank"><i class="bi bi-eye"></i> {{ \Illuminate\Support\Str::limit($attachment->original_name, 34) }}</a>@endforeach @if($attachmentCount > 3)<button type="button" class="emx2-more-files" data-emx2-open-tab="documents">Xem thêm {{ $attachmentCount - 3 }} file</button>@endif</section>
            <section class="emx2-side-card"><header><h3>Nhóm thực hiện</h3>@if($permissions['assignment_manage'])<button type="button" data-emx2-open-assignment>Chỉnh sửa</button>@endif</header>@forelse($schedule->assignees as $assignee)<div class="emx2-member"><span>{{ mb_strtoupper(mb_substr($assignee->user?->name ?: '?', 0, 1)) }}</span><div><strong>{{ $assignee->user?->name ?: 'Nhân sự' }}</strong><small>{{ (int) $assignee->user_id === (int) $leaderId ? 'Phụ trách chính' : 'Thành viên' }} · {{ $assignee->accepted_at ? 'Đã nhận việc' : 'Chưa nhận việc' }}</small></div></div>@empty<div class="emx2-no-members"><i class="bi bi-people"></i><span>Chưa có nhân sự thực hiện</span></div>@endforelse</section>
            @if($schedule->issue_note || $schedule->technical_note)<section class="emx2-side-card"><header><h3>Thông tin công việc</h3></header><p class="emx2-note">{{ $schedule->issue_note ?: $schedule->technical_note }}</p></section>@endif
        </aside>
    </div>

    @if($permissions['update'])
    <div class="emx2-modal" data-emx2-assignment-modal hidden><div class="emx2-modal-backdrop" data-emx2-close-assignment></div><section class="emx2-modal-dialog ema10-modal" role="dialog" aria-modal="true" aria-labelledby="emx2AssignmentTitle"><header><div><span>NHÂN SỰ NỘI BỘ</span><h2 id="emx2AssignmentTitle">Chọn người thực hiện</h2><p>Chọn nhiều kỹ thuật viên và chỉ định đúng một người phụ trách chính.</p></div><button type="button" data-emx2-close-assignment><i class="bi bi-x-lg"></i></button></header><form method="POST" action="{{ route('projects-unified.maintenance.update', ['schedule'=>$schedule->id]) }}" data-emx2-assignment-form>@csrf @method('PUT')<div class="emx2-search"><i class="bi bi-search"></i><input type="search" placeholder="Tìm theo tên hoặc phòng ban..." data-emx2-user-search></div><div class="emx2-user-list">@foreach($technicalUsers as $user)<?php $userSelected = (int)$leaderId === (int)$user->id || in_array((int)$user->id,$memberIds,true); ?><label class="emx2-user" data-emx2-user-row data-emx2-name="{{ mb_strtolower($user->name.' '.($user->department?->name ?: 'Kỹ thuật')) }}"><input type="checkbox" value="{{ $user->id }}" data-emx2-user-check @checked($userSelected)><span class="ema10-user-avatar">{{ mb_strtoupper(mb_substr($user->name,0,1)) }}</span><span><strong>{{ $user->name }}</strong><small>{{ $user->department?->name ?: 'Kỹ thuật' }}</small></span><span class="emx2-lead"><input type="radio" name="leader_user_id" value="{{ $user->id }}" @checked((int)$leaderId === (int)$user->id)> Phụ trách chính</span></label>@endforeach</div><div class="emx2-selection-count"><span>Đã chọn <b data-emx2-user-count>{{ $assignedCount }}</b> người</span><small>Sau khi lưu, phân công sẽ chuyển sang chờ Admin duyệt.</small></div><footer><button class="emd9-btn light" type="button" data-emx2-close-assignment>Hủy</button><button class="emd9-btn primary" type="submit"><i class="bi bi-check2-circle"></i> Lưu &amp; gửi duyệt</button></footer></form></section></div>
    @endif

    <footer class="emd9-sticky">
        <a class="emd9-btn light" href="{{ route('projects-unified.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Quay lại</a>
        <div>
            <button class="emd9-btn light" type="button" onclick="window.print()"><i class="bi bi-printer"></i> In phiếu</button>
            
@if($permissions['submit'] && in_array($schedule->status,['in_progress','waiting_material','waiting_submission','revision_requested'],true))
<button class="emd9-btn primary" type="button" data-emp1-open="complete"><i class="bi bi-send-check"></i> Gửi duyệt</button>
@endif

@if($permissions['approve'] && $schedule->status==='pending_approval')
<button class="emd9-btn success" type="button" data-emp1-open="complete"><i class="bi bi-patch-check"></i> Phê duyệt</button>
@endif

        </div>
    </footer>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/technical-maintenance-detail-v9.js') }}?v={{ file_exists(public_path('js/technical-maintenance-detail-v9.js')) ? filemtime(public_path('js/technical-maintenance-detail-v9.js')) : time() }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-emd9-root]');
    if (!root) return;
    const steps = [...root.querySelectorAll('[data-emp1-target]')];
    const stageTitles = { summary:'Tổng quan', plan:'Kế hoạch', assignment:'Phân công', perform:'Thực hiện', incident:'Phát sinh', complete:'Hoàn tất', documents:'Hồ sơ đính kèm', history:'Lịch sử xử lý' };
    const openStage = (name, updateHash = false) => {
        root.querySelectorAll('[data-emd9-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.emd9Panel === name));
        root.querySelectorAll('.emx2-tabs [data-emd9-tab]').forEach(tab => tab.classList.toggle('active', !['documents', 'history'].includes(name) ? tab === root.querySelector('.emx2-tabs [data-emd9-tab]') : tab.dataset.emd9Tab === name));
        steps.forEach(step => step.classList.toggle('active', step.dataset.emp1Target === name));
        const title = root.querySelector('[data-emr9-current-title]');
        if (title) title.textContent = stageTitles[name] || 'Tổng quan';
        if (updateHash && stageTitles[name]) history.replaceState(null, '', '#round-' + name);
        if (updateHash) root.querySelector('.emp1-content')?.scrollIntoView({ behavior:'smooth', block:'start' });
    };
    const requestedStage = location.hash.startsWith('#round-') ? location.hash.slice(7) : (root.dataset.emx2InitialStage || 'summary');
    openStage(stageTitles[requestedStage] ? requestedStage : 'summary');
    steps.forEach(step => step.addEventListener('click', () => {
        openStage(step.dataset.emp1Tab, true);
    }));
    root.querySelectorAll('.emx2-tabs [data-emd9-tab]').forEach(tab => tab.addEventListener('click', () => openStage(tab.dataset.emd9Tab, true)));
    root.querySelectorAll('[data-emp1-open]').forEach(button => button.addEventListener('click', () => {
        openStage(button.dataset.emp1Open);
    }));
    root.querySelectorAll('[data-emx2-stage]').forEach(button => button.addEventListener('click', () => openStage(button.dataset.emx2Stage, true)));
    root.querySelectorAll('[data-emx2-open-tab]').forEach(button => button.addEventListener('click', () => root.querySelector('[data-emd9-tab="' + button.dataset.emx2OpenTab + '"]')?.click()));

    const checklistSettings = root.querySelector('[data-eme11-settings-modal]');
    if (checklistSettings) {
        const closeChecklistSettings = () => { checklistSettings.hidden = true; document.body.classList.remove('eme11-modal-open'); };
        root.querySelectorAll('[data-eme11-settings-open]').forEach(button => button.addEventListener('click', () => { checklistSettings.hidden = false; document.body.classList.add('eme11-modal-open'); }));
        checklistSettings.querySelectorAll('[data-eme11-settings-close]').forEach(button => button.addEventListener('click', closeChecklistSettings));
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !checklistSettings.hidden) closeChecklistSettings(); });
    }

    const incidentToggle = root.querySelector('[data-emi12-incident-toggle]');
    const syncIncident = () => {
        const present = !!incidentToggle?.checked;
        root.querySelectorAll('[data-emi12-incident-fields]').forEach(field => { field.hidden = !present; });
        const none = root.querySelector('[data-emi12-incident-none]');
        if (none) none.disabled = present;
        const kind = root.querySelector('[data-emi12-incident-kind]');
        if (kind) kind.disabled = !present;
        const selected = kind?.value;
        const scope = root.querySelector('[data-emi12-warranty-scope]');
        if (scope) scope.value = ['warranty_paid', 'maintenance_paid'].includes(selected) ? 'out_of_scope' : (present ? 'in_scope' : 'pending_assessment');
        const state = root.querySelector('[data-emi12-state]');
        if (state) { state.textContent = present ? 'Có phát sinh' : 'Không phát sinh'; state.classList.toggle('active', present); }
    };
    incidentToggle?.addEventListener('change', syncIncident);
    root.querySelector('[data-emi12-incident-kind]')?.addEventListener('change', syncIncident);
    syncIncident();

    const proposalModal = root.querySelector('[data-emi12-proposal-modal]');
    const closeProposal = () => { if (!proposalModal) return; proposalModal.hidden = true; document.body.classList.remove('emi12-modal-open'); };
    root.querySelector('[data-emi12-proposal-open]')?.addEventListener('click', () => { if (!proposalModal) return; proposalModal.hidden = false; document.body.classList.add('emi12-modal-open'); proposalModal.querySelector('input[name="items[0][name]"]')?.focus(); });
    proposalModal?.querySelectorAll('[data-emi12-proposal-close]').forEach(button => button.addEventListener('click', closeProposal));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && proposalModal && !proposalModal.hidden) closeProposal(); });

    const closeProposalDetail = modal => {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('emi14-modal-open');
    };
    root.querySelectorAll('[data-emi14-open]').forEach(button => button.addEventListener('click', () => {
        const modal = root.querySelector('[data-emi14-modal="' + button.dataset.emi14Open + '"]');
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('emi14-modal-open');
    }));
    root.querySelectorAll('[data-emi14-modal]').forEach(modal => {
        modal.querySelectorAll('[data-emi14-close]').forEach(button => button.addEventListener('click', () => closeProposalDetail(modal)));
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        root.querySelectorAll('[data-emi14-modal]:not([hidden])').forEach(closeProposalDetail);
    });

    /* EMI13: popup đề xuất vật tư nhiều dòng */

    if (proposalModal) {
        const rowsContainer = proposalModal.querySelector('[data-emi13-rows]');
        const countElement = proposalModal.querySelector('[data-emi13-count]');

        const refreshRows = () => {
            const rows = [...rowsContainer.querySelectorAll('tr')];

            rows.forEach((row, index) => {
                row.querySelector('[data-emi13-name]').name = `items[${index}][name]`;
                row.querySelector('[data-emi13-qty]').name = `items[${index}][qty]`;
                row.querySelector('[data-emi13-note]').name = `items[${index}][note]`;
                row.querySelector('[data-emi13-unit]').name = `items[${index}][unit]`;

                const removeButton = row.querySelector('[data-emi13-remove]');

                removeButton.disabled = rows.length === 1;
            });

            if (countElement) {
                countElement.textContent = String(rows.length);
            }
        };

        const addRow = (name = '', quantity = 1, note = '') => {
            const row = document.createElement('tr');

            row.innerHTML = `
                <td>
                    <input
                        type="text"
                        data-emi13-name
                        placeholder="Nhập tên vật tư"
                        required
                    >
                    <input
                        type="hidden"
                        data-emi13-unit
                        value="Cái"
                    >
                </td>
                <td>
                    <input
                        type="number"
                        data-emi13-qty
                        min="1"
                        required
                    >
                </td>
                <td>
                    <input
                        type="text"
                        data-emi13-note
                        placeholder="Ghi chú nếu có"
                    >
                </td>
                <td>
                    <button
                        type="button"
                        class="emi13-remove"
                        data-emi13-remove
                        title="Xóa dòng"
                    >×</button>
                </td>
            `;

            row.querySelector('[data-emi13-name]').value = name;
            row.querySelector('[data-emi13-qty]').value = Math.max(1, Number(quantity) || 1);
            row.querySelector('[data-emi13-note]').value = note;

            row.querySelector('[data-emi13-remove]').addEventListener('click', () => {
                if (rowsContainer.querySelectorAll('tr').length > 1) {
                    row.remove();
                    refreshRows();
                }
            });

            rowsContainer.appendChild(row);

            refreshRows();

            return row;
        };

        addRow();

        proposalModal.querySelector('[data-emi13-add]')?.addEventListener('click', () => {
            addRow().querySelector('[data-emi13-name]')?.focus();
        });

        proposalModal.querySelector('[data-emi13-template]')?.addEventListener('click', () => {
            const content = '\uFEFFTên vật tư,Số lượng,Ghi chú\nTấm pin năng lượng,2,Thay thế thiết bị hỏng\n';

            const url = URL.createObjectURL(
                new Blob([content], {
                    type: 'text/csv;charset=utf-8;'
                })
            );

            const link = document.createElement('a');

            link.href = url;
            link.download = 'mau-de-xuat-vat-tu.csv';
            link.click();

            URL.revokeObjectURL(url);
        });

        const importRows = (data) => {
            const normalized = data
                .slice(1)
                .filter(row => row && String(row[0] || '').trim() !== '');

            if (!normalized.length) {
                alert('File chưa có dữ liệu vật tư.');
                return;
            }

            rowsContainer.innerHTML = '';

            normalized.forEach(row => {
                addRow(
                    String(row[0] || '').trim(),
                    Number(row[1]) || 1,
                    String(row[2] || '').trim()
                );
            });
        };

        const parseCsv = text => {
            const result = [];
            let row = [];
            let value = '';
            let quoted = false;

            text.replace(/^\uFEFF/, '').split('').forEach((character, index, characters) => {
                if (character === '"') {
                    if (quoted && characters[index + 1] === '"') {
                        value += '"';
                    } else if (!(quoted && characters[index - 1] === '"')) {
                        quoted = !quoted;
                    }
                } else if (character === ',' && !quoted) {
                    row.push(value);
                    value = '';
                } else if ((character === '\n' || character === '\r') && !quoted) {
                    if (value.length || row.length) {
                        row.push(value);
                        result.push(row);
                        row = [];
                        value = '';
                    }
                } else {
                    value += character;
                }
            });

            if (value.length || row.length) {
                row.push(value);
                result.push(row);
            }

            return result;
        };

        proposalModal.querySelector('[data-emi13-import]')?.addEventListener('change', async event => {
            const file = event.target.files?.[0];

            if (!file) {
                return;
            }

            if (file.name.toLowerCase().endsWith('.csv')) {
                importRows(parseCsv(await file.text()));
                event.target.value = '';
                return;
            }

            const readExcel = async () => {
                const workbook = XLSX.read(await file.arrayBuffer(), {
                    type: 'array'
                });

                const sheet = workbook.Sheets[workbook.SheetNames[0]];

                importRows(
                    XLSX.utils.sheet_to_json(sheet, {
                        header: 1,
                        defval: ''
                    })
                );

                event.target.value = '';
            };

            if (window.XLSX) {
                await readExcel();
                return;
            }

            const script = document.createElement('script');

            script.src = 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js';

            script.onload = readExcel;

            script.onerror = () => {
                alert('Không tải được bộ đọc Excel. Vui lòng lưu file thành CSV rồi nhập lại.');
            };

            document.head.appendChild(script);
        });
    }

    const uploadPicker = root.querySelector('[data-emi12-upload-picker]');
    const uploadTarget = root.querySelector('[data-emi12-upload-target]');
    uploadPicker?.addEventListener('change', () => { if (!uploadPicker.files?.length || !uploadTarget) return; const transfer = new DataTransfer(); [...uploadPicker.files].forEach(file => transfer.items.add(file)); uploadTarget.files = transfer.files; root.querySelector('[data-emi12-upload-form]')?.submit(); });

    const modal = root.querySelector('[data-emx2-assignment-modal]');
    if (!modal) return;
    const closeModal = () => { modal.hidden = true; document.body.classList.remove('emx2-modal-open'); };
    root.querySelectorAll('[data-emx2-open-assignment]').forEach(button => button.addEventListener('click', () => { modal.hidden = false; document.body.classList.add('emx2-modal-open'); modal.querySelector('[data-emx2-user-search]')?.focus(); }));
    modal.querySelectorAll('[data-emx2-close-assignment]').forEach(button => button.addEventListener('click', closeModal));
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !modal.hidden) closeModal(); });
    const rows = [...modal.querySelectorAll('[data-emx2-user-row]')];
    const count = modal.querySelector('[data-emx2-user-count]');
    const syncSelection = () => { const selected = rows.filter(row => row.querySelector('[data-emx2-user-check]').checked); if (count) count.textContent = String(selected.length); rows.forEach(row => { const check = row.querySelector('[data-emx2-user-check]'); const lead = row.querySelector('[name="leader_user_id"]'); if (!check.checked && lead.checked) lead.checked = false; row.classList.toggle('selected', check.checked); }); };
    rows.forEach(row => { const check = row.querySelector('[data-emx2-user-check]'); const lead = row.querySelector('[name="leader_user_id"]'); check.addEventListener('change', syncSelection); lead.addEventListener('change', () => { check.checked = true; syncSelection(); }); });
    modal.querySelector('[data-emx2-user-search]')?.addEventListener('input', event => { const q = event.target.value.toLocaleLowerCase('vi').trim(); rows.forEach(row => { row.hidden = q !== '' && !row.dataset.emx2Name.includes(q); }); });
    const externalToggle = modal.querySelector('[data-emx2-external-toggle]');
    externalToggle?.addEventListener('change', () => { modal.querySelector('[data-emx2-external-fields]').hidden = !externalToggle.checked; });
    modal.querySelector('[data-emx2-assignment-form]')?.addEventListener('submit', event => { const selected = rows.filter(row => row.querySelector('[data-emx2-user-check]').checked); let leader = modal.querySelector('[name="leader_user_id"]:checked'); if (selected.length > 0 && !leader) { leader = selected[0].querySelector('[name="leader_user_id"]'); leader.checked = true; } modal.querySelectorAll('[data-emx2-generated-member]').forEach(input => input.remove()); selected.forEach(row => { const value = row.querySelector('[data-emx2-user-check]').value; if (leader && value === leader.value) return; const input = document.createElement('input'); input.type = 'hidden'; input.name = 'member_user_ids[]'; input.value = value; input.dataset.emx2GeneratedMember = '1'; event.target.appendChild(input); }); });
    syncSelection();
});
</script>
@endsection
