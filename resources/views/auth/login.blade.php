@extends('layouts.guest')

@section('title', 'Đăng nhập • EGO SOLAR CRM')

@section('content')
    <div class="ego-login-heading">
        <span class="ego-login-heading__kicker">WELCOME BACK</span>
        <h2>Chào mừng trở lại</h2>
        <p>Đăng nhập để tiếp tục vào không gian làm việc EGO Solar.</p>
    </div>

    @if ($errors->any())
        <div class="ego-auth-alert" role="alert" aria-live="polite">
            <span class="ego-auth-alert__icon">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            </span>
            <div>
                <strong>Không thể đăng nhập</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    @error('login')
        <div class="ego-auth-alert" role="alert" aria-live="polite">
            <span class="ego-auth-alert__icon">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            </span>
            <div>
                <strong>Không thể đăng nhập</strong>
                <p>{{ $message }}</p>
            </div>
        </div>
    @enderror

    <form method="POST"
          action="{{ route('login') }}"
          class="ego-login-form"
          data-ego-login-form>
        @csrf

        <div class="ego-field">
            <label for="email" class="ego-field__label">Email công việc</label>
            <div class="ego-field__control @error('email') is-invalid @enderror">
                <span class="ego-field__icon">
                    <i class="bi bi-envelope" aria-hidden="true"></i>
                </span>
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       autocomplete="username"
                       inputmode="email"
                       autocapitalize="none"
                       spellcheck="false"
                       placeholder="tenban@egosolar.vn"
                       required
                       autofocus>
            </div>
            @error('email')
                <small class="ego-field__error">{{ $message }}</small>
            @enderror
        </div>

        <div class="ego-field">
            <div class="ego-field__label-row">
                <label for="password" class="ego-field__label">Mật khẩu</label>
                <span class="ego-caps-warning" data-ego-caps-warning hidden>
                    <i class="bi bi-capslock" aria-hidden="true"></i>
                    Caps Lock đang bật
                </span>
            </div>

            <div class="ego-field__control @error('password') is-invalid @enderror">
                <span class="ego-field__icon">
                    <i class="bi bi-key" aria-hidden="true"></i>
                </span>
                <input id="password"
                       type="password"
                       name="password"
                       autocomplete="current-password"
                       placeholder="Nhập mật khẩu"
                       required
                       data-ego-password>
                <button type="button"
                        class="ego-password-toggle"
                        data-ego-password-toggle
                        aria-label="Hiện mật khẩu"
                        aria-pressed="false">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            @error('password')
                <small class="ego-field__error">{{ $message }}</small>
            @enderror
        </div>

        <div class="ego-login-options">
            <label class="ego-remember" for="remember">
                <input type="checkbox"
                       name="remember"
                       id="remember"
                       value="1"
                       @if (old('remember')) checked @endif>
                <span class="ego-remember__box" aria-hidden="true">
                    <i class="bi bi-check-lg"></i>
                </span>
                <span>Ghi nhớ đăng nhập</span>
            </label>

            <span class="ego-login-help">
                <i class="bi bi-headset" aria-hidden="true"></i>
                Liên hệ quản trị viên
            </span>
        </div>

        <button type="submit" class="ego-login-button" data-ego-submit>
            <span class="ego-login-button__idle">
                <span>Đăng nhập hệ thống</span>
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </span>
            <span class="ego-login-button__loading" aria-hidden="true">
                <span class="ego-spinner"></span>
                <span>Đang xác thực...</span>
            </span>
        </button>

        <p class="ego-login-note">
            Tài khoản được quản trị viên nội bộ cấp và quản lý theo vai trò.
        </p>
    </form>
@endsection
