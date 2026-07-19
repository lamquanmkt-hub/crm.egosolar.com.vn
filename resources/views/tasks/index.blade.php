@extends('layouts.app')

@section('content')
<style>
    .task-page{min-height:100vh;background:#f4f7fb;padding-bottom:42px;font-size:12px}
    .page-shell{padding:18px}
    .task-hero{border-radius:22px;padding:20px 22px;color:#fff;background:radial-gradient(700px 260px at 88% 0%,rgba(34,211,238,.25),transparent 60%),linear-gradient(135deg,#020617,#075985 58%,#0f766e);box-shadow:0 16px 38px rgba(15,23,42,.16);margin-bottom:12px}
    .hero-top{display:flex;justify-content:space-between;align-items:center;gap:14px}
    .task-hero h1{font-size:24px;font-weight:950;letter-spacing:-.03em;margin:0}
    .task-hero p{font-size:12px;opacity:.86;margin:4px 0 0;font-weight:650}
    .btn-pill{border-radius:999px;font-weight:850;font-size:12px;padding:7px 13px}

    .summary-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:12px}
    .summary-card{background:#fff;border:1px solid #e5eaf1;border-radius:16px;padding:12px 14px;box-shadow:0 9px 24px rgba(15,23,42,.045)}
    .summary-label{color:#64748b;font-size:11px;font-weight:850;margin-bottom:3px}
    .summary-value{font-size:24px;font-weight:950;line-height:1;letter-spacing:-.03em}

    .dept-tabs-card{background:#fff;border:1px solid #e5eaf1;border-radius:18px;box-shadow:0 12px 30px rgba(15,23,42,.055);overflow:hidden}
    .dept-tabs-head{padding:12px 14px;border-bottom:1px solid #edf2f7;background:linear-gradient(180deg,#fff,#f8fafc)}
    .dept-tabs-title{font-size:15px;font-weight:950;color:#0f172a;margin:0}
    .dept-tabs-note{font-size:11.5px;font-weight:700;color:#64748b;margin-top:3px}

    .dept-tabs-scroll{display:flex;gap:8px;overflow-x:auto;padding:12px 14px;border-bottom:1px solid #edf2f7;background:#fff;scrollbar-width:thin}
    .dept-tab-btn{border:1px solid #dbe3ef;background:#f8fafc;color:#334155;border-radius:999px;padding:8px 12px;display:inline-flex;align-items:center;gap:7px;font-size:12px;font-weight:900;white-space:nowrap;cursor:pointer;transition:.15s ease}
    .dept-tab-btn:hover{border-color:#38bdf8;color:#075985;background:#f0f9ff}
    .dept-tab-btn.active{background:#0f766e;color:#fff;border-color:#0f766e;box-shadow:0 10px 22px rgba(15,118,110,.16)}
    .dept-tab-count{min-width:22px;height:20px;padding:0 6px;border-radius:999px;background:rgba(15,23,42,.08);display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:950}
    .dept-tab-btn.active .dept-tab-count{background:rgba(255,255,255,.20);color:#fff}

    .dept-panel{display:none}
    .dept-panel.active{display:block}
    .dept-panel-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 15px;border-bottom:1px solid #edf2f7;background:#fbfdff}
    .dept-title{margin:0;font-size:16px;font-weight:950;color:#0f172a;letter-spacing:-.015em}
    .dept-desc{margin-top:2px;color:#64748b;font-size:11.5px;font-weight:700}
    .dept-stats{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end}
    .dept-badge{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:4px 8px;font-size:11px;font-weight:900;background:#f1f5f9;color:#475569}

    .task-list{display:grid}
    .task-item{padding:13px 15px;border-bottom:1px solid #e5eaf1;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center}
    .task-item:hover{background:#f8fafc}
    .task-item:last-child{border-bottom:0}
    .task-title a{color:#0f172a;font-size:13.5px;font-weight:950;text-decoration:none;text-transform:uppercase}
    .task-title a:hover{color:#0369a1}
    .task-meta{display:flex;flex-wrap:wrap;gap:8px;color:#64748b;font-size:11.5px;margin-top:5px;font-weight:650}
    .task-meta span{display:inline-flex;align-items:center;gap:4px}
    .task-side{min-width:225px;display:flex;flex-direction:column;align-items:flex-end;gap:8px}
    .progress-wrap{width:190px}
    .progress{height:7px;border-radius:999px;background:#e2e8f0;overflow:hidden}
    .progress-bar{background:linear-gradient(90deg,#0ea5e9,#22c55e)}
    .soft-badge{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:4px 8px;font-size:10.5px;font-weight:900;white-space:nowrap}
    .st-new{background:#e0f2fe;color:#075985}
    .st-in_progress{background:#fef3c7;color:#92400e}
    .st-submitted{background:#ede9fe;color:#5b21b6}
    .st-approved{background:#dcfce7;color:#166534}
    .pr-low{background:#f1f5f9;color:#475569}
    .pr-medium{background:#e0f2fe;color:#075985}
    .pr-high{background:#fee2e2;color:#991b1b}
    .empty-state{padding:38px 16px;text-align:center;color:#64748b;font-size:12px;font-weight:750}
    .task-actions{display:flex;gap:5px;flex-wrap:wrap;justify-content:flex-end}

    @media(max-width:1200px){.summary-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:768px){
        .page-shell{padding:12px}
        .hero-top,.dept-panel-head{flex-direction:column;align-items:flex-start}
        .summary-grid{grid-template-columns:1fr}
        .task-item{grid-template-columns:1fr}
        .task-side{align-items:flex-start;min-width:0}
        .progress-wrap{width:100%}
        .task-actions{justify-content:flex-start}
    }
</style>

<div class="task-page">
    <div class="page-shell">

        <div class="task-hero">
            <div class="hero-top">
                <div>
                    <h1>Quản lý công việc</h1>
                    <p>Giao việc, theo dõi tiến độ và xem công việc theo từng phòng ban.</p>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('tasks.create') }}" class="btn btn-light btn-pill">
                        <i class="bi bi-plus-circle"></i> Giao việc mới
                    </a>
                    <a href="{{ route('tasks.my') }}" class="btn btn-outline-light btn-pill">
                        Việc của tôi
                    </a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger border-0 shadow-sm rounded-4">{{ session('error') }}</div>
        @endif

        <div class="summary-grid">
            <div class="summary-card"><div class="summary-label">Tổng việc</div><div class="summary-value text-primary">{{ $summary['total'] ?? 0 }}</div></div>
            <div class="summary-card"><div class="summary-label">Mới giao</div><div class="summary-value text-info">{{ $summary['new'] ?? 0 }}</div></div>
            <div class="summary-card"><div class="summary-label">Đang làm</div><div class="summary-value text-warning">{{ $summary['in_progress'] ?? 0 }}</div></div>
            <div class="summary-card"><div class="summary-label">Đã nộp</div><div class="summary-value" style="color:#7c3aed">{{ $summary['submitted'] ?? 0 }}</div></div>
            <div class="summary-card"><div class="summary-label">Đã duyệt</div><div class="summary-value text-success">{{ $summary['approved'] ?? 0 }}</div></div>
        </div>

        <div class="dept-tabs-card">
            <div class="dept-tabs-head">
                <h2 class="dept-tabs-title">Công việc theo phòng ban</h2>
                <div class="dept-tabs-note">Mỗi tab là một phòng ban. Chọn tab để xem danh sách công việc tương ứng.</div>
            </div>

            <div class="dept-tabs-scroll" id="deptTabs">
                @foreach($taskGroups as $index => $group)
                    <button type="button"
                            class="dept-tab-btn {{ $index === 0 ? 'active' : '' }}"
                            data-target="dept-panel-{{ $index }}">
                        <span>{{ $group['name'] }}</span>
                        <span class="dept-tab-count">{{ $group['total'] }}</span>
                    </button>
                @endforeach
            </div>

            @forelse($taskGroups as $index => $group)
                <div id="dept-panel-{{ $index }}" class="dept-panel {{ $index === 0 ? 'active' : '' }}">
                    <div class="dept-panel-head">
                        <div>
                            <h2 class="dept-title">
                                <i class="bi bi-diagram-3"></i> {{ $group['name'] }}
                            </h2>
                            <div class="dept-desc">
                                Tổng {{ $group['total'] }} công việc trong phòng ban này.
                            </div>
                        </div>

                        <div class="dept-stats">
                            <span class="dept-badge">Tổng: {{ $group['total'] }}</span>
                            <span class="dept-badge st-new">Mới: {{ $group['new'] }}</span>
                            <span class="dept-badge st-in_progress">Đang làm: {{ $group['in_progress'] }}</span>
                            <span class="dept-badge st-submitted">Đã nộp: {{ $group['submitted'] }}</span>
                            <span class="dept-badge st-approved">Đã duyệt: {{ $group['approved'] }}</span>
                        </div>
                    </div>

                    <div class="task-list">
                        @forelse($group['tasks'] as $task)
                            @php
                                $status = $task->status ?? 'new';
                                $priority = $task->priority ?? 'medium';
                                $progress = (int)($task->progress_percent ?? 0);
                            @endphp

                            <div class="task-item">
                                <div>
                                    <div class="task-title">
                                        <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                    </div>

                                    <div class="task-meta">
                                        <span><i class="bi bi-person-up"></i> Giao bởi: {{ optional($task->requester)->name ?? 'Không rõ' }}</span>
                                        <span><i class="bi bi-person-check"></i> Nhận: {{ optional($task->assignee)->name ?? 'Không rõ' }}</span>
                                        <span><i class="bi bi-calendar-event"></i> Hạn: {{ $task->due_at ?: 'Không đặt hạn' }}</span>
                                        <span><i class="bi bi-clock"></i> {{ $task->created_at }}</span>
                                    </div>
                                </div>

                                <div class="task-side">
                                    <div class="d-flex gap-1 flex-wrap justify-content-end">
                                        <span class="soft-badge pr-{{ $priority }}">{{ $priorities[$priority] ?? $priority }}</span>
                                        <span class="soft-badge st-{{ $status }}">{{ $statuses[$status] ?? $status }}</span>
                                    </div>

                                    <div class="progress-wrap">
                                        <div class="d-flex justify-content-between small text-muted mb-1">
                                            <span>Tiến độ</span>
                                            <b>{{ $progress }}%</b>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>

                                    <div class="task-actions">
                                        <a href="{{ route('tasks.show', $task) }}" class="btn btn-sm btn-outline-primary btn-pill">Xem</a>
                                        <a href="{{ route('tasks.edit', $task) }}" class="btn btn-sm btn-outline-warning btn-pill">Sửa</a>
                                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" class="d-inline" onsubmit="return confirm('Bạn chắc chắn muốn xóa công việc này?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill">Xóa</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state">Phòng ban này chưa có công việc.</div>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    Chưa có phòng ban hoặc chưa có công việc.
                    <div class="mt-2">
                        <a href="{{ route('tasks.create') }}" class="btn btn-primary btn-pill">
                            <i class="bi bi-plus-circle"></i> Giao việc mới
                        </a>
                    </div>
                </div>
            @endforelse
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const buttons = document.querySelectorAll('.dept-tab-btn');
    const panels = document.querySelectorAll('.dept-panel');

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = btn.getAttribute('data-target');

            buttons.forEach(function (b) {
                b.classList.remove('active');
            });

            panels.forEach(function (panel) {
                panel.classList.remove('active');
            });

            btn.classList.add('active');

            const panel = document.getElementById(target);
            if (panel) {
                panel.classList.add('active');
            }
        });
    });
});
</script>
@endsection
