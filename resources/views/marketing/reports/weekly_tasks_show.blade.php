@extends('layouts.app')

@section('title', 'Chi tiết công việc')

@section('content')
@php
    $fmtDate = function ($v) {
        if (empty($v)) return '-';
        try { return \Carbon\Carbon::parse($v)->format('Y-m-d'); } catch (\Throwable $e) { return (string)$v; }
    };

    $assignees = $task->assignees ?? [];
    if (!is_array($assignees)) $assignees = [];
    if (empty($assignees) && !empty($task->assignee)) {
        $assignees = array_values(array_filter(array_map('trim', explode(',', $task->assignee))));
    }

    $links = $task->links ?? [];
    if (!is_array($links)) $links = [];

    $attachments = $task->attachments ?? [];
    if (!is_array($attachments)) $attachments = [];
@endphp

<div class="container-fluid px-4 mt-3 weekly-task-show">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h3 class="fw-bold mb-1">Chi tiết công việc</h3>
            <div class="text-muted">#{{ $task->id }} • {{ $task->title }}</div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('marketing.reports.weekly-tasks.edit', $task->id) }}" class="btn btn-ego-soft wt-btn">
                <i class="bi bi-pencil-square"></i> Sửa
            </a>
            <a href="{{ route('marketing.reports.weekly-tasks') }}" class="btn btn-ego-soft wt-btn">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>

    <div class="card wt-card">
        <div class="card-body p-3 p-md-4">

            <div class="row g-3">

                <div class="col-lg-8">
                    <div class="wt-block">
                        <div class="wt-title">Thông tin</div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <div class="wt-k">Tên công việc</div>
                                <div class="wt-v">{{ $task->title }}</div>
                            </div>

                            <div class="col-md-3">
                                <div class="wt-k">Priority</div>
                                <div class="wt-v text-uppercase">{{ $task->priority ?? '-' }}</div>
                            </div>

                            <div class="col-md-3">
                                <div class="wt-k">Hạng mục</div>
                                <div class="wt-v">{{ $task->category ?? '-' }}</div>
                            </div>

                            <div class="col-md-4">
                                <div class="wt-k">Trạng thái</div>
                                <div class="wt-v text-uppercase">{{ $task->status ?? '-' }}</div>
                            </div>

                            <div class="col-md-4">
                                <div class="wt-k">Tiến độ</div>
                                <div class="wt-v">{{ (int)($task->progress ?? 0) }}%</div>
                            </div>

                            <div class="col-md-4">
                                <div class="wt-k">Người phụ trách</div>
                                <div class="wt-v">
                                    @if(count($assignees))
                                        @foreach($assignees as $a)
                                            <span class="wt-pill">{{ $a }}</span>
                                        @endforeach
                                    @else
                                        -
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="wt-k">Ngày bắt đầu</div>
                                <div class="wt-v">{{ $fmtDate($task->start_date) }}</div>
                            </div>

                            <div class="col-md-6">
                                <div class="wt-k">Hạn</div>
                                <div class="wt-v">{{ $fmtDate($task->due_date) }}</div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="wt-k">Ghi chú</div>
                            <div class="wt-note">{{ $task->note ?? '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="wt-block h-100">
                        <div class="wt-title">Link & File</div>

                        <div class="mt-2">
                            <div class="wt-k">Links</div>
                            @if(count($links))
                                <div class="wt-list">
                                    @foreach($links as $l)
                                        <a class="wt-a" target="_blank" href="{{ $l }}">
                                            <i class="bi bi-link-45deg"></i> {{ $l }}
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small">Chưa có link.</div>
                            @endif
                        </div>

                        <div class="mt-3">
                            <div class="wt-k">Attachments</div>
                            @if(count($attachments))
                                <div class="wt-list">
                                    @foreach($attachments as $a)
                                        @php
                                            $name = $a['name'] ?? ($a['path'] ?? 'file');
                                            $path = $a['path'] ?? null;
                                        @endphp
                                        @if($path)
                                            <a class="wt-a" target="_blank" href="{{ asset('storage/'.$path) }}">
                                                <i class="bi bi-paperclip"></i> {{ $name }}
                                            </a>
                                        @else
                                            <div class="text-muted small">{{ $name }}</div>
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="text-muted small">Chưa có file.</div>
                            @endif
                        </div>

                    </div>
                </div>

            </div>

        </div>
    </div>

</div>
@endsection

@push('styles')
{{-- ✅ Font Inter --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<style>
.weekly-task-show{
    --ego: #0E7C86;
    --ego2:#0B5E66;
    --border: rgba(12, 92, 100, .10);
    --text: #0f172a;
    --muted:#64748b;

    /* ✅ Font đẹp */
    font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
}

/* ✅ giảm “đậm quá” cho đẹp */
.weekly-task-show h3{
    letter-spacing: -0.02em;
}

.weekly-task-show .wt-card{
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow:hidden;
    background:#fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .04);
}

.weekly-task-show .wt-btn{
    height:40px;
    border-radius:12px;
    font-weight:700;
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:0 14px;
}

.weekly-task-show .btn-ego-soft{
    background: rgba(14, 124, 134, .10);
    border: 1px solid var(--border);
    color: var(--ego2);
}

.weekly-task-show .wt-block{
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 14px;
    background: linear-gradient(135deg, rgba(14,124,134,.06), rgba(14,124,134,.02));
}

.weekly-task-show .wt-title{
    font-weight:800;
    color:var(--text);
    font-size: 15px;
}

.weekly-task-show .wt-k{
    font-size:11px;
    color:var(--muted);
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:.35px;
}

.weekly-task-show .wt-v{
    font-weight:700;
    color:var(--text);
    margin-top:4px;
    font-size: 14px;
}

.weekly-task-show .wt-note{
    margin-top:6px;
    background:#fff;
    border:1px solid rgba(15,23,42,.06);
    border-radius:12px;
    padding:10px;
    min-height:44px;
    font-weight:500;
    color: #111827;
}

.weekly-task-show .wt-pill{
    display:inline-flex;
    align-items:center;
    padding:6px 10px;
    border-radius:999px;
    margin: 0 8px 8px 0;
    background:#fff;
    border:1px solid var(--border);
    font-weight:700;
    font-size: 12px;
}

.weekly-task-show .wt-list{
    display:flex;
    flex-direction:column;
    gap:8px;
    margin-top:8px;
}

.weekly-task-show .wt-a{
    display:flex;
    align-items:center;
    gap:8px;
    padding:10px 12px;
    border-radius:12px;
    font-weight:600;
    background:#fff;
    border:1px solid rgba(15,23,42,.06);
    text-decoration:none;
    color:var(--text);
}
.weekly-task-show .wt-a:hover{ filter: brightness(.98); }
</style>
@endpush