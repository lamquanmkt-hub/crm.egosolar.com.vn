@extends('layouts.app')

@section('title', 'Chi tiết đợt bảo trì')

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
    $approvalTone = ['not_submitted'=>'muted','pending'=>'pending','approved'=>'success','revision_requested'=>'orange','rejected'=>'danger'];
    $hasValidSite = (bool)($schedule->site_id && $schedule->site);
    $roundNo = max(1,(int)($schedule->round_no ?: 1));
    $totalRounds = max(1,(int)($schedule->total_rounds ?: 1));
@endphp

<div class="ego-container tm3-page">
    <nav class="tm3-breadcrumb">
        <a href="{{ route('ky-thuat.maintenance.index') }}">Bảo trì &amp; Bảo hành</a>
        <i class="bi bi-chevron-right"></i>
        @if($hasValidSite)<a href="{{ route('ky-thuat.maintenance.site', ['site'=>$schedule->site_id]) }}">{{ $schedule->site?->name ?: $schedule->site_name }}</a><i class="bi bi-chevron-right"></i>@endif
        <span>{{ $schedule->schedule_code ?: '#'.$schedule->id }}</span>
    </nav>

    @if(session('success'))<div class="tm3-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="tm3-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())<div class="tm3-alert danger align-start"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể thực hiện</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

    <header class="tm3-page-header schedule-header">
        <div>
            <div class="tm3-title-line"><span class="tm3-kicker">Chi tiết đợt bảo trì</span><span class="tm3-round-badge">Đợt {{ $roundNo }}/{{ $totalRounds }}</span></div>
            <h1>{{ $schedule->schedule_code ?: '#'.$schedule->id }}</h1>
            <p>{{ $schedule->site?->name ?: $schedule->site_name ?: 'Công trình chưa đặt tên' }}</p>
        </div>
        <div class="tm3-header-actions stacked-status">
            <span class="tm3-status {{ $statusTone[$schedule->status] ?? 'muted' }}">{{ $statuses[$schedule->status] ?? $schedule->status }}</span>
            <span class="tm3-status {{ $approvalTone[$schedule->approval_status] ?? 'muted' }}">{{ $approvalStatuses[$schedule->approval_status] ?? $schedule->approval_status }}</span>
            @if($hasValidSite)<a class="btn btn-light" href="{{ route('ky-thuat.maintenance.site', ['site'=>$schedule->site_id]) }}"><i class="bi bi-building"></i> Hồ sơ công trình</a>@endif
        </div>
    </header>

    @if(!$hasValidSite)
        <div class="tm3-alert warning"><i class="bi bi-link-45deg"></i><span>Đợt này chưa liên kết với công trình hợp lệ. Dữ liệu lịch vẫn được giữ nguyên để Admin hoặc Trưởng phòng kỹ thuật xử lý.</span></div>
    @endif

    <section class="tm3-card tm3-cycle-bar-card">
        <div class="tm3-cycle-context">
            <div><small>Đợt trước</small><strong>{{ $previousSchedule ? 'Đợt '.$previousSchedule->round_no.' · '.optional($previousSchedule->scheduled_date)->format('d/m/Y') : 'Không có' }}</strong></div>
            <div class="current"><small>Đang xem</small><strong>Đợt {{ $roundNo }}/{{ $totalRounds }}</strong></div>
            <div><small>Đợt tiếp theo</small><strong>{{ $nextSchedule ? 'Đợt '.$nextSchedule->round_no.' · '.optional($nextSchedule->scheduled_date)->format('d/m/Y') : 'Không có' }}</strong></div>
        </div>
        <div class="tm3-stepper" aria-label="Tiến độ chu kỳ">
            @foreach($siblings as $item)
                <a href="{{ route('ky-thuat.maintenance.show', ['schedule'=>$item->id]) }}" class="{{ (int)$item->id === (int)$schedule->id ? 'active' : '' }} {{ in_array($item->status,['approved','completed'],true) ? 'done' : '' }}" title="Đợt {{ $item->round_no }} · {{ optional($item->scheduled_date)->format('d/m/Y') }} · {{ $statuses[$item->status] ?? $item->status }}">
                    <span>@if(in_array($item->status,['approved','completed'],true))<i class="bi bi-check-lg"></i>@else{{ $item->round_no ?: 1 }}@endif</span>
                </a>
            @endforeach
        </div>
    </section>

    <nav class="tm3-tabs" role="tablist">
        <button class="active" type="button" data-tm3-tab="overview"><i class="bi bi-grid"></i> Tổng quan</button>
        <button type="button" data-tm3-tab="files"><i class="bi bi-images"></i> Hình ảnh &amp; file <span>{{ $schedule->attachments->count() }}</span></button>
        <button type="button" data-tm3-tab="approval"><i class="bi bi-patch-check"></i> Phê duyệt</button>
        <button type="button" data-tm3-tab="history"><i class="bi bi-clock-history"></i> Lịch sử</button>
    </nav>

    <section class="tm3-tab-panel active" data-tm3-panel="overview">
        <div class="tm3-detail-layout">
            <main class="tm3-detail-main">
                <section class="tm3-card">
                    <div class="tm3-section-head"><div><h2>Thông tin đợt</h2><p>Lịch, phân công và nội dung xử lý</p></div></div>
                    <div class="tm3-reference-grid">
                        <div><small>Công trình</small><strong>{{ $schedule->site?->name ?: $schedule->site_name ?: '—' }}</strong></div>
                        <div><small>Khách hàng</small><strong>{{ $schedule->site?->contact_name ?: $schedule->customer_name ?: '—' }}</strong></div>
                        <div class="wide"><small>Địa chỉ</small><strong>{{ $schedule->site?->address ?: $schedule->address ?: '—' }}</strong></div>
                    </div>

                    @if($permissions['update'])
                    <form method="POST" action="{{ route('ky-thuat.maintenance.update', ['schedule'=>$schedule->id]) }}" class="tm3-form-grid">@csrf @method('PUT')
                        <div><label class="form-label">Ngày dự kiến</label><input class="form-control" type="date" name="scheduled_date" value="{{ optional($schedule->scheduled_date)->format('Y-m-d') }}" required></div>
                        <div><label class="form-label">Loại lịch</label><select class="form-select" name="type">@foreach($types as $key=>$label)<option value="{{ $key }}" @selected($schedule->type===$key)>{{ $label }}</option>@endforeach</select></div>
                        <div><label class="form-label">Ưu tiên</label><select class="form-select" name="priority">@foreach($priorities as $key=>$label)<option value="{{ $key }}" @selected($schedule->priority===$key)>{{ $label }}</option>@endforeach</select></div>
                        <div><label class="form-label">Trưởng nhóm kỹ thuật</label><select class="form-select" name="leader_user_id"><option value="">Chưa chọn</option>@foreach($technicalUsers as $user)<option value="{{ $user->id }}" @selected((int)$leaderId===(int)$user->id)>{{ $user->name }}{{ $user->position?->name ? ' — '.$user->position->name : '' }}</option>@endforeach</select></div>
                        <div class="wide"><label class="form-label">Thành viên thực hiện</label><select class="form-select" name="member_user_ids[]" multiple size="4">@foreach($technicalUsers as $user)<option value="{{ $user->id }}" @selected(in_array((int)$user->id,$memberIds,true))>{{ $user->name }}{{ $user->department?->name ? ' — '.$user->department->name : '' }}</option>@endforeach</select><small class="tm3-help">Chỉ hiển thị nhân sự kỹ thuật đang hoạt động.</small></div>
                        <div><label class="form-label">Công suất kWp</label><input class="form-control" type="number" step="0.01" name="system_kwp" value="{{ $schedule->system_kwp }}"></div>
                        <div class="span-2"><label class="form-label">Inverter / thiết bị</label><input class="form-control" name="inverter_info" value="{{ $schedule->inverter_info }}"></div>
                        <div class="wide"><label class="form-label">Hiện trạng / yêu cầu</label><textarea class="form-control" name="issue_note" rows="3">{{ $schedule->issue_note }}</textarea></div>
                        <div class="wide"><label class="form-label">Ghi chú kỹ thuật</label><textarea class="form-control" name="technical_note" rows="3">{{ $schedule->technical_note }}</textarea></div>
                        <div class="wide"><label class="form-label">Kết quả xử lý</label><textarea class="form-control" name="result_note" rows="4" placeholder="Cập nhật đầy đủ trước khi gửi duyệt">{{ $schedule->result_note }}</textarea></div>
                        <div class="wide tm3-form-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Lưu thông tin đợt</button></div>
                    </form>
                    @else
                    <dl class="tm3-info-list columns"><div><dt>Ngày dự kiến</dt><dd>{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: '—' }}</dd></div><div><dt>Loại lịch</dt><dd>{{ $types[$schedule->type] ?? $schedule->type }}</dd></div><div><dt>Trưởng nhóm</dt><dd>{{ $schedule->leader?->user?->name ?: 'Chưa phân công' }}</dd></div><div><dt>Người thực hiện</dt><dd>{{ $schedule->assignee_names }}</dd></div><div><dt>Hiện trạng</dt><dd>{{ $schedule->issue_note ?: '—' }}</dd></div><div><dt>Kết quả</dt><dd>{{ $schedule->result_note ?: '—' }}</dd></div></dl>
                    @endif
                </section>
            </main>
            <aside class="tm3-detail-side">
                <section class="tm3-card">
                    <div class="tm3-side-title">Trạng thái hiện tại</div>
                    <div class="tm3-status-stack"><span class="tm3-status {{ $statusTone[$schedule->status] ?? 'muted' }}">{{ $statuses[$schedule->status] ?? $schedule->status }}</span><span class="tm3-status {{ $approvalTone[$schedule->approval_status] ?? 'muted' }}">{{ $approvalStatuses[$schedule->approval_status] ?? $schedule->approval_status }}</span></div>
                    <dl class="tm3-info-list"><div><dt>Ngày dự kiến</dt><dd>{{ optional($schedule->scheduled_date)->format('d/m/Y') ?: '—' }}</dd></div><div><dt>Trưởng nhóm</dt><dd>{{ $schedule->leader?->user?->name ?: 'Chưa phân công' }}</dd></div><div><dt>Thành viên</dt><dd>{{ max(0,$schedule->assignees->count()-($schedule->leader ? 1 : 0)) }}</dd></div><div><dt>Người phê duyệt</dt><dd>{{ $schedule->approver?->name ?: 'Chưa có' }}</dd></div><div><dt>Hoạt động gần nhất</dt><dd>{{ optional($schedule->updated_at)->format('d/m/Y H:i') }}</dd></div></dl>
                </section>
                <section class="tm3-card">
                    <div class="tm3-side-title">Người phụ trách</div>
                    <div class="tm3-people-list">@forelse($schedule->assignees as $assignee)<div><span>{{ mb_substr($assignee->user?->name ?: '?',0,1) }}</span><div><strong>{{ $assignee->user?->name ?: 'Người dùng đã xóa' }}</strong><small>{{ ($assignee->is_leader || $assignee->role==='leader') ? 'Trưởng nhóm' : 'Thành viên kỹ thuật' }}</small></div></div>@empty<div class="tm3-empty compact">Chưa phân công.</div>@endforelse</div>
                </section>
            </aside>
        </div>
    </section>

    <section class="tm3-tab-panel" data-tm3-panel="files">
        <section class="tm3-card">
            <div class="tm3-section-head"><div><h2>Hình ảnh &amp; hồ sơ đợt</h2><p>Minh chứng trước, trong và sau xử lý</p></div><span class="tm3-count">{{ $schedule->attachments->count() }} file</span></div>
            @if($permissions['upload'])
                <form class="tm3-upload-bar" method="POST" action="{{ route('ky-thuat.maintenance.schedule-files.store', ['schedule'=>$schedule->id]) }}" enctype="multipart/form-data">@csrf
                    <select class="form-select" name="category" required><option value="before">Trước khi xử lý</option><option value="during">Trong khi xử lý</option><option value="after">Sau khi xử lý</option><option value="fault">Thiết bị lỗi</option><option value="serial">Ảnh serial</option><option value="report">Biên bản</option><option value="video">Video</option><option value="other">Khác</option></select>
                    <input class="form-control" type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.mp4">
                    <input class="form-control" name="description" placeholder="Ghi chú cho nhóm file">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-cloud-arrow-up"></i> Tải lên</button>
                </form>
            @endif
            <div class="tm3-file-grid">@forelse($schedule->attachments as $attachment)<article class="tm3-file-card"><span class="tm3-file-icon"><i class="bi bi-file-earmark-image"></i></span><div class="tm3-file-info"><strong title="{{ $attachment->original_name }}">{{ \Illuminate\Support\Str::limit($attachment->original_name,45) }}</strong><small>{{ strtoupper($attachment->category) }} · {{ number_format($attachment->file_size/1024,1) }} KB</small><small>{{ $attachment->uploader?->name ?: 'Hệ thống' }} · {{ optional($attachment->created_at)->format('d/m/Y H:i') }}</small></div><div class="tm3-file-actions"><a href="{{ route('ky-thuat.maintenance.schedule-files.preview', ['attachment'=>$attachment->id]) }}" target="_blank"><i class="bi bi-eye"></i></a><a href="{{ route('ky-thuat.maintenance.schedule-files.download', ['attachment'=>$attachment->id]) }}"><i class="bi bi-download"></i></a>@if($permissions['upload'])<form method="POST" action="{{ route('ky-thuat.maintenance.schedule-files.destroy', ['attachment'=>$attachment->id]) }}" onsubmit="return confirm('Xóa file này?')">@csrf @method('DELETE')<button type="submit"><i class="bi bi-trash3"></i></button></form>@endif</div></article>@empty<div class="tm3-empty compact">Chưa có ảnh hoặc hồ sơ cho đợt này.</div>@endforelse</div>
        </section>
    </section>

    <section class="tm3-tab-panel" data-tm3-panel="approval">
        <div class="tm3-detail-layout approval-layout">
            <main class="tm3-detail-main">
                <section class="tm3-card"><div class="tm3-section-head"><div><h2>Phê duyệt kỹ thuật</h2><p>Gửi duyệt, phản hồi và mở lại công việc</p></div></div>
                    <div class="tm3-approval-summary"><span class="tm3-status {{ $approvalTone[$schedule->approval_status] ?? 'muted' }}">{{ $approvalStatuses[$schedule->approval_status] ?? $schedule->approval_status }}</span><p>{{ $schedule->approval_note ?: 'Chưa có phản hồi phê duyệt.' }}</p>@if($schedule->submitted_at)<small>Gửi duyệt: {{ $schedule->submitter?->name ?: '—' }} · {{ $schedule->submitted_at->format('d/m/Y H:i') }}</small>@endif @if($schedule->approved_at)<small>Duyệt bởi: {{ $schedule->approver?->name ?: '—' }} · {{ $schedule->approved_at->format('d/m/Y H:i') }}</small>@endif</div>
                    <div class="tm3-approval-actions">
                        @if($permissions['submit'] && in_array($schedule->status,['in_progress','waiting_material','waiting_submission','revision_requested'],true))<form method="POST" action="{{ route('ky-thuat.maintenance.approval.submit', ['schedule'=>$schedule->id]) }}">@csrf<textarea class="form-control" name="comment" rows="3" placeholder="Ghi chú khi gửi duyệt"></textarea><button class="btn btn-primary" type="submit"><i class="bi bi-send-check"></i> Gửi Trưởng phòng duyệt</button></form>@endif
                        @if($permissions['approve'] && $schedule->status==='pending_approval')<form method="POST" action="{{ route('ky-thuat.maintenance.approval.approve', ['schedule'=>$schedule->id]) }}">@csrf<textarea class="form-control" name="comment" rows="3" placeholder="Nhận xét phê duyệt"></textarea><button class="btn btn-success" type="submit"><i class="bi bi-patch-check"></i> Phê duyệt</button></form>@endif
                        @if($permissions['revision'] && $schedule->status==='pending_approval')<form method="POST" action="{{ route('ky-thuat.maintenance.approval.revision', ['schedule'=>$schedule->id]) }}">@csrf<textarea class="form-control" name="comment" rows="3" required placeholder="Nội dung cần chỉnh sửa"></textarea><button class="btn btn-warning" type="submit"><i class="bi bi-arrow-return-left"></i> Yêu cầu chỉnh sửa</button></form>@endif
                        @if($permissions['reject'] && $schedule->status==='pending_approval')<form method="POST" action="{{ route('ky-thuat.maintenance.approval.reject', ['schedule'=>$schedule->id]) }}" onsubmit="return confirm('Xác nhận từ chối kết quả này?')">@csrf<textarea class="form-control" name="comment" rows="3" required placeholder="Lý do từ chối"></textarea><button class="btn btn-outline-danger" type="submit"><i class="bi bi-x-octagon"></i> Từ chối</button></form>@endif
                        @if($permissions['reopen'] && in_array($schedule->status,['approved','completed','pending_approval','revision_requested'],true))<form method="POST" action="{{ route('ky-thuat.maintenance.approval.reopen', ['schedule'=>$schedule->id]) }}" onsubmit="return confirm('Mở lại công việc này?')">@csrf<textarea class="form-control" name="comment" rows="3" required placeholder="Lý do mở lại"></textarea><button class="btn btn-outline-secondary" type="submit"><i class="bi bi-arrow-clockwise"></i> Mở lại công việc</button></form>@endif
                    </div>
                </section>
            </main>
            <aside class="tm3-detail-side"><section class="tm3-card"><div class="tm3-side-title">Nguyên tắc duyệt</div><ul class="tm3-check-list"><li>Người thực hiện không tự duyệt công việc của mình.</li><li>Phải có kết quả xử lý trước khi gửi duyệt.</li><li>Mọi phản hồi đều được lưu lịch sử.</li><li>Chỉ Trưởng phòng kỹ thuật hoặc Admin được mở lại.</li></ul></section></aside>
        </div>
    </section>

    <section class="tm3-tab-panel" data-tm3-panel="history">
        <section class="tm3-card"><div class="tm3-section-head"><div><h2>Lịch sử trạng thái &amp; phê duyệt</h2><p>Toàn bộ thay đổi quan trọng của đợt</p></div></div><div class="tm3-timeline">
            @foreach($schedule->approvals as $approval)<div class="tm3-timeline-item"><span></span><div><strong>{{ strtoupper(str_replace('_',' ',$approval->action)) }}</strong><p>{{ $approval->comment ?: ($approvalStatuses[$approval->status] ?? $approval->status) }}</p><small>{{ $approval->approver?->name ?: $approval->submitter?->name ?: 'Hệ thống' }} · {{ optional($approval->reviewed_at ?: $approval->submitted_at ?: $approval->created_at)->format('d/m/Y H:i') }}</small></div></div>@endforeach
            @foreach($schedule->statusHistories as $history)<div class="tm3-timeline-item"><span></span><div><strong>{{ $statuses[$history->to_status] ?? $history->to_status }}</strong><p>{{ $history->reason ?: $history->note ?: 'Cập nhật trạng thái' }}</p><small>{{ $history->user?->name ?: 'Hệ thống' }} · {{ optional($history->changed_at)->format('d/m/Y H:i') }}</small></div></div>@endforeach
            @if($schedule->approvals->isEmpty() && $schedule->statusHistories->isEmpty())<div class="tm3-empty compact">Chưa có lịch sử.</div>@endif
        </div></section>
    </section>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/technical-maintenance-v3.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v3.js')) ? filemtime(public_path('js/technical-maintenance-v3.js')) : time() }}"></script>
@endsection
