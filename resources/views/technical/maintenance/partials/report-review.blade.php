<div class="om10-review-grid">
    <div><small>Nhóm thực hiện</small><strong>{{ $schedule->assignees->pluck('user.name')->filter()->implode(', ') ?: '—' }}</strong></div>
    <div><small>Thời gian bắt đầu</small><strong>{{ optional($schedule->started_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
    <div><small>Hoàn tất thực hiện</small><strong>{{ optional($schedule->execution_finished_at ?: $schedule->completed_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
    <div><small>Hoàn tất hồ sơ</small><strong>{{ optional($schedule->completed_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
    <div><small>Checklist</small><strong>{{ $checkDone }}/{{ $checkTotal }} hoàn thành</strong></div>
    <div><small>Minh chứng</small><strong>{{ $schedule->attachments->count() }} file</strong></div>
    @if($schedule->approval_status === 'approved')
        <div><small>Duyệt theo quy trình cũ</small><strong>{{ $schedule->approver?->name ?: '—' }}</strong></div>
        <div><small>Duyệt lúc</small><strong>{{ optional($schedule->approved_at)->format('d/m/Y H:i') ?: '—' }}</strong></div>
    @else
        <div><small>Quy trình hoàn tất</small><strong>Không yêu cầu duyệt cuối</strong></div>
        <div><small>Trạng thái</small><strong>{{ $schedule->status === 'completed' ? 'Đã hoàn thành' : ($approvalStatuses[$schedule->approval_status] ?? $schedule->approval_status) }}</strong></div>
    @endif
</div>
@if($schedule->technical_note)
    <div class="om10-review-report"><small>GHI CHÚ KỸ THUẬT</small><p>{!! nl2br(e($schedule->technical_note)) !!}</p></div>
@endif
<div class="om10-review-files"><small>HỒ SƠ / MINH CHỨNG · {{ $schedule->attachments->count() }} FILE</small><div>@forelse($schedule->attachments as $file)<a href="{{ route('ky-thuat.maintenance.schedule-files.preview',$file) }}" target="_blank"><i class="bi bi-paperclip"></i><span>{{ \Illuminate\Support\Str::limit($file->original_name,35) }} · {{ strtoupper($file->category) }}</span></a>@empty<span>Chưa có file.</span>@endforelse</div></div>
