@extends('layouts.app')

@section('title', $item->title ?? 'Thông báo nhân sự')

@section('content')
@php
    $categoryLabels = [
        'general' => 'Thông báo chung',
        'holiday' => 'Ngày nghỉ',
        'event' => 'Chương trình',
        'policy' => 'Chính sách',
        'training' => 'Đào tạo',
        'urgent' => 'Khẩn',
    ];

    $categoryIcons = [
        'general' => '📢',
        'holiday' => '🏖️',
        'event' => '🎉',
        'policy' => '📌',
        'training' => '🎓',
        'urgent' => '⚠️',
    ];

    $isImage = fn($file) => str_starts_with((string) $file->mime_type, 'image/');
@endphp

<style>
    .hra-show {
        padding:24px 28px 42px;
        background:linear-gradient(180deg,#f7fbff 0%,#fff 100%);
        min-height:calc(100vh - 72px);
    }

    .hra-show-card {
        max-width:980px;
        margin:0 auto;
        background:#fff;
        border:1px solid #e5edf7;
        border-radius:26px;
        box-shadow:0 24px 60px rgba(15,23,42,.08);
        overflow:hidden;
    }

    .hra-show-hero {
        padding:28px;
        color:#fff;
        background:
            radial-gradient(circle at 85% 12%,rgba(34,211,238,.22),transparent 32%),
            linear-gradient(135deg,#0f172a,#155e75 56%,#2563eb);
    }

    .hra-show-icon {
        width:64px;
        height:64px;
        border-radius:22px;
        background:rgba(255,255,255,.16);
        display:grid;
        place-items:center;
        font-size:32px;
        margin-bottom:14px;
    }

    .hra-show-hero h1 {
        margin:0 0 10px;
        font-size:30px;
        font-weight:950;
        letter-spacing:-.04em;
    }

    .hra-show-meta {
        display:flex;
        flex-wrap:wrap;
        gap:8px;
    }

    .hra-pill {
        display:inline-flex;
        align-items:center;
        gap:5px;
        min-height:30px;
        padding:5px 11px;
        border-radius:999px;
        font-size:12px;
        font-weight:900;
        background:rgba(255,255,255,.15);
        color:#fff;
        border:1px solid rgba(255,255,255,.18);
    }

    .hra-body {
        padding:28px;
        color:#334155;
        font-size:15px;
        line-height:1.75;
        white-space:pre-wrap;
    }

    .hra-files {
        border-top:1px solid #e5edf7;
        padding:22px 28px 28px;
    }

    .hra-files h3 {
        font-size:18px;
        font-weight:950;
        margin-bottom:14px;
    }

    .hra-file-grid {
        display:grid;
        grid-template-columns:repeat(auto-fill,minmax(220px,1fr));
        gap:12px;
    }

    .hra-file {
        border:1px solid #e5edf7;
        border-radius:18px;
        overflow:hidden;
        background:#f8fafc;
        text-decoration:none;
        color:#0f172a;
    }

    .hra-file img {
        width:100%;
        height:160px;
        object-fit:cover;
        display:block;
        background:#e2e8f0;
    }

    .hra-file-info {
        padding:12px;
        font-weight:850;
        font-size:13px;
        word-break:break-word;
    }

    .hra-actions {
        display:flex;
        justify-content:space-between;
        gap:10px;
        padding:18px 28px;
        border-top:1px solid #e5edf7;
        background:#fbfdff;
    }

    .hra-btn {
        height:42px;
        border:0;
        border-radius:14px;
        padding:0 15px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        white-space:nowrap;
    }

    .hra-btn-light {
        color:#1d4ed8;
        background:#fff;
        border:1px solid #e5edf7;
    }

    .hra-btn-danger {
        color:#be123c;
        background:#fff1f2;
        border:1px solid #fecdd3;
    }

    @media(max-width:768px){
        .hra-show{padding:16px}
        .hra-actions{flex-direction:column}
    }
</style>

<div class="hra-show">
    <div class="hra-show-card">
        <div class="hra-show-hero">
            <div class="hra-show-icon">{{ $categoryIcons[$item->category] ?? '📢' }}</div>

            <h1>{{ $item->title }}</h1>

            <div class="hra-show-meta">
                <span class="hra-pill">{{ $categoryLabels[$item->category] ?? $item->category }}</span>
                <span class="hra-pill">{{ $item->creator_name ?: 'HR/Admin' }}</span>

                @if($item->created_at)
                    <span class="hra-pill">{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</span>
                @endif

                @if($item->is_pinned)
                    <span class="hra-pill">Đang ghim</span>
                @endif
            </div>
        </div>

        <div class="hra-body">{{ $item->body }}</div>

        @if($files->count())
            <div class="hra-files">
                <h3>Ảnh / file đính kèm</h3>

                <div class="hra-file-grid">
                    @foreach($files as $file)
                        @php
                            $url = \Illuminate\Support\Facades\Storage::disk('public')->url($file->path);
                        @endphp

                        <a class="hra-file" href="{{ $url }}" target="_blank">
                            @if($isImage($file))
                                <img src="{{ $url }}" alt="{{ $file->original_name }}">
                            @else
                                <div style="height:160px;display:grid;place-items:center;font-size:42px;">📎</div>
                            @endif

                            <div class="hra-file-info">{{ $file->original_name }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="hra-actions">
            <a class="hra-btn hra-btn-light" href="{{ route('hr.announcements.index') }}">← Quay lại danh sách</a>

            @if($canManage)
                <form method="POST" action="{{ route('hr.announcements.destroy', $item->id) }}" onsubmit="return confirm('Xoá thông báo này?')">
                    @csrf
                    @method('DELETE')
                    <button class="hra-btn hra-btn-danger" type="submit">Xoá thông báo</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
