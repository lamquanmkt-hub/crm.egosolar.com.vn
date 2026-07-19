@extends('layouts.app')

@section('content')
<style>
.company-doc-page{
    padding:22px;
    min-height:calc(100vh - 80px);
    background:#f3f6fb;
    color:#0f172a;
}

.company-doc-shell{
    max-width:100%;
    margin:0 auto;
}

.company-doc-hero{
    position:relative;
    overflow:hidden;
    border-radius:22px;
    padding:26px 28px;
    background:
        linear-gradient(135deg,#0f2f67 0%,#1554a8 48%,#0ea5b7 100%);
    box-shadow:0 18px 42px rgba(15,23,42,.14);
    margin-bottom:18px;
    color:#fff;
}

.company-doc-hero:before{
    content:"";
    position:absolute;
    right:-70px;
    top:-110px;
    width:310px;
    height:310px;
    border-radius:999px;
    background:rgba(255,255,255,.12);
}

.company-doc-hero-inner{
    position:relative;
    z-index:1;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:22px;
    flex-wrap:wrap;
}

.company-doc-title{
    display:flex;
    align-items:center;
    gap:18px;
}

.company-doc-title-icon{
    width:62px;
    height:62px;
    border-radius:18px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:rgba(255,255,255,.13);
    border:1px solid rgba(255,255,255,.18);
    box-shadow:inset 0 1px 0 rgba(255,255,255,.16);
}

.company-doc-title-icon i{
    font-size:29px;
    color:#fff;
}

.company-doc-title h1{
    margin:0;
    font-size:30px;
    line-height:1.1;
    font-weight:900;
    letter-spacing:-.035em;
    color:#fff;
}

.company-doc-title p{
    margin:8px 0 0;
    font-size:14px;
    line-height:1.55;
    color:rgba(255,255,255,.82);
    max-width:680px;
}

.company-doc-stats{
    display:flex;
    gap:12px;
    flex-wrap:wrap;
}

.company-doc-stat{
    min-width:124px;
    padding:14px 16px;
    border-radius:16px;
    background:rgba(255,255,255,.13);
    border:1px solid rgba(255,255,255,.18);
    backdrop-filter:blur(8px);
}

.company-doc-stat b{
    display:block;
    font-size:23px;
    line-height:1;
    font-weight:900;
    letter-spacing:-.02em;
    color:#fff;
}

.company-doc-stat span{
    display:block;
    margin-top:7px;
    font-size:12px;
    font-weight:700;
    color:rgba(255,255,255,.76);
}

.company-doc-card{
    background:#fff;
    border:1px solid #e3e9f2;
    border-radius:22px;
    box-shadow:0 10px 28px rgba(15,23,42,.06);
    margin-bottom:16px;
    overflow:hidden;
}

.company-doc-card-body{
    padding:20px;
}

.company-doc-tabs{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.company-doc-tab{
    min-height:44px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:9px;
    padding:0 17px;
    border-radius:14px;
    border:1px solid #d7e3f3;
    background:#fff;
    color:#1e40af;
    font-size:14px;
    font-weight:800;
    text-decoration:none!important;
    transition:.16s ease;
}

.company-doc-tab i{
    font-size:16px;
}

.company-doc-tab:hover{
    transform:translateY(-1px);
    border-color:#93c5fd;
    color:#1d4ed8;
    box-shadow:0 10px 22px rgba(37,99,235,.08);
}

.company-doc-tab.active{
    color:#fff;
    border-color:#2563eb;
    background:linear-gradient(135deg,#2563eb,#0891b2);
    box-shadow:0 14px 30px rgba(37,99,235,.20);
}

.company-doc-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:18px;
}

.company-doc-kicker{
    font-size:11px;
    font-weight:900;
    letter-spacing:.11em;
    text-transform:uppercase;
    color:#64748b;
    margin-bottom:4px;
}

.company-doc-head h2{
    margin:0;
    font-size:24px;
    line-height:1.15;
    font-weight:900;
    letter-spacing:-.035em;
    color:#0f172a;
    display:flex;
    align-items:center;
    gap:9px;
}

.company-doc-head h2 i{
    color:#2563eb;
    font-size:21px;
}

.company-doc-breadcrumb{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
    margin-top:8px;
    font-size:13px;
    color:#64748b;
}

.company-doc-breadcrumb a{
    color:#2563eb;
    font-weight:800;
    text-decoration:none;
}

.company-doc-action-grid{
    display:grid;
    grid-template-columns:minmax(340px,.92fr) minmax(420px,1.08fr);
    gap:16px;
}

.company-doc-box{
    border:1px solid #e2e8f0;
    border-radius:18px;
    background:#fff;
    padding:18px;
    transition:.16s ease;
}

.company-doc-box:hover{
    border-color:#bfdbfe;
    box-shadow:0 14px 30px rgba(15,23,42,.07);
}

.company-doc-box-title{
    display:flex;
    align-items:center;
    gap:13px;
    margin-bottom:16px;
}

.company-doc-box-icon{
    width:46px;
    height:46px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eff6ff;
    color:#2563eb;
    border:1px solid #dbeafe;
}

.company-doc-box-icon i{
    font-size:21px;
}

.company-doc-box-title strong{
    display:block;
    font-size:16px;
    line-height:1.2;
    font-weight:900;
    color:#0f172a;
}

.company-doc-box-title span{
    display:block;
    margin-top:3px;
    font-size:12.5px;
    line-height:1.35;
    color:#64748b;
}

.company-doc-input-row{
    display:flex;
    align-items:center;
    gap:10px;
}

.company-doc-input,
.company-doc-file{
    width:100%;
    height:48px;
    border:1px solid #cbd5e1;
    border-radius:13px;
    padding:0 14px;
    outline:none;
    background:#fff;
    color:#0f172a;
    font-size:14px;
    font-weight:650;
    transition:.15s ease;
}

.company-doc-file{
    padding:10px 12px;
}

.company-doc-input::placeholder{
    color:#94a3b8;
    font-weight:600;
}

.company-doc-input:focus,
.company-doc-file:focus{
    border-color:#3b82f6;
    box-shadow:0 0 0 4px rgba(59,130,246,.11);
}

.company-doc-btn{
    min-height:48px;
    border-radius:13px;
    padding:0 18px;
    border:1px solid transparent;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    font-size:14px;
    font-weight:900;
    line-height:1;
    white-space:nowrap;
    cursor:pointer;
    text-decoration:none!important;
    transition:.15s ease;
}

.company-doc-btn i{
    font-size:15px;
}

.company-doc-btn:hover{
    transform:translateY(-1px);
}

.company-doc-btn-primary{
    background:#2563eb;
    border-color:#2563eb;
    color:#fff;
    box-shadow:0 10px 22px rgba(37,99,235,.18);
}

.company-doc-btn-success{
    background:#059669;
    border-color:#059669;
    color:#fff;
    box-shadow:0 10px 22px rgba(5,150,105,.16);
}

.company-doc-btn-light{
    background:#fff;
    border-color:#dbe3ef;
    color:#0f172a;
}

.company-doc-btn-download{
    min-height:38px;
    border-radius:11px;
    padding:0 13px;
    background:#eff6ff;
    border-color:#bfdbfe;
    color:#1d4ed8;
    font-size:12.5px;
}

.company-doc-btn-danger{
    min-height:38px;
    border-radius:11px;
    padding:0 13px;
    background:#fff1f2;
    border-color:#fecdd3;
    color:#be123c;
    font-size:12.5px;
}

.company-doc-note{
    margin-top:10px;
    font-size:12.5px;
    line-height:1.45;
    color:#64748b;
}

.company-doc-list-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    margin-bottom:16px;
}

.company-doc-list-head h3{
    margin:0;
    font-size:22px;
    line-height:1.15;
    font-weight:900;
    letter-spacing:-.03em;
    color:#0f172a;
}

.company-doc-sub{
    margin-top:5px;
    font-size:13px;
    color:#64748b;
    font-weight:600;
}

.company-doc-search{
    position:relative;
    width:100%;
    max-width:360px;
}

.company-doc-search i{
    position:absolute;
    left:14px;
    top:50%;
    transform:translateY(-50%);
    color:#64748b;
    font-size:15px;
}

.company-doc-search input{
    width:100%;
    height:44px;
    border:1px solid #dbe3ef;
    border-radius:13px;
    padding:0 14px 0 40px;
    outline:none;
    background:#fff;
    color:#0f172a;
    font-size:14px;
    font-weight:650;
}

.company-doc-search input:focus{
    border-color:#3b82f6;
    box-shadow:0 0 0 4px rgba(59,130,246,.10);
}

.company-doc-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
    gap:14px;
}

