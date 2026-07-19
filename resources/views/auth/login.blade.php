@extends('layouts.guest')
@section('title', 'Đăng nhập')

@section('content')
    <h4 class="auth-title">Đăng nhập hệ thống</h4>

    @if ($errors->any())
        <div class="alert alert-danger small">
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @error('login')
        <div class="alert alert-danger small">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <div class="input-group">
                <span class="input-group-text bg-white border" style="border-radius:14px 0 0 14px;">
                    <i class="bi bi-envelope"></i>
                </span>
                <input id="email" type="email" name="email"
                       class="form-control" required autofocus
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

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember" style="font-weight:700; color:rgba(15,23,42,.70)">
                    Ghi nhớ
                </label>
            </div>
            {{-- nếu bạn có route quên mật khẩu thì mở dòng này --}}
            {{-- <a class="link-ego" href="{{ route('password.request') }}">Quên mật khẩu?</a> --}}
        </div>

        <button type="submit" class="btn btn-ego text-white w-100">
            <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập
        </button>

        <div class="mt-3 text-center" style="font-weight:750; color: rgba(15,23,42,.65)">
            Chưa có tài khoản?
            <a class="link-ego" href="{{ route('register') }}">Đăng ký</a>
        </div>
    </form>
@endsection
