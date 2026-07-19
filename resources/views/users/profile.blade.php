@extends('layouts.app')

@section('content')
@php
    use Illuminate\Support\Carbon;

    $user = $user ?? auth()->user();

    $name = $user->name ?? 'User';
    $email = $user->email ?? '—';

    $roles = collect();
    if ($user && method_exists($user, 'getRoleNames')) {
        $roles = $user->getRoleNames();
    } elseif (!empty($user->role)) {
        $roles = collect([$user->role]);
    }

    $roleText = $roles->count()
        ? $roles->map(fn($r) => ucfirst(str_replace('_', ' ', $r)))->implode(', ')
        : 'User';

    $initials = collect(preg_split('/\s+/u', trim($name)))
        ->filter()
        ->take(2)
        ->map(fn($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $initials = $initials ?: 'U';

    $avatarUrl = null;
    $avatarRaw = $user->avatar ?? null;

    if (is_object($avatarRaw)) {
        $filePath = $avatarRaw->file_path ?? $avatarRaw->path ?? null;
        if ($filePath) {
            $avatarUrl = str_starts_with($filePath, 'http')
                ? $filePath
                : asset('storage/' . ltrim(str_replace('public/', '', $filePath), '/'));
        }
    } elseif (is_string($avatarRaw) && $avatarRaw !== '') {
        $avatarUrl = str_starts_with($avatarRaw, 'http')
            ? $avatarRaw
            : asset('storage/' . ltrim(str_replace('public/', '', $avatarRaw), '/'));
    }

    $memberSince = !empty($user->created_at)
        ? Carbon::parse($user->created_at)->format('d/m/Y')
        : '—';

    $lastSeen = !empty($user->last_seen_at)
        ? Carbon::parse($user->last_seen_at)->diffForHumans()
        : 'Đang online';

    $emailVerified = !empty($user->email_verified_at);

    $profilePercent = 0;
    if (!empty($user->name)) $profilePercent += 25;
    if (!empty($user->email)) $profilePercent += 25;
    if ($avatarUrl) $profilePercent += 25;
    if ($roles->count()) $profilePercent += 25;
@endphp

<div class="pfx-page">
    @if(session('success'))
        <div class="alert alert-success pfx-alert shadow-sm border-0">
            <i class="bi bi-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger pfx-alert shadow-sm border-0">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger pfx-alert shadow-sm border-0">
            <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle"></i> Vui lòng kiểm tra lại:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="pfx-hero pfx-reveal" id="pfxHero">
        <div class="pfx-hero-glow"></div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 position-relative">
            <div class="d-flex align-items-center gap-3">
                <div class="pfx-avatar">
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}" alt="{{ $name }}">
                    @else
                        <span>{{ $initials }}</span>
                    @endif
                    <i class="pfx-online-dot"></i>
                </div>

                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h3 class="pfx-title mb-0">{{ $name }}</h3>
                        <span class="pfx-pill">
                            <i class="bi bi-shield-check"></i> {{ $roleText }}
                        </span>
                    </div>

                    <div class="pfx-muted mb-2">
                        <i class="bi bi-envelope"></i> {{ $email }}
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <span class="pfx-chip"><i class="bi bi-circle-fill"></i> Online</span>
                        <span class="pfx-chip"><i class="bi bi-calendar-check"></i> {{ $memberSince }}</span>
                        <span class="pfx-chip"><i class="bi bi-clock-history"></i> {{ $lastSeen }}</span>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ url('/profile/edit') }}" class="btn pfx-btn pfx-ripple">
                    <i class="bi bi-pencil-square"></i> Cập nhật hồ sơ
                </a>
                <a href="{{ url('/profile/edit') }}#password" class="btn pfx-btn-outline pfx-ripple">
                    <i class="bi bi-key"></i> Đổi mật khẩu
                </a>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-4">
            <div class="pfx-card p-3 pfx-reveal">
                <div class="text-center">
                    <div class="pfx-avatar-lg mx-auto mb-3">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $name }}">
                        @else
                            <span>{{ $initials }}</span>
                        @endif
                    </div>

                    <h4 class="pfx-subtitle mb-1">{{ $name }}</h4>
                    <div class="pfx-muted mb-2">{{ $email }}</div>

                    <span class="pfx-pill">
                        <i class="bi bi-person-badge"></i> {{ $roleText }}
                    </span>
                </div>

                <div class="pfx-sep"></div>

                <form method="POST" action="{{ route('users.profile.avatar') }}" enctype="multipart/form-data" class="pfx-upload">
                    @csrf

                    <label class="pfx-section-label mb-2">
                        <i class="bi bi-image"></i> Cập nhật avatar
                    </label>

                    <input
                        type="file"
                        name="avatar"
                        class="form-control pfx-input"
                        accept="image/jpeg,image/png,image/webp"
                        required
                    >

                    <div class="pfx-muted mt-2">JPG, PNG, WEBP. Tối đa 2MB.</div>

                    <button type="submit" class="btn pfx-btn w-100 mt-3 pfx-ripple">
                        <i class="bi bi-cloud-arrow-up"></i> Lưu avatar
                    </button>
                </form>
            </div>

            <div class="pfx-card p-3 mt-3 pfx-reveal">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <div class="pfx-section-title">Hoàn thiện hồ sơ</div>
                        <div class="pfx-muted">Mức độ đầy đủ thông tin hiện tại</div>
                    </div>
                    <div class="pfx-score" data-count="{{ $profilePercent }}">0%</div>
                </div>

                <div class="pfx-progress mb-3">
                    <div class="pfx-progress-bar" data-progress="{{ $profilePercent }}"></div>
                </div>

                <div class="pfx-checks">
                    <div class="{{ !empty($user->name) ? 'ok' : '' }}">
                        <i class="bi {{ !empty($user->name) ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Có họ tên
                    </div>
                    <div class="{{ !empty($user->email) ? 'ok' : '' }}">
                        <i class="bi {{ !empty($user->email) ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Có email
                    </div>
                    <div class="{{ $avatarUrl ? 'ok' : '' }}">
                        <i class="bi {{ $avatarUrl ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Có avatar
                    </div>
                    <div class="{{ $roles->count() ? 'ok' : '' }}">
                        <i class="bi {{ $roles->count() ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Có vai trò
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="pfx-info pfx-reveal">
                        <div class="pfx-icon"><i class="bi bi-person"></i></div>
                        <div>
                            <div class="pfx-label">Họ tên</div>
                            <div class="pfx-value">{{ $name }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="pfx-info pfx-reveal">
                        <div class="pfx-icon"><i class="bi bi-envelope"></i></div>
                        <div>
                            <div class="pfx-label">Email</div>
                            <div class="pfx-value">{{ $email }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="pfx-info pfx-reveal">
                        <div class="pfx-icon"><i class="bi bi-shield-lock"></i></div>
                        <div>
                            <div class="pfx-label">Vai trò</div>
                            <div class="pfx-value">{{ $roleText }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="pfx-info pfx-reveal">
                        <div class="pfx-icon"><i class="bi bi-calendar2-week"></i></div>
                        <div>
                            <div class="pfx-label">Ngày tham gia</div>
                            <div class="pfx-value">{{ $memberSince }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pfx-card p-3 mt-3 pfx-reveal">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="pfx-icon sm"><i class="bi bi-person-vcard"></i></div>
                    <div>
                        <h5 class="pfx-subtitle mb-0">Thông tin tài khoản</h5>
                        <div class="pfx-muted">Thông tin cơ bản và trạng thái tài khoản.</div>
                    </div>
                </div>

                <div class="pfx-grid">
                    <div class="pfx-mini">
                        <div class="pfx-label">User ID</div>
                        <div class="pfx-value">#{{ $user->id ?? '—' }}</div>
                    </div>

                    <div class="pfx-mini">
                        <div class="pfx-label">Trạng thái</div>
                        <div class="pfx-value text-success">
                            <i class="bi bi-circle-fill"></i> Đang hoạt động
                        </div>
                    </div>

                    <div class="pfx-mini">
                        <div class="pfx-label">Xác thực email</div>
                        <div class="pfx-value {{ $emailVerified ? 'text-success' : 'text-warning' }}">
                            <i class="bi {{ $emailVerified ? 'bi-check-circle-fill' : 'bi-exclamation-circle' }}"></i>
                            {{ $emailVerified ? 'Đã xác thực' : 'Chưa xác thực' }}
                        </div>
                    </div>

                    <div class="pfx-mini">
                        <div class="pfx-label">Hoạt động gần nhất</div>
                        <div class="pfx-value">{{ $lastSeen }}</div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mt-0">
                <div class="col-md-6">
                    <div class="pfx-card p-3 h-100 pfx-reveal">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="pfx-icon sm"><i class="bi bi-lock"></i></div>
                            <div>
                                <h5 class="pfx-subtitle mb-0">Bảo mật</h5>
                                <div class="pfx-muted">Quản lý mật khẩu tài khoản.</div>
                            </div>
                        </div>

                        <div class="pfx-mini mb-3">
                            <div class="pfx-value">Mật khẩu</div>
                            <div class="pfx-muted">Nên đổi định kỳ để tăng bảo mật.</div>
                        </div>

                        <a href="{{ url('/profile/edit') }}#password" class="btn pfx-btn-outline w-100 pfx-ripple">
                            <i class="bi bi-key"></i> Đổi mật khẩu
                        </a>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="pfx-card p-3 h-100 pfx-reveal">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="pfx-icon sm"><i class="bi bi-lightning-charge"></i></div>
                            <div>
                                <h5 class="pfx-subtitle mb-0">Thao tác nhanh</h5>
                                <div class="pfx-muted">Các hành động thường dùng.</div>
                            </div>
                        </div>

                        <div class="pfx-actions">
                            <a href="{{ url('/profile/edit') }}"><i class="bi bi-pencil-square"></i> Sửa thông tin cá nhân</a>
                            <a href="{{ url('/') }}"><i class="bi bi-speedometer2"></i> Về dashboard</a>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><i class="bi bi-box-arrow-right"></i> Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pfx-card p-3 mt-3 pfx-reveal">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="pfx-icon sm"><i class="bi bi-shield-check"></i></div>
                    <div>
                        <h5 class="pfx-subtitle mb-0">Quyền truy cập</h5>
                        <div class="pfx-muted">Vai trò hiện tại trong hệ thống.</div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @forelse($roles as $r)
                        <span class="pfx-pill">
                            <i class="bi bi-check2-circle"></i> {{ ucfirst(str_replace('_', ' ', $r)) }}
                        </span>
                    @empty
                        <span class="pfx-chip">
                            <i class="bi bi-info-circle"></i> Chưa có vai trò cụ thể
                        </span>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .pfx-page{
        --pfx-primary:#28d7d1;
        --pfx-primary-2:#59e3da;
        --pfx-primary-3:#8feef0;
        --pfx-accent:#7dd3fc;
        --pfx-bg:#f3fffe;
        --pfx-card:#ffffff;
        --pfx-line:#d7f5f3;
        --pfx-text:#11354a;
        --pfx-muted:#5a7284;
        min-height:calc(100vh - 64px);
        padding:18px 24px 28px;
        background:
            radial-gradient(circle at top left, rgba(40,215,209,.14), transparent 28%),
            radial-gradient(circle at right top, rgba(125,211,252,.10), transparent 26%),
            linear-gradient(180deg, var(--pfx-bg) 0%, #ffffff 72%);
        font-size:14px;
    }

    .pfx-page *{
        font-family: inherit;
    }

    .pfx-alert{
        border-radius:16px;
    }

    .pfx-title{
        font-size:20px;
        font-weight:800;
        color:var(--pfx-text);
        letter-spacing:-.2px;
    }

    .pfx-subtitle{
        font-size:16px;
        font-weight:800;
        color:var(--pfx-text);
        letter-spacing:-.2px;
    }

    .pfx-muted{
        color:var(--pfx-muted);
        font-size:12.5px;
        line-height:1.45;
    }

    .pfx-label{
        color:var(--pfx-muted);
        font-size:12px;
        font-weight:700;
        margin-bottom:2px;
    }

    .pfx-value{
        color:var(--pfx-text);
        font-size:14px;
        font-weight:800;
        line-height:1.4;
    }

    .pfx-hero,
    .pfx-card,
    .pfx-info{
        position:relative;
        overflow:hidden;
        border:1px solid var(--pfx-line);
        background:rgba(255,255,255,.86);
        box-shadow:
            0 10px 32px rgba(17,53,74,.07),
            inset 0 1px 0 rgba(255,255,255,.75);
        backdrop-filter:blur(10px);
    }

    .pfx-hero{
        border-radius:24px;
        padding:20px;
        background:
            linear-gradient(135deg, rgba(255,255,255,.92), rgba(236,255,255,.82)),
            radial-gradient(circle at 0% 0%, rgba(40,215,209,.18), transparent 35%);
        transition:transform .18s ease, box-shadow .18s ease;
        transform-style:preserve-3d;
    }

    .pfx-hero-glow{
        position:absolute;
        inset:auto auto -40px -20px;
        width:220px;
        height:220px;
        border-radius:999px;
        background:radial-gradient(circle, rgba(40,215,209,.22) 0%, rgba(40,215,209,0) 70%);
        pointer-events:none;
    }

    .pfx-card{
        border-radius:22px;
        transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }

    .pfx-card:hover,
    .pfx-info:hover{
        transform:translateY(-3px);
        box-shadow:0 16px 36px rgba(17,53,74,.10);
        border-color:#c1efee;
    }

    .pfx-info{
        border-radius:18px;
        padding:16px;
        display:flex;
        align-items:center;
        gap:12px;
        min-height:88px;
        transition:transform .22s ease, box-shadow .22s ease, border-color .22s ease;
    }

    .pfx-icon{
        width:40px;
        height:40px;
        border-radius:14px;
        display:flex;
        align-items:center;
        justify-content:center;
        flex:0 0 auto;
        color:#1598a7;
        border:1px solid #d0f5f3;
        background:
            linear-gradient(135deg, rgba(40,215,209,.12), rgba(125,211,252,.10));
        box-shadow:inset 0 1px 0 rgba(255,255,255,.8);
    }

    .pfx-icon.sm{
        width:38px;
        height:38px;
        border-radius:13px;
    }

    .pfx-avatar,
    .pfx-avatar-lg{
        position:relative;
        flex:0 0 auto;
        padding:3px;
        background:linear-gradient(135deg, var(--pfx-primary), var(--pfx-accent));
        border-radius:999px;
        box-shadow:0 10px 24px rgba(40,215,209,.24);
    }

    .pfx-avatar{
        width:78px;
        height:78px;
    }

    .pfx-avatar-lg{
        width:112px;
        height:112px;
    }

    .pfx-avatar img,
    .pfx-avatar-lg img{
        width:100%;
        height:100%;
        object-fit:cover;
        border-radius:999px;
        display:block;
        background:#fff;
    }

    .pfx-avatar span,
    .pfx-avatar-lg span{
        width:100%;
        height:100%;
        display:flex;
        align-items:center;
        justify-content:center;
        border-radius:999px;
        background:linear-gradient(135deg, #ffffff, #f0ffff);
        color:#1598a7;
        font-weight:900;
    }

    .pfx-avatar span{font-size:26px;}
    .pfx-avatar-lg span{font-size:38px;}

    .pfx-online-dot{
        position:absolute;
        right:4px;
        bottom:4px;
        width:15px;
        height:15px;
        border-radius:999px;
        background:#22c55e;
        border:3px solid #fff;
        box-shadow:0 0 0 4px rgba(34,197,94,.12);
    }

    .pfx-pill,
    .pfx-chip{
        display:inline-flex;
        align-items:center;
        gap:6px;
        border-radius:999px;
        padding:7px 11px;
        font-size:12px;
        line-height:1;
        font-weight:750;
    }

    .pfx-pill{
        background:linear-gradient(135deg, rgba(40,215,209,.14), rgba(125,211,252,.10));
        color:#1598a7;
        border:1px solid #c9f0ef;
    }

    .pfx-chip{
        background:rgba(255,255,255,.92);
        color:#4f6c7c;
        border:1px solid #e4f5f5;
    }

    .pfx-btn,
    .pfx-btn-outline{
        position:relative;
        overflow:hidden;
        border-radius:14px;
        font-size:13px;
        font-weight:800;
        padding:10px 14px;
        transition:all .2s ease;
    }

    .pfx-btn{
        border:0;
        color:#fff;
        background:linear-gradient(135deg, #26d1cf, #49dede);
        box-shadow:0 12px 24px rgba(38,209,207,.25);
    }

    .pfx-btn:hover{
        color:#fff;
        transform:translateY(-1px);
        box-shadow:0 16px 28px rgba(38,209,207,.28);
    }

    .pfx-btn-outline{
        color:#1598a7;
        border:1px solid #beeceb;
        background:rgba(255,255,255,.88);
    }

    .pfx-btn-outline:hover{
        color:#1598a7;
        background:#eefdfd;
        transform:translateY(-1px);
    }

    .pfx-upload{
        padding:14px;
        border-radius:18px;
        border:1px dashed #bfeeed;
        background:
            linear-gradient(135deg, rgba(40,215,209,.06), rgba(125,211,252,.04));
    }

    .pfx-section-label,
    .pfx-section-title{
        color:var(--pfx-text);
        font-size:14px;
        font-weight:800;
    }

    .pfx-input{
        border-radius:13px;
        border-color:#d9eeee;
        font-size:13px;
        background:#fff;
    }

    .pfx-input:focus{
        border-color:#8cebea;
        box-shadow:0 0 0 .18rem rgba(40,215,209,.12);
    }

    .pfx-sep{
        height:1px;
        margin:18px 0;
        background:linear-gradient(90deg, transparent, #ddf5f4, transparent);
    }

    .pfx-score{
        width:56px;
        height:56px;
        border-radius:18px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:15px;
        font-weight:900;
        color:#1598a7;
        background:linear-gradient(135deg, rgba(40,215,209,.13), rgba(125,211,252,.10));
        border:1px solid #c9f0ef;
        box-shadow:inset 0 1px 0 rgba(255,255,255,.75);
    }

    .pfx-progress{
        height:11px;
        border-radius:999px;
        background:#edf8f8;
        overflow:hidden;
        border:1px solid #e3f4f4;
    }

    .pfx-progress-bar{
        height:100%;
        width:0;
        border-radius:999px;
        background:linear-gradient(90deg, #28d7d1, #59e3da, #7dd3fc);
        box-shadow:0 4px 12px rgba(40,215,209,.25);
        transition:width 1.2s cubic-bezier(.2,.8,.2,1);
    }

    .pfx-checks{
        display:grid;
        gap:8px;
        color:#62808d;
        font-weight:700;
        font-size:13px;
    }

    .pfx-checks .ok{
        color:#1598a7;
    }

    .pfx-grid{
        display:grid;
        grid-template-columns:repeat(2,minmax(0,1fr));
        gap:12px;
    }

    .pfx-mini{
        border-radius:17px;
        padding:14px;
        border:1px solid #e7f5f5;
        background:
            linear-gradient(180deg, rgba(255,255,255,.9), rgba(250,255,255,.92));
    }

    .pfx-actions{
        display:grid;
        gap:10px;
    }

    .pfx-actions a,
    .pfx-actions button{
        width:100%;
        display:flex;
        align-items:center;
        gap:10px;
        text-align:left;
        border-radius:15px;
        padding:12px 13px;
        border:1px solid #e4f5f5;
        background:#fff;
        color:var(--pfx-text);
        font-size:13px;
        font-weight:800;
        text-decoration:none;
        transition:all .2s ease;
    }

    .pfx-actions a:hover,
    .pfx-actions button:hover{
        color:#1598a7;
        background:#f0fefe;
        border-color:#c9f0ef;
        transform:translateX(2px);
    }

    .pfx-reveal{
        opacity:0;
        transform:translateY(18px);
        transition:opacity .55s ease, transform .55s ease;
    }

    .pfx-reveal.show{
        opacity:1;
        transform:none;
    }

    .pfx-ripple .pfx-ripple-dot{
        position:absolute;
        border-radius:999px;
        transform:scale(0);
        background:rgba(255,255,255,.45);
        animation:pfxRipple .65s linear;
        pointer-events:none;
    }

    @keyframes pfxRipple{
        to{
            transform:scale(4);
            opacity:0;
        }
    }

    @media(max-width:1199.98px){
        .pfx-page{
            padding:16px;
        }
    }

    @media(max-width:991.98px){
        .pfx-grid{
            grid-template-columns:1fr;
        }
    }

    @media(max-width:767.98px){
        .pfx-title{font-size:18px;}
        .pfx-page{padding:14px;}
        .pfx-hero{padding:16px;}
        .pfx-avatar{width:68px;height:68px;}
        .pfx-avatar-lg{width:98px;height:98px;}
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const revealItems = document.querySelectorAll('.pfx-reveal');
    revealItems.forEach((item, index) => {
        setTimeout(() => item.classList.add('show'), 80 * (index + 1));
    });

    const progressBar = document.querySelector('.pfx-progress-bar');
    if (progressBar) {
        const progress = parseInt(progressBar.getAttribute('data-progress') || '0', 10);
        setTimeout(() => {
            progressBar.style.width = progress + '%';
        }, 300);
    }

    const score = document.querySelector('.pfx-score');
    if (score) {
        const target = parseInt(score.getAttribute('data-count') || '0', 10);
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 30));
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            score.textContent = current + '%';
        }, 28);
    }

    const hero = document.getElementById('pfxHero');
    if (hero && window.innerWidth > 991) {
        hero.addEventListener('mousemove', function (e) {
            const rect = hero.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const rotateY = ((x / rect.width) - 0.5) * 3.2;
            const rotateX = ((y / rect.height) - 0.5) * -2.8;
            hero.style.transform = 'perspective(1000px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg)';
        });

        hero.addEventListener('mouseleave', function () {
            hero.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
        });
    }

    document.querySelectorAll('.pfx-ripple').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const rect = btn.getBoundingClientRect();
            const dot = document.createElement('span');
            const size = Math.max(rect.width, rect.height);
            dot.className = 'pfx-ripple-dot';
            dot.style.width = size + 'px';
            dot.style.height = size + 'px';
            dot.style.left = (e.clientX - rect.left - size / 2) + 'px';
            dot.style.top = (e.clientY - rect.top - size / 2) + 'px';
            btn.appendChild(dot);
            setTimeout(() => dot.remove(), 700);
        });
    });
});
</script>

<!-- EGO_CUSTOMER_MODAL_STYLE_START -->
<style id="ego-customer-modal-style">
.ego-customer-modal{
    border:0 !important;
    border-radius:22px !important;
    overflow:hidden !important;
    box-shadow:0 30px 80px rgba(15,23,42,.28) !important;
    background:#f8fafc !important;
}

.ego-customer-modal .modal-header{
    background:linear-gradient(135deg,#0ea5e9 0%, #2563eb 100%) !important;
    color:#fff !important;
    border-bottom:0 !important;
    padding:18px 24px !important;
}

.ego-customer-modal .modal-title,
.ego-customer-modal h5,
.ego-customer-modal h4{
    color:#fff !important;
    font-weight:800 !important;
    font-size:30px !important;
    margin:0 !important;
}

.ego-customer-modal .btn-close,
.ego-customer-modal .close{
    filter:brightness(0) invert(1) !important;
    opacity:1 !important;
}

.ego-customer-modal .modal-body{
    background:#f8fafc !important;
    padding:18px !important;
    max-height:78vh !important;
    overflow-y:auto !important;
}

.ego-customer-modal .ego-customer-panel{
    background:#fff !important;
    border:1px solid #e2e8f0 !important;
    border-radius:18px !important;
    padding:16px 16px 12px !important;
    margin-bottom:16px !important;
    box-shadow:0 10px 26px rgba(15,23,42,.05) !important;
}

.ego-customer-modal .ego-customer-panel-title{
    display:flex !important;
    align-items:center !important;
    gap:8px !important;
    font-size:20px !important;
    font-weight:800 !important;
    color:#0f172a !important;
    margin-bottom:14px !important;
    padding-bottom:10px !important;
    border-bottom:1px solid #eef2f7 !important;
}

.ego-customer-modal label,
.ego-customer-modal .form-label{
    font-size:14px !important;
    font-weight:700 !important;
    color:#334155 !important;
    margin-bottom:8px !important;
}

.ego-customer-modal .form-control,
.ego-customer-modal .form-select,
.ego-customer-modal input,
.ego-customer-modal select,
.ego-customer-modal textarea{
    border-radius:14px !important;
    border:1px solid #dbe4ee !important;
    background:#fff !important;
    box-shadow:none !important;
    min-height:44px !important;
    padding:10px 14px !important;
    font-size:14px !important;
    color:#0f172a !important;
}

.ego-customer-modal textarea{
    min-height:88px !important;
    resize:vertical !important;
}

.ego-customer-modal .form-control:focus,
.ego-customer-modal .form-select:focus,
.ego-customer-modal input:focus,
.ego-customer-modal select:focus,
.ego-customer-modal textarea:focus{
    border-color:#38bdf8 !important;
    box-shadow:0 0 0 4px rgba(56,189,248,.14) !important;
    outline:none !important;
}

.ego-customer-modal ::placeholder{
    color:#94a3b8 !important;
}

.ego-customer-modal .text-muted,
.ego-customer-modal small,
.ego-customer-modal .form-text{
    color:#64748b !important;
    font-size:12px !important;
}

.ego-customer-modal .modal-footer{
    background:#fff !important;
    border-top:1px solid #e2e8f0 !important;
    padding:14px 18px !important;
}

.ego-customer-modal .btn{
    border-radius:14px !important;
    min-height:42px !important;
    padding:10px 16px !important;
    font-weight:700 !important;
}

.ego-customer-modal .btn-primary,
.ego-customer-modal .btn-success{
    background:linear-gradient(135deg,#06b6d4 0%, #2563eb 100%) !important;
    border:0 !important;
    box-shadow:0 12px 28px rgba(37,99,235,.22) !important;
}

.ego-customer-modal .btn-secondary,
.ego-customer-modal .btn-light{
    background:#f8fafc !important;
    border:1px solid #dbe4ee !important;
    color:#0f172a !important;
}
</style>

<script id="ego-customer-modal-style-js">
(function(){
    function beautifyCustomerModal(){
        document.querySelectorAll('.modal').forEach(function(modal){
            const text = (modal.innerText || '').trim();

            if(
                text.includes('Thêm mới khách hàng') ||
                text.includes('Thông tin cơ bản')
            ){
                const content = modal.querySelector('.modal-content');
                if(content) content.classList.add('ego-customer-modal');

                const body = modal.querySelector('.modal-body');
                if(body){
                    body.querySelectorAll(':scope > div').forEach(function(el){
                        if(el.querySelector('input, select, textarea')){
                            el.classList.add('ego-customer-panel');
                        }
                    });

                    body.querySelectorAll('.ego-customer-panel').forEach(function(panel){
                        const firstHeading = panel.querySelector('h1,h2,h3,h4,h5,h6,.fw-bold,strong,legend');
                        if(firstHeading && !firstHeading.classList.contains('ego-customer-panel-title')){
                            firstHeading.classList.add('ego-customer-panel-title');
                        }
                    });
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', beautifyCustomerModal);
    beautifyCustomerModal();

    const obs = new MutationObserver(function(){
        beautifyCustomerModal();
    });

    obs.observe(document.body, {childList:true, subtree:true});
})();
</script>
<!-- EGO_CUSTOMER_MODAL_STYLE_END -->

@endsection
