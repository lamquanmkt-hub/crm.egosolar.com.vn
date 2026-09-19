@extends('layouts.app')

@section('title', 'Duyệt bảo hành & O&M')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/technical-maintenance-v10.css') }}?v={{ file_exists(public_path('css/technical-maintenance-v10.css')) ? filemtime(public_path('css/technical-maintenance-v10.css')) : time() }}">
@endsection

@section('content')
@php
    $selected = $selectedSchedule;
    $selectedPending = $selected
        && $selected->status === 'pending_approval'
        && $selected->approval_status === 'pending';
    $selectedOverdue = $selectedPending
        && $selected->submitted_at
        && $selected->submitted_at->lt(now()->subHours(24));
    $selectedCanApprove = $selectedPending && auth()->user()->can('approve', $selected);
    $selectedChecklistTotal = $selected?->checklistItems?->count() ?? 0;
    $selectedChecklistDone = $selected?->checklistItems?->where('is_done', true)->count() ?? 0;
    $selectedEvidence = $selected?->attachments?->whereIn('category', ['before','during','after','report','fault','serial','checklist']) ?? collect();
@endphp

<div class="ego-container om10-page om11-approval-page">
    <nav class="om10-breadcrumb">
        <a href="{{ route('ky-thuat.maintenance.index') }}">Bảo hành & O&M</a>
        <i class="bi bi-chevron-right"></i>
        <span>Hàng đợi phê duyệt</span>
    </nav>

    <header class="om11-approval-head">
        <div class="om11-head-copy">
            <span class="om11-head-icon"><i class="bi bi-shield-check"></i></span>
            <div>
                <div class="om10-eyebrow">KHÔNG GIAN ADMIN</div>
                <h1>Duyệt từng đợt bảo hành</h1>
                <p>Mỗi hồ sơ bên dưới là một đợt độc lập. Phê duyệt đợt hiện tại không làm thay đổi các đợt còn lại của công trình.</p>
            </div>
        </div>
        <a class="om10-btn light" href="{{ route('ky-thuat.maintenance.index') }}"><i class="bi bi-arrow-left"></i> Quay lại điều hành</a>
    </header>

    @if(session('success'))<div class="om10-alert success"><i class="bi bi-check-circle-fill"></i><span>{{ session('success') }}</span></div>@endif
    @if(session('error'))<div class="om10-alert danger"><i class="bi bi-exclamation-octagon-fill"></i><span>{{ session('error') }}</span></div>@endif
    @if($errors->any())
        <div class="om10-alert danger"><i class="bi bi-exclamation-triangle-fill"></i><div><strong>Chưa thể thực hiện</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <div class="om11-approval-kpis">
        <a class="{{ $approvalView === 'pending' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.approval.index', ['approval_view' => 'pending']) }}">
            <span><small>Chờ tôi duyệt</small><strong>{{ number_format($approvalSummary['pending'] ?? 0) }}</strong></span>
            <i class="bi bi-inbox"></i>
        </a>
        <a class="{{ request('sla') === 'overdue' ? 'active danger' : '' }}" href="{{ route('ky-thuat.maintenance.approval.index', ['approval_view' => 'pending', 'sla' => 'overdue']) }}">
            <span><small>Quá 24 giờ</small><strong>{{ number_format($approvalSummary['overdue'] ?? 0) }}</strong></span>
            <i class="bi bi-alarm"></i>
        </a>
        <a class="{{ $approvalView === 'revision' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.approval.index', ['approval_view' => 'revision']) }}">
            <span><small>Đang bổ sung</small><strong>{{ number_format($approvalSummary['revision'] ?? 0) }}</strong></span>
            <i class="bi bi-arrow-repeat"></i>
        </a>
        <a class="{{ $approvalView === 'approved' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.approval.index', ['approval_view' => 'approved']) }}">
            <span><small>Đã duyệt tháng này</small><strong>{{ number_format($approvalSummary['approved_month'] ?? 0) }}</strong></span>
            <i class="bi bi-patch-check"></i>
        </a>
    </div>

    <div class="om11-approval-shell">
        <section class="om10-card om11-queue-card">
            <div class="om11-queue-toolbar">
                <div class="om10-tabs">
                    <a class="{{ $approvalView === 'pending' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.approval.index', ['approval_view' => 'pending']) }}">Chờ duyệt</a>
                    <a class="{{ $approvalView === 'revision' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.approval.index', ['approval_view' => 'revision']) }}">Cần bổ sung</a>
                    <a class="{{ $approvalView === 'approved' ? 'active' : '' }}" href="{{ route('ky-thuat.maintenance.approval.index', ['approval_view' => 'approved']) }}">Đã duyệt</a>
                </div>
                <form method="GET" action="{{ route('ky-thuat.maintenance.approval.index') }}" class="om11-approval-filter">
                    <input type="hidden" name="approval_view" value="{{ $approvalView }}">
                    @if(request('sla'))<input type="hidden" name="sla" value="{{ request('sla') }}">@endif
                    <label><i class="bi bi-search"></i><input type="search" name="q" value="{{ request('q') }}" placeholder="Tên công trình hoặc mã hồ sơ"></label>
                    <select name="priority">
                        <option value="">Mọi ưu tiên</option>
                        @foreach($priorities as $key => $label)<option value="{{ $key }}" @selected(request('priority') === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <button class="om10-icon-btn" type="submit" aria-label="Lọc danh sách"><i class="bi bi-funnel"></i></button>
                </form>
            </div>

            <div class="om11-queue-caption">
                <div><strong>{{ number_format($approvalQueue->total()) }} hồ sơ</strong><span>Mỗi dòng tương ứng một đợt bảo hành</span></div>
                @if($approvalView === 'pending')<small>Sắp xếp hồ sơ gửi lâu nhất trước</small>@endif
            </div>

            <div class="om11-table-wrap">
                <table class="om11-approval-table">
                    <thead><tr><th>Công trình</th><th>Đợt</th><th>Hồ sơ</th><th>Gửi duyệt</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody>
                        @forelse($approvalQueue as $item)
                            @php
                                $isSelected = $selected && (int) $selected->id === (int) $item->id;
                                $isOverdue = $item->status === 'pending_approval'
                                    && $item->submitted_at
                                    && $item->submitted_at->lt(now()->subHours(24));
                                $rowStatus = $item->approval_status === 'approved'
                                    ? 'Đã duyệt'
                                    : (in_array($item->approval_status, ['revision_requested','rejected'], true) ? 'Cần bổ sung' : ($isOverdue ? 'Quá 24 giờ' : 'Chờ duyệt'));
                                $rowTone = $item->approval_status === 'approved'
                                    ? 'done'
                                    : (in_array($item->approval_status, ['revision_requested','rejected'], true) ? 'revision' : 'approval');
                                $selectUrl = route('ky-thuat.maintenance.approval.index', array_merge(
                                    request()->except(['page', 'selected']),
                                    ['selected' => $item->id]
                                ));
                            @endphp
                            <tr class="{{ $isSelected ? 'selected' : '' }} {{ $isOverdue ? 'overdue' : '' }}">
                                <td>
                                    <a class="om11-project-link" href="{{ $selectUrl }}">
                                        <strong>{{ $item->site?->name ?: $item->site_name ?: 'Công trình chưa đặt tên' }}</strong>
                                        <span>{{ $item->schedule_code ?: '#'.$item->id }} · {{ $item->customer_name ?: $item->site?->contact_name ?: 'Chưa có khách hàng' }}</span>
                                    </a>
                                </td>
                                <td><strong>{{ (int) ($item->round_no ?: 1) }}/{{ (int) ($item->total_rounds ?: 1) }}</strong><span>{{ optional($item->scheduled_date)->format('d/m/Y') ?: '—' }}</span></td>
                                <td><strong>{{ (int) $item->checklist_done }}/{{ (int) $item->checklist_total }}</strong><span>{{ (int) $item->evidence_count }} minh chứng</span></td>
                                <td><strong>{{ optional($item->submitted_at)->format('d/m/Y') ?: '—' }}</strong><span>{{ optional($item->submitted_at)->format('H:i') ?: '' }} · {{ $item->submitter?->name ?: 'Không rõ' }}</span></td>
                                <td><span class="om10-status {{ $rowTone }}">{{ $rowStatus }}</span></td>
                                <td><a class="om11-row-open" href="{{ $selectUrl }}" aria-label="Mở hồ sơ {{ $item->schedule_code }}"><i class="bi bi-chevron-right"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><div class="om10-empty"><i class="bi bi-inbox"></i><strong>Không có hồ sơ trong hàng đợi</strong><span>Hồ sơ sẽ xuất hiện khi kỹ thuật viên gửi duyệt từng đợt.</span></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($approvalQueue->hasPages())<div class="om11-pagination">{{ $approvalQueue->onEachSide(1)->links() }}</div>@endif
        </section>

        <aside class="om10-card om11-review-panel">
            @if($selected)
                <div class="om11-review-head">
                    <div>
                        <div class="om10-badges">
                            <span>{{ $types[$selected->type] ?? $selected->type }}</span>
                            <span>Đợt {{ (int) ($selected->round_no ?: 1) }}/{{ (int) ($selected->total_rounds ?: 1) }}</span>
                        </div>
                        <h2>{{ $selected->site?->name ?: $selected->site_name ?: 'Công trình' }}</h2>
                        <p>{{ $selected->schedule_code ?: '#'.$selected->id }} · {{ $selected->customer_name ?: $selected->site?->contact_name ?: 'Chưa có khách hàng' }}</p>
                    </div>
                    @if($selectedPending)<span class="om10-status {{ $selectedOverdue ? 'revision' : 'approval' }}">{{ $selectedOverdue ? 'Quá 24 giờ' : 'Chờ duyệt' }}</span>@elseif($selected->approval_status === 'approved')<span class="om10-status done">Đã duyệt</span>@else<span class="om10-status revision">Cần bổ sung</span>@endif
                </div>

                <div class="om11-review-facts">
                    <div><small>Thực hiện</small><strong>{{ optional($selected->scheduled_date)->format('d/m/Y') ?: '—' }}</strong></div>
                    <div><small>Nhóm kỹ thuật</small><strong>{{ $selected->assignees->pluck('user.name')->filter()->implode(', ') ?: 'Chưa phân công' }}</strong></div>
                    <div><small>Checklist</small><strong>{{ $selectedChecklistDone }}/{{ $selectedChecklistTotal }} hoàn thành</strong></div>
                    <div><small>Minh chứng</small><strong>{{ $selectedEvidence->count() }} file/ảnh</strong></div>
                </div>

                @if($selected->technical_note)
                <section class="om11-review-section">
                    <div class="om11-section-title"><span>Ghi chú kỹ thuật</span><span class="om10-status done">Hồ sơ hiện trường</span></div>
                    <div class="om11-internal-note"><strong>Ghi chú nội bộ</strong><span>{{ $selected->technical_note }}</span></div>
                </section>
                @endif

                <section class="om11-review-section">
                    <div class="om11-section-title"><span>Checklist hiện trường</span><strong>{{ $selectedChecklistDone }}/{{ $selectedChecklistTotal }}</strong></div>
                    <div class="om11-review-checklist">
                        @foreach($selected->checklistItems as $item)
                            @php $itemEvidenceReady = !$item->requires_evidence || $item->attachments->count() >= max(1,(int)$item->min_evidence); @endphp
                            <div class="{{ $item->is_done && $itemEvidenceReady ? 'done' : 'missing' }}"><i class="bi {{ $item->is_done && $itemEvidenceReady ? 'bi-check-circle-fill' : 'bi-x-circle' }}"></i><span>{{ $item->label }} @if($item->requires_evidence)<small>· {{ $item->attachments->count() }}/{{ max(1,(int)$item->min_evidence) }} file</small>@endif</span></div>
                        @endforeach
                    </div>
                </section>

                <section class="om11-review-section">
                    <div class="om11-section-title"><span>Minh chứng</span><strong>{{ $selectedEvidence->count() }} file</strong></div>
                    <div class="om11-evidence-list">
                        @forelse($selectedEvidence as $file)
                            <a href="{{ route('ky-thuat.maintenance.schedule-files.preview', $file) }}" target="_blank">
                                <i class="bi {{ str_starts_with((string) $file->mime_type, 'image/') ? 'bi-image' : 'bi-file-earmark-text' }}"></i>
                                <span><strong>{{ \Illuminate\Support\Str::limit($file->original_name, 34) }}</strong><small>{{ strtoupper($file->category) }} · {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB</small></span>
                                <i class="bi bi-box-arrow-up-right"></i>
                            </a>
                        @empty
                            <div class="om10-empty mini">Chưa có minh chứng.</div>
                        @endforelse
                    </div>
                </section>

                @if($selectedCanApprove)
                    <div class="om11-decision-box">
                        <div class="om11-decision-note"><i class="bi bi-info-circle"></i><span>Quyết định chỉ áp dụng cho <strong>đợt {{ (int) ($selected->round_no ?: 1) }}/{{ (int) ($selected->total_rounds ?: 1) }}</strong>. Các đợt khác không thay đổi.</span></div>
                        <form method="POST" action="{{ route('ky-thuat.maintenance.approval.approve', $selected) }}" data-om11-confirm="Phê duyệt riêng đợt {{ (int) ($selected->round_no ?: 1) }}/{{ (int) ($selected->total_rounds ?: 1) }}?">@csrf
                            <label><span>Nhận xét duyệt</span><textarea name="comment" rows="2" placeholder="Không bắt buộc"></textarea></label>
                            <button class="om10-btn success" type="submit"><i class="bi bi-patch-check"></i> Phê duyệt đợt {{ (int) ($selected->round_no ?: 1) }}/{{ (int) ($selected->total_rounds ?: 1) }}</button>
                        </form>
                        <form method="POST" action="{{ route('ky-thuat.maintenance.approval.revision', $selected) }}" data-om11-confirm="Gửi yêu cầu bổ sung cho đợt này?">@csrf
                            <label><span>Nội dung cần bổ sung</span><textarea name="comment" rows="3" required placeholder="Nêu rõ checklist hoặc minh chứng cần bổ sung"></textarea></label>
                            <button class="om10-btn danger-outline" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Yêu cầu bổ sung</button>
                        </form>
                    </div>
                @elseif($selected->approval_status === 'approved')
                    <div class="om11-approved-box"><i class="bi bi-patch-check-fill"></i><div><strong>Đợt này đã được phê duyệt</strong><span>{{ $selected->approver?->name ?: 'Admin' }} · {{ optional($selected->approved_at)->format('d/m/Y H:i') ?: '—' }}</span></div></div>
                @elseif($selected->approval_note)
                    <div class="om11-revision-box"><i class="bi bi-arrow-repeat"></i><div><strong>Nội dung yêu cầu bổ sung</strong><span>{{ $selected->approval_note }}</span></div></div>
                @endif

                <div class="om11-review-footer">
                    <a href="{{ route('ky-thuat.maintenance.show', $selected) }}"><i class="bi bi-folder2-open"></i> Mở toàn bộ hồ sơ đợt</a>
                    @if($selected->site_id)<a href="{{ route('ky-thuat.maintenance.site', ['site' => $selected->site_id]) }}">Hồ sơ công trình <i class="bi bi-arrow-right"></i></a>@endif
                </div>
            @else
                <div class="om10-empty"><i class="bi bi-file-earmark-check"></i><strong>Chọn một hồ sơ để xem</strong><span>Checklist, minh chứng và nút duyệt sẽ hiển thị tại đây.</span></div>
            @endif
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/technical-maintenance-v10.js') }}?v={{ file_exists(public_path('js/technical-maintenance-v10.js')) ? filemtime(public_path('js/technical-maintenance-v10.js')) : time() }}"></script>
@endsection
