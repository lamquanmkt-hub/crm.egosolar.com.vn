@extends('layouts.app')

@section('content')
@php
    $avatarUrl = null;

    if (!empty(optional($user->avatar)->file_path)) {
        $avatarUrl = asset('storage/' . ltrim($user->avatar->file_path, '/'));
    } elseif (!empty($user->avatar) && is_string($user->avatar)) {
        $avatarUrl = asset('storage/' . ltrim($user->avatar, '/'));
    }

    $initials = collect(preg_split('/\s+/', trim((string) $user->name)))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');

    $initials = mb_strtoupper($initials ?: 'U');
@endphp

<style>
    :root{
        --pf-bg:#f4f7fb;
        --pf-surface:#ffffff;
        --pf-line:#e5edf7;
        --pf-text:#0f172a;
        --pf-muted:#64748b;
        --pf-primary:#2563eb;
        --pf-primary-2:#1d4ed8;
        --pf-success:#059669;
        --pf-danger:#dc2626;
        --pf-shadow:0 20px 60px rgba(15,23,42,.08);
        --pf-shadow-soft:0 10px 24px rgba(15,23,42,.05);
    }

    .profile-modern-page{
        min-height:100vh;
        background:linear-gradient(180deg,#f8fbff 0%,#f4f7fb 100%);
    }

    .profile-hero{
        position:relative;
        overflow:hidden;
        border:none;
        border-radius:32px;
        color:#fff;
        background:
            radial-gradient(circle at top right, rgba(125,211,252,.28), transparent 24%),
            radial-gradient(circle at left bottom, rgba(37,99,235,.18), transparent 24%),
            linear-gradient(135deg,#07111f 0%,#102a56 46%,#2563eb 100%);
        box-shadow:0 28px 70px rgba(2,6,23,.18);
    }

    .profile-hero::after{
        content:"";
        position:absolute;
        width:260px;
        height:260px;
        right:-70px;
        bottom:-90px;
        border-radius:50%;
        background:rgba(255,255,255,.08);
        filter:blur(8px);
    }

    .profile-chip{
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:10px 14px;
        border-radius:999px;
        background:rgba(255,255,255,.11);
        border:1px solid rgba(255,255,255,.14);
        font-size:13px;
        font-weight:700;
    }

    .profile-shell{
        background:var(--pf-surface);
        border:1px solid var(--pf-line);
        border-radius:28px;
        box-shadow:var(--pf-shadow);
        overflow:hidden;
    }

    .profile-sidebar{
        background:linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
        border-right:1px solid var(--pf-line);
        height:100%;
    }

    .profile-avatar-wrap{
        width:118px;
        height:118px;
        margin:0 auto;
        position:relative;
    }

    .profile-avatar,
    .profile-avatar-fallback{
        width:118px;
        height:118px;
        border-radius:28px;
        object-fit:cover;
        border:3px solid #dbeafe;
        box-shadow:var(--pf-shadow-soft);
    }

    .profile-avatar-fallback{
        display:flex;
        align-items:center;
        justify-content:center;
        background:linear-gradient(135deg,#dbeafe,#bfdbfe);
        color:#0f172a;
        font-size:34px;
        font-weight:800;
    }

    .profile-badge{
        display:inline-flex;
        align-items:center;
        gap:7px;
        padding:8px 12px;
        border-radius:999px;
        border:1px solid transparent;
        font-size:12px;
        font-weight:800;
        white-space:nowrap;
    }

    .profile-badge-blue{
        background:#eff6ff;
        color:#1d4ed8;
        border-color:#dbeafe;
    }

    .profile-badge-green{
        background:#ecfdf5;
        color:#047857;
        border-color:#d1fae5;
    }

    .profile-label{
        font-size:13px;
        font-weight:800;
        color:#334155;
        margin-bottom:8px;
    }

    .profile-modern-page .form-control,
    .profile-modern-page .form-select{
        min-height:50px;
        border-radius:16px;
        border-color:#dbe4ef;
        padding-left:15px;
        padding-right:15px;
        box-shadow:none !important;
    }

    .profile-modern-page .form-control:focus,
    .profile-modern-page .form-select:focus{
        border-color:#93c5fd;
    }

    .profile-modern-page .btn{
        border-radius:14px;
        font-weight:700;
    }

    .profile-modern-page .btn-primary{
        background:linear-gradient(135deg,var(--pf-primary),var(--pf-primary-2));
        border:none;
    }

    .profile-panel-title{
        font-size:20px;
        font-weight:800;
        color:var(--pf-text);
        margin-bottom:4px;
    }

    .profile-panel-subtitle{
        color:var(--pf-muted);
        font-size:13px;
    }

    .profile-upload-box{
        border:1px dashed #bfdbfe;
        background:linear-gradient(180deg,#f8fbff 0%,#eff6ff 100%);
        border-radius:20px;
        padding:18px;
    }

    .profile-note{
        font-size:12px;
        color:var(--pf-muted);
    }

    .profile-divider{
        height:1px;
        background:linear-gradient(90deg,transparent,#dbe4ef,transparent);
        margin:24px 0;
        border:none;
    }

    @media (max-width: 991.98px){
        .profile-sidebar{
            border-right:none;
            border-bottom:1px solid var(--pf-line);
        }
        .profile-hero{
            border-radius:26px;
        }
    }
</style>

<div class="container-fluid py-4 profile-modern-page">
    <div class="card profile-hero mb-4">
        <div class="card-body p-4 p-xl-5">
            <div class="row g-4 align-items-center">
                <div class="col-xl-8">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="profile-chip"><i class="bi bi-person-circle"></i> Hồ sơ cá nhân</span>
                        <span class="profile-chip"><i class="bi bi-shield-check"></i> Cập nhật an toàn</span>
                    </div>

                    <h1 class="fw-bold mb-3" style="font-size:clamp(28px,4vw,42px);line-height:1.1;">
                        Quản lý <span style="color:#93c5fd;">thông tin cá nhân</span>,
                        đổi mật khẩu và cập nhật <span style="color:#bfdbfe;">ảnh đại diện</span> trên một màn hình hiện đại.
                    </h1>

                    <div style="max-width:760px; opacity:.92; font-size:15px;">
                        Trang này cho phép bạn cập nhật nhanh thông tin cơ bản, thay avatar và đổi mật khẩu mà không cần thao tác rườm rà.
                    </div>
                </div>

                <div class="col-xl-4 text-xl-end">
                    <div class="d-flex flex-wrap gap-2 justify-content-xl-end">
                        <a href="{{ route('users.profile') }}" class="btn btn-light px-3">
                            <i class="bi bi-arrow-left me-1"></i> Quay lại hồ sơ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4">
            <div class="fw-bold mb-2">
                <i class="bi bi-exclamation-octagon me-1"></i> Vui lòng kiểm tra lại các trường sau
            </div>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="profile-shell">
        <div class="row g-0">
            <div class="col-lg-4">
                <div class="profile-sidebar p-4 p-xl-5">
                    <div class="text-center mb-4">
                        <div class="profile-avatar-wrap mb-3">
                            @if($avatarUrl)
                                <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" class="profile-avatar">
                            @else
                                <div class="profile-avatar-fallback">{{ $initials }}</div>
                            @endif
                        </div>

                        <div class="profile-panel-title">{{ $user->name }}</div>
                        <div class="profile-panel-subtitle mb-3">{{ $user->email }}</div>

                        <div class="d-flex flex-wrap gap-2 justify-content-center">
                            <span class="profile-badge profile-badge-blue">
                                <i class="bi bi-person-badge"></i> Tài khoản cá nhân
                            </span>

                            @if((int) ($user->is_active ?? 1) === 1)
                                <span class="profile-badge profile-badge-green">
                                    <i class="bi bi-check-circle"></i> Đang hoạt động
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="profile-upload-box">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-cloud-arrow-up fs-5 text-primary"></i>
                            <div class="fw-bold">Cập nhật avatar</div>
                        </div>

                        <div class="profile-note mb-3">
                            Hỗ trợ JPG, JPEG, PNG, WEBP. Dung lượng tối đa 2MB.
                        </div>

                        <form action="{{ route('users.profile.avatar') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="mb-3">
                                <input
                                    type="file"
                                    name="avatar"
                                    class="form-control @error('avatar') is-invalid @enderror"
                                    accept=".jpg,.jpeg,.png,.webp,image/*"
                                    required
                                >
                                @error('avatar')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-upload me-1"></i> Tải avatar mới
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="p-4 p-xl-5">
                    <div class="mb-4">
                        <div class="profile-panel-title">Chỉnh sửa hồ sơ</div>
                        <div class="profile-panel-subtitle">
                            Cập nhật thông tin cơ bản và đổi mật khẩu nếu cần.
                        </div>
                    </div>

                    <form action="{{ route('users.profile-update') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="profile-label">Họ và tên</label>
                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name', $user->name) }}"
                                    placeholder="Nhập họ và tên"
                                    required
                                >
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="profile-label">Email</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    class="form-control @error('email') is-invalid @enderror"
                                    value="{{ old('email', $user->email) }}"
                                    placeholder="Nhập email"
                                    required
                                >
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr class="profile-divider">

                        <div class="mb-3">
                            <div class="profile-panel-title" style="font-size:18px;">Đổi mật khẩu</div>
                            <div class="profile-panel-subtitle">
                                Để trống nếu bạn chưa muốn thay đổi mật khẩu hiện tại.
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="password" class="profile-label">Mật khẩu mới</label>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Tối thiểu 6 ký tự"
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="profile-note mt-2">Nên dùng mật khẩu mạnh, có chữ hoa, chữ thường và số.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="password_confirmation" class="profile-label">Xác nhận mật khẩu</label>
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    class="form-control @error('password_confirmation') is-invalid @enderror"
                                    placeholder="Nhập lại mật khẩu mới"
                                >
                                @error('password_confirmation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check2-circle me-1"></i> Lưu thay đổi
                            </button>

                            <a href="{{ route('users.profile') }}" class="btn btn-light border px-4">
                                <i class="bi bi-x-circle me-1"></i> Huỷ
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection