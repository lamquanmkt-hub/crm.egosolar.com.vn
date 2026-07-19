@extends('layouts.app')

@section('title', 'Thông báo nhân sự')

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

    $targetLabels = [
        'all' => 'Toàn công ty',
        'department' => 'Phòng ban',
        'user' => 'Cá nhân',
    ];

    $statusLabels = [
        'draft' => 'Nháp',
        'published' => 'Đã đăng',
        'archived' => 'Lưu trữ',
    ];

    $categoryIcons = [
        'general' => '📢',
        'holiday' => '🏖️',
        'event' => '🎉',
        'policy' => '📌',
        'training' => '🎓',
        'urgent' => '⚠️',
    ];
@endphp

<style>
    .hra-page {
        --text:#0f172a;
        --muted:#64748b;
        --line:#e5edf7;
        --blue:#2563eb;
        --cyan:#06b6d4;
        --green:#059669;
        --amber:#f59e0b;
        --red:#e11d48;
        padding:24px 28px 42px;
        background:linear-gradient(180deg,#f7fbff 0%,#f8fafc 45%,#fff 100%);
        min-height:calc(100vh - 72px);
        color:var(--text);
    }

    .hra-hero {
        border-radius:28px;
        padding:26px;
        color:#fff;
        background:
            radial-gradient(circle at 8% 16%,rgba(34,211,238,.24),transparent 34%),
            radial-gradient(circle at 88% 12%,rgba(59,130,246,.28),transparent 30%),
            linear-gradient(135deg,#0f172a,#155e75 48%,#2563eb);
        box-shadow:0 26px 70px rgba(37,99,235,.22);
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:18px;
        margin-bottom:18px;
    }

    .hra-hero h1 {
        margin:0 0 8px;
        font-size:30px;
        font-weight:950;
        letter-spacing:-.04em;
    }

    .hra-hero p {
        margin:0;
        color:rgba(255,255,255,.8);
        max-width:760px;
    }

    .hra-btn {
        height:44px;
        border:0;
        border-radius:14px;
        padding:0 16px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        gap:8px;
        font-weight:900;
        text-decoration:none;
        cursor:pointer;
        white-space:nowrap;
    }

    .hra-btn-primary {
        color:#fff;
        background:linear-gradient(135deg,#2563eb,#1d4ed8);
        box-shadow:0 14px 30px rgba(37,99,235,.26);
    }

    .hra-btn-white {
        color:#fff;
        background:rgba(255,255,255,.14);
        border:1px solid rgba(255,255,255,.22);
    }

    .hra-btn-light {
        color:#1d4ed8;
        background:#fff;
        border:1px solid var(--line);
    }

    .hra-btn-danger {
        color:#be123c;
        background:#fff1f2;
        border:1px solid #fecdd3;
    }

    .hra-alert {
        padding:13px 15px;
        border-radius:16px;
        margin-bottom:14px;
        font-weight:850;
    }

    .hra-alert.ok { background:#dcfce7;color:#047857; }
    .hra-alert.err { background:#ffe4e6;color:#be123c; }

    .hra-kpis {
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:14px;
        margin-bottom:18px;
    }

    .hra-kpi {
        background:#fff;
        border:1px solid var(--line);
        border-radius:22px;
        padding:18px;
        box-shadow:0 24px 54px rgba(15,23,42,.07);
    }

    .hra-kpi .k {
        color:#475569;
        font-size:12px;
        font-weight:950;
        text-transform:uppercase;
        margin-bottom:8px;
    }

    .hra-kpi .v {
        font-size:25px;
        font-weight:950;
        letter-spacing:-.04em;
    }

    .hra-card {
        background:#fff;
        border:1px solid var(--line);
        border-radius:24px;
        box-shadow:0 24px 56px rgba(15,23,42,.07);
        overflow:hidden;
        margin-bottom:18px;
    }

    .hra-card-head {
        padding:18px 20px;
        border-bottom:1px solid var(--line);
        display:flex;
        justify-content:space-between;
        align-items:center;
        gap:14px;
    }

    .hra-card-title {
        font-size:19px;
        font-weight:950;
        letter-spacing:-.02em;
        margin-bottom:4px;
    }

    .hra-sub {
        color:var(--muted);
        font-size:13px;
    }

    .hra-form {
        padding:20px;
        display:grid;
        grid-template-columns:repeat(4,minmax(0,1fr));
        gap:12px;
    }

    .hra-field label {
        display:block;
        color:#475569;
        font-size:12px;
        font-weight:950;
        margin-bottom:7px;
    }

    .hra-input,
    .hra-select,
    .hra-textarea {
        width:100%;
        border:1px solid var(--line);
        border-radius:14px;
        background:#fff;
        color:#0f172a;
        outline:none;
        padding:0 13px;
        box-shadow:0 10px 22px rgba(15,23,42,.04);
    }

    .hra-input,
    .hra-select { height:45px; }

    .hra-textarea {
        min-height:130px;
        padding:12px 13px;
        resize:vertical;
    }

    .span-2 { grid-column:span 2; }
    .span-4 { grid-column:span 4; }

    .hra-filter {
        padding:16px 18px;
        border-bottom:1px solid var(--line);
        display:grid;
        grid-template-columns:1.2fr 180px 160px 130px auto;
        gap:10px;
        align-items:end;
        background:#fbfdff;
    }

    .hra-tabs {
        display:flex;
        gap:8px;
        flex-wrap:wrap;
    }

    .hra-tab {
        height:36px;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:0 13px;
        border-radius:999px;
        font-weight:900;
        font-size:13px;
        text-decoration:none;
        color:#475569;
        background:#f1f5f9;
    }

    .hra-tab.active {
        color:#1d4ed8;
        background:#eaf2ff;
    }

    .hra-list {
        display:grid;
        gap:13px;
        padding:18px;
    }

    .hra-item {
        display:grid;
        grid-template-columns:54px minmax(0,1fr) auto;
        gap:14px;
        padding:16px;
        border:1px solid var(--line);
        border-radius:20px;
        background:#fff;
        transition:.16s ease;
    }

    .hra-item:hover {
        transform:translateY(-1px);
        box-shadow:0 18px 42px rgba(15,23,42,.08);
    }

    .hra-item.unread {
        background:linear-gradient(180deg,#eff6ff,#fff);
        border-color:#bfdbfe;
    }

    .hra-icon {
        width:54px;
        height:54px;
        border-radius:18px;
        display:grid;
        place-items:center;
        background:#eaf2ff;
        font-size:26px;
    }

    .hra-title {
        font-size:16px;
        font-weight:950;
        margin-bottom:6px;
    }

    .hra-title a {
        color:#0f172a;
        text-decoration:none;
    }

    .hra-title a:hover {
        color:#1d4ed8;
    }

    .hra-body {
        color:#475569;
        font-size:13px;
        line-height:1.5;
        max-width:900px;
    }

    .hra-meta {
        margin-top:10px;
        display:flex;
        flex-wrap:wrap;
        gap:8px;
    }

    .hra-pill {
        display:inline-flex;
        align-items:center;
        gap:5px;
        height:28px;
        padding:0 10px;
        border-radius:999px;
        font-size:12px;
        font-weight:900;
        background:#f1f5f9;
        color:#475569;
    }

    .hra-pill.blue { background:#eaf2ff;color:#1d4ed8; }
    .hra-pill.green { background:#dcfce7;color:#047857; }
    .hra-pill.amber { background:#fef3c7;color:#b45309; }
    .hra-pill.red { background:#ffe4e6;color:#be123c; }

    .hra-actions {
        display:flex;
        flex-direction:column;
        gap:8px;
        align-items:flex-end;
    }

    .hra-empty {
        padding:48px 20px;
        text-align:center;
        color:#64748b;
        font-weight:800;
    }

    .hra-pagination {
        padding:14px 18px;
        border-top:1px solid var(--line);
    }

    @media(max-width:1200px){
        .hra-kpis{grid-template-columns:repeat(2,1fr)}
        .hra-filter{grid-template-columns:repeat(2,1fr)}
        .hra-form{grid-template-columns:repeat(2,1fr)}
        .span-4{grid-column:span 2}
    }

    @media(max-width:768px){
        .hra-page{padding:16px}
        .hra-hero{flex-direction:column;align-items:flex-start}
        .hra-kpis,.hra-filter,.hra-form{grid-template-columns:1fr}
        .span-2,.span-4{grid-column:span 1}
        .hra-item{grid-template-columns:1fr}
        .hra-actions{align-items:flex-start;flex-direction:row}
    }
</style>

<div class="hra-page">
    <div class="hra-hero">
        <div>
            <h1>Thông báo nhân sự</h1>
            <p>Đăng thông báo ngày nghỉ, chương trình nội bộ, chính sách mới hoặc thông báo khẩn cho toàn công ty, phòng ban hoặc từng nhân sự.</p>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <form method="POST" action="{{ route('hr.announcements.read-all') }}">
                @csrf
                <button class="hra-btn hra-btn-white" type="submit">✓ Đọc tất cả</button>
            </form>

            @if($canManage)
                <a class="hra-btn hra-btn-white" href="{{ route('hr.announcements.index', ['scope' => 'manage']) }}">Quản lý</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="hra-alert ok">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="hra-alert err">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="hra-kpis">
        <div class="hra-kpi">
            <div class="k">Đã đăng</div>
            <div class="v">{{ number_format($summary['published'] ?? 0) }}</div>
        </div>
        <div class="hra-kpi">
            <div class="k">Chưa đọc</div>
            <div class="v" style="color:#e11d48">{{ number_format($summary['unread'] ?? 0) }}</div>
        </div>
        <div class="hra-kpi">
            <div class="k">Đang ghim</div>
            <div class="v" style="color:#2563eb">{{ number_format($summary['pinned'] ?? 0) }}</div>
        </div>
        <div class="hra-kpi">
            <div class="k">Bản nháp</div>
            <div class="v" style="color:#f59e0b">{{ number_format($summary['draft'] ?? 0) }}</div>
        </div>
    </div>

    @if($canManage)
        <details class="hra-card" {{ request('open_create') ? 'open' : '' }}>
            <summary class="hra-card-head" style="cursor:pointer">
                <div>
                    <div class="hra-card-title">+ Tạo thông báo mới</div>
                    <div class="hra-sub">Ngày nghỉ, chương trình nội bộ, chính sách, đào tạo hoặc thông báo khẩn.</div>
                </div>
                <span class="hra-pill blue">HR/Admin</span>
            </summary>

            <form method="POST" action="{{ route('hr.announcements.store') }}" enctype="multipart/form-data" class="hra-form">
                @csrf

                <div class="hra-field span-2">
                    <label>Tiêu đề</label>
                    <input class="hra-input" name="title" placeholder="VD: Thông báo nghỉ lễ 30/4 - 1/5" required>
                </div>

                <div class="hra-field">
                    <label>Loại thông báo</label>
                    <select class="hra-select" name="category" required>
                        @foreach($categoryLabels as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hra-field">
                    <label>Trạng thái</label>
                    <select class="hra-select" name="status" required>
                        <option value="published">Đăng ngay</option>
                        <option value="draft">Lưu nháp</option>
                        <option value="archived">Lưu trữ</option>
                    </select>
                </div>

                <div class="hra-field">
                    <label>Gửi cho</label>
                    <select class="hra-select" name="target_type" id="targetType">
                        <option value="all">Toàn công ty</option>
                        <option value="department">Theo phòng ban</option>
                        <option value="user">Theo nhân sự</option>
                    </select>
                </div>

                <div class="hra-field">
                    <label>Phòng ban</label>
                    <select class="hra-select" name="department_id">
                        <option value="">-- Chọn phòng ban --</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hra-field">
                    <label>Nhân sự</label>
                    <select class="hra-select" name="user_id">
                        <option value="">-- Chọn nhân sự --</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} · {{ $user->email }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="hra-field">
                    <label>Ghim thông báo</label>
                    <select class="hra-select" name="is_pinned">
                        <option value="0">Không ghim</option>
                        <option value="1">Ghim lên đầu</option>
                    </select>
                </div>

                <div class="hra-field">
                    <label>Bắt đầu hiển thị</label>
                    <input class="hra-input" name="starts_at" type="datetime-local">
                </div>

                <div class="hra-field">
                    <label>Hết hạn</label>
                    <input class="hra-input" name="ends_at" type="datetime-local">
                </div>

                <div class="hra-field span-2">
                    <label>Ảnh / file đính kèm</label>
                    <input class="hra-input" name="attachments[]" type="file" multiple>
                </div>

                <div class="hra-field span-4">
                    <label>Nội dung</label>
                    <textarea class="hra-textarea" name="body" placeholder="Nhập nội dung thông báo..." required></textarea>
                </div>

                <div class="span-4">
                    <button class="hra-btn hra-btn-primary" type="submit">Đăng thông báo</button>
                </div>
            </form>
        </details>
    @endif

    <div class="hra-card">
        <div class="hra-card-head">
            <div>
                <div class="hra-card-title">Danh sách thông báo</div>
                <div class="hra-sub">Thông báo chưa đọc sẽ được tô nền xanh nhạt và hiện badge trên topbar.</div>
            </div>

            <div class="hra-tabs">
                <a class="hra-tab {{ $scope !== 'manage' ? 'active' : '' }}" href="{{ route('hr.announcements.index') }}">Của tôi</a>
                @if($canManage)
                    <a class="hra-tab {{ $scope === 'manage' ? 'active' : '' }}" href="{{ route('hr.announcements.index', ['scope' => 'manage']) }}">Quản lý</a>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('hr.announcements.index') }}" class="hra-filter">
            <input type="hidden" name="scope" value="{{ $scope }}">

            <div class="hra-field">
                <label>Tìm kiếm</label>
                <input class="hra-input" name="q" value="{{ request('q') }}" placeholder="Tìm tiêu đề, nội dung...">
            </div>

            <div class="hra-field">
                <label>Loại</label>
                <select class="hra-select" name="category">
                    <option value="">Tất cả</option>
                    @foreach($categoryLabels as $key => $label)
                        <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            @if($canManage && $scope === 'manage')
                <div class="hra-field">
                    <label>Trạng thái</label>
                    <select class="hra-select" name="status">
                        <option value="">Tất cả</option>
                        @foreach($statusLabels as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div></div>
            @endif

            <button class="hra-btn hra-btn-primary" type="submit">Lọc</button>
            <a class="hra-btn hra-btn-light" href="{{ route('hr.announcements.index') }}">Reset</a>
        </form>

        @if($announcements->count())
            <div class="hra-list">
                @foreach($announcements as $item)
                    @php
                        $isUnread = empty($item->read_at) && $item->status === 'published';
                        $category = $item->category ?: 'general';
                        $targetText = $targetLabels[$item->target_type] ?? 'Toàn công ty';

                        if($item->target_type === 'department' && $item->department_name) {
                            $targetText = 'Phòng ban: ' . $item->department_name;
                        }

                        if($item->target_type === 'user' && $item->target_user_name) {
                            $targetText = 'Nhân sự: ' . $item->target_user_name;
                        }
                    @endphp

                    <div class="hra-item {{ $isUnread ? 'unread' : '' }}">
                        <div class="hra-icon">{{ $categoryIcons[$category] ?? '📢' }}</div>

                        <div>
                            <div class="hra-title">
                                <a href="{{ route('hr.announcements.show', $item->id) }}">{{ $item->title }}</a>
                            </div>

                            <div class="hra-body">
                                {{ \Illuminate\Support\Str::limit(strip_tags($item->body ?? ''), 220) }}
                            </div>

                            <div class="hra-meta">
                                <span class="hra-pill blue">{{ $categoryLabels[$category] ?? $category }}</span>
                                <span class="hra-pill">{{ $targetText }}</span>
                                <span class="hra-pill {{ $item->status === 'published' ? 'green' : ($item->status === 'draft' ? 'amber' : '') }}">
                                    {{ $statusLabels[$item->status] ?? $item->status }}
                                </span>

                                @if($item->is_pinned)
                                    <span class="hra-pill red">Đang ghim</span>
                                @endif

                                @if($item->created_at)
                                    <span class="hra-pill">{{ \Carbon\Carbon::parse($item->created_at)->format('d/m/Y H:i') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="hra-actions">
                            @if($isUnread)
                                <span class="hra-pill red">Chưa đọc</span>
                            @else
                                <span class="hra-pill green">Đã đọc</span>
                            @endif

                            <a class="hra-btn hra-btn-light" href="{{ route('hr.announcements.show', $item->id) }}">Xem</a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="hra-pagination">
                {{ $announcements->links() }}
            </div>
        @else
            <div class="hra-empty">
                Chưa có thông báo phù hợp.
            </div>
        @endif
    </div>
</div>
@endsection
