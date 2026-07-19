@extends('layouts.app')

@section('content')
@php
    /* EGO_FIX_CAN_BOSS_EDIT_FALLBACK */
    $canBossEdit = $canBossEdit ?? false;
@endphp
<style>
    .task-page{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
        font-family:inherit;
    }

    .page-shell{padding:22px}

    .task-hero{
        position:relative;
        overflow:hidden;
        border-radius:24px;
        padding:24px;
        color:#fff;
        background:
            radial-gradient(700px 280px at 88% 0%, rgba(34,211,238,.25), transparent 60%),
            linear-gradient(135deg,#020617,#075985 58%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
    }

    .task-hero h1{
        font-size:26px;
        font-weight:950;
        letter-spacing:-.03em;
        margin:0;
    }

    .task-hero p{
        font-size:13px;
        opacity:.84;
        margin:5px 0 0;
    }

    .summary-grid{
        display:grid;
        grid-template-columns:repeat(5,1fr);
        gap:12px;
        margin-bottom:16px;
    }

    .summary-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:18px;
        padding:14px 15px;
        box-shadow:0 10px 28px rgba(15,23,42,.055);
    }

    .summary-label{
        color:#64748b;
        font-size:12px;
        font-weight:800;
        margin-bottom:4px;
    }

    .summary-value{
        color:#0f172a;
        font-size:25px;
        font-weight:950;
        line-height:1.05;
        letter-spacing:-.02em;
    }

    .content-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:20px;
        overflow:hidden;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
    }

    .card-toolbar{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        background:#fff;
    }

    .card-title{
        font-size:15px;
        font-weight:950;
        color:#0f172a;
        margin:0;
    }

    .card-desc{
        color:#64748b;
        font-size:12px;
        margin-top:2px;
    }

    .task-list{display:grid}

    .task-item{
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        display:grid;
        grid-template-columns:1fr auto;
        gap:16px;
        align-items:center;
        transition:.15s ease;
    }

    .task-item:hover{background:#f8fafc}
    .task-item:last-child{border-bottom:0}

    .task-title a{
        color:#0f172a;
        font-size:14px;
        font-weight:950;
        text-decoration:none;
    }

    .task-title a:hover{color:#0369a1}

    .task-meta{
        display:flex;
        flex-wrap:wrap;
        gap:9px;
        color:#64748b;
        font-size:12px;
        margin-top:5px;
    }

    .task-meta span{
        display:inline-flex;
        align-items:center;
        gap:4px;
    }

    .task-side{
        min-width:210px;
        display:flex;
        flex-direction:column;
        align-items:flex-end;
        gap:8px;
    }

    .progress-wrap{
        width:190px;
    }

    .progress{
        height:8px;
        border-radius:999px;
        background:#e2e8f0;
        overflow:hidden;
    }

    .progress-bar{
        background:linear-gradient(90deg,#0ea5e9,#22c55e);
    }

    .soft-badge{
        display:inline-flex;
        align-items:center;
        gap:4px;
        border-radius:999px;
        padding:4px 9px;
        font-size:11px;
        font-weight:900;
        white-space:nowrap;
    }

    .st-new{background:#e0f2fe;color:#075985}
    .st-in_progress{background:#fef3c7;color:#92400e}
    .st-submitted{background:#ede9fe;color:#5b21b6}
    .st-approved{background:#dcfce7;color:#166534}

    .pr-low{background:#f1f5f9;color:#475569}
    .pr-medium{background:#e0f2fe;color:#075985}
    .pr-high{background:#fee2e2;color:#991b1b}

    .btn-pill{
        border-radius:999px;
        font-weight:800;
        font-size:13px;
        padding:7px 14px;
    }

    .empty-state{
        padding:54px 20px;
        text-align:center;
        color:#64748b;
    }

    .empty-icon{
        width:58px;
        height:58px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        border-radius:20px;
        background:#eef6ff;
        color:#0369a1;
        font-size:27px;
        margin-bottom:12px;
    }

    .pagination-wrap{
        padding:12px 16px;
        border-top:1px solid #e5eaf1;
    }

    @media(max-width:1200px){
        .summary-grid{grid-template-columns:repeat(2,1fr)}
    }

    @media(max-width:768px){
        .page-shell{padding:14px}
        .summary-grid{grid-template-columns:1fr}
        .task-item{grid-template-columns:1fr}
        .task-side{align-items:flex-start}
        .progress-wrap{width:100%}
        .card-toolbar{flex-direction:column;align-items:flex-start}
    }
</style>

<div class="task-page">
    <div class="page-shell">

        <div class="task-hero">
            <h1>Việc của tôi</h1>
            <p>Theo dõi công việc được giao, cập nhật tiến độ và nộp kết quả cho sếp duyệt.</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Tổng việc</div>
                <div class="summary-value text-primary">{{ $summary['total'] ?? 0 }}</div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Mới giao</div>
                <div class="summary-value text-info">{{ $summary['new'] ?? 0 }}</div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Đang làm</div>
                <div class="summary-value text-warning">{{ $summary['in_progress'] ?? 0 }}</div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Đã nộp</div>
                <div class="summary-value" style="color:#7c3aed">{{ $summary['submitted'] ?? 0 }}</div>
            </div>

            <div class="summary-card">
                <div class="summary-label">Đã duyệt</div>
                <div class="summary-value text-success">{{ $summary['approved'] ?? 0 }}</div>
            </div>
        </div>

        <div class="content-card">
            <div class="card-toolbar">
                <div>
                    <h2 class="card-title">
                        <i class="bi bi-check2-square"></i> Danh sách việc được giao
                    </h2>
                    <div class="card-desc">Bấm vào từng việc để cập nhật tiến độ, nộp kết quả hoặc xem file đính kèm.</div>
                </div>
            </div>

            <div class="task-list">
                @forelse($tasks as $task)
                    @php
                        $status = $task->status ?? 'new';
                        $priority = $task->priority ?? 'medium';
                        $progress = (int)($task->progress_percent ?? 0);

                        $bossCanEdit = (bool)($canBossEdit ?? false);
                        $user = auth()->user();

                        if (!$bossCanEdit && $user) {
                            $roles = ['admin','manager','management','director','general_director','ban_giam_doc','giam_doc','ceo','assistant','tro_ly'];

                            if (method_exists($user, 'hasAnyRole')) {
                                $bossCanEdit = $user->hasAnyRole($roles);
                            } elseif (method_exists($user, 'hasRole')) {
                                foreach ($roles as $r) {
                                    if ($user->hasRole($r)) {
                                        $bossCanEdit = true;
                                        break;
                                    }
                                }
                            } else {
                                $bossCanEdit = in_array(strtolower((string)($user->role ?? '')), $roles, true);
                            }
                        }

                        if ((int)($task->requester_id ?? 0) === (int)auth()->id()) {
                            $bossCanEdit = true;
                        }
                    @endphp

                    <div class="task-item">
                        <div>
                            <div class="task-title">
                                <a href="{{ route('tasks.show', $task) }}">
                                    {{ $task->title }}
                                </a>
                            </div>

                            <div class="task-meta">
                                <span><i class="bi bi-person"></i> Giao bởi: {{ optional($task->requester)->name ?? 'Không rõ' }}</span>
                                <span><i class="bi bi-calendar-event"></i> Hạn: {{ $task->due_at ?: 'Không đặt hạn' }}</span>
                                <span><i class="bi bi-clock"></i> {{ $task->created_at }}</span>
                            </div>
                        </div>

                        <div class="task-side">
                            <div class="d-flex gap-1 flex-wrap justify-content-end">
                                <span class="soft-badge pr-{{ $priority }}">
                                    {{ $priorities[$priority] ?? $priority }}
                                </span>

                                <span class="soft-badge st-{{ $status }}">
                                    {{ $statuses[$status] ?? $status }}
                                </span>
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

                            @if($status === 'submitted')
                                <a href="{{ route('tasks.show', $task) }}#result-form" class="btn btn-sm btn-warning btn-pill">
                                    <i class="bi bi-pencil-square"></i> Sửa bài nộp
                                </a>
                            @elseif($status === 'approved' && ($bossCanEdit ?? false))
                                <a href="{{ route('tasks.show', $task) }}#result-form" class="btn btn-sm btn-warning btn-pill">
                                    <i class="bi bi-pencil-square"></i> Sửa đã duyệt
                                </a>
                            @elseif($status === 'approved')
                                <a href="{{ route('tasks.show', $task) }}" class="btn btn-sm btn-outline-success btn-pill">
                                    <i class="bi bi-eye"></i> Xem
                                </a>
                            @else
                                <a href="{{ route('tasks.show', $task) }}" class="btn btn-sm btn-outline-primary btn-pill">
                                    Xem / cập nhật
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <div class="fw-bold text-dark">Bạn chưa có công việc nào</div>
                        <div class="mt-1">Khi sếp giao việc, danh sách sẽ hiển thị tại đây.</div>
                    </div>
                @endforelse
            </div>

            @if(method_exists($tasks, 'links'))
                <div class="pagination-wrap">
                    {{ $tasks->links() }}
                </div>
            @endif
        </div>

    </div>
</div>
@endsection