.company-doc-item{
    min-height:158px;
    border:1px solid #e2e8f0;
    border-radius:18px;
    background:#fff;
    padding:16px;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    box-shadow:0 8px 18px rgba(15,23,42,.035);
    transition:.16s ease;
}

.company-doc-item:hover{
    transform:translateY(-2px);
    border-color:#bfdbfe;
    box-shadow:0 18px 34px rgba(15,23,42,.08);
}

.company-doc-item-top{
    display:flex;
    align-items:flex-start;
    gap:13px;
}

.company-doc-item-icon{
    width:48px;
    height:48px;
    border-radius:14px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 auto;
    background:#eff6ff;
    color:#2563eb;
    border:1px solid #dbeafe;
}

.company-doc-item.folder .company-doc-item-icon{
    background:#fff7ed;
    color:#ea580c;
    border-color:#fed7aa;
}

.company-doc-item-icon i{
    font-size:22px;
}

.company-doc-item-name{
    font-size:15px;
    line-height:1.35;
    font-weight:900;
    color:#0f172a;
    overflow-wrap:anywhere;
}

.company-doc-item-name a{
    color:#0f172a;
    text-decoration:none;
}

.company-doc-item-name a:hover{
    color:#2563eb;
}

.company-doc-meta{
    display:flex;
    flex-direction:column;
    gap:5px;
    margin-top:9px;
    color:#64748b;
    font-size:12.5px;
    line-height:1.25;
    font-weight:650;
}

.company-doc-meta span{
    display:flex;
    align-items:center;
    gap:6px;
}

.company-doc-actions{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    flex-wrap:wrap;
    margin-top:15px;
}

.company-doc-empty{
    border:1px dashed #cbd5e1;
    border-radius:18px;
    padding:38px 20px;
    text-align:center;
    background:#f8fafc;
    color:#64748b;
}

.company-doc-empty-icon{
    width:70px;
    height:70px;
    margin:0 auto 12px;
    border-radius:20px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eff6ff;
    color:#2563eb;
    border:1px solid #dbeafe;
}

.company-doc-empty-icon i{
    font-size:30px;
}

.company-doc-empty-title{
    font-size:17px;
    font-weight:900;
    color:#0f172a;
    margin-bottom:4px;
}

.company-doc-alert{
    border-radius:16px;
    padding:13px 16px;
    margin-bottom:14px;
    background:#ecfdf5;
    border:1px solid #bbf7d0;
    color:#065f46;
    font-size:14px;
    font-weight:800;
}



/* EGO_DOC_CLIP_FIXED */
.company-doc-btn-copy{min-height:38px;border-radius:11px;padding:0 13px;background:#f8fafc;border-color:#cbd5e1;color:#334155;font-size:12.5px}
.company-doc-btn-move{min-height:38px;border-radius:11px;padding:0 13px;background:#fefce8;border-color:#fde68a;color:#854d0e;font-size:12.5px}
.company-doc-clipboard{border:1px solid #bae6fd;background:linear-gradient(135deg,#eff6ff,#ecfeff)}
.company-doc-clipboard .company-doc-card-body{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.company-doc-clipboard-title{font-weight:900;color:#0f172a}
.company-doc-clipboard-sub{font-size:12.5px;color:#64748b;font-weight:700;margin-top:4px}
.company-doc-btn-preview{
    min-height:38px;
    border-radius:11px;
    padding:0 13px;
    background:#ecfeff;
    border-color:#99f6e4;
    color:#0f766e;
    font-size:12.5px;
}

.company-doc-preview-modal{
    position:fixed;
    inset:0;
    z-index:99999;
    display:none;
    background:rgba(15,23,42,.64);
    backdrop-filter:blur(5px);
    padding:22px;
}

.company-doc-preview-modal.show{
    display:flex;
    align-items:center;
    justify-content:center;
}

.company-doc-preview-box{
    width:min(1180px,96vw);
    height:min(820px,92vh);
    background:#fff;
    border-radius:22px;
    overflow:hidden;
    box-shadow:0 30px 90px rgba(15,23,42,.34);
    display:flex;
    flex-direction:column;
}

.company-doc-preview-head{
    height:58px;
    padding:0 16px 0 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    background:linear-gradient(135deg,#0f2f67,#0891b2);
    color:#fff;
}

.company-doc-preview-title{
    min-width:0;
    font-size:15px;
    font-weight:900;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
}

.company-doc-preview-actions{
    display:flex;
    align-items:center;
    gap:8px;
    flex:0 0 auto;
}

.company-doc-preview-actions a,
.company-doc-preview-actions button{
    height:36px;
    border:1px solid rgba(255,255,255,.28);
    border-radius:11px;
    padding:0 12px;
    background:rgba(255,255,255,.13);
    color:#fff;
    font-size:12px;
    font-weight:900;
    cursor:pointer;
    text-decoration:none!important;
}

.company-doc-preview-frame{
    flex:1;
    width:100%;
    border:0;
    background:#f8fafc;
}

@media(max-width:576px){
    .company-doc-preview-modal{
        padding:10px;
    }

    .company-doc-preview-box{
        width:100%;
        height:94vh;
        border-radius:16px;
    }

    .company-doc-preview-head{
        height:auto;
        min-height:58px;
        flex-wrap:wrap;
        padding:10px;
    }
}

@media(max-width:992px){
    .company-doc-action-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:576px){
    .company-doc-page{
        padding:12px;
    }

    .company-doc-hero{
        padding:20px;
    }

    .company-doc-title{
        align-items:flex-start;
    }

    .company-doc-title h1{
        font-size:25px;
    }

    .company-doc-input-row{
        flex-direction:column;
        align-items:stretch;
    }

    .company-doc-btn{
        width:100%;
    }

    .company-doc-grid{
        grid-template-columns:1fr;
    }
}
</style>

@php
    $companyDocClipboard = session('company_doc_clipboard');
    $folderCount = $folders->count();
    $fileCount = $files->count();
    $totalSize = $files->sum('size');

    $formatBytes = function ($bytes) {
        $bytes = (float) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        return number_format($bytes, 0, ',', '.') . ' B';
    };

    $depIcons = [
        'sales' => 'bi-briefcase',
        'marketing' => 'bi-megaphone',
        'technical' => 'bi-tools',
        'accounting' => 'bi-receipt',
        'assistant' => 'bi-person-workspace',
        'media' => 'bi-images',
    ];

    $fileIcon = function ($name) {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf'], true)) return 'bi-file-earmark-pdf';
        if (in_array($ext, ['doc','docx'], true)) return 'bi-file-earmark-word';
        if (in_array($ext, ['xls','xlsx','csv'], true)) return 'bi-file-earmark-excel';
        if (in_array($ext, ['ppt','pptx'], true)) return 'bi-file-earmark-ppt';
        if (in_array($ext, ['jpg','jpeg','png','gif','webp','svg'], true)) return 'bi-file-earmark-image';
        if (in_array($ext, ['zip','rar','7z'], true)) return 'bi-file-earmark-zip';
        return 'bi-file-earmark-text';
    };
@endphp

<div class="company-doc-page">
    <div class="company-doc-shell">

        <div class="company-doc-hero">
            <div class="company-doc-hero-inner">
                <div class="company-doc-title">
                    <div class="company-doc-title-icon">
                        <i class="bi bi-folder2-open"></i>
                    </div>
                    <div>
                        <h1>Hồ sơ công ty</h1>
                        <p>Quản lý tài liệu nội bộ theo từng phòng ban, phân quyền rõ ràng, lưu trữ tập trung và dễ tra cứu.</p>
                    </div>
                </div>

                <div class="company-doc-stats">
                    <div class="company-doc-stat">
                        <b>{{ $folderCount }}</b>
                        <span>Folder</span>
                    </div>
                    <div class="company-doc-stat">
                        <b>{{ $fileCount }}</b>
                        <span>File</span>
                    </div>
                    <div class="company-doc-stat">
                        <b>{{ $formatBytes($totalSize) }}</b>
                        <span>Dung lượng</span>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="company-doc-alert">
                <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger" style="border-radius:16px">
                <strong>Có lỗi:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="company-doc-card">
            <div class="company-doc-card-body">
                <div class="company-doc-tabs">
                    @foreach($allowed as $dep)
                        <a class="company-doc-tab {{ $department === $dep ? 'active' : '' }}"
                           href="{{ route('company-documents.index', ['department' => $dep]) }}">
                            <i class="bi {{ $depIcons[$dep] ?? 'bi-folder' }}"></i>
                            <span>{{ $departments[$dep]['label'] ?? $dep }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="company-doc-card">
            <div class="company-doc-card-body">
                <div class="company-doc-head">
                    <div>
                        <div class="company-doc-kicker">Đang xem</div>
                        <h2>
                            <i class="bi {{ $depIcons[$department] ?? 'bi-folder' }}"></i>
                            {{ $departments[$department]['label'] ?? $department }}
                        </h2>
                        <div class="company-doc-breadcrumb">
                            <a href="{{ route('company-documents.index', ['department' => $department]) }}">Thư mục gốc</a>
                            @if($currentFolder)
                                <span>/</span>
                                <strong>{{ $currentFolder->name }}</strong>
                            @endif
                        </div>
                    </div>

                    @if($currentFolder)
                        <a class="company-doc-btn company-doc-btn-light"
                           href="{{ route('company-documents.index', ['department' => $department, 'folder' => $currentFolder->parent_id]) }}">
                            <i class="bi bi-arrow-left"></i>
                            Quay lại
                        </a>
                    @endif
                </div>

                <div class="company-doc-action-grid">
                    <form method="post" action="{{ route('company-documents.folders.store') }}" class="company-doc-box">
                        @csrf
                        <input type="hidden" name="department" value="{{ $department }}">
                        <input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}">

                        <div class="company-doc-box-title">
                            <div class="company-doc-box-icon">
                                <i class="bi bi-folder-plus"></i>
                            </div>
                            <div>
                                <strong>Tạo folder mới</strong>
                                <span>Phân loại tài liệu theo nhóm, dự án hoặc nghiệp vụ.</span>
                            </div>
                        </div>

                        <div class="company-doc-input-row">
                            <input class="company-doc-input" name="name" placeholder="Nhập tên folder..." required>
                            <button class="company-doc-btn company-doc-btn-primary" type="submit">
                                <i class="bi bi-plus-lg"></i>
                                Tạo
                            </button>
                        </div>
                    </form>

                    <form method="post" action="{{ route('company-documents.files.upload') }}" enctype="multipart/form-data" class="company-doc-box">
                        @csrf
                        <input type="hidden" name="department" value="{{ $department }}">
                        <input type="hidden" name="folder_id" value="{{ $currentFolder?->id }}">

                        <div class="company-doc-box-title">
                            <div class="company-doc-box-icon">
                                <i class="bi bi-cloud-arrow-up"></i>
                            </div>
                            <div>
                                <strong>Upload tài liệu</strong>
                                <span>Hỗ trợ chọn nhiều file và lưu theo đúng phòng ban.</span>
                            </div>
                        </div>

                        <div class="company-doc-input-row">
                            <input class="company-doc-file" type="file" name="files[]" multiple required>
                            <button class="company-doc-btn company-doc-btn-success" type="submit">
                                <i class="bi bi-upload"></i>
                                Upload
                            </button>
                        </div>

                        <div class="company-doc-note">
                            Tối đa 50MB/file. File lưu trong storage/app/public/company-documents.
                        </div>
                    </form>
                </div>
            </div>
        </div>


        {{-- EGO_DOC_PASTE_FIXED --}}
        @if($companyDocClipboard)
            <div class="company-doc-card company-doc-clipboard">
                <div class="company-doc-card-body">
                    <div>
                        <div class="company-doc-clipboard-title">
                            Đang {{ ($companyDocClipboard['action'] ?? '') === 'copy' ? 'sao chép' : 'di chuyển' }}
                            {{ ($companyDocClipboard['object_type'] ?? '') === 'folder' ? 'folder' : 'file' }}
                        </div>
                        <div class="company-doc-clipboard-sub">
                            {{ $companyDocClipboard['name'] ?? 'Đã chọn mục' }}
                            → Dán vào: <strong>{{ $currentFolder?->name ?? 'Thư mục gốc' }}</strong>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <form method="post" action="{{ route('company-documents.paste') }}">
                            @csrf
                            <input type="hidden" name="department" value="{{ $department }}">
                            <input type="hidden" name="folder_id" value="{{ $currentFolder?->id }}">
                            <button class="company-doc-btn company-doc-btn-success" type="submit">
                                Dán vào đây
                            </button>
                        </form>

                        <form method="post" action="{{ route('company-documents.clipboard.clear') }}">
                            @csrf
                            <button class="company-doc-btn company-doc-btn-light" type="submit">
                                Hủy
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endif
        {{-- EGO_DOC_PASTE_FIXED_END --}}
        <div class="company-doc-card">
            <div class="company-doc-card-body">
                <div class="company-doc-list-head">
                    <div>
                        <h3>Danh sách hồ sơ</h3>
                        <div class="company-doc-sub">{{ $folderCount }} folder • {{ $fileCount }} file trong mục hiện tại</div>
                    </div>

                    <div class="company-doc-search">
                        <i class="bi bi-search"></i>
                        <input id="companyDocSearch" type="search" placeholder="Tìm nhanh folder hoặc file...">
                    </div>
                </div>

                @if($folders->isEmpty() && $files->isEmpty())
                    <div class="company-doc-empty">
                        <div class="company-doc-empty-icon">
                            <i class="bi bi-inboxes"></i>
                        </div>
                        <div class="company-doc-empty-title">Chưa có hồ sơ nào</div>
                        <div>Tạo folder hoặc upload file đầu tiên cho phòng ban này.</div>
                    </div>
                @else
                    <div class="company-doc-grid" id="companyDocGrid">
                        @foreach($folders as $folder)
                            <div class="company-doc-item folder" data-name="{{ mb_strtolower($folder->name, 'UTF-8') }}">
                                <div>
                                    <div class="company-doc-item-top">
                                        <div class="company-doc-item-icon">
                                            <i class="bi bi-folder-fill"></i>
                                        </div>
                                        <div>
                                            <div class="company-doc-item-name">
                                                <a href="{{ route('company-documents.index', ['department' => $department, 'folder' => $folder->id]) }}">
                                                    {{ $folder->name }}
                                                </a>
                                            </div>
                                            <div class="company-doc-meta">
                                                <span><i class="bi bi-folder"></i> Folder</span>
                                                <span><i class="bi bi-clock"></i> {{ optional($folder->created_at)->format('d/m/Y H:i') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="company-doc-actions">
                                    <a class="company-doc-btn company-doc-btn-download"
                                       href="{{ route('company-documents.index', ['department' => $department, 'folder' => $folder->id]) }}">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                        Mở
                                    </a>


                                    {{-- EGO_DOC_FOLDER_COPY_FIXED --}}
                                    <form method="post" action="{{ route('company-documents.clipboard.set') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="object_type" value="folder">
                                        <input type="hidden" name="object_id" value="{{ $folder->id }}">
                                        <input type="hidden" name="action" value="copy">
                                        <button class="company-doc-btn company-doc-btn-copy" type="submit">Sao chép</button>
                                    </form>

                                    <form method="post" action="{{ route('company-documents.clipboard.set') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="object_type" value="folder">
                                        <input type="hidden" name="object_id" value="{{ $folder->id }}">
                                        <input type="hidden" name="action" value="move">
                                        <button class="company-doc-btn company-doc-btn-move" type="submit">Di chuyển</button>
                                    </form>
                                    {{-- EGO_DOC_FOLDER_COPY_FIXED_END --}}
                                    <form method="post" action="{{ route('company-documents.folders.destroy', $folder) }}"
                                          onsubmit="return confirm('Xóa folder này và toàn bộ file bên trong?')" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="company-doc-btn company-doc-btn-danger" type="submit">
                                            <i class="bi bi-trash3"></i>
                                            Xóa
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach

                        @foreach($files as $file)
                            <div class="company-doc-item file" data-name="{{ mb_strtolower($file->original_name, 'UTF-8') }}">
                                <div>
                                    <div class="company-doc-item-top">
                                        <div class="company-doc-item-icon">
                                            <i class="bi {{ $fileIcon($file->original_name) }}"></i>
                                        </div>
                                        <div>
                                            <div class="company-doc-item-name">{{ $file->original_name }}</div>
                                            <div class="company-doc-meta">
                                                <span><i class="bi bi-file-earmark"></i> {{ $file->mime ?: 'File' }}</span>
                                                <span><i class="bi bi-hdd"></i> {{ $formatBytes($file->size ?? 0) }}</span>
                                                <span><i class="bi bi-clock"></i> {{ optional($file->created_at)->format('d/m/Y H:i') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="company-doc-actions">
                                    <button type="button"
                                            class="company-doc-btn company-doc-btn-preview"
                                            data-preview-url="{{ route('company-documents.files.preview', $file) }}"
                                            data-download-url="{{ route('company-documents.files.download', $file) }}"
                                            data-file-name="{{ e($file->original_name) }}"
                                            onclick="openCompanyDocPreview(this)">
                                        <i class="bi bi-eye"></i>
                                        Xem trước
                                    </button>

                                    <a class="company-doc-btn company-doc-btn-download" href="{{ route('company-documents.files.download', $file) }}">
                                        <i class="bi bi-download"></i>
                                        Tải xuống
                                    </a>


                                    {{-- EGO_DOC_FILE_COPY_FIXED --}}
                                    <form method="post" action="{{ route('company-documents.clipboard.set') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="object_type" value="file">
                                        <input type="hidden" name="object_id" value="{{ $file->id }}">
                                        <input type="hidden" name="action" value="copy">
                                        <button class="company-doc-btn company-doc-btn-copy" type="submit">Sao chép</button>
                                    </form>

                                    <form method="post" action="{{ route('company-documents.clipboard.set') }}" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="object_type" value="file">
                                        <input type="hidden" name="object_id" value="{{ $file->id }}">
                                        <input type="hidden" name="action" value="move">
                                        <button class="company-doc-btn company-doc-btn-move" type="submit">Di chuyển</button>
                                    </form>
                                    {{-- EGO_DOC_FILE_COPY_FIXED_END --}}
                                    <form method="post" action="{{ route('company-documents.files.destroy', $file) }}"
                                          onsubmit="return confirm('Xóa file này?')" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="company-doc-btn company-doc-btn-danger" type="submit">
                                            <i class="bi bi-trash3"></i>
                                            Xóa
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div id="companyDocNoResult" class="company-doc-empty" style="display:none;margin-top:14px">
                        <div class="company-doc-empty-icon">
                            <i class="bi bi-search"></i>
                        </div>
                        <div class="company-doc-empty-title">Không tìm thấy kết quả</div>
                        <div>Thử nhập từ khóa khác.</div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>


<div id="companyDocPreviewModal" class="company-doc-preview-modal" onclick="closeCompanyDocPreview(event)">
    <div class="company-doc-preview-box" onclick="event.stopPropagation()">
        <div class="company-doc-preview-head">
            <div id="companyDocPreviewTitle" class="company-doc-preview-title">Xem trước file</div>
            <div class="company-doc-preview-actions">
                <a id="companyDocPreviewDownload" href="#" target="_blank">
                    <i class="bi bi-download"></i> Tải xuống
                </a>
                <button type="button" onclick="closeCompanyDocPreview()">
                    <i class="bi bi-x-lg"></i> Đóng
                </button>
            </div>
        </div>
        <iframe id="companyDocPreviewFrame" class="company-doc-preview-frame" src="about:blank"></iframe>
    </div>
</div>


<script>
(function(){
    var input = document.getElementById('companyDocSearch');
    var grid = document.getElementById('companyDocGrid');
    var noResult = document.getElementById('companyDocNoResult');

    if (!input || !grid) return;

    input.addEventListener('input', function(){
        var q = (input.value || '').toLowerCase().trim();
        var items = grid.querySelectorAll('.company-doc-item');
        var shown = 0;

        items.forEach(function(item){
            var name = (item.getAttribute('data-name') || '').toLowerCase();
            var ok = !q || name.indexOf(q) !== -1;
            item.style.display = ok ? '' : 'none';
            if (ok) shown++;
        });

        if (noResult) {
            noResult.style.display = shown ? 'none' : '';
        }
    });
})();
</script>

<script>
window.openCompanyDocPreview = function(btn){
    var modal = document.getElementById('companyDocPreviewModal');
    var frame = document.getElementById('companyDocPreviewFrame');
    var title = document.getElementById('companyDocPreviewTitle');
    var download = document.getElementById('companyDocPreviewDownload');

    if (!modal || !frame) {
        alert('Chưa có khung xem trước. Vui lòng clear cache rồi thử lại.');
        return;
    }

    var previewUrl = btn.getAttribute('data-preview-url');
    var downloadUrl = btn.getAttribute('data-download-url');
    var fileName = btn.getAttribute('data-file-name') || 'Xem trước file';

    title.textContent = fileName;
    download.href = downloadUrl || previewUrl;
    frame.src = previewUrl;
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
};

window.closeCompanyDocPreview = function(event){
    if (event && event.target && event.target.id !== 'companyDocPreviewModal') {
        return;
    }

    var modal = document.getElementById('companyDocPreviewModal');
    var frame = document.getElementById('companyDocPreviewFrame');

    if (frame) {
        frame.src = 'about:blank';
    }

    if (modal) {
        modal.classList.remove('show');
    }

    document.body.style.overflow = '';
};

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
        window.closeCompanyDocPreview();
    }
});
</script>

@endsection
