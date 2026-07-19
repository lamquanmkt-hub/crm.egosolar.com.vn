@extends('layouts.app')

@section('content')
<style>
    .proposal-show{
        min-height:100vh;
        background:#f4f7fb;
        padding-bottom:48px;
        font-size:13px;
        font-family:inherit;
    }

    .page-shell{
        padding:22px;
    }

    .page-head{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        margin-bottom:16px;
    }

    .page-head h4{
        margin:0;
        font-size:22px;
        font-weight:950;
        color:#0f172a;
    }

    .page-head .desc{
        color:#64748b;
        font-size:12px;
        margin-top:2px;
    }

    .btn-pill{
        border-radius:999px;
        font-weight:800;
        font-size:13px;
        padding:8px 16px;
    }

    .status-hero{
        border-radius:24px;
        padding:22px;
        color:#fff;
        background:
            radial-gradient(650px 260px at 90% 0%, rgba(34,211,238,.23), transparent 60%),
            linear-gradient(135deg,#020617,#075985 55%,#0f766e);
        box-shadow:0 18px 44px rgba(15,23,42,.18);
        margin-bottom:16px;
    }

    .status-hero h3{
        font-size:24px;
        font-weight:950;
        margin:0;
        letter-spacing:-.03em;
    }

    .status-hero p{
        margin:5px 0 0;
        opacity:.82;
        font-size:13px;
    }

    .info-grid{
        display:grid;
        grid-template-columns:repeat(5,1fr);
        gap:12px;
        margin-bottom:16px;
    }

    .info-box{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:18px;
        padding:13px 15px;
        box-shadow:0 10px 28px rgba(15,23,42,.055);
    }

    .info-label{
        color:#64748b;
        font-size:12px;
        font-weight:800;
        margin-bottom:5px;
    }

    .info-value{
        color:#0f172a;
        font-size:15px;
        font-weight:950;
    }

    .layout{
        display:grid;
        grid-template-columns:1fr 370px;
        gap:16px;
        align-items:start;
    }

    .soft-card{
        background:#fff;
        border:1px solid #e5eaf1;
        border-radius:22px;
        overflow:hidden;
        box-shadow:0 12px 32px rgba(15,23,42,.065);
        margin-bottom:16px;
    }

    .card-head{
        padding:14px 16px;
        border-bottom:1px solid #e5eaf1;
        background:#fff;
        font-size:15px;
        font-weight:950;
        color:#0f172a;
    }

    .card-head.dark{
        background:#0f172a;
        color:#fff;
        border-bottom:0;
    }

    .card-body-custom{
        padding:18px;
    }

    .soft-badge{
        display:inline-flex;
        align-items:center;
        gap:4px;
        border-radius:999px;
        padding:5px 10px;
        font-size:12px;
        font-weight:900;
        white-space:nowrap;
    }

    .st-pending{background:#fef3c7;color:#92400e}
    .st-approved{background:#dcfce7;color:#166534}
    .st-rejected{background:#fee2e2;color:#991b1b}

    .pr-low{background:#f1f5f9;color:#475569}
    .pr-normal{background:#e0f2fe;color:#075985}
    .pr-high{background:#ffedd5;color:#9a3412}
    .pr-urgent{background:#fee2e2;color:#991b1b}

    .content-title{
        font-size:20px;
        font-weight:950;
        color:#0f172a;
        margin-bottom:14px;
    }

    .section-block{
        margin-bottom:20px;
    }

    .section-block:last-child{
        margin-bottom:0;
    }

    .section-label{
        font-size:12px;
        font-weight:950;
        color:#0f172a;
        margin-bottom:6px;
        text-transform:uppercase;
        letter-spacing:.02em;
    }

    .section-content{
        color:#475569;
        line-height:1.7;
        white-space:pre-line;
        background:#f8fafc;
        border:1px solid #e5eaf1;
        border-radius:14px;
        padding:13px;
    }

    .file-grid{
        display:grid;
        gap:10px;
    }

    .file-item{
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:12px;
        padding:12px;
        border:1px solid #e5eaf1;
        border-radius:14px;
        background:#f8fafc;
    }

    .file-left{
        display:flex;
        align-items:center;
        gap:10px;
        min-width:0;
    }

    .file-icon{
        width:40px;
        height:40px;
        display:flex;
        align-items:center;
        justify-content:center;
        border-radius:13px;
        background:#e0f2fe;
        color:#0369a1;
        font-size:20px;
        flex:0 0 auto;
    }

    .file-name{
        font-weight:900;
        color:#0f172a;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
        max-width:360px;
    }

    .file-meta{
        color:#64748b;
        font-size:12px;
    }

    .form-label{
        font-size:12px;
        font-weight:850;
        color:#334155;
    }

    .form-control{
        border-radius:12px;
        border-color:#dbe3ee;
        font-size:13px;
    }

    .timeline-box{
        padding:13px;
        border-radius:16px;
        background:#f8fafc;
        border:1px solid #e5eaf1;
    }

    @media(max-width:1200px){
        .layout{grid-template-columns:1fr}
        .info-grid{grid-template-columns:repeat(2,1fr)}
    }

    @media(max-width:768px){
        .page-shell{padding:14px}
        .page-head{flex-direction:column;align-items:flex-start}
        .info-grid{grid-template-columns:1fr}
        .file-item{align-items:flex-start;flex-direction:column}
    }
</style>

<div class="proposal-show">
    <div class="page-shell">

        <div class="page-head">
            <div>
                <h4>Chi tiết đề xuất</h4>
                <div class="desc">{{ $proposal->title }}</div>
            </div>

            <a href="{{ route('de-xuat.index') }}" class="btn btn-outline-secondary btn-pill">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
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

        @if($errors->any())
            <div class="alert alert-danger border-0 shadow-sm rounded-4">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="status-hero">
            <h3>{{ $proposal->title }}</h3>
            <p>
                Đề xuất bởi <b>{{ $proposal->employee_name }}</b>
                @if($proposal->department_name)
                    · {{ $proposal->department_name }}
                @endif
            </p>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Người tạo</div>
                <div class="info-value">{{ $proposal->employee_name }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Phòng ban</div>
                <div class="info-value">{{ $proposal->department_name ?: '-' }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Số tiền</div>
                <div class="info-value">{{ number_format($proposal->amount, 0, ',', '.') }} đ</div>
            </div>

            <div class="info-box">
                <div class="info-label">Ngày cần xử lý</div>
                <div class="info-value">{{ $proposal->needed_date ?: '-' }}</div>
            </div>

            <div class="info-box">
                <div class="info-label">Trạng thái</div>
                <div class="info-value">
                    @if($proposal->status === 'approved')
                        <span class="soft-badge st-approved">Đã duyệt</span>
                    @elseif($proposal->status === 'rejected')
                        <span class="soft-badge st-rejected">Từ chối</span>
                    @else
                        <span class="soft-badge st-pending">Chờ duyệt</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="layout">
            <div>
                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-file-text"></i> Nội dung đề xuất
                    </div>

                    <div class="card-body-custom">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="soft-badge pr-{{ $proposal->priority ?? 'normal' }}">
                                Ưu tiên: {{ $priorities[$proposal->priority ?? 'normal'] ?? 'Bình thường' }}
                            </span>

                            <span class="soft-badge pr-normal">
                                Loại: {{ $types[$proposal->proposal_type] ?? $proposal->proposal_type }}
                            </span>
                        </div>

                        <div class="content-title">{{ $proposal->title }}</div>

                        <div class="section-block">
                            <div class="section-label">Nội dung</div>
                            <div class="section-content">{{ $proposal->content ?: 'Không có nội dung.' }}</div>
                        </div>

                        <div class="section-block">
                            <div class="section-label">Lý do đề xuất</div>
                            <div class="section-content">{{ $proposal->reason ?: 'Không có lý do.' }}</div>
                        </div>

                        <div class="section-block">
                            <div class="section-label">Kết quả kỳ vọng</div>
                            <div class="section-content">{{ $proposal->expected_result ?: 'Không có.' }}</div>
                        </div>
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-paperclip"></i> File đính kèm
                    </div>

                    <div class="card-body-custom">
                        <div class="file-grid">
                            @forelse($attachments ?? [] as $file)
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
                                                {{ $file->file_mime ?: 'file' }}
                                                · {{ number_format(($file->file_size ?? 0) / 1024, 1) }} KB
                                            </div>
                                        </div>
                                    </div>

                                    <a href="{{ asset('storage/' . $file->file_path) }}"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-primary btn-pill">
                                        Xem / tải
                                    </a>
                                </div>
                            @empty
                                <div class="text-muted">
                                    Không có file đính kèm.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="soft-card">
                    <div class="card-head dark">
                        <i class="bi bi-check2-square"></i> Xử lý đề xuất
                    </div>

                    <div class="card-body-custom">
                        <div class="timeline-box mb-3">
                            <div class="mb-2">
                                <div class="text-muted small">Người duyệt</div>
                                <div class="fw-bold">{{ $proposal->approved_name ?: '-' }}</div>
                            </div>

                            <div>
                                <div class="text-muted small">Thời gian xử lý</div>
                                <div class="fw-bold">{{ $proposal->approved_at ?: '-' }}</div>
                            </div>
                        </div>

                        @if($proposal->status === 'approved')
                            <div class="alert alert-success rounded-4">
                                <b>Đã duyệt.</b>
                                <div class="mt-1">{{ $proposal->approved_note ?: 'Không có ghi chú.' }}</div>
                            </div>
                        @elseif($proposal->status === 'rejected')
                            <div class="alert alert-danger rounded-4">
                                <b>Đã từ chối.</b>
                                <div class="mt-1">{{ $proposal->reject_reason ?: 'Không có lý do.' }}</div>
                            </div>
                        @endif

                        @if($canApprove && $proposal->status === 'pending')
                            <form method="POST" action="{{ route('de-xuat.approve', $proposal->id) }}" class="mb-3">
                                @csrf

                                <label class="form-label">Ghi chú duyệt</label>
                                <textarea name="approved_note"
                                          class="form-control mb-2"
                                          rows="3"
                                          placeholder="Ghi chú nếu cần..."></textarea>

                                <button class="btn btn-success w-100 btn-pill">
                                    <i class="bi bi-check-circle"></i> Duyệt đề xuất
                                </button>
                            </form>

                            <form method="POST" action="{{ route('de-xuat.reject', $proposal->id) }}">
                                @csrf

                                <label class="form-label">Lý do từ chối</label>
                                <textarea name="reject_reason"
                                          class="form-control mb-2"
                                          rows="3"
                                          placeholder="Nhập lý do từ chối..."></textarea>

                                <button class="btn btn-danger w-100 btn-pill">
                                    <i class="bi bi-x-circle"></i> Từ chối
                                </button>
                            </form>
                        @elseif(!$canApprove)
                            <div class="alert alert-secondary rounded-4 mb-0">
                                Bạn chỉ có quyền xem trạng thái đề xuất.
                            </div>
                        @endif

                        <hr>

                        @php
                            $canDelete = $canApprove || ((int)$proposal->user_id === (int)auth()->id() && $proposal->status === 'pending');
                        @endphp

                        @if($canDelete)
                            <form method="POST"
                                  action="{{ route('de-xuat.destroy', $proposal->id) }}"
                                  onsubmit="return confirm('Bạn chắc chắn muốn xóa đề xuất này?')">
                                @csrf
                                <button class="btn btn-outline-danger w-100 btn-pill">
                                    <i class="bi bi-trash"></i> Xóa đề xuất
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="soft-card">
                    <div class="card-head">
                        <i class="bi bi-info-circle"></i> Thông tin nhanh
                    </div>

                    <div class="card-body-custom">
                        <div class="mb-2">
                            <div class="text-muted small">Ngày tạo</div>
                            <div class="fw-bold">{{ $proposal->created_at }}</div>
                        </div>

                        <div class="mb-2">
                            <div class="text-muted small">Cập nhật cuối</div>
                            <div class="fw-bold">{{ $proposal->updated_at }}</div>
                        </div>

                        <div>
                            <div class="text-muted small">Mã đề xuất</div>
                            <div class="fw-bold">#{{ $proposal->id }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection