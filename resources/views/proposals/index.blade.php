@extends('layouts.app')

@section('content')
<style>
    .proposal-page{
        min-height:100vh;
        background:#f3f6fb;
        padding-bottom:42px;
        font-size:13px;
    }

    .proposal-shell{
        padding:22px;
    }

    .proposal-hero{
        border-radius:22px;
        padding:22px 24px;
        color:#fff;
        background:
            radial-gradient(680px 260px at 90% 0%, rgba(34,211,238,.26), transparent 60%),
            linear-gradient(135deg,#07111f,#075985 58%,#0f766e);
        box-shadow:0 16px 38px rgba(15,23,42,.16);
        margin-bottom:14px;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:16px;
        overflow:hidden;
        position:relative;
    }

    .proposal-hero::after{
        content:"";
        position:absolute;
        right:-58px;
        bottom:-86px;
        width:190px;
        height:190px;
        border-radius:999px;
        background:rgba(255,255,255,.12);
    }

    .proposal-hero-main{
        position:relative;
        z-index:2;
    }

    .proposal-hero-title{
        margin:0;
        font-size:26px;
        line-height:1.15;
        font-weight:950;
        letter-spacing:-.03em;
    }

    .proposal-hero-sub{
        margin-top:6px;
        font-size:13px;
        color:rgba(255,255,255,.84);
        font-weight:600;
    }

    .proposal-hero-action{
        position:relative;
        z-index:2;
        flex:0 0 auto;
    }

    .btn-pill{
        border-radius:999px;
        font-weight:850;
        font-size:13px;
        padding:9px 16px;
    }

    .proposal-summary{
        display:grid;
        grid-template-columns:repeat(5, minmax(0,1fr));
        gap:12px;
        margin-bottom:14px;
    }

    .proposal-kpi{
        background:#fff;
        border:1px solid #e4ebf3;
        border-radius:18px;
        padding:14px 15px;
        box-shadow:0 9px 24px rgba(15,23,42,.045);
        min-height:74px;
    }

    .proposal-kpi-label{
        font-size:12px;
        color:#64748b;
        font-weight:850;
        margin-bottom:5px;
    }

    .proposal-kpi-value{
        font-size:24px;
        line-height:1.05;
        font-weight:950;
        letter-spacing:-.02em;
        color:#0f172a;
    }

    .proposal-card{
        background:#fff;
        border:1px solid #e4ebf3;
        border-radius:20px;
        overflow:hidden;
        box-shadow:0 12px 30px rgba(15,23,42,.055);
    }

    .proposal-card-head{
        padding:15px 16px;
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        border-bottom:1px solid #e4ebf3;
        background:#fff;
    }

    .proposal-card-title{
        margin:0;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
    }

    .proposal-card-desc{
        margin-top:3px;
        color:#64748b;
        font-size:12px;
        font-weight:600;
    }

    .proposal-filter{
        display:grid;
        grid-template-columns:minmax(220px, 1.4fr) repeat(3, minmax(150px, .7fr)) auto auto;
        gap:8px;
        padding:12px 16px;
        border-bottom:1px solid #e4ebf3;
        background:#f8fafc;
        align-items:center;
    }

    .proposal-filter .form-control,
    .proposal-filter .form-select{
        height:38px;
        border-radius:12px;
        border-color:#dbe3ee;
        font-size:12px;
        font-weight:650;
    }

    .proposal-list{
        display:grid;
    }

    .proposal-table-head{
        display:grid;
        grid-template-columns:minmax(0, 1fr) 150px 120px 135px 130px;
        gap:12px;
        align-items:center;
        padding:11px 16px;
        background:#f8fafc;
        border-bottom:1px solid #e4ebf3;
        color:#64748b;
        font-size:12px;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.02em;
    }

    .proposal-row{
        display:grid;
        grid-template-columns:minmax(0, 1fr) 150px 120px 135px 130px;
        gap:12px;
        align-items:center;
        padding:16px;
        border-bottom:1px solid #edf2f7;
        background:#fff;
        transition:.15s ease;
    }

    .proposal-row:hover{
        background:#f9fcff;
    }

    .proposal-row:last-child{
        border-bottom:0;
    }

    .proposal-title-line{
        display:flex;
        align-items:flex-start;
        gap:11px;
        min-width:0;
    }

    .proposal-icon{
        width:38px;
        height:38px;
        border-radius:14px;
        flex:0 0 auto;
        display:flex;
        align-items:center;
        justify-content:center;
        background:#e0f2fe;
        color:#0369a1;
        border:1px solid #bae6fd;
        font-size:17px;
    }

    .proposal-title{
        color:#0f172a;
        font-size:15px;
        line-height:1.35;
        font-weight:950;
        text-decoration:none;
        display:block;
    }

    .proposal-title:hover{
        color:#0369a1;
        text-decoration:underline;
        text-underline-offset:3px;
    }

    .proposal-meta{
        margin-top:8px;
        display:flex;
        flex-wrap:wrap;
        gap:7px;
    }

    .proposal-chip{
        display:inline-flex;
        align-items:center;
        gap:5px;
        border-radius:999px;
        padding:5px 9px;
        background:#f1f5f9;
        color:#475569;
        font-size:11.5px;
        font-weight:800;
        border:1px solid rgba(15,23,42,.06);
    }

    .proposal-chip.blue{
        background:#eff6ff;
        color:#1d4ed8;
        border-color:#bfdbfe;
    }

    .proposal-chip.orange{
        background:#fff7ed;
        color:#c2410c;
        border-color:#fed7aa;
    }

    .proposal-chip.green{
        background:#ecfdf5;
        color:#047857;
        border-color:#bbf7d0;
    }

    .proposal-money{
        font-size:16px;
        font-weight:950;
        color:#0f172a;
        line-height:1.2;
        white-space:nowrap;
        text-align:right;
    }

    .proposal-cell{
        display:flex;
        align-items:center;
    }

    .proposal-cell.center{
        justify-content:center;
    }

    .proposal-cell.right{
        justify-content:flex-end;
    }

    .proposal-badges{
        display:flex;
        gap:6px;
        justify-content:center;
        flex-wrap:wrap;
    }

    .soft-badge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:5px;
        border-radius:999px;
        padding:6px 10px;
        font-size:11.5px;
        font-weight:900;
        white-space:nowrap;
        min-width:82px;
    }

    .st-pending{
        background:#fef3c7;
        color:#92400e;
    }

    .st-approved{
        background:#dcfce7;
        color:#166534;
    }

    .st-rejected{
        background:#fee2e2;
        color:#991b1b;
    }

    .pr-low{
        background:#f1f5f9;
        color:#475569;
    }

    .pr-normal{
        background:#e0f2fe;
        color:#075985;
    }

    .pr-high{
        background:#ffedd5;
        color:#9a3412;
    }

    .pr-urgent{
        background:#fee2e2;
        color:#991b1b;
    }

    .proposal-detail-btn{
        border-radius:999px;
        font-size:12px;
        font-weight:850;
        padding:7px 13px;
        width:100%;
        max-width:118px;
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

    @media(max-width:1200px){
        .proposal-summary{
            grid-template-columns:repeat(2, minmax(0,1fr));
        }

        .proposal-filter{
            grid-template-columns:repeat(2, minmax(0,1fr));
        }

        .proposal-table-head{
            display:none;
        }

        .proposal-row{
            grid-template-columns:1fr;
            gap:12px;
        }

        .proposal-money{
            text-align:left;
            font-size:18px;
        }

        .proposal-cell,
        .proposal-cell.center,
        .proposal-cell.right{
            justify-content:flex-start;
        }

        .proposal-cell::before{
            content:attr(data-label);
            min-width:95px;
            color:#64748b;
            font-size:12px;
            font-weight:900;
        }

        .proposal-badges{
            justify-content:flex-start;
        }

        .proposal-detail-btn{
            width:auto;
            max-width:none;
        }
    }

    @media(max-width:768px){
        .proposal-shell{
            padding:14px;
        }

        .proposal-hero{
            flex-direction:column;
            align-items:flex-start;
            padding:18px;
        }

        .proposal-hero-title{
            font-size:22px;
        }

        .proposal-summary{
            grid-template-columns:1fr;
        }

        .proposal-card-head{
            flex-direction:column;
            align-items:flex-start;
        }

        .proposal-filter{
            grid-template-columns:1fr;
        }

        .proposal-title-line{
            gap:10px;
        }
    }
</style>

<div class="proposal-page">
    <div class="proposal-shell">

        <div class="proposal-hero">
            <div class="proposal-hero-main">
                <h1 class="proposal-hero-title">Đề xuất nội bộ</h1>
                <div class="proposal-hero-sub">
                    Tạo đề xuất, đính kèm file, theo dõi trạng thái và duyệt nhanh trong một nơi.
                </div>
            </div>

            <div class="proposal-hero-action">
                <a href="{{ route('de-xuat.create') }}" class="btn btn-light btn-pill">
                    <i class="bi bi-plus-circle"></i> Tạo đề xuất
                </a>
            </div>
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

        <div class="proposal-summary">
            <div class="proposal-kpi">
                <div class="proposal-kpi-label">Tổng đề xuất</div>
                <div class="proposal-kpi-value text-primary">{{ $summary['total'] ?? 0 }}</div>
            </div>

            <div class="proposal-kpi">
                <div class="proposal-kpi-label">Chờ duyệt</div>
                <div class="proposal-kpi-value text-warning">{{ $summary['pending'] ?? 0 }}</div>
            </div>

            <div class="proposal-kpi">
                <div class="proposal-kpi-label">Đã duyệt</div>
                <div class="proposal-kpi-value text-success">{{ $summary['approved'] ?? 0 }}</div>
            </div>

            <div class="proposal-kpi">
                <div class="proposal-kpi-label">Từ chối</div>
                <div class="proposal-kpi-value text-danger">{{ $summary['rejected'] ?? 0 }}</div>
            </div>

            <div class="proposal-kpi">
                <div class="proposal-kpi-label">Tiền chờ duyệt</div>
                <div class="proposal-kpi-value">
                    {{ number_format($summary['amount_pending'] ?? 0, 0, ',', '.') }}đ
                </div>
            </div>
        </div>

        <div class="proposal-card">
            <div class="proposal-card-head">
                <div>
                    <h2 class="proposal-card-title">
                        <i class="bi bi-list-check"></i> Danh sách đề xuất
                    </h2>
                    <div class="proposal-card-desc">
                        Người thường thấy đề xuất của mình. Sếp/kế toán/manager thấy toàn bộ.
                    </div>
                </div>

                <a href="{{ route('de-xuat.create') }}" class="btn btn-primary btn-pill">
                    <i class="bi bi-plus-lg"></i> Tạo mới
                </a>
            </div>

            <form method="GET" action="{{ route('de-xuat.index') }}" class="proposal-filter">
                <input type="text"
                       name="q"
                       value="{{ request('q') }}"
                       class="form-control"
                       placeholder="Tìm tiêu đề, nội dung, người tạo...">

                <select name="status" class="form-select">
                    <option value="">Tất cả trạng thái</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Chờ duyệt</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Đã duyệt</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Từ chối</option>
                </select>

                <select name="type" class="form-select">
                    <option value="">Tất cả loại</option>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <select name="priority" class="form-select">
                    <option value="">Tất cả ưu tiên</option>
                    @foreach($priorities as $key => $label)
                        <option value="{{ $key }}" {{ request('priority') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>

                <button class="btn btn-outline-primary btn-pill">
                    <i class="bi bi-funnel"></i> Lọc
                </button>

                @if(request()->hasAny(['q','status','type','priority']))
                    <a href="{{ route('de-xuat.index') }}" class="btn btn-outline-secondary btn-pill">
                        Xóa lọc
                    </a>
                @endif
            </form>

            <div class="proposal-list">
                <div class="proposal-table-head">
                    <div>Thông tin đề xuất</div>
                    <div class="text-end">Số tiền</div>
                    <div class="text-center">Ưu tiên</div>
                    <div class="text-center">Trạng thái</div>
                    <div class="text-end">Thao tác</div>
                </div>

                @forelse($proposals as $proposal)
                    @php
                        if ($proposal->status === 'approved') {
                            $statusText = 'Đã duyệt';
                            $statusClass = 'st-approved';
                        } elseif ($proposal->status === 'rejected') {
                            $statusText = 'Từ chối';
                            $statusClass = 'st-rejected';
                        } else {
                            $statusText = 'Chờ duyệt';
                            $statusClass = 'st-pending';
                        }

                        $priority = $proposal->priority ?? 'normal';
                        $priorityClass = 'pr-' . $priority;
                        $priorityText = $priorities[$priority] ?? 'Bình thường';
                    @endphp

                    <div class="proposal-row">
                        <div>
                            <div class="proposal-title-line">
                                <div class="proposal-icon">
                                    <i class="bi bi-lightbulb"></i>
                                </div>

                                <div>
                                    <a href="{{ route('de-xuat.show', $proposal->id) }}" class="proposal-title">
                                        {{ $proposal->title }}
                                    </a>

                                    <div class="proposal-meta">
                                        <span class="proposal-chip">
                                            <i class="bi bi-person"></i> {{ $proposal->employee_name }}
                                        </span>

                                        <span class="proposal-chip blue">
                                            <i class="bi bi-building"></i> {{ $proposal->department_name ?: '-' }}
                                        </span>

                                        <span class="proposal-chip orange">
                                            <i class="bi bi-tag"></i> {{ $types[$proposal->proposal_type] ?? $proposal->proposal_type }}
                                        </span>

                                        <span class="proposal-chip green">
                                            <i class="bi bi-calendar-event"></i> {{ $proposal->needed_date ?: 'Không đặt ngày' }}
                                        </span>

                                        <span class="proposal-chip">
                                            <i class="bi bi-clock"></i> {{ $proposal->created_at }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="proposal-cell right" data-label="Số tiền">
                            <div class="proposal-money">
                                {{ number_format($proposal->amount, 0, ',', '.') }} đ
                            </div>
                        </div>

                        <div class="proposal-cell center" data-label="Ưu tiên">
                            <span class="soft-badge {{ $priorityClass }}">{{ $priorityText }}</span>
                        </div>

                        <div class="proposal-cell center" data-label="Trạng thái">
                            <span class="soft-badge {{ $statusClass }}">{{ $statusText }}</span>
                        </div>

                        <div class="proposal-cell right" data-label="Thao tác">
                            <a href="{{ route('de-xuat.show', $proposal->id) }}"
                               class="btn btn-sm btn-outline-primary proposal-detail-btn">
                                Xem chi tiết
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-inbox"></i>
                        </div>
                        <div class="fw-bold text-dark">Chưa có đề xuất nào</div>
                        <div class="mt-1">Bấm “Tạo đề xuất” để gửi đề xuất đầu tiên.</div>
                        <a href="{{ route('de-xuat.create') }}" class="btn btn-primary btn-pill mt-3">
                            <i class="bi bi-plus-circle"></i> Tạo đề xuất
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection