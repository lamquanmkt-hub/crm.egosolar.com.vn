@extends('layouts.guest')
@section('title', 'Đăng ký')

@section('content')
    <h4 class="auth-title">Tạo tài khoản mới</h4>

    @if ($errors->any())
        <div class="alert alert-danger small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">Họ và tên</label>
            <div class="input-group">
                <span class="input-group-text bg-white border" style="border-radius:14px 0 0 14px;">
                    <i class="bi bi-person"></i>
                </span>
                <input id="name" type="text" name="name"
                       class="form-control" required
                       value="{{ old('name') }}"
                       style="border-radius:0 14px 14px 0;">
            </div>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <div class="input-group">
                <span class="input-group-text bg-white border" style="border-radius:14px 0 0 14px;">
                    <i class="bi bi-envelope"></i>
                </span>
                <input id="email" type="email" name="email"
                       class="form-control" required
                       value="{{ old('email') }}"
                       style="border-radius:0 14px 14px 0;">
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mật khẩu</label>
            <div class="input-group">
                <span class="input-group-text bg-white border" style="border-radius:14px 0 0 14px;">
                    <i class="bi bi-key"></i>
                </span>
                <input id="password" type="password" name="password" class="form-control" required
                       style="border-radius:0 14px 14px 0;">
            </div>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">Xác nhận mật khẩu</label>
            <div class="input-group">
                <span class="input-group-text bg-white border" style="border-radius:14px 0 0 14px;">
                    <i class="bi bi-shield-check"></i>
                </span>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       class="form-control" required
                       style="border-radius:0 14px 14px 0;">
            </div>
        </div>

        <button type="submit" class="btn btn-ego text-white w-100">
            <i class="bi bi-person-plus me-1"></i> Đăng ký
        </button>

        <div class="mt-3 text-center" style="font-weight:750; color: rgba(15,23,42,.65)">
            Đã có tài khoản?
            <a class="link-ego" href="{{ route('login') }}">Đăng nhập</a>
        </div>
    </form>
@endsection
