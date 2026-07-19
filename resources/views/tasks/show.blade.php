@extends('layouts.app')

@section('content')
<style>
    .task-show{min-height:100vh;background:#f4f7fb;padding-bottom:44px;font-size:13px}
    .page-shell{padding:22px}
    .page-head{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:14px}
    .page-head h4{margin:0;font-size:22px;font-weight:950;color:#0f172a}
    .page-head .desc{color:#64748b;font-size:12px;margin-top:2px}
    .btn-pill{border-radius:999px;font-weight:800;font-size:13px;padding:8px 16px}
    .task-hero{border-radius:24px;padding:22px;color:#fff;background:linear-gradient(135deg,#020617,#075985 55%,#0f766e);box-shadow:0 18px 44px rgba(15,23,42,.16);margin-bottom:14px}
    .task-hero h3{font-size:24px;font-weight:950;margin:0;letter-spacing:-.03em}
    .task-hero p{margin:6px 0 0;opacity:.85;font-size:13px}
    .info-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:14px}
    .info-box{background:#fff;border:1px solid #e5eaf1;border-radius:18px;padding:13px 15px;box-shadow:0 8px 22px rgba(15,23,42,.045)}
    .info-label{color:#64748b;font-size:12px;font-weight:800;margin-bottom:5px}
    .info-value{color:#0f172a;font-size:15px;font-weight:950}
    .layout{display:grid;grid-template-columns:1fr 330px;gap:16px;align-items:start}
    .soft-card{background:#fff;border:1px solid #e5eaf1;border-radius:20px;overflow:hidden;box-shadow:0 10px 28px rgba(15,23,42,.055);margin-bottom:14px}
    .card-head{padding:13px 16px;border-bottom:1px solid #e5eaf1;background:#fff;font-size:15px;font-weight:950;color:#0f172a}
    .card-head.dark{background:#0f172a;color:#fff;border-bottom:0}
    .card-body-custom{padding:16px}
    .soft-badge{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:900;white-space:nowrap}
    .st-new{background:#e0f2fe;color:#075985}.st-in_progress{background:#fef3c7;color:#92400e}.st-submitted{background:#ede9fe;color:#5b21b6}.st-revision{background:#fff7ed;color:#9a3412}.st-rejected{background:#fee2e2;color:#991b1b}.st-approved{background:#dcfce7;color:#166534}
    .pr-low{background:#f1f5f9;color:#475569}.pr-medium{background:#e0f2fe;color:#075985}.pr-high{background:#fee2e2;color:#991b1b}
    .content-title{font-size:20px;font-weight:950;color:#0f172a;margin-bottom:12px}
    .section-label{font-size:12px;font-weight:950;color:#0f172a;margin-bottom:6px;text-transform:uppercase;letter-spacing:.02em}
    .section-content{color:#475569;line-height:1.65;white-space:pre-line;background:#f8fafc;border:1px solid #e5eaf1;border-radius:14px;padding:12px}
    .progress{height:9px;border-radius:999px;background:#e2e8f0;overflow:hidden}.progress-bar{background:linear-gradient(90deg,#0ea5e9,#22c55e)}
    .file-grid{display:grid;gap:9px}
    .file-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:11px;border:1px solid #e5eaf1;border-radius:14px;background:#f8fafc}
    .file-left{display:flex;align-items:center;gap:10px;min-width:0}
    .file-icon{width:38px;height:38px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#e0f2fe;color:#0369a1;font-size:19px;flex:0 0 auto}
    .file-name{font-weight:900;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:430px}
    .file-meta{color:#64748b;font-size:12px}
    .form-label{font-size:12px;font-weight:850;color:#334155}
    .form-control,.form-select{border-radius:12px;border-color:#dbe3ee;font-size:13px}
    .side-row{display:flex;justify-content:space-between;gap:10px;padding:9px 0;border-bottom:1px dashed #e5eaf1}
    .side-row:last-child{border-bottom:0}
    .side-label{color:#64748b;font-size:12px;font-weight:800}
    .side-value{color:#0f172a;font-weight:900;text-align:right}
    .notice-mini{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:14px;padding:11px 12px;font-weight:700}
    @media(max-width:1200px){.layout{grid-template-columns:1fr}.info-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:768px){.page-shell{padding:14px}.page-head{flex-direction:column;align-items:flex-start}.info-grid{grid-template-columns:1fr}.file-item{align-items:flex-start;flex-direction:column}.file-name{max-width:260px}}

    .task-preview-backdrop{
        position:fixed;
        inset:0;
        background:rgba(15,23,42,.62);
        z-index:99999;
        display:none;
        align-items:center;
        justify-content:center;
        padding:18px;
    }
    .task-preview-backdrop.show{display:flex}
    .task-preview-modal{
        width:min(1160px,96vw);
        height:min(780px,92vh);
        background:#fff;
        border-radius:22px;
        overflow:hidden;
        border:1px solid #e2e8f0;
        box-shadow:0 32px 90px rgba(15,23,42,.36);
        display:flex;
        flex-direction:column;
    }
    .task-preview-head{
        height:58px;
        padding:0 16px;
        background:linear-gradient(135deg,#eff6ff,#ecfeff);
        border-bottom:1px solid #dbeafe;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
    }
    .task-preview-title{
        min-width:0;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }
    .task-preview-actions{
        display:flex;
        gap:8px;
        align-items:center;
        flex:0 0 auto;
    }
    .task-preview-action-btn{
        height:34px;
        border-radius:10px;
        padding:0 12px;
        border:1px solid #dbe3ef;
        background:#fff;
        color:#0f172a;
        font-size:12px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        display:inline-flex;
        align-items:center;
        justify-content:center;
    }
    .task-preview-action-btn.primary{
        background:#2563eb;
        color:#fff;
        border-color:#2563eb;
    }
    .task-preview-body{
        flex:1;
        min-height:0;
        background:#f8fafc;
    }
    .task-preview-frame{
        width:100%;
        height:100%;
        border:0;
        display:block;
        background:#fff;
    }
    .task-preview-btn-group{
        display:flex;
        gap:8px;
        align-items:center;
        flex-wrap:wrap;
    }
    .task-preview-btn-group form{margin:0;}
    @media(max-width:720px){
        .task-preview-modal{width:96vw;height:88vh;border-radius:16px}
        .task-preview-head{height:auto;min-height:58px;align-items:flex-start;flex-direction:column;padding:12px}
        .task-preview-actions{width:100%}
        .task-preview-action-btn{flex:1}
    }

</style>

@php
    $status = $task->status ?? 'new';
    $priority = $task->priority ?? 'medium';
    $progress = (int)($task->progress_percent ?? 0);

    $taskFiles = collect($attachments ?? [])->where('type', 'task');
    $resultFiles = collect($attachments ?? [])->where('type', 'result');

    $relatedTasks = collect($relatedTasks ?? [$task]);
    $assigneeNames = $relatedTasks
        ->map(fn($item) => optional($item->assignee)->name)
        ->filter()
        ->unique()
        ->values();

    $isSubmitted = $status === 'submitted';
    $isRevision = $status === 'revision';
    $isRejected = $status === 'rejected';
    $isApproved = $status === 'approved';
    $returnReason = $task->revision_reason ?? $task->rejection_reason ?? null;
    $canBossEdit = (bool)($canApprove ?? false);
    $canEditResult = ($canUpdate && !$isApproved) || $canBossEdit;
    $canDeleteTaskFiles = (bool)($canApprove ?? false);
@endphp

<div class="task-show">
    <div class="page-shell">

        <div class="page-head">
            <div>
                <h4>Chi tiết công việc</h4>
                <div class="desc">{{ $task->title }}</div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('tasks.my') }}" class="btn btn-outline-secondary btn-pill">
                    <i class="bi bi-arrow-left"></i> Việc của tôi
                </a>

                @if($canApprove)
                    <a href="{{ route('tasks.index') }}" class="btn btn-outline-primary btn-pill">
                        Danh sách việc
                    </a>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger border-0 shadow-sm rounded-4">{{ session('error') }}</div>
        @endif

        {{-- EGO_TASK_REVISION_ALERT_START --}}
        @if(($isRevision || $isRejected) && $returnReason)
            <div class="alert alert-warning border-0 shadow-sm rounded-4">
                <div class="fw-bold mb-1">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    Hồ sơ đã được trả về để sửa đổi / bổ sung
                </div>
                <div style="white-space:pre-line">{{ $returnReason }}</div>
                @if($canUpdate && !$isApproved)
                    <div class="small mt-2">
                        Vui lòng chỉnh sửa nội dung kết quả, bổ sung file nếu cần rồi bấm <b>Nộp lại kết quả</b>.
                    </div>
                @endif
            </div>
        @endif
        {{-- EGO_TASK_REVISION_ALERT_END --}}

{{-- EGO_TASK_REVISION_FILES_START --}}
@php
    $revisionFiles = collect();

    if (\Illuminate\Support\Facades\Schema::hasTable('task_attachments')) {
        $revisionFiles = \Illuminate\Support\Facades\DB::table('task_attachments')
            ->where('task_id', $task->id)
            ->where('type', 'revision')
            ->orderByDesc('id')
            ->get();
    }

    $canManageRevisionFiles = (bool)($canApprove ?? false) || (int)($task->requester_id ?? 0) === (int)auth()->id();
@endphp

@if($revisionFiles->count())
    <div class="mt-3 mb-3">
        <div class="fw-bold mb-2">
            <i class="bi bi-paperclip"></i>
            File yêu cầu sửa đổi / bổ sung
        </div>

        <div class="d-grid gap-2">
            @foreach($revisionFiles as $revisionFile)
                <div class="p-2 rounded-3 border bg-light">
                    <div class="small fw-semibold text-truncate mb-2">
                        <i class="bi bi-file-earmark"></i>
                        {{ $revisionFile->file_name }}
                    </div>

                    <div class="d-flex gap-2 flex-wrap align-items-center">
                        <a class="btn btn-sm btn-outline-primary btn-pill"
                           href="{{ asset('storage/' . $revisionFile->file_path) }}"
                           target="_blank">
                            <i class="bi bi-eye"></i> Xem
                        </a>

                        <a class="btn btn-sm btn-outline-secondary btn-pill"
                           href="{{ asset('storage/' . $revisionFile->file_path) }}"
                           download>
                            <i class="bi bi-download"></i> Tải
                        </a>

                        @if($canManageRevisionFiles)
                            <form method="POST"
                                  action="{{ route('tasks.attachments.replace', [$task, $revisionFile->id]) }}"
                                  enctype="multipart/form-data"
                                  class="m-0">
                                @csrf
                                <label class="btn btn-sm btn-outline-warning btn-pill mb-0">
                                    <i class="bi bi-pencil-square"></i> Sửa
                                    <input type="file"
                                           name="file"
                                           class="d-none"
                                           onchange="this.form.submit()">
                                </label>
                            </form>

                            <form method="POST"
                                  action="{{ route('tasks.attachments.destroy', [$task, $revisionFile->id]) }}"
                                  class="m-0"
                                  onsubmit="return confirm('Xóa file yêu cầu sửa đổi / bổ sung này?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-pill">
                                    <i class="bi bi-trash"></i> Xóa
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
{{-- EGO_TASK_REVISION_FILES_END --}}


        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="task-hero">
            <h3>{{ $task->title }}</h3>
            <p>
                Giao bởi <b>{{ optional($task->requester)->name ?? 'Không rõ' }}</b>
                · Người nhận <b>{{ $assigneeNames->count() ? $assigneeNames->implode(', ') : (optional($task->assignee)->name ?? 'Không rõ') }}</b>
            </p>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Người giao</div>
                <div class="info-value">{{ optional($task->requester)->name ?? '-' }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Người nhận</div>
                <div class="info-value">{{ $assigneeNames->count() ? $assigneeNames->implode(', ') : (optional($task->assignee)->name ?? '-') }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Hạn hoàn thành</div>
                <div class="info-value">{{ $task->due_at ?: '-' }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Ưu tiên</div>
                <div class="info-value">
                    <span class="soft-badge pr-{{ $priority }}">{{ $priorities[$priority] ?? $priority }}</span>
                </div>
            </div>

            <div class="info-box">
                <div class="info-label">Trạng thái</div>
                <div class="info-value">
                    <span class="soft-badge st-{{ $status }}">{{ $statuses[$status] ?? $status }}</span>
                </div>
            </div>
        </div>

        <div class="layout">
            <div>
                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-file-text"></i> Nội dung công việc
                    </div>

                    <div class="card-body-custom">
                        <div class="content-title">{{ $task->title }}</div>

                        <div class="mb-3">
                            <div class="section-label">Mô tả / yêu cầu</div>
                            <div class="section-content">{{ $task->description ?: 'Không có mô tả.' }}</div>
                        </div>

                        @if(!empty($task->link_url))
                            <div class="mb-3">
                                <div class="section-label">Link liên quan</div>
                                <a href="{{ $task->link_url }}" target="_blank" class="btn btn-outline-primary btn-pill">
                                    <i class="bi bi-link-45deg"></i> Mở link
                                </a>
                            </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="section-label mb-0">Tiến độ hiện tại</div>
                            <b>{{ $progress }}%</b>
                        </div>

                        <div class="progress">
                            <div class="progress-bar" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-paperclip"></i> File giao việc
                    </div>

                    <div class="card-body-custom">
                        <div class="file-grid">
                            @forelse($taskFiles as $file)
                                <div class="file-item">
                                    <div class="file-left">
                                        <div class="file-icon">
                                            @if(str_contains($file->file_mime ?? '', 'image'))
                                                <i class="bi bi-image"></i>
                                            @elseif(str_contains($file->file_mime ?? '', 'pdf'))
                                                <i class="bi bi-file-earmark-pdf"></i>
                                            @else
                                                <i class="bi bi-file-earmark"></i>
                                            @endif
                                        </div>

                                        <div style="min-width:0">
                                            <div class="file-name">{{ $file->file_name }}</div>
                                            <div class="file-meta">
                                                {{ $file->file_mime ?: 'file' }} · {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB
                                            </div>
                                        </div>
                                    </div>

                                    <div class="task-preview-btn-group">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary btn-pill task-preview-btn"
                                                data-file-url="{{ asset('storage/' . $file->file_path) }}"
                                                data-file-name="{{ $file->file_name }}"
                                                data-file-mime="{{ $file->file_mime }}">
                                            Xem
                                        </button>
                                        <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-pill">
                                            Tải
                                        </a>

                                        @if($canDeleteTaskFiles)
                                            <form method="POST"
                                                  action="{{ route('tasks.attachments.destroy', [$task, $file->id]) }}"
                                                  onsubmit="return confirm('Xóa file giao việc này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger btn-pill">
                                                    <i class="bi bi-trash"></i> Xóa
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="text-muted">Không có file giao việc.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if($isSubmitted || $isApproved || $task->result_note || $resultFiles->count())
                    <div class="soft-card">
                        <div class="card-head">
                            <i class="bi bi-upload"></i> Kết quả đã nộp
                        </div>

                        <div class="card-body-custom">
                            <div class="mb-3">
                                <div class="section-label">Ghi chú kết quả</div>
                                <div class="section-content">{{ $task->result_note ?: 'Chưa có ghi chú kết quả.' }}</div>
                            </div>

                            <div class="file-grid">
                                @forelse($resultFiles as $file)
                                    <div class="file-item">
                                        <div class="file-left">
                                            <div class="file-icon">
                                                @if(str_contains($file->file_mime ?? '', 'image'))
                                                    <i class="bi bi-image"></i>
                                                @elseif(str_contains($file->file_mime ?? '', 'pdf'))
                                                    <i class="bi bi-file-earmark-pdf"></i>
                                                @else
                                                    <i class="bi bi-file-earmark"></i>
                                                @endif
                                            </div>

                                            <div style="min-width:0">
                                                <div class="file-name">{{ $file->file_name }}</div>
                                                <div class="file-meta">
                                                    {{ $file->file_mime ?: 'file' }} · {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex gap-2 flex-wrap">
                                            <div class="task-preview-btn-group">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary btn-pill task-preview-btn"
                                                    data-file-url="{{ asset('storage/' . $file->file_path) }}"
                                                    data-file-name="{{ $file->file_name }}"
                                                    data-file-mime="{{ $file->file_mime }}">
                                                Xem
                                            </button>
                                            <a href="{{ asset('storage/' . $file->file_path) }}" target="_blank" class="btn btn-sm btn-outline-secondary btn-pill">
                                                Tải
                                            </a>
                                        </div>

                                            @if($canEditResult)
                                                <form method="POST"
                                                      action="{{ route('tasks.attachments.destroy', [$task, $file->id]) }}"
                                                      onsubmit="return confirm('Xóa file kết quả này?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger btn-pill">
                                                        <i class="bi bi-trash"></i> Xóa
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-muted">Chưa có file kết quả.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif

                @if($canEditResult)
                    <div class="soft-card" id="result-form">
                        <div class="card-head dark">
                            <i class="bi {{ ($isSubmitted || $isApproved) ? 'bi-pencil-square' : 'bi-send-check' }}"></i>
                            {{ $isApproved ? 'Sửa kết quả đã duyệt' : ($isSubmitted ? 'Sửa bài nộp' : 'Nộp kết quả') }}
                        </div>

                        <div class="card-body-custom">
                            @if($isApproved && $canBossEdit)
                                <div class="notice-mini mb-3">
                                    Công việc đã duyệt. Admin / Ban giám đốc vẫn có thể sửa lại kết quả khi cần.
                                </div>
                            @elseif($isSubmitted)
                                <div class="notice-mini mb-3">
                                    Nếu nộp sai, sửa lại ghi chú hoặc thêm file mới rồi bấm cập nhật. Khi đã duyệt, nhân viên sẽ không sửa được nữa.
                                </div>
                            @endif

                            {{-- EGO_TASK_REVISION_FORM_NOTICE_START --}}
                            @if($isRevision || $isRejected)
                                <div class="notice-mini mb-3">
                                    Hồ sơ đang cần sửa đổi / bổ sung. Sau khi cập nhật, bấm nộp lại để sếp duyệt lại.
                                </div>
                            @endif
                            {{-- EGO_TASK_REVISION_FORM_NOTICE_END --}}

                            <form method="POST" action="{{ route('tasks.submit', $task) }}" enctype="multipart/form-data">
                                @csrf

                                <div class="mb-3">
                                    <label class="form-label">Tiến độ hoàn thành (%)</label>
                                    <input type="number"
                                           name="progress_percent"
                                           class="form-control"
                                           value="{{ old('progress_percent', $progress ?: 100) }}"
                                           min="0"
                                           max="100">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Ghi chú kết quả</label>
                                    <textarea name="result_note"
                                              rows="4"
                                              class="form-control"
                                              placeholder="Nhập nội dung kết quả đã làm...">{{ old('result_note', $task->result_note) }}</textarea>
                                </div>

                                @if($resultFiles->count())
                                    <div class="form-check mb-3">
                                        <input class="form-check-input"
                                               type="checkbox"
                                               name="clear_result_attachments"
                                               value="1"
                                               id="clear_result_attachments">
                                        <label class="form-check-label small fw-bold" for="clear_result_attachments">
                                            Xóa toàn bộ file kết quả cũ trước khi lưu file mới
                                        </label>
                                    </div>
                                @endif

                                <div class="mb-3">
                                    <label class="form-label">{{ ($isSubmitted || $isApproved) ? 'Thêm file / ảnh mới nếu cần' : 'File / ảnh kết quả' }}</label>
                                    <input type="file" name="result_attachments[]" class="form-control" multiple>
                                </div>

                                <button class="btn {{ ($isSubmitted || $isApproved) ? 'btn-warning' : 'btn-success' }} w-100 btn-pill">
                                    <i class="bi bi-save2"></i>
                                    {{ $isApproved ? 'Cập nhật kết quả đã duyệt' : (($isRevision || $isRejected) ? 'Nộp lại kết quả' : ($isSubmitted ? 'Cập nhật lại bài nộp' : 'Nộp kết quả')) }}
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>

            <div>
                @if($canUpdate && !$isSubmitted && !$isApproved)
                    <div class="soft-card">
                        <div class="card-head dark">
                            <i class="bi bi-graph-up"></i> Cập nhật nhanh
                        </div>

                        <div class="card-body-custom">
                            <form method="POST" action="{{ route('tasks.status', $task) }}">
                                @csrf
                                @method('PATCH')

                                <div class="mb-3">
                                    <label class="form-label">Trạng thái</label>
                                    <select name="status" class="form-select">
                                        <option value="new" {{ $status === 'new' ? 'selected' : '' }}>Mới giao</option>
                                        <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>Đang làm</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Tiến độ (%)</label>
                                    <input type="number" name="progress_percent" class="form-control" value="{{ $progress }}" min="0" max="100">
                                </div>

                                <button class="btn btn-primary w-100 btn-pill">
                                    <i class="bi bi-save"></i> Lưu tiến độ
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-info-circle"></i> Thông tin nhanh
                    </div>

                    <div class="card-body-custom">
                        <div class="side-row">
                            <div class="side-label">Mã công việc</div>
                            <div class="side-value">#{{ $task->id }}</div>
                        </div>
                        <div class="side-row">
                            <div class="side-label">Ngày giao</div>
                            <div class="side-value">{{ $task->created_at }}</div>
                        </div>
                        <div class="side-row">
                            <div class="side-label">Ngày nộp</div>
                            <div class="side-value">{{ $task->completed_at ?: '-' }}</div>
                        </div>
                        <div class="side-row">
                            <div class="side-label">Cập nhật cuối</div>
                            <div class="side-value">{{ $task->updated_at }}</div>
                        </div>
                    </div>
                </div>

                @if($canApprove)
                    <div class="soft-card">
                        <div class="card-head dark">
                            <i class="bi bi-check2-square"></i> Duyệt công việc
                        </div>

                        <div class="card-body-custom">
                            @if($isApproved)
                                <div class="alert alert-success rounded-4 mb-0">
                                    Công việc này đã được duyệt hoàn thành.
                                </div>
                            @elseif($isSubmitted)
                                <form method="POST" action="{{ route('tasks.approve', $task) }}">
                                    @csrf
                                    <button class="btn btn-success w-100 btn-pill">
                                        <i class="bi bi-check-circle"></i> Duyệt hoàn thành
                                    </button>
                                </form>

                                <hr>

                                
{{-- EGO_TASK_REVISION_FILES_IN_APPROVAL_CARD_START --}}
@if(isset($revisionFiles) && $revisionFiles->count())
    <div class="mb-3 small text-muted">
        Các file yêu cầu sửa đổi đang nằm ở mục <b>File yêu cầu sửa đổi / bổ sung</b> bên trên.
    </div>
@endif
{{-- EGO_TASK_REVISION_FILES_IN_APPROVAL_CARD_END --}}
<form method="POST"
                                      action="{{ route('tasks.return-revision', $task) }}"
                                      onsubmit="return confirm('Trả hồ sơ này cho nhân viên sửa đổi / bổ sung?')" enctype="multipart/form-data">
                                    @csrf

                                    <div class="mb-3">
                                        <label class="form-label">Lý do từ chối / yêu cầu sửa đổi bổ sung</label>
                                        <textarea name="revision_reason"
                                                  rows="4"
                                                  class="form-control"
                                                  required
                                                  placeholder="Ví dụ: Thiếu file báo cáo, nội dung chưa đúng yêu cầu, cần bổ sung hình ảnh minh chứng...">{{ old('revision_reason') }}</textarea>
                                    <div class="mb-3">
                                        <label class="form-label">File đính kèm yêu cầu sửa đổi / bổ sung</label>
                                        <input type="file"
                                               name="revision_attachments[]"
                                               class="form-control"
                                               multiple>
                                        <div class="form-text">
                                            Có thể gắn báo cáo mẫu, hình ảnh, file Excel/PDF hoặc tài liệu cần nhân viên chỉnh theo.
                                        </div>
                                    </div>

                                    </div>

                                    <button class="btn btn-warning w-100 btn-pill">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                        Trả hồ sơ sửa đổi / bổ sung
                                    </button>
                                </form>
                            @elseif($isRevision || $isRejected)
                                <div class="alert alert-warning rounded-4 mb-0">
                                    Đã trả hồ sơ cho nhân viên sửa đổi / bổ sung.
                                    @if($returnReason)
                                        <div class="mt-2 small" style="white-space:pre-line">
                                            <b>Lý do:</b> {{ $returnReason }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <div class="alert alert-info rounded-4 mb-0">
                                    Nhân viên chưa nộp kết quả.
                                </div>
                            @endif

                            <hr>

                            <form method="POST"
                                  action="{{ route('tasks.destroy', $task) }}"
                                  onsubmit="return confirm('Bạn chắc chắn muốn xóa công việc này?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger w-100 btn-pill">
                                    <i class="bi bi-trash"></i> Xóa công việc
                                </button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div id="taskPreviewBackdrop" class="task-preview-backdrop" onclick="closeTaskPreview(event)">
    <div class="task-preview-modal" onclick="event.stopPropagation()">
        <div class="task-preview-head">
            <div id="taskPreviewTitle" class="task-preview-title">Xem file</div>
            <div class="task-preview-actions">
                <a id="taskPreviewDownload" class="task-preview-action-btn primary" href="#" target="_blank" rel="noopener">
                    Tải file
                </a>
                <button type="button" class="task-preview-action-btn" onclick="hideTaskPreview()">Đóng</button>
            </div>
        </div>
        <div class="task-preview-body">
            <iframe id="taskPreviewFrame" class="task-preview-frame" src=""></iframe>
        </div>
    </div>
</div>

<script>
    function buildPreviewUrl(url, mime){
        const absoluteUrl = new URL(url, window.location.origin).href;
        const lower = (absoluteUrl + ' ' + (mime || '')).toLowerCase();

        const isOffice =
            lower.includes('.doc') ||
            lower.includes('.xls') ||
            lower.includes('.ppt') ||
            lower.includes('spreadsheet') ||
            lower.includes('wordprocessingml') ||
            lower.includes('presentationml') ||
            lower.includes('ms-excel') ||
            lower.includes('msword') ||
            lower.includes('powerpoint');

        if(isOffice){
            return 'https://view.officeapps.live.com/op/embed.aspx?src=' + encodeURIComponent(absoluteUrl);
        }

        return absoluteUrl;
    }

    function openTaskPreview(url, name, mime){
        const backdrop = document.getElementById('taskPreviewBackdrop');
        const frame = document.getElementById('taskPreviewFrame');
        const title = document.getElementById('taskPreviewTitle');
        const download = document.getElementById('taskPreviewDownload');

        const absoluteUrl = new URL(url, window.location.origin).href;

        title.textContent = name || 'Xem file';
        download.href = absoluteUrl;
        frame.src = buildPreviewUrl(absoluteUrl, mime);
        backdrop.classList.add('show');
    }

    function hideTaskPreview(){
        const backdrop = document.getElementById('taskPreviewBackdrop');
        const frame = document.getElementById('taskPreviewFrame');

        frame.src = '';
        backdrop.classList.remove('show');
    }

    function closeTaskPreview(event){
        if(event.target && event.target.id === 'taskPreviewBackdrop'){
            hideTaskPreview();
        }
    }

    document.addEventListener('click', function(event){
        const btn = event.target.closest('.task-preview-btn');

        if(!btn){
            return;
        }

        event.preventDefault();

        openTaskPreview(
            btn.dataset.fileUrl,
            btn.dataset.fileName,
            btn.dataset.fileMime
        );
    });

    document.addEventListener('keydown', function(event){
        if(event.key === 'Escape'){
            hideTaskPreview();
        }
    });
</script>

@endsection