@extends('layouts.app')

@section('content')
<style>
    .ego-hr-wrap{
        width:100%;
        max-width:100%;
        padding:0 2px 28px;
    }

    .ego-hr-head{
        display:flex;
        align-items:flex-end;
        justify-content:space-between;
        gap:14px;
        margin-bottom:14px;
    }

    .ego-hr-title{
        margin:0;
        font-size:24px;
        line-height:1.15;
        font-weight:900;
        color:#0f172a;
        letter-spacing:-.03em;
    }

    .ego-hr-sub{
        margin-top:5px;
        font-size:13px;
        font-weight:700;
        color:#64748b;
    }

    .ego-hr-top-actions{
        display:flex;
        align-items:center;
        gap:8px;
        flex-wrap:wrap;
    }

    .ego-hr-btn,
    .ego-hr-btn-outline,
    .ego-hr-btn-danger{
        height:34px;
        border-radius:10px;
        padding:0 12px;
        border:1px solid transparent;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:6px;
        font-size:12px;
        font-weight:850;
        line-height:1;
        text-decoration:none;
        cursor:pointer;
        white-space:nowrap;
    }

    .ego-hr-btn{
        background:#0f766e;
        color:#fff;
        box-shadow:0 8px 18px rgba(15,118,110,.14);
    }

    .ego-hr-btn-outline{
        background:#fff;
        color:#0f172a;
        border-color:#dbe3ef;
    }

    .ego-hr-btn-danger{
        background:#fff1f2;
        color:#be123c;
        border-color:#fecdd3;
    }

    .ego-hr-stats{
        display:grid;
        grid-template-columns:repeat(3,minmax(0,1fr));
        gap:10px;
        margin-bottom:12px;
    }

    .ego-hr-stat{
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:16px;
        padding:13px 14px;
        box-shadow:0 10px 24px rgba(15,23,42,.045);
        min-height:78px;
        position:relative;
        overflow:hidden;
    }

    .ego-hr-stat:after{
        content:"";
        position:absolute;
        right:-18px;
        top:-22px;
        width:70px;
        height:70px;
        border-radius:999px;
        background:linear-gradient(135deg,rgba(15,118,110,.10),rgba(14,165,233,.08));
    }

    .ego-hr-stat-label{
        position:relative;
        z-index:1;
        font-size:11px;
        line-height:1.2;
        text-transform:uppercase;
        letter-spacing:.035em;
        font-weight:900;
        color:#64748b;
    }

    .ego-hr-stat-value{
        position:relative;
        z-index:1;
        margin-top:8px;
        font-size:24px;
        line-height:1;
        font-weight:950;
        color:#0f766e;
        letter-spacing:-.04em;
    }

    .ego-hr-grid{
        display:grid;
        grid-template-columns:minmax(0,1.05fr) minmax(340px,.95fr);
        gap:12px;
        align-items:start;
    }

    .ego-hr-panel{
        background:#fff;
        border:1px solid #e2e8f0;
        border-radius:18px;
        box-shadow:0 12px 30px rgba(15,23,42,.055);
        overflow:hidden;
    }

    .ego-hr-panel-head{
        padding:13px 15px;
        border-bottom:1px solid #edf2f7;
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        background:linear-gradient(180deg,#fff,#f8fafc);
    }

    .ego-hr-panel-title{
        margin:0;
        font-size:15px;
        line-height:1.2;
        font-weight:950;
        color:#0f172a;
        letter-spacing:-.015em;
    }

    .ego-hr-panel-note{
        margin-top:3px;
        font-size:12px;
        font-weight:650;
        color:#64748b;
    }

    .ego-hr-panel-body{
        padding:14px 15px 16px;
    }

    .ego-hr-form-row{
        display:grid;
        grid-template-columns:minmax(180px,1fr) auto;
        gap:8px;
        margin-bottom:10px;
    }

    .ego-hr-upload-row{
        display:grid;
        grid-template-columns:minmax(150px,.75fr) minmax(180px,1fr) auto;
        gap:8px;
        margin-bottom:12px;
    }

    .ego-hr-input,
    .ego-hr-select,
    .ego-hr-file{
        width:100%;
        height:34px;
        border:1px solid #dbe3ef;
        border-radius:10px;
        background:#fff;
        color:#0f172a;
        font-size:12px;
        font-weight:750;
        outline:none;
    }

    .ego-hr-input,
    .ego-hr-select{
        padding:0 10px;
    }

    .ego-hr-file{
        padding:6px 9px;
    }

    .ego-hr-input:focus,
    .ego-hr-select:focus,
    .ego-hr-file:focus{
        border-color:#14b8a6;
        box-shadow:0 0 0 3px rgba(20,184,166,.12);
    }

    .ego-hr-folder{
        border:1px solid #e2e8f0;
        border-radius:14px;
        background:#f8fafc;
        margin-top:9px;
        overflow:hidden;
    }

    .ego-hr-folder-head{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:10px;
        padding:10px 11px;
        border-bottom:1px solid #e8eef6;
    }

    .ego-hr-folder-title{
        display:flex;
        align-items:center;
        gap:8px;
        min-width:0;
        color:#0f172a;
        font-size:13px;
        font-weight:950;
    }

    .ego-hr-folder-title span{
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .ego-hr-folder-icon{
        width:26px;
        height:26px;
        border-radius:9px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:#ecfeff;
        color:#0891b2;
        flex:0 0 auto;
    }

    .ego-hr-files{
        padding:2px 11px 8px;
    }

    .ego-hr-file-row{
        min-height:38px;
        display:grid;
        grid-template-columns:minmax(0,1fr) auto;
        align-items:center;
        gap:10px;
        border-bottom:1px dashed #dce5ef;
    }

    .ego-hr-file-row:last-child{
        border-bottom:0;
    }

    .ego-hr-file-link{
        min-width:0;
        display:flex;
        align-items:center;
        gap:8px;
        color:#0369a1;
        text-decoration:none;
        font-size:12px;
        font-weight:850;
    }

    .ego-hr-file-link span{
        overflow:hidden;
        text-overflow:ellipsis;
        white-space:nowrap;
    }

    .ego-hr-empty{
        padding:12px;
        border:1px dashed #cbd5e1;
        border-radius:14px;
        background:#f8fafc;
        color:#64748b;
        font-size:12px;
        font-weight:750;
        text-align:center;
    }

    .ego-hr-shortcuts{
        display:grid;
        grid-template-columns:repeat(1,minmax(0,1fr));
        gap:9px;
    }

    .ego-hr-shortcut{
        display:flex;
        align-items:flex-start;
        gap:10px;
        padding:12px;
        border:1px solid #dbeafe;
        border-radius:14px;
        background:#f8fafc;
        color:#0f172a;
        text-decoration:none;
        min-height:70px;
        transition:.16s ease;
    }

    .ego-hr-shortcut:hover{
        transform:translateY(-1px);
        border-color:#93c5fd;
        box-shadow:0 10px 20px rgba(37,99,235,.08);
    }

    .ego-hr-shortcut-icon{
        width:30px;
        height:30px;
        border-radius:11px;
        background:#eff6ff;
        color:#2563eb;
        display:flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        font-size:14px;
    }

    .ego-hr-shortcut-title{
        font-size:13px;
        line-height:1.25;
        font-weight:950;
        color:#0f172a;
    }

    .ego-hr-shortcut-desc{
        margin-top:3px;
        font-size:11.5px;
        line-height:1.35;
        font-weight:650;
        color:#64748b;
    }

    .ego-hr-alert{
        margin-bottom:12px;
        padding:10px 12px;
        border:1px solid #bbf7d0;
        background:#f0fdf4;
        color:#166534;
        border-radius:14px;
        font-size:12px;
        font-weight:850;
    }

    @media(max-width:1100px){
        .ego-hr-grid{grid-template-columns:1fr}
        .ego-hr-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    }

    @media(max-width:720px){
        .ego-hr-head{display:block}
        .ego-hr-top-actions{margin-top:10px}
        .ego-hr-stats{grid-template-columns:1fr}
        .ego-hr-form-row,
        .ego-hr-upload-row{grid-template-columns:1fr}
        .ego-hr-btn,
        .ego-hr-btn-outline,
        .ego-hr-btn-danger{width:100%}
    }
</style>

<div class="ego-hr-wrap">
    <div class="ego-hr-head">
        <div>
            <h1 class="ego-hr-title">HS nhân sự</h1>
            <div class="ego-hr-sub">Tổng hợp hồ sơ nhân sự, folder, phòng ban và chức vụ.</div>
        </div>

        <div class="ego-hr-top-actions">
            <a class="ego-hr-btn-outline" href="{{ route('hr.employees.index') }}">Nhân viên</a>
            <a class="ego-hr-btn-outline" href="{{ route('hr.departments.index') }}">Phòng ban</a>
            <a class="ego-hr-btn-outline" href="{{ route('hr.positions.index') }}">Chức vụ</a>
            <a class="ego-hr-btn" href="{{ route('hr.operations.index') }}">HC & Vận Hành</a>
        </div>
    </div>

    @if(session('success'))
        <div class="ego-hr-alert">{{ session('success') }}</div>
    @endif

    <div class="ego-hr-stats">
        <div class="ego-hr-stat">
            <div class="ego-hr-stat-label">Tổng nhân sự</div>
            <div class="ego-hr-stat-value">{{ number_format($stats['employees'] ?? 0) }}</div>
        </div>
        <div class="ego-hr-stat">
            <div class="ego-hr-stat-label">Phòng ban</div>
            <div class="ego-hr-stat-value">{{ number_format($stats['departments'] ?? 0) }}</div>
        </div>
        <div class="ego-hr-stat">
            <div class="ego-hr-stat-label">Chức vụ</div>
            <div class="ego-hr-stat-value">{{ number_format($stats['positions'] ?? 0) }}</div>
        </div>
    </div>

    <div class="ego-hr-grid">
        <div class="ego-hr-panel">
            <div class="ego-hr-panel-head">
                <div>
                    <h3 class="ego-hr-panel-title">Folder & hồ sơ nhân sự</h3>
                    <div class="ego-hr-panel-note">Tự tạo folder, upload file và tải lại khi cần.</div>
                </div>
            </div>

            <div class="ego-hr-panel-body">
                <form method="POST" action="{{ route('hr.documents.folders.store', $category) }}">
                    @csrf
                    <div class="ego-hr-form-row">
                        <input class="ego-hr-input" name="name" placeholder="Tên folder mới..." required>
                        <button class="ego-hr-btn" type="submit">Thêm folder</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('hr.documents.files.store', $category) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="ego-hr-upload-row">
                        <select class="ego-hr-select" name="folder_id" required>
                            <option value="">Chọn folder</option>
                            @foreach($folders as $folder)
                                <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                            @endforeach
                        </select>
                        <input class="ego-hr-file" type="file" name="file" required>
                        <button class="ego-hr-btn" type="submit">Upload file</button>
                    </div>
                </form>

                @forelse($folders as $folder)
                    <div class="ego-hr-folder">
                        <div class="ego-hr-folder-head">
                            <div class="ego-hr-folder-title">
                                <div class="ego-hr-folder-icon">📁</div>
                                <span>{{ $folder->name }}</span>
                            </div>

                            <form method="POST" action="{{ route('hr.documents.folders.delete', $folder->id) }}" onsubmit="return confirm('Xóa folder này và toàn bộ file bên trong?')">
                                @csrf
                                @method('DELETE')
                                <button class="ego-hr-btn-danger" type="submit">Xóa folder</button>
                            </form>
                        </div>

                        <div class="ego-hr-files">
                            @forelse($folder->files as $file)
                                <div class="ego-hr-file-row">
                                    <a class="ego-hr-file-link" href="{{ route('hr.documents.files.download', $file->id) }}">
                                        📄 <span>{{ $file->original_name }}</span>
                                    </a>

                                    <form method="POST" action="{{ route('hr.documents.files.delete', $file->id) }}" onsubmit="return confirm('Xóa file này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ego-hr-btn-danger" type="submit">Xóa</button>
                                    </form>
                                </div>
                            @empty
                                <div class="ego-hr-empty">Chưa có file trong folder này.</div>
                            @endforelse
                        </div>
                    </div>
                @empty
                    <div class="ego-hr-empty">Chưa có folder. Tạo folder trước rồi upload file vào.</div>
                @endforelse
            </div>
        </div>

        <div class="ego-hr-panel">
            <div class="ego-hr-panel-head">
                <div>
                    <h3 class="ego-hr-panel-title">Quản lý hồ sơ</h3>
                    <div class="ego-hr-panel-note">Truy cập nhanh các danh mục nhân sự.</div>
                </div>
            </div>

            <div class="ego-hr-panel-body">
                <div class="ego-hr-shortcuts">
                    <a class="ego-hr-shortcut" href="{{ route('hr.employees.index') }}">
                        <div class="ego-hr-shortcut-icon">👥</div>
                        <div>
                            <div class="ego-hr-shortcut-title">Danh sách nhân viên</div>
                            <div class="ego-hr-shortcut-desc">Mở danh sách hồ sơ nhân viên hiện có.</div>
                        </div>
                    </a>

                    <a class="ego-hr-shortcut" href="{{ route('hr.departments.index') }}">
                        <div class="ego-hr-shortcut-icon">🏢</div>
                        <div>
                            <div class="ego-hr-shortcut-title">Phòng ban</div>
                            <div class="ego-hr-shortcut-desc">Quản lý cơ cấu phòng ban trong công ty.</div>
                        </div>
                    </a>

                    <a class="ego-hr-shortcut" href="{{ route('hr.positions.index') }}">
                        <div class="ego-hr-shortcut-icon">💼</div>
                        <div>
                            <div class="ego-hr-shortcut-title">Chức vụ</div>
                            <div class="ego-hr-shortcut-desc">Quản lý chức danh và vị trí công việc.</div>
                        </div>
                    </a>

                    <a class="ego-hr-shortcut" href="{{ route('hr.operations.index') }}">
                        <div class="ego-hr-shortcut-icon">⚙️</div>
                        <div>
                            <div class="ego-hr-shortcut-title">HC & Vận Hành</div>
                            <div class="ego-hr-shortcut-desc">Tài liệu hành chính và vận hành nội bộ.</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